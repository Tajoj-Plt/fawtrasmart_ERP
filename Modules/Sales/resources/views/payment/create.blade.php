{{--
    تسجيل دفعة من عميل (سند قبض).

    أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-*` و`sl-doc-*`) وعلى
    قياس «تسجيل دفعة لمورّد» في المشتريات: بطاقة الفاتورة وأرقامها ←
    بطاقة السند ← شريط الحفظ.

    ما تأتي به الإعدادات:
      • الرقم           ← «تسلسل أرقام المستندات» (القسم `invoice-payment`)
      • العملة والكسور  ← «العملات»
      • طرق الدفع       ← «طرق الدفع» المفعَّلة
      • الخزائن         ← «الحسابات المحاسبية» (`main_treasury`) وخزينة الموظّف
      • الإقفال         ← «الفترات المحاسبية» (تنبيهٌ في الرأس)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *كل النصوص مكتوبةٌ بالعربية في القالب* بلا ترجمة.
      • *«ر.س» مكتوبة* بدل رمز العملة المضبوط.
      • *لا يُعرَض المتبقّي على الفاتورة* — يُكتب المبلغ بلا معرفة سقفه،
        فيردّه الخادم بعد الإرسال.
      • *حقل «رقم المعرف»* (`name="id"`) يعرض معرّف الفاتورة الخام في
        خانةٍ قابلة للتحرير، ولا يقرؤه التحقّق ولا الخادم.
--}}

@extends('sales::master')

@section('title', __('sales_payment.create_title'))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $clientName = $invoice && $invoice->client
            ? ($invoice->client->trade_name
                ?: trim(($invoice->client->first_name ?? '') . ' ' . ($invoice->client->last_name ?? '')))
            : __('sales_documents.ui.unknown');

        $grandTotal = (float) ($invoice->grand_total ?? 0);
        $paid = (float) ($previouslyPaid ?? 0);
        $remaining = $invoiceRemaining ?? max(0, round($grandTotal - $paid, 2));

        /* المبلغ المقترح يأتي من المتحكّم. كان يُحسب هنا من إجمالي الفاتورة
           ناقص المسدَّد، فيطمس ما يرسله المتحكّم: من ضغط «تحصيل» على قسطٍ
           بمئة وجد متبقّي الفاتورة كاملاً في الحقل. */
        $payable = isset($payable) ? (float) $payable : (float) $remaining;
        $detail = $installmentDetail ?? null;
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('paymentsClient.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_payment.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_payment.create_title'),
            'icon' => 'credit-card',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_payment.plural') => route('paymentsClient.index'),
                __('sales_payment.create_title') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-payments.create',
            'open' => false,
            'title' => __('sales_payment.guide.create.title'),
            'lead' => __('sales_payment.guide.lead_create'),
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

        {{-- الإقفال المحاسبي — يُقال في الرأس لا عند الضغط على حفظ --}}
        @if (! empty($periodBlock))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-lock"></i></span>
                <div class="sl-doc-note__body">{{ $periodBlock }}</div>
            </div>
        @endif

        <form action="{{ route('paymentsClient.store') }}" method="POST" enctype="multipart/form-data"
              id="clientPaymentForm">
            @csrf

            <input type="hidden" name="invoice_id" value="{{ $invoiceId }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="installments_id" value="{{ $installmentId ?? '' }}">
            <input type="hidden" name="employee_id" value="{{ auth()->id() }}">

            {{-- ══════════ اعتمادُ إشعار سدادٍ من بوابة العميل ══════════

                 الشاشةُ نفسُها والمسارُ نفسُه — الإشعارُ يعبّئ ويُختم عند
                 الحفظ. وما أعلنه العميل يبقى ظاهراً أمام الموظّف ليطابقه
                 بالإيصال قبل أن يحفظ: الاعتمادُ قرارُ موظّفٍ لا نقرةُ تمرير. --}}
            @if (!empty($paymentNotice))
                <input type="hidden" name="payment_notice_id" value="{{ $paymentNotice->id }}">

                <div class="sl-doc-note sl-doc-note--info mb-2">
                    <span class="sl-doc-note__icon"><i class="feather icon-inbox"></i></span>
                    <div class="sl-doc-note__body">
                        <strong>إشعار سداد من بوابة العميل</strong> —
                        أعلن العميل سدادَ {{ number_format((float) $paymentNotice->amount, 2) }}
                        {{ CurrencySettings::symbol() }} ({{ $paymentNotice->methodLabel() }})
                        @if ($paymentNotice->paid_at)
                            بتاريخ {{ $paymentNotice->paid_at->format('Y-m-d') }}
                        @endif
                        @if ($paymentNotice->reference)
                            — المرجع: {{ $paymentNotice->reference }}
                        @endif
                        @if ($paymentNotice->note)
                            <br>ملاحظة العميل: {{ $paymentNotice->note }}
                        @endif
                        @if ($paymentNotice->attachment)
                            <br><a href="{{ asset($paymentNotice->attachment) }}" target="_blank" rel="noopener">
                                <i class="feather icon-paperclip"></i> عرض الإيصال المرفق
                            </a>
                        @endif
                        <br><small>سيُختم الإشعار «معتمداً» ويُربط بهذا السند عند الحفظ.</small>
                    </div>
                </div>
            @endif

            {{-- ══════════ الفاتورة وأرقامها ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <div class="sl-facts">
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_payment.form.invoice') }}</span>
                                    <span class="sl-fact__value">
                                        @if ($invoice)
                                            <a href="{{ route('invoices.show', $invoice->id) }}" class="sl-code">
                                                {{ $invoice->code }}
                                            </a>
                                        @else
                                            {{ __('sales_payment.table.no_invoice') }}
                                        @endif
                                    </span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_payment.form.client') }}</span>
                                    <span class="sl-fact__value">{{ $clientName }}</span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_payment.show.invoice_date') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $invoice
                                            ? LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at)
                                            : __('sales_documents.ui.dash') }}
                                    </span>
                                </div>

                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_payment.show.currency') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $invoice->currency ?? CurrencySettings::code() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- المتبقّي معروضاً: كان المبلغ يُكتب بلا معرفة سقفه،
                             فيردّه الخادم بعد الإرسال --}}
                        <div class="col-lg-5">
                            <div class="sl-totals">
                                <div class="sl-totals__row">
                                    <span>{{ __('sales_payment.invoice_facts.grand_total') }}</span>
                                    <span>{{ CurrencySettings::format($grandTotal) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    <span>{{ __('sales_payment.invoice_facts.paid') }}</span>
                                    <span>{{ CurrencySettings::format($paid) }}</span>
                                </div>
                                <div class="sl-totals__row sl-totals__row--grand">
                                    <span class="{{ $remaining > 0 ? 'text-danger' : '' }}">
                                        {{ __('sales_payment.invoice_facts.remaining') }}
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
                        <i class="feather icon-file-text"></i> {{ __('sales_payment.form.document') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="amount">
                                    {{ __('sales_payment.form.amount') }} <span class="required">*</span>
                                </label>

                                <div class="input-group">
                                    <input type="number" id="amount" name="amount" class="form-control sl-num"
                                           step="0.01" min="0.01" max="{{ $payable ?: null }}" required
                                           value="{{ old('amount', !empty($paymentNotice) ? min((float) $paymentNotice->amount, (float) $payable) : ($payable ?: null)) }}">
                                    <span class="input-group-text">{{ CurrencySettings::symbol() }}</span>
                                </div>

                                @if ($detail)
                                    {{-- تحصيلُ قسطٍ بعينه: يُقال أيُّ قسطٍ ومتبقّي كم، فلا
                                         يُخلَط مبلغُ القسط بمتبقّي الفاتورة --}}
                                    <small class="sl-help">
                                        {{ __('sales_installments.fields.number') }}
                                        #{{ $detail->installment_number }}
                                        — {{ __('sales_installments.fields.remaining') }}:
                                        {{ number_format((float) $detail->remaining_amount, 2) }}
                                        {{ CurrencySettings::symbol() }}
                                    </small>
                                @else
                                    <small class="sl-help">{{ __('sales_payment.form.amount_hint') }}</small>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_date">
                                    {{ __('sales_payment.form.date') }} <span class="required">*</span>
                                </label>
                                <input type="date" id="payment_date" name="payment_date" class="form-control"
                                       required value="{{ old('payment_date', !empty($paymentNotice) && $paymentNotice->paid_at ? $paymentNotice->paid_at->format('Y-m-d') : date('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="paymentNumber">{{ __('sales_payment.form.number') }}</label>
                                {{-- بـ`name` كي يصل الخادم: كان حقلاً معروضاً
                                     بلا اسم، فلا يُرسَل ولا يولّده الخادم،
                                     ويبقى `reference_number` فارغاً في كل سند --}}
                                <input type="text" id="paymentNumber" name="reference_number"
                                       class="form-control sl-num" value="{{ old('reference_number', $paymentNumber) }}" readonly>
                                <small class="sl-help">{{ __('sales_payment.form.number_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_type">
                                    {{ __('sales_payment.form.method') }} <span class="required">*</span>
                                </label>
                                <select name="payment_type" id="payment_type" class="form-control" required>
                                    <option value="">{{ __('sales_payment.form.select_method') }}</option>
                                    @foreach ($payments as $method)
                                        <option value="{{ $method->id }}"
                                            {{-- طريقةُ إشعار البوابة أوّلاً: ما أعلنه العميل هو ما يُعتمد،
                                                 فلا يُعاد اختيارُها بيدٍ وقد تُخطئ --}}
                                            @selected(old('payment_type', ($paymentNotice->payment_method_id ?? null)
                                                ?: $payments->firstWhere('is_default', true)?->id) == $method->id)>
                                            {{ $method->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="treasury_id">{{ __('sales_payment.form.treasury') }}</label>
                                <select name="treasury_id" id="treasury_id" class="form-control">
                                    <option value="">{{ __('sales_payment.form.select_treasury') }}</option>
                                    @foreach ($treasury as $box)
                                        <option value="{{ $box->id }}"
                                            @selected(old('treasury_id', $treasury_id) == $box->id)>
                                            {{ $box->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- حالة السند تُشتقّ من المبلغ ولا تُختار.

                             **ما كان** — قائمةٌ منسدلةٌ إلزامية بخمسة
                             خيارات اسمُها `status_payment`، و`store()`
                             لا يقرأ هذا المفتاح إطلاقاً: يحسب الحالة من
                             المبلغ مقابل متبقّي الفاتورة ثمّ يكتب
                             نتيجته. فالمستخدم يختار «مكتمل» لدفعةٍ
                             جزئية ثمّ يقرأ في القائمة «غير مكتمل».

                             صارت مؤشّراً حيّاً يعرض ما ستكون عليه
                             الحالة عند الحفظ — كما في «تسجيل دفعة
                             لمورّد». --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label>{{ __('sales_payment.form.status') }}</label>

                                <div class="form-control d-flex align-items-center justify-content-between">
                                    <span id="derived_status" class="fw-bold text-muted">{{ __('sales_documents.ui.dash') }}</span>
                                    <small id="derived_status_hint" class="sl-help mb-0"></small>
                                </div>

                                <small class="sl-help">{{ __('sales_payment.form.status_derived') }}</small>
                            </div>
                        </div>

                        {{-- المُحصِّل معروضاً لا مخفيّاً: يُقيَّد في السند
                             ويظهر في المطبوعات، فيُرى قبل الحفظ --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label>{{ __('sales_payment.form.collected_by') }}</label>
                                <input type="text" class="form-control" readonly
                                       value="{{ auth()->user()->employee->full_name ?? auth()->user()->name }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="payment_data">{{ __('sales_payment.form.reference') }}</label>
                                <input type="text" id="payment_data" name="payment_data" class="form-control"
                                       value="{{ old('payment_data', $paymentNotice->reference ?? null) }}">
                                <small class="sl-help">{{ __('sales_payment.form.reference_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="attachments">{{ __('sales_payment.form.attachments') }}</label>
                                <input id="attachments" type="file" name="attachments" class="form-control">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="sl-field">
                                <label for="notes">{{ __('sales_payment.form.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"
                                          placeholder="{{ __('sales_payment.form.notes_placeholder') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-doc-card__foot">
                    <div class="sl-toolbar sl-toolbar--end">
                        <a href="{{ route('paymentsClient.index') }}" class="btn btn-outline-secondary">
                            {{ __('sales_documents.ui.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled(! empty($periodBlock))>
                            <i class="feather icon-save"></i> {{ __('sales_payment.form.save') }}
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
                dash: @json(__('sales_documents.ui.dash')),
                complete: @json(__('sales_payment.status.1')),
                partial: @json(__('sales_payment.status.2')),
                fullyPays: @json(__('sales_payment.form.status_full')),
                remainingIs: @json(__('sales_payment.form.status_remaining')),
                overLimit: @json(__('sales_payment.form.amount_hint')),
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
                    label.textContent = TEXT.dash;
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
                    hint.textContent = TEXT.remainingIs.replace(':amount', money(REMAINING - value));
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const amount = document.getElementById('amount');
                if (!amount) return;

                amount.addEventListener('input', function () {
                    if (REMAINING > 0) {
                        amount.setCustomValidity(
                            parseFloat(amount.value) > REMAINING ? TEXT.overLimit : ''
                        );
                    }

                    refreshStatus(amount);
                });

                refreshStatus(amount);

                /* تأكيد الحفظ — السند يُرحَّل قيداً ويغيّر رصيد الخزينة
                   وذمّة العميل، كما في «تسجيل دفعة لمورّد». */
                const form = document.getElementById('clientPaymentForm');

                if (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.dataset.confirmed === '1' || typeof Swal === 'undefined') return;
                        if (!form.checkValidity()) return;

                        event.preventDefault();

                        Swal.fire({
                            title: @json(__('sales_payment.form.confirm_title')),
                            text: @json(__('sales_payment.form.confirm_text')),
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: @json(__('sales_payment.form.confirm_yes')),
                            cancelButtonText: @json(__('sales_documents.ui.cancel')),
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
