{{--
    المتحقّقون والمغادرون — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::turnover`. حركةٌ لكلّ صف خلال المدى:
    التحاقٌ بتاريخ التعيين، ومغادرةٌ بتاريخ الإنهاء وسببه.
--}}
@extends('hr::master')

@section('title')
    المتحقّقون والمغادرون
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'exchange-alt',
        'headTitle' => 'المتحقّقون والمغادرون',
        'headSubtitle' => 'من التحق ومن غادر خلال الفترة، مع القسم وسبب المغادرة ومعدّلها',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.employees-turnover',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من تاريخ التعيين وتاريخ الإنهاء في ملفات الموظفين، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                '«التحاق» = تاريخ تعيينه داخل الفترة. «مغادرة» = تاريخ إنهاء خدمته داخل الفترة.',
                '«العدد في أول الفترة» = من عُيِّن قبلها ولم تنتهِ خدمته قبلها. و«معدّل المغادرة» = المغادرون ÷ العدد في أول الفترة.',
                'من سُجّلت حالته «مستقيل» أو «منتهي الخدمة» بلا تاريخ إنهاء لا يظهر هنا — أكمل تاريخ الإنهاء في ملفه.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.turnover') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withStatus' => false])

                    <div class="rpt-field">
                        <label for="movement">الحركة</label>
                        <select name="movement" id="movement" class="form-control">
                            <option value="" @selected($movement === '')>الالتحاق والمغادرة</option>
                            <option value="hire" @selected($movement === 'hire')>الملتحقون فقط</option>
                            <option value="leave" @selected($movement === 'leave')>المغادرون فقط</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="termination_reason">سبب المغادرة يحتوي</label>
                        <input type="text" name="termination_reason" id="termination_reason" class="form-control" value="{{ request('termination_reason') }}">
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.turnover')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">الملتحقون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['hires']) }}</bdi></div><div class="ag-kpi__sub">تعيين في الفترة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-plus"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">المغادرون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['leavers']) }}</bdi></div><div class="ag-kpi__sub">انتهاء خدمة في الفترة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-minus"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">صافي التغيّر</div><div class="ag-kpi__value"><bdi>{{ ($totals['net'] > 0 ? '+' : '').number_format($totals['net']) }}</bdi></div><div class="ag-kpi__sub">العدد في أول الفترة {{ number_format($totals['headcount_start']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">معدّل المغادرة</div><div class="ag-kpi__value"><bdi>{{ $totals['rate'] === null ? '—' : $totals['rate'].'%' }}</bdi></div><div class="ag-kpi__sub">المغادرون ÷ العدد في أول الفترة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-percent"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-table"></i> الحركات <span class="rpt-pill rpt-pill-secondary">{{ number_format($rows->total()) }} حركة</span></h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr><th>#</th><th>الحركة</th><th>التاريخ</th><th>الرقم</th><th>الموظف</th><th>القسم</th><th>المسمى</th><th>الفرع</th><th>الحالة اليوم</th><th>آخر يوم عمل</th><th>سبب المغادرة</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td><span class="rpt-pill {{ $row['kind'] === 'hire' ? 'rpt-pill-success' : 'rpt-pill-danger' }}">{{ $row['kind_label'] }}</span></td>
                            <td class="rpt-num">{{ $row['date'] }}</td>
                            <td class="rpt-num">{{ $row['code'] }}</td>
                            <td class="rpt-name">@include('hr::reports.employees._name', ['empId' => $row['id'], 'empName' => $row['name']])</td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['job_title'] }}</span></td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['branch'] }}</span></td>
                            <td><span class="rpt-pill {{ $row['status_tone'] }}">{{ $row['status'] }}</span></td>
                            <td class="rpt-num">{{ $row['last_working_day'] }}</td>
                            <td>{{ $row['reason'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="rpt-empty"><i class="fas fa-inbox"></i> لا التحاق ولا مغادرة في هذه الفترة</td></tr>
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
        'frTitle' => 'المتحقّقون والمغادرون',
        'frInfo' => $periodLabel,
        'frHeaders' => ['#', 'الحركة', 'التاريخ', 'الرقم', 'الموظف', 'القسم', 'المسمى', 'الفرع', 'الحالة اليوم', 'آخر يوم عمل', 'سبب المغادرة'],
    ])
@endsection
