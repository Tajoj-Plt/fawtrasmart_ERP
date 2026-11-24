{{--
    ملخّص الحضور الشامل — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `Reports\AttendanceReportController::summary`. عرضان: موظفٌ لكلّ صف،
    أو يومٌ لكلّ صف. التصديرُ والطباعةُ للعرض الحاليّ كلِّه (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    ملخص الحضور الشامل
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $hm = fn (int $m) => $m > 0 ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '—';
    $money = fn ($v) => $v > 0 ? number_format($v, 2) : '—';
    $tone = [
        'present' => 'rpt-pill-success', 'late' => 'rpt-pill-warning', 'absent' => 'rpt-pill-danger',
        'half_day' => 'rpt-pill-warning', 'leave' => 'rpt-pill-secondary', 'holiday' => 'rpt-pill-secondary',
    ];
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'user-check',
        'headTitle' => 'ملخص الحضور الشامل',
        'headSubtitle' => 'لكل موظف: أيام الحضور والغياب والإجازات، والتأخير والانصراف المبكر والإضافي والاستراحة، وصافي ساعات العمل، وخصم الحضور',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.attendance-summary',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من أيام الحضور كما احتسبها النظام بعد كل تسجيل، وبعد المعالجة الليلية.',
            'blocks' => [[
                '«أيام العمل» = حاضر + متأخر + نصف يوم. و«صافي العمل» = من الحضور إلى الانصراف ناقصاً الاستراحات.',
                '«خصم الحضور» = خصميات الغياب والتأخير والانصراف المبكر ونصف اليوم غير الملغاة — ومنها المعلّق بانتظار الاعتماد.',
                '«لم ينصرف» يومٌ فيه حضور بلا انصراف — راجعه أو اطلب له تصحيحاً.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.attendance.summary') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields')

                    @unless ($isSummary)
                        <div class="rpt-field">
                            <label for="day_status">حالة اليوم</label>
                            <select name="day_status" id="day_status" class="form-control">
                                <option value="">كل الحالات</option>
                                @foreach ($statusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(request('day_status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endunless

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-users"></i> لكل موظف</button>
                            <button type="submit" name="view" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-calendar-day"></i> لكل يوم</button>
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.attendance.summary') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">أيام العمل</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['worked']) }}</bdi></div><div class="ag-kpi__sub">لـ {{ number_format($totals['employees']) }} موظف · صافي {{ $hm($totals['net_minutes']) }} ساعة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-check"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">أيام الغياب</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['absent']) }}</bdi></div><div class="ag-kpi__sub">يوم</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-times"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">التأخير</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['late_days']) }}</bdi></div><div class="ag-kpi__sub">يوم · {{ $hm($totals['late_minutes']) }} ساعة · مبكر {{ $hm($totals['early_minutes']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-clock"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">خصم الحضور</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['deduction'], 2) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['deduction_pending'] > 0 ? 'منها '.number_format($totals['deduction_pending'], 2).' بانتظار الاعتماد' : 'إضافي '.$hm($totals['overtime_minutes']).' ساعة' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'الحضور لكل موظف' : 'الحضور لكل يوم' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? number_format($summary->total()).' موظف' : number_format($details->total()).' يوم' }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>الموظف</th><th>القسم</th><th>أيام العمل</th><th>غياب</th><th>نصف يوم</th>
                            <th>إجازة</th><th>عطلة</th><th>أيام تأخير</th><th>دقائق التأخير</th><th>انصراف مبكر</th>
                            <th>إضافي</th><th>استراحة</th><th>صافي العمل</th><th>لم ينصرف</th><th>خصم الحضور</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>{{ $summary->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.attendance.summary', array_merge(request()->except(['page', 'view']), ['view' => 'details', 'employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    <div class="text-muted small">{{ $row['job_title'] }}</div>
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td class="rpt-num"><strong>{{ $row['worked'] }}</strong></td>
                                <td class="rpt-num {{ $row['absent'] ? 'rpt-neg' : '' }}">{{ $row['absent'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['half'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['leave'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['off'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['late_days'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['late_minutes'] ? 'rpt-neg' : '' }}">{{ $hm($row['late_minutes']) }}</td>
                                <td class="rpt-num {{ $row['early_minutes'] ? 'rpt-neg' : '' }}">{{ $hm($row['early_minutes']) }}</td>
                                <td class="rpt-num {{ $row['overtime_minutes'] ? 'rpt-pos' : '' }}">{{ $hm($row['overtime_minutes']) }}</td>
                                <td class="rpt-num">{{ $hm($row['break_minutes']) }}</td>
                                <td class="rpt-num"><strong>{{ $hm($row['net_minutes']) }}</strong></td>
                                <td class="rpt-num {{ $row['missing_checkout'] ? 'rpt-neg' : '' }}">{{ $row['missing_checkout'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['deduction'] ? 'rpt-neg' : '' }}">
                                    {{ $money($row['deduction']) }}
                                    @if ($row['deduction_pending'] > 0)<div class="small text-muted">معلّق {{ number_format($row['deduction_pending'], 2) }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="16" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>التاريخ</th><th>اليوم</th><th>الموظف</th><th>القسم</th><th>الحالة</th><th>الدوام</th>
                            <th>الحضور</th><th>الانصراف</th><th>تأخير (د)</th><th>مبكر (د)</th><th>إضافي (د)</th><th>استراحة (د)</th><th>صافي العمل</th><th>الخصم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($details as $row)
                            <tr>
                                <td>{{ $details->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">
                                    @if (Route::has('attendanceDays.show') && hasPermission('attendance_days.show'))
                                        <a href="{{ route('attendanceDays.show', $row['day_id']) }}" target="_blank" rel="noopener">{{ $row['date'] }}</a>
                                    @else
                                        {{ $row['date'] }}
                                    @endif
                                </td>
                                <td>{{ $row['day_name'] }}</td>
                                <td class="rpt-name">{{ $row['employee'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td><span class="rpt-pill {{ $tone[$row['status']] ?? 'rpt-pill-secondary' }}">{{ $row['status_label'] }}</span></td>
                                <td class="rpt-num">{{ $row['expected'] }}</td>
                                <td class="rpt-num">{{ $row['in'] }}</td>
                                <td class="rpt-num">{{ $row['out'] }}</td>
                                <td class="rpt-num {{ $row['late'] ? 'rpt-neg' : '' }}">{{ $row['late'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['early'] ? 'rpt-neg' : '' }}">{{ $row['early'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['overtime'] ? 'rpt-pos' : '' }}">{{ $row['overtime'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['break'] ?: '—' }}</td>
                                <td class="rpt-num"><strong>{{ $hm($row['net']) }}</strong></td>
                                <td class="rpt-num {{ $row['deduction'] ? 'rpt-neg' : '' }}">{{ $money($row['deduction']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="15" class="rpt-empty"><i class="fas fa-inbox"></i> لا أيام حضور في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @php $pager = $isSummary ? $summary : $details; @endphp
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
        'frTitle' => $isSummary ? 'ملخص الحضور الشامل — لكل موظف' : 'ملخص الحضور الشامل — لكل يوم',
        'frInfo' => $periodLabel,
        'frHeaders' => $isSummary
            ? ['#', 'الموظف', 'القسم', 'أيام العمل', 'غياب', 'نصف يوم', 'إجازة', 'عطلة', 'أيام تأخير', 'دقائق التأخير', 'انصراف مبكر', 'إضافي', 'استراحة', 'صافي العمل', 'لم ينصرف', 'خصم الحضور']
            : ['#', 'التاريخ', 'اليوم', 'الموظف', 'القسم', 'الحالة', 'الدوام', 'الحضور', 'الانصراف', 'تأخير (د)', 'مبكر (د)', 'إضافي (د)', 'استراحة (د)', 'صافي العمل', 'الخصم'],
    ])
@endsection
