@extends('sitting::master')

@section('title', 'المستخدمون')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('settings.roles.view')
                <a href="{{ route('settings.roles.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-shield"></i> الأدوار
                </a>
            @endpermission
            @permission('settings.users.create')
                <a href="{{ route('settings.users.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> مستخدم جديد
                </a>
            @endpermission
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'المستخدمون',
            'icon' => 'users',
            'sub' => 'حسابات الدخول إلى النظام، أدوارها وفروعها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'المستخدمون' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">إجمالي المستخدمين</span>
                <span class="stk-metric__value stk-num">{{ $users->total() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">الأدوار المتاحة</span>
                <span class="stk-metric__value stk-num">{{ $roles->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">الفروع المتاحة</span>
                <span class="stk-metric__value stk-num">{{ $branches->count() }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('settings.users.index') }}">
                    <div class="row">
                        <div class="col-md-5 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    value="{{ request('keywords') }}" placeholder="الاسم أو البريد الإلكتروني…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="role_id">الدور</label>
                                <select id="role_id" name="role_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="branch_id">الفرع</label>
                                <select id="branch_id" name="branch_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('settings.users.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-list"></i> قائمة المستخدمين
                    <span class="stk-count">{{ $users->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive stk-scroll">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>الفرع</th>
                            <th class="text-center" style="width:100px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                        <div class="stk-media__body">
                                            <span class="stk-media__title">
                                                <a href="{{ route('settings.users.show', $user->id) }}">{{ $user->name }}</a>
                                            </span>
                                            <span class="stk-media__sub">{{ $user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($user->primaryRole)
                                        <span class="stk-state stk-state--on">{{ $user->primaryRole->name }}</span>
                                    @else
                                        <span class="stk-state stk-state--neutral">بلا دور</span>
                                    @endif
                                </td>
                                <td>{{ $user->branch->name ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="dropdown stk-actions">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('settings.users.show', $user->id) }}">
                                                <i class="feather icon-eye"></i> عرض
                                            </a>
                                            @permission('settings.users.edit')
                                                <a class="dropdown-item" href="{{ route('settings.users.edit', $user->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endpermission
                                            @permission('settings.users.delete')
                                                @if ($user->id !== auth()->id())
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger delete-user" href="#"
                                                        data-id="{{ $user->id }}" data-name="{{ $user->name }}">
                                                        <i class="feather icon-trash-2"></i> حذف
                                                    </a>
                                                @endif
                                            @endpermission
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
                                        <h6 class="stk-empty__title">لا مستخدمين مطابقين</h6>
                                        <p class="stk-empty__hint">
                                            جرّب توسيع الفلاتر أو أعِد تعيينها — أو أضف مستخدماً جديداً.
                                        </p>

                                        @permission('settings.users.create')
                                            <a href="{{ route('settings.users.create') }}" class="btn btn-primary mt-1">
                                                <i class="feather icon-plus"></i> مستخدم جديد
                                            </a>
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

                @include('sitting::components.table-foot', ['paginator' => $users])
        </div>
    </div>

    <form id="deleteUserForm" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $(document).on('click', '.delete-user', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).data('name');

                Swal.fire({
                    title: 'حذف المستخدم؟',
                    text: 'سيُحذف المستخدم «' + name + '» نهائياً.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                }).then(function (result) {
                    if (! result.isConfirmed) return;
                    const form = document.getElementById('deleteUserForm');
                    form.action = '{{ route('settings.users.destroy', ['user' => '_ID_']) }}'.replace('_ID_', id);
                    form.submit();
                });
            });
        });
    </script>
@endsection
