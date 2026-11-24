{{--
    النشطون وغير النشطين — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::byStatus`. عرضان: حالةٌ لكلّ صف
    (الحالات الستّ)، أو موظفٌ لكلّ صف مع تاريخ الإنهاء وسببه.
--}}
@extends('hr::master')

@section('title')
    النشطون وغير النشطين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php $isSummary = $reportView === 'summary'; @endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'user-tag',
        'headTitle' => 'النشطون وغير النشطين',
        'headSubtitle' => 'الموظفون حسب حالتهم الوظيفية اليوم: نشط، تحت التجربة، غير نشط، موقوف، مستقيل، منتهي الخدمة',
        'headGuideKey' => 'hr-reports.employees-by-status',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من «الحالة» في ملف كل موظف كما هي الآن، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                '«على رأس العمل» = نشط + تحت التجربة — يُصرف لهما الراتب.',
                '«متوقّف مؤقتاً» = غير نشط أو موقوف. «انتهت خدمته» = مستقيل أو منتهي الخدمة.',
                'حقل «تعيين أو إنهاء بين» اختياري: يحصر القائمة فيمن عُيِّن أو انتهت خدمته في المدى.',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.by_status') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field')

                    <div class="rpt-field">
                        <label for="from_date">تعيين أو إنهاء بين</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date') }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="termination_reason">سبب الإنهاء يحتوي</label>
                        <input type="text" name="termination_reason" id="termination_reason" class="form-control" value="{{ request('termination_reason') }}">
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-layer-group"></i> لكل حالة</button>
                            <button type="submit" name="view" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-users"></i> لكل موظف</button>
                        </div>
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.by_status')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">الموظفون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['all']) }}</bdi></div><div class="ag-kpi__sub">في هذا النطاق</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">على رأس العمل</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['working']) }}</bdi></div><div class="ag-kpi__sub">منهم تحت التجربة {{ $totals['probation'] }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-check"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">متوقّفون مؤقتاً</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['stopped']) }}</bdi></div><div class="ag-kpi__sub">غير نشط + موقوف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-clock"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">انتهت خدمتهم</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['ended']) }}</bdi></div><div class="ag-kpi__sub">مستقيل + منتهي الخدمة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-times"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'لكل حالة' : 'لكل موظف' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? number_format($summary->total()).' حالة' : number_format($details->total()).' موظف' }}</span>
            </h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead><tr><th>#</th><th>الحالة</th><th>المجموعة</th><th>العدد</th><th>النسبة</th></tr></thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>{{ $summary->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($row['count'])
                                        <a href="{{ route('hr.reports.employees.by_status', array_merge(request()->except(['page', 'view']), ['view' => 'details', 'status' => $row['value']])) }}"><span class="rpt-pill {{ $row['tone'] }}">{{ $row['label'] }}</span></a>
                                    @else
                                        <span class="rpt-pill {{ $row['tone'] }}">{{ $row['label'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['group'] }}</td>
                                <td class="rpt-num"><strong>{{ number_format($row['count']) }}</strong></td>
                                <td class="rpt-num">{{ $row['share'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>الرقم</th><th>الموظف</th><th>القسم</th><th>المسمى</th><th>الفرع</th><th>الحالة</th><th>التعيين</th><th>تاريخ الإنهاء</th><th>آخر يوم عمل</th><th>سبب الإنهاء</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($details as $row)
                            <tr>
                                <td>{{ $details->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">{{ $row['code'] }}</td>
                                <td class="rpt-name">@include('hr::reports.employees._name', ['empId' => $row['id'], 'empName' => $row['name']])</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['job_title'] }}</span></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['branch'] }}</span></td>
                                <td><span class="rpt-pill {{ $row['status_tone'] }}">{{ $row['status'] }}</span></td>
                                <td class="rpt-num">{{ $row['hire_date'] }}</td>
                                <td class="rpt-num">{{ $row['termination_date'] }}</td>
                                <td class="rpt-num">{{ $row['last_working_day'] }}</td>
                                <td>{{ $row['reason'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
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
        'frTitle' => 'النشطون وغير النشطين — '.($isSummary ? 'لكل حالة' : 'لكل موظف'),
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => $isSummary
            ? ['#', 'الحالة', 'المجموعة', 'العدد', 'النسبة']
            : ['#', 'الرقم', 'الموظف', 'القسم', 'المسمى', 'الفرع', 'الحالة', 'التعيين', 'تاريخ الإنهاء', 'آخر يوم عمل', 'سبب الإنهاء'],
    ])
@endsection
