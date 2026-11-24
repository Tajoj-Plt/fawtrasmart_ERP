<?php

namespace Modules\Manufacturing\Services;

use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use Illuminate\Support\Facades\DB;

/**
 * نقطة واحدة لكل مسّ لمخزون التصنيع.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * الموديول يلمس `product_details` في أربعة عشر موضعاً بأربعة أنماط
 * مختلفة، وفيها ثلاثة عيوب متكرّرة:
 *
 * 1. **صفر أقفال.** `grep -c lockForUpdate` على الموديول كله = 0.
 *    الفحص والخصم منفصلان بعشرات السطور:
 *
 *        $pd = ProductDetails::where(...)->first();      // يقرأ 100
 *        if ($pd->quantity < $qty) { ...نقص... }         // يجتاز
 *        // ... عشرات السطور وإنشاء إذن ...
 *        $pd->quantity -= $qty;  $pd->save();            // يكتب 20
 *
 *    أمران متزامنان يطلبان 80 من رصيد 100: كلاهما يقرأ 100 ويجتاز،
 *    ثم يكتب كلٌّ منهما 20 ⇒ الرصيد 20 بدل −60، أي **أُنشئ 60 من العدم**.
 *
 * 2. **الكتابة فوق `unit_price`.** `$pd->unit_price = $unitPrice;` تمحو
 *    تكلفة الرصيد القائم: 1000 وحدة بـ10 تصير كلها بـ50 عند إضافة
 *    10 وحدات بـ50 ⇒ تضخيم فوري بـ40,000 بلا قيد مقابل.
 *
 * 3. **`updateOrCreate` مع `DB::raw("quantity - $qty")`** في BOMController:
 *    عند غياب السجل ينفّذ INSERT بعبارة تشير إلى العمود نفسه ⇒
 *    `Unknown column 'quantity' in 'field list'` ⇒ **أول صرف لمادة في
 *    مستودع لا سجل لها فيه يفشل ويُرجع المعاملة كاملة**.
 *
 * ── التصميم ──────────────────────────────────────────────────────────
 *
 * `product_details` **رصيد جارٍ** — صفّ واحد لكل (منتج، مستودع). هذا ما
 * تفعله وحدة المخزون في `addToWarehouse`/`removeFromWarehouse`، وهي
 * المرجع. الأعمدة `type` و`date` و`type_of_operation` و`subaccount`
 * بقايا تصميم أقدم لا يستعملها المسار الحيّ.
 *
 * الخصم **ذرّي مشروط**: `decrement` مع `where('quantity','>=',$qty)`
 * يكتب `quantity = quantity - N` في الخادم، فلا سباق أصلاً، والشرط
 * يمنع الرصيد السالب بلا قفل ولا قراءة مسبقة.
 *
 * الإضافة **بمتوسط مرجّح** تحت `lockForUpdate` — القفل لازم هنا لأن
 * حساب المتوسط يحتاج قراءة القيمة القديمة.
 *
 * ── الاستعمال ────────────────────────────────────────────────────────
 *
 *     $inv = app(InventoryService::class);
 *
 *     // داخل معاملة مفتوحة دائماً
 *     $inv->issue($productId, $warehouseId, $qty);
 *     $inv->receive($productId, $warehouseId, $qty, $unitPrice);
 *
 * كل الدوال ترمي `\RuntimeException` عند تعذّر العملية — لا تُرجع false
 * ولا تبتلع: الرسالة تصعد إلى `catch` الخارجي فيُرجَع كل شيء.
 */
class InventoryService
{
    /** دقة الكميات — للمقارنات لا للتخزين. */
    private const QTY_EPSILON = 0.0001;

    /** دقة سعر الوحدة عند حساب المتوسط المرجّح. */
    private const PRICE_SCALE = 6;

    // ═════════════════════════════════════════════════════════════════
    // القراءة
    // ═════════════════════════════════════════════════════════════════

