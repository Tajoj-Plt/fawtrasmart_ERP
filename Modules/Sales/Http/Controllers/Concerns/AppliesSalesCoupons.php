<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\SalesInvoiceSetting;
use App\Services\Sales\SalesCouponService;

/**
 * وصلُ «كوبونات الخصم» بفاتورة البيع وعرض السعر.
 *
 * نقطةٌ واحدة يناديها المتحكّمان، على غرار {@see AppliesDiscountOffers}:
 * المنطقُ كلُّه في {@see SalesCouponService}، وهنا المفتاحُ الذي يُشغّله
 * («تفعيل الكوبونات» في إعدادات الفواتير) وسببُ الرفض محفوظاً ليُعرَض في
 * رسالة الحفظ.
 *
 * **والرفضُ لا يُسقِط المستند.** كوبونٌ منتهي الصلاحية أو بلغ سقفَه لا
 * يمنع إصدار الفاتورة: تُحفَظ بلا خصم الكوبون ويُقال للبائع لماذا. وإسقاطُ
 * فاتورةٍ مكتوبةٍ كاملةً لأجل رمزٍ خاطئ عقوبةٌ لا تناسب الخطأ.
 */
trait AppliesSalesCoupons
{
    /** سببُ رفض الكوبون في هذا الطلب — يُلحَق برسالة الحفظ */
    private ?string $couponRejection = null;

    /** ما طُبِّق: `['code' => …, 'discount' => …, 'free_shipping' => …]` */
    private ?array $appliedCoupon = null;

    /** هل الكوبونات مفعَّلة أصلاً؟ */
    protected function couponsEnabled(): bool
    {
        return (bool) SalesInvoiceSetting::current()->enable_coupons;
    }

    /**
     * فحصُ كوبون المستند وحسابُ خصمه.
     *
     * @param  float  $amount  وعاءُ الخصم — بعد خصوم البنود وخصم المستند
     * @param  array<int, mixed>  $productIds
     * @return array{code: string|null, coupon_id: int|null, discount: float, free_shipping: bool}
     */
    protected function documentCoupon(
        ?string $code,
        ?int $clientId,
        float $amount,
        array $productIds = [],
        float $shippingCost = 0.0,
        ?int $ignoreInvoiceId = null,
    ): array {
        $empty = ['code' => null, 'coupon_id' => null, 'discount' => 0.0, 'free_shipping' => false];

        if (trim((string) $code) === '') {
            return $empty;
        }

        if (! $this->couponsEnabled()) {
            $this->couponRejection = __('sales_invoice.coupon.disabled');

            return $empty;
        }

        $result = app(SalesCouponService::class)->evaluate(
            $code,
            $clientId,
            $amount,
            $productIds,
            $shippingCost,
            $ignoreInvoiceId,
        );

        if (! $result['ok']) {
            $this->couponRejection = $result['error'];

            return $empty;
        }

        $this->appliedCoupon = [
            'model' => $result['coupon'],
            'code' => $result['code'],
            'discount' => $result['discount'],
            'free_shipping' => $result['free_shipping'],
        ];

        return [
            'code' => $result['code'],
            'coupon_id' => $result['coupon']->id,
            'discount' => $result['discount'],
            'free_shipping' => $result['free_shipping'],
        ];
    }

    /**
     * تسجيلُ الاستعمال بعد حفظ المستند — للفواتير وحدها.
     *
     * عرضُ السعر لا يستهلك كوبوناً: وعدٌ لم يُنفَّذ بعد، وقد لا يتحوّل إلى
     * فاتورة أصلاً. ولو عُدَّ استعمالاً لاستنفد عرضٌ ملغىً حصّةَ عميلٍ من
     * كوبونٍ لم يشترِ به شيئاً.
     */
    protected function recordCouponUsage(?int $clientId, ?int $invoiceId): void
    {
        if (! $this->appliedCoupon || empty($this->appliedCoupon['model'])) {
            return;
        }

        app(SalesCouponService::class)->recordUsage(
            $this->appliedCoupon['model'],
            $clientId,
            $invoiceId,
            (float) $this->appliedCoupon['discount'],
        );
    }

    /**
     * فحصُ كوبونٍ من الشاشة قبل الحفظ — نقطةٌ واحدة لشاشتَي الفاتورة وعرض
     * السعر (لكلٍّ مسارُها وصلاحيتُها، والمنطقُ هنا).
     *
     * **الشاشةُ يجب أن تُري ما سيحفظه الخادم.** الحسمُ الذي يقع في الخادم
     * وحده كسر «مدفوعة بالكامل» يوم طُبِّقت عروضُ الحسم أوّل مرّة: مربّعُ
     * السداد يكتب الإجماليَّ **المعروض** في `advance_payment`، ثمّ يحسب
     * الخادمُ إجمالياً أقلّ فيردّ «الدفعة المقدَّمة أكبر من قيمة الفاتورة».
     * فكلُّ خصمٍ يقع في الخادم يحتاج نظيراً في الشاشة.
     *
     * ويُعاد الفحصُ عند الحفظ على كلّ حال، فهذا عرضٌ لا حُكم.
     */
    public function checkCoupon(\Illuminate\Http\Request $request)
    {
        if (! $this->couponsEnabled()) {
            return response()->json([
                'ok' => false,
                'discount' => 0,
                'free_shipping' => false,
                'message' => __('sales_invoice.coupon.disabled'),
            ]);
        }

        $result = app(SalesCouponService::class)->evaluate(
            $request->input('code'),
            $request->filled('client_id') ? (int) $request->input('client_id') : null,
            (float) $request->input('amount', 0),
            (array) $request->input('product_ids', []),
            (float) $request->input('shipping_cost', 0),
        );

        return response()->json([
            'ok' => $result['ok'],
            'code' => $result['code'],
            'discount' => $result['discount'],
            'free_shipping' => $result['free_shipping'],
            'message' => $result['ok']
                ? ($result['free_shipping']
                    ? __('sales_invoice.coupon.applied_free_shipping', ['code' => $result['code']])
                    : __('sales_invoice.coupon.applied', [
                        'code' => $result['code'],
                        'amount' => number_format($result['discount'], 2),
                    ]))
                : $result['error'],
        ]);
    }

    /** سطرٌ يُلحق برسالة الحفظ — ما طُبِّق، أو لماذا رُفض */
    protected function couponNotice(): ?string
    {
        if ($this->appliedCoupon) {
            return $this->appliedCoupon['free_shipping']
                ? __('sales_invoice.coupon.applied_free_shipping', ['code' => $this->appliedCoupon['code']])
                : __('sales_invoice.coupon.applied', [
                    'code' => $this->appliedCoupon['code'],
                    'amount' => number_format((float) $this->appliedCoupon['discount'], 2),
                ]);
        }

        return $this->couponRejection;
    }
}
