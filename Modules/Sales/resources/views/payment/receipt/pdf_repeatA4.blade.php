{{--
    سند قبض من عميل — مقاس A4.

    ما تأتي به الإعدادات:
      • اسم المنشأة وعنوانها ← «بيانات المنشأة» (`OrganizationInfo`)
      • العملة والكسور      ← «العملات»
      • صيغة التاريخ        ← إعدادات اللغة النشطة
      • طريقة الدفع         ← «طرق الدفع» (الاسم لا الرقم)

    ما أُصلح:
      • *اسم منشأةٍ مكتوبٌ في القالب* — «مؤسسة أعمال خاصة للنجارة»
        و«الرياض» — يظهران لكل منشأة تطبع سنداً، لأنّ `$receipt->branch`
        لا وجود له أصلاً: لا عمود `branch_id` في `payments_process`،
        فالعلاقة فارغةٌ دائماً ويظهر البديل المكتوب دائماً.
      • *«ريال» مكتوبة* بدل رمز العملة المضبوط.
      • *`$receipt->payment_date->format('d/m/Y')`* على حقلٍ قد يكون
        فارغاً — سندٌ بلا تاريخ يرمي «Call to a member function
        format() on null»، وصيغةُ التاريخ تخالف اللغة النشطة.
      • *طباعةٌ تلقائيةٌ بلا شرط*: شاشة عرض السند تُحمّل هذا القالب في
        إطارٍ بـ`?embed=1`، ففتحُ الشاشة وحده كان يفتح حوار الطباعة.
      • *«التوقيع» موظّف الفاتورة* لا مُحصِّل الدفعة.
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use App\Support\OrganizationInfo;

    $isRtl = LanguageSettings::isRtl();
    $invoice = $receipt->invoice;

    $clientName = $invoice && $invoice->client
        ? ($invoice->client->trade_name
            ?: trim(($invoice->client->first_name ?? '') . ' ' . ($invoice->client->last_name ?? '')))
        : ($receipt->client->trade_name ?? __('sales_documents.ui.unknown'));

    $methodName = $receipt->payment_type->name ?? __('sales_documents.ui.dash');
    $paidStamp = (int) $receipt->payment_status === 1;
    $cancelled = (int) $receipt->payment_status === 5;
@endphp
<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sales_payment.receipt.title', ['number' => $receipt->reference_number ?: $receipt->id]) }}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');

        @page { size: A4; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 16px;
            font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            background: #eef0f4;
            color: #1b1f27;
            font-size: 12pt;
            line-height: 1.6;
        }

        .sheet {
            width: 186mm;
            margin: 0 auto;
            background: #fff;
            padding: 14mm;
            border: 1px solid #d6dae2;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .12);
            position: relative;
        }

        .sheet__head {
            text-align: center;
            padding-bottom: 6mm;
            margin-bottom: 7mm;
            border-bottom: 2px solid #1b1f27;
        }

        .sheet__org { font-size: 17pt; font-weight: 700; }
        .sheet__org-sub { font-size: 10pt; color: #6b7280; }

        .sheet__title {
            display: inline-block;
            margin-top: 5mm;
            padding: 2mm 8mm;
            border: 1.5px solid #1b1f27;
            border-radius: 3px;
            font-size: 15pt;
            font-weight: 700;
        }

        .rows { margin-bottom: 7mm; }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 6mm;
            padding: 2.6mm 0;
            border-bottom: 1px dotted #c9ced8;
        }

        .row__label { color: #6b7280; font-size: 11pt; }
        .row__value { font-weight: 500; text-align: {{ $isRtl ? 'left' : 'right' }}; }

        .row--amount {
            border: 2px solid #1b1f27;
            border-radius: 3px;
            padding: 4mm 5mm;
            margin-top: 4mm;
            background: #f8f9fb;
        }

        .row--amount .row__label { font-size: 12pt; font-weight: 700; color: #1b1f27; }
        .row--amount .row__value { font-size: 19pt; font-weight: 700; font-variant-numeric: tabular-nums; }

        .note {
            border: 1px solid #d6dae2;
            border-radius: 3px;
            padding: 4mm;
            margin-bottom: 7mm;
            font-size: 11pt;
        }

        .note strong { display: block; color: #6b7280; font-size: 9.5pt; margin-bottom: 1mm; }

        .signs { display: flex; gap: 10mm; margin-top: 16mm; }

        .signs > div {
            flex: 1;
            border-top: 1px solid #9aa1ad;
            padding-top: 2mm;
            font-size: 10pt;
            color: #6b7280;
            text-align: center;
        }

        .thanks { text-align: center; margin-top: 8mm; font-size: 10.5pt; color: #6b7280; }

        /* ── الختم ── */
        .stamp {
            position: absolute;
            top: 42%;
            {{ $isRtl ? 'left' : 'right' }}: 16mm;
            transform: rotate(-18deg);
            opacity: .22;
            pointer-events: none;
        }

        .stamp span {
            display: inline-block;
            border: 5px solid #1a7f4b;
            color: #1a7f4b;
            border-radius: 6px;
            padding: 3mm 10mm;
            font-size: 30pt;
            font-weight: 700;
        }

        .stamp--void span { border-color: #b3261e; color: #b3261e; }

        /* ── شريط الأدوات ── */
        .controls {
            width: 186mm;
            margin: 0 auto 10px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .controls button {
            font: inherit;
            font-size: 10pt;
            padding: 7px 16px;
            border: 1px solid #c9ced8;
            border-radius: 5px;
            background: #fff;
            cursor: pointer;
        }

        .controls button:hover { background: #f2f4f7; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; border: none; box-shadow: none; padding: 0; }
            .controls { display: none !important; }
        }
    </style>
</head>

<body>
    <div class="controls">
        <button type="button" onclick="window.print()">{{ __('sales_documents.print.print') }}</button>
        <button type="button" onclick="window.close()">{{ __('sales_documents.print.close') }}</button>
    </div>

    <div class="sheet">
        <div class="sheet__head">
            <div class="sheet__org">{{ OrganizationInfo::name() }}</div>
            <div class="sheet__org-sub">{{ OrganizationInfo::address() }}</div>
            <div class="sheet__org-sub">{{ OrganizationInfo::phone() }}</div>

            @if (OrganizationInfo::taxNumber())
                <div class="sheet__org-sub">
                    {{ __('sales_payment.receipt.tax_number') }}: {{ OrganizationInfo::taxNumber() }}
                </div>
            @endif

            <div class="sheet__title">{{ __('sales_payment.receipt.heading') }}</div>
        </div>

        <div class="rows">
            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.number') }}</span>
                <span class="row__value">{{ $receipt->reference_number ?: str_pad($receipt->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.date') }}</span>
                <span class="row__value">
                    {{ LanguageSettings::formatDate($receipt->payment_date ?: $receipt->created_at) }}
                </span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.received_from') }}</span>
                <span class="row__value">{{ $clientName }}</span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.invoice') }}</span>
                <span class="row__value">{{ $invoice->code ?? __('sales_documents.ui.dash') }}</span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.method') }}</span>
                <span class="row__value">{{ $methodName }}</span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.treasury') }}</span>
                <span class="row__value">{{ $receipt->treasury_name ?: __('sales_documents.ui.dash') }}</span>
            </div>

            <div class="row">
                <span class="row__label">{{ __('sales_payment.receipt.received_by') }}</span>
                <span class="row__value">{{ $receipt->recorded_by_name ?: __('sales_documents.ui.dash') }}</span>
            </div>

            @if ($receipt->payment_data)
                <div class="row">
                    <span class="row__label">{{ __('sales_payment.receipt.reference_data') }}</span>
                    <span class="row__value">{{ $receipt->payment_data }}</span>
                </div>
            @endif

            <div class="row row--amount">
                <span class="row__label">{{ __('sales_payment.receipt.amount') }}</span>
                <span class="row__value">{{ CurrencySettings::format($receipt->amount) }}</span>
            </div>
        </div>

        @if ($invoice)
            <div class="note">
                <strong>{{ __('sales_payment.receipt.invoice_state') }}</strong>
                {{ __('sales_payment.receipt.invoice_totals', [
                    'total' => CurrencySettings::format($invoice->grand_total ?? 0),
                    'due' => CurrencySettings::format(max(0, (float) ($invoice->due_value ?? 0))),
                ]) }}
            </div>
        @endif

        @if ($receipt->notes)
            <div class="note">
                <strong>{{ __('sales_payment.receipt.notes') }}</strong>
                {{ $receipt->notes }}
            </div>
        @endif

        <div class="signs">
            <div>{{ __('sales_payment.receipt.payer_signature') }}</div>
            <div>{{ __('sales_payment.receipt.receiver_signature') }}</div>
        </div>

        <div class="thanks">{{ __('sales_payment.receipt.thanks') }}</div>

        {{-- الختم يتبع حالة السند: «ملغى» ختمٌ أحمر لا «مدفوع» أخضر --}}
        @if ($cancelled)
            <div class="stamp stamp--void"><span>{{ __('sales_payment.receipt.stamp_void') }}</span></div>
        @elseif ($paidStamp)
            <div class="stamp"><span>{{ __('sales_payment.receipt.stamp_paid') }}</span></div>
        @endif
    </div>

    <script>
        /* الطباعة التلقائية للفتح المباشر وحده. شاشة عرض السند تُحمّل
           هذا القالب في إطارٍ بـ`?embed=1`، وكانت الطباعة لا تسأل عن
           الوسيط فيُفتح حوار الطباعة بمجرّد فتح الشاشة. */
        @unless (request()->boolean('embed'))
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 400);
            });
        @endunless
    </script>
</body>

</html>
