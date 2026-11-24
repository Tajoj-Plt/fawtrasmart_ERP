{{--
    تقاريرُ الصرفات الخمسة — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::runsReport()`. التقريرُ نفسُه بتجميعٍ
    مختلف لكلّ مسار ($group): summary (راتبٌ لكلّ صف) / month / department /
    employee / stage. والمجمَّعةُ لها عرضٌ ثانٍ «لكل راتب» بالصفوف نفسها.
    التصديرُ والطباعةُ للعرض الحاليّ كلِّه (`export=rows`) بترتيب `$headers`.
--}}
@extends('hr::master')

@section('title')
    {{ $meta['title'] }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isGrouped = $group !== 'summary';
    $isSummary = $reportView === 'summary';
    $money = fn ($v) => number_format((float) $v, 2);
    $route = $meta['route'];
    $canOpenRun = Route::has('payroll-runs.show') && hasPermission('disbursements.show');
    $pager = $isSummary ? $groups : $rows;
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => ['summary' => 'layer-group', 'month' => 'calendar-alt', 'department' => 'sitemap', 'employee' => 'money-check-alt', 'stage' => 'tasks'][$group],
        'headTitle' => $meta['title'],
        'headSubtitle' => $meta['subtitle'],
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-'.$group,
        'headGuide' => [
            'title' => 'من أين تأتي الأرقام؟',
            'lead' => $meta['lead'],
            'blocks' => [$meta['blocks']],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route($route),
        'views' => $isGrouped ? [
            'summary' => ['لكل '.$meta['unit'], 'layer-group'],
            'details' => ['لكل راتب', 'list'],
        ] : [],
        'selects' => [[
            'name' => 'status',
            'label' => 'حالة الصرف',
            'options' => $runStatusOptions,
        ]],
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">عدد الرواتب</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['count']) }}</bdi></div><div class="ag-kpi__sub">لـ {{ number_format($totals['employees']) }} موظف{{ $totals['cancelled'] ? ' · ملغاة '.$totals['cancelled'].' (خارج المبالغ)' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">الأساسي + الاستحقاقات</div><div class="ag-kpi__value"><bdi>{{ $money($totals['basic'] + $totals['additions']) }}</bdi></div><div class="ag-kpi__sub">منها إضافات وبدلات {{ $money($totals['additions']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-plus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">الخصم وأقساط السلف</div><div class="ag-kpi__value"><bdi>{{ $money($totals['deductions'] + $totals['advances']) }}</bdi></div><div class="ag-kpi__sub">خصم {{ $money($totals['deductions']) }} · سلف {{ $money($totals['advances']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الصافي المدفوع</div><div class="ag-kpi__value"><bdi>{{ $money($totals['net_paid']) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['unpaid_count'] ? 'لم يُدفع بعد '.number_format($totals['unpaid_count']).' راتب · أساسيها '.$money($totals['basic_unpaid']) : 'لا رواتب معلّقة' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? $meta['title'] : ($isGrouped ? 'الرواتب لكل موظف وشهر' : 'الرواتب') }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $isSummary ? $meta['unit'] : 'راتب' }}</span>
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
                                <td class="rpt-name">
                                    @if ($g['link'] && $g['count'])
                                        <a href="{{ route($route, array_merge(request()->except(['page', 'view', 'export']), $g['link'], ['view' => 'details'])) }}">
                                            @if ($group === 'stage')
                                                <span class="rpt-pill {{ $g['tone'] }}">{{ $g['label'] }}</span>
                                            @else
                                                {{ $g['label'] }}
                                            @endif
                                        </a>
                                    @elseif ($group === 'stage')
                                        <span class="rpt-pill {{ $g['tone'] }}">{{ $g['label'] }}</span>
                                    @else
                                        {{ $g['label'] }}
                                    @endif
                                </td>
                                @if ($group === 'employee')<td><span class="rpt-pill rpt-pill-secondary">{{ $g['department'] }}</span></td>@endif
                                <td class="rpt-num">{{ $g['count'] ?: '—' }}</td>
                                @if ($group !== 'employee')<td class="rpt-num">{{ $g['employees'] ?: '—' }}</td>@endif
                                <td class="rpt-num">{{ $money($g['basic']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($g['additions']) }}</td>
                                <td class="rpt-num {{ $g['deductions'] > 0 ? 'rpt-neg' : '' }}">{{ $money($g['deductions']) }}</td>
                                <td class="rpt-num {{ $g['advances'] > 0 ? 'rpt-neg' : '' }}">{{ $money($g['advances']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($g['net']) }}</strong></td>
                                <td class="rpt-num">{{ $money($g['net_paid']) }}</td>
                                <td class="rpt-num {{ $g['unpaid_count'] > 0 ? 'rpt-neg' : '' }}">{{ $g['unpaid_count'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @else
                        @forelse ($rows as $r)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($canOpenRun)
                                        <a href="{{ route('payroll-runs.show', $r['id']) }}" target="_blank" rel="noopener">{{ $r['employee'] }}</a>
                                    @else
                                        {{ $r['employee'] }}
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $r['department'] }}</span></td>
                                <td class="rpt-num">{{ $r['period'] }}</td>
                                <td class="rpt-num">{{ $money($r['basic']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($r['additions']) }}</td>
                                <td class="rpt-num {{ $r['deductions'] > 0 ? 'rpt-neg' : '' }}">{{ $money($r['deductions']) }}</td>
                                <td class="rpt-num {{ $r['advances'] > 0 ? 'rpt-neg' : '' }}">{{ $money($r['advances']) }}</td>
                                <td class="rpt-num">@if ($r['net_pending'])<span class="text-muted small">يُحتسب عند الصرف</span>@else<strong>{{ $money($r['net']) }}</strong>@endif</td>
                                <td><span class="rpt-pill {{ $r['tone'] }}">{{ $r['stage_label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
                @if ($pager->total() > 0)
                    <tfoot>
                        <tr>
                            @if ($isSummary)
                                <td colspan="{{ $group === 'employee' ? 3 : 2 }}"><strong>الإجمالي{{ $totals['cancelled'] ? ' (دون الملغاة)' : '' }}</strong></td>
                                <td class="rpt-num"><strong>{{ number_format($totals['count']) }}</strong></td>
                                @if ($group !== 'employee')<td class="rpt-num"><strong>{{ number_format($totals['employees']) }}</strong></td>@endif
                            @else
                                <td colspan="4"><strong>الإجمالي{{ $totals['cancelled'] ? ' (دون الملغاة)' : '' }} — {{ number_format($totals['count']) }} راتب</strong></td>
                            @endif
                            <td class="rpt-num"><strong>{{ $money($totals['basic']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['additions']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['deductions']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['advances']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['net']) }}</strong></td>
                            @if ($isSummary)
                                <td class="rpt-num"><strong>{{ $money($totals['net_paid']) }}</strong></td>
                                <td class="rpt-num"><strong>{{ number_format($totals['unpaid_count']) }}</strong></td>
                            @else
                                <td></td>
                            @endif
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
        'frTitle' => $meta['title'].($isGrouped ? ($isSummary ? ' — لكل '.$meta['unit'] : ' — لكل راتب') : ''),
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
