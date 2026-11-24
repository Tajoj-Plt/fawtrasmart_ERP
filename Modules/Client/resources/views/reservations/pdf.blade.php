{{--
    تأكيدُ الحجز مرفقاً PDF — يُصيَّر بـTCPDF (`SalesDocumentMailer::pdf`).

    **جداولُ لا flexbox**: TCPDF لا يعرف `display:flex` فيُلصق التسميةَ
    بقيمتها، ولا متغيّراتِ CSS ولا صوراً عن بُعد. لذلك هي غيرُ `print.blade`
    الذي يُطبع من المتصفّح.
--}}
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $business = \App\Support\BusinessInfo::class;
    $time = substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5);
    $rows = [
        [__('reservations.columns.client'), $reservation->client_name ?: '—'],
        [__('reservations.columns.phone'), $reservation->client?->mobile ?: ($reservation->client?->phone ?: '—')],
        [__('reservations.columns.service'), $reservation->service_name ?: '—'],
        [__('reservations.columns.employee'), $reservation->employee?->full_name ?: '—'],
        [__('reservations.columns.date'), $reservation->reservation_date?->format('Y-m-d')],
        [__('reservations.print.time'), $time.' ('.__('reservations.ui.minutes', ['n' => $reservation->duration]).')'],
    ];

    if ($reservation->invoice) {
        $rows[] = [__('reservations.ui.invoice_number'), $reservation->invoice->code];
    }
@endphp
<table width="100%" cellpadding="4">
    <tr>
        <td width="60%">
            <span style="font-size:15pt;font-weight:bold;">{{ $business::name() }}</span><br>
            @if ($business::phone())<span style="color:#666666;">{{ $business::phone() }}</span><br>@endif
            @if ($business::address())<span style="color:#666666;">{{ $business::address() }}</span>@endif
        </td>
        <td width="40%" align="left">
            <span style="font-size:14pt;font-weight:bold;">{{ __('reservations.print.title') }}</span><br>
            <span style="font-size:12pt;">{{ $reservation->code }}</span><br>
            <span style="color:#666666;">{{ $reservation->status->label() }}</span>
        </td>
    </tr>
</table>
<hr>
<br>
<table width="100%" cellpadding="6">
    @foreach ($rows as $index => [$label, $value])
        <tr style="background-color:{{ $index % 2 ? '#ffffff' : '#f5f6f8' }};">
            <td width="35%" style="color:#555555;">{{ $label }}</td>
            <td width="65%" style="font-weight:bold;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
<br><br>
<table width="100%" cellpadding="6">
    <tr>
        <td width="65%" style="color:#555555;">{{ __('reservations.columns.price') }}</td>
        <td width="35%" align="left">{{ $money($reservation->unit_price) }}</td>
    </tr>
    @if ((float) $reservation->discount_amount > 0)
        <tr>
            <td style="color:#555555;">{{ __('reservations.columns.discount') }}</td>
            <td align="left">-{{ $money($reservation->discount_amount) }}</td>
        </tr>
    @endif
    @if ((float) $reservation->tax_amount > 0)
        <tr>
            <td style="color:#555555;">{{ __('reservations.columns.tax') }}</td>
            <td align="left">{{ $money($reservation->tax_amount) }}</td>
        </tr>
    @endif
    <tr style="background-color:#1f3a5f;color:#ffffff;">
        <td style="font-weight:bold;">{{ __('reservations.columns.total') }}</td>
        <td align="left" style="font-weight:bold;">{{ $money($reservation->total) }} {{ $reservation->currency }}</td>
    </tr>
</table>
@if ($reservation->notes)
    <br><br>
    <table width="100%" cellpadding="6">
        <tr style="background-color:#f5f6f8;"><td><strong>{{ __('reservations.attributes.notes') }}:</strong> {{ $reservation->notes }}</td></tr>
    </table>
@endif
<br><br>
<span style="color:#999999;font-size:8pt;">{{ __('reservations.print.footer', ['date' => now()->format('Y-m-d H:i')]) }}</span>
