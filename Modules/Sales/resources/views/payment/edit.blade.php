{{--
    تعديل سند قبض من عميل.

    أُعيد بناء الشاشة على قياس «تسجيل دفعة» (`sales::payment.create`)
    بنظام `sl-*` و`sl-doc-*`، فتخرج الشاشتان على قياسٍ واحد.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات»
      • طرق الدفع       ← «طرق الدفع» المفعَّلة
      • الخزائن         ← «الحسابات المحاسبية» (خزائن النقد وحسابات البنوك)
      • الإقفال         ← «الفترات المحاسبية» (تنبيهٌ في الرأس)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *طرق الدفع خمسةُ خياراتٍ مكتوبةٍ في القالب* (نقدي… أخرى) بأرقامٍ
        ثابتة من ١ إلى ٥ لا تقابل «طرق الدفع» المضبوطة في الإعدادات —
        فتعديلُ سندٍ يكتب فيه طريقةً لا وجود لها.
      • *قائمة الخزينة خيارٌ واحد* `value="1"` مكتوبٌ في الوسم، ولا حساب
        بهذا المعرّف؛ والعمود يشير إلى `accounts`.
      • *حالة الدفع قائمةٌ إلزامية* اسمها `status_payment` لا يقرؤها
        `update()` إطلاقاً: يحسب الحالة من المبلغ ثمّ يكتب نتيجته.
      • *لا يُعرَض المتبقّي على الفاتورة*، فيُكتب المبلغ بلا معرفة سقفه.
      • *كل النصوص مكتوبةٌ بالعربية في القالب* بلا ترجمة.
--}}

@extends('sales::master')

