{{--
    إنشاء فاتورة دورية (اشتراك).

    أُعيد بناء الشاشة على نظام تصميم المبيعات (`sl-*` و`sl-doc-*`) — نفسه
    الذي تُبنى به شاشة «إنشاء فاتورة مبيعات»، فتخرج الاثنتان على قياس
    واحد. الترتيب: ترويسة ← الجدولة ← بيانات الاشتراك والعميل ← البنود ←
    الخصم والتوصيل والمرفقات.

    ما تأتي به الإعدادات:
      • الرقم           ← «تسلسل أرقام المستندات» (القسم `periodic-invoice`)
      • العملة والكسور  ← «العملات»
      • الضريبة ونوعها  ← «إعدادات الضريبة» (متضمَّنة/مضافة)
      • طرق الدفع       ← «طرق الدفع» وخزائنها
      • الإقفال         ← «الفترات المحاسبية» (تنبيهٌ في الرأس)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    **عقد الـDOM محفوظ كما هو.** `public/assets/js/invoice-calculator.js`
    مشتركٌ بين أربع شاشات ويعتمد على مُعرِّفات وأصناف بعينها:
    `#items-table` و`.item-row` و`.product-select` و`.price` و`#add-row`
    و`#tax-rows`. فأُعيد الشكلُ ولم تُمسّ هذه الأسماء.

    ما أُصلح:
      • *لا حقل مستودع إطلاقاً* — والمتحكّم يقرأ `store_house_id`، والبنود
        كانت تحمل `value="2"` مثبَّتة في الوسم.
      • *الضريبة حقلٌ رقميّ حرّ* قيمته `15` مكتوبة، فلا يصل الخادمَ معرّفُ
        الضريبة ومنه وحده يُعرَف نوعها. وللبند ضريبةٌ واحدة: أُلغيت
        الضريبة الثانية بطلب المستخدم.
      • *لا حقل وحدة* — فتصدر الفواتير بوحدةٍ مجهولة ومعامل تحويلٍ واحد.
      • *كل النصوص مكتوبةٌ بالعربية في القالب* بلا ترجمة، ومنها «القيمة
        المضافة (15%)» — نسبةٌ مثبَّتة في وسم الخيار.
      • *حقول زخرفية بلا `name`*: «عنوان إضافي» و«بيانات إضافية» وزرّ
        يضيفهما — لا تُرسَل ولا تُحفَظ.
--}}

@extends('sales::master')

@section('title', __('sales_periodic.create_title'))

