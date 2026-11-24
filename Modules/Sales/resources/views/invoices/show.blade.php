{{--
    عرض فاتورة مبيعات.

    أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-*` و`sl-doc-*`) — نفسه
    الذي تُبنى به شاشتا الفواتير وإنشاء الفاتورة، فتخرج الثلاث على قياس
    واحد. الترتيب: ترويسة ← بطاقة هوية الفاتورة وإجمالياتها ← شريط
    الإجراءات ← تبويبات التفاصيل.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كل مبلغ في الشاشة)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • القوالب         ← «قوالب المبيعات» في قائمة المطبوعات
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *تبويبان بلا محتوى*: «المدفوعات» و«المخزون» لهما زرّان في شريط
        التبويبات ولا لوحة تقابلهما — الضغط عليهما يُخفي كل شيء ولا يُظهر
        شيئاً. صار لكلٍّ منهما لوحته وبياناتها من المتحكّم.
      • *جدول المنتجات مُزاح*: سبعة عناوين وثلاث خلايا في كل صفّ، فتقع
        كمية المخزون تحت «سعر الوحدة» ويبقى أربعة أعمدة فارغة.
      • *«حذف» برابط GET*: `href="{{ route('invoices.destroy', …) }}"`
        على مسارٍ يقبل DELETE وحده — نقرةٌ تُرجع 405. صار يستدعي
        `deleteInvoice()` مثل زرّ الحذف الآخر في الشريط نفسه.
      • *«ر.س» مكتوبة*: في تبويب الربح والتواقيع، وصورةُ ريالٍ ثابتة في
        تبويب المرتجعات — كلّها صارت من «العملات».
      • *`<style>` من ٢٧٠ سطراً* يعيد تعريف البطاقات والتبويبات والجداول،
        وقد صار في نظام `sl-*`.
--}}

@extends('sales::master')

@section('title', __('sales_invoice.show_title', ['code' => $invoice->code]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما، وكلُّ
         قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها في محدِّدٍ واحد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $symbol = CurrencySettings::symbol();

        $paymentStates = [
            1 => [__('sales_invoice.status.paid'), 'success', 'check-circle'],
            2 => [__('sales_invoice.status.partial'), 'warning', 'alert-circle'],
            3 => [__('sales_invoice.status.unpaid'), 'danger', 'x-circle'],
            4 => [__('sales_invoice.status.received'), 'info', 'info'],
        ];

        [$stateLabel, $stateTone, $stateIcon] =
            $paymentStates[$invoice->payment_status] ?? [__('sales_invoice.status.unknown'), 'secondary', 'help-circle'];

        // المرتجعُ (أو الإشعار الدائن) أطفأ ما بقي — لا «غير مدفوعة» بلا مستحق
        [$stateLabel, $stateTone, $stateIcon] = match ($invoice->returnSettlementState()) {
            'returned' => [__('sales_invoice.status.fully_returned'), 'secondary', 'corner-up-left'],
            'settled' => [__('sales_invoice.status.settled'), 'success', 'check-circle'],
            default => [$stateLabel, $stateTone, $stateIcon],
        };

        $clientName = $invoice->client
            ? ($invoice->client->trade_name ?: trim($invoice->client->first_name . ' ' . $invoice->client->last_name))
            : __('sales_invoice.table.unknown_client');

        $isTaxed = $invoice->tax_total > 0 || (isset($TaxsInvoice) && count($TaxsInvoice) > 0);

        /* المعاينة والطباعة تتبعان **القالب الافتراضي** المضبوط في
           «قوالب المستندات».

           كان الرابط يخرج بلا معرّف قالب، و`renderSalesTemplate()` تعدّ
           ذلك طلباً للقالب المدمج — فالقالب الذي يصمّمه المستخدم ويعيّنه
           افتراضياً لا يظهر إلّا إن اختاره من القائمة يدوياً في كل مرّة. */
        $activeTemplate = $isTaxed ? ($defaultTaxTemplate ?? null) : ($defaultTemplate ?? null);

        $previewRoute = $isTaxed
            ? route('invoices.print_tax', array_filter([$invoice->id, $activeTemplate?->id]) + ['embed' => true])
            : route('invoices.print', array_filter([$invoice->id, $activeTemplate?->id]) + ['embed' => true]);

        $templates = (isset($documentTemplates) && $documentTemplates instanceof \Illuminate\Support\Collection)
            ? $documentTemplates
            : collect();

        $netDue = ($invoice->due_value ?? 0) - ($invoice->returned_payment ?? 0);
        $paidTotal = $payments->sum('amount');

        /* فاتورةٌ لها مرتجع لا تقبل تحصيلاً: المرتجع يعكس البيع ويردّ
           البضاعة، فقبضُ دفعةٍ عليه يقبض ثمن ما رُدّ. والتبويب «المرتجعات»
           يعرضها أصلاً، فالمصدر واحد. */
        $hasReturn = isset($return_invoices) && count($return_invoices) > 0;

        /* الربح يأتي محسوباً من `InvoicesController::invoiceProfitReport()`.
           كان يُحسب هنا بسعر المنتج في الكتالوج مطروحاً منه سعر شرائه —
           رقمٌ لا يمسّ هذه الفاتورة ولا سعرَها ولا خصمَها. */
        $profitTotal = $profitReport['profit'] ?? 0;
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_invoice.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_invoice.show_title', ['code' => $invoice->code]),
            'icon' => 'file-text',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_invoice.invoices') => route('invoices.index'),
                $invoice->code => null,
            ],
        ])


        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{-- حالة المستند لدى هيئة الزكاة والضريبة والجمارك وزرّ إرساله --}}
        @include('sales::partials.zatca-status', ['zatcaType' => 'invoice', 'zatcaId' => $invoice->id])

        {{-- ══════════ إرشادُ الشاشة ══════════
             مطويّةٌ افتراضياً: من فتح فاتورةً بعينها جاء لبياناتها لا للشرح --}}
@include('sales::components.guide', [
            'key' => 'sales-invoices.show',
            'title' => __('sales_invoice.guide.show.title'),
            'lead' => __('sales_invoice.guide.show.lead'),
            'open' => false,
            'blocks' => [
                __('sales_invoice.guide.show_actions.title') => [
                    __('sales_invoice.guide.show_actions.a'),
                    __('sales_invoice.guide.show_actions.b'),
                    __('sales_invoice.guide.show_actions.c'),
                    __('sales_invoice.guide.show_actions.d'),
                ],
                __('sales_invoice.guide.money_flow.title') => [
                    __('sales_invoice.guide.money_flow.a'),
                    __('sales_invoice.guide.money_flow.b'),
                    __('sales_invoice.guide.money_flow.c'),
                ],
                __('sales_invoice.guide.noedit.title') => [
                    __('sales_invoice.guide.noedit.a'),
                    __('sales_invoice.guide.noedit.b'),
                ],
            ],
        ])

        {{-- ══════════ هوية الفاتورة وإجمالياتها ══════════ --}}
        <div class="sl-doc-card sl-doc-card--summary">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_invoice.table.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $invoice->code }}</span></span>
                            </div>

                            {{-- فاتورةٌ صدرت من «تحويل إلى فاتورة» في الحجوزات — رابطٌ إلى حجزها --}}
                            @php
                                $sourceReservation = class_exists(\Modules\Client\Reservations\Models\Reservation::class)
                                    ? \Modules\Client\Reservations\Models\Reservation::withoutGlobalScopes()
                                        ->whereNull('deleted_at')
                                        ->where('invoice_id', $invoice->id)
                                        ->first(['id', 'code'])
                                    : null;
                            @endphp
                            @if ($sourceReservation)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('reservations.links.from_reservation') }}</span>
                                    <span class="sl-fact__value">
                                        @if (Route::has('Reservations.show'))
                                            <a href="{{ route('Reservations.show', $sourceReservation->id) }}" class="sl-code">{{ $sourceReservation->code }}</a>
                                        @else
                                            <span class="sl-code">{{ $sourceReservation->code }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endif

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ __('sales_invoice.table.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_invoice.invoice_date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_invoice.filters.sales_person') }}</span>
                                <span class="sl-fact__value">{{ $invoice->employee->full_name ?? '—' }}</span>
                            </div>

                            {{-- شروط الدفع — المهلةُ وتاريخُ استحقاقها.
                                 لا يُعرَض سطرٌ لفاتورةٍ نقدية: «بلا مهلة»
                                 خبرٌ لا يُقرأ، وفواتير النقد أكثرُ الفواتير. --}}
                            @if ($paymentTerms)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('payment_terms.label') }}</span>
                                    <span class="sl-fact__value sl-num">
                                        {{ $paymentTerms->summary() }}
                                    </span>
                                </div>
                            @endif

                            {{-- مصدر الطلب — يُعرَض متى سُجّل وحده: فاتورةٌ
                                 قديمة أو منشأةٌ لا تستعمل المصادر لا يُعرَض
                                 لها سطرٌ فارغ --}}
                            @if ($invoice->orderSource)
                                <div class="sl-fact">
                                    <span class="sl-fact__label">{{ __('sales_invoice.ui.order_source') }}</span>
                                    <span class="sl-fact__value">{{ $invoice->orderSource->name }}</span>
                                </div>
                            @endif

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_invoice.filters.added_by') }}</span>
                                <span class="sl-fact__value">{{ $invoice->createdByUser->name ?? '—' }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_invoice.context.currency') }}</span>
                                <span class="sl-fact__value sl-num">{{ $invoice->currency ?: CurrencySettings::code() }}</span>
                            </div>
                        </div>

                        <div class="sl-tags mt-2">
                            {{-- شارةُ الاستحقاق إلى جانب حالة السداد: الأولى
                                 تقول «كم بقي»، والثانية تقول «هل حان». --}}
                            @if ($paymentTerms && $paymentTerms->isAlert)
                                <span class="badge badge-light-{{ $paymentTerms->tone() }}"
                                      title="{{ $paymentTerms->summary() }}">
                                    <i class="feather {{ $paymentTerms->icon() }}"></i> {{ $paymentTerms->label() }}
                                </span>
                            @endif

                            <span class="badge badge-light-{{ $stateTone }}">
                                <i class="feather icon-{{ $stateIcon }}"></i> {{ $stateLabel }}
                            </span>

                            @if ($isTaxed)
                                <span class="badge badge-light-primary">
                                    <i class="feather icon-percent"></i> {{ __('sales_invoice.show.taxed') }}
                                </span>
                            @endif

                            @if ($return_invoices->isNotEmpty())
                                <span class="badge badge-light-danger">
                                    <i class="feather icon-corner-up-left"></i>
                                    {{ __('sales_invoice.show.returns_count', ['count' => $return_invoices->count()]) }}
                                </span>
                            @endif

                            @if ($invoice->journalEntry && $invoice->journalEntry->id)
                                <a href="{{ route('journal.show', $invoice->journalEntry->id) }}"
                                   class="badge badge-light-secondary">
                                    <i class="feather icon-book-open"></i> {{ __('sales_invoice.show.journal_entry') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-5">
                        {{-- الصندوق المشترك: يرسم السطر متى كان لقيمته وجود
                             ويسكت عنه متى كانت صفراً — نفس شرط شاشة الإنشاء
                             ونفس شرط المطبوعة --}}
                        @include('sales::components.totals', [
                            'totals' => [
                                'subtotal' => (float) ($invoice->subtotal ?? $invoice->total),
                                /* الخصم **صافياً من الكوبون**: العمود
                                   `discount_amount` يحمل خصومَ البنود وخصمَ
                                   المستند وخصمَ الكوبون معاً — كما يقيّدها
                                   القيد في «الخصم المسموح به» — فعرضُهما
                                   معاً يُظهر الكوبون مرّتين. */
                                'discount' => (float) ($invoice->discount_amount ?? 0)
                                    - (float) ($invoice->coupon_discount ?? 0),
                                'coupon' => (float) ($invoice->coupon_discount ?? 0),
                                'shipping' => (float) ($invoice->shipping_cost ?? 0),
                                'tax' => (float) ($invoice->tax_total ?? 0),
                                'grand_total' => (float) ($invoice->grand_total ?? $invoice->total),
                                // لا سطر «إيداع» هنا: `advance_payment` في
                                // فاتورة المبيعات يُحدَّث ليساوي مجموع
                                // المدفوعات (Invoice::updatePaymentStatus)،
                                // فسطرُه تكرارٌ لسطر «المدفوع» لا أكثر
                                'paid' => (float) $paidTotal,
                                // ما أرجعه مرتجعٌ أو إشعارٌ دائن — كان يُطرح من
                                // «المستحق» ولا يُقال من أين نقص
                                'returned' => (float) ($invoice->returned_payment ?? 0),
                                'remaining' => (float) $netDue,
                            ],
                            'totalsLabels' => [
                                'subtotal' => __('sales_invoice.subtotal'),
                                'discount' => __('sales_invoice.discount'),
                                'coupon' => __('sales_invoice.coupon.row', ['code' => $invoice->coupon_code ?? '']),
                                'shipping' => __('sales_invoice.ui.shipping'),
                                'tax' => __('sales_invoice.tax'),
                                'grand_total' => __('sales_invoice.grand_total'),
                                'paid' => __('sales_invoice.ui.paid_amount'),
                                'remaining' => __('sales_invoice.show.due'),
                            ],
                            'totalsDueWarning' => true,
                        ])
                    </div>
                </div>
            </div>

            {{-- ══════════ شريط الإجراءات ══════════ --}}
            <div class="sl-doc-card__foot">
                <div class="sl-toolbar">
                    {{-- المطبوعات — القوالب المصمَّمة في «قوالب المبيعات» أوّلاً --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button"
                                data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="feather icon-printer"></i> {{ __('sales_invoice.documents') }}
                        </button>

                        <div class="dropdown-menu">
                            @permission('sales.invoices.print')
                                @if ($templates->isNotEmpty())
                                    <h6 class="dropdown-header">{{ __('sales_invoice.show.from_settings') }}</h6>

                                    @foreach ($templates as $template)
                                        <a class="dropdown-item"
                                           href="{{ route('invoices.print', [$invoice->id, $template->id]) }}"
                                           target="_blank" rel="noopener">
                                            <i class="feather icon-file"></i> {{ $template->name }}
                                            @if ($template->is_default)
                                                <i class="feather icon-star text-warning"></i>
                                            @endif
                                        </a>
                                    @endforeach

                                    <div class="dropdown-divider"></div>
                                @endif

                                {{-- الطباعة والتنزيل بالقالب الافتراضي، لا بالمدمج --}}
                                <a class="dropdown-item"
                                   href="{{ route('invoices.print', array_filter([$invoice->id, $defaultTemplate?->id])) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-printer"></i> {{ __('sales_invoice.actions.print') }}
                                </a>
                            @endpermission

                            @if ($isTaxed)
                                @permission('sales.invoices.print_tax')
                                    <a class="dropdown-item"
                                       href="{{ route('invoices.print_tax', array_filter([$invoice->id, $defaultTaxTemplate?->id])) }}"
                                       target="_blank" rel="noopener">
                                        <i class="feather icon-percent"></i> {{ __('sales_invoice.actions.print_tax') }}
                                    </a>
                                @endpermission
                            @endif

                            @permission('sales.invoices.export_pdf')
                                <a class="dropdown-item"
                                   href="{{ route('invoices.generatePdf', array_filter([$invoice->id, $defaultTemplate?->id])) }}">
                                    <i class="feather icon-download"></i> {{ __('sales_invoice.actions.pdf') }}
                                </a>
                            @endpermission

                            @permission('sales.invoices.print')
                                {{-- القالب المدمج يبقى متاحاً صراحةً: منشأةٌ لم
                                     تصمّم قالباً بعد، أو قالبٌ صُمِّم وأُريد
                                     مقارنته بالأصل --}}
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('invoices.print', $invoice->id) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-file-text"></i> {{ __('sales_invoice.show.builtin_template') }}
                                </a>
                            @endpermission
                        </div>
                    </div>

                    @if (! $invoice->is_paid)
                        @permission('sales.payments.create')
                            @if ($hasReturn)
                                <button type="button" class="btn btn-outline-secondary" disabled
                                        title="{{ __('sales_payment.blocked.has_return_short') }}">
                                    <i class="feather icon-slash"></i> {{ __('sales_invoice.actions.add_payment') }}
                                </button>
                            @else
                                <a href="{{ route('paymentsClient.create', ['id' => $invoice->id, 'type' => 'invoice']) }}"
                                   class="btn btn-outline-secondary">
                                    <i class="feather icon-credit-card"></i> {{ __('sales_invoice.actions.add_payment') }}
                                </a>
                            @endif
                        @endpermission
                    @endif

                    @if (hasPermission('sales.returns.create') || hasPermission('sales.credit_notes.create'))
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                    data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="feather icon-corner-up-left"></i> {{ __('sales_invoice.show.return') }}
                            </button>

                            <div class="dropdown-menu">
                                @permission('sales.returns.create')
                                    {{-- مرتجعٌ واحد للفاتورة، والإشعارُ الدائن الذي سوّاها يُعدّ مرتجعاً --}}
                                    @if ($returnBlocked = $invoice->returnBlockReason())
                                        <span class="dropdown-item disabled text-muted" title="{{ $returnBlocked }}">
                                            <i class="feather icon-slash"></i> {{ __('sales_invoice.show.return_invoice') }}
                                        </span>
                                    @else
                                        <a class="dropdown-item" href="{{ route('ReturnIInvoices.create', ['id' => $invoice->id]) }}">
                                            <i class="feather icon-file-text"></i> {{ __('sales_invoice.show.return_invoice') }}
                                        </a>
                                    @endif
                                @endpermission
                                @permission('sales.credit_notes.create')
                                    {{-- والإشعارُ الدائن كالمرتجع: فاتورةٌ رُدَّت لا تُردّ مرّتين --}}
                                    @if ($creditBlocked = $invoice->creditNoteBlockReason())
                                        <span class="dropdown-item disabled text-muted" title="{{ $creditBlocked }}">
                                            <i class="feather icon-slash"></i> {{ __('sales_invoice.show.credit_note') }}
                                        </span>
                                    @else
                                        <a class="dropdown-item" href="{{ route('CreditNotes.create', ['id' => $invoice->id]) }}">
                                            <i class="feather icon-plus-circle"></i> {{ __('sales_invoice.show.credit_note') }}
                                        </a>
                                    @endif
                                @endpermission
                            </div>
                        </div>
                    @endif

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="feather icon-more-horizontal"></i> {{ __('sales_invoice.show.more') }}
                        </button>

                        <div class="dropdown-menu">
                            {{-- الملصقات الثلاثة تُطبع من المتصفّح، فتُفتح في
                                 لسانٍ جديد كما تُفتح بقيّة المطبوعات: الضغط
                                 عليها كان يغادر شاشة الفاتورة إلى ملفّ PDF
                                 ولا رجعة منه إلّا بزرّ الرجوع. --}}
                            @permission('sales.invoices.labels')
                                <h6 class="dropdown-header">{{ __('sales_invoice.show.labels') }}</h6>
                                <a class="dropdown-item" href="{{ route('invoices.label', $invoice->id) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-tag"></i> {{ __('sales_invoice.show.parcel_label') }}
                                </a>
                                <a class="dropdown-item" href="{{ route('invoices.picklist', $invoice->id) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-list"></i> {{ __('sales_invoice.show.picklist') }}
                                </a>
                                <a class="dropdown-item" href="{{ route('invoices.shipping_label', $invoice->id) }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-truck"></i> {{ __('sales_invoice.show.shipping_label') }}
                                </a>

                                <div class="dropdown-divider"></div>
                            @endpermission
                            @unless ($invoiceSettings->send_on_social)
                                {{-- بندٌ معطَّل لا غياب: من بحث عن «إرسال واتساب»
                                     ولم يجده لا سبيل له إلى معرفة أنّ له مفتاحاً --}}
                                <span class="dropdown-item disabled text-muted" style="white-space:normal">
                                    <i class="feather icon-slash"></i>
                                    {{ __('sales_invoice.settings_notice.social_off') }}
                                </span>
                            @endunless

                            @if ($invoiceSettings->send_on_social)
                                <h6 class="dropdown-header">{{ __('sales_invoice.show.send_via') }}</h6>

                                @if (! empty($invoice->client?->phone))
                                    <a class="dropdown-item" target="_blank" rel="noopener"
                                       href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $invoice->client->phone) }}?text={{ urlencode(
                                           __('sales_invoice.show.whatsapp_body', [
                                               'client' => $clientName,
                                               'link' => route('invoices.print', ['id' => $invoice->id, 'embed' => true]),
                                               'business' => \App\Support\BusinessInfo::name(),
                                           ]),
                                       ) }}">
                                        <i class="feather icon-message-circle text-success"></i> {{ __('sales_invoice.show.whatsapp') }}
                                    </a>
                                @endif

                                @permission('sales.invoices.send')
                                    {{-- كان رابطاً GET يرسل فوراً بقالبٍ مدمج ولا
                                         يسأل عن شيء. صار نافذةً: المستلِم والقالب
                                         وسطرُ الملاحظة — انظر `sales::partials.send-email` --}}
                                    @include('sales::partials.send-email', [
                                        'mailId' => 'invoice-' . $invoice->id,
                                        'mailAsDropdownItem' => true,
                                        'mailRoute' => route('invoices.send', $invoice->id),
                                        'mailNumber' => $invoice->code,
                                        'mailEmail' => $invoice->client?->email,
                                        'mailClientName' => $clientName,
                                        'mailDocumentType' => $isTaxed ? 'tax_invoice' : 'sales_invoice',
                                        'mailTemplates' => $isTaxed ? ($taxDocumentTemplates ?? collect()) : $templates,
                                        'mailDefaultTemplate' => $activeTemplate,
                                    ])
                                @endpermission
                            @endif

                            <div class="dropdown-divider"></div>

                            @permission('sales.invoices.notes')
                                <a class="dropdown-item" href="#" onclick="event.preventDefault(); addNoteOrAttachment()">
                                    <i class="feather icon-paperclip"></i> {{ __('sales_invoice.show.add_note') }}
                                </a>
                                <a class="dropdown-item" href="#" onclick="event.preventDefault(); viewAllNotes()">
                                    <i class="feather icon-file-text"></i> {{ __('sales_invoice.show.all_notes') }}
                                </a>
                            @endpermission
                            @permission('sales.installments.create')
                                {{-- اتفاقيةُ التقسيط جدولُ سدادِ دَينٍ قائم: فاتورةٌ
                                     سُدِّدت بالكامل لا دَينَ فيها يُجدوَل. يُعطَّل
                                     الزرُّ بسببه — لا يُخفى — كي يُعرَف لِمَ لا يعمل. --}}
                                @if ($installmentBlocked = $invoice->installmentBlockReason())
                                    <span class="dropdown-item disabled text-muted" title="{{ $installmentBlocked }}">
                                        <i class="feather icon-slash"></i> {{ __('sales_invoice.show.installment_agreement') }}
                                    </span>
                                @else
                                    <a class="dropdown-item" href="{{ route('installments.create', ['id' => $invoice->id]) }}">
                                        <i class="feather icon-repeat"></i> {{ __('sales_invoice.show.installment_agreement') }}
                                    </a>
                                @endif
                            @endpermission
                            @permission('sales.invoices.create')
                                <a class="dropdown-item" href="#" onclick="event.preventDefault(); copyInvoice()">
                                    <i class="feather icon-copy"></i> {{ __('sales_invoice.show.copy') }}
                                </a>
                            @endpermission

                            @permission('sales.invoices.delete')
                                <div class="dropdown-divider"></div>
                                {{-- زرّ لا رابط: المسار يقبل DELETE وحده، والرابط
                                     الذي كان هنا يُرجع 405 --}}
                                <a class="dropdown-item text-danger" href="#"
                                   onclick="event.preventDefault(); deleteInvoice()">
                                    <i class="feather icon-trash-2"></i> {{ __('sales_invoice.actions.delete') }}
                                </a>
                            @endpermission
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['invoice-preview', 'eye', __('sales_invoice.show.tab_preview'), true],
                            ['items', 'package', __('sales_invoice.show.tab_items'), false],
                            ['payments', 'credit-card', __('sales_invoice.show.tab_payments'), false],
                            ['warehouse', 'box', __('sales_invoice.show.tab_stock'), false],
                            ['returns', 'corner-up-left', __('sales_invoice.show.tab_returns'), false],
                            ['profit', 'trending-up', __('sales_invoice.show.tab_profit'), false],
                            ['signature', 'edit-2', __('sales_invoice.show.tab_signature'), false],
                            ['activity', 'clock', __('sales_invoice.show.tab_activity'), false],
                        ];
                    @endphp

                    @foreach ($tabs as [$id, $icon, $label, $active])
                        @php
                            /* الغيابان مختلفان: من لا يملك الصلاحية لا يُقال له
                               شيء — الربح ليس من شأنه؛ ومن يملكها وأطفأ الإعدادَ
                               يُقال له إنّ مفتاحاً أخفاه وأين هو. */
                            $profitByPermission = $id === 'profit' && ! hasPermission('sales.invoices.view_profit');
                            $profitBySetting = $id === 'profit' && ! $profitByPermission && ! $invoiceSettings->show_invoice_profit;
                        @endphp

                        @continue($profitByPermission)

                        @if ($profitBySetting)
                            <li class="nav-item">
                                <span class="nav-link disabled text-muted" title="{{ __('sales_invoice.settings_notice.profit_off') }}">
                                    <i class="feather icon-slash"></i> {{ $label }}
                                </span>
                            </li>
                            @continue
                        @endif

                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" data-toggle="tab"
                               data-bs-toggle="tab" href="#{{ $id }}" role="tab">
                                <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="tab-content">

                {{-- معاينة المستند --}}
                <div class="tab-pane active" id="invoice-preview" role="tabpanel">
                    <iframe src="{{ $previewRoute }}" class="sl-doc-frame" frameborder="0"
                            title="{{ __('sales_invoice.show.tab_preview') }}"></iframe>
                </div>

                {{-- البنود — سبعة أعمدة وسبع خلايا، بعد أن كانت ثلاثاً --}}
                <div class="tab-pane" id="items" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:46px">{{ __('sales_invoice.form.row') }}</th>
                                    <th>{{ __('sales_invoice.item') }}</th>
                                    <th class="num" style="width:110px">{{ __('sales_invoice.quantity') }}</th>
                                    <th class="num" style="width:130px">{{ __('sales_invoice.unit_price') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_invoice.discount') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_invoice.tax') }}</th>
                                    <th class="num" style="width:140px">{{ __('sales_invoice.total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($invoice->items as $index => $item)
                                    @php
                                        $lineSubtotal = $item->unit_price * $item->quantity;
                                        $lineTax = ($item->tax_1 ?? 0) > 0
                                            ? ($lineSubtotal - ($item->discount ?? 0)) * ($item->tax_1 / 100)
                                            : 0;
                                        $lineTotal = $lineSubtotal - ($item->discount ?? 0) + $lineTax;
                                    @endphp

                                    <tr>
                                        <td class="num"><span class="sl-row__index">{{ $index + 1 }}</span></td>
                                        <td>
                                            <span class="sl-media__title d-block">
                                                {{ $item->product->name ?? $item->item }}
                                                @if ($item->unit_name)
                                                    <span class="sl-media__sub">({{ $item->unit_name }})</span>
                                                @endif
                                                @if (!empty($item->variant_label))
                                                    <span class="sl-media__sub">{{ $item->variant_label }}</span>
                                                @endif
                                            </span>
                                            @if ($item->description)
                                                <span class="sl-media__sub">{{ $item->description }}</span>
                                            @endif
                                        </td>
                                        <td class="num sl-num">{{ CurrencySettings::quantity($item->quantity) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($item->unit_price) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($item->discount ?? 0) }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::format($lineTax) }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($lineTotal) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'package',
                                                'title' => __('sales_invoice.show.no_items'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- المدفوعات — كان التبويب بلا لوحة --}}
                <div class="tab-pane" id="payments" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:120px">{{ __('sales_invoice.show.payment_number') }}</th>
                                    <th style="width:160px">{{ __('sales_invoice.table.date') }}</th>
                                    <th>{{ __('sales_invoice.show.treasury') }}</th>
                                    <th>{{ __('sales_invoice.filters.added_by') }}</th>
                                    <th class="num" style="width:150px">{{ __('sales_invoice.amount') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td><span class="sl-code">{{ $payment->reference_number ?: '#' . $payment->id }}</span></td>
                                        <td class="sl-num">
                                            {{ LanguageSettings::formatDate($payment->payment_date ?: $payment->created_at) }}
                                        </td>
                                        <td><span class="sl-media__sub">{{ $payment->treasury_name ?: '—' }}</span></td>
                                        <td><span class="sl-media__sub">{{ $payment->employee->name ?? '—' }}</span></td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($payment->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'credit-card',
                                                'title' => __('sales_invoice.show.no_payments'),
                                                'hint' => __('sales_invoice.show.no_payments_hint'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if ($payments->isNotEmpty())
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">{{ __('sales_invoice.ui.paid_amount') }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($paidTotal) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- المخزون — كان التبويب بلا لوحة --}}
                <div class="tab-pane" id="warehouse" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_invoice.item') }}</th>
                                    <th>{{ __('sales_invoice.ui.warehouse') }}</th>
                                    <th class="num" style="width:150px">{{ __('sales_invoice.show.deducted') }}</th>
                                    <th class="num" style="width:150px">{{ __('sales_invoice.show.on_hand') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($stockRows as $row)
                                    <tr>
                                        <td>
                                            <span class="sl-media__title">
                                                {{ $row['item']->product->name ?? $row['item']->item }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="sl-media__sub">
                                                {{ $row['store_house']->name ?? __('sales_invoice.show.no_warehouse') }}
                                            </span>
                                        </td>
                                        <td class="num sl-num text-danger">
                                            −{{ CurrencySettings::number($row['deducted'], 0) }}
                                        </td>
                                        <td class="num sl-num">{{ CurrencySettings::number($row['on_hand'], 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'box',
                                                'title' => __('sales_invoice.show.no_stock'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- المرتجعات --}}
                <div class="tab-pane" id="returns" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:120px">{{ __('sales_invoice.table.number') }}</th>
                                    <th>{{ __('sales_invoice.table.client') }}</th>
                                    <th style="width:170px">{{ __('sales_invoice.table.date') }}</th>
                                    <th>{{ __('sales_invoice.filters.added_by') }}</th>
                                    <th class="num" style="width:150px">{{ __('sales_invoice.amount') }}</th>
                                    <th class="text-center" style="width:60px">{{ __('sales_invoice.table.actions') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($return_invoices as $return)
                                    <tr>
                                        <td><span class="sl-code">{{ $return->code ?: '#' . $return->id }}</span></td>
                                        <td>
                                            <span class="sl-media__sub">
                                                {{ $return->client
                                                    ? ($return->client->trade_name ?: trim($return->client->first_name . ' ' . $return->client->last_name))
                                                    : __('sales_invoice.table.unknown_client') }}
                                            </span>
                                        </td>
                                        <td class="sl-num">{{ LanguageSettings::formatDate($return->created_at, true) }}</td>
                                        <td><span class="sl-media__sub">{{ $return->createdByUser->name ?? '—' }}</span></td>
                                        <td class="num sl-line-total text-danger">
                                            {{ CurrencySettings::format($return->grand_total ?? $return->total) }}
                                        </td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                        data-toggle="dropdown" data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @permission('sales.returns.view')
                                                        <a class="dropdown-item" href="{{ route('ReturnIInvoices.show', $return->id) }}">
                                                            <i class="feather icon-eye"></i> {{ __('sales_invoice.actions.show') }}
                                                        </a>
                                                    @endpermission
                                                    @permission('sales.returns.edit')
                                                        <a class="dropdown-item" href="{{ route('ReturnIInvoices.edit', $return->id) }}">
                                                            <i class="feather icon-edit-2"></i> {{ __('sales_invoice.actions.edit') }}
                                                        </a>
                                                    @endpermission
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'corner-up-left',
                                                'title' => __('sales_invoice.show.no_returns'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ربح الفاتورة --}}
                @if (hasPermission('sales.invoices.view_profit') && $invoiceSettings->show_invoice_profit)
                    <div class="tab-pane" id="profit" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table sl-doc-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('sales_invoice.item') }}</th>
                                        <th class="num" style="width:110px">{{ __('sales_invoice.quantity') }}</th>
                                        <th class="num" style="width:140px">{{ __('sales_invoice.unit_price') }}</th>
                                        <th class="num" style="width:150px">{{ __('sales_invoice.show.net_revenue') }}</th>
                                        <th class="num" style="width:150px">{{ __('sales_invoice.show.cost_price') }}</th>
                                        <th class="num" style="width:150px">{{ __('sales_invoice.show.profit') }}</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($profitReport['rows'] as $row)
                                        <tr>
                                            <td>
                                                <span class="sl-media__title">{{ $row['name'] }}</span>
                                                @if ($row['is_free'])
                                                    {{-- بندٌ حرٌّ: لا منتجَ له فلا تكلفةَ شراء —
                                                         إيرادُه ربحٌ كلّه، وذلك صحيحٌ لا نقص --}}
                                                    <span class="badge badge-light-secondary">
                                                        {{ __('sales_invoice.ui.free_item') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="num sl-num">
                                                {{ CurrencySettings::number($row['quantity'], 2) }}
                                                @if ($row['unit_name'])
                                                    <span class="sl-help">{{ $row['unit_name'] }}</span>
                                                @endif
                                            </td>
                                            <td class="num sl-num">{{ CurrencySettings::format($row['unit_price']) }}</td>
                                            <td class="num sl-num">{{ CurrencySettings::format($row['revenue']) }}</td>
                                            <td class="num sl-num">
                                                {{ $row['is_free'] ? '—' : CurrencySettings::format($row['cost']) }}
                                            </td>
                                            <td class="num sl-line-total {{ $row['profit'] < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ CurrencySettings::format($row['profit']) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                @include('sales::components.empty-state', [
                                                    'icon' => 'trending-up',
                                                    'title' => __('sales_invoice.show.no_items'),
                                                ])
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                @if (! empty($profitReport['rows']))
                                    <tfoot>
                                        {{-- خصم الفاتورة سطرٌ مستقلّ لا يُطوى في الإيراد:
                                             وُزِّع على البنود بنسبة إيراد كلٍّ منها،
                                             وذكرُه يُبيّن لماذا نقص الإيراد عن مجموع البنود --}}
                                        @if ($profitReport['invoice_discount'] > 0)
                                            <tr>
                                                <td colspan="3" class="text-end">{{ __('sales_invoice.show.invoice_discount') }}</td>
                                                <td class="num sl-num text-danger">
                                                    −{{ CurrencySettings::format($profitReport['invoice_discount']) }}
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td colspan="3" class="text-end">{{ __('sales_invoice.show.totals') }}</td>
                                            <td class="num sl-line-total">{{ CurrencySettings::format($profitReport['revenue']) }}</td>
                                            <td class="num sl-line-total">{{ CurrencySettings::format($profitReport['cost']) }}</td>
                                            <td class="num sl-line-total {{ $profitTotal < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ CurrencySettings::format($profitTotal) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>

                        <div class="sl-doc-card__body">
                            <p class="sl-help" style="margin:0">
                                <i class="feather icon-info"></i>
                                {{ __('sales_invoice.show.profit_basis') }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- التوقيع الإلكتروني — المُعرِّفات عقدٌ مع سكربت `signature_pad` --}}
                <div class="tab-pane" id="signature" role="tabpanel">
                    <div class="sl-doc-card__body">
                        <form action="{{ route('invoices.signatures.store', $invoice->id) }}" method="POST"
                              id="signature-form">
                            @csrf

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="signer-name">
                                            {{ __('sales_invoice.show.signer_name') }} <span class="required">*</span>
                                        </label>
                                        <input type="text" name="signer_name" id="signer-name" class="form-control" required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="signer-role">
                                            {{ __('sales_invoice.show.signer_role') }} <span class="required">*</span>
                                        </label>
                                        <input type="text" name="signer_role" id="signer-role" class="form-control" required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="amount-paid">{{ __('sales_invoice.ui.paid_amount') }}</label>
                                        <input type="number" name="amount_paid" id="amount-paid"
                                               class="form-control" step="0.01" min="0" placeholder="0.00"
                                               @disabled($hasReturn)>
                                        <small class="sl-help">
                                            @if ($hasReturn)
                                                <i class="feather icon-slash"></i>
                                                {{ __('sales_payment.blocked.has_return_short') }}
                                            @else
                                                {{ __('sales_invoice.show.signature_payment_hint') }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </div>

                            {{-- ما يحتاجه سندُ القبض: طريقةُ دفعٍ وخزينة.
                                 كان القبضُ يُسجَّل «نقداً» مكتوبةً في
                                 الشيفرة وبلا خزينةٍ في السند، فيُرحَّل
                                 القيدُ على خزينةٍ لا يذكرها السند --}}
                            @unless ($hasReturn)
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="sl-field">
                                            <label for="signature-payment-method">
                                                {{ __('sales_invoice.show.signature_payment_method') }}
                                            </label>
                                            <select name="payment_method" id="signature-payment-method" class="form-control">
                                                @foreach ($signaturePaymentMethods as $method)
                                                    <option value="{{ $method->id }}" @selected($method->is_default)>
                                                        {{ $method->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="sl-field">
                                            <label for="signature-treasury">
                                                {{ __('sales_invoice.show.signature_treasury') }}
                                            </label>
                                            <select name="treasury_id" id="signature-treasury" class="form-control">
                                                <option value="">{{ __('sales_invoice.show.signature_treasury_auto') }}</option>
                                                @foreach ($signatureTreasuries as $treasury)
                                                    <option value="{{ $treasury->id }}">{{ $treasury->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="sl-help">{{ __('sales_invoice.show.signature_treasury_hint') }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endunless

                            <div class="sl-field mb-3">
                                <label>{{ __('sales_invoice.show.signature') }} <span class="required">*</span></label>

                                <div class="sl-signature">
                                    <canvas id="signature-pad"></canvas>
                                    <div id="signature-guide" class="sl-signature__guide">
                                        {{ __('sales_invoice.show.sign_here') }}
                                    </div>
                                </div>

                                <input type="hidden" name="signature_data" id="signature-data">
                                <small class="sl-help">{{ __('sales_invoice.show.sign_hint') }}</small>
                            </div>

                            <div class="sl-toolbar">
                                <button type="button" id="clear-signature" class="btn btn-outline-danger">
                                    <i class="feather icon-x"></i> {{ __('sales_invoice.show.clear_signature') }}
                                </button>
                                @permission('sales.invoices.edit')
                                    <button type="submit" id="save-signature" class="btn btn-primary">
                                        <i class="feather icon-save"></i> {{ __('sales_invoice.show.save_signature') }}
                                    </button>
                                @endpermission
                            </div>
                        </form>

                        <div class="sl-section mt-4">
                            <div class="sl-section__head">
                                <i class="feather icon-edit-2"></i>
                                <h6 class="sl-section__title">{{ __('sales_invoice.show.signatures_log') }}</h6>
                            </div>

                            @forelse ($invoice->signatures ?? [] as $signature)
                                <div class="sl-doc-note">
                                    <span class="sl-doc-note__icon"><i class="feather icon-check"></i></span>
                                    <div class="sl-doc-note__body flex-grow-1">
                                        <strong>{{ $signature->signer_name }}</strong>
                                        @if ($signature->signer_role)
                                            <span class="sl-media__sub">— {{ $signature->signer_role }}</span>
                                        @endif

                                        <span class="sl-help d-block">
                                            {{ LanguageSettings::formatDate($signature->created_at, true) }}
                                            @if ($signature->amount_paid)
                                                — {{ __('sales_invoice.ui.paid_amount') }}:
                                                {{ CurrencySettings::format($signature->amount_paid) }}
                                            @endif
                                        </span>

                                        <img src="{{ $signature->signature_data }}" alt="{{ $signature->signer_name }}"
                                             class="sl-signature__proof">
                                    </div>
                                </div>
                            @empty
                                @include('sales::components.empty-state', [
                                    'icon' => 'edit-2',
                                    'title' => __('sales_invoice.show.no_signatures'),
                                ])
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="activity" role="tabpanel">
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
                                                <strong>{{ $log->user->name ?? __('sales_invoice.show.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('sales_invoice.table.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => __('sales_invoice.show.no_activity'),
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- نموذج الحذف — يُرسله `deleteInvoice()` بعد التأكيد --}}
        <form id="deleteForm" action="{{ route('invoices.destroy', $invoice->id) }}" method="POST" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection

    @section('scripts')
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

        <script>
            $(document).ready(function() {
                // تهيئة Toastr
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": true,
                    "progressBar": true,
                    "positionClass": "toast-top-left",
                    "preventDuplicates": true,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut",
                    "rtl": true,
                    "escapeHtml": true
                };

                // تهيئة لوحة التوقيع
                const canvas = document.getElementById('signature-pad');
                if (canvas) {
                    const signaturePad = new SignaturePad(canvas, {
                        backgroundColor: 'rgb(255,255,255)',
                        penColor: 'rgb(0,0,0)',
                        minWidth: 0.7,
                        maxWidth: 2,
                        throttle: 0,
                        onBegin: () => {
                            const guide = document.getElementById('signature-guide');
                            if (guide) guide.style.display = 'none';
                        }
                    });

                    // ضبط حجم Canvas
                    function resizeCanvas() {
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        const width = canvas.offsetWidth;
                        const height = canvas.offsetHeight;

                        if (width === 0 || height === 0) return;

                        const data = !signaturePad.isEmpty() ? signaturePad.toData() : null;

                        canvas.width = width * ratio;
                        canvas.height = height * ratio;
                        canvas.getContext('2d').scale(ratio, ratio);

                        canvas.style.width = width + 'px';
                        canvas.style.height = height + 'px';

                        if (data) {
                            signaturePad.fromData(data);
                        }
                    }

                    window.addEventListener('resize', resizeCanvas);
                    resizeCanvas();

                    // مسح التوقيع
                    document.getElementById('clear-signature')?.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (!signaturePad.isEmpty()) {
                            Swal.fire({
                                title: 'هل أنت متأكد؟',
                                text: "سيتم مسح التوقيع الحالي تماماً!",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'نعم، امسح!',
                                cancelButtonText: 'إلغاء'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    signaturePad.clear();
                                    const guide = document.getElementById('signature-guide');
                                    if (guide) guide.style.display = 'block';
                                    toastr.success('تم مسح التوقيع بنجاح', 'عملية ناجحة');
                                }
                            });
                        } else {
                            toastr.info('لا يوجد توقيع لمسحه', 'ملاحظة');
                        }
                    });

                    // حفظ التوقيع
                    document.getElementById('signature-form')?.addEventListener('submit', function(e) {
                        e.preventDefault();

                        const signerName = document.getElementById('signer-name').value.trim();
                        const signerRole = document.getElementById('signer-role').value.trim();

                        if (!signerName) {
                            toastr.error('الرجاء إدخال الاسم الكامل للموقع', 'حقل مطلوب');
                            return;
                        }

                        if (!signerRole) {
                            toastr.error('الرجاء إدخال صفة الموقع', 'حقل مطلوب');
                            return;
                        }

                        if (signaturePad.isEmpty()) {
                            toastr.error('الرجاء تقديم التوقيع أولاً', 'توقيع مطلوب');
                            return;
                        }

                        Swal.fire({
                            title: 'تأكيد الحفظ',
                            text: "هل أنت متأكد من حفظ هذا التوقيع؟",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'نعم، احفظه!',
                            cancelButtonText: 'إلغاء'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const signatureData = signaturePad.toDataURL();
                                document.getElementById('signature-data').value = signatureData;

                                Swal.fire({
                                    title: 'جاري الحفظ',
                                    html: 'يرجى الانتظار...',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });

                                const form = this;
                                axios.post(form.action, new FormData(form))
                                    .then(response => {
                                        Swal.close();
                                        if (response.data.success) {
                                            toastr.success('تم حفظ التوقيع بنجاح', 'نجاح');
                                            setTimeout(() => {
                                                window.location.reload();
                                            }, 2000);
                                        } else {
                                            toastr.error(response.data.message ||
                                                'حدث خطأ أثناء الحفظ', 'خطأ');
                                        }
                                    })
                                    .catch(error => {
                                        Swal.close();
                                        /* سببُ الرفض كما قاله الخادم.
                                           كان يُقرأ `errors` وحده — وهو
                                           لا يوجد إلّا في أخطاء التحقّق،
                                           فرفضُ «فاتورةٌ لها مرتجع» أو
                                           «فترةٌ مقفلة» يخرج للمستخدم
                                           «فشل في الحفظ» بلا سبب. */
                                        const data = error.response && error.response.data;
                                        let errorMessage = 'فشل في الحفظ. يرجى المحاولة لاحقًا.';

                                        if (data && data.errors) {
                                            errorMessage = Object.values(data.errors).join('<br>');
                                        } else if (data && data.message) {
                                            errorMessage = data.message;
                                        }

                                        toastr.error(errorMessage, 'خطأ');
                                    });
                            }
                        });
                    });
                }
            });

            // دالة حذف الفاتورة
            function deleteInvoice() {
                Swal.fire({
                    title: 'حذف فاتورة المبيعات',
                    text: 'هل أنت متأكد من حذف فاتورة المبيعات رقم "{{ $invoice->id }}"؟ هذا الإجراء لا يمكن التراجع عنه!',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteForm').submit();
                    }
                });
            }

            // دالة نسخ الفاتورة
            function copyInvoice() {
                Swal.fire({
                    title: 'نسخ فاتورة المبيعات',
                    text: 'سيتم إنشاء نسخة جديدة من فاتورة المبيعات',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#17a2b8',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'تأكيد النسخ',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ route('invoices.create') }}?copy_from={{ $invoice->id }}";
                    }
                });
            }

            // دالة طباعة الفاتورة
            function printInvoice(url) {
                window.open(url, '_blank');
            }

            // دالة إضافة ملاحظة
            function addNoteOrAttachment() {
                const now = new Date();
                const currentDate = now.toISOString().split('T')[0];
                const currentTime = now.toTimeString().split(' ')[0].substring(0, 5);

                Swal.fire({
                    title: 'إضافة ملاحظة أو مرفق',
                    html: `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="process" class="form-label text-start d-block">نوع الإجراء:</label>
                            <select id="process" class="form-control">
                                <option value="">اختر نوع الإجراء</option>
                                <option value="مراجعة الفاتورة">مراجعة الفاتورة</option>
                                <option value="تأكيد الدفع">تأكيد الدفع</option>
                                <option value="تأكيد التسليم">تأكيد التسليم</option>
                                <option value="التواصل مع العميل">التواصل مع العميل</option>
                                <option value="تحديث البيانات">تحديث البيانات</option>
                                <option value="ملاحظة عامة">ملاحظة عامة</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="noteDate" class="form-label text-start d-block">التاريخ:</label>
                            <input type="date" id="noteDate" class="form-control" value="${currentDate}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="noteTime" class="form-label text-start d-block">الوقت:</label>
                            <input type="time" id="noteTime" class="form-control" value="${currentTime}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="attachment" class="form-label text-start d-block">مرفق (اختياري):</label>
                            <input type="file" id="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label text-start d-block">تفاصيل الملاحظة:</label>
                        <textarea id="note" class="form-control" rows="4" placeholder="اكتب تفاصيل الملاحظة هنا..."></textarea>
                    </div>
                `,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-save me-1"></i> حفظ الملاحظة',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> إلغاء',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        const process = document.getElementById('process').value;
                        const note = document.getElementById('note').value.trim();
                        const noteDate = document.getElementById('noteDate').value;
                        const noteTime = document.getElementById('noteTime').value;
                        const attachment = document.getElementById('attachment').files[0];

                        if (!process) {
                            Swal.showValidationMessage('يرجى اختيار نوع الإجراء');
                            return false;
                        }
                        if (!note) {
                            Swal.showValidationMessage('يرجى إدخال تفاصيل الملاحظة');
                            return false;
                        }
                        if (!noteDate) {
                            Swal.showValidationMessage('يرجى تحديد التاريخ');
                            return false;
                        }
                        if (!noteTime) {
                            Swal.showValidationMessage('يرجى تحديد الوقت');
                            return false;
                        }

                        return {
                            process,
                            note,
                            noteDate,
                            noteTime,
                            attachment
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        saveNoteToDatabase(result.value);
                    }
                });
            }

            // دالة حفظ الملاحظة في قاعدة البيانات
            function saveNoteToDatabase(noteData) {
                const formData = new FormData();
                formData.append('description', noteData.note);
                formData.append('process', noteData.process);
                formData.append('date', noteData.noteDate);
                formData.append('time', noteData.noteTime);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                if (noteData.attachment) {
                    formData.append('attachment', noteData.attachment);
                }

                // يجب تعديل هذا المسار حسب مسارات فواتير المبيعات في مشروعك
fetch(`{{ route('invoices.addNote', $invoice->id) }}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'تم الحفظ بنجاح!',
                                text: 'تم إضافة الملاحظة بنجاح',
                                icon: 'success',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'خطأ!',
                                text: data.message || 'حدث خطأ أثناء حفظ الملاحظة',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء الاتصال بالخادم',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    });
            }

            // دالة عرض جميع الملاحظات
            function viewAllNotes() {
                Swal.fire({
                    title: 'جاري تحميل الملاحظات...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(`{{ route('invoices.getNotes', $invoice->id) }}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            let notesHtml = '';

                            if (data.notes && data.notes.length > 0) {
                                data.notes.forEach((note, index) => {
                                    const processName = note.process || 'غير محدد';
                                    const employeeName = note.employee_name || 'غير محدد';
                                    const noteDate = note.date || 'غير محدد';
                                    const noteTime = note.time || 'غير محدد';
                                    const description = note.description || 'لا يوجد وصف';

                                    notesHtml += `
                                <div class="card mb-3 border-start border-primary border-3" id="note-${note.id}">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge bg-primary me-2">${index + 1}</span>
                                                    <h6 class="mb-0 text-primary fw-bold">${processName}</h6>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user me-1 text-info"></i><strong>الموظف:</strong> ${employeeName}
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-calendar me-1 text-success"></i><strong>التاريخ:</strong> ${noteDate}
                                                    <i class="fas fa-clock me-2 ms-3 text-warning"></i><strong>الوقت:</strong> ${noteTime}
                                                </small>
                                            </div>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteNote(${note.id})" title="حذف الملاحظة">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>

                                        <div class="mt-3 p-3 bg-light rounded">
                                            <p class="mb-0 text-dark">${description}</p>
                                        </div>

                                        ${note.has_attachment ? `
                                                    <div class="mt-3 pt-2 border-top">
                                                        <a href="${note.attachment_url}" target="_blank" class="btn btn-outline-info btn-sm">
                                                            <i class="fas fa-paperclip me-1"></i>عرض المرفق
                                                        </a>
                                                    </div>
                                                ` : ''}

                                        ${note.created_at ? `
                                                    <div class="mt-2 pt-2 border-top">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>تم الإنشاء: ${note.created_at}
                                                        </small>
                                                    </div>
                                                ` : ''}
                                    </div>
                                </div>
                            `;
                                });
                            } else {
                                notesHtml = `
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-info-circle fs-1 text-info mb-3"></i>
                                <h5 class="mb-2">لا توجد ملاحظات حتى الآن</h5>
                                <p class="mb-0 text-muted">يمكنك إضافة ملاحظة جديدة من خلال زر "إضافة ملاحظة جديدة"</p>
                            </div>
                        `;
                            }

                            Swal.fire({
                                title: `<i class="fas fa-sticky-note me-2 text-primary"></i>جميع الملاحظات (${data.notes ? data.notes.length : 0})`,
                                html: `
                            <div class="text-start" style="max-height: 500px; overflow-y: auto; padding: 10px;">
                                ${notesHtml}
                            </div>
                            <div class="mt-3 pt-3 border-top text-center">
                                <button type="button" class="btn btn-success btn-sm me-2" onclick="Swal.close(); addNoteOrAttachment();">
                                    <i class="fas fa-plus me-1"></i>إضافة ملاحظة جديدة
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload();">
                                    <i class="fas fa-sync me-1"></i>تحديث الصفحة
                                </button>
                            </div>
                        `,
                                width: '800px',
                                showConfirmButton: false,
                                showCancelButton: true,
                                cancelButtonText: '<i class="fas fa-times me-1"></i>إغلاق',
                                cancelButtonColor: '#6c757d',
                                customClass: {
                                    popup: 'swal-rtl'
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'خطأ!',
                                text: data.message || 'لم يتم العثور على ملاحظات',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('خطأ في جلب الملاحظات:', error);
                        Swal.fire({
                            title: 'خطأ في الاتصال!',
                            text: 'حدث خطأ أثناء جلب الملاحظات. يرجى التحقق من الاتصال بالإنترنت والمحاولة مرة أخرى.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            footer: `<small class="text-muted">تفاصيل الخطأ: ${error.message}</small>`
                        });
                    });
            }

            // دالة حذف ملاحظة
            function deleteNote(noteId) {
                Swal.fire({
                    title: 'تأكيد الحذف',
                    text: 'هل أنت متأكد من حذف هذه الملاحظة؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`{{ route('invoices.deleteNote', ':id') }}`.replace(':id', noteId), {

                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                        'content'),
                                    'Content-Type': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('تم الحذف!', 'تم حذف الملاحظة بنجاح', 'success');
                                    viewAllNotes();
                                } else {
                                    Swal.fire('خطأ!', data.message, 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire('خطأ!', 'حدث خطأ أثناء حذف الملاحظة', 'error');
                            });
                    }
                });
            }

            // عرض رسائل النجاح والخطأ
            @if (session('success'))
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: '{{ session('success') }}',
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    title: 'خطأ!',
                    text: '{{ session('error') }}',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            @endif

            // تأثيرات إضافية للتفاعل
            document.addEventListener('DOMContentLoaded', function() {
                const buttons = document.querySelectorAll('.action-btn');
                buttons.forEach(button => {
                    button.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px) scale(1.02)';
                    });

                    button.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0) scale(1)';
                    });
                });
            });
        </script>
    @endsection
