{{--
    الدفع المجمّع لفواتير المورّد — «الفواتير المستحقة».

    أُعيد بناء الشاشة على نظام تصميم وحدة المشتريات (`page-head` / `stats-cards`
    / `po-card` / `modern-table`)، فصارت على قياس «فواتير الشراء» و«الموردين»
    و«المدفوعات» بدل ورقة أنماط مكتوبة داخل الملف بمئة سطر.

    ترتيب الشاشة صار يتبع ترتيب العمل نفسه:
      ١. المورّد والتاريخ وطريقة الدفع   ← بيانات السند
      ٢. الفواتير المستحقة               ← ما يُسدَّد
      ٣. المبلغ والخزينة والمرجع         ← كيف يُسدَّد

    ما يأتي من الإعدادات:
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS
      • العملة والكسور والرمز   ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • الخزائن                 ← «الحسابات المحاسبية»

    **معرّفات JavaScript تبقى كما هي حرفياً** — `#supplier_id` و`#invoicesCard`
    و`#invoicesTableBody` و`#invoicesCount` و`#selectAllInvoices` و`#payment_amount`
    و`#totalDueDisplay` و`#selectedInvoicesCount` و`#remainingAfterPayment`
    و`#payFullAmountBtn` و`#distributionInfo` و`#distributionDetails` و`#submitBtn`
    و`#bulkPaymentForm` — فالسكربت أسفل الملف يعمل بلا تعديل.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.payment.bulk') }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <style>
        /* ما تنفرد به هذه الشاشة وحدها — وما عداه من نظام التصميم المشترك */
        .bulk-summary {
            position: sticky;
            top: 1rem;
        }

        .bulk-summary__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
            padding: .55rem 0;
            border-bottom: 1px dashed var(--po-border, #e6e9f0);
            font-size: .9rem;
        }

        .bulk-summary__row:last-of-type {
            border-bottom: 0;
        }

        .bulk-summary__value {
            font-weight: 700;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .bulk-summary__row--grand .bulk-summary__value {
            font-size: 1.1rem;
            color: var(--po-primary, #7367f0);
        }

        /* توزيع المبلغ على الفواتير — يظهر بعد إدخال المبلغ */
        .bulk-distribution {
            margin-top: 1rem;
            padding: .85rem 1rem;
            border-radius: var(--po-radius, .5rem);
            background: var(--po-primary-soft, rgba(115, 103, 240, .12));
            font-size: .85rem;
        }

        .bulk-distribution__line {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            padding: .25rem 0;
        }

        .po-list-table .num {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;
    @endphp

    <div class="po-page">
        @include('purchases::partials.page-head', [
            'title' => __('purchase_cycle.payment.bulk'),
            'crumbs' => [
                ['label' => __('purchase_cycle.payment.section'), 'url' => route('PaymentSupplier.indexPurchase')],
                ['label' => __('purchase_cycle.payment.bulk')],
            ],
        ])

        <div class="content-body">
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            <form action="{{ route('PaymentSupplier.bulk-payment.store') }}" method="POST" id="bulkPaymentForm">
                @csrf

                <div class="row g-3">
                    {{-- ===== العمود الرئيسي: المورّد ثم فواتيره ===== --}}
                    <div class="col-lg-8">
                        {{-- ١) بيانات السند --}}
                        <div class="po-card">
                            <div class="po-card__head">
                                <h6 class="po-card__title">
                                    <i class="fas fa-truck"></i>{{ __('purchase_cycle.fields.supplier') }}
                                </h6>
                                <span class="po-hint">{{ __('purchase_orders.required_note') }}</span>
                            </div>

                            <div class="po-card__body">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="po-field">
                                            <label for="supplier_id">
                                                {{ __('purchase_cycle.fields.supplier') }} <span class="required">*</span>
                                            </label>
                                            {{-- المورّد مقفل: الشاشة لا تُفتَح إلا من
                                                 شاشته، فتبديله هنا يعني تسديد فواتير
                                                 مورّدٍ من سياق مورّدٍ آخر. --}}
                                            <select name="supplier_id" id="supplier_id" class="form-control" required>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" selected>
                                                        {{ $supplier->trade_name ?: trim(($supplier->first_name ?? '') . ' ' . ($supplier->last_name ?? '')) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="po-hint">{{ __('purchase_cycle.payment.supplier_locked') }}</span>
                                        </div>
                                    </div>

                                    <div class="col-lg-3 col-md-6">
                                        <div class="po-field">
                                            <label for="payment_date">
                                                {{ __('purchase_cycle.payment.date') }} <span class="required">*</span>
                                            </label>
                                            <input type="date" name="payment_date" id="payment_date"
                                                class="form-control" required
                                                value="{{ old('payment_date', date('Y-m-d')) }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-3 col-md-6">
                                        <div class="po-field">
                                            <label for="payment_method">{{ __('purchase_cycle.payment.method') }}</label>
                                            <select name="payment_method" id="payment_method" class="form-control">
                                                <option value="">{{ __('purchase_cycle.payment.method') }}</option>
                                                @foreach ($paymentMethods as $method)
                                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ٢) الفواتير المستحقة — تظهر بعد اختيار المورّد --}}
                        <div class="card table-card-modern" id="invoicesCard" style="display: none;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-file-invoice"></i> {{ __('purchase_cycle.payment.due_invoices') }}
                                </h4>

                                <div class="po-toolbar">
                                    <span class="po-status po-status--quotation" id="invoicesCount">0</span>

                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input po-check" id="selectAllInvoices">
                                        <label class="form-check-label" for="selectAllInvoices">
                                            {{ __('purchase_orders.bulk.select_all') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-content">
                                <div class="table-responsive">
                                    <table class="table modern-table po-list-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 46px;"></th>
                                                <th>{{ __('purchase_cycle.fields.code') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.fields.date') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.fields.grand_total') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.invoice.paid_amount') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.invoice.due_value') }}</th>
                                                <th class="text-center">{{ __('purchase_cycle.fields.status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="invoicesTableBody">
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-hand-pointer" style="font-size: 40px; color: #ccc;"></i>
                                                    <p class="mt-2 text-muted mb-0">
                                                        {{ __('purchase_cycle.payment.pick_supplier') }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- توزيع المبلغ على الفواتير المحدَّدة --}}
                                <div class="bulk-distribution" id="distributionInfo" style="display: none;">
                                    <strong>
                                        <i class="fas fa-info-circle me-1"></i>{{ __('purchase_cycle.payment.distribution') }}
                                    </strong>
                                    <div id="distributionDetails" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===== العمود الجانبي: كيف يُسدَّد ===== --}}
                    <div class="col-lg-4">
                        <div class="bulk-summary">
                            {{-- ٣) المبلغ --}}
                            <div class="po-card">
                                <div class="po-card__head">
                                    <h6 class="po-card__title">
                                        <i class="fas fa-calculator"></i>{{ __('purchase_cycle.payment.summary') }}
                                    </h6>
                                    <span class="po-hint">
                                        <i class="fas fa-coins me-1"></i>{{ CurrencySettings::name() }}
                                    </span>
                                </div>

                                <div class="po-card__body">
                                    <div class="bulk-summary__row">
                                        <span>{{ __('purchase_cycle.payment.total_due') }}</span>
                                        <span class="bulk-summary__value" id="totalDueDisplay">
                                            {{ CurrencySettings::format(0) }}
                                        </span>
                                    </div>

                                    <div class="bulk-summary__row">
                                        <span>{{ __('purchase_cycle.payment.selected_invoices') }}</span>
                                        <span class="bulk-summary__value" id="selectedInvoicesCount">0</span>
                                    </div>

                                    <div class="bulk-summary__row bulk-summary__row--grand">
                                        <span>{{ __('purchase_cycle.payment.remaining_after') }}</span>
                                        <span class="bulk-summary__value" id="remainingAfterPayment">
                                            {{ CurrencySettings::format(0) }}
                                        </span>
                                    </div>

                                    <div class="po-field mt-3">
                                        <label for="payment_amount">
                                            {{ __('purchase_cycle.payment.amount') }} <span class="required">*</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" name="payment_amount"
                                            id="payment_amount" class="form-control" placeholder="0.00"
                                            value="{{ old('payment_amount') }}">
                                    </div>

                                    <button type="button" class="btn btn-outline-primary w-100 mt-2"
                                        id="payFullAmountBtn" disabled>
                                        <i class="fas fa-coins me-1"></i>{{ __('purchase_cycle.payment.pay_full') }}
                                    </button>
                                </div>
                            </div>

                            {{-- الخزينة والمرجع --}}
                            <div class="po-card">
                                <div class="po-card__head">
                                    <h6 class="po-card__title">
                                        <i class="fas fa-vault"></i>{{ __('purchase_cycle.payment.treasury') }}
                                    </h6>
                                </div>

                                <div class="po-card__body">
                                    <div class="po-field">
                                        <label for="treasury_id">
                                            {{ __('purchase_cycle.payment.treasury') }} <span class="required">*</span>
                                        </label>
                                        {{-- الخزائن من «الحسابات المحاسبية» — والقائمة كانت
                                             تحمل الخزينة الرئيسية وحدها فلا يُختار غيرها --}}
                                        <select name="treasury_id" id="treasury_id" class="form-control" required>
                                            @forelse ($treasuries ?? [] as $treasury)
                                                <option value="{{ $treasury->id }}"
                                                    @selected(($mainTreasuryAccount->id ?? null) == $treasury->id)>
                                                    {{ $treasury->name }} — {{ CurrencySettings::format($treasury->balance ?? 0) }}
                                                </option>
                                            @empty
                                                @if ($mainTreasuryAccount)
                                                    <option value="{{ $mainTreasuryAccount->id }}" selected>
                                                        {{ $mainTreasuryAccount->name }} — {{ CurrencySettings::format($mainTreasuryAccount->balance ?? 0) }}
                                                    </option>
                                                @endif
                                            @endforelse
                                        </select>
                                    </div>

                                    <div class="po-field">
                                        <label for="reference_number">{{ __('purchase_cycle.payment.number') }}</label>
                                        <input type="text" name="reference_number" id="reference_number"
                                            class="form-control" value="{{ old('reference_number') }}">
                                    </div>

                                    <div class="po-field">
                                        <label for="notes">{{ __('purchase_cycle.fields.notes') }}</label>
                                        <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                                    </div>
                                </div>

                                <div class="po-card__foot">
                                    <a href="{{ route('PaymentSupplier.indexPurchase') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }} me-1"></i>{{ __('purchase_cycle.actions.back') }}
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                        <i class="fas fa-check me-1"></i>{{ __('purchase_cycle.payment.execute') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
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
        $('#supplier_id').select2({
            placeholder: '-- اختر المورد --',
            allowClear: true,
            dir: 'rtl'
        });
    }

    // عند تغيير المورد
    $('#supplier_id').on('change', function() {
        const supplierId = $(this).val();

        if (!supplierId) {
            $('#invoicesCard').hide();
            resetSummary();
            return;
        }

        // جلب الفواتير المستحقة للمورد
        $.ajax({
            url: '{{ route("PaymentSupplier.supplier-unpaid-invoices") }}',
            type: 'GET',
            data: { supplier_id: supplierId },
            beforeSend: function() {
                $('#invoicesTableBody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</td></tr>');
                $('#invoicesCard').show();
            },
            success: function(response) {
                invoicesData = response.invoices;
                renderInvoicesTable();
                updateInvoicesCount();
            },
            error: function() {
                $('#invoicesTableBody').html('<tr><td colspan="7" class="text-center text-danger">حدث خطأ في جلب الفواتير</td></tr>');
            }
        });
    });

    // عرض جدول الفواتير
    function renderInvoicesTable() {
        if (invoicesData.length === 0) {
            $('#invoicesTableBody').html('<tr><td colspan="7" class="text-center text-success"><i class="fas fa-check-circle"></i> لا توجد فواتير مستحقة لهذا المورد</td></tr>');
            $('#selectAllInvoices').prop('disabled', true);
            return;
        }

        $('#selectAllInvoices').prop('disabled', false);

        let html = '';
        invoicesData.forEach(function(invoice) {
            // payment_status يستخدم قيم نصية: 'unpaid', 'partially_paid', 'draft'
            let statusBadge = '';
            if (invoice.payment_status === 'draft') {
                statusBadge = '<span class="badge badge-due">مسودة</span>';
            } else if (invoice.payment_status === 'partially_paid') {
                statusBadge = '<span class="badge badge-partial">مدفوع جزئياً</span>';
            } else {
                statusBadge = '<span class="badge badge-due">غير مدفوع</span>';
            }

            html += `
                <tr class="invoice-row" data-id="${invoice.id}">
                    <td class="text-center">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input invoice-checkbox"
                                   name="invoice_ids[]" value="${invoice.id}"
                                   id="invoice_${invoice.id}"
                                   data-due="${invoice.due_value}">
                            <label class="custom-control-label" for="invoice_${invoice.id}"></label>
                        </div>
                    </td>
                    <td><strong>${invoice.code}</strong></td>
                    <td>${invoice.date}</td>
                    <td>${parseFloat(invoice.grand_total).toFixed(2)}</td>
                    <td>${parseFloat(invoice.advance_payment).toFixed(2)}</td>
                    <td><strong class="text-danger">${parseFloat(invoice.due_value).toFixed(2)}</strong></td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });

        $('#invoicesTableBody').html(html);
    }

    // تحديث عدد الفواتير
    function updateInvoicesCount() {
        $('#invoicesCount').text(invoicesData.length + ' فاتورة');
    }

    // تحديد/إلغاء تحديد الكل
    $('#selectAllInvoices').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.invoice-checkbox').prop('checked', isChecked);
        $('.invoice-row').toggleClass('selected', isChecked);
        calculateTotals();
    });

    // عند تحديد فاتورة
    $(document).on('change', '.invoice-checkbox', function() {
        const row = $(this).closest('.invoice-row');
        row.toggleClass('selected', $(this).is(':checked'));

        // تحديث حالة "تحديد الكل"
        const allChecked = $('.invoice-checkbox:checked').length === $('.invoice-checkbox').length;
        $('#selectAllInvoices').prop('checked', allChecked);

        calculateTotals();
    });

    // حساب الإجماليات
    function calculateTotals() {
        totalSelectedDue = 0;
        selectedInvoices = [];

        $('.invoice-checkbox:checked').each(function() {
            const invoiceId = $(this).val();
            const dueValue = parseFloat($(this).data('due'));
            totalSelectedDue += dueValue;
            selectedInvoices.push({
                id: invoiceId,
                due: dueValue
            });
        });

        // تحديث العرض
        $('#totalDueDisplay').text(totalSelectedDue.toFixed(2) + ' ر.س');
        $('#selectedInvoicesCount').text(selectedInvoices.length);

        // تفعيل/تعطيل زر الدفع الكامل
        $('#payFullAmountBtn').prop('disabled', selectedInvoices.length === 0);

        updateRemainingAmount();
        validateForm();
    }

    // تحديث المتبقي بعد الدفع
    function updateRemainingAmount() {
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;
        const remaining = totalSelectedDue - paymentAmount;

        $('#remainingAfterPayment').text(remaining.toFixed(2) + ' ر.س');

        if (remaining < 0) {
            $('#remainingAfterPayment').addClass('text-warning');
        } else {
            $('#remainingAfterPayment').removeClass('text-warning');
        }

        // عرض توزيع المبلغ
        showDistribution(paymentAmount);
    }

    // عرض توزيع المبلغ على الفواتير
    function showDistribution(paymentAmount) {
        if (paymentAmount <= 0 || selectedInvoices.length === 0) {
            $('#distributionInfo').hide();
            return;
        }

        let remaining = paymentAmount;
        let html = '';

        // ترتيب الفواتير حسب التاريخ (الأقدم أولاً)
        const sortedInvoices = [...selectedInvoices].sort((a, b) => {
            const invoiceA = invoicesData.find(i => i.id == a.id);
            const invoiceB = invoicesData.find(i => i.id == b.id);
            return new Date(invoiceA.invoice_date) - new Date(invoiceB.invoice_date);
        });

        sortedInvoices.forEach(function(inv) {
            const invoice = invoicesData.find(i => i.id == inv.id);
            const dueValue = parseFloat(inv.due);
            const willPay = Math.min(remaining, dueValue);
            const invoiceRemaining = dueValue - willPay;

            remaining -= willPay;

            const statusClass = invoiceRemaining <= 0 ? 'fully-covered' : 'remaining-amount';
            const statusText = invoiceRemaining <= 0 ? '✓ مسددة بالكامل' : `متبقي: ${invoiceRemaining.toFixed(2)}`;

            html += `
                <div class="distribution-item">
                    <span>${invoice.code}</span>
                    <span>سيُدفع: ${willPay.toFixed(2)} ر.س</span>
                    <span class="${statusClass}">${statusText}</span>
                </div>
            `;
        });

        if (remaining > 0) {
            html += `
                <div class="distribution-item">
                    <span colspan="3" class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        فائض المبلغ: ${remaining.toFixed(2)} ر.س (سيبقى كرصيد)
                    </span>
                </div>
            `;
        }

        $('#distributionDetails').html(html);
        $('#distributionInfo').show();
    }

    // عند تغيير مبلغ الدفع
    $('#payment_amount').on('input', function() {
        updateRemainingAmount();
        validateForm();
    });

    // دفع كامل المبلغ
    $('#payFullAmountBtn').on('click', function() {
        $('#payment_amount').val(totalSelectedDue.toFixed(2));
        updateRemainingAmount();
        validateForm();
    });

    // التحقق من صحة النموذج
    function validateForm() {
        const supplierId = $('#supplier_id').val();
        const paymentAmount = parseFloat($('#payment_amount').val()) || 0;
        const hasSelectedInvoices = selectedInvoices.length > 0;

        const isValid = supplierId && paymentAmount > 0 && hasSelectedInvoices;
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
        $('#selectAllInvoices').prop('checked', false);
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

    // إذا كان المورد محدداً مسبقاً (قادم من صفحة المورد)، جلب فواتيره تلقائياً
    @if(isset($selected_supplier_id) && $selected_supplier_id)
    setTimeout(function() {
        $('#supplier_id').trigger('change');
    }, 500);
    @endif
});
</script>
@endsection
