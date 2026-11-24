@extends('sitting::master')

@section('title', $role->name)

@section('content')
    @include('sitting::roles._module-styles')

    @php
        use App\Support\PermissionLabels;

        /*
            التصنيف يُقرأ من أعمدة `module` و`group_key` في جدول
            `permissions` — يكتبها `permissions:sync-catalog`. الترتيب
            بـ`sort_order` فتظهر الأقسام بترتيب كتالوجاتها لا بالأبجدية.
        */
        $grouped = $role->permissions
            ->sortBy('sort_order')
            ->groupBy(fn($permission) => $permission->module ?: PermissionLabels::UNCLASSIFIED)
            ->map(fn($items) => $items->groupBy(fn($permission) => $permission->group_label ?: 'صلاحيات أخرى'));
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('settings.roles.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
            @permission('settings.roles.edit')
                <a href="{{ route('settings.roles.edit', $role->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endpermission
        @endsection

        @include('sitting::components.page-head', [
            'title' => $role->name,
            'icon' => 'shield',
            'sub' => $role->permissions->count().' صلاحية — '.$role->users->count().' مستخدم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الأدوار' => route('settings.roles.index'),
                $role->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-key"></i></span>
                <span class="stk-metric__label">الصلاحيات</span>
                <span class="stk-metric__value stk-num">{{ $role->permissions->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">المستخدمون</span>
                <span class="stk-metric__value stk-num">{{ $role->users->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-grid"></i></span>
                <span class="stk-metric__label">الأقسام</span>
                <span class="stk-metric__value stk-num">{{ $grouped->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-lock"></i></span>
                <span class="stk-metric__label">الحارس</span>
                <span class="stk-metric__value">{{ $role->guard_name }}</span>
            </div>
            {{-- «موحّدة»: القائمة الجامعة في كل شاشة؛ «حسب الوحدة»: لكل وحدةٍ قائمتُها --}}
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-menu"></i></span>
                <span class="stk-metric__label">القائمة الجانبية</span>
                <span class="stk-metric__value">{{ $role->unified_sidebar ? 'موحّدة' : 'حسب الوحدة' }}</span>
            </div>
            {{-- «التطبيق فقط»: الموقع مغلقٌ على الدور، وواجهةُ التطبيق مفتوحة --}}
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-smartphone"></i></span>
                <span class="stk-metric__label">قناة العمل</span>
                <span class="stk-metric__value">{{ $role->mobile_only ? 'التطبيق فقط' : 'الموقع والتطبيق' }}</span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="permissions-tab" data-bs-toggle="tab"
                            data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions"
                            aria-selected="true">
                            <i class="feather icon-key"></i> الصلاحيات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="members-tab" data-bs-toggle="tab" data-bs-target="#members"
                            type="button" role="tab" aria-controls="members" aria-selected="false">
                            <i class="feather icon-users"></i> المستخدمون
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">

                    <div class="tab-pane active" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                        {{--
                            كانت الصلاحيات شارات بأسمائها الخام تحت عنوان مجموعةٍ
                            لاتيني (`g_a_d_r`، `inv`) — قائمةٌ لا تُقرأ. صارت
                            التسمية عربية والاسم الخام في `title` لمن يحتاجه.
                        --}}
                        @forelse ($grouped as $moduleKey => $groups)
                            @php $moduleCount = $groups->flatten()->count(); @endphp

                            <div class="perm-module mb-3">
                                <div class="perm-module__head">
                                    <span class="perm-module__icon">
                                        <i class="feather icon-{{ PermissionLabels::groupIcon($moduleKey) }}"></i>
                                    </span>

                                    <span class="perm-module__titles">
                                        <span class="perm-module__title">
                                            {{ PermissionLabels::groupLabel($moduleKey) }}
                                            <span class="stk-count">{{ $moduleCount }}</span>
                                        </span>
                                        <span class="perm-module__sub">
                                            {{ PermissionLabels::groupDescription($moduleKey) }}
                                        </span>
                                    </span>
                                </div>

                                <div class="perm-module__body">
                                    @foreach ($groups as $groupLabel => $permissions)
                                        <div class="stk-section">
                                            <div class="stk-section__head">
                                                <i class="feather icon-chevron-left"></i>
                                                <h6 class="stk-section__title">{{ $groupLabel }}</h6>
                                                <span class="stk-count">{{ $permissions->count() }}</span>
                                            </div>

                                            <div class="stk-tags">
                                                @foreach ($permissions as $permission)
                                                    <span class="stk-tag" title="{{ $permission->name }}">
                                                        {{ $permission->label ?: PermissionLabels::describe($permission->name) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-key"></i></span>
                                <h6 class="stk-empty__title">لا صلاحيات مُسنَدة</h6>
                                <p class="stk-empty__hint">هذا الدور لا يمنح شيئاً بعد — عدّله وأسنِد إليه صلاحيات.</p>

                                @permission('settings.roles.edit')
                                    <a href="{{ route('settings.roles.edit', $role->id) }}" class="btn btn-primary mt-1">
                                        <i class="feather icon-edit-2"></i> إسناد صلاحيات
                                    </a>
                                @endpermission
                            </div>
                        @endforelse
                    </div>

                    <div class="tab-pane" id="members" role="tabpanel" aria-labelledby="members-tab">
                        @if ($role->users->count() > 0)
                            <div class="table-responsive stk-scroll">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>المستخدم</th>
                                            <th>الفرع</th>
                                            <th class="text-center" style="width:90px">إجراءات</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($role->users as $user)
                                            <tr>
                                                <td>
                                                    <div class="stk-media">
                                                        <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                                        <div class="stk-media__body">
                                                            <span class="stk-media__title">{{ $user->name }}</span>
                                                            <span class="stk-media__sub">{{ $user->email }}</span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td>{{ $user->branch->name ?? '—' }}</td>

                                                <td class="text-center">
                                                    @permission('settings.users.view')
                                                        <a href="{{ route('settings.users.show', $user->id) }}"
                                                            class="btn btn-sm btn-outline-secondary" aria-label="عرض المستخدم">
                                                            <i class="feather icon-eye"></i>
                                                        </a>
                                                    @endpermission
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
                                <h6 class="stk-empty__title">لا مستخدمين بهذا الدور</h6>
                                <p class="stk-empty__hint">لم يُسنَد هذا الدور إلى أحدٍ بعد.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