@section('title', __('sales_payment.edit_title'))

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
        $paid = (float) ($paidByOthers ?? 0);

        // سقفُ هذا السند: إجمالي الفاتورة ناقصَ ما سدّدته السنداتُ الأخرى
        $ceiling = max(0, round($grandTotal - $paid, 2));

        $code = $payment->reference_number ?: ('#' . $payment->id);
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('paymentsClient.show', $payment->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_payment.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_payment.edit_title'),
            'icon' => 'credit-card',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_payment.plural') => route('paymentsClient.index'),
                $code => route('paymentsClient.show', $payment->id),
                __('sales_documents.ui.edit') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-payments.edit',
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

        <form action="{{ route('paymentsClient.update', $payment->id) }}" method="POST"
              enctype="multipart/form-data" id="clientPaymentForm">
            @csrf
            @method('PUT')

            <input type="hidden" name="invoice_id" value="{{ $payment->invoice_id }}">
            <input type="hidden" name="type" value="invoice">
            <input type="hidden" name="employee_id" value="{{ $payment->employee_id ?: auth()->id() }}">

            {{-- ══════════ الفاتورة وأرقامها ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <div class="sl-facts">
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_payment.form.number') }}</span>
                                    <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                                </div>

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
                                    <span class="sl-fact__label">{{ __('sales_payment.show.currency') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $invoice->currency ?? CurrencySettings::code() }}
                                    </span>
                                </div>
                            </div>
                        </div>

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
                                    <span>{{ __('sales_payment.invoice_facts.remaining') }}</span>
                                    <span>{{ CurrencySettings::format($ceiling) }}</span>
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
                        <i class="feather icon-edit-2"></i> {{ __('sales_payment.form.edit_document') }}
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
                                           step="0.01" min="0.01" max="{{ $ceiling ?: null }}" required
                                           value="{{ old('amount', $payment->amount) }}">
                                    <span class="input-group-text">{{ CurrencySettings::symbol() }}</span>
                                </div>

                                <small class="sl-help">{{ __('sales_payment.form.amount_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_date">
                                    {{ __('sales_payment.form.date') }} <span class="required">*</span>
                                </label>
                                <input type="date" id="payment_date" name="payment_date" class="form-control" required
                                       value="{{ old('payment_date', optional($payment->payment_date)->format('Y-m-d')) }}">
                            </div>
                        </div>

                        {{-- طرق الدفع من «طرق الدفع» لا خياراتٍ مكتوبةً في الوسم --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_type">
                                    {{ __('sales_payment.form.method') }} <span class="required">*</span>
                                </label>
                                <select name="payment_type" id="payment_type" class="form-control" required>
                                    <option value="">{{ __('sales_payment.form.select_method') }}</option>
                                    @foreach ($payments as $method)
                                        <option value="{{ $method->id }}"
                                            @selected(old('payment_type', $payment->payment_method) == $method->id)>
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
                                            @selected(old('treasury_id', $payment->treasury_id) == $box->id)>
                                            {{ $box->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- الحالة تُشتقّ من المبلغ ولا تُختار — `update()`
                             يعيد حسابها ويكتب نتيجته فوق ما يُرسَل --}}
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

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label>{{ __('sales_payment.form.collected_by') }}</label>
                                <input type="text" class="form-control" readonly
                                       value="{{ $payment->recorded_by_name ?: auth()->user()->name }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="payment_data">{{ __('sales_payment.form.reference') }}</label>
                                <input type="text" id="payment_data" name="payment_data" class="form-control"
                                       value="{{ old('payment_data', $payment->payment_data) }}">
                                <small class="sl-help">{{ __('sales_payment.form.reference_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="attachments">{{ __('sales_payment.form.attachments') }}</label>
                                <input id="attachments" type="file" name="attachments" class="form-control">

                                @if ($payment->attachments)
                                    <small class="sl-help">
                                        <a href="{{ asset('assets/uploads/' . $payment->attachments) }}"
                                           target="_blank" rel="noopener">{{ $payment->attachments }}</a>
                                    </small>
                                @endif
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="sl-field">
                                <label for="notes">{{ __('sales_payment.form.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"
                                          placeholder="{{ __('sales_payment.form.notes_placeholder') }}">{{ old('notes', $payment->notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-doc-card__foot">
                    <div class="sl-toolbar sl-toolbar--end">
                        <a href="{{ route('paymentsClient.show', $payment->id) }}" class="btn btn-outline-secondary">
                            {{ __('sales_documents.ui.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled(! empty($periodBlock))>
                            <i class="feather icon-save"></i> {{ __('sales_payment.form.update') }}
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

            const CEILING = {{ $ceiling }};

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

            /* نفس قاعدة `update()`: المبلغ يغطّي المتبقّي فالسداد مكتمل */
            function refreshStatus(field) {
                const label = document.getElementById('derived_status');
                const hint = document.getElementById('derived_status_hint');
                if (!label || !hint) return;

                const value = parseFloat(field.value);

                if (!value || value <= 0) {
                    label.textContent = TEXT.dash;
                    label.className = 'fw-bold text-muted';
                    hint.textContent = TEXT.remainingIs.replace(':amount', money(CEILING));
                    return;
                }

                if (value >= CEILING - 0.009) {
                    label.textContent = TEXT.complete;
                    label.className = 'fw-bold text-success';
                    hint.textContent = TEXT.fullyPays;
                } else {
                    label.textContent = TEXT.partial;
                    label.className = 'fw-bold text-warning';
                    hint.textContent = TEXT.remainingIs.replace(':amount', money(CEILING - value));
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const amount = document.getElementById('amount');
                if (!amount) return;

                amount.addEventListener('input', function () {
                    if (CEILING > 0) {
                        amount.setCustomValidity(
                            parseFloat(amount.value) > CEILING ? TEXT.overLimit : ''
                        );
                    }

                    refreshStatus(amount);
                });

                refreshStatus(amount);

                const form = document.getElementById('clientPaymentForm');

                if (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.dataset.confirmed === '1' || typeof Swal === 'undefined') return;
                        if (!form.checkValidity()) return;

                        event.preventDefault();

                        Swal.fire({
                            title: @json(__('sales_payment.form.confirm_update_title')),
                            text: @json(__('sales_payment.form.confirm_update_text')),
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: @json(__('sales_payment.form.update')),
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
