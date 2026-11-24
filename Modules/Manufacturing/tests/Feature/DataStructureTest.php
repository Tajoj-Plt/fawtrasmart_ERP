<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Enums\BomLineType;
use App\Models\ManufacturOrders;
use App\Models\ProductionMaterialsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Http\Controllers\Concerns\BuildsBomItems;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    المرحلة ٣ — بنية البيانات.

    تُثبت أن الكميات والأسعار صارت عشرية، وأن العميل والحساب اختياريان،
    وأن نوع السطر معلَنٌ لا مستنتَج.
*/
class DataStructureTest extends ManufacturingTestCase
{
    use BuildsBomItems;

    /* ══════════ أ · الأعداد العشرية ══════════ */

    /*
        وصفةٌ تطلب ٢٫٥ كجم دقيق. كان العمود `int` فتُحفظ ٢ — نقصُ نصف
        كيلو في كل دفعة إنتاج، صامتاً.
    */
    public function test_bom_raw_quantity_keeps_its_fraction(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $flour = $this->fixtures->product();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$flour->id],
            'raw_quantity' => [2.5],
            'raw_unit_price' => [4.25],
        ]));

        $item = ProductionMaterialsItem::where('production_material_id', $bom->id)->first();

        $this->assertSame(2.5, (float) $item->raw_quantity);
        $this->assertSame(4.25, (float) $item->raw_unit_price);
    }

    /*
        سعرُ الفاقد كان `int(11)` بينما سعرُ الخام عشري — في الجدول
        نفسه. ٧٫٥٠ ﷼ كانت تُحفظ ٧.
    */
    public function test_waste_price_and_quantity_keep_their_fractions(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $scrap = $this->fixtures->product();

        $this->syncBomItems($bom, new Request([
            'end_life_product_id' => [$scrap->id],
            'end_life_quantity' => [0.75],
            'end_life_unit_price' => [7.5],
        ]));

        $item = ProductionMaterialsItem::where('production_material_id', $bom->id)->first();

        $this->assertSame(0.75, (float) $item->end_life_quantity);
        $this->assertSame(7.5, (float) $item->end_life_unit_price);
    }

    /* نصفُ ساعة تشغيل كان يُحفظ صفراً. */
    public function test_operating_time_keeps_its_fraction(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());

        $this->syncBomItems($bom, new Request([
            'workstation_id' => [1],
            'operating_time' => [1.5],
            'manu_total_cost' => [20],
        ]));

        $item = ProductionMaterialsItem::where('production_material_id', $bom->id)->first();

        $this->assertSame(1.5, (float) $item->operating_time);
    }

    /* أمرُ تصنيعٍ بـ٢٫٥ كجم — مخبزٌ أو مصنع عطور. */
    public function test_order_quantity_keeps_its_fraction(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $order = $this->fixtures->order($bom, ['quantity' => 2.5]);

        $this->assertSame(2.5, (float) $order->fresh()->quantity);
    }

    /* ══════════ ب · العميل والحساب اختياريان ══════════ */

    /*
        أمرُ تصنيعٍ **للمخزون** — الحال الغالب في المصانع. كان يسقط
        بنصّ استثناء SQL خام يُعرض للمستخدم.
    */
    public function test_an_order_saves_without_a_client(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $order = $this->fixtures->order($bom, ['client_id' => null]);

        $this->assertNull($order->fresh()->client_id);
    }

    public function test_an_order_saves_without_an_account(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $order = $this->fixtures->order($bom, ['account_id' => null]);

        $this->assertNull($order->fresh()->account_id);
    }

    /* ══════════ ج · نوع السطر معلَن ══════════ */

    public function test_each_line_is_written_with_its_declared_type(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $raw = $this->fixtures->product();
        $scrap = $this->fixtures->product();
        $account = $this->fixtures->expenseAccount();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$raw->id],
            'raw_quantity' => [1],
            'expenses_account_id' => [$account->id],
            'expenses_total' => [5],
            'workstation_id' => [1],
            'operating_time' => [2],
            'end_life_product_id' => [$scrap->id],
            'end_life_quantity' => [1],
        ]));

        $items = ProductionMaterialsItem::where('production_material_id', $bom->id)->get();

        $this->assertCount(4, $items);
        $this->assertEqualsCanonicalizing(
            [BomLineType::Raw, BomLineType::Expense, BomLineType::Operation, BomLineType::Waste],
            $items->pluck('line_type')->all()
        );
    }

    /*
        التكافؤ مع الفرز القديم.

        `line_type` يحلّ محلّ `whereNotNull` تدريجياً، فيجب أن يعطي
        الطريقان النتيجة نفسها — وإلا صار في النظام مصدرا حقيقة.
    */
    public function test_filtering_by_line_type_matches_the_old_discriminator(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $account = $this->fixtures->expenseAccount();

        $this->syncBomItems($bom, new Request([
            'raw_product_id' => [$this->fixtures->product()->id, $this->fixtures->product()->id],
            'raw_quantity' => [1, 2],
            'expenses_account_id' => [$account->id],
            'expenses_total' => [9],
        ]));

        foreach (BomLineType::cases() as $type) {
            $byType = ProductionMaterialsItem::where('production_material_id', $bom->id)
                ->where('line_type', $type->value)->count();

            $byColumn = ProductionMaterialsItem::where('production_material_id', $bom->id)
                ->whereNotNull($type->discriminatorColumn())->count();

            $this->assertSame($byColumn, $byType, "تعارض في تصنيف {$type->value}");
        }
    }

    /*
        الملءُ الرجعي: صفٌّ كُتب قبل وجود العمود — نحاكيه بكتابةٍ
        مباشرة بلا `line_type` — يجب أن يصنّفه منطقُ الهجرة نفسه.
    */
    public function test_the_backfill_logic_classifies_a_legacy_row(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $raw = $this->fixtures->product();

        $id = DB::table('production_materials_items')->insertGetId([
            'production_material_id' => $bom->id,
            'raw_product_id' => $raw->id,
            'raw_quantity' => 3,
            'line_type' => null,
        ]);

        foreach (BomLineType::cases() as $type) {
            DB::table('production_materials_items')
                ->whereNull('line_type')
                ->whereNotNull($type->discriminatorColumn())
                ->where($type->discriminatorColumn(), '>', 0)
                ->update(['line_type' => $type->value]);
        }

        $this->assertSame(
            BomLineType::Raw->value,
            DB::table('production_materials_items')->where('id', $id)->value('line_type')
        );
    }

    /* ══════════ د · بنيةٌ مضافة ══════════ */

    public function test_bom_items_carry_a_unit_like_order_items_do(): void
    {
        foreach (['raw_unit_id', 'raw_conversion_factor'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('production_materials_items', $column),
                "العمود {$column} مفقود في بنود الوصفة"
            );
        }
    }

    /*
        `branch_id` بنيةٌ فقط في هذه المرحلة — لا عزل.
        و`null` تعني «كل الفروع» بدلالة `scopeForBranch`.
    */
    public function test_branch_column_exists_and_null_means_every_branch(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $order = $this->fixtures->order($bom, ['branch_id' => null]);

        $this->assertTrue(Schema::hasColumn('manufactur_orders', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('production_materials', 'branch_id'));

        $this->assertTrue(
            ManufacturOrders::forBranch(999)->whereKey($order->id)->exists(),
            'أمرٌ بلا فرع يجب أن يُرى من أي فرع'
        );
    }

    /* ══════════ هـ · لا عمود كميةٍ أو سعرٍ صحيح ══════════ */

    public function test_no_quantity_or_price_column_remains_an_integer(): void
    {
        $offenders = [];

        foreach (['production_materials_items', 'manufactur_orders_items', 'manufactur_orders'] as $table) {
            foreach (DB::select("SHOW COLUMNS FROM `{$table}`") as $column) {
                $isMoneyLike = (bool) preg_match('/(quantity|price|operating_time)$/', $column->Field);

                if ($isMoneyLike && str_contains(strtolower($column->Type), 'int')) {
                    $offenders[] = "{$table}.{$column->Field} ({$column->Type})";
                }
            }
        }

        $this->assertSame([], $offenders, 'أعمدة كمية أو سعر ما زالت صحيحة: '.implode(' · ', $offenders));
    }
}
