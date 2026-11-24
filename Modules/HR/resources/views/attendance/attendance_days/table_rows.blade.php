{{--
    صفوفُ جدول أيام الحضور — تُعرض في التحميل الأول، ويُعيدها
    `attendanceDays.filter` نصّاً يُوضع في `#attendance-table-body`.
--}}
@php
    $dayStatusMap = [
        'present' => ['حاضر', 'badge-light-success'],
        'absent' => ['غائب', 'badge-light-danger'],
        'late' => ['متأخر', 'badge-light-warning'],
        'half_day' => ['نصف يوم', 'badge-light-info'],
        'leave' => ['إجازة', 'badge-light-primary'],
        'holiday' => ['عطلة', 'badge-secondary'],
    ];
@endphp

@if (isset($attendance_days) && ! empty($attendance_days) && $attendance_days->count() > 0)
    @foreach ($attendance_days as $index => $attendance_day)
        @php [$statusLabel, $statusClass] = $dayStatusMap[$attendance_day->status] ?? [$attendance_day->status ?: '—', 'badge-secondary']; @endphp
        <tr>
            <td class="stk-num text-muted">{{ $attendance_days->firstItem() + $index }}</td>
            <td>
                <div class="stk-media">
                    <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                    <span class="stk-media__body">
                        @if ($attendance_day->employee)
                            @permission('attendance_days.show')
                                <a href="{{ route('attendanceDays.show', $attendance_day->id) }}" class="stk-media__title">{{ $attendance_day->employee->full_name }}</a>
                            @else
                                <span class="stk-media__title">{{ $attendance_day->employee->full_name }}</span>
                            @endpermission
                            <span class="stk-media__sub">#{{ $attendance_day->employee->id }}{{ $attendance_day->employee->department ? ' · '.$attendance_day->employee->department->name : '' }}</span>
                        @else
                            <span class="stk-media__title text-muted">موظف محذوف</span>
                            <span class="stk-media__sub">#{{ $attendance_day->employee_id }}</span>
                        @endif
                    </span>
                </div>
            </td>
            <td>{{ \Carbon\Carbon::parse($attendance_day->attendance_date)->locale('ar')->translatedFormat('l، d/m/Y') }}</td>
            @if ($attendance_day->status == 'present')
                @php
                    $loginTime = \Carbon\Carbon::parse($attendance_day->check_in_time);
                    $logoutTime = \Carbon\Carbon::parse($attendance_day->check_out_time);
                    $totalDuration = $loginTime->diff($logoutTime);
                @endphp
                <td class="stk-num">{{ $attendance_day->check_in_time }}</td>
                <td class="stk-num">{{ $attendance_day->check_out_time }}</td>
                <td class="stk-num">{{ $totalDuration->h }} ساعة و {{ $totalDuration->i }} دقيقة</td>
            @else
                <td class="text-muted">—</td>
                <td class="text-muted">—</td>
                <td class="text-muted">—</td>
            @endif
            <td class="text-center">
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
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
                                <a class="dropdown-item" href="{{ route('attendanceDays.show', $attendance_day->id) }}">
                                    <i class="fa fa-eye me-2 text-primary"></i>عرض
                                </a>
                            @endpermission
                            @permission('attendance_corrections.create')
                                <a class="dropdown-item" href="{{ route('attendanceDays.edit', $attendance_day->id) }}">
                                    <i class="fa fa-edit me-2 text-success"></i>طلب تصحيح
                                </a>
                            @endpermission
                            @permission('attendance_days.edit')
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $attendance_day->id }}">
                                    <i class="fa fa-trash me-2"></i>حذف
                                </a>
                            @endpermission
                        </div>
                    </div>
                @endif

                @permission('attendance_days.edit')
                    <!-- Modal delete -->
                    <div class="modal fade text-left" id="modal_DELETE{{ $attendance_day->id }}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel{{ $attendance_day->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color: var(--app-danger, #EA5455) !important;">
                                    <h4 class="modal-title" id="myModalLabel{{ $attendance_day->id }}" style="color: #FFFFFF">حذف يوم الحضور</h4>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                                        <span aria-hidden="true" style="color: #FFFFFF">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body text-start">
                                    <strong>هل أنت متأكد من حذف هذا اليوم؟</strong>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                                    <form method="POST" action="{{ route('attendanceDays.delete', $attendance_day->id) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endpermission
            </td>
        </tr>
    @endforeach
@else
    <tr>
        <td colspan="7" class="p-0">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                <h5 class="stk-empty__title">لا توجد أيام حضور</h5>
                <p class="stk-empty__hint">لم يطابق البحث أيّ يوم — جرّب معايير أوسع، أو احسب الحضور من البصمات المسجّلة.</p>
                <button type="button" class="btn btn-outline-secondary js-att-days-reset"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            </div>
        </td>
    </tr>
@endif
