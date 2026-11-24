@extends('hr::master')

@section('title', 'إنشاء قسيمة راتب')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('payslips.index'))
                <a href="{{ route('salarySlip.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-slash"></i> إلغاء
                </a>
            @endif
            <button type="submit" form="salary_slip_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ كمسودة
            </button>
        @endsection

        @include('stock::components.page-head', [
            'title' => 'إنشاء قسيمة راتب',
            'icon' => 'file-plus',
            'sub' => 'القسيمة اتفاقٌ مستمرّ يولّد صرفةً في كل دورة — لا تصرف مالاً بذاتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'قسائم الرواتب' => route('salarySlip.index'),
                'إضافة' => null,
            ],
        ])

        @include('hr::components.guide', [
            'key' => 'hr-salary-slips.create',
            'title' => 'قبل أن تحفظ القسيمة',
            'lead' => 'اختر المسير أوّلاً: قائمةُ الموظفين تُملأ بموظفيه وحدهم، والخادمُ يرفض موظفاً لا يشمله المسير.',
            'blocks' => [
                'المبالغ' => [
                    'الأساسيُّ يُحمَّل من العقد الساري. وموظفٌ بلا عقدٍ يُحسب راتبُه من بنود الاستحقاق — الأساسيُّ عندها اختياريّ، والشرطُ مبلغٌ موجبٌ في القسيمة.',
                    'المبلغُ المكتوب في البند يتقدّم على صيغته، والصيغةُ تُحسب على الأساسيّ (basic).',
                ],
                'ما لا يُدخل هنا' => [
                    'السلفُ والخصمياتُ المعتمدةُ والتأميناتُ تُخصم تلقائياً وقت الصرف، والعمولاتُ نظامٌ مستقلّ.',
                ],
            ],
            'facts' => ['تُحفظ مسودة', 'الاعتمادُ يبدأ توليدَ الصرفات'],
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

        {{-- رقمُ القسيمة معاينةٌ لا إدخال: يُصرف من «الترقيم المتسلسل» عند الحفظ، ولا name له فلا يُرسَل --}}
        @push('ps-code-field')
            <div class="form-group col-md-3">
                <label for="salary_slip_code">رقم القسيمة</label>
                <input type="text" class="form-control stk-num" id="salary_slip_code" value="{{ $nextSlipCode ?: 'يُولَّد عند الحفظ' }}" readonly disabled>
                <small class="stk-help">من إعدادات الترقيم المتسلسل — يُصرف عند الحفظ</small>
            </div>
        @endpush

        @permission('payslips.create')
            <form id="salary_slip_form" action="{{ route('salarySlip.store') }}" method="POST">
                @csrf
                @include('hr::salaries.salary_slip.partials.form', ['slip' => null])
            </form>
        @endpermission
    </div>
@endsection
