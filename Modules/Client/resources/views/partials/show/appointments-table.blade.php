@if ($appointments->count() > 0)
    <div class="mb-3">
        <button class="btn btn-sm btn-outline-primary active filter-appointments" data-filter="all">
            الكل <span class="badge bg-primary">{{ $appointments->total() }}</span>
        </button>
        <button class="btn btn-sm btn-outline-primary filter-appointments" data-filter="2">
            تم <span class="badge bg-primary">{{ $appointments->where('status', 2)->count() }}</span>
        </button>
        <button class="btn btn-sm btn-outline-primary filter-appointments" data-filter="1">
            مجدول <span class="badge bg-primary">{{ $appointments->where('status', 1)->count() }}</span>
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم الموعد</th>
                    <th>العنوان</th>
                    <th>التاريخ</th>
                    <th>الموظف</th>
                    <th>الحالة</th>
                    <th>خيارات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($appointments as $appointment)
                    <tr data-status="{{ $appointment->status }}">
                        <td><strong>#{{ $appointment->id }}</strong></td>
                        <td>{{ $appointment->title }}</td>
                        <td>{{ $appointment->created_at->format('Y-m-d') }}</td>
                        <td>{{ $appointment->employeeNames() ?? 'غير محدد' }}</td>
                        <td>
                            @php
                                $statusClass = match ($appointment->status) {
                                    1 => 'bg-warning',
                                    2 => 'bg-success',
                                    3 => 'bg-danger',
                                    4 => 'bg-info',
                                    default => 'bg-secondary',
                                };
                                $statusText = match ($appointment->status) {
                                    1 => 'مجدول',
                                    2 => 'تم',
                                    3 => 'صرف النظر',
                                    4 => 'معاد جدولته',
                                    default => 'غير محدد',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                        <td style="width: 120px">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
                                    type="button" id="dropdownMenu{{ $appointment->id }}"
                                    data-bs-toggle="dropdown">
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end shadow-lg"
                                    aria-labelledby="dropdownMenu{{ $appointment->id }}">
                                    <li>
                                        <a class="dropdown-item"
                                            href="{{ route('appointments.edit', $appointment->id) }}">
                                            <i class="fa fa-edit me-2 text-success"></i>تعديل
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 1]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                            <i class="fa fa-clock me-2 text-warning"></i>قيد الانتظار
                                        </button></form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 2]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                            <i class="fa fa-check me-2 text-success"></i>مكتمل
                                        </button></form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 3]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                            <i class="fa fa-times me-2 text-danger"></i>ملغي
                                        </button></form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 4]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                            <i class="fa fa-redo me-2 text-info"></i>معاد جدولته
                                        </button></form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($appointments->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info text-muted">
                عرض {{ $appointments->firstItem() ?? 0 }} إلى {{ $appointments->lastItem() ?? 0 }} من {{ $appointments->total() }} موعد
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $appointments->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $appointments->currentPage() - 1 }}"
                           data-tab="appointments"
                           {{ $appointments->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </li>

                    @php
                        $start = max($appointments->currentPage() - 2, 1);
                        $end = min($start + 4, $appointments->lastPage());
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $appointments->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link pagination-link"
                               href="#"
                               data-page="{{ $i }}"
                               data-tab="appointments">
                                {{ $i }}
                            </a>
                        </li>
                    @endfor

                    <li class="page-item {{ !$appointments->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $appointments->currentPage() + 1 }}"
                           data-tab="appointments"
                           {{ !$appointments->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
@else
    <div class="empty-state">
        <i class="fas fa-calendar-alt"></i>
        <h5>لا توجد مواعيد</h5>
        <p>لم يتم ترتيب أي مواعيد لهذا العميل بعد</p>
        <a href="{{ route('appointments.create', ['client_id' => $client->id]) }}" class="btn btn-unified">
            <i class="fas fa-plus me-1"></i>ترتيب موعد جديد
        </a>
    </div>
@endif