@extends('hr::master')

@section('title', 'إضافة خصمية')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'إضافة خصمية',
        'icon' => 'minus-circle',
        'crumbs' => [
            'الخصميات' => route('hr.deductions.index'),
            'إضافة' => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @include('hr::components.guide', [
            'key' => 'hr-deductions.create',
            'title' => 'ما الذي يترتّب على إضافة خصمية؟',
            'lead' => 'الخصميةُ تُنشأ «بانتظار الاعتماد» ولا أثرَ ماليَّ لها حتى تُعتمد.',
            'blocks' => [
                'عند الاعتماد' => [
                    'يُنشأ قيد: مدين مستحقات الموظف / دائن حساب نوع الخصمية — بتاريخ الخصمية إن كانت فترتُه مفتوحة.',
                    'وإن لم يُربط حسابٌ للنوع ولا حسابٌ عامّ رُفض الاعتمادُ بالسبب.',
                ],
                'عند صرف الراتب' => [
                    'تُقتطع من صافي الراتب دون قيدٍ ثانٍ. وما لا يتّسع له الراتبُ يبقى للشهر التالي «مسوّاةً جزئياً».',
                ],
            ],
        ])

        <form method="POST" action="{{ route('hr.deductions.store') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    @if (! empty($nextDeductionCode))
                        <p class="stk-help mb-3"><i class="feather icon-hash"></i> الرقم المتوقَّع: <strong>{{ $nextDeductionCode }}</strong> — يُصرف عند الحفظ.</p>
                    @endif
                    @include('hr::salaries.deductions._form')
                </div>
                <div class="stk-form-foot">
                    <a href="{{ route('hr.deductions.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ الخصمية</button>
                </div>
            </div>
        </form>
    </div>
@endsection
