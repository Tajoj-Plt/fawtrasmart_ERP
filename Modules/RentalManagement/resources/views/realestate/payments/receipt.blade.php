{{-- إيصال استلام — صفحة طباعة مستقلّة. --}}
@php
    use App\Helpers\NumberToWords;
    use Modules\RentalManagement\Support\RealEstateMoney;
    // الإيصال بعملة الدفعة المسجَّلة وقت القبض، لا بعملة النظام اليوم
    $currency = $payment->currency ?: RealEstateMoney::code();
    $money = fn ($v) => RealEstateMoney::format($v, $currency);
    $org = \App\Support\OrganizationInfo::all();
    $contract = $payment->contract;
    $taxRate = rtrim(rtrim(number_format((float) ($contract->tax_rate ?? 0), 2), '0'), '.');
    // مورِّد التأجير في المبنى المُدار هو المالك: رقمه الضريبي هو ما يُطبع مع الضريبة
    $vatOwner = ($parts['tax'] ?? 0) > 0 && ($managedOwner ?? null) && $managedOwner->is_vat_registered ? $managedOwner : null;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إيصال {{ $payment->code }}</title>
    <style>
        body { font-family: 'Cairo', 'Tahoma', sans-serif; color: #222; margin: 0; padding: 24px; font-size: 13px; line-height: 1.8; }
        .paper { max-width: 720px; margin: 0 auto; border: 1px solid #999; padding: 22px; position: relative; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #222; padding-bottom: 10px; }
        h1 { font-size: 19px; text-align: center; margin: 14px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { border: 1px solid #bbb; padding: 5px 8px; text-align: right; }
        th { background: #f2f2f2; }
        .kv td:first-child { width: 28%; background: #f7f7f7; font-weight: 600; }
        .amount { font-size: 20px; font-weight: 700; text-align: center; border: 2px solid #222; padding: 8px; margin: 12px 0 4px; }
        .words { text-align: center; font-weight: 600; margin: 0 0 12px; }
        .totals td:first-child { background: #f7f7f7; font-weight: 600; }
        .totals td:last-child { width: 30%; text-align: left; direction: ltr; }
        .void { position: absolute; top: 40%; left: 10%; right: 10%; text-align: center; font-size: 64px; color: rgba(200, 0, 0, .18); transform: rotate(-18deg); font-weight: 800; }
        .signs { display: flex; justify-content: space-between; margin-top: 40px; }
        .signs div { width: 40%; text-align: center; border-top: 1px solid #222; padding-top: 6px; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button { padding: 6px 18px; font-size: 14px; cursor: pointer; }
        .muted { color: #666; font-size: 12px; }
        @media print { .toolbar { display: none; } body { padding: 0; } .paper { border: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">طباعة</button></div>
    <div class="paper">
        @if ($payment->posting_status === 'cancelled') <div class="void">ملغى</div> @endif

        <div class="head">
            <div><strong>{{ $org['name'] ?? config('app.name') }}</strong><div class="muted">{{ $org['address'] ?? '' }}</div><div class="muted">{{ $org['phone'] ?? '' }} @if (! empty($org['tax_number'])) — الرقم الضريبي {{ $org['tax_number'] }} @endif</div></div>
            <div class="muted" style="text-align:left">رقم الإيصال: <strong>{{ $payment->code }}</strong><br>التاريخ: {{ $payment->payment_date->format('Y-m-d') }}</div>
        </div>

        <h1>إيصال استلام دفعة إيجار</h1>

        <table class="kv">
            <tr><td>استلمنا من</td><td>{{ $payment->client->display_name ?? '—' }}</td></tr>
            <tr><td>عن العقد</td><td>{{ $contract->code ?? '—' }} — {{ $contract->unit->full_label ?? '' }}</td></tr>
            <tr><td>طريقة الدفع</td><td>{{ $payment->method_label }} @if ($payment->reference_no) — مرجع {{ $payment->reference_no }} @endif</td></tr>
            @if ($vatOwner)
                <tr><td>المؤجِّر (مورِّد الخدمة)</td><td>{{ $vatOwner->name }} — الرقم الضريبي {{ $vatOwner->tax_number ?: '—' }}</td></tr>
            @endif
        </table>

        <div class="amount">{{ $money($payment->amount) }}</div>
        <p class="words">{{ NumberToWords::arabic($payment->amount, $currency) }}</p>

        <table>
            <thead><tr><th>القسط</th><th>النوع</th><th>تاريخ الاستحقاق</th><th>المسدَّد</th></tr></thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr><td>{{ $allocation->schedule->installment_no ?: '—' }}</td><td>{{ $allocation->schedule->kind_label ?? '—' }}</td><td>{{ $allocation->schedule?->due_date?->format('Y-m-d') }}</td><td>{{ $money($allocation->amount) }}</td></tr>
                @endforeach
                @if ((float) $payment->unallocated_amount > 0)
                    <tr><td colspan="3">دفعة مقدّمة</td><td>{{ $money($payment->unallocated_amount) }}</td></tr>
                @endif
            </tbody>
        </table>

        {{-- الضريبة سطرٌ مستقل: الإيجار قبلها، ثم هي، ثم الإجمالي --}}
        <table class="totals">
            @if ($parts['rent'] > 0)
                <tr><td>الإيجار قبل الضريبة</td><td>{{ $money($parts['net_rent']) }}</td></tr>
                <tr><td>ضريبة القيمة المضافة{{ $parts['tax'] > 0 ? ' ('.$taxRate.'%)' : '' }}</td><td>{{ $parts['tax'] > 0 ? $money($parts['tax']) : 'لا ضريبة' }}</td></tr>
            @endif
            @if ($parts['deposit'] > 0) <tr><td>تأمين مستردّ</td><td>{{ $money($parts['deposit']) }}</td></tr> @endif
            @if ($parts['maintenance'] > 0) <tr><td>صيانة على المستأجر</td><td>{{ $money($parts['maintenance']) }}</td></tr> @endif
            @if ($parts['advance'] > 0) <tr><td>دفعة مقدّمة</td><td>{{ $money($parts['advance']) }}</td></tr> @endif
            <tr><td>الإجمالي المستلَم</td><td><strong>{{ $money($payment->amount) }}</strong></td></tr>
        </table>

        @if ($payment->notes) <p class="muted">ملاحظات: {{ $payment->notes }}</p> @endif

        <div class="signs"><div>المستلم: {{ $payment->creator->name ?? '' }}</div><div>الختم</div></div>
    </div>
</body>
</html>
