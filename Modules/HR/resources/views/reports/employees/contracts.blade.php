{{--
    عقود الموظفين — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::contracts`. عقدٌ لكلّ صف مع الأيام
    المتبقية. «تنتهي قريباً» و«المنتهية» تقرآن آخرَ عقدٍ لكلّ موظف.
--}}
@extends('hr::master')

@section('title')
    العقود
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $statusTitles = ['expiring' => 'تنتهي خلال '.$within.' يوماً', 'expired' => 'المنتهية', 'active' => 'السارية', 'all' => 'كل العقود'];
    $stateTones = ['expired' => 'rpt-pill-danger', 'expiring' => 'rpt-pill-warning', 'active' => 'rpt-pill-success', 'open' => 'rpt-pill-success'];
    $kpiLink = fn (string $s) => route('hr.reports.employees.contracts', array_merge(request()->except(['page', 'status']), ['status' => $s]));
    $canShowContract = Route::has('Contracts.show') && hasPermission('contracts.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'file-contract',
        'headTitle' => 'العقود',
        'headSubtitle' => 'العقود المنتهية والتي تنتهي قريباً والسارية — مع الأيام المتبقية لكل عقد',
        'headGuideKey' => 'hr-reports.employees-contracts',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من عقود الموظفين المسجّلة، لمن في نطاق رؤيتك فقط.',
            'blocks' => [[
                '«الأيام المتبقية» من اليوم إلى تاريخ انتهاء العقد. العقد بلا تاريخ انتهاء ساري دائماً.',
                '«تنتهي قريباً» و«المنتهية» تقرآن آخر عقد لكل موظف: العقد الذي جُدِّد (للموظف عقد أحدث منه) لا يُنبَّه عليه.',
                'مدة «تنتهي خلال» تبدأ من مدة الإنذار في إعدادات الرواتب ('.$noticeDays.' يوماً)، ويمكنك تغييرها هنا.',
                'الافتراض: من هم على رأس العمل فقط — غيّر «حالة الموظف» لترى عقود من غادر.',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.contracts') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field', ['statusName' => 'employee_state', 'statusDefault' => 'working', 'statusLabel' => 'حالة الموظف'])

                    <div class="rpt-field">
                        <label for="status">العقود</label>
                        <select name="status" id="status" class="form-control">
                            @foreach ($statusTitles as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="within_days">تنتهي خلال (يوماً)</label>
                        <input type="number" min="0" name="within_days" id="within_days" class="form-control" value="{{ $within }}">
                    </div>

                    <div class="rpt-field">
                        <label for="expiry_from">تاريخ الانتهاء</label>
                        <div class="ag-range">
                            <input type="date" name="expiry_from" id="expiry_from" class="form-control" value="{{ request('expiry_from') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="expiry_to" id="expiry_to" class="form-control" value="{{ request('expiry_to') }}">
                        </div>
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.contracts')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <a href="{{ $kpiLink('expired') }}" class="ag-kpi{{ $status === 'expired' ? ' is-active' : '' }}" style="--c:#ef4444">
            <div><div class="ag-kpi__label">منتهية</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['expired']) }}</bdi></div><div class="ag-kpi__sub">آخر عقد للموظف انتهى</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-file-excel"></i></span>
        </a>
        <a href="{{ $kpiLink('expiring') }}" class="ag-kpi{{ $status === 'expiring' ? ' is-active' : '' }}" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">تنتهي قريباً</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['expiring']) }}</bdi></div><div class="ag-kpi__sub">خلال {{ $within }} يوماً</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </a>
        <a href="{{ $kpiLink('active') }}" class="ag-kpi{{ $status === 'active' ? ' is-active' : '' }}" style="--c:#10b981">
            <div><div class="ag-kpi__label">سارية</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['valid']) }}</bdi></div><div class="ag-kpi__sub">منها بلا تاريخ انتهاء {{ $totals['open'] }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-file-signature"></i></span>
        </a>
        <div class="ag-kpi" style="--c:#64748b">
            <div><div class="ag-kpi__label">موظفون بلا عقد</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['without']) }}</bdi></div><div class="ag-kpi__sub">لا عقد مسجّل لهم</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-slash"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-table"></i> {{ $statusTitles[$status] }} <span class="rpt-pill rpt-pill-secondary">{{ number_format($rows->total()) }} عقد</span></h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr><th>#</th><th>رقم العقد</th><th>الموظف</th><th>القسم</th><th>المسمى</th><th>نوع العقد</th><th>البداية</th><th>الانتهاء</th><th>الأيام المتبقية</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="rpt-num">
                                @if ($canShowContract)
                                    <a href="{{ route('Contracts.show', $row['id']) }}" target="_blank" rel="noopener">{{ $row['code'] }}</a>
                                @else
                                    {{ $row['code'] }}
                                @endif
                            </td>
                            <td class="rpt-name">@include('hr::reports.employees._name', ['empId' => $row['employee_id'], 'empName' => $row['employee']])</td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['job_title'] }}</span></td>
                            <td>{{ $row['type'] }}</td>
                            <td class="rpt-num">{{ $row['start'] }}</td>
                            <td class="rpt-num">{{ $row['end'] }}</td>
                            <td class="rpt-num">@include('hr::reports.employees._days', ['days' => $row['days'], 'warnWithin' => $within, 'endedWord' => 'انتهى'])</td>
                            <td>
                                <span class="rpt-pill {{ $stateTones[$row['state']] }}">{{ $stateLabels[$row['state']] }}</span>
                                @unless ($row['latest'])<div class="small text-muted">جُدِّد بعقد أحدث</div>@endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا عقود مطابقة — جرّب «كل العقود» أو وسّع مدة «تنتهي خلال»</td></tr>
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
        'frTitle' => 'العقود — '.$statusTitles[$status],
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => ['#', 'رقم العقد', 'الموظف', 'القسم', 'المسمى', 'نوع العقد', 'البداية', 'الانتهاء', 'الأيام المتبقية', 'الحالة'],
    ])
@endsection
