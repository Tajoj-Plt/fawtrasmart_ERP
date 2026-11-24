{{--
    الموظفون مجمَّعين (حسب القسم / حسب المسمّى) — بتصميم تقارير العملاء.
    المتحكّم: `EmployeeReportController::groupedReport` — يمدّه قالبا
    `by_department` و`by_job_title` بلا زيادة، والفرقُ كلُّه في المتغيّرات:
    $groupKey (department_id|job_title_id)، $groupLabel، $otherLabel،
    $reportTitle، $reportRoute. عرضان: مجموعةٌ لكلّ صف، أو موظفٌ لكلّ صف.
--}}
@extends('hr::master')

@section('title')
    {{ $reportTitle }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php $isSummary = $reportView === 'summary'; @endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => $groupKey === 'department_id' ? 'sitemap' : 'briefcase',
        'headTitle' => $reportTitle,
        'headSubtitle' => 'عدد الموظفين في كل '.$groupLabel.' وحالاتهم اليوم، ثم تفاصيلهم موظفاً موظفاً',
        'headGuideKey' => 'hr-reports.employees-'.$groupKey,
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من ملفات الموظفين كما هي الآن، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                '«على رأس العمل» = نشط + تحت التجربة. «متوقّف مؤقتاً» = غير نشط أو موقوف. «انتهت خدمته» = مستقيل أو منتهي الخدمة.',
                '«النسبة» من مجموع الموظفين الظاهرين بعد الفلترة.',
                'اضغط اسم '.$groupLabel.' لترى موظفيه في عرض «لكل موظف».',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route($reportRoute) }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field')

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-layer-group"></i> لكل {{ $groupLabel }}</button>
                            <button type="submit" name="view" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-users"></i> لكل موظف</button>
                        </div>
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route($reportRoute)])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">الموظفون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">في {{ number_format($totals['groups']) }} {{ $groupLabel }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">على رأس العمل</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['working']) }}</bdi></div><div class="ag-kpi__sub">نشط + تحت التجربة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-check"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">غير عاملين</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['stopped'] + $totals['ended']) }}</bdi></div><div class="ag-kpi__sub">متوقّف {{ $totals['stopped'] }} · انتهت خدمته {{ $totals['ended'] }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-slash"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الأكبر عدداً</div><div class="ag-kpi__value"><bdi>{{ $totals['largest'] ? number_format($totals['largest']['count']) : '—' }}</bdi></div><div class="ag-kpi__sub">{{ $totals['largest']['name'] ?? 'لا بيانات' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-trophy"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'لكل '.$groupLabel : 'لكل موظف' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? number_format($summary->total()).' '.$groupLabel : number_format($details->total()).' موظف' }}</span>
            </h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>{{ $groupLabel }}</th><th>العدد</th><th>على رأس العمل</th><th>منهم تحت التجربة</th><th>متوقّف مؤقتاً</th><th>انتهت خدمته</th><th>النسبة</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>{{ $summary->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($row['id'])
                                        <a href="{{ route($reportRoute, array_merge(request()->except(['page', 'view']), ['view' => 'details', $groupKey => $row['id']])) }}">{{ $row['name'] }}</a>
                                    @else
                                        <span class="text-muted">{{ $row['name'] }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num"><strong>{{ number_format($row['count']) }}</strong></td>
                                <td class="rpt-num rpt-pos">{{ $row['working'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['probation'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['stopped'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['ended'] ? 'rpt-neg' : '' }}">{{ $row['ended'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['share'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>{{ $groupLabel }}</th><th>الرقم</th><th>الموظف</th><th>{{ $otherLabel }}</th><th>الفرع</th><th>التعيين</th><th>الحالة</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($details as $row)
                            <tr>
                                <td>{{ $details->firstItem() + $loop->index }}</td>
                                <td>{{ $row['group'] }}</td>
                                <td class="rpt-num">{{ $row['code'] }}</td>
                                <td class="rpt-name">@include('hr::reports.employees._name', ['empId' => $row['id'], 'empName' => $row['name']])</td>
                                <td>{{ $row['other'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['branch'] }}</span></td>
                                <td class="rpt-num">{{ $row['hire_date'] }}</td>
                                <td><span class="rpt-pill {{ $row['status_tone'] }}">{{ $row['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @include('hr::reports.employees._pager', ['pager' => $isSummary ? $summary : $details])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => $reportTitle.' — '.($isSummary ? 'لكل '.$groupLabel : 'لكل موظف'),
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => $isSummary
            ? ['#', $groupLabel, 'العدد', 'على رأس العمل', 'منهم تحت التجربة', 'متوقّف مؤقتاً', 'انتهت خدمته', 'النسبة']
            : ['#', $groupLabel, 'الرقم', 'الموظف', $otherLabel, 'الفرع', 'التعيين', 'الحالة'],
    ])
@endsection
