@extends('onlinestore::master')

@section('title', 'إضافة صنف جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_categories.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.categories.create'))
                <button type="submit" form="categoryForm" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة صنف جديد',
            'icon' => 'folder',
            'sub' => 'صنف جديد يُعرض في كتالوج المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('store_categories.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form action="{{ route('store_categories.store') }}" method="POST" enctype="multipart/form-data" id="categoryForm">
            @csrf

            <div class="row">
                <!-- القسم الرئيسي -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> المعلومات الأساسية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label for="name" class="stk-req">اسم الصنف</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" placeholder="اسم الصنف">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="description">الوصف</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                                        rows="4" placeholder="وصف الصنف...">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم الجانبي -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> التصنيف</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="main_category">الصنف الرئيسي</label>
                                <select name="main_category" id="main_category" class="form-control select2 @error('main_category') is-invalid @enderror">
                                    <option value="">-- صنف رئيسي --</option>
                                    @foreach ($mainCategories as $mainCat)
                                        <option value="{{ $mainCat->id }}" @selected(old('main_category') == $mainCat->id)>
                                            {{ $mainCat->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">اتركه فارغاً إذا كان هذا صنف رئيسي</small>
                                @error('main_category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-image"></i> صورة الصنف</h6>
                        </div>
                        <div class="card-body">
                            <div class="image-upload-area mb-1">
                                <input type="file" name="image" id="imageInput" class="d-none" accept="image/*">
                                <label for="imageInput" class="image-upload-label">
                                    <div class="upload-placeholder" id="uploadPlaceholder">
                                        <i class="feather icon-upload-cloud"></i>
                                        <span>اضغط لرفع صورة</span>
                                        <small class="text-muted d-block">PNG, JPG حتى 2MB</small>
                                    </div>
                                    <img id="imagePreview" class="img-fluid rounded d-none" alt="معاينة">
                                </label>
                            </div>
                            @error('image')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('styles')
<style>
    .image-upload-area {
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
    }
    .image-upload-area:hover {
        border-color: #7367f0;
        background: #f8f8ff;
    }
    .image-upload-label {
        cursor: pointer;
        margin: 0;
        width: 100%;
    }
    .upload-placeholder {
        padding: 2rem 1rem;
    }
    .upload-placeholder i {
        font-size: 3rem;
        color: #ccc;
        display: block;
        margin-bottom: 0.5rem;
    }
    #imagePreview {
        max-height: 200px;
        width: auto;
        margin: 0 auto;
    }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // معاينة الصورة
    $('#imageInput').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result).removeClass('d-none');
                $('#uploadPlaceholder').addClass('d-none');
            }
            reader.readAsDataURL(file);
        }
    });

    // التحقق من النموذج
    $('#categoryForm').submit(function(e) {
        var name = $('input[name="name"]').val().trim();
        if (!name) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'يرجى إدخال اسم الصنف'
            });
            return false;
        }
    });
});
</script>
@endsection
