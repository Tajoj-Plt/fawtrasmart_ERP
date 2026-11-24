{{--
    قسائم الرواتب — للعرض والطباعة الجماعية، داخل غلاف تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::payslips`. قسيمةٌ لكلّ راتب بالبنود
    المكتوبة فيه. بلا تقسيم صفحات: «طباعة القسائم» تطبع الصفحة كلَّها، قسيمةً في
    كلّ كتلة. و«تصدير» يُخرج جدولاً بسطرٍ لكلّ راتب (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    قسائم الرواتب
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
        .rpt .ps-slip { break-inside: avoid; page-break-inside: avoid; margin: 0; }
        .rpt .ps-slip table td { padding: 4px 8px; }
        .rpt .ps-slip .ps-net td { font-weight: 700; border-top: 2px solid var(--rpt-border, #e2e8f0); }
        @media print {
            .rpt .ps-grid { display: block; }
            .rpt .ps-slip { margin-bottom: 12px; }
        }
    </style>
@endsection

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $canPrintOne = Route::has('payroll-runs.print') && hasPermission('payslips.print');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'file-invoice-dollar',
        'headTitle' => 'قسائم الرواتب',
        'headSubtitle' => 'قسيمة لكل راتب في الفترة: الأساسي والبدلات والخصم وأقساط السلف والصافي — للعرض والطباعة دفعة واحدة',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-payslips',
        'headGuide' => [
            'title' => 'ماذا في القسيمة؟',
            'lead' => 'بنود كل راتب كما كُتبت في «سجل صرف الرواتب». تفتح على الشهر الجاري.',
            'blocks' => [[
                '«طباعة القسائم» تطبع كل القسائم الظاهرة؛ ضيّق الفلتر (قسم أو موظف) لتطبع جزءاً منها.',
                'الراتب الذي لم يُدفع بعد تُكتب بنوده نهائياً يوم الصرف، فقد يظهر بأساسيه وحده.',
                'الرواتب الملغاة لا تظهر إلا إذا اخترتها من «حالة الصرف».',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route('hr.reports.payroll.payslips'),
        'selects' => [['name' => 'status', 'label' => 'حالة الصرف', 'options' => $runStatusOptions]],
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis no-print">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">عدد القسائم</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['count']) }}</bdi></div><div class="ag-kpi__sub">لـ {{ number_format($totals['employees']) }} موظف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">الأساسي + البدلات</div><div class="ag-kpi__value"><bdi>{{ $money($totals['basic'] + $totals['additions']) }}</bdi></div><div class="ag-kpi__sub">بدلات {{ $money($totals['additions']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-plus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">الخصم وأقساط السلف</div><div class="ag-kpi__value"><bdi>{{ $money($totals['deductions'] + $totals['advances']) }}</bdi></div><div class="ag-kpi__sub">خصم {{ $money($totals['deductions']) }} · سلف {{ $money($totals['advances']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الصافي المدفوع</div><div class="ag-kpi__value"><bdi>{{ $money($totals['net_paid']) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['unpaid_count'] ? 'لم يُدفع بعد '.$totals['unpaid_count'].' راتب' : 'كلها مدفوعة' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
    </div>

    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-file-invoice-dollar"></i> القسائم
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($runs->count()) }} قسيمة</span>
            </h4>
            <div class="ag-tools no-print">
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print"></i> طباعة القسائم</button>
            </div>
        </div>

        <div class="rpt-card__body">
            @if ($runs->isEmpty())
                <div class="rpt-empty"><i class="fas fa-inbox"></i> لا قسائم في هذا المدى والنطاق</div>
            @else
                <div class="ps-grid">
                    @foreach ($runs as $run)
                        @php
                            $isBasic = fn ($i) => $i->item_type === 'addition' && $i->source_table === null && $i->name === 'الراتب الأساسي';
                            $additions = $run->items->where('item_type', 'addition')->reject($isBasic);
                            $retros = $run->items->where('item_type', 'retro');
                            $deductions = $run->items->where('item_type', 'deduction');
                            $advances = $run->items->where('item_type', 'advance');
                            $stage = $run->stage();
                            $paid = $stage === \App\Models\PayrollRun::STAGE_PAID;
                        @endphp
                        <div class="rpt-card ps-slip">
                            <div class="rpt-card__head ag-table-head">
                                <h4>
                                    {{ $run->employee?->full_name ?? '—' }}
                                    @if ($run->employee?->employee_code)
                                        <small class="text-muted">({{ $run->employee->employee_code }})</small>
                                    @endif
                                </h4>
                                <span class="rpt-pill {{ $paid ? 'rpt-pill-success' : ($stage === 'cancelled' ? 'rpt-pill-secondary' : 'rpt-pill-warning') }}">{{ \App\Models\PayrollRun::stages()[$stage] ?? $run->status_label }}</span>
                            </div>
                            <div class="rpt-card__body">
                                <div class="text-muted small mb-1">
                                    {{ $run->employee?->department?->name ?? 'بدون قسم' }}
                                    · {{ $run->period_label }}
                                    @if ($run->employee?->branch?->name) · {{ $run->employee->branch->name }} @endif
                                </div>
                                <table class="rpt-table">
                                    <tbody>
                                        <tr><td>الراتب الأساسي</td><td class="rpt-num">{{ $money($run->basic_amount) }}</td></tr>
                                        @foreach ($additions as $item)
                                            <tr><td>{{ $item->name }}</td><td class="rpt-num rpt-pos">+ {{ $money($item->amount) }}</td></tr>
                                        @endforeach
                                        @foreach ($retros as $item)
                                            <tr><td>{{ $item->name }} (فروقات بأثر رجعي)</td><td class="rpt-num rpt-pos">+ {{ $money($item->amount) }}</td></tr>
                                        @endforeach
                                        @foreach ($deductions as $item)
                                            <tr><td>{{ $item->name }}</td><td class="rpt-num rpt-neg">− {{ $money($item->amount) }}</td></tr>
                                        @endforeach
                                        @foreach ($advances as $item)
                                            <tr><td>{{ $item->name }}</td><td class="rpt-num rpt-neg">− {{ $money($item->amount) }}</td></tr>
                                        @endforeach
                                        <tr class="ps-net">
                                            <td>الصافي</td>
                                            <td class="rpt-num">{{ ! $paid && (float) $run->net_salary <= 0 ? 'يُحتسب عند الصرف' : $money($run->net_salary) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                @if ($canPrintOne)
                                    <div class="mt-1 no-print small"><a href="{{ route('payroll-runs.print', $run->id) }}" target="_blank" rel="noopener"><i class="fas fa-print"></i> قسيمة منفردة</a></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'قسائم الرواتب',
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
