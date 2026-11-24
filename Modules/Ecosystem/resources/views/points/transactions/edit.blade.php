@extends('ecosystem::master')

@section('title', 'تعديل معاملة النقاط')

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
.calc-type-badge {
    font-size: 0.85em;
    padding: 4px 10px;
    border-radius: 12px;
}
.tier-table td, .tier-table th {
    padding: 4px 8px;
    font-size: 0.85em;
}
</style>
@endpush

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تعديل معاملة النقاط</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.transactions.index') }}">معاملات النقاط</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.transactions.update', $transaction) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.transactions.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <div>
                                <a href="{{ route('ecosystem.points.transactions.show', $transaction->id) }}" class="btn btn-outline-info">
                                    <i class="feather icon-eye"></i> عرض
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i> حفظ التغييرات
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
                        <h4 class="card-title"><i class="feather icon-user"></i> معلومات المستخدم</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="feather icon-info"></i>
                            <strong>ملاحظة:</strong> لا يمكن تغيير المستخدم أو النقاط بعد إنشاء المعاملة. يمكنك فقط تعديل الوصف والحالة.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <input type="text" class="form-control" 
                                           value="{{ $transaction->user_type->value == 'employee' ? 'موظف' : 'عميل' }}" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>المستخدم</label>
                                    <input type="text" class="form-control" 
                                           value="{{ $transaction->user_type->value == 'employee' ? ($transaction->employee?->full_name ?? 'غير محدد') : ($transaction->client?->full_name ?? 'غير محدد') }}" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>النقاط</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               value="{{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}" disabled>
                                        <div class="input-group-append">
                                            <span class="input-group-text {{ $transaction->points > 0 ? 'bg-success' : 'bg-danger' }} text-white">
                                                <i class="feather icon-star"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <small class="text-muted">لا يمكن تعديل النقاط</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="form-control @error('description') is-invalid @enderror" 
                                      placeholder="وصف تفصيلي للمعاملة...">{{ old('description', $transaction->description) }}</textarea>
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
                                            <option value="{{ $status }}" {{ old('status', $transaction->status->value) == $status ? 'selected' : '' }}>
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
                                           value="{{ old('expires_at', $transaction->expires_at?->format('Y-m-d')) }}">
                                    @error('expires_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-info"></i> معلومات الأرصدة</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted">الرصيد قبل المعاملة</label>
                            <h4 class="mb-0">{{ number_format($transaction->balance_before) }} <small>نقطة</small></h4>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted">الرصيد بعد المعاملة</label>
                            <h4 class="mb-0 {{ $transaction->points > 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($transaction->balance_after) }} <small>نقطة</small>
                            </h4>
                        </div>
                        <div>
                            <label class="text-muted">الفرق</label>
                            <h4 class="mb-0 {{ $transaction->points > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }} <small>نقطة</small>
                            </h4>
                        </div>
                    </div>
                </div>

                {{-- تفاصيل إعداد النقاط الحالي --}}
                <div class="card border-info" id="setting_details_card" @if(!$currentSetting) style="display: none;" @endif>
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-settings"></i> إعداد النقاط</h4>
                    </div>
                    <div class="card-body" id="setting_details_body">
                        @if($currentSetting)
                            <div class="mb-2">
                                <strong class="d-block">{{ $currentSetting->action_name }}</strong>
                                <small class="text-muted">{{ $currentSetting->description }}</small>
                            </div>
                            @php
                                $typeColors = ['fixed' => 'primary', 'percentage' => 'success', 'per_amount' => 'warning', 'tiered' => 'danger'];
                                $calcType = $currentSetting->calculation_type instanceof \App\Enums\CalculationType 
                                    ? $currentSetting->calculation_type->value 
                                    : $currentSetting->calculation_type;
                                $typeColor = $typeColors[$calcType] ?? 'secondary';
                            @endphp
                            <div class="mb-2">
                                <span class="badge badge-{{ $typeColor }} calc-type-badge">{{ $currentSetting->getCalculationTypeLabel() }}</span>
                                <small class="text-muted">{{ $currentSetting->getCalculationDescription() }}</small>
                            </div>
                            @if($currentSetting->min_amount)
                                <small class="d-block"><i class="feather icon-arrow-down text-warning"></i> حد أدنى للمبلغ: <strong>{{ number_format($currentSetting->min_amount) }}</strong> ريال</small>
                            @endif
                            @if($currentSetting->max_amount)
                                <small class="d-block"><i class="feather icon-arrow-up text-danger"></i> حد أقصى للمبلغ: <strong>{{ number_format($currentSetting->max_amount) }}</strong> ريال</small>
                            @endif
                            @if($currentSetting->max_points)
                                <small class="d-block"><i class="feather icon-star text-primary"></i> سقف النقاط: <strong>{{ $currentSetting->max_points }}</strong> نقطة</small>
                            @endif
                            @if($calcType === 'tiered' && $currentSetting->tiers_config)
                                <div class="mt-2">
                                    <small class="d-block mb-1"><strong>الشرائح:</strong></small>
                                    <table class="table table-sm table-bordered tier-table mb-0">
                                        <thead><tr><th>من</th><th>إلى</th><th>النقاط</th></tr></thead>
                                        <tbody>
                                            @foreach($currentSetting->tiers_config as $tier)
                                                <tr>
                                                    <td>{{ number_format($tier['min'] ?? 0) }}</td>
                                                    <td>{{ isset($tier['max']) ? number_format($tier['max']) : '∞' }}</td>
                                                    <td>{{ $tier['points'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="card border-light-secondary">
                    <div class="card-header bg-light">
                        <h4 class="card-title"><i class="feather icon-clock"></i> معلومات الإنشاء</h4>
                    </div>
                    <div class="card-body">
                        <small class="d-block mb-2">
                            <strong>أنشئ بواسطة:</strong><br>
                            {{ $transaction->creator?->name ?? 'غير محدد' }}
                        </small>
                        <small class="d-block mb-2">
                            <strong>تاريخ الإنشاء:</strong><br>
                            {{ $transaction->created_at->format('Y-m-d H:i') }}
                        </small>
                        <small class="d-block mb-2">
                            <strong>آخر تحديث:</strong><br>
                            {{ $transaction->updated_at->format('Y-m-d H:i') }}
                        </small>
                        @if($transaction->branch)
                        <small class="d-block">
                            <strong>الفرع:</strong><br>
                            {{ $transaction->branch->name }}
                        </small>
                        @endif
                    </div>
                </div>

                <div class="card border-{{ $transaction->points > 0 ? 'success' : 'danger' }}">
                    <div class="card-header bg-light-{{ $transaction->points > 0 ? 'success' : 'danger' }}">
                        <h4 class="card-title">
                            <i class="feather icon-{{ $transaction->points > 0 ? 'plus-circle' : 'minus-circle' }}"></i>
                            نوع المعاملة
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($transaction->points > 0)
                            <div class="alert alert-success mb-0">
                                <i class="feather icon-trending-up"></i>
                                <strong>إضافة نقاط</strong>
                                <p class="mb-0 mt-1">تمت إضافة {{ number_format($transaction->points) }} نقطة للمستخدم</p>
                            </div>
                        @else
                            <div class="alert alert-danger mb-0">
                                <i class="feather icon-trending-down"></i>
                                <strong>خصم نقاط</strong>
                                <p class="mb-0 mt-1">تم خصم {{ number_format(abs($transaction->points)) }} نقطة من المستخدم</p>
                            </div>
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

    const currentActionType = '{{ old('action_type', $transaction->action_type) }}';
    const currentUserType = '{{ $transaction->user_type->value ?? $transaction->user_type }}';

    function populateActionTypes() {
        const $select = $('#action_type');
        if ($select.data('select2')) { $select.select2('destroy'); }
        $select.empty();

        const settings = pointsSettings[currentUserType] || [];
        
        if (settings.length === 0) {
            // إذا لم توجد إعدادات، أظهر القيمة الحالية
            $select.append('<option value="' + currentActionType + '" selected>' + currentActionType + '</option>');
        } else {
            $select.append('<option value="">اختر نوع الإجراء</option>');

            let hasCurrentValue = false;
            
            // تحقق من وجود القيمة الحالية في القائمة
            settings.forEach(function(setting) {
                if (setting.action_type === currentActionType) hasCurrentValue = true;
            });

            // إذا القيمة الحالية غير موجودة في قائمة الإعدادات، أضفها كخيار
            if (!hasCurrentValue && currentActionType) {
                $select.append('<option value="' + currentActionType + '" selected>' + currentActionType + ' (إجراء غير مُعد)</option>');
            }

            settings.forEach(function(setting) {
                const pointsText = setting.points > 0 ? '+' + setting.points : setting.points;
                const badge = setting.calculation_type === 'fixed' ? '' : ' (' + setting.calculation_type_label + ')';
                const selected = (currentActionType === setting.action_type) ? ' selected' : '';
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
    }

    // تحديث كارت تفاصيل الإعداد عند تغيير الإجراء
    $('#action_type').on('change', function() {
        const actionType = $(this).val();
        const settings = pointsSettings[currentUserType] || [];
        const setting = settings.find(s => s.action_type === actionType);

        if (!setting) {
            $('#setting_details_card').slideUp(200);
            return;
        }

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

        if (setting.min_amount) {
            html += '<small class="d-block"><i class="feather icon-arrow-down text-warning"></i> حد أدنى: <strong>' + Number(setting.min_amount).toLocaleString('en-US') + '</strong> ريال</small>';
        }
        if (setting.max_amount) {
            html += '<small class="d-block"><i class="feather icon-arrow-up text-danger"></i> حد أقصى: <strong>' + Number(setting.max_amount).toLocaleString('en-US') + '</strong> ريال</small>';
        }
        if (setting.max_points) {
            html += '<small class="d-block"><i class="feather icon-star text-primary"></i> سقف النقاط: <strong>' + setting.max_points + '</strong> نقطة</small>';
        }

        if (setting.calculation_type === 'tiered' && setting.tiers_config) {
            html += '<div class="mt-2"><small class="d-block mb-1"><strong>الشرائح:</strong></small>';
            html += '<table class="table table-sm table-bordered tier-table mb-0">';
            html += '<thead><tr><th>من</th><th>إلى</th><th>النقاط</th></tr></thead><tbody>';
            setting.tiers_config.forEach(function(tier) {
                const maxText = tier.max ? Number(tier.max).toLocaleString('en-US') : '∞';
                html += '<tr><td>' + Number(tier.min).toLocaleString('en-US') + '</td><td>' + maxText + '</td><td>' + tier.points + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        $body.html(html);
        $('#setting_details_card').slideDown(200);
    });

    populateActionTypes();
});
</script>
@endpush
