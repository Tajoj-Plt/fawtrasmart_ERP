{{--
    الإعدادات العامّة لوحدة المهام — شاشةٌ من عائلة شاشات الإعدادات.

    تمتدّ من `sitting::master` لا من `taskmanager::master`: مدخلُها بطاقةٌ
    في `SittingAccount/dashboard`، والرجوع منها إلى لوحة الإعدادات لا إلى
    لوحة المهام. وقالبُ الوحدة يعرض شريط المهام الجانبي فيُخرج المستخدم
    من سياق الإعدادات في منتصف عمله.

    كان في هذا الملف قبلَ اليوم قالبٌ ميّت: يمتدّ من `taskmanager::master`،
    ويقرأ `$settings->task_statuses` (عمودٌ لا وجود له)، ويشير إلى أربعة
    مساراتٍ غير مسجَّلة. لم يكن يُفتح من أيّ مسار.
--}}
@extends('sitting::master')

@section('title', 'الإعدادات العامّة للمهام')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">الإعدادات العامّة للمهام</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                            <li class="breadcrumb-item active">الإعدادات العامّة للمهام</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('taskmanager::layouts.alerts.success')
    @include('taskmanager::layouts.alerts.error')

    <div class="alert alert-info" role="alert">
        <div class="alert-body">
            <i class="feather icon-info"></i>
            هذه القيم هي الافتراضية لكل المشاريع. وإعدادُ المشروع الواحد — إن وُجد — يغلبها،
            ويُضبط من شاشة إعدادات ذلك المشروع.
        </div>
    </div>

    <div class="row">
        {{-- ═══════════ الحالات ═══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-activity"></i> حالات المهام</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-1">
                            <thead>
                                <tr>
                                    <th>الحالة</th>
                                    <th style="width: 90px">اللون</th>
                                    <th style="width: 70px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($statuses as $status)
                                    <tr>
                                        <td>{{ $status['name'] ?? '' }}</td>
                                        <td>
                                            <span class="d-inline-block rounded"
                                                  style="width:16px;height:16px;vertical-align:middle;background-color: {{ $status['color'] ?? '#ccc' }}"></span>
                                            <small class="text-muted">{{ $status['color'] ?? '' }}</small>
                                        </td>
                                        <td class="text-center">
                                            @permission('tasks.settings.global.edit')
                                                <button type="button" class="btn btn-sm btn-outline-secondary delete-item"
                                                        data-group="statuses" data-id="{{ $status['id'] ?? '' }}"
                                                        data-name="{{ $status['name'] ?? '' }}">
                                                    <i class="feather icon-rotate-ccw"></i>
                                                </button>
                                            @endpermission
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @permission('tasks.settings.global.edit')
                        <form method="POST" action="{{ route('taskmanager.global-settings.statuses.store') }}" class="row">
                            @csrf
                            <div class="col-12 mb-1">
                                <select name="id" class="form-control form-control-sm" required>
                                    @foreach ($statuses as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-7 mb-1">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="اسم الحالة الجديد" required maxlength="60">
                            </div>
                            <div class="col-5 mb-1">
                                <input type="color" name="color" class="form-control form-control-sm" value="#2196F3" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary btn-block">
                                    <i class="feather icon-save"></i> حفظ الاسم واللون
                                </button>
                            </div>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>

        {{-- ═══════════ الأنواع ═══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-tag"></i> أنواع المهام</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-1">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th style="width: 90px">اللون</th>
                                    <th style="width: 70px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr>
                                        <td>
                                            <i class="{{ $type['icon'] ?? 'feather icon-check-square' }}"></i>
                                            {{ $type['name'] ?? '' }}
                                        </td>
                                        <td>
                                            <span class="d-inline-block rounded"
                                                  style="width:16px;height:16px;vertical-align:middle;background-color: {{ $type['color'] ?? '#ccc' }}"></span>
                                            <small class="text-muted">{{ $type['color'] ?? '' }}</small>
                                        </td>
                                        <td class="text-center">
                                            @permission('tasks.settings.global.edit')
                                                <button type="button" class="btn btn-sm btn-outline-secondary delete-item"
                                                        data-group="types" data-id="{{ $type['id'] ?? '' }}"
                                                        data-name="{{ $type['name'] ?? '' }}">
                                                    <i class="feather icon-rotate-ccw"></i>
                                                </button>
                                            @endpermission
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @permission('tasks.settings.global.edit')
                        <form method="POST" action="{{ route('taskmanager.global-settings.types.store') }}" class="row">
                            @csrf
                            <div class="col-12 mb-1">
                                <select name="id" class="form-control form-control-sm" required>
                                    @foreach ($types as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-7 mb-1">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="اسم النوع الجديد" required maxlength="60">
                            </div>
                            <div class="col-5 mb-1">
                                <input type="color" name="color" class="form-control form-control-sm" value="#9C27B0" required>
                            </div>
                            <div class="col-12 mb-1">
                                <input type="text" name="icon" class="form-control form-control-sm"
                                       placeholder="أيقونة (مثل feather icon-book)" maxlength="80">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary btn-block">
                                    <i class="feather icon-save"></i> حفظ الاسم واللون
                                </button>
                            </div>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>

        {{-- ═══════════ الأولويات ═══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-flag"></i> الأولويات</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-1">
                            <thead>
                                <tr>
                                    <th>الأولوية</th>
                                    <th style="width: 70px">المستوى</th>
                                    <th style="width: 70px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($priorities as $priority)
                                    <tr>
                                        <td>
                                            <span class="d-inline-block rounded"
                                                  style="width:16px;height:16px;vertical-align:middle;background-color: {{ $priority['color'] ?? '#ccc' }}"></span>
                                            {{ $priority['name'] ?? '' }}
                                        </td>
                                        <td class="text-center">{{ $priority['level'] ?? '—' }}</td>
                                        <td class="text-center">
                                            @permission('tasks.settings.global.edit')
                                                <button type="button" class="btn btn-sm btn-outline-secondary delete-item"
                                                        data-group="priorities" data-id="{{ $priority['id'] ?? '' }}"
                                                        data-name="{{ $priority['name'] ?? '' }}">
                                                    <i class="feather icon-rotate-ccw"></i>
                                                </button>
                                            @endpermission
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @permission('tasks.settings.global.edit')
                        <form method="POST" action="{{ route('taskmanager.global-settings.priorities.store') }}" class="row">
                            @csrf
                            <div class="col-12 mb-1">
                                <select name="id" class="form-control form-control-sm" required>
                                    @foreach ($priorities as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-7 mb-1">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="اسم الأولوية الجديد" required maxlength="60">
                            </div>
                            <div class="col-5 mb-1">
                                <input type="color" name="color" class="form-control form-control-sm" value="#FF9800" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary btn-block">
                                    <i class="feather icon-save"></i> حفظ الاسم واللون
                                </button>
                            </div>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ التنبيهات ═══════════ --}}
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-bell"></i> التنبيهات الافتراضية</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('taskmanager.global-settings.notifications.update') }}">
                @csrf
                @method('PUT')
                <div class="row">
                    @foreach ([
                        'task_created' => 'عند إنشاء مهمة',
                        'task_assigned' => 'عند إسناد مهمة',
                        'task_updated' => 'عند تعديل مهمة',
                        'comment_added' => 'عند إضافة تعليق',
                        'status_changed' => 'عند تغيير الحالة',
                        'deadline_approaching' => 'عند اقتراب الموعد النهائي',
                    ] as $key => $label)
                        <div class="col-md-4 col-12 mb-1">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="notif-{{ $key }}"
                                       name="{{ $key }}" value="1"
                                       @checked(data_get($notifications, $key, false))
                                       @if (! hasPermission('tasks.settings.global.edit')) disabled @endif>
                                <label class="custom-control-label" for="notif-{{ $key }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                @permission('tasks.settings.global.edit')
                    <div class="text-right mt-1">
                        <a href="{{ route('SittingAccount.dashboard') }}" class="btn btn-outline-secondary">
                            {{ __('settings.common.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('settings.common.save') }}
                        </button>
                    </div>
                @endpermission
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // الحذف يمرّ بـ DELETE لا بنموذجٍ لكل صفّ: ثلاثون بنداً تعني
            // ثلاثين نموذجاً مخفيّاً في الصفحة بلا داعٍ
            document.querySelectorAll('.delete-item').forEach(function (button) {
                button.addEventListener('click', function () {
                    var group = this.dataset.group;
                    var id = this.dataset.id;
                    var name = this.dataset.name;

                    if (!confirm('إعادة «' + name + '» إلى اسمه ولونه الافتراضيين؟')) {
                        return;
                    }

                    // العنوان من `route()` بعنصرين نائبين لا مركَّباً بـ`url()`:
                    // المسار تحت بادئة اللغة، وتركيبُه يدوياً يكسره لأي لغةٍ
                    // تُضاف لاحقاً
                    var url = '{{ route('taskmanager.global-settings.destroy', ['group' => '__GROUP__', 'id' => '__ID__']) }}'
                        .replace('__GROUP__', encodeURIComponent(group))
                        .replace('__ID__', encodeURIComponent(id));

                    fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    })
                        .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
                        .then(function (result) {
                            if (result.ok && result.body.success) {
                                window.location.reload();
                            } else {
                                alert(result.body.message || 'تعذّرت الإعادة.');
                            }
                        })
                        .catch(function () { alert('تعذّر الاتصال بالخادم.'); });
                });
            });
        });
    </script>
@endsection
