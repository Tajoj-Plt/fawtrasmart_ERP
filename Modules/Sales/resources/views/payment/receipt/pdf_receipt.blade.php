{{--
    سند قبض من عميل — إيصالٌ حراريّ بعرض ٨٠ ملم.

    نفس أعطال قالب A4 كانت هنا حرفاً بحرف (نسخةٌ منه): اسمُ منشأةٍ
    مكتوبٌ في القالب عبر `$receipt->branch` — وهي علاقةٌ فارغةٌ دائماً
    إذ لا عمود `branch_id` في `payments_process` — و«ريال» مكتوبة،
    و`payment_date->format()` على حقلٍ قد يكون فارغاً، وطباعةٌ تلقائيةٌ
    تُفتح حتى داخل إطار المعاينة.

    وزيد عليها ما يخصّ الحراريّ: عرضُ الورقة ٨٠ ملم لا A4، وهامشٌ صفر،
    وخطٌّ أكبر — الطابعة الحرارية لا تطبع الظلال ولا الألوان.
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

        @page { size: 80mm auto; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 10px;
            font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            background: #eef0f4;
            color: #000;
            font-size: 12px;
            line-height: 1.55;
        }

        .slip {
            width: 76mm;
            margin: 0 auto;
            background: #fff;
            padding: 5mm 4mm;
        }

        .slip__head { text-align: center; padding-bottom: 3mm; border-bottom: 1px dashed #000; }

        .slip__org { font-size: 15px; font-weight: 700; }
        .slip__sub { font-size: 11px; }

        .slip__title {
            margin-top: 3mm;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid #000;
            padding: 1mm 0;
        }

        .line {
            display: flex;
            justify-content: space-between;
            gap: 3mm;
            padding: 1.2mm 0;
            font-size: 12px;
        }

        .line span:first-child { color: #444; }
        .line span:last-child { font-weight: 500; text-align: {{ $isRtl ? 'left' : 'right' }}; }

        .amount {
            margin: 3mm 0;
            padding: 2.5mm 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            text-align: center;
        }

        .amount__label { font-size: 11px; }
        .amount__value { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .notes { font-size: 11px; padding-top: 2mm; border-top: 1px dashed #000; }

        .void {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            border: 2px solid #000;
            padding: 1.5mm 0;
            margin-top: 3mm;
        }

        .foot { text-align: center; font-size: 11px; margin-top: 4mm; padding-top: 2mm; border-top: 1px dashed #000; }

        .sign { margin-top: 8mm; border-top: 1px solid #000; padding-top: 1.5mm; font-size: 11px; text-align: center; }

        .controls { width: 76mm; margin: 0 auto 8px; display: flex; gap: 6px; justify-content: center; }

        .controls button {
            font: inherit;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #c9ced8;
            border-radius: 5px;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .slip { width: auto; padding: 0 2mm; }
            .controls { display: none !important; }
        }
    </style>
</head>

<body>
    <div class="controls">
        <button type="button" onclick="window.print()">{{ __('sales_documents.print.print') }}</button>
        <button type="button" onclick="window.close()">{{ __('sales_documents.print.close') }}</button>
    </div>

    <div class="slip">
        <div class="slip__head">
            <div class="slip__org">{{ OrganizationInfo::name() }}</div>
            <div class="slip__sub">{{ OrganizationInfo::phone() }}</div>

            @if (OrganizationInfo::taxNumber())
                <div class="slip__sub">
                    {{ __('sales_payment.receipt.tax_number') }}: {{ OrganizationInfo::taxNumber() }}
                </div>
            @endif

            <div class="slip__title">{{ __('sales_payment.receipt.heading') }}</div>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.number') }}</span>
            <span>{{ $receipt->reference_number ?: str_pad($receipt->id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.date') }}</span>
            <span>{{ LanguageSettings::formatDate($receipt->payment_date ?: $receipt->created_at) }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.received_from') }}</span>
            <span>{{ $clientName }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.invoice') }}</span>
            <span>{{ $invoice->code ?? __('sales_documents.ui.dash') }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.method') }}</span>
            <span>{{ $methodName }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.treasury') }}</span>
            <span>{{ $receipt->treasury_name ?: __('sales_documents.ui.dash') }}</span>
        </div>

        <div class="line">
            <span>{{ __('sales_payment.receipt.received_by') }}</span>
            <span>{{ $receipt->recorded_by_name ?: __('sales_documents.ui.dash') }}</span>
        </div>

        <div class="amount">
            <div class="amount__label">{{ __('sales_payment.receipt.amount') }}</div>
            <div class="amount__value">{{ CurrencySettings::format($receipt->amount) }}</div>
        </div>

        @if ($receipt->notes)
            <div class="notes">{{ $receipt->notes }}</div>
        @endif

        @if ($cancelled)
            <div class="void">{{ __('sales_payment.receipt.stamp_void') }}</div>
        @endif

        <div class="sign">{{ __('sales_payment.receipt.receiver_signature') }}</div>

        <div class="foot">{{ __('sales_payment.receipt.thanks') }}</div>
    </div>

    <script>
        {{-- الطباعة التلقائية للفتح المباشر وحده — لا داخل إطار المعاينة --}}
        @unless (request()->boolean('embed'))
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 400);
            });
        @endunless
    </script>
</body>

</html>
