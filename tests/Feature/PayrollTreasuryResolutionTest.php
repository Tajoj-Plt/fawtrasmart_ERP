<?php

namespace Tests\Feature;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\PayrollAccountant;
use App\Support\AccountingSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * خزينةُ صرف الراتب: المفتاحُ المضبوط لا الاسمُ العربيّ.
 *
 * **ما كان**
 *
 * احتياطُ `PayrollAccountant::resolveTreasury()` كان
 * `Account::where('name', 'الخزينة الرئيسية')->first()` — بحثاً بالاسم
 * حرفياً. فمنشأةٌ سمّت خزينتها غير ذلك، أو تعمل بالإنجليزية، أو أعادت
 * تسميةَ خزينتها الرئيسية، لا يجد لها هذا السطر خزينةً: فيُرمى «لا توجد
 * خزينة متاحة للصرف» ويتوقّف صرفُ الرواتب كلُّه، بينما الخزينةُ قائمةٌ
 * ومربوطةٌ في شاشة «الحسابات المحاسبية».
 *
 * وهو عطبٌ صُلح في وحدة المبيعات ووحدة نقاط البيع بالانتقال إلى المفتاح
 * `main_treasury` — «والاسمُ لا يدخل في الحساب»
 * ([Modules\Pos\…\ResolvesAccountingAccounts::treasuryAccountFor]) — وكانت
 * شاشاتُ السلف في الموارد البشرية قد انتقلت إليه أيضاً. فمسارُ ترحيل
 * الرواتب كان آخرَ ما بقي على البحث بالاسم.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الأولوياتَ المنصوصة تُحترم (الصرفة ← القسيمة ← خزينةُ الموظف)، وأنّ
 * الاحتياطَ صار المفتاحَ المضبوط ثمّ الثابتَ المزروع، وأنّ خزينةً باسمٍ
 * آخر لم تعد تُسقط الصرف.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class PayrollTreasuryResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AccountingSettings::forget();

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('code')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('balance_type')->default('debit');
            $t->decimal('balance', 15, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('accounting_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key');
            $t->string('name')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('treasury_employees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('treasury_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        AccountingSettings::forget();

        parent::tearDown();
    }

    private function seedAccount(int $id, string $name): void
    {
        DB::table('accounts')->insert([
            'id' => $id,
            'name' => $name,
            'balance_type' => 'debit',
            'balance' => 0,
            'is_active' => 1,
        ]);
    }

    private function mapMainTreasury(int $accountId): void
    {
        DB::table('accounting_settings')->insert([
            'key' => 'main_treasury',
            'name' => 'الخزينة الرئيسية',
            'account_id' => $accountId,
            'is_active' => 1,
        ]);

        AccountingSettings::forget();
    }

    /** الأطرافُ الثلاثة بلا خزينةٍ مختارة — فيقع الحلُّ على الاحتياط. */
    private function resolve(?int $runTreasury = null, ?int $slipTreasury = null): ?Account
    {
        $run = new PayrollRun(['treasury_id' => $runTreasury]);
        $slip = new SalarySlip(['treasury_id' => $slipTreasury]);

        $employee = new Employee;
        $employee->id = 7;

        return (new PayrollAccountant)->resolveTreasury($run, $slip, $employee);
    }

    /**
     * خزينةٌ اسمُها غيرُ «الخزينة الرئيسية» تُحلّ من المفتاح المضبوط.
     * هذه هي الحالةُ التي كانت تُسقط الصرف.
     */
    public function test_a_differently_named_main_treasury_is_resolved_from_the_setting(): void
    {
        $this->seedAccount(91, 'Main Cash Box');
        $this->mapMainTreasury(91);

        $treasury = $this->resolve();

        $this->assertNotNull($treasury, 'خزينةٌ مربوطةٌ في الإعدادات يجب أن تُوجد وإن اختلف اسمُها');
        $this->assertSame(91, (int) $treasury->id);
    }

    /** وبغير ربطٍ في الشاشة يقع الحلُّ على الثابت المزروع. */
    public function test_it_falls_back_to_the_seeded_constant_when_unmapped(): void
    {
        $this->seedAccount(SystemAccountIds::MAIN_TREASURY, 'الخزينة الرئيسية');

        $treasury = $this->resolve();

        $this->assertNotNull($treasury);
        $this->assertSame(SystemAccountIds::MAIN_TREASURY, (int) $treasury->id);
    }

    /** خزينةُ الصرفة تعلو على كلِّ ما بعدها. */
    public function test_the_run_treasury_wins(): void
    {
        $this->seedAccount(91, 'Main Cash Box');
        $this->mapMainTreasury(91);
        $this->seedAccount(55, 'خزينة الفرع');

        $this->assertSame(55, (int) $this->resolve(runTreasury: 55)->id);
    }

    /** ثمّ خزينةُ القسيمة. */
    public function test_the_slip_treasury_comes_next(): void
    {
        $this->seedAccount(91, 'Main Cash Box');
        $this->mapMainTreasury(91);
        $this->seedAccount(56, 'خزينة القسيمة');

        $this->assertSame(56, (int) $this->resolve(slipTreasury: 56)->id);
    }

    /** ثمّ خزينةُ الموظف من `treasury_employees`. */
    public function test_the_employee_treasury_comes_before_the_fallback(): void
    {
        $this->seedAccount(91, 'Main Cash Box');
        $this->mapMainTreasury(91);
        $this->seedAccount(57, 'خزينة الموظف');
        DB::table('treasury_employees')->insert(['treasury_id' => 57, 'employee_id' => 7]);

        $this->assertSame(57, (int) $this->resolve()->id);
    }

    /** حارسٌ على الشيفرة: لا اسمَ خزينةٍ مكتوباً نصّاً في مسار الترحيل. */
    public function test_the_posting_path_no_longer_searches_by_name(): void
    {
        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));
        $code = preg_replace('#/\*.*?\*/#s', '', $source);
        $code = preg_replace('#//.*$#m', '', (string) $code);

        $this->assertStringNotContainsString(
            "'الخزينة الرئيسية'",
            (string) $code,
            'بحثٌ بالاسم العربيّ في مسار ترحيل الرواتب — يجب أن يأتي من مفتاح main_treasury'
        );

        $this->assertStringContainsString(
            "AccountingSettings::account('main_treasury'",
            (string) $code,
            'الخزينةُ الافتراضية يجب أن تُقرأ من «الحسابات المحاسبية»'
        );
    }
}
