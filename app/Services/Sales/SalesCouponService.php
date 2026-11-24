<?php

namespace App\Services\Sales;

use App\Enums\CouponType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Store\StoreCoupon;
use App\Models\Store\StoreCouponUsage;

/**
 * ══════════════════════════════════════════════════════════════════════
 * الكوبونات في مستندات المبيعات
 * ══════════════════════════════════════════════════════════════════════
 *
 * ── ما كان ──
 *
 * `store_coupons` جدولٌ كاملُ الشروط — نسبةٌ أو مبلغٌ أو شحنٌ مجانيّ، حدٌّ
 * أدنى للطلب، سقفٌ للخصم، حدُّ استعمالٍ للكوبون وللعميل، منتجاتٌ وفئاتٌ
 * وعملاءُ مسموحون ومستثنون، ونافذةُ صلاحية — وله شاشةُ إدارةٍ كاملة
 * («كوبونات الخصم» في المتجر الإلكتروني). ولم يكن يقرؤه إلّا سلّةُ المتجر:
 * فاتورةُ البيع وعرضُ السعر لا يعرفان عنه شيئاً، ومن أصدر كوبوناً لعملائه
 * لم يكن له سبيلٌ لإعماله في فاتورةٍ تُكتب في النظام.
 *
 * ── لماذا خدمةٌ لا نداءٌ مباشر للنموذج ──
 *
 * `StoreCoupon::calculateDiscount()` كُتبت لطلب المتجر: لا تفحص الفئات
 * (`applicable_categories` تُحفَظ في الشاشة ولا يقرؤها شيء)، و
 * `canBeUsedByClient()` تقيس «أوّل طلبٍ» على `online_orders` وحدها — فعميلٌ
 * له خمسون فاتورةَ بيعٍ في النظام يبقى «أوّل طلب» ما لم يشترِ من المتجر.
 * وكلتاهما تعود `false`/`0` بلا سبب، والبائعُ أمام الشاشة لا يعلم لمَ
 * رُفض الكوبون.
 *
 * فهنا فحصٌ واحد يعود بـ **سببٍ مكتوب** يظهر للبائع، ويقيس حدودَ
 * الاستعمال على `store_coupon_usages` — وهي التي تُكتب من الطرفين معاً —
 * لا على جدول طلبات المتجر.
 *
 * ── أين يقع الخصم ──
 *
 * خصمُ الكوبون **خصمٌ على المستند** لا على بنوده: يُجمع إلى خصم المستند
 * فيدخل «الخصم المسموح به» في القيد، ولا يمسّ وعاءَ الضريبة — كما يفعل
 * خصمُ المستند المكتوب بيد البائع في هذا النظام حرفاً بحرف. وتوحيدُهما
 * مقصود: لو طُرح من الوعاء لخرجت فاتورةٌ بكوبونٍ بضريبةٍ تخالف فاتورةً
 * بخصمٍ يدويٍّ بالقيمة نفسِها.
 *
 * و«الشحن المجانيّ» ليس خصماً بمبلغ: يُصفَّر الشحن وضريبتُه معاً. ولو
 * حُسب خصماً بقيمة الشحن لبقيت ضريبةُ الشحن مقيَّدةً على شحنٍ أُعفي منه.
 *
 * ── والتجميد ──
 *
 * ما يُحفَظ في المستند هو **ما طُبِّق فعلاً** (`coupon_code`
 * و`coupon_discount`)، لا مُعرَّفٌ يُعاد حسابُه: كوبونٌ تنتهي صلاحيتُه أو
 * تُخفَّض نسبتُه بعد إصدار الفاتورة يجب ألّا يغيّر رقماً صدر به مستند.
 */
