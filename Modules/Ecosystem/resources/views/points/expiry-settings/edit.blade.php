@extends('ecosystem::master')

@section('title', 'تعديل إعداد انتهاء الصلاحية')

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
.expiry-type-info {
    background: #f0f7ff;
    border: 1px solid #d0e3ff;
    border-radius: 8px;
    padding: 12px 15px;
    margin-top: 10px;
    font-size: 0.85rem;
    display: none;
}
.expiry-type-info.show {
    display: block;
}
.tier-checkbox-group {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px;
}
.tier-checkbox-group .custom-control {
    margin-bottom: 5px;
}
</style>
@endpush

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل إعداد انتهاء الصلاحية</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.expiry-settings.index') }}">إعدادات الانتهاء</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.expiry-settings.update', $expiry_setting->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.expiry-settings.index') }}" class="btn btn-outline-secondary">
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
                <!-- نوع المستخدم ونوع الانتهاء -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-clock"></i> إعدادات الانتهاء الأساسية</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select name="user_type" id="user_type"
                                            class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">-- اختر نوع المستخدم --</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('user_type', $expiry_setting->user_type->value ?? '') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">تطبيق إعداد الانتهاء على أي نوع مستخدم</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="expiry_type">نوع الانتهاء <span class="text-danger">*</span></label>
                                    <select name="expiry_type" id="expiry_type"
                                            class="form-control @error('expiry_type') is-invalid @enderror" required>
                                        <option value="">-- اختر نوع الانتهاء --</option>
                                        @foreach($expiryTypes as $type)
                                            <option value="{{ $type->value }}" {{ old('expiry_type', $expiry_setting->expiry_type->value ?? '') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('expiry_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- حقول FIFO -->
                        <div id="fifoFields" style="{{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::FIFO ? '' : 'display: none;' }}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="expiry_days">عدد أيام الانتهاء <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="expiry_days" id="expiry_days"
                                                   class="form-control @error('expiry_days') is-invalid @enderror"
                                                   value="{{ old('expiry_days', $expiry_setting->expiry_days) }}" min="1">
                                            <div class="input-group-append">
                                                <span class="input-group-text">يوم</span>
                                            </div>
                                        </div>
                                        @error('expiry_days')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">تنتهي صلاحية النقاط بعد هذا العدد من الأيام من تاريخ الكسب</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- حقول التاريخ الثابت / الذكرى السنوية -->
                        <div id="dateFields" style="{{ in_array($expiry_setting->expiry_type, [\App\Enums\ExpiryType::FIXED_DATE, \App\Enums\ExpiryType::ANNIVERSARY]) ? '' : 'display: none;' }}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="expiry_month">شهر الانتهاء <span class="text-danger">*</span></label>
                                        <select name="expiry_month" id="expiry_month"
                                                class="form-control @error('expiry_month') is-invalid @enderror">
                                            <option value="">-- اختر الشهر --</option>
                                            @foreach(range(1, 12) as $month)
                                                <option value="{{ $month }}" {{ old('expiry_month', $expiry_setting->expiry_month) == $month ? 'selected' : '' }}>
                                                    {{ \Carbon\Carbon::create(null, $month)->translatedFormat('F') }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('expiry_month')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="expiry_day">يوم الانتهاء <span class="text-danger">*</span></label>
                                        <select name="expiry_day" id="expiry_day"
                                                class="form-control @error('expiry_day') is-invalid @enderror">
                                            <option value="">-- اختر اليوم --</option>
                                            @foreach(range(1, 31) as $day)
                                                <option value="{{ $day }}" {{ old('expiry_day', $expiry_setting->expiry_day) == $day ? 'selected' : '' }}>
                                                    {{ $day }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('expiry_day')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات نوع الانتهاء -->
                        <div id="expiryInfoFifo" class="expiry-type-info {{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::FIFO ? 'show' : '' }}">
                            <i class="feather icon-info text-primary"></i>
                            <strong>FIFO (الأول يدخل الأول يخرج):</strong>
                            كل دفعة نقاط تنتهي صلاحيتها بعد عدد محدد من الأيام من تاريخ كسبها. عند الاستبدال، يتم خصم النقاط الأقدم أولاً.
                        </div>
                        <div id="expiryInfoFixed" class="expiry-type-info {{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::FIXED_DATE ? 'show' : '' }}">
                            <i class="feather icon-info text-primary"></i>
                            <strong>تاريخ ثابت:</strong>
                            جميع النقاط تنتهي في تاريخ محدد كل عام (مثال: 31 ديسمبر). مناسب للبرامج الموسمية.
                        </div>
                        <div id="expiryInfoAnniversary" class="expiry-type-info {{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::ANNIVERSARY ? 'show' : '' }}">
                            <i class="feather icon-info text-primary"></i>
                            <strong>ذكرى سنوية:</strong>
                            النقاط تنتهي في ذكرى انضمام المستخدم. كل مستخدم له تاريخ انتهاء مختلف.
                        </div>
                        <div id="expiryInfoNever" class="expiry-type-info {{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::NEVER ? 'show' : '' }}">
                            <i class="feather icon-info text-success"></i>
                            <strong>لا تنتهي:</strong>
                            النقاط تبقى صالحة إلى الأبد. لا حاجة لتحديد مدة.
                        </div>
                    </div>
                </div>

                <!-- إعدادات التحذير والتذكير -->
                <div class="card" id="warningCard" style="{{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::NEVER ? 'display: none;' : '' }}">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-alert-triangle"></i> التحذير والتذكير</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="warning_days">أيام التحذير المبكر</label>
                                    <div class="input-group">
                                        <input type="number" name="warning_days" id="warning_days"
                                               class="form-control @error('warning_days') is-invalid @enderror"
                                               value="{{ old('warning_days', $expiry_setting->warning_days) }}" min="1">
                                        <div class="input-group-append">
                                            <span class="input-group-text">يوم</span>
                                        </div>
                                    </div>
                                    @error('warning_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">إرسال تحذير قبل انتهاء الصلاحية بعدد الأيام المحدد</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="grace_period_days">فترة السماح</label>
                                    <div class="input-group">
                                        <input type="number" name="grace_period_days" id="grace_period_days"
                                               class="form-control @error('grace_period_days') is-invalid @enderror"
                                               value="{{ old('grace_period_days', $expiry_setting->grace_period_days) }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">يوم</span>
                                        </div>
                                    </div>
                                    @error('grace_period_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">أيام إضافية بعد الانتهاء يمكن خلالها استخدام النقاط</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reminder_days_input">أيام التذكير</label>
                            <input type="text" name="reminder_days_input" id="reminder_days_input"
                                   class="form-control @error('reminder_days') is-invalid @enderror"
                                   value="{{ old('reminder_days_input', $expiry_setting->reminder_days ? implode(',', $expiry_setting->reminder_days) : '') }}"
                                   placeholder="مثال: 30,15,7,3,1">
                            @error('reminder_days')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">أدخل أيام التذكير مفصولة بفاصلة (قبل الانتهاء). مثال: 30,15,7,3,1</small>
                        </div>
                    </div>
                </div>

                <!-- استثناء المستويات -->
                <div class="card" id="excludeTiersCard" style="{{ $expiry_setting->expiry_type === \App\Enums\ExpiryType::NEVER ? 'display: none;' : '' }}">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-shield"></i> استثناء المستويات</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">اختر المستويات التي لا تنطبق عليها سياسة الانتهاء (نقاطهم لا تنتهي)</p>
                        <div class="tier-checkbox-group">
                            @forelse($tiers as $tier)
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="exclude_tiers[]"
                                           id="tier{{ $tier->id }}" value="{{ $tier->id }}"
                                           {{ in_array($tier->id, old('exclude_tiers', $expiry_setting->exclude_tiers ?? [])) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="tier{{ $tier->id }}">
                                        {{ $tier->name }}
                                        <small class="text-muted">({{ number_format($tier->min_points) }}+ نقطة)</small>
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted mb-0">لا توجد مستويات متاحة</p>
                            @endforelse
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
                                    <option value="{{ $branch->id }}" {{ old('branch_id', $expiry_setting->branch_id) == $branch->id ? 'selected' : '' }}>
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
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $expiry_setting->is_active) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عند التفعيل، سيتم تطبيق سياسة الانتهاء هذه على النقاط الجديدة</small>
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
                                <td>{{ $expiry_setting->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">آخر تحديث:</td>
                                <td>{{ $expiry_setting->updated_at->format('Y-m-d H:i') }}</td>
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

    // Show/hide fields based on expiry type
    $('#expiry_type').on('change', function() {
        var type = $(this).val();

        // Hide all
        $('#fifoFields, #dateFields').hide();
        $('.expiry-type-info').removeClass('show');

        switch(type) {
            case 'fifo':
                $('#fifoFields').show();
                $('#expiryInfoFifo').addClass('show');
                $('#warningCard, #excludeTiersCard').show();
                break;
            case 'fixed_date':
                $('#dateFields').show();
                $('#expiryInfoFixed').addClass('show');
                $('#warningCard, #excludeTiersCard').show();
                break;
            case 'anniversary':
                $('#dateFields').show();
                $('#expiryInfoAnniversary').addClass('show');
                $('#warningCard, #excludeTiersCard').show();
                break;
            case 'never':
                $('#expiryInfoNever').addClass('show');
                $('#warningCard, #excludeTiersCard').hide();
                break;
            default:
                $('#warningCard, #excludeTiersCard').show();
                break;
        }
    });
});
</script>
@endsection