@section('content')
    {{-- من أجل `.sl-guide` و`.sl-help`: `document-theme` لا تعرّفهما،
         وكلُّ قواعد `settings-theme` `.sl-`ـيّة ولا تتقاطع معها --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme')
    @include('sales::partials.document-theme-po')

    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $symbol = CurrencySettings::symbol();
        $taxList = $taxs ?? collect();
        $defaultTax = $taxList->first();
    @endphp

    <div class="content-body sl-scope sl-doc sl-doc--po">

        @section('page-actions')
            <a href="{{ route('periodic_invoices.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                {{ __('sales_periodic.form.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_periodic.create_title'),
            'icon' => 'repeat',
            'sub' => __('sales_periodic.index_subtitle'),
            'crumbs' => [
                __('sales_documents.ui.home') => route('dashboard'),
                __('sales_periodic.plural') => route('periodic_invoices.index'),
                __('sales_periodic.create_title') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-periodic.create',
            'title' => __('sales_periodic.guide.create.title'),
            'lead' => __('sales_periodic.guide.lead_create'),
            'open' => false,
            'blocks' => [
                __('sales_periodic.guide.effects.title') => [
                    __('sales_periodic.guide.effects.a'),
                    __('sales_periodic.guide.effects.b'),
                    __('sales_periodic.guide.effects.c'),
                ],
                __('sales_periodic.guide.money_flow.title') => [
                    __('sales_periodic.guide.money_flow.a'),
                    __('sales_periodic.guide.money_flow.b'),
                    __('sales_periodic.guide.money_flow.c'),
                ],
                __('sales_periodic.guide.statuses.title') => [
                    __('sales_periodic.guide.statuses.a'),
                    __('sales_periodic.guide.statuses.b'),
                ],
                __('sales_periodic.guide.what_is.title') => [
                    __('sales_periodic.guide.what_is.a'),
                    __('sales_periodic.guide.what_is.b'),
                    __('sales_periodic.guide.what_is.c'),
                ],
                __('sales_periodic.guide.noedit.title') => [
                    __('sales_periodic.guide.noedit.a'),
                    __('sales_periodic.guide.noedit.b'),
                ],
            ],
            'facts' => [
                __('sales_periodic.guide.facts.a'),
                __('sales_periodic.guide.facts.b'),
                __('sales_periodic.guide.facts.c'),
            ],
        ])

        @unless (hasPermission('sales.periodic_invoices.create'))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-slash"></i></span>
                <div class="sl-doc-note__body">{{ __('sales_periodic.create.no_permission') }}</div>
            </div>
        @else

        {{-- الإقفال المحاسبي — يُقال في الرأس لا عند الضغط على حفظ --}}
        @if (! empty($periodBlock))
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-lock"></i></span>
                <div class="sl-doc-note__body">{{ $periodBlock }}</div>
            </div>
        @endif

        <form id="periodic-invoice-form" action="{{ route('periodic_invoices.store') }}" method="POST"
              enctype="multipart/form-data">
            @csrf

            {{-- ══════════ الجدولة ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-calendar"></i> {{ __('sales_periodic.create.schedule') }}
                    </h6>
                    <span class="sl-hint">{{ __('sales_periodic.create.schedule_hint') }}</span>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="sl-field">
                                <label for="first_invoice_date">
                                    {{ __('sales_periodic.create.first_date') }} <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="first_invoice_date"
                                       name="first_invoice_date" required
                                       value="{{ old('first_invoice_date', date('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="sl-field">
                                <label for="repeat_type">
                                    {{ __('sales_periodic.create.repeat_every') }} <span class="required">*</span>
                                </label>

                                <div class="input-group">
                                    <input type="number" class="form-control" name="repeat_interval"
                                           min="1" value="{{ old('repeat_interval', 1) }}" style="max-width:5.5rem">

                                    <select class="form-control" id="repeat_type" name="repeat_type" required>
                                        @foreach (\App\Models\PeriodicInvoice::REPEAT_TYPES as $key => $slug)
                                            <option value="{{ $key }}" @selected(old('repeat_type', 3) == $key)>
                                                {{ __('sales_periodic.repeat_types.' . $key) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="sl-field">
                                <label for="repeat_count">{{ __('sales_periodic.create.repeat_count') }}</label>
                                <input type="number" class="form-control" id="repeat_count" name="repeat_count"
                                       min="1" value="{{ old('repeat_count', 12) }}">
                                <small class="sl-help">{{ __('sales_periodic.create.repeat_count_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="sl-field">
                                <label for="end_date">{{ __('sales_periodic.create.end_date') }}</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       value="{{ old('end_date') }}">
                                <small class="sl-help">{{ __('sales_periodic.create.end_date_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="sl-field">
                                <label for="invoice_days_offset">{{ __('sales_periodic.create.offset') }}</label>

                                <div class="input-group">
                                    <input type="number" class="form-control" id="invoice_days_offset"
                                           name="invoice_days_offset" min="0" value="{{ old('invoice_days_offset', 0) }}">
                                    <span class="input-group-text">{{ __('sales_periodic.create.offset_unit') }}</span>
                                </div>
                                <small class="sl-help">{{ __('sales_periodic.create.offset_hint') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="sl-section mt-3">
                        <div class="sl-section__head">
                            <i class="feather icon-sliders"></i>
                            <h6 class="sl-section__title">{{ __('sales_periodic.create.options') }}</h6>
                        </div>

                        <div class="row g-3">
                            @php
                                $switches = [
                                    ['is_active', 'form.is_active', true],
                                    ['auto_generate', 'form.auto_generate', true],
                                    ['send_copy', 'form.send_copy', false],
                                    ['show_from_to_dates', 'form.show_dates', false],
                                    ['disable_partial_payment', 'form.disable_partial', false],
                                ];
                            @endphp

                            @foreach ($switches as [$name, $labelKey, $default])
                                <div class="col-md-4">
                                    @include('sales::components.switch', [
                                        'name' => $name,
                                        'id' => $name,
                                        'checked' => (bool) old($name, $default),
                                        'title' => __('sales_periodic.' . $labelKey),
                                    ])
                                </div>
                            @endforeach
                        </div>

                        <small class="sl-help d-block mt-2">{{ __('sales_periodic.form.auto_generate_hint') }}</small>
                    {{-- «شروط الدفع» لم تعد حقلاً: تاريخ الجدول هو الاستحقاق نفسُه --}}
                    <small class="sl-help d-block mt-2">
                        <i class="feather icon-info"></i> {{ __('sales_periodic.create.due_explained') }}
                    </small>

                    </div>
                </div>
            </div>

            {{-- ══════════ بيانات الاشتراك والعميل ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-user"></i> {{ __('sales_periodic.create.document') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="clientSelect">
                                    {{ __('sales_periodic.create.client') }} <span class="required">*</span>
                                </label>
                                <div class="sl-select-add">
                                <select class="form-control select2" id="clientSelect" name="client_id" required>
                                    <option value="">{{ __('sales_periodic.create.select_client') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                                            {{ $client->trade_name ?: trim($client->first_name . ' ' . $client->last_name) }}
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

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="invoice_number">{{ __('sales_periodic.create.number') }}</label>
                                <input type="text" class="form-control sl-num" id="invoice_number"
                                       name="invoice_number" value="{{ old('invoice_number', $invoice_number) }}" readonly>
                                <small class="sl-help">{{ __('sales_periodic.create.number_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="employee_id">{{ __('sales_periodic.create.employee') }}</label>
                                <select class="form-control select2" id="employee_id" name="employee_id">
                                    <option value="">{{ __('sales_periodic.create.select_employee') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                            {{ $employee->full_name ?? $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- المستودع — لم يكن في النموذج حقلٌ له إطلاقاً،
                             والبنود تحمل رقماً مثبَّتاً في الوسم --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="store_house_id">
                                    {{ __('sales_periodic.create.warehouse') }} <span class="required">*</span>
                                </label>
                                <select class="form-control" id="store_house_id" name="store_house_id" required>
                                    <option value="">{{ __('sales_periodic.create.select_warehouse') }}</option>
                                    @foreach ($store_houses as $store)
                                        <option value="{{ $store->id }}"
                                            @selected(old('store_house_id', $store_houses->count() === 1 ? $store->id : null) == $store->id)>
                                            {{ $store->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="sl-help">{{ __('sales_periodic.create.warehouse_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="payment_method">{{ __('sales_periodic.create.payment_method') }}</label>
                                <select class="form-control" id="payment_method" name="payment_method">
                                    <option value="">{{ __('sales_periodic.create.select_payment_method') }}</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->id }}"
                                            @selected(old('payment_method', $paymentMethods->firstWhere('is_default', true)?->id) == $method->id)>
                                            {{ $method->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>


                        {{-- الإيداع — الحقول نفسها التي في شاشة «إنشاء
                             فاتورة مبيعات»، والمعنى نفسُه: ما يُقبَض عند
                             إصدار كلّ نسخة. منه يحسب المولّد حالةَ الفاتورة
                             ومستحقَّها، فحلّ محلَّ مُنتقي «نوع السداد»
                             بقيمتيه — ومن يقبض ثلث القيمة لم يكن يجد في
                             تينك القيمتين ما يقوله --}}
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="advance_payment">{{ __('sales_periodic.create.deposit') }}</label>

                                <div class="input-group">
                                    <input type="number" class="form-control" id="advance_payment"
                                           name="advance_payment" min="0" step="0.01" placeholder="0.00"
                                           value="{{ old('advance_payment', 0) }}">
                                    <select name="advance_payment_type" id="advance_payment_type"
                                            class="form-control" style="flex:0 0 auto;width:5.5rem">
                                        <option value="amount">{{ $symbol }}</option>
                                        <option value="percentage" @selected(old('advance_payment_type') === 'percentage')>%</option>
                                    </select>
                                </div>

                                {{-- سببُ القفل مكتوبٌ لا مُستنتَج: حقلٌ لا
                                     يُكتب فيه بلا تفسيرٍ يُقرأ عطلاً --}}
                                <small id="deposit-lock-hint" class="sl-help d-none">
                                    <i class="feather icon-lock"></i>
                                    {{ __('sales_periodic.create.deposit_locked') }}
                                </small>

                                <small class="sl-help">{{ __('sales_periodic.create.deposit_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="sl-field w-100">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_paid" value="1"
                                           id="is_paid"
                                           @checked(old('is_paid'))>
                                    <label class="form-check-label" for="is_paid">
                                        {{ __('sales_periodic.create.fully_paid') }}
                                    </label>
                                </div>
                                <small class="sl-help">{{ __('sales_periodic.create.fully_paid_hint') }}</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="treasury_id">{{ __('sales_periodic.create.treasury') }}</label>
                                <select class="form-control" id="treasury_id" name="treasury_id">
                                    <option value="">{{ __('sales_periodic.create.select_treasury') }}</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}" @selected(old('treasury_id') == $treasury->id)>
                                            {{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="sl-field">
                                <label for="details_subscription">{{ __('sales_periodic.create.details') }}</label>
                                <input type="text" class="form-control" id="details_subscription"
                                       name="details_subscription" value="{{ old('details_subscription') }}"
                                       placeholder="{{ __('sales_periodic.create.details_placeholder') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ البنود ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-package"></i> {{ __('sales_periodic.create.items') }}
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table sl-doc-table mb-0" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:46px">{{ __('sales_documents.items.row') }}</th>
                                <th style="min-width:200px">
                                    {{ __('sales_periodic.items.item') }} <span class="required">*</span>
                                </th>
                                <th style="min-width:150px">{{ __('sales_documents.items.description') }}</th>
                                <th style="width:132px">
                                    {{ __('sales_documents.items.quantity') }} <span class="required">*</span>
                                </th>
                                {{-- الوحدة عمودٌ قائمٌ بذاته لا سطرٌ تحت الصنف —
                                     كجدول «إنشاء فاتورة مبيعات»: كانت القائمة
                                     تُحشر أسفل مُنتقي الصنف فيطول أوّلُ عمودٍ
                                     ويتفاوت ارتفاع الصفوف، والكميةُ تُقرأ بلا
                                     وحدتها في العمود المجاور --}}
                                <th style="width:150px">{{ __('sales_periodic.items.unit') }}</th>
                                <th style="width:130px">{{ __('sales_documents.items.unit_price') }}</th>
                                {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                                <th style="width:170px">{{ __('sales_periodic.items.tax_1') }}</th>
                                <th class="num" style="width:120px">{{ __('sales_documents.items.total') }}</th>
                                <th style="width:46px"></th>
                            </tr>
                        </thead>

                        <tbody>
                            {{-- الصفّ الأوّل هو القالب الذي يستنسخه السكربت عند
                                 «إضافة بند»، فترتيب حقوله وأصنافها عقدٌ معه --}}
                            <tr class="item-row">
                                <td class="num"><span class="sl-row__index">1</span></td>

                                <td>
                                    <div class="sl-select-add">
                                        <select name="items[0][product_id]" class="form-control product-select" required
                                                onchange="handlePeriodicProductChange(this)">
                                            <option value="">{{ __('sales_periodic.items.select_item') }}</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}" data-price="{{ $item->sale_price ?? $item->price }}">
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

                                    <input type="hidden" name="items[0][conversion_factor]" class="conversion-factor" value="1">
                                    <input type="hidden" name="items[0][quantity_in_base_unit]" class="quantity-in-base-unit" value="0">
                                    <input type="hidden" name="items[0][unit_name]" class="unit-name" value="">
                                    <input type="hidden" name="items[0][store_house_id]" class="item-store-house" value="">
                                </td>

                                <td>
                                    <input type="text" name="items[0][description]" class="form-control item-description"
                                           placeholder="{{ __('sales_documents.items.description') }}">
                                </td>

                                <td>
                                    {{-- كميةٌ كسريّة: `invoice_items.quantity` عمودٌ
                                         عشريّ، فمن يبيع بالكيلو أو بالساعة يكتب
                                         `1.5` ولا تُبتَر. `step="any"` لا `0.01` —
                                         أربع خاناتٍ يقبلها العمود، وخطوةٌ مقيَّدة
                                         تردّها الشاشة قبل أن يراها الخادم --}}
                                    <input type="number" name="items[0][quantity]" class="form-control quantity"
                                           value="1" min="0.0001" step="any" inputmode="decimal" required>
                                </td>

                                {{-- الوحدة — مخفيّةٌ حتى يُختار منتجٌ له وحدات.
                                     منتجٌ بلا وحدات لا يُسأل عن اختيارٍ لا خيار
                                     فيه، فيبقى العمود فارغاً في صفّه --}}
                                <td>
                                    <select name="items[0][product_unit_id]" class="form-control unit-select"
                                            onchange="handlePeriodicUnitChange(this)" style="display:none">
                                        <option value="">{{ __('sales_periodic.items.base_unit') }}</option>
                                    </select>

                                    {{-- «= ٢٤٠٠ حبة» — ما تُساويه الكمية بالوحدة
                                         الأساسية، كسطر شاشة الفاتورة نفسِه --}}
                                    <small class="base-unit-quantity sl-hint-line" style="display:none"></small>
                                </td>

                                <td>
                                    <input type="text" name="items[0][unit_price]" class="form-control price"
                                           value="" inputmode="decimal" required placeholder="0.00">
                                </td>

                                {{-- خصمُ البند أُزيل من الشاشة: الخصمُ من تبويب «الخصم والتسوية»
                                     وحده. الحقولُ مخفيّةٌ بصفر لأنّ الحاسبة والمتحكّم يقرآنها بالاسم. --}}
                                <input type="hidden" name="items[0][discount]" class="discount-amount" value="0">
                                <input type="hidden" name="items[0][discount_percentage]" class="discount-percentage" value="0">
                                <input type="hidden" name="items[0][discount_type]" class="discount-type" value="amount">

                                {{-- ضريبةٌ واحدة لكل بند — أُلغيت الضريبة الثانية
                                     بطلب المستخدم، فصار للبند ضريبةٌ كضريبة
                                     البند في شاشة «إنشاء فاتورة مبيعات».

                                     والاصطلاح صار اصطلاحَها نفسه: **قيمة
                                     القائمة هي النسبة** والمعرّف في الحقل
                                     المخفيّ. كان معكوساً — القائمة تحمل
                                     المعرّف والمخفيّ يحمل النسبة — و
                                     `invoice-calculator.js` يبحث عن
                                     `[name$='[tax_1]']` فيقع على حقلٍ
                                     مخفيّ لا قائمة، ثمّ يقرأ `options`
                                     منه فيرمي TypeError: فلا تُحسب
                                     إجماليات الشاشة إطلاقاً. --}}
                                <td>
                                    <select name="items[0][tax_1]" class="form-control tax-select"
                                            data-target="tax_1" onchange="updateHiddenInput(this)">
                                        <option value="">{{ __('sales_periodic.items.no_tax') }}</option>
                                        @foreach ($taxList as $tax)
                                            <option value="{{ $tax->tax }}" data-id="{{ $tax->id }}"
                                                    data-name="{{ $tax->name }}" data-type="{{ $tax->type }}"
                                                @selected($loop->first)>
                                                {{ $tax->name }} ({{ $tax->tax + 0 }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="items[0][tax_1_id]"
                                           value="{{ $defaultTax->id ?? '' }}">
                                </td>

                                <td class="num"><span class="row-total sl-line-total">0.00</span></td>

                                <td class="num">
                                    <button type="button" class="btn sl-row__remove remove-row"
                                            aria-label="{{ __('sales_documents.items.remove_row') }}"
                                            title="{{ __('sales_documents.items.remove_row') }}">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
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
                        <i class="feather icon-plus"></i> {{ __('sales_documents.items.add_row') }}
                    </button>

                    <div class="sl-totals">
                        <div class="sl-totals__row">
                            <span>{{ __('sales_documents.totals.subtotal') }}</span>
                            <span id="subtotal">{{ CurrencySettings::format(0) }}</span>
                        </div>
                        <div class="sl-totals__row">
                            <span>{{ __('sales_documents.totals.discount') }}</span>
                            <span id="total-discount">{{ CurrencySettings::format(0) }}</span>
                        </div>
                        <div class="sl-totals__row">
                            <span>{{ __('sales_documents.totals.shipping') }}</span>
                            <span id="shipping-cost">{{ CurrencySettings::format(0) }}</span>
                        </div>

                        {{-- ضريبة البنود وضريبة الشحن سطران: الشحن ضريبتُه مستقلّة
                             ويُرحَّل إلى حسابه («ضريبة شحن مبيعات»). `#tax-total`
                             مخفيٌّ يبقى لأنّ الحاسبة تكتب فيه --}}
                        <div class="sl-totals__row">
                            <span>{{ __('sales_periodic.totals.items_tax') }}</span>
                            <span id="items-tax-total">0.00</span>
                        </div>
                        <div class="sl-totals__row">
                            <span>{{ __('sales_periodic.totals.shipping_tax') }}</span>
                            <span id="shipping-tax">0.00</span>
                        </div>
                        <span class="d-none" id="tax-total">0.00</span>

                        <div class="sl-totals__row sl-totals__row--grand">
                            <span>{{ __('sales_documents.totals.grand_total') }}</span>
                            <span id="grand-total">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ الخصم والتوصيل والمرفقات ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head sl-doc-card__head--flush">
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#section-discount" role="tab">
                                <i class="feather icon-percent"></i> {{ __('sales_periodic.create.discount') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-shipping" role="tab">
                                <i class="feather icon-truck"></i> {{ __('sales_periodic.create.shipping') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-documents" role="tab">
                                <i class="feather icon-paperclip"></i> {{ __('sales_periodic.create.attachments') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-notes" role="tab">
                                <i class="feather icon-file-text"></i> {{ __('sales_periodic.create.notes') }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="section-discount" role="tabpanel">
                        <div class="sl-doc-card__body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="discount_amount">{{ __('sales_periodic.create.discount') }}</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="discount_amount"
                                                   name="discount_amount" value="{{ old('discount_amount', 0) }}"
                                                   min="0" step="0.01">
                                            <select name="discount_type" class="form-control" style="max-width:6rem">
                                                <option value="amount">{{ $symbol }}</option>
                                                <option value="percentage" @selected(old('discount_type') === 'percentage')>%</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="section-shipping" role="tabpanel">
                        <div class="sl-doc-card__body">
                            <div class="row g-3">
                                {{-- «نوع الضريبة» (خاضع/صفري/معفى) أُسقط بطلب
                                     المستخدم: كان مُنتقياً في تبويب **الشحن**
                                     يحكم ضريبة المستند كلِّه لا شحنَه وحده،
                                     وضريبةُ كلّ بندٍ مختارةٌ في صفّه أصلاً.
                                     فالإعفاء يكون ببندٍ «بلا ضريبة» لا بمفتاحٍ
                                     فوق الجدول يُلغيه من حيث لا يُرى --}}

                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="shipping">{{ __('sales_periodic.create.shipping_cost') }}</label>
                                        <input type="number" class="form-control" id="shipping" name="shipping_cost"
                                               value="{{ old('shipping_cost', 0) }}" min="0" step="0.01">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="shipping_tax_id">{{ __('sales_periodic.create.shipping_tax') }}</label>
                                        <select class="form-control" id="shipping_tax_id" name="shipping_tax_id">
                                            <option value="">{{ __('sales_periodic.items.no_tax') }}</option>
                                            @foreach ($taxList as $tax)
                                                <option value="{{ $tax->id }}" @selected($loop->first)>
                                                    {{ $tax->name }} ({{ $tax->tax }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="section-documents" role="tabpanel">
                        <div class="sl-doc-card__body">
                            <label class="sl-upload" for="uploadFile">
                                <i class="feather icon-upload-cloud"></i>
                                <p class="sl-upload__title">{{ __('sales_documents.upload.click') }}</p>
                                <p class="sl-upload__hint">{{ __('sales_documents.upload.hint') }}</p>
                                <input type="file" class="d-none" id="uploadFile" name="attachments[]" multiple>
                            </label>
                            <ul class="sl-help mt-2 mb-0" id="uploadFileList"></ul>
                        </div>
                    </div>

                    <div class="tab-pane" id="section-notes" role="tabpanel">
                        <div class="sl-doc-card__body">
                            <div class="sl-field">
                                <label for="notes">{{ __('sales_periodic.create.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="5"
                                          placeholder="{{ __('sales_periodic.create.notes_placeholder') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-doc-card__foot">
                    <div class="sl-toolbar sl-toolbar--end">
                        <a href="{{ route('periodic_invoices.index') }}" class="btn btn-outline-secondary">
                            {{ __('sales_documents.ui.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary" @disabled(! empty($periodBlock))>
                            <i class="feather icon-save"></i> {{ __('sales_periodic.create.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @endunless
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/js/invoice-calculator.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{--
        الوحدات والضرائب والمستودع.

        ثلاثة حقول لم تكن في النموذج أو كانت مثبَّتة في الوسم:

          • *الوحدة* — لم يكن لها حقلٌ أصلاً، فتصدر فواتير الاشتراك بوحدةٍ
            مجهولة ومعامل تحويلٍ واحد مهما كان المنتج يُباع بالكرتون.
          • *الضريبة* — حقلٌ رقميّ حرّ قيمته `15` مثبَّتة، فلا يصل الخادمَ
            معرّفُ الضريبة، ومنه وحده يُعرَف نوعها (متضمَّنة/مضافة).
          • *المستودع* — `value="2"` مكتوبة في الوسم لكلّ بند، ولا حقلَ
            يختاره في الشاشة أصلاً.
    --}}
    <script>
        (function () {
            'use strict';

            window.PeriodicProductUnits = @json($productUnits ?? []);

            // النصّ من ملفّ الترجمة لا مكتوباً في السكربت
            const BASE_UNIT_LABEL = @json(__('sales_periodic.items.base_unit'));

            /* عند اختيار منتج: تُملأ وحداته، وتُخفى القائمة إن لم يكن له
               إلّا وحدته الأساسية — قائمةٌ بخيارٍ واحد تشوّش ولا تفيد. */
            window.handlePeriodicProductChange = function (select) {
                const row = select.closest('.item-row');
                if (!row) return;

                const unitSelect = row.querySelector('.unit-select');
                const factor = row.querySelector('.conversion-factor');
                const unitName = row.querySelector('.unit-name');
                const units = window.PeriodicProductUnits[select.value] || [];

                if (unitSelect) {
                    unitSelect.replaceChildren();

                    const base = document.createElement('option');
                    base.value = '';
                    base.textContent = BASE_UNIT_LABEL;
                    unitSelect.appendChild(base);

                    units.forEach(function (unit) {
                        const option = document.createElement('option');
                        option.value = unit.id;
                        option.textContent = unit.name;
                        option.dataset.factor = unit.conversion_factor;
                        option.dataset.price = unit.sale_price || 0;
                        if (unit.is_base_unit) option.selected = true;
                        unitSelect.appendChild(option);
                    });

                    unitSelect.style.display = units.length ? '' : 'none';

                    if (factor) factor.value = 1;
                    if (unitName) unitName.value = '';

                }

                /* السعر يتبع المنتج المختار ووحدته — كان لا يُكتب إلّا في حقلٍ
                   فارغ، فتبديلُ المنتج يُبقي سعرَ المنتج السابق */
                if (unitSelect && units.length) {
                    handlePeriodicUnitChange(unitSelect);
                } else {
                    setPeriodicPrice(row, periodicUnitPrice(row, null));
                }
            };

            /* سعر الوحدة المختارة — قاعدة شاشة «إنشاء فاتورة مبيعات»:
               سعرُ بيع الوحدة إن ضُبط، وإلّا سعرُ المنتج مضروباً في معامل
               التحويل (كرتونٌ من ٢٤ بسعر ٢٤ حبّة)، والوحدةُ الأساسية بسعر
               المنتج. كان اختيار الوحدة يبدّل المعامل وحده ويترك السعر
               كما هو، فيُحفظ الكرتون بسعر الحبّة. */
            function periodicUnitPrice(row, option) {
                const product = row.querySelector('.product-select');
                const productOption = product ? product.options[product.selectedIndex] : null;
                const basePrice = productOption ? parseFloat(productOption.dataset.price) || 0 : 0;

                if (!option || !option.value) return basePrice;

                const unitPrice = parseFloat(option.dataset.price) || 0;
                if (unitPrice > 0) return unitPrice;

                return basePrice * (parseFloat(option.dataset.factor) || 1);
            }

            function setPeriodicPrice(row, price) {
                const priceField = row.querySelector('.price');
                if (!priceField || !(price > 0)) return;

                priceField.value = price.toFixed(2);
                // الحاسبة تستمع إلى الكتابة والتغيير معاً
                priceField.dispatchEvent(new Event('input', { bubbles: true }));
                priceField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            /* `keepPrice` للتحميل الأوّل في التعديل: السعر المحفوظ على البند
               لا يُكتب فوقه بمجرّد فتح الشاشة. */
            window.handlePeriodicUnitChange = function (select, keepPrice) {
                const row = select.closest('.item-row');
                if (!row) return;

                const option = select.options[select.selectedIndex];
                const factor = row.querySelector('.conversion-factor');
                const unitName = row.querySelector('.unit-name');

                if (factor) factor.value = (option && option.dataset.factor) ? option.dataset.factor : 1;
                if (unitName) unitName.value = (option && select.value) ? option.textContent.trim() : '';

                if (!keepPrice) {
                    setPeriodicPrice(row, periodicUnitPrice(row, option));
                }

                updatePeriodicBaseQuantity(row);
            };

            /* المستودع المختار أعلى الشاشة يسري على كل البنود */
            function syncStoreHouse() {
                const chosen = document.getElementById('store_house_id');
                if (!chosen) return;

                document.querySelectorAll('.item-store-house').forEach(function (field) {
                    field.value = chosen.value || '';
                });
            }

            /* «= ٢٤٠٠ حبة» — ما تُساويه الكمية بالوحدة الأساسية،
               وسطرُ شاشة «إنشاء فاتورة مبيعات» نفسُه. يُكتب معه الحقلُ
               المخفيّ `quantity_in_base_unit` الذي يقرؤه الخادم: بلاه
               تُحفظ كمّيةُ الكرتون رقماً بلا معنى في المخزون. */
            function updatePeriodicBaseQuantity(row) {
                if (!row) return;

                const quantityInput = row.querySelector('.quantity');
                const factorField = row.querySelector('.conversion-factor');
                const baseField = row.querySelector('.quantity-in-base-unit');
                const display = row.querySelector('.base-unit-quantity');

                const factor = parseFloat(factorField ? factorField.value : 1) || 1;
                const quantity = parseFloat(quantityInput ? quantityInput.value : 0) || 0;
                const inBase = quantity * factor;

                if (baseField) baseField.value = inBase;

                if (!display) return;

                if (factor !== 1 && quantity > 0) {
                    const unitName = row.querySelector('.unit-name');
                    const rounded = Math.round(inBase * 10000) / 10000;

                    display.style.display = '';
                    display.textContent = '= ' + rounded + ' ' + BASE_UNIT_LABEL;
                    display.title = unitName && unitName.value
                        ? unitName.value + ' × ' + factor
                        : display.textContent;
                } else {
                    display.style.display = 'none';
                }
            }

            /* «مدفوعة بالكامل» تقفل مبلغ الإيداع وتملؤه بالإجمالي —
               سلوكُ `applyFullPaymentState()` في شاشة الفاتورة نفسُه.
               والمُنتقي «﷼/٪» يُقفل معه بلا `disabled`: `disabled` يُسقط
               الحقل من الإرسال فلا يعرف الخادم نوع المبلغ. */
            function applyDepositLock() {
                const check = document.getElementById('is_paid');
                const amount = document.getElementById('advance_payment');
                const type = document.getElementById('advance_payment_type');
                const hint = document.getElementById('deposit-lock-hint');

                if (!check || !amount) return;

                const paid = check.checked;

                if (paid) {
                    const grand = document.getElementById('grand-total');
                    const value = grand
                        ? parseFloat(String(grand.textContent).replace(/[^\d.-]/g, '')) || 0
                        : 0;

                    amount.value = value.toFixed(2);
                } else if (amount.readOnly) {
                    // لا يُصفَّر إلّا ما كتبه القفلُ نفسه: مبلغٌ كتبه
                    // المستخدم قبل أن يؤشّر الخانة يعود كما تركه
                    amount.value = '0';
                }

                amount.readOnly = paid;
                amount.classList.toggle('is-locked', paid);

                if (type) {
                    if (paid) type.value = 'amount';
                    type.style.pointerEvents = paid ? 'none' : '';
                    type.tabIndex = paid ? -1 : 0;
                    type.classList.toggle('is-locked', paid);
                }

                if (hint) hint.classList.toggle('d-none', !paid);
            }

            /* ترقيم الصفوف — الصفوف تُستنسخ بعد التحميل */
            function renumber() {
                document.querySelectorAll('#items-table .item-row').forEach(function (row, index) {
                    const badge = row.querySelector('.sl-row__index');
                    if (badge) badge.textContent = index + 1;
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                syncStoreHouse();
                renumber();

                // الإيداع: القفل عند التأشير، وعند تغيّر الإجمالي بإضافة
                // بندٍ أو تعديل سعر — وعند فتح الشاشة، فالخانة قد تصل
                // مؤشَّرةً من `old()` أو من الاشتراك المحفوظ بلا حدث
                applyDepositLock();

                const fullyPaidCheck = document.getElementById('is_paid');
                if (fullyPaidCheck) fullyPaidCheck.addEventListener('change', applyDepositLock);

                const grandTotalCell = document.getElementById('grand-total');
                if (grandTotalCell) {
                    new MutationObserver(applyDepositLock)
                        .observe(grandTotalCell, { childList: true, characterData: true, subtree: true });
                }

                const chosen = document.getElementById('store_house_id');
                if (chosen) chosen.addEventListener('change', syncStoreHouse);

                const table = document.getElementById('items-table');
                if (table) {
                    new MutationObserver(function () {
                        renumber();
                        syncStoreHouse();
                    }).observe(table.querySelector('tbody'), { childList: true });
                }

                /* الكمية تُكتب فيُعاد حسابُ ما تُساويه بالوحدة الأساسية.
                   `input` لا `change` وحده: السطر يتأخّر عن الرقم حتى
                   يغادر المستخدم الحقل، فيُقرأ ثابتاً لا يتبع ما يكتب */
                document.addEventListener('input', function (event) {
                    if (event.target.classList.contains('quantity')) {
                        updatePeriodicBaseQuantity(event.target.closest('.item-row'));
                    }
                }, true);

                document.querySelectorAll('#items-table .item-row').forEach(updatePeriodicBaseQuantity);

                // الصفوف تُستنسخ بعد التحميل، فالربط بالتفويض
                document.addEventListener('change', function (event) {
                    if (event.target.classList.contains('product-select')) {
                        handlePeriodicProductChange(event.target);
                        syncStoreHouse();
                    }
                    if (event.target.classList.contains('unit-select')) {
                        handlePeriodicUnitChange(event.target);
                    }
                }, true);

                // تأكيد الحفظ — الاشتراك يُصدر فواتير بيعٍ على مواعيده
                const form = document.getElementById('periodic-invoice-form');

                if (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.dataset.confirmed === '1' || typeof Swal === 'undefined') return;

                        event.preventDefault();

                        Swal.fire({
                            title: @json(__('sales_periodic.create.confirm_title')),
                            text: @json(__('sales_periodic.create.confirm_text')),
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: @json(__('sales_periodic.create.confirm_yes')),
                            cancelButtonText: @json(__('sales_periodic.create.confirm_cancel')),
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
