{{--
    وثائق الموظفين المنتهية — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::documents`. وثيقةٌ لكلّ صف مع الأيام
    المتبقية، وأحدثُ وثيقةٍ لكلّ موظفٍ ونوع (المجدَّدةُ لا تُعدّ منتهية).
--}}
@extends('hr::master')

@section('title')
    وثائق الموظفين المنتهية
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $horizonTitles = ['window' => 'انتهت أو تنتهي خلال '.$within.' يوماً', 'expired' => 'المنتهية فقط', 'all' => 'كل الوثائق ذات تاريخ انتهاء'];
    $kpiLink = fn (string $h) => route('hr.reports.employees.documents', array_merge(request()->except(['page', 'horizon']), ['horizon' => $h]));
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'id-card',
        'headTitle' => 'وثائق الموظفين المنتهية',
        'headSubtitle' => 'الهويات والإقامات والجوازات والرخص التي انتهت أو قاربت على الانتهاء — مع الأيام المتبقية',
        'headGuideKey' => 'hr-reports.employees-documents',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من وثائق الموظفين المرفوعة في ملفاتهم (ما له تاريخ انتهاء فقط)، لمن في نطاق رؤيتك.',
            'blocks' => [[
                'لكل موظف ونوع وثيقة تُقرأ أحدث وثيقة انتهاءً: الإقامة المجدَّدة لا تُبقي القديمة «منتهية».',
                '«الأيام المتبقية» من اليوم إلى تاريخ الانتهاء. ومدة «تنتهي خلال» تبدأ من مدة الإنذار في إعدادات الرواتب ('.$noticeDays.' يوماً).',
                'الافتراض: من هم على رأس العمل فقط — غيّر «حالة الموظف» لترى وثائق من غادر.',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.documents') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field', ['statusName' => 'employee_state', 'statusDefault' => 'working', 'statusLabel' => 'حالة الموظف'])

                    <div class="rpt-field">
                        <label for="type">نوع الوثيقة</label>
                        <select name="type" id="type" class="form-control">
                            <option value="">كل الأنواع</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="horizon">الوثائق</label>
                        <select name="horizon" id="horizon" class="form-control">
                            @foreach ($horizonTitles as $value => $label)
                                <option value="{{ $value }}" @selected($horizon === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="within_days">تنتهي خلال (يوماً)</label>
                        <input type="number" min="0" name="within_days" id="within_days" class="form-control" value="{{ $within }}">
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.documents')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <a href="{{ $kpiLink('expired') }}" class="ag-kpi{{ $horizon === 'expired' ? ' is-active' : '' }}" style="--c:#ef4444">
            <div><div class="ag-kpi__label">منتهية</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['expired']) }}</bdi></div><div class="ag-kpi__sub">وثيقة يجب تجديدها</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        </a>
        <a href="{{ $kpiLink('window') }}" class="ag-kpi{{ $horizon === 'window' ? ' is-active' : '' }}" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">تنتهي قريباً</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['expiring']) }}</bdi></div><div class="ag-kpi__sub">خلال {{ $within }} يوماً</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </a>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">موظفون معنيّون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">لديهم وثيقة منتهية أو قريبة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <a href="{{ $kpiLink('all') }}" class="ag-kpi{{ $horizon === 'all' ? ' is-active' : '' }}" style="--c:#10b981">
            <div><div class="ag-kpi__label">سارية</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['valid']) }}</bdi></div><div class="ag-kpi__sub">بعد أكثر من {{ $within }} يوماً</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </a>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-table"></i> {{ $horizonTitles[$horizon] }} <span class="rpt-pill rpt-pill-secondary">{{ number_format($rows->total()) }} وثيقة</span></h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr><th>#</th><th>الموظف</th><th>القسم</th><th>الوثيقة</th><th>الرقم</th><th>جهة الإصدار</th><th>الإصدار</th><th>الانتهاء</th><th>الأيام المتبقية</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="rpt-name">@include('hr::reports.employees._name', ['empId' => $row['employee_id'], 'empName' => $row['employee']])</td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                            <td>
                                {{ $row['title'] }}
                                @if ($row['title'] !== $row['type'])<div class="small text-muted">{{ $row['type'] }}</div>@endif
                            </td>
                            <td class="rpt-num">{{ $row['number'] }}</td>
                            <td>{{ $row['authority'] }}</td>
                            <td class="rpt-num">{{ $row['issue'] }}</td>
                            <td class="rpt-num">{{ $row['expiry'] }}</td>
                            <td class="rpt-num">@include('hr::reports.employees._days', ['days' => $row['days'], 'warnWithin' => $within, 'endedWord' => 'انتهت'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="rpt-empty"><i class="fas fa-inbox"></i> لا وثائق مطابقة — وسّع مدة «تنتهي خلال» أو اختر نوعاً آخر</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('hr::reports.employees._pager', ['pager' => $rows])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'وثائق الموظفين — '.$horizonTitles[$horizon],
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => ['#', 'الموظف', 'القسم', 'الوثيقة', 'الرقم', 'جهة الإصدار', 'الإصدار', 'الانتهاء', 'الأيام المتبقية'],
    ])
@endsection
