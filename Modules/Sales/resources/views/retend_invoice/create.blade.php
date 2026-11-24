{{--
    إنشاء فاتورة مرتجعة.

    أُعيد بناء الشاشة على قياس «إنشاء فاتورة مبيعات»
    (`sales::invoices.create`) بنظام `sl-doc-*`: الترتيب نفسه — بيانات
    المستند ← البنود ← التفاصيل الإضافية ← الملاحظات وأزرار الحفظ —
    وجدول البنود نفسه ولوحة الإجماليات نفسها.

    ما تأتي به الإعدادات:
      • الرقم           ← «تسلسل أرقام المستندات» (القسم `return-invoice`)
      • العملة والكسور  ← «العملات»
      • نِسَب الضريبة    ← «إعدادات الضريبة»
      • طرق الدفع       ← «طرق الدفع» (ردّ المبلغ للعميل) والخزائن
      • الإقفال         ← «الفترات المحاسبية»: تُنبَّه الشاشة قبل الحفظ
      • السلوك          ← «إعدادات الفواتير» (`SalesInvoiceSetting`)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    **عقد الـDOM محفوظ.** `invoice-calculator.js` المشترك وسكربت الشاشة
    يعتمدان على `#items-table` و`.item-row` و`#add-row` و`#tax-rows`
    وأسماء الإجماليات و`#payment-fields` و`#return-amount-yes/no`
    و`#payment-status-*` و`confirmSubmit()`. أُعيد الشكل ولم تُمسّ.

    ما أُصلح:
      • *رقم المرتجع «سيتم إنشاؤه تلقائياً»* — نصٌّ في خانة قراءةٍ فقط،
        والمستخدم لا يعرف الرقم قبل الحفظ. صار يُعرَض فعلاً من «الترقيم
        المتسلسل»، وقابلاً للتعديل.
      • *«ريال» مكتوبة* في مُنتقي نوع الخصم، وصورةُ ريالٍ ثابتة في
        الإجماليات — كلتاهما من «العملات» الآن.
      • *`$salesSettings`* — مفتاحُ جلسةٍ لا يكتبه شيء في المشروع، تُقارَن
        به أسماءُ إعداداتٍ لا وجود لها. صار `SalesInvoiceSetting`.
      • *مبلغ الفاتورة الأصلية* كان `number_format(...) . ' ريال'`.
--}}

@extends('sales::master')

