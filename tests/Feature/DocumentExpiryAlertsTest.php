<?php

namespace Tests\Feature;

use App\Enums\EmployeeDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Cron\CronRegistry;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * تنبيهُ انتهاء وثائق الموظفين.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كانت المهمّة معلَنةً ولا وجود لها: مسجَّلةٌ في `CronRegistry::planned()`
 * ومبذورةٌ في `cron_jobs` معطَّلةً موسومةً «(غير مطبَّقة بعد)» — لأنّ لا
 * بيانات لها أصلاً. فلمّا وُجد `employee_documents` كُتب أمرُها.
 *
 * ويُقاس هنا ثلاثةُ أشياء لا واحد:
 *
 *   ١. **أنّ المهمّة صارت متوفّرة** — فينزع `CronJobsSeeder` وسمَها
 *      ويفعّل صفَّها تلقائياً. ولولا انتقالُها من `planned()` إلى
 *      `commands()` لبقيت معطَّلةً وإن كُتب أمرُها.
 *   ٢. **أنّ خياراتها مسجَّلة** — القائمةُ البيضاء في `CronRegistry` ترفض
 *      كلَّ خيارٍ غير مذكور قبل أن يبلغ `Artisan::call`.
 *   ٣. **سلوكُ التنبيه** — ومنه أنّه لا يتكرّر كلَّ يوم، وأنّ ما انتهى
 *      يُنبَّه عليه، وأنّ ما لا ينتهي لا يدخل.
 */
class DocumentExpiryAlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->string('employee_code', 64)->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('type', 40)->default('other');
            $t->string('title')->nullable();
            $t->string('number', 100)->nullable();
            $t->date('issue_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('issuing_authority')->nullable();
            $t->string('file_path')->nullable();
            $t->string('original_name')->nullable();
            $t->string('mime_type', 100)->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('last_alert_sent_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->tinyInteger('read')->default(0);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('receiver_id')->nullable();
            $t->string('type')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->integer('contract_end_notice_period')->nullable();
            $t->integer('document_expiry_notice_period')->nullable();
            $t->timestamps();
        });
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'employee_code' => 'EMP-000001',
            'status' => 1,
        ]);
    }

    private function document(Employee $employee, $expiry, string $type = 'residency'): EmployeeDocument
    {
        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'expiry_date' => $expiry,
        ]);
    }

    private function runAlerts(array $options = []): void
    {
        $this->artisan('hr:document-expiry-alerts', $options);
    }

    private function alerts(): int
    {
        return DB::table('notifications')->where('type', 'like', 'employee_document_expiry%')->count();
    }

    // ═══════════════ السجلّ ═══════════════

    /**
     * المهمّةُ متوفّرةٌ الآن — وهذا ما ينزع الوسمَ ويفعّل الصفّ.
     *
     * ولو تُرك تعريفُها في `planned()` لبقيت معطَّلةً وإن كُتب أمرُها،
     * ورفض `CronJobRunner` تشغيلَها.
     */
    public function test_the_job_is_no_longer_declared_as_unimplemented(): void
    {
        $this->assertTrue(CronRegistry::isAvailable('hr:document-expiry-alerts'));
        $this->assertNotContains('hr:document-expiry-alerts', CronRegistry::plannedHandlers());
    }

    /** وخياراتُها مسجَّلةٌ — وما ليس في القائمة يُرفض قبل التنفيذ. */
    public function test_its_options_are_whitelisted(): void
    {
        $options = CronRegistry::find('hr:document-expiry-alerts')['options'] ?? [];

        $this->assertContains('days', $options);
        $this->assertContains('dry-run', $options);
    }

    // ═══════════════ الاختيار ═══════════════

    public function test_it_alerts_on_documents_inside_the_window_and_on_expired_ones(): void
    {
        $employee = $this->employee();

        $this->document($employee, now()->addDays(10));   // داخل النافذة
        $this->document($employee, now()->subDays(5), 'passport'); // انتهت

        $this->runAlerts();

        $this->assertSame(2, $this->alerts(), 'يُنبَّه على ما اقترب وما فات');
    }

    public function test_it_ignores_documents_far_away_or_without_an_expiry(): void
    {
        $employee = $this->employee();

        $this->document($employee, now()->addDays(200), 'driving_license');
        $this->document($employee, null, 'certificate');

        $this->runAlerts();

        $this->assertSame(0, $this->alerts());
    }

    // ═══════════════ التكرار ═══════════════

    /** التذكيرُ أسبوعيّ: مهمّةٌ يومية تُنبّه ثلاثين مرّة فتُتجاهَل. */
    public function test_it_does_not_repeat_the_same_alert_the_next_day(): void
    {
        $employee = $this->employee();
        $this->document($employee, now()->addDays(10));

        $this->runAlerts();
        $first = $this->alerts();

        $this->runAlerts();

        $this->assertSame($first, $this->alerts(), 'كُرِّر التنبيه في اليوم نفسه');
    }

    /** ويعود بعد أسبوع، فالوثيقةُ ما زالت تنتهي. */
    public function test_it_reminds_again_after_a_week(): void
    {
        $employee = $this->employee();
        $document = $this->document($employee, now()->addDays(10));

        $this->runAlerts();
        $first = $this->alerts();

        $document->forceFill(['last_alert_sent_at' => now()->subDays(8)])->save();

        $this->runAlerts();

        $this->assertGreaterThan($first, $this->alerts());
    }

    // ═══════════════ الخيارات ═══════════════

    /** والمعاينةُ الجافّة لا تُرسل ولا تختم. */
    public function test_a_dry_run_sends_nothing_and_stamps_nothing(): void
    {
        $employee = $this->employee();
        $document = $this->document($employee, now()->addDays(10));

        $this->runAlerts(['--dry-run' => true]);

        $this->assertSame(0, $this->alerts());
        $this->assertNull($document->fresh()->last_alert_sent_at);
    }

    public function test_the_days_option_overrides_the_configured_window(): void
    {
        $employee = $this->employee();
        $this->document($employee, now()->addDays(90));

        $this->runAlerts();
        $this->assertSame(0, $this->alerts(), 'خارج النافذة الافتراضية');

        $this->runAlerts(['--days' => 120]);
        $this->assertSame(1, $this->alerts(), 'الخيار يوسّع النافذة');
    }

    /** والإعداداتُ تعلو على الافتراضي حين تُضبط. */
    public function test_the_configured_notice_period_is_honoured(): void
    {
        DB::table('payroll_settings')->insert([
            'document_expiry_notice_period' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PayrollSettings::forget();

        $employee = $this->employee();
        $this->document($employee, now()->addDays(60));

        $this->runAlerts();

        $this->assertSame(1, $this->alerts(), 'لم تُقرأ المدّة من الإعدادات');
    }

    /** ووثيقةٌ لموظفٍ محذوف لا تُنبّه أحداً ولا تُسقط المهمّة. */
    public function test_a_document_without_an_owner_is_skipped(): void
    {
        $employee = $this->employee();
        $document = $this->document($employee, now()->addDays(5));

        DB::table('employees')->where('id', $employee->id)->delete();

        $this->runAlerts();

        $this->assertSame(0, $this->alerts());
        $this->assertNull($document->fresh()->last_alert_sent_at);
    }

    public function test_the_alert_names_the_document_and_the_employee(): void
    {
        $employee = $this->employee();
        $this->document($employee, now()->addDays(3));

        $this->runAlerts();

        $description = (string) DB::table('notifications')
            ->where('type', 'employee_document_expiry_admin')
            ->value('description');

        $this->assertStringContainsString(EmployeeDocumentType::RESIDENCY->label(), $description);
        $this->assertStringContainsString('سالم', $description);
        $this->assertStringContainsString('EMP-000001', $description);
    }
}
