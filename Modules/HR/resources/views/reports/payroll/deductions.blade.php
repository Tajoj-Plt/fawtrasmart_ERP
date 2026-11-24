{{--
    الخصميات — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::deductions`. سجلُّ الخصم (`deductions`)
    لا بندُ الراتب: لكلّ خصمٍ مبلغُه وما خُصم منه فعلاً وما بقي
    (`Deduction::remaining_amount`). الملغاةُ والمرفوضة خارج الصفوف والمبالغ
    ما لم تُطلب. عرضان: لكل خصم، أو لكل موظف.
--}}
@extends('hr::master')

@section('title')
    الخصميات
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $money = fn ($v) => number_format((float) $v, 2);
    $pager = $isSummary ? $groups : $rows;
    $canOpen = Route::has('hr.deductions.show') && hasPermission('deductions.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'minus-circle',
        'headTitle' => 'الخصميات',
        'headSubtitle' => 'كل خصم على الموظفين في الفترة: نوعه ومبلغه وما خُصم منه فعلاً من الراتب وما بقي',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-deductions',
        'headGuide' => [
            'title' => 'من أين تأتي الأرقام؟',
            'lead' => 'من سجل «الخصميات» نفسه (غياب، تأخير، مخالفة، جزاء…)، بتاريخ الخصم.',
            'blocks' => [[
                '«خُصم فعلاً» = ما اقتُطع من الرواتب المدفوعة حتى الآن. و«باقٍ للخصم» = المعتمد الذي لم يُقتطع بعد، ويُقتطع من الراتب القادم.',
                '«بانتظار الاعتماد» خصمٌ لم يُعتمد بعد — لا يُقتطع حتى يُعتمد، فلا يدخل في «باقٍ للخصم».',
                'الخصميات الملغاة والمرفوضة لا تظهر ولا تدخل في المبالغ؛ اخترها من «الحالة» لتراها.',
                'بنود الخصم داخل كل راتب (ومنها التأمينات) تجدها في «مكونات الراتب».',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route('hr.reports.payroll.deductions'),
        'views' => [
            'details' => ['لكل خصم', 'list'],
            'summary' => ['لكل موظف', 'users'],
        ],
        'selects' => [
            ['name' => 'deduction_type', 'label' => 'نوع الخصم', 'options' => ['' => 'كل الأنواع'] + $deductionTypes],
            ['name' => 'status', 'label' => 'الحالة', 'options' => $statusOptions],
        ],
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">إجمالي الخصميات</div><div class="ag-kpi__value"><bdi>{{ $money($totals['amount']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['count']) }} خصم{{ $totals['void_hidden'] ? ' · مستبعد '.$totals['void_hidden'].' ملغى/مرفوض' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">خُصم فعلاً</div><div class="ag-kpi__value"><bdi>{{ $money($totals['settled']) }}</bdi></div><div class="ag-kpi__sub">اقتُطع من الرواتب</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">باقٍ للخصم</div><div class="ag-kpi__value"><bdi>{{ $money($totals['remaining']) }}</bdi></div><div class="ag-kpi__sub">معتمد ولم يُقتطع بعد</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#64748b">
            <div><div class="ag-kpi__label">بانتظار الاعتماد</div><div class="ag-kpi__value"><bdi>{{ $money($totals['pending']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['pending_count']) }} خصم لم يُعتمد</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-clock"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'الخصميات لكل موظف' : 'الخصميات' }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $isSummary ? 'موظف' : 'خصم' }}</span>
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
                                <td class="rpt-name"><a href="{{ route('hr.reports.payroll.deductions', array_merge(request()->except(['page', 'view', 'export']), ['view' => 'details', 'employee_id' => $g['employee_id']])) }}">{{ $g['employee'] }}</a></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $g['department'] }}</span></td>
                                <td class="rpt-num">{{ number_format($g['count']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($g['amount']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $money($g['settled']) }}</td>
                                <td class="rpt-num {{ $g['remaining'] > 0 ? 'rpt-neg' : '' }}">{{ $money($g['remaining']) }}</td>
                                <td class="rpt-num">{{ $g['pending'] > 0 ? $money($g['pending']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا خصميات في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @else
                        @forelse ($rows as $r)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">
                                    @if ($canOpen)
                                        <a href="{{ route('hr.deductions.show', $r['id']) }}" target="_blank" rel="noopener">{{ $r['date'] }}</a>
                                    @else
                                        {{ $r['date'] }}
                                    @endif
                                </td>
                                <td class="rpt-name">{{ $r['employee'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $r['department'] }}</span></td>
                                <td>{{ $r['type_label'] }}</td>
                                <td class="rpt-num"><strong>{{ $money($r['shown_amount']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $r['settled'] > 0 ? $money($r['settled']) : '—' }}</td>
                                <td class="rpt-num {{ $r['remaining'] > 0 ? 'rpt-neg' : '' }}">{{ $r['remaining'] > 0 ? $money($r['remaining']) : '—' }}</td>
                                <td><span class="rpt-pill {{ $r['tone'] }}">{{ $r['status_label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا خصميات في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
                @if ($pager->total() > 0)
                    <tfoot>
                        <tr>
                            <td colspan="{{ $isSummary ? 3 : 5 }}"><strong>الإجمالي{{ $totals['void_shown'] ? ' (دون الملغاة والمرفوضة)' : '' }}</strong></td>
                            @if ($isSummary)<td class="rpt-num"><strong>{{ number_format($totals['count']) }}</strong></td>@endif
                            <td class="rpt-num"><strong>{{ $money($totals['amount']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['settled']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['remaining']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $isSummary ? $money($totals['pending']) : '' }}</strong></td>
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
        'frTitle' => $isSummary ? 'الخصميات — لكل موظف' : 'الخصميات',
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
