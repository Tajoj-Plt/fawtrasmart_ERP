<?php

namespace Tests\Feature;

use App\Models\SalaryItem;
use App\Models\SalarySlip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * اتّجاهُ بند الراتب: مستحقٌّ أم مستقطع؟
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كان [SalaryItem] يسمّي القيمتين بالعكس — `TYPE_DEDUCTION = 1` و
 * `TYPE_ADDITION = 2` — بينما التشغيلُ كلُّه على العكس: الشاشةُ تعرض
 * `value="1"` مستحقّاً، وعلاقاتُ [SalarySlip] تُرشّح `1` استحقاقاً،
 * و`ContractsController` و`SalaryTemplatesController` و
 * `SalarySlipApiController` كذلك.
 *
 * ولم ينفجر الخللُ لأن أحداً لم يثق بالاسم. فأولُ من كان سيكتب
 * `SalaryItem::TYPE_ADDITION` بدل الرقم `1` كان **يقلب الاستحقاقات
 * والخصميات في كل قسيمة** — ولا شيء يشي بالعطل إلا صافي راتبٍ خاطئ.
 *
 * فالاختبارُ يشدّ الطبقات الثلاث إلى بعضها: الثابت، والعلاقة، والشاشة.
 * ومتى افترقت واحدةٌ سقط.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase` — كما في
 * [PayrollDisbursementPostingTest] وللسبب نفسه.
 */
class SalaryItemTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('status')->nullable();
            $t->string('currency', 10)->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->tinyInteger('type')->nullable()->default(1);
            $t->tinyInteger('status')->nullable()->default(1);
            $t->text('description')->nullable();
            $t->tinyInteger('salary_item_value')->nullable()->default(1);
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('calculation_formula')->nullable();
            $t->text('condition')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->unsignedBigInteger('salary_template_id')->nullable();
            $t->unsignedBigInteger('salary_slips_id')->nullable();
            $t->unsignedBigInteger('contracts_id')->nullable();
            $t->timestamps();
        });
    }

    public function test_the_constants_carry_the_values_the_system_actually_uses(): void
    {
        $this->assertSame(1, SalaryItem::TYPE_ADDITION, 'المستحق قيمته 1 في القاعدة والشاشات');
        $this->assertSame(2, SalaryItem::TYPE_DEDUCTION, 'المستقطع قيمته 2 في القاعدة والشاشات');
    }

    /**
     * العلاقتان تُصنّفان البنود كما تعنيه الأسماء. ولو عُكس الثابتان
     * لَقلبَ هذا الاختبارُ نتيجتَه فوراً.
     */
    public function test_the_slip_relations_sort_items_by_their_real_direction(): void
    {
        $slip = SalarySlip::create(['employee_id' => 1, 'basic_amount' => 5000]);

        SalaryItem::create([
            'name' => 'بدل سكن',
            'type' => SalaryItem::TYPE_ADDITION,
            'amount' => 1000,
            'salary_slips_id' => $slip->id,
        ]);

        SalaryItem::create([
            'name' => 'خصم تأخير',
            'type' => SalaryItem::TYPE_DEDUCTION,
            'amount' => 150,
            'salary_slips_id' => $slip->id,
        ]);

        $additions = $slip->additionItems()->pluck('name')->all();
        $deductions = $slip->deductionItems()->pluck('name')->all();

        $this->assertSame(['بدل سكن'], $additions);
        $this->assertSame(['خصم تأخير'], $deductions);

        // والمبالغ لا تختلط: هذا ما يبني عليه `PayrollDisbursementService`
        $this->assertEqualsWithDelta(1000, (float) $slip->additionItems()->sum('amount'), 0.001);
        $this->assertEqualsWithDelta(150, (float) $slip->deductionItems()->sum('amount'), 0.001);
    }

    /**
     * الشاشةُ التي يختار منها المستخدم النوعَ يجب أن توافق الثابتين.
     * وهي المصدرُ الذي حسم التناقضَ أصلاً: القيمُ في القاعدة كُتبت منها.
     */
    public function test_the_item_form_offers_the_same_mapping_as_the_constants(): void
    {
        $blade = base_path('Modules/HR/resources/views/salaries/salary_items/create.blade.php');

        $this->assertFileExists($blade);

        $html = (string) file_get_contents($blade);

        // كتلةُ `<select name="type">` وحدها — لا كل `option` في الصفحة
        $this->assertSame(
            1,
            preg_match('/<select[^>]*name="type"[^>]*>(.*?)<\/select>/s', $html, $select),
            'لم يُعثر على حقل اختيار النوع في شاشة البند'
        );

        preg_match_all('/<option\s+value="(\d+)"[^>]*>\s*([^<]+?)\s*<\/option>/u', $select[1], $options, PREG_SET_ORDER);

        $map = [];
        foreach ($options as $option) {
            $map[(int) $option[1]] = trim($option[2]);
        }

        $this->assertArrayHasKey(SalaryItem::TYPE_ADDITION, $map);
        $this->assertArrayHasKey(SalaryItem::TYPE_DEDUCTION, $map);

        $this->assertSame('مستحق', $map[SalaryItem::TYPE_ADDITION]);
        $this->assertSame('مستقطع', $map[SalaryItem::TYPE_DEDUCTION]);
    }

    /**
     * ووصفُ العمود في المهاجرة يوافقهما كذلك — فهو ما ضلّل القارئ أوّلاً.
     */
    public function test_the_migration_comment_no_longer_states_the_reverse(): void
    {
        $migration = base_path('database/migrations/2025_08_14_140749_create_salary_items_table.php');

        $this->assertFileExists($migration);

        $source = (string) file_get_contents($migration);

        // سطرُ تعريف العمود وحده — لا الشرحُ الذي يحكي الخطأ القديم
        $this->assertSame(
            1,
            preg_match("/tinyInteger\('type'\).*?comment\('([^']+)'\)/", $source, $found),
            'لم يُعثر على تعريف عمود النوع'
        );

        $this->assertStringNotContainsString('1=>deduction', $found[1]);
        $this->assertStringContainsString('1=>addition', $found[1]);
    }
}
