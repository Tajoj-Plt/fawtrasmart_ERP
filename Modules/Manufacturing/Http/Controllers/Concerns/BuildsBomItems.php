<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Enums\BomLineType;
use App\Models\CategoryUnit;
use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use App\Models\ProductUnit;
use Illuminate\Http\Request;

/**
 * كتابةُ بنود قائمة المواد — سطرٌ لكل بند، لا سطرٌ لكل مادّةٍ خام.
 *
 * ── ما كان يحدث ──────────────────────────────────────────────────────
 *
 * الشاشةُ أربعةُ جداول مستقلّة، وكلٌّ يرسل مصفوفتَه الخاصّة مرقَّمةً من
 * الصفر: `raw_product_id[]` و`expenses_account_id[]` و`workstation_id[]`
 * و`end_life_product_id[]`. فالفهرس 0 في المصروفات هو **أوّلُ مصروف**،
 * ولا صلة له بأوّل مادّةٍ خام.
 *
 * وكان `store` و`update` يمرّان على المواد الخام وحدها، ويحشران في كل
 * سطرٍ بياناتِ المصروف والعملية والهالك من **الفهرس نفسه**:
 *
 *     foreach ($request->raw_product_id as $index => $rawProductId) {
 *         ProductionMaterialsItem::create([
 *             'raw_product_id'      => $rawProductId,
 *             'expenses_account_id' => $request->expenses_account_id[$index] ?? 0,
 *             'workstation_id'      => $request->workstation_id[$index] ?? 0,
 *             'end_life_product_id' => $request->end_life_product_id[$index] ?? 0,
 *             …
 *
 * وينتج عن ذلك ثلاثةُ أعطال:
 *
 *  · **ضياعُ بنود.** قائمةٌ بمادّةٍ خام واحدة وثلاثةِ مصروفات تُنشئ
 *    سطراً واحداً — فيضيع مصروفان. وقائمةٌ بلا مواد خام أصلاً
 *    (تجميعٌ أو تعبئة) لا تُنشئ **شيئاً**: تضيع بنودُها كلّها.
 *
 *  · **خلطُ الأنواع.** السطرُ الواحد يحمل مادّةً خاماً ومصروفاً وعمليةً
 *    معاً. والقارئون يفرزون بـ`whereNotNull` — في شاشة التعديل وفي
 *    بناء أذون المستودع — فتتطابق المجموعاتُ الأربع وتصير التصفيةُ
 *    بلا أثر.
 *
 *  · **الأصفار.** `?? 0` يملأ المفاتيحَ الأجنبية بصفرٍ لا بـNULL،
 *    فيبقى `whereNotNull` صادقاً حتى حين لا يكون هناك مصروفٌ إطلاقاً.
 *
 * ── ما يفعله هذا ─────────────────────────────────────────────────────
 *
 * أربعُ حلقاتٍ مستقلّة، كلٌّ على مصفوفتها. وكلُّ سطرٍ يملأ أعمدةَ نوعه
 * ويترك البقيّة **NULL** — وهو ما يفترضه كلُّ قارئٍ لهذا الجدول.
 *
 * ويقابله في الأوامر [BuildsOrderItems] بالنهج نفسه: مسحٌ ثم إعادةُ بناء.
 *
 * ── المبالغ يحسبها الخادم ────────────────────────────────────────────
 *
 * كانت `raw_total` و`expenses_total` و`manu_total` و`end_life_total`
 * و`last_total_cost` تُكتب كما أرسلتها الشاشة. وحقولُها `readonly` —
 * حاجزٌ بصريّ لا أمنيّ. الآن تُهمَل المبالغُ المرسَلة كلُّها ويُعاد
 * حسابها هنا بصيغ الشاشة نفسها (`rowTotal()` في `bom/create.blade.php`):
 *
 *     المواد الخام   الكمية × سعر الوحدة
 *     المصروفات      المبلغ كما أُدخل — لا يُضرب في كمية الإنتاج
 *     التشغيل        تكلفة الساعة × وقت التشغيل
 *     الهالك         الكمية × سعر الوحدة
 *
 *     إجمالي القائمة = مواد + مصروفات + تشغيل − الهالك
 *
 * والهالكُ والنواتجُ الثانوية **تُطرح**: قيمتُها تعود للمنشأة فتُنقص
 * تكلفةَ المنتج — القاعدةُ نفسُها في أوامر التصنيع.
 *
 * ── المصروف الثابت يبقى ثابتاً ───────────────────────────────────────
 *
 * نوعُ التكلفة ١ «مبلغ ثابت» لا يُضرب في كمية الإنتاج في أيّ موضعٍ هنا،
 * ولا النوع ٢ «بناءً على الكمية»: مبلغُه في القائمة هو مبلغُ **كمية
 * القائمة**، ويُقاس بنسبة (كمية الأمر ÷ كمية القائمة) عند نسخه إلى أمر
 * التصنيع وحده. فالقائمةُ لا تقيس شيئاً.
 */
