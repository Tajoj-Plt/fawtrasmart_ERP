<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>قسيمة راتب #{{ $run->id }}</title>
    <style>
        body { font-family: 'Cairo','Tahoma',Arial,sans-serif; direction:rtl; color: var(--app-text, #1f2937); margin:0; padding:24px; background: var(--app-surface, #fff); }
        .header { text-align:center; padding-bottom:14px; border-bottom:2px solid var(--app-primary, #6366f1); margin-bottom:18px; }
        .header h1 { margin:0; color:var(--app-primary, #6366f1); font-size:1.6rem; }
        .header .sub { color: var(--app-muted, #6b7280); font-size:.9rem; margin-top:6px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px; }
        .info-row { display:flex; justify-content:space-between; padding:6px 12px; background: var(--app-body-bg, #f9fafb); border-radius:4px; font-size:.85rem; }
        .info-row .l { color: var(--app-muted, #6b7280); }
        .info-row .v { font-weight:700; }
        h3.section-title { color:var(--app-primary, #6366f1); font-size:1rem; margin:20px 0 10px; padding-bottom:6px; border-bottom: 1px solid var(--app-border, #e5e7eb); }
        table { width:100%; border-collapse:collapse; font-size:.85rem; margin-bottom:10px; }
        th { background: var(--app-body-bg, #f3f4f6); padding:8px 10px; text-align:right; border: 1px solid var(--app-border, #e5e7eb); font-weight:700; }
        td { padding:8px 10px; border: 1px solid var(--app-border, #e5e7eb); }
        tfoot th { background: var(--app-primary-soft, #eef2ff); }
        .net { background: var(--app-success, #10b981); color:#fff; padding:14px 22px; border-radius:8px; text-align:center; margin-top:18px; }
        .net .label { font-size:.85rem; }
        .net .amt { font-size:2rem; font-weight:700; margin-top:6px; }
        .footer { margin-top:30px; padding-top:14px; border-top: 1px solid var(--app-border, #e5e7eb); text-align:center; font-size:.75rem; color: var(--app-muted, #9ca3af); }
        .signatures { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px; padding-top:30px; }
        .sig-box { text-align:center; padding-top:30px; border-top: 1px dashed var(--app-muted, #9ca3af); font-size:.85rem; }
        @media print { body { padding:0; } .no-print { display:none; } }
    </style>
</head>
<body>

@php
    $emp = $run->employee;
    $name = trim(($emp->first_name ?? '').' '.($emp->middle_name ?? '').' '.($emp->nickname ?? ''));
    $additions = $run->items->where('item_type', 'addition');
    $deductions = $run->items->where('item_type', 'deduction');
    $advances = $run->items->where('item_type', 'advance');
    $retros = $run->items->where('item_type', 'retro');
@endphp

<button class="no-print" onclick="window.print()" style="padding:10px 20px;background:var(--app-primary, #6366f1);color:#fff;border:none;border-radius:6px;cursor:pointer;float:left;margin-bottom:14px;">
    🖨️ طباعة
</button>

<div class="header">
    <h1>قسيمة راتب</h1>
    <div class="sub">صرف رقم #{{ $run->id }} | قسيمة #{{ $run->salary_slip_id }}</div>
</div>

<h3 class="section-title">🧑 بيانات الموظف</h3>
<div class="info-grid">
    <div class="info-row"><span class="l">الاسم:</span><span class="v">{{ $name }}</span></div>
    <div class="info-row"><span class="l">رقم الموظف:</span><span class="v">#{{ $run->employee_id }}</span></div>
    <div class="info-row"><span class="l">العقد:</span><span class="v">{{ $run->contract ? '#'.$run->contract->id : 'بدون عقد' }}</span></div>
    <div class="info-row"><span class="l">حساب المستحقات:</span><span class="v">{{ optional($emp->salaryPayableAccount)->name ?? '—' }}</span></div>
</div>

<h3 class="section-title">📅 فترة الصرف</h3>
<div class="info-grid">
    <div class="info-row"><span class="l">من تاريخ:</span><span class="v">{{ $run->period_from->format('Y-m-d') }}</span></div>
    <div class="info-row"><span class="l">إلى تاريخ:</span><span class="v">{{ $run->period_to->format('Y-m-d') }}</span></div>
    <div class="info-row"><span class="l">تاريخ الصرف:</span><span class="v">{{ $run->disbursed_at ? $run->disbursed_at->format('Y-m-d') : '—' }}</span></div>
    <div class="info-row"><span class="l">مصدر التشغيل:</span><span class="v">{{ $run->trigger_source === 'cron' ? 'تلقائي' : 'يدوي' }}</span></div>
</div>

@if($additions->count())
<h3 class="section-title">➕ الاستحقاقات</h3>
<table>
    <thead><tr><th>البند</th><th style="width:30%;">المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
    <tbody>
        @foreach($additions as $item)
            <tr><td>{{ $item->name }}</td><td>{{ \App\Support\CurrencySettings::format($item->amount) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th>إجمالي الاستحقاقات</th><th>{{ \App\Support\CurrencySettings::format($additions->sum('amount')) }}</th></tr>
    </tfoot>
</table>
@endif

@if($retros->count())
<h3 class="section-title">↩ تسويات رجعية</h3>
<table>
    <thead><tr><th>البند</th><th style="width:30%;">المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
    <tbody>
        @foreach($retros as $item)
            <tr><td>{{ $item->name }}@if($item->description)<br><small>{{ $item->description }}</small>@endif</td><td>{{ \App\Support\CurrencySettings::format($item->amount) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th>إجمالي التسويات الرجعية</th><th>{{ \App\Support\CurrencySettings::format($retros->sum('amount')) }}</th></tr>
    </tfoot>
</table>
@endif

@if($deductions->count())
<h3 class="section-title">➖ الخصومات</h3>
<table>
    <thead><tr><th>البند</th><th style="width:30%;">المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
    <tbody>
        @foreach($deductions as $item)
            <tr><td>{{ $item->name }}</td><td>−{{ \App\Support\CurrencySettings::format($item->amount) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th>إجمالي الخصومات</th><th>−{{ \App\Support\CurrencySettings::format($deductions->sum('amount')) }}</th></tr>
    </tfoot>
</table>
@endif

@if($advances->count())
<h3 class="section-title">💰 تسديد السلف</h3>
<table>
    <thead><tr><th>البند</th><th style="width:30%;">المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
    <tbody>
        @foreach($advances as $item)
            <tr><td>{{ $item->name }}</td><td>−{{ \App\Support\CurrencySettings::format($item->amount) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th>إجمالي السلف المخصومة</th><th>−{{ \App\Support\CurrencySettings::format($advances->sum('amount')) }}</th></tr>
    </tfoot>
</table>
@endif

<div class="net">
    <div class="label">صافي الراتب المستحق</div>
    <div class="amt">{{ \App\Support\CurrencySettings::format($run->net_salary) }}</div>
</div>

<h3 class="section-title">📋 ملخص محاسبي</h3>
<table>
    <tr><th>إجمالي الاستحقاقات</th><td>{{ \App\Support\CurrencySettings::format($run->basic_amount + $run->total_additions) }}</td></tr>
    <tr><th>إجمالي الخصومات</th><td>−{{ \App\Support\CurrencySettings::format($run->total_deductions) }}</td></tr>
    <tr><th>إجمالي السلف المخصومة</th><td>−{{ \App\Support\CurrencySettings::format($run->total_advances_deducted) }}</td></tr>
    <tr><th>الصافي المستحق</th><td><strong>{{ \App\Support\CurrencySettings::format($run->net_salary) }}</strong></td></tr>
    @if($run->expense_id)
        <tr><th>سند صرف رقم</th><td>{{ optional($run->expense)->code ?? '—' }}</td></tr>
    @endif
    @if($run->journal_entry_id)
        <tr><th>قيد محاسبي</th><td>{{ optional($run->journalEntry)->reference_number ?? '—' }}</td></tr>
    @endif
</table>

<div class="signatures">
    <div class="sig-box">المحاسب / المدير المالي</div>
    <div class="sig-box">الموظف</div>
</div>

<div class="footer">
    تم إنشاء هذه القسيمة بواسطة النظام في {{ now()->format('Y-m-d H:i') }} | معرف الصرف: #{{ $run->id }}
</div>

</body>
</html>
