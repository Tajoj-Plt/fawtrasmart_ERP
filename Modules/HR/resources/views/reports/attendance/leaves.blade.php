{{--
    تقرير الإجازات والأذونات — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `Reports\LeaveReportController::index`. ثلاثةُ عروض: طلباتُ الإجازة،
    ولكلّ موظف (مع الرصيد المتبقّي)، والأذونات.
    التصديرُ والطباعةُ للعرض الحاليّ كلِّه (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    تقرير الإجازات والأذونات
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $viewTitles = ['requests' => 'طلبات الإجازة', 'employees' => 'لكل موظف', 'permissions' => 'الأذونات'];
    $viewIcons = ['requests' => 'plane-departure', 'employees' => 'users', 'permissions' => 'door-open'];
    $pager = match ($reportView) { 'employees' => $perEmployee, 'permissions' => $permissionRows, default => $requestRows };
    $unit = match ($reportView) { 'employees' => 'موظف', 'permissions' => 'إذن', default => 'طلب' };
    $tone = ['approved' => 'rpt-pill-success', 'pending' => 'rpt-pill-warning', 'rejected' => 'rpt-pill-danger', 'cancelled' => 'rpt-pill-secondary'];
    $canShowLeave = Route::has('attendance.leave_requests.show') && hasPermission('leave_requests.show');
    $canShowPermission = Route::has('leave_permissions.show') && hasPermission('leave_permissions.show');
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'plane-departure',
        'headTitle' => 'تقرير الإجازات والأذونات',
        'headSubtitle' => 'طلبات الإجازة وحالاتها، وأذونات الوصول المتأخر والانصراف المبكر، ورصيد الإجازات المتبقي لكل موظف',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.leaves',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'يظهر الطلب إذا وقع أي يوم منه داخل الفترة — والفترة تشمل الأيام القادمة، فترى الإجازات المعتمدة قبل وقوعها.',
            'blocks' => [[
                '«أيام الإجازة المعتمدة» = أيام الطلبات الموافق عليها التي تقع داخل الفترة فقط؛ الطلب الممتد خارجها يُحسب منه ما يقع داخلها.',
                'الطلبات المعلّقة والمرفوضة تظهر في الجدول، لكنها لا تدخل في أيام الإجازة المعتمدة.',
                'الأذونات (وصول متأخر / انصراف مبكر) لا تحتاج اعتماداً: الإذن المسجّل يُعفي الموظف من خصم التأخير أو الانصراف المبكر في يومه.',
                '«الرصيد المتبقي» من أرصدة الإجازات لسنة '.$balanceYear.' = الرصيد الأساسي + المرحّل + الإضافي − المستخدم.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.leaves') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['defaultEmployeeStatus' => ''])

                    @if ($reportView === 'permissions')
                        <div class="rpt-field">
                            <label for="permission_kind">نوع الإذن</label>
                            <select name="permission_kind" id="permission_kind" class="form-control">
                                <option value="">كل الأذونات</option>
                                @foreach ($permissionKinds as $key => $label)
                                    <option value="{{ $key }}" @selected((string) request('permission_kind') === (string) $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="rpt-field">
                            <label for="leave_type_id">نوع الإجازة</label>
                            <select name="leave_type_id" id="leave_type_id" class="form-control rpt-select2">
                                <option value="">كل الأنواع</option>
                                @foreach ($leaveTypes as $item)
                                    <option value="{{ $item->id }}" @selected((string) request('leave_type_id') === (string) $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            <label for="status">حالة الطلب</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">كل الحالات</option>
                                @foreach ($statusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            @foreach ($viewTitles as $key => $label)
                                <button type="submit" name="view" value="{{ $key }}" class="btn {{ $reportView === $key ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-{{ $viewIcons[$key] }}"></i> {{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.leaves') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">أيام الإجازة المعتمدة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['approved_days']) }}</bdi></div><div class="ag-kpi__sub">يوم داخل الفترة · {{ number_format($totals['approved_count']) }} طلب</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-calendar-check"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">طلبات بانتظار الاعتماد</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['pending']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['pending_days']) }} يوم مطلوب</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الأذونات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['permissions']) }}</bdi></div><div class="ag-kpi__sub">وصول متأخر {{ number_format($totals['late_permissions']) }} · انصراف مبكر {{ number_format($totals['early_permissions']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-door-open"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">في إجازة اليوم</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['on_leave_today']) }}</bdi></div><div class="ag-kpi__sub">موظف بإجازة معتمدة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-umbrella-beach"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $viewTitles[$reportView] }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $unit }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($reportView === 'employees')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>الموظف</th><th>القسم</th><th>أيام معتمدة</th><th>حسب النوع</th><th>معلّقة</th><th>مرفوضة</th><th>إذن وصول متأخر</th><th>إذن انصراف مبكر</th><th>الرصيد المتبقي {{ $balanceYear }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($perEmployee as $row)
                            <tr>
                                <td>{{ $perEmployee->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.leaves', array_merge(request()->except(['page', 'view']), ['view' => 'requests', 'employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    <div class="text-muted small">{{ $row['job_title'] }}</div>
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td class="rpt-num"><strong>{{ $row['approved_days'] ?: '—' }}</strong></td>
                                <td class="small">{{ $row['approved_by_type'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['pending'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['rejected'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['late_permissions'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['early_permissions'] ?: '—' }}</td>
                                <td class="small">
                                    @forelse ($row['balances'] as $bal)
                                        <div>{{ $bal['type'] }}: <strong class="{{ $bal['remaining'] > 0 ? 'rpt-pos' : 'rpt-neg' }}">{{ $bal['remaining'] }}</strong> من {{ $bal['total'] }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا إجازات ولا أذونات ولا أرصدة في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($reportView === 'permissions')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>الموظف</th><th>القسم</th><th>نوع الإذن</th><th>التصنيف</th><th>من</th><th>إلى</th><th>تاريخ التقديم</th><th>ملاحظات</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($permissionRows as $row)
                            <tr>
                                <td>{{ $permissionRows->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.leaves', array_merge(request()->except(['page']), ['employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td>
                                    @if ($canShowPermission)
                                        <a href="{{ route('leave_permissions.show', $row['id']) }}" target="_blank" rel="noopener">{{ $row['kind_label'] }}</a>
                                    @else
                                        {{ $row['kind_label'] }}
                                    @endif
                                </td>
                                <td>{{ $row['type_label'] }}</td>
                                <td class="rpt-num">{{ $row['start'] }}</td>
                                <td class="rpt-num">{{ $row['end'] }}</td>
                                <td class="rpt-num">{{ $row['submitted'] }}</td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($row['notes'], 80) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty"><i class="fas fa-inbox"></i> لا أذونات في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>الموظف</th><th>القسم</th><th>نوع الإجازة</th><th>من</th><th>إلى</th><th>الأيام</th><th>منها في الفترة</th><th>الحالة</th><th>تاريخ الطلب</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($requestRows as $row)
                            <tr>
                                <td>{{ $requestRows->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.leaves', array_merge(request()->except(['page', 'view']), ['view' => 'employees', 'employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td>
                                    @if ($canShowLeave)
                                        <a href="{{ route('attendance.leave_requests.show', $row['id']) }}" target="_blank" rel="noopener">{{ $row['leave_type'] }}</a>
                                    @else
                                        {{ $row['leave_type'] }}
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $row['start'] }}</td>
                                <td class="rpt-num">{{ $row['end'] }}</td>
                                <td class="rpt-num"><strong>{{ $row['days'] }}</strong></td>
                                <td class="rpt-num">{{ $row['in_period'] }}</td>
                                <td><span class="rpt-pill {{ $tone[$row['status']] ?? 'rpt-pill-secondary' }}">{{ $row['status_label'] }}</span></td>
                                <td class="rpt-num">{{ $row['requested_at'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا طلبات إجازة في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
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
        'frTitle' => 'تقرير الإجازات والأذونات — '.$viewTitles[$reportView],
        'frInfo' => $periodLabel,
        'frHeaders' => match ($reportView) {
            'employees' => ['#', 'الموظف', 'القسم', 'أيام معتمدة', 'حسب النوع', 'معلّقة', 'مرفوضة', 'إذن وصول متأخر', 'إذن انصراف مبكر', 'الرصيد المتبقي '.$balanceYear],
            'permissions' => ['#', 'الموظف', 'القسم', 'نوع الإذن', 'التصنيف', 'من', 'إلى', 'تاريخ التقديم', 'ملاحظات'],
            default => ['#', 'الموظف', 'القسم', 'نوع الإجازة', 'من', 'إلى', 'الأيام', 'منها في الفترة', 'الحالة', 'تاريخ الطلب'],
        },
    ])
@endsection
