{{--
    محتوى بطاقة جدول أذونات الإجازة.

    يُعاد من `leave_permissions.ajax.index` في حقل `html` ويُرسم داخل
    `#resultsContainer` في index. روابطُ الصفحات تحمل الفلاتر، وتلتقطها
    شاشةُ index فتحمّلها بـ AJAX بدل فتح رابط JSON.
--}}
@php
    $lpFilterKeys = ['keywords', 'from_date', 'to_date', 'status', 'leave_type', 'department', 'branch', 'job_title'];
    $lpHasFilters = collect(request()->only($lpFilterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $lpCanShow = hasPermission('leave_permissions.show');
    $lpCanEdit = hasPermission('leave_permissions.edit');
    $lpCanDestroy = hasPermission('leave_permissions.destroy');
@endphp

@if (isset($leavePermissions) && $leavePermissions->count() > 0)
    @php $leavePermissions->appends(request()->except(['page', '_token'])); @endphp

    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة الأذونات
            <span class="stk-count">{{ $leavePermissions->total() }}</span>
        </h6>
        @include('stock::components.per-page', [
            'id' => 'leavePermissionsPerPage',
            'current' => request('per_page', 10),
            'onchange' => "document.getElementById('lp_per_page').value=this.value; $('#searchForm').trigger('submit');",
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="leave-permissions-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الموظف</th>
                    <th class="text-center">رقم الإذن</th>
                    <th>التاريخ</th>
                    <th>النوع</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($leavePermissions as $index => $leavePermission)
                    <tr>
                        <td class="stk-num text-muted">{{ $leavePermissions->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                <span class="stk-media__body">
                                    @if ($lpCanShow)
                                        <a href="{{ route('leave_permissions.show', $leavePermission->id) }}" class="stk-media__title">{{ $leavePermission->employee->full_name ?? 'غير محدد' }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $leavePermission->employee->full_name ?? 'غير محدد' }}</span>
                                    @endif
                                    <span class="stk-media__sub">#{{ $leavePermission->employee->id ?? '—' }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="text-center stk-num">#{{ $leavePermission->id }}</td>
                        <td>
                            <span class="stk-num">{{ \Carbon\Carbon::parse($leavePermission->start_date)->format('d/m/Y') }}</span>
                            <span class="d-block text-muted small stk-num">إلى {{ \Carbon\Carbon::parse($leavePermission->end_date)->format('d/m/Y') }}</span>
                        </td>
                        <td>
                            {{-- `leave_type` نوعُ الإذن (وصول متأخر / انصراف مبكر)، و`type` نوعُ الإجازة (اعتيادية / عرضية) --}}
                            @if ((int) $leavePermission->leave_type === \App\Models\LeavePermissions::LATE_ARRIVAL)
                                <span class="badge badge-primary"><i class="feather icon-clock me-1"></i>{{ $leavePermission->leave_type_text }}</span>
                            @elseif ($leavePermission->leave_type_text)
                                <span class="badge badge-warning"><i class="feather icon-log-out me-1"></i>{{ $leavePermission->leave_type_text }}</span>
                            @endif
                            @if ($leavePermission->type_text)
                                <span class="d-block text-muted small mt-1">{{ $leavePermission->type_text }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($lpCanShow || $lpCanEdit || $lpCanDestroy)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $leavePermission->id }}"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $leavePermission->id }}">
                                        @if ($lpCanShow)
                                            <a class="dropdown-item" href="{{ route('leave_permissions.show', $leavePermission->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endif
                                        @if ($lpCanEdit)
                                            <a class="dropdown-item" href="{{ route('leave_permissions.edit', $leavePermission->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endif
                                        @if ($lpCanDestroy)
                                            @if ($lpCanShow || $lpCanEdit)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $leavePermission->id }}">
                                                <i class="fa fa-trash me-2"></i>حذف
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($leavePermissions->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $leavePermissions->firstItem() }}–{{ $leavePermissions->lastItem() }} من {{ $leavePermissions->total() }}</p>
            {{ $leavePermissions->links() }}
        </div>
    @endif

    {{-- نوافذ الحذف خارج الجدول: وضعُها داخل <tbody> ينتهك بنيته --}}
    @if ($lpCanDestroy)
        @foreach ($leavePermissions as $leavePermission)
            <div class="modal fade" id="modal_DELETE{{ $leavePermission->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">حذف إذن الإجازة</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">سيُحذف إذن الإجازة للموظف <strong>{{ $leavePermission->employee->full_name ?? 'غير محدد' }}</strong>.</p>
                            <p class="stk-empty__hint mb-0">
                                رقم الإذن: #{{ $leavePermission->id }} —
                                الفترة: {{ \Carbon\Carbon::parse($leavePermission->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($leavePermission->end_date)->format('d/m/Y') }}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                            <form action="{{ route('leave_permissions.destroy', $leavePermission->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد الحذف</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-clock"></i></div>
            <h5 class="stk-empty__title">لا توجد أذونات إجازة</h5>
            <p class="stk-empty__hint">
                @if ($lpHasFilters)
                    لم يطابق البحث أي إذن — جرّب معايير أوسع
                @else
                    لم يُضف أي إذن بعد. الإذن يعفي الموظف من احتساب التأخير أو الانصراف المبكر في يومه.
                @endif
            </p>
            @if ($lpHasFilters)
                <button type="button" class="btn btn-outline-secondary lp-reset-filters"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            @elseif (hasPermission('leave_permissions.create'))
                <a href="{{ route('leave_permissions.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة إذن</a>
            @endif
        </div>
    </div>
@endif