class SalesCouponService
{
    /**
     * فحصُ كوبونٍ وحسابُ خصمه على مستند.
     *
     * @param  string|null  $code  الرمز كما كتبه البائع
     * @param  float  $amount  وعاءُ الخصم — المجموع بعد خصوم البنود وخصم المستند
     * @param  array<int, mixed>  $productIds  منتجات المستند
     * @param  float  $shippingCost  لكوبون «الشحن المجانيّ»
     * @param  int|null  $ignoreInvoiceId  فاتورةٌ تُعدَّل: استعمالُها هي لا يُحسب عليها
     * @return array{ok: bool, coupon: StoreCoupon|null, code: string|null, discount: float,
     *               free_shipping: bool, error: string|null}
     */
    public function evaluate(
        ?string $code,
        ?int $clientId,
        float $amount,
        array $productIds = [],
        float $shippingCost = 0.0,
        ?int $ignoreInvoiceId = null,
    ): array {
        $code = trim((string) $code);

        if ($code === '') {
            return $this->nothing();
        }

        $coupon = StoreCoupon::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

        if (! $coupon) {
            return $this->refuse(__('sales_invoice.coupon.not_found', ['code' => $code]));
        }

        if (! $coupon->is_active) {
            return $this->refuse(__('sales_invoice.coupon.inactive', ['code' => $coupon->code]));
        }

        if ($coupon->start_date && $coupon->start_date->isFuture()) {
            return $this->refuse(__('sales_invoice.coupon.not_started', [
                'date' => $coupon->start_date->format('Y-m-d'),
            ]));
        }

        if ($coupon->end_date && $coupon->end_date->isPast()) {
            return $this->refuse(__('sales_invoice.coupon.expired', [
                'date' => $coupon->end_date->format('Y-m-d'),
            ]));
        }

        if ($coupon->usage_limit && $this->usageCount($coupon, null, $ignoreInvoiceId) >= $coupon->usage_limit) {
            return $this->refuse(__('sales_invoice.coupon.limit_reached'));
        }

        if (! empty($coupon->applicable_clients)
            && (! $clientId || ! in_array($clientId, array_map('intval', $coupon->applicable_clients), true))) {
            return $this->refuse(__('sales_invoice.coupon.client_not_eligible'));
        }

        if ($clientId && $coupon->usage_limit_per_user
            && $this->usageCount($coupon, $clientId, $ignoreInvoiceId) >= $coupon->usage_limit_per_user) {
            return $this->refuse(__('sales_invoice.coupon.client_limit_reached'));
        }

        /* «أوّل طلبٍ فقط» يُقاس على فواتير البيع أيضاً لا على طلبات المتجر
           وحدها: العميلُ واحدٌ والنظامُ واحد. */
        if ($clientId && $coupon->is_first_order_only && $this->hasEarlierPurchase($clientId, $ignoreInvoiceId)) {
            return $this->refuse(__('sales_invoice.coupon.first_order_only'));
        }

        if ($coupon->min_order_amount && $amount < (float) $coupon->min_order_amount) {
            return $this->refuse(__('sales_invoice.coupon.min_amount', [
                'amount' => number_format((float) $coupon->min_order_amount, 2),
            ]));
        }

        $productIds = array_values(array_filter(array_map('intval', $productIds)));

        if (! empty($coupon->excluded_products)
            && array_intersect($productIds, array_map('intval', $coupon->excluded_products))) {
            return $this->refuse(__('sales_invoice.coupon.excluded_product'));
        }

        if (! $this->matchesScope($coupon, $productIds)) {
            return $this->refuse(__('sales_invoice.coupon.no_eligible_product'));
        }

        if ($coupon->type === CouponType::FREE_SHIPPING) {
            if ($shippingCost <= 0) {
                return $this->refuse(__('sales_invoice.coupon.no_shipping'));
            }

            return [
                'ok' => true,
                'coupon' => $coupon,
                'code' => $coupon->code,
                'discount' => 0.0,
                'free_shipping' => true,
                'error' => null,
            ];
        }

        $discount = $coupon->type === CouponType::PERCENTAGE
            ? $amount * ((float) $coupon->value) / 100
            : (float) $coupon->value;

        if ($coupon->max_discount && $discount > (float) $coupon->max_discount) {
            $discount = (float) $coupon->max_discount;
        }

        // خصمٌ يفوق المستند يقلب إجماليه سالباً
        $discount = round(min($discount, max(0.0, $amount)), 2);

        if ($discount <= 0) {
            return $this->refuse(__('sales_invoice.coupon.zero_discount'));
        }

        return [
            'ok' => true,
            'coupon' => $coupon,
            'code' => $coupon->code,
            'discount' => $discount,
            'free_shipping' => false,
            'error' => null,
        ];
    }

