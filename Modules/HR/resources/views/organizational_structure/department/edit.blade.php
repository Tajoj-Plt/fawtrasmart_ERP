@extends('hr::master')

@section('title')
    تعديل قسم: {{ $department->name }}
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل قسم: '.$department->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الأقسام' => route('department.index'),
            $department->name => hasPermission('departments.show') ? route('department.show', $department->id) : null,
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('department.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="department_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="department_form" action="{{ route('department.update', $department->id) }}" method="POST">
        @csrf

        @include('hr::organizational_structure.department._form', ['department' => $department, 'selectedEmployees' => $selectedEmployees])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('department.index') }}" class="btn btn-outline-secondary">
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
