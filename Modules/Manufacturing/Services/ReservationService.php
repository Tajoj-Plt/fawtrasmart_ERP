<?php

namespace Modules\Manufacturing\Services;

use App\Enums\ReferenceType;
use App\Models\ManufacturingGeneralSetting;
use App\Models\ManufacturOrders;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Schema;

/**
 * حجزُ المواد — الفرق بين «موجود» و«متاح».
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * للمخزون رقمٌ واحد اليوم: الرصيد الفعلي. فأمرُ تصنيعٍ يحتاج ١٠٠ كجم
 * دقيق لا يترك أثراً حتى لحظة الصرف — وبينهما يُنشئ زميلٌ أمراً ثانياً
 * على الكمية نفسها، فيَعِد الاثنان بما لا يكفيهما، ولا يظهر التعارض
 * إلا حين يفشل صرفُ أحدهما وقد وُعد العميلُ بموعد.
 *
 *     المتاح  =  الرصيد الفعلي  −  المحجوز النشط
 *
 * ── دورة الحجز ───────────────────────────────────────────────────────
 *
 *     إنشاء الأمر     →  active     (يُخصم من المتاح)
 *     استلام المواد   →  consumed   (صار حركةَ مخزون — لا يُخصم مرّتين)
 *     إلغاء أو حذف    →  released   (فُكّ بلا صرف)
 *
 * **ولا يُحذف صفٌّ أبداً.** الحجزُ سجلٌّ يُسأل عنه: «لماذا لم يكن المتاح
 * كافياً يوم الثلاثاء؟». الحذفُ يمحو الجواب.
 *
 * ── حجزُ الأمر لا يُحجب عن نفسه ──────────────────────────────────────
 *
 * حين يأتي الأمرُ ليصرف مادّته، يجب ألّا يُحسب حجزُه هو ضمن «المحجوز» —
 * وإلا منع نفسَه من أخذ ما حجزه. فكلُّ دوالّ القياس تقبل استثناءَ مستند.
 */
class ReservationService
{
    /** فرقٌ أصغر من هذا ضجيجُ فاصلةٍ عشرية لا نقصٌ حقيقي. */
    private const QTY_EPSILON = 0.0001;

    /**
     * هل الحجز مُفعَّل؟
     *
     * الافتراضُ **مُفعَّل** حتى مع غياب صفّ الإعدادات أو العمود: المنعُ
     * الصامت أسوأ من الرقم الزائد. ومنشأةٌ لا تريده تُطفئه صراحةً.
     */
    public function enabled(): bool
    {
        if (! Schema::hasTable('manufacturing_general_settings')
            || ! Schema::hasColumn('manufacturing_general_settings', 'reserve_materials_on_order')) {
            return true;
        }

        return (bool) ManufacturingGeneralSetting::value('reserve_materials_on_order', true);
    }

    // ═════════════════════════════════════════════════════════════════
    // القياس
    // ═════════════════════════════════════════════════════════════════

