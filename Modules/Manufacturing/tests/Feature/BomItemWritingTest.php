<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use Illuminate\Http\Request;
use Modules\Manufacturing\Http\Controllers\Concerns\BuildsBomItems;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    بنودُ قائمة المواد تُكتب سطراً لكل بند.

    الشاشةُ أربعةُ جداول مستقلّة، وكان الحفظ يمرّ على المواد الخام وحدها
    ويحشر في سطرها بياناتِ المصروف والعملية والهالك من الفهرس نفسه.
*/
class BomItemWritingTest extends ManufacturingTestCase
{
    use BuildsBomItems;

    private function bom(): ProductionMaterials
    {
        return $this->fixtures->bom($this->fixtures->product());
    }

    private function items(ProductionMaterials $bom)
    {
        return ProductionMaterialsItem::where('production_material_id', $bom->id)->get();
    }

    /*
        العطل الأشدّ: قائمةٌ بمادّةٍ خام واحدة وثلاثةِ مصروفات كانت
        تُنشئ سطراً واحداً — فيضيع مصروفان بلا أثر ولا رسالة.
    */
    public function test_expense_lines_beyond_the_raw_count_are_not_lost(): void
    {
        $bom = $this->bom();
        $flour = $this->fixtures->product();
        $account = $this->fixtures->expenseAccount();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$flour->id],
            'raw_quantity' => [5],
            'raw_unit_price' => [2],
            'raw_total' => [10],

            'expenses_account_id' => [$account->id, $account->id, $account->id],
            'expenses_price' => [10, 20, 30],
            'expenses_total' => [10, 20, 30],
        ]));

        $items = $this->items($bom);

        $this->assertCount(4, $items, 'مادّةٌ خام واحدة وثلاثةُ مصروفات = أربعةُ سطور');
        $this->assertCount(1, $items->whereNotNull('raw_product_id'));
        $this->assertCount(3, $items->whereNotNull('expenses_account_id'));
        $this->assertEqualsCanonicalizing(
            [10.0, 20.0, 30.0],
            $items->whereNotNull('expenses_account_id')->pluck('expenses_total')->map(fn ($v) => (float) $v)->all()
        );
    }

    /*
        قائمةٌ بلا مواد خام — تجميعٌ أو تعبئة — كانت لا تُنشئ شيئاً
        إطلاقاً لأن الحلقة كلّها معلّقةٌ على وجود `raw_product_id`.
    */
    public function test_a_bom_with_no_raw_materials_still_saves_its_lines(): void
    {
        $bom = $this->bom();
        $account = $this->fixtures->expenseAccount();

        $this->syncBomItems($bom, new Request([
            'expenses_account_id' => [$account->id],
            'expenses_total' => [50],
        ]));

        $items = $this->items($bom);

        $this->assertCount(1, $items);
        $this->assertNull($items->first()->raw_product_id);
        $this->assertSame((int) $account->id, (int) $items->first()->expenses_account_id);
    }

    /*
        الفرزُ في شاشة التعديل وفي بناء أذون المستودع يقوم على
        `whereNotNull`. فإن حمل السطرُ نوعين، تطابقت المجموعات.
    */
    public function test_each_row_carries_exactly_one_line_type(): void
    {
        $bom = $this->bom();
        $raw = $this->fixtures->product();
        $waste = $this->fixtures->product();
        $account = $this->fixtures->expenseAccount();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$raw->id],
            'raw_quantity' => [3],
            'expenses_account_id' => [$account->id],
            'expenses_total' => [7],
            'end_life_product_id' => [$waste->id],
            'end_life_quantity' => [1],
        ]));

        $items = $this->items($bom);
        $this->assertCount(3, $items);

        foreach ($items as $item) {
            $filled = collect([
                'raw_product_id' => $item->raw_product_id,
                'expenses_account_id' => $item->expenses_account_id,
                'workstation_id' => $item->workstation_id,
                'end_life_product_id' => $item->end_life_product_id,
            ])->filter(fn ($v) => $v !== null);

            $this->assertCount(1, $filled, 'السطر يحمل أكثر من نوع: '.$filled->keys()->implode(', '));
        }
    }

    /* الأعمدة المرجعية الفارغة تُكتب NULL لا صفراً. */
    public function test_absent_references_are_written_as_null_not_zero(): void
    {
        $bom = $this->bom();
        $raw = $this->fixtures->product();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$raw->id],
            'raw_quantity' => [2],
            // لا مرحلةَ إنتاج ولا مصروف ولا محطة عمل
        ]));

        $item = $this->items($bom)->first();

        $this->assertNull($item->raw_production_stage_id);
        $this->assertNull($item->expenses_account_id);
        $this->assertNull($item->workstation_id);
        $this->assertNull($item->end_life_product_id);
    }

    /*
        التعديلُ كان يستعمل updateOrCreate مطابقاً على `item_id` —
        وهو حقلٌ لا ترسله الشاشة أصلاً — فيُنشئ صفوفاً جديدة كل حفظ.
    */
    public function test_saving_twice_does_not_duplicate_rows(): void
    {
        $bom = $this->bom();
        $raw = $this->fixtures->product();

        $payload = new Request([
            'raw_product_id' => [$raw->id],
            'raw_quantity' => [4],
            'raw_total' => [8],
        ]);

        $this->syncBomItems($bom, $payload);
        $this->syncBomItems($bom, $payload);
        $this->syncBomItems($bom, $payload);

        $this->assertCount(1, $this->items($bom), 'ثلاثةُ حفظاتٍ خلّفت أكثر من سطر');
    }

    /* الصفرُ في الكميات قيمةٌ صحيحة — لا يُحوَّل إلى NULL. */
    public function test_zero_quantities_are_kept_as_zero(): void
    {
        $bom = $this->bom();
        $raw = $this->fixtures->product();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$raw->id],
            'raw_quantity' => [0],
            'raw_unit_price' => [0],
            'raw_total' => [0],
        ]));

        $item = $this->items($bom)->first();

        $this->assertNotNull($item);
        $this->assertSame(0.0, (float) $item->raw_quantity);
        $this->assertSame(0.0, (float) $item->raw_total);
    }
}
