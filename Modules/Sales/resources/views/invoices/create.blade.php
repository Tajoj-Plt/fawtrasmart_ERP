{{--
    إنشاء فاتورة مبيعات.

    أُعيد بناء الشاشة على قياس «إنشاء فاتورة مشتريات»
    (`purchases::purchases.invoices_purchase.create` ونموذجها المشترك):
    ترتيبٌ واحد — بيانات المستند ← البنود ← التفاصيل الإضافية ← الملاحظات
    وأزرار الحفظ — وجدول بنودٍ حقولُه داخل خلاياه، ولوحة إجماليات في ذيل
    البطاقة، وشريط أدوات واحد.

    ما تأتي به الإعدادات:
      • الرقم           ← «تسلسل أرقام المستندات» (القسم `invoice`)
      • العملة والكسور  ← «العملات»
      • نِسَب الضريبة    ← «إعدادات الضريبة»
      • الإقفال         ← «الفترات المحاسبية»: تُنبَّه الشاشة قبل الحفظ لا بعده
      • السلوك          ← «إعدادات الفواتير» (`SalesInvoiceSetting`)
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*` التي يقوم عليها `sl-*`

    **عقد الـDOM محفوظ كما هو.** `public/assets/js/invoice-calculator.js`
    مشتركٌ بين أربع شاشات (الفواتير، الإشعار الدائن، الفواتير الدورية،
    الفاتورة المرتجعة)، ويعتمد على مُعرِّفات وأصناف بعينها: `#items-table`
    و`.item-row` و`.product-select` و`.price` و`#add-row` و`#tax-rows`
    وأسماء عناصر الإجماليات. فأُعيد الشكلُ ولم تُمسّ هذه الأسماء.

    ملاحظة على عدد الأعمدة: صفوف «خصم إضافي» و«التسوية» التي يحقنها
    السكربت كانت تُبنى بـ`colspan="7"` ثم خليّتين — تسعةً ثابتة. وصار
    الجدول عشرة بعد إضافة عمود «الوحدة»، فيقرأ `insertTaxRow()` العددَ
    من `thead` بدل أن يكتبه رقماً: الملف مشتركٌ بين أربع شاشات لا تتّفق
    على عدد أعمدتها.
--}}

@extends('sales::master')

@section('title', __('sales_invoice.create_title'))

{{-- `@push` لا `@section('styles')`: `sales::master` يجمع الأنماط من
     `@stack('styles')`، والقسم بهذا الاسم لا يُطبَع فيسقط بلا أثر --}}