    /** الرصيدُ الفعلي — ما في المستودع بصرف النظر عن الوعود. */
    public function onHand(int $productId, int $warehouseId): float
    {
        return (float) (ProductDetails::where('product_id', $productId)
            ->where('store_house_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    /**
     * المحجوزُ النشط لمنتجٍ في مستودع.
     *
     * `$exceptReferenceId` يستثني حجوزَ مستندٍ بعينه، كي لا يمنع الأمرُ
     * نفسَه من صرف ما حجزه.
     */
    public function reserved(
        int $productId,
        int $warehouseId,
        ?int $exceptReferenceId = null,
        string $referenceType = ReferenceType::MANUFACTURING_ORDER->value
    ): float {
        $query = StockReservation::query()
            ->active()
            ->where('product_id', $productId)
            ->where('store_house_id', $warehouseId);

        if ($exceptReferenceId !== null) {
            $query->whereNot(function ($q) use ($exceptReferenceId, $referenceType) {
                $q->where('reference_type', $referenceType)
                    ->where('reference_id', $exceptReferenceId);
            });
        }

        return (float) $query->sum('quantity');
    }

    /** المتاحُ للتصرّف = الفعلي − المحجوز (باستثناء حجز المستند نفسه). */
    public function available(int $productId, int $warehouseId, ?int $exceptReferenceId = null): float
    {
        return round(
            $this->onHand($productId, $warehouseId) - $this->reserved($productId, $warehouseId, $exceptReferenceId),
            4
        );
    }

    /**
     * الأرقامُ الثلاثة معاً — لكل شاشةٍ تعرض رصيداً.
     *
     * @return array{on_hand:float, reserved:float, available:float}
     */
    public function breakdown(int $productId, int $warehouseId, ?int $exceptReferenceId = null): array
    {
        $onHand = $this->onHand($productId, $warehouseId);
        $reserved = $this->reserved($productId, $warehouseId, $exceptReferenceId);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => round($onHand - $reserved, 4),
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // الكتابة
    // ═════════════════════════════════════════════════════════════════

    /**
     * حجزُ مواد أمرٍ من مستودع.
     *
     * يُستدعى داخل معاملةٍ قائمة. ويقفل صفوفَ الرصيد قبل القياس، فلا
     * يعتمد أمران على الكمية المتاحة نفسها: الثاني ينتظر الأول ثم يقيس
     * بعد حجزه.
     *
     * @param  array<int, array{product_id:int, quantity:float}>  $lines
     * @return array<int, array{product_id:int, name:string, required:float, available:float, shortage:float}> النواقص، فارغةً إن نجح
     */
    public function reserve(ManufacturOrders $order, int $warehouseId, array $lines): array
    {
        /*
            الحجزُ القديم يُفكّ أوّلاً — مهما كان ما بعده.

            كان الفكُّ بعد شرط الخروج: فمن أوقف الحجزَ من الإعدادات، أو
            صفّر كمياتِ الأمر، خرجت الدالّةُ قبل أن تفكّ حجزَه القائم —
            فيبقى وعداً بكمياتٍ لم تعد مطلوبة، يُنقص المتاحَ للأوامر الأخرى.
        */
        $this->release($order, 'إعادة حجز');

        if (! $this->enabled()) {
            return [];
        }

        // المادةُ الواحدة في أكثر من سطرٍ تُجمَع — فحصٌ واحد وحجزٌ واحد
        $lines = $this->groupByProduct($lines);

        if ($lines === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_column($lines, 'product_id')));

        /*
            القفلُ قبل القياس.

            بلا `lockForUpdate` يقرأ أمران المتاحَ نفسَه في اللحظة نفسها
            فيحجزان كلاهما — وهو عينُ العطل الذي يمنعه الحجز. القفل
            يجعل الثاني ينتظر التزامَ الأول ثم يقيس بعده.
        */
        ProductDetails::whereIn('product_id', $productIds)
            ->where('store_house_id', $warehouseId)
            ->lockForUpdate()
            ->get();

        $shortages = $this->shortages($lines, $warehouseId, $order->id);

        if ($shortages !== []) {
            return $shortages;
        }

        foreach ($lines as $line) {
            $quantity = (float) $line['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            StockReservation::create([
                'product_id' => (int) $line['product_id'],
                'store_house_id' => $warehouseId,
                'quantity' => $quantity,
                'reference_type' => ReferenceType::MANUFACTURING_ORDER->value,
                'reference_id' => $order->id,
                'status' => StockReservation::STATUS_ACTIVE,
                'note' => 'حجز مواد أمر التصنيع '.$order->code,
                'created_by' => auth()->id(),
            ]);
        }

        return [];
    }

    /**
     * تحويلُ الحجز إلى صرف.
     *
     * بعد أن تُخصم المواد فعلاً لا يصحّ أن يبقى الحجزُ نشطاً — وإلا
     * خُصمت الكميةُ من المتاح مرّتين: مرّةً بالحركة ومرّةً بالوعد.
     */
    public function consume(ManufacturOrders $order): int
    {
        return $this->transition($order, StockReservation::STATUS_CONSUMED, [
            'consumed_at' => now(),
        ]);
    }

    /** فكُّ الحجز بلا صرف — إلغاءٌ أو حذفٌ أو إعادةُ حجز. */
    public function release(ManufacturOrders $order, string $reason = 'فكّ الحجز'): int
    {
        return $this->transition($order, StockReservation::STATUS_RELEASED, [
            'released_at' => now(),
            'note' => $reason,
        ]);
    }

    /** حجوزُ أمرٍ النشطة — للعرض والفحص. */
    public function forOrder(ManufacturOrders $order)
    {
        return StockReservation::active()
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->get();
    }

    // ═════════════════════════════════════════════════════════════════

    /**
     * النواقصُ بالقياس إلى **المتاح** لا إلى الرصيد الفعلي.
     *
     * @param  array<int, array{product_id:int, quantity:float}>  $lines
     */
    /**
     * يجمع السطور بالمنتج.
     *
     * الفحصُ سطراً سطراً يُجيز ما لا يجوز: مادةٌ رصيدُها ١٠ في سطرين كلٌّ
     * منهما ٨ — كلُّ سطرٍ وحدَه يكفيه الرصيد، ومجموعُهما ١٦.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{product_id:int, quantity:float}>
     */
    public function groupByProduct(array $lines): array
    {
        $grouped = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $grouped[$productId] = ($grouped[$productId] ?? 0) + $quantity;
        }

        return array_map(
            fn ($productId, $quantity) => ['product_id' => (int) $productId, 'quantity' => round($quantity, 4)],
            array_keys($grouped),
            $grouped
        );
    }

    public function shortages(array $lines, int $warehouseId, ?int $exceptReferenceId = null): array
    {
        $lines = $this->groupByProduct($lines);

        $productIds = array_values(array_unique(array_column($lines, 'product_id')));

        if ($productIds === []) {
            return [];
        }

        $names = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $shortages = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $required = (float) $line['quantity'];

            if ($required <= 0) {
                continue;
            }

            $available = $this->available($productId, $warehouseId, $exceptReferenceId);

            if ($available + self::QTY_EPSILON < $required) {
                $shortages[] = [
                    'product_id' => $productId,
                    'name' => $names[$productId] ?? ('#'.$productId),
                    'required' => $required,
                    'available' => $available,
                    'shortage' => round($required - $available, 4),
                ];
            }
        }

        return $shortages;
    }

    private function transition(ManufacturOrders $order, string $status, array $extra): int
    {
        return StockReservation::active()
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->update(array_merge(['status' => $status], $extra));
    }
}
