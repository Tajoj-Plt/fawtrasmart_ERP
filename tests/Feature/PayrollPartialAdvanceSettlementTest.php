<?php

namespace Tests\Feature;

use App\Services\PayrollSettlement;
use Tests\TestCase;

/**
 * لا يُخصَم من الراتب قرشُ سلفةٍ لا يقابله سطرٌ يُثبته.
 *
 * **ما كان**
 *
 * `planAdvances()` يقتطع من كلِّ سلفةٍ لا يكفيها المتبقي مقدارَ المتبقي
 * ويعلّمها `__partial`. فينزل صافي الموظف بالجزء، ويدخل الجزءُ في
 * `totalAdvances` فيُدان به حسابُ السلف في قيد الصرف — أي يُثبت الدفترُ أنّ
 * المدينيةَ نزلت. ثمّ يعود `applyAdvanceSettlement()` **بلا فعل** لأقساط
 * `installment_payments`، إذ لا عمودَ مبلغٍ مسدَّدٍ في جدولها: حالتُها `paid`
 * أو `unpaid` ولا ثالث.
 *
 * فيبقى القسطُ `unpaid` ويُطالَب به الموظفُ **كاملاً** في الشهر التالي:
 * يدفع جزءَه مرّتين، ويُدان حسابُ السلف بأكثر مما عليه.
 * وللسلف المجدولة صورةٌ أخفُّ: يزيد `settled_amount` ولا يزيد
 * `paid_installments`، فلا يتقدّم شهرُ الاستحقاق التالي.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الخطّة لا تقتطع إلا بوحداتٍ يقبلها الجدولُ المقابل: القسطُ كلُّه، أو
 * عددٌ صحيحٌ من الأقساط المجدولة، أو أيُّ مبلغٍ للسلفة العادية التي لها عمود
 * مبلغ. ثمّ أنّ الحارسَ في جانب التطبيق يرفع خللَ الخطّة بدل أن يمضي الخصمُ
 * صامتاً.
 *
 * لا حاجةَ إلى قاعدةِ بيانات: `planAdvances()` دالّةٌ صافية على مصفوفة.
 */
class PayrollPartialAdvanceSettlementTest extends TestCase
{
    private function settlement(): PayrollSettlement
    {
        return app(PayrollSettlement::class);
    }

    /** قسطُ نظام A — الوحدةُ القسطُ كلُّه. */
    private function instalmentRow(int $id, float $amount): array
    {
        return [
            'system' => 'A',
            'advance_id' => 7,
            'source_table' => 'installment_payments',
            'source_id' => $id,
            'amount' => $amount,
            'unit' => $amount,
            'name' => 'قسط سلفة #7',
            'description' => null,
        ];
    }

    /** سلفةٌ مجدولة — الوحدةُ قسطٌ كامل. */
    private function scheduledRow(float $perInstalment, int $dueCount): array
    {
        return [
            'system' => 'B',
            'advance_id' => 9,
            'source_table' => 'salary_advance_requests',
            'source_id' => 9,
            'amount' => $perInstalment * $dueCount,
            'unit' => $perInstalment,
            'name' => "قسط سلفة مجدولة #9 ({$dueCount} قسط)",
            'description' => null,
            '__extra' => [
                'due_count' => $dueCount,
                'amount_per_installment' => $perInstalment,
            ],
        ];
    }

    /** سلفةٌ عادية — مبلغٌ حرٌّ يقبل الكسر. */
    private function lumpRow(float $amount): array
    {
        return [
            'system' => 'B',
            'advance_id' => 11,
            'source_table' => 'salary_advance_requests',
            'source_id' => 11,
            'amount' => $amount,
            'unit' => null,
            'name' => 'تسوية سلفة عادية #11',
            'description' => null,
        ];
    }

    /**
     * الحالةُ التي كانت تُخصَم مرّتين: متبقٍّ ٤٠٠ وقسطٌ ١٬٠٠٠.
     * لا يُخصَم شيء — لا ٤٠٠ تُقيَّد ولا يبقى القسطُ مطالَباً بها ثانيةً.
     */
    public function test_an_instalment_that_does_not_fit_is_not_partially_taken(): void
    {
        $plan = $this->settlement()->planAdvances([$this->instalmentRow(1, 1000.0)], 400.0);

        $this->assertSame([], $plan['items'], 'القسطُ الذرّة لا يُقتطع جزئياً');
        $this->assertSame(0.0, $plan['total']);
    }

