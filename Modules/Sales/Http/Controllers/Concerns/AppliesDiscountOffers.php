<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Services\Sales\OfferDiscountService;
use Illuminate\Support\Collection;

/**
 * وصلُ «عروض الحسم» بمستندات المبيعات.
 *
 * ثلاثة متحكّمات تحسب خصمَ البند كلٌّ بطريقته — الفاتورة
 * (`lineDiscountValue`)، وعرض السعر (`calculateTotals`)، والمرتجع
 * (`itemDiscount`) — وكلُّها كانت تقرأ ما كتبه المستخدم وحده. هنا نقطةٌ
 * واحدة تُضيف إليها العرضَ المطابق.
 *
 * **قاعدتان تُغنيان عن كثيرٍ من الشرح:**
 *
 *   1. *ما كتبه المستخدم يتقدّم.* بندٌ عليه خصمٌ يدويّ لا يمسّه العرض —
 *      لا جمعاً ولا استبدالاً. فمن حسم بيده يعرف ما يفعل، والعرضُ يملأ
 *      الفراغ لا يُزاحم.
 *   2. *الكمّية كما كُتبت في البند* لا بالوحدة الأساسية. عروضُ الهدايا
 *      تُحوِّل إلى الحبّات لأنّ حدَّها الأدنى مذكورٌ بها، أمّا «الكمية
 *      المطلوبة» هنا فتُقرأ كما يراها البائع في السطر. والأهمّ أنّ حلقة
 *      الإجماليات وحلقة الضريبة في متحكّم الفاتورة لا تملكان معامل
 *      التحويل نفسه، فمطابقةٌ بالوحدة الأساسية تُنتج خصمين مختلفين
 *      للبند الواحد في الطلب الواحد.
 *
 * ويُحصى ما طُبِّق ليُذكر في رسالة الحفظ: الحسم يقع في الخادم بعد أن يضغط
 * المستخدم «حفظ»، فلو مرّ صامتاً لظنّ أنّ الإجمالي اختلّ.
 */
trait AppliesDiscountOffers
{
    /** العروض السارية، محفوظةً لكلّ عميلٍ سُئل عنه في هذا الطلب. */
    private array $discountOfferCache = [];

    /** ما طُبِّق فعلاً: مفتاح البند ⇒ اسم العرض — بلا تكرارٍ للبند الواحد. */
    private array $appliedDiscountOffers = [];

    /**
     * خصمُ البند بعد إعمال العروض.
     *
     * @param  float  $manualDiscount  ما حسبه المتحكّم من مُدخَل المستخدم
     * @param  int|string|null  $key  مفتاح البند — لئلّا يُحصى مرّتين حين
     *                                تمرّ عليه حلقتا الإجماليات والضريبة
     */
    protected function lineDiscountWithOffers(
        array $item,
        float $lineTotal,
        float $manualDiscount,
        ?int $clientId,
        int|string|null $key = null,
        ?string $date = null,
    ): float {
        // ما كتبه المستخدم يتقدّم — والبند الحرّ لا منتجَ له فلا عرض
        if ($manualDiscount > 0 || empty($item['product_id']) || $lineTotal <= 0) {
            return $manualDiscount;
        }

        $offers = $this->activeDiscountOffers($clientId, $date);

        if ($offers->isEmpty()) {
            return $manualDiscount;
        }

        $match = app(OfferDiscountService::class)->lineDiscount(
            $offers,
            (int) $item['product_id'],
            (float) ($item['quantity'] ?? 0),
            $lineTotal,
        );

        if (! $match) {
            return $manualDiscount;
        }

        if ($key !== null) {
            $this->appliedDiscountOffers[$key] = $match['name'];
        }

        return $match['amount'];
    }

    /** العروض السارية على هذا العميل — استعلامٌ واحد لكلّ مستند. */
    protected function activeDiscountOffers(?int $clientId, ?string $date = null): Collection
    {
        $cacheKey = ($clientId ?? 0).'|'.($date ?? '');

        return $this->discountOfferCache[$cacheKey] ??= app(OfferDiscountService::class)
            ->activeFor($clientId, $date);
    }

    /**
     * سطرٌ يُلحق برسالة النجاح حين طُبِّق عرضٌ — أو `null`.
     */
    protected function appliedOffersNotice(): ?string
    {
        if ($this->appliedDiscountOffers === []) {
            return null;
        }

        return __('sales_settings.offers.applied_notice', [
            'count' => count($this->appliedDiscountOffers),
            'names' => implode(
                __('sales_settings.ui.list_separator'),
                array_unique(array_values($this->appliedDiscountOffers))
            ),
        ]);
    }
}
