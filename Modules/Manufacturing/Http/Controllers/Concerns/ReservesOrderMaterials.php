<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Models\ManufacturOrders;
use Illuminate\Http\Request;
use Modules\Manufacturing\Services\ReservationService;

/**
 * حجزُ مواد الأمر عند حفظه.
 *
 * ── قيدُ تصميمٍ يستحقّ الذكر ─────────────────────────────────────────
 *
 * تقول الخطة «حجزُ المواد عند إنشاء الأمر». وشاشةُ إنشاء الأمر **لا
 * تسأل عن مستودع** — `storehouse_id` يُختار لحظةَ استلام المواد فقط.
 * ولا يصحّ حجزُ كميةٍ بلا مستودع: المتاحُ يُقاس لكل مستودعٍ على حدة،
 * فحجزٌ «في مكانٍ ما» يُنقص المتاحَ في كل المستودعات معاً.
 *
 * فأُضيف حقلُ **مستودع المواد** إلى شاشة الأمر — **اختيارياً**:
 *
 *  · اختير مستودع  ⟵  تُحجز المواد فيه فور الحفظ.
 *  · لم يُختر       ⟵  لا حجز، والسلوكُ كما كان تماماً.
 *
 * فلا يُفرض تغييرٌ على من لا يريده، ولا يُحجز حجزٌ لا معنى له.
 */
trait ReservesOrderMaterials
{
    /**
     * حجزُ مواد الأمر إن اختير مستودع.
     *
     * يُستدعى بعد بناء البنود — الحجزُ يقرؤها. ولا يُسقط الحفظ عند
     * النقص: الأمرُ يُحفظ ويُبلَّغ المستخدم أن الحجز لم يتمّ. فمنعُ
     * التخطيط لأن المواد غير متوفّرة اليوم ليس من عمل النظام.
     *
     * @return array<int, array<string, mixed>> النواقص إن تعذّر الحجز
     */
    protected function reserveOrderMaterials(ManufacturOrders $order, Request $request): array
    {
        $warehouseId = (int) $request->input('storehouse_id', 0);

        if ($warehouseId <= 0) {
            // أُزيل المستودعُ من الأمر — حجزُه السابق لم يعد له مكان فيُفكّ
            app(ReservationService::class)->release($order, 'أُزيل مستودع المواد من الأمر');

            return [];
        }

        $lines = $order->manufacturOrdersItem()
            ->whereNotNull('raw_product_id')
            ->where('raw_quantity', '>', 0)
            ->get()
            ->map(fn ($item) => [
                'product_id' => (int) $item->raw_product_id,
                // بالوحدة الأساسية — المخزونُ يُمسَك بها، والبندُ قد يُكتب بوحدةٍ كبرى
                'quantity' => round((float) $item->raw_quantity * ((float) ($item->raw_conversion_factor ?: 1) ?: 1), 4),
            ])
            ->all();

        return app(ReservationService::class)->reserve($order, $warehouseId, $lines);
    }

    /** رسالةٌ مفهومة عن سبب تعذّر الحجز. */
    protected function reservationShortageMessage(array $shortages): string
    {
        $message = "حُفظ الأمر، ولم تُحجز المواد — المتاح لا يكفي:\n\n";

        foreach ($shortages as $shortage) {
            $message .= sprintf(
                "• %s: المطلوب %s، المتاح %s، النقص %s\n",
                $shortage['name'],
                $this->trimNumber($shortage['required']),
                $this->trimNumber($shortage['available']),
                $this->trimNumber($shortage['shortage'])
            );
        }

        return $message."\nيمكن استلام المواد لاحقاً بعد توفّرها.";
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
