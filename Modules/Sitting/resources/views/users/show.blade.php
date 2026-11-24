@extends('sitting::master')

@section('title', $user->name)

@section('content')
    @include('sitting::roles._module-styles')

    @php
        use App\Support\PermissionLabels;

        /* التصنيف من أعمدة `module` و`group_label` في جدول `permissions` */
        $groupedPermissions = collect($permissions ?? [])
            ->sortBy('sort_order')
            ->groupBy(fn($permission) => $permission->module ?: PermissionLabels::UNCLASSIFIED)
            ->map(fn($items) => $items->groupBy(fn($permission) => $permission->group_label ?: 'صلاحيات أخرى'));
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('settings.users.edit')
                <a href="{{ route('settings.users.edit', $user->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endpermission
            @permission('settings.users.delete')
                @if ($user->id !== auth()->id())
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                        data-bs-target="#deleteUserModal">
                        <i class="feather icon-trash-2"></i> حذف
                    </button>
                @endif
            @endpermission
        @endsection

        @include('sitting::components.page-head', [
            'title' => $user->name,
            'icon' => 'user',
            'sub' => $user->email,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستخدمون' => route('settings.users.index'),
                $user->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">الدور</span>
                <span class="stk-metric__value">{{ $user->primaryRole->name ?? 'بلا دور' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">الفرع</span>
                <span class="stk-metric__value">{{ $user->branch->name ?? 'غير محدد' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-key"></i></span>
                <span class="stk-metric__label">الصلاحيات الفعلية</span>
                <span class="stk-metric__value stk-num">{{ count($permissions ?? []) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">تاريخ الإنشاء</span>
                <span class="stk-metric__value stk-num">{{ $user->created_at?->format('Y-m-d') }}</span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details"
                            type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="feather icon-user"></i> التفاصيل
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions"
                            type="button" role="tab" aria-controls="permissions" aria-selected="false">
                            <i class="feather icon-key"></i> الصلاحيات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"
                            type="button" role="tab" aria-controls="activity" aria-selected="false">
                            <i class="feather icon-activity"></i> سجل النشاط
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <div class="tab-pane active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الاسم</dt>
                                        <dd>{{ $user->name }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>البريد الإلكتروني</dt>
                                        <dd class="stk-num">{{ $user->email }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>رقم الهاتف</dt>
                                        <dd class="stk-num">{{ $user->phone ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الدور</dt>
                                        <dd>
                                            @if ($user->primaryRole)
                                                @if (hasPermission('settings.roles.view'))
                                                    <a href="{{ route('settings.roles.show', $user->primaryRole->id) }}"
                                                        class="stk-state stk-state--on">{{ $user->primaryRole->name }}</a>
                                                @else
                                                    <span class="stk-state stk-state--on">{{ $user->primaryRole->name }}</span>
                                                @endif
                                            @else
                                                <span class="stk-state stk-state--neutral">بلا دور</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الفرع</dt>
                                        <dd>{{ $user->branch->name ?? 'غير محدد' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>تاريخ الإنشاء</dt>
                                        <dd class="stk-num">{{ $user->created_at?->format('Y-m-d H:i') }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                        {{--
                            ما يملكه المستخدم فعلاً: صلاحيات دوره وما مُنح له
                            مباشرةً معاً، بالتسمية العربية والاسم الخام في `title`.
                        --}}
                        @forelse ($groupedPermissions as $moduleKey => $groups)
                            <div class="perm-module">
                                <div class="perm-module__head">
                                    <span class="perm-module__icon">
                                        <i class="feather icon-{{ PermissionLabels::groupIcon($moduleKey) }}"></i>
                                    </span>

                                    <span class="perm-module__titles">
                                        <span class="perm-module__title">
                                            {{ PermissionLabels::groupLabel($moduleKey) }}
                                            <span class="stk-count">{{ $groups->flatten()->count() }}</span>
                                        </span>
                                        <span class="perm-module__sub">
                                            {{ PermissionLabels::groupDescription($moduleKey) }}
                                        </span>
                                    </span>
                                </div>

                                <div class="perm-module__body">
                                    @foreach ($groups as $groupLabel => $items)
                                        <div class="stk-section">
                                            <div class="stk-section__head">
                                                <i class="feather icon-chevron-left"></i>
                                                <h6 class="stk-section__title">{{ $groupLabel }}</h6>
                                                <span class="stk-count">{{ $items->count() }}</span>
                                            </div>

                                            <div class="stk-tags">
                                                @foreach ($items as $permission)
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
                                <h6 class="stk-empty__title">لا صلاحيات فعلية</h6>
                                <p class="stk-empty__hint">
                                    لا يملك هذا المستخدم أي صلاحية — أسنِد إليه دوراً يحمل ما يحتاجه.
                                </p>
                            </div>
                        @endforelse
                    </div>

                    <div class="tab-pane" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                        @if ($logs && count($logs) > 0)
                            @foreach ($logs as $date => $dayLogs)
                                @php $currentDate = \Carbon\Carbon::parse($date); @endphp

                                <div class="stk-section__head">
                                    <i class="feather icon-calendar"></i>
                                    <h6 class="stk-section__title">
                                        {{ $currentDate->translatedFormat('l') }} — {{ $currentDate->format('Y-m-d') }}
                                    </h6>
                                </div>

                                <div class="stk-timeline mb-4">
                                    @foreach ($dayLogs as $log)
                                        <div class="stk-timeline__item">
                                            <span class="stk-timeline__dot stk-timeline__dot--log">
                                                <i class="feather icon-activity"></i>
                                            </span>
                                            <div class="stk-timeline__body">
                                                <div class="stk-timeline__text">
                                                    <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                    {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                </div>
                                                <div class="stk-timeline__meta">
                                                    <span class="stk-timeline__time">{{ $log->created_at->format('H:i') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                                <h6 class="stk-empty__title">لا سجلّات نشاط</h6>
                                <p class="stk-empty__hint">لم تُسجَّل عمليات على هذا المستخدم بعد.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($user->id !== auth()->id())
        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">حذف المستخدم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            سيُحذف المستخدم <strong>{{ $user->name }}</strong> نهائياً ولن يستطيع تسجيل الدخول بعد الآن.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <form method="POST" action="{{ route('settings.users.destroy', $user->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="feather icon-trash-2"></i> تأكيد الحذف
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