trait BuildsBomItems
{
    use RecalculatesTotals;

    /**
     * يمسح بنودَ القائمة ويُعيد بناءها من الطلب، ويُرجع إجماليَّ التكلفة
     * محسوباً في الخادم.
     *
     * يُستدعى داخل معاملةٍ مفتوحة — المسحُ والبناء لا ينفصلان.
     */
    protected function syncBomItems(ProductionMaterials $bom, Request $request): float
    {
        /*
            مسحٌ ثم إعادةُ بناء.

            كان التعديل يستعمل `updateOrCreate` مطابقاً على
            `'id' => $request->item_id[$index] ?? null`، والشاشةُ لا ترسل
            `item_id` إطلاقاً. فلا يطابق شيئاً أبداً ويُنشئ صفوفاً جديدة
            في كل حفظ — تتضاعف بنودُ القائمة مع كل تعديل.
        */
        $bom->ProductionMaterialsItem()->delete();

        $raw = $this->writeBomRawLines($bom, $request);
        $expenses = $this->writeBomExpenseLines($bom, $request);
        $operations = $this->writeBomOperationLines($bom, $request);
        $waste = $this->writeBomWasteLines($bom, $request);

        // الهالك يُطرح: قيمتُه تُنقص تكلفةَ المنتج — كما في أوامر التصنيع
        return $this->money($raw + $expenses + $operations - $waste);
    }

