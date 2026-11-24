<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * دقّةُ الراتب الأساسي في العقد.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان `contracts.amount` عموداً صحيحاً (`integer`)، وقاعدةُ التحقّق في
 * `ContractsController` هي `'amount' => 'required|numeric'` — تقبل
 * الكسور. و**MySQL لا يقصّ الكسر بل يقرّبه**: `7500.50` تُخزَّن `7501`.
 * فالراتب الأساسي قد يزيد ريالاً أو ينقص، بلا رسالةٍ ولا تحذير، ويسري
 * الفرقُ إلى قسيمة الراتب وقيد الاستحقاق وسند الصرف.
 *
 * فالعطلُ لم يكن في التحقّق — كان صحيحاً — بل في العمود الذي يكذّبه.
 *
 * **حدُّ هذا الاختبار**: `phpunit.xml` يفرض sqlite، وأنواعُه ديناميكية
 * فلا يقرّب شيئاً — أي أن اختباراً سلوكياً هنا **يمرّ على العمود القديم
 * أيضاً**. فالحارسُ الحقيقي على النوع هو
 * `test_the_migration_declares_a_decimal_column` أدناه، والسلوكُ
 * تُثبِّته الحالاتُ التي قبله. وقد جُرِّب التحويلُ فعلياً على المحرّكين
 * معاً قبل الإيداع.
 */
class ContractAmountPrecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contracts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('code')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('currency', 3)->default('SAR');
            $t->timestamps();
        });
    }

    public function test_a_fractional_basic_salary_survives_the_round_trip(): void
    {
        $contract = Contract::create(['code' => 'CTR-1', 'amount' => 7500.50]);

        $this->assertEqualsWithDelta(7500.50, (float) $contract->fresh()->amount, 0.001);
    }

    /** والمنزلتان تُحفظان لا تُقرَّبان: `7501` هو ما كان يقع. */
    public function test_the_amount_is_neither_rounded_nor_truncated(): void
    {
        $contract = Contract::create(['code' => 'CTR-2', 'amount' => 7500.50]);

        $stored = (float) $contract->fresh()->amount;

        $this->assertNotEquals(7501.0, $stored, 'قُرِّب المبلغ — وهو ما يفعله MySQL بالعمود الصحيح');
        $this->assertNotEquals(7500.0, $stored, 'قُصَّ الكسر');
        $this->assertEqualsWithDelta(7500.50, $stored, 0.001);
    }

    /** الصبُّ يوحّد النوع بين المحرّكين: نصٌّ بمنزلتين عشريتين. */
    public function test_the_cast_normalises_the_returned_type(): void
    {
        $contract = Contract::create(['code' => 'CTR-3', 'amount' => 7500.5]);

        $this->assertSame('7500.50', $contract->fresh()->amount);
    }

    /** والقيمةُ الصحيحة تبقى كما هي — التحويل موسِّعٌ لا مغيِّر. */
    public function test_whole_amounts_are_unchanged(): void
    {
        $contract = Contract::create(['code' => 'CTR-4', 'amount' => 7500]);

        $this->assertSame('7500.00', $contract->fresh()->amount);
        $this->assertEqualsWithDelta(7500, (float) $contract->fresh()->amount, 0.001);
    }

    /** وعقدٌ بلا مبلغ يبقى فارغاً لا صفراً. */
    public function test_a_null_amount_stays_null(): void
    {
        $contract = Contract::create(['code' => 'CTR-5', 'amount' => null]);

        $this->assertNull($contract->fresh()->amount);
    }

    /**
     * الحارسُ الحقيقي على النوع — إذ لا يستطيع sqlite أن يحرسه سلوكياً.
     *
     * ويحرس معه قابليّةَ النقل: `ALTER TABLE … MODIFY` صيغةُ MySQL وحدها
     * ولا يفهمها sqlite، ومهاجرةٌ واحدة بتلك الصيغة (`payment_methods`)
     * تُسقط حزمة الاختبارات كلَّها عند الترحيل. فباني المخطَّط وحده.
     */
    public function test_the_migration_declares_a_decimal_column(): void
    {
        $path = base_path('database/migrations/2026_08_30_180000_widen_contract_amount_to_decimal.php');

        $this->assertFileExists($path);

        $source = (string) file_get_contents($path);
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        $this->assertMatchesRegularExpression(
            "/decimal\(\s*'amount'\s*,\s*15\s*,\s*2\s*\)/",
            $code,
            'المهاجرة يجب أن تعلن decimal(15,2) — وهي دقّة المال في هذا النظام'
        );

        $this->assertStringNotContainsString('DB::statement', $code);
        $this->assertStringNotContainsString('MODIFY', $code);
        $this->assertStringContainsString('->change()', $code);
    }
}
