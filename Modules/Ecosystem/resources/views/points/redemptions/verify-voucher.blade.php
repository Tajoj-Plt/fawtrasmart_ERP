@extends('ecosystem::master')

@section('title', 'التحقق من القسيمة واستخدامها')

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">التحقق من القسيمة</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.redemptions.index') }}">طلبات الاستبدال</a></li>
                            <li class="breadcrumb-item active">التحقق من القسيمة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.redemptions.index') }}" class="pts-btn pts-btn-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="row justify-content-center">
            <div class="col-lg-8">
                {{-- بطاقة إدخال كود القسيمة --}}
                <div class="card profile-card-modern mb-3">
                    <div class="card-body text-center py-4">
                        <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width: 80px; height: 80px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2rem; color: #fff;">
                            <i class="feather icon-tag"></i>
                        </div>
                        <h4 class="mb-1">التحقق من كود القسيمة/الخصم</h4>
                        <p class="text-muted mb-3">أدخل كود القسيمة للتحقق من صلاحيتها وتطبيق الخصم</p>

                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="input-group input-group-lg mb-3">
                                    <input type="text" id="voucher_code" class="form-control text-center"
                                           placeholder="VCH-XXXXXXXX" 
                                           style="font-family: monospace; letter-spacing: 2px; font-size: 1.25rem; border-radius: 10px 0 0 10px;">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" id="verifyBtn" type="button" style="border-radius: 0 10px 10px 0; padding: 0 2rem;">
                                            <i class="feather icon-search"></i> تحقق
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- حالة التحميل --}}
                        <div id="loading-state" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">جاري التحقق...</span>
                            </div>
                            <p class="text-muted mt-2">جاري التحقق من القسيمة...</p>
                        </div>

                        {{-- رسالة خطأ --}}
                        <div id="error-state" class="alert alert-danger" style="display: none; border-radius: 10px;">
                            <i class="feather icon-x-circle ml-1"></i>
                            <span id="error-message"></span>
                        </div>
                    </div>
                </div>

                {{-- بطاقة تفاصيل القسيمة (تظهر بعد التحقق) --}}
                <div id="voucher-details" style="display: none;">
                    <div class="card profile-card-modern mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="font-large-2 ml-2" id="detail-icon"></span>
                                <div>
                                    <h5 class="mb-0" id="detail-reward-name"></h5>
                                    <span class="pts-badge pts-badge-primary" id="detail-reward-type"></span>
                                </div>
                                <span class="pts-badge pts-badge-success mr-auto" style="font-size: 0.85rem;">
                                    <i class="feather icon-check-circle"></i> صالحة
                                </span>
                            </div>

                            <div class="rdm-details-list">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                    <span class="text-muted"><i class="feather icon-hash ml-1"></i> رقم الطلب</span>
                                    <strong id="detail-redemption-number"></strong>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                    <span class="text-muted"><i class="feather icon-user ml-1"></i> المستفيد</span>
                                    <strong id="detail-beneficiary"></strong>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                    <span class="text-muted"><i class="feather icon-tag ml-1"></i> الكود</span>
                                    <code class="px-2 py-1 rounded" style="background: #f0f0f0; font-size: 1rem; letter-spacing: 1px;" id="detail-voucher-code"></code>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;" id="detail-serial-row">
                                    <span class="text-muted"><i class="feather icon-credit-card ml-1"></i> الرقم التسلسلي</span>
                                    <code id="detail-serial"></code>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                    <span class="text-muted"><i class="feather icon-dollar-sign ml-1"></i> القيمة</span>
                                    <strong class="text-success" id="detail-value"></strong>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;" id="detail-max-discount-row" style="display: none;">
                                    <span class="text-muted"><i class="feather icon-trending-down ml-1"></i> الحد الأقصى للخصم</span>
                                    <strong class="text-danger" id="detail-max-discount"></strong>
                                </div>
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;" id="detail-min-order-row" style="display: none;">
                                    <span class="text-muted"><i class="feather icon-shopping-cart ml-1"></i> الحد الأدنى للفاتورة</span>
                                    <strong class="text-warning" id="detail-min-order"></strong>
                                </div>
                                <div class="d-flex justify-content-between py-2" id="detail-expires-row">
                                    <span class="text-muted"><i class="feather icon-calendar ml-1"></i> تنتهي في</span>
                                    <span id="detail-expires"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- بطاقة تطبيق القسيمة --}}
                    <div class="card profile-card-modern mb-3">
                        <div class="card-header" style="background: linear-gradient(135deg, rgba(40, 199, 111, 0.08) 0%, rgba(40, 199, 111, 0.02) 100%);">
                            <h5 class="mb-0"><i class="feather icon-check-circle" style="color: #28c76f;"></i> تطبيق القسيمة</h5>
                        </div>
                        <div class="card-body">
                            {{-- حقل مبلغ الفاتورة (يظهر لخصم النسبة المئوية) --}}
                            <div id="invoice-amount-section">
                                <div class="form-group">
                                    <label for="invoice_amount" class="font-weight-bold">
                                        <i class="feather icon-file-text text-primary ml-1"></i> مبلغ الفاتورة <span class="text-danger" id="invoice-required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" id="invoice_amount" class="form-control" 
                                               step="0.01" min="0" placeholder="أدخل مبلغ الفاتورة..."
                                               style="border-radius: 10px 0 0 10px;">
                                        <div class="input-group-append">
                                            <span class="input-group-text" style="border-radius: 0 10px 10px 0;">ريال</span>
                                        </div>
                                    </div>
                                    <small class="text-muted" id="invoice-hint"></small>
                                    <div id="min-order-warning" class="text-danger small mt-1" style="display: none;">
                                        <i class="feather icon-alert-circle"></i> <span id="min-order-warning-text"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- حقل رمز PIN --}}
                            <div id="pin-section" style="display: none;">
                                <div class="form-group">
                                    <label for="voucher_pin" class="font-weight-bold">
                                        <i class="feather icon-lock text-warning ml-1"></i> رمز التحقق PIN
                                    </label>
                                    <input type="text" id="voucher_pin" class="form-control text-center" 
                                           maxlength="4" placeholder="****"
                                           style="font-family: monospace; letter-spacing: 5px; font-size: 1.5rem; max-width: 200px; border-radius: 10px;">
                                </div>
                            </div>

                            {{-- حقل رقم الفاتورة (اختياري) --}}
                            <div class="form-group" id="invoice-select-section" style="display: none;">
                                <label for="invoice_id" class="font-weight-bold">
                                    <i class="feather icon-file text-primary ml-1"></i> اختر فاتورة العميل
                                </label>
                                <select id="invoice_id" class="form-control" style="width: 100%; border-radius: 10px;">
                                    <option value="">-- اختر فاتورة (اختياري) --</option>
                                </select>
                                <small class="text-muted">عند اختيار فاتورة سيتم تعبئة مبلغ الفاتورة تلقائياً - يمكنك البحث داخل القائمة</small>
                                <div id="no-invoices-msg" style="display: none;" class="text-muted small mt-1">
                                    <i class="feather icon-info"></i> لا توجد فواتير لهذا العميل
                                </div>
                            </div>

                            {{-- حقل رقم الفاتورة اليدوي (للموظفين أو غير العملاء) --}}
                            <div class="form-group" id="invoice-manual-section" style="display: none;">
                                <label for="invoice_id_manual" class="font-weight-bold">
                                    <i class="feather icon-file text-muted ml-1"></i> رقم الفاتورة (اختياري)
                                </label>
                                <input type="number" id="invoice_id_manual" class="form-control" 
                                       placeholder="أدخل رقم الفاتورة..."
                                       style="border-radius: 10px; max-width: 300px;">
                            </div>

                            {{-- معاينة الخصم --}}
                            <div id="discount-preview" class="mt-3" style="display: none;">
                                <div class="p-3 rounded" style="background: linear-gradient(135deg, rgba(40, 199, 111, 0.08) 0%, rgba(40, 199, 111, 0.02) 100%); border: 1px solid rgba(40, 199, 111, 0.2); border-radius: 12px;">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">مبلغ الفاتورة</small>
                                            <h5 class="mb-0" id="preview-invoice-amount">0.00</h5>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">قيمة الخصم</small>
                                            <h5 class="mb-0 text-danger" id="preview-discount">0.00</h5>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">المبلغ بعد الخصم</small>
                                            <h5 class="mb-0 text-success" id="preview-final">0.00</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                <button type="button" id="useVoucherBtn" class="pts-btn pts-btn-success" disabled style="padding: 0.75rem 3rem; font-size: 1.1rem;">
                                    <i class="feather icon-check-circle"></i> تطبيق القسيمة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- نتيجة الاستخدام --}}
                <div id="usage-result" style="display: none;">
                    <div class="card profile-card-modern mb-3">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto mb-3"
                                 style="width: 80px; height: 80px; background: linear-gradient(135deg, #28c76f 0%, #48da89 100%); font-size: 2rem; color: #fff;">
                                <i class="feather icon-check"></i>
                            </div>
                            <h4 class="text-success mb-2">تم تطبيق القسيمة بنجاح!</h4>
                            <p class="text-muted" id="result-message"></p>

                            <div class="p-3 rounded mx-auto mt-3" style="max-width: 400px; background: #f8f8f8; border-radius: 12px;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">المكافأة:</span>
                                    <strong id="result-reward-name"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">مبلغ الفاتورة:</span>
                                    <strong id="result-invoice-amount"></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">قيمة الخصم:</span>
                                    <strong class="text-danger" id="result-discount"></strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted font-weight-bold">المبلغ النهائي:</span>
                                    <strong class="text-success font-large-1" id="result-final"></strong>
                                </div>
                            </div>

                            <div class="mt-3">
                                <a href="{{ route('ecosystem.points.redemptions.verify-voucher') }}" class="pts-btn pts-btn-primary">
                                    <i class="feather icon-tag"></i> التحقق من قسيمة أخرى
                                </a>
                                <a href="{{ route('ecosystem.points.redemptions.index') }}" class="pts-btn pts-btn-secondary mr-2">
                                    <i class="feather icon-list"></i> طلبات الاستبدال
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let voucherData = null;

    // Enter key triggers verify
    $('#voucher_code').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#verifyBtn').click();
        }
    });

    // التحقق من القسيمة
    $('#verifyBtn').on('click', function() {
        const code = $('#voucher_code').val().trim();
        if (!code) {
            $('#error-message').text('يرجى إدخال كود القسيمة');
            $('#error-state').show();
            return;
        }

        // إخفاء العناصر السابقة
        $('#error-state, #voucher-details, #usage-result').hide();
        $('#loading-state').show();

        $.ajax({
            url: '{{ route("ecosystem.points.redemptions.verify-voucher.check") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                voucher_code: code
            },
            success: function(response) {
                $('#loading-state').hide();
                voucherData = response.data;
                console.log('Voucher Data:', voucherData);
                console.log('Client ID:', voucherData.client_id);
                console.log('Invoices:', voucherData.invoices);
                displayVoucherDetails(voucherData);
            },
            error: function(xhr) {
                $('#loading-state').hide();
                const msg = xhr.responseJSON?.message || 'حدث خطأ أثناء التحقق';
                $('#error-message').text(msg);
                $('#error-state').show();
                voucherData = null;
            }
        });
    });

    function displayVoucherDetails(data) {
        // ملء التفاصيل
        $('#detail-icon').text(data.reward_type_icon);
        $('#detail-reward-name').text(data.reward_name);
        $('#detail-reward-type').text(data.reward_type_label);
        $('#detail-redemption-number').text(data.redemption_number);
        $('#detail-beneficiary').text(data.beneficiary + ' (' + data.user_type + ')');
        $('#detail-voucher-code').text(data.voucher_code);

        if (data.voucher_serial) {
            $('#detail-serial').text(data.voucher_serial);
            $('#detail-serial-row').show();
        } else {
            $('#detail-serial-row').hide();
        }

        // القيمة
        if (data.reward_type === 'discount_percentage') {
            $('#detail-value').text(data.reward_value + '% خصم');
        } else {
            $('#detail-value').text(parseFloat(data.reward_value).toFixed(2) + ' ' + window.AppCurrency.symbol);
        }

        // الحد الأقصى للخصم
        if (data.max_discount_value) {
            $('#detail-max-discount').text(parseFloat(data.max_discount_value).toFixed(2) + ' ' + window.AppCurrency.symbol);
            $('#detail-max-discount-row').show();
        } else {
            $('#detail-max-discount-row').hide();
        }

        // الحد الأدنى للطلب
        if (data.min_order_amount) {
            $('#detail-min-order').text(parseFloat(data.min_order_amount).toFixed(2) + ' ' + window.AppCurrency.symbol);
            $('#detail-min-order-row').show();
        } else {
            $('#detail-min-order-row').hide();
        }

        // تاريخ الانتهاء
        if (data.voucher_expires_at) {
            $('#detail-expires').html(data.voucher_expires_at);
            $('#detail-expires-row').show();
        } else {
            $('#detail-expires').text('غير محدد');
        }

        // حقل رمز PIN
        if (data.has_pin) {
            $('#pin-section').show();
        } else {
            $('#pin-section').hide();
        }

        // إعداد حقل مبلغ الفاتورة
        if (data.needs_invoice_amount) {
            $('#invoice-amount-section').show();
            $('#invoice-required').show();
            $('#invoice-hint').text('مطلوب لحساب قيمة الخصم (' + data.reward_value + '%)');
        } else {
            // حتى لو لم يكن مطلوباً، نعرضه لتسجيل مبلغ الفاتورة
            $('#invoice-amount-section').show();
            $('#invoice-required').hide();
            $('#invoice-hint').text('اختياري - لتسجيل مبلغ الفاتورة');
        }

        // حالة الحد الأدنى
        if (data.min_order_amount) {
            $('#invoice-hint').append(' (الحد الأدنى: ' + parseFloat(data.min_order_amount).toFixed(2) + ' ريال)');
        }

        // عرض قسم الفواتير حسب نوع المستفيد
        if (data.client_id) {
            console.log('Client detected, showing invoice section');
            $('#invoice-select-section').show();
            $('#invoice-manual-section').hide();
            populateClientInvoices(data.invoices || []);
        } else {
            console.log('No client_id, showing manual section');
            $('#invoice-select-section').hide();
            $('#invoice-manual-section').show();
        }

        $('#voucher-details').slideDown();
        $('#useVoucherBtn').prop('disabled', false);
    }

    // تعبئة فواتير العميل من بيانات الاستجابة مباشرة
    function populateClientInvoices(invoices) {
        console.log('populateClientInvoices called with:', invoices);
        console.log('Invoices count:', invoices ? invoices.length : 0);
        
        $('#no-invoices-msg').hide();
        $('#invoice_id').html('<option value="">-- اختر فاتورة (اختياري) --</option>');

        // Destroy Select2 قبل التحديث
        if ($.fn.select2 && $('#invoice_id').hasClass('select2-hidden-accessible')) {
            $('#invoice_id').select2('destroy');
        }

        if (invoices && invoices.length > 0) {
            console.log('Processing', invoices.length, 'invoices');
            invoices.forEach(function(inv) {
                console.log('Invoice:', inv);
                var statusBadge = inv.is_paid ? '✅' : '⏳';
                var label = statusBadge + ' #' + inv.code + ' - ' + parseFloat(inv.grand_total).toFixed(2) + ' ريال (' + (inv.invoice_date || '-') + ') - ' + inv.type_label;
                $('#invoice_id').append(
                    $('<option>', {
                        value: inv.id,
                        text: label,
                        'data-grand-total': inv.grand_total
                    })
                );
            });

            // تفعيل Select2 مع البحث داخل القائمة
            if ($.fn.select2) {
                $('#invoice_id').select2({
                    dir: 'rtl',
                    placeholder: '-- اختر فاتورة (اختياري) --',
                    allowClear: true,
                    width: '100%'
                });
            }
            console.log('Select2 initialized successfully');
        } else {
            console.log('No invoices found, showing message');
            $('#no-invoices-msg').html('<i class="feather icon-info"></i> لا توجد فواتير لهذا العميل').show();
        }
    }

    // عند اختيار فاتورة - تعبئة مبلغ الفاتورة تلقائياً
    $('#invoice_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var grandTotal = selectedOption.data('grand-total');
        if (grandTotal) {
            $('#invoice_amount').val(parseFloat(grandTotal).toFixed(2)).trigger('input');
        }
    });

    // حساب الخصم المتوقع عند تغيير مبلغ الفاتورة
    $('#invoice_amount').on('input', function() {
        if (!voucherData) return;

        const amount = parseFloat($(this).val()) || 0;

        // التحقق من الحد الأدنى
        if (voucherData.min_order_amount && amount > 0 && amount < parseFloat(voucherData.min_order_amount)) {
            $('#min-order-warning-text').text('مبلغ الفاتورة أقل من الحد الأدنى (' + parseFloat(voucherData.min_order_amount).toFixed(2) + ' ريال)');
            $('#min-order-warning').show();
            $('#discount-preview').hide();
            return;
        }
        $('#min-order-warning').hide();

        if (amount > 0) {
            let discount = 0;
            if (voucherData.reward_type === 'discount_percentage') {
                discount = amount * (parseFloat(voucherData.reward_value) / 100);
                if (voucherData.max_discount_value && discount > parseFloat(voucherData.max_discount_value)) {
                    discount = parseFloat(voucherData.max_discount_value);
                }
            } else if (voucherData.reward_type === 'discount_amount' || voucherData.reward_type === 'voucher') {
                discount = Math.min(parseFloat(voucherData.reward_value), amount);
            }

            const final_amount = amount - discount;

            $('#preview-invoice-amount').text(amount.toFixed(2) + ' ' + window.AppCurrency.symbol);
            $('#preview-discount').text(discount.toFixed(2) + ' ' + window.AppCurrency.symbol);
            $('#preview-final').text(final_amount.toFixed(2) + ' ' + window.AppCurrency.symbol);
            $('#discount-preview').show();
        } else {
            $('#discount-preview').hide();
        }
    });

    // تطبيق القسيمة
    $('#useVoucherBtn').on('click', function() {
        if (!voucherData) return;

        const invoiceAmount = parseFloat($('#invoice_amount').val()) || 0;
        const pin = $('#voucher_pin').val();
        const invoiceId = parseInt($('#invoice_id').val()) || parseInt($('#invoice_id_manual').val()) || null;

        // التحقق من مبلغ الفاتورة للنسبة المئوية
        if (voucherData.needs_invoice_amount && invoiceAmount <= 0) {
            Swal.fire({
                title: 'مبلغ الفاتورة مطلوب',
                text: 'يرجى إدخال مبلغ الفاتورة لحساب قيمة الخصم',
                icon: 'warning',
                confirmButtonText: 'حسناً'
            });
            return;
        }

        // التحقق من الحد الأدنى
        if (voucherData.min_order_amount && invoiceAmount > 0 && invoiceAmount < parseFloat(voucherData.min_order_amount)) {
            Swal.fire({
                title: 'مبلغ الفاتورة أقل من الحد الأدنى',
                text: 'الحد الأدنى لمبلغ الفاتورة: ' + parseFloat(voucherData.min_order_amount).toFixed(2) + ' ' + window.AppCurrency.symbol,
                icon: 'error',
                confirmButtonText: 'حسناً'
            });
            return;
        }

        // تأكيد التطبيق
        Swal.fire({
            title: 'تطبيق القسيمة؟',
            html: 'هل تريد تطبيق القسيمة <strong>' + voucherData.voucher_code + '</strong>؟<br><small>لا يمكن التراجع بعد التطبيق</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، تطبيق',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const btn = $('#useVoucherBtn');
            btn.prop('disabled', true).html('<i class="feather icon-loader spin"></i> جاري التطبيق...');

            $.ajax({
                url: '{{ route("ecosystem.points.redemptions.use-voucher") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    voucher_code: voucherData.voucher_code,
                    voucher_pin: pin,
                    invoice_amount: invoiceAmount,
                    invoice_id: invoiceId
                },
                success: function(response) {
                    // إخفاء التفاصيل وإظهار النتيجة
                    $('#voucher-details').slideUp();

                    const data = response.data;
                    $('#result-message').text('تم تطبيق ' + data.reward_type_label + ' بنجاح');
                    $('#result-reward-name').text(data.reward_name);
                    $('#result-invoice-amount').text(parseFloat(data.invoice_amount).toFixed(2) + ' ' + window.AppCurrency.symbol);
                    $('#result-discount').text(parseFloat(data.discount_amount).toFixed(2) + ' ' + window.AppCurrency.symbol);
                    $('#result-final').text(parseFloat(data.final_amount).toFixed(2) + ' ' + window.AppCurrency.symbol);

                    $('#usage-result').slideDown();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="feather icon-check-circle"></i> تطبيق القسيمة');
                    const msg = xhr.responseJSON?.message || 'حدث خطأ أثناء تطبيق القسيمة';
                    Swal.fire({
                        title: 'خطأ',
                        text: msg,
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                }
            });
        });
    });
});
</script>
@endpush
