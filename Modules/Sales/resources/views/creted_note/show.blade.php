{{--
    عرض إشعار دائن.

    أُعيد بناء الشاشة على قياس شاشة عرض الفاتورة (`sales::invoices.show`)
    بنظام `sl-doc-*`: ترويسة ← بطاقة هوية المستند وإجمالياته ← شريط
    إجراءات واحد ← تبويبات التفاصيل.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كل مبلغ في الشاشة)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • القوالب         ← «قوالب المبيعات» (النوع `credit_note`)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *`@section('content')` مكرَّرة مرّتين* في أوّل الملف.
      • *شاشةٌ بلا بيانات*: لا بنود ولا إجماليات ولا ضرائب ولا فاتورة
        مرجعية ولا قيد محاسبي؛ نسخةٌ من قالب الطباعة مصغَّرةً بـ
        `transform: scale(0.8)` وسجلُّ نشاط.
      • *الطباعة بكشط الـDOM*: `printCreditNote()` تقرأ محتوى
        `div[style*="transform: scale(0.8);"]` وتبنيه في نافذة جديدة —
        تنكسر بمجرّد تغيير سطر تنسيق، وتتجاهل القوالب المصمَّمة في
        «قوالب المستندات» رغم أنّ المسار `CreditNotes.print` يقبلها.
      • *«الإلغاء» بلا زرّ*: `CreditNotes.cancel` يعكس القيد ويُرجع
        الكمّيات، ولا شيء في الشاشة يستدعيه.
      • *`<style>` من ٢٧٠ سطراً* بألوان مثبَّتة لا تتبع «مظهر النظام».
--}}

@extends('sales::master')