    /**
     * الرصيد المتاح لمنتج في مستودع.
     */
    public function available(int $productId, int $warehouseId): float
    {
        return (float) (ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    /**
     * تكلفة الوحدة المخزَّنة — للتسعير عند الصرف.
     */
    public function unitCost(int $productId, int $warehouseId): float
    {
        /*
            «سعر التكلفة» في النظام كلِّه هو متوسطُ تكلفة الصنف المرجَّح من
            أذون الإضافة — الرقمُ المعروض في بطاقة الصنف، وبه تُقيَّد تكلفةُ
            المبيعات ([\App\Models\Product::averageCost()]).

            كان الصرفُ هنا يقرأ `product_details.unit_price` للمستودع. وذلك
            العمودُ تكتبه «استلام بضاعة» في المشتريات بسعر **آخر استلام** لا
            بالمتوسط، وأذونُ الرصيد الافتتاحي تتركه فارغاً — فيخرج قيدُ صرف
            المواد بتكلفةٍ غير تكلفة الصنف: مرّةً بآخر سعر ومرّةً بصفر.

            الترتيب: متوسطُ الصنف، ثمّ سعرُ المستودع المخزَّن، ثمّ سعرُ الشراء.
        */
        $average = $this->averageCosts([$productId])[$productId] ?? 0.0;

        if ($average > 0) {
            return $average;
        }

        $stored = (float) (ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->value('unit_price') ?? 0);

        if ($stored > 0) {
            return $stored;
        }

        return (float) (Product::where('id', $productId)->value('purchase_price') ?? 0);
    }

    /**
     * متوسطُ تكلفة الأصناف — صيغةُ [\App\Models\Product::averageCost()] نفسُها
     * لمجموعة أصنافٍ في استعلامٍ واحد، وبلا تقريبٍ إلى خانتين.
     *
     *     Σ قيمة سطور أذون الإضافة ÷ Σ كمياتها
     *
     * وتُستبعد المصادرُ نفسُها (التحويلات، المبيعات ومرتجعاتها، الصرف
     * والسحب) ومعها صرفُ مواد التصنيع وإرجاعُها: حركاتٌ تُخرج المادة أو
     * تعيدها بتكلفتها، فلا تدخل في تكوين التكلفة.
     *
     * @param  array<int, int>|null  $productIds  null = كل الأصناف
     * @return array<int, float>
     */
    public function averageCosts(?array $productIds = null): array
    {
        $sources = \App\Constants\permissions\PermissionSources::class;

        $excluded = [
            $sources::MANUAL_TRANSFER,
            $sources::POS_SHIFT_OUTBOUND,
            $sources::POS_SHIFT_INBOUND,
            $sources::SALES_INVOICE,
            $sources::SALES_RETURN,
            $sources::SALES_CREDIT_NOTE,
            $sources::WAREHOUSE_DISBURSEMENT,
            $sources::WAREHOUSE_WITHDRAWAL,
            $sources::MANUFACTURING_DISBURSEMENT,
            $sources::MANUFACTURING_RETURNS,
        ];

        return DB::table('warehouse_permits_products as l')
            ->join('warehouse_permits as p', 'p.id', '=', 'l.warehouse_permits_id')
            ->whereNotIn('p.permission_source_id', $excluded)
            ->where(function ($q) {
                $q->whereNull('p.status')->orWhereNotIn('p.status', ['reversed']);
            })
            ->where('l.quantity', '>', 0)
            ->where('l.unit_price', '>', 0)
            ->when($productIds !== null, fn ($q) => $q->whereIn('l.product_id', $productIds ?: [0]))
            ->groupBy('l.product_id')
            ->selectRaw(
                'l.product_id, SUM(CASE WHEN l.total > 0 AND l.total < l.quantity * l.unit_price'
                .' THEN l.total ELSE l.quantity * l.unit_price END) / NULLIF(SUM(l.quantity), 0) AS cost'
            )
            ->pluck('cost', 'product_id')
            ->map(fn ($cost) => round((float) $cost, 6))
            ->all();
    }

    /**
     * فحص توفّر مجموعة مواد دفعةً واحدة — قبل بدء أي كتابة.
     *
     * يُرجع قائمة النواقص فارغةً إن كان كل شيء متاحاً.
     *
     * ⚠️ الفحص المسبق **لا يغني عن الشرط الذرّي في `issue()`**: بينهما
     * فجوة زمنية قد يخصم فيها طلبٌ آخر. الفحص هنا لرسالة مفيدة للمستخدم،
     * والضمان في `issue()` وحدها.
     *
     * @param  array<int, array{product_id:int, quantity:float}>  $lines
     * @return array<int, array{product_id:int, name:string, required:float, available:float, shortage:float}>
     */
    public function shortagesFor(array $lines, int $warehouseId): array
    {
        $productIds = array_values(array_unique(array_column($lines, 'product_id')));

        if ($productIds === []) {
            return [];
        }

        // استعلامان لا استعلامان لكل بند
        $balances = ProductDetails::whereIn('product_id', $productIds)
            ->where('store_house_id', $warehouseId)
            ->pluck('quantity', 'product_id');

        $names = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $shortages = [];

        foreach ($lines as $line) {
            $pid = (int) $line['product_id'];
            $required = (float) $line['quantity'];

            if ($required <= 0) {
                continue;
            }

            $availableQty = (float) ($balances[$pid] ?? 0);

            if ($availableQty + self::QTY_EPSILON < $required) {
                $shortages[] = [
                    'product_id' => $pid,
                    'name' => $names[$pid] ?? ('#'.$pid),
                    'required' => $required,
                    'available' => $availableQty,
                    'shortage' => round($required - $availableQty, 4),
                ];
            }
        }

        return $shortages;
    }

    // ═════════════════════════════════════════════════════════════════
    // الكتابة
    // ═════════════════════════════════════════════════════════════════

    /**
     * صرف كمية من مستودع — ذرّي ومشروط.
     *
     * @throws \RuntimeException إن لم يكفِ الرصيد
     */
    public function issue(int $productId, int $warehouseId, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        // `decrement` يكتب `quantity = quantity - N` في الخادم، والشرط
        // `>=` يُقيَّم في نفس العبارة ⇒ لا فجوة بين الفحص والخصم.
        $affected = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->where('quantity', '>=', $quantity)
            ->decrement('quantity', $quantity);

        if ($affected === 0) {
            throw new \RuntimeException(sprintf(
                'رصيد غير كافٍ للمادة «%s» في المستودع المحدَّد — المتوفر %s والمطلوب %s.',
                $this->productName($productId),
                $this->formatQty($this->available($productId, $warehouseId)),
                $this->formatQty($quantity)
            ));
        }
    }

    /**
     * صرفُ كميةٍ **بتكلفتها هي** — عكسُ إضافةٍ سابقة.
     *
     * `issue()` تُنقص الكمية وتُبقي متوسطَ التكلفة كما هو، وهذا صحيحٌ في
     * الصرف العاديّ (ما يخرج يخرج بالمتوسط). لكنّ عكسَ إذن إضافةٍ يُخرج
     * كميةً دخلت بتكلفةٍ بعينها ورفعت المتوسطَ أو خفضته — فإخراجُها
     * بالمتوسط يُبقي أثرَها فيه. هنا يُعاد المتوسطُ إلى ما كان:
     *
     *     المتوسط = (الكمية × المتوسط − المُخرَج × تكلفته) ÷ الباقي
     *
     * @throws \RuntimeException إن لم يكفِ الرصيد
     */
    public function issueAtCost(int $productId, int $warehouseId, float $quantity, float $unitPrice): void
    {
        if ($quantity <= 0) {
            return;
        }

        $row = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $row || (float) $row->quantity + self::QTY_EPSILON < $quantity) {
            throw new \RuntimeException(sprintf(
                'رصيد غير كافٍ للمادة «%s» في المستودع المحدَّد — المتوفر %s والمطلوب %s.',
                $this->productName($productId),
                $this->formatQty((float) ($row->quantity ?? 0)),
                $this->formatQty($quantity)
            ));
        }

        $oldQty = (float) $row->quantity;
        $oldPrice = (float) $row->unit_price;
        $newQty = max(0.0, $oldQty - $quantity);

        if ($newQty > self::QTY_EPSILON && $unitPrice > 0) {
            $restored = (($oldQty * $oldPrice) - ($quantity * $unitPrice)) / $newQty;

            // قيمةٌ سالبة تعني أن الرصيد تبدّل بحركاتٍ أخرى — يبقى المتوسطُ كما هو
            if ($restored >= 0) {
                $row->unit_price = round($restored, self::PRICE_SCALE);
            }
        }

        $row->quantity = $newQty;
        $row->save();
    }

    /**
     * إضافة كمية إلى مستودع بمتوسط تكلفة مرجّح.
     *
     * المتوسط لا يُطبَّق حين تكون التكلفة الواردة صفراً (مواد مرتجعة بلا
     * سعر مثلاً): إضافة كمية بتكلفة صفر تخفض متوسط الرصيد كله ظلماً.
     */
    public function receive(int $productId, int $warehouseId, float $quantity, float $unitPrice = 0): void
    {
        if ($quantity <= 0) {
            return;
        }

        $row = ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            ProductDetails::create([
                'product_id' => $productId,
                'store_house_id' => $warehouseId,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, self::PRICE_SCALE),
            ]);

            return;
        }

