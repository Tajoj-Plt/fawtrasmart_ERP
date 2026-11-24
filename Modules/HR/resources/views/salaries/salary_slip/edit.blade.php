@extends('hr::master')

@section('title', 'تعديل قسيمة '.($slip->code ?: '#'.$slip->id))

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('payslips.show'))
                <a href="{{ route('salarySlip.show', $slip->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-slash"></i> إلغاء
                </a>
            @endif
            <button type="submit" form="salary_slip_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التعديلات
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'تعديل قسيمة '.($slip->code ?: '#'.$slip->id),
            'icon' => 'edit-2',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قسائم الرواتب' => route('salarySlip.index'),
                ($slip->code ?: '#'.$slip->id) => route('salarySlip.show', $slip->id),
                'تعديل' => null,
            ],
        ])

        @include('hr::components.guide', [
            'key' => 'hr-salary-slips.edit',
            'title' => 'ما الذي يتغيّر بالتعديل؟',
            'lead' => 'الصرفاتُ المصروفة سابقاً تبقى بمبالغها وقيودها. الصرفاتُ التي تُولَّد بعد الحفظ تأخذ المبالغ الجديدة.',
            'blocks' => [
                'ما لا يتغيّر' => ['الموظفُ والمسير — القسيمةُ اتفاقٌ لموظفٍ بعينه تحت مسيرٍ بعينه.'],
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

        @permission('payslips.edit')
            <form id="salary_slip_form" action="{{ route('salarySlip.update', $slip->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('hr::salaries.salary_slip.partials.form')
            </form>
        @endpermission
    </div>
@endsection