@section('title', __('sales_return.create_title'))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')
    @include('sales::partials.document-theme-po')

    @php
        use App\Models\SalesInvoiceSetting;
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        // الاسم ليس `$settings` عمداً: `@extends` يمرّر متغيّرات هذا
        // القالب كلَّها إلى `sales::master` ومنها إلى القائمة الجانبية،
        // وهي تنتظر `$settings` مصفوفةَ «شاشة التطبيقات»
        $invoiceRules = $invoiceSettings ?? SalesInvoiceSetting::current();

        $symbol = CurrencySettings::symbol();
        $methods = $paymentMethods ?? collect();
        $canEditDate = hasPermission('sales.invoices.edit_date');

        $returnReasons = [
            'damaged' => __('sales_return.reasons.damaged'),
            'wrong_item' => __('sales_return.reasons.wrong_item'),
            'customer_request' => __('sales_return.reasons.customer_request'),
            'quality_issue' => __('sales_return.reasons.quality_issue'),
            'other' => __('sales_return.reasons.other'),
        ];
    @endphp

    <div class="content-body sl-scope sl-doc sl-doc--po">

        @include('sales::components.page-head', [
            'title' => __('sales_return.create_title'),
            'icon' => 'corner-up-left',
            'sub' => __('sales_invoice.form.required_note'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_return.plural') => route('ReturnIInvoices.index'),
                ($invoice->code ?? '') => route('invoices.show', $invoice->id),
                __('sales_settings.ui.add') => null,
            ],
        ])

        {{-- ══════════ ما يُحفَظ به هذا المرتجع ══════════ --}}
        <div class="sl-doc-note">
            <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
            <div class="sl-doc-note__body">
                <strong>{{ __('sales_invoice.context.currency') }}:</strong>
                {{ CurrencySettings::name() }} ({{ CurrencySettings::code() }})
                &nbsp;·&nbsp;
                <strong>{{ __('sales_invoice.context.numbering') }}:</strong>
                <span class="sl-code">{{ $return_number ?? '—' }}</span>
                <span class="sl-help">{{ __('sales_invoice.context.numbering_hint') }}</span>
            </div>
        </div>

        @if (! empty($periodBlock))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-lock"></i></span>
                <div class="sl-doc-note__body">
                    <strong>{{ __('sales_invoice.context.period_blocked') }}</strong>
                    <span class="d-block">{{ $periodBlock }}</span>
                </div>
            </div>
        @endif

        @if (! empty($is_main_treasury))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-alert-triangle"></i></span>
                <div class="sl-doc-note__body">{{ __('sales_return.main_treasury_note') }}</div>
            </div>
        @endif

        @include('sales::components.form-errors')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-returns.create',
            'title' => __('sales_return.guide.create.title'),
            'lead' => __('sales_return.guide.lead_create'),
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
                __('sales_return.guide.statuses.title') => [
                    __('sales_return.guide.statuses.a'),
                    __('sales_return.guide.statuses.b'),
                    __('sales_return.guide.statuses.c'),
                ],
                __('sales_return.guide.blockers.title') => [
                    __('sales_return.guide.blockers.a'),
                    __('sales_return.guide.blockers.b'),
                    __('sales_return.guide.blockers.c'),
                ],
                __('sales_return.guide.noedit.title') => [
                    __('sales_return.guide.noedit.a'),
                    __('sales_return.guide.noedit.b'),
                ],
            ],
            'facts' => [
                __('sales_return.guide.facts.a'),
                __('sales_return.guide.facts.b'),
                __('sales_return.guide.facts.c'),
            ],
        ])

        <form id="return-invoice-form" action="{{ route('ReturnIInvoices.store') }}" method="post"
              enctype="multipart/form-data" onsubmit="return confirmSubmit(event)">
            @csrf

            <input type="hidden" name="current_latitude" id="current_latitude" value="0">
            <input type="hidden" name="current_longitude" id="current_longitude" value="0">
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

            {{-- ══════════ الفاتورة الأصلية ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-file-text"></i> {{ __('sales_return.filters.source_invoice') }}
                    </h6>

                    <a href="{{ route('invoices.show', $invoice->id) }}" class="sl-hint" target="_blank" rel="noopener">
                        <i class="feather icon-external-link"></i> {{ __('sales_invoice.actions.show') }}
                    </a>
                </div>

                <div class="sl-doc-card__body">
                    <div class="sl-facts">
                        <div class="sl-fact">
                            <span class="sl-fact__label">{{ __('sales_invoice.table.number') }}</span>
                            <span class="sl-fact__value"><span class="sl-code">{{ $invoice->code ?? '—' }}</span></span>
                        </div>

                        <div class="sl-fact">
                            <span class="sl-fact__label">{{ __('sales_invoice.table.date') }}</span>
                            <span class="sl-fact__value sl-num">
                                {{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}
                            </span>
                        </div>

                        <div class="sl-fact">
                            <span class="sl-fact__label">{{ __('sales_invoice.table.total') }}</span>
                            <span class="sl-fact__value sl-num">
                                {{ CurrencySettings::format($invoice->grand_total ?? 0) }}
                            </span>
                        </div>

                        <div class="sl-fact">
                            <span class="sl-fact__label">{{ __('sales_invoice.filters.payment_status') }}</span>
                            <span class="sl-fact__value">
                                <i class="feather icon-info" id="payment-status-icon"></i>
                                <span id="payment-status-text"></span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- رسالة حالة الدفع — يملؤها سكربت الشاشة --}}
                <div id="payment-status-alert" class="sl-doc-card__foot" style="display:none">
                    <div id="alert-container" class="w-100"></div>
                </div>
            </div>

            {{-- ══════════ بيانات المرتجع ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-corner-up-left"></i> {{ __('sales_return.form.document') }}
                    </h6>
                    <span class="sl-hint">{{ __('sales_invoice.form.required_note') }}</span>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="clientSelect">
                                    {{ __('sales_invoice.client') }} <span class="required">*</span>
                                </label>
                                {{-- العميل يتبع الفاتورة الأصلية فلا يُختار.
                                     المُعرِّف `clientSelect` عقدٌ مع سكربت الشاشة --}}
                                <input type="text" class="form-control" id="clientSelect" readonly
                                       value="{{ $invoice->client->trade_name ?? __('sales_invoice.table.unknown_client') }}{{ $invoice->client->code ? ' - '.$invoice->client->code : '' }}">
                                <input type="hidden" name="client_id" value="{{ $invoice->client_id }}">
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="code">{{ __('sales_return.filters.number') }}</label>
                                {{-- كان نصّاً «سيتم إنشاؤه تلقائياً» في خانة
                                     قراءةٍ فقط، فلا يعرف المستخدم رقمه --}}
                                <input type="text" class="form-control" id="code" name="code"
                                       value="{{ old('code', $return_number ?? '') }}" readonly>
                                <small class="sl-help">{{ __('sales_invoice.context.numbering_locked') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="invoice_date">
                                    {{ __('sales_return.form.date') }} <span class="required">*</span>
                                </label>
                                <input class="form-control" type="date" id="invoice_date" name="invoice_date" required
                                       value="{{ old('invoice_date', date('Y-m-d')) }}"
                                       {{ $canEditDate ? '' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="issue_date">{{ __('sales_invoice.issue_date') }}</label>
                                <input class="form-control" type="date" id="issue_date" name="issue_date"
                                       value="{{ old('issue_date', date('Y-m-d')) }}"
                                       {{ $canEditDate ? '' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="storeHouseSelect">
                                    {{ __('sales_return.form.warehouse') }} <span class="required">*</span>
                                </label>
                                <select class="form-control select2" id="storeHouseSelect" name="store_house_id" required>
                                    <option value="">{{ __('sales_invoice.ui.warehouse') }}</option>
                                    @foreach ($store_houses as $sh)
                                        <option value="{{ $sh->id }}" @selected($sh->id == ($selectedStoreHouseId ?? ''))>
                                            {{ $sh->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="return_reason">{{ __('sales_return.form.reason') }}</label>
                                <select class="form-control" id="return_reason" name="return_reason">
                                    <option value="">{{ __('sales_invoice.none') }}</option>
                                    @foreach ($returnReasons as $value => $label)
                                        <option value="{{ $value }}" @selected(old('return_reason') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ ردّ المبلغ ══════════

                 الخيار الافتراضي يتبع **الفاتورة** لا إعدادَ «الفواتير مدفوعة
                 افتراضياً»: فاتورةٌ قُبض منها شيء ← «ردّ المبلغ»، وفاتورةٌ
                 آجلة لم يُقبض منها شيء ← «دون ردّ» (ولا مبلغَ يُردّ أصلاً،
                 فالمرتجع يُنقص مديونية العميل وحدها). كان الإعداد يختار
                 «ردّ المبلغ» لفاتورةٍ آجلة. --}}
            @php
                $refundByDefault = old('return_amount_option') !== null
                    ? old('return_amount_option') === 'return'
                    : (float) ($invoice->paid_amount ?? 0) > 0 && in_array((int) $invoice->payment_status, [1, 2], true);
            @endphp
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-credit-card"></i> {{ __('sales_return.form.refund') }}
                    </h6>
                    <span class="sl-hint">{{ __('sales_return.form.refund_hint') }}</span>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input payment-toggle" type="radio" name="return_amount_option"
                                       value="return" id="return-amount-yes"
                                       @checked($refundByDefault) @disabled(! $refundByDefault)>
                                <label class="form-check-label" for="return-amount-yes">
                                    <strong class="text-success">{{ __('sales_return.form.refund_yes') }}</strong>
                                    <small class="sl-help">{{ __('sales_return.form.refund_yes_hint') }}</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input payment-toggle" type="radio" name="return_amount_option"
                                       value="not_return" id="return-amount-no"
                                       @checked(! $refundByDefault)>
                                <label class="form-check-label" for="return-amount-no">
                                    <strong class="text-danger">{{ __('sales_return.form.refund_no') }}</strong>
                                    <small class="sl-help">{{ __('sales_return.form.refund_no_hint') }}</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- حقول الردّ — يُظهرها سكربت الشاشة مع الخيار الأوّل --}}
                    <div id="payment-fields" class="row g-3 mt-2" @style(['display: none' => ! $refundByDefault])>
                        {{-- وسيلة الإرجاع من شاشة «طرق الدفع».

                             كانت أربعة خيارات مكتوبة في القالب (`cash`,
                             `credit_card`, `bank_transfer`, `account_credit`)
                             لا صلة لها بما يضبطه المستخدم. والعمود
                             `invoices.payment_method` عددٌ صحيح، فالنصّ
                             `'cash'` كان يُحفَظ صفراً — أي «بلا وسيلة». --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_method">{{ __('sales_return.form.refund_method') }}</label>
                                <select class="form-control" id="payment_method" name="payment_method">
                                    <option value="">{{ __('sales_credit_note.form.select_payment_method') }}</option>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->id }}"
                                            @selected(old('payment_method', $methods->firstWhere('is_default', true)?->id) == $method->id)>
                                            {{ LanguageSettings::isRtl() ? $method->name : ($method->name_en ?: $method->name) }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($methods->isEmpty())
                                    <small class="sl-help">{{ __('sales_settings.context.payments_none') }}</small>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="treasury_id">{{ __('sales_credit_note.form.treasury') }}</label>
                                <select class="form-control" id="treasury_id" name="treasury_id">
                                    <option value="">{{ __('sales_credit_note.form.select_treasury') }}</option>
                                    @foreach ($treasuries ?? [] as $treasury)
                                        {{-- خزينةُ الموظف مختارةً سلفاً: القائمة مقصورةٌ
                                             على ما يجوز له السحبُ منه، وفتحُها على
                                             «اختر خزينة» كان يُقرأ «خزينتي لا تظهر» --}}
                                        <option value="{{ $treasury->id }}"
                                            @selected(old('treasury_id', $selectedTreasuryId ?? null) == $treasury->id)>
                                            {{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- الاسم `payment_reference` لا `reference_number`:
                             الأخير هو عمود الفاتورة الأصلية، ويكتبه المتحكّم --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_reference">{{ __('sales_return.form.refund_reference') }}</label>
                                <input type="text" class="form-control" id="payment_reference" name="payment_reference"
                                       placeholder="{{ __('sales_return.form.refund_reference_hint') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ البنود ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-package"></i> {{ __('sales_return.form.items') }}
                    </h6>
                    <span class="sl-hint">
                        <i class="feather icon-dollar-sign"></i>
                        {{ CurrencySettings::name() }} ({{ $symbol }})
                    </span>
                </div>

                <div class="sl-doc-card__body pb-0">
                    <div class="sl-doc-note">
                        <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
                        <div class="sl-doc-note__body">{{ __('sales_return.form.items_hint') }}</div>
                    </div>
                </div>

                <input type="hidden" id="products-data" value="{{ json_encode($items) }}">

                {{-- الضريبة على كل الأصناف — تظهر فقط حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة --}}
                @include('partials.comprehensive-tax-field')

                <div class="table-responsive">
                    <table class="table sl-doc-table mb-0" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:46px">{{ __('sales_invoice.form.row') }}</th>
                                <th style="min-width:200px">
                                    {{ __('sales_invoice.item') }} <span class="required">*</span>
                                </th>
                                <th style="min-width:150px">{{ __('sales_invoice.description') }}</th>
                                <th style="width:130px">
                                    {{ __('sales_return.form.return_quantity') }} <span class="required">*</span>
                                </th>
                                <th style="width:130px">{{ __('sales_invoice.unit_price') }}</th>
                                {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                                <th style="width:150px">{{ __('sales_invoice.tax1') }}</th>
                                <th class="num" style="width:120px">{{ __('sales_invoice.total') }}</th>
                                <th style="width:46px"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($invoice->items as $index => $item)
                                @php
                                    $itemTotal = ($item->unit_price ?? 0) * ($item->quantity ?? 0) - ($item->discount ?? 0);
                                @endphp

                                <tr class="item-row">
                                    <td class="num"><span class="sl-row__index">{{ $index + 1 }}</span></td>

                                    <td>
                                        <div class="sl-select-add">
                                            <select name="items[{{ $index }}][product_id]" class="form-control product-select" required>
                                                <option value="">{{ __('sales_invoice.ui.select_item') }}</option>
                                                @foreach ($items as $product)
                                                    <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}"
                                                        @selected($product->id == $item->product_id)>
                                                        {{ $product->name }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            @permission('stock.products.edit')
                                                <a href="{{ route('products.create') }}" target="_blank" rel="noopener"
                                                   class="btn btn-outline-primary sl-select-add__btn"
                                                   title="{{ __('sales_invoice.actions.quick_add_product') }}">
                                                    <i class="feather icon-plus"></i>
                                                </a>
                                            @endpermission
                                        </div>

                                        @if ($item->unit_name)
                                            <small class="sl-help">
                                                <i class="feather icon-box"></i> {{ $item->unit_name }}
                                            </small>
                                        @endif

                                        <input type="hidden" name="items[{{ $index }}][unit_name]" value="{{ $item->unit_name ?? '' }}">
                                        <input type="hidden" name="items[{{ $index }}][original_quantity]" value="{{ $item->quantity ?? 0 }}">
                                        <input type="hidden" name="items[{{ $index }}][original_price]" value="{{ $item->unit_price ?? 0 }}">
                                        <input type="hidden" name="items[{{ $index }}][product_unit_id]" value="{{ $item->product_unit_id ?? '' }}">
                                        <input type="hidden" name="items[{{ $index }}][store_house_id]" value="{{ $item->store_house_id ?? '' }}">
                                    </td>

                                    <td>
                                        <input type="text" name="items[{{ $index }}][description]"
                                               class="form-control item-description"
                                               value="{{ $item->description ?? '' }}"
                                               placeholder="{{ __('sales_invoice.description') }}">
                                    </td>

                                    {{-- الكميةُ كسريّةٌ كما حُفظت في الفاتورة.

                                         كانت `intval()` في القيمة والحدّ الأعلى، و`min="1"` بلا
                                         `step`: بندٌ بـ٢٫٥ كغ يظهر ٢، ولا يُرجَع منه نصفٌ ولا
                                         ١٫٥ — المتصفّح يردّ الرقم قبل الإرسال. والخادم يقرأ
                                         الكمية `floatval` أصلاً، فالمنعُ كان في الشاشة وحدها. --}}
                                    @php
                                        $originalQty = rtrim(rtrim(number_format((float) ($item->quantity ?? 0), 4, '.', ''), '0'), '.');
                                    @endphp
                                    <td>
                                        <input type="number" name="items[{{ $index }}][quantity]" class="form-control quantity"
                                               value="{{ $originalQty }}"
                                               min="0.0001" step="any" max="{{ $originalQty }}" required>
                                        <small class="remaining-info sl-help" style="display:none"></small>
                                        <small class="sl-help">
                                            {{ __('sales_return.form.original_quantity', ['n' => $originalQty]) }}
                                        </small>
                                    </td>

                                    <td>
                                        <input type="number" name="items[{{ $index }}][unit_price]" class="form-control price"
                                               value="{{ number_format($item->unit_price ?? 0, 2, '.', '') }}"
                                               step="0.01" required placeholder="0.00">
                                    </td>

                                    {{-- خصمُ البند أُزيل من الشاشة — والخصمُ الذي بِيع به البندُ
                                         يُحمَل مخفيّاً **نسبةً** من سطر الفاتورة الأصليّ لا مبلغاً:

                                           • لا يُحذف: الردُّ على العميل ما دفعه فعلاً، لا السعرُ
                                             قبل خصمه — وإلّا رُدَّ له مالٌ لم يدفعه.
                                           • نسبةٌ لا مبلغ: كان يُنقل المبلغُ كاملاً فإرجاعُ قطعةٍ
                                             من ثلاث يحسم عنها خصمَ الثلاث. وكان المستخدم يصحّحه
                                             بيده في العمود؛ ولا عمودَ الآن، فالنسبةُ تتناسب مع
                                             الكمية المُرجَعة بذاتها.

                                         `discount` و`discount_percentage` كلاهما النسبة: المتحكّم
                                         يقرأ الأوّل والحاسبةُ في الشاشة تقرأ الثاني. --}}
                                    @php
                                        $lineGross = (float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0);
                                        $linePercent = $lineGross > 0
                                            ? round(min(100, (float) ($item->discount ?? 0) / $lineGross * 100), 6)
                                            : 0;
                                    @endphp
                                    <input type="hidden" name="items[{{ $index }}][discount]" class="discount-amount" value="{{ $linePercent }}">
                                    <input type="hidden" name="items[{{ $index }}][discount_percentage]" class="discount-percentage" value="{{ $linePercent }}">
                                    <input type="hidden" name="items[{{ $index }}][discount_type]" class="discount-type"
                                           value="{{ $linePercent > 0 ? 'percentage' : 'amount' }}">

                                    <td>
                                        <select name="items[{{ $index }}][tax_1]" class="form-control tax-select"
                                                data-target="tax_1" onchange="updateHiddenInput(this)">
                                            <option value="">{{ __('sales_invoice.none') }}</option>
                                            @foreach ($taxs as $tax)
                                                <option value="{{ $tax->tax }}" data-id="{{ $tax->id }}"
                                                    data-name="{{ $tax->name }}" data-type="{{ $tax->type }}"
                                                    @selected(($item->tax_1 ?? 0) == $tax->tax)>
                                                    {{ $tax->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="items[{{ $index }}][tax_1_id]" value="{{ $item->tax_1_id ?? '' }}">
                                    </td>

                                    <td class="num">
                                        <span class="row-total sl-line-total text-danger">
                                            {{ CurrencySettings::number($itemTotal) }}
                                        </span>
                                    </td>

                                    <td class="num">
                                        <button type="button" class="btn sl-row__remove remove-row"
                                                aria-label="{{ __('sales_invoice.form.remove_row') }}"
                                                title="{{ __('sales_invoice.form.remove_row') }}">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        {{-- `#tax-rows` عقدٌ مع السكربت المشترك: يحقن صفوف
                             «خصم إضافي» و«التسوية» قبل آخر صفٍّ فيه --}}
                        <tfoot id="tax-rows">
                            <tr class="d-none"><td colspan="8"></td></tr>
                        </tfoot>
                    </table>
                </div>

                <div class="sl-doc-card__foot">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-row">
                        <i class="feather icon-plus"></i> {{ __('sales_invoice.form.add_row') }}
                    </button>

                    {{-- المجموع و«إجمالي المرتجع» ثابتان، وما بينهما مشروط:
                         `data-total-row="<id>"` عقدٌ مع `invoice-calculator.js`
                         — يُظهر السطر متى صار للقيمة وجود ويُخفيه متى عادت
                         صفراً. يبدأ مخفيّاً لأن المرتجع يبدأ بلا بنود.
                         و«المجموع بعد الخصم» يبقى ظاهراً: هو خطوة الحساب
                         التي تشرح الرقم الذي بعده لا مبلغاً قائماً بذاته. --}}
                    <div class="sl-totals">
                        <div class="sl-totals__row">
                            <span>{{ __('sales_invoice.subtotal') }}</span>
                            <span><span id="subtotal">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="total-discount">
                            <span>{{ __('sales_invoice.discount') }}</span>
                            <span><span id="total-discount">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row">
                            <span>{{ __('sales_return.form.after_discount') }}</span>
                            <span><span id="after-discount">0.00</span> {{ $symbol }}</span>
                        </div>
                        {{-- مرتجع المبيعات لا شحن فيه ولا إيداع اليوم، فتبقى
                             هذه صفراً ولا تظهر. وهي أسطرٌ لا أرقامٌ مخفيّة
                             حتى تظهر من نفسها إن أُضيفت حقولها يوماً.

                             والشحن قبل الضريبة: الضريبة تُحسب على المجموع
                             والشحن معاً، فسطرُها يلي الشحن لا يسبقه --}}
                        <div class="sl-totals__row d-none" data-total-row="shipping-cost">
                            <span>{{ __('sales_invoice.ui.shipping') }}</span>
                            <span><span id="shipping-cost">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" id="tax-row" data-total-row="tax-total">
                            <span>{{ __('sales_invoice.tax') }}</span>
                            <span><span id="tax-total">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="advance-payment">
                            <span>{{ __('sales_invoice.ui.advance_payment') }}</span>
                            <span><span id="advance-payment">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="paid-amount-display">
                            <span>{{ __('sales_invoice.ui.paid_amount') }}</span>
                            <span><span id="paid-amount-display">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row sl-totals__row--grand">
                            <span>{{ __('sales_return.form.return_total') }}</span>
                            <span><span id="grand-total">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="remaining-amount">
                            <span>{{ __('sales_invoice.ui.remaining') }}</span>
                            <span><span id="remaining-amount">0.00</span> {{ $symbol }}</span>
                        </div>

                        <small id="tax-details" class="sl-help"></small>
                    </div>
                </div>
            </div>

            {{-- ══════════ التفاصيل الإضافية ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head sl-doc-card__head--flush">
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-discount" href="#">
                                <i class="feather icon-percent"></i> {{ __('sales_invoice.discount') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-shipping" href="#">
                                <i class="feather icon-truck"></i> {{ __('sales_invoice.ui.shipping') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-documents" href="#">
                                <i class="feather icon-paperclip"></i> {{ __('sales_invoice.ui.attachments') }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sl-doc-card__body">
                    <div id="section-discount" class="tab-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="discount_amount">{{ __('sales_return.form.extra_discount') }}</label>
                                    <div class="input-group">
                                        <input type="number" id="discount_amount" name="discount_amount"
                                               class="form-control"
                                               value="{{ old('discount_amount', $originalExtras['discount_amount'] ?? 0) }}"
                                               min="0" step="0.01">
                                        {{-- بالنصّ لا بالرمز وحده: صندوقٌ ضيّق برمزٍ
                                             مجرَّد يبدو فارغاً فلا يُعرف المختار --}}
                                        {{-- `old()` على النوع كما على الرقم: كان الرقمُ يعود بعد
                                             ردّ الاستمارة والنوعُ يعود «مبلغاً» — فخصمُ ١٠٪ يُعاد
                                             إرسالُه عشرةَ ريالات دون أن يلحظ أحد --}}
                                        <select name="discount_type" class="form-control"
                                                style="flex:0 0 auto;width:auto;min-width:8.5rem">
                                            <option value="amount" @selected(old('discount_type', 'amount') === 'amount')>
                                                {{ __('sales_invoice.ui.discount_as_amount', ['symbol' => $symbol]) }}
                                            </option>
                                            <option value="percentage" @selected(old('discount_type') === 'percentage')>
                                                {{ __('sales_invoice.ui.discount_as_percent') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- أسماء الحقول هي أسماء شاشة الفاتورة نفسها:
                         `invoice-calculator.js` يقرؤها بالاسم، والمتحكّم
                         يقرؤها بالاسم — فلا سكربت خاصّ لهذه الشاشة --}}
                    <div id="section-shipping" class="tab-section d-none">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="sl-field">
                                    <label for="shipping">{{ __('sales_invoice.ui.shipping') }}</label>
                                    <input type="number" class="form-control" name="shipping_cost" id="shipping"
                                           value="{{ old('shipping_cost', $originalExtras['shipping_cost'] ?? 0) }}"
                                           min="0" step="0.01">
                                    <small class="sl-help">{{ __('sales_return.form.shipping_hint') }}</small>
                                    @if (($originalExtras['shipping_cost'] ?? 0) > 0)
                                        <small class="sl-help">
                                            <i class="feather icon-corner-down-left"></i>
                                            {{ __('sales_return.form.shipping_from_invoice', [
                                                'amount' => CurrencySettings::format($originalExtras['shipping_cost']),
                                                'code' => $invoice->code,
                                            ]) }}
                                        </small>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="sl-field">
                                    <label for="shipping_tax_id">{{ __('sales_invoice.ui.shipping_tax') }}</label>
                                    <select class="form-control" id="shipping_tax_id" name="shipping_tax_id">
                                        <option value="">{{ __('sales_invoice.none') }}</option>
                                        @foreach ($taxs as $tax)
                                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                data-name="{{ $tax->name }}"
                                                @selected((string) old('shipping_tax_id', $originalExtras['shipping_tax_id'] ?? '') === (string) $tax->id)>
                                                {{ $tax->name }} ({{ $tax->tax }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="sl-help">{{ __('sales_invoice.ui.shipping_tax_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="section-documents" class="tab-section d-none">
                        <label class="sl-upload" for="uploadFile">
                            <i class="feather icon-upload-cloud"></i>
                            <p class="sl-upload__title">{{ __('sales_return.form.attachments_title') }}</p>
                            <p class="sl-upload__hint">{{ __('sales_return.form.attachments_hint') }}</p>
                            <input type="file" class="d-none" id="uploadFile" name="attachments[]" multiple>
                        </label>
                        <ul class="sl-help mt-2 mb-0" id="uploadFileList"></ul>
                    </div>
                </div>
            </div>

            {{-- ══════════ الملاحظات وأزرار الحفظ ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-edit-3"></i> {{ __('sales_return.form.notes') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <textarea id="tinyMCE" name="notes" class="form-control" rows="5"
                              placeholder="{{ __('sales_return.form.notes_placeholder') }}">{{ old('notes') }}</textarea>
                </div>

                <div class="sl-doc-card__foot">
                    <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                        {{ __('sales_return.form.back') }}
                    </a>

                    <div class="sl-toolbar sl-toolbar--end">
                        <button type="button" class="btn btn-outline-danger" id="resetSearch" onclick="clearAllItems()">
                            <i class="feather icon-x"></i> {{ __('sales_invoice.form.clear_rows') }}
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="feather icon-save"></i> {{ __('sales_invoice.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{-- jQuery وsweetalert2 يأتيان من `sales::master` — وتحميلُهما ثانيةً
         هنا كان يستبدل نسخةَ jQuery **بعد** أن رُبط بها select2، فتفقد
         النسخةُ الجديدة الملحقَ وتصمت قائمةُ المستودع ولا تُظهر مختارَها.
         انظر `master.blade.php` (jQuery في السطر ٦٠، select2 في ٣١٢). --}}
    {{-- عروضُ الحسم السارية — تقرؤها الحاسبة فتُساوي المعروضَ بالمحفوظ --}}
    @include('sales::partials.discount-offers')

    @php $invoiceCalculatorVersion = @filemtime(public_path('assets/js/invoice-calculator.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/invoice-calculator.js') }}?v={{ $invoiceCalculatorVersion }}"></script>

    {{-- الضريبة الشاملة على المستند — تكوينُ الـ JS وتحميل حاسبتها --}}
    @include('partials.comprehensive-tax-script')

    <script>
        // ✅ دالة جلب الموقع الحالي للموظف (يجب أن تكون معرفة قبل الاستدعاء)
        function getEmployeeCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;

                        // تحديث الحقول الخفية
                        document.getElementById('current_latitude').value = latitude;
                        document.getElementById('current_longitude').value = longitude;

                        console.log('✅ تم جلب الموقع:', latitude, longitude);
                    },
                    function(error) {
                        console.warn('⚠️ خطأ في جلب الموقع:', error.message);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                console.warn('⚠️ المتصفح لا يدعم Geolocation');
            }
        }

        // متغير الإعدادات
        const activeSettings = @json($salesSettings ?? []);
        const defaultPaidEnabled = activeSettings.includes('default_paid_invoices');
        const autoInventoryEnabled = activeSettings.includes('auto_inventory_update');

        // بيانات الفاتورة الأصلية
        const invoiceData = {
            id: {{ $invoice->id }},
            code: '{{ $invoice->code }}',
            grand_total: {{ $invoice->grand_total }},
            paid_amount: {{ $invoice->paid_amount ?? 0 }},
            payment_status: {{ $invoice->payment_status }},
            due_value: {{ $invoice->due_value ?? 0 }}
        };

        // البيانات المنتجات من الخادم
        const productsData = JSON.parse(document.getElementById('products-data').value || '[]');
        const productsMap = {};
        productsData.forEach(product => {
            productsMap[product.id] = product;
        });

        document.addEventListener('DOMContentLoaded', function() {
            // جلب الموقع الحالي للموظف
            getEmployeeCurrentLocation();

            setupTabs();
            setupEventHandlers();
            checkPaymentStatus();

            // عرض رصيد العميل إذا كان محدد
            const clientSelect = document.getElementById('clientSelect');
            // الدالة ليست في هذه الشاشة (العميل هنا للقراءة فقط) — نداؤها بلا
            // حارسٍ يوقف ما بعده: حقول الضرائب وحساب الإجماليات
            if (clientSelect && clientSelect.value && typeof showClientBalance === 'function') {
                showClientBalance(clientSelect);
            }

            // تحديث الحقول المخفية للضرائب عند تحميل الصفحة
            document.querySelectorAll('.tax-select').forEach(function(select) {
                updateHiddenInput(select);
            });

            // حساب الإجماليات عند تحميل الصفحة
            if (typeof calculateTotals === 'function') {
                calculateTotals();
            }
        });

        function setupTabs() {
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                    document.querySelectorAll('.tab-section').forEach(section => section.classList.add('d-none'));

                    this.classList.add('active');
                    const targetSection = document.getElementById('section-' + this.id.replace('tab-', ''));
                    if (targetSection) {
                        targetSection.classList.remove('d-none');
                    }
                });
            });
        }

        function setupEventHandlers() {
            // معالج تبديل خيارات إرجاع المبلغ (راديو بوتن)
            const returnOptions = document.querySelectorAll('[name="return_amount_option"]');
            const paymentFields = document.getElementById('payment-fields');

            returnOptions.forEach(option => {
                option.addEventListener('change', function() {
                    if (paymentFields) {
                        // إظهار الحقول فقط إذا تم اختيار "إرجاع المبلغ"
                        if (this.value === 'return') {
                            // `block` كان يُسقط تخطيط الصفّ فتتراكم الحقول عمودياً
                            paymentFields.style.display = '';
                        } else {
                            paymentFields.style.display = 'none';
                        }
                    }
                });
            });

            // الخيار الافتراضي يضعه القالبُ من حالة الفاتورة (مدفوعة ← ردّ،
            // آجلة ← دون ردّ)؛ هنا تُطابَق حقولُ الردّ مع الخيار المختار
            const chosen = document.querySelector('[name="return_amount_option"]:checked');
            if (paymentFields) {
                paymentFields.style.display = chosen && chosen.value === 'return' ? '' : 'none';
            }
        }

        /* ═══════════ حالة دفع الفاتورة الأصلية ═══════════

           سطرٌ واحد من الحقائق بألوان الشاشة نفسها، أيّاً كانت الحالة. كان
           صندوقاً كبيراً يتبدّل لونُه (أخضر / أصفر / أحمر) وفي داخله صندوقٌ
           أزرق، فيأخذ نصفَ الشاشة ليقول ثلاثة أرقام — و«غير مدفوعة» بالأحمر
           تُقرأ خطأً وهي حالةٌ عاديّة لفاتورةٍ آجلة. */
        function checkPaymentStatus() {
            const paymentStatus = Number(invoiceData.payment_status);
            const paidAmount = parseFloat(invoiceData.paid_amount) || 0;
            const dueValue = parseFloat(invoiceData.due_value) || 0;
            const symbol = @json($symbol);

            const statusIcon = document.getElementById('payment-status-icon');
            const statusText = document.getElementById('payment-status-text');
            const alertDiv = document.getElementById('payment-status-alert');
            const alertContainer = document.getElementById('alert-container');
            const returnYesOption = document.getElementById('return-amount-yes');
            const returnNoOption = document.getElementById('return-amount-no');
            const paymentFields = document.getElementById('payment-fields');

            // 1 = مدفوعة بالكامل، 2 = مدفوعة جزئياً، 3 = غير مدفوعة
            const states = {
                1: {label: 'مدفوعة بالكامل', icon: 'icon-check-circle',
                    note: 'يمكنك ردّ المبلغ للعميل أو تسجيل المرتجع دون ردّ.'},
                2: {label: 'مدفوعة جزئياً', icon: 'icon-clock',
                    note: 'يُردّ للعميل ما دفعه فقط، والباقي يُنقص مديونيته.'},
                3: {label: 'غير مدفوعة', icon: 'icon-minus-circle',
                    note: 'لا مبلغ يُردّ — المرتجع يُنقص مديونية العميل فقط.'},
            };
            const state = states[paymentStatus];

            if (! state) {
                if (alertDiv) alertDiv.style.display = 'none';

                return;
            }

            const refundable = paymentStatus === 3 ? 0 : paidAmount;
            const money = (value) => value.toFixed(2) + ' ' + symbol;
            const fact = (label, value) => '<div class="sl-fact" data-payment-fact><span class="sl-fact__label">' + label
                + '</span><span class="sl-fact__value sl-num">' + value + '</span></div>';

            if (statusIcon) statusIcon.className = 'feather ' + state.icon;
            if (statusText) {
                statusText.textContent = state.label;
                statusText.className = '';
            }

            // الأرقامُ في شبكة حقائق الفاتورة نفسها، فتتحاذى أعمدتُها مع ما فوقها
            const facts = statusText ? statusText.closest('.sl-facts') : null;

            if (facts) {
                facts.querySelectorAll('[data-payment-fact]').forEach((el) => el.remove());
                facts.insertAdjacentHTML('beforeend',
                    fact('المبلغ المدفوع', money(paidAmount))
                    + fact('المبلغ المتبقي', money(dueValue))
                    + fact('يمكن استرجاعه', money(refundable)));
            }

            if (alertContainer && alertDiv) {
                alertContainer.innerHTML = '<small class="sl-help d-block m-0"><i class="feather icon-info"></i> '
                    + state.note + '</small>';
                alertDiv.style.display = 'block';
            }

            // بلا مقبوضٍ لا ردّ: الخيارُ معطَّل وحقولُه مخفيّة
            if (returnYesOption) returnYesOption.disabled = refundable <= 0;

            if (refundable <= 0) {
                if (returnNoOption) returnNoOption.checked = true;
                if (paymentFields) paymentFields.style.display = 'none';
            }
        }

        // دالة نسخ آخر مرتجع
        window.copyLastReturn = function() {
            Swal.fire({
                title: 'نسخ آخر مرتجع',
                text: 'هل تريد نسخ بيانات آخر فاتورة مرتجعة؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، انسخ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    performCopyLastReturn();
                }
            });
        };

        function performCopyLastReturn() {
            fetch('/sales/return-invoices/get-last', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.invoice) {
                    fillReturnData(data.invoice);
                    Swal.fire('تم!', 'تم نسخ بيانات آخر مرتجع بنجاح', 'success');
                } else {
                    Swal.fire('تنبيه', 'لم يتم العثور على مرتجعات سابقة', 'info');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('خطأ', 'حدث خطأ أثناء جلب البيانات', 'error');
            });
        }

        function fillReturnData(returnData) {
            // العميل ثابت لا يتم تحديثه في المرتجع

            // ملء الحقول الأساسية
            const basicFields = ['discount_amount', 'discount_type', 'return_reason', 'notes'];

            basicFields.forEach(fieldName => {
                if (returnData[fieldName] !== undefined) {
                    const field = document.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        field.value = returnData[fieldName];
                    }
                }
            });

            // إعادة حساب الإجماليات
            if (typeof calculateTotals === 'function') {
                calculateTotals();
            }
        }

        // دالة تأكيد الحفظ
        function confirmSubmit(event) {
            event.preventDefault();

            let settingsMessage = '';
            if (activeSettings.length > 0) {
                settingsMessage = '<div class="alert alert-info mt-3 text-start"><strong>الإعدادات المفعلة:</strong><br>';

                if (defaultPaidEnabled) {
                    settingsMessage += '• سيتم إرجاع المبلغ تلقائياً<br>';
                }
                if (autoInventoryEnabled) {
                    settingsMessage += '• سيتم إرجاع المنتجات للمخزون تلقائياً<br>';
                }

                settingsMessage += '</div>';
            }

            Swal.fire({
                title: 'تأكيد حفظ المرتجع',
                html: `<p>هل أنت متأكد من حفظ فاتورة المرتجع؟</p>${settingsMessage}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احفظه!',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('return-invoice-form').submit();
                }
            });
        }

        // دالة تحديث الحقول المخفية للضرائب
        function updateHiddenInput(selectElement) {
            const row = selectElement.closest('tr');
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const taxId = selectedOption.getAttribute('data-id');

            // تحديد اسم الحقل المخفي بناءً على الضريبة (tax_1 أو tax_2)
            const name = selectElement.getAttribute('name');
            const hiddenInputName = name.replace('[tax_', '[tax_').replace(']', '_id]');
            const hiddenInput = row.querySelector(`[name="${hiddenInputName}"]`);

            if (hiddenInput) {
                hiddenInput.value = taxId || '';
            }
        }

        /*
         * «إضافة بند» و«حذف بند» من `invoice-calculator.js` وحده.
         *
         * كان هنا مستمعٌ ثالثٌ لهما يستنسخ الصفّ بنفسه، فوق نسختَي
         * الحاسبة، فتُنتج النقرة الواحدة ثلاثة بنود. وكان يحسب فهرس
         * الصفّ الجديد من عدد الصفوف — فيتكرّر فهرسٌ بعد حذف صفٍّ من
         * الوسط ويضيع بندٌ عند الحفظ؛ الحاسبة تحسبه من أكبر فهرسٍ
         * مستعمَل.
         */

        // حساب الإجماليات عند تغيير القيم
        document.addEventListener('input', function(e) {
            if (e.target.matches('.quantity, .price, .discount-amount, .discount-percentage, [name="discount_amount"]')) {
                if (typeof calculateTotals === 'function') {
                    calculateTotals();
                }
            }
        });

        // معالج اختيار المنتج وتخزين الكمية المتبقية
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const row = e.target.closest('.item-row');
                const productId = e.target.value;
                const priceInput = row.querySelector('.price');
                const quantityInput = row.querySelector('.quantity');

                if (productId && productsMap[productId]) {
                    const product = productsMap[productId];

                    // حساب الكمية المتبقية من product_details
                    let totalQuantity = 0;
                    if (product.product_details && Array.isArray(product.product_details)) {
                        totalQuantity = product.product_details.reduce((sum, detail) => {
                            return sum + (parseFloat(detail.quantity) || 0);
                        }, 0);
                    }

                    // تخزين الكمية المتبقية في data attribute
                    quantityInput.setAttribute('data-available', totalQuantity);

                    // ملء السعر تلقائياً
                    if (priceInput && product.sale_price) {
                        priceInput.value = parseFloat(product.sale_price).toFixed(2);
                    }
                } else {
                    quantityInput.setAttribute('data-available', '0');
                }
            }

            // معالج إدخال الكمية لإظهار المتبقي
            if (e.target.classList.contains('quantity')) {
                const row = e.target.closest('.item-row');
                // `parseFloat` لا `parseInt`: الكمية كسريّة، و`parseInt('1.5')` واحد
                const quantity = parseFloat(e.target.value) || 0;
                const available = parseFloat(e.target.getAttribute('data-available') || 0);
                const infoSpan = row.querySelector('.remaining-info');
                const quantityInput = e.target;

                if (quantity > 0 && available > 0) {
                    // حساب المتبقي بعد هذه الكمية
                    const remaining = available - quantity;

                    // عرض الرسالة
                    infoSpan.style.display = 'block';

                    if (remaining < 0) {
                        // الكمية أكثر من المتاح
                        infoSpan.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-circle"></i> متوفر: <strong>${available}</strong> - المتبقي: <strong>${remaining}</strong></span>`;
                        quantityInput.classList.add('is-invalid');
                    } else {
                        // الكمية صحيحة
                        infoSpan.innerHTML = `<span class="text-success"><i class="fas fa-check-circle"></i> متوفر: <strong>${available}</strong> - المتبقي: <strong>${remaining}</strong></span>`;
                        quantityInput.classList.remove('is-invalid');
                    }
                } else {
                    infoSpan.style.display = 'none';
                    quantityInput.classList.remove('is-invalid');
                }
            }

            if (e.target.matches('.tax-select, .discount-type, [name="discount_type"]')) {
                if (typeof calculateTotals === 'function') {
                    calculateTotals();
                }
            }
        }, true);
    </script>
@endsection
