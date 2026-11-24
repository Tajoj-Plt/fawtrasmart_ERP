@extends('sales::master')

@section('title')
    الدفع المجمع للفواتير
@stop

@section('css')
    <style>
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }

        .summary-card .card-body {
            padding: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-value {
            font-weight: bold;
            font-size: 1.1em;
        }

        .payment-input {
            font-size: 1.3em;
            font-weight: bold;
            text-align: center;
        }

        .distribution-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }

        .distribution-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.9em;
        }

        .remaining-amount {
            color: #dc3545;
            font-weight: bold;
        }

        .fully-covered {
            color: #28a745;
        }

        @media (max-width: 768px) {
            .summary-card {
                margin-bottom: 20px;
            }
        }
    </style>
@endsection

@section('content')

        {{-- من أجل `.sl-guide` وحدها؛ كلُّ قواعدها `.sl-`ـيّة فلا تمسّ
             أنماطَ هذه الشاشة --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-payments.bulk',
            'open' => false,
            'title' => __('sales_payment.guide.bulk.title'),
            'lead' => __('sales_payment.guide.lead_bulk'),
            'blocks' => [
                __('sales_payment.guide.what_is.title') => [
                    __('sales_payment.guide.what_is.a'),
                    __('sales_payment.guide.what_is.b'),
                    __('sales_payment.guide.what_is.c'),
                ],
                __('sales_payment.guide.effects.title') => [
                    __('sales_payment.guide.effects.a'),
                    __('sales_payment.guide.effects.b'),
                    __('sales_payment.guide.effects.c'),
                ],
                __('sales_payment.guide.blockers.title') => [
                    __('sales_payment.guide.blockers.a'),
                    __('sales_payment.guide.blockers.b'),
                    __('sales_payment.guide.blockers.c'),
                ],
            ],
        ])
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">
                        <i class="fas fa-coins mr-1"></i> الدفع المجمع للفواتير
                    </h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('paymentsClient.index') }}">المدفوعات</a></li>
                            <li class="breadcrumb-item active">دفع مجمع</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form id="bulkPaymentForm" action="{{ route('paymentsClient.bulk-payment.store') }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <label>اختر العميل والفواتير المراد تسديدها، ثم أدخل المبلغ الكلي</label>
                    </div>
                    <div>
                        <a href="{{ route('paymentsClient.index') }}" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-right"></i> رجوع
                        </a>
                        <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                            <i class="fa fa-check"></i> تنفيذ الدفع المجمع
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- العمود الأيسر: اختيار العميل والفواتير -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="fas fa-user mr-1"></i> اختيار العميل</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="client_id" class="form-label">العميل <span class="text-danger">*</span></label>
                                <select name="client_id" id="client_id" class="form-control select2" required>
                                    <option value="">-- اختر العميل --</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" {{ (old('client_id') == $client->id || (isset($selected_client_id) && $selected_client_id == $client->id)) ? 'selected' : '' }}>
                                            {{ $client->trade_name ?? $client->first_name . ' ' . $client->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="payment_date" class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date" class="form-control"
                                       value="{{ old('payment_date', date('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label for="payment_method" class="form-label">طريقة الدفع</label>
                                <select name="payment_method" id="payment_method" class="form-control">
                                    <option value="">-- اختر --</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول الفواتير -->
                <div class="card" id="invoicesCard" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="fas fa-file-invoice mr-1"></i> الفواتير المستحقة</h4>
                        <span class="badge badge-info" id="invoicesCount">0 فاتورة</span>
                    </div>
                    <div class="card-body">
                        <!-- اختيار الفواتير -->
                        <div class="form-group">
                            <label for="invoice_ids">
                                الفواتير المراد سدادها
                                <small class="text-muted">— الفواتير المسدَّدة بالكامل أو المرتجعة لا تظهر هنا أصلاً</small>
                            </label>
                            <select name="invoice_ids[]" id="invoice_ids" class="form-control" multiple="multiple"></select>
                        </div>

                        <button type="button" class="btn btn-primary btn-block" id="payAllInvoices" disabled>
                            <i class="fas fa-layer-group"></i> دفع الكل — تحديد كل الفواتير وتعبئة إجمالي المستحق
                        </button>

                        <!-- معلومات التوزيع -->
                        <div class="distribution-info mt-3" id="distributionInfo" style="display: none;">
                            <h6><i class="fas fa-info-circle"></i> توزيع المبلغ على الفواتير:</h6>
                            <div id="distributionDetails"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- العمود الأيمن: ملخص الدفع -->
            <div class="col-lg-4">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="text-center mb-4"><i class="fas fa-calculator"></i> ملخص الدفع</h5>

                        <div class="summary-item">
                            <span>إجمالي المستحق:</span>
                            <span class="summary-value" id="totalDueDisplay">0.00 ر.س</span>
                        </div>

                        <div class="summary-item">
                            <span>عدد الفواتير:</span>
                            <span class="summary-value" id="selectedInvoicesCount">0</span>
                        </div>

                        <div class="summary-item">
                            <span>الخزينة:</span>
                            <span class="summary-value">{{ $mainTreasuryAccount->name ?? 'الرئيسية' }}</span>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.3);">

                        <div class="form-group">
                            <label for="payment_amount" class="text-center d-block mb-2">
                                <strong>مبلغ الدفع</strong>
                            </label>
                            <input type="number" name="payment_amount" id="payment_amount"
                                   class="form-control payment-input"
                                   placeholder="0.00" step="0.01" min="0.01"
                                   value="{{ old('payment_amount') }}">
                        </div>

                        <div class="summary-item mt-3">
                            <span>المتبقي بعد الدفع:</span>
                            <span class="summary-value" id="remainingAfterPayment">0.00 ر.س</span>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-light btn-block" id="payFullAmount">
                                <i class="fas fa-coins"></i> دفع كامل المستحق
                            </button>
                        </div>
                    </div>
                </div>

                <!-- معلومات إضافية -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="fas fa-info-circle"></i> معلومات إضافية</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="reference_number">رقم المرجع / الشيك</label>
                            <input type="text" name="reference_number" id="reference_number"
                                   class="form-control" placeholder="اختياري"
                                   value="{{ old('reference_number') }}">
                        </div>
                        <div class="form-group">
                            <label for="notes">ملاحظات</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"
                                      placeholder="أي ملاحظات إضافية...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // متغيرات عامة
    let invoicesData = [];
    let selectedInvoices = [];
    let totalSelectedDue = 0;

    // تهيئة Select2
    if ($.fn.select2) {
        $('#client_id').select2({
            placeholder: '-- اختر العميل --',
            allowClear: true,
            dir: 'rtl'
        });

        $('#invoice_ids').select2({
            placeholder: 'اختر فاتورة واحدة أو أكثر...',
            dir: 'rtl',
            width: '100%'
        });
    }

    // عند تغيير العميل
    $('#client_id').on('change', function() {
        const clientId = $(this).val();

        if (!clientId) {
            $('#invoicesCard').hide();
            resetSummary();
            return;
        }

        // جلب الفواتير المستحقة للعميل
        $.ajax({
            url: '{{ route("paymentsClient.client-unpaid-invoices") }}',
            type: 'GET',
            data: { client_id: clientId },
            beforeSend: function() {
                $('#invoice_ids').empty().trigger('change');
                $('#invoicesCard').show();
            },
            success: function(response) {
                invoicesData = response.invoices;
                renderInvoicesSelect();
                updateInvoicesCount();
            },
            error: function() {
                invoicesData = [];
                renderInvoicesSelect();
                $('#invoicesCount').text('تعذّر جلب الفواتير');
            }
        });
    });

    // تعبئة قائمة الفواتير (select2)
    function renderInvoicesSelect() {
        const $select = $('#invoice_ids').empty();

        invoicesData.forEach(function(invoice) {
            const statusText = invoice.payment_status === 3 ? 'مسودة' : 'غير مكتمل';
            const label = `${invoice.code} — ${invoice.invoice_date} — المستحق: ${parseFloat(invoice.due_value).toFixed(2)} ر.س (${statusText})`;
            $select.append(new Option(label, invoice.id, false, false));
        });

        $select.trigger('change');
        $('#payAllInvoices').prop('disabled', invoicesData.length === 0);
        calculateTotals();
    }

    // تحديث عدد الفواتير
    function updateInvoicesCount() {
        $('#invoicesCount').text(
            invoicesData.length === 0 ? 'لا توجد فواتير مستحقة' : invoicesData.length + ' فاتورة'
        );
    }

    // زر «دفع الكل»: يحدد كل الفواتير ويعبّي إجمالي المستحق — لا يُنفّذ
    // السداد بنفسه، المستخدم يراجع ويضغط «تنفيذ الدفع المجمّع» بنفسه
    $('#payAllInvoices').on('click', function() {
        const allIds = invoicesData.map(invoice => String(invoice.id));
        $('#invoice_ids').val(allIds).trigger('change');
        $('#payFullAmount').trigger('click');
    });

    // عند تغيير التحديد في select2
    $('#invoice_ids').on('change', function() {
        calculateTotals();
    });

    // حساب الإجماليات
    function calculateTotals() {
        totalSelectedDue = 0;
        selectedInvoices = [];

        const selectedIds = $('#invoice_ids').val() || [];
        selectedIds.forEach(function(id) {
            const invoice = invoicesData.find(i => String(i.id) === String(id));
            if (!invoice) {
                return;
            }
            const dueValue = parseFloat(invoice.due_value);
            totalSelectedDue += dueValue;
            selectedInvoices.push({
                id: invoice.id,
                due: dueValue
            });
        });

        $('#totalDueDisplay').text(totalSelectedDue.toFixed(2) + ' ر.س');
        $('#selectedInvoicesCount').text(selectedInvoices.length);

        updatePaymentCalculations();
        validateForm();
    }

    // عند تغيير مبلغ الدفع
    $('#payment_amount').on('input', function() {
        updatePaymentCalculations();
        validateForm();
    });

    // حساب المتبقي وتوزيع المبلغ
    function updatePaymentCalculations() {
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;
        const remaining = totalSelectedDue - paymentAmount;

        // تحديث المتبقي
        if (remaining < 0) {
            $('#remainingAfterPayment').html('<span class="text-warning">' + Math.abs(remaining).toFixed(2) + ' ر.س (زيادة)</span>');
        } else if (remaining === 0) {
            $('#remainingAfterPayment').html('<span class="fully-covered">0.00 ر.س <i class="fas fa-check"></i></span>');
        } else {
            $('#remainingAfterPayment').text(remaining.toFixed(2) + ' ر.س');
        }

        // عرض توزيع المبلغ
        if (paymentAmount > 0 && selectedInvoices.length > 0) {
            showDistribution(paymentAmount);
        } else {
            $('#distributionInfo').hide();
        }
    }

    // عرض توزيع المبلغ على الفواتير
    function showDistribution(paymentAmount) {
        let remainingPayment = paymentAmount;
        let html = '';

        selectedInvoices.forEach(function(invoice) {
            const invoiceData = invoicesData.find(i => i.id == invoice.id);
            const willPay = Math.min(remainingPayment, invoice.due);
            remainingPayment -= willPay;

            const status = willPay >= invoice.due
                ? '<span class="text-success"><i class="fas fa-check"></i> سيُسدد بالكامل</span>'
                : '<span class="text-warning"><i class="fas fa-clock"></i> سيُسدد جزئياً</span>';

            html += `
                <div class="distribution-item">
                    <span>فاتورة ${invoiceData.code}:</span>
                    <span>${willPay.toFixed(2)} ر.س ${status}</span>
                </div>
            `;
        });

        if (remainingPayment > 0) {
            html += `
                <div class="distribution-item remaining-amount">
                    <span><i class="fas fa-exclamation-triangle"></i> مبلغ زائد:</span>
                    <span>${remainingPayment.toFixed(2)} ر.س</span>
                </div>
            `;
        }

        $('#distributionDetails').html(html);
        $('#distributionInfo').show();
    }

    // دفع كامل المستحق
    $('#payFullAmount').on('click', function() {
        $('#payment_amount').val(totalSelectedDue.toFixed(2));
        updatePaymentCalculations();
        validateForm();
    });

    // التحقق من صحة النموذج
    function validateForm() {
        const clientId = $('#client_id').val();
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;
        const hasSelectedInvoices = selectedInvoices.length > 0;

        const isValid = clientId && paymentAmount > 0 && hasSelectedInvoices;
        $('#submitBtn').prop('disabled', !isValid);
    }

    // إعادة تعيين الملخص
    function resetSummary() {
        invoicesData = [];
        selectedInvoices = [];
        totalSelectedDue = 0;
        $('#totalDueDisplay').text('0.00 ر.س');
        $('#selectedInvoicesCount').text('0');
        $('#remainingAfterPayment').text('0.00 ر.س');
        $('#distributionInfo').hide();
        $('#invoice_ids').empty().trigger('change');
        $('#payAllInvoices').prop('disabled', true);
        validateForm();
    }

    // عند إرسال النموذج
    $('#bulkPaymentForm').on('submit', function(e) {
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;

        if (paymentAmount <= 0) {
            e.preventDefault();
            alert('يرجى إدخال مبلغ صحيح للدفع');
            return false;
        }

        if (selectedInvoices.length === 0) {
            e.preventDefault();
            alert('يرجى اختيار فاتورة واحدة على الأقل');
            return false;
        }

        // تأكيد العملية
        if (!confirm('هل أنت متأكد من تنفيذ الدفع المجمع بمبلغ ' + paymentAmount.toFixed(2) + ' ر.س لعدد ' + selectedInvoices.length + ' فاتورة؟')) {
            e.preventDefault();
            return false;
        }

        $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري التنفيذ...');
    });

    // إذا كان العميل محدداً مسبقاً (قادم من صفحة العميل)، جلب فواتيره تلقائياً
    @if(isset($selected_client_id) && $selected_client_id)
    setTimeout(function() {
        $('#client_id').trigger('change');
    }, 500);
    @endif
});
</script>
@endsection
