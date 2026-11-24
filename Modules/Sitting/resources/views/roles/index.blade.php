@extends('sitting::master')

@section('title', 'الأدوار')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('settings.permissions.view')
                <a href="{{ route('settings.permissions.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-key"></i> الصلاحيات
                </a>
            @endpermission
            @permission('settings.roles.create')
                <a href="{{ route('settings.roles.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> دور جديد
                </a>
            @endpermission
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'الأدوار',
            'icon' => 'shield',
            'sub' => 'مجموعات الصلاحيات التي تُسنَد إلى المستخدمين',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الأدوار' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">إجمالي الأدوار</span>
                <span class="stk-metric__value stk-num">{{ $roles->total() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-key"></i></span>
                <span class="stk-metric__label">الصلاحيات في النظام</span>
                <span class="stk-metric__value stk-num">{{ $permissionsTotal ?? '—' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">مستخدمون بلا دور</span>
                <span class="stk-metric__value stk-num">{{ $usersWithoutRole ?? '—' }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('settings.roles.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    value="{{ request('keywords') }}" placeholder="اسم الدور…">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('settings.roles.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأدوار
                    <span class="stk-count">{{ $roles->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive stk-scroll">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الدور</th>
                            <th class="text-center">عدد الصلاحيات</th>
                            <th class="text-center">عدد المستخدمين</th>
                            <th class="text-center">القائمة الجانبية</th>
                            <th class="text-center">قناة العمل</th>
                            <th class="text-center" style="width:90px">إجراءات</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-shield"></i></span>
                                        <div class="stk-media__body">
                                            <span class="stk-media__title">
                                                <a href="{{ route('settings.roles.show', $role->id) }}">{{ $role->name }}</a>
                                            </span>
                                            <span class="stk-media__sub">الحارس: {{ $role->guard_name }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <span class="stk-state {{ $role->permissions_count > 0 ? 'stk-state--on' : 'stk-state--neutral' }}">
                                        {{ $role->permissions_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="stk-num">{{ $role->users_count }}</span>
                                </td>

                                {{-- موحّدة = القائمة الجامعة في كل شاشة؛ المطفأة تبقى قائمةَ كل وحدة --}}
                                <td class="text-center">
                                    @if ($role->unified_sidebar)
                                        <span class="stk-state stk-state--on" title="قائمة جامعة في كل شاشات النظام">
                                            <i class="feather icon-menu"></i> موحّدة
                                        </span>
                                    @else
                                        <span class="stk-state stk-state--neutral" title="لكل وحدةٍ قائمتُها">
                                            حسب الوحدة
                                        </span>
                                    @endif
                                </td>
                                {{-- «التطبيق فقط»: الدخول للموقع مرفوض، وواجهةُ التطبيق مفتوحة --}}
                                <td class="text-center">
                                    @if ($role->mobile_only)
                                        <span class="stk-state stk-state--warn" title="لا يدخل الموقع — الدخول من التطبيق وحده">
                                            <i class="feather icon-smartphone"></i> التطبيق فقط
                                        </span>
                                    @else
                                        <span class="stk-state stk-state--neutral" title="الموقع والتطبيق معاً">
                                            الموقع والتطبيق
                                        </span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <div class="dropdown stk-actions">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('settings.roles.show', $role->id) }}">
                                                <i class="feather icon-eye"></i> عرض
                                            </a>

                                            @permission('settings.roles.edit')
                                                <a class="dropdown-item" href="{{ route('settings.roles.edit', $role->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endpermission

                                            @permission('settings.roles.delete')
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item text-danger delete-role"
                                                    data-id="{{ $role->id }}" data-name="{{ $role->name }}"
                                                    data-users="{{ $role->users_count }}">
                                                    <i class="feather icon-trash-2"></i> حذف
                                                </button>
                                            @endpermission
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-shield-off"></i></span>
                                        <h6 class="stk-empty__title">لا أدوار مطابقة</h6>
                                        <p class="stk-empty__hint">
                                            جرّب توسيع البحث أو أعِد تعيينه — أو أنشئ دوراً جديداً.
                                        </p>

                                        @permission('settings.roles.create')
                                            <a href="{{ route('settings.roles.create') }}" class="btn btn-primary mt-1">
                                                <i class="feather icon-plus"></i> دور جديد
                                            </a>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

                @include('sitting::components.table-foot', ['paginator' => $roles])
        </div>
    </div>

    <form id="deleteRoleForm" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    <script>
        $(function () {
            $(document).on('click', '.delete-role', function () {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var users = parseInt($(this).data('users'), 10) || 0;

                // الحذف يُرفَض خادمياً إن كان الدور مُسنَداً — نقولها قبل الطلب لا بعده
                if (users > 0) {
                    Swal.fire('لا يمكن الحذف', 'الدور «' + name + '» مُسنَد إلى ' + users +
                        ' مستخدم. انقلهم إلى دورٍ آخر أولاً.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'حذف الدور؟',
                    text: 'سيُحذف الدور «' + name + '» وصلاحياته المُسنَدة إليه.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'تراجع',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                }).then(function (result) {
                    if (! result.isConfirmed) return;

                    var form = document.getElementById('deleteRoleForm');
                    form.action = '{{ route('settings.roles.destroy', ['role' => '_ID_']) }}'.replace('_ID_', id);
                    form.submit();
                });
            });
        });
    </script>
@endsection
