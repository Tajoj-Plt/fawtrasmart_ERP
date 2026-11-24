{{--
    عرض سند قبض من عميل.

    أُعيد بناء الشاشة على قياس شاشة عرض الفاتورة (`sales::invoices.show`)
    بنظام `sl-doc-*`: ترويسة ← بطاقة هوية السند ومبلغه ← شريط إجراءات ←
    تبويبات (السند، الفاتورة، سجلّ النشاط).

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كل مبلغ في الشاشة)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • طرق الدفع       ← «طرق الدفع» (الاسم لا الرقم)
      • القوالب         ← «قوالب المستندات» (النوع `receipt_voucher`)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *نصوصٌ مكتوبةٌ في القالب* بالعربية، فلا تتبع اللغة النشطة.
      • *«ر.س» و«ريال» مكتوبتان* بدل رمز العملة المضبوط.
      • *طريقة الدفع رقماً*: العمود `payment_method` معرّفٌ في «طرق
        الدفع»، وكان يُعرَض عدداً خاماً.
      • *بلا قيدٍ ولا سجلّ نشاط ولا مطبوعات* رغم أنّ للسند قوالبَ في
        «قوالب المستندات» ونوعَها `receipt_voucher`.
--}}

@extends('sales::master')

@section('title', __('sales_payment.show_title', ['code' => $payment->reference_number ?: $payment->id]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $invoice = $payment->invoice;

        $clientName = $invoice && $invoice->client
            ? ($invoice->client->trade_name
                ?: trim(($invoice->client->first_name ?? '') . ' ' . ($invoice->client->last_name ?? '')))
            : ($payment->client->trade_name ?? __('sales_documents.ui.unknown'));

        $code = $payment->reference_number ?: ('#' . $payment->id);

        $states = [
            1 => ['success', 'check-circle'],
            2 => ['warning', 'alert-circle'],
            3 => ['secondary', 'edit-3'],
            4 => ['info', 'clock'],
            5 => ['danger', 'x-circle'],
        ];

        [$stateTone, $stateIcon] = $states[(int) $payment->payment_status] ?? ['dark', 'help-circle'];
        $stateLabel = __('sales_payment.status.' . (int) $payment->payment_status);

        $isCancelled = (int) $payment->payment_status === 5;

        // اسم طريقة الدفع من «طرق الدفع» لا رقمها
        $methods = (isset($paymentMethods) && $paymentMethods instanceof \Illuminate\Support\Collection)
            ? $paymentMethods
            : collect();
        $methodName = optional($methods->firstWhere('id', $payment->payment_method))->name;

        $templates = (isset($documentTemplates) && $documentTemplates instanceof \Illuminate\Support\Collection)
            ? $documentTemplates
            : collect();

        $activeTemplate = $defaultTemplate ?? null;

        // مدفوعات الفاتورة كلُّها — لموضع هذا السند منها
        $invoicePayments = $invoice
            ? $invoice->payments_process->sortByDesc('payment_date')
            : collect();

        $invoicePaid = $invoicePayments->where('payment_status', '!=', 5)->sum('amount');
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('paymentsClient.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_payment.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_payment.show_title', ['code' => $code]),
            'icon' => 'credit-card',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_payment.plural') => route('paymentsClient.index'),
                $code => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-payments.show',
            'title' => __('sales_payment.guide.show.title'),
            'lead' => __('sales_payment.guide.lead_show'),
            'open' => false,
            'blocks' => [
                __('sales_payment.guide.effects.title') => [
                    __('sales_payment.guide.effects.a'),
                    __('sales_payment.guide.effects.b'),
                    __('sales_payment.guide.effects.c'),
                ],
            ],
        ])

        {{-- ══════════ هوية السند ومبلغه ══════════ --}}
        <div class="sl-doc-card sl-doc-card--summary">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($payment->payment_date ?: $payment->created_at) }}
                                </span>
                            </div>

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ __('sales_payment.show.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.invoice') }}</span>
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
                                <span class="sl-fact__label">{{ __('sales_payment.show.method') }}</span>
                                <span class="sl-fact__value">{{ $methodName ?: __('sales_documents.ui.dash') }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.treasury') }}</span>
                                <span class="sl-fact__value">
                                    {{ $payment->treasury_name ?: __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.reference') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $payment->reference_number ?: __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.added_by') }}</span>
                                <span class="sl-fact__value">
                                    {{-- من ملفّ الموظف لا من اسم المستخدم — انظر الموديل --}}
                                    {{ $payment->recorded_by_name ?: __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_payment.show.currency') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $invoice->currency ?? CurrencySettings::code() }}
                                </span>
                            </div>
                        </div>

                        <div class="sl-tags mt-3">
                            <span class="badge badge-light-{{ $stateTone }}">
                                <i class="feather icon-{{ $stateIcon }}"></i> {{ $stateLabel }}
                            </span>

                            @if ($journalEntry)
                                <a href="{{ route('journal.show', $journalEntry->id) }}"
                                   class="badge badge-light-secondary">
                                    <i class="feather icon-book-open"></i>
                                    {{ __('sales_payment.show.journal_entry') }}
                                </a>
                            @endif
                        </div>

                        @if ($payment->notes)
                            <p class="sl-help mt-3">{{ $payment->notes }}</p>
                        @endif

                        <p class="sl-help mt-2">{{ __('sales_payment.show.currency_frozen') }}</p>
                    </div>

                    <div class="col-lg-5">
                        <div class="sl-totals">
                            <div class="sl-totals__row sl-totals__row--grand">
                                <span>{{ __('sales_payment.show.amount') }}</span>
                                <span>{{ CurrencySettings::format($payment->amount ?? 0) }}</span>
                            </div>

                            @if ($invoice)
                                <div class="sl-totals__row">
                                    <span>{{ __('sales_payment.invoice_facts.grand_total') }}</span>
                                    <span>{{ CurrencySettings::format($invoice->grand_total ?? 0) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    <span>{{ __('sales_payment.invoice_facts.paid') }}</span>
                                    <span>{{ CurrencySettings::format($invoicePaid) }}</span>
                                </div>
                                <div class="sl-totals__row">
                                    @php $due = ($invoice->grand_total ?? 0) - $invoicePaid; @endphp
                                    <span class="{{ $due > 0 ? 'text-danger' : '' }}">
                                        {{ __('sales_payment.invoice_facts.remaining') }}
                                    </span>
                                    <span class="{{ $due > 0 ? 'text-danger' : '' }}">
                                        {{ CurrencySettings::format($due) }}
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
                    {{-- المطبوعات — قوالب «قوالب المستندات» أوّلاً --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="feather icon-printer"></i> {{ __('sales_payment.show.documents') }}
                        </button>

                        <div class="dropdown-menu">
                            @if ($templates->isNotEmpty())
                                <h6 class="dropdown-header">{{ __('sales_payment.show.from_settings') }}</h6>

                                @foreach ($templates as $template)
                                    <a class="dropdown-item"
                                       href="{{ route('paymentsClient.rereceipt', $payment->id) }}?template={{ $template->id }}"
                                       target="_blank" rel="noopener">
                                        <i class="feather icon-file"></i> {{ $template->name }}
                                        @if ($template->is_default)
                                            <i class="feather icon-star text-warning"></i>
                                        @endif
                                    </a>
                                @endforeach

                                <div class="dropdown-divider"></div>
                            @else
                                <h6 class="dropdown-header">{{ __('sales_payment.show.no_template') }}</h6>
                            @endif

                            {{-- القالبان المدمجان يبقيان متاحين صراحةً:
                                 منشأةٌ لم تصمّم قالباً بعد يجب أن تطبع
                                 سندها، والحراريّ لم يكن له زرٌّ إطلاقاً
                                 رغم وجود قالبه في الوحدة. --}}
                            <a class="dropdown-item"
                               href="{{ route('paymentsClient.rereceipt', $payment->id) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-file-text"></i> {{ __('sales_payment.receipt.a4') }}
                            </a>

                            <a class="dropdown-item"
                               href="{{ route('paymentsClient.rereceipt', ['id' => $payment->id, 'type' => 'thermal']) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-printer"></i> {{ __('sales_payment.receipt.thermal') }}
                            </a>

                            <a class="dropdown-item" href="{{ route('paymentsClient.pdf', $payment->id) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-download"></i> {{ __('sales_payment.show.pdf') }}
                            </a>
                        </div>
                    </div>

                    {{-- لم يكن للدفعة زرُّ إرسالٍ إطلاقاً، وهي المستندُ
                         الذي يطلبه العميلُ إثباتاً لسداده --}}
                    @permission('sales.payments.print')
                        @include('sales::partials.send-email', [
                            'mailId' => 'payment-' . $payment->id,
                            'mailRoute' => route('paymentsClient.send-email', $payment->id),
                            'mailNumber' => $code,
                            'mailEmail' => $payment->client?->email ?? $invoice?->client?->email,
                            'mailClientName' => $clientName,
                            'mailTemplates' => $templates,
                            'mailDefaultTemplate' => $activeTemplate,
                        ])
                    @endpermission

                    @if ($invoice)
                        <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-outline-secondary">
                            <i class="feather icon-file-text"></i> {{ __('sales_payment.show.open_invoice') }}
                        </a>
                    @endif

                    @unless ($isCancelled)
                        @permission('sales.payments.edit')
                            <a href="{{ route('paymentsClient.edit', $payment->id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-edit-2"></i> {{ __('sales_payment.show.edit') }}
                            </a>
                        @endpermission

                        {{-- الإلغاء يعكس القيد ويعيد المبلغ إلى ذمّة العميل --}}
                        @permission('sales.payments.cancel')
                            <form action="{{ route('paymentsClient.cancel', $payment->id) }}" method="POST"
                                  id="cancelForm" class="sl-toolbar sl-toolbar--end">
                                @csrf
                                <button type="button" class="btn btn-outline-danger" onclick="confirmCancel()">
                                    <i class="feather icon-x-circle"></i> {{ __('sales_payment.show.cancel') }}
                                </button>
                            </form>
                        @endpermission
                    @endunless
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['payment-document', 'file-text', __('sales_payment.show.tab_document'), true],
                            ['payment-invoice', 'shopping-bag', __('sales_payment.show.tab_invoice'), false],
                            ['payment-activity', 'clock', __('sales_payment.show.tab_activity'), false],
                        ];
                    @endphp

                    @foreach ($tabs as [$tabId, $icon, $label, $active])
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" data-bs-toggle="tab"
                               href="#{{ $tabId }}" role="tab">
                                <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="tab-content">

                {{-- معاينة السند: مسار الإيصال نفسه داخل إطار --}}
                <div class="tab-pane active" id="payment-document" role="tabpanel">
                    <iframe src="{{ route('paymentsClient.rereceipt', array_filter([
                                'id' => $payment->id,
                                'template' => $activeTemplate?->id,
                            ]) + ['embed' => 1]) }}"
                            class="sl-doc-frame" frameborder="0"
                            title="{{ __('sales_payment.show.tab_document') }}"></iframe>
                </div>

                {{-- الفاتورة ومدفوعاتها --}}
                <div class="tab-pane" id="payment-invoice" role="tabpanel">
                    @if ($invoice)
                        <div class="table-responsive">
                            <table class="table sl-doc-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:150px">{{ __('sales_payment.show.number') }}</th>
                                        <th style="width:170px">{{ __('sales_payment.show.date') }}</th>
                                        <th>{{ __('sales_payment.show.method') }}</th>
                                        <th>{{ __('sales_payment.show.treasury') }}</th>
                                        <th class="num" style="width:150px">{{ __('sales_payment.table.amount') }}</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($invoicePayments as $row)
                                        <tr @class(['table-active' => $row->id === $payment->id])>
                                            <td>
                                                <a href="{{ route('paymentsClient.show', $row->id) }}" class="sl-code">
                                                    {{ $row->reference_number ?: '#' . $row->id }}
                                                </a>
                                            </td>
                                            <td class="sl-num">
                                                {{ LanguageSettings::formatDate($row->payment_date ?: $row->created_at) }}
                                            </td>
                                            <td>
                                                <span class="sl-media__sub">
                                                    {{ optional($methods->firstWhere('id', $row->payment_method))->name
                                                        ?: __('sales_documents.ui.dash') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="sl-media__sub">
                                                    {{ $row->treasury_name ?: __('sales_documents.ui.dash') }}
                                                </span>
                                            </td>
                                            <td class="num sl-line-total">{{ CurrencySettings::format($row->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">{{ __('sales_payment.show.invoice_paid') }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($invoicePaid) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        @include('sales::components.empty-state', [
                            'icon' => 'file-text',
                            'title' => __('sales_payment.show.no_invoice'),
                        ])
                    @endif
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="payment-activity" role="tabpanel">
                    <div class="sl-doc-card__body">
                        @forelse ($logs as $date => $dayLogs)
                            <div class="sl-timeline">
                                <p class="sl-timeline__day">
                                    <i class="feather icon-calendar"></i>
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
                                                <strong>{{ $log->user->name ?? __('sales_payment.show.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('sales_payment.show.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => __('sales_payment.show.no_activity'),
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /* الإلغاء يعكس القيد ويعيد المبلغ إلى ذمّة العميل — فعلٌ لا
           يُتراجَع عنه، فيُسأل عنه قبل الإرسال. */
        function confirmCancel() {
            const form = document.getElementById('cancelForm');
            if (!form) return;

            if (typeof Swal === 'undefined') {
                if (confirm(@json(__('sales_payment.show.cancel_text')))) form.submit();
                return;
            }

            Swal.fire({
                title: @json(__('sales_payment.show.cancel_title')),
                text: @json(__('sales_payment.show.cancel_text')),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: @json(__('sales_payment.show.cancel_confirm')),
                cancelButtonText: @json(__('sales_payment.show.cancel_dismiss')),
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        }
    </script>
@endsection
