@extends('hr::master')

@section('title')
    عرض ايام الحضور
@stop

@php
    $employeeName = $attendance_day->employee->full_name ?? 'موظف محذوف';
    $isPresent = $attendance_day->status == 'present';

    // مدّتا اليوم (المتوقّعة من الوردية، والفعلية من الدخول والخروج) — لليوم الحاضر وحده
    $totalDuration = null;
    $totalDurationDefault = null;
    if ($isPresent) {
        $loginTime = \Carbon\Carbon::parse($attendance_day->check_in_time);
        $logoutTime = \Carbon\Carbon::parse($attendance_day->check_out_time);
        $StartShift = \Carbon\Carbon::parse($attendance_day->start_shift);
        $EndShift = \Carbon\Carbon::parse($attendance_day->end_shift);
        $totalDuration = $loginTime->diff($logoutTime);
        $totalDurationDefault = $StartShift->diff($EndShift);
    }
@endphp

@section('page-actions')
    @permission('attendance_corrections.create')
        <a href="{{ route('attendanceDays.edit', $attendance_day->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">طلب تصحيح</span>
        </a>
    @endpermission
    @permission('attendance_days.edit')
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $attendance_day->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('hr::components.table-menu')

    @include('stock::components.page-head', [
        'title' => $employeeName,
        'icon' => 'calendar',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أيام الحضور' => route('attendanceDays.index'),
            \Carbon\Carbon::parse($attendance_day->attendance_date)->locale('ar')->translatedFormat('l, d/m/Y') => null,
        ],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-calendar"></i></span>
                    <h4 class="mb-25">{{ $employeeName }}</h4>
                    <p class="stk-help mt-0 mb-1">{{ \Carbon\Carbon::parse($attendance_day->attendance_date)->locale('ar')->translatedFormat('l, d/m/Y') }}</p>
                    @if ($isPresent)
                        <span class="stk-state stk-state--on">حاضر</span>
                    @elseif ($attendance_day->status == 'absent')
                        <span class="stk-state stk-state--neutral">يوم اجازة</span>
                    @else
                        <span class="stk-state stk-state--off">غياب</span>
                    @endif
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">ساعات العمل الفعلية</span>
                        <span class="stk-metric__value">{{ $totalDuration ? $totalDuration->h.':'.str_pad($totalDuration->i, 2, '0', STR_PAD_LEFT) : '—' }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحركات المسجّلة</span>
                        <span class="stk-metric__value">{{ isset($dayPunches) ? $dayPunches->count() : 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>اسم الموظف</dt>
                            <dd>{{ $employeeName }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الرقم التعريفي للموظف</dt>
                            <dd class="stk-num">#{{ $attendance_day->employee->id ?? $attendance_day->employee_id }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>رقم دفتر الحضور</dt>
                            <dd class="stk-num">#{{ $attendance_day->id }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الحالة</dt>
                            <dd>
                                @if ($isPresent)
                                    <span class="mr-1 bullet bullet-success bullet-sm"></span>حاضر
                                @elseif($attendance_day->status == 'absent')
                                    <span class="mr-1 bullet bullet-secondary bullet-sm"></span>يوم اجازة (No Shift)
                                @else
                                    <span class="mr-1 bullet bullet-danger bullet-sm"></span>غياب
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if ($isPresent)
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-clock"></i> بيانات الوردية والحضور</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>بداية الوردية</dt>
                                <dd class="stk-num">{{ $attendance_day->start_shift }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>نهاية الوردية</dt>
                                <dd class="stk-num">{{ $attendance_day->end_shift }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تسجيل الدخول</dt>
                                <dd class="stk-num">{{ $attendance_day->check_in_time }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تسجيل الخروج</dt>
                                <dd class="stk-num">{{ $attendance_day->check_out_time }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>موقع الحضور</dt>
                                <dd>
                                    @if($attendance_day->checkin_latitude !== null && $attendance_day->checkin_longitude !== null)
                                        <a href="https://www.google.com/maps?q={{ $attendance_day->checkin_latitude }},{{ $attendance_day->checkin_longitude }}"
                                           target="_blank" rel="noopener">
                                            {{ number_format((float) $attendance_day->checkin_latitude, 6) }},
                                            {{ number_format((float) $attendance_day->checkin_longitude, 6) }}
                                        </a>
                                        @if($attendance_day->checkInGeofence)
                                            <span class="stk-help mt-0">{{ $attendance_day->checkInGeofence->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>موقع الانصراف</dt>
                                <dd>
                                    @if($attendance_day->checkout_latitude !== null && $attendance_day->checkout_longitude !== null)
                                        <a href="https://www.google.com/maps?q={{ $attendance_day->checkout_latitude }},{{ $attendance_day->checkout_longitude }}"
                                           target="_blank" rel="noopener">
                                            {{ number_format((float) $attendance_day->checkout_latitude, 6) }},
                                            {{ number_format((float) $attendance_day->checkout_longitude, 6) }}
                                        </a>
                                        @if($attendance_day->checkOutGeofence)
                                            <span class="stk-help mt-0">{{ $attendance_day->checkOutGeofence->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>ساعات العمل المتوقعة</dt>
                                <dd>{{ $totalDurationDefault->h }} ساعة و {{ $totalDurationDefault->i }} دقيقة</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>ساعات العمل الفعلية</dt>
                                <dd>{{ $totalDuration->h }} ساعة و {{ $totalDuration->i }} دقيقة</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════ الحركات والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab"
                                aria-controls="details" aria-selected="true">
                                <i class="feather icon-map-pin"></i> حركات اليوم
                                <span class="stk-count">{{ isset($dayPunches) ? $dayPunches->count() : 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="attendance-record-tab" data-toggle="tab" href="#attendance-record" role="tab"
                                aria-controls="attendance-record" aria-selected="false">
                                <i class="feather icon-list"></i> سجل الحضور
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
                    {{--
                        حركاتُ اليوم من سجل الأختام.

                        كان هذا القسمُ يقرأ «الجلسات» من
                        `attendance_records` — طبقةٌ وسطى لم
                        تعد تُكتب — فيقول «لا توجد جلسات
                        مسجلة» على كل يومٍ مهما امتلأ.
                    --}}
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        @if(isset($dayPunches) && $dayPunches->count() > 0)
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">الحركات المسجّلة لهذا اليوم</h6>
                                <a href="{{ route('hr.attendance-punches.index', ['employee_id' => $attendance_day->employee_id, 'date_from' => $attendance_day->attendance_date->toDateString(), 'date_to' => $attendance_day->attendance_date->toDateString()]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    عرضها في سجلات الحضور
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>الوقت</th>
                                            <th>النوع</th>
                                            <th>المدخل / الوسيلة</th>
                                            <th>الجهاز</th>
                                            <th>الموقع</th>
                                            <th>النتيجة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dayPunches as $punch)
                                            <tr>
                                                <td class="stk-num">{{ $punch->punched_at->format('H:i:s') }}</td>
                                                <td>{{ $punch->punch_type === 'in' ? 'حضور' : 'انصراف' }}</td>
                                                <td>{{ $punch->channelLabel() }} · {{ $punch->methodLabel() }}</td>
                                                <td>{{ $punch->kiosk?->name ?? '--' }}</td>
                                                <td>
                                                    @if($punch->mapsUrl())
                                                        <a href="{{ $punch->mapsUrl() }}" target="_blank" rel="noopener">
                                                            {{ $punch->locationDisplay() }}
                                                        </a>
                                                    @else
                                                        {{ $punch->locationDisplay() }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($punch->result === 'accepted')
                                                        <span class="badge badge-success">مقبول</span>
                                                    @else
                                                        <span class="badge badge-danger">مردود</span>
                                                        <div class="text-muted" style="font-size:.72rem">{{ $punch->rejection_reason }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-map-pin"></i></div>
                                    <h5 class="stk-empty__title">لا توجد حركات مسجّلة لهذا اليوم.</h5>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- سجل الحضور الشامل --}}
                    <div class="tab-pane fade" id="attendance-record" role="tabpanel" aria-labelledby="attendance-record-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">سجل الحضور الشامل</h6>
                            <form method="GET" class="form-inline" style="gap: .5rem">
                                <div class="form-group">
                                    <label for="filter_month" class="mr-1">الشهر:</label>
                                    <select name="filter_month" id="filter_month" class="form-control form-control-sm">
                                        <option value="">جميع الأشهر</option>
                                        @for($i = 1; $i <= 12; $i++)
                                            <option value="{{ $i }}" {{ request('filter_month') == $i ? 'selected' : '' }}>
                                                {{ \Carbon\Carbon::create()->month($i)->locale('ar')->translatedFormat('F') }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="filter_year" class="mr-1">السنة:</label>
                                    <select name="filter_year" id="filter_year" class="form-control form-control-sm">
                                        @for($year = date('Y'); $year >= date('Y') - 5; $year--)
                                            <option value="{{ $year }}" {{ request('filter_year', date('Y')) == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">تصفية</button>
                            </form>
                        </div>

                        @if(isset($attendanceRecords) && count($attendanceRecords) > 0)
                            @php
                                $totalDays = count($attendanceRecords);
                                $presentDays = $attendanceRecords->where('status', 'present')->count();
                                $absentDays = $attendanceRecords->where('status', 'absent')->count();
                                $leaveDays = $attendanceRecords->where('status', 'leave')->count();
                                $totalWorkedHours = 0;

                                foreach($attendanceRecords->where('status', 'present') as $record) {
                                    if($record->check_in_time && $record->check_out_time) {
                                        $login = \Carbon\Carbon::parse($record->check_in_time);
                                        $logout = \Carbon\Carbon::parse($record->check_out_time);
                                        $totalWorkedHours += $login->diffInHours($logout, false);
                                    }
                                }
                            @endphp

                            <div class="stk-metrics" style="margin: 0; border-width: 0 0 1px; border-radius: 0; box-shadow: none">
                                <div class="stk-metric stk-metric--success">
                                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                                    <span class="stk-metric__value">{{ $presentDays }}</span>
                                    <span class="stk-metric__label">ايام الحضور</span>
                                </div>
                                <div class="stk-metric stk-metric--danger">
                                    <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                                    <span class="stk-metric__value">{{ $absentDays }}</span>
                                    <span class="stk-metric__label">ايام الغياب</span>
                                </div>
                                <div class="stk-metric stk-metric--info">
                                    <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                                    <span class="stk-metric__value">{{ $leaveDays }}</span>
                                    <span class="stk-metric__label">ايام الإجازة</span>
                                </div>
                                <div class="stk-metric">
                                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                                    <span class="stk-metric__value">{{ number_format($totalWorkedHours, 1) }}</span>
                                    <span class="stk-metric__label">اجمالي ساعات العمل</span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 zero-configuration dataTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>اليوم</th>
                                            <th>الحالة</th>
                                            <th>وقت الدخول</th>
                                            <th>وقت الخروج</th>
                                            <th>ساعات العمل</th>
                                            <th>التأخير</th>
                                            <th>ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attendanceRecords as $record)
                                            @php
                                                $attendanceDate = \Carbon\Carbon::parse($record->attendance_date);
                                                $workedHours = 0;
                                                $lateMinutes = 0;

                                                if($record->status == 'present' && $record->check_in_time && $record->check_out_time) {
                                                    $login = \Carbon\Carbon::parse($record->check_in_time);
                                                    $logout = \Carbon\Carbon::parse($record->check_out_time);
                                                    $workedHours = $login->diffInMinutes($logout) / 60;

                                                    /*
                                                        التأخيرُ المحسوبُ في المحرّك أولاً (`late_minutes`) — هو
                                                        ما يعرضه «الحضور اليومي» وما يُبنى عليه الخصم، فلا يُعرض
                                                        للمستخدم رقمان مختلفان لليوم نفسِه.

                                                        والحسابُ المحليُّ يبقى احتياطاً لصفوفٍ قديمةٍ لم يكتب
                                                        المحرّكُ فيها شيئاً — وعلى حدود الدقيقة لا بقصِّ الثواني:
                                                        `diffInMinutes` تُرجع عشريّاً في Carbon 3، فختمٌ 09:00:50
                                                        على وردية 08:00:00 كان يُقرأ 59 دقيقة.
                                                    */
                                                    if($record->late_minutes !== null && (int) $record->late_minutes > 0) {
                                                        $lateMinutes = (int) $record->late_minutes;
                                                    } elseif($record->start_shift) {
                                                        $shiftStart = \Carbon\Carbon::parse($record->start_shift);
                                                        if($login->isAfter($shiftStart)) {
                                                            $lateMinutes = max(0, intdiv($login->getTimestamp() - $login->second, 60)
                                                                - intdiv($shiftStart->getTimestamp() - $shiftStart->second, 60));
                                                        }
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ $attendanceDate->locale('ar')->translatedFormat('d/m/Y') }}</strong>
                                                        <small class="stk-help">#{{ $record->id }}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="font-weight-bolder">{{ $attendanceDate->locale('ar')->translatedFormat('l') }}</span>
                                                </td>
                                                <td>
                                                    @if ($record->status == 'present')
                                                        <span class="stk-tag"><i class="feather icon-check me-1"></i> حاضر</span>
                                                    @elseif($record->status == 'leave')
                                                        <span class="stk-tag"><i class="feather icon-calendar me-1"></i> إجازة</span>
                                                    @else
                                                        <span class="stk-tag"><i class="feather icon-x me-1"></i> غياب</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($record->check_in_time)
                                                        <span class="font-weight-bolder text-success">
                                                            <i class="feather icon-log-in me-1"></i>
                                                            {{ \Carbon\Carbon::parse($record->check_in_time)->format('H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($record->check_out_time)
                                                        <span class="font-weight-bolder text-danger">
                                                            <i class="feather icon-log-out me-1"></i>
                                                            {{ \Carbon\Carbon::parse($record->check_out_time)->format('H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($workedHours > 0)
                                                        <span class="stk-tag"><i class="feather icon-clock me-1"></i> {{ number_format($workedHours, 1) }} ساعة</span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($lateMinutes > 0)
                                                        {{-- «ساعة و10 دقائق» لا «70» — نفس صيغة «الحضور اليومي» --}}
                                                        <span class="stk-tag"><i class="feather icon-alert-triangle me-1"></i> {{ \App\Support\DurationLabel::minutes($lateMinutes) }}</span>
                                                    @elseif($record->status == 'present')
                                                        <span class="stk-tag"><i class="fa fa-thumbs-up me-1"></i> في الوقت</span>
                                                    @else
                                                        <span class="text-muted">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($record->notes)
                                                        <small class="stk-help mt-0">{{ Str::limit($record->notes, 50) }}</small>
                                                    @else
                                                        <span class="text-muted">لا توجد ملاحظات</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($attendanceRecords, 'links'))
                                <div class="d-flex justify-content-between align-items-center p-2 gap-3">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination mb-0">
                                            @if ($attendanceRecords->onFirstPage())
                                                <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                                            @else
                                                <li class="page-item"><a class="page-link" href="{{ $attendanceRecords->previousPageUrl() }}">&laquo;</a></li>
                                            @endif
                                            @for ($i = 1; $i <= $attendanceRecords->lastPage(); $i++)
                                                <li class="page-item {{ $attendanceRecords->currentPage() == $i ? 'active' : '' }}">
                                                    <a class="page-link" href="{{ $attendanceRecords->url($i) }}">{{ $i }}</a>
                                                </li>
                                            @endfor
                                            @if ($attendanceRecords->hasMorePages())
                                                <li class="page-item"><a class="page-link" href="{{ $attendanceRecords->nextPageUrl() }}">&raquo;</a></li>
                                            @else
                                                <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                                            @endif
                                        </ul>
                                    </nav>

                                    <div class="pagination-info">
                                        صفحة {{ $attendanceRecords->currentPage() }} من {{ $attendanceRecords->lastPage() }}
                                        (عرض {{ $attendanceRecords->firstItem() }} إلى {{ $attendanceRecords->lastItem() }} من {{ $attendanceRecords->total() }} سجل)
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-info"></i></div>
                                    <h5 class="stk-empty__title">لا توجد سجلات حضور لهذا الموظف حتى الآن!</h5>
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

    <!-- Modal delete -->
    <div class="modal fade" id="modal_DELETE{{ $attendance_day->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف يوم الحضور</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">هل انت متاكد من انك تريد الحذف ؟</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">الغاء</button>
                    @permission('attendance_days.edit')
                        <form method="POST" action="{{ route('attendanceDays.delete', $attendance_day->id) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد</button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
