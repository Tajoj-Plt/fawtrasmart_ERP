@extends('sitting::master')

@section('title', 'الصلاحيات')

@section('content')
    @php
        use App\Support\PermissionLabels;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('settings.roles.view')
                <a href="{{ route('settings.roles.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-shield"></i> الأدوار
                </a>
            @endpermission
            @permission('settings.permissions.create')
                <a href="{{ route('settings.permissions.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> صلاحية جديدة
                </a>
            @endpermission
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'الصلاحيات',
            'icon' => 'key',
            'sub' => 'الصلاحيات المُعرَّفة في النظام والأدوار التي تستعملها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الصلاحيات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-key"></i></span>
                <span class="stk-metric__label">إجمالي الصلاحيات</span>
                <span class="stk-metric__value stk-num">{{ $permissionsTotal ?? $permissions->total() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">الأدوار في النظام</span>
                <span class="stk-metric__value stk-num">{{ $rolesTotal ?? '—' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span>
                <span class="stk-metric__label">غير مُسنَدة لأي دور</span>
                <span class="stk-metric__value stk-num">{{ $unassignedTotal ?? '—' }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('settings.permissions.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    value="{{ request('keywords') }}" placeholder="التسمية العربية أو الاسم الخام…">
                                <small class="stk-help">
                                    يشمل البحث التسمية والاسم معاً: <span class="stk-num">settings.users.view</span>
                                </small>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="module">القسم</label>
                                <select id="module" name="module" class="form-control">
                                    <option value="">كل الأقسام</option>
                                    @foreach ($modules ?? [] as $key => $label)
                                        <option value="{{ $key }}" {{ request('module') === (string) $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('settings.permissions.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-list"></i> قائمة الصلاحيات
                    <span class="stk-count">{{ $permissions->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive stk-scroll">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الصلاحية</th>
                            <th>القسم</th>
                            <th>المجموعة</th>
                            <th class="text-center">عدد الأدوار</th>
                            <th class="text-center" style="width:90px">إجراءات</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($permissions as $permission)
                            @php
                                $moduleKey = $permission->module ?: PermissionLabels::UNCLASSIFIED;
                            @endphp

                            <tr>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-key"></i></span>
                                        <div class="stk-media__body">
                                            <span class="stk-media__title">
                                                {{ $permission->label ?: PermissionLabels::describe($permission->name) }}
                                            </span>
                                            <span class="stk-media__sub stk-num">{{ $permission->name }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="stk-tag">
                                        <i class="feather icon-{{ PermissionLabels::groupIcon($moduleKey) }}"></i>
                                        &nbsp;{{ $permission->module_label ?: PermissionLabels::groupLabel($moduleKey) }}
                                    </span>
                                </td>

                                <td>
                                    @if ($permission->group_label)
                                        {{ $permission->group_label }}
                                    @else
                                        <span class="stk-state stk-state--warn">غير مصنَّفة</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="stk-state {{ $permission->roles_count > 0 ? 'stk-state--on' : 'stk-state--neutral' }}">
                                        {{ $permission->roles_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="dropdown stk-actions">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
                                            @permission('settings.permissions.edit')
                                                <a class="dropdown-item"
                                                    href="{{ route('settings.permissions.edit', $permission->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endpermission

                                            @permission('settings.permissions.delete')
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item text-danger delete-permission"
                                                    data-id="{{ $permission->id }}" data-name="{{ $permission->name }}"
                                                    data-roles="{{ $permission->roles_count }}">
                                                    <i class="feather icon-trash-2"></i> حذف
                                                </button>
                                            @endpermission
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-key"></i></span>
                                        <h6 class="stk-empty__title">لا صلاحيات مطابقة</h6>
                                        <p class="stk-empty__hint">
                                            جرّب توسيع البحث أو أعِد تعيينه — أو أضف صلاحيةً جديدة.
                                        </p>

                                        @permission('settings.permissions.create')
                                            <a href="{{ route('settings.permissions.create') }}" class="btn btn-primary mt-1">
                                                <i class="feather icon-plus"></i> صلاحية جديدة
                                            </a>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

                @include('sitting::components.table-foot', ['paginator' => $permissions])
        </div>
    </div>

    <form id="deletePermissionForm" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    <script>
        $(function () {
            $(document).on('click', '.delete-permission', function () {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var roles = parseInt($(this).data('roles'), 10) || 0;

                // الحذف يُرفَض خادمياً إن كانت الصلاحية مُسنَدة — نقولها قبل الطلب لا بعده
                if (roles > 0) {
                    Swal.fire('لا يمكن الحذف', 'الصلاحية «' + name + '» مُسنَدة إلى ' + roles +
                        ' دور. انزعها من الأدوار أولاً.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'حذف الصلاحية؟',
                    text: 'ستُحذف الصلاحية «' + name + '» من النظام.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'تراجع',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                }).then(function (result) {
                    if (! result.isConfirmed) return;

                    var form = document.getElementById('deletePermissionForm');
                    form.action = '{{ route('settings.permissions.destroy', ['permission' => '_ID_']) }}'.replace('_ID_', id);
                    form.submit();
                });
            });
        });
    </script>
@endsection
