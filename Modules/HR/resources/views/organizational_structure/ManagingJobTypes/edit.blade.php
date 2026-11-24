@extends('hr::master')

@section('title')
    تعديل نوع وظيفة: {{ $type->name }}
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل نوع وظيفة: '.$type->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أنواع الوظائف' => route('ManagingJobTypes.index'),
            $type->name => hasPermission('job_types.show') ? route('ManagingJobTypes.show', $type->id) : null,
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('ManagingJobTypes.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="job_type_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="job_type_form" action="{{ route('ManagingJobTypes.update', $type->id) }}" method="POST">
        @csrf

        @include('hr::organizational_structure.partials._simple_form', [
            'record' => $type,
            'sectionTitle' => 'بيانات نوع الوظيفة',
            'subject' => 'نوع الوظيفة',
            'namePlaceholder' => 'مثال: دوام كامل',
            'nameTip' => 'تغييرُ الاسم يسري فوراً على كلّ من يحمل هذا النوع.',
            'settingsGroup' => 'org_job_type_rules',
        ])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('ManagingJobTypes.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ التعديلات
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
