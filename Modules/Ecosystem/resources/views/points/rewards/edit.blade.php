@extends('ecosystem::master')

@section('title', 'تعديل المكافأة: ' . $reward->name)

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل المكافأة</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.rewards.index') }}">المكافآت</a></li>
                        <li class="breadcrumb-item active">تعديل: {{ $reward->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    @include('ecosystem::points.rewards.partials.hr-guidance')

    <form action="{{ route('ecosystem.points.rewards.update', $reward) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="{{ route('ecosystem.points.rewards.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-arrow-right"></i> رجوع
                                </a>
                                <a href="{{ route('ecosystem.points.rewards.show', $reward) }}" class="btn btn-outline-info">
                                    <i class="feather icon-eye"></i> عرض
                                </a>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> تحديث المكافأة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- المعلومات الأساسية -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> المعلومات الأساسية</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">اسم المكافأة <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $reward->name) }}" required placeholder="أدخل اسم المكافأة">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name_en">الاسم بالإنجليزية</label>
                                    <input type="text" name="name_en" id="name_en" 
                                           class="form-control @error('name_en') is-invalid @enderror" 
                                           value="{{ old('name_en', $reward->name_en) }}" placeholder="Reward name in English">
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
                                    <select name="user_type" id="user_type" 
                                            class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">-- اختر --</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ old('user_type', $reward->user_type->value) == $type ? 'selected' : '' }}>
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
                                    <label for="reward_type">نوع المكافأة <span class="text-danger">*</span></label>
                                    <select name="reward_type" id="reward_type" 
                                            class="form-control @error('reward_type') is-invalid @enderror" required>
                                        <option value="">-- اختر --</option>
                                        @foreach($rewardTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('reward_type', $reward->reward_type->value) == $type->value ? 'selected' : '' }}>
                                                {{ $type->icon() }} {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reward_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="وصف المكافأة...">{{ old('description', $reward->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- النقاط والقيمة -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-star"></i> النقاط والقيمة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="points_required">النقاط المطلوبة <span class="text-danger">*</span></label>
                                    <input type="number" name="points_required" id="points_required" 
                                           class="form-control @error('points_required') is-invalid @enderror" 
                                           value="{{ old('points_required', $reward->points_required) }}" min="1" required placeholder="0">
                                    @error('points_required')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="reward_value">قيمة المكافأة</label>
                                    <input type="number" name="reward_value" id="reward_value" 
                                           class="form-control @error('reward_value') is-invalid @enderror" 
                                           value="{{ old('reward_value', $reward->reward_value) }}" min="0" step="0.01" placeholder="0.00">
                                    <small class="text-muted" id="reward-value-hint">النسبة أو المبلغ حسب نوع المكافأة</small>
                                    @error('reward_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="product_id">المنتج (اختياري)</label>
                                    <select name="product_id" id="product_id" 
                                            class="form-control select2 @error('product_id') is-invalid @enderror">
                                        <option value="">-- اختر منتج --</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" {{ old('product_id', $reward->product_id) == $product->id ? 'selected' : '' }}>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('product_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- مكافأة «إجازة»: نوع الإجازة الذي تُضاف إليه الأيام عند الاعتماد --}}
                        <div id="leave-type-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="leave_type_id">نوع الإجازة <span class="text-danger">*</span>
                                            <i class="feather icon-help-circle stk-tip" title="عند اعتماد طلب الاستبدال تُضاف أيام المكافأة إلى «الرصيد الإضافي» لهذا النوع في رصيد إجازات الموظف للسنة الجارية."></i>
                                        </label>
                                        <select name="leave_type_id" id="leave_type_id"
                                                class="form-control @error('leave_type_id') is-invalid @enderror">
                                            <option value="">-- اختر نوع الإجازة --</option>
                                            @foreach($leaveTypes ?? [] as $leaveType)
                                                <option value="{{ $leaveType->id }}" {{ (string) old('leave_type_id', $reward->leave_type_id) === (string) $leaveType->id ? 'selected' : '' }}>
                                                    {{ $leaveType->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(($leaveTypes ?? collect())->isEmpty())
                                            <small class="stk-help text-warning">لا توجد أنواع إجازات معرَّفة — أضفها من إعدادات الحضور ← أنواع الإجازات أولاً.</small>
                                        @endif
                                        @error('leave_type_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- حقول خاصة بالخصومات والقسائم --}}
                        <div id="discount-voucher-fields" style="{{ in_array(old('reward_type', $reward->reward_type->value), ['discount_percentage', 'discount_amount', 'voucher']) ? '' : 'display: none;' }}">
                            <hr>
                            <h6 class="text-muted mb-3"><i class="feather icon-tag"></i> إعدادات الخصم/القسيمة</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="max_discount_value">الحد الأقصى للخصم</label>
                                        <input type="number" name="max_discount_value" id="max_discount_value" 
                                               class="form-control @error('max_discount_value') is-invalid @enderror" 
                                               value="{{ old('max_discount_value', $reward->max_discount_value) }}" min="0" step="0.01" placeholder="0.00">
                                        <small class="text-muted">اتركه فارغاً بدون حد أقصى (مهم لخصم النسبة المئوية)</small>
                                        @error('max_discount_value')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="min_order_amount">الحد الأدنى لمبلغ الفاتورة</label>
                                        <input type="number" name="min_order_amount" id="min_order_amount" 
                                               class="form-control @error('min_order_amount') is-invalid @enderror" 
                                               value="{{ old('min_order_amount', $reward->min_order_amount) }}" min="0" step="0.01" placeholder="0.00">
                                        <small class="text-muted">اتركه فارغاً بدون حد أدنى</small>
                                        @error('min_order_amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="validity_days">صلاحية الكود (بالأيام)</label>
                                        <input type="number" name="validity_days" id="validity_days" 
                                               class="form-control @error('validity_days') is-invalid @enderror" 
                                               value="{{ old('validity_days', $reward->validity_days) }}" min="1" placeholder="30">
                                        <small class="text-muted">عدد الأيام بعد الإصدار. اتركه فارغاً بدون انتهاء</small>
                                        @error('validity_days')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الكمية والحدود -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-package"></i> الكمية والحدود</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="quantity_available">الكمية المتاحة</label>
                                    <input type="number" name="quantity_available" id="quantity_available" 
                                           class="form-control @error('quantity_available') is-invalid @enderror" 
                                           value="{{ old('quantity_available', $reward->quantity_available) }}" min="0" placeholder="اتركه فارغاً للكمية غير المحدودة">
                                    <small class="text-muted">اتركه فارغاً للكمية غير المحدودة</small>
                                    @error('quantity_available')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="max_per_user">الحد الأقصى لكل مستخدم</label>
                                    <input type="number" name="max_per_user" id="max_per_user" 
                                           class="form-control @error('max_per_user') is-invalid @enderror" 
                                           value="{{ old('max_per_user', $reward->max_per_user) }}" min="1" placeholder="1">
                                    @error('max_per_user')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="min_tier_id">الحد الأدنى للمستوى</label>
                                    <select name="min_tier_id" id="min_tier_id" 
                                            class="form-control @error('min_tier_id') is-invalid @enderror">
                                        <option value="">-- جميع المستويات --</option>
                                        @foreach($tiers as $tier)
                                            <option value="{{ $tier->id }}" {{ old('min_tier_id', $reward->min_tier_id) == $tier->id ? 'selected' : '' }}>
                                                {{ $tier->name }} ({{ $tier->user_type->label() }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('min_tier_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- التواريخ -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar"></i> فترة الصلاحية</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="starts_at">تاريخ البداية</label>
                                    <input type="datetime-local" name="starts_at" id="starts_at" 
                                           class="form-control @error('starts_at') is-invalid @enderror" 
                                           value="{{ old('starts_at', $reward->starts_at ? $reward->starts_at->format('Y-m-d\TH:i') : '') }}">
                                    <small class="text-muted">اتركه فارغاً لتبدأ فوراً</small>
                                    @error('starts_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="expires_at">تاريخ الانتهاء</label>
                                    <input type="datetime-local" name="expires_at" id="expires_at" 
                                           class="form-control @error('expires_at') is-invalid @enderror" 
                                           value="{{ old('expires_at', $reward->expires_at ? $reward->expires_at->format('Y-m-d\TH:i') : '') }}">
                                    <small class="text-muted">اتركه فارغاً بدون انتهاء</small>
                                    @error('expires_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الشروط والأحكام -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-file-text"></i> الشروط والأحكام</h4>
                    </div>
                    <div class="card-body">
                        @php
                            $terms = old('terms_conditions', $reward->terms_conditions ?? []);
                            if (empty($terms)) $terms = [''];
                        @endphp
                        <div id="terms-container">
                            @foreach($terms as $index => $term)
                            <div class="input-group mb-2 term-row">
                                <input type="text" name="terms_conditions[]" class="form-control" 
                                       value="{{ $term }}" placeholder="أدخل شرطاً...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-danger remove-term" {{ $index === 0 && count($terms) === 1 ? 'disabled' : '' }}>
                                        <i class="feather icon-x"></i>
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-term">
                            <i class="feather icon-plus"></i> إضافة شرط
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- الإعدادات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> الإعدادات</h4>
                    </div>
                    <div class="card-body">
                        <div class="switch-form-group">
                            <label class="switch-label">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" 
                                       class="switch-input" {{ old('is_active', $reward->is_active) ? 'checked' : '' }}>
                                <span class="switch-toggle"></span>
                                <span class="switch-text">مكافأة نشطة</span>
                            </label>
                        </div>
                        <hr>
                        <div class="switch-form-group">
                            <label class="switch-label">
                                <input type="hidden" name="is_featured" value="0">
                                <input type="checkbox" name="is_featured" value="1" 
                                       class="switch-input" {{ old('is_featured', $reward->is_featured) ? 'checked' : '' }}>
                                <span class="switch-toggle"></span>
                                <span class="switch-text">مكافأة مميزة</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- الفرع -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-map-pin"></i> الفرع</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <select name="branch_id" id="branch_id" 
                                    class="form-control @error('branch_id') is-invalid @enderror">
                                <option value="">-- جميع الفروع --</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $reward->branch_id) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- الصورة الحالية -->
                @if($reward->image)
                <div class="card border-success">
                    <div class="card-header bg-light-success">
                        <h4 class="card-title"><i class="feather icon-image"></i> الصورة الحالية</h4>
                    </div>
                    <div class="card-body text-center">
                        <img src="{{ asset('uploads/rewards/' . $reward->image) }}" alt="{{ $reward->name }}" 
                             class="img-fluid rounded" style="max-height: 150px;">
                    </div>
                </div>
                @endif

                <!-- تغيير الصورة -->
                <div class="card border-info">
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-camera"></i> تغيير الصورة</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="image" name="image" accept="image/*">
                                <label class="custom-file-label" for="image">اختر صورة جديدة...</label>
                            </div>
                            <small class="text-muted">الحد الأقصى: 2MB - الأنواع: JPG, PNG, GIF, SVG</small>
                        </div>
                        <div id="image-preview" class="mt-2 text-center" style="display: none;">
                            <img src="" alt="معاينة" class="img-fluid rounded" style="max-height: 150px;">
                        </div>
                    </div>
                </div>

                <!-- الإحصائيات -->
                <div class="card border-primary">
                    <div class="card-header bg-light-primary">
                        <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> إحصائيات</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>عدد مرات الاستبدال:</span>
                            <strong>{{ number_format($reward->times_redeemed ?? 0) }}</strong>
                        </div>
                        @if($reward->quantity_available)
                        <div class="d-flex justify-content-between mb-2">
                            <span>الكمية المتبقية:</span>
                            <strong class="{{ $reward->quantity_available <= 5 ? 'text-danger' : '' }}">
                                {{ number_format($reward->quantity_available) }}
                            </strong>
                        </div>
                        @endif
                        <div class="d-flex justify-content-between">
                            <span>تاريخ الإنشاء:</span>
                            <strong>{{ $reward->created_at->format('Y/m/d') }}</strong>
                        </div>
                    </div>
                </div>

                <!-- حالة المكافأة -->
                <div class="card border-{{ $reward->is_active ? 'success' : 'danger' }}">
                    <div class="card-body text-center">
                        @if($reward->is_active)
                            <i class="feather icon-check-circle text-success font-large-2"></i>
                            <h5 class="mt-1 text-success">المكافأة نشطة</h5>
                        @else
                            <i class="feather icon-x-circle text-danger font-large-2"></i>
                            <h5 class="mt-1 text-danger">المكافأة غير نشطة</h5>
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
    // Add term
    $('#add-term').on('click', function() {
        const html = `
            <div class="input-group mb-2 term-row">
                <input type="text" name="terms_conditions[]" class="form-control" placeholder="أدخل شرطاً...">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-danger remove-term">
                        <i class="feather icon-x"></i>
                    </button>
                </div>
            </div>
        `;
        $('#terms-container').append(html);
        updateRemoveButtons();
    });

    // Remove term
    $(document).on('click', '.remove-term', function() {
        $(this).closest('.term-row').remove();
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        const rows = $('.term-row');
        rows.find('.remove-term').prop('disabled', rows.length <= 1);
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

    // إظهار/إخفاء حقول الخصم والقسيمة حسب نوع المكافأة
    function toggleDiscountFields() {
        const type = $('#reward_type').val();
        const discountTypes = ['discount_percentage', 'discount_amount', 'voucher'];
        if (discountTypes.includes(type)) {
            $('#discount-voucher-fields').slideDown();
        } else {
            $('#discount-voucher-fields').slideUp();
        }

        // تحديث تلميح قيمة المكافأة
        if (type === 'discount_percentage') {
            $('#reward-value-hint').text('النسبة المئوية للخصم (مثال: 5 = 5%)');
        } else if (type === 'discount_amount') {
            $('#reward-value-hint').text('مبلغ الخصم الثابت بالريال');
        } else if (type === 'voucher') {
            $('#reward-value-hint').text('قيمة القسيمة الشرائية بالريال');
        } else if (type === 'cash') {
            $('#reward-value-hint').text('المبلغ بالريال — للموظف يُضاف لمرّةٍ واحدة على راتبه التالي (ليس بدلاً متكرّراً)');
        } else if (type === 'leave_day') {
            $('#reward-value-hint').text('عدد أيام الإجازة (عدد صحيح) — تُضاف إلى رصيد إجازات الموظف عند الاعتماد');
        } else {
            $('#reward-value-hint').text('النسبة أو المبلغ حسب نوع المكافأة');
        }
    }

    // نوع الإجازة لمكافأة «إجازة» وحدها
    function toggleLeaveFields() {
        const isLeave = $('#reward_type').val() === 'leave_day';
        $('#leave-type-fields').toggle(isLeave);
        $('#leave_type_id').prop('required', isLeave);
    }
    $('#reward_type').on('change', toggleLeaveFields);
    toggleLeaveFields();

    $('#reward_type').on('change', toggleDiscountFields);
    // تشغيل عند التحميل
    toggleDiscountFields();
});
</script>
@endpush
