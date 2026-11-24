@extends('hr::master')

@section('title')
    تعديل مستوى وظيفي: {{ $functionalLevel->name }}
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل مستوى وظيفي: '.$functionalLevel->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'المستويات الوظيفية' => route('ManagingFunctionalLevels.index'),
            $functionalLevel->name => hasPermission('job_levels.show') ? route('ManagingFunctionalLevels.show', $functionalLevel->id) : null,
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('ManagingFunctionalLevels.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="functional_level_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="functional_level_form" action="{{ route('ManagingFunctionalLevels.update', $functionalLevel->id) }}" method="POST">
        @csrf

        @include('hr::organizational_structure.partials._simple_form', [
            'record' => $functionalLevel,
            'sectionTitle' => 'بيانات المستوى الوظيفي',
            'subject' => 'المستوى الوظيفي',
            'namePlaceholder' => 'مثال: مستوى أوّل',
            'nameTip' => 'تغييرُ الاسم يسري فوراً على كلّ من هو في هذا المستوى.',
            'settingsGroup' => 'org_job_level_rules',
        ])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('ManagingFunctionalLevels.index') }}" class="btn btn-outline-secondary">
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
