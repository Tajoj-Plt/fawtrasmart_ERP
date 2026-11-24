@extends('sitting::master')

@section('title', 'دور جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('settings.roles.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="role_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ الدور
            </button>
        @endsection

        @include('sitting::components.page-head', [
            'title' => 'دور جديد',
            'icon' => 'shield',
            'sub' => 'مجموعة صلاحيات تُسنَد إلى المستخدمين دفعةً واحدة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات' => route('SittingAccount.dashboard'),
                'الأدوار' => route('settings.roles.index'),
                'جديد' => null,
            ],
        ])

        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" style="border-radius:10px">
                <strong>تعذّر الحفظ</strong>
                <ul class="mb-0 mt-1" style="padding-inline-start:1.2rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="role_form" method="POST" action="{{ route('settings.roles.store') }}">
            @csrf
            @include('sitting::roles._form', ['assigned' => old('permissions', [])])
        </form>
    </div>
@endsection
