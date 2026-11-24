{{--
    عرض فاتورة مرتجعة.

    أُعيد بناء الشاشة على قياس شاشة عرض الفاتورة (`sales::invoices.show`)
    بنظام `sl-doc-*`: ترويسة ← بطاقة هوية المستند وإجمالياته ← شريط
    إجراءات واحد ← تبويبات التفاصيل.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات» (كل مبلغ في الشاشة)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • القوالب         ← «قوالب المبيعات» (النوع `sales_return`)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *تبويبٌ لا لوحة له*: زرّ «تفاصيل المرتجع» يشير إلى
        `#return-details`، واللوحة الوحيدة معرّفها `invoice` — فالضغط
        عليه يُخفي كل شيء ولا يُظهر شيئاً.
      • *شاشةٌ بلا بيانات*: لا بنود ولا إجماليات ولا ضرائب ولا فاتورة
        أصلية ولا قيد محاسبي؛ إطار طباعةٍ وسجلُّ نشاطٍ فحسب.
      • *`<style>` من ٢٧٠ سطراً* بألوان مثبَّتة لا تتبع «مظهر النظام»،
        وقد صار في نظام `sl-*`.
      • *زرّ طباعةٍ بقالبٍ واحد*: القوالب المصمَّمة في «قوالب المبيعات»
        لا تظهر رغم أنّ المسار يقبل معرّف قالب.
      • *`.print-button` تنادي `window.print()`* على صفحة العرض لا على
        المستند: تطبع الشاشة بقائمتها الجانبية.
--}}

@extends('sales::master')

