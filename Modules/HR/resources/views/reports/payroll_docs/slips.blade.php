{{--
    قسائم الرواتب — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `Reports\PayrollDocumentsReportController::slipsRegister`. عرضان: قسيمةٌ
    لكلّ صف، أو قسمٌ لكلّ صف (كان تقرير «قسائم الراتب حسب القسم» المستقلّ).
    تقريرٌ لحظيّ بلا مدى: القسيمةُ اتفاقٌ مستمرّ لا حدثٌ في شهر.
--}}
@extends('hr::master')

@section('title')
    قسائم الرواتب
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSlips = $reportView === 'slips';
    $money = fn ($v) => $v > 0 ? number_format($v, 2) : '—';
    $tone = [
        'draft' => 'rpt-pill-secondary', 'approved' => 'rpt-pill-warning', 'active' => 'rpt-pill-success',
        'stopped' => 'rpt-pill-danger', 'expired' => 'rpt-pill-secondary',
    ];
    $canShowSlip = Route::has('salarySlip.show') && hasPermission('payslips.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'file-invoice-dollar',
        'headTitle' => 'قسائم الرواتب',
        'headSubtitle' => 'اتفاق الأجر لكل موظف: الأساسي والبدلات والخصم الثابت والصافي المتوقع، ويوم الصرف وآخر صرف تمّ عليه',
        'headPeriod' => 'الوضع الحالي',
        'headGuideKey' => 'hr-reports.payroll-slips',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'القسيمة اتفاقٌ مستمر على أجر الموظف، ومنها يُولّد النظام صرفة كل شهر. هذا التقرير يقرأ القسائم كما هي الآن، لا ما صُرف.',
            'blocks' => [[
                '«البدلات» و«الخصم الثابت» = البنود المكتوبة على القسيمة نفسها. أما الإضافي والتأمينات والمكافآت والسلف وخصميات الحضور فتُحسب يوم الصرف فلا تدخل هنا.',
                '«الصافي المتوقع» = الأساسي + البدلات − الخصم الثابت، بالطريقة نفسها التي يحسب بها الصرف.',
                'المؤشرات تجمع القسائم السارية فقط (المعتمدة والنشطة) بما يعادلها شهرياً؛ والموقوفة والمنتهية والمسودة تظهر في الجدول ولا تدخل المجاميع.',
                'ما صُرف فعلاً تجده في تقرير «صرف الرواتب».',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.payroll.slips_register') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'defaultEmployeeStatus' => ''])

                    <div class="rpt-field">
                        <label for="slip_status">حالة القسيمة</label>
                        <select name="slip_status" id="slip_status" class="form-control">
                            <option value="">كل الحالات</option>
                            @foreach ($slipStatuses as $key => $label)
                                <option value="{{ $key }}" @selected(request('slip_status') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="payroll_id">مسير الرواتب</label>
                        <select name="payroll_id" id="payroll_id" class="form-control rpt-select2">
                            <option value="">كل المسيرات</option>
                            <option value="none" @selected(request('payroll_id') === 'none')>قسائم بلا مسير</option>
                            @foreach ($sheetOptions as $sheet)
                                <option value="{{ $sheet->id }}" @selected((string) request('payroll_id') === (string) $sheet->id)>{{ $sheet->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="slips" class="btn {{ $isSlips ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-file-alt"></i> لكل قسيمة</button>
                            <button type="submit" name="view" value="by_department" class="btn {{ ! $isSlips ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-sitemap"></i> لكل قسم</button>
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.payroll.slips_register') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">القسائم السارية</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['live']) }}</bdi></div><div class="ag-kpi__sub">من {{ number_format($totals['slips']) }} قسيمة · {{ number_format($totals['employees']) }} موظف{{ $totals['no_sheet'] ? ' · '.$totals['no_sheet'].' بلا مسير' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-file-signature"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الأساسي الشهري</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['basic'], 2) }}</bdi></div><div class="ag-kpi__sub">للقسائم السارية</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-money-bill-wave"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">البدلات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['allowances'], 2) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['deductions'] > 0 ? 'خصم ثابت '.number_format($totals['deductions'], 2) : 'لا خصم ثابت' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-plus-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">الصافي الشهري المتوقع</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['net'], 2) }}</bdi></div><div class="ag-kpi__sub">قبل الإضافي والتأمينات والسلف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSlips ? 'القسائم' : 'القسائم لكل قسم' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSlips ? number_format($rows->total()).' قسيمة' : number_format($byDepartment->total()).' قسم' }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isSlips)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>الموظف</th><th>رقم القسيمة</th><th>المسير</th><th>الأساسي</th><th>البدلات</th>
                            <th>الخصم الثابت</th><th>الصافي المتوقع</th><th>الدورة</th><th>يوم الصرف</th><th>الحالة</th>
                            <th>البداية</th><th>النهاية</th><th>آخر صرف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.payroll.slips_register', array_merge(request()->except(['page']), ['employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    <div class="text-muted small">{{ $row['department'] }}</div>
                                </td>
                                <td class="rpt-num">
                                    @if ($canShowSlip)
                                        <a href="{{ route('salarySlip.show', $row['slip_id']) }}" target="_blank" rel="noopener">{{ $row['code'] }}</a>
                                    @else
                                        {{ $row['code'] }}
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['sheet'] }}</span></td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num {{ $row['allowances'] ? 'rpt-pos' : '' }}">{{ $money($row['allowances']) }}</td>
                                <td class="rpt-num {{ $row['deductions'] ? 'rpt-neg' : '' }}">{{ $money($row['deductions']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                                <td>{{ $row['frequency'] }}</td>
                                <td class="rpt-num">{{ $row['pay_day'] }}</td>
                                <td><span class="rpt-pill {{ $tone[$row['status']] ?? 'rpt-pill-secondary' }}">{{ $row['status_label'] }}</span></td>
                                <td class="rpt-num">{{ $row['start'] }}</td>
                                <td class="rpt-num">{{ $row['end'] }}</td>
                                <td class="rpt-num">
                                    @if ($row['last_run_date'])
                                        {{ $row['last_run_date'] }}
                                        <div class="small text-muted">{{ $row['last_run'] }} · {{ number_format($row['last_run_net'], 2) }}</div>
                                    @else
                                        <span class="text-muted">لم يُصرف بعد</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="rpt-empty"><i class="fas fa-inbox"></i> لا قسائم في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>القسم</th><th>الموظفون</th><th>القسائم</th><th>السارية</th>
                            <th>الأساسي الشهري</th><th>البدلات</th><th>الخصم الثابت</th><th>الصافي المتوقع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byDepartment as $row)
                            <tr>
                                <td>{{ $byDepartment->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($row['department_id'])
                                        <a href="{{ route('hr.reports.payroll.slips_register', array_merge(request()->except(['page', 'view']), ['view' => 'slips', 'department_id' => $row['department_id']])) }}">{{ $row['department'] }}</a>
                                    @else
                                        {{ $row['department'] }}
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $row['employees'] }}</td>
                                <td class="rpt-num">{{ $row['slips'] }}</td>
                                <td class="rpt-num">{{ $row['live'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $money($row['basic']) }}</td>
                                <td class="rpt-num {{ $row['allowances'] ? 'rpt-pos' : '' }}">{{ $money($row['allowances']) }}</td>
                                <td class="rpt-num {{ $row['deductions'] ? 'rpt-neg' : '' }}">{{ $money($row['deductions']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($row['net']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty"><i class="fas fa-inbox"></i> لا قسائم في هذا النطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @php $pager = $isSlips ? $rows : $byDepartment; @endphp
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
        'frTitle' => $isSlips ? 'قسائم الرواتب' : 'قسائم الرواتب — لكل قسم',
        'frInfo' => 'الوضع الحالي',
        'frHeaders' => $isSlips
            ? $slipHeads
            : ['#', 'القسم', 'الموظفون', 'القسائم', 'السارية', 'الأساسي الشهري', 'البدلات', 'الخصم الثابت', 'الصافي المتوقع'],
    ])
@endsection
