{{-- ملف: resources/views/appointments/partials/table.blade.php --}}

@if (isset($appointments) && !empty($appointments) && count($appointments) > 0)
    <div class="table">
        <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper dt-bootstrap4">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="dataTables_length" id="DataTables_Table_0_length" style="text-align: left;">
                        <label style="margin-bottom: 0;">
                            عرض
                            <select name="DataTables_Table_0_length" aria-controls="DataTables_Table_0" class="form-select form-select-sm d-inline-block w-auto"
                                onchange="if(window.loadData) { window.loadData(1, parseInt(this.value)); }">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
                            </select>
                            سجل
                        </label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <table class="table dataTable" id="DataTables_Table_0" role="grid" aria-describedby="DataTables_Table_0_info">
                        <thead>
                            <tr role="row">
                                <th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-sort="ascending">رقم الموعد</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">العميل</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">التاريخ والوقت</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">المدة</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">الموظف</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1">الحالة</th>
                                <th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" style="width: 10%">خيارات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appointments as $appointment)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-2" style="background-color: #3498db">
                                                <span class="avatar-content">#</span>
                                            </div>
                                            <div>
                                                #{{ $appointment->id }}
                                                {{-- موعدٌ كتبه تأكيدُ حجز — رابطٌ إلى حجزه --}}
                                                @if ($appointment->reservation_id && Route::has('Reservations.show'))
                                                    <a href="{{ route('Reservations.show', $appointment->reservation_id) }}" class="d-block small">
                                                        <i class="fa fa-bookmark"></i> {{ __('reservations.links.from_reservation') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="client-info">
                                            <strong>
                                                {{ $appointment->client->trade_name }}
                                            </strong>
                                            <br>
                                            @if ($appointment->client->status_client)
                                                <span class="badge" style="background-color: {{ $appointment->client->status_client->color }};">
                                                    {{ $appointment->client->status_client->name }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">
                                                    غير محدد
                                                </span>
                                            @endif
                                            <br>
                                            <small class="text-muted">{{ $appointment->client->phone }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d') }}
                                        <br>
                                        <small class="text-muted">الوقت: {{ $appointment->time }}</small>
                                    </td>
                                    <td>
                                        {{ $appointment->duration ?? 'غير محدد' }}
                                    </td>
                                    <td>
                                        {{ $appointment->employeeNames() ?? 'غير محدد' }}
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                1 => ['class' => 'warning', 'text' => 'قيد الانتظار'],
                                                2 => ['class' => 'success', 'text' => 'مكتمل'],
                                                3 => ['class' => 'danger', 'text' => 'ملغي'],
                                                4 => ['class' => 'info', 'text' => 'معاد جدولته'],
                                            ];
                                            $status = $statusMap[$appointment->status] ?? ['class' => 'dark', 'text' => 'غير معروفة'];
                                        @endphp
                                        <span class="badge bg-{{ $status['class'] }}">{{ $status['text'] }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <div class="dropdown">
                                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="{{ route('appointments.show', $appointment->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                    @if ($appointment->reservation_id)
                                                        {{-- موعدُ حجز: تاريخُه وحالتُه من الحجز — يُعدَّل ويُلغى من شاشته --}}
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
                                                            <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                        </a>
                                                        <a class="dropdown-item status-action"
                                                           href="{{ route('update-status', ['id' => $appointment->id, 'status' => 1]) }}">
                                                            <i class="fa fa-clock me-2 text-warning"></i>قيد الانتظار
                                                        </a>
                                                        <a class="dropdown-item status-action"
                                                           href="{{ route('update-status', ['id' => $appointment->id, 'status' => 2]) }}">
                                                            <i class="fa fa-check me-2 text-success"></i>مكتمل
                                                        </a>
                                                        <a class="dropdown-item status-action"
                                                           href="{{ route('update-status', ['id' => $appointment->id, 'status' => 3]) }}">
                                                            <i class="fa fa-times me-2 text-danger"></i>ملغي
                                                        </a>
                                                        <a class="dropdown-item status-action"
                                                           href="{{ route('update-status', ['id' => $appointment->id, 'status' => 4]) }}">
                                                            <i class="fa fa-redo me-2 text-info"></i>معاد جدولته
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        {{-- `.delete-btn` يلتقطه سكربت القائمة: تأكيدٌ ثم `DELETE` عبر AJAX --}}
                                                        <a class="dropdown-item text-danger delete-btn"
                                                           href="{{ route('appointments.destroy', $appointment->id) }}">
                                                            <i class="fa fa-trash me-2"></i>حذف
                                                        </a>
                                                        @endpermission
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- الترقيم --}}
            @if ($appointments instanceof \Illuminate\Pagination\LengthAwarePaginator && $appointments->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="dataTables_info" id="DataTables_Table_0_info">
                        عرض {{ $appointments->firstItem() }} إلى {{ $appointments->lastItem() }} من {{ $appointments->total() }} نتيجة
                    </div>
                    <div class="dataTables_paginate">
                        <ul class="pagination">
                            {{-- الصفحة الأولى --}}
                            @if ($appointments->onFirstPage())
                                <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-right"></i></span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $appointments->url(1) }}"><i class="fa fa-angle-double-right"></i></a></li>
                            @endif

                            {{-- السابق --}}
                            @if ($appointments->onFirstPage())
                                <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-right"></i></span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $appointments->previousPageUrl() }}"><i class="fa fa-angle-right"></i></a></li>
                            @endif

                            {{-- أرقام الصفحات --}}
                            @php
                                $start = max(1, $appointments->currentPage() - 2);
                                $end = min($appointments->lastPage(), $appointments->currentPage() + 2);
                            @endphp

                            @if ($start > 1)
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            @endif

                            @for ($i = $start; $i <= $end; $i++)
                                <li class="page-item {{ $appointments->currentPage() == $i ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $appointments->url($i) }}">{{ $i }}</a>
                                </li>
                            @endfor

                            @if ($end < $appointments->lastPage())
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            @endif

                            {{-- التالي --}}
                            @if ($appointments->hasMorePages())
                                <li class="page-item"><a class="page-link" href="{{ $appointments->nextPageUrl() }}"><i class="fa fa-angle-left"></i></a></li>
                            @else
                                <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-left"></i></span></li>
                            @endif

                            {{-- الأخيرة --}}
                            @if ($appointments->hasMorePages())
                                <li class="page-item"><a class="page-link" href="{{ $appointments->url($appointments->lastPage()) }}"><i class="fa fa-angle-double-left"></i></a></li>
                            @else
                                <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-left"></i></span></li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
@else
    <div class="alert alert-info text-center" role="alert">
        <i class="fa fa-info-circle me-2"></i>
        <p class="mb-0">لا توجد مواعيد مسجلة حالياً</p>
    </div>
@endif

<style>
    .dropdown-menu {
        position: absolute !important;
        z-index: 1000 !important;
        min-width: 10rem !important;
        background-color: #fff !important;
        border-radius: 0.25rem !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175) !important;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa !important;
    }

    .badge {
        font-size: 0.75em;
        padding: 0.35em 0.65em;
    }

    .dataTables_paginate .pagination {
        justify-content: flex-end;
    }

    .page-link {
        padding: 0.375rem 0.75rem;
        position: relative;
        display: block;
        color: #5a8dee;
        text-decoration: none;
        background-color: #fff;
        border: 1px solid #ddd;
    }

    .page-item.active .page-link {
        z-index: 3;
        color: #fff;
        background-color: #5a8dee;
        border-color: #5a8dee;
    }

    .dataTables_info {
        padding-top: 0.85em;
        font-size: 0.875rem;
        color: #6c757d;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // تعطيل DataTables الافتراضية لأننا نستخدم نمط مخصص
        if ($.fn.dataTable.isDataTable('#DataTables_Table_0')) {
            $('#DataTables_Table_0').DataTable().destroy();
        }
    });
</script>