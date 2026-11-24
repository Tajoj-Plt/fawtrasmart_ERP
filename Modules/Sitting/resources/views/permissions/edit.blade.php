@extends('sitting::master')

@section('title', 'تعديل صلاحية')

@section('content')
    @php
        use App\Support\PermissionLabels;

        $moduleKey = $permission->module ?: PermissionLabels::UNCLASSIFIED;
    @endphp

    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('settings.permissions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="permission_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التغييرات
            </button>
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'تعديل: '.PermissionLabels::describe($permission->name),
            'icon' => 'key',
            'sub' => $permission->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الصلاحيات' => route('settings.permissions.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" style="border-radius:10px">
                <strong>تعذّر الحفظ</strong>
                <ul class="mb-0 mt-1" style="padding-inline-start:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon">
                    <i class="feather icon-{{ PermissionLabels::groupIcon($moduleKey) }}"></i>
                </span>
                <span class="stk-metric__label">القسم</span>
                <span class="stk-metric__value">
                    {{ $permission->module_label ?: PermissionLabels::groupLabel($moduleKey) }}
                </span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-grid"></i></span>
                <span class="stk-metric__label">المجموعة</span>
                <span class="stk-metric__value">{{ $permission->group_label ?: 'غير مصنَّفة' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">الأدوار المستعمِلة</span>
                <span class="stk-metric__value stk-num">{{ $permission->roles_count }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-lock"></i></span>
                <span class="stk-metric__label">الحارس</span>
                <span class="stk-metric__value">{{ $permission->guard_name }}</span>
            </div>
        </div>

        <form id="permission_form" method="POST" action="{{ route('settings.permissions.update', $permission->id) }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-key"></i> بيانات الصلاحية</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">اسم الصلاحية</label>
                            <input type="text" id="name" name="name" class="form-control stk-num"
                                value="{{ old('name', $permission->name) }}" required maxlength="190">
                            <small class="stk-help">
                                التسمية والقسم يتبعان هذا الاسم في الكتالوج — تغييره يجعل الصلاحية
                                غير مصنَّفة حتى تُضاف باسمها الجديد إلى كتالوج قسمها.
                            </small>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="label_display">التسمية المعروضة</label>
                            <input type="text" id="label_display" class="form-control"
                                value="{{ $permission->label ?: PermissionLabels::describe($permission->name) }}" readonly>
                            <small class="stk-help">
                                تكتبها المزامنة من كتالوج القسم — لتغييرها عدّل الكتالوج ثم
                                <span class="stk-num">permissions:sync-catalog</span>.
                            </small>
                        </div>
                    </div>

                    @if ($permission->roles_count > 0)
                        <div class="alert alert-warning mb-0">
                            <i class="feather icon-alert-triangle"></i>
                            هذه الصلاحية مُسنَدة إلى أدوارٍ قائمة، والكود يحرس بها مساراتٍ باسمها
                            الحالي. تغيير الاسم يقطع ذلك الحرس حتى يُحدَّث في الكود.
                        </div>
                    @endif
                </div>
            </div>
        </form>
    </div>
@endsection