        $oldQty = (float) $row->quantity;
        $oldPrice = (float) $row->unit_price;
        $newQty = $oldQty + $quantity;

        // المتوسط المرجّح — بدلاً من الكتابة فوق السعر القديم
        if ($unitPrice > 0 && $newQty > 0) {
            $row->unit_price = round(
                (($oldQty * $oldPrice) + ($quantity * $unitPrice)) / $newQty,
                self::PRICE_SCALE
            );
        }

        $row->quantity = $newQty;
        $row->save();
    }

    /**
     * عكس إذن مخزني كامل — يخدم الإلغاء والتراجع والحذف بمنطق واحد.
     *
     * إذن الصرف يُعكس بإضافة، وإذن الإضافة يُعكس بصرف. يُرجع مجموع
     * القيمة المعكوسة.
     *
     * @throws \RuntimeException إن لم يكفِ الرصيد لعكس إذن إضافة
     */
    public function reversePermit(WarehousePermits $permit, bool $restoreCost = false): float
    {
        $lines = WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->get();
        $warehouseId = (int) $permit->store_houses_id;
        $total = 0.0;

        foreach ($lines as $line) {
            if (! $line->product_id || $line->quantity <= 0) {
                continue;
            }

            $lineWarehouse = (int) ($line->store_house_id ?? $warehouseId);
            $qty = (float) $line->quantity;

            if ((int) $permit->permission_type === 2) {
                // كان صرفاً ⇒ العكس إضافة، بتكلفة السطر نفسها
                $this->receive($line->product_id, $lineWarehouse, $qty, (float) $line->unit_price);
            } else {
                // كان إضافة ⇒ العكس صرف
                if ($restoreCost) {
                    $this->issueAtCost($line->product_id, $lineWarehouse, $qty, (float) $line->unit_price);
                } else {
                    $this->issue($line->product_id, $lineWarehouse, $qty);
                }
            }

            $total += (float) $line->total;
        }

        return round($total, 2);
    }

    // ═════════════════════════════════════════════════════════════════
    // بناء الإذونات
    // ═════════════════════════════════════════════════════════════════

    /**
     * يضيف بند إذن ويحرّك المخزون معه، ويسجّل الرصيد قبل وبعد.
     *
     * `stock_before` و`stock_after` كانا يُكتبان صفراً ثابتاً في كل
     * السطور (BOMController:530، 617، 638)، ودالة قراءتهما
     * `getProductStockBefore` جسمها معلَّق وتُرجع 0 دائماً — فسجلّ حركة
     * المخزون بلا قيمة تدقيقية. هنا يُكتبان فعلاً.
     *
     * @param  string  $direction  'issue' أو 'receive'
     */
    public function addPermitLine(
        WarehousePermits $permit,
        int $productId,
        float $quantity,
        float $unitPrice,
        string $direction,
        ?int $warehouseId = null
    ): WarehousePermitsProducts {
        $warehouseId = $warehouseId ?? (int) $permit->store_houses_id;

        $before = $this->available($productId, $warehouseId);

        if ($direction === 'issue') {
            $this->issue($productId, $warehouseId, $quantity);
        } else {
            $this->receive($productId, $warehouseId, $quantity, $unitPrice);
        }

        $after = $this->available($productId, $warehouseId);

        return WarehousePermitsProducts::create([
            'warehouse_permits_id' => $permit->id,
            'product_id' => $productId,
            // مستودع البند صراحةً: الإذن قد يسحب من أكثر من مستودع، ورأسه
            // يحمل واحداً فقط. يتطلّب الهجرة `..._000007`.
            'store_house_id' => $warehouseId,
            'quantity' => $quantity,
            'unit_price' => round($unitPrice, self::PRICE_SCALE),
            'total' => round($quantity * $unitPrice, 2),
            'stock_before' => $before,
            'stock_after' => $after,
        ]);
    }

    /**
     * يُغلق الإذن بإجمالٍ مشتقٍّ من بنوده.
     *
     * `grand_total` كان يُكتب مسبقاً بقيمة «تشمل الهالك» بينما تُضاف
     * بنود المنتج الرئيسي وحدها — ودالة إضافة بنود الهالك
     * `addWasteMaterialsToSamePermit` لا تُستدعى إطلاقاً. فرأس الإذن
     * لا يطابق بنوده أبداً. هنا يُشتقّ منها فلا يمكن أن يختلف.
     */
    public function sealPermit(WarehousePermits $permit): float
    {
        $total = (float) WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->sum('total');

        $permit->update(['grand_total' => round($total, 2)]);

        return round($total, 2);
    }

    // ═════════════════════════════════════════════════════════════════
    // مساعدات
    // ═════════════════════════════════════════════════════════════════

    private function productName(int $productId): string
    {
        return (string) (Product::where('id', $productId)->value('name') ?: '#'.$productId);
    }

    private function formatQty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * تأكيد أننا داخل معاملة — حارس تطوير.
     *
     * كل دوال الكتابة أعلاه تفترض معاملة مفتوحة. استدعِها في بداية أي
     * عملية مركّبة أثناء التطوير للتأكد.
     *
     * @throws \LogicException
     */
    public function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'InventoryService يجب أن يُستدعى داخل DB::transaction — وإلا بقي أثرٌ مخزني بلا مستند عند الفشل.'
            );
        }
    }
}
