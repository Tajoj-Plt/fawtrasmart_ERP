{{-- صفحة طباعة مستقلّة — HTML مهيّأ للطباعة كما في أذون المخزون، لا مكتبة PDF. --}}
@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;

    // كل مبلغٍ بعملة العقد المسجَّلة
    $money = fn ($v) => $contract->money($v);
    $num = fn ($v) => number_format((float) $v, 2);
    $org = \App\Support\OrganizationInfo::all();
    $own = ($ownership ?? 'managed') === 'own' || ! $lessor;
    $lessorName = $own ? ($org['name'] ?? config('app.name')) : $lessor->display_name;
    $tenantMobile = $contract->tenant?->mobile ?: $contract->tenant?->phone;
    $rows = $contract->schedules->where('status', '!=', 'cancelled');
    $rentTotal = (float) $rows->where('kind', 'rent')->sum('amount_due');
    // الإجمالي المطبوع هو مجموع أقساط الإيجار نفسها — فلا يختلف رقمٌ عن الجدول تحته
    $gross = $rentTotal > 0 ? $rentTotal : $contract->gross_total;
    $rate = (float) $contract->tax_rate / 100;
    $taxAmount = $rate > 0 ? round($gross - $gross / (1 + $rate), 2) : 0;
    $words = \App\Helpers\NumberToWords::arabic($gross, $contract->currency_code);
    $signedAt = $contract->signed_at?->format('Y-m-d');
    $idLabels = collect(V::ID_TYPES)->map(fn ($m) => $m['label']);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>عقد {{ $contract->code }}</title>
    <style>
        body { font-family: 'Cairo', 'Tahoma', sans-serif; color: #222; margin: 0; padding: 24px; font-size: 13px; line-height: 1.8; }
        h1 { font-size: 20px; text-align: center; margin: 8px 0 2px; }
        h2 { font-size: 14px; border-bottom: 1px solid #999; padding-bottom: 4px; margin: 18px 0 8px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .muted { color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #bbb; padding: 5px 8px; text-align: right; }
        th { background: #f2f2f2; }
        .kv td:first-child { width: 22%; background: #f7f7f7; font-weight: 600; }
        .num { direction: ltr; unicode-bidi: embed; display: inline-block; }
        tfoot td { font-weight: 700; background: #f7f7f7; }
        .signs { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
        .signs div { width: 42%; text-align: center; }
        .signs .line { border-top: 1px solid #222; margin-top: 46px; padding-top: 6px; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button { padding: 6px 18px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">طباعة</button></div>

    <div class="head">
        <div>
            <strong>{{ $org['name'] ?? config('app.name') }}</strong>
            <div class="muted">{{ $org['address'] ?? '' }}</div>
            <div class="muted">{{ $org['phone'] ?? '' }}</div>
            @if (! empty($org['tax_number'])) <div class="muted">الرقم الضريبي: {{ $org['tax_number'] }}</div> @endif
        </div>
        <div class="muted" style="text-align:left">
            رقم العقد: <strong>{{ $contract->code }}</strong><br>
            الحالة: {{ $contract->status_meta['label'] }}<br>
            تاريخ العقد: {{ $signedAt ?? $contract->created_at?->format('Y-m-d') }}<br>
            تاريخ الطباعة: {{ now()->format('Y-m-d') }}
        </div>
    </div>

    <h1>عقد {{ $contract->type_label }}</h1>

    <h2>الأطراف</h2>
    <table class="kv">
        <tr><td>الطرف الأول (المؤجِّر)</td><td>{{ $lessorName ?: '—' }}</td></tr>
        @if ($own)
            @if (! empty($org['commercial_registration'])) <tr><td>السجل التجاري</td><td><span class="num">{{ $org['commercial_registration'] }}</span></td></tr> @endif
            @if (! empty($org['phone'])) <tr><td>هاتف المؤجِّر</td><td><span class="num">{{ $org['phone'] }}</span></td></tr> @endif
        @else
            <tr><td>هوية المؤجِّر</td><td>{{ $idLabels[$lessor->id_type] ?? 'رقم الهوية' }}: <span class="num">{{ $lessor->id_number ?: '—' }}</span></td></tr>
            <tr><td>جوال المؤجِّر</td><td><span class="num">{{ $lessor->mobile ?: ($lessor->phone ?: '—') }}</span></td></tr>
            @if (filled($lessor->getAttribute('tax_number'))) <tr><td>الرقم الضريبي للمؤجِّر</td><td><span class="num">{{ $lessor->getAttribute('tax_number') }}</span></td></tr> @endif
        @endif
        <tr><td>الطرف الثاني (المستأجر)</td><td>{{ $contract->tenant->display_name ?? '—' }}</td></tr>
        <tr><td>هوية المستأجر</td><td>{{ $tenantProfile?->id_type_label ?? 'رقم الهوية' }}: <span class="num">{{ $tenantProfile?->id_number ?: '—' }}</span>@if ($tenantProfile?->nationality) — الجنسية: {{ $tenantProfile->nationality }}@endif</td></tr>
        <tr><td>جوال المستأجر</td><td><span class="num">{{ $tenantMobile ?: '—' }}</span></td></tr>
        @if (filled($contract->tenant?->tax_number)) <tr><td>الرقم الضريبي للمستأجر</td><td><span class="num">{{ $contract->tenant->tax_number }}</span></td></tr> @endif
    </table>

    <h2>العين المؤجَّرة</h2>
    <table class="kv">
        <tr><td>العقار</td><td>{{ $contract->unit->building->name ?? '—' }} — {{ collect([$contract->unit->building->city ?? null, $contract->unit->building->district ?? null, $contract->unit->building->address ?? null])->filter()->implode('، ') }}</td></tr>
        <tr><td>الوحدة</td><td>{{ $contract->unit->type_label ?? '' }} رقم {{ $contract->unit->unit_no ?? '—' }}@if ($contract->unit?->floor) — {{ $contract->unit->floor->label }}@endif @if ($contract->unit?->area_sqm) — {{ $contract->unit->area_sqm }} م²@endif</td></tr>
    </table>

    <h2>المدّة والقيمة</h2>
    <table class="kv">
        <tr><td>المدّة</td><td>من {{ $contract->start_date->format('Y-m-d') }} إلى {{ $contract->end_date->format('Y-m-d') }} ({{ $contract->duration_label }})</td></tr>
        <tr><td>العملة</td><td>{{ $contract->currency_code }}</td></tr>
        <tr><td>القيمة السنوية</td><td>{{ $money($contract->annual_value) }}</td></tr>
        <tr><td>قيمة العقد قبل الضريبة</td><td>{{ $money($rate > 0 ? $gross - $taxAmount : $gross) }}</td></tr>
        <tr><td>الضريبة</td><td>{{ $contract->tax_label }}@if ($rate > 0) — {{ $money($taxAmount) }}@endif</td></tr>
        <tr><td>إجمالي قيمة العقد</td><td><strong>{{ $money($gross) }}</strong></td></tr>
        <tr><td>الإجمالي كتابةً</td><td>{{ $words }}</td></tr>
        <tr><td>دورية السداد</td><td>{{ V::label(V::PAYMENT_FREQUENCIES, $contract->payment_frequency) }}</td></tr>
        <tr><td>التأمين</td><td>{{ $money((float) $contract->security_deposit + (float) $contract->deposit_carried) }}@if ((float) $contract->deposit_carried > 0) <span class="muted">(منه {{ $money($contract->deposit_carried) }} مرحَّل من العقد السابق)</span>@endif</td></tr>
    </table>

    <h2>جدول الدفعات</h2>
    <table>
        <thead><tr><th>#</th><th>النوع</th><th>تاريخ الاستحقاق</th><th>المبلغ ({{ $contract->currency_code }})</th></tr></thead>
        <tbody>
            @foreach ($rows as $schedule)
                <tr><td>{{ $schedule->installment_no ?: '—' }}</td><td>{{ $schedule->kind_label }}</td><td>{{ $schedule->due_date->format('Y-m-d') }}</td><td>{{ $num($schedule->amount_due) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">إجمالي أقساط الإيجار</td><td>{{ $money($rentTotal) }}</td></tr></tfoot>
    </table>

    @if ($contract->terms->isNotEmpty())
        <h2>الشروط والبنود</h2>
        <ol>
            @foreach ($contract->terms as $term)
                <li><strong>{{ $term->title }}</strong>@if ($term->body)<div style="white-space:pre-line">{{ $term->body }}</div>@endif</li>
            @endforeach
        </ol>
    @endif

    <div class="signs">
        <div>
            <strong>الطرف الأول (المؤجِّر)</strong><br>{{ $lessorName ?: '—' }}
            <div class="line">التوقيع</div>
            <div class="muted">التاريخ: {{ $signedAt ?? '____ / ____ / ________' }}</div>
        </div>
        <div>
            <strong>الطرف الثاني (المستأجر)</strong><br>{{ $contract->tenant->display_name ?? '—' }}
            <div class="line">التوقيع</div>
            <div class="muted">التاريخ: {{ $signedAt ?? '____ / ____ / ________' }}</div>
        </div>
    </div>
</body>
</html>
