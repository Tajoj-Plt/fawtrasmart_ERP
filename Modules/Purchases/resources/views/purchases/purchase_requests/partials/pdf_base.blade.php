{{--
    قالب طباعة طلب الشراء المدمج — أساس القوالب الخمسة.

    كانت الخمسة نسخاً متقاربة بنصوص عربية وتواريخ ومبالغ مكتوبة في القالب،
    فأي تبديل للّغة أو العملة أو صيغة التاريخ في الإعدادات لا يظهر فيها.
    صارت هنا واحداً يقرأ من:
      • «معلومات المنشأة»            → الترويسة والشعار والرقم الضريبي
      • «العملات» (العملة الافتراضية) → المبالغ وكسورها ورمزها
      • «إعدادات اللغات»              → الاتجاه وصيغة التاريخ والفواصل
      • «معلومات الحساب»              → الختم والتوقيع

    التخطيط بالجداول لا بـflex لأنّ TCPDF لا يدعمها.

    @param \App\Models\PurchaseOrder $purchaseOrder
    @param array $totals
    @param array $business
    @param string $variant   default|detailed|simple|compact|summary
    @param bool $autoPrint
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use App\Support\PrintSettings;
    use Modules\Purchases\Support\OrderLine;

    $variant = $variant ?? 'default';
    $rtl = LanguageSettings::isRtl();
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';

    $compact = $variant === 'compact';
    $simple = $variant === 'simple';
    $detailed = $variant === 'detailed';
    $summaryOnly = $variant === 'summary';

    $fontSize = $compact ? 9 : ($simple ? 11 : 12);
    $showItems = ! $summaryOnly;
    $showDescription = $detailed;
    $showSignatures = ! $compact && ! $summaryOnly;

    $statusKey = 'purchase_orders.statuses.'.$purchaseOrder->status;
    $statusLabel = __($statusKey);
    $statusLabel = $statusLabel === $statusKey ? $purchaseOrder->status : $statusLabel;
@endphp
    <!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ LanguageSettings::direction() }}">

