@extends('hr::master')

@section('title', 'تعديل مسير الرواتب')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('payroll.index'))
                <a href="{{ route('PayrollProcess.show', $payroll->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-slash"></i> إلغاء
                </a>
            @endif
            <button type="submit" form="payroll_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التعديلات
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'تعديل مسير الرواتب — '.$payroll->name,
            'icon' => 'edit-2',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسيرات الرواتب' => route('PayrollProcess.index'),
                $payroll->name => route('PayrollProcess.show', $payroll->id),
                'تعديل' => null,
            ],
        ])

        @include('hr::components.guide', [
            'key' => 'hr-payroll-process.edit',
            'title' => 'ما الذي يتغيّر بالتعديل؟',
            'lead' => 'الحفظُ يُعيد حسابَ موظفي المسير من القواعد أو الاختيار الجديد.',
            'blocks' => [
                'ما لا يتغيّر' => [
                    'موظفٌ له قسيمةٌ في المسير يبقى فيه — فكُّه يوقف صرفات قسيمته بصمت.',
                    'وموظفٌ خارج نطاق صلاحيتك يبقى كما هو: التعديلُ يرى بعضَ المسير لا كلَّه.',
                ],
            ],
        ])

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @include('layouts.alerts.error')

        @permission('payroll.edit')
            <form id="payroll_form" action="{{ route('PayrollProcess.update', $payroll->id) }}" method="post">
                @csrf
                @method('PUT')
                @include('hr::salaries.payroll_ process.partials.form')
            </form>
        @endpermission
    </div>
@endsection
