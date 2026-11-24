{{--
    عرض سند صرف المورّد.

    أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-*` و`sl-doc-*`) على قياس
    «عرض سند قبض» نفسه: هويّة السند ومبلغه ← شريط الإجراءات ← تبويبات
    (معاينة السند، الفاتورة ومدفوعاتها، سجلّ النشاط).

    **الحالة تُشتقّ ولا تُخزَّن.** كانت تُقرأ من عمود `payment_status` الذي
    يُكتَب لحظةَ الحفظ فيبقى على قيمته الأولى مهما جرى على الفاتورة بعده —
    تُسدَّد بالكامل بدفعةٍ لاحقة والسند ما زال يقول غير ذلك. الحقيقةُ في
    الفاتورة: متبقّيها صفرٌ فالسداد مكتمل. أمّا «ملغى» فهو الحالة 5 في
    العمود الرقمي — وكانت الشاشة تقارنه بالنصّ `cancelled` فلا تُظهر سنداً
    ملغياً أبداً.

    ما يأتي من الإعدادات:
      • العملة والكسور والرمز   ← «العملات»
      • صيغة التاريخ والاتجاه   ← إعدادات اللغة النشطة
      • الألوان والخطّ          ← «مظهر النظام»
      • قائمة «المطبوعات»       ← «قوالب المستندات» + «طريقة الطباعة»
      • طريقة الدفع             ← «طرق الدفع» (بالاسم لا بالمعرّف)
--}}
@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.payment.document') }} — {{ $payment->reference_number ?: $payment->id }}
@stop

@section('content')
    @include('purchases::purchases.supplier_payments.partials.doc-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $invoice = $payment->purchase_invoice;
        $supplier = $payment->supplier ?? $invoice?->supplier;
        $code = $payment->reference_number ?: ('#'.$payment->id);
        $isCancelled = (int) $payment->payment_status === 5;

        $grandTotal = (float) ($invoice->grand_total ?? 0);
        $remaining = (float) ($invoice->due_value ?? 0);
        $settled = max($grandTotal - $remaining, 0);

        if ($isCancelled) {
            $state = ['tone' => 'danger', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.payment.cancelled')];
        } elseif (! $invoice) {
            $state = ['tone' => 'secondary', 'icon' => 'fa-unlink', 'label' => __('purchase_cycle.payment.confirmed')];
        } elseif ($remaining <= 0.009) {
            $state = ['tone' => 'success', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.payment.completed')];
        } else {
            $state = ['tone' => 'warning', 'icon' => 'fa-adjust', 'label' => __('purchase_cycle.payment.incomplete')];
        }

        $methods = (isset($paymentMethods) && $paymentMethods instanceof \Illuminate\Support\Collection)
            ? $paymentMethods
            : collect();
        $methodName = fn ($id) => optional($methods->firstWhere('id', $id))->name ?: '—';

        $templates = (isset($documentTemplates) && $documentTemplates instanceof \Illuminate\Support\Collection)
            ? $documentTemplates
            : collect();

        $invoicePayments = $invoicePayments ?? collect();
        $invoicePaid = $invoicePayments->where('payment_status', '!=', 5)->sum('amount');
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('PaymentSupplier.indexPurchase') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('purchase_cycle.payment.back') }}
            </a>
        @endsection

        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => __('purchase_cycle.payment.show_title', ['code' => $code]),
            'icon' => 'credit-card',
            'sub' => $supplier->trade_name ?? null,
            'crumbs' => [
                __('purchase_orders.breadcrumb.home') => route('purchases.dashboard'),
                __('purchase_cycle.payment.section') => route('PaymentSupplier.indexPurchase'),
                $code => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('purchases::components.guide', [
            'key' => 'purchase-payments.show',
            'open' => false,
            'title' => __('purchase_cycle.payment.guide.show_title'),
            'lead' => __('purchase_cycle.payment.guide.lead_show'),
            'blocks' => [
                __('purchase_cycle.payment.guide.effects.title') => [
                    __('purchase_cycle.payment.guide.effects.a'),
                    __('purchase_cycle.payment.guide.effects.b'),
                    __('purchase_cycle.payment.guide.effects.c'),
                ],
            ],
        ])

        {{-- ══════════ هوية السند ومبلغه ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($payment->payment_date ?? $payment->created_at) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.supplier') }}</span>
                                <span class="sl-fact__value">{{ $supplier->trade_name ?? '—' }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.invoice') }}</span>
                                <span class="sl-fact__value">
                                    @if ($invoice)
                                        <a href="{{ route('invoicePurchases.show', $invoice->id) }}" class="sl-code">
                                            {{ $invoice->code }}
                                        </a>
                                    @else
                                        {{ __('purchase_cycle.payment.no_invoice') }}
                                    @endif
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.method') }}</span>
                                <span class="sl-fact__value">{{ $methodName($payment->payment_method) }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.treasury') }}</span>
                                <span class="sl-fact__value">{{ $payment->treasury_name ?: '—' }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.show.created_by') }}</span>
                                <span class="sl-fact__value">{{ $payment->employee->name ?? '—' }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.form.currency') }}</span>
                                <span class="sl-fact__value sl-num">{{ CurrencySettings::code() }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('purchase_cycle.payment.show.updated_at') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($payment->updated_at, true) }}
                                </span>
                            </div>
                        </div>

                        <div class="sl-tags mt-3">
                            <span class="badge bg-{{ $state['tone'] }}">
                                <i class="fas {{ $state['icon'] }}"></i> {{ $state['label'] }}
                            </span>

                            {{-- القيد المحاسبي — السند يُرحَّل قيداً (مدين المورّد =
                                 دائن الخزينة) ولم تكن الشاشة تدلّ عليه --}}
                            @if (! empty($journalEntry))
                                <a href="{{ route('journal.show', $journalEntry->id) }}" class="badge bg-secondary">
                                    <i class="fas fa-book"></i> {{ __('purchase_cycle.payment.journal_entry') }}
                                </a>
                            @endif
                        </div>

                        @if ($payment->notes)
                            <p class="sl-help mt-3">{{ $payment->notes }}</p>
                        @endif
                    </div>

                    <div class="col-lg-5">
                        <div class="sl-totals">
                            <div class="sl-totals__row sl-totals__row--grand">
                                <span>{{ __('purchase_cycle.payment.amount') }}</span>
                                <span>{{ CurrencySettings::format($payment->amount ?? 0) }}</span>
                            </div>

                            @if ($invoice)
                                <div class="sl-totals__row">
                                    <span>{{ __('purchase_cycle.payment.form.grand_total') }}</span>
                                    <span>{{ CurrencySettings::format($grandTotal) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    <span>{{ __('purchase_cycle.payment.form.paid') }}</span>
                                    <span>{{ CurrencySettings::format($settled) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    <span class="{{ $remaining > 0 ? 'text-danger' : '' }}">
                                        {{ __('purchase_cycle.payment.form.remaining') }}
                                    </span>
                                    <span class="{{ $remaining > 0 ? 'text-danger' : '' }}">
                                        {{ CurrencySettings::format($remaining) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ شريط الإجراءات ══════════ --}}
            <div class="sl-doc-card__foot">
                <div class="sl-toolbar">
                    @permission('purchases.payments.export')
                        @include('purchases::partials.print-menu', [
                            'templates' => $templates,
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'PaymentSupplier.print',
                            'pdfRoute' => 'PaymentSupplier.pdf',
                            'documentId' => $payment->id,
                        ])
                    @endpermission

                    @if ($invoice)
                        <a href="{{ route('invoicePurchases.show', $invoice->id) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-invoice"></i> {{ __('purchase_cycle.payment.show.open_invoice') }}
                        </a>
                    @endif

                    @if ($supplier)
                        @permission('purchases.suppliers.view_statement')
                            <a href="{{ route('SupplierManagement.statement', $supplier->id) }}"
                               class="btn btn-outline-secondary">
                                <i class="fas fa-file-invoice-dollar"></i> {{ __('purchase_cycle.payment.show.statement') }}
                            </a>
                        @endpermission
                    @endif

                    @unless ($isCancelled)
                        @permission('purchases.payments.edit')
                            <a href="{{ route('PaymentSupplier.editSupplierPayment', $payment->id) }}"
                               class="btn btn-outline-secondary">
                                <i class="fas fa-edit"></i> {{ __('purchase_cycle.payment.show.edit') }}
                            </a>
                        @endpermission

                        {{-- الإلغاء يعكس القيد ويعيد المبلغ إلى ذمّة المورّد --}}
                        @permission('purchases.payments.cancel')
                            <button type="button" class="btn btn-outline-warning" onclick="cancelPayment()">
                                <i class="fas fa-ban"></i> {{ __('purchase_cycle.payment.show.cancel') }}
                            </button>
                        @endpermission
                    @endunless

                    @permission('purchases.payments.delete')
                        <button type="button" class="btn btn-outline-danger" onclick="deletePayment()">
                            <i class="fas fa-trash-alt"></i> {{ __('purchase_cycle.actions.delete') }}
                        </button>
                    @endpermission
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['payment-document', 'fa-file-alt', __('purchase_cycle.payment.show.tab_document'), true],
                            ['payment-invoice', 'fa-file-invoice', __('purchase_cycle.payment.show.tab_invoice'), false],
                            ['payment-activity', 'fa-clock', __('purchase_cycle.payment.show.tab_activity'), false],
                        ];
                    @endphp

                    @foreach ($tabs as [$tabId, $icon, $label, $active])
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" data-bs-toggle="tab"
                               href="#{{ $tabId }}" role="tab">
                                <i class="fas {{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="tab-content">

                {{-- معاينة السند: مسار الإيصال نفسه داخل إطار (`?embed=1` يمنع
                     الطباعة التلقائية فيه) --}}
                <div class="tab-pane active" id="payment-document" role="tabpanel">
                    <iframe src="{{ route('PaymentSupplier.rereceipt', $payment->id) }}?embed=1"
                            class="sl-doc-frame" frameborder="0"
                            title="{{ __('purchase_cycle.payment.show.tab_document') }}"></iframe>
                </div>

                {{-- الفاتورة ومدفوعاتها --}}
                <div class="tab-pane" id="payment-invoice" role="tabpanel">
                    @if ($invoice)
                        <div class="table-responsive">
                            <table class="table sl-doc-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:150px">{{ __('purchase_cycle.payment.number') }}</th>
                                        <th style="width:170px">{{ __('purchase_cycle.payment.date') }}</th>
                                        <th>{{ __('purchase_cycle.payment.method') }}</th>
                                        <th>{{ __('purchase_cycle.payment.treasury') }}</th>
                                        <th class="num" style="width:150px">{{ __('purchase_cycle.payment.amount') }}</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($invoicePayments as $row)
                                        <tr @class(['table-active' => $row->id === $payment->id, 'text-muted' => (int) $row->payment_status === 5])>
                                            <td>
                                                <a href="{{ route('PaymentSupplier.showSupplierPayment', $row->id) }}" class="sl-code">
                                                    {{ $row->reference_number ?: '#'.$row->id }}
                                                </a>
                                            </td>
                                            <td class="sl-num">
                                                {{ LanguageSettings::formatDate($row->payment_date ?? $row->created_at) }}
                                            </td>
                                            <td>{{ $methodName($row->payment_method) }}</td>
                                            <td>{{ $row->treasury_name ?: '—' }}</td>
                                            <td class="num sl-line-total">{{ CurrencySettings::format($row->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">{{ __('purchase_cycle.payment.show.invoice_paid') }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($invoicePaid) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="sl-doc-card__body">
                            <p class="sl-help mb-0">{{ __('purchase_cycle.payment.show.no_invoice') }}</p>
                        </div>
                    @endif
                </div>

                {{-- سجلّ النشاط — كان السند بلا خطٍّ زمنيّ، فلا يُعرف من عدّله
                     ولا متى أُلغي --}}
                <div class="tab-pane" id="payment-activity" role="tabpanel">
                    <div class="sl-doc-card__body">
                        @forelse ($logs ?? [] as $date => $dayLogs)
                            <div class="sl-timeline">
                                <p class="sl-timeline__day">
                                    <i class="fas fa-calendar"></i>
                                    {{ \Carbon\Carbon::parse($date)->translatedFormat('l') }}
                                    <span class="sl-num">{{ $date }}</span>
                                </p>

                                @foreach ($dayLogs as $log)
                                    <div class="sl-timeline__item">
                                        <span class="sl-timeline__dot"></span>
                                        <div class="sl-timeline__body">
                                            <span class="sl-timeline__time sl-num">
                                                {{ $log->created_at->format('H:i') }}
                                            </span>
                                            <div class="sl-timeline__text">
                                                <strong>{{ $log->user->name ?? __('purchase_cycle.payment.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('purchase_cycle.payment.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <p class="sl-help mb-0">{{ __('purchase_cycle.payment.no_activity') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="deleteForm" action="{{ route('PaymentSupplier.destroySupplierPayment', $payment->id) }}"
          method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <form id="cancelForm" action="{{ route('PaymentSupplier.cancelSupplierPayment', $payment->id) }}"
          method="POST" class="d-none">
        @csrf
    </form>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @php
        $paymentShow = [
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $code]),
            'yesDelete' => __('purchase_cycle.payment.show.delete_confirm'),
            'cancelTitle' => __('purchase_cycle.payment.show.cancel_title'),
            'cancelText' => __('purchase_cycle.payment.show.cancel_text'),
            'yesCancel' => __('purchase_cycle.payment.show.cancel_confirm'),
            'dismiss' => __('purchase_cycle.payment.show.dismiss'),
        ];
    @endphp

    <script>
        window.paymentShow = @json($paymentShow);

        function confirmAndSubmit(title, text, icon, confirmText, formId) {
            const form = document.getElementById(formId);
            if (!form) return;

            if (typeof Swal === 'undefined') {
                if (confirm(text)) form.submit();
                return;
            }

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: window.paymentShow.dismiss,
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        }

        /* الحذف والإلغاء يعكسان القيد — فعلان لا يُتراجَع عنهما، فيُسأل عنهما قبل الإرسال. */
        function deletePayment() {
            confirmAndSubmit(window.paymentShow.deleteTitle, window.paymentShow.deleteText,
                'warning', window.paymentShow.yesDelete, 'deleteForm');
        }

        function cancelPayment() {
            confirmAndSubmit(window.paymentShow.cancelTitle, window.paymentShow.cancelText,
                'warning', window.paymentShow.yesCancel, 'cancelForm');
        }
    </script>
@endsection
