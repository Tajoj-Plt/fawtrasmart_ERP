{{--
    محتوى بطاقة جدول سياسات الإجازات.

    يُعرض داخل `#tableContainer` في index، ويُعاد نفسُه من
    `leave_policy.search` و`leave_policy.index` (AJAX) في حقل `html`.
--}}
@php
    $lpHasFilters = request()->filled('keywords') || (request()->has('status') && request('status') !== '' && request('status') !== null);
    $lpCanShow = hasPermission('leave_policies.show');
    $lpCanEdit = hasPermission('leave_policies.edit');
    $lpCanDestroy = hasPermission('leave_policies.destroy');
@endphp

@if (isset($leave_policies) && $leave_policies->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة السياسات
            <span class="stk-count">{{ $leave_policies->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>السياسة</th>
                    <th class="text-center">أنواع الإجازات</th>
                    <th class="text-center">الموظفون</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($leave_policies as $leave_policy)
                    @php $isActive = (int) $leave_policy->status === 0; @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-bookmark"></i></span>
                                <span class="stk-media__body">
                                    @if ($lpCanShow)
                                        <a href="{{ route('leave_policy.show', $leave_policy->id) }}" class="stk-media__title">{{ $leave_policy->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $leave_policy->name }}</span>
                                    @endif
                                    <span class="stk-media__sub">رقم {{ $leave_policy->id }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="text-center stk-num">{{ $leave_policy->leaveType()->count() }} نوع</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary show-employees-modal"
                                data-policy-id="{{ $leave_policy->id }}"
                                data-policy-name="{{ $leave_policy->name ?? '' }}">
                                <i class="feather icon-users me-1"></i>
                                <span class="employees-count-{{ $leave_policy->id }}">جاري التحميل...</span>
                            </button>
                        </td>
                        <td class="text-center">
                            <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                        </td>
                        <td class="text-center">
                            @if ($lpCanShow || $lpCanEdit || $lpCanDestroy)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $leave_policy->id }}"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $leave_policy->id }}">
                                        @if ($lpCanShow)
                                            <a class="dropdown-item" href="{{ route('leave_policy.show', $leave_policy->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endif
                                        @if ($lpCanEdit)
                                            <a class="dropdown-item" href="{{ route('leave_policy.edit', $leave_policy->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                            <a class="dropdown-item" href="{{ route('leave_policy.leave_policy_employees', $leave_policy->id) }}">
                                                <i class="fa fa-users me-2 text-info"></i>إدارة الموظفين
                                            </a>
                                        @endif
                                        @if ($lpCanShow)
                                            <a class="dropdown-item show-employees-modal" href="#"
                                                data-policy-id="{{ $leave_policy->id }}"
                                                data-policy-name="{{ $leave_policy->name }}">
                                                <i class="fa fa-list me-2 text-warning"></i>عرض الموظفين
                                            </a>
                                        @endif
                                        @if ($lpCanDestroy)
                                            @if ($lpCanShow || $lpCanEdit)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                            <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $leave_policy->id }}">
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

    {{-- نوافذ الحذف خارج الجدول: وضعُها داخل <tr> ينتهك بنيته --}}
    @if ($lpCanDestroy)
        @foreach ($leave_policies as $leave_policy)
            <div class="modal fade" id="modal_DELETE{{ $leave_policy->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">حذف سياسة الإجازة</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">ستُحذف السياسة <strong>{{ $leave_policy->name }}</strong>.</p>
                            <p class="stk-empty__hint mb-0">سيتم حذف جميع البيانات المرتبطة بهذه السياسة.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                            <form method="POST" action="{{ route('leave_policy.delete', $leave_policy->id) }}" class="d-inline">
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

    {{-- عددُ موظفي كلّ سياسة يُجلب بعد رسم الجدول --}}
    <script>
        $(function () {
            var countUrl = @json(route('leave_policy.employeesCount', '__ID__'));
            @foreach ($leave_policies as $leave_policy)
                $.ajax({
                    url: countUrl.replace('__ID__', '{{ $leave_policy->id }}'),
                    type: 'GET',
                    success: function (response) {
                        $('.employees-count-{{ $leave_policy->id }}').text((response.success ? response.count : 0) + ' موظف');
                    },
                    error: function () {
                        $('.employees-count-{{ $leave_policy->id }}').text('—');
                    }
                });
            @endforeach
        });
    </script>
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-bookmark"></i></div>
            <h5 class="stk-empty__title">لا توجد سياسات إجازة</h5>
            <p class="stk-empty__hint">
                @if ($lpHasFilters)
                    لم يطابق البحث أي سياسة — جرّب معايير أوسع
                @else
                    السياسة تجمع أنواع الإجازات وأرصدتها ثم تُسند إلى مجموعة موظفين.
                @endif
            </p>
            @if ($lpHasFilters)
                <a href="{{ route('leave_policy.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
            @elseif (hasPermission('leave_policies.create'))
                <a href="{{ route('leave_policy.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة سياسة</a>
            @endif
        </div>
    </div>
@endif
