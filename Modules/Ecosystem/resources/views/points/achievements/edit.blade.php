@extends('ecosystem::master')

@section('title', 'تعديل الإنجاز')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل الإنجاز</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.achievements.index') }}">الإنجازات</a></li>
                        <li class="breadcrumb-item active">تعديل: {{ $achievement->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.achievements.update', $achievement->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.achievements.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-right"></i> رجوع
                            </a>
                            <div>
                                <a href="{{ route('ecosystem.points.achievements.show', $achievement->id) }}" class="btn btn-outline-info mr-1">
                                    <i class="feather icon-eye"></i> عرض
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i> حفظ التعديلات
                                </button>
                            </div>
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
                                    <label for="name">الاسم بالعربية <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $achievement->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name_en">الاسم بالإنجليزية</label>
                                    <input type="text" id="name_en" name="name_en" class="form-control @error('name_en') is-invalid @enderror" 
                                           value="{{ old('name_en', $achievement->name_en) }}">
                                    @error('name_en')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف <span class="text-danger">*</span></label>
                            <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $achievement->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select id="user_type" name="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع المستخدم</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('user_type', $achievement->user_type->value) === $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
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
                                    <label for="category">الفئة <span class="text-danger">*</span></label>
                                    <select id="category" name="category" class="form-control @error('category') is-invalid @enderror" required>
                                        <option value="">اختر الفئة</option>
                                        @foreach($categories as $key => $label)
                                            <option value="{{ $key }}" {{ old('category', $achievement->category) === $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-target"></i> شروط الإنجاز</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_type">نوع الشرط <span class="text-danger">*</span></label>
                                    <select id="condition_type" name="condition_type" class="form-control @error('condition_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع الشرط</option>
                                        @foreach($conditionTypes as $key => $label)
                                            <option value="{{ $key }}" {{ old('condition_type', $achievement->condition_type) === $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('condition_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_period">الفترة الزمنية <span class="text-danger">*</span></label>
                                    <select id="condition_period" name="condition_period" class="form-control @error('condition_period') is-invalid @enderror" required>
                                        <option value="">اختر الفترة</option>
                                        @foreach($conditionPeriods as $period)
                                            <option value="{{ $period->value }}" {{ old('condition_period', $achievement->condition_period->value) === $period->value ? 'selected' : '' }}>
                                                {{ $period->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('condition_period')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_operator">المعامل <span class="text-danger">*</span></label>
                                    <select id="condition_operator" name="condition_operator" class="form-control @error('condition_operator') is-invalid @enderror" required>
                                        <option value="">اختر المعامل</option>
                                        @foreach($conditionOperators as $operator)
                                            <option value="{{ $operator->value }}" {{ old('condition_operator', $achievement->condition_operator->value) === $operator->value ? 'selected' : '' }}>
                                                {{ $operator->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('condition_operator')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_value">القيمة المطلوبة <span class="text-danger">*</span></label>
                                    <input type="number" id="condition_value" name="condition_value" 
                                           class="form-control @error('condition_value') is-invalid @enderror" 
                                           value="{{ old('condition_value', $achievement->condition_value) }}" min="0" required>
                                    @error('condition_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="feather icon-info"></i>
                            <strong>مثال:</strong> لإنشاء إنجاز "إنشاء 10 فواتير"، اختر: نوع الشرط = عدد الفواتير، المعامل = أكبر من أو يساوي، القيمة = 10
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> المكافأة</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="bonus_points">نقاط المكافأة <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-star"></i></span>
                                </div>
                                <input type="number" id="bonus_points" name="bonus_points" 
                                       class="form-control @error('bonus_points') is-invalid @enderror" 
                                       value="{{ old('bonus_points', $achievement->bonus_points) }}" min="0" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">نقطة</span>
                                </div>
                            </div>
                            @error('bonus_points')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">النقاط التي سيحصل عليها المستخدم عند تحقيق هذا الإنجاز</small>
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
                            <label class="switch-label">حالة الإنجاز</label>
                            <label class="switch">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $achievement->is_active) ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                            <small class="text-muted d-block mt-1">نشط / غير نشط</small>
                        </div>

                        <div class="switch-form-group mt-3">
                            <label class="switch-label">إنجاز مخفي</label>
                            <label class="switch">
                                <input type="checkbox" name="is_hidden" value="1" {{ old('is_hidden', $achievement->is_hidden) ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                            <small class="text-muted d-block mt-1">إخفاء الإنجاز عن المستخدمين حتى يحققوه</small>
                        </div>

                        <div class="switch-form-group mt-3">
                            <label class="switch-label">إنجاز لمرة واحدة</label>
                            <label class="switch">
                                <input type="checkbox" name="is_one_time" value="1" {{ old('is_one_time', $achievement->is_one_time) ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                            <small class="text-muted d-block mt-1">يمكن تحقيقه مرة واحدة فقط أو بشكل متكرر</small>
                        </div>
                    </div>
                </div>

                <div class="card border-info">
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-image"></i> المظهر</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="icon">الرمز التعبيري (Emoji)</label>
                            <input type="text" id="icon" name="icon" class="form-control @error('icon') is-invalid @enderror" 
                                   value="{{ old('icon', $achievement->icon ?? '🏆') }}" placeholder="🏆">
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">أدخل رمز تعبيري مثل: 🏆 ⭐ 🎯 💰</small>
                        </div>

                        <div class="form-group">
                            <label for="color">لون الإنجاز</label>
                            <input type="color" id="color" name="color" class="form-control @error('color') is-invalid @enderror" 
                                   value="{{ old('color', $achievement->color ?? '#FFD700') }}" style="height: 50px;">
                            @error('color')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="image">صورة الإنجاز (اختياري)</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input @error('image') is-invalid @enderror" 
                                       id="image" name="image" accept="image/*">
                                <label class="custom-file-label" for="image">اختر صورة</label>
                            </div>
                            @error('image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">الحد الأقصى: 2MB</small>
                            
                            @if($achievement->image)
                                <div class="mt-2">
                                    <img src="{{ asset('storage/' . $achievement->image) }}" 
                                         alt="{{ $achievement->name }}"
                                         style="max-width: 100%; border-radius: 10px;">
                                </div>
                            @endif
                            
                            <div id="image-preview" class="mt-2" style="display: none;">
                                <img src="" alt="Preview" style="max-width: 100%; border-radius: 10px;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-primary">
                    <div class="card-header bg-light-primary">
                        <h4 class="card-title"><i class="feather icon-eye"></i> معاينة</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="achievement-preview" id="achievement-preview" style="background: {{ $achievement->color ?? '#FFD700' }}20; padding: 15px; border-radius: 10px;">
                            <div class="achievement-icon" id="preview-icon" style="font-size: 3rem; margin-bottom: 10px;">@include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '3rem'])</div>
                            <h5 id="preview-name" class="mb-0">{{ $achievement->name }}</h5>
                            <small class="text-muted d-block mt-1" id="preview-description">{{ $achievement->description }}</small>
                            <div class="mt-2">
                                <span class="badge badge-success" id="preview-points" style="background: {{ $achievement->color ?? '#FFD700' }}">
                                    <i class="feather icon-star"></i> {{ number_format($achievement->bonus_points) }} نقطة
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-light-secondary">
                    <div class="card-header bg-light">
                        <h4 class="card-title"><i class="feather icon-clock"></i> معلومات الإنشاء</h4>
                    </div>
                    <div class="card-body">
                        <small class="d-block mb-2">
                            <strong>تاريخ الإنشاء:</strong><br>
                            {{ $achievement->created_at->format('Y/m/d H:i') }}
                        </small>
                        @if($achievement->created_at != $achievement->updated_at)
                            <small class="d-block">
                                <strong>آخر تحديث:</strong><br>
                                {{ $achievement->updated_at->format('Y/m/d H:i') }}
                            </small>
                        @endif
                    </div>
                </div>

                <div class="card border-{{ $achievement->is_active ? 'success' : 'danger' }}">
                    <div class="card-header bg-light-{{ $achievement->is_active ? 'success' : 'danger' }}">
                        <h4 class="card-title">
                            <i class="feather icon-{{ $achievement->is_active ? 'check' : 'x' }}-circle"></i>
                            حالة الإنجاز
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($achievement->is_active)
                            <p class="text-success mb-0">
                                <i class="feather icon-check-circle"></i> هذا الإنجاز نشط ومتاح للمستخدمين
                            </p>
                        @else
                            <p class="text-danger mb-0">
                                <i class="feather icon-x-circle"></i> هذا الإنجاز غير نشط حالياً
                            </p>
                        @endif
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
        const icon = $(this).val() || '🏆';
        $('#preview-icon').text(icon);
    });

    // Color preview
    $('#color').on('input', function() {
        $('#achievement-preview').css('background', $(this).val() + '20');
        $('#preview-points').css('background', $(this).val());
    });

    // Name preview
    $('#name').on('input', function() {
        $('#preview-name').text($(this).val() || 'اسم الإنجاز');
    });

    // Description preview
    $('#description').on('input', function() {
        $('#preview-description').text($(this).val() || 'وصف الإنجاز');
    });

    // Points preview
    $('#bonus_points').on('input', function() {
        const points = $(this).val() || 0;
        $('#preview-points').html('<i class="feather icon-star"></i> ' + Number(points).toLocaleString() + ' نقطة');
    });
});
</script>
@endpush
