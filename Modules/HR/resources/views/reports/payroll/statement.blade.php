{{--
    كشف حساب الموظف — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::statement`. القيودُ المحاسبية على
    حسابَي الموظف: «مستحقات الراتب» (التزامٌ يزيد بالدائن) و«سلف الموظف» (أصلٌ
    يزيد بالمدين). بلا موظف: صفٌّ لكلّ موظف؛ وباختيار موظف: حركتُه برصيدٍ جارٍ.
--}}
@extends('hr::master')

@section('title')
    كشف حساب الموظف
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isLedger = $reportView === 'details';
    $money = fn ($v) => number_format((float) $v, 2);
    $pager = $isLedger ? $ledger : $summaries;
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'book-open',
        'headTitle' => $selected ? 'كشف حساب '.$selected->full_name : 'كشف حساب الموظف',
        'headSubtitle' => 'حركة حسابَي الموظف في المحاسبة: «مستحقات الراتب» (ما له على المنشأة) و«سلف الموظف» (ما عليه للمنشأة)',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-statement',
        'headGuide' => [
            'title' => 'كيف يُقرأ الكشف؟',
            'lead' => 'من القيود المحاسبية المرحَّلة على الحسابين المربوطين بكل موظف — لا من شاشات الرواتب.',
            'blocks' => [[
                '«مستحقات الراتب»: يزيد الرصيد حين يُستحق الراتب، وينقص حين يُدفع. الرصيد الباقي = راتب مستحق لم يُدفع.',
                '«سلف الموظف»: يزيد حين تُصرف له سلفة، وينقص حين يسدّدها. الرصيد الباقي = سلف ما زالت عليه.',
                'الخصم ليس له قيد مستقل: يُنقص الراتب المستحق قبل ترحيله — تراه في بطاقة «الخصم».',
                'اضغط اسم الموظف لترى حركته سطراً سطراً.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route('hr.reports.payroll.statement'),
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">الرواتب المستحقة</div><div class="ag-kpi__value"><bdi>{{ $money($operational['earnings']) }}</bdi></div><div class="ag-kpi__sub">الأساسي + البدلات في {{ number_format($operational['count']) }} راتب مدفوع</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-plus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">الخصم</div><div class="ag-kpi__value"><bdi>{{ $money($operational['deductions']) }}</bdi></div><div class="ag-kpi__sub">من الرواتب المدفوعة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">أقساط السلف</div><div class="ag-kpi__value"><bdi>{{ $money($operational['advances']) }}</bdi></div><div class="ag-kpi__sub">اقتُطعت من الرواتب</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-credit-card"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الصافي المدفوع</div><div class="ag-kpi__value"><bdi>{{ $money($operational['net']) }}</bdi></div><div class="ag-kpi__sub">{{ $selected ? 'رصيد المستحقات أول المدة '.$money($opening['payable']).' · السلف '.$money($opening['advance']) : ($unlinked ? $unlinked.' موظف بلا حسابات مربوطة' : 'كل الموظفين لهم حسابات') }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isLedger ? 'حركة '.$selected->full_name : 'أرصدة الموظفين' }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $isLedger ? 'حركة' : 'موظف' }}</span>
                @if ($isLedger)
                    <a href="{{ route('hr.reports.payroll.statement', request()->except(['page', 'employee_id', 'export', 'view'])) }}" class="btn btn-sm ag-btn-soft no-print"><i class="fas fa-users"></i> كل الموظفين</a>
                @endif
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @if ($isLedger)
                        @forelse ($ledger as $l)
                            <tr>
                                <td>{{ $ledger->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">{{ $l['date'] }}</td>
                                <td class="rpt-num">{{ $l['reference'] }}</td>
                                <td><span class="rpt-pill {{ $l['kind'] === 'payable' ? 'rpt-pill-success' : 'rpt-pill-warning' }}">{{ $l['kind_label'] }}</span></td>
                                <td>{{ $l['nature'] }}</td>
                                <td>{{ $l['description'] }}</td>
                                <td class="rpt-num">{{ $l['debit'] > 0 ? $money($l['debit']) : '—' }}</td>
                                <td class="rpt-num">{{ $l['credit'] > 0 ? $money($l['credit']) : '—' }}</td>
                                <td class="rpt-num"><strong>{{ $money($l['balance']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا حركة على حسابَي الموظف في هذا المدى</td></tr>
                        @endforelse
                    @else
                        @forelse ($summaries as $r)
                            <tr>
                                <td>{{ $summaries->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.payroll.statement', array_merge(request()->except(['page', 'export', 'view']), ['employee_id' => $r['employee_id']])) }}">{{ $r['employee'] }}</a>
                                    @if (! $r['payable']['linked'] && ! $r['advance']['linked'])
                                        <div class="text-muted small">لا حسابات مربوطة</div>
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $r['department'] }}</span></td>
                                <td class="rpt-num">{{ $money($r['payable']['opening']) }}</td>
                                <td class="rpt-num">{{ $money($r['payable']['debit']) }}</td>
                                <td class="rpt-num">{{ $money($r['payable']['credit']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($r['payable']['closing']) }}</strong></td>
                                <td class="rpt-num">{{ $money($r['advance']['opening']) }}</td>
                                <td class="rpt-num">{{ $money($r['advance']['debit']) }}</td>
                                <td class="rpt-num">{{ $money($r['advance']['credit']) }}</td>
                                <td class="rpt-num {{ $r['advance']['closing'] > 0 ? 'rpt-neg' : '' }}"><strong>{{ $money($r['advance']['closing']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
                @if (! $isLedger && $pager->total() > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>الإجمالي</strong></td>
                            @foreach (['payable', 'advance'] as $side)
                                @foreach (['opening', 'debit', 'credit', 'closing'] as $col)
                                    <td class="rpt-num"><strong>{{ $money($sideTotals[$side][$col]) }}</strong></td>
                                @endforeach
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($pager->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من {{ number_format($pager->total()) }}</small>
                {{ $pager->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => $selected ? 'كشف حساب '.$selected->full_name : 'كشف حساب الموظف — الأرصدة',
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
