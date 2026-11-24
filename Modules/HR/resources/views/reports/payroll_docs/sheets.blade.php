{{--
    مسيرات الرواتب — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `Reports\PayrollDocumentsReportController::sheets`. عرضان: مسيرٌ لكلّ صف،
    أو موظفٌ لكلّ صف داخل المسيرات (ومن يشمله المسير بلا قسيمةٍ يظهر كذلك).
--}}
@extends('hr::master')

@section('title')
    مسيرات الرواتب
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $money = fn ($v) => $v > 0 ? number_format($v, 2) : '—';
    $sheetTone = ['active' => 'rpt-pill-success', 'draft' => 'rpt-pill-warning', 'inactive' => 'rpt-pill-danger'];
    $slipTone = [
        'draft' => 'rpt-pill-secondary', 'approved' => 'rpt-pill-warning', 'active' => 'rpt-pill-success',
        'stopped' => 'rpt-pill-danger', 'expired' => 'rpt-pill-secondary',
    ];
    $stageTone = [
        'paid' => 'rpt-pill-success', 'ready' => 'rpt-pill-warning', 'scheduled' => 'rpt-pill-secondary',
        'awaiting_approval' => 'rpt-pill-warning', 'held' => 'rpt-pill-danger', 'cancelled' => 'rpt-pill-secondary',
    ];
    $canShowSheet = Route::has('PayrollProcess.show') && hasPermission('payroll.show');
    $canShowSlip = Route::has('salarySlip.show') && hasPermission('payslips.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'clipboard-list',
        'headTitle' => 'مسيرات الرواتب',
        'headSubtitle' => 'لكل مسير: من يشمل، وكم قسيمة فيه، وصافيه الشهري المتوقع، وما صُرف من قسائمه في الفترة وما ينتظر',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-sheets',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'المسير مظلّة زمنية تجمع الموظفين، واعتماده هو الإذن الوحيد بصرف رواتب قسائمه. الصرف نفسه يتم على كل قسيمة.',
            'blocks' => [[
                '«الموظفون» = من حُفظوا في المسير (أو من تنطبق عليهم قواعده في المسيرات القديمة) ومن له قسيمة فيه — في حدود نطاقك وفلاترك.',
                '«الأساسي» و«الصافي المتوقع» للقسائم السارية فقط (المعتمدة والنشطة)، بالطريقة نفسها التي يحسب بها الصرف، وقبل الإضافي والتأمينات والسلف.',
                '«المصروف في الفترة» = صافي الصرفات المدفوعة لقسائم المسير بتاريخ صرفها داخل الفترة المختارة.',
                '«معلّقة» = صرفات لم تُدفع بعد (أي تاريخ)، ومنها الموقوفة بقرار.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.payroll.sheets') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['defaultEmployeeStatus' => ''])

                    <div class="rpt-field">
                        <label for="payroll_id">المسير</label>
                        <select name="payroll_id" id="payroll_id" class="form-control rpt-select2">
                            <option value="">كل المسيرات</option>
                            @foreach ($sheetOptions as $sheet)
                                <option value="{{ $sheet->id }}" @selected((string) request('payroll_id') === (string) $sheet->id)>{{ $sheet->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="sheet_status">حالة المسير</label>
                        <select name="sheet_status" id="sheet_status" class="form-control">
                            <option value="">كل الحالات</option>
                            @foreach ($sheetStatuses as $key => $label)
                                <option value="{{ $key }}" @selected(request('sheet_status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-clipboard-list"></i> لكل مسير</button>
                            <button type="submit" name="view" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-users"></i> لكل موظف</button>
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.payroll.sheets') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">المسيرات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['sheets']) }}</bdi></div><div class="ag-kpi__sub">مسير في هذا النطاق</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-clipboard-list"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">المعتمدة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['approved']) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['sheets'] - $totals['approved'] > 0 ? ($totals['sheets'] - $totals['approved']).' مسودة أو موقوف' : 'كلها معتمدة' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الموظفون المشمولون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">بلا تكرار بين المسيرات</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">المصروف في الفترة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['paid'], 2) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['paid_count']) }} صرفة{{ $totals['pending'] ? ' · '.$totals['pending'].' معلّقة'.($totals['held'] ? ' (منها '.$totals['held'].' موقوفة)' : '') : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'المسيرات' : 'موظفو المسيرات' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? number_format($summary->total()).' مسير' : number_format($details->total()).' صف' }}</span>
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
                            <th>#</th><th>المسير</th><th>الدورة</th><th>المدة</th><th>الموظفون</th><th>القسائم</th>
                            <th>الأساسي الشهري</th><th>الصافي المتوقع</th><th>المصروف في الفترة</th><th>صرفات</th><th>معلّقة</th><th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>{{ $summary->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.payroll.sheets', array_merge(request()->except(['page', 'view']), ['view' => 'details', 'payroll_id' => $row['payroll_id']])) }}">{{ $row['name'] }}</a>
                                    @if ($canShowSheet)
                                        <a href="{{ route('PayrollProcess.show', $row['payroll_id']) }}" target="_blank" rel="noopener" class="small text-muted ms-1" title="فتح المسير"><i class="fas fa-external-link-alt"></i></a>
                                    @endif
                                </td>
                                <td>{{ $row['cycle'] }}</td>
                                <td class="rpt-num">{{ $row['period'] }}</td>
                                <td class="rpt-num">{{ $row['employees'] }}</td>
                                <td class="rpt-num">{{ $row['slips'] }}@if ($row['slips'] !== $row['live_slips'])<div class="small text-muted">سارية {{ $row['live_slips'] }}</div>@endif</td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td class="rpt-num {{ $row['paid'] ? 'rpt-pos' : '' }}">{{ $money($row['paid']) }}</td>
                                <td class="rpt-num">{{ $row['paid_count'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['held'] ? 'rpt-neg' : '' }}">{{ $row['pending'] ?: '—' }}@if ($row['held'])<div class="small">موقوفة {{ $row['held'] }}</div>@endif</td>
                                <td>
                                    <span class="rpt-pill {{ $sheetTone[$row['status']] ?? 'rpt-pill-secondary' }}">{{ $row['status_label'] }}</span>
                                    @if ($row['approved_at'])<div class="small text-muted">{{ $row['approved_at'] }}{{ $row['approved_by'] ? ' · '.$row['approved_by'] : '' }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لا مسيرات في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>المسير</th><th>الموظف</th><th>القسيمة</th><th>حالة القسيمة</th><th>الأساسي</th>
                            <th>البدلات</th><th>الخصم الثابت</th><th>الصافي المتوقع</th><th>آخر صرفة</th><th>المصروف في الفترة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($details as $row)
                            <tr>
                                <td>{{ $details->firstItem() + $loop->index }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['sheet'] }}</span></td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.payroll.sheets', array_merge(request()->except(['page']), ['employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    <div class="text-muted small">{{ $row['department'] }}</div>
                                </td>
                                <td class="rpt-num">
                                    @if ($row['slip_id'] && $canShowSlip)
                                        <a href="{{ route('salarySlip.show', $row['slip_id']) }}" target="_blank" rel="noopener">{{ $row['code'] }}</a>
                                    @else
                                        {{ $row['code'] }}
                                    @endif
                                </td>
                                <td><span class="rpt-pill {{ $row['slip_status'] ? ($slipTone[$row['slip_status']] ?? 'rpt-pill-secondary') : 'rpt-pill-danger' }}">{{ $row['slip_status_label'] }}</span></td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num {{ $row['allowances'] ? 'rpt-pos' : '' }}">{{ $money($row['allowances']) }}</td>
                                <td class="rpt-num {{ $row['deductions'] ? 'rpt-neg' : '' }}">{{ $money($row['deductions']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td>
                                    @if ($row['last_run_stage'])
                                        <span class="rpt-pill {{ $stageTone[$row['last_run_stage']] ?? 'rpt-pill-secondary' }}">{{ $row['last_run'] }}</span>
                                        <div class="small text-muted">{{ $row['last_run_period'] }}</div>
                                    @else
                                        <span class="text-muted">{{ $row['last_run'] }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num {{ $row['paid'] ? 'rpt-pos' : '' }}">{{ $money($row['paid']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في المسيرات المختارة</td></tr>
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
        'frTitle' => $isSummary ? 'مسيرات الرواتب' : 'مسيرات الرواتب — لكل موظف',
        'frInfo' => $periodLabel,
        'frHeaders' => $isSummary
            ? ['#', 'المسير', 'الدورة', 'المدة', 'الموظفون', 'القسائم', 'الأساسي الشهري', 'الصافي المتوقع', 'المصروف في الفترة', 'صرفات', 'معلّقة', 'الحالة']
            : ['#', 'المسير', 'الموظف', 'القسم', 'القسيمة', 'حالة القسيمة', 'الأساسي', 'البدلات', 'الخصم الثابت', 'الصافي المتوقع', 'آخر صرفة', 'المصروف في الفترة'],
    ])
@endsection
