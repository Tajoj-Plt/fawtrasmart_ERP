@extends('hr::master')

@section('title', 'تعديل خصمية ' . ($deduction->code ?: '#' . $deduction->id))

@section('content')
    @include('stock::components.page-head', [
        'title' => 'تعديل خصمية ' . ($deduction->code ?: '#' . $deduction->id),
        'icon' => 'edit-2',
        'crumbs' => [
            'الخصميات' => route('hr.deductions.index'),
            ($deduction->code ?: '#' . $deduction->id) => route('hr.deductions.show', $deduction->id),
            'تعديل' => null,
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

        <div class="alert alert-info">
            <i class="feather icon-info"></i>
            تُعدَّل الخصميةُ ما دامت بانتظار الاعتماد وحدها — بعد الاعتماد لها قيدٌ محاسبي، وتصحيحُها بالإلغاء ثمّ إنشاء خصميةٍ جديدة.
        </div>

        <form method="POST" action="{{ route('hr.deductions.update', $deduction->id) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">
                    @include('hr::salaries.deductions._form', ['deduction' => $deduction])
                </div>
                <div class="stk-form-foot">
                    <a href="{{ route('hr.deductions.show', $deduction->id) }}" class="btn btn-outline-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ التعديل</button>
                </div>
            </div>
        </form>
    </div>
@endsection
