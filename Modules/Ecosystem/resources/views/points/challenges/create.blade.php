@extends('ecosystem::master')

@section('title', 'إنشاء تحدي جديد')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إنشاء تحدي جديد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                        <li class="breadcrumb-item active">إنشاء جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    @include('layouts.alerts.error')

    <form action="{{ route('ecosystem.points.challenges.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.challenges.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-right"></i> رجوع
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ التحدي
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                {{-- المعلومات الأساسية --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> المعلومات الأساسية</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">اسم التحدي بالعربية <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name_en">اسم التحدي بالإنجليزية</label>
                                    <input type="text" id="name_en" name="name_en" class="form-control @error('name_en') is-invalid @enderror"
                                           value="{{ old('name_en') }}">
                                    @error('name_en')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select id="user_type" name="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع المستخدم</option>
                                        <option value="employee" {{ old('user_type') === 'employee' ? 'selected' : '' }}>موظفين</option>
                                        <option value="client" {{ old('user_type') === 'client' ? 'selected' : '' }}>عملاء</option>
                                        <option value="both" {{ old('user_type') === 'both' ? 'selected' : '' }}>الجميع</option>
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="challenge_type">نوع التحدي <span class="text-danger">*</span></label>
                                    <select id="challenge_type" name="challenge_type" class="form-control @error('challenge_type') is-invalid @enderror" required>
                                        <option value="">اختر النوع</option>
                                        @foreach($challengeTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('challenge_type') === $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('challenge_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="competition_type">نوع المنافسة <span class="text-danger">*</span></label>
                                    <select id="competition_type" name="competition_type" class="form-control @error('competition_type') is-invalid @enderror" required>
                                        <option value="">اختر النوع</option>
                                        @foreach($competitionTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('competition_type') === $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('competition_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <strong>هدف:</strong> جميع من يحقق الهدف يفوز |
                                        <strong>ترتيب:</strong> مكافآت حسب المركز |
                                        <strong>أول N:</strong> أسرع N فائزين
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- هدف التحدي --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-target"></i> هدف التحدي</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="target_action">الإجراءات المطلوبة <span class="text-danger">*</span></label>
                                    <select id="target_action" name="target_action[]" class="form-control select2 @error('target_action') is-invalid @enderror @error('target_action.*') is-invalid @enderror" multiple required>
                                    </select>
                                    <small class="form-text text-muted">يمكنك اختيار إجراء واحد أو عدة إجراءات</small>
                                    @error('target_action')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @error('target_action.*')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="target_value">القيمة المستهدفة <span class="text-danger">*</span></label>
                                    <input type="number" id="target_value" name="target_value" class="form-control @error('target_value') is-invalid @enderror"
                                           value="{{ old('target_value') }}" min="1" required>
                                    @error('target_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="target_unit">وحدة القياس</label>
                                    <input type="text" id="target_unit" name="target_unit" class="form-control @error('target_unit') is-invalid @enderror"
                                           value="{{ old('target_unit') }}" placeholder="مثال: عملية، نقطة، ريال">
                                    @error('target_unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- المكافآت --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> المكافآت</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="reward_points">نقاط المركز الأول <span class="text-danger">*</span></label>
                                    <input type="number" id="reward_points" name="reward_points" class="form-control @error('reward_points') is-invalid @enderror"
                                           value="{{ old('reward_points') }}" min="1" required>
                                    @error('reward_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3" id="reward_2nd_group">
                                <div class="form-group">
                                    <label for="reward_points_2nd">نقاط المركز الثاني</label>
                                    <input type="number" id="reward_points_2nd" name="reward_points_2nd" class="form-control @error('reward_points_2nd') is-invalid @enderror"
                                           value="{{ old('reward_points_2nd') }}" min="0">
                                    @error('reward_points_2nd')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3" id="reward_3rd_group">
                                <div class="form-group">
                                    <label for="reward_points_3rd">نقاط المركز الثالث</label>
                                    <input type="number" id="reward_points_3rd" name="reward_points_3rd" class="form-control @error('reward_points_3rd') is-invalid @enderror"
                                           value="{{ old('reward_points_3rd') }}" min="0">
                                    @error('reward_points_3rd')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3" id="max_winners_group">
                                <div class="form-group">
                                    <label for="max_winners">الحد الأقصى للفائزين</label>
                                    <input type="number" id="max_winners" name="max_winners" class="form-control @error('max_winners') is-invalid @enderror"
                                           value="{{ old('max_winners') }}" min="1">
                                    @error('max_winners')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">حسب نوع المنافسة</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- القواعد --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-book-open"></i> القواعد والشروط</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="rules">قواعد التحدي</label>
                            <textarea id="rules" name="rules" rows="4" class="form-control @error('rules') is-invalid @enderror"
                                      placeholder="اكتب قواعد التحدي (اختياري)...">{{ old('rules') }}</textarea>
                            @error('rules')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- المدة الزمنية --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar"></i> المدة الزمنية</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="starts_at">تاريخ البداية <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="starts_at" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
                                   value="{{ old('starts_at') }}" required>
                            @error('starts_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="ends_at">تاريخ النهاية <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="ends_at" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
                                   value="{{ old('ends_at') }}" required>
                            @error('ends_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- إعدادات إضافية --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> إعدادات</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="branch_id">الفرع</label>
                            <select id="branch_id" name="branch_id" class="form-control @error('branch_id') is-invalid @enderror">
                                <option value="">جميع الفروع</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="min_tier_id">الحد الأدنى للمستوى</label>
                            <select id="min_tier_id" name="min_tier_id" class="form-control @error('min_tier_id') is-invalid @enderror">
                                <option value="">بدون حد أدنى</option>
                                @foreach($tiers as $tier)
                                    <option value="{{ $tier->id }}" {{ old('min_tier_id') == $tier->id ? 'selected' : '' }}>
                                        {{ $tier->name }} ({{ number_format($tier->min_points) }} نقطة)
                                    </option>
                                @endforeach
                            </select>
                            @error('min_tier_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="image">صورة التحدي</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="image" name="image" accept="image/*">
                                <label class="custom-file-label" for="image">اختر صورة</label>
                            </div>
                            @error('image')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>

                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">تفعيل التحدي</span>
                                <label class="ios-switch">
                                    <input type="checkbox" id="is_active" name="is_active" value="1"
                                           {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">تفعيل أو تعطيل هذا التحدي</small>
                        </div>

                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">تحدي مميز</span>
                                <label class="ios-switch">
                                    <input type="checkbox" id="is_featured" name="is_featured" value="1"
                                           {{ old('is_featured') ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عرض التحدي في القسم المميز</small>
                        </div>

                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">تسجيل تلقائي</span>
                                <label class="ios-switch">
                                    <input type="checkbox" id="auto_enroll" name="auto_enroll" value="1"
                                           {{ old('auto_enroll') ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">تسجيل جميع المستخدمين المؤهلين تلقائياً</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: calc(1.25em + 1.3rem + 1px);
        border: 1px solid #d9d9d9;
        border-radius: 0.357rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.25em + 1.3rem);
        padding-right: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(1.25em + 1.3rem);
    }
    .select2-container--default .select2-selection--multiple {
        min-height: calc(1.25em + 1.3rem + 1px);
        border: 1px solid #d9d9d9;
        border-radius: 0.357rem;
        padding: 3px 8px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #7367f0;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        margin: 2px;
        font-size: 0.85rem;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #fff;
        margin-left: 4px;
        margin-right: 0;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ddd;
    }
    .select2-container { width: 100% !important; }
    .select2-dropdown { direction: rtl; }
    .select2-results__group { font-weight: bold; color: #7367f0; padding: 8px 12px; }
    /* iOS Switch Styles */
    .ios-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; display: inline-block; }
    .ios-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .ios-switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e0e0e0; border-radius: 24px; transition: 0.3s; }
    .ios-switch .slider::before { content: ''; position: absolute; height: 20px; width: 20px; right: 2px; bottom: 2px; background-color: #fff; border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
    .ios-switch input:checked + .slider { background-color: #7367f0; }
    .ios-switch input:checked + .slider::before { transform: translateX(-20px); }
    .ios-switch input:focus + .slider { box-shadow: 0 0 0 2px rgba(115, 103, 240, 0.2); }
    .switch-form-group { margin-bottom: 1.25rem; }
    .switch-form-group .switch-row { display: flex; align-items: center; justify-content: space-between; }
    .switch-form-group .switch-label { font-size: 0.9rem; font-weight: 600; color: #333; margin-bottom: 0; }
    .switch-form-group .switch-help { font-size: 0.8rem; color: #888; margin-top: 0.35rem; display: block; }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // بيانات الإجراءات حسب نوع المستخدم
    var actionTypes = @json($actionTypes);
    var oldTargetActions = @json(old('target_action', []));

    // تحديث قائمة الإجراءات عند تغيير نوع المستخدم
    function updateActionTypes() {
        var userType = $('#user_type').val();
        var $select = $('#target_action');

        // تدمير Select2
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.empty();

        var typesToShow = [];
        if (userType === 'employee') {
            typesToShow = ['employee'];
        } else if (userType === 'client') {
            typesToShow = ['client'];
        } else if (userType === 'both') {
            typesToShow = ['employee', 'client'];
        }

        typesToShow.forEach(function(type) {
            if (actionTypes[type]) {
                $.each(actionTypes[type], function(groupName, actions) {
                    var $group = $('<optgroup>', { label: (type === 'employee' ? '👤 ' : '🛒 ') + groupName });
                    actions.forEach(function(item) {
                        $group.append($('<option>', { value: item.value, text: item.label }));
                    });
                    $select.append($group);
                });
            }
        });

        // إعادة تهيئة Select2 كقائمة متعددة
        $select.select2({
            dir: 'rtl',
            placeholder: 'ابحث واختر الإجراءات...',
            allowClear: true,
            width: '100%',
            multiple: true
        });

        if (oldTargetActions && oldTargetActions.length > 0) {
            $select.val(oldTargetActions).trigger('change');
        }
    }

    $('#user_type').on('change', updateActionTypes);
    updateActionTypes();

    // إظهار/إخفاء حقول المكافآت حسب نوع المنافسة
    function updateRewardFields() {
        var type = $('#competition_type').val();
        if (type === 'target') {
            $('#reward_2nd_group, #reward_3rd_group').hide();
            $('#max_winners_group').hide();
        } else if (type === 'ranking') {
            $('#reward_2nd_group, #reward_3rd_group').show();
            $('#max_winners_group').hide();
        } else if (type === 'first_n') {
            $('#reward_2nd_group, #reward_3rd_group').hide();
            $('#max_winners_group').show();
        } else {
            $('#reward_2nd_group, #reward_3rd_group, #max_winners_group').show();
        }
    }

    $('#competition_type').on('change', updateRewardFields);
    updateRewardFields();

    // عرض اسم الملف المختار
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').text(fileName || 'اختر صورة');
    });
});
</script>
@endsection