@section('title', __('sales_return.show.title', ['code' => $return_invoice->code ?: $return_invoice->id]))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $clientName = $return_invoice->client
            ? ($return_invoice->client->trade_name
                ?: trim(($return_invoice->client->first_name ?? '') . ' ' . ($return_invoice->client->last_name ?? '')))
            : __('sales_documents.ui.unknown');

        $code = $return_invoice->code ?: $return_invoice->id;

        $taxRows = $TaxsInvoice ?? collect();
        $isTaxed = ($return_invoice->tax_total ?? 0) > 0 || $taxRows->count() > 0;

        $templates = (isset($documentTemplates) && $documentTemplates instanceof \Illuminate\Support\Collection)
            ? $documentTemplates
            : collect();

        /* المعاينة والطباعة تتبعان **القالب الافتراضي** المضبوط في «قوالب
           المستندات». الرابط بلا معرّف قالبٍ يعدّه `renderSalesTemplate()`
           طلباً للقالب المدمج، فلا يظهر قالب المستخدم إلّا باختياره يدوياً. */
        $activeTemplate = $defaultTemplate ?? null;

        $previewRoute = route('ReturnIInvoices.print', array_filter([$id, $activeTemplate?->id]) + ['embed' => true]);

        // الفاتورة الأصلية: `reference_number` في المرتجع = معرّف الفاتورة
        $sourceInvoice = $return_invoice->referenceInvoice;
    @endphp

    <div class="content-body sl-scope sl-doc">

        @section('page-actions')
            <a href="{{ route('ReturnIInvoices.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_return.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_return.show.title', ['code' => $code]),
            'icon' => 'corner-up-left',
            'sub' => $clientName,
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_return.plural') => route('ReturnIInvoices.index'),
                $code => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{-- حالة المستند لدى هيئة الزكاة والضريبة والجمارك وزرّ إرساله --}}
        @include('sales::partials.zatca-status', ['zatcaType' => 'invoice', 'zatcaId' => $return_invoice->id])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-returns.show',
            'title' => __('sales_return.guide.show.title'),
            'lead' => __('sales_return.guide.lead_show'),
            'open' => false,
            'blocks' => [
                __('sales_return.guide.effects.title') => [
                    __('sales_return.guide.effects.a'),
                    __('sales_return.guide.effects.b'),
                    __('sales_return.guide.effects.c'),
                    __('sales_return.guide.effects.d'),
                ],
                __('sales_return.guide.money_flow.title') => [
                    __('sales_return.guide.money_flow.a'),
                    __('sales_return.guide.money_flow.b'),
                    __('sales_return.guide.money_flow.c'),
                ],
                __('sales_return.guide.noedit.title') => [
                    __('sales_return.guide.noedit.a'),
                    __('sales_return.guide.noedit.b'),
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
                                <span class="sl-fact__label">{{ __('sales_return.show.number') }}</span>
                                <span class="sl-fact__value"><span class="sl-code">{{ $code }}</span></span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_return.show.source_invoice') }}</span>
                                <span class="sl-fact__value">
                                    @if ($sourceInvoice)
                                        <a href="{{ route('invoices.show', $sourceInvoice->id) }}" class="sl-code">
                                            {{ $sourceInvoice->code ?: $sourceInvoice->id }}
                                        </a>
                                    @else
                                        {{ __('sales_documents.ui.dash') }}
                                    @endif
                                </span>
                            </div>

                            <div class="sl-fact sl-fact--wide">
                                <span class="sl-fact__label">{{ __('sales_return.show.client') }}</span>
                                <span class="sl-fact__value">{{ $clientName }}</span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_return.show.date') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ LanguageSettings::formatDate($return_invoice->invoice_date ?: $return_invoice->created_at) }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_return.show.sales_person') }}</span>
                                <span class="sl-fact__value">
                                    {{ $return_invoice->employee->full_name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_return.show.added_by') }}</span>
                                <span class="sl-fact__value">
                                    {{ $return_invoice->createdByUser->name ?? __('sales_documents.ui.dash') }}
                                </span>
                            </div>

                            <div class="sl-fact">
                                <span class="sl-fact__label">{{ __('sales_return.show.currency') }}</span>
                                <span class="sl-fact__value sl-num">
                                    {{ $return_invoice->currency ?: CurrencySettings::code() }}
                                </span>
                            </div>
                        </div>

                        <div class="sl-tags mt-2">
                            <span class="badge badge-light-danger">
                                <i class="feather icon-corner-up-left"></i> {{ __('sales_return.singular') }}
                            </span>

                            @if ($isTaxed)
                                <span class="badge badge-light-primary">
                                    <i class="feather icon-percent"></i> {{ __('sales_return.show.taxed') }}
                                </span>
                            @endif

                            @if ($return_invoice->journalEntry && $return_invoice->journalEntry->id)
                                <a href="{{ route('journal.show', $return_invoice->journalEntry->id) }}"
                                   class="badge badge-light-secondary">
                                    <i class="feather icon-book-open"></i> {{ __('sales_return.show.journal_entry') }}
                                </a>
                            @endif
                        </div>

                        <p class="sl-help mt-2">{{ __('sales_return.show.currency_frozen') }}</p>
                    </div>

                    <div class="col-lg-5">
                        {{-- الصندوق المشترك: يرسم السطر متى كان لقيمته وجود
                             ويسكت عنه متى كانت صفراً — نفس شرط شاشة الإنشاء
                             ونفس شرط المطبوعة --}}
                        @include('sales::components.totals', [
                            'totals' => [
                                'subtotal' => (float) ($return_invoice->subtotal ?? $return_invoice->total ?? 0),
                                'discount' => (float) ($return_invoice->discount_amount ?? 0),
                                'shipping' => (float) ($return_invoice->shipping_cost ?? 0),
                                'tax' => (float) ($return_invoice->tax_total ?? 0),
                                'grand_total' => (float) ($return_invoice->grand_total ?? $return_invoice->total ?? 0),
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
                            <i class="feather icon-printer"></i> {{ __('sales_return.show.documents') }}
                        </button>

                        <div class="dropdown-menu">
                            @if ($templates->isNotEmpty())
                                <h6 class="dropdown-header">{{ __('sales_return.show.from_settings') }}</h6>

                                @foreach ($templates as $template)
                                    <a class="dropdown-item"
                                       href="{{ route('ReturnIInvoices.print', [$id, $template->id]) }}"
                                       target="_blank" rel="noopener">
                                        <i class="feather icon-file"></i> {{ $template->name }}
                                        @if ($template->is_default)
                                            <i class="feather icon-star text-warning"></i>
                                        @endif
                                    </a>
                                @endforeach

                                <div class="dropdown-divider"></div>
                            @else
                                <h6 class="dropdown-header">{{ __('sales_return.show.no_template') }}</h6>
                            @endif

                            <a class="dropdown-item"
                               href="{{ route('ReturnIInvoices.print', array_filter([$id, $activeTemplate?->id])) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-printer"></i> {{ __('sales_return.show.print') }}
                            </a>

                            {{-- القالب المدمج يبقى متاحاً صراحةً: منشأةٌ لم
                                 تصمّم قالباً بعد يجب أن تطبع مرتجعها --}}
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('ReturnIInvoices.print', $id) }}"
                               target="_blank" rel="noopener">
                                <i class="feather icon-file-text"></i> {{ __('sales_return.show.builtin_template') }}
                            </a>
                        </div>
                    </div>

                    {{-- كان رابطاً GET يشترط بريداً محفوظاً في بطاقة العميل،
                         ويرسل رابطاً لا المستند. صار نافذةً تقبل بريداً
                         لهذه الرسالة وحدها وترفق المرتجع بقالبه --}}
                    @permission('sales.returns.send')
                        @include('sales::partials.send-email', [
                            'mailId' => 'return-' . $id,
                            'mailRoute' => route('ReturnIInvoices.send-email', $id),
                            'mailNumber' => $code,
                            'mailEmail' => $return_invoice->client?->email,
                            'mailClientName' => $clientName,
                            'mailTemplates' => $templates,
                            'mailDefaultTemplate' => $activeTemplate,
                        ])
                    @endpermission

                    @if (! empty($return_invoice->client?->phone))
                        <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                           href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $return_invoice->client->phone) }}?text={{ urlencode(
                               __('sales_return.show.whatsapp_body', [
                                   'client' => $clientName,
                                   'number' => $code,
                                   'link' => route('ReturnIInvoices.print', $id),
                                   'business' => \App\Support\BusinessInfo::name(),
                               ]),
                           ) }}">
                            <i class="feather icon-message-circle text-success"></i> {{ __('sales_return.show.whatsapp') }}
                        </a>
                    @endif

                    @if ($sourceInvoice)
                        <a href="{{ route('invoices.show', $sourceInvoice->id) }}" class="btn btn-outline-secondary">
                            <i class="feather icon-file-text"></i> {{ __('sales_return.show.source_invoice') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        <div class="sl-doc-card">
            <div class="sl-doc-card__head sl-doc-card__head--flush">
                <ul class="nav sl-doc-tabs w-100" role="tablist">
                    @php
                        $tabs = [
                            ['return-preview', 'eye', __('sales_return.show.tab_preview'), true],
                            ['return-items', 'package', __('sales_return.show.tab_items'), false],
                            ['return-taxes', 'percent', __('sales_return.show.tab_taxes'), false],
                            ['return-activity', 'clock', __('sales_return.show.tab_activity'), false],
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

                {{-- معاينة المستند: مسار الطباعة نفسه داخل إطار --}}
                <div class="tab-pane active" id="return-preview" role="tabpanel">
                    <iframe src="{{ $previewRoute }}" class="sl-doc-frame" frameborder="0"
                            title="{{ __('sales_return.show.tab_preview') }}"></iframe>
                </div>

                {{-- البنود --}}
                <div class="tab-pane" id="return-items" role="tabpanel">
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
                                @forelse ($return_invoice->items as $index => $item)
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
                                                'title' => __('sales_return.show.no_items'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- الضرائب --}}
                <div class="tab-pane" id="return-taxes" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table sl-doc-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_return.show.tax_name') }}</th>
                                    <th class="num" style="width:140px">{{ __('sales_return.show.tax_rate') }}</th>
                                    <th class="num" style="width:160px">{{ __('sales_return.show.tax_amount') }}</th>
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
                                                'title' => __('sales_return.show.no_taxes'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- سجلّ النشاط --}}
                <div class="tab-pane" id="return-activity" role="tabpanel">
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
                                                <strong>{{ $log->user->name ?? __('sales_return.show.unknown_user') }}</strong>
                                                {!! Str::markdown($log->description ?? '') !!}
                                                <span class="sl-media__sub">
                                                    {{ $log->user->branch->name ?? __('sales_return.show.no_branch') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            @include('sales::components.empty-state', [
                                'icon' => 'clock',
                                'title' => __('sales_return.show.no_activity'),
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
