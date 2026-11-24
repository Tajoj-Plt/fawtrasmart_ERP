@extends('hr::master')

@section('title', 'مسير رواتب جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('payroll.index'))
                <a href="{{ route('PayrollProcess.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-slash"></i> إلغاء
                </a>
            @endif
            <button type="submit" form="payroll_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ المسير
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'مسير رواتب جديد',
            'icon' => 'plus-circle',
            'sub' => 'يجمع الموظفين تحت فترة تغطية واحدة — لا يصرف راتباً',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسيرات الرواتب' => route('PayrollProcess.index'),
                'إضافة' => null,
            ],
        ])

        @include('hr::components.guide', [
            'key' => 'hr-payroll-process.create',
            'title' => 'قبل أن تحفظ المسير — ما الذي يترتّب على الحفظ؟',
            'lead' => 'يُحفظ المسيرُ «مسودة» ومعه قائمةُ موظفيه كما هي لحظةَ الحفظ. اعتمادُه من شاشة العرض هو ما يسمح بإنشاء القسائم تحته.',
            'blocks' => [
                'بالقواعد' => [
                    'الفرعُ المختار يشمل كلَّ ما تحته من مناطق ومجموعات ومنافذ، وفرعُ الموظف يُقرأ من بطاقته ثمّ من فرعه الأساسيّ ثمّ من حساب دخوله.',
                    '«كل الأقسام» و«كل المسميات» لا تُضيّقان شيئاً. وتظهر تحت القواعد معاينةٌ بعدد الموظفين وأسمائهم قبل الحفظ.',
                ],
                'بالاختيار' => [
                    'القائمةُ فيها الموظفون النشطون ومن تحت التجربة ضمن نطاق صلاحيتك وحده.',
                ],
            ],
            'facts' => ['يُحفظ مسودة', 'لا يُحفظ مسيرٌ بلا موظفين'],
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

        @permission('payroll.create')
            <form id="payroll_form" action="{{ route('PayrollProcess.store') }}" method="post">
                @csrf
                @include('hr::salaries.payroll_ process.partials.form', ['payroll' => null])
            </form>
        @endpermission
    </div>
@endsection
