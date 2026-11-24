@extends('hr::master')

@section('title')
    إضافة مسمى وظيفي
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة مسمى وظيفي',
        'icon' => 'briefcase',
        'crumbs' => ['الرئيسية' => url('/'), 'المسميات الوظيفية' => route('JobTitles.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('JobTitles.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="job_title_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ المسمى</button>',
    ])

    @include('layouts.alerts.error')

    @include('hr::components.guide', [
        'key' => 'hr-job-titles.create',
        'title' => 'قبل أن تضيف مسمى وظيفياً',
        'lead' => 'المسمّى يُختار في ملفّ الموظف وعقده، وتُخصَّص به قواعدُ الحضور والإجازات والورديات.',
        'blocks' => [
            'ما يترتّب على الحفظ' => [
                'يظهر المسمّى فوراً في حقل «المسمى الوظيفي» في ملفّ الموظف والعقد وفي فلاتر التقارير.',
                'والقسمُ إلزاميّ: كلُّ مسمّى يتبع قسماً واحداً.',
            ],
        ],
    ])

    <form id="job_title_form" action="{{ route('JobTitles.store') }}" method="POST">
        @csrf

        @include('hr::organizational_structure.JobTitle._form', ['jobTitle' => null])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('JobTitles.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ المسمى
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
