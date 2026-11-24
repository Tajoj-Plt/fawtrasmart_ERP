@extends('hr::master')

@section('title')
    إضافة نوع وظيفة
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة نوع وظيفة',
        'icon' => 'tag',
        'crumbs' => ['الرئيسية' => url('/'), 'أنواع الوظائف' => route('ManagingJobTypes.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('ManagingJobTypes.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="job_type_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ النوع</button>',
    ])

    @include('layouts.alerts.error')

    @include('hr::components.guide', [
        'key' => 'hr-job-types.create',
        'title' => 'قبل أن تضيف نوع وظيفة',
        'lead' => 'نوعُ الوظيفة طبيعةُ الارتباط الوظيفي (دوامٌ كامل، جزئيّ، مؤقّت…) ويُختار في ملفّ الموظف.',
        'blocks' => [
            'ما لا يعنيه' => [
                'هو غيرُ خانة «موظف / مستخدم» في نموذج الموظف — تلك تقرّر إنشاء الخزينة والمستودع الافتراضيَّين.',
            ],
        ],
    ])

    <form id="job_type_form" action="{{ route('ManagingJobTypes.store') }}" method="POST">
        @csrf

        @include('hr::organizational_structure.partials._simple_form', [
            'record' => null,
            'sectionTitle' => 'بيانات نوع الوظيفة',
            'subject' => 'نوع الوظيفة',
            'namePlaceholder' => 'مثال: دوام كامل',
            'nameTip' => 'طبيعةُ الارتباط الوظيفي كما تظهر في ملفّ الموظف والتقارير.',
            'settingsGroup' => 'org_job_type_rules',
        ])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('ManagingJobTypes.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ النوع
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