    /** المواد الخام — الكمية × سعر الوحدة، بالوحدة المختارة للسطر. */
    private function writeBomRawLines(ProductionMaterials $bom, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('raw_product_id', []) as $i => $productId) {
            if ($this->blank($productId)) {
                continue;
            }

            $quantity = $this->amount($request, 'raw_quantity', $i);
            $unitPrice = $this->money($this->amount($request, 'raw_unit_price', $i));
            $total = $this->lineTotal($quantity, $unitPrice);

            [$unitId, $factor] = $this->resolveBomUnit(
                (int) $productId,
                $request->input("raw_unit_id.{$i}"),
                $request->input("raw_conversion_factor.{$i}")
            );

            ProductionMaterialsItem::create([
                'production_material_id' => $bom->id,
                'line_type' => BomLineType::Raw,
                'raw_product_id' => (int) $productId,
                'raw_production_stage_id' => $this->reference($request, 'raw_production_stage_id', $i),
                'raw_unit_id' => $unitId,
                'raw_conversion_factor' => $factor,
                'raw_unit_price' => $unitPrice,
                'raw_quantity' => $quantity,
                'raw_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }

    /**
     * المصروفات — المبلغ كما أُدخل.
     *
     * «مبلغ ثابت» و«بناءً على الكمية» كلاهما يُحفظ هنا بمبلغه: القائمةُ
     * لا تضرب مصروفاً في كمية الإنتاج. الفرقُ بين النوعين يظهر عند نسخ
     * القائمة إلى أمرٍ بكميةٍ أخرى — الثابتُ يبقى، والثاني يُقاس.
     */
    private function writeBomExpenseLines(ProductionMaterials $bom, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('expenses_account_id', []) as $i => $accountId) {
            if ($this->blank($accountId)) {
                continue;
            }

            $amount = $this->money($this->amount($request, 'expenses_price', $i));

            ProductionMaterialsItem::create([
                'production_material_id' => $bom->id,
                'line_type' => BomLineType::Expense,
                'expenses_account_id' => (int) $accountId,
                'expenses_production_stage_id' => $this->reference($request, 'expenses_production_stage_id', $i),
                'expenses_cost_type' => $this->costType($request, 'expenses_cost_type', $i),
                'expenses_price' => $amount,
                'expenses_description' => (string) $request->input("expenses_description.{$i}", ''),
                'expenses_total' => $amount,
            ]);

            $sum += $amount;
        }

        return $sum;
    }

    /**
     * عمليات التشغيل — تكلفة الساعة × وقت التشغيل.
     *
     * كان الإجمالي يُحفظ بتكلفة الساعة وحدها: عمليةٌ بثلاث ساعاتٍ على
     * محطّةٍ ساعتُها 20 تُحسب 20 لا 60، فتخرج التكلفةُ المعيارية ناقصةً
     * بقدر كل ساعةٍ بعد الأولى.
     */
    private function writeBomOperationLines(ProductionMaterials $bom, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('workstation_id', []) as $i => $workstationId) {
            if ($this->blank($workstationId)) {
                continue;
            }

            $hourly = $this->money($this->amount($request, 'manu_total_cost', $i));
            $time = round($this->amount($request, 'operating_time', $i), 2);
            $total = $this->lineTotal($time, $hourly);

            ProductionMaterialsItem::create([
                'production_material_id' => $bom->id,
                'line_type' => BomLineType::Operation,
                'workstation_id' => (int) $workstationId,
                'manu_production_stage_id' => $this->reference($request, 'manu_production_stage_id', $i),
                'manu_cost_type' => $this->costType($request, 'manu_cost_type', $i),
                'operating_time' => $time,
                'manu_total_cost' => $hourly,
                'manu_description' => (string) $request->input("manu_description.{$i}", ''),
                'manu_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }

    /** الهالك والنواتج الثانوية — الكمية × سعر الوحدة، ويُطرح من الإجمالي. */
    private function writeBomWasteLines(ProductionMaterials $bom, Request $request): float
    {
        $sum = 0.0;

        foreach ((array) $request->input('end_life_product_id', []) as $i => $productId) {
            if ($this->blank($productId)) {
                continue;
            }

            $quantity = $this->amount($request, 'end_life_quantity', $i);
            $unitPrice = $this->money($this->amount($request, 'end_life_unit_price', $i));
            $total = $this->lineTotal($quantity, $unitPrice);

            ProductionMaterialsItem::create([
                'production_material_id' => $bom->id,
                'line_type' => BomLineType::Waste,
                'end_life_product_id' => (int) $productId,
                'end_life_production_stage_id' => $this->reference($request, 'end_life_production_stage_id', $i),
                'end_life_unit_price' => $unitPrice,
                'end_life_quantity' => $quantity,
                'end_life_total' => $total,
            ]);

            $sum += $total;
        }

        return $sum;
    }

    /**
     * وحدةُ السطر ومعاملُ تحويلها — `[raw_unit_id, raw_conversion_factor]`.
     *
     * قائمةُ «الوحدة» في الشاشة تُرسل إحدى ثلاث قيم:
     *
     *     ''              الوحدة الأساسية للمنتج        ← [null, 1]
     *     '17'            وحدةٌ خاصّة بالمنتج           ← [17, معاملها]
     *                     (`product_units.id`)
     *     'category_12'   وحدةٌ من وحدات التصنيف        ← [null, معاملها]
     *                     (`category_unit.id`) — للمنتج الذي لا وحداتَ
     *                     خاصّةً له
     *
     * و`raw_unit_id` عمودٌ `bigint` يشير إلى `product_units` وحده
     * ([ProductionMaterialsItem::rawUnit]): فالقيمةُ النصّية `category_12`
     * لا تُكتب فيه إطلاقاً — كانت تسقط بالحفظ أو تصير صفراً. يُحفظ منها
     * **معاملُ التحويل** وحده، وهو ما يحتاجه الحساب؛ وشاشةُ التعديل
     * تُعيد اختيار الوحدة بالمعرّف إن وُجد وإلّا بالمعامل.
     *
     * والمعاملُ يُقرأ من جدول الوحدات لا من الحقل المخفي: الحقلُ احتياطٌ
     * حين لا تُعرف الوحدة.
     */
    private function resolveBomUnit(int $productId, mixed $unit, mixed $postedFactor): array
    {
        $fallback = (float) $postedFactor > 0 ? (float) $postedFactor : 1.0;

        if ($unit === null || $unit === '') {
            // بلا وحدةٍ مختارة — الأساسية؛ والمعاملُ المرسَل يُحترم إن وُجد
            return [null, $fallback];
        }

        if (is_string($unit) && preg_match('/^category_(\d+)$/', $unit, $match)) {
            $factor = (float) CategoryUnit::whereKey((int) $match[1])->value('conversion_factor');

            return [null, $factor > 0 ? $factor : $fallback];
        }

        if (! is_numeric($unit) || (int) $unit <= 0) {
            return [null, $fallback];
        }

        // وحدةُ منتجٍ آخر لا تُقبل: المعرّف يُكتب في العنوان باليد
        $productUnit = ProductUnit::with('categoryUnit')
            ->where('product_id', $productId)
            ->whereKey((int) $unit)
            ->first();

        if (! $productUnit) {
            return [null, $fallback];
        }

        $factor = (float) optional($productUnit->categoryUnit)->conversion_factor;

        return [(int) $productUnit->id, $factor > 0 ? $factor : $fallback];
    }

    /** نوعُ التكلفة: ١ ثابت · ٢ بناءً على الكمية · ٣ معادلة — والافتراضُ ثابت. */
    private function costType(Request $request, string $field, int $index): int
    {
        $value = (int) $request->input("{$field}.{$index}");

        return in_array($value, [1, 2, 3], true) ? $value : 1;
    }

    /** مفتاحٌ أجنبي: الفارغ والصفر كلاهما غياب — وNULL هو تعبيرُ الغياب. */
    private function reference(Request $request, string $field, int $index): ?int
    {
        $value = $request->input("{$field}.{$index}");

        return $this->blank($value) ? null : (int) $value;
    }

    /** رقم: الصفر قيمةٌ صحيحة، والفارغ يصير صفراً. */
    private function amount(Request $request, string $field, int $index): float
    {
        return (float) ($request->input("{$field}.{$index}") ?: 0);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || $value === '' || (int) $value === 0;
    }
}
