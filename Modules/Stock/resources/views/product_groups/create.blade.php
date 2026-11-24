@extends('stock::master')

@section('title', 'إضافة مجموعة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إضافة مجموعة',
            'icon' => 'plus-circle',
            'sub' => 'مجموعة جديدة في شجرة تجميع الأصناف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المجموعات' => route('product_groups.index'),
                'إضافة' => null,
            ],
            'actions' => '<a href="' . route('product_groups.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="group_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ المجموعة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="group_form" action="{{ route('product_groups.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> بيانات المجموعة</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">اسم المجموعة</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}" placeholder="مثال: مواد غذائية">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="code">الكود</label>
                                        <input type="text" id="code" name="code"
                                               class="form-control text-center stk-num @error('code') is-invalid @enderror"
                                               value="{{ old('code') }}" placeholder="اختياري">
                                        @error('code')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="parent_id">المجموعة الأب</label>
                                        <select id="parent_id" name="parent_id"
                                                class="form-control select2 @error('parent_id') is-invalid @enderror">
                                            <option value="">مجموعة رئيسية (بلا أب)</option>
                                            @foreach ($parentGroups ?? [] as $parent)
                                                <option value="{{ $parent->id }}"
                                                    {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                                    {{ str_repeat('— ', max(0, ($parent->level ?? 1) - 1)) }}{{ $parent->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('parent_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <span class="stk-help">المستوى يُحتسب تلقائياً من موضع المجموعة في الشجرة</span>
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
                                       {{ old('is_active', '1') ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">المجموعة نشطة</span>
                                    <span class="stk-switch__hint">غير النشطة لا تظهر عند تصنيف الأصناف</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('product_groups.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ المجموعة
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }
        });
    </script>
@endsection
