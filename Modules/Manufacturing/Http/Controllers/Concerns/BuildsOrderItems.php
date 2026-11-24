<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Enums\BomLineType;
use App\Models\IndirectCost;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use Illuminate\Http\Request;
use Modules\Manufacturing\Services\IndirectCostAllocator;

/**
 * بناء بنود أمر التصنيع — بمبالغ يحسبها الخادم لا يقبلها من الطلب.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * كل مبلغ في المديول كان يُحسب في JavaScript ويُقبل كما هو:
 *
 *     'raw_total'      => $request->raw_total[$index] ?? null,
 *     'expenses_total' => $request->expenses_total[$index] ?? null,
 *     'manu_total'     => $request->manu_total[$index] ?? null,
 *     'end_life_total' => $request->end_life_total[$index] ?? null,
 *     'last_total_cost'=> $request->last_total_cost ?? 0,
 *
 * و`completeOrder` يبني قيود «مخزون تحت التشغيل» من مجموع هذه الحقول
 * نفسها. فتعديل حقلٍ مخفي في أدوات المطوّر يكتب **قيد يومية مُرحَّلاً
 * بالمبلغ الذي يريده المستخدم** — والقيد يبقى متوازناً شكلاً فيجتاز حارس
 * التوازن، ويدخل المنتج المستودعَ بتكلفة مخترَعة تتبعها كل بيعة لاحقاً.
 *
 * والحقول `readonly` في الواجهة، وهو حاجزٌ بصري لا أمني: السمة تُزال من
 * أدوات المطوّر، والطلب يُرسَل بأي أداة.
 *
 * ── الصيغ ────────────────────────────────────────────────────────────
 *
 * منقولةٌ حرفياً عن `rowTotal()` في `orders/partials/form.blade.php`،
 * فلا يختلف ما يراه المستخدم عمّا يُحفظ:
 *
 *     المواد الخام   الكمية × سعر الوحدة
 *     المصروفات      المبلغ كما هو            (حقل مبلغ لا معادلة)
 *     التشغيل        تكلفة الساعة × وقت التشغيل
 *     الهالك         الكمية × سعر الوحدة
 *
 *     إجمالي الأمر   مواد + مصروفات + تشغيل   (الهالك مخرَجٌ لا تكلفة)
 *
 * ── ولماذا سمة واحدة ─────────────────────────────────────────────────
 *
 * الحلقات الأربع كانت مكرّرة حرفياً بين `store` و`update` — ثماني كتل
 * بنحو 145 سطراً متطابقة. وقد بدأ التباعد فعلاً: `store` يكتب `?? null`
 * و`OrdersController` المحذوف كان يكتب `?? 0`. نقطةٌ واحدة تمنع تكرار
 * ذلك، وتضمن أن أي إصلاح مستقبلي يسري على المسارين معاً.
 */
trait BuildsOrderItems
{
    /**
     * يحذف بنود الأمر ويُعيد بناءها من الطلب، ويُرجع إجمالي التكلفة.
     *
     * يُستدعى داخل معاملة مفتوحة — الحذف والبناء لا ينفصلان.
     */
    protected function syncOrderItems(ManufacturOrders $order, Request $request): float
    {
        $order->manufacturOrdersItem()->delete();

        $raw = $this->buildRawLines($order, $request);
        $expenses = $this->buildExpenseLines($order, $request);
        $labour = $this->buildLabourLines($order, $request);

        // المصروف غير المباشر بعد المباشر: «بحسب التكلفة» يُحسب على مجموعه
        $indirect = $this->buildIndirectLines($order, $request, $raw + $expenses + $labour);

        /*
            الهالك يُطرح بقيمته — القاعدةُ الواحدة في الشاشة والخادم وقائمة المواد.

            كانت الشاشةُ تطرحه والمحفوظُ لا يطرحه، فيرى المستخدم رقماً
            ويُحفظ غيرُه. والهالكُ ذو القيمة يدخل المخزونَ بقيمته عند
            الإتمام، فما يتحمّله المنتجُ هو الباقي:

                الإجمالي = مواد + مصروفات + تشغيل + غير مباشرة − هالك
        */
        $waste = $this->buildWasteLines($order, $request);

        return $this->money(max(0.0, $raw + $expenses + $labour + $indirect - $waste));
    }

    /**
     * المصروفات غير المباشرة — نصيبُ الأمر من كلّ تكلفةٍ مختارة.
     *
     * يُختار المستندُ في الشاشة، والمبلغُ يحسبه الخادم:
     *
     *     بحسب الكمية   نصيب الوحدة × كمية الأمر
     *     بحسب التكلفة  نصيب الوحدة × التكلفة المباشرة للأمر
     *
     * ويُحفظ بندَ مصروفٍ على حساب التكلفة نفسِه، فيدخل تكلفةَ الأمر ويُرحَّل
     * مع قيد الإتمام (مدين: مخزون تحت التشغيل، دائن: حساب المصروف) —
     * ومنه تكلفةُ الوحدة التي يدخل بها المنتجُ المخزون.
     *
     * والمستندُ نفسُه لا يُحمَّل مرّتين على الأمر الواحد.
     */
    private function buildIndirectLines(ManufacturOrders $order, Request $request, float $directCost): float
    {
        $sum = 0.0;
        $seen = [];
        $allocator = app(IndirectCostAllocator::class);

        foreach ((array) $request->input('indirect_cost_id', []) as $i => $costId) {
            $costId = (int) $costId;

            if ($costId <= 0 || isset($seen[$costId])) {
                continue;
            }

            $seen[$costId] = true;

            $cost = IndirectCost::find($costId);

            if (! $cost || ! $cost->account_id) {
                continue;
            }

            $amount = $allocator->shareFor($cost, (float) $order->quantity, $directCost);

            ManufacturOrdersItem::create([
                'manufactur_order_id' => $order->id,
                'line_type' => BomLineType::Expense,
                'indirect_cost_id' => $cost->id,
                'expenses_account_id' => $cost->account_id,
                // ٢ = «بناءً على الكمية» في قائمة أنواع التكلفة
                'expenses_cost_type' => 2,
                'expenses_production_stage_id' => $request->input("indirect_stage_id.$i") ?: null,
                'expenses_price' => $amount,
                'expenses_description' => 'مصروف غير مباشر '.($cost->code ?: '#'.$cost->id),
                'expenses_total' => $amount,
            ]);

            $sum += $amount;
        }

        return $sum;
    }

