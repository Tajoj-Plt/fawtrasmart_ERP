{{--
    صرف الرواتب — بتصميم تقارير العملاء (rpt / ag). المسار `hr.reports.payments`.
    المتحكّم: `Reports\PayrollDocumentsReportController::payments` (عبر
    `HRReportsController::paymentsReport`). ثلاثةُ عروض: لكلّ دفعة، لكلّ موظف،
    لكلّ خزينة. المصدرُ `payroll_runs` المدفوعة وحدها.
--}}
@extends('hr::master')

@section('title')
    صرف الرواتب
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $views = ['runs' => ['لكل دفعة', 'receipt'], 'employees' => ['لكل موظف', 'users'], 'treasuries' => ['لكل خزينة', 'cash-register']];
    $money = fn ($v) => $v > 0 ? number_format($v, 2) : '—';
    $canShowRun = Route::has('payroll-runs.show') && hasPermission('disbursements.show');
    $pager = match ($reportView) { 'employees' => $byEmployee, 'treasuries' => $byTreasury, default => $lines };
    $headers = match ($reportView) {
        'employees' => ['#', 'الموظف', 'القسم', 'الدفعات', 'الأساسي', 'الإضافات', 'الخصميات', 'السلف المخصومة', 'الصافي المصروف', 'آخر صرف'],
        'treasuries' => ['#', 'الخزينة', 'الدفعات', 'الموظفون', 'الصافي المصروف', 'أول صرف', 'آخر صرف'],
        default => ['#', 'تاريخ الصرف', 'المرجع', 'الموظف', 'القسم', 'الفترة', 'الأساسي', 'الإضافات', 'الخصميات', 'السلف المخصومة', 'الصافي', 'الخزينة', 'القيد'],
    };
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'hand-holding-usd',
        'headTitle' => 'صرف الرواتب',
        'headSubtitle' => 'ما صُرف فعلاً للموظفين: لكل دفعة أو لكل موظف أو لكل خزينة — الأساسي والإضافات والخصميات والسلف والصافي',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-payments',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من دفعات الرواتب المدفوعة فقط — كل دفعة راتب فترة لموظف واحد، خرج نقدها من الخزينة وكُتب قيدها.',
            'blocks' => [[
                'تاريخ الدفعة = يوم صرفها؛ والدفعات القديمة بلا يوم صرف تُحسب بيومها المجدول ثم بنهاية فترتها.',
                '«الصافي» = الأساسي + الإضافات − الخصميات − السلف المخصومة، كما حُسب يوم الصرف ولا يتغيّر بعده.',
                'الدفعات المعلّقة والموقوفة والملغاة لا تدخل المجاميع؛ وعدد المعلّقة حتى نهاية الفترة يظهر في المؤشر الأخير.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.payments') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['defaultEmployeeStatus' => ''])

                    <div class="rpt-field">
                        <label for="treasury_id">الخزينة</label>
                        <select name="treasury_id" id="treasury_id" class="form-control rpt-select2">
                            <option value="">كل الخزائن</option>
                            @foreach ($treasuryOptions as $item)
                                <option value="{{ $item->id }}" @selected((string) request('treasury_id') === (string) $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            @foreach ($views as $key => [$label, $icon])
                                <button type="submit" name="view" value="{{ $key }}" class="btn {{ $reportView === $key ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-{{ $icon }}"></i> {{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.payments') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">الصافي المصروف</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['net'], 2) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['runs']) }} دفعة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">الموظفون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">صُرف لهم في الفترة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">الخصميات والسلف</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['deductions'] + $totals['advances'], 2) }}</bdi></div><div class="ag-kpi__sub">خصميات {{ number_format($totals['deductions'], 2) }} · سلف {{ number_format($totals['advances'], 2) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">بانتظار الصرف</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['pending']) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['pending'] ? 'دفعة حتى نهاية الفترة'.($totals['held'] ? ' · منها '.$totals['held'].' موقوفة' : '') : 'لا دفعات معلّقة' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> صرف الرواتب — {{ $views[$reportView][0] }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ ['runs' => 'دفعة', 'employees' => 'موظف', 'treasuries' => 'خزينة'][$reportView] }}</span>
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
                    <tr>
                        @foreach ($headers as $h)<th>{{ $h }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($reportView === 'employees')
                        @forelse ($byEmployee as $row)
                            <tr>
                                <td>{{ $byEmployee->firstItem() + $loop->index }}</td>
                                <td class="rpt-name"><a href="{{ route('hr.reports.payments', array_merge(request()->except(['page', 'view']), ['view' => 'runs', 'employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td class="rpt-num">{{ $row['runs'] }}</td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num {{ $row['additions'] ? 'rpt-pos' : '' }}">{{ $money($row['additions']) }}</td>
                                <td class="rpt-num {{ $row['deductions'] ? 'rpt-neg' : '' }}">{{ $money($row['deductions']) }}</td>
                                <td class="rpt-num {{ $row['advances'] ? 'rpt-neg' : '' }}">{{ $money($row['advances']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td class="rpt-num">{{ $row['last'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب مصروفة في هذه الفترة والنطاق</td></tr>
                        @endforelse
                    @elseif ($reportView === 'treasuries')
                        @forelse ($byTreasury as $row)
                            <tr>
                                <td>{{ $byTreasury->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($row['treasury_id'])
                                        <a href="{{ route('hr.reports.payments', array_merge(request()->except(['page', 'view']), ['view' => 'runs', 'treasury_id' => $row['treasury_id']])) }}">{{ $row['treasury'] }}</a>
                                    @else
                                        {{ $row['treasury'] }}
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $row['runs'] }}</td>
                                <td class="rpt-num">{{ $row['employees'] }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td class="rpt-num">{{ $row['first'] }}</td>
                                <td class="rpt-num">{{ $row['last'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب مصروفة في هذه الفترة والنطاق</td></tr>
                        @endforelse
                    @else
                        @forelse ($lines as $row)
                            <tr>
                                <td>{{ $lines->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">{{ $row['date'] }}</td>
                                <td class="rpt-num">
                                    @if ($canShowRun)
                                        <a href="{{ route('payroll-runs.show', $row['run_id']) }}" target="_blank" rel="noopener">{{ $row['code'] }}</a>
                                    @else
                                        {{ $row['code'] }}
                                    @endif
                                </td>
                                <td class="rpt-name"><a href="{{ route('hr.reports.payments', array_merge(request()->except(['page']), ['employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td class="rpt-num">{{ $row['period'] }}</td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num {{ $row['additions'] ? 'rpt-pos' : '' }}">{{ $money($row['additions']) }}</td>
                                <td class="rpt-num {{ $row['deductions'] ? 'rpt-neg' : '' }}">{{ $money($row['deductions']) }}</td>
                                <td class="rpt-num {{ $row['advances'] ? 'rpt-neg' : '' }}">{{ $money($row['advances']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['treasury'] }}</span></td>
                                <td class="rpt-num">{{ $row['journal'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب مصروفة في هذه الفترة والنطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
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
        'frTitle' => 'صرف الرواتب — '.$views[$reportView][0],
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
