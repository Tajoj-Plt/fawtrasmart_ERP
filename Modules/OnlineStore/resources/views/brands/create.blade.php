@extends('onlinestore::master')

@section('title', 'إضافة علامة تجارية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.brands.create'))
                <button type="submit" form="brand_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة علامة تجارية',
            'icon' => 'award',
            'sub' => 'علامة تجارية جديدة تُعرض في كتالوج المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'العلامات التجارية' => route('brands.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="brand_form" action="{{ route('brands.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات العلامة</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم العلامة التجارية</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="slug">الرابط (Slug)</label>
                            <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug') }}" dir="ltr">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="website">الموقع الإلكتروني</label>
                            <input type="url" name="website" id="website" class="form-control" value="{{ old('website') }}" dir="ltr" placeholder="https://">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="logo">الشعار</label>
                            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="sort_order">الترتيب</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_active">حالة التفعيل</label>
                            <select name="is_active" id="is_active" class="form-control">
                                <option value="1" @selected(old('is_active', 1) == 1)>نشط</option>
                                <option value="0" @selected((string) old('is_active', 1) === '0')>غير نشط</option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_featured">علامة مميزة</label>
                            <select name="is_featured" id="is_featured" class="form-control">
                                <option value="0" @selected((string) old('is_featured', 0) === '0')>لا</option>
                                <option value="1" @selected(old('is_featured') == 1)>نعم</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-search"></i> تحسين محركات البحث (SEO)</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="meta_title">Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title" class="form-control" value="{{ old('meta_title') }}" maxlength="60">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="meta_keywords">Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="{{ old('meta_keywords') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="meta_description">Meta Description</label>
                            <input type="text" name="meta_description" id="meta_description" class="form-control" value="{{ old('meta_description') }}" maxlength="160">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
$('#name').on('input', function() {
    var slug = $(this).val().toLowerCase().replace(/[^\w؀-ۿ\s-]/g, '').replace(/\s+/g, '-').trim();
    if (!$('#slug').val() || $('#slug').data('auto')) { $('#slug').val(slug).data('auto', true); }
});
$('#slug').on('input', function() { $(this).data('auto', false); });
</script>
@endsection
