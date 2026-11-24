{{--
    كشف السلف — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::advances`. طلباتُ السلف
    (`salary_advance_requests` — المرحلةُ من `stage()` والمسدَّدُ من
    `repaidAmount()`/`remainingAmount()`) والسلفُ بأقساط من النظام السابق
    (`salary_advances` — المسدَّدُ = الأقساطُ المدفوعة وحدها). المرفوضةُ والملغاة
    خارج المبالغ. عرضان: لكل سلفة، أو لكل موظف.
--}}
@extends('hr::master')

@section('title')
    كشف السلف
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $money = fn ($v) => number_format((float) $v, 2);
    $pager = $isSummary ? $groups : $rows;
    $canOpen = hasPermission('loans.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'hand-holding-usd',
        'headTitle' => 'كشف السلف',
        'headSubtitle' => 'سلف الموظفين المطلوبة في الفترة: ما صُرف منها، وما سدّده الموظف نقداً أو من راتبه، وما بقي عليه',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-advances',
        'headGuide' => [
            'title' => 'من أين تأتي الأرقام؟',
            'lead' => 'من «طلبات السلف» ومن السلف بأقساط في النظام السابق، بتاريخ طلب السلفة.',
            'blocks' => [[
                '«المصروف» = السلف التي خرج مالها للموظف فعلاً. و«المسدَّد» = ما رجع منها نقداً أو اقتطاعاً من الراتب. و«المتبقي» = ما زال على الموظف.',
                'السلفة المعلّقة أو المعتمدة التي لم تُصرف بعد ليست ديناً على أحد — تظهر في «بانتظار الاعتماد أو الصرف» وحدها.',
                'السلف المرفوضة والملغاة لا تظهر ولا تدخل في المبالغ؛ اخترها من «حالة السلفة» لتراها.',
                'في النظام السابق يُعدّ القسط مسدَّداً إذا كانت حالته «مدفوع» فقط.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route('hr.reports.payroll.advances'),
        'views' => [
            'details' => ['لكل سلفة', 'list'],
            'summary' => ['لكل موظف', 'users'],
        ],
        'selects' => [
            ['name' => 'advance_state', 'label' => 'حالة السلفة', 'options' => $stateOptions, 'value' => $currentState],
        ],
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">المصروف للموظفين</div><div class="ag-kpi__value"><bdi>{{ $money($totals['disbursed']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['disbursed_count']) }} سلفة{{ $totals['void_hidden'] ? ' · مستبعد '.$totals['void_hidden'].' مرفوضة/ملغاة' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">المسدَّد</div><div class="ag-kpi__value"><bdi>{{ $money($totals['paid']) }}</bdi></div><div class="ag-kpi__sub">نقداً أو من الراتب</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">المتبقي على الموظفين</div><div class="ag-kpi__value"><bdi>{{ $money($totals['remaining']) }}</bdi></div><div class="ag-kpi__sub">في {{ number_format($totals['outstanding_count']) }} سلفة لم تُسدَّد بالكامل</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">بانتظار الاعتماد أو الصرف</div><div class="ag-kpi__value"><bdi>{{ $money($totals['waiting']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['waiting_count']) }} طلب — لم يخرج مالها</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-clock"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'السلف لكل موظف' : 'السلف' }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $isSummary ? 'موظف' : 'سلفة' }}</span>
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
                    @if ($isSummary)
                        @forelse ($groups as $g)
                            <tr>
                                <td>{{ $groups->firstItem() + $loop->index }}</td>
                                <td class="rpt-name"><a href="{{ route('hr.reports.payroll.advances', array_merge(request()->except(['page', 'view', 'export']), ['view' => 'details', 'employee_id' => $g['employee_id']])) }}">{{ $g['employee'] }}</a></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $g['department'] }}</span></td>
                                <td class="rpt-num">{{ number_format($g['count']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($g['disbursed']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $money($g['paid']) }}</td>
                                <td class="rpt-num {{ $g['remaining'] > 0 ? 'rpt-neg' : '' }}">{{ $money($g['remaining']) }}</td>
                                <td class="rpt-num">{{ $g['waiting'] > 0 ? $money($g['waiting']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا سلف في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @else
                        @forelse ($rows as $r)
                            @php
                                $showRoute = $r['kind'] === 'request' ? 'hr.advance-requests.show' : 'ancestor.show';
                            @endphp
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">
                                    @if ($canOpen && Route::has($showRoute))
                                        <a href="{{ route($showRoute, $r['id']) }}" target="_blank" rel="noopener">{{ $r['date'] ?? '—' }}</a>
                                    @else
                                        {{ $r['date'] ?? '—' }}
                                    @endif
                                </td>
                                <td class="rpt-name">{{ $r['employee'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $r['department'] }}</span></td>
                                <td>{{ $r['source'] }}</td>
                                <td class="rpt-num"><strong>{{ $money($r['amount']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $r['paid'] > 0 ? $money($r['paid']) : '—' }}</td>
                                <td class="rpt-num {{ $r['remaining'] > 0 ? 'rpt-neg' : '' }}">{{ $r['remaining'] > 0 ? $money($r['remaining']) : '—' }}</td>
                                <td><span class="rpt-pill {{ $r['tone'] }}">{{ $r['state_label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا سلف في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
                @if ($pager->total() > 0)
                    <tfoot>
                        <tr>
                            @if ($isSummary)
                                <td colspan="3"><strong>الإجمالي</strong></td>
                                <td class="rpt-num"><strong>{{ number_format($totals['count']) }}</strong></td>
                                <td class="rpt-num"><strong>{{ $money($totals['disbursed']) }}</strong></td>
                            @else
                                <td colspan="5"><strong>الإجمالي — المصروف منها</strong></td>
                                <td class="rpt-num"><strong>{{ $money($totals['disbursed']) }}</strong></td>
                            @endif
                            <td class="rpt-num"><strong>{{ $money($totals['paid']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['remaining']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $isSummary ? $money($totals['waiting']) : '' }}</strong></td>
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
        'frTitle' => $isSummary ? 'كشف السلف — لكل موظف' : 'كشف السلف',
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