@push('styles')
    <style>
        /* تلميح داخل خليّة الجدول — لا `.sl-help`.
           ذاك مبنيٌّ لحقول النماذج: هامشٌ علويّ ٠٫٣ ومقاسٌ ٠٫٧٥، فيطول به
           الصفُّ ويلتفّ نصُّه في عمودٍ عرضُه ١٣٠ بكسل. وهنا سطرٌ واحد
           مضغوط، يُقصّ بنقاطٍ إن ضاق العمود ويظهر كاملاً في `title`. */
        .sl-scope .sl-doc-table .sl-hint-line {
            display: block;
            margin-top: .15rem;
            font-size: .68rem;
            line-height: 1.3;
            color: var(--app-muted, #6e6b7b);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-variant-numeric: tabular-nums;
        }

        /* تجاوزُ الرصيد لونٌ لا حجم: الحقل نفسُه يُصبَغ بـ`is-invalid` */
        .sl-scope .sl-doc-table .sl-hint-line.text-danger {
            color: var(--app-danger, #ea5455) !important;
        }

        /* الحقلُ المقفول يُرى مقفولاً: `readonly` خاصّيةٌ لا مظهر، فحقلٌ
           لا يقبل الكتابة ويبدو كغيره يُقرأ عطلاً لا قراراً. */
        .sl-scope .form-control.is-locked,
        .sl-scope select.is-locked {
            background-color: var(--sl-bg-muted, #f1f3f5);
            color: var(--sl-text-muted, #6c757d);
            cursor: not-allowed;
        }
    </style>
@endpush

@section('content')
    @include('sales::partials.document-theme')
    {{-- من أجل `.sl-guide` و`.sl-help` — ولا تُستدعى بعد `document-theme-po`
         لأنّ تلك تعيد تعريف `--sl-r-lg` و`--sl-line-soft` لشاشات المستندات،
         والمتأخّرُ يغلب. وكلُّ قواعدها `.sl-`ـيّة لا تتقاطع مع أيٍّ منهما --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.document-theme-po')

    @php
        use App\Models\SalesInvoiceSetting;
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        // الاسم ليس `$settings` عمداً: `@extends` يمرّر متغيّرات هذا القالب
        // كلَّها إلى `sales::master` عبر `get_defined_vars()`، ومنها إلى
        // القائمة الجانبية — وهي تنتظر `$settings` مصفوفةَ «شاشة التطبيقات».
        // فمتغيّرٌ بالاسم نفسه هنا يطمس تلك المصفوفة فتختفي مجموعات القائمة
        $invoiceRules = $invoiceSettings ?? SalesInvoiceSetting::current();

        // أسماء الإعدادات المفعَّلة — تُمرَّر إلى السكربت ليعمل بها
        $enabledFlags = array_values(array_filter(
            SalesInvoiceSetting::FLAGS,
            fn (string $flag) => (bool) $invoiceRules->{$flag},
        ));

        $canEditDate = hasPermission('sales.invoices.edit_date');
        $symbol = CurrencySettings::symbol();

        /* الإعدادات التي يظهر أثرُها في هذه الشاشة بعينها — الأيقونة وحدها
           هنا، والتسمية من `sales_settings.invoice.fields.*` حيث تُعرَّف
           لشاشة الإعدادات نفسِها. كانت مكتوبةً عربيةً في هذا القالب، فكان
           لكلّ إعدادٍ اسمان يختلفان بين الشاشتين ولا يُترجَم أحدهما. */
        $screenFlagIcons = [
            'default_paid_status' => 'credit-card',
            'auto_pay_if_balance' => 'refresh-cw',
            'auto_pay_from_wallet' => 'briefcase',
            'preview_before_save' => 'eye',
            'select_price_list' => 'list',
            'show_invoice_profit' => 'trending-up',
            'no_sell_below_cost' => 'shield',
            'enable_max_discount' => 'percent',
            'disable_delivery_options' => 'truck',
        ];

        $onFlags = array_intersect_key($screenFlagIcons, array_flip($enabledFlags));

        /* والمطفأة تُذكَر كذلك: حقلٌ غائبٌ يُقرأ عطلاً في الشاشة لا خياراً
           مطفأً، فمن يبحث عن «تسوية المبيعات» ولا يجدها لا سبيل له إلى
           معرفة أنّ لها مفتاحاً أصلاً — فضلاً عن موضعه. */
        $offFlags = array_diff_key($screenFlagIcons, $onFlags);

        $showShipping = ! $invoiceRules->disable_delivery_options;

        /* البند الحرّ أُسقط بطلب المستخدم: كلّ بندٍ يقابله منتجٌ في
           الكتالوج. ومعه أُسقط مفتاحُه من شاشة الإعدادات — انظر
           `SalesInvoiceSetting`. عمودُ `allow_free_entry` باقٍ في القاعدة
           فلا تنكسر فواتيرُ سابقة، و`invoice_items.product_id` يقبل
           الفراغ فتُعرَض بنودُها القديمة كما هي. */

        /* الحدّ الأقصى للخصم — يُفحص في الخادم قبل أيّ كتابة، ويُعرَض هنا
           كذلك: أن يُقال للمندوب «تجاوزتَ الحدّ» بعد ملء الفاتورة كلّها
           أسوأ من أن يراه على الصفّ وهو يكتب. */
        $maxDiscountPercent = $invoiceRules->enable_max_discount
            ? $invoiceRules->maxDiscountFor(current_branch_id())
            : null;

        /* والسقف بالمبلغ — `null` ما لم يُضبَط في الإعدادات. يسري على
           **مجموع** الخصم في الفاتورة: خصوم البنود مع الخصم العامّ. */
        $maxDiscountAmount = $invoiceRules->enable_max_discount
            ? $invoiceRules->maxDiscountAmount()
            : null;
    @endphp

    <div class="content-body sl-scope sl-doc sl-doc--po">

        @include('sales::components.page-head', [
            'title' => __('sales_invoice.create_title'),
            'icon' => 'file-plus',
            'sub' => __('sales_invoice.form.required_note'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_invoice.invoices') => route('invoices.index'),
                __('sales_settings.ui.add') => null,
            ],
        ])

        {{-- ══════════ ما تُحفَظ به هذه الفاتورة ══════════
             العملة والترقيم كانا يجريان في الخلفية بلا ذِكر، فيُفاجَأ
             المستخدم برقمٍ غير الذي رآه أو بعملةٍ غير التي توقّع --}}
        <div class="sl-doc-note">
            <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
            <div class="sl-doc-note__body">
                <strong>{{ __('sales_invoice.context.currency') }}:</strong>
                {{ CurrencySettings::name() }} ({{ CurrencySettings::code() }})
                &nbsp;·&nbsp;
                <strong>{{ __('sales_invoice.context.numbering') }}:</strong>
                <span class="sl-code">{{ $invoice_number ?: '—' }}</span>
                <span class="sl-help">{{ __('sales_invoice.context.numbering_hint') }}</span>
            </div>
        </div>

        @if (! empty($periodBlock))
            {{-- الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال الآن لا بعد
                 ملء الفاتورة كلّها والضغط على حفظ --}}
            <div class="sl-doc-note sl-doc-note--warn">
                <span class="sl-doc-note__icon"><i class="feather icon-lock"></i></span>
                <div class="sl-doc-note__body">
                    <strong>{{ __('sales_invoice.context.period_blocked') }}</strong>
                    <span class="d-block">{{ $periodBlock }}</span>
                </div>
            </div>
        @endif

        <div class="sl-doc-note">
            <span class="sl-doc-note__icon"><i class="feather icon-settings"></i></span>
            <div class="sl-doc-note__body">
                @if (! empty($onFlags))
                    <strong class="d-block">{{ __('sales_invoice.settings_notice.enabled_title') }}</strong>
                    <div class="sl-tags">
                        @foreach ($onFlags as $flag => $icon)
                            <span class="badge badge-light-primary">
                                <i class="feather icon-{{ $icon }}"></i>
                                {{ __('sales_settings.invoice.fields.'.$flag) }}
                                {{-- الشارة تحمل رقمها: «تفعيل الحدّ الأقصى للخصم»
                                     وحدها لا تقول كم هو --}}
                                @if ($flag === 'enable_max_discount' && $maxDiscountPercent !== null)
                                    <span class="sl-num">({{ rtrim(rtrim(number_format($maxDiscountPercent, 2), '0'), '.') }}%)</span>
                                @elseif ($flag === 'enable_max_discount' && $maxDiscountAmount !== null)
                                    <span class="sl-num">({{ number_format($maxDiscountAmount, 2) }} {{ $symbol }})</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($offFlags))
                    <strong class="d-block" style="margin-top:.5rem">{{ __('sales_invoice.settings_notice.disabled_title') }}</strong>
                    <div class="sl-tags">
                        @foreach ($offFlags as $flag => $icon)
                            <span class="badge badge-light-secondary">
                                <i class="feather icon-{{ $icon }}"></i>
                                {{ __('sales_settings.invoice.fields.'.$flag) }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @permission('sales.settings.update')
                    @if (Route::has('SittingInvoice.invoice'))
                        <a href="{{ route('SittingInvoice.invoice') }}" class="sl-help">
                            {{ __('sales_invoice.settings_notice.manage') }}
                        </a>
                    @endif
                @endpermission
            </div>
        </div>

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
@include('sales::components.guide', [
            'key' => 'sales-invoices.create',
            'title' => __('sales_invoice.guide.create.title'),
            'lead' => __('sales_invoice.guide.create.lead'),
            'open' => false,
            'blocks' => [
                __('sales_invoice.guide.flow.title') => [
                    __('sales_invoice.guide.flow.a'),
                    __('sales_invoice.guide.flow.b'),
                    __('sales_invoice.guide.flow.c'),
                    __('sales_invoice.guide.flow.d'),
                ],
                __('sales_invoice.guide.money_flow.title') => [
                    __('sales_invoice.guide.money_flow.a'),
                    __('sales_invoice.guide.money_flow.b'),
                    __('sales_invoice.guide.money_flow.c'),
                ],
                __('sales_invoice.guide.statuses.title') => [
                    __('sales_invoice.guide.statuses.a'),
                    __('sales_invoice.guide.statuses.b'),
                    __('sales_invoice.guide.statuses.c'),
                ],
                __('sales_invoice.guide.blockers.title') => [
                    __('sales_invoice.guide.blockers.a'),
                    __('sales_invoice.guide.blockers.b'),
                    __('sales_invoice.guide.blockers.c'),
                    __('sales_invoice.guide.blockers.d'),
                    __('sales_invoice.guide.blockers.e'),
                ],
                __('sales_invoice.guide.terms.title') => [
                    __('sales_invoice.guide.terms.a'),
                    __('sales_invoice.guide.terms.b'),
                    __('sales_invoice.guide.terms.c'),
                    __('sales_invoice.guide.terms.d'),
                ],
                __('sales_invoice.guide.noedit.title') => [
                    __('sales_invoice.guide.noedit.a'),
                    __('sales_invoice.guide.noedit.b'),
                ],
            ],
            'facts' => [
                __('sales_invoice.guide.facts.a'),
                __('sales_invoice.guide.facts.b'),
                __('sales_invoice.guide.facts.c'),
            ],
        ])

        @include('sales::components.form-errors')
        @include('layouts.alerts.error')

        <form id="invoice-form" action="{{ route('invoices.store') }}" method="post"
              enctype="multipart/form-data" onsubmit="return confirmSubmit(event)">
            @csrf

            {{-- حقول الموقع الخفية — يملؤها السكربت من المتصفّح --}}
            <input type="hidden" name="current_latitude" id="current_latitude" value="0">
            <input type="hidden" name="current_longitude" id="current_longitude" value="0">

            {{-- فاتورةٌ من حجز («تحويل إلى فاتورة»): `store()` يربط الحجزَ بها عند الحفظ --}}
            @if (! empty($reservationSource))
                <input type="hidden" name="reservation_id" value="{{ $reservationSource['id'] }}">
                <div class="alert alert-info d-flex align-items-center gap-2">
                    <i class="feather icon-bookmark"></i>
                    <span>{{ __('reservations.invoice_banner', ['code' => $reservationSource['code']]) }}</span>
                </div>
            @endif

            {{-- ══════════ بيانات المستند ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-file-text"></i> {{ __('sales_invoice.form.document') }}
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
                                        onchange="showClientBalance(this)">
                                    <option value="">{{ __('sales_invoice.select_client') }}</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c->id }}"
                                            data-balance="{{ $c->account->balance ?? 0 }}"
                                            data-name="{{ $c->trade_name }}"
                                            @if (isset($selectedClient) && $c->id == $selectedClient) selected @endif>
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

                        {{-- ══ إنشاء من عرض سعر / أمر بيع ══

                             شاشةُ الفاتورة لم تكن تعرف بعروض الأسعار شيئاً:
                             الطريقُ الوحيد إلى فاتورةٍ من عرضٍ كان زرَّ
                             «تحويل» في شاشة العرض نفسِه، فمن يبدأ من هنا —
                             وهو الأغلب — يعيد كتابة بنودٍ مكتوبةٍ أصلاً.

                             والقائمةُ تُصفَّى على **العميل المختار**: خياراتُها
                             كلُّها في الصفحة، ويُخفي `filterQuotesByClient()`
                             ما لا يخصّه. فلا تُعرَض على عميلٍ عروضُ عميلٍ آخر. --}}
                        @if (($convertibleQuotes ?? collect())->isNotEmpty())
                            <div class="col-lg-3 col-md-6">
                                <div class="sl-field">
                                    <label for="quoteSelect">{{ __('sales_invoice.from_quote.label') }}</label>

                                    <select class="form-control" id="quoteSelect">
                                        <option value="">{{ __('sales_invoice.from_quote.placeholder') }}</option>
                                        @foreach ($convertibleQuotes as $q)
                                            <option value="{{ $q['id'] }}" data-client="{{ $q['client_id'] }}">
                                                {{ $q['code'] }} — {{ $q['total'] }}{{ $q['date'] ? ' — '.$q['date'] : '' }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <small class="sl-help" id="quoteSelectHint">
                                        {{ __('sales_invoice.from_quote.hint') }}
                                    </small>
                                </div>
                            </div>
                        @endif

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="price-list-select">{{ __('sales_invoice.ui.price_list') }}</label>
                                <select class="form-control select2" id="price-list-select" name="price_list_id"
                                        {{ $invoiceRules->select_price_list ? 'required' : '' }}>
                                    <option value="">{{ __('sales_invoice.filters.all') }}</option>
                                    @foreach ($price_lists as $price_list)
                                        <option value="{{ $price_list->id }}">{{ $price_list->name ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="storeHouseSelect">
                                    {{ __('sales_invoice.ui.warehouse') }} <span class="required">*</span>
                                </label>
                                <select class="form-control select2" id="storeHouseSelect" name="store_house_id" required>
                                    @foreach ($store_houses as $sh)
                                        <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="code">{{ __('sales_invoice.invoice_id') }}</label>
                                {{-- الاسم `code` لا `invoice_number`: المتحكّم يقرأ
                                     `$request->code`، فما كان يُرسَل باسم
                                     `invoice_number` لم يصل إليه قطّ ويُولَّد رقمٌ
                                     غيره عند الحفظ --}}
                                <input type="text" class="form-control" id="code" name="code"
                                       value="{{ old('code', $invoice_number) }}" readonly>
                                <small class="sl-help">{{ __('sales_invoice.context.numbering_locked') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="sl-field">
                                <label for="invoice_date">{{ __('sales_invoice.invoice_date') }}</label>
                                <input class="form-control" type="date" id="invoice_date" name="invoice_date"
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
                                <label for="employee_id">{{ __('sales_invoice.filters.sales_person') }}</label>
                                {{-- القائمة مقصورةٌ على نطاق رؤية المستخدم
                                     (`DataScope::employeeOptions()`): الكلّ، أو
                                     فرعُه وما تحته، أو هو ومن يتبعه إدارياً،
                                     أو هو وحدَه — واسمُه أوّلاً. --}}
                                <select name="employee_id" id="employee_id" class="form-control select2">
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                            @selected(old('employee_id', auth()->user()->employee_id) == $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-hint">{{ __('sales_invoice.ui.sales_person_scope_hint') }}</span>
                            </div>
                        </div>

                        {{-- مصدر الطلب — من شاشة «مصادر الطلب».
                             كانت تلك الشاشة تحفظ مصدراً افتراضياً ومفتاحاً
                             اسمه «إلزامي»، ولا حقلَ في الفاتورة يحمل المصدر
                             أصلاً: فلا الافتراضيّ يُطبَّق ولا الإلزام يمنع.
                             والنجمة تتبع المفتاح، و`store()` يفحصه كذلك —
                             `required` في الوسم وعدٌ لا حارس --}}
                        @if (($orderSources ?? collect())->isNotEmpty())
                            <div class="col-lg-3 col-md-6">
                                <div class="sl-field">
                                    <label for="order_source_id">
                                        {{ __('sales_invoice.ui.order_source') }}
                                        @if (! empty($orderSourceRequired))
                                            <span class="required">*</span>
                                        @endif
                                    </label>

                                    <select class="form-control select2" id="order_source_id" name="order_source_id"
                                            @required(! empty($orderSourceRequired))>
                                        <option value="">{{ __('sales_invoice.ui.order_source_select') }}</option>
                                        @foreach ($orderSources as $source)
                                            <option value="{{ $source->id }}"
                                                @selected(old('order_source_id', $defaultOrderSourceId ?? null) == $source->id)>
                                                {{ $source->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @if (! empty($orderSourceRequired))
                                        <small class="sl-help">{{ __('sales_invoice.ui.order_source_required_hint') }}</small>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- شروط الدفع — المهلةُ بالأيام وتاريخُ الاستحقاق معها.

                             كان الحقلُ يُرسَل باسم `terms` و`store()` يكتبه
                             باسم `terms` أيضاً، ولا عمودَ بهذا الاسم في
                             `invoices` — فيُسقطه `$fillable` بصمت وتضيع كلُّ
                             مهلةٍ كُتبت. صار يُحفَظ في `payment_terms`، وصار
                             التاريخُ المحسوب يُعرض تحت الحقل. --}}
                        @include('sales::components.payment-terms-field', [
                            'baseDateId' => 'issue_date',
                            'value' => old('terms'),
                        ])
                    </div>

                    {{-- بطاقة رصيد العميل — كان السكربت `showClientBalance()`
                         يبحث عن `#clientBalanceCard` ولا وجود له في القالب،
                         فيُحسب الرصيد ولا يُعرض شيء --}}
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
                        <i class="feather icon-package"></i> {{ __('sales_invoice.form.items') }}
                    </h6>
                    <span class="sl-hint">
                        <i class="feather icon-dollar-sign"></i>
                        {{ CurrencySettings::name() }} ({{ $symbol }})
                    </span>
                </div>

                <input type="hidden" id="products-data" value="{{ json_encode($items) }}">
                <input type="hidden" id="products-with-units-data" value="{{ json_encode($productsWithUnits ?? []) }}">

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
                                <th style="width:132px">
                                    {{ __('sales_invoice.quantity') }} <span class="required">*</span>
                                </th>
                                {{-- الوحدة عمودٌ قائمٌ بذاته لا سطرٌ تحت الصنف:
                                     كانت القائمة تُحشر أسفل مُنتقي الصنف فيطول
                                     أوّلُ عمودٍ ويتفاوت ارتفاع الصفوف، والكميةُ
                                     تُقرأ بلا وحدتها في العمود المجاور --}}
                                <th style="width:150px">{{ __('sales_invoice.ui.unit') }}</th>
                                <th style="width:130px">{{ __('sales_invoice.unit_price') }}</th>
                                {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                                <th style="width:150px">{{ __('sales_invoice.tax1') }}</th>
                                <th class="num" style="width:120px">{{ __('sales_invoice.total') }}</th>
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
                                        <select name="items[0][product_id]" class="form-control product-select"
                                                required onchange="handleProductChange(this)">
                                            <option value="">{{ __('sales_invoice.ui.select_item') }}</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}"
                                                    data-price="{{ $item->sale_price }}"
                                                    data-has-units="{{ $item->productUnits && $item->productUnits->count() > 0 ? 'true' : 'false' }}">
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
                                    <input type="hidden" name="items[0][store_house_id]" value="">
                                </td>

                                <td>
                                    <input type="text" name="items[0][description]" class="form-control item-description"
                                           placeholder="{{ __('sales_invoice.description') }}">
                                </td>

                                <td>
                                    {{-- كميةٌ كسريّة: `invoice_items.quantity` صار
                                         `decimal(15,4)`، فمن يبيع بالكيلو أو
                                         بالمتر أو بالساعة يكتب `1.5` ولا تُبتَر.
                                         `step="any"` لا `0.01` — أربع خاناتٍ
                                         يقبلها العمود، وخطوةٌ مقيَّدة تردّها
                                         الشاشة قبل أن يراها الخادم --}}
                                    <input type="number" name="items[0][quantity]" class="form-control quantity"
                                           value="1" min="0.0001" step="any" inputmode="decimal" required
                                           onchange="updateQuantityInBaseUnit(this)">

                                    {{-- تلميحٌ واحدٌ تحت الكمية لا اثنان: كان
                                         «المتاح والمتبقّي» فوق «= الكمية بالوحدة
                                         الأساسية»، فيطول الصفُّ سطرين ويلتفّ
                                         النصُّ في عمودٍ ضيّق. الرصيدُ هنا،
                                         والتحويلُ انتقل تحت عمود الوحدة حيث
                                         مصدرُه --}}
                                    <small class="remaining-info sl-hint-line" style="display:none"></small>
                                </td>

                                {{-- الوحدة — مخفيّةٌ حتى يُختار منتجٌ له وحدات.
                                     منتجٌ بلا وحدات لا يُسأل عن اختيارٍ لا خيار
                                     فيه، فيبقى العمود فارغاً في صفّه --}}
                                <td>
                                    <select name="items[0][product_unit_id]" class="form-control unit-select"
                                            onchange="handleUnitChange(this)" style="display:none">
                                        <option value="">{{ __('sales_invoice.ui.base_unit') }}</option>
                                    </select>

                                    {{-- «= ٢٤٠٠ حبة» — ما تُساويه الكمية بالوحدة
                                         الأساسية. وحلَّ محلَّ «معامل التحويل: ١٢٠»
                                         لأنّ المعامل مكتوبٌ أصلاً في اسم الخيار
                                         («كرتون × ١٢٠»)، فكان سطراً يكرّر ما فوقه --}}
                                    <small class="base-unit-quantity sl-hint-line" style="display:none"></small>
                                </td>

                                <td>
                                    <input type="text" name="items[0][unit_price]" class="form-control price"
                                           value="" inputmode="decimal" required placeholder="0.00">
                                </td>

                                {{-- خصمُ البند أُزيل من الشاشة بطلب المستخدم: الخصمُ من تبويب
                                     «الخصم والتسوية» وحده. الحقولُ باقيةٌ مخفيّةً بصفر — عقدُ
                                     `invoice-calculator.js` والمتحكّم يقرآنها بالاسم، وعروضُ
                                     الحسم الآليّة تُطبَّق على البند متى كان خصمُه اليدويّ صفراً. --}}
                                <input type="hidden" name="items[0][discount]" class="discount-amount" value="0">
                                <input type="hidden" name="items[0][discount_percentage]" class="discount-percentage" value="0">
                                <input type="hidden" name="items[0][discount_type]" class="discount-type" value="amount">

                                <td>
                                    <select name="items[0][tax_1]" class="form-control tax-select"
                                            data-target="tax_1" onchange="updateHiddenInput(this)">
                                        <option value="">{{ __('sales_invoice.none') }}</option>
                                        @foreach ($taxs as $tax)
                                            <option value="{{ $tax->tax }}"
                                                data-id="{{ $tax->id }}"
                                                data-name="{{ $tax->name }}"
                                                data-type="{{ $tax->type }}">{{ $tax->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="items[0][tax_1_id]">
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
                        </tbody>

                        {{-- `#tax-rows` عقدٌ مع السكربت: يحقن صفوف «خصم إضافي»
                             و«التسوية» قبل آخر صفٍّ فيه. الصفّ المخفيّ هو تلك
                             النقطة، فلا يحتاج الذيلُ صفوفَ إجمالياتٍ صارت في
                             لوحةٍ مستقلّة --}}
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
                         صفراً. يبدأ مخفيّاً لأن الفاتورة تبدأ بلا بنود، وأوّل
                         حسابٍ يفصل فيه. --}}
                    <div class="sl-totals">
                        <div class="sl-totals__row">
                            <span>{{ __('sales_invoice.subtotal') }}</span>
                            <span><span id="subtotal">0.00</span> {{ $symbol }}</span>
                        </div>
                        <div class="sl-totals__row d-none" data-total-row="total-discount">
                            <span>{{ __('sales_invoice.discount') }}</span>
                            <span><span id="total-discount">0.00</span> {{ $symbol }}</span>
                        </div>
                        @if ($showShipping)
                            <div class="sl-totals__row d-none" data-total-row="shipping-cost">
                                <span>{{ __('sales_invoice.ui.shipping') }}</span>
                                <span><span id="shipping-cost">0.00</span> {{ $symbol }}</span>
                            </div>
                        @else
                            {{-- الحقل مخفيّ لا محذوف: السكربت يكتب فيه دائماً --}}
                            <span class="d-none" id="shipping-cost">0.00</span>

                            {{-- وسطرٌ يقول لماذا غاب الصفّ: قسمٌ يختفي بلا أثر
                                 يُقرأ عطلاً في الشاشة لا خياراً مطفأً --}}
                            <div class="sl-totals__row">
                                <span class="sl-help" style="margin:0">
                                    <i class="feather icon-info"></i>
                                    {{ __('sales_invoice.settings_notice.delivery_off') }}
                                </span>
                            </div>
                        @endif
                        <div class="sl-totals__row d-none" data-total-row="tax-total">
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
                            <span>{{ __('sales_invoice.grand_total') }}</span>
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
                    {{-- المُعرِّفات `tab-*` عقدٌ مع السكربت المشترك: هو من
                         يبدّل الأقسام، لا Bootstrap --}}
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-discount" href="#">
                                <i class="feather icon-percent"></i> {{ __('sales_invoice.discount') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-deposit" href="#">
                                <i class="feather icon-dollar-sign"></i> {{ __('sales_invoice.ui.deposit') }}
                            </a>
                        </li>
                        @if ($showShipping)
                            <li class="nav-item">
                                <a class="nav-link" id="tab-shipping" href="#">
                                    <i class="feather icon-truck"></i> {{ __('sales_invoice.ui.delivery') }}
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link" id="tab-documents" href="#">
                                <i class="feather icon-paperclip"></i> {{ __('sales_invoice.ui.attachments') }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sl-doc-card__body">
                    {{-- الخصم والتسوية --}}
                    <div id="section-discount" class="tab-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="discount_amount">{{ __('sales_invoice.ui.discount_value') }}</label>

                                    {{-- الرقم ووحدتُه — و`old()` عليهما معاً.
                                         كان الحقلان بلا `old()`: تُردّ الفاتورة (تجاوزُ حدّ
                                         الخصم مثلاً) فتعود الشاشة بخصمٍ صفرٍ ووحدةٍ غير التي
                                         اختِيرت، فيبدو أنّ الاختيار «لا يُثبت». والقائمة
                                         بعرضٍ ثابتٍ ضيّق كقائمة البند: كانت `form-control`
                                         داخل `input-group` فتتمدّد نصفَ السطر، فيُقرأ رمزُها
                                         (﷼ أو %) بعيداً عن رقمه في أقصى الحقل. --}}
                                    <div class="input-group">
                                        <input type="number" id="discount_amount" name="discount_amount"
                                               class="form-control" value="{{ old('discount_amount', 0) }}"
                                               min="0" step="0.01">
                                        {{-- «مبلغ (ر.س)» و«نسبة ٪» بالنصّ لا بالرمز وحدَه.
                                             كان الخياران رمزين مجرَّدين في صندوقٍ عرضُه
                                             ٥٫٥rem، فيبدو المُنتقي فارغاً في كثير من
                                             المتصفّحات ولا يُعرف أيُّهما المختار — فيظنّ
                                             المستخدم أنّ النسبة «لا تُقبل» وهي مقبولةٌ
                                             عند الخادم أصلاً. والعرضُ صار تلقائياً
                                             ليتّسع للنصّ في العربية والإنجليزية معاً. --}}
                                        <select name="discount_type" id="discount_type" class="form-control"
                                                style="flex:0 0 auto;width:auto;min-width:8.5rem">
                                            <option value="amount" @selected(old('discount_type', 'amount') === 'amount')>
                                                {{ __('sales_invoice.ui.discount_as_amount', ['symbol' => $symbol]) }}
                                            </option>
                                            <option value="percentage" @selected(old('discount_type') === 'percentage')>
                                                {{ __('sales_invoice.ui.discount_as_percent') }}
                                            </option>
                                        </select>
                                    </div>
                                    {{-- الخصم العامّ يخضع للحدّ الأقصى كخصم البند سواءً — الخادم
                                         يردّ الفاتورة كلّها متى تجاوزه، فالتلميح هنا قبل الردّ --}}
                                    @if ($maxDiscountPercent !== null)
                                        <small class="sl-help">{{ __('sales_invoice.ui.discount_limit_hint', ['max' => rtrim(rtrim(number_format($maxDiscountPercent, 2), '0'), '.')]) }}</small>
                                    @endif
                                    @if ($maxDiscountAmount !== null)
                                        <small class="sl-help">{{ __('sales_invoice.ui.discount_limit_amount_hint', ['max' => number_format($maxDiscountAmount, 2).' '.$symbol]) }}</small>
                                    @endif
                                    {{-- ما تساويه النسبة بالمبلغ، حيّاً: «١٠٪ = 1,000.00
                                         ر.س». الأثرُ يُرى عند الاختيار لا بعد الحفظ --}}
                                    <small id="general-discount-preview" class="sl-help d-block" style="display:none"></small>

                                    <small id="general-discount-limit-note" class="sl-help text-danger d-block" style="display:none"></small>
                                </div>
                            </div>

                            {{-- ══ كوبون الخصم ══

                                 حقلٌ نصّيّ لا مُنتقٍ: الكوبون رمزٌ يحمله العميل
                                 ويُملى على البائع، وعرضُ الكوبونات كلِّها في
                                 قائمةٍ يكشف ما لم يُعطَ له.

                                 و«تحقّق» يفحصه في الخادم ويردّ المبلغ أو سببَ
                                 الرفض قبل الحفظ. **الشاشة يجب أن تُري ما سيحفظه
                                 الخادم**: خصمٌ يقع في الخادم وحده كسر «مدفوعة
                                 بالكامل» يوم طُبِّقت عروضُ الحسم أوّل مرّة —
                                 مربّعُ السداد يكتب الإجماليَّ المعروض في
                                 `advance_payment` فيردّه الخادمُ «أكبر من قيمة
                                 الفاتورة». --}}
                            @if (($invoiceRules->enable_coupons ?? false))
                                <div class="col-md-6">
                                    <div class="sl-field">
                                        <label for="coupon_code">{{ __('sales_invoice.coupon.label') }}</label>

                                        <div class="input-group">
                                            <input type="text" id="coupon_code" name="coupon_code" maxlength="64"
                                                   class="form-control" value="{{ old('coupon_code') }}"
                                                   placeholder="{{ __('sales_invoice.coupon.placeholder') }}">
                                            <button type="button" id="coupon-check" class="btn btn-outline-primary"
                                                    style="flex:0 0 auto">
                                                {{ __('sales_invoice.coupon.apply') }}
                                            </button>
                                        </div>

                                        <small class="sl-help">{{ __('sales_invoice.coupon.hint') }}</small>
                                        <small id="coupon-feedback" class="sl-help d-block" style="display:none"></small>
                                    </div>
                                </div>
                            @endif

                            {{-- كان هنا حقل «التسوية»: مبلغٌ حرٌّ ببيانٍ حرّ يُزاد
                                 على الفاتورة أو يُحسم منها بعد الخصم والضريبة.
                                 أُسقط بطلب المستخدم — والخصم أعلاه هو الباب
                                 الوحيد لتخفيض الفاتورة. أعمدة
                                 `invoices.adjustment_*` باقية فتُطبَع الفواتير
                                 القديمة بتسوياتها --}}
                        </div>
                    </div>

                    {{-- الإيداع --}}
                    <div id="section-deposit" class="tab-section d-none">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="sl-field">
                                    <label for="paid-amount-input">{{ __('sales_invoice.ui.paid_amount') }}</label>
                                    <div class="input-group">
                                        {{-- نصٌّ لا `number` بطلب المستخدم: يُكتب فيه أيُّ رقم —
                                             كسراً أو صحيحاً، بأرقامٍ عربية أو بالفاصلة «٫». حقلُ
                                             `number` كان يُرجِع قيمةً فارغة لكل ما لا يفهمه المتصفّح
                                             فيضيع الكسر العربيّ. التطبيعُ إلى رقمٍ في الحاسبة عند
                                             الكتابة، وفي المتحكّم عند الحفظ. --}}
                                        <input type="text" id="paid-amount-input" class="form-control money-2dp" value="0"
                                               name="advance_payment" inputmode="decimal" autocomplete="off" placeholder="0.00">
                                        {{-- مبلغٌ فقط — لا مُنتقي «مبلغ / ٪».
                                             كان هنا `advance_payment_type` بخيار «٪» تقرؤه
                                             الحاسبةُ في الشاشة ولا يقرؤه الخادم: يكتب
                                             المستخدم ١٠ ويختار ٪ فتعرض الشاشة عُشرَ الإجمالي
                                             ويُحفظ عشرةُ ريالات. أُسقط بطلب المستخدم. --}}
                                        <span class="input-group-text">{{ $symbol }}</span>
                                    </div>

                                    {{-- سببُ القفل مكتوبٌ لا مُستنتَج: حقلٌ لا
                                         يُكتب فيه بلا تفسيرٍ يُقرأ عطلاً.
                                         يُظهره `applyFullPaymentState()` مع
                                         القفل ويُخفيه معه --}}
                                    <small id="full-payment-lock-hint" class="sl-help d-none">
                                        <i class="feather icon-lock"></i>
                                        {{ __('sales_invoice.ui.paid_amount_locked') }}
                                    </small>
                                </div>
                            </div>

                            {{-- طريقة الدفع من شاشة «طرق الدفع».
                                 `store()` يقرأ `$request->payment_method` ويحفظه
                                 منذ البداية، والنموذج كان بلا حقلٍ يرسله — فطريقة
                                 الدفع في كل فاتورة محفوظةٌ `null`. --}}
                            <div class="col-md-4">
                                <div class="sl-field">
                                    <label for="payment_method">{{ __('sales_credit_note.form.payment_method') }}</label>
                                    <select class="form-control" id="payment_method" name="payment_method">
                                        <option value="">{{ __('sales_credit_note.form.select_payment_method') }}</option>
                                        @foreach ($paymentMethods ?? [] as $method)
                                            <option value="{{ $method->id }}"
                                                @selected(old('payment_method', ($paymentMethods ?? collect())->firstWhere('is_default', true)?->id) == $method->id)>
                                                {{ \App\Support\LanguageSettings::isRtl() ? $method->name : ($method->name_en ?: $method->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if (($paymentMethods ?? collect())->isEmpty())
                                        <small class="sl-help">{{ __('sales_settings.context.payments_none') }}</small>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-4 d-flex align-items-end">
                                <div class="sl-field w-100">
                                    <div class="form-check">
                                        <input class="form-check-input payment-toggle" type="checkbox"
                                               name="is_paid" value="1" id="full-payment-check"
                                               @checked($invoiceRules->default_paid_status)>
                                        <label class="form-check-label" for="full-payment-check">
                                            {{ __('sales_invoice.ui.fully_paid') }}
                                        </label>
                                    </div>
                                    @if ($invoiceRules->default_paid_status)
                                        <small class="sl-help">{{ __('sales_invoice.ui.fully_paid_auto') }}</small>
                                    @endif
                                </div>
                            </div>

                            {{-- «إعطاء الفواتير حالات يدوية» — إعدادات الفواتير --}}
                            @if ($invoiceRules->manual_invoice_status)
                                <div class="col-md-4">
                                    <div class="sl-field">
                                        <label for="payment_status">{{ __('sales_settings.invoice.fields.manual_invoice_status') }}</label>
                                        <select class="form-control" id="payment_status" name="payment_status">
                                            <option value="3" @selected(old('payment_status') == 3)>{{ __('sales_invoice.status.draft') }}</option>
                                            <option value="1" @selected(old('payment_status') == 1)>{{ __('sales_invoice.status.paid') }}</option>
                                            <option value="2" @selected(old('payment_status') == 2)>{{ __('sales_invoice.status.unpaid') }}</option>
                                            <option value="4" @selected(old('payment_status') == 4)>{{ __('sales_invoice.status.under_review') }}</option>
                                            <option value="5" @selected(old('payment_status') == 5)>{{ __('sales_invoice.status.failed') }}</option>
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- التوصيل --}}
                    @if ($showShipping)
                        <div id="section-shipping" class="tab-section d-none">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="sl-field">
                                        {{-- كان المفتاح `tax1` — أي «الضريبة 1»، وهو عنوانُ عمود ضريبة
                                             البند لا هذا الحقل. والحقل يختار **ضريبة الشحن**
                                             ويحسب الخادم مبلغها على تكلفته وحدها. --}}
                                        <label for="methodSelect">{{ __('sales_invoice.ui.shipping_tax') }}</label>
                                        <select class="form-control" id="methodSelect" name="shipping_tax_id">
                                            <option value="">{{ __('sales_invoice.none') }}</option>
                                            @foreach ($taxs as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}">
                                                    {{ $tax->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="sl-field">
                                        <label for="shipping">{{ __('sales_invoice.ui.shipping') }}</label>
                                        <input type="number" class="form-control" name="shipping_cost" id="shipping"
                                               value="0" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- إرفاق المستندات --}}
                    <div id="section-documents" class="tab-section d-none">
                        <ul class="nav sl-doc-tabs mb-3">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-new-document" href="#">
                                    {{ __('sales_invoice.ui.upload_new') }}
                                </a>
                            </li>
                        </ul>

                        <div id="content-new-document" class="tab-pane active">
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
            </div>

            {{-- ══════════ الملاحظات وأزرار الحفظ ══════════ --}}
            <div class="sl-doc-card">
                <div class="sl-doc-card__head">
                    <h6 class="sl-doc-card__title">
                        <i class="feather icon-edit-3"></i> {{ __('sales_invoice.form.notes') }}
                    </h6>
                </div>

                <div class="sl-doc-card__body">
                    <textarea id="tinyMCE" name="notes" class="form-control" rows="5"></textarea>

                    {{-- «وصف مخصّص لقيود اليومية» — إعدادات الفواتير --}}
                    @if ($invoiceRules->custom_journal_description)
                        <div class="sl-field mt-3">
                            <label for="journal_description">
                                {{ __('sales_settings.invoice.fields.custom_journal_description') }}
                                <span class="sl-hint d-inline">({{ __('sales_settings.ui.optional') }})</span>
                            </label>
                            <textarea id="journal_description" name="journal_description" class="form-control" rows="2">{{ old('journal_description') }}</textarea>
                            <span class="sl-hint">{{ __('sales_settings.invoice.fields.custom_journal_description_hint') }}</span>
                        </div>
                    @endif
                </div>

                <div class="sl-doc-card__foot">
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }}"></i>
                        {{ __('sales_invoice.form.back') }}
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

            {{-- بطاقة الدفعة المقدّمة الإضافية — يُظهرها السكربت المشترك --}}
            <div id="section-deposit-extra" class="sl-doc-card" style="display:none">
                <div class="sl-doc-card__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="sl-field">
                                <label for="paid-amount-input-extra">{{ __('sales_invoice.ui.paid_amount') }}</label>
                                <div class="input-group">
                                    <input type="text" id="paid-amount-input-extra" class="form-control money-2dp" value="0"
                                           name="paid_amount" inputmode="decimal" autocomplete="off" placeholder="0.00">
                                    {{-- مبلغٌ فقط، كحقل الإيداع أعلاه — `payment_amount_type`
                                         لم يكن يقرؤه شيءٌ في الشاشة ولا في الخادم --}}
                                    <span class="input-group-text">{{ $symbol }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input advance-payment-toggle" type="checkbox"
                                       name="is_advance_paid" value="1" id="advance-payment-check">
                                <label class="form-check-label" for="advance-payment-check">
                                    {{ __('sales_invoice.ui.partial_advance') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- حاسبة الفواتير المشتركة — هي من يملك حساب الإجماليات وإضافة الصفوف
         وتبديل التبويبات في أربع شاشات، فلا تُنسَخ منطقُها هنا. الإصدار
         بزمن التعديل يكسر كاش المتصفح متى عُدِّل الملف --}}
    {{-- عروضُ الحسم السارية — تقرؤها الحاسبة فتُساوي المعروضَ بالمحفوظ --}}
    @include('sales::partials.discount-offers')

    @php $invoiceCalculatorVersion = @filemtime(public_path('assets/js/invoice-calculator.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/invoice-calculator.js') }}?v={{ $invoiceCalculatorVersion }}"></script>

    {{-- الضريبة الشاملة على المستند — تكوينُ الـ JS وتحميل حاسبتها --}}
    @include('partials.comprehensive-tax-script')

    <script>
        /*
         * «إعدادات الفواتير» كما هي في القاعدة.
         *
         * كانت الشاشة تقرأ `session('sales_settings')` — مفتاحُ جلسةٍ لا
         * يكتبه شيء في المشروع كلّه — وتقارنه بأسماء إعداداتٍ لا وجود لها
         * في `SalesInvoiceSetting::FLAGS` (`auto_apply_offers`،
         * `auto_inventory_update`، `commission_calculation`،
         * `client_notifications`). فكانت البطاقة لا تظهر، والدفع التلقائي
         * لا يعمل، ونصّ التأكيد يعد بما لا يفعله النظام.
         */
        const invoiceSettings = @json($enabledFlags);
        const previewBeforeSave = invoiceSettings.includes('preview_before_save');
        const defaultPaidEnabled = invoiceSettings.includes('default_paid_status');

        /** أقصى نسبة خصم على البند — `null` حين يكون المفتاح مطفأً */
        const maxDiscountPercent = @json($maxDiscountPercent !== null ? (float) $maxDiscountPercent : null);

        /* نصوص المعاينة والحدّ — تُقرأ من ملفّ الترجمة مرّةً هنا، لا عند
           كل موضعٍ تُركَّب فيه سلسلةُ HTML: التوجيه داخل سلسلةٍ مركَّبة
           يُقرأ بصعوبة ويتكرّر */
        const CURRENCY_SYMBOL = @json($symbol);
        const SAVE_LABEL = @json(__('sales_invoice.save'));
        const BACK_TO_EDIT_LABEL = @json(__('sales_invoice.ui.back_to_edit'));
        const PREVIEW_TITLE = @json(__('sales_invoice.ui.preview_title'));
        const PREVIEW_NO_ITEMS = @json(__('sales_invoice.ui.no_items_yet'));
        const PREVIEW_CLIENT_LABEL = @json(__('sales_invoice.client'));
        const PREVIEW_DATE_LABEL = @json(__('sales_invoice.invoice_date'));
        const PREVIEW_COL_ITEM = @json(__('sales_invoice.item'));
        const PREVIEW_COL_QUANTITY = @json(__('sales_invoice.quantity'));
        const PREVIEW_COL_UNIT = @json(__('sales_invoice.ui.unit'));
        const PREVIEW_COL_PRICE = @json(__('sales_invoice.unit_price'));
        const PREVIEW_COL_TAX = @json(__('sales_invoice.tax1'));
        const PREVIEW_COL_TOTAL = @json(__('sales_invoice.total'));
        const DISCOUNT_LIMIT_MESSAGE = @json(__('sales_invoice.ui.discount_over_limit'))
            .replace(':max', maxDiscountPercent === null ? '' : maxDiscountPercent);

        /* رسالتا الخصم العامّ ومجموع الخصم — بقالبيهما، لأنّ النسبة
           الفعلية لا تُعرف إلا عند الفحص */
        const DISCOUNT_LIMIT_GENERAL_TEMPLATE = @json(__('sales_invoice.ui.discount_over_limit_general'));
        const DISCOUNT_PERCENT_PREVIEW = @json(__('sales_invoice.ui.discount_percent_preview'));
        const DISCOUNT_LIMIT_TOTAL_TEMPLATE = @json(__('sales_invoice.ui.discount_over_limit_total'));
        const DISCOUNT_LIMIT_AMOUNT_TEMPLATE = @json(__('sales_invoice.ui.discount_over_limit_amount'));

        /** أقصى مبلغ خصم على الفاتورة كلّها — `null` حين كانت الوحدة نسبة */
        const maxDiscountAmount = @json($maxDiscountAmount !== null ? (float) $maxDiscountAmount : null);

        /* واحدةٌ منهما تسري لا الاثنتان، فالسؤال «أثمّة سقف؟» لا «أثمّة
           نسبة؟» — وإلّا مرّ السقف بالمبلغ بلا فحصٍ في الشاشة */
        const discountLimited = maxDiscountPercent !== null || maxDiscountAmount !== null;

        /** الموقع الحالي للموظّف — يفحصه الخادم قبل قبول الفاتورة */
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
                showErrorAlert(@json(session('error')));
            @endif

            if (defaultPaidEnabled) {
                const paymentCheckbox = document.getElementById('full-payment-check');
                if (paymentCheckbox) paymentCheckbox.checked = true;
            }

            const clientSelect = document.getElementById('clientSelect');
            if (clientSelect && clientSelect.value) showClientBalance(clientSelect);

            /* ترقيم الصفوف يُعاد بعد كل إضافة أو حذف: السكربت المشترك
               يستنسخ الصفّ الأوّل بما فيه رقمه، فتصير كل الصفوف «1» */
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

            /* اسم الملف المرفوع يُعرض تحت حقل الرفع: `<input type=file>`
               مخفيٌّ داخل الصندوق، فبلا هذا لا يعرف المستخدم أنّ رفعه وقع */
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
        });

        /** رصيد العميل المختار — بعملة النظام لا بـ«ر.س» مكتوبة */
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

        /* ══════════ الحدّ الأقصى للخصم ══════════
           الخادم يرفض الفاتورة كلّها متى تجاوز بندٌ الحدَّ، وهذا يقوله
           على الصفّ قبل أن تُملأ الفاتورة. */

        /** الحقل الظاهر في الصفّ — القيمة أو النسبة بحسب `discount_type` */
        function lineDiscountField(row) {
            const typeField = row.querySelector('.discount-type');

            return typeField && typeField.value === 'percentage'
                ? row.querySelector('.discount-percentage')
                : row.querySelector('.discount-amount');
        }

        function lineDiscountPercent(row) {
            const quantityField = row.querySelector('.quantity');
            const priceField = row.querySelector('.price');
            const typeField = row.querySelector('.discount-type');

            /* الصفّ حقلان: `.discount-amount` للقيمة و`.discount-percentage`
               للنسبة — يُخفي السكربتُ أحدَهما. كان يُقرأ حقل القيمة في
               الحالتين، فالنسبة تُقاس بصفرٍ ولا يُنبَّه المستخدم إلى
               تجاوزها. */
            const field = lineDiscountField(row);
            const entered = parseFloat(field ? field.value : 0) || 0;

            if (typeField && typeField.value === 'percentage') return entered;

            const quantity = parseFloat(quantityField ? quantityField.value : 0) || 0;
            const price = parseFloat(priceField ? priceField.value : 0) || 0;
            const lineTotal = quantity * price;

            return lineTotal > 0 ? (entered / lineTotal) * 100 : (entered > 0 ? 100 : 0);
        }

        function checkRowDiscount(row) {
            if (maxDiscountPercent === null) return true;

            const input = lineDiscountField(row);
            if (!input) return true;

            const over = lineDiscountPercent(row) > maxDiscountPercent + 0.001;

            /* التنبيه على الحقل الظاهر، والآخر يُنظَّف: تبديلُ النوع
               يُبقي الحُمرة على حقلٍ مخفيّ لولا ذلك */
            row.querySelectorAll('.discount-amount, .discount-percentage')
                .forEach(function (field) { field.classList.remove('is-invalid'); });

            input.classList.toggle('is-invalid', over);

            let note = row.querySelector('.discount-limit-note');

            if (over && !note) {
                note = document.createElement('small');
                note.className = 'discount-limit-note sl-help text-danger d-block';
                input.closest('.input-group').after(note);
            }

            if (note) {
                note.style.display = over ? '' : 'none';
                note.textContent = DISCOUNT_LIMIT_MESSAGE;
            }

            return !over;
        }

        document.addEventListener('input', function (e) {
            if (!discountLimited) return;
            if (!e.target.matches('.discount-amount, .discount-percentage, .price, .quantity')) return;

            const row = e.target.closest('.item-row');
            if (row) checkRowDiscount(row);
        });

        document.addEventListener('change', function (e) {
            if (!discountLimited) return;
            if (!e.target.classList.contains('discount-type')) return;

            const row = e.target.closest('.item-row');
            if (row) checkRowDiscount(row);
        });

        /* ══════════ الخصم العامّ على الفاتورة ══════════
           الحدّ نفسه يسري عليه: من نقل الخصم من البند إلى أسفل الفاتورة
           لم يخرج منه. والأساس مجموعُ البنود قبل أيّ خصم — كما يحسبه
           الخادم في `store()` — لا الإجمالي بعد الخصم والضريبة. */

        function documentSubtotal() {
            return Array.prototype.slice
                .call(document.querySelectorAll('#items-table .item-row'))
                .reduce(function (sum, row) {
                    const quantityField = row.querySelector('.quantity');
                    const priceField = row.querySelector('.price');
                    const quantity = parseFloat(quantityField ? quantityField.value : 0) || 0;
                    const price = parseFloat(priceField ? priceField.value : 0) || 0;

                    return sum + quantity * price;
                }, 0);
        }

        function lineDiscountTotal() {
            return Array.prototype.slice
                .call(document.querySelectorAll('#items-table .item-row'))
                .reduce(function (sum, row) {
                    const quantityField = row.querySelector('.quantity');
                    const priceField = row.querySelector('.price');
                    const lineTotal = (parseFloat(quantityField ? quantityField.value : 0) || 0)
                        * (parseFloat(priceField ? priceField.value : 0) || 0);

                    return sum + (lineDiscountPercent(row) * lineTotal) / 100;
                }, 0);
        }

        /** مبلغٌ منسَّقٌ برمز العملة — لرسائل السقف بالمبلغ */
        function money(value) {
            const number = window.AppCurrency
                ? window.AppCurrency.number(value)
                : (parseFloat(value) || 0).toFixed(2);

            return number + ' ' + CURRENCY_SYMBOL;
        }

        /** قيمة الخصم العامّ — النسبةُ تُحوَّل، والقيمةُ تُؤخذ كما هي */
        function generalDiscountValue(subtotal) {
            const amountField = document.getElementById('discount_amount');
            const typeField = document.getElementById('discount_type');
            const amount = parseFloat(amountField ? amountField.value : 0) || 0;

            if (amount <= 0) return 0;

            return typeField && typeField.value === 'percentage'
                ? (subtotal * amount) / 100
                : amount;
        }

        /** يردّ `null` متى كان الخصم ضمن الحدّ، وإلّا نصَّ التجاوز */
        function generalDiscountBreach() {
            if (!discountLimited) return null;

            const subtotal = documentSubtotal();
            const general = generalDiscountValue(subtotal);

            const generalPercent = subtotal > 0
                ? (general / subtotal) * 100
                : (general > 0 ? 100 : 0);

            if (maxDiscountPercent !== null && generalPercent > maxDiscountPercent + 0.001) {
                return DISCOUNT_LIMIT_GENERAL_TEMPLATE
                    .replace(':percent', generalPercent.toFixed(2))
                    .replace(':max', maxDiscountPercent);
            }

            const totalPercent = subtotal > 0
                ? ((lineDiscountTotal() + general) / subtotal) * 100
                : generalPercent;

            if (maxDiscountPercent !== null && totalPercent > maxDiscountPercent + 0.001) {
                return DISCOUNT_LIMIT_TOTAL_TEMPLATE
                    .replace(':percent', totalPercent.toFixed(2))
                    .replace(':max', maxDiscountPercent);
            }

            /* والسقف بالمبلغ — على مجموع الخصم في الفاتورة، كما يفحصه
               الخادم. سقفان مستقلّان: يُردّ الخصم إن تجاوز أيّهما. */
            if (maxDiscountAmount !== null) {
                const totalValue = lineDiscountTotal() + general;

                if (totalValue > maxDiscountAmount + 0.001) {
                    return DISCOUNT_LIMIT_AMOUNT_TEMPLATE
                        .replace(':total', money(totalValue))
                        .replace(':max', money(maxDiscountAmount));
                }
            }

            return null;
        }

        /** «١٠٪ = 1,000.00 ر.س» تحت الحقل — يُرى أثرُ الاختيار فوراً */
        function updateGeneralDiscountPreview() {
            const preview = document.getElementById('general-discount-preview');
            const amountField = document.getElementById('discount_amount');
            const typeField = document.getElementById('discount_type');

            if (!preview || !amountField || !typeField) return;

            const amount = parseFloat(amountField.value) || 0;

            if (typeField.value !== 'percentage' || amount <= 0) {
                preview.style.display = 'none';

                return;
            }

            const value = (documentSubtotal() * amount) / 100;

            preview.style.display = '';
            preview.textContent = DISCOUNT_PERCENT_PREVIEW
                .replace(':percent', amount)
                .replace(':value', money(value));
        }

        function checkGeneralDiscount() {
            const note = document.getElementById('general-discount-limit-note');
            const input = document.getElementById('discount_amount');
            const breach = generalDiscountBreach();

            if (input) input.classList.toggle('is-invalid', !!breach);

            if (note) {
                note.style.display = breach ? '' : 'none';
                note.textContent = breach || '';
            }

            return !breach;
        }

        document.addEventListener('input', function (e) {
            if (!discountLimited) return;
            if (!e.target.matches('#discount_amount, .discount-amount, .discount-percentage, .price, .quantity')) return;

            checkGeneralDiscount();
            updateGeneralDiscountPreview();
        });

        /* الحقول كلّها لا الخيار وحده: نسخُ فاتورةٍ سابقة يكتب «قيمة
           الخصم» ويرسل `change` عليها، فلولا ذلك لبقي التجاوز المنسوخ
           بلا رسالةٍ حتى يمسّ المستخدم الحقل. */
        document.addEventListener('change', function (e) {
            if (!discountLimited) return;
            if (e.target.id !== 'discount_type'
                && e.target.id !== 'discount_amount'
                && !e.target.classList.contains('discount-type')
                && !e.target.matches('.discount-amount, .discount-percentage, .price, .quantity')) return;

            checkGeneralDiscount();
        });

        /* ══════════ معاينة الفاتورة قبل الحفظ ══════════
         *
         * كان هذا الإعداد يعرض سؤالاً: «هل تريد الحفظ؟» — وهو تأكيدٌ لا
         * معاينة. والفرق ليس لفظياً: الفاتورة **لا تُعدَّل بعد إصدارها**
         * في هذا النظام (`update()` تردّ بخطأ امتثالٍ ضريبيّ)، فالمراجعة
         * قبل الحفظ هي المراجعة الوحيدة الممكنة. المعاينة الآن تعرض ما
         * سيُحفَظ فعلاً: العميل والتاريخ والبنود والإجماليات كما حسبها
         * السكربت المشترك، لا سؤالاً مجرَّداً.
         */
        function previewEscape(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function buildInvoicePreview() {
            const esc = previewEscape;

            const clientSelect = document.getElementById('clientSelect');
            const clientName = clientSelect && clientSelect.selectedIndex >= 0
                ? clientSelect.options[clientSelect.selectedIndex].text
                : '—';

            const dateField = document.querySelector('[name="invoice_date"]');
            const invoiceDate = dateField && dateField.value ? dateField.value : '—';

            let rows = '';

            document.querySelectorAll('#items-table .item-row').forEach(function (row) {
                const select = row.querySelector('.product-select');

                const name = select && select.value && select.selectedIndex >= 0
                    ? select.options[select.selectedIndex].text
                    : '';

                if (!name) return;

                const quantityField = row.querySelector('.quantity');
                const unitSelect = row.querySelector('.unit-select');
                const priceField = row.querySelector('.price');
                const taxSelect = row.querySelector('.tax-select');
                const rowTotal = row.querySelector('.row-total');

                const tax = taxSelect && taxSelect.value
                    ? taxSelect.options[taxSelect.selectedIndex].text
                    : '—';

                /* الوحدة كما تُقرأ في الجدول لا كمعرّفها: منتجٌ بلا وحدات
                   لا مُنتقيَ له، فيبقى الحقل شَرطة كما تفعل الضريبة */
                const unit = unitSelect && unitSelect.style.display !== 'none'
                    && unitSelect.selectedIndex >= 0
                    ? unitSelect.options[unitSelect.selectedIndex].text
                    : '—';

                rows += '<tr>'
                    + '<td style="text-align:start">' + esc(name) + '</td>'
                    + '<td>' + esc(quantityField ? quantityField.value : '') + '</td>'
                    + '<td>' + esc(unit) + '</td>'
                    + '<td>' + esc(priceField ? priceField.value : '') + '</td>'
                    + '<td>' + esc(tax) + '</td>'
                    + '<td>' + esc(rowTotal ? rowTotal.textContent : '') + '</td>'
                    + '</tr>';
            });

            if (!rows) {
                rows = '<tr><td colspan="6">' + esc(PREVIEW_NO_ITEMS) + '</td></tr>';
            }

            /* الإجماليات من اللوحة نفسها لا بحسابٍ ثانٍ: صفٌّ مخفيّ
               (`d-none`) قيمتُه صفر فلا يُعرَض في المعاينة كما لا يُعرَض
               في الشاشة */
            let totals = '';

            document.querySelectorAll('.sl-totals .sl-totals__row').forEach(function (row) {
                if (row.classList.contains('d-none')) return;

                const cells = row.querySelectorAll(':scope > span');
                if (cells.length < 2) return;

                const strong = row.classList.contains('sl-totals__row--grand');

                totals += '<div style="display:flex;justify-content:space-between;padding:.35rem 0'
                    + (strong ? ';font-weight:700;border-top:1px solid rgba(0,0,0,.15);margin-top:.35rem' : '')
                    + '"><span>' + esc(cells[0].textContent.trim()) + '</span>'
                    + '<span>' + esc(cells[1].textContent.trim()) + '</span></div>';
            });

            return '<div style="text-align:start;font-size:.9rem">'
                + '<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:.75rem">'
                + '<div><strong>' + esc(PREVIEW_CLIENT_LABEL) + ':</strong> ' + esc(clientName) + '</div>'
                + '<div><strong>' + esc(PREVIEW_DATE_LABEL) + ':</strong> ' + esc(invoiceDate) + '</div>'
                + '</div>'
                + '<div style="overflow-x:auto"><table class="table table-sm" style="width:100%;text-align:center">'
                + '<thead><tr>'
                + '<th style="text-align:start">' + esc(PREVIEW_COL_ITEM) + '</th>'
                + '<th>' + esc(PREVIEW_COL_QUANTITY) + '</th>'
                + '<th>' + esc(PREVIEW_COL_UNIT) + '</th>'
                + '<th>' + esc(PREVIEW_COL_PRICE) + '</th>'
                + '<th>' + esc(PREVIEW_COL_TAX) + '</th>'
                + '<th>' + esc(PREVIEW_COL_TOTAL) + '</th>'
                + '</tr></thead><tbody>' + rows + '</tbody></table></div>'
                + '<div style="margin-top:.75rem">' + totals + '</div>'
                + '</div>';
        }

        /**
         * المعاينة قبل الحفظ — تظهر متى فُعِّل «معاينة الفاتورة قبل
         * الحفظ» وحده.
         */
        function confirmSubmit(event) {
            /* فحصُ الحدّ الأقصى يسبق كلّ شيء: الخادم يردّ الفاتورة كلّها
               بسببه، فلا معنى لمعاينة ما لن يُقبَل */
            if (discountLimited) {
                const offending = Array.prototype.slice
                    .call(document.querySelectorAll('#items-table .item-row'))
                    .filter(function (row) { return !checkRowDiscount(row); });

                if (offending.length) {
                    event.preventDefault();
                    showErrorAlert(DISCOUNT_LIMIT_MESSAGE);

                    return false;
                }

                /* والخصم العامّ كذلك — بابُه غير باب البند، وحدُّهما واحد */
                const generalBreach = generalDiscountBreach();

                if (generalBreach) {
                    event.preventDefault();
                    checkGeneralDiscount();
                    showErrorAlert(generalBreach);

                    return false;
                }
            }

            if (!previewBeforeSave) return true;

            event.preventDefault();

            Swal.fire({
                title: PREVIEW_TITLE,
                html: buildInvoicePreview(),
                width: '52rem',
                showCancelButton: true,
                confirmButtonText: SAVE_LABEL,
                cancelButtonText: BACK_TO_EDIT_LABEL,
                reverseButtons: true,
            }).then(function (result) {
                if (result.isConfirmed) document.getElementById('invoice-form').submit();
            });

            return false;
        }

        function showErrorAlert(message) {
            Swal.fire({
                title: @json(__('sales_settings.ui.validation_failed')),
                html: message,
                icon: 'error',
                confirmButtonText: @json(__('sales_invoice.ui.ok')),
            });
        }

        /* ══════════ وحدات المنتجات ══════════
           لكل منتجٍ وحداتُه ومعاملُ تحويلها؛ اختيار وحدةٍ يبدّل السعر
           ويحسب الكمية بالوحدة الأساسية التي يُخصم بها المخزون. */

        let productsWithUnitsData = {};

        try {
            JSON.parse(document.getElementById('products-with-units-data').value || '[]')
                .forEach(product => { productsWithUnitsData[product.id] = product; });
        } catch (e) {
            console.error('تعذّرت قراءة بيانات وحدات المنتجات:', e);
        }

        function handleProductChange(selectElement) {
            const row = selectElement.closest('.item-row');
            const productId = selectElement.value;
            const unitSelect = row.querySelector('.unit-select');
            const priceInput = row.querySelector('.price');
            const quantityInput = row.querySelector('.quantity');
            const conversionFactorInput = row.querySelector('.conversion-factor');
            const baseUnitQuantity = row.querySelector('.base-unit-quantity');
            const remainingInfo = row.querySelector('.remaining-info');

            unitSelect.innerHTML = '<option value="">' + @json(__('sales_invoice.ui.base_unit')) + '</option>';
            conversionFactorInput.value = '1';

            if (baseUnitQuantity) baseUnitQuantity.style.display = 'none';

            const product = productId ? productsWithUnitsData[productId] : null;

            if (!product) {
                unitSelect.style.display = 'none';
                unitSelect.disabled = true;
                quantityInput.setAttribute('data-available', '0');
                if (remainingInfo) remainingInfo.style.display = 'none';
                return;
            }

            if (product.has_units && product.units && product.units.length) {
                product.units.forEach(function (unit) {
                    const option = document.createElement('option');

                    option.value = unit.id || ('category_' + unit.category_unit_id);
                    option.setAttribute('data-price', unit.sale_price);
                    option.setAttribute('data-conversion-factor', unit.conversion_factor);
                    option.setAttribute('data-is-base', unit.is_base_unit ? 'true' : 'false');
                    option.textContent = unit.name
                        + (unit.is_base_unit ? ' (' + @json(__('sales_invoice.ui.base_unit')) + ')' : '')
                        + (unit.conversion_factor != 1 ? ' × ' + unit.conversion_factor : '');

                    unitSelect.appendChild(option);

                    if (unit.is_default) {
                        option.selected = true;
                        if (unit.sale_price > 0) priceInput.value = parseFloat(unit.sale_price).toFixed(2);
                        conversionFactorInput.value = unit.conversion_factor;
                    }
                });

                unitSelect.style.display = 'block';
                unitSelect.disabled = false;
            } else {
                unitSelect.style.display = 'none';
                unitSelect.disabled = true;
                if (product.sale_price > 0) priceInput.value = parseFloat(product.sale_price).toFixed(2);
            }

            applyProductTaxes(row, product);
            applyStockMode(row, product);

            quantityInput.setAttribute('data-available', product.available_quantity || 0);
            updateQuantityInBaseUnit(quantityInput);

            if (typeof invoiceCalculator !== 'undefined' && invoiceCalculator) {
                invoiceCalculator.calculateTotals();
            }
        }

        /* الأنواع التي لها رصيد في المستودعات — مقابل `ProductType::tracksStock()`
           في الخادم. الخدمي والرقمي لا رصيد لهما ولا مستودع. */
        const STOCK_TRACKING_TYPES = ['products', 'compiled'];

        function rowTracksStock(row) {
            return row.getAttribute('data-tracks-stock') !== 'false';
        }

        /**
         * الصنف الخدمي والرقمي: لا رصيد ولا مستودع.
         *
         * كانت الشاشة تعرض «المتاح: 0 — المتبقّي: -1» لكل خدمة وتصبغ حقل
         * الكمّية بالأحمر، لأنّ التحقّق يقارن بالمخزون بلا نظرٍ إلى النوع.
         * فيظنّ البائع أنّ الخدمة نفدت من المستودع — وهي لا تدخله أصلاً.
         */
        function applyStockMode(row, product) {
            const tracks = STOCK_TRACKING_TYPES.indexOf(product.type) !== -1;
            row.setAttribute('data-tracks-stock', tracks ? 'true' : 'false');

            const remainingInfo = row.querySelector('.remaining-info');
            const quantityInput = row.querySelector('.quantity');
            const storeHouseInput = row.querySelector('input[name^="items"][name$="[store_house_id]"]');

            if (tracks) {
                return;
            }

            // لا رصيد يُعرَض ولا تحذير يُصبَغ
            if (remainingInfo) {
                remainingInfo.style.display = 'none';
                remainingInfo.textContent = '';
            }

            if (quantityInput) {
                quantityInput.classList.remove('is-invalid');
            }

            // ولا مستودع يُرسَل: البيع بلا إذنٍ ولا خصمٍ من رصيد
            if (storeHouseInput) {
                storeHouseInput.value = '';
            }
        }

        /* ضريبتا الصنف تُختاران تلقائياً عند اختياره.
           كانتا مضبوطتين في شاشة المنتج ولا تصلان الفاتورة، فيختارهما
           البائع يدوياً لكل بند — أو ينساهما. تبقى القائمة قابلةً للتبديل. */
        function applyProductTaxes(row, product) {
            [['tax_1', product.tax1], ['tax_2', product.tax2]].forEach(function (pair) {
                const target = pair[0];
                const rate = pair[1];
                const select = row.querySelector('.tax-select[data-target="' + target + '"]');

                if (!select || rate === null || rate === undefined) {
                    return;
                }

                // المطابقة بالقيمة: خيارات القائمة قيمتها نسبة الضريبة
                const match = Array.prototype.find.call(select.options, function (opt) {
                    return opt.value !== '' && parseFloat(opt.value) === parseFloat(rate);
                });

                if (match) {
                    select.value = match.value;
                    updateHiddenInput(select);
                }
            });
        }

        function handleUnitChange(selectElement) {
            const row = selectElement.closest('.item-row');
            const option = selectElement.options[selectElement.selectedIndex];
            const priceInput = row.querySelector('.price');
            const conversionFactorInput = row.querySelector('.conversion-factor');
            const quantityInput = row.querySelector('.quantity');
            const productId = row.querySelector('.product-select').value;

            /* «معامل التحويل: ١٢٠» كان سطراً ثالثاً تحت الوحدة، ونصُّه
               مكتوبٌ أصلاً في اسم الخيار المختار («كرتون × ١٢٠»). حلّ
               محلَّه ما لا يُعرَف إلّا بالحساب: الكمية بالوحدة الأساسية،
               ويكتبها `updateQuantityInBaseUnit()` أدناه. */
            if (option && option.value) {
                const price = parseFloat(option.getAttribute('data-price')) || 0;
                const factor = parseFloat(option.getAttribute('data-conversion-factor')) || 1;

                if (price > 0) priceInput.value = price.toFixed(2);
                conversionFactorInput.value = factor;
            } else {
                conversionFactorInput.value = '1';

                const product = productId ? productsWithUnitsData[productId] : null;
                if (product && product.sale_price > 0) priceInput.value = parseFloat(product.sale_price).toFixed(2);
            }

            updateQuantityInBaseUnit(quantityInput);

            if (typeof invoiceCalculator !== 'undefined' && invoiceCalculator) {
                invoiceCalculator.calculateTotals();
            }
        }

        /**
         * رقمٌ بكسوره الفعلية: `2400` لا `2400.00`، و`2.5` لا `2.50`.
         *
         * التلميحان سطرٌ واحدٌ في عمودٍ ضيّق، وأصفارٌ لاحقة تدفع النصَّ إلى
         * الالتفاف بلا أن تضيف معنى. أربعُ خاناتٍ حدٌّ أعلى — وهو ما يخزّنه
         * `invoice_items.quantity`.
         */
        function trimNumber(value) {
            const number = parseFloat(value) || 0;

            return String(parseFloat(number.toFixed(4)));
        }

        /** اسم الوحدة الأساسية للصنف المختار — وإلّا العبارة العامّة */
        function baseUnitNameFor(row) {
            const select = row.querySelector('.product-select');
            const product = select && select.value ? productsWithUnitsData[select.value] : null;

            if (product && product.units && product.units.length) {
                const base = product.units.filter(function (unit) { return unit.is_base_unit; })[0];

                if (base && base.name) return base.name;
            }

            return @json(__('sales_invoice.ui.base_unit'));
        }

        function updateQuantityInBaseUnit(quantityInput) {
            const row = quantityInput.closest('.item-row');
            const factor = parseFloat(row.querySelector('.conversion-factor').value) || 1;
            const quantity = parseFloat(quantityInput.value) || 0;
            const inBase = quantity * factor;

            row.querySelector('.quantity-in-base-unit').value = inBase;

            const baseDisplay = row.querySelector('.base-unit-quantity');

            /* اسم الوحدة الأساسية لا عبارة «الوحدة الأساسية»: «= ٢٤٠٠ حبة»
               يُقرأ بلا تفسير، و«= ٢٤٠٠ الوحدة الأساسية» يحتاج قارئاً يعرف
               أيَّ وحدةٍ يقصد. ويسقط إلى العبارة حين لا تُعرَف. */
            if (baseDisplay && factor != 1) {
                baseDisplay.style.display = '';
                baseDisplay.textContent = '= ' + trimNumber(inBase) + ' ' + baseUnitNameFor(row);
                baseDisplay.title = baseDisplay.textContent;
            } else if (baseDisplay) {
                baseDisplay.style.display = 'none';
            }

            const available = parseFloat(quantityInput.getAttribute('data-available') || 0);
            const remainingInfo = row.querySelector('.remaining-info');

            /* الخدمي والرقمي لا رصيد لهما — فلا «متاح» يُعرَض ولا كمّيةٌ
               تُصبَغ بالأحمر لتجاوزها مخزوناً لا وجود له. */
            if (!rowTracksStock(row)) {
                if (remainingInfo) remainingInfo.style.display = 'none';
                quantityInput.classList.remove('is-invalid');
                return;
            }

            if (!remainingInfo || quantity <= 0) return;

            const remaining = available - inBase;

            /* «المتاح ١١٠٣ · المتبقّي ‎-١٢٩٧» — سطرٌ واحد بفاصلٍ نقطيّ.
               كان بنقطتين وشَرطةٍ طويلة وكسورٍ ثابتة (`toFixed(2)`)، فيخرج
               «المتاح: 1103 — المتبقّي: -1297.00» ويلتفّ سطرين في عمودٍ
               ضيّق. والعنوان الكامل في `title` لمن ضاق عنده العمود. */
            const text = @json(__('sales_invoice.ui.available')) + ' ' + trimNumber(available)
                + ' · ' + @json(__('sales_invoice.ui.remaining')) + ' ' + trimNumber(remaining);

            remainingInfo.style.display = '';
            remainingInfo.textContent = text;
            remainingInfo.title = text;
            remainingInfo.classList.toggle('text-danger', remaining < 0);
            quantityInput.classList.toggle('is-invalid', remaining < 0);
        }

        /* حقول الأرقام: الفاصلة العشرية وحدها تُقبل، ورقمان بعدها.
           بالتفويض لا بربطٍ لكل حقل: الصفوف تُستنسخ بعد التحميل، فربطُ
           الحقول مرّةً واحدة يترك الصفوف الجديدة بلا تحقّق */
        document.addEventListener('input', function (e) {
            if (!e.target.matches('.price, .quantity, .discount-amount')) return;

            let value = e.target.value.replace(/[^\d.]/g, '');
            const parts = value.split('.');

            if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
            if (parts[1] && parts[1].length > 2) value = parseFloat(value).toFixed(2);

            e.target.value = value;
        }, true);

        /* حقول المبالغ: تُكتب بحرّية، وتُقرَّب إلى منزلتين **أمام المستخدم**.

           كان عليها `step="0.01"`، فيردّ المتصفّح نفسُه كل رقمٍ بأكثر من
           منزلتين — يكتب المستخدم ‎10.555‎ فلا تُرسَل الاستمارة أصلاً،
           وتظهر فقاعةٌ عن «أقرب قيمتين صالحتين». وهو منعٌ في غير موضعه:
           الرقم مفهومٌ ومقصود، وإنما تخزينُنا منزلتان.

           و`step="any"` وحدها لا تكفي: كانت ستقبل ‎10.555‎ ثم يقرّبها
           العمود `decimal(10,2)` في القاعدة بصمت، فيُحفَظ غيرُ ما رآه من
           كتبه — وهو أسوأ من الردّ.

           فالتقريب هنا، عند مغادرة الحقل، **وفي الحقل نفسه**: يرى المستخدم
           ‎10.56‎ قبل أن يحفظ، فالقيمة المعروضة هي القيمة المُرسَلة هي
           القيمة المخزَّنة.

           وعلى `change` لا `input`: حقل `type="number"` يُرجع قيمةً فارغة
           ما دام ما فيه غير مكتمل (‎"10."‎ مثلاً)، فتقريبٌ مع كل ضغطة
           يمسح النقطة فور كتابتها ويمنع كتابة الكسر إطلاقاً. */
        document.addEventListener('change', function (e) {
            if (!e.target.matches('.money-2dp')) return;

            const raw = parseFloat(e.target.value);

            if (!isFinite(raw)) return;

            const rounded = raw.toFixed(2);

            if (e.target.value === rounded) return;

            e.target.value = rounded;

            /* الحاسبة تستمع لـ`input` لا لـ`change` — وبلا هذا السطر يبقى
               «المتبقّي» محسوباً على الرقم قبل التقريب. */
            e.target.dispatchEvent(new Event('input', { bubbles: true }));
        }, true);

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('product-select')) handleProductChange(e.target);
            if (e.target.classList.contains('quantity')) updateQuantityInBaseUnit(e.target);
        }, true);

        /* ─────────────────────────────────────────────────────────────
           «نسخ الفاتورة»

           زرّ النسخ في شاشة العرض يفتح هذه الشاشة بـ`?copy_from=<id>`،
           والمتحكّم يمرّر بنود الفاتورة الأصلية في `copySource`. تُملأ
           الصفوف هنا لا في القالب: الصفّ الأوّل قالبٌ يستنسخه
           `addNewItemRow()`، فبناؤه من PHP يكسر ذلك العقد.

           لا يُنسَخ الرقم ولا التاريخ ولا المدفوعات: مستندٌ جديد.
           ───────────────────────────────────────────────────────────── */
        /* -------------------------------------------------------------
           تعبئةُ النموذج من مستندٍ آخر — نسخةُ فاتورة، أو عرضُ سعر.

           كانت هذه الشيفرة داخل شرط `copySource` فلا توجد إلّا
           حين تُفتح الشاشةُ بـ`?copy_from=`. وصار للتعبئة مصدرٌ ثانٍ —
           مُنتقي عرض السعر أدناه — يعمل بعد تحميل الصفحة وبلا وسيطٍ في
           الرابط، فأُخرجت دالّةً يناديها المصدران معاً. والشكلُ واحدٌ:
           `quoteSource()` في المتحكّم تردّ حمولةَ `copySource` نفسَها.
           ------------------------------------------------------------- */
        window.fillInvoiceFromSource = function (source, notice) {
                const tbody = document.querySelector('#items-table tbody');
                const addRow = document.getElementById('add-row');

                if (!tbody || !addRow) return;

                const setValue = function (field, value) {
                    if (!field || value === null || value === undefined) return;

                    /* قائمةٌ لا تحمل هذه القيمة تُترك على حالها: كتابةُ
                       قيمةٍ غريبة على `select` تجعله بلا خيارٍ محدَّد —
                       يُعرَض فارغاً ويُرسَل خالياً، فيُقرأ في الخادم على
                       الافتراضي بلا أن يعلم أحد.

                       والمطابقةُ **بالقيمة العددية** حين تفشل الحرفية:
                       مُنتقي ضريبة البند خياراتُه `15` كما يطبعها القالب،
                       والقاعدةُ تردّ `"15.00"` بحكم `decimal:2`. فمقارنةُ
                       النصّ تفشل، ويبقى المُنتقي على «بدون» — فتُنسَخ
                       الفاتورةُ بلا ضريبة بنودها ويخرج إجماليها أقلّ. */
                    if (field.tagName === 'SELECT') {
                        const wanted = String(value);
                        const options = [...field.options];
                        let match = options.find(function (option) { return option.value === wanted; });

                        if (!match && wanted !== '' && !isNaN(parseFloat(wanted))) {
                            match = options.find(function (option) {
                                return option.value !== ''
                                    && !isNaN(parseFloat(option.value))
                                    && Math.abs(parseFloat(option.value) - parseFloat(wanted)) < 0.0001;
                            });
                        }

                        if (!match) return;

                        value = match.value;
                    }

                    field.value = value;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                };

                // بندٌ واحد أصلاً في الجدول؛ الباقي بالزرّ نفسه الذي
                // يستعمله المستخدم، فيبقى ترقيم الحقول متّسقاً
                for (let i = tbody.querySelectorAll('.item-row').length; i < source.items.length; i++) {
                    addRow.click();
                }

                tbody.querySelectorAll('.item-row').forEach(function (row, index) {
                    const item = source.items[index];
                    if (!item) return;

                    const product = row.querySelector('.product-select');

                    if (product && item.product_id) {
                        product.value = item.product_id;
                        // `handleProductChange` تجلب الوحدات والسعر
                        product.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    setValue(row.querySelector('.item-description'), item.description);
                    setValue(row.querySelector('.quantity'), item.quantity);

                    // السعر بعد الوحدة: `handleProductChange` تكتب سعر
                    // القائمة فوقه، فيُعاد سعر الفاتورة الأصلية بعدها
                    setValue(row.querySelector('.price'), item.unit_price);
                    setValue(row.querySelector('.discount-amount'), item.discount);
                    setValue(row.querySelector('.discount-type'), item.discount_type);

                    /* الضريبةُ: المُنتقي خياراتُه **نِسَب**، والحقلُ المخفيّ
                       يحمل المعرّف الذي يقرأه الخادم — ويكتبه
                       `updateHiddenInput()` عند التغيير. فإن لم تُطابق
                       النسبةُ خياراً (حُذفت الضريبة من الإعدادات) كُتب
                       المعرّفُ المنسوخ مباشرةً فلا يضيع. */
                    const tax = row.querySelector('.tax-select');

                    if (tax && item.tax_1 !== null && item.tax_1 !== undefined) {
                        setValue(tax, item.tax_1);
                    }

                    const taxIdField = row.querySelector('[name$="[tax_1_id]"]');

                    if (taxIdField && item.tax_1_id && ! taxIdField.value) {
                        taxIdField.value = item.tax_1_id;
                    }
                });

                /* ══ الوحدة تُنقل مع الرقم ══
                 *
                 * كان يُنقل `discount_amount` وحدَه ويُترك `discount_type`
                 * على ما هو في الشاشة. فمبلغُ خصمٍ منسوخ (والحمولة تصرّح
                 * دائماً بأنه `amount`) يُقرأ **نسبةً** متى كان المُنتقي
                 * على «نسبة»: خصمُ ٢٥ ريالاً يصير ٢٥٪ من الفاتورة. وهو ما
                 * يبدو للمستخدم أنّ «نوع الخصم لا يُطبَّق».
                 *
                 * والشحن مثلُه: تُنقل تكلفتُه ولا تُنقل ضريبتُه، فتخرج
                 * الفاتورة المنسوخة بضريبة شحنٍ صفراً أو بضريبة البنود. */
                setValue(document.getElementById('discount_type'), source.discount_type || 'amount');
                setValue(document.getElementById('discount_amount'), source.discount_amount);
                setValue(document.getElementById('shipping'), source.shipping_cost);
                setValue(document.getElementById('terms'), source.payment_terms);

                /* ضريبة الشحن تُختار بمعرّفها إن وصل، وإلّا بمطابقة نسبتها
                   على المبلغ المحفوظ — نظير `storedShippingTaxId()` في
                   الخادم، فالمستند القديم يحمل المبلغ لا المعرّف. */
                const shippingTaxSelect = document.getElementById('methodSelect');

                if (shippingTaxSelect) {
                    if (source.shipping_tax_id) {
                        setValue(shippingTaxSelect, source.shipping_tax_id);
                    } else if (source.shipping_cost > 0 && source.shipping_tax > 0) {
                        const rate = (source.shipping_tax / source.shipping_cost) * 100;
                        const match = [...shippingTaxSelect.options].find(function (option) {
                            return option.dataset.rate && Math.abs(parseFloat(option.dataset.rate) - rate) < 0.01;
                        });

                        if (match) setValue(shippingTaxSelect, match.value);
                    }
                }

                setValue(document.querySelector("[name='tax_type']"), source.tax_type);

                /* ══ بقيّةُ المستند ══
                 *
                 * المستودعُ ومصدرُ الطلب وطريقةُ الدفع والكوبون والدفعة —
                 * كلُّها كانت تسقط من النسخة، فيُنسخ المستندُ ناقصاً ويُعاد
                 * ملؤه بيدٍ. و`setValue` تتخطّى القائمةَ التي لا تحمل القيمة،
                 * فخزينةٌ أو مستودعٌ خارج نطاق المستخدم لا يُكتب عليها. */
                setValue(document.getElementById('storeHouseSelect'), source.store_house_id);
                setValue(document.getElementById('order_source_id'), source.order_source_id);
                setValue(document.getElementById('payment_method'), source.payment_method);
                setValue(document.getElementById('coupon_code'), source.coupon_code);

                /* الدفعةُ المقدَّمة مبلغاً في حقلها — **ولا يُؤشَّر** مربّعُ
                   «مدفوعة بالكامل» ولا مربّعُ الدفعة: تأشيرُهما يولّد سندَ
                   قبضٍ وحركةَ خزينة لمالٍ لم يُقبَض بعد. من ينسخ ينسخ
                   مستنداً، ومن يحفظ يقرّر ما قُبض. */
                if (source.advance_payment > 0) {
                    setValue(document.getElementById('paid-amount-input'), source.advance_payment);
                }

                /* الملاحظاتُ في محرّر TinyMCE لا في `textarea` عاديّ:
                   كتابةُ `value` عليه لا تظهر في المحرّر ولا تُرسَل. */
                const notesField = document.getElementById('tinyMCE');

                if (notesField && source.notes) {
                    const editor = window.tinymce && tinymce.get('tinyMCE');

                    if (editor) editor.setContent(source.notes);
                    else notesField.value = source.notes;
                }

                const employee = document.getElementById('employee_id');
                if (employee && source.employee_id) setValue(employee, source.employee_id);

                const client = document.getElementById('clientSelect');

                if (client && source.client_id) {
                    client.value = source.client_id;
                    // select2 لا يرى تغيير `value` مباشرةً
                    if (window.jQuery && jQuery.fn.select2) jQuery(client).trigger('change');
                    else client.dispatchEvent(new Event('change', { bubbles: true }));
                }

                /* إعادةُ الحساب صراحةً — لا انتظاراً للمُهلة المؤجَّلة.
                   `window.invoiceCalculator` صار يُعرَّف فعلاً (كان `let`
                   في نطاق السكربت وحده، فالحراسة تسقط والإجماليات تبقى على
                   حالها بعد التعبئة). */
                if (window.invoiceCalculator && window.invoiceCalculator.calculateTotals) {
                    window.invoiceCalculator.calculateTotals();
                }

                if (window.toastr && notice) {
                    toastr.info(notice.replace(':code', source.code || ''));
                }
        };

        @if (! empty($copySource))
            document.addEventListener('DOMContentLoaded', function () {
                window.fillInvoiceFromSource(
                    @json($copySource),
                    @json(__('sales_invoice.show.copied_from'))
                );
            });
        @endif

        /* -------------------------------------------------------------
           مُنتقي «إنشاء من عرض سعر»

           خياراتُ المُنتقي كلُّها في الصفحة، ويُخفى منها ما لا يخصّ العميل
           المختار — فلا تُعرَض على عميلٍ عروضُ عميلٍ آخر. والاختيارُ يجلب
           بنودَ المستند من `invoices.quote_source` ويملأ بها النموذج
           بالدالّة أعلاه: التعبئةُ ليست تحويلاً، وحالةُ العرض لا تتغيّر
           إلّا بحفظ الفاتورة.
           ------------------------------------------------------------- */
        (function () {
            const quoteSelect = document.getElementById('quoteSelect');
            const clientSelect = document.getElementById('clientSelect');

            if (!quoteSelect || !clientSelect) return;

            const hint = document.getElementById('quoteSelectHint');
            const hintDefault = @json(__('sales_invoice.from_quote.hint'));
            const hintPickClient = @json(__('sales_invoice.from_quote.pick_client_first'));
            const hintNone = @json(__('sales_invoice.from_quote.none_for_client'));
            const sourceUrl = @json(url('invoices/quote-source'));

            function filterQuotesByClient() {
                const clientId = clientSelect.value;
                let visible = 0;

                [...quoteSelect.options].forEach(function (option) {
                    if (!option.value) return;

                    const belongs = clientId && option.dataset.client === String(clientId);

                    option.hidden = !belongs;
                    option.disabled = !belongs;

                    if (belongs) visible++;
                });

                // خيارٌ صار مخفيّاً وهو المختار يعود إلى «اختر عرض سعر»
                if (quoteSelect.selectedOptions[0] && quoteSelect.selectedOptions[0].hidden) {
                    quoteSelect.value = '';
                }

                if (hint) {
                    hint.textContent = !clientId ? hintPickClient : (visible ? hintDefault : hintNone);
                }
            }

            clientSelect.addEventListener('change', filterQuotesByClient);

            // select2 يبدّل القيمة بحدثٍ من jQuery لا يراه المستمع أعلاه
            if (window.jQuery) jQuery(clientSelect).on('change', filterQuotesByClient);

            quoteSelect.addEventListener('change', function () {
                const id = quoteSelect.value;

                if (!id) return;

                quoteSelect.disabled = true;

                fetch(sourceUrl + '/' + encodeURIComponent(id), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || '');

                            return data;
                        });
                    })
                    .then(function (source) {
                        window.fillInvoiceFromSource(source, @json(__('sales_invoice.from_quote.filled')));

                        const coupon = document.getElementById('coupon_code');

                        if (coupon && source.coupon_code) coupon.value = source.coupon_code;
                    })
                    .catch(function (error) {
                        if (window.toastr) toastr.error(error.message || hintNone);
                    })
                    .finally(function () {
                        quoteSelect.disabled = false;
                    });
            });

            filterQuotesByClient();
        })();

        /* -------------------------------------------------------------
           فحصُ الكوبون قبل الحفظ

           الخصمُ يقع في الخادم، فلو لم تُره الشاشةُ لظنّ البائعُ أنّ
           الإجمالي اختلّ — وهو الدرسُ المستفاد من «عروض الحسم». والفحصُ
           يُعاد عند الحفظ على كلّ حال، فهذا عرضٌ لا حُكم.
           ------------------------------------------------------------- */
        (function () {
            const button = document.getElementById('coupon-check');
            const input = document.getElementById('coupon_code');
            const feedback = document.getElementById('coupon-feedback');

            if (!button || !input) return;

            button.addEventListener('click', function () {
                const clientSelect = document.getElementById('clientSelect');
                const shipping = document.getElementById('shipping');

                const productIds = [...document.querySelectorAll('#items-table .product-select')]
                    .map(function (select) { return select.value; })
                    .filter(Boolean);

                /* الوعاءُ: مجموعُ البنود بعد خصومها وبعد خصم الفاتورة —
                   وهو الوعاءُ نفسُه الذي يمرّره الخادم إلى الخدمة. */
                const subtotal = [...document.querySelectorAll('#items-table .item-row')]
                    .reduce(function (sum, row) {
                        const total = row.querySelector('.total');

                        return sum + (parseFloat(total ? total.value : 0) || 0);
                    }, 0);

                const generalDiscount = parseFloat(
                    (document.getElementById('discount_amount') || {}).value || 0
                ) || 0;

                const isPercent = (document.getElementById('discount_type') || {}).value === 'percentage';
                const amount = Math.max(0, subtotal - (isPercent ? subtotal * generalDiscount / 100 : generalDiscount));

                button.disabled = true;

                fetch(@json(route('invoices.check_coupon')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    },
                    body: JSON.stringify({
                        code: input.value,
                        client_id: clientSelect ? clientSelect.value : null,
                        amount: amount,
                        product_ids: productIds,
                        shipping_cost: shipping ? shipping.value : 0,
                    }),
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!feedback) return;

                        feedback.style.display = '';
                        feedback.textContent = data.message || '';
                        feedback.classList.toggle('text-danger', !data.ok);
                        feedback.classList.toggle('text-success', !!data.ok);
                    })
                    .catch(function () {
                        if (!feedback) return;

                        feedback.style.display = '';
                        feedback.classList.add('text-danger');
                        feedback.textContent = @json(__('sales_invoice.coupon.zero_discount'));
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
            });
        })();
    </script>
@endsection
