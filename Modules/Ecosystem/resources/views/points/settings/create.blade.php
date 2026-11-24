@extends('ecosystem::master')

@section('title', 'إضافة إعداد نقاط جديد')

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
</style>
@endpush

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إضافة إعداد نقاط جديد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.settings.index') }}">إعدادات النقاط</a></li>
                        <li class="breadcrumb-item active">إضافة جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.settings.store') }}" method="POST">
        @csrf
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.settings.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ الإعداد
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
                        <h4 class="card-title"><i class="feather icon-info"></i> معلومات الإعداد</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select name="user_type" id="user_type" class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">-- اختر نوع المستخدم --</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ old('user_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\UserType::from($type)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">هل هذا الإعداد للعملاء أم الموظفين؟</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="action_type">نوع الإجراء <span class="text-danger">*</span></label>
                                    <select name="action_type" id="action_type" 
                                            class="form-control @error('action_type') is-invalid @enderror" required>
                                        <option value="">-- اختر نوع المستخدم أولاً --</option>
                                    </select>
                                    @error('action_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">الإجراءات متاحة حسب نوع المستخدم المحدد</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="action_name">اسم الإجراء <span class="text-danger">*</span></label>
                            <input type="text" name="action_name" id="action_name" 
                                   class="form-control @error('action_name') is-invalid @enderror" 
                                   value="{{ old('action_name') }}" required placeholder="مثال: إنشاء فاتورة">
                            @error('action_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">الاسم الذي سيظهر للمستخدمين</small>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="وصف تفصيلي للإجراء...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="points">عدد النقاط الافتراضي <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="points" id="points" 
                                               class="form-control @error('points') is-invalid @enderror" 
                                               value="{{ old('points', 0) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">نقطة</span>
                                        </div>
                                    </div>
                                    @error('points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">استخدم قيمة موجبة للإضافة أو سالبة للخصم</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="branch_id">الفرع</label>
                                    <select name="branch_id" id="branch_id" class="form-control select2 @error('branch_id') is-invalid @enderror">
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
                                    <small class="text-muted">اتركه فارغاً لتطبيقه على جميع الفروع</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- قسم طريقة حساب النقاط -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calculator"></i> طريقة حساب النقاط</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="calculation_type">نوع الحساب <span class="text-danger">*</span></label>
                                    <select name="calculation_type" id="calculation_type" class="form-control @error('calculation_type') is-invalid @enderror" required>
                                        @foreach($calculationTypes as $type)
                                            <option value="{{ $type->value }}" 
                                                    data-needs-value="{{ $type->needsCalculationValue() ? '1' : '0' }}"
                                                    data-needs-tiers="{{ $type->needsTiersConfig() ? '1' : '0' }}"
                                                    data-value-label="{{ $type->calculationValueLabel() }}"
                                                    {{ old('calculation_type', 'fixed') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }} - {{ $type->description() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('calculation_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6" id="calculationValueGroup" style="display: none;">
                                <div class="form-group">
                                    <label for="calculation_value" id="calculationValueLabel">قيمة الحساب</label>
                                    <input type="number" step="0.0001" name="calculation_value" id="calculation_value" 
                                           class="form-control @error('calculation_value') is-invalid @enderror" 
                                           value="{{ old('calculation_value') }}" min="0">
                                    @error('calculation_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="min_amount">الحد الأدنى للمعاملة</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="min_amount" id="min_amount" 
                                               class="form-control @error('min_amount') is-invalid @enderror" 
                                               value="{{ old('min_amount') }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">ريال</span>
                                        </div>
                                    </div>
                                    @error('min_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">المعاملات أقل من هذا المبلغ لن تحصل على نقاط</small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="max_amount">الحد الأقصى للمعاملة</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="max_amount" id="max_amount" 
                                               class="form-control @error('max_amount') is-invalid @enderror" 
                                               value="{{ old('max_amount') }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">ريال</span>
                                        </div>
                                    </div>
                                    @error('max_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">المبلغ الأقصى المحتسب للنقاط</small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="max_points">الحد الأقصى للنقاط</label>
                                    <div class="input-group">
                                        <input type="number" name="max_points" id="max_points" 
                                               class="form-control @error('max_points') is-invalid @enderror" 
                                               value="{{ old('max_points') }}" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">نقطة</span>
                                        </div>
                                    </div>
                                    @error('max_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">أقصى عدد نقاط يمكن الحصول عليه</small>
                                </div>
                            </div>
                        </div>

                        <!-- إعدادات المستويات للحساب المتدرج -->
                        <div id="tiersConfigSection" style="display: none;">
                            <hr>
                            <h5><i class="feather icon-layers"></i> إعدادات المستويات</h5>
                            <p class="text-muted mb-2">حدد النقاط لكل نطاق من قيم المعاملات</p>
                            
                            <div id="tiersContainer">
                                <div class="tier-row row mb-2" data-tier-index="0">
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" name="tiers[0][min]" class="form-control" placeholder="من (ريال)" value="0">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" name="tiers[0][max]" class="form-control" placeholder="إلى (ريال)">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="tiers[0][points]" class="form-control" placeholder="النقاط">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-danger btn-sm remove-tier" disabled>
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addTierBtn">
                                <i class="feather icon-plus"></i> إضافة مستوى
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
                                <span class="switch-label">الإعداد نشط</span>
                                <label class="ios-switch">
                                    <input type="checkbox" id="is_active" name="is_active" value="1"
                                           {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عند التفعيل، سيتم تطبيق هذا الإعداد تلقائياً</small>
                        </div>

                        {{-- سبب التعطيل --}}
                        <div class="form-group mt-1" id="disabledReasonGroup" style="display: none;">
                            <label for="disabled_reason">سبب التعطيل</label>
                            <textarea class="form-control" id="disabled_reason" name="disabled_reason" rows="2"
                                      placeholder="اذكر سبب تعطيل هذا الإعداد (اختياري)">{{ old('disabled_reason') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card" id="tipsCard">
                    <div class="card-header py-75">
                        <h4 class="card-title mb-0" style="font-size: 0.95rem;"><i class="feather icon-help-circle"></i> نصائح وإرشادات</h4>
                    </div>
                    <div class="card-body p-0">
                        {{-- نصائح عامة --}}
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-success mr-50"></span>
                                    <small>نقاط <strong>موجبة</strong> للمكافأة</small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-danger mr-50"></span>
                                    <small>نقاط <strong>سالبة</strong> للعقوبة والخصم</small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-primary mr-50"></span>
                                    <small>حدد <strong>فرع</strong> لإعدادات خاصة بفرع معين</small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-warning mr-50"></span>
                                    <small>يمكنك <strong>تعطيل</strong> الإعداد مؤقتاً دون حذفه</small>
                                </div>
                            </li>
                        </ul>

                        {{-- إرشادات ديناميكية حسب نوع الحساب --}}
                        <div id="dynamicTips" style="display: none;">
                            <div class="border-top mx-1"></div>
                            <div class="p-1">
                                <small class="font-weight-bold d-block mb-75" id="tipCalcLabel"></small>
                                <div id="tipCalcContent"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-warning">
                    <div class="card-header bg-light-warning">
                        <h4 class="card-title"><i class="feather icon-code"></i> أمثلة للإجراءات</h4>
                    </div>
                    <div class="card-body">
                        <small class="d-block mb-2"><code>invoice_created</code> - إنشاء فاتورة</small>
                        <small class="d-block mb-2"><code>invoice_paid</code> - دفع فاتورة</small>
                        <small class="d-block mb-2"><code>visit_completed</code> - إتمام زيارة</small>
                        <small class="d-block mb-2"><code>attendance_on_time</code> - حضور في الموعد</small>
                        <small class="d-block mb-2"><code>review_submitted</code> - تقديم تقييم</small>
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
    // أنواع الإجراءات من ActionType Enum مجمّعة حسب نوع المستخدم
    const actionTypes = @json($actionTypes);
    const oldActionType = '{{ old('action_type') }}';

    // تحديث قائمة الإجراءات بناءً على نوع المستخدم
    function updateActionTypes() {
        const userType = $('#user_type').val();
        const $select = $('#action_type');
        if ($select.data('select2')) { $select.select2('destroy'); }
        $select.empty();

        if (!userType || !actionTypes[userType]) {
            $select.append('<option value="">-- اختر نوع المستخدم أولاً --</option>');
            $select.prop('disabled', true);
        } else {
            $select.prop('disabled', false);
            $select.append('<option value="">-- اختر نوع الإجراء --</option>');

            const groups = actionTypes[userType];
            Object.keys(groups).forEach(function(groupName) {
                const $optgroup = $('<optgroup label="' + groupName + '">');
                groups[groupName].forEach(function(item) {
                    const selected = (oldActionType === item.value) ? ' selected' : '';
                    const negativeIndicator = item.is_negative ? ' 🔻' : ' ✅';
                    $optgroup.append('<option value="' + item.value + '" data-label="' + item.label + '"' + selected + '>' + item.label + negativeIndicator + ' (' + item.value + ')</option>');
                });
                $select.append($optgroup);
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
    }

    // تحديث اسم الإجراء تلقائياً عند اختيار نوع الإجراء
    $('#action_type').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const label = selectedOption.data('label');
        if (label && !$('#action_name').val()) {
            $('#action_name').val(label);
        }
    });

    $('#user_type').on('change', function() {
        updateActionTypes();
        // مسح حقل اسم الإجراء عند تغيير نوع المستخدم
        $('#action_name').val('');
    });

    updateActionTypes(); // حالة أولية

    // Points input validation and styling
    $('#points').on('input', function() {
        const value = parseInt($(this).val());
        const parent = $(this).closest('.form-group');
        
        if (value > 0) {
            parent.find('.input-group-text').removeClass('bg-danger text-white').addClass('bg-success text-white');
        } else if (value < 0) {
            parent.find('.input-group-text').removeClass('bg-success text-white').addClass('bg-danger text-white');
        } else {
            parent.find('.input-group-text').removeClass('bg-success bg-danger text-white');
        }
    });

    // Calculation type change handler
    function updateCalculationFields() {
        const selectedOption = $('#calculation_type option:selected');
        const selectedValue = selectedOption.val();
        const needsValue = selectedOption.data('needs-value') === 1;
        const needsTiers = selectedOption.data('needs-tiers') === 1;
        const valueLabel = selectedOption.data('value-label');

        // Show/hide calculation value field
        if (needsValue) {
            $('#calculationValueGroup').show();
            $('#calculationValueLabel').text(valueLabel);
        } else {
            $('#calculationValueGroup').hide();
        }

        // Show/hide tiers config section
        if (needsTiers) {
            $('#tiersConfigSection').show();
        } else {
            $('#tiersConfigSection').hide();
        }

        // Update instructions based on calculation type
        updateInstructions(selectedValue);
    }

    // Instructions content for each calculation type
    function updateInstructions(type) {
        const tips = {
            'fixed': {
                label: 'إرشادات النقاط الثابتة',
                content: `
                    <small class="d-block mb-50">نقاط ثابتة لا تتغير بتغير المبلغ</small>
                    <div class="bg-light rounded p-75 mb-50">
                        <small class="text-muted d-block mb-25">مثال:</small>
                        <small class="d-block">زيارة → <strong>+3</strong> نقاط دائماً</small>
                        <small class="d-block">غياب → <strong>-20</strong> نقطة دائماً</small>
                    </div>
                    <small class="text-muted"><i class="feather icon-alert-circle" style="font-size: 11px;"></i> حقل "قيمة الحساب" غير مطلوب</small>
                `
            },
            'per_amount': {
                label: 'إرشادات نقطة لكل مبلغ',
                content: `
                    <small class="d-block mb-50">المعادلة: <code class="small">النقاط = المبلغ ÷ قيمة الحساب</code></small>
                    <div class="bg-light rounded p-75 mb-50">
                        <small class="text-muted d-block mb-25">مثال (قيمة الحساب = 200):</small>
                        <small class="d-block">400 ريال → <strong>2</strong> نقطة</small>
                        <small class="d-block">1000 ريال → <strong>5</strong> نقاط</small>
                        <small class="d-block text-muted">10000 ريال → 25 نقطة (سقف)</small>
                    </div>
                    <small class="text-muted"><i class="feather icon-alert-circle" style="font-size: 11px;"></i> حدد الحد الأقصى للنقاط لمنع القيم المبالغ فيها</small>
                `
            },
            'percentage': {
                label: 'إرشادات النسبة المئوية',
                content: `
                    <small class="d-block mb-50">المعادلة: <code class="small">النقاط = المبلغ × (النسبة ÷ 100)</code></small>
                    <div class="bg-light rounded p-75 mb-50">
                        <small class="text-muted d-block mb-25">مثال (نسبة 1%):</small>
                        <small class="d-block">200 ريال → <strong>2</strong> نقطة</small>
                        <small class="d-block">1000 ريال → <strong>10</strong> نقاط</small>
                        <small class="d-block text-muted">30000 ريال → 200 نقطة (سقف)</small>
                    </div>
                    <small class="text-muted"><i class="feather icon-alert-circle" style="font-size: 11px;"></i> النسبة الموصى بها: 0.5% - 2%</small>
                `
            },
            'tiered': {
                label: 'إرشادات المستويات المتدرجة',
                content: `
                    <small class="d-block mb-50">نقاط مختلفة حسب نطاق المبلغ</small>
                    <div class="bg-light rounded p-75 mb-50">
                        <small class="text-muted d-block mb-25">مثال:</small>
                        <small class="d-block">50-100 ريال → <strong>5</strong> نقاط</small>
                        <small class="d-block">100-300 ريال → <strong>10</strong> نقاط</small>
                        <small class="d-block">300-500 ريال → <strong>20</strong> نقطة</small>
                        <small class="d-block">500+ ريال → <strong>35</strong> نقطة</small>
                    </div>
                    <small class="text-muted"><i class="feather icon-alert-circle" style="font-size: 11px;"></i> اترك "إلى" فارغاً في المستوى الأخير</small>
                `
            }
        };

        if (type && tips[type]) {
            $('#tipCalcLabel').text(tips[type].label);
            $('#tipCalcContent').html(tips[type].content);
            $('#dynamicTips').slideDown(200);
        } else {
            $('#dynamicTips').slideUp(200);
        }
    }

    $('#calculation_type').on('change', updateCalculationFields);
    updateCalculationFields(); // Initial call

    // Tier management
    let tierIndex = 1;

    $('#addTierBtn').on('click', function() {
        const newTier = `
            <div class="tier-row row mb-2" data-tier-index="${tierIndex}">
                <div class="col-md-3">
                    <input type="number" step="0.01" name="tiers[${tierIndex}][min]" class="form-control" placeholder="من (ريال)">
                </div>
                <div class="col-md-3">
                    <input type="number" step="0.01" name="tiers[${tierIndex}][max]" class="form-control" placeholder="إلى (ريال)">
                </div>
                <div class="col-md-3">
                    <input type="number" name="tiers[${tierIndex}][points]" class="form-control" placeholder="النقاط">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-danger btn-sm remove-tier">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </div>
            </div>
        `;
        $('#tiersContainer').append(newTier);
        tierIndex++;
        updateRemoveButtons();
    });

    $(document).on('click', '.remove-tier', function() {
        $(this).closest('.tier-row').remove();
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        const tierRows = $('.tier-row');
        if (tierRows.length <= 1) {
            tierRows.find('.remove-tier').prop('disabled', true);
        } else {
            tierRows.find('.remove-tier').prop('disabled', false);
        }
    }

    // إظهار/إخفاء حقل سبب التعطيل
    function toggleDisabledReason() {
        if ($('#is_active').is(':checked')) {
            $('#disabledReasonGroup').slideUp(200);
        } else {
            $('#disabledReasonGroup').slideDown(200);
        }
    }

    $('#is_active').on('change', toggleDisabledReason);
    toggleDisabledReason(); // حالة أولية
});
</script>
@endpush
