@extends('hr::master')

@section('title')
    إضافة مستوى وظيفي
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة مستوى وظيفي',
        'icon' => 'bar-chart-2',
        'crumbs' => ['الرئيسية' => url('/'), 'المستويات الوظيفية' => route('ManagingFunctionalLevels.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('ManagingFunctionalLevels.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="functional_level_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ المستوى</button>',
    ])

    @include('layouts.alerts.error')

    @include('hr::components.guide', [
        'key' => 'hr-functional-levels.create',
        'title' => 'قبل أن تضيف مستوى وظيفياً',
        'lead' => 'المستوى درجةٌ في التدرّج الوظيفي (مبتدئ، أوّل، خبير…) يُختار في ملفّ الموظف وعقده.',
        'blocks' => [
            'ما يترتّب على الحفظ' => [
                'يظهر المستوى فوراً في حقل «المستوى الوظيفي» في ملفّ الموظف والعقد، وتُخصَّص به سياساتُ الإجازات وقواعدُ الحضور.',
            ],
        ],
    ])

    <form id="functional_level_form" action="{{ route('ManagingFunctionalLevels.store') }}" method="POST">
        @csrf

        @include('hr::organizational_structure.partials._simple_form', [
            'record' => null,
            'sectionTitle' => 'بيانات المستوى الوظيفي',
            'subject' => 'المستوى الوظيفي',
            'namePlaceholder' => 'مثال: مستوى أوّل',
            'nameTip' => 'اسمٌ يدلّ على الدرجة في التدرّج الوظيفي — يظهر في ملفّ الموظف والعقد والتقارير.',
            'settingsGroup' => 'org_job_level_rules',
        ])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('ManagingFunctionalLevels.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ المستوى
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
