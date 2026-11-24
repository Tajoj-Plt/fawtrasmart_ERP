<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Services\Cron\CronRegistry;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * تنبيهُ انتهاء عقود الموظفين — بعد إصلاح عللِه الأربع.
 *
 * **ما كان معطوباً**
 *
 * ١. `$employee->last_name` و`$employee->employee_number` — **حقلان لا
 *    وجود لهما في `employees`**. فتخرج الرسالة «عقد الموظف سالم  (رقم: )»
 *    باسمٍ ناقصٍ ورقمٍ فارغ.
 *
 * ٢. `now()->diffInDays($end)` تُرجع كسراً (`30.000000000069445`)، فتُطبع
 *    الرسالة «(بعد 30.000000000069 يوم)».
 *
 * ٣. `whereDate('end_date', $targetDate)` تطابق **اليوم الموافق تماماً** —
 *    ففواتُ `schedule:run` يوماً واحداً يُسقط التنبيه عن ذلك العقد
 *    نهائياً. وعقدٌ **انتهى فعلاً** لا يُنبَّه عليه أبداً لأن يومه مضى.
 *
 * ٤. ولا كابحَ مع المدى، فيُنبَّه على العقد نفسِه كلَّ يوم.
 */
class ContractExpiryAlertsTest extends TestCase
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
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('code');
            $t->date('end_date')->nullable();
            $t->string('attachments')->nullable();
            $t->timestamp('last_alert_sent_at')->nullable();
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
            $t->timestamps();
        });
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'middle_name' => 'أحمد',
            'nickname' => 'العتيبي',
            'email' => 'e'.uniqid().'@example.test',
            'employee_code' => 'EMP-000007',
        ]);
    }

    private function contract(Employee $employee, $endDate): Contract
    {
        return Contract::create([
            'employee_id' => $employee->id,
            'code' => 'C'.uniqid(),
            'end_date' => $endDate,
        ]);
    }

    private function runAlerts(array $options = []): void
    {
        $this->artisan('contracts:check-expirations', $options);
    }

    private function adminMessages(): array
    {
        return DB::table('notifications')
            ->where('type', 'contract_expiration_admin')
            ->pluck('description')
            ->all();
    }

    // ═══════════════ ١ + ٢: نصُّ الرسالة ═══════════════

    /** الاسمُ كاملٌ ورقمُ الموظف حاضر — وكانا فارغَين. */
    public function test_the_message_names_the_employee_and_the_code(): void
    {
        $this->contract($this->employee(), now()->addDays(20));

        $this->runAlerts();

        $message = $this->adminMessages()[0] ?? '';

        $this->assertStringContainsString('سالم أحمد العتيبي', $message);
        $this->assertStringContainsString('EMP-000007', $message);
        $this->assertStringNotContainsString('(رقم: )', $message, 'بقي الرقمُ فارغاً');
    }

    /** والأيامُ عددٌ صحيح لا كسرٌ من فرق الساعات. */
    public function test_the_message_shows_whole_days(): void
    {
        $this->contract($this->employee(), now()->addDays(20));

        $this->runAlerts();

        $message = $this->adminMessages()[0] ?? '';

        $this->assertStringContainsString('20 يوماً', $message);
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d{3,}/', $message, 'ظهر كسرٌ في عدد الأيام');
    }

    // ═══════════════ ٣: المدى لا اليوم ═══════════════

    /**
     * عقدٌ يقع داخل النافذة ولا يوافق يومَها بالضبط يُنبَّه عليه.
     *
     * وبالمطابقةِ القديمة كان يمرّ بلا تنبيهٍ إلا في يومٍ واحدٍ بعينه.
     */
    public function test_a_contract_anywhere_inside_the_window_is_alerted(): void
    {
        $this->contract($this->employee(), now()->addDays(7));

        $this->runAlerts();

        $this->assertCount(1, $this->adminMessages());
    }

    /** وعقدٌ انتهى فعلاً — وهو أَولى — لم يكن يُنبَّه عليه أبداً. */
    public function test_an_already_expired_contract_is_alerted(): void
    {
        $this->contract($this->employee(), now()->subDays(4));

        $this->runAlerts();

        $this->assertStringContainsString('انتهت منذ 4 يوماً', $this->adminMessages()[0] ?? '');
    }

    public function test_a_contract_beyond_the_window_is_ignored(): void
    {
        $this->contract($this->employee(), now()->addDays(300));

        $this->runAlerts();

        $this->assertSame([], $this->adminMessages());
    }

    /** وعقدٌ بلا تاريخ انتهاءٍ لا يُنبَّه عليه. */
    public function test_an_open_ended_contract_is_ignored(): void
    {
        $this->contract($this->employee(), null);

        $this->runAlerts();

        $this->assertSame([], $this->adminMessages());
    }

    // ═══════════════ ٤: الكبح ═══════════════

    public function test_it_does_not_repeat_the_alert_the_next_day(): void
    {
        $this->contract($this->employee(), now()->addDays(20));

        $this->runAlerts();
        $first = count($this->adminMessages());

        $this->runAlerts();

        $this->assertCount($first, $this->adminMessages(), 'كُرِّر التنبيه');
    }

    public function test_it_reminds_again_after_a_week(): void
    {
        $contract = $this->contract($this->employee(), now()->addDays(20));

        $this->runAlerts();
        $first = count($this->adminMessages());

        $contract->forceFill(['last_alert_sent_at' => now()->subDays(8)])->save();

        $this->runAlerts();

        $this->assertGreaterThan($first, count($this->adminMessages()));
    }

    // ═══════════════ الخيارات ═══════════════

    public function test_a_dry_run_sends_nothing_and_stamps_nothing(): void
    {
        $contract = $this->contract($this->employee(), now()->addDays(20));

        $this->runAlerts(['--dry-run' => true]);

        $this->assertSame([], $this->adminMessages());
        $this->assertNull($contract->fresh()->last_alert_sent_at);
    }

    public function test_the_days_option_widens_the_window(): void
    {
        $this->contract($this->employee(), now()->addDays(100));

        $this->runAlerts();
        $this->assertSame([], $this->adminMessages());

        $this->runAlerts(['--days' => 200]);
        $this->assertCount(1, $this->adminMessages());
    }

    public function test_the_configured_notice_period_is_honoured(): void
    {
        DB::table('payroll_settings')->insert([
            'contract_end_notice_period' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PayrollSettings::forget();

        $this->contract($this->employee(), now()->addDays(90));

        $this->runAlerts();

        $this->assertCount(1, $this->adminMessages());
    }

    /** وخيارا الأمر مسجَّلان — وما ليس في القائمة البيضاء يُرفض. */
    public function test_the_options_are_whitelisted(): void
    {
        $options = CronRegistry::find('contracts:check-expirations')['options'] ?? [];

        $this->assertContains('days', $options);
        $this->assertContains('dry-run', $options);
    }
}
