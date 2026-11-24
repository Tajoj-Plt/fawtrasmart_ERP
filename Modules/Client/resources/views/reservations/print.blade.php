{{--
    مطبوعةُ الحجز — صفحةٌ مستقلّة بلا قوائم النظام، تُفتح في تبويبٍ وتُطبع.
    تحمل ما يحتاجه العميلُ أو الموظّف: الرقم والموعد والخدمة والموظّف والسعر.
--}}
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $rtl = app()->getLocale() !== 'en';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('reservations.print.title') }} {{ $reservation->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Tajawal", "Segoe UI", Tahoma, sans-serif; color: #222; margin: 0; background: #f4f4f7; }
        .sheet { max-width: 720px; margin: 24px auto; background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 18px; }
        .head h1 { margin: 0; font-size: 22px; }
        .head .code { font-size: 18px; font-weight: 700; }
        .status { display: inline-block; padding: 3px 12px; border-radius: 20px; color: #fff; font-size: 13px; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { text-align: start; padding: 9px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        th { width: 34%; color: #666; font-weight: 600; }
        .money td { text-align: end; }
        .total td, .total th { font-size: 16px; font-weight: 800; color: #222; border-top: 2px solid #222; }
        .notes { background: #f8f8fb; padding: 12px; border-radius: 8px; font-size: 14px; }
        .foot { margin-top: 26px; font-size: 12px; color: #888; text-align: center; }
        .actions { text-align: center; margin: 16px; }
        .actions button { padding: 8px 22px; border: 0; background: #7367f0; color: #fff; border-radius: 6px; font-size: 14px; cursor: pointer; }
        [dir="ltr"] .num { direction: ltr; }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; max-width: none; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">{{ __('reservations.actions.print') }}</button></div>

    <div class="sheet">
        <div class="head">
            <div>
                <h1>{{ __('reservations.print.title') }}</h1>
                <div class="status" style="background: {{ $reservation->status->color() }}">{{ $reservation->status->label() }}</div>
                @if ($reservation->invoice)
                    <div class="status" style="background: #27ae60">{{ __('reservations.links.invoiced_badge') }}</div>
                @endif
            </div>
            <div style="text-align:end">
                <div class="code">{{ $reservation->code }}</div>
                <div><strong>{{ \App\Support\BusinessInfo::name() }}</strong></div>
                @if (\App\Support\BusinessInfo::phone())
                    <div dir="ltr">{{ \App\Support\BusinessInfo::phone() }}</div>
                @endif
                @if (\App\Support\BusinessInfo::address())
                    <div>{{ \App\Support\BusinessInfo::address() }}</div>
                @endif
            </div>
        </div>

        <table>
            <tr><th>{{ __('reservations.columns.client') }}</th><td>{{ $reservation->client_name ?: '—' }}</td></tr>
            <tr><th>{{ __('reservations.columns.phone') }}</th><td dir="ltr" style="text-align:start">{{ $reservation->client?->mobile ?: ($reservation->client?->phone ?: '—') }}</td></tr>
            <tr><th>{{ __('reservations.columns.service') }}</th><td>{{ $reservation->service_name ?: '—' }}</td></tr>
            <tr><th>{{ __('reservations.columns.employee') }}</th><td>{{ $reservation->employee?->full_name ?? '—' }}</td></tr>
            <tr><th>{{ __('reservations.columns.date') }}</th><td>{{ $reservation->reservation_date?->format('Y-m-d') }}</td></tr>
            <tr>
                <th>{{ __('reservations.print.time') }}</th>
                <td><span dir="ltr">{{ substr((string) $reservation->start_time, 0, 5) }} – {{ substr((string) $reservation->end_time, 0, 5) }}</span>
                    ({{ __('reservations.ui.minutes', ['n' => $reservation->duration]) }})</td>
            </tr>
            @if ($reservation->invoice)
                <tr><th>{{ __('reservations.ui.invoice_number') }}</th><td>{{ $reservation->invoice->code }}</td></tr>
            @endif
        </table>

        <table class="money">
            <tr><th>{{ __('reservations.columns.price') }}</th><td>{{ $money($reservation->unit_price) }}</td></tr>
            @if ((float) $reservation->discount_amount > 0)
                <tr><th>{{ __('reservations.columns.discount') }}</th><td>−{{ $money($reservation->discount_amount) }}</td></tr>
            @endif
            @if ((float) $reservation->tax_amount > 0)
                <tr><th>{{ __('reservations.columns.tax') }}</th><td>{{ $money($reservation->tax_amount) }}</td></tr>
            @endif
            <tr class="total"><th>{{ __('reservations.columns.total') }}</th><td>{{ $money($reservation->total) }} {{ $reservation->currency }}</td></tr>
        </table>

        @if ($reservation->notes)
            <div class="notes"><strong>{{ __('reservations.attributes.notes') }}:</strong> {{ $reservation->notes }}</div>
        @endif

        <div class="foot">{{ __('reservations.print.footer', ['date' => now()->format('Y-m-d H:i')]) }}</div>
    </div>

    {{-- الزرُّ «طباعة» يفتح هذه الصفحة في تبويب — فتُفتح نافذةُ الطباعة مباشرةً بدل صفحةٍ ينتظر فيها المستخدمُ ما يفعل --}}
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    </script>
</body>
</html>
