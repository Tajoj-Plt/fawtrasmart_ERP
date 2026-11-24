{{--
    صندوق إجماليات المستند — للشاشات التي تعرض مستنداً محفوظاً.

    المجموع والإجمالي النهائي يظهران دوماً، وما بينهما مشروط: السطر لا
    يُرسَم إلا إذا كان لقيمته وجود. فمستندٌ بلا شحن لا يعرض سطر شحنٍ
    بصفر، وفاتورةٌ لم يُدفع منها شيء لا تعرض «المدفوع: 0» ثم تكرّر
    الإجمالي في سطر «المتبقي». وهو نفس شرط شاشات الإنشاء والتعديل
    (`data-total-row` في السكربتات) ونفس شرط المطبوعات (`@if` في قوالب
    المستندات) — فما يراه المستخدم واحدٌ في المواضع الثلاثة.

    @param array $totals   المفاتيح: subtotal، grand_total (إلزاميان)،
                           discount، shipping، tax، deposit، paid، returned،
                           remaining (اختيارية، تظهر متى كان لها وجود)
    @param array $totalsLabels   مفتاح ← نصّه المعروض. الغائب يأخذ نصّه من
                           `sales_documents.totals.*`
    @param bool $totalsDueWarning  تلوين «المتبقي» بالأحمر متى كان موجباً
--}}
@php
    use App\Support\CurrencySettings;

    $totalsLabels = ($totalsLabels ?? []) + [
        'subtotal' => __('sales_documents.totals.subtotal'),
        'discount' => __('sales_documents.totals.discount'),
        'coupon' => __('sales_invoice.coupon.section'),
        'shipping' => __('sales_documents.totals.shipping'),
        'tax' => __('sales_documents.totals.tax'),
        'grand_total' => __('sales_documents.totals.grand_total'),
        'deposit' => __('sales_documents.totals.deposit'),
        'paid' => __('sales_documents.totals.paid'),
        'returned' => __('sales_documents.totals.returned'),
        'remaining' => __('sales_documents.totals.remaining'),
    ];

    // الحدّ 0.005 لأن العملة تُقرَّب لخانتين، فما دونه يُطبع «0.00» فيبدو
    // سطراً فارغاً بلا معنى
    $optional = static function (string $key) use ($totals): ?float {
        $value = (float) ($totals[$key] ?? 0);

        return abs($value) >= 0.005 ? $value : null;
    };

    /* خصمُ الكوبون سطرٌ مستقلّ عن الخصم — والمُنادي يمرّر `discount`
       **صافياً منه**، وإلّا حُسب الخصمُ مرّتين في العين لا في الحساب.
       والمجموعُ المحفوظ (`discount_amount`) يحملهما معاً كما يقيّدهما
       القيدُ في «الخصم المسموح به». */
    $rowsBefore = array_filter([
        'discount' => $optional('discount'),
        'coupon' => $optional('coupon'),
        'shipping' => $optional('shipping'),
        'tax' => $optional('tax'),
    ], static fn ($value) => $value !== null);

    $rowsAfter = array_filter([
        'deposit' => $optional('deposit'),
        'paid' => $optional('paid'),
        // ما أرجعه مرتجعٌ أو إشعارٌ دائن (`returned_payment`)
        'returned' => $optional('returned'),
    ], static fn ($value) => $value !== null);

    /* «المتبقي» يستثنى من القاعدة: ما دام لم يُدفع شيء فهو الإجمالي نفسه
       مكرَّراً في سطرين. فيظهر متى دُفع شيء — أو متى خالف الإجمالي لسببٍ
       آخر (مرتجعٌ خصم من المستحق مثلاً)، وحينها ليس تكراراً. */
    $remaining = $optional('remaining');

    if ($rowsAfter === [] && abs(($remaining ?? 0.0) - (float) ($totals['grand_total'] ?? 0)) < 0.005) {
        $remaining = null;
    }
    $totalsDueWarning = ($totalsDueWarning ?? false) && $remaining !== null && $remaining > 0;
@endphp

<div class="sl-totals">
    <div class="sl-totals__row">
        <span>{{ $totalsLabels['subtotal'] }}</span>
        <span>{{ CurrencySettings::format($totals['subtotal'] ?? 0) }}</span>
    </div>

    @foreach ($rowsBefore as $key => $value)
        <div class="sl-totals__row">
            <span>{{ $totalsLabels[$key] }}</span>
            <span>{{ CurrencySettings::format($value) }}</span>
        </div>
    @endforeach

    <div class="sl-totals__row sl-totals__row--grand">
        <span>{{ $totalsLabels['grand_total'] }}</span>
        <span>{{ CurrencySettings::format($totals['grand_total'] ?? 0) }}</span>
    </div>

    @foreach ($rowsAfter as $key => $value)
        <div class="sl-totals__row">
            <span>{{ $totalsLabels[$key] }}</span>
            <span>{{ CurrencySettings::format($value) }}</span>
        </div>
    @endforeach

    @if ($remaining !== null)
        <div class="sl-totals__row">
            <span class="{{ $totalsDueWarning ? 'text-danger' : '' }}">{{ $totalsLabels['remaining'] }}</span>
            <span class="{{ $totalsDueWarning ? 'text-danger' : '' }}">{{ CurrencySettings::format($remaining) }}</span>
        </div>
    @endif
</div>
