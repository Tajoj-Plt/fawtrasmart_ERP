@extends('hr::master')

@section('title')
    تعديل مسمى وظيفي: {{ $title->name }}
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل مسمى وظيفي: '.$title->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'المسميات الوظيفية' => route('JobTitles.index'),
            $title->name => hasPermission('job_titles.show') ? route('JobTitles.show', $title->id) : null,
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('JobTitles.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="job_title_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="job_title_form" action="{{ route('JobTitles.update', $title->id) }}" method="POST">
        @csrf

        @include('hr::organizational_structure.JobTitle._form', ['jobTitle' => $title])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('JobTitles.index') }}" class="btn btn-outline-secondary">
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
