{{--
    صندوق الإجماليات — بالعملة الافتراضية وكسورها وفواصلها ورمزها.

    المجموع والإجمالي النهائي يظهران دوماً، وما بينهما مشروط: السطر لا
    يُرسَم إلا إذا كان لقيمته وجود. فمستندٌ بلا شحن لا يعرض سطر شحنٍ
    بصفر، ومستندٌ بلا إيداع لا يعرض سطر إيداع — وما يظهر في الشاشة يظهر
    في المطبوعة نفسها (انظر `totals.*` في قوالب المستندات).

    @param array{
        subtotal: float, discount: float, tax: float, grand_total: float,
        shipping?: float, deposit?: float, paid?: float, refund?: float,
        remaining?: float
    } $totals
--}}
@php
    use Modules\Purchases\Services\PurchaseSettings;
    use App\Support\CurrencySettings;

    // «إجمالي الخصومات» مفتاحُ إعدادات: مطفأً يُخفي السطر ولو كان فيه مبلغ
    $showDiscount = PurchaseSettings::enabled('total_discounts');

    /**
     * الأسطر المشروطة بترتيب عرضها. القيمة الغائبة عن `$totals` والقيمةُ
     * الصفرية سواء: لا سطر لهما. والحدّ 0.005 لأن العملة تُقرَّب لخانتين،
     * فما دونه يُطبع «0.00» فيبدو سطراً فارغاً بلا معنى.
     */
    $optional = static function (array $totals, string $key): ?float {
        $value = (float) ($totals[$key] ?? 0);

        return abs($value) >= 0.005 ? $value : null;
    };

    $shipping = $optional($totals, 'shipping');
    $discount = $showDiscount ? $optional($totals, 'discount') : null;
    $tax = $optional($totals, 'tax');
    $deposit = $optional($totals, 'deposit');
    $paid = $optional($totals, 'paid');
    $refund = $optional($totals, 'refund');
    $remaining = $optional($totals, 'remaining');
@endphp

<div class="po-card__foot">
    <span class="po-hint">
        <i class="fas fa-coins me-1"></i>{{ CurrencySettings::name() }}
    </span>

    <div class="po-totals">
        <div class="po-totals__row">
            <span>{{ __('purchase_orders.totals.subtotal') }}</span>
            <span>{{ CurrencySettings::format($totals['subtotal'] ?? 0) }}</span>
        </div>

        @if ($discount !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.discount') }}</span>
                <span>{{ CurrencySettings::format($discount) }}</span>
            </div>
        @endif

        @if ($shipping !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.shipping') }}</span>
                <span>{{ CurrencySettings::format($shipping) }}</span>
            </div>
        @endif

        @if ($tax !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.tax') }}</span>
                <span>{{ CurrencySettings::format($tax) }}</span>
            </div>
        @endif

        <div class="po-totals__row po-totals__row--grand">
            <span>{{ __('purchase_orders.totals.grand_total') }}</span>
            <span>{{ CurrencySettings::format($totals['grand_total'] ?? 0) }}</span>
        </div>

        @if ($deposit !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.deposit') }}</span>
                <span>{{ CurrencySettings::format($deposit) }}</span>
            </div>
        @endif

        @if ($paid !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.paid') }}</span>
                <span>{{ CurrencySettings::format($paid) }}</span>
            </div>
        @endif

        @if ($refund !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.refund') }}</span>
                <span>{{ CurrencySettings::format($refund) }}</span>
            </div>
        @endif

        @if ($remaining !== null)
            <div class="po-totals__row">
                <span>{{ __('purchase_orders.totals.remaining') }}</span>
                <span>{{ CurrencySettings::format($remaining) }}</span>
            </div>
        @endif
    </div>
</div>
