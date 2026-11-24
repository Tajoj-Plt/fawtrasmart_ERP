@extends('onlinestore::master')

@section('title', 'إضافة خزينة جديدة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('account.treasury.create'))
                <button type="submit" form="treasury_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة خزينة جديدة',
            'icon' => 'archive',
            'sub' => 'خزينة نقدية جديدة تابعة للمتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن والحسابات البنكية' => route('store_treasury.index'),
                'إضافة خزينة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="treasury_form" class="form form-vertical" action="{{ route('store_treasury.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الخزينة</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="type">النوع</label>
                            <input type="text" disabled id="type" class="form-control" value="خزينة">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="is_active">الحالة</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1" @selected(old('is_active', 1) == 1)>نشط</option>
                                <option value="0" @selected(old('is_active', 1) == 0)>غير نشط</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم الخزينة</label>
                            <input type="text" id="name" class="form-control" name="name" value="{{ old('name') }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        @include('onlinestore::treasury.partials._branch-field', ['branches' => $branches, 'selected' => $defaultBranchId])

                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea name="description" class="form-control" id="description" rows="2">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
