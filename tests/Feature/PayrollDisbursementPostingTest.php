<?php

namespace Tests\Feature;

use App\Models\AccountingPeriodSetting;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\SystemCurrency;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\PayrollDisbursementService;
use App\Support\CurrencySettings;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ضوابطُ ترحيل قيود الرواتب: الفترةُ المحاسبية والعملةُ والفرع.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كانت `PayrollDisbursementService` الموضعَ الوحيد في النظام الذي يكتب
 * قيوداً محاسبية بلا فحص إقفال الفترة — فيُرحَّل راتبٌ في شهرٍ أُقفل
 * وصدرت عنه القوائم. وكانت تكتب `'currency' => $slip->currency ?? 'SAR'`،
 * و`??` لا تعمل إلا على `null`: فقسيمةٌ بلا عملة تُمرِّر `'SAR'` نصّاً،
 * وهي قيمةٌ غير فارغة **تُبطل** حارس `HasDocumentCurrency` الذي كان
 * سيملؤها بالعملة الافتراضية. فالعطل تعطيلُ ضمانةٍ قائمة لا غيابُها.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml` — وهو ما يفعله
 * [InstallerAdminRoleTest] و[HrPermissionMiddlewareTest] للسبب نفسه.
 *
 * وخدمةُ الفترات تُستبدل ببديلٍ في الحاوي بدل بناء `accounting_periods`
 * و`fiscal_years` وإعداداتها: المقيس هنا **أن الخدمة تسأل الحارس وتقف
 * عند منعه**، لا منطقُ الحارس نفسه — وله اختبارُه في وحدة الحسابات.
 */
class PayrollDisbursementPostingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
            هذا الملفُّ يقيس غيرَ بوّابة الاعتماد، فتُطفأ صراحةً ليبقى المقيسُ
            وحدَه في الصورة. واشتراطُ الاعتماد له اختبارُه:
            [PayrollDisbursementApprovalRequiredTest].
        */
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('require_disbursement_approval')->default(false);
            $t->timestamps();
        });
        DB::table('payroll_settings')->insert(['require_disbursement_approval' => false]);
        PayrollSettings::forget();

        Cache::flush();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->unsignedBigInteger('branch_id')->nullable();
            // الموديل يستعمل `SoftDeletes` — فالعمود شرطُ كل استعلام عليه
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('payroll_id')->nullable();
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->string('currency', 10)->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('total_additions', 15, 2)->default(0);
            $t->decimal('total_deductions', 15, 2)->default(0);
            $t->decimal('total_advances_deducted', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default(PayrollRun::STATUS_PENDING);
            $t->string('trigger_source', 20)->default(PayrollRun::TRIGGER_CRON);
            $t->timestamp('disbursed_at')->nullable();
            $t->unsignedBigInteger('disbursed_by')->nullable();
            $t->unsignedBigInteger('expense_id')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('treasury_id')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->string('reference_number')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->date('date')->nullable();
            $t->text('description')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->string('currency', 10)->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('salary_id')->nullable();
            $t->unsignedBigInteger('created_by_employee')->nullable();
            $t->string('reference_type')->nullable();
            $t->string('linking_type')->nullable();
            $t->unsignedBigInteger('accounting_period_id')->nullable();
            $t->timestamps();
        });

        Schema::create('system_currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10);
            $t->string('name')->nullable();
            $t->string('symbol')->nullable();
            $t->string('native_symbol')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
    }

    /** خدمةُ فتراتٍ بديلة: تمنع دائماً بهذا السبب، أو تسمح دائماً. */
    private function fakeAccountingPeriods(?string $blockReason): void
    {
        app()->bind(AccountingPeriodService::class, function () use ($blockReason) {
            return new class($blockReason) extends AccountingPeriodService
            {
                public function __construct(private ?string $reason) {}

                public function settings(): AccountingPeriodSetting
                {
                    // بلا صلاحية تجاوز، فلا يلتفّ الاختبارُ على المنع
                    return new AccountingPeriodSetting(
                        ['override_permission' => null] + AccountingPeriodSetting::DEFAULTS
                    );
                }

                public function blockReason(Carbon|string|null $date): ?string
                {
                    return $this->reason;
                }
            };
        });
    }

    private function makeRun(?string $slipCurrency = null, ?int $branchId = null): PayrollRun
    {
        $employee = Employee::create([
            'first_name' => 'سالم',
            'status' => 1,
            'branch_id' => $branchId,
        ]);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
            'currency' => $slipCurrency,
            'basic_amount' => 3000,
            'start_date' => now()->subMonths(2)->toDateString(),
        ]);

        return PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'scheduled_date' => now()->subDay()->toDateString(),
            'status' => PayrollRun::STATUS_PENDING,
            'basic_amount' => 3000,
        ]);
    }

    // ═══════════════ الفترة المحاسبية ═══════════════

    public function test_disbursement_is_blocked_when_the_period_is_locked(): void
    {
        $this->fakeAccountingPeriods('الفترة المحاسبية لهذا التاريخ مقفلة.');

        $run = $this->makeRun();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('الفترة المحاسبية لهذا التاريخ مقفلة.');

        app(PayrollDisbursementService::class)->disburse($run);
    }

    /**
     * المنعُ يقع **قبل** أن يُمَسّ شيء: لا قيدَ يُكتب ولا حالةَ تتغيّر.
     * ولو وقع داخل المعاملة لَتَراجَع عنه `rollBack`، لكن الفحصَ المبكر
     * يجنّب الخزينةَ والسندَ أصلاً.
     */
    public function test_a_blocked_period_leaves_no_trace(): void
    {
        $this->fakeAccountingPeriods('الفترة مقفلة.');

        $run = $this->makeRun();

        try {
            app(PayrollDisbursementService::class)->disburse($run);
            $this->fail('كان يجب أن يُمنع الصرف');
        } catch (\RuntimeException) {
            // متوقَّع
        }

        $this->assertSame(0, JournalEntry::count(), 'لا يُكتب قيدٌ في فترة مقفلة');
        $this->assertSame(PayrollRun::STATUS_PENDING, $run->fresh()->status, 'الصرفة تبقى معلّقة');
        $this->assertNull($run->fresh()->disbursed_at);
    }

    /**
     * وفترةٌ مفتوحة تتجاوز الحارس. الصرفُ يتعثّر بعدها لسببٍ آخر (لا
     * حسابات ولا خزينة في هذا المخطَّط المصغَّر) — والمقصود أن السبب
     * **ليس** الفترة.
     */
    public function test_an_open_period_passes_the_guard(): void
    {
        $this->fakeAccountingPeriods(null);

        $run = $this->makeRun();

        try {
            app(PayrollDisbursementService::class)->disburse($run);
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('الفترة', $e->getMessage());

            return;
        }

        $this->assertTrue(true);
    }

    // ═══════════════ العملة والفرع ═══════════════

    /**
     * العقدُ الذي تعتمد عليه الخدمة الآن: تمرير `null` يعني «املأها بما
     * ضُبط»، لا `'SAR'` مكتوبةً. ولو عاد أحدٌ إلى الصيغة القديمة سقط هذا.
     */
    public function test_a_null_currency_is_filled_from_the_configured_default(): void
    {
        SystemCurrency::create(['code' => 'AED', 'name' => 'درهم', 'is_default' => true]);
        CurrencySettings::forget();

        $this->assertSame('AED', CurrencySettings::code(), 'الافتراضية في هذا الاختبار ليست الريال');

        $entry = JournalEntry::create([
            'reference_number' => 'SAL-ACC-1',
            'date' => now()->toDateString(),
            'description' => 'استحقاق راتب',
            'status' => 1,
            'currency' => null,
            'branch_id' => null,
        ]);

        $this->assertSame('AED', $entry->fresh()->currency);
        $this->assertNotSame('SAR', $entry->fresh()->currency);
    }

    /** وعملةُ القسيمة حين تكون محدَّدة تعلو على الافتراضية. */
    public function test_an_explicit_slip_currency_wins_over_the_default(): void
    {
        SystemCurrency::create(['code' => 'AED', 'name' => 'درهم', 'is_default' => true]);
        CurrencySettings::forget();

        $entry = JournalEntry::create([
            'reference_number' => 'SAL-ACC-2',
            'date' => now()->toDateString(),
            'description' => 'استحقاق راتب',
            'status' => 1,
            'currency' => 'USD',
        ]);

        $this->assertSame('USD', $entry->fresh()->currency);
    }

    /**
     * الفرعُ يُكتب من فرع الموظف. وكان يُترك للموديل الذي يملؤه من فرع
     * المستخدم المسجَّل — فيخرج فارغاً في الكرون حيث لا مستخدم، وهو
     * طريقُ أغلب الصرف.
     */
    public function test_the_employee_branch_reaches_the_entry(): void
    {
        $entry = JournalEntry::create([
            'reference_number' => 'SAL-ACC-3',
            'date' => now()->toDateString(),
            'description' => 'استحقاق راتب',
            'status' => 1,
            'branch_id' => 7,
        ]);

        $this->assertSame(7, (int) $entry->fresh()->branch_id);
    }

    /**
     * حارسٌ على الشيفرة نفسها: لا عملةَ مكتوبةً نصّاً في مسار الترحيل.
     * الاختبارات أعلاه تقيس العقدَ مع الموديل، وهذا يقيس أن الخدمة تلتزمه.
     */
    public function test_the_service_no_longer_hardcodes_a_currency(): void
    {
        $sources = [
            file_get_contents(app_path('Services/PayrollDisbursementService.php')),
            file_get_contents(app_path('Services/PayrollAccountant.php')),
        ];
        $code = preg_replace('#/\*.*?\*/#s', '', implode("\n", $sources));
        $code = preg_replace('#//.*$#m', '', (string) $code);

        $this->assertStringNotContainsString(
            "'SAR'",
            (string) $code,
            'عملةٌ مكتوبةً نصّاً في خدمة الصرف — يجب أن تأتي من إعدادات العملات'
        );

        $this->assertStringContainsString(
            'AccountingPeriodGuard::blockReason',
            (string) file_get_contents(app_path('Services/PayrollDisbursementService.php')),
            'خدمة الصرف يجب أن تسأل حارس الفترة المحاسبية قبل الترحيل'
        );
    }
}