    /**
     * تسجيلُ الاستعمال — بعد حفظ المستند، وبفحصٍ يمنع التكرار عند التعديل.
     */
    public function recordUsage(StoreCoupon $coupon, ?int $clientId, ?int $invoiceId, float $discount): void
    {
        if ($invoiceId) {
            $existing = StoreCouponUsage::where('coupon_id', $coupon->id)
                ->where('invoice_id', $invoiceId)
                ->first();

            if ($existing) {
                $existing->update(['discount_amount' => $discount, 'client_id' => $clientId]);

                return;
            }
        }

        StoreCouponUsage::create([
            'coupon_id' => $coupon->id,
            'client_id' => $clientId,
            'invoice_id' => $invoiceId,
            'discount_amount' => $discount,
        ]);

        $coupon->increment('used_count');
    }

    /** الكوبونات المعروضة في مُنتقي الشاشة — السارية اليوم وحدها */
    public function selectable(?int $clientId = null)
    {
        return StoreCoupon::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderBy('code')
            ->get()
            ->filter(function (StoreCoupon $coupon) use ($clientId) {
                if (empty($coupon->applicable_clients)) {
                    return true;
                }

                return $clientId && in_array($clientId, array_map('intval', $coupon->applicable_clients), true);
            })
            ->values();
    }

    /**
     * نطاقُ المنتجات: قائمةُ المنتجات أوّلاً، فالفئات — وخلوُّهما معاً يعني
     * «كلَّ المنتجات». والفئاتُ لم تكن تُفحَص إطلاقاً.
     */
    private function matchesScope(StoreCoupon $coupon, array $productIds): bool
    {
        $hasProducts = ! empty($coupon->applicable_products);
        $hasCategories = ! empty($coupon->applicable_categories);

        if (! $hasProducts && ! $hasCategories) {
            return true;
        }

        if ($hasProducts && array_intersect($productIds, array_map('intval', $coupon->applicable_products))) {
            return true;
        }

        if ($hasCategories && $productIds) {
            return Product::whereIn('id', $productIds)
                ->whereIn('category_id', array_map('intval', $coupon->applicable_categories))
                ->exists();
        }

        return false;
    }

    private function usageCount(StoreCoupon $coupon, ?int $clientId, ?int $ignoreInvoiceId): int
    {
        return StoreCouponUsage::where('coupon_id', $coupon->id)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($ignoreInvoiceId, fn ($q) => $q->where(function ($inner) use ($ignoreInvoiceId) {
                $inner->whereNull('invoice_id')->orWhere('invoice_id', '!=', $ignoreInvoiceId);
            }))
            ->count();
    }

    private function hasEarlierPurchase(int $clientId, ?int $ignoreInvoiceId): bool
    {
        return Invoice::where('client_id', $clientId)
            ->whereNull('voided_at')
            ->when($ignoreInvoiceId, fn ($q) => $q->where('id', '!=', $ignoreInvoiceId))
            ->exists();
    }

    private function nothing(): array
    {
        return [
            'ok' => false, 'coupon' => null, 'code' => null,
            'discount' => 0.0, 'free_shipping' => false, 'error' => null,
        ];
    }

    private function refuse(string $error): array
    {
        return [
            'ok' => false, 'coupon' => null, 'code' => null,
            'discount' => 0.0, 'free_shipping' => false, 'error' => $error,
        ];
    }
}
