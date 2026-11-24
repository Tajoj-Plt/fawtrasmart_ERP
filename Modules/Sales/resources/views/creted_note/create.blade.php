{{--
    إنشاء إشعار دائن.

    أُعيد بناء الشاشة على قياس «إنشاء إشعار مدين» في المشتريات
    (`purchases::purchases.city_notices.create`): الترتيب نفسه — بيانات
    المستند ← البنود ← التفاصيل الإضافية ← الملاحظات وأزرار الحفظ —
    وجدول البنود نفسه ولوحة الإجماليات نفسها، بنظام `sl-doc-*`.

    والإشعار الدائن — كنظيره المدين — قد يصدر عن فاتورة وقد يصدر مستقلاً:
      • جاء من فاتورة (`$originalInvoice`) ← المرجع مقفل وبنوده منسوخة عنها
      • فُتِح من القائمة                   ← المرجع اختياري من فواتير العميل

    ما تأتي به الإعدادات:
      • الرقم           ← «تسلسل أرقام المستندات» (القسم `credit-note`)
      • العملة والكسور  ← «العملات»
      • نِسَب الضريبة    ← «إعدادات الضريبة»
      • الإقفال         ← «الفترات المحاسبية»: تُنبَّه الشاشة قبل الحفظ
      • السلوك          ← «إعدادات الفواتير» (`SalesInvoiceSetting`)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    **عقد الـDOM محفوظ.** `public/assets/js/invoice-calculator.js` مشتركٌ
    بين أربع شاشات، ويعتمد على `#items-table` و`.item-row` و`#add-row`
    و`#tax-rows` وأسماء عناصر الإجماليات. أُعيد الشكل ولم تُمسّ الأسماء.
--}}

@extends('sales::master')

