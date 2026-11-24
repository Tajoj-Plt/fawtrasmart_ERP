@extends('ecosystem::master')

@section('title', 'تعديل إعداد الإحالة')

@push('styles')
<style>
.select2-container--default .select2-selection--single {
    height: 38px !important;
    padding: 5px 12px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}
.select2-container {
    width: 100% !important;
}
.milestone-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 8px;
    border: 1px solid #e9ecef;
}
.milestone-row .remove-milestone {
    cursor: pointer;
    color: #dc3545;
    font-size: 1.1rem;
}
.milestone-row .remove-milestone:hover {
    color: #a71d2a;
}
</style>
@endpush

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل إعداد الإحالة</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.referral-settings.index') }}">إعدادات الإحالة</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.referral-settings.update', $referral_setting->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.referral-settings.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ التعديلات
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- النقاط -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> إعدادات النقاط</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="referrer_points">نقاط المُحيل <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="referrer_points" id="referrer_points"
                                               class="form-control @error('referrer_points') is-invalid @enderror"
                                               value="{{ old('referrer_points', $referral_setting->referrer_points) }}" required min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">نقطة</span>
                                        </div>
                                    </div>
                                    @error('referrer_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">النقاط التي يحصل عليها العميل الذي قام بالإحالة</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="referred_points">نقاط المُحال إليه <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="referred_points" id="referred_points"
                                               class="form-control @error('referred_points') is-invalid @enderror"
                                               value="{{ old('referred_points', $referral_setting->referred_points) }}" required min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">نقطة</span>
                                        </div>
                                    </div>
                                    @error('referred_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">النقاط التي يحصل عليها العميل الجديد المُحال</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شروط التأكيد -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-check-square"></i> شروط التأكيد</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirmation_type">نوع التأكيد <span class="text-danger">*</span></label>
                                    <select name="confirmation_type" id="confirmation_type"
                                            class="form-control @error('confirmation_type') is-invalid @enderror" required>
                                        <option value="">-- اختر نوع التأكيد --</option>
                                        @foreach($confirmationTypes as $type)
                                            <option value="{{ $type->value }}" 
                                                {{ old('confirmation_type', $referral_setting->confirmation_type->value ?? '') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('confirmation_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">متى يتم تأكيد الإحالة ومنح النقاط</small>
                                </div>
                            </div>

                            <div class="col-md-6" id="confirmationValueGroup" 
                                 style="{{ in_array($referral_setting->confirmation_type?->value, ['min_purchase', 'days_active']) ? '' : 'display: none;' }}">
                                <div class="form-group">
                                    <label for="confirmation_value" id="confirmationValueLabel">قيمة التأكيد</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="confirmation_value" id="confirmation_value"
                                               class="form-control @error('confirmation_value') is-invalid @enderror"
                                               value="{{ old('confirmation_value', $referral_setting->confirmation_value) }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="confirmationValueUnit">
                                                {{ $referral_setting->confirmation_type?->value === 'days_active' ? 'يوم' : 'ريال' }}
                                            </span>
                                        </div>
                                    </div>
                                    @error('confirmation_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الحدود -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-shield"></i> حدود الإحالة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_referrals_per_month">الحد الأقصى شهرياً</label>
                                    <div class="input-group">
                                        <input type="number" name="max_referrals_per_month" id="max_referrals_per_month"
                                               class="form-control @error('max_referrals_per_month') is-invalid @enderror"
                                               value="{{ old('max_referrals_per_month', $referral_setting->max_referrals_per_month) }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">إحالة</span>
                                        </div>
                                    </div>
                                    @error('max_referrals_per_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">اتركه فارغاً لعدم تحديد حد شهري</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_referrals_total">الحد الأقصى الإجمالي</label>
                                    <div class="input-group">
                                        <input type="number" name="max_referrals_total" id="max_referrals_total"
                                               class="form-control @error('max_referrals_total') is-invalid @enderror"
                                               value="{{ old('max_referrals_total', $referral_setting->max_referrals_total) }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">إحالة</span>
                                        </div>
                                    </div>
                                    @error('max_referrals_total')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">اتركه فارغاً لعدم تحديد حد إجمالي</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- مكافآت المراحل -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-award"></i> مكافآت المراحل (اختياري)</h4>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addMilestone">
                            <i class="feather icon-plus"></i> إضافة مرحلة
                        </button>
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-3">
                            يمكنك إضافة نقاط إضافية عند وصول العميل لعدد معين من الإحالات الناجحة
                        </small>
                        <div id="milestonesContainer">
                            @if($referral_setting->bonus_milestones)
                                @foreach($referral_setting->bonus_milestones as $index => $milestone)
                                    <div class="milestone-row d-flex align-items-center" data-index="{{ $index }}">
                                        <div class="flex-grow-1">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <div class="form-group mb-0">
                                                        <label class="small">عند إتمام</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" name="milestones[{{ $index }}][count]"
                                                                   class="form-control" value="{{ $milestone['count'] ?? '' }}" min="1" required>
                                                            <div class="input-group-append">
                                                                <span class="input-group-text">إحالة</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group mb-0">
                                                        <label class="small">نقاط إضافية</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" name="milestones[{{ $index }}][points]"
                                                                   class="form-control" value="{{ $milestone['points'] ?? '' }}" min="1" required>
                                                            <div class="input-group-append">
                                                                <span class="input-group-text">نقطة</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <span class="remove-milestone" title="حذف"><i class="feather icon-trash-2"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- الحالة والفرع -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> إعدادات عامة</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="branch_id">الفرع</label>
                            <select name="branch_id" id="branch_id" class="form-control select2 @error('branch_id') is-invalid @enderror">
                                <option value="">جميع الفروع</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $referral_setting->branch_id) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">اتركه فارغاً لتطبيقه على جميع الفروع</small>
                        </div>

                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">الإعداد نشط</span>
                                <label class="ios-switch">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $referral_setting->is_active) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عند التفعيل، سيتم استخدام هذا الإعداد للإحالات الجديدة</small>
                        </div>
                    </div>
                </div>

                <!-- معلومات الإعداد -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> معلومات</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">تاريخ الإنشاء:</td>
                                <td>{{ $referral_setting->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">آخر تحديث:</td>
                                <td>{{ $referral_setting->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    if ($.fn.select2) {
        $('.select2').select2();
    }

    // Show/hide confirmation value based on type
    $('#confirmation_type').on('change', function() {
        var type = $(this).val();
        var $group = $('#confirmationValueGroup');
        var $label = $('#confirmationValueLabel');
        var $unit = $('#confirmationValueUnit');

        if (type === 'min_purchase') {
            $group.show();
            $label.text('الحد الأدنى للشراء');
            $unit.text('ريال');
        } else if (type === 'days_active') {
            $group.show();
            $label.text('عدد الأيام');
            $unit.text('يوم');
        } else {
            $group.hide();
        }
    });

    // Milestone counter
    var milestoneIndex = {{ count($referral_setting->bonus_milestones ?? []) }};

    // Add milestone
    $('#addMilestone').on('click', function() {
        var html = `
            <div class="milestone-row d-flex align-items-center" data-index="${milestoneIndex}">
                <div class="flex-grow-1">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="small">عند إتمام</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="milestones[${milestoneIndex}][count]" 
                                           class="form-control" placeholder="مثال: 5" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">إحالة</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="small">نقاط إضافية</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="milestones[${milestoneIndex}][points]" 
                                           class="form-control" placeholder="مثال: 50" min="1" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">نقطة</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <span class="remove-milestone" title="حذف"><i class="feather icon-trash-2"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#milestonesContainer').append(html);
        milestoneIndex++;
    });

    // Remove milestone
    $(document).on('click', '.remove-milestone', function() {
        $(this).closest('.milestone-row').fadeOut(200, function() {
            $(this).remove();
        });
    });
});
</script>
@endsection
