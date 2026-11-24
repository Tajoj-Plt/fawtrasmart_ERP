@extends('onlinestore::master')

@section('title', 'تعديل العلامة التجارية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.brands.edit'))
                <button type="submit" form="brand_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> تحديث
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل العلامة التجارية',
            'icon' => 'award',
            'sub' => $brand->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'العلامات التجارية' => route('brands.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="brand_form" action="{{ route('brands.update', $brand) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات العلامة</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم العلامة التجارية</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $brand->name) }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="slug">الرابط (Slug)</label>
                            <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $brand->slug) }}" dir="ltr">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="website">الموقع الإلكتروني</label>
                            <input type="url" name="website" id="website" class="form-control" value="{{ old('website', $brand->website) }}" dir="ltr" placeholder="https://">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="logo">
                                الشعار
                                @if ($brand->logo)
                                    <span class="badge badge-light-success">موجود</span>
                                @endif
                            </label>
                            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        </div>

                        <div class="form-group col-md-2">
                            <label for="sort_order">الترتيب</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', $brand->sort_order) }}" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_active">حالة التفعيل</label>
                            <select name="is_active" id="is_active" class="form-control">
                                <option value="1" @selected(old('is_active', $brand->is_active) == 1)>نشط</option>
                                <option value="0" @selected(old('is_active', $brand->is_active) == 0)>غير نشط</option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_featured">علامة مميزة</label>
                            <select name="is_featured" id="is_featured" class="form-control">
                                <option value="0" @selected(old('is_featured', $brand->is_featured) == 0)>لا</option>
                                <option value="1" @selected(old('is_featured', $brand->is_featured) == 1)>نعم</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" class="form-control" rows="2">{{ old('description', $brand->description) }}</textarea>
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
                            <input type="text" name="meta_title" id="meta_title" class="form-control" value="{{ old('meta_title', $brand->meta_title) }}" maxlength="60">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="meta_keywords">Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="{{ old('meta_keywords', $brand->meta_keywords) }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="meta_description">Meta Description</label>
                            <input type="text" name="meta_description" id="meta_description" class="form-control" value="{{ old('meta_description', $brand->meta_description) }}" maxlength="160">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
