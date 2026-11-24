@extends('ecosystem::master')

@section('title', 'إضافة مستوى نقاط جديد')



@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إضافة مستوى نقاط جديد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.tiers.index') }}">مستويات النقاط</a></li>
                        <li class="breadcrumb-item active">إضافة جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.tiers.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.tiers.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ المستوى
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> المعلومات الأساسية</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">اسم المستوى (عربي) <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name') }}" required placeholder="مثال: برونزي">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name_en">اسم المستوى (إنجليزي)</label>
                                    <input type="text" name="name_en" id="name_en" 
                                           class="form-control @error('name_en') is-invalid @enderror" 
                                           value="{{ old('name_en') }}" placeholder="Example: Bronze">
                                    @error('name_en')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select name="user_type" id="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع المستخدم</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ old('user_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\UserType::from($type)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sort_order">الترتيب</label>
                                    <input type="number" name="sort_order" id="sort_order" 
                                           class="form-control @error('sort_order') is-invalid @enderror" 
                                           value="{{ old('sort_order', 0) }}" min="0">
                                    @error('sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">ترتيب ظهور المستوى</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-target"></i> نطاق النقاط</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="min_points">الحد الأدنى للنقاط <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="min_points" id="min_points" 
                                               class="form-control @error('min_points') is-invalid @enderror" 
                                               value="{{ old('min_points', 0) }}" required min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="feather icon-star"></i></span>
                                        </div>
                                    </div>
                                    @error('min_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">أقل عدد نقاط للدخول في هذا المستوى</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_points">الحد الأقصى للنقاط</label>
                                    <div class="input-group">
                                        <input type="number" name="max_points" id="max_points" 
                                               class="form-control @error('max_points') is-invalid @enderror" 
                                               value="{{ old('max_points') }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="feather icon-star"></i></span>
                                        </div>
                                    </div>
                                    @error('max_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">اتركه فارغاً للمستوى الأعلى (بلا حد)</small>
                                </div>
                            </div>
                        </div>

                        @if($existingTiers->isNotEmpty())
                            <div class="alert alert-info mt-2">
                                <h6><i class="feather icon-info"></i> المستويات الحالية:</h6>
                                @foreach($existingTiers as $userType => $tiers)
                                    <div class="mb-1">
                                        <strong>{{ \App\Enums\UserType::from($userType)->label() }}:</strong>
                                        @foreach($tiers as $tier)
                                            <span class="badge badge-secondary mr-1">
                                                {{ $tier->name }} ({{ number_format($tier->min_points) }} - {{ $tier->max_points ? number_format($tier->max_points) : '∞' }})
                                            </span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> المكافآت والمزايا</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="points_multiplier">مضاعف النقاط</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">x</span>
                                        </div>
                                        <input type="number" name="points_multiplier" id="points_multiplier" 
                                               class="form-control @error('points_multiplier') is-invalid @enderror" 
                                               value="{{ old('points_multiplier', 1) }}" min="1" max="10" step="0.1">
                                    </div>
                                    @error('points_multiplier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">مثال: 1.5 يعني 50% نقاط إضافية</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="discount_percentage">نسبة الخصم</label>
                                    <div class="input-group">
                                        <input type="number" name="discount_percentage" id="discount_percentage" 
                                               class="form-control @error('discount_percentage') is-invalid @enderror" 
                                               value="{{ old('discount_percentage', 0) }}" min="0" max="100" step="0.5">
                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    @error('discount_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">خصم على المشتريات لهذا المستوى</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="switch-label"><i class="feather icon-headphones text-primary"></i> دعم فني مميز</span>
                                            <label class="ios-switch mb-0">
                                                <input type="checkbox" id="priority_support" name="priority_support" value="1" {{ old('priority_support') ? 'checked' : '' }}>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        <small class="switch-help">دعم فني سريع ومميز</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="switch-label"><i class="feather icon-truck text-success"></i> شحن مجاني</span>
                                            <label class="ios-switch mb-0">
                                                <input type="checkbox" id="free_shipping" name="free_shipping" value="1" {{ old('free_shipping') ? 'checked' : '' }}>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        <small class="switch-help">شحن مجاني على جميع الطلبات</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label>مزايا إضافية</label>
                            <div id="benefits-container">
                                <div class="input-group mb-2 benefit-row">
                                    <input type="text" name="benefits[]" class="form-control" placeholder="أدخل ميزة...">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-danger remove-benefit" disabled>
                                            <i class="feather icon-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-benefit">
                                <i class="feather icon-plus"></i> إضافة ميزة
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> الإعدادات</h4>
                    </div>
                    <div class="card-body">
                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">المستوى نشط</span>
                                <label class="ios-switch">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">المستوى النشط يمكن للمستخدمين الوصول إليه</small>
                        </div>
                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-image"></i> المظهر</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="color">اللون</label>
                            <input type="color" name="color" id="color" 
                                   class="form-control @error('color') is-invalid @enderror" 
                                   value="{{ old('color', '#5c6bc0') }}" style="height: 45px;">
                            @error('color')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="icon">الأيقونة (Feather Icons)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-award" id="icon-preview"></i></span>
                                </div>
                                <input type="text" name="icon" id="icon" 
                                       class="form-control @error('icon') is-invalid @enderror" 
                                       value="{{ old('icon', 'feather icon-award') }}" placeholder="feather icon-award">
                            </div>
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">مثال: feather icon-star, feather icon-award</small>
                        </div>

                        <div class="form-group">
                            <label for="image">صورة المستوى</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input @error('image') is-invalid @enderror" 
                                       id="image" name="image" accept="image/*">
                                <label class="custom-file-label" for="image">اختر صورة...</label>
                            </div>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">الأبعاد المثالية: 200x200 بكسل</small>
                            <div id="image-preview" class="mt-2" style="display: none;">
                                <img src="" alt="Preview" class="img-thumbnail" style="max-width: 150px;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-primary">
                    <div class="card-header bg-light-primary">
                        <h4 class="card-title"><i class="feather icon-eye"></i> معاينة</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="tier-preview-badge" id="tier-preview" style="background: #5c6bc0; color: #fff; padding: 15px; border-radius: 10px;">
                            <i class="feather icon-award font-large-2 mb-1" id="preview-icon"></i>
                            <h4 id="preview-name" class="text-white mb-0">اسم المستوى</h4>
                            <small id="preview-range">0 - ∞ نقطة</small>
                        </div>
                    </div>
                </div>

                <div class="card border-warning">
                    <div class="card-header bg-light-warning">
                        <h4 class="card-title"><i class="feather icon-info"></i> نصائح</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-1">
                                <i class="feather icon-layers text-primary"></i>
                                <small>أنشئ مستويات متدرجة للتحفيز</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-gift text-success"></i>
                                <small>أضف مزايا جذابة لكل مستوى</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-target text-info"></i>
                                <small>حدد نطاق نقاط واضح ومتسق</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-percent text-warning"></i>
                                <small>زِد المكافآت تدريجياً مع المستويات</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Add benefit
    $('#add-benefit').on('click', function() {
        const html = `
            <div class="input-group mb-2 benefit-row">
                <input type="text" name="benefits[]" class="form-control" placeholder="أدخل ميزة...">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-danger remove-benefit">
                        <i class="feather icon-x"></i>
                    </button>
                </div>
            </div>
        `;
        $('#benefits-container').append(html);
        updateRemoveButtons();
    });

    // Remove benefit
    $(document).on('click', '.remove-benefit', function() {
        $(this).closest('.benefit-row').remove();
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        const rows = $('.benefit-row');
        rows.find('.remove-benefit').prop('disabled', rows.length <= 1);
    }

    // Image preview
    $('#image').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#image-preview').show().find('img').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
            $(this).next('.custom-file-label').text(file.name);
        }
    });

    // Icon preview
    $('#icon').on('input', function() {
        const icon = $(this).val() || 'feather icon-award';
        $('#icon-preview').attr('class', icon);
        $('#preview-icon').attr('class', icon + ' font-large-2 mb-1');
    });

    // Color preview
    $('#color').on('input', function() {
        $('#tier-preview').css('background', $(this).val());
    });

    // Name preview
    $('#name').on('input', function() {
        $('#preview-name').text($(this).val() || 'اسم المستوى');
    });

    // Points range preview
    function updateRangePreview() {
        const min = $('#min_points').val() || 0;
        const max = $('#max_points').val() || '∞';
        $('#preview-range').text(Number(min).toLocaleString() + ' - ' + (max === '∞' ? max : Number(max).toLocaleString()) + ' نقطة');
    }
    $('#min_points, #max_points').on('input', updateRangePreview);
});
</script>
@endpush
