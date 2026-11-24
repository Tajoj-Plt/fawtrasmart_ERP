@extends('stock::master')

@section('title', 'تعديل الوحدة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل الوحدة',
            'icon' => 'edit-2',
            'sub' => $unit->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الوحدات' => route('units.index'),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('units.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="unit_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="unit_form" action="{{ route('units.update', $unit->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> بيانات الوحدة</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">اسم الوحدة</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name', $unit->name) }}" placeholder="مثال: كيلوجرام">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="symbol">الرمز</label>
                                        <input type="text" id="symbol" name="symbol" maxlength="10"
                                               class="form-control text-center @error('symbol') is-invalid @enderror"
                                               value="{{ old('symbol', $unit->symbol) }}" placeholder="كجم">
                                        @error('symbol')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <span class="stk-help">اختصار يظهر في الجداول</span>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="description">الوصف</label>
                                        <textarea id="description" name="description" rows="3"
                                                  class="form-control @error('description') is-invalid @enderror"
                                                  placeholder="متى تُستعمل هذه الوحدة">{{ old('description', $unit->description) }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-toggle-right"></i> الحالة</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch">
                                <input type="checkbox" name="is_active" value="1"
                                       {{ old('is_active', $unit->is_active) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">الوحدة نشطة</span>
                                    <span class="stk-switch__hint">غير النشطة لا تظهر عند ربط صنف بوحداته</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('units.index') }}" class="btn btn-outline-secondary">
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