<head>
    <meta charset="UTF-8">
    <title>{{ __('purchase_orders.pdf.title') }} — {{ $purchaseOrder->code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Tajawal', sans-serif;
            font-size: {{ $fontSize }}px;
            line-height: {{ $compact ? 1.25 : 1.5 }};
            color: #2f3349;
        }

        .doc-head {
            width: 100%;
            border-bottom: 2px solid #2f3349;
            padding-bottom: {{ $compact ? 4 : 8 }}px;
            margin-bottom: {{ $compact ? 8 : 16 }}px;
        }

        .doc-title {
            font-size: {{ $fontSize + ($compact ? 4 : 7) }}px;
            font-weight: bold;
        }

        .doc-sub {
            color: #6e6b7b;
            font-size: {{ $fontSize - 1 }}px;
        }

        .panel {
            width: 100%;
            margin-bottom: {{ $compact ? 8 : 14 }}px;
        }

        .panel td {
            vertical-align: top;
            padding: 0 4px;
        }

        .panel h3 {
            margin: 0 0 4px 0;
            font-size: {{ $fontSize + 1 }}px;
            border-bottom: 1px solid #dcdfe6;
            padding-bottom: 3px;
        }

        .kv {
            width: 100%;
            border-collapse: collapse;
        }

        .kv td {
            padding: 2px 4px;
            font-size: {{ $fontSize - 1 }}px;
        }

        .kv .k {
            color: #6e6b7b;
            width: 42%;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: {{ $compact ? 6 : 12 }}px 0;
        }

        table.items th,
        table.items td {
            border: 1px solid #b9bcc7;
            padding: {{ $compact ? '3px 4px' : '6px 7px' }};
            text-align: center;
        }

        table.items th {
            background-color: #eceef3;
            font-weight: bold;
        }

        table.items td.name {
            text-align: {{ $align }};
        }

        table.totals {
            width: 45%;
            border-collapse: collapse;
            margin-{{ $opposite }}: 0;
            margin-{{ $align }}: auto;
        }

        table.totals td {
            padding: {{ $compact ? '3px 5px' : '5px 8px' }};
            border-bottom: 1px solid #dcdfe6;
            font-size: {{ $fontSize }}px;
        }

        table.totals tr.grand td {
            border-top: 2px solid #2f3349;
            border-bottom: 0;
            font-weight: bold;
            font-size: {{ $fontSize + 2 }}px;
        }

        .signatures {
            width: 100%;
            margin-top: 40px;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            font-size: {{ $fontSize - 1 }}px;
        }

        .sign-line {
            margin-top: 34px;
            border-top: 1px solid #2f3349;
            padding-top: 4px;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: {{ $fontSize - 2 }}px;
            color: #8a8f9c;
        }

        .notes {
            border: 1px solid #dcdfe6;
            padding: 6px 8px;
            font-size: {{ $fontSize - 1 }}px;
        }

        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    {{-- الترويسة: بيانات المنشأة من الإعدادات --}}
    <table class="doc-head">
        <tr>
            <td style="text-align: {{ $align }}; width: 60%;">
                <div class="doc-title">{{ $business['name'] ?? '' }}</div>
                <div class="doc-sub">
                    {{ $business['address'] ?? '' }}<br>
                    {{ $business['phone'] ?? '' }}
                    @if (! empty($business['email']))
                        — {{ $business['email'] }}
                    @endif
                    <br>
                    {{ __('settings.doc_templates.fields.business_tax_number') }}:
                    {{ $business['tax_number'] ?? '' }}
                </div>
            </td>
            <td style="text-align: {{ $opposite }}; width: 40%;">
                <div class="doc-title">{{ __('purchase_orders.pdf.title') }}</div>
                <div class="doc-sub">
                    {{ __('purchase_orders.fields.code') }}: {{ $purchaseOrder->code }}<br>
                    {{ __('purchase_orders.fields.order_date') }}:
                    {{ LanguageSettings::formatDate($purchaseOrder->order_date) }}<br>
                    {{ __('purchase_orders.fields.status') }}: {{ $statusLabel }}
                </div>
            </td>
        </tr>
    </table>

    {{-- لوحات البيانات --}}
    <table class="panel">
        <tr>
            <td style="width: 50%;">
                <h3>{{ __('purchase_orders.tabs.details') }}</h3>
                <table class="kv">
                    <tr>
                        <td class="k">{{ __('purchase_orders.fields.title') }}</td>
                        <td>{{ $purchaseOrder->title }}</td>
                    </tr>
                    <tr>
                        <td class="k">{{ __('purchase_orders.fields.order_date') }}</td>
                        <td>{{ LanguageSettings::formatDate($purchaseOrder->order_date) }}</td>
                    </tr>
                    <tr>
                        <td class="k">{{ __('purchase_orders.fields.due_date') }}</td>
                        <td>{{ LanguageSettings::formatDate($purchaseOrder->due_date) ?: __('purchase_orders.empty.unknown') }}</td>
                    </tr>
                    @if ($detailed)
                        <tr>
                            <td class="k">{{ __('purchase_orders.fields.items_count') }}</td>
                            <td>{{ LanguageSettings::formatNumber($purchaseOrder->items->count(), 0) }}</td>
                        </tr>
                        <tr>
                            <td class="k">{{ __('purchase_orders.fields.currency') }}</td>
                            <td>{{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})</td>
                        </tr>
                    @endif
                </table>
            </td>

            <td style="width: 50%;">
                <h3>{{ __('purchase_orders.pdf.issued_by') }}</h3>
                <table class="kv">
                    <tr>
                        <td class="k">{{ __('purchase_orders.fields.creator') }}</td>
                        <td>{{ $purchaseOrder->creator->name ?? __('purchase_orders.empty.unknown') }}</td>
                    </tr>
                    {{-- لا تُعرض بيانات الاعتماد هنا: جدول `purchase_orders` لا
                         يحمل أعمدة approved_at/approval_note، فكانت تظهر فارغة دوماً --}}
                    @if ($detailed)
                        <tr>
                            <td class="k">{{ __('purchase_orders.fields.updated_at') }}</td>
                            <td>{{ LanguageSettings::formatDate($purchaseOrder->updated_at, true) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">{{ __('purchase_orders.pdf.printed_at') }}</td>
                        <td>{{ LanguageSettings::formatDate(now(), true) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($showItems)
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;">{{ __('purchase_orders.items.row') }}</th>
                    <th>{{ __('purchase_orders.items.item') }}</th>
                    @if ($showDescription)
                        <th>{{ __('purchase_orders.items.description') }}</th>
                    @endif
                    <th style="width: 10%;">{{ __('purchase_orders.items.quantity') }}</th>
                    @unless ($simple)
                        <th style="width: 14%;">{{ __('purchase_orders.items.unit_price') }}</th>
                        <th style="width: 12%;">{{ __('purchase_orders.items.discount') }}</th>
                        <th style="width: 12%;">{{ __('purchase_orders.items.tax') }}</th>
                    @endunless
                    <th style="width: 16%;">{{ __('purchase_orders.items.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchaseOrder->items as $index => $item)
                    <tr>
                        <td>{{ LanguageSettings::formatNumber($index + 1, 0) }}</td>
                        <td class="name">{{ $item->item ?: ($item->product->name ?? '—') }}</td>
                        @if ($showDescription)
                            <td class="name">{{ $item->description ?: '—' }}</td>
                        @endif
                        <td>{{ LanguageSettings::formatNumber($item->quantity, 0) }}</td>
                        @unless ($simple)
                            <td>{{ CurrencySettings::number($item->unit_price) }}</td>
                            <td>{{ CurrencySettings::number($item->discount) }}</td>
                            <td>{{ CurrencySettings::number(OrderLine::tax($item)) }}</td>
                        @endunless
                        <td>{{ CurrencySettings::number(OrderLine::total($item)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $simple ? 4 : ($showDescription ? 8 : 7) }}">
                            {{ __('purchase_orders.items.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    {{-- الإجماليات بعملة النظام الافتراضية --}}
    <table class="totals">
        <tr>
            <td>{{ __('purchase_orders.totals.subtotal') }}</td>
            <td style="text-align: {{ $opposite }};">{{ CurrencySettings::format($totals['subtotal']) }}</td>
        </tr>
        <tr>
            <td>{{ __('purchase_orders.totals.discount') }}</td>
            <td style="text-align: {{ $opposite }};">{{ CurrencySettings::format($totals['discount']) }}</td>
        </tr>
        <tr>
            <td>{{ __('purchase_orders.totals.tax') }}</td>
            <td style="text-align: {{ $opposite }};">{{ CurrencySettings::format($totals['tax']) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('purchase_orders.totals.grand_total') }}</td>
            <td style="text-align: {{ $opposite }};">{{ CurrencySettings::format($totals['grand_total']) }}</td>
        </tr>
    </table>

    @if (! $compact && filled($purchaseOrder->notes))
        <p class="notes">
            <strong>{{ __('purchase_orders.fields.notes') }}:</strong> {{ $purchaseOrder->notes }}
        </p>
    @endif

    @if ($showSignatures)
        <table class="signatures">
            <tr>
                <td>
                    {{ __('purchase_orders.pdf.signature') }}
                    @if ($signature = PrintSettings::signatureUrl())
                        <br><img src="{{ $signature }}" alt="" style="max-height: 45px;">
                    @endif
                    <div class="sign-line">{{ __('purchase_orders.fields.creator') }}</div>
                </td>
                <td>
                    {{ __('purchase_orders.pdf.stamp') }}
                    @if ($stamp = PrintSettings::stampUrl())
                        <br><img src="{{ $stamp }}" alt="" style="max-height: 45px;">
                    @endif
                    <div class="sign-line">{{ __('purchase_orders.fields.approved_by') }}</div>
                </td>
            </tr>
        </table>
    @endif

    <div class="footer">
        {{ $business['name'] ?? '' }} — {{ __('purchase_orders.pdf.printed_at') }}:
        {{ LanguageSettings::formatDate(now(), true) }}
    </div>

    @if (! empty($autoPrint))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>

</html>
