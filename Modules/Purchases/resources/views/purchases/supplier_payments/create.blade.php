{{--
    تسجيل دفعة لمورّد (سند صرف).

    أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-*` و`sl-doc-*`) وعلى
    قياس «تسجيل دفعة من عميل» نفسه: بطاقة الفاتورة وأرقامها ← بطاقة السند ←
    شريط الحفظ — فصارت الدفعات إنشاءً وعرضاً وفهرسةً على تصميمٍ واحد
    بين المبيعات والمشتريات.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات»
      • طرق الدفع       ← «طرق الدفع» المفعَّلة اليدوية، والافتراضية مختارة
      • الخزائن         ← «الحسابات المحاسبية» وخزينة الموظّف
      • الإقفال         ← «الفترات المحاسبية» (تنبيهٌ في الرأس)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *«ر.س» مكتوبة* بدل رمز العملة المضبوط.
      • *لا يُعرَض المتبقّي ولا المورّد ولا رقم الفاتورة* — كانت الشاشة
        تعرض «رقم المعرف» الخام في خانةٍ لا يقرؤها التحقّق ولا الخادم.
      • *كل النصوص مكتوبةٌ بالعربية في القالب* بلا ترجمة.
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.payment.create_title') }}
@stop

@section('content')
    @include('purchases::purchases.supplier_payments.partials.doc-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $supplierName = $invoice?->supplier?->trade_name ?: '—';

        $grandTotal = (float) ($invoice->grand_total ?? 0);
        $remaining = max(0, (float) ($amount ?? 0));
        $paid = max(0, round($grandTotal - $remaining, 2));
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('invoicePurchases.show', $invoiceId) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('purchase_cycle.payment.back') }}
            </a>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.payment.create_title'),
            'icon' => 'credit-card',
            'sub' => $supplierName,
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.payment.section') => route('PaymentSupplier.indexPurchase'),
                __('purchase_cycle.payment.create_title') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- أخطاء التحقّق: `layouts.alerts.error` لا يعرض إلا رسالة الجلسة --}}
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0" style="padding-inline-start:1.1rem">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-payments.create',
            'open' => false,
            'title' => __('purchase_cycle.payment.guide.create_title'),
            'lead' => __('purchase_cycle.payment.guide.lead_create'),
            'blocks' => [
                __('purchase_cycle.payment.guide.effects.title') => [
                    __('purchase_cycle.payment.guide.effects.a'),
                    __('purchase_cycle.payment.guide.effects.b'),
                    __('purchase_cycle.payment.guide.effects.c'),
                ],
                __('purchase_cycle.payment.guide.blockers.title') => [
                    __('purchase_cycle.payment.guide.blockers.a'),
                    __('purchase_cycle.payment.guide.blockers.b'),
                    __('purchase_cycle.payment.guide.blockers.c'),
                ],
            ],
        ])

        {{-- الإقفال المحاسبي — يُقال في الرأس لا عند الضغط على حفظ --}}
        @if (! empty($periodBlock))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="fas fa-lock"></i></span>
                <div class="sl-doc-note__body">{{ $periodBlock }}</div>
            </div>
        @endif

        <form action="{{ route('PaymentSupplier.storePurchase') }}" method="POST" enctype="multipart/form-data"
              id="paymentForm">
            @csrf

            <input type="hidden" name="purchases_id" value="{{ $invoiceId }}">
            <input type="hidden" name="installment_id" value="{{ $installmentId ?? '' }}">
            {{-- `employee_id` على `PaymentsProcess` مفتاحٌ أجنبي إلى `users.id`
                 لا إلى `employees.id` — والخادم يكتب `Auth::id()` مكانه أصلاً --}}
            <input type="hidden" name="employee_id" value="{{ auth()->id() }}">

            {{-- ══════════ الفاتورة وأرقامها ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <div class="sl-facts">
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.invoice') }}</span>
                                    <span class="sl-fact__value">
                                        <a href="{{ route('invoicePurchases.show', $invoiceId) }}" class="sl-code">
                                            {{ $invoice->code ?? '#'.$invoiceId }}
                                        </a>
                                    </span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.supplier') }}</span>
                                    <span class="sl-fact__value">{{ $supplierName }}</span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.invoice_date') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $invoice
                                            ? LanguageSettings::formatDate($invoice->date ?: $invoice->created_at)
                                            : '—' }}
                                    </span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.currency') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ CurrencySettings::code() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- المتبقّي معروضاً: كان المبلغ يُكتب بلا معرفة سقفه --}}
                        <div class="col-lg-5">
                            <div class="sl-totals">
                                <div class="sl-totals__row">
                                    <span>{{ __('purchase_cycle.payment.form.grand_total') }}</span>
                                    <span>{{ CurrencySettings::format($grandTotal) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    <span>{{ __('purchase_cycle.payment.form.paid') }}</span>
                                    <span>{{ CurrencySettings::format($paid) }}</span>
                                </div>
                                <div class="sl-totals__row sl-totals__row--grand">
                                    <span class="{{ $remaining > 0 ? 'text-danger' : '' }}">
                                        {{ __('purchase_cycle.payment.form.remaining') }}
                                    </span>
                                    <span class="{{ $remaining > 0 ? 'text-danger' : '' }}">
                                        {{ CurrencySettings::format($remaining) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ بيانات السند ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="fas fa-file-invoice-dollar"></i> {{ __('purchase_cycle.payment.form.document') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="amount">
                                    {{ __('purchase_cycle.payment.form.amount') }} <span class="required">*</span>
                                </label>

                                <div class="input-group">
                                    <input type="number" id="amount" name="amount" class="form-control sl-num"
                                           step="0.01" min="0.01" max="{{ $remaining ?: null }}" required
                                           value="{{ old('amount', $remaining ?: null) }}">
                                    <span class="input-group-text">{{ CurrencySettings::symbol() }}</span>
                                </div>

                                <small class="sl-help">{{ __('purchase_cycle.payment.form.amount_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_date">
                                    {{ __('purchase_cycle.payment.form.date') }} <span class="required">*</span>
                                </label>
                                <input type="date" id="payment_date" name="payment_date" class="form-control"
                                       required value="{{ old('payment_date', date('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_type">
                                    {{ __('purchase_cycle.payment.form.method') }} <span class="required">*</span>
                                </label>
                                {{-- طرق الدفع من «طرق الدفع» — المفعَّلة اليدوية منها،
                                     مرتّبةً كما رتّبها المسؤول والافتراضية مختارةً سلفاً --}}
                                <select name="payment_type" id="payment_type" class="form-control" required>
                                    <option value="">{{ __('purchase_cycle.payment.form.select_method') }}</option>
                                    @foreach ($payments as $method)
                                        <option value="{{ $method->id }}"
                                            @selected(old('payment_type', $payments->firstWhere('is_default', true)?->id) == $method->id)>
                                            {{ $method->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="treasury_id">{{ __('purchase_cycle.payment.form.treasury') }}</label>
                                <select name="treasury_id" id="treasury_id" class="form-control"
                                        @if (auth()->user()->isEmployee()) readonly @endif>
                                    <option value="">{{ __('purchase_cycle.payment.form.select_treasury') }}</option>
                                    @foreach ($treasury as $box)
                                        <option value="{{ $box->id }}"
                                            @selected(old('treasury_id', $treasury_id) == $box->id)>
                                            {{ $box->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- حالة الدفع تُشتقّ من المبلغ ولا تُختار: `store()` يعيد
                             حسابها من المبلغ مقابل متبقّي الفاتورة ويكتب نتيجته،
                             فالقائمةُ المنسدلة كانت حقلاً لا أثر له. صارت مؤشّراً
                             حيّاً يعرض ما ستكون عليه الحالة عند الحفظ. --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label>{{ __('purchase_cycle.payment.form.status') }}</label>

                                <div class="form-control d-flex align-items-center justify-content-between">
                                    <span id="derived_status" class="fw-bold text-muted">—</span>
                                    <small id="derived_status_hint" class="sl-help mb-0"></small>
                                </div>

                                <small class="sl-help">{{ __('purchase_cycle.payment.form.status_derived') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label>{{ __('purchase_cycle.payment.form.paid_by') }}</label>
                                <input type="text" class="form-control" readonly
                                       value="{{ auth()->user()->employee->full_name ?? auth()->user()->name }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="payment_data">{{ __('purchase_cycle.payment.form.reference') }}</label>
                                <input type="text" id="payment_data" name="payment_data" class="form-control"
                                       value="{{ old('payment_data') }}">
                                <small class="sl-help">{{ __('purchase_cycle.payment.form.reference_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="attachments">{{ __('purchase_cycle.payment.form.attachments') }}</label>
                                <input id="attachments" type="file" name="attachments" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="sl-help">{{ __('purchase_cycle.payment.form.attachments_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="sl-field">
                                <label for="notes">{{ __('purchase_cycle.payment.form.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"
                                          placeholder="{{ __('purchase_cycle.payment.form.notes_placeholder') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-doc-card__foot">
                    <div class="sl-toolbar sl-toolbar--end">
                        <a href="{{ route('invoicePurchases.show', $invoiceId) }}" class="btn btn-outline-secondary" id="cancelBtn">
                            {{ __('purchase_cycle.payment.form.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" @disabled(! empty($periodBlock))>
                            <i class="fas fa-save"></i> {{ __('purchase_cycle.payment.form.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function () {
            'use strict';

            /* المبلغ لا يتجاوز المتبقّي — يُقال في الشاشة قبل الإرسال لا
               بعد أن يردّه الخادم. */
            const REMAINING = {{ $remaining }};

            const TEXT = {
                complete: @json(__('purchase_cycle.payment.completed')),
                partial: @json(__('purchase_cycle.payment.incomplete')),
                fullyPays: @json(__('purchase_cycle.payment.form.status_full')),
                remainingIs: @json(__('purchase_cycle.payment.form.invoice_remaining')),
                willRemain: @json(__('purchase_cycle.payment.form.status_remaining')),
                overLimit: @json(__('purchase_cycle.payment.form.amount_hint')),
            };

            function money(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            /* المؤشّر يعكس ما سيحسبه الخادم: القاعدة نفسها التي في
               `store()` — المبلغ يغطّي المتبقّي فالسداد مكتمل. */
            function refreshStatus(amountField) {
                const label = document.getElementById('derived_status');
                const hint = document.getElementById('derived_status_hint');
                if (!label || !hint) return;

                const value = parseFloat(amountField.value);

                if (!value || value <= 0) {
                    label.textContent = '—';
                    label.className = 'fw-bold text-muted';
                    hint.textContent = TEXT.remainingIs.replace(':amount', money(REMAINING));
                    return;
                }

                // هامش القرش الواحد: المقارنة على العشريات تُخطئ بالتقريب
                if (value >= REMAINING - 0.009) {
                    label.textContent = TEXT.complete;
                    label.className = 'fw-bold text-success';
                    hint.textContent = TEXT.fullyPays;
                } else {
                    label.textContent = TEXT.partial;
                    label.className = 'fw-bold text-warning';
                    hint.textContent = TEXT.willRemain.replace(':amount', money(REMAINING - value));
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const amount = document.getElementById('amount');
                if (!amount) return;

                amount.addEventListener('input', function () {
                    if (REMAINING > 0) {
                        amount.setCustomValidity(
                            parseFloat(amount.value) > REMAINING + 0.009 ? TEXT.overLimit : ''
                        );
                    }

                    refreshStatus(amount);
                });

                refreshStatus(amount);

                /* تأكيد الحفظ — السند يُرحَّل قيداً ويغيّر رصيد الخزينة
                   وذمّة المورّد. */
                const form = document.getElementById('paymentForm');

                if (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.dataset.confirmed === '1' || typeof Swal === 'undefined') return;
                        if (!form.checkValidity()) return;

                        event.preventDefault();

                        Swal.fire({
                            title: @json(__('purchase_cycle.payment.form.confirm_title')),
                            text: @json(__('purchase_cycle.payment.form.confirm_text')),
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: @json(__('purchase_cycle.payment.form.confirm_yes')),
                            cancelButtonText: @json(__('purchase_cycle.payment.form.cancel')),
                            reverseButtons: true,
                        }).then(function (result) {
                            if (result.isConfirmed) {
                                form.dataset.confirmed = '1';
                                form.submit();
                            }
                        });
                    });
                }
            });
        })();
    </script>
@endsection
