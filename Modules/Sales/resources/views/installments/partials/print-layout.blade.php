{{--
    ورقة اتفاقية التقسيط المطبوعة.

    لم يكن للقسم مطبوعةٌ أصلاً: لا `print()` ولا `pdf()` ولا قالب. فعقدُ
    التقسيط — وهو مستندٌ يُوقَّع ويُسلَّم للعميل — لم يكن يخرج من النظام.

    مستقلّةٌ عن `sales::master` عمداً، وتقرأ متغيّرات `--app-*` التي تحقنها
    `App\Support\ThemeSettings` فيسري عليها ما يُضبط في «مظهر النظام».

    الوسائط:
      agreement  موديل `Installment`
      autoPrint  فتح نافذة الطباعة عند التحميل
--}}

@php
    use App\Support\BusinessInfo;
    use App\Support\CurrencySettings;
    use App\Support\PrintSettings;

    $isRtl = $languageIsRtl ?? true;

    $safe = function (callable $read, $fallback = '') {
        try { return $read() ?: $fallback; } catch (\Throwable $e) { return $fallback; }
    };

    $businessName = $safe(fn () => BusinessInfo::name());
    $taxNumber = $safe(fn () => BusinessInfo::taxNumber());
    $logo = $safe(fn () => PrintSettings::logoUrl(), null);
    $stamp = $safe(fn () => PrintSettings::stampUrl(), null);
    $signature = $safe(fn () => PrintSettings::signatureUrl(), null);

    $terms = array_filter([
        __('sales_installments.fields.total_amount') => $agreement->money($agreement->total_amount),
        __('sales_installments.fields.down_payment') => $agreement->money($agreement->down_payment),
        __('sales_installments.fields.principal_amount') => $agreement->money($agreement->principal_amount),
        __('sales_installments.fields.profit_amount') => (float) $agreement->profit_rate > 0
            ? $agreement->money($agreement->profit_amount).' ('.$agreement->profit_rate.'%)' : null,
        __('sales_installments.fields.admin_fees') => (float) $agreement->admin_fees > 0
            ? $agreement->money($agreement->admin_fees) : null,
        __('sales_installments.fields.tax_amount') => (float) $agreement->tax_rate > 0
            ? $agreement->money($agreement->tax_amount)
                .' ('.rtrim(rtrim(number_format((float) $agreement->tax_rate, 2), '0'), '.').'%'
                .($agreement->tax?->name ? ' — '.$agreement->tax->name : '').')'
            : null,
        __('sales_installments.fields.financed_amount') => $agreement->money($agreement->financed_amount),
        __('sales_installments.fields.installment_number') => CurrencySettings::number($agreement->installment_number, 0),
        __('sales_installments.fields.payment_rate') => __('sales_installments.rates.'.$agreement->rate_key),
        __('sales_installments.fields.payment_method') => $agreement->paymentMethod?->name,

        /* سياسة الغرامة تُطبَع في شروط الاتفاق: مبلغٌ يُحمَّل على العميل
           بمقتضى هذا المستند، فالمستندُ الذي يوقّعه أولى أن يقوله. وتُحذَف
           من المطبوع إن لم تُضبَط — «بلا غرامة» سطرٌ لا يقول شيئاً. */
        __('sales_installments.sections.penalty') => $agreement->hasLateFeePolicy()
            ? __('sales_installments.late_fee.policy', [
                'type' => __('sales_installments.late_fee_types.'.$agreement->late_fee_type),
                'value' => $agreement->late_fee_type === 'percent'
                    ? rtrim(rtrim(number_format((float) $agreement->late_fee_value, 2), '0'), '.').'%'
                    : $agreement->money($agreement->late_fee_value),
                'grace' => CurrencySettings::number((int) $agreement->late_fee_grace_days, 0),
                'basis' => __('sales_installments.late_fee_bases.'.($agreement->late_fee_basis ?: 'once')),
            ])
            : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('sales_installments.print.title') }} — {{ $agreement->code }}</title>

    <style>
        :root {
            --ink: var(--app-text, #2f2f2f);
            --muted: var(--app-muted, #6e6b7b);
            --line: var(--app-border, #d9dce3);
            --brand: var(--app-primary, #7367f0);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 24px;
            font-family: var(--app-font, "Tajawal", "Segoe UI", Tahoma, sans-serif);
            color: var(--ink); background: #fff;
            font-size: 12.5px; line-height: 1.6;
        }

        .sheet { max-width: 860px; margin: 0 auto; }

        .head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 24px; padding-bottom: 14px; margin-bottom: 18px;
            border-bottom: 2px solid var(--brand);
        }
        .head__logo { max-height: 60px; max-width: 170px; object-fit: contain; }
        .head__name { font-size: 17px; font-weight: 700; margin: 0 0 3px; }
        .head__meta { color: var(--muted); font-size: 11.5px; margin: 0; }

        .doc-title { text-align: center; font-size: 19px; font-weight: 700; margin: 0 0 18px; }

        table { width: 100%; border-collapse: collapse; }

        .facts td { padding: 6px 10px; border: 1px solid var(--line); vertical-align: top; }
        .facts td:first-child {
            width: 34%; color: var(--muted); font-weight: 600;
            background: color-mix(in srgb, var(--brand) 4%, #fff);
        }

        .block { margin-top: 18px; }
        .block__title {
            font-size: 12.5px; font-weight: 700; margin: 0 0 7px;
            padding-bottom: 4px; border-bottom: 1px solid var(--line);
        }
        .block__body { white-space: pre-line; margin: 0; }

        .grid th, .grid td {
            padding: 6px 8px; border: 1px solid var(--line);
            font-variant-numeric: tabular-nums;
        }
        .grid th {
            background: color-mix(in srgb, var(--brand) 6%, #fff);
            font-size: 11px; font-weight: 700; color: var(--muted);
        }
        .grid td { text-align: center; }
        .grid tfoot td { font-weight: 700; background: color-mix(in srgb, var(--brand) 4%, #fff); }

        .signs {
            display: flex; justify-content: space-between; gap: 40px;
            margin-top: 42px; text-align: center;
        }
        .signs > div { flex: 1; }
        .signs img { max-height: 65px; max-width: 140px; object-fit: contain; display: block; margin: 0 auto 6px; }
        .signs span {
            display: block; padding-top: 8px;
            border-top: 1px solid var(--line); color: var(--muted); font-size: 11.5px;
        }

        .foot { margin-top: 24px; text-align: center; color: var(--muted); font-size: 10.5px; }

        @media print {
            body { padding: 0; }
            @page { margin: 12mm; }
            .grid tr { page-break-inside: avoid; }
        }
    </style>
</head>

<body>
    <div class="sheet">
        <header class="head">
            <div>
                @if ($logo)<img src="{{ $logo }}" alt="" class="head__logo">@endif
                @if ($businessName)<p class="head__name">{{ $businessName }}</p>@endif
                @if ($taxNumber)<p class="head__meta">{{ $taxNumber }}</p>@endif
            </div>

            <div style="text-align:{{ $isRtl ? 'left' : 'right' }}">
                <p class="head__meta">{{ __('sales_installments.fields.code') }}</p>
                <p class="head__name">{{ $agreement->code }}</p>
                <p class="head__meta">{{ optional($agreement->created_at)->format('Y-m-d') }}</p>
            </div>
        </header>

        <h1 class="doc-title">{{ __('sales_installments.print.title') }}</h1>

        @if ($agreement->client)
            <div class="block">
                <p class="block__title">{{ __('sales_installments.print.party') }}</p>
                <p class="block__body">
                    <strong>{{ $agreement->client->trade_name }}</strong>
                    @if ($agreement->client->code) — {{ $agreement->client->code }} @endif
                    @if ($agreement->invoice)
                        <br>{{ __('sales_installments.fields.invoice') }}:
                        {{ $agreement->invoice->code ?: '#'.$agreement->invoice->id }}
                    @endif
                </p>
            </div>
        @endif

        <table class="facts" style="margin-top:14px">
            @foreach ($terms as $label => $value)
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>

        {{-- جدول السداد: هو جوهر المستند — العميل يوقّع على مواعيدَ ومبالغ --}}
        <div class="block">
            <p class="block__title">{{ __('sales_installments.print.schedule') }}</p>

            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:60px">{{ __('sales_installments.fields.number') }}</th>
                        <th>{{ __('sales_installments.fields.due_date') }}</th>
                        <th>{{ __('sales_installments.fields.amount') }}</th>
                        <th>{{ __('sales_installments.fields.paid_amount') }}</th>
                        <th>{{ __('sales_installments.fields.remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agreement->details as $row)
                        <tr>
                            <td>{{ $row->installment_number }}</td>
                            <td>{{ optional($row->due_date)->format('Y-m-d') }}</td>
                            <td>{{ CurrencySettings::number($row->amount) }}</td>
                            <td>{{ CurrencySettings::number($row->paid_amount) }}</td>
                            <td>{{ CurrencySettings::number($row->remaining_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">{{ __('sales_installments.print.total_row') }} ({{ $agreement->currency_code }})</td>
                        <td>{{ CurrencySettings::number($agreement->details->sum('amount')) }}</td>
                        <td>{{ CurrencySettings::number($agreement->details->sum('paid_amount')) }}</td>
                        <td>{{ CurrencySettings::number($agreement->remaining_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($agreement->note)
            <div class="block">
                <p class="block__title">{{ __('sales_installments.fields.note') }}</p>
                <p class="block__body">{{ $agreement->note }}</p>
            </div>
        @endif

        <div class="signs">
            <div>
                <span>{{ __('sales_installments.print.signature_client') }}</span>
            </div>
            <div>
                @if ($signature)<img src="{{ $signature }}" alt="">@endif
                @if ($stamp)<img src="{{ $stamp }}" alt="">@endif
                <span>{{ __('sales_installments.print.signature_company') }}</span>
            </div>
        </div>

        @if ($businessName)<p class="foot">{{ $businessName }}</p>@endif
    </div>

    @if ($autoPrint ?? false)
        <script>
            window.addEventListener('load', function () {
                window.setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>
