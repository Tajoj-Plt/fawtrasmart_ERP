@extends('hr::master')

@section('title')
    عرض دفتر الحضور
@stop

@php
    $sheetFrom = \Carbon\Carbon::parse($attendanceSheet->from_date);
    $sheetTo = \Carbon\Carbon::parse($attendanceSheet->to_date);
    $sheetByRules = $attendanceSheet->use_rules == 1;
@endphp

@section('page-actions')
    @permission('attendance_books.edit')
        <a href="{{ route('attendance_sheets.edit', $attendanceSheet->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endpermission
    @if (canAny(['attendance_books.destroy']))
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $attendanceSheet->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف الدفتر</span>
        </button>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('hr::components.table-menu')

    @include('stock::components.page-head', [
        'title' => 'دفتر الحضور #'.$attendanceSheet->id,
        'icon' => 'book-open',
        'crumbs' => ['الرئيسية' => url('/'), 'دفاتر الحضور' => route('attendance_sheets.index'), '#'.$attendanceSheet->id => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-book-open"></i></span>
                    <h4 class="mb-25">دفتر الحضور #{{ $attendanceSheet->id }}</h4>
                    <p class="stk-help mt-0 mb-1">
                        {{ $sheetFrom->locale('ar')->translatedFormat('d/m/Y') }} - {{ $sheetTo->locale('ar')->translatedFormat('d/m/Y') }}
                    </p>
                    <span class="stk-state stk-state--neutral">{{ $sheetByRules ? 'قائم على القواعد' : 'موظفين محددين' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        {{-- العددُ ضمن نطاق المستخدم: صاحبُ «دفاتر حضوره هو» لا يُعَدّ له زملاؤه --}}
                        <span class="stk-metric__label">الموظفون</span>
                        <span class="stk-metric__value">{{ $scopedEmployees->count() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">أيام الحضور المسجّلة</span>
                        <span class="stk-metric__value">{{ $summary['total'] }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>رقم الدفتر</dt>
                            <dd class="stk-num">#{{ $attendanceSheet->id }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>نوع الدفتر</dt>
                            <dd>
                                @if ($sheetByRules)
                                    <span class="badge badge-primary">قائم على القواعد</span>
                                @else
                                    <span class="badge badge-secondary">موظفين محددين</span>
                                @endif
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>من تاريخ</dt>
                            <dd>{{ $sheetFrom->locale('ar')->translatedFormat('l، d F Y') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>إلى تاريخ</dt>
                            <dd>{{ $sheetTo->locale('ar')->translatedFormat('l، d F Y') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإنشاء</dt>
                            <dd>{{ $attendanceSheet->created_at->locale('ar')->translatedFormat('l، d F Y - H:i') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>عدد الموظفين</dt>
                            <dd>{{ $scopedEmployees->count() }} موظف</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($sheetByRules)
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-sliders"></i> القواعد المطبقة</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الفرع</dt>
                                <dd>{{ $attendanceSheet->branch->name ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>القسم</dt>
                                <dd>{{ $attendanceSheet->department->name ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>المسمى الوظيفي</dt>
                                <dd>{{ $attendanceSheet->jobTitle->name ?? 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الوردية</dt>
                                <dd>{{ $attendanceSheet->shift->name ?? 'غير محدد' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════ الموظفون والأيام والإحصاءات والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab"
                                aria-controls="details" aria-selected="true">
                                <i class="feather icon-users"></i> الموظفون
                                <span class="stk-count">{{ $scopedEmployees->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="attendance-days-tab" data-toggle="tab" href="#attendance-days" role="tab"
                                aria-controls="attendance-days" aria-selected="false">
                                <i class="feather icon-calendar"></i> أيام الحضور
                                <span class="stk-count">{{ $summary['total'] }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="statistics-tab" data-toggle="tab" href="#statistics" role="tab"
                                aria-controls="statistics" aria-selected="false">
                                <i class="feather icon-bar-chart-2"></i> الإحصائيات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-log-tab" data-toggle="tab" href="#activity-log" role="tab"
                                aria-controls="activity-log" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    {{-- الموظفون في الدفتر --}}
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>القسم</th>
                                        <th>المسمى الوظيفي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($scopedEmployees as $employee)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar mr-2">
                                                        <span class="avatar-content">{{ substr($employee->full_name, 0, 2) }}</span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">{{ $employee->full_name }}</h6>
                                                        <small class="text-muted">{{ $employee->branch->name ?? 'غير محدد' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="stk-tag">{{ $employee->department->name ?? 'غير محدد' }}</span></td>
                                            <td><span class="stk-tag">{{ $employee->job_title->name ?? 'غير محدد' }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">لا يوجد موظفين في هذا الدفتر</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- أيام الحضور --}}
                    <div class="tab-pane fade" id="attendance-days" role="tabpanel" aria-labelledby="attendance-days-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">أيام الحضور التفصيلية</h6>
                            <div class="d-flex align-items-center" style="gap: .75rem">
                                <div class="spinner-border spinner-border-sm d-none" id="loading_spinner" role="status">
                                    <span class="sr-only">جاري التحميل...</span>
                                </div>
                                @if ($summary['total'] > 0)
                                    <label class="mb-0">
                                        عرض
                                        <select name="per_page" class="form-select form-select-sm d-inline-block w-auto" onchange="updatePerPage(this.value)">
                                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                            <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                                            <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                                            <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                                        </select>
                                        سجل
                                    </label>
                                @endif
                            </div>
                        </div>

                        @if ($summary['total'] > 0)
                            {{--
                                الأرقامُ من `$summary` — محسوبةً في المتحكّم على
                                الفترة كلِّها. وكانت تُحسَب هنا من `$attendanceDays`
                                وهي صفحةُ خمسين صفّاً من جدولٍ مُصفّى.
                            --}}
                            @php
                                $totalDays = $summary['total'];
                                $presentDays = $summary['present'];
                                $absentDays = $summary['absent'];
                                $leaveDays = $summary['leave'];
                                $lateDays = $summary['late'];
                                $totalWorkedHours = $summary['worked_minutes'] / 60;
                            @endphp

                            {{-- ما يُراجَع قبل الإغلاق: الدقائقُ التي يُحسَم عليها، وما حُسِم فعلاً --}}
                            <div class="stk-metrics" style="margin: 0; border-width: 0 0 1px; border-radius: 0; box-shadow: none">
                                <div class="stk-metric">
                                    <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                                    <span class="stk-metric__value">{{ $totalDays }}</span>
                                    <span class="stk-metric__label">إجمالي الأيام</span>
                                </div>
                                <div class="stk-metric stk-metric--success">
                                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                                    <span class="stk-metric__value">{{ $presentDays }}</span>
                                    <span class="stk-metric__label">أيام الحضور</span>
                                </div>
                                <div class="stk-metric stk-metric--danger">
                                    <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                                    <span class="stk-metric__value">{{ $absentDays }}</span>
                                    <span class="stk-metric__label">أيام الغياب</span>
                                </div>
                                <div class="stk-metric stk-metric--info">
                                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                                    <span class="stk-metric__value">{{ number_format($totalWorkedHours, 1) }}</span>
                                    <span class="stk-metric__label">إجمالي ساعات العمل</span>
                                </div>
                                <div class="stk-metric stk-metric--warning">
                                    <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                                    <span class="stk-metric__value">{{ number_format($summary['late_minutes']) }}</span>
                                    <span class="stk-metric__label">إجمالي دقائق التأخير</span>
                                </div>
                                <div class="stk-metric stk-metric--warning">
                                    <span class="stk-metric__icon"><i class="feather icon-log-out"></i></span>
                                    <span class="stk-metric__value">{{ number_format($summary['early_leave_minutes']) }}</span>
                                    <span class="stk-metric__label">دقائق الانصراف المبكر</span>
                                </div>
                                <div class="stk-metric stk-metric--success">
                                    <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                                    <span class="stk-metric__value">{{ number_format($summary['overtime_minutes']) }}</span>
                                    <span class="stk-metric__label">دقائق العمل الإضافي</span>
                                </div>
                                <div class="stk-metric stk-metric--danger">
                                    <span class="stk-metric__icon"><i class="feather icon-minus-circle"></i></span>
                                    <span class="stk-metric__value">{{ number_format($summary['deducted_amount'], 2) }}</span>
                                    <span class="stk-metric__label">إجمالي الخصميات ({{ $summary['deducted_days'] }} يوم)</span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 zero-configuration dataTable" id="attendance-days-table">
                                    <thead>
                                        <tr>
                                            <th>الموظف</th>
                                            <th>التاريخ</th>
                                            <th>اليوم</th>
                                            <th>الحالة</th>
                                            <th>وقت الدخول</th>
                                            <th>وقت الخروج</th>
                                            <th>ساعات العمل</th>
                                            <th>التأخير</th>
                                            <th style="width: 10%">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($attendanceDays as $day)
                                            @php
                                                $attendanceDate = \Carbon\Carbon::parse($day->attendance_date);
                                                $workedHours = 0;
                                                $lateMinutes = 0;

                                                if (
                                                    $day->status == 'present' &&
                                                    $day->check_in_time &&
                                                    $day->check_out_time
                                                ) {
                                                    $login = \Carbon\Carbon::parse($day->check_in_time);
                                                    $logout = \Carbon\Carbon::parse($day->check_out_time);
                                                    $workedHours = $login->diffInMinutes($logout) / 60;

                                                    // حساب التأخير
                                                    if ($day->start_shift) {
                                                        $shiftStart = \Carbon\Carbon::parse($day->start_shift);
                                                        if ($login->isAfter($shiftStart)) {
                                                            $lateMinutes = $shiftStart->diffInMinutes($login);
                                                        }
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="font-weight-bolder">{{ $day->employee->full_name }}</span>
                                                        <small class="stk-help mt-0">#{{ $day->employee->employee_number ?? $day->employee->id }}</small>
                                                    </div>
                                                </td>
                                                <td class="stk-num">{{ $attendanceDate->locale('ar')->translatedFormat('d/m/Y') }}</td>
                                                <td><span class="text-muted">{{ $attendanceDate->locale('ar')->translatedFormat('l') }}</span></td>
                                                <td>
                                                    @if ($day->status == 'present')
                                                        <span class="badge badge-success"><i class="feather icon-check me-1"></i> حاضر</span>
                                                    @elseif($day->status == 'absent')
                                                        <span class="badge badge-danger"><i class="feather icon-x me-1"></i> غياب</span>
                                                    @elseif($day->status == 'leave')
                                                        <span class="badge badge-secondary"><i class="feather icon-calendar me-1"></i> إجازة</span>
                                                    @elseif($day->status == 'late')
                                                        <span class="badge badge-warning"><i class="feather icon-clock me-1"></i> متأخر</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($day->check_in_time)
                                                        <span class="font-weight-bolder text-success">
                                                            <i class="feather icon-log-in me-1"></i>
                                                            {{ \Carbon\Carbon::parse($day->check_in_time)->format('H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($day->check_out_time)
                                                        <span class="font-weight-bolder text-danger">
                                                            <i class="feather icon-log-out me-1"></i>
                                                            {{ \Carbon\Carbon::parse($day->check_out_time)->format('H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($workedHours > 0)
                                                        <span class="badge badge-info">{{ number_format($workedHours, 1) }} ساعة</span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($lateMinutes > 0)
                                                        <span class="badge badge-warning"><i class="feather icon-clock me-1"></i> {{ $lateMinutes }} دقيقة</span>
                                                    @elseif($day->status == 'present')
                                                        <span class="badge badge-success"><i class="fa fa-thumbs-up me-1"></i> في الوقت</span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if (canAny(['attendance_days.show', 'attendance_corrections.create', 'attendance_days.edit']))
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            data-bs-toggle="dropdown" data-bs-display="static"
                                                            aria-expanded="false" aria-label="خيارات">
                                                            <i class="fa fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            @permission('attendance_days.show')
                                                                <a class="dropdown-item" href="{{ route('attendanceDays.show', $day->id) }}">
                                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                                </a>
                                                            @endpermission
                                                            @permission('attendance_corrections.create')
                                                                <a class="dropdown-item" href="{{ route('attendanceDays.edit', $day->id) }}">
                                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                                </a>
                                                            @endpermission
                                                            @permission('attendance_days.edit')
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger delete-btn"
                                                                    href="#"
                                                                    data-toggle="modal"
                                                                    data-target="#modal_DELETE_DAY{{ $day->id }}">
                                                                    <i class="fa fa-trash me-2"></i>حذف
                                                                </a>
                                                            @endpermission
                                                        </div>
                                                    </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{--
                                `links()` لا حلقةٌ على كل الصفحات: دفترُ شهرٍ
                                لعشرين موظفاً يبلغ اثنتي عشرة صفحة، ودفترُ سنةٍ
                                يبلغ مئةً وخمسين — وكانت كلُّها تُطبَع أرقاماً.
                            --}}
                            @if ($attendanceDays instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                                <div class="d-flex justify-content-between align-items-center flex-wrap p-2 gap-3">
                                    <nav aria-label="ترقيم الصفحات">
                                        {{ $attendanceDays->onEachSide(1)->links() }}
                                    </nav>

                                    <div class="pagination-info">
                                        صفحة {{ $attendanceDays->currentPage() }} من {{ $attendanceDays->lastPage() }}
                                        (عرض {{ $attendanceDays->firstItem() }} إلى {{ $attendanceDays->lastItem() }} من {{ $attendanceDays->total() }} سجل)
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                                    <h5 class="stk-empty__title">لا توجد أيام حضور لهذا الدفتر حتى الآن!</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- الإحصائيات --}}
                    <div class="tab-pane fade" id="statistics" role="tabpanel" aria-labelledby="statistics-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">إحصائيات الحضور</h6>
                        </div>
                        @if (! empty($employeeStats))
                            {{--
                                `$employeeStats` من المتحكّم، على الفترة كلِّها
                                وبكلِّ موظفي الدفتر — بمن لا يومَ له فيها، وهو
                                أوّلُ من يُبحَث عنه في مراجعة الالتزام.

                                ونسبتان لا واحدة: «الالتزام» يَعُدّ المتأخّرَ
                                حاضراً (فهو حضر)، و«الغياب» يَعُدّ الغيابَ وحدَه.
                                وكانت النسبةُ الواحدةُ تخلطهما فتُظهر من تأخّر
                                يوماً كمن غاب يوماً.
                            --}}
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الموظف</th>
                                            <th>إجمالي الأيام</th>
                                            <th>أيام الحضور</th>
                                            <th>أيام الغياب</th>
                                            <th>أيام الإجازة</th>
                                            <th>أيام التأخير</th>
                                            <th>دقائق التأخير</th>
                                            <th>دقائق الانصراف المبكر</th>
                                            <th>ساعات العمل</th>
                                            <th>نسبة الالتزام</th>
                                            <th>نسبة الغياب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($employeeStats as $stat)
                                            <tr>
                                                <td><strong>{{ $stat['name'] }}</strong></td>
                                                <td>{{ $stat['total'] }}</td>
                                                <td><span class="badge badge-success">{{ $stat['present'] }}</span></td>
                                                <td><span class="badge badge-danger">{{ $stat['absent'] }}</span></td>
                                                <td><span class="badge badge-secondary">{{ $stat['leave'] }}</span></td>
                                                <td><span class="badge badge-warning">{{ $stat['late'] }}</span></td>
                                                <td>{{ $stat['late_minutes'] ?: '—' }}</td>
                                                <td>{{ $stat['early_leave_minutes'] ?: '—' }}</td>
                                                <td>{{ number_format($stat['worked_minutes'] / 60, 1) }}</td>
                                                <td>
                                                    <span class="badge {{ $stat['attendance_rate'] >= 90 ? 'badge-success' : ($stat['attendance_rate'] >= 70 ? 'badge-warning' : 'badge-danger') }}">
                                                        {{ number_format($stat['attendance_rate'], 1) }}%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $stat['absence_rate'] <= 5 ? 'badge-success' : ($stat['absence_rate'] <= 15 ? 'badge-warning' : 'badge-danger') }}">
                                                        {{ number_format($stat['absence_rate'], 1) }}%
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></div>
                                    <h5 class="stk-empty__title">لا توجد بيانات لعرض الإحصائيات!</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- سجل النشاطات --}}
                    <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal delete attendance sheet -->
    <div class="modal fade" id="modal_DELETE{{ $attendanceSheet->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف دفتر الحضور</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <strong><i class="feather icon-alert-triangle"></i> تحذير!</strong>
                        <p>سيتم حذف دفتر الحضور وجميع أيام الحضور المرتبطة به
                            ({{ $attendanceSheet->attendanceDays->count() ?? 0 }} سجل).</p>
                        <p class="mb-0"><strong>هذا الإجراء لا يمكن التراجع عنه!</strong></p>
                    </div>
                    <p class="mb-0"><strong>هل أنت متأكد من أنك تريد الحذف؟</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                    @permission('attendance_books.destroy')
                        <form method="POST" action="{{ route('attendance_sheets.delete', $attendanceSheet->id) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="feather icon-trash-2"></i> تأكيد الحذف
                            </button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>

    <!-- Modal delete individual attendance days -->
    @if (isset($attendanceDays))
        @foreach ($attendanceDays as $day)
            <div class="modal fade" id="modal_DELETE_DAY{{ $day->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">حذف يوم الحضور</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>الموظف:</strong> {{ $day->employee->full_name }}</p>
                            <p><strong>التاريخ:</strong>
                                {{ \Carbon\Carbon::parse($day->attendance_date)->locale('ar')->translatedFormat('l، d F Y') }}
                            </p>
                            <hr>
                            <strong>هل أنت متأكد من أنك تريد حذف سجل هذا اليوم؟</strong>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                            @permission('attendance_days.edit')
                                <form method="POST" action="{{ route('attendanceDays.delete', $day->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="feather icon-trash-2"></i> تأكيد الحذف
                                    </button>
                                </form>
                            @endpermission
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let currentFilters = {
                employee_filter: '',
                status_filter: ''
            };

            // تحميل البيانات الأولية
            loadAttendanceDays();

            // التعامل مع الفلاتر
            $('#employee_filter, #status_filter').on('change', function() {
                currentFilters = {
                    employee_filter: $('#employee_filter').val(),
                    status_filter: $('#status_filter').val()
                };
                currentPage = 1; // إعادة تعيين الصفحة للأولى
                loadAttendanceDays();
            });

            // إعادة تعيين الفلاتر
            $('#reset_filters').on('click', function() {
                $('#employee_filter').val('');
                $('#status_filter').val('');
                currentFilters = {
                    employee_filter: '',
                    status_filter: ''
                };
                currentPage = 1;
                loadAttendanceDays();
            });

            // دالة تحميل أيام الحضور
            function loadAttendanceDays(page = 1) {
                // إظهار loading
                $('#loading_spinner').removeClass('d-none');

                $.ajax({
                    url: '',
                    method: 'GET',
                    data: {
                        employee_filter: currentFilters.employee_filter,
                        status_filter: currentFilters.status_filter,
                        page: page
                    },
                    success: function(response) {
                        // تحديث الإحصائيات
                        $('#statistics_cards').html(response.statistics);

                        // تحديث الجدول
                        $('#attendance_days_table').html(response.table);

                        // تحديث pagination
                        $('#pagination_container').html(response.pagination);

                        // إضافة tooltips للأزرار الجديدة
                        $('[title]').tooltip();

                        // إخفاء loading
                        $('#loading_spinner').addClass('d-none');

                        // إعادة ربط أحداث الحذف للعناصر الجديدة
                        bindDeleteEvents();

                        // إعادة ربط أحداث pagination
                        bindPaginationEvents();
                    },
                    error: function(xhr) {
                        $('#loading_spinner').addClass('d-none');
                        console.error('خطأ في تحميل البيانات:', xhr);

                        // عرض رسالة خطأ
                        $('#attendance_days_table').html(
                            '<tr><td colspan="9" class="text-center text-danger">' +
                            '<i class="feather icon-alert-triangle"></i> حدث خطأ في تحميل البيانات' +
                            '</td></tr>'
                        );
                    }
                });
            }

            // ربط أحداث الحذف
            function bindDeleteEvents() {
                $('.delete-attendance-day').off('click').on('click', function(e) {
                    e.preventDefault();

                    const dayId = $(this).data('day-id');
                    const employeeName = $(this).data('employee-name');
                    const attendanceDate = $(this).data('attendance-date');

                    Swal.fire({
                        title: 'حذف يوم الحضور',
                        html: `
                            <p><strong>الموظف:</strong> ${employeeName}</p>
                            <p><strong>التاريخ:</strong> ${attendanceDate}</p>
                            <hr>
                            <strong>هل أنت متأكد من أنك تريد حذف سجل هذا اليوم؟</strong>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            deleteAttendanceDay(dayId);
                        }
                    });
                });
            }

            // ربط أحداث pagination
            function bindPaginationEvents() {
                $(document).on('click', '.pagination a', function(e) {
                    e.preventDefault();
                    const url = $(this).attr('href');
                    const page = new URL(url).searchParams.get('page');
                    if (page) {
                        currentPage = page;
                        loadAttendanceDays(page);
                    }
                });
            }

            // حذف يوم الحضور
            function deleteAttendanceDay(dayId) {
                Swal.fire({
                    title: 'جاري الحذف...',
                    text: 'يرجى الانتظار',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: `{{ route('attendanceDays.delete', ':id') }}`.replace(':id', dayId),
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            title: 'تم الحذف!',
                            text: 'تم حذف سجل الحضور بنجاح',
                            icon: 'success',
                            timer: 2000,
                            timerProgressBar: true
                        });

                        // إعادة تحميل البيانات
                        loadAttendanceDays(currentPage);
                    },
                    error: function(xhr) {
                        let errorMessage = 'حدث خطأ أثناء الحذف';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        Swal.fire({
                            title: 'خطأ!',
                            text: errorMessage,
                            icon: 'error'
                        });
                    }
                });
            }

            // التعامل مع التبويبات وحفظ الحالة في URL
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var targetTab = $(e.target).attr("href");
                var url = window.location.href;
                var newUrl = url.split('#')[0] + targetTab;
                window.history.replaceState(null, null, newUrl);

                // إذا تم تفعيل تبويبة أيام الحضور، تأكد من تحميل البيانات
                if (targetTab === '#attendance-days') {
                    setTimeout(function() {
                        if ($('#attendance_days_table').children().length === 0) {
                            loadAttendanceDays();
                        }
                    }, 100);
                }
            });

            // تفعيل التبويبة من URL إذا كانت موجودة
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }

            // إذا كان هناك معامل tab في URL
            var urlParams = new URLSearchParams(window.location.search);
            var tabParam = urlParams.get('tab');
            if (tabParam) {
                $('.nav-tabs a[href="#' + tabParam + '"]').tab('show');
            }

            // إضافة تأثيرات للجداول
            $(document).on('mouseenter', '.table-hover tbody tr', function() {
                $(this).addClass('table-active');
            }).on('mouseleave', '.table-hover tbody tr', function() {
                $(this).removeClass('table-active');
            });

            // تأكيد إضافي للحذف
            $('.btn-outline-danger').on('click', function(e) {
                var modalTarget = $(this).data('target');
                if (!modalTarget && !$(this).hasClass('delete-attendance-day')) {
                    e.preventDefault();
                    var result = confirm('هل أنت متأكد من أنك تريد المتابعة؟');
                    if (!result) {
                        return false;
                    }
                }
            });

            // تحسين عرض الإحصائيات
            $(document).on('mouseenter',
                '.card.bg-primary, .card.bg-success, .card.bg-danger, .card.bg-secondary, .card.bg-warning, .card.bg-info',
                function() {
                    $(this).addClass('shadow-lg');
                }).on('mouseleave',
                '.card.bg-primary, .card.bg-success, .card.bg-danger, .card.bg-secondary, .card.bg-warning, .card.bg-info',
                function() {
                    $(this).removeClass('shadow-lg');
                });

            // إضافة loading للأزرار عند النقر
            $('.btn').on('click', function() {
                var $btn = $(this);
                if (!$btn.hasClass('btn-outline-danger') && !$btn.hasClass('delete-attendance-day')) {
                    $btn.addClass('disabled').append(' <i class="fa fa-spinner fa-spin"></i>');
                }
            });

            // إضافة SweetAlert2 إذا لم يكن موجوداً
            if (typeof Swal === 'undefined') {
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                document.head.appendChild(script);
            }
        });
    
function updatePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}


    </script>
@endsection