    /** المواد الخام — الكمية × سعر الوحدة. */
    private function buildRawLines(ManufacturOrders $order, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('raw_product_id', []) as $i => $productId) {
            if (empty($productId)) {
                continue;
            }

            $quantity = (float) ($request->input("raw_quantity.$i") ?? 0);
            $unitPrice = (float) ($request->input("raw_unit_price.$i") ?? 0);
            $total = $this->lineTotal($quantity, $unitPrice);

            ManufacturOrdersItem::create([
                'manufactur_order_id' => $order->id,
                'line_type' => BomLineType::Raw,
                'raw_product_id' => $productId,
                'raw_production_stage_id' => $request->input("raw_production_stage_id.$i") ?: null,
                /*
                    معرّفُ وحدة الصنف إن كان رقماً — وإلّا فارغ.

                    وحدةُ التصنيف (صنفٌ بلا وحداتٍ خاصّة) لا صفَّ لها في
                    `product_units`، وكانت الشاشةُ ترسلها نصّاً
                    (`category_12`) إلى عمودٍ رقميٍّ فيسقط الحفظُ كلُّه.
                    المعاملُ هو ما يُعتدّ به في الصرف والحجز، وهو محفوظٌ
                    في السطر التالي؛ وبه تُعاد الوحدةُ في شاشة التعديل.
                */
                'raw_unit_id' => is_numeric($request->input("raw_unit_id.$i")) ? (int) $request->input("raw_unit_id.$i") : null,
                'raw_conversion_factor' => (float) ($request->input("raw_conversion_factor.$i") ?: 1),
                'raw_unit_price' => $this->unitPrice($unitPrice),
                'raw_quantity' => $quantity,
                'raw_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }

    /** المصروفات — المبلغ كما أُدخل، لا معادلة. */
    private function buildExpenseLines(ManufacturOrders $order, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('expenses_account_id', []) as $i => $accountId) {
            if (empty($accountId)) {
                continue;
            }

            $amount = $this->money($request->input("expenses_price.$i") ?? 0);

            ManufacturOrdersItem::create([
                'manufactur_order_id' => $order->id,
                'line_type' => BomLineType::Expense,
                'expenses_account_id' => $accountId,
                'expenses_cost_type' => $request->input("expenses_cost_type.$i") ?: null,
                'expenses_production_stage_id' => $request->input("expenses_production_stage_id.$i") ?: null,
                'expenses_price' => $amount,
                'expenses_description' => $request->input("expenses_description.$i"),
                'expenses_total' => $amount,
            ]);

            $sum += $amount;
        }

        return $sum;
    }

    /** التشغيل — تكلفة الساعة × وقت التشغيل. */
    private function buildLabourLines(ManufacturOrders $order, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('workstation_id', []) as $i => $workstationId) {
            if (empty($workstationId)) {
                continue;
            }

            $hourly = (float) ($request->input("manu_total_cost.$i") ?? 0);
            $time = (float) ($request->input("operating_time.$i") ?? 0);
            $total = $this->lineTotal($time, $hourly);

            ManufacturOrdersItem::create([
                'manufactur_order_id' => $order->id,
                'line_type' => BomLineType::Operation,
                'workstation_id' => $workstationId,
                'operating_time' => $time,
                'manu_production_stage_id' => $request->input("manu_production_stage_id.$i") ?: null,
                'manu_cost_type' => $request->input("manu_cost_type.$i") ?: null,
                'manu_total_cost' => $this->unitPrice($hourly),
                'manu_description' => $request->input("manu_description.$i"),
                'manu_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }

    /** الهالك والنواتج الثانوية — الكمية × سعر الوحدة. */
    private function buildWasteLines(ManufacturOrders $order, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('end_life_product_id', []) as $i => $productId) {
            if (empty($productId)) {
                continue;
            }

            $quantity = (float) ($request->input("end_life_quantity.$i") ?? 0);
            $unitPrice = (float) ($request->input("end_life_unit_price.$i") ?? 0);
            $total = $this->lineTotal($quantity, $unitPrice);

            ManufacturOrdersItem::create([
                'manufactur_order_id' => $order->id,
                'line_type' => BomLineType::Waste,
                'end_life_product_id' => $productId,
                'end_life_production_stage_id' => $request->input("end_life_production_stage_id.$i") ?: null,
                'end_life_unit_price' => $this->unitPrice($unitPrice),
                'end_life_quantity' => $quantity,
                'end_life_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }
}