    /** ويُخصَم كاملاً حين يتّسع له المتبقي. */
    public function test_an_instalment_that_fits_is_taken_whole(): void
    {
        $plan = $this->settlement()->planAdvances([$this->instalmentRow(1, 1000.0)], 1000.0);

        $this->assertCount(1, $plan['items']);
        $this->assertSame(1000.0, $plan['total']);
        $this->assertArrayNotHasKey('__partial', $plan['items'][0]);
    }

    /** وقسطان من ثلاثةٍ يمرّان، والثالثُ يُترك بلا اقتطاعٍ منه. */
    public function test_it_stops_at_the_last_instalment_that_fits(): void
    {
        $plan = $this->settlement()->planAdvances([
            $this->instalmentRow(1, 500.0),
            $this->instalmentRow(2, 500.0),
            $this->instalmentRow(3, 500.0),
        ], 1200.0);

        $this->assertCount(2, $plan['items']);
        $this->assertSame(1000.0, $plan['total']);
    }

    /**
     * السلفةُ المجدولة تُقلَّص إلى عددٍ صحيحٍ من الأقساط، ويُصحَّح
     * `due_count` معها — فهو ما يعتمده `applyAdvanceSettlement()` في زيادة
     * `paid_installments`.
     */
    public function test_a_scheduled_advance_is_reduced_to_whole_instalments(): void
    {
        $plan = $this->settlement()->planAdvances([$this->scheduledRow(300.0, 3)], 700.0);

        $this->assertCount(1, $plan['items']);
        $this->assertSame(600.0, $plan['total'], 'قسطان لا 700');
        $this->assertSame(2, $plan['items'][0]['__extra']['due_count']);
        $this->assertArrayNotHasKey('__partial', $plan['items'][0]);
        $this->assertStringContainsString('2 قسط', $plan['items'][0]['name']);
    }

    /** ولا يُقتطع منها شيءٌ إن لم يتّسع المتبقي لقسطٍ واحد. */
    public function test_a_scheduled_advance_below_one_instalment_is_skipped(): void
    {
        $plan = $this->settlement()->planAdvances([$this->scheduledRow(300.0, 3)], 250.0);

        $this->assertSame([], $plan['items']);
        $this->assertSame(0.0, $plan['total']);
    }

    /**
     * العشريّاتُ المتتابعة لا تُسقط قسطاً: قسطٌ بـ333.33 ومتبقٍّ 999.99
     * يخرج ثلاثةَ أقساطٍ لا اثنَين.
     */
    public function test_repeating_decimals_do_not_lose_an_instalment(): void
    {
        $plan = $this->settlement()->planAdvances([$this->scheduledRow(333.33, 3)], 999.99);

        $this->assertCount(1, $plan['items']);
        $this->assertSame(3, $plan['items'][0]['__extra']['due_count']);
        $this->assertSame(999.99, $plan['total']);
    }

    /**
     * والسلفةُ العادية تبقى على التجزئة: `settled_amount` عمودُ مبلغٍ
     * يُسجِّل الكسرَ بدقّة، فالاقتطاعُ الجزئيُّ هنا له سطرٌ يُثبته.
     */
    public function test_a_lump_advance_keeps_its_partial_settlement(): void
    {
        $plan = $this->settlement()->planAdvances([$this->lumpRow(5000.0)], 1800.0);

        $this->assertCount(1, $plan['items']);
        $this->assertSame(1800.0, $plan['total']);
        $this->assertTrue($plan['items'][0]['__partial']);
        $this->assertStringContainsString('جزئي', $plan['items'][0]['name']);
    }

    /** ولا يتجاوز الاقتطاعُ المتاحَ من الاستحقاق بحالٍ. */
    public function test_the_plan_never_exceeds_the_budget(): void
    {
        foreach ([0.0, 120.0, 999.99, 5000.0] as $budget) {
            $plan = $this->settlement()->planAdvances([
                $this->instalmentRow(1, 500.0),
                $this->scheduledRow(300.0, 4),
                $this->lumpRow(2000.0),
            ], $budget);

            $this->assertLessThanOrEqual(
                $budget + 0.001,
                $plan['total'],
                "الخطّةُ تجاوزت الميزانية عند {$budget}"
            );
        }
    }
}
