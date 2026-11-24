@extends('onlinestore::master')

@section('title', 'تعديل الخزينة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('account.treasury.edit'))
                <button type="submit" form="treasury_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> تحديث
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل الخزينة',
            'icon' => 'archive',
            'sub' => $treasury->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن والحسابات البنكية' => route('store_treasury.index'),
                'تعديل الخزينة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="treasury_form" class="form form-vertical" action="{{ route('store_treasury.update', $treasury->id) }}" method="POST" enctype="multipart/form-data">
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
                            {{-- كان الحقل `status` بقيمٍ مقلوبة (0 = نشط) ولا يقرؤه الخادم
                                 أصلاً — فتغيير الحالة من هنا لم يكن يُحفظ --}}
                            <label for="is_active">الحالة</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1" @selected(old('is_active', $treasury->is_active) == 1)>نشط</option>
                                <option value="0" @selected(old('is_active', $treasury->is_active) == 0)>غير نشط</option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم الخزينة</label>
                            <input type="text" id="name" class="form-control" name="name" value="{{ old('name', $treasury->name) }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        @include('onlinestore::treasury.partials._branch-field', ['branches' => $branches, 'selected' => $treasury->branch_id])

                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            {{-- الوصف يسكن صفّ `treasuries` لا الحساب --}}
                            <textarea name="description" class="form-control" id="description" rows="2">{{ old('description', $treasuryRow->description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
