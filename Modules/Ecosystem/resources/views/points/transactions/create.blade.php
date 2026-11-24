@extends('ecosystem::master')

@section('title', 'إضافة معاملة نقاط جديدة')

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
#setting_details_card {
    transition: all 0.3s ease;
}
.calc-type-badge {
    font-size: 0.85em;
    padding: 4px 10px;
    border-radius: 12px;
}
.tier-table td, .tier-table th {
    padding: 4px 8px;
    font-size: 0.85em;
}
.points-result {
    font-size: 1.8em;
    font-weight: bold;
    transition: all 0.3s ease;
}
</style>
@endpush

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إضافة معاملة نقاط جديدة</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.transactions.index') }}">معاملات النقاط</a></li>
                        <li class="breadcrumb-item active">إضافة جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.transactions.store') }}" method="POST">
        @csrf
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.transactions.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ المعاملة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                {{-- معلومات المستخدم --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-user"></i> معلومات المستخدم</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select name="user_type" id="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع المستخدم</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ old('user_type') == $type ? 'selected' : '' }}>
                                                {{ $type == 'employee' ? 'موظف' : 'عميل' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="employee_select_group" style="display: none;">
                                    <label for="employee_id">الموظف <span class="text-danger">*</span></label>
                                    <select name="employee_id" id="employee_id" class="form-control select2 @error('employee_id') is-invalid @enderror">
                                        <option value="">اختر الموظف</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group" id="client_select_group" style="display: none;">
                                    <label for="client_id">العميل <span class="text-danger">*</span></label>
                                    <select name="client_id" id="client_id" class="form-control select2 @error('client_id') is-invalid @enderror">
                                        <option value="">اختر العميل</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $client->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- تفاصيل المعاملة --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-star"></i> تفاصيل المعاملة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="action_type">نوع الإجراء <span class="text-danger">*</span></label>
                                    <select name="action_type" id="action_type" 
                                            class="form-control @error('action_type') is-invalid @enderror" required>
                                        <option value="">اختر نوع الإجراء</option>
                                    </select>
                                    @error('action_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="action_type_hint">اختر نوع المستخدم أولاً</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="amount_group" style="display: none;">
                                    <label for="transaction_amount">مبلغ المعاملة (ريال)</label>
                                    <div class="input-group">
                                        <input type="number" name="transaction_amount" id="transaction_amount" 
                                               class="form-control @error('transaction_amount') is-invalid @enderror" 
                                               value="{{ old('transaction_amount') }}" step="0.01" min="0"
                                               placeholder="أدخل مبلغ المعاملة">
                                        <div class="input-group-append">
                                            <span class="input-group-text">ريال</span>
                                        </div>
                                    </div>
                                    @error('transaction_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="amount_hint"></small>
                                </div>
                            </div>
                        </div>

                        {{-- حقل النقاط المحسوبة (مخفي - يُملأ تلقائياً) --}}
                        <input type="hidden" name="points" id="points" value="{{ old('points', 0) }}">

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="وصف تفصيلي للمعاملة...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">الحالة <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status }}" {{ old('status', 'completed') == $status ? 'selected' : '' }}>
                                                {{ \App\Enums\TransactionStatus::from($status)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="expires_at">تاريخ انتهاء الصلاحية</label>
                                    <input type="date" name="expires_at" id="expires_at" 
                                           class="form-control @error('expires_at') is-invalid @enderror" 
                                           value="{{ old('expires_at') }}">
                                    @error('expires_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">اتركه فارغاً إذا كانت النقاط دائمة</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- إعدادات الفرع --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> إعدادات إضافية</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="branch_id">الفرع</label>
                            <select name="branch_id" id="branch_id" class="form-control @error('branch_id') is-invalid @enderror">
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
                    </div>
                </div>

                {{-- النقاط المحسوبة --}}
                <div class="card border-primary" id="points_result_card" style="display: none;">
                    <div class="card-header bg-light-primary">
                        <h4 class="card-title"><i class="feather icon-star"></i> النقاط المحسوبة</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="points-result" id="points_display">0</div>
                        <small class="text-muted d-block" id="points_calc_desc">نقطة</small>
                    </div>
                </div>

                {{-- تفاصيل الإعداد --}}
                <div class="card border-info" id="setting_details_card" style="display: none;">
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-info"></i> تفاصيل الإعداد</h4>
                    </div>
                    <div class="card-body" id="setting_details_body">
                        {{-- يتم ملؤه عبر JavaScript --}}
                    </div>
                </div>

                {{-- نصائح --}}
                <div class="card border-warning">
                    <div class="card-header bg-light-warning">
                        <h4 class="card-title"><i class="feather icon-award"></i> آلية الحساب</h4>
                    </div>
                    <div class="card-body">
                        <small class="d-block mb-2 text-muted">
                            يتم احتساب النقاط تلقائياً بناءً على إعدادات النقاط المحددة لكل إجراء.
                        </small>
                        <hr>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-1">
                                <i class="feather icon-hash text-primary"></i>
                                <small><strong>ثابت:</strong> نقاط محددة لكل عملية</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-percent text-success"></i>
                                <small><strong>نسبة:</strong> % من مبلغ المعاملة</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-divide text-warning"></i>
                                <small><strong>لكل مبلغ:</strong> نقطة لكل X ريال</small>
                            </li>
                            <li class="mb-1">
                                <i class="feather icon-layers text-danger"></i>
                                <small><strong>متدرج:</strong> حسب شرائح المبلغ</small>
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
    // Initialize Select2
    $('.select2').select2({
        placeholder: 'اختر...',
        allowClear: true,
        width: '100%',
        dir: 'rtl',
        language: {
            noResults: function() { return "لا توجد نتائج"; },
            searching: function() { return "جاري البحث..."; }
        }
    });

    // إعدادات النقاط من قاعدة البيانات (points_settings)
    @php
        $settingsJson = [];
        foreach ($pointsSettings as $userType => $settings) {
            $settingsJson[$userType] = $settings->map(function($s) {
                return [
                    'id' => $s->id,
                    'action_type' => $s->action_type,
                    'action_name' => $s->action_name,
                    'description' => $s->description,
                    'points' => $s->points,
                    'calculation_type' => $s->calculation_type instanceof \App\Enums\CalculationType ? $s->calculation_type->value : $s->calculation_type,
                    'calculation_type_label' => $s->getCalculationTypeLabel(),
                    'calculation_value' => $s->calculation_value,
                    'min_amount' => $s->min_amount,
                    'max_amount' => $s->max_amount,
                    'max_points' => $s->max_points,
                    'tiers_config' => $s->tiers_config,
                    'calc_description' => $s->getCalculationDescription(),
                ];
            })->values();
        }
    @endphp
    const pointsSettings = {!! json_encode($settingsJson, JSON_UNESCAPED_UNICODE) !!};

    let currentSetting = null;

    // تحديث قائمة الإجراءات بناءً على إعدادات النقاط
    function updateActionTypes() {
        const userType = $('#user_type').val();
        const $select = $('#action_type');
        const oldValue = '{{ old('action_type') }}';
        
        if ($select.data('select2')) { $select.select2('destroy'); }
        $select.empty();
        currentSetting = null;
        hideSettingDetails();
        hideAmountField();
        updatePointsDisplay(0);
        
        if (!userType || !pointsSettings[userType]) {
            $select.append('<option value="">اختر نوع المستخدم أولاً</option>');
            $select.prop('disabled', true);
            $('#action_type_hint').text('اختر نوع المستخدم أولاً');
        } else {
            $select.prop('disabled', false);
            $('#action_type_hint').text('اختر الإجراء لعرض تفاصيل الإعداد وحساب النقاط تلقائياً');
            
            $select.append('<option value="">اختر نوع الإجراء</option>');
            
            const settings = pointsSettings[userType];
            settings.forEach(function(setting) {
                const pointsText = setting.points > 0 ? '+' + setting.points : setting.points;
                const badge = setting.calculation_type === 'fixed' ? '' : ' (' + setting.calculation_type_label + ')';
                const selected = (oldValue === setting.action_type) ? ' selected' : '';
                $select.append(
                    '<option value="' + setting.action_type + '"' + selected + '>' + 
                    setting.action_name + ' [' + pointsText + ' نقطة' + badge + ']' +
                    '</option>'
                );
            });
        }

        // تهيئة Select2 مع خاصية البحث
        $select.select2({
            placeholder: 'ابحث عن الإجراء...',
            allowClear: true,
            width: '100%',
            dir: 'rtl',
            language: {
                noResults: function() { return "لا توجد نتائج"; },
                searching: function() { return "جاري البحث..."; }
            }
        });

        if (oldValue) {
            $select.val(oldValue).trigger('change');
        }
    }

    // عرض تفاصيل الإعداد المحدد
    function showSettingDetails(setting) {
        currentSetting = setting;
        const $body = $('#setting_details_body');
        let html = '';

        html += '<div class="mb-2">';
        html += '<strong class="d-block">' + setting.action_name + '</strong>';
        html += '<small class="text-muted">' + (setting.description || '') + '</small>';
        html += '</div>';

        const typeColors = { fixed: 'primary', percentage: 'success', per_amount: 'warning', tiered: 'danger' };
        const typeColor = typeColors[setting.calculation_type] || 'secondary';
        html += '<div class="mb-2">';
        html += '<span class="badge badge-' + typeColor + ' calc-type-badge">' + setting.calculation_type_label + '</span>';
        html += ' <small class="text-muted">' + setting.calc_description + '</small>';
        html += '</div>';

        html += '<div class="mb-2">';
        if (setting.min_amount) {
            html += '<small class="d-block"><i class="feather icon-arrow-down text-warning"></i> حد أدنى للمبلغ: <strong>' + number_format(setting.min_amount) + '</strong> ريال</small>';
        }
        if (setting.max_amount) {
            html += '<small class="d-block"><i class="feather icon-arrow-up text-danger"></i> حد أقصى للمبلغ: <strong>' + number_format(setting.max_amount) + '</strong> ريال</small>';
        }
        if (setting.max_points) {
            html += '<small class="d-block"><i class="feather icon-star text-primary"></i> سقف النقاط: <strong>' + setting.max_points + '</strong> نقطة</small>';
        }
        html += '</div>';

        if (setting.calculation_type === 'tiered' && setting.tiers_config) {
            html += '<div class="mt-2">';
            html += '<small class="d-block mb-1"><strong>الشرائح:</strong></small>';
            html += '<table class="table table-sm table-bordered tier-table mb-0">';
            html += '<thead><tr><th>من</th><th>إلى</th><th>النقاط</th></tr></thead><tbody>';
            setting.tiers_config.forEach(function(tier) {
                const maxText = tier.max ? number_format(tier.max) : '∞';
                html += '<tr><td>' + number_format(tier.min) + '</td><td>' + maxText + '</td><td>' + tier.points + '</td></tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
        }

        $body.html(html);
        $('#setting_details_card').slideDown(200);
        $('#points_result_card').slideDown(200);

        if (setting.calculation_type !== 'fixed') {
            showAmountField(setting);
        } else {
            hideAmountField();
            calculateAndDisplay();
        }
    }

    function hideSettingDetails() {
        $('#setting_details_card').slideUp(200);
        $('#points_result_card').slideUp(200);
    }

    function showAmountField(setting) {
        let hint = '';
        if (setting.calculation_type === 'percentage') {
            hint = 'أدخل المبلغ لحساب ' + setting.calculation_value + '% منه';
        } else if (setting.calculation_type === 'per_amount') {
            hint = 'نقطة واحدة لكل ' + number_format(setting.calculation_value) + ' ' + window.AppCurrency.symbol;
        } else if (setting.calculation_type === 'tiered') {
            hint = 'أدخل المبلغ لتحديد الشريحة المناسبة';
        }
        
        $('#amount_hint').text(hint);
        $('#amount_group').slideDown(200);
        
        if (setting.min_amount) {
            $('#transaction_amount').attr('min', setting.min_amount);
            $('#transaction_amount').attr('placeholder', 'الحد الأدنى: ' + number_format(setting.min_amount) + ' ' + window.AppCurrency.symbol);
        } else {
            $('#transaction_amount').removeAttr('min');
            $('#transaction_amount').attr('placeholder', 'أدخل مبلغ المعاملة');
        }
    }

    function hideAmountField() {
        $('#amount_group').slideUp(200);
        $('#transaction_amount').val('');
    }

    // حساب النقاط من إعدادات النقاط
    function calculateAndDisplay() {
        if (!currentSetting) return;

        const amount = parseFloat($('#transaction_amount').val()) || null;
        let points = 0;

        switch (currentSetting.calculation_type) {
            case 'fixed':
                points = currentSetting.points;
                break;

            case 'percentage':
                if (amount !== null && currentSetting.calculation_value > 0) {
                    if (currentSetting.min_amount && amount < currentSetting.min_amount) {
                        points = 0;
                    } else {
                        let effectiveAmount = amount;
                        if (currentSetting.max_amount && effectiveAmount > currentSetting.max_amount) {
                            effectiveAmount = currentSetting.max_amount;
                        }
                        points = Math.floor(effectiveAmount * (currentSetting.calculation_value / 100));
                    }
                } else {
                    points = currentSetting.points;
                }
                break;

            case 'per_amount':
                if (amount !== null && currentSetting.calculation_value > 0) {
                    if (currentSetting.min_amount && amount < currentSetting.min_amount) {
                        points = 0;
                    } else {
                        let effectiveAmount = amount;
                        if (currentSetting.max_amount && effectiveAmount > currentSetting.max_amount) {
                            effectiveAmount = currentSetting.max_amount;
                        }
                        points = Math.floor(effectiveAmount / currentSetting.calculation_value);
                    }
                } else {
                    points = currentSetting.points;
                }
                break;

            case 'tiered':
                if (amount !== null && currentSetting.tiers_config) {
                    if (currentSetting.min_amount && amount < currentSetting.min_amount) {
                        points = 0;
                    } else {
                        points = currentSetting.points;
                        for (let tier of currentSetting.tiers_config) {
                            const min = tier.min || 0;
                            const max = tier.max || Infinity;
                            if (amount >= min && amount < max) {
                                points = tier.points;
                                break;
                            }
                        }
                    }
                } else {
                    points = currentSetting.points;
                }
                break;

            default:
                points = currentSetting.points;
        }

        // تطبيق سقف النقاط
        if (currentSetting.max_points && Math.abs(points) > currentSetting.max_points) {
            points = currentSetting.points < 0 ? -currentSetting.max_points : currentSetting.max_points;
        }

        // الحفاظ على إشارة النقاط
        if (currentSetting.points < 0 && points > 0) {
            points = -points;
        }

        updatePointsDisplay(points);
    }

    function updatePointsDisplay(points) {
        const $display = $('#points_display');
        const $desc = $('#points_calc_desc');
        const $card = $('#points_result_card');
        
        // تحديث الحقل المخفي الذي يُرسل للسيرفر
        $('#points').val(points);

        const sign = points > 0 ? '+' : '';
        $display.text(sign + number_format(points) + ' نقطة');

        if (points > 0) {
            $display.removeClass('text-danger text-muted').addClass('text-success');
            $card.removeClass('border-danger border-secondary').addClass('border-primary');
        } else if (points < 0) {
            $display.removeClass('text-success text-muted').addClass('text-danger');
            $card.removeClass('border-primary border-secondary').addClass('border-danger');
        } else {
            $display.removeClass('text-success text-danger').addClass('text-muted');
            $card.removeClass('border-primary border-danger').addClass('border-secondary');
        }

        if (currentSetting) {
            let desc = currentSetting.calc_description;
            if (currentSetting.max_points && Math.abs(points) >= currentSetting.max_points) {
                desc += ' (الحد الأقصى)';
            }
            $desc.text(desc);
        }
    }

    function number_format(num) {
        if (num === null || num === undefined) return '0';
        return parseFloat(num).toLocaleString('en-US');
    }

    // Toggle user selection
    function toggleUserSelection() {
        const userType = $('#user_type').val();
        
        if (userType === 'employee') {
            $('#employee_select_group').show();
            $('#client_select_group').hide();
            $('#employee_id').prop('required', true);
            $('#client_id').prop('required', false).val('');
        } else if (userType === 'client') {
            $('#employee_select_group').hide();
            $('#client_select_group').show();
            $('#employee_id').prop('required', false).val('');
            $('#client_id').prop('required', true);
        } else {
            $('#employee_select_group').hide();
            $('#client_select_group').hide();
            $('#employee_id').prop('required', false);
            $('#client_id').prop('required', false);
        }
        
        updateActionTypes();
    }

    // Events
    $('#user_type').on('change', toggleUserSelection);

    $('#action_type').on('change', function() {
        const userType = $('#user_type').val();
        const actionType = $(this).val();
        
        if (!userType || !actionType || !pointsSettings[userType]) {
            currentSetting = null;
            hideSettingDetails();
            return;
        }

        const setting = pointsSettings[userType].find(s => s.action_type === actionType);
        if (setting) {
            showSettingDetails(setting);
        } else {
            hideSettingDetails();
        }
    });

    $('#transaction_amount').on('input', function() {
        calculateAndDisplay();
    });

    // Initial state
    toggleUserSelection();
});
</script>
@endpush
