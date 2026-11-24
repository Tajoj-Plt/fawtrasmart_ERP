@extends('ecosystem::master')

@section('title', 'طلب استبدال مكافأة جديد')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">طلب استبدال مكافأة جديد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.redemptions.index') }}">طلبات الاستبدال</a></li>
                        <li class="breadcrumb-item active">طلب جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    @include('layouts.alerts.error')

    <form action="{{ route('ecosystem.points.redemptions.store') }}" method="POST" id="redemptionForm">
        @csrf

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.redemptions.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-right"></i> رجوع
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="feather icon-send"></i> إرسال طلب الاستبدال
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- اختيار المستفيد (أولاً) -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-user"></i> المستفيد</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم <span class="text-danger">*</span></label>
                                    <select name="user_type" id="user_type" 
                                            class="form-control @error('user_type') is-invalid @enderror" required>
                                        <option value="">-- اختر --</option>
                                        <option value="employee" {{ old('user_type') == 'employee' ? 'selected' : '' }}>موظف</option>
                                        <option value="client" {{ old('user_type') == 'client' ? 'selected' : '' }}>عميل</option>
                                    </select>
                                    @error('user_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- اختيار الموظف -->
                                <div class="form-group" id="employee-group" style="display: none;">
                                    <label for="employee_id">الموظف <span class="text-danger">*</span></label>
                                    <select name="employee_id" id="employee_id" 
                                            class="form-control select2 @error('employee_id') is-invalid @enderror">
                                        <option value="">-- اختر الموظف --</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" 
                                                    data-balance="{{ $employee->current_points_balance }}"
                                                    {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->full_name }} (رصيد: {{ number_format($employee->current_points_balance) }} نقطة)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- اختيار العميل -->
                                <div class="form-group" id="client-group" style="display: none;">
                                    <label for="client_id">العميل <span class="text-danger">*</span></label>
                                    <select name="client_id" id="client_id" 
                                            class="form-control select2 @error('client_id') is-invalid @enderror">
                                        <option value="">-- اختر العميل --</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" 
                                                    data-balance="{{ $client->current_points_balance }}"
                                                    {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $client->full_name }} (رصيد: {{ number_format($client->current_points_balance) }} نقطة)
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

                <!-- اختيار المكافأة (يتم تفعيلها بعد اختيار نوع المستخدم) -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> اختيار المكافأة</h4>
                    </div>
                    <div class="card-body">
                        <div id="reward-disabled-hint" class="alert alert-warning" style="{{ old('user_type') ? 'display:none;' : '' }}">
                            <i class="feather icon-alert-circle"></i> يرجى اختيار نوع المستخدم أولاً لعرض المكافآت المتاحة
                        </div>
                        <div class="form-group">
                            <label for="reward_id">المكافأة <span class="text-danger">*</span></label>
                            <select name="reward_id" id="reward_id" 
                                    class="form-control select2 @error('reward_id') is-invalid @enderror" required
                                    {{ old('user_type') ? '' : 'disabled' }}>
                                <option value="">-- اختر المكافأة --</option>
                                @foreach($rewards as $reward)
                                    <option value="{{ $reward->id }}" 
                                            {{ old('reward_id') == $reward->id ? 'selected' : '' }}
                                            data-points="{{ $reward->points_required }}"
                                            data-value="{{ $reward->reward_value }}"
                                            data-type="{{ $reward->reward_type->value }}"
                                            data-type-label="{{ $reward->reward_type->label() }}"
                                            data-type-icon="{{ $reward->reward_type->icon() }}"
                                            data-quantity="{{ $reward->getRemainingQuantity() }}"
                                            data-max-per-user="{{ $reward->max_per_user }}"
                                            data-user-type="{{ $reward->user_type->value }}"
                                            data-product-id="{{ $reward->product_id ?? '' }}"
                                            data-max-discount="{{ $reward->max_discount_value ?? '' }}"
                                            data-min-order="{{ $reward->min_order_amount ?? '' }}"
                                            data-validity-days="{{ $reward->validity_days ?? '' }}"
                                            data-needs-code="{{ $reward->needsVoucherCode() ? '1' : '0' }}"
                                            data-needs-invoice="{{ $reward->needsInvoiceAmount() ? '1' : '0' }}"
                                            data-single-qty="{{ $reward->singleQuantityOnly() ? '1' : '0' }}">
                                        {{ $reward->reward_type->icon() }} {{ $reward->name }} 
                                        ({{ number_format($reward->points_required) }} نقطة)
                                        @if($reward->getRemainingQuantity() !== null)
                                            - متوفر: {{ $reward->getRemainingQuantity() }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('reward_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- معاينة المكافأة المحددة -->
                        <div id="reward-preview" class="alert alert-light-info" style="display: none;">
                            <div class="d-flex align-items-center">
                                <span class="font-large-1 mr-2" id="preview-reward-icon"></span>
                                <div>
                                    <h6 class="mb-0" id="preview-reward-name"></h6>
                                    <small class="text-muted">
                                        النوع: <span id="preview-reward-type"></span> |
                                        النقاط: <strong id="preview-reward-points" class="text-primary"></strong> |
                                        القيمة: <strong id="preview-reward-value" class="text-success"></strong>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات القسيمة/الخصم (تظهر تلقائياً عند اختيار نوع يحتاج كود) -->
                <div class="card" id="voucher-info-card" style="display: none;">
                    <div class="card-header" style="background: linear-gradient(135deg, rgba(115, 103, 240, 0.08) 0%, rgba(115, 103, 240, 0.02) 100%);">
                        <h4 class="card-title"><i class="feather icon-tag" style="color: #7367f0;"></i> <span id="voucher-card-title">معلومات القسيمة</span></h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-primary mb-3" style="border-radius: 10px;">
                            <div class="d-flex align-items-start">
                                <i class="feather icon-info font-medium-3 ml-2 mt-1" style="color: #7367f0;"></i>
                                <div>
                                    <strong id="voucher-info-title">سيتم إصدار كود خصم/قسيمة تلقائياً</strong>
                                    <p class="mb-0 mt-1 small" id="voucher-info-desc">عند إنشاء هذا الطلب سيتم توليد كود فريد يمكن استخدامه عند إنشاء الفاتورة. سيتم خصم النقاط فوراً.</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4" id="max-discount-info" style="display: none;">
                                <div class="p-3 rounded text-center" style="background: #f8f8f8; border: 1px dashed #ddd;">
                                    <i class="feather icon-trending-down text-danger font-medium-3"></i>
                                    <div class="mt-1">
                                        <small class="text-muted">الحد الأقصى للخصم</small>
                                        <h5 class="mb-0 text-danger" id="max-discount-value">-</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" id="min-order-info" style="display: none;">
                                <div class="p-3 rounded text-center" style="background: #f8f8f8; border: 1px dashed #ddd;">
                                    <i class="feather icon-shopping-cart text-warning font-medium-3"></i>
                                    <div class="mt-1">
                                        <small class="text-muted">الحد الأدنى للفاتورة</small>
                                        <h5 class="mb-0 text-warning" id="min-order-value">-</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" id="validity-info" style="display: none;">
                                <div class="p-3 rounded text-center" style="background: #f8f8f8; border: 1px dashed #ddd;">
                                    <i class="feather icon-calendar text-info font-medium-3"></i>
                                    <div class="mt-1">
                                        <small class="text-muted">صلاحية الكود</small>
                                        <h5 class="mb-0 text-info" id="validity-value">-</h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- تنبيه خاص لنوع خصم النسبة المئوية -->
                        <div id="percentage-discount-note" class="alert alert-light-warning mt-3 mb-0" style="display: none; border-radius: 10px;">
                            <i class="feather icon-alert-triangle text-warning ml-1"></i>
                            <strong>ملاحظة:</strong> بالنسبة لخصم النسبة المئوية، سيتم حساب قيمة الخصم الفعلية عند استخدام الكود بناءً على مبلغ الفاتورة.
                            <span id="percentage-note-details"></span>
                        </div>
                    </div>
                </div>

                <!-- تفاصيل الطلب -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-edit"></i> تفاصيل الطلب</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="quantity">الكمية <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantity" 
                                           class="form-control @error('quantity') is-invalid @enderror" 
                                           value="{{ old('quantity', 1) }}" min="1" required>
                                    <small class="text-muted" id="quantity-hint"></small>
                                    @error('quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="delivery_method">طريقة التسليم</label>
                                    <select name="delivery_method" id="delivery_method" 
                                            class="form-control @error('delivery_method') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        @foreach(\App\Enums\DeliveryMethod::cases() as $method)
                                            <option value="{{ $method->value }}" {{ old('delivery_method') == $method->value ? 'selected' : '' }}>
                                                {{ $method->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('delivery_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="branch_id">الفرع</label>
                                    <select name="branch_id" id="branch_id" 
                                            class="form-control @error('branch_id') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
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

                        <!-- عنوان التسليم -->
                        <div class="form-group" id="delivery-address-group" style="display: none;">
                            <label for="delivery_address">عنوان التسليم</label>
                            <input type="text" name="delivery_address" id="delivery_address" 
                                   class="form-control @error('delivery_address') is-invalid @enderror" 
                                   value="{{ old('delivery_address') }}" placeholder="أدخل عنوان التسليم...">
                            @error('delivery_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- اختيار المستودع (للمنتجات والهدايا) -->
                        <div class="row" id="storehouse-group" style="display: none;">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="store_house_id">المستودع <span class="text-danger">*</span></label>
                                    <select name="store_house_id" id="store_house_id" 
                                            class="form-control select2 @error('store_house_id') is-invalid @enderror">
                                        <option value="">-- اختر المستودع --</option>
                                        @foreach($storeHouses as $store)
                                            <option value="{{ $store->id }}" {{ old('store_house_id') == $store->id ? 'selected' : '' }}>
                                                {{ $store->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        <i class="feather icon-info"></i> اختر المستودع الذي سيتم الصرف منه
                                    </small>
                                    @error('store_house_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- اختيار الخزينة (للمكافآت النقدية) -->
                        <div class="row" id="treasury-group" style="display: none;">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="treasury_account_id">الخزينة <span class="text-danger">*</span></label>
                                    <select name="treasury_account_id" id="treasury_account_id" 
                                            class="form-control select2 @error('treasury_account_id') is-invalid @enderror">
                                        <option value="">-- اختر الخزينة --</option>
                                        @foreach($treasuries as $treasury)
                                            <option value="{{ $treasury->id }}" {{ old('treasury_account_id') == $treasury->id ? 'selected' : '' }}>
                                                {{ $treasury->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        <i class="feather icon-info"></i> اختر الخزينة التي سيتم الصرف منها
                                    </small>
                                    @error('treasury_account_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- مكافأة الموظف: تُنفَّذ عبر الموارد البشرية لا الخزينة --}}
                        <div class="alert alert-info py-50" id="employee-payroll-hint" style="display: none;">
                            <i class="feather icon-briefcase"></i>
                            <span data-hint="cash">المكافأة النقدية للموظف تُصرف مع راتبه: عند الموافقة تُضاف استحقاقاً لمرّةٍ واحدة على قسيمة راتبه التالية، وتُقيَّد على «حساب مصروف المكافآت» — لا خزينة ولا تسليم يدوي.</span>
                            <span data-hint="leave_day">مكافأة الإجازة للموظف: عند الموافقة تُضاف أيامُها إلى رصيد إجازاته (نوع الإجازة المحدَّد في المكافأة) للسنة الجارية.</span>
                        </div>

                        <div class="form-group">
                            <label for="notes">ملاحظات</label>
                            <textarea name="notes" id="notes" rows="3" 
                                      class="form-control @error('notes') is-invalid @enderror" 
                                      placeholder="أي ملاحظات إضافية...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- ملخص الطلب -->
                <div class="card border-primary">
                    <div class="card-header bg-light-primary">
                        <h4 class="card-title"><i class="feather icon-file-text"></i> ملخص الطلب</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">المكافأة:</span>
                            <strong id="summary-reward">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">نوع المكافأة:</span>
                            <span id="summary-type">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">الكمية:</span>
                            <strong id="summary-quantity">1</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">النقاط لكل وحدة:</span>
                            <strong class="text-primary" id="summary-points-per">0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>إجمالي النقاط المطلوبة:</span>
                            <strong class="text-danger font-medium-3" id="summary-total-points">0</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">القيمة لكل وحدة:</span>
                            <span id="summary-value-per">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>إجمالي القيمة:</span>
                            <strong class="text-success font-medium-3" id="summary-total-value">0</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>رصيد المستفيد:</span>
                            <strong class="font-medium-3" id="summary-user-balance">
                                <span class="text-muted">-</span>
                            </strong>
                        </div>
                        <div id="balance-status" class="mt-1" style="display: none;"></div>
                    </div>
                </div>

                <!-- تنبيهات -->
                <div class="card border-warning">
                    <div class="card-header bg-light-warning">
                        <h4 class="card-title"><i class="feather icon-alert-triangle"></i> تنبيهات</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0" id="warnings-list">
                            <li class="mb-2">
                                <i class="feather icon-info text-info"></i>
                                اختر المكافأة والمستفيد لبدء الطلب
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- نصائح -->
                <div class="card border-info">
                    <div class="card-header bg-light-info">
                        <h4 class="card-title"><i class="feather icon-info"></i> نصائح</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="feather icon-check text-success"></i>
                                تأكد من رصيد نقاط المستفيد
                            </li>
                            <li class="mb-2">
                                <i class="feather icon-check text-success"></i>
                                سيتم خصم النقاط فوراً عند إرسال الطلب
                            </li>
                            <li class="mb-2">
                                <i class="feather icon-check text-success"></i>
                                يُنشأ القيد المحاسبي عند الموافقة
                            </li>
                            <li>
                                <i class="feather icon-check text-success"></i>
                                يمكن إلغاء الطلب واسترداد النقاط قبل التسليم
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
    let selectedReward = null;
    let userBalance = 0;

    // عند تغيير المكافأة المحددة
    $('#reward_id').on('change', function() {
        const option = $(this).find(':selected');
        if (option.val()) {
            selectedReward = {
                points: parseInt(option.data('points')) || 0,
                value: parseFloat(option.data('value')) || 0,
                type: option.data('type'),
                typeLabel: option.data('type-label'),
                typeIcon: option.data('type-icon'),
                quantity: option.data('quantity'),
                maxPerUser: option.data('max-per-user'),
                userType: option.data('user-type'),
                productId: option.data('product-id'),
                maxDiscount: option.data('max-discount') || null,
                minOrder: option.data('min-order') || null,
                validityDays: option.data('validity-days') || null,
                needsCode: option.data('needs-code') == '1',
                needsInvoice: option.data('needs-invoice') == '1',
                singleQty: option.data('single-qty') == '1'
            };

            // تقييد الكمية إلى 1 للأنواع التي لا تدعم التكرار
            if (selectedReward.singleQty) {
                $('#quantity').val(1).prop('readonly', true).prop('max', 1);
                $('#quantity-hint').text('هذا النوع لا يدعم كمية أكثر من 1');
            } else {
                $('#quantity').prop('readonly', false).prop('max', '');
            }

            // عرض معاينة المكافأة
            $('#preview-reward-icon').text(selectedReward.typeIcon);
            $('#preview-reward-name').text(option.text().trim());
            $('#preview-reward-type').text(selectedReward.typeLabel);
            $('#preview-reward-points').text(selectedReward.points.toLocaleString());
            if (selectedReward.type === 'discount_percentage') {
                $('#preview-reward-value').text(selectedReward.value.toFixed(2) + '%');
            } else {
                $('#preview-reward-value').text(selectedReward.value.toFixed(2));
            }
            $('#reward-preview').slideDown();

            // عرض/إخفاء بطاقة معلومات القسيمة/الخصم
            if (selectedReward.needsCode) {
                // تحديث عنوان البطاقة حسب النوع
                if (selectedReward.type === 'discount_percentage') {
                    $('#voucher-card-title').text('معلومات كود الخصم');
                    $('#voucher-info-title').text('سيتم إصدار كود خصم نسبة ' + selectedReward.value + '% تلقائياً');
                    $('#voucher-info-desc').text('عند الموافقة على الطلب، سيتم إصدار كود خصم يُستخدم عند إنشاء الفاتورة. قيمة الخصم الفعلية تُحسب من مبلغ الفاتورة.');
                } else if (selectedReward.type === 'discount_amount') {
                    $('#voucher-card-title').text('معلومات كود الخصم');
                    $('#voucher-info-title').text('سيتم إصدار كود خصم بقيمة ' + selectedReward.value.toFixed(2) + ' ريال تلقائياً');
                    $('#voucher-info-desc').text('عند الموافقة على الطلب، سيتم إصدار كود خصم ثابت يُخصم من مبلغ الفاتورة.');
                } else if (selectedReward.type === 'voucher') {
                    $('#voucher-card-title').text('معلومات القسيمة الشرائية');
                    $('#voucher-info-title').text('سيتم إصدار قسيمة شرائية بقيمة ' + selectedReward.value.toFixed(2) + ' ' + window.AppCurrency.symbol);
                    $('#voucher-info-desc').text('سيتم إصدار كود قسيمة شرائية مع رمز PIN للتحقق. يمكن استخدامها عبر صفحة التحقق من القسائم.');
                }

                // عرض تفاصيل الحدود
                if (selectedReward.maxDiscount) {
                    $('#max-discount-value').text(parseFloat(selectedReward.maxDiscount).toFixed(2) + ' ' + window.AppCurrency.symbol);
                    $('#max-discount-info').show();
                } else {
                    $('#max-discount-info').hide();
                }

                if (selectedReward.minOrder) {
                    $('#min-order-value').text(parseFloat(selectedReward.minOrder).toFixed(2) + ' ' + window.AppCurrency.symbol);
                    $('#min-order-info').show();
                } else {
                    $('#min-order-info').hide();
                }

                if (selectedReward.validityDays) {
                    $('#validity-value').text(selectedReward.validityDays + ' يوم');
                    $('#validity-info').show();
                } else {
                    $('#validity-info').hide();
                }

                // ملاحظة خاصة لنوع النسبة المئوية
                if (selectedReward.type === 'discount_percentage') {
                    let noteDetails = ' (نسبة الخصم: ' + selectedReward.value + '%)';
                    if (selectedReward.maxDiscount) {
                        noteDetails += ' بحد أقصى ' + parseFloat(selectedReward.maxDiscount).toFixed(2) + ' ' + window.AppCurrency.symbol;
                    }
                    $('#percentage-note-details').text(noteDetails);
                    $('#percentage-discount-note').show();
                } else {
                    $('#percentage-discount-note').hide();
                }

                $('#voucher-info-card').slideDown();
            } else {
                $('#voucher-info-card').slideUp();
            }

            // تلميح مسار الموارد البشرية لمكافآت الموظف (نقدية ← الراتب، إجازة ← رصيد الإجازات)
            const hrHint = $('#user_type').val() === 'employee' && ['cash', 'leave_day'].includes(selectedReward.type);
            $('#employee-payroll-hint').toggle(hrHint)
                .find('[data-hint]').each(function () { $(this).toggle($(this).data('hint') === selectedReward.type); });

            // إظهار/إخفاء حقول المستودع والخزينة حسب وجود منتج مرتبط
            if (selectedReward.productId) {
                // إذا كانت المكافأة مرتبطة بمنتج، أظهر المستودع
                $('#storehouse-group').slideDown();
                $('#treasury-group').slideUp();
                $('#store_house_id').prop('required', true);
                $('#treasury_account_id').prop('required', false).val('');
            } else if ((selectedReward.type === 'cash' && $('#user_type').val() !== 'employee') || selectedReward.type === 'voucher') {
                // مكافأة الموظف النقدية تُصرف مع راتبه — لا خزينة لها (انظر الفرع التالي)
                // إذا كانت مكافأة نقدية أو قسيمة، أظهر الخزينة
                $('#treasury-group').slideDown();
                $('#storehouse-group').slideUp();
                $('#treasury_account_id').prop('required', true);
                $('#store_house_id').prop('required', false).val('');
            } else {
                // باقي الأنواع لا تحتاج مستودع أو خزينة
                $('#storehouse-group, #treasury-group').slideUp();
                $('#store_house_id, #treasury_account_id').prop('required', false).val('');
            }
        } else {
            selectedReward = null;
            $('#reward-preview').slideUp(); $('#employee-payroll-hint').hide();
            $('#voucher-info-card').slideUp();
            $('#storehouse-group, #treasury-group').slideUp();
            $('#store_house_id, #treasury_account_id').prop('required', false).val('');
            // إعادة تفعيل حقل الكمية
            $('#quantity').prop('readonly', false).prop('max', '');
        }
        updateSummary();
    });

    // فلترة المكافآت حسب نوع المستخدم
    function filterRewardsByUserType(userType) {
        // إعادة تعيين الاختيار
        $('#reward_id').val('').trigger('change.select2');
        selectedReward = null;
        $('#reward-preview').slideUp(); $('#employee-payroll-hint').hide();
        $('#storehouse-group, #treasury-group').slideUp();
        $('#store_house_id, #treasury_account_id').prop('required', false).val('');

        if (!userType) {
            // لم يتم اختيار نوع - تعطيل القائمة
            $('#reward_id').prop('disabled', true);
            $('#reward-disabled-hint').slideDown();
            return;
        }

        // تفعيل القائمة
        $('#reward_id').prop('disabled', false);
        $('#reward-disabled-hint').slideUp();

        // إظهار/إخفاء الخيارات حسب نوع المستخدم
        $('#reward_id option').each(function() {
            if (!$(this).val()) return; // تخطي الخيار الفارغ
            const rewardUserType = $(this).data('user-type');
            if (rewardUserType === userType || rewardUserType === 'both') {
                $(this).show().prop('disabled', false);
            } else {
                $(this).hide().prop('disabled', true);
            }
        });

        updateSummary();
    }

    // عند تغيير نوع المستخدم
    $('#user_type').on('change', function() {
        const type = $(this).val();
        if (type === 'employee') {
            $('#employee-group').show();
            $('#client-group').hide();
            $('#client_id').val('');
        } else if (type === 'client') {
            $('#client-group').show();
            $('#employee-group').hide();
            $('#employee_id').val('');
        } else {
            $('#employee-group, #client-group').hide();
        }

        // فلترة المكافآت حسب نوع المستخدم المحدد
        filterRewardsByUserType(type);

        updateUserBalance();
    });

    // عند تغيير الموظف أو العميل
    $('#employee_id, #client_id').on('change', function() {
        updateUserBalance();
    });

    function updateUserBalance() {
        const userType = $('#user_type').val();
        let selectedOption = null;

        if (userType === 'employee') {
            selectedOption = $('#employee_id').find(':selected');
        } else if (userType === 'client') {
            selectedOption = $('#client_id').find(':selected');
        }

        if (selectedOption && selectedOption.val()) {
            userBalance = parseInt(selectedOption.data('balance')) || 0;
            $('#summary-user-balance').html(
                '<span class="' + (userBalance > 0 ? 'text-success' : 'text-danger') + '">' +
                userBalance.toLocaleString() + ' نقطة</span>'
            );
        } else {
            userBalance = 0;
            $('#summary-user-balance').html('<span class="text-muted">-</span>');
        }
        updateSummary();
    }

    // عند تغيير طريقة التسليم
    $('#delivery_method').on('change', function() {
        if ($(this).val() === 'delivery') {
            $('#delivery-address-group').slideDown();
        } else {
            $('#delivery-address-group').slideUp();
        }
    });

    // عند تغيير الكمية
    $('#quantity').on('input', function() {
        updateSummary();
    });

    function updateSummary() {
        const quantity = parseInt($('#quantity').val()) || 1;

        if (selectedReward) {
            const totalPoints = selectedReward.points * quantity;
            const totalValue = selectedReward.value * quantity;

            $('#summary-reward').text($('#reward_id option:selected').text().split('(')[0].trim());
            $('#summary-type').text(selectedReward.typeIcon + ' ' + selectedReward.typeLabel);
            $('#summary-quantity').text(quantity);
            $('#summary-points-per').text(selectedReward.points.toLocaleString());
            $('#summary-total-points').text(totalPoints.toLocaleString());
            if (selectedReward.type === 'discount_percentage') {
                $('#summary-value-per').text(selectedReward.value.toFixed(2) + '%');
                $('#summary-total-value').text(totalValue.toFixed(2) + '%');
            } else {
                $('#summary-value-per').text(selectedReward.value.toFixed(2));
                $('#summary-total-value').text(totalValue.toFixed(2));
            }

            // تحديث التنبيهات
            let warnings = [];
            let hasError = false;

            if (selectedReward.quantity !== null && selectedReward.quantity !== '' && quantity > parseInt(selectedReward.quantity)) {
                warnings.push('<li class="mb-2 text-danger"><i class="feather icon-alert-circle text-danger"></i> الكمية المطلوبة تتجاوز المتاح (' + selectedReward.quantity + ')</li>');
                hasError = true;
            }
            if (selectedReward.maxPerUser && quantity > parseInt(selectedReward.maxPerUser)) {
                warnings.push('<li class="mb-2 text-warning"><i class="feather icon-alert-triangle text-warning"></i> الحد الأقصى لكل مستخدم: ' + selectedReward.maxPerUser + '</li>');
                hasError = true;
            }

            // التحقق من كفاية النقاط
            if (userBalance > 0 && totalPoints > userBalance) {
                warnings.push('<li class="mb-2 text-danger"><i class="feather icon-x-circle text-danger"></i> رصيد النقاط غير كافي! المطلوب: <strong>' + totalPoints.toLocaleString() + '</strong> | الرصيد: <strong>' + userBalance.toLocaleString() + '</strong></li>');
                hasError = true;
                $('#balance-status').html('<div class="alert alert-danger p-1 mb-0 small"><i class="feather icon-x-circle"></i> الرصيد غير كافي لهذا الاستبدال</div>').show();
            } else if (userBalance > 0 && totalPoints <= userBalance) {
                const remaining = userBalance - totalPoints;
                $('#balance-status').html('<div class="alert alert-success p-1 mb-0 small"><i class="feather icon-check-circle"></i> الرصيد كافي. المتبقي بعد الخصم: <strong>' + remaining.toLocaleString() + '</strong></div>').show();
            } else {
                $('#balance-status').hide();
            }

            warnings.push('<li class="mb-2"><i class="feather icon-star text-primary"></i> سيتم خصم <strong>' + totalPoints.toLocaleString() + '</strong> نقطة</li>');

            if (warnings.length > 0) {
                $('#warnings-list').html(warnings.join(''));
            }

            // تفعيل/تعطيل زر الإرسال
            if (hasError) {
                $('#submitBtn').prop('disabled', true).addClass('btn-secondary').removeClass('btn-primary');
            } else {
                $('#submitBtn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
            }

            // الكمية المتاحة  
            if (selectedReward.quantity !== null && selectedReward.quantity !== '') {
                $('#quantity-hint').text('الكمية المتاحة: ' + selectedReward.quantity);
            } else {
                $('#quantity-hint').text('غير محدودة');
            }
        } else {
            $('#summary-reward, #summary-type').text('-');
            $('#summary-quantity').text(quantity);
            $('#summary-points-per, #summary-total-points, #summary-value-per, #summary-total-value').text('0');
            $('#quantity-hint').text('');
            $('#balance-status').hide();
            $('#submitBtn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
        }
    }

    // منع الإرسال إذا الرصيد غير كافي
    $('#redemptionForm').on('submit', function(e) {
        if (selectedReward && userBalance > 0) {
            const quantity = parseInt($('#quantity').val()) || 1;
            const totalPoints = selectedReward.points * quantity;
            if (totalPoints > userBalance) {
                e.preventDefault();
                Swal.fire({
                    title: 'رصيد غير كافي!',
                    html: 'النقاط المطلوبة: <strong>' + totalPoints.toLocaleString() + '</strong><br>الرصيد الحالي: <strong>' + userBalance.toLocaleString() + '</strong>',
                    icon: 'error',
                    confirmButtonText: 'حسناً'
                });
                return false;
            }
        }
    });

    // تفعيل الأقسام حسب القيم المحفوظة
    const oldUserType = '{{ old("user_type") }}';
    if (oldUserType) {
        // إظهار حقول المستفيد وفلترة المكافآت
        $('#user_type').trigger('change');
    } else {
        // لم يتم اختيار نوع - تعطيل المكافآت
        filterRewardsByUserType('');
    }

    const oldDelivery = '{{ old("delivery_method") }}';
    if (oldDelivery === 'delivery') {
        $('#delivery-address-group').show();
    }

    // تحديث الملخص عند التحميل
    if ($('#reward_id').val()) {
        $('#reward_id').trigger('change');
    }
    // تحديث رصيد المستفيد عند التحميل
    updateUserBalance();
});
</script>
@endpush