@section('title', __('sales_credit_note.create_title'))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: قوالبُ المستندات لا تعرّفهما، وكلُّ
         قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها في محدِّدٍ واحد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')
    @include('sales::partials.document-theme-po')

    @php
        use App\Models\SalesInvoiceSetting;
        use App\Services\Tax\DocumentTaxCalculator;
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        // الاسم ليس `$settings` عمداً: `@extends` يمرّر متغيّرات هذا
        // القالب كلَّها إلى `sales::master` ومنها إلى القائمة الجانبية،
        // وهي تنتظر `$settings` مصفوفةَ «شاشة التطبيقات»
        $invoiceRules = $invoiceSettings ?? SalesInvoiceSetting::current();

        $symbol = CurrencySettings::symbol();
        $original = $originalInvoice ?? null;
        $methods = $paymentMethods ?? collect();

        // بنود الفاتورة المرجعية تُنسخ ابتداءً؛ والمعادة بعد فشل تحقّق
        // تتقدّم عليها، وإلا ضاع الإدخال
        $rows = old('items');

        if ($rows === null && $original) {
            $rows = collect($original->items)
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    // `invoice_items.discount` مبلغٌ في الحالين؛ قراءتُه «نسبةً»
                    // لبندٍ نوعُه ٢ تجعل خصمَ 50 ريالاً خمسين بالمئة
                    'discount_type' => 'amount',
                    'tax_1' => (float) $item->tax_1,
                    'tax_1_id' => $item->tax_1_id,
                    // الوحدةُ التي بيع بها البند: بلاها يُردّ الكرتون حبّةً
                    'product_unit_id' => $item->product_unit_id,
                    'conversion_factor' => $item->conversion_factor,
                    'unit_name' => $item->unit_name,
                ])
                ->values()
                ->all();
        }

        /* ما تأتي به الفاتورة المرجعية فوق البنود: المسؤول والخصم والشحن
           وضريبته. `invoices.discount_amount` يحمل خصمَ البنود وخصمَ الفاتورة
           معاً، والبنودُ تحمل خصمَها في صفوفها — فيُطرح منه كي لا يُحسب مرّتين.
           وضريبةُ الشحن محفوظةٌ مبلغاً فتُستخرج ضريبتُها بمطابقة النسبة. */
        $sourceShipping = (float) ($original->shipping_cost ?? 0);
        $sourceShippingTax = (float) ($original->shipping_tax ?? 0);

        $prefill = [
            'employee_id' => $original?->employee_id,
            'discount_amount' => $original
                ? round(max(0, (float) $original->discount_amount - (float) collect($original->items)->sum('discount')), 2)
                : 0,
            'shipping_cost' => $sourceShipping,
            'shipping_tax_id' => ($sourceShipping > 0 && $sourceShippingTax > 0)
                ? DocumentTaxCalculator::matchByRate(round($sourceShippingTax / $sourceShipping * 100, 2))
                : null,
        ];

        $rows = $rows ?: [[]];

        $canEditDate = hasPermission('sales.invoices.edit_date');
    @endphp

    <div class="content-body sl-scope sl-doc sl-doc--po">

        @include('sales::components.page-head', [
            'title' => __('sales_credit_note.create_title'),
            'icon' => 'plus-circle',
            'sub' => __('sales_invoice.form.required_note'),
            'crumbs' => array_filter([
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_credit_note.plural') => route('CreditNotes.index'),
                $original?->code => $original ? route('invoices.show', $original->id) : null,
                __('sales_settings.ui.add') => null,
            ], fn ($url, $label) => $label !== '', ARRAY_FILTER_USE_BOTH),
        ])

        {{-- ══════════ ما يُحفَظ به هذا الإشعار ══════════ --}}
        <div class="sl-doc-note">
            <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
            <div class="sl-doc-note__body">
                <strong>{{ __('sales_invoice.context.currency') }}:</strong>
                {{ CurrencySettings::name() }} ({{ CurrencySettings::code() }})
                &nbsp;·&nbsp;
                <strong>{{ __('sales_invoice.context.numbering') }}:</strong>
                <span class="sl-code">{{ $Credits_number ?: '—' }}</span>
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

        @include('sales::components.form-errors')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-credit-notes.create',
            'title' => __('sales_credit_note.guide.create.title'),
            'lead' => __('sales_credit_note.guide.create.lead'),
            'open' => false,
            'blocks' => [
                __('sales_credit_note.guide.flow.title') => [
                    __('sales_credit_note.guide.flow.a'),
                    __('sales_credit_note.guide.flow.b'),
                    __('sales_credit_note.guide.flow.c'),
                    __('sales_credit_note.guide.flow.d'),
                ],
                __('sales_credit_note.guide.money_flow.title') => [
                    __('sales_credit_note.guide.money_flow.a'),
                    __('sales_credit_note.guide.money_flow.b'),
                    __('sales_credit_note.guide.money_flow.c'),
                    __('sales_credit_note.guide.money_flow.d'),
                ],
                __('sales_credit_note.guide.statuses.title') => [
                    __('sales_credit_note.guide.statuses.a'),
                    __('sales_credit_note.guide.statuses.b'),
                    __('sales_credit_note.guide.statuses.c'),
                ],
                __('sales_credit_note.guide.blockers.title') => [
                    __('sales_credit_note.guide.blockers.a'),
                    __('sales_credit_note.guide.blockers.b'),
                    __('sales_credit_note.guide.blockers.c'),
                ],
                __('sales_credit_note.guide.noedit.title') => [
                    __('sales_credit_note.guide.noedit.a'),
                    __('sales_credit_note.guide.noedit.b'),
                ],
            ],
            'facts' => [
                __('sales_credit_note.guide.facts.a'),
                __('sales_credit_note.guide.facts.b'),
                __('sales_credit_note.guide.facts.c'),
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        <form id="credit-note-form" action="{{ route('CreditNotes.store') }}" method="post"
              enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="current_latitude" id="current_latitude" value="0">
            <input type="hidden" name="current_longitude" id="current_longitude" value="0">

            {{-- ══════════ بيانات المستند ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-file-text"></i> {{ __('sales_credit_note.form.document') }}
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
                                <div class="sl-select-add">
                                <select class="form-control select2" id="clientSelect" name="client_id" required
                                        onchange="showClientBalance(this)"
                                        {{ $original ? 'readonly' : '' }}>
                                    <option value="">{{ __('sales_invoice.select_client') }}</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c->id }}"
                                            data-balance="{{ $c->account->balance ?? 0 }}"
                                            @selected(old('client_id', $selectedClientId) == $c->id)>
                                            {{ $c->trade_name }} - {{ $c->code }}
                                        </option>
                                    @endforeach
                                </select>
                                    {{-- العميلُ غير مسجَّل؟ رابطٌ يفتح شاشة إضافته في تبويبٍ جديد بدل
                                         إجبار المستخدم على مغادرة النموذج الذي يملؤه وضياع ما أدخله.
                                         محروسٌ بالصلاحية نفسِها التي يحرس بها مسارُ الإضافة: زرٌّ يقود
                                         إلى ٤٠٣ أسوأ من غيابه. --}}
                                    @permission('clients_add_client')
                                        @if (Route::has('clients.create'))
                                            <a href="{{ route('clients.create') }}" target="_blank" rel="noopener"
                                               class="btn btn-outline-primary sl-select-add__btn"
                                               title="{{ __('sales_invoice.actions.quick_add_client') }}">
                                                <i class="feather icon-plus"></i>
                                            </a>
                                        @endif
                                    @endpermission
                                </div>
                            </div>
                        </div>

                        {{-- الفاتورة المرجعية: مقفلة متى جاء الإشعار منها،
                             واختيارية متى فُتِحت الشاشة من القائمة --}}
                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="invoice_id">{{ __('sales_credit_note.source_invoice') }}</label>

                                @if ($original)
                                    <input type="hidden" name="invoice_id" value="{{ $original->id }}">
                                    <input type="text" class="form-control" value="{{ $original->code }}" readonly>
                                @else
                                    {{-- فواتيرُ العميل المختار وحده، بلا ما صدر له مرتجعٌ أو إشعار؛
                                         تُعاد من `CreditNotes.clientInvoices` كلّما تبدّل العميل --}}
                                    <select class="form-control select2" id="invoice_id" name="invoice_id"
                                            data-url="{{ route('CreditNotes.clientInvoices') }}">
                                        <option value="">{{ __('sales_credit_note.no_source') }}</option>
                                        @foreach ($availableInvoices ?? [] as $inv)
                                            <option value="{{ $inv->id }}" @selected(old('invoice_id') == $inv->id)>
                                                {{ $inv->code }} — {{ CurrencySettings::format($inv->grand_total) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                                <small class="sl-help">{{ __('sales_credit_note.source_invoice_hint') }}</small>
                            </div>
                        </div>

                        {{-- لا اختيارَ للمستودع هنا: البضاعةُ تُردّ إلى مستودع
                             الفاتورة المرجعية، وإلّا فإلى المستودع المضبوط في
                             «إعدادات الفواتير» — انظر `store()` --}}

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="credit_number">{{ __('sales_credit_note.filters.number') }}</label>
                                <input type="text" class="form-control" id="credit_number" name="credit_number"
                                       value="{{ old('credit_number', $Credits_number) }}" readonly>
                                <small class="sl-help">{{ __('sales_invoice.context.numbering_locked') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="credit_date">
                                    {{ __('sales_credit_note.form.credit_date') }} <span class="required">*</span>
                                </label>
                                <input class="form-control" type="date" id="credit_date" name="credit_date" required
                                       value="{{ old('credit_date', date('Y-m-d')) }}"
                                       {{ $canEditDate ? '' : 'readonly' }}>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="release_date">
                                    {{ __('sales_credit_note.form.release_date') }} <span class="required">*</span>
                                </label>
                                <input class="form-control" type="date" id="release_date" name="release_date" required
                                       value="{{ old('release_date', date('Y-m-d')) }}"
                                       {{ $canEditDate ? '' : 'readonly' }}>
                            </div>
                        </div>

                        {{-- كان هنا مُنتقي «نوع الضريبة» (خاضعة/صفرية/معفاة).
                             حُذف بطلب المستخدم: الضريبةُ تُختار **على البند**
                             في جدول الأصناف، ومُنتقٍ ثانٍ على مستوى المستند
                             يسأل عن الشيء نفسه بجوابٍ قد يناقضه.

                             وشاشاتُ المبيعات الأخرى لا تسأله أصلاً: فاتورةُ
                             البيع والمرتجع بلا الحقل، وعرضُ السعر يرسله
                             مخفيّاً بقيمة «خاضعة». فصار الإشعارُ على قاعدتها.

                             والعمودُ باقٍ في القاعدة وتُعرَض به الإشعاراتُ
                             القديمة؛ والحفظُ يكتب «خاضعة» حين لا يصله شيء. --}}

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="employee_id">{{ __('sales_invoice.filters.sales_person') }}</label>
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    <option value="">{{ __('sales_invoice.none') }}</option>
                                    @foreach (\App\Models\Employee::all() as $employee)
                                        <option value="{{ $employee->id }}" @selected(old('employee_id', $prefill['employee_id']) == $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="clientBalanceCard" class="sl-doc-note mt-3" style="display:none">
                        <span class="sl-doc-note__icon"><i class="feather icon-user"></i></span>
                        <div class="sl-doc-note__body">
                            <strong id="clientName">—</strong>
                            <span class="d-block">
                                {{ __('sales_invoice.ui.balance') }}:
                                <span id="clientBalance" class="sl-num">0.00</span> {{ $symbol }}
                                — <span id="balanceStatus"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ البنود ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-package"></i> {{ __('sales_credit_note.form.items') }}
                    </h6>
                    <span class="sl-hint">
                        <i class="feather icon-dollar-sign"></i>
                        {{ CurrencySettings::name() }} ({{ $symbol }})
                    </span>
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
                                <th style="width:110px">
                                    {{ __('sales_invoice.quantity') }} <span class="required">*</span>
                                </th>
                                {{-- الوحدة عمودٌ قائمٌ بذاته لا سطرٌ تحت الصنف —
                                     نظير ما في شاشة الفاتورة، فالجدولان يُقرآن
                                     بالطريقة نفسها --}}
                                <th style="width:130px">{{ __('sales_invoice.ui.unit') }}</th>
                                <th style="width:130px">{{ __('sales_invoice.unit_price') }}</th>
                                {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                                <th style="width:150px">{{ __('sales_invoice.tax1') }}</th>
                                <th class="num" style="width:120px">{{ __('sales_invoice.total') }}</th>
                                <th style="width:46px"></th>
                            </tr>
                        </thead>

                        {{-- خريطةُ الوحدات — يقرؤها السكربت عند اختيار المنتج --}}
                        <input type="hidden" id="products-with-units-data"
                               value="{{ json_encode($productsWithUnits ?? []) }}">

                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr class="item-row">
                                    <td class="num"><span class="sl-row__index">{{ $index + 1 }}</span></td>

                                    <td>
                                        <div class="sl-select-add">
                                            <select name="items[{{ $index }}][product_id]" class="form-control product-select" required
                                                    onchange="handleCreditProductChange(this)">
                                                <option value="">{{ __('sales_invoice.ui.select_item') }}</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item->id }}" data-price="{{ $item->sale_price }}"
                                                        @selected(($row['product_id'] ?? null) == $item->id)>
                                                        {{ $item->name }}
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

                                        {{-- ما يقابل الوحدةَ الواحدة بالوحدة الأساسية.
                                             يُحفظ مع الكمية فلا يختلّ المخزون حين تُردّ
                                             البضاعة بالكرتون وقد صُرفت بالحبّة. --}}
                                        <input type="hidden" name="items[{{ $index }}][conversion_factor]"
                                               class="conversion-factor" value="{{ $row['conversion_factor'] ?? 1 }}">
                                        <input type="hidden" name="items[{{ $index }}][unit_name]"
                                               class="unit-name" value="{{ $row['unit_name'] ?? '' }}">
                                    </td>

                                    <td>
                                        <input type="text" name="items[{{ $index }}][description]"
                                               class="form-control item-description"
                                               value="{{ $row['description'] ?? '' }}"
                                               placeholder="{{ __('sales_invoice.description') }}">
                                    </td>

                                    <td>
                                        {{-- كميةٌ كسريّة: `invoice_items.quantity` صار
                                             `decimal(15,4)`، فالمرتجَع بنصف كيلو أو
                                             بربع متر يُكتب كما هو ولا يُبتَر --}}
                                        <input type="number" name="items[{{ $index }}][quantity]" class="form-control quantity"
                                               value="{{ $row['quantity'] ?? 1 }}" min="0.0001" step="any"
                                               inputmode="decimal" required>
                                    </td>

                                    {{-- الوحدة — مخفيّةٌ حتى يُختار منتجٌ له وحدات.
                                         منتجٌ بلا وحدات لا يُسأل عن اختيارٍ لا خيار
                                         فيه، فيبقى العمود فارغاً في صفّه. --}}
                                    <td>
                                        <select name="items[{{ $index }}][product_unit_id]"
                                                class="form-control unit-select"
                                                data-selected="{{ $row['product_unit_id'] ?? '' }}"
                                                onchange="handleCreditUnitChange(this)" style="display:none">
                                            <option value="">{{ __('sales_invoice.ui.base_unit') }}</option>
                                        </select>
                                    </td>

                                    <td>
                                        <input type="text" name="items[{{ $index }}][unit_price]" class="form-control price"
                                               value="{{ $row['unit_price'] ?? '' }}" inputmode="decimal" required
                                               placeholder="0.00">
                                    </td>

                                    {{-- خصمُ البند أُزيل من الشاشة، ويُحمَل مخفيّاً **نسبةً** —
                                         للعلّة نفسها في «إنشاء مرتجع»: الإشعارُ المنقول من فاتورةٍ
                                         يُسوّى بما دُفع لا بالسعر قبل خصمه، والنسبةُ تتناسب مع
                                         الكمية حين تُعدَّل. والسطرُ الجديد بلا خصم. --}}
                                    @php
                                        $lineInput = (float) ($row['discount'] ?? 0);
                                        $lineGross = (float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0);
                                        $linePercent = ($row['discount_type'] ?? 'amount') === 'percentage'
                                            ? min(100, $lineInput)
                                            : ($lineGross > 0 ? round(min(100, $lineInput / $lineGross * 100), 6) : 0);
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
                                                    @selected(isset($row['tax_1']) && (float) $row['tax_1'] === (float) $tax->tax)>
                                                    {{ $tax->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="items[{{ $index }}][tax_1_id]"
                                               value="{{ $row['tax_1_id'] ?? '' }}">
                                    </td>

                                    <td class="num"><span class="row-total sl-line-total">0.00</span></td>

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

                        {{-- `#tax-rows` عقدٌ مع السكربت: يحقن صفوف «خصم إضافي»
                             و«التسوية» قبل آخر صفٍّ فيه --}}
                        <tfoot id="tax-rows">
                            <tr class="d-none"><td colspan="9"></td></tr>
                        </tfoot>
                    </table>
                </div>

                <div class="sl-doc-card__foot">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-row">
                        <i class="feather icon-plus"></i> {{ __('sales_invoice.form.add_row') }}
                    </button>

                    {{-- المجموع والإجمالي النهائي ثابتان، وما بينهما مشروط:
                         `data-total-row="<id>"` عقدٌ مع `invoice-calculator.js`
                         — يُظهر السطر متى صار للقيمة وجود ويُخفيه متى عادت
                         صفراً. يبدأ مخفيّاً لأن الإشعار يبدأ بلا بنود. --}}
                    <div class="sl-totals">
                        <div class="sl-totals__row">
                            <span>{{ __('sales_invoice.subtotal') }}</span>
                            <span><span id="subtotal">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="total-discount">
                            <span>{{ __('sales_invoice.discount') }}</span>
                            <span><span id="total-discount">0.00</span> {{ $symbol }}</span>
                        </div>
                        {{-- الشحن قبل الضريبة: الضريبة تُحسب على المجموع
                             والشحن معاً، فسطرُها يلي الشحن لا يسبقه --}}
                        <div class="sl-totals__row d-none" data-total-row="shipping-cost">
                            <span>{{ __('sales_invoice.ui.shipping') }}</span>
                            <span><span id="shipping-cost">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="tax-total">
                            <span>{{ __('sales_invoice.tax') }}</span>
                            <span><span id="tax-total">0.00</span> {{ $symbol }}</span>
                        </div>

                        {{-- الإشعار الدائن لا حقل إيداعٍ فيه اليوم، فتبقى هذه
                             صفراً ولا تظهر. وهي أسطرٌ لا أرقامٌ مخفيّة حتى
                             تظهر من نفسها إن أُضيف الحقل يوماً --}}
                        <div class="sl-totals__row d-none" data-total-row="advance-payment">
                            <span>{{ __('sales_invoice.ui.advance_payment') }}</span>
                            <span><span id="advance-payment">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="paid-amount-display">
                            <span>{{ __('sales_invoice.ui.paid_amount') }}</span>
                            <span><span id="paid-amount-display">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row sl-totals__row--grand">
                            <span>{{ __('sales_invoice.grand_total') }}</span>
                            <span><span id="grand-total">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="remaining-amount">
                            <span>{{ __('sales_invoice.ui.remaining') }}</span>
                            <span><span id="remaining-amount">0.00</span> {{ $symbol }}</span>
                        </div>

                        {{-- عنصر يكتب فيه السكربت المشترك دائماً — يبقى
                             مخفيّاً لا محذوفاً، وإلا رمى عند كل إعادة حساب --}}
                        <span class="d-none" id="next-payment">0.00</span>

                        <small id="tax-details" class="sl-help"></small>
                    </div>
                </div>
            </div>

            {{-- ══════════ ما سيُسدّده الإشعار ══════════
                 معاينةٌ من `CreditNotes.settlementPreview` بالخطّة نفسها التي
                 يُنفّذها الحفظ (`ClientDebtSettlement::plan()`)، تتجدّد كلّما
                 تغيّر العميل أو الفاتورة المرجعية أو الإجمالي --}}
            <div class="sl-doc-card" id="settlement-card"
                 data-url="{{ route('CreditNotes.settlementPreview') }}">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-layers"></i> {{ __('sales_credit_note.settlement.title') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <p class="sl-help mb-2">{{ __('sales_credit_note.settlement.hint') }}</p>

                    <p class="sl-help mb-0" id="settlement-empty">{{ __('sales_credit_note.settlement.empty') }}</p>

                    <div class="table-responsive d-none" id="settlement-table-wrap">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('sales_credit_note.settlement.item') }}</th>
                                    <th>{{ __('sales_credit_note.settlement.date') }}</th>
                                    <th class="text-end">{{ __('sales_credit_note.settlement.due') }}</th>
                                    <th class="text-end">{{ __('sales_credit_note.settlement.pay') }}</th>
                                    <th class="text-end">{{ __('sales_credit_note.settlement.left') }}</th>
                                </tr>
                            </thead>
                            <tbody id="settlement-rows"></tbody>
                        </table>
                    </div>

                    <div class="sl-doc-note mt-2 mb-0 d-none" id="settlement-surplus">
                        <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
                        <div class="sl-doc-note__body" id="settlement-surplus-text"></div>
                    </div>

                    <p class="sl-help mt-2 mb-0">
                        <i class="feather icon-dollar-sign"></i> {{ __('sales_credit_note.settlement.cash_hint') }}
                    </p>
                </div>
            </div>

            {{-- ══════════ التفاصيل الإضافية ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head sl-doc-card__head--flush">
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-discount" href="#">
                                {{-- «الخصم» وحدَه: حقلُ «التسوية» حُذف من المبيعات،
                                     فاسمٌ يَعِد به يُبقي المستخدم يبحث عمّا لا وجود له --}}
                                <i class="feather icon-percent"></i> {{ __('sales_invoice.discount') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-shipping" href="#">
                                <i class="feather icon-truck"></i> {{ __('sales_invoice.ui.delivery') }}
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
                    {{-- الخصم --}}
                    <div id="section-discount" class="tab-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="discount_amount">{{ __('sales_invoice.ui.discount_value') }}</label>
                                    <div class="input-group">
                                        <input type="number" id="discount_amount" name="discount_amount"
                                               class="form-control" value="{{ old('discount_amount', $prefill['discount_amount']) }}"
                                               min="0" step="0.01">
                                        {{-- بالنصّ لا بالرمز وحده: صندوقٌ ضيّق برمزٍ
                                             مجرَّد يبدو فارغاً فلا يُعرف المختار --}}
                                        <select name="discount_type" class="form-control"
                                                style="flex:0 0 auto;width:auto;min-width:8.5rem">
                                            <option value="amount">
                                                {{ __('sales_invoice.ui.discount_as_amount', ['symbol' => $symbol]) }}
                                            </option>
                                            <option value="percentage">
                                                {{ __('sales_invoice.ui.discount_as_percent') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- كان هنا تبويب «ردّ المبلغ»: طريقةُ دفعٍ وخزينة.
                         حُذف بطلب المستخدم — والإشعارُ الدائن في هذا النظام
                         **يُسوّي ذمّة العميل** ولا يصرف نقداً: قيدُه يُنقص
                         مديونيته ولا يمسّ خزينة، ولم يكن الحقلان يُنشئان
                         حركةً في خزينةٍ أصلاً (يُحفظان على صفّ الإشعار
                         ويُقرآن للعرض). والعمودان باقيان فتُعرَض بهما
                         الإشعاراتُ القديمة، ويصلان `null` من هذه الشاشة. --}}

                    {{-- التوصيل --}}
                    <div id="section-shipping" class="tab-section d-none">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="sl-field">
                                    <label for="methodSelect">{{ __('sales_invoice.tax1') }}</label>
                                    <select class="form-control" id="methodSelect" name="shipping_tax_id">
                                        <option value="">{{ __('sales_invoice.none') }}</option>
                                        @foreach ($taxs as $tax)
                                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                @selected(old('shipping_tax_id', $prefill['shipping_tax_id']) == $tax->id)>{{ $tax->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="sl-field">
                                    <label for="shipping">{{ __('sales_invoice.ui.shipping') }}</label>
                                    <input type="number" class="form-control" name="shipping_cost" id="shipping"
                                           value="{{ old('shipping_cost', $prefill['shipping_cost']) }}" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- المرفقات --}}
                    <div id="section-documents" class="tab-section d-none">
                        <label class="sl-upload" for="uploadFile">
                            <i class="feather icon-upload-cloud"></i>
                            <p class="sl-upload__title">{{ __('sales_invoice.ui.upload_new') }}</p>
                            <p class="sl-upload__hint">{{ __('sales_invoice.ui.upload_hint') }}</p>
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
                        <i class="feather icon-edit-3"></i> {{ __('sales_invoice.form.notes') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <textarea id="tinyMCE" name="notes" class="form-control" rows="5">{{ old('notes') }}</textarea>
                </div>

                <div class="sl-doc-card__foot">
                    <a href="{{ $original ? route('invoices.show', $original->id) : route('CreditNotes.index') }}"
                       class="btn btn-outline-secondary">
                        <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                        {{ __('sales_credit_note.form.back') }}
                    </a>

                    <div class="sl-toolbar sl-toolbar--end">
                        <button type="button" class="btn btn-outline-danger" onclick="clearAllItems()">
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @php $invoiceCalculatorVersion = @filemtime(public_path('assets/js/invoice-calculator.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/invoice-calculator.js') }}?v={{ $invoiceCalculatorVersion }}"></script>

    {{-- الضريبة الشاملة على المستند — تكوينُ الـ JS وتحميل حاسبتها --}}
    @include('partials.comprehensive-tax-script')

    <script>
        /* ═══════════ وحدات المنتجات ═══════════

           نفس سلوك شاشة الفاتورة: اختيارُ منتجٍ له وحدات يُظهر القائمة
           ويملؤها ويختار الافتراضية، ومنتجٌ بلا وحدات يُخفيها ويعيد معامل
           التحويل إلى ١.

           والمفتاحُ في القيمة إمّا `product_unit_id` رقماً، وإمّا
           `category_<id>` حين تكون الوحدة من وحدات الصنف بلا صفٍّ خاصّ
           بالمنتج — وتلك لا معرّف لها يُخزَّن، فيُحفظ اسمُها ومعاملها. */
        const creditUnitsData = {};

        (function () {
            const holder = document.getElementById('products-with-units-data');

            if (! holder) return;

            try {
                JSON.parse(holder.value || '[]').forEach(function (product) {
                    creditUnitsData[product.id] = product;
                });
            } catch (e) {
                /* بياناتٌ معطوبة لا تُسقط الشاشة: تبقى القوائم مخفيّةً
                   والكميةُ بالوحدة الأساسية كما كانت قبل هذه الإضافة */
                console.warn('تعذّرت قراءة وحدات المنتجات:', e);
            }
        })();

        function handleCreditProductChange(selectElement) {
            const row = selectElement.closest('.item-row');

            if (! row) return;

            const unitSelect = row.querySelector('.unit-select');
            const factorInput = row.querySelector('.conversion-factor');
            const nameInput = row.querySelector('.unit-name');
            const priceInput = row.querySelector('.price');

            if (! unitSelect) return;

            unitSelect.innerHTML = '<option value="">' + @json(__('sales_invoice.ui.base_unit')) + '</option>';

            if (factorInput) factorInput.value = '1';
            if (nameInput) nameInput.value = '';

            const product = creditUnitsData[selectElement.value];

            if (! product || ! product.has_units || ! product.units || ! product.units.length) {
                unitSelect.style.display = 'none';
                unitSelect.disabled = true;

                return;
            }

            product.units.forEach(function (unit) {
                const option = document.createElement('option');

                option.value = unit.id || ('category_' + unit.category_unit_id);
                option.setAttribute('data-price', unit.sale_price);
                option.setAttribute('data-factor', unit.conversion_factor);
                option.setAttribute('data-name', unit.name);
                option.textContent = unit.name
                    + (unit.is_base_unit ? ' (' + @json(__('sales_invoice.ui.base_unit')) + ')' : '')
                    + (unit.conversion_factor != 1 ? ' × ' + unit.conversion_factor : '');

                unitSelect.appendChild(option);

                if (unit.is_default) {
                    option.selected = true;

                    if (factorInput) factorInput.value = unit.conversion_factor;
                    if (nameInput) nameInput.value = unit.name;

                    /* السعرُ يُملأ من الوحدة إن كان الحقلُ فارغاً أو صفراً
                       وحده: سعرٌ كتبه المستخدم لا يُدهَس بتغيير الوحدة */
                    if (priceInput && unit.sale_price > 0 && (! priceInput.value || parseFloat(priceInput.value) === 0)) {
                        priceInput.value = parseFloat(unit.sale_price).toFixed(2);
                    }
                }
            });

            unitSelect.style.display = 'block';
            unitSelect.disabled = false;
        }

        function handleCreditUnitChange(unitSelect) {
            const row = unitSelect.closest('.item-row');

            if (! row) return;

            const option = unitSelect.options[unitSelect.selectedIndex];
            const factorInput = row.querySelector('.conversion-factor');
            const nameInput = row.querySelector('.unit-name');
            const priceInput = row.querySelector('.price');

            const factor = option ? parseFloat(option.getAttribute('data-factor') || '1') : 1;
            const price = option ? parseFloat(option.getAttribute('data-price') || '0') : 0;

            if (factorInput) factorInput.value = isNaN(factor) ? 1 : factor;
            if (nameInput) nameInput.value = option ? (option.getAttribute('data-name') || '') : '';

            // تغييرُ الوحدة يغيّر السعر: الكرتون ليس بسعر الحبّة
            if (priceInput && price > 0) {
                priceInput.value = price.toFixed(2);
            }

            if (typeof calculateTotals === 'function') calculateTotals();
        }

        /* صفوفٌ جاءت معبّأةً (من الفاتورة المرجعية أو بعد رفض الحفظ): تُملأ
           قائمةُ وحداتها وتُختار وحدةُ البند نفسُها. والسعرُ ومعاملُ التحويل
           يبقيان كما جاءا — هما ما بيع به البند، لا سعرُ الوحدة اليوم. */
        function restoreCreditRowUnits() {
            document.querySelectorAll('#items-table .item-row').forEach(function (row) {
                const productSelect = row.querySelector('.product-select');
                const unitSelect = row.querySelector('.unit-select');

                if (! productSelect || ! productSelect.value || ! unitSelect) return;

                const factorInput = row.querySelector('.conversion-factor');
                const nameInput = row.querySelector('.unit-name');
                const priceInput = row.querySelector('.price');
                const kept = {
                    factor: factorInput ? factorInput.value : '',
                    name: nameInput ? nameInput.value : '',
                    price: priceInput ? priceInput.value : '',
                };

                handleCreditProductChange(productSelect);

                const wanted = unitSelect.dataset.selected || '';
                const match = Array.from(unitSelect.options).find(function (option) {
                    return (wanted && option.value === wanted)
                        || (! wanted && kept.name && option.getAttribute('data-name') === kept.name);
                });

                if (match) {
                    unitSelect.value = match.value;
                } else if (wanted || kept.name) {
                    unitSelect.value = '';
                }

                if (factorInput && kept.factor) factorInput.value = kept.factor;
                if (nameInput) nameInput.value = kept.name;
                if (priceInput && kept.price) priceInput.value = kept.price;
            });

            if (typeof calculateTotals === 'function') calculateTotals();
        }

        /** الموقع الحالي للموظّف — يفحصه الخادم قبل قبول الإشعار */
        function getEmployeeCurrentLocation() {
            if (!navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    document.getElementById('current_latitude').value = position.coords.latitude;
                    document.getElementById('current_longitude').value = position.coords.longitude;
                },
                function (error) {
                    console.warn('تعذّر جلب الموقع:', error.message);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        document.addEventListener('DOMContentLoaded', function () {
            getEmployeeCurrentLocation();

            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: @json(__('sales_settings.ui.validation_failed')),
                    html: @json(session('error')),
                    confirmButtonText: @json(__('sales_invoice.ui.ok')),
                });
            @endif

            const clientSelect = document.getElementById('clientSelect');
            if (clientSelect && clientSelect.value) showClientBalance(clientSelect);

            /* ترقيم الصفوف يُعاد بعد كل إضافة أو حذف: السكربت المشترك
               يستنسخ الصفّ الأوّل بما فيه رقمه */
            const itemsTable = document.getElementById('items-table');

            if (itemsTable) {
                const renumber = function () {
                    itemsTable.querySelectorAll('.item-row').forEach(function (row, index) {
                        const badge = row.querySelector('.sl-row__index');
                        if (badge) badge.textContent = index + 1;
                    });
                };

                new MutationObserver(renumber).observe(itemsTable.querySelector('tbody'), { childList: true });
                renumber();
            }

            const uploadInput = document.getElementById('uploadFile');
            const uploadList = document.getElementById('uploadFileList');

            if (uploadInput && uploadList) {
                uploadInput.addEventListener('change', function () {
                    uploadList.innerHTML = '';

                    Array.from(this.files).forEach(function (file) {
                        const li = document.createElement('li');
                        li.textContent = file.name;
                        uploadList.appendChild(li);
                    });
                });
            }

            /* اختيار فاتورة مرجعية يملأ العميل تلقائياً: الإشعار الدائن
               يتبع عميل فاتورته، فتركُ الحقلين مستقلَّين يسمح بإشعارٍ
               على عميلٍ غير عميل الفاتورة */
            const invoiceSelect = document.getElementById('invoice_id');

            /* بحدث jQuery لا `addEventListener`: الحقلُ select2، وهو يُطلق
               `change` عبر jQuery وحده فلا يسمعه مستمعٌ أصليّ — فكان اختيارُ
               الفاتورة لا يفعل شيئاً والنموذجُ يبقى فارغاً. والصفحةُ تُعاد
               بـ`?id=` فتأتي معبّأةً من الخادم: العميل والبنود بوحداتها
               والمسؤول والخصم والشحن وضريبته. */
            if (invoiceSelect) {
                $(invoiceSelect).on('change', function () {
                    if (! this.value) return;
                    window.location.search = '?id=' + encodeURIComponent(this.value);
                });
            }

            restoreCreditRowUnits();

            /* «الفاتورة المرجعية» تتبع العميل: فواتيرُه وحده، بلا ما صدر له مرتجعٌ
               أو إشعارٌ دائن — تُجلب من الخادم كلّما تبدّل العميل. كانت القائمة
               ثابتةً من تحميل الصفحة، فيبقى فيها فواتيرُ عملاءَ آخرين.
               `change.select2` يُحدِّث العرض وحده، فلا يُطلق إعادةَ التحميل أعلاه. */
            if (invoiceSelect && invoiceSelect.dataset.url) {
                const noSource = @json(__('sales_credit_note.no_source'));

                $('#clientSelect').on('change', function () {
                    const clientId = $(this).val();
                    const $select = $(invoiceSelect);

                    $select.empty().append(new Option(noSource, ''));
                    $select.val('').trigger('change.select2');

                    if (!clientId) return;

                    fetch(invoiceSelect.dataset.url + '?' + new URLSearchParams({client_id: clientId}), {
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then((r) => r.ok ? r.json() : Promise.reject(r))
                        .then((invoices) => {
                            // ردٌّ متأخّر لعميلٍ سابق لا يكتب فوق قائمة العميل الحالي
                            if ($('#clientSelect').val() !== clientId) return;

                            invoices.forEach((inv) => $select.append(new Option(inv.label, inv.id)));
                            $select.val('').trigger('change.select2');
                        })
                        .catch(() => {});
                });
            }

            initSettlementPreview();
        });

        /* ═══════════ معاينة ما سيُسدّده الإشعار ═══════════

           الإجماليُّ يكتبه `invoice-calculator.js` في `#grand-total` نصّاً، فلا
           حدثَ يُسمَع منه — يُراقَب العنصر نفسه. والعميلُ حقلُ select2 فحدثُه
           من jQuery. والطلباتُ مؤجَّلةٌ قليلاً: الكتابة في كمية بندٍ تُعيد
           الحساب مع كلّ حرف. */
        function initSettlementPreview() {
            const card = document.getElementById('settlement-card');
            const total = document.getElementById('grand-total');
            if (!card || !total) return;

            const t = @json(__('sales_credit_note.settlement'));
            const els = {
                empty: document.getElementById('settlement-empty'),
                wrap: document.getElementById('settlement-table-wrap'),
                rows: document.getElementById('settlement-rows'),
                surplus: document.getElementById('settlement-surplus'),
                surplusText: document.getElementById('settlement-surplus-text'),
            };

            const money = (v) => window.AppCurrency ? window.AppCurrency.number(v) : Number(v).toFixed(2);
            const cell = (text, cls) => {
                const td = document.createElement('td');
                td.textContent = text;
                if (cls) td.className = cls;
                return td;
            };

            let timer = null;
            let seq = 0;

            function currentInvoiceId() {
                const field = document.querySelector('[name="invoice_id"]');
                return field ? field.value : '';
            }

            function render(plan) {
                els.rows.innerHTML = '';

                const lines = [];
                if (plan.previous_pay > 0) {
                    lines.push({label: t.previous_balance, date: '—', due: plan.previous_balance, pay: plan.previous_pay});
                }
                plan.invoices.forEach((inv) => lines.push({
                    label: t.invoice.replace(':code', inv.code || inv.id),
                    date: inv.date || '—',
                    due: inv.due,
                    pay: inv.pay,
                }));

                let paid = 0;
                lines.forEach((line) => {
                    paid += line.pay;
                    const tr = document.createElement('tr');
                    tr.append(
                        cell(line.label),
                        cell(line.date),
                        cell(money(line.due), 'text-end'),
                        cell(money(line.pay), 'text-end fw-bold'),
                        cell(money(Math.max(0, line.due - line.pay)), 'text-end'),
                    );
                    els.rows.appendChild(tr);
                });

                if (lines.length) {
                    const tr = document.createElement('tr');
                    tr.append(cell(t.total, 'fw-bold'), cell(''), cell(''), cell(money(paid), 'text-end fw-bold'), cell(''));
                    els.rows.appendChild(tr);
                }

                els.wrap.classList.toggle('d-none', !lines.length);
                els.empty.classList.toggle('d-none', lines.length > 0);
                if (!lines.length) els.empty.textContent = t.nothing_open;

                els.surplus.classList.toggle('d-none', !(plan.unallocated > 0));
                els.surplusText.textContent = t.surplus.replace(':amount', money(plan.unallocated));
            }

            function refresh() {
                const clientId = $('#clientSelect').val();
                const amount = parseFloat(String(total.textContent).replace(/[^\d.-]/g, '')) || 0;

                if (!clientId || amount <= 0) {
                    els.wrap.classList.add('d-none');
                    els.surplus.classList.add('d-none');
                    els.empty.classList.remove('d-none');
                    els.empty.textContent = t.empty;
                    return;
                }

                const mine = ++seq;
                const params = new URLSearchParams({client_id: clientId, amount: amount, invoice_id: currentInvoiceId()});

                fetch(card.dataset.url + '?' + params, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                    .then((r) => r.ok ? r.json() : Promise.reject(r))
                    .then((plan) => { if (mine === seq) render(plan); })
                    .catch(() => {});
            }

            function schedule() {
                clearTimeout(timer);
                timer = setTimeout(refresh, 400);
            }

            new MutationObserver(schedule).observe(total, {childList: true, characterData: true, subtree: true});
            $('#clientSelect').on('change', schedule);
            schedule();
        }

        /** رصيد العميل المختار — بعملة النظام */
        window.showClientBalance = function (selectElement) {
            const card = document.getElementById('clientBalanceCard');
            if (!card) return;

            if (!selectElement || !selectElement.value || selectElement.selectedIndex === 0) {
                card.style.display = 'none';
                return;
            }

            const option = selectElement.options[selectElement.selectedIndex];
            const balance = parseFloat(option.getAttribute('data-balance')) || 0;

            document.getElementById('clientName').textContent = option.text.split(' - ')[0];
            document.getElementById('clientBalance').textContent = window.AppCurrency
                ? window.AppCurrency.number(Math.abs(balance))
                : Math.abs(balance).toFixed(2);

            const status = document.getElementById('balanceStatus');

            if (balance > 0) {
                status.textContent = @json(__('sales_invoice.ui.creditor'));
                status.className = 'text-success';
            } else if (balance < 0) {
                status.textContent = @json(__('sales_invoice.ui.debtor'));
                status.className = 'text-danger';
            } else {
                status.textContent = @json(__('sales_invoice.ui.balanced'));
                status.className = 'text-warning';
            }

            card.style.display = 'flex';
        };

        /* حقول الأرقام بالتفويض: الصفوف تُستنسخ بعد التحميل، فربطُ
           الحقول مرّةً واحدة يترك الصفوف الجديدة بلا تحقّق */
        document.addEventListener('input', function (e) {
            if (!e.target.matches('.price, .quantity, .discount-amount')) return;

            let value = e.target.value.replace(/[^\d.]/g, '');
            const parts = value.split('.');

            if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
            if (parts[1] && parts[1].length > 2) value = parseFloat(value).toFixed(2);

            e.target.value = value;
        }, true);
    </script>
@endsection
