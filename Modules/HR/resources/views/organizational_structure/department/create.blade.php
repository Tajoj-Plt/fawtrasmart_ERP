@extends('hr::master')

@section('title')
    إضافة قسم
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة قسم',
        'icon' => 'layers',
        'crumbs' => ['الرئيسية' => url('/'), 'الأقسام' => route('department.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('department.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="department_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ القسم</button>',
    ])

    @include('layouts.alerts.error')

    @include('hr::components.guide', [
        'key' => 'hr-departments.create',
        'title' => 'قبل أن تضيف قسماً',
        'lead' => 'القسمُ وحدةٌ في الهيكل التنظيمي يُنسَب إليها الموظفون من ملفّاتهم، وتُنسَب إليها المسمّياتُ الوظيفية.',
        'blocks' => [
            'ما يترتّب على الحفظ' => [
                'يظهر القسمُ فوراً في حقل «القسم» في ملفّ الموظف، وفي نموذج المسمّى الوظيفي، وفي فلاتر التقارير.',
                'و«المديرون المعنيّون» قائمةٌ إدارية فقط: لا تنقل أحداً إلى القسم ولا تُخرجه منه.',
            ],
        ],
    ])

    <form id="department_form" action="{{ route('department.store') }}" method="POST">
        @csrf

        @include('hr::organizational_structure.department._form', ['department' => null])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('department.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ القسم
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