@section('title', __('sales_credit_note.show.title', ['code' => $credit->credit_number ?: $credit->id]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: قوالبُ المستندات لا تعرّفهما، وكلُّ
         قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها في محدِّدٍ واحد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $clientName = $credit->client
            ? ($credit->client->trade_name
                ?: trim(($credit->client->first_name ?? '') . ' ' . ($credit->client->last_name ?? '')))
            : __('sales_documents.ui.unknown');

        $code = $credit->credit_number ?: $credit->id;

        $taxRows = $TaxsInvoice ?? collect();
        $isTaxed = ($credit->total_tax ?? 0) > 0 || $taxRows->count() > 0;

        $templates = (isset($documentTemplates) && $documentTemplates instanceof \Illuminate\Support\Collection)
            ? $documentTemplates
            : collect();

        $activeTemplate = $defaultTemplate ?? null;

        $previewRoute = route('CreditNotes.print', $credit->id) . '?' . http_build_query(
            array_filter(['template' => $activeTemplate?->id]) + ['embed' => 1],
        );

        /* حالتان للإلغاء لا واحدة: `getStatusTextAttribute()` تعرّف
           `5` ملغيّاً، بينما `cancel()` في المتحكّم يكتب `0`. فصفوف
           القاعدة فيها الاثنان، وشاشة الفهرس تعدّهما ملغيَّين — وهذه
           الشاشة على قياسها. */
        $statusTones = [
            0 => ['danger', 'x-circle', __('sales_credit_note.status.cancelled')],
            1 => ['secondary', 'edit-3', __('sales_credit_note.status.draft')],
            2 => ['warning', 'clock', __('sales_credit_note.status.pending')],
            3 => ['success', 'check-circle', __('sales_credit_note.status.approved')],
            4 => ['info', 'file-text', __('sales_credit_note.status.converted')],
            5 => ['danger', 'x-circle', __('sales_credit_note.status.cancelled')],
        ];

        [$stateTone, $stateIcon, $stateLabel] =
            $statusTones[(int) $credit->status] ?? ['dark', 'help-circle', __('sales_documents.ui.unknown')];

        $isCancelled = in_array((int) $credit->status, [0, 5], true);
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('CreditNotes.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_credit_note.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_credit_note.show.title', ['code' => $code]),
            'icon' => 'file-minus',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_credit_note.plural') => route('CreditNotes.index'),
                $code => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{-- حالة المستند لدى هيئة الزكاة والضريبة والجمارك وزرّ إرساله --}}
        @include('sales::partials.zatca-status', ['zatcaType' => 'credit_note', 'zatcaId' => $credit->id])

        {{-- ══════════ إرشادُ الشاشة ══════════
             مطويّةٌ افتراضياً: من فتح مستنداً بعينه جاء لبياناته لا للشرح --}}
@include('sales::components.guide', [
            'key' => 'sales-credit-notes.show',
            'title' => __('sales_credit_note.guide.show.title'),
            'lead' => __('sales_credit_note.guide.show.lead'),
            'open' => false,
            'blocks' => [
                __('sales_credit_note.guide.show_actions.title') => [
                    __('sales_credit_note.guide.show_actions.a'),
                    __('sales_credit_note.guide.show_actions.b'),
                    __('sales_credit_note.guide.show_actions.c'),
                ],
                __('sales_credit_note.guide.money_flow.title') => [
                    __('sales_credit_note.guide.money_flow.a'),
                    __('sales_credit_note.guide.money_flow.b'),
                    __('sales_credit_note.guide.money_flow.c'),
                ],
                __('sales_credit_note.guide.noedit.title') => [
                    __('sales_credit_note.guide.noedit.a'),
                    __('sales_credit_note.guide.noedit.b'),
                ],
            ],
        ])

        {{-- ══════════ هوية المستند وإجمالياته ══════════ --}}
        <div class="sl-doc-card sl-doc-card--summary">
            <div class="sl-doc-card__body">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="sl-facts">
                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.source_invoice') }}</span>
                                <span class="sl-fact__value">
                                    @if ($credit->invoice)
                                        @permission('sales.invoices.view')
                                            <a href="{{ route('invoices.show', $credit->invoice->id) }}" class="sl-code">
                                                {{ $credit->invoice->code ?: $credit->invoice->id }}
                                            </a>
                                        @else
                                            <span class="sl-code">{{ $credit->invoice->code ?: $credit->invoice->id }}</span>
                                        @endpermission
                                    @else
                                        {{ __('sales_credit_note.no_source') }}
                                    @endif
                                </span>
                            </div>

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.credit_date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($credit->credit_date ?: $credit->created_at) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.release_date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $credit->release_date
                                        ? LanguageSettings::formatDate($credit->release_date)
                                        : __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.sales_person') }}</span>
                                <span class="sl-fact__value">
                                    {{ $credit->employee->full_name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.added_by') }}</span>
                                <span class="sl-fact__value">
                                    {{ $credit->createdBy->name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.warehouse') }}</span>
                                <span class="sl-fact__value">
                                    {{ $credit->storeHouse->name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.treasury') }}</span>
                                <span class="sl-fact__value">
                                    {{ $credit->treasury->name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_credit_note.show.currency') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $credit->currency ?: CurrencySettings::code() }}
                                </span>
                            </div>
                        </div>

                        <div class="sl-tags mt-2">
                            <span class="badge badge-light-{{ $stateTone }}">
                                <i class="feather icon-{{ $stateIcon }}"></i> {{ $stateLabel }}
                            </span>

                            @if ($isTaxed)
                                <span class="badge badge-light-primary">
                                    <i class="feather icon-percent"></i> {{ __('sales_credit_note.show.taxed') }}
                                </span>
                            @endif

                            @if ($journalEntry)
                                <a href="{{ route('journal.show', $journalEntry->id) }}"
                                   class="badge badge-light-secondary">
                                    <i class="feather icon-book-open"></i>
                                    {{ __('sales_credit_note.show.journal_entry') }}
                                </a>
                            @endif
                        </div>

                        <p class="sl-help mt-2">{{ __('sales_credit_note.show.currency_frozen') }}</p>
                    </div>

                    <div class="col-lg-5">
                        {{-- الصندوق المشترك: يرسم السطر متى كان لقيمته وجود
                             ويسكت عنه متى كانت صفراً — نفس شرط شاشة الإنشاء
                             ونفس شرط المطبوعة --}}
                        @include('sales::components.totals', [
                            'totals' => [
                                'subtotal' => (float) ($credit->subtotal ?? 0),
                                'discount' => (float) ($credit->total_discount ?? 0),
                                'shipping' => (float) ($credit->shipping_cost ?? 0),
                                'tax' => (float) ($credit->total_tax ?? 0),
                                'grand_total' => (float) ($credit->grand_total ?? 0),
                            ],
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
                            <i class="feather icon-printer"></i> {{ __('sales_credit_note.show.documents') }}
                        </button>

                        <div class="dropdown-menu">
                            @permission('sales.credit_notes.print')
                                @if ($templates->isNotEmpty())
                                    <h6 class="dropdown-header">{{ __('sales_credit_note.show.from_settings') }}</h6>

                                    {{-- المسار `CreditNotes.print` يأخذ المعرّف وحده،
                                         والقالب عبر `?template=` كما تقرؤه
                                         `renderSalesTemplate()` --}}
                                    @foreach ($templates as $template)
                                        <a class="dropdown-item"
                                           href="{{ route('CreditNotes.print', $credit->id) }}?template={{ $template->id }}"
                                           target="_blank" rel="noopener">
                                            <i class="feather icon-file"></i> {{ $template->name }}
                                            @if ($template->is_default)
                                                <i class="feather icon-star text-warning"></i>
                                            @endif
                                        </a>
                                    @endforeach

                                    <div class="dropdown-divider"></div>
                                @else
                                    <h6 class="dropdown-header">{{ __('sales_credit_note.show.no_template') }}</h6>
                                @endif

                                <a class="dropdown-item"
                                   href="{{ route('CreditNotes.print', $credit->id) }}{{ $activeTemplate ? '?template=' . $activeTemplate->id : '' }}"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-printer"></i> {{ __('sales_credit_note.show.print') }}
                                </a>

                                {{-- القالب المدمج يبقى متاحاً صراحةً: منشأةٌ لم
                                     تصمّم قالباً بعد يجب أن تطبع إشعارها --}}
                                <div class="dropdown-divider"></div>
                                {{-- `template=builtin` قيمةٌ غير رقمية، و
                                     `renderSalesTemplate()` تعدّ ذلك طلباً
                                     صريحاً للقالب المدمج --}}
                                <a class="dropdown-item"
                                   href="{{ route('CreditNotes.print', $credit->id) }}?template=builtin"
                                   target="_blank" rel="noopener">
                                    <i class="feather icon-file-text"></i> {{ __('sales_credit_note.show.builtin_template') }}
                                </a>
                            @endpermission
                        </div>
                    </div>

                    {{-- كان الزرُّ يختفي كلّما خلت بطاقة العميل من بريد،
                         ويرسل رابطاً لا الإشعار. صار نافذةً تقبل بريداً
                         لهذه الرسالة وحدها وترفق الإشعار بقالبه --}}
                    @permission('sales.credit_notes.send')
                        @include('sales::partials.send-email', [
                            'mailId' => 'credit-' . $credit->id,
                            'mailRoute' => route('CreditNotes.send', $credit->id),
                            'mailNumber' => $code,
                            'mailEmail' => $credit->client?->email,
                            'mailClientName' => $clientName,
                            'mailTemplates' => $templates,
                            'mailDefaultTemplate' => $activeTemplate,
                        ])
                    @endpermission

                    @if (! empty($credit->client?->phone))
                        <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                           href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $credit->client->phone) }}?text={{ urlencode(
                               __('sales_credit_note.show.whatsapp_body', [
                                   'client' => $clientName,
                                   'number' => $code,
                                   'link' => route('CreditNotes.print', $credit->id),
                                   'business' => \App\Support\BusinessInfo::name(),
                               ]),
                           ) }}">
                            <i class="feather icon-message-circle text-success"></i>
                            {{ __('sales_credit_note.show.whatsapp') }}
                        </a>
                    @endif

                    @if ($credit->invoice)
                        @permission('sales.invoices.view')
                            <a href="{{ route('invoices.show', $credit->invoice->id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-file-text"></i> {{ __('sales_credit_note.source_invoice') }}
                            </a>
                        @endpermission
                    @endif

                    {{-- الإلغاء: نموذجٌ يُرسَل بعد تأكيد. كان المسار موجوداً
                         بلا زرٍّ يصل إليه --}}
                    @unless ($isCancelled)
                        {{-- sales.credit_notes.cancel — الصلاحية نفسها التي يتحقّق منها
                             CreditNotificationController::cancel() لا صلاحيات التعديل/الحذف القديمة --}}
                        @permission('sales.credit_notes.cancel')
                            <form action="{{ route('CreditNotes.cancel', $credit->id) }}" method="POST"
                                  id="cancelForm" class="sl-toolbar sl-toolbar--end">
                                @csrf
                                <button type="button" class="btn btn-outline-danger" onclick="confirmCancel()">
                                    <i class="feather icon-x-circle"></i> {{ __('sales_credit_note.actions.cancel') }}
                                </button>
                            </form>
                        @endpermission
                    @endunless
                </div>

                @unless ($isCancelled)
                    <p class="sl-help mt-2">{{ __('sales_credit_note.cancel_hint') }}</p>
                @endunless
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['credit-preview', 'eye', __('sales_credit_note.show.tab_preview'), true],
                            ['credit-items', 'package', __('sales_credit_note.show.tab_items'), false],
                            ['credit-settlement', 'layers', __('sales_credit_note.settlement.show_title'), false],
                            ['credit-taxes', 'percent', __('sales_credit_note.show.tab_taxes'), false],
                            ['credit-activity', 'clock', __('sales_credit_note.show.tab_activity'), false],
                        ];
                    @endphp

                    @foreach ($tabs as [$tabId, $icon, $label, $active])
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" data-toggle="tab"
                               data-bs-toggle="tab" href="#{{ $tabId }}" role="tab">
                                <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="tab-content">

                {{-- معاينة المستند: مسار الطباعة نفسه داخل إطار — لا نسخةٌ
                     ثانية من القالب مصغَّرةً بـ`transform: scale(0.8)` --}}
                <div class="tab-pane active" id="credit-preview" role="tabpanel">
                    <iframe src="{{ $previewRoute }}" class="sl-doc-frame" frameborder="0"
                            title="{{ __('sales_credit_note.show.tab_preview') }}"></iframe>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="credit-items" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:46px">{{ __('sales_documents.items.row') }}</th>
                                    <th>{{ __('sales_documents.items.item') }}</th>
                                    <th class="num" style="width:110px">{{ __('sales_documents.items.quantity') }}</th>
                                    <th class="num" style="width:130px">{{ __('sales_documents.items.unit_price') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_documents.items.discount') }}</th>
                                    <th class="num" style="width:120px">{{ __('sales_documents.items.tax_1') }}</th>
                                    <th class="num" style="width:140px">{{ __('sales_documents.items.total') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($credit->items as $index => $item)
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
                                                'title' => __('sales_credit_note.show.no_items'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ما سدّده الإشعار من ديون العميل — `ClientDebtSettlement` --}}
                <div class="tab-pane" id="credit-settlement" role="tabpanel">
                    @php
                        $settlementRows = $settlements ?? collect();
                        $settledTotal = (float) $settlementRows->sum('amount');
                        $surplus = round((float) $credit->grand_total - $settledTotal, 2);
                    @endphp

                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_credit_note.settlement.item') }}</th>
                                    <th style="width:140px">{{ __('sales_credit_note.settlement.date') }}</th>
                                    <th class="num" style="width:160px">{{ __('sales_credit_note.settlement.paid') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($settlementRows as $row)
                                    <tr>
                                        <td>
                                            @if ($row->invoice)
                                                <a href="{{ route('invoices.show', $row->invoice->id) }}">
                                                    {{ __('sales_credit_note.settlement.invoice', ['code' => $row->invoice->code ?? $row->invoice->id]) }}
                                                </a>
                                            @else
                                                {{ __('sales_credit_note.settlement.previous_balance') }}
                                            @endif
                                        </td>
                                        <td class="sl-num">{{ optional($row->invoice?->invoice_date)->format('Y-m-d') ?? '—' }}</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($row->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'layers',
                                                'title' => __('sales_credit_note.settlement.none_saved'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse

                                @if ($settlementRows->isNotEmpty())
                                    <tr>
                                        <td colspan="2"><strong>{{ __('sales_credit_note.settlement.total') }}</strong></td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($settledTotal) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($settlementRows->isNotEmpty() && $surplus > 0)
                        <div class="sl-doc-card__body pt-0">
                            {{-- `data-hint-keep`: مبلغ الفائض يُقرأ هنا — لا يُطوى في علامة «!» --}}
                            <p class="sl-help mb-0" data-hint-keep>
                                {{ __('sales_credit_note.settlement.surplus', ['amount' => CurrencySettings::format($surplus)]) }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- الضرائب --}}
                <div class="tab-pane" id="credit-taxes" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_credit_note.show.tax_name') }}</th>
                                    <th class="num" style="width:140px">{{ __('sales_credit_note.show.tax_rate') }}</th>
                                    <th class="num" style="width:160px">{{ __('sales_credit_note.show.tax_amount') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($taxRows as $tax)
                                    <tr>
                                        <td>{{ $tax->name }}</td>
                                        <td class="num sl-num">{{ CurrencySettings::number($tax->rate ?? 0, 2) }}%</td>
                                        <td class="num sl-line-total">{{ CurrencySettings::format($tax->value ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            @include('sales::components.empty-state', [
                                                'icon' => 'percent',
                                                'title' => __('sales_credit_note.show.no_taxes'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="credit-activity" role="tabpanel">
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
                                                <strong>{{ $log->user->name ?? __('sales_credit_note.show.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('sales_credit_note.show.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => __('sales_credit_note.show.no_activity'),
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
        /* الإلغاء يعكس القيد ويُرجع الكمّيات — فعلٌ لا يُتراجَع عنه، فيُسأل
           عنه قبل الإرسال. */
        function confirmCancel() {
            const form = document.getElementById('cancelForm');
            if (!form) return;

            if (typeof Swal === 'undefined') {
                if (confirm(@json(__('sales_credit_note.show.cancel_text')))) form.submit();
                return;
            }

            Swal.fire({
                title: @json(__('sales_credit_note.show.cancel_title')),
                text: @json(__('sales_credit_note.show.cancel_text')),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: @json(__('sales_credit_note.show.cancel_confirm')),
                cancelButtonText: @json(__('sales_credit_note.show.cancel_dismiss')),
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        }
    </script>
@endsection
