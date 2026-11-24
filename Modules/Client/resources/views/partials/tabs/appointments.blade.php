{{-- تبويب «المواعيد» — جدولٌ بتصميم «جدول العملاء» (`partials/client_cards`)
     وزرُّ ثلاث نقاطٍ في كل صفّ؛ القائمة تخرج من حدود الجدول بـ`client::partials.table-menu`.
     أزرار التصفية في ترويسة الجدول تُخفي الصفوف بـ`data-status` بالسكربت أسفل
     الملف — `setupAppointmentsFilter()` في public/js/client/show_client.js لا تُحمَّل
     في هذه الشاشة. لا حدَّ «عرض المزيد» هنا لأن التصفية تُخفي الصفوف أيضاً. --}}
@php
    $apRows = $client->appointments;
    $apStatuses = [
        1 => ['label' => 'مجدول', 'tone' => 'warning'],
        2 => ['label' => 'تم', 'tone' => 'on'],
        3 => ['label' => 'صرف النظر', 'tone' => 'danger'],
        4 => ['label' => 'معاد جدولته', 'tone' => 'neutral'],
    ];
@endphp
<div class="tab-pane" id="appointments" role="tabpanel">
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-calendar-alt"></i>
                المواعيد
                <span class="cl-count">{{ $apRows->count() }} موعد</span>
            </h6>

            @if ($apRows->isNotEmpty())
                <div class="btn-group btn-group-sm" role="group" aria-label="تصفية المواعيد">
                    <button type="button" class="btn btn-outline-primary filter-appointments active" data-filter="all">
                        الكل · {{ $apRows->count() }}
                    </button>
                    <button type="button" class="btn btn-outline-primary filter-appointments" data-filter="2">
                        تم · {{ $apRows->where('status', 2)->count() }}
                    </button>
                    <button type="button" class="btn btn-outline-primary filter-appointments" data-filter="1">
                        مجدول · {{ $apRows->where('status', 1)->count() }}
                    </button>
                </div>
            @endif
        </div>

        @if ($apRows->isNotEmpty())
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>الموعد</th>
                                <th>الوقت</th>
                                <th>المدّة</th>
                                <th>الموظف</th>
                                <th>النوع</th>
                                <th>ملاحظة</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width: 70px;">خيارات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($apRows as $appointment)
                                @php
                                    $apStatus = $apStatuses[(int) $appointment->status] ?? ['label' => 'غير محدد', 'tone' => 'off'];
                                    $apDate = $appointment->appointment_date ? \Carbon\Carbon::parse($appointment->appointment_date) : null;
                                @endphp
                                <tr data-status="{{ $appointment->status }}">
                                    <td class="text-center cl-num">{{ $loop->iteration }}</td>
                                    <td class="cl-num">
                                        @if ($apDate)
                                            <strong>{{ $apDate->format('Y-m-d') }}</strong>
                                            <small class="text-muted d-block">{{ $apDate->locale('ar')->translatedFormat('l') }}</small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="cl-num">
                                        @if ($appointment->time)
                                            <bdi dir="ltr">{{ \Carbon\Carbon::parse($appointment->time)->format('h:i A') }}</bdi>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $appointment->duration ?: '—' }}</td>
                                    <td>{{ $appointment->employeeNames() ?? 'غير محدد' }}</td>
                                    <td>
                                        {{ $appointment->action_type ?: '—' }}
                                        @if ($appointment->is_recurring)
                                            <small class="text-muted d-block"><i class="fas fa-sync-alt"></i> متكرر</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($appointment->notes)
                                            <span class="d-inline-block text-truncate" style="max-width: 200px;" title="{{ $appointment->notes }}">{{ $appointment->notes }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="cl-state cl-state--{{ $apStatus['tone'] }}">{{ $apStatus['label'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('appointments.show', $appointment->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض الموعد
                                                </a>
                                                @if ($appointment->reservation_id)
                                                    {{-- موعدُ حجز: يُعدَّل ويُلغى من شاشة الحجز لا من هنا --}}
                                                    @if (Route::has('Reservations.show'))
                                                        <a class="dropdown-item" href="{{ route('Reservations.show', $appointment->reservation_id) }}">
                                                            <i class="fa fa-calendar-check me-2 text-info"></i>{{ __('reservations.links.open_reservation') }}
                                                        </a>
                                                    @endif
                                                    <span class="dropdown-item-text small text-muted" style="white-space: normal; max-width: 16rem;">
                                                        <i class="fa fa-lock me-1"></i>{{ __('reservations.errors.appointment_reservation_locked') }}
                                                    </span>
                                                @else
                                                    @permission('client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own')
                                                    <a class="dropdown-item" href="{{ route('appointments.edit', $appointment->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل الموعد
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 1]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                        <i class="fa fa-clock me-2 text-warning"></i>قيد الانتظار
                                                    </button></form>
                                                    <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 2]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                        <i class="fa fa-check me-2 text-success"></i>مكتمل
                                                    </button></form>
                                                    <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 4]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                        <i class="fa fa-redo me-2 text-info"></i>معاد جدولته
                                                    </button></form>
                                                    <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 3]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa fa-times me-2"></i>ملغي
                                                    </button></form>
                                                    <div class="dropdown-divider"></div>
                                                    <form method="POST" action="{{ route('appointments.destroy', $appointment->id) }}" class="d-inline m-0"
                                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا الموعد؟ لا يمكن التراجع عن هذه العملية.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fa fa-trash me-2"></i>حذف الموعد
                                                        </button>
                                                    </form>
                                                    @endpermission
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-calendar-alt"></i></span>
                <p class="cl-empty__title">لا توجد مواعيد</p>
                <p class="cl-empty__hint">لم يتم ترتيب أي مواعيد لهذا العميل بعد</p>
            </div>
        @endif
    </div>
</div>

<script>
    // تصفية صفوف المواعيد بالحالة
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#appointments .filter-appointments');
        if (!btn) return;
        var filter = btn.getAttribute('data-filter');
        document.querySelectorAll('#appointments .filter-appointments').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        document.querySelectorAll('#appointments tr[data-status]').forEach(function (row) {
            row.hidden = !(filter === 'all' || row.getAttribute('data-status') === filter);
        });
    });
</script>
