{{--
    ══════════════════════════════════════════════════════════════════════
    نموذج عرض السعر — إضافةً وتعديلاً في قالب واحد
    ══════════════════════════════════════════════════════════════════════

    البنية منقولة عن شاشة «إنشاء فاتورة مشتريات»
    (`purchases::partials.form`): بطاقة بيانات المستند، ثم جدول البنود
    وإجمالياته، ثم تبويبات التفاصيل الإضافية، ثم الملاحظات وأزرار الحفظ.
    الأصناف بالبادئة `sl-` من `sales::partials.document-theme`.

    ── ما كان ──

    `create.blade.php` و`edit.blade.php` قالبان منفصلان (٣٤ ك و٤٥ ك)
    يتباعدان مع كل تعديل، وفيهما:

      • عربيّةٌ مكتوبة في الوسم، لا مفتاح ترجمة واحد.
      • «ر.س» نصّاً في اثني عشر موضعاً، وكسورٌ مثبَّتة عند رقمين — بينما
        شاشة «العملات» تضبط الرمز والكسور والفواصل.
      • `expiry_date` و`terms` و`quote_number` حقولٌ تُعرَض وتُرسَل ولا
        يقرأها المتحكّم أصلاً: يكتبها المستخدم فتضيع عند الحفظ.
      • jQuery وSelect2 من شبكة توزيع في رأس القالب — والقالب الأمّ
        يحمّلهما، فنسختان تُفقِد الأولى مُلحقاتِها.
      • حساب الإجماليات في `invoice.js` بمنطقٍ يخالف ما يحسبه الخادم،
        فالرقم المعروض غير المحفوظ.

    ── ما صار ──

      الرقم        ← «الترقيم المتسلسل»، قسم `quotation`
      العملة والكسور ← «العملات»
      التاريخ وصيغته ← إعدادات اللغة النشطة
      نِسَب الضريبة  ← «إعدادات الضريبة»
      الحساب المحاسبي والفترة ← تنبيهٌ في الشاشة لا رسالةٌ بعد الحفظ
      الألوان والخطّ ← «مظهر النظام» عبر متغيّرات `--app-*`

    والحساب كلّه في `public/assets/js/sales-document-form.js` بنفس معادلة
    `QuoteController::store()` سطراً بسطر.

    ── الوسائط ──

    @param string $formAction     رابط الإرسال
    @param bool   $isEdit
    @param string $backUrl
    @param string $docNumber      رقم العرض المقترَح أو المحفوظ
    @param string $docLabel       عنوان البطاقة
    @param \Illuminate\Support\Collection $clients
    @param \Illuminate\Support\Collection $products
    @param \Illuminate\Support\Collection $taxes
    @param array  $rows           بنود محفوظة/معادة من `old()`
    @param array  $defaults       قيم ابتدائية
    @param string|null $periodBlock سبب قفل الفترة، أو null
    @param array  $config         مفاتيح العرض
--}}

@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use Modules\Sales\Support\ProductUnits;

    $config = array_merge([
        'second_tax' => true,     // عمود ضريبة ثانية لكل بند
        'shipping' => true,       // تبويب الشحن
        'attachments' => true,    // تبويب المرفقات
        'expiry_date' => true,    // حقل «صالح حتى»
    ], $config ?? []);

    $isEdit = (bool) ($isEdit ?? false);
    $rows = (isset($rows) && is_array($rows)) ? $rows : [];
    $defaults = (isset($defaults) && is_array($defaults)) ? $defaults : [];
    $periodBlock = (isset($periodBlock) && is_string($periodBlock) && $periodBlock !== '') ? $periodBlock : null;

    /* يمرّره `QuoteController::formData()` من `DEFAULT_STATUS`، فلا يفترق
       ما يعرضه المُنتقي عمّا يحفظه الخادم — ولا حين يبدّله وارثٌ */
    $defaultStatus = $defaultStatus ?? \App\Models\Quote::STATUS_APPROVED;

    $clients = $clients ?? collect();
    $salesPersons = $salesPersons ?? collect();
    $products = $products ?? collect();
    $taxes = $taxes ?? collect();

    $isRtl = $languageIsRtl ?? LanguageSettings::isRtl();

    /* القيمة المعروضة: ما أعاده `old()` بعد خطأ تحقّق أوّلاً — وإلّا ما
       جاء من المتحكّم. بدونها يفقد المستخدم ما كتبه عند أوّل خطأ. */
    $cur = fn (string $field, $fallback = null) => old($field, $defaults[$field] ?? $fallback);

    /* خريطة نِسَب الضريبة تُبنى مرّةً هنا وتُمرَّر إلى JS: بدونها يحتاج كل
       تغييرٍ في بندٍ نداءَ شبكة ليعرف النسبة. */
    $taxRates = $taxes->mapWithKeys(fn ($tax) => [(string) $tax->id => (float) $tax->tax])->all();

    /* نوع الضريبة — «متضمَّنة» تُستخرَج من السعر ولا تُضاف فوقه.
       كان الحسّاب في الشاشة يضيفها دائماً بينما يستخرجها الخادم
       (`DocumentTaxCalculator`)، فيخالف المعروضُ المحفوظَ. */
    $taxTypes = $taxes->mapWithKeys(fn ($tax) => [(string) $tax->id => (string) $tax->type])->all();

    /* سعر الصنف ووصفه ووحداته — يملأ بها JS البندَ عند اختياره، ولا يدهس
       ما كتبه المستخدم.

       والوحدات من `ProductUnits` نفسِه الذي تقرأ منه شاشتا الفاتورة
       والإشعار الدائن: وحداتُ المنتج إن عُرِّفت له، وإلّا وحداتُ صنفه،
       وإلّا فلا وحدات — فيبقى المُنتقي مخفيّاً في صفّه. ونسخُ منطقٍ ثانٍ
       هنا يعني تعريفين للوحدة الافتراضية يتباعدان مع أوّل تصحيح. */
    $productUnitsMap = collect(ProductUnits::map($products))->keyBy(fn ($p) => (string) $p['id']);

    $productMap = $products->mapWithKeys(fn ($p) => [(string) $p->id => [
        'price' => (float) ($p->sale_price ?? $p->price ?? 0),
        'description' => $p->description ?? null,
        'has_units' => (bool) ($productUnitsMap[(string) $p->id]['has_units'] ?? false),
        'units' => $productUnitsMap[(string) $p->id]['units'] ?? [],
    ]])->all();

    $currencyCode = $cur('currency', CurrencySettings::code());
@endphp

<form class="form" id="salesDocumentForm" method="POST" enctype="multipart/form-data"
      action="{{ $formAction }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @include('sales::components.form-errors')
    @include('layouts.alerts.error')

    {{-- ══════════ تنبيه الفترة المقفلة ══════════
         يُعرَض قبل النموذج لا بعد الحفظ: أن يملأ المستخدم عشرين حقلاً ثم
         يُقال له إن التاريخ مقفل هو أسوأ ترتيبٍ ممكن. --}}
    @if ($periodBlock)
        <div class="sl-doc-note sl-doc-note--warn" role="alert">
            <span class="sl-doc-note__icon"><i class="feather icon-lock"></i></span>
            <div class="sl-doc-note__body">
                <strong>{{ $doc['t']('notes.period_hint') }}</strong>
                {{ $periodBlock }}
            </div>
        </div>
    @endif

    {{-- ══════════ ما يسري من الإعدادات على هذه الشاشة ══════════ --}}
    @include('sales::components.system-context', [
        'only' => ['currency', 'language', 'accounts', 'period'],
    ])

    {{-- ══════════════════════════════════════════════════════════════
         بيانات المستند
         ══════════════════════════════════════════════════════════════ --}}
    <div class="sl-doc-card">
        {{-- الرأس كما في «إنشاء فاتورة بيع»: العنوان وحدَه، وتنبيهُ الحقول
             المطلوبة قُبالته. رقمُ المستند لم يعد وسماً هنا لأنّه حقلٌ قابلٌ
             للتحرير في الشبكة أدناه — نظير `code` في شاشة الفاتورة. --}}
        <div class="sl-doc-card__head">
            <h6 class="sl-doc-card__title">
                <i class="feather icon-file-text"></i> {{ $docLabel }}
            </h6>
            <span class="sl-hint">{{ $doc['t']('ui.required_note') }}</span>
        </div>

        <div class="sl-doc-card__body">
            <div class="row g-3">

                <div class="col-lg-3 col-md-6">
                    <div class="sl-field">
                        <label for="client_id">
                            {{ $doc['t']('fields.client') }} <span class="required">*</span>
                        </label>

                        <div class="sl-select-add">
                        <select id="client_id" name="client_id" required
                                class="form-control select2 sl-select2 @error('client_id') is-invalid @enderror"
                                data-placeholder="{{ $doc['t']('fields.select_client') }}">
                            <option value="">{{ $doc['t']('fields.select_client') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                    @selected((string) $cur('client_id') === (string) $client->id)>
                                    {{ $client->trade_name ?: trim(($client->first_name ?? '').' '.($client->last_name ?? '')) }}@if (filled($client->code)) - {{ $client->code }}@endif
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

                        @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- الرقم للقراءة فقط: يُولَّد من «الترقيم المتسلسل»
                     ويُرسَل كما هو، والخادم يستبدله إن كان محجوزاً بدل
                     أن يرفض المستند كلّه — انظر `resolveDocumentNumber()`. --}}
                <div class="col-lg-3 col-md-6">
                    <div class="sl-field">
                        <label for="quote_number">{{ $doc['t']('fields.number') }}</label>

                        <input type="text" id="quote_number" name="quote_number" maxlength="50"
                               class="form-control @error('quote_number') is-invalid @enderror"
                               value="{{ $cur('quote_number', $docNumber) }}" readonly>

                        @error('quote_number')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @else
                            <span class="sl-hint">{{ $doc['t']('fields.number_hint') }}</span>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="sl-field">
                        <label for="quote_date">
                            {{ $doc['t']('fields.date') }} <span class="required">*</span>
                        </label>

                        <input type="date" id="quote_date" name="quote_date" required
                               class="form-control @error('quote_date') is-invalid @enderror"
                               value="{{ $cur('quote_date', now()->format('Y-m-d')) }}">

                        @error('quote_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if ($config['expiry_date'])
                    <div class="col-lg-3 col-md-6">
                        <div class="sl-field">
                            <label for="expiry_date">
                                {{ $doc['t']('fields.expiry_date') }}
                                <span class="sl-hint d-inline">({{ $doc['t']('ui.optional') }})</span>
                            </label>

                            <input type="date" id="expiry_date" name="expiry_date"
                                   class="form-control @error('expiry_date') is-invalid @enderror"
                                   value="{{ $cur('expiry_date') }}">

                            @error('expiry_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @else
                                <span class="sl-hint">{{ $doc['t']('fields.expiry_hint') }}</span>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- مسؤول المبيعات — القائمة مقصورةٌ على نطاق رؤية المستخدم
                     (`DataScope::employeeOptions()`): الكلّ، أو فرعُه وما تحته،
                     أو هو ومن يتبعه إدارياً، أو هو وحدَه.

                     ولم يكن للمستند مُنتقٍ أصلاً رغم وجود العمود
                     `employee_id` — فكان `convertToInvoice()` ينسب الفاتورة
                     إلى من ضغط زرَّ التحويل لا إلى من باع. --}}
                @if ($salesPersons->isNotEmpty())
                    <div class="col-lg-3 col-md-6">
                        <div class="sl-field">
                            <label for="employee_id">{{ $doc['t']('fields.employee') }}</label>

                            <select id="employee_id" name="employee_id"
                                    class="form-control select2 @error('employee_id') is-invalid @enderror">
                                <option value="">{{ $doc['t']('fields.select_employee') }}</option>
                                @foreach ($salesPersons as $salesPerson)
                                    <option value="{{ $salesPerson->id }}"
                                        @selected((string) $cur('employee_id', auth()->user()->employee_id) === (string) $salesPerson->id)>
                                        {{ $salesPerson->full_name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('employee_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @else
                                <span class="sl-hint">{{ __('sales_invoice.ui.sales_person_scope_hint') }}</span>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- شروط الدفع — المهلةُ بالأيام، وتاريخُ الاستحقاق محسوباً
                     تحتها. تنتقل مع المستند إلى الفاتورة عند التحويل. --}}
                @include('sales::components.payment-terms-field', [
                    'baseDateId' => 'quote_date',
                    'value' => $cur('terms'),
                ])

                {{-- «إعطاء عروض الأسعار حالات يدوية» — إعدادات الفواتير.
                     يظهر فقط حين يُفعَّل الإعداد، ويُستثنى «مُحوَّل إلى فاتورة»:
                     تلك القيمة تكتبها convertToInvoice() وحدها. --}}
                {{-- المفتاح يختلف بالمستند: `manual_quote_status` لعرض السعر
                     و`manual_sales_order_status` لأمر البيع — والمتحكّم هو
                     من يقرأه، فالقالب يتلقّى النتيجة --}}
                @if ($manualStatusEnabled ?? false)
                    <div class="col-lg-3 col-md-6">
                        <div class="sl-field">
                            <label for="status">{{ $doc['t']('status.label') }}</label>

                            <select id="status" name="status" class="form-control select2 @error('status') is-invalid @enderror">
                                {{-- المختار ابتداءً هو ما يحفظه المتحكّم حين لا
                                     يُرسَل شيء (`DEFAULT_STATUS` = «معتمد»)، فلا
                                     يعِد المُنتقي بحالةٍ ويحفظ الخادمُ أخرى --}}
                                @foreach ($manualStatuses ?? [] as $statusValue)
                                    <option value="{{ $statusValue }}"
                                        {{ old('status', $currentStatus ?? $defaultStatus) === $statusValue ? 'selected' : '' }}>
                                        {{ $doc['t']('status.'.$statusValue) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('status')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- «معاملة الضريبة» أُخفيت عن الشاشة بطلب المستخدم: البند
                     يحمل ضريبته في عموده، فقائمةٌ ثانيةٌ فوقه تُربك أكثر
                     ممّا تفيد.

                     الحقل يبقى مُرسَلاً لا محذوفاً: `store()`/`update()`
                     يتحقّقان منه `required|in:1,2,3`، وعمود `quotes.tax_type`
                     يقرؤه `convertToInvoice()` وحسابُ ضريبة الشحن. حذفُه من
                     النموذج يعني 422 على كلّ حفظ. والقيمة من المستند نفسِه
                     لا ثابتةً، فعرضٌ محفوظ بـ«معفى» يبقى معفيّاً عند تعديله. --}}
                <input type="hidden" name="tax_type" value="{{ (int) $cur('tax_type', 1) }}">

                {{-- حقل «نسبة الضريبة» أُزيل: لا عمود له في `quotes`، ولم
                     يكن `store()` يحفظه، وكان يُستعمل في موضعٍ واحد —
                     اشتقاق ضريبة الشحن — بينما للشحن حقل ضريبته في تبويب
                     الشحن. مصدران لرقمٍ واحد يعنيان أن ما يعرضه المتصفّح
                     غير ما يحفظه الخادم. --}}

                {{-- العملة تُثبَّت مع المستند: تبديل الافتراضية في
                     «العملات» لاحقاً لا يمسّ عرضاً محفوظاً. --}}
                <div class="col-12">
                    <p class="sl-hint mb-0">
                        <i class="feather icon-dollar-sign"></i>
                        {{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})
                        — {{ $doc['t']('notes.currency_frozen') }}
                    </p>
                    <input type="hidden" name="currency" value="{{ $currencyCode }}">
                </div>

            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════════════════
         البنود
         ══════════════════════════════════════════════════════════════ --}}
    <div class="sl-doc-card">
        <div class="sl-doc-card__head">
            <h6 class="sl-doc-card__title">
                <i class="feather icon-package"></i> {{ $doc['t']('sections.items') }}
            </h6>
            <span class="sl-hint">
                <i class="feather icon-dollar-sign"></i>
                {{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})
            </span>
        </div>

        {{-- مستندٌ قديمٌ حُفظ بخصم بند: لا عمودَ يُريه الآن، فيُقال إنّه محفوظ.
             يُحمَل مخفيّاً كما هو فلا يتغيّر الإجماليُّ بمجرّد حفظ التعديل. --}}
        @if (collect($rows)->contains(fn ($r) => (float) ($r['discount'] ?? 0) > 0))
            <div class="sl-doc-note mb-3">
                <span class="sl-doc-note__icon"><i class="feather icon-info"></i></span>
                <div class="sl-doc-note__body">{{ __('sales_documents.items.legacy_line_discount') }}</div>
            </div>
        @endif

        {{-- الضريبة على كل الأصناف — تظهر فقط حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة --}}
        @include('partials.comprehensive-tax-field')

        <div class="table-responsive">
            <table class="table sl-doc-table mb-0" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:46px">{{ $doc['t']('items.row') }}</th>
                        <th style="min-width:200px">
                            {{ $doc['t']('items.item') }} <span class="required">*</span>
                        </th>
                        <th style="min-width:150px">{{ $doc['t']('items.description') }}</th>
                        <th style="width:100px">
                            {{ $doc['t']('items.quantity') }} <span class="required">*</span>
                        </th>
                        {{-- الوحدة عمودٌ قائمٌ بذاته كما في شاشة الفاتورة:
                             كميةٌ بلا وحدتها لا يُعرَف أهي حبّةٌ أم كرتون،
                             ولا تصل الوحدةُ إلى الفاتورة عند التحويل --}}
                        <th style="width:130px">{{ $doc['t']('items.unit') }}</th>
                        <th style="width:130px">{{ $doc['t']('items.unit_price') }}</th>
                        {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                        <th style="width:140px">{{ $doc['t']('items.tax_1') }}</th>
                        @if ($config['second_tax'])
                            <th style="width:140px">{{ $doc['t']('items.tax_2') }}</th>
                        @endif
                        <th class="num" style="width:130px">{{ $doc['t']('items.total') }}</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>

                {{-- تُملأ من `#rowTemplate` — لا صفّ في الوسم، فصفٌّ ثابت
                     يُرسَل فارغاً مع الحفظ ويصير بنداً بلا صنف --}}
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div class="sl-doc-card__foot">
            <button type="button" class="btn btn-outline-primary btn-sm" id="addRowBtn">
                <i class="feather icon-plus"></i> {{ $doc['t']('items.add_row') }}
            </button>

            {{-- المجموع والإجمالي النهائي ثابتان، وما بينهما مشروط:
                 `data-total-row` يمسكه السكربت فيُظهره متى صار للقيمة وجود
                 ويُخفيه متى عادت صفراً. يبدأ مخفيّاً لأن النموذج يبدأ بلا
                 بنود، وأوّل `recalculate()` يفصل فيه. --}}
            <div class="sl-totals">
                <div class="sl-totals__row">
                    <span>{{ $doc['t']('totals.subtotal') }}</span>
                    <span data-total="subtotal">{{ CurrencySettings::format(0) }}</span>
                </div>
                <div class="sl-totals__row d-none" data-total-row="discount">
                    <span>{{ $doc['t']('totals.discount') }}</span>
                    <span data-total="discount">{{ CurrencySettings::format(0) }}</span>
                </div>
                @if ($config['shipping'])
                    <div class="sl-totals__row d-none" data-total-row="shipping">
                        <span>{{ $doc['t']('totals.shipping') }}</span>
                        <span data-total="shipping">{{ CurrencySettings::format(0) }}</span>
                    </div>
                @endif
                <div class="sl-totals__row d-none" data-total-row="tax">
                    <span>{{ $doc['t']('totals.tax') }}</span>
                    <span data-total="tax">{{ CurrencySettings::format(0) }}</span>
                </div>
                <div class="sl-totals__row sl-totals__row--grand">
                    <span>{{ $doc['t']('totals.grand_total') }}</span>
                    <span data-total="grand">{{ CurrencySettings::format(0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         التفاصيل الإضافية — تبويبات كما في شاشة فاتورة الشراء
         ══════════════════════════════════════════════════════════════ --}}
    <div class="sl-doc-card">
        <div class="sl-doc-card__head sl-doc-card__head--flush">
            <ul class="nav sl-doc-tabs w-100" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" data-bs-toggle="tab"
                       href="#section-discount" role="tab">
                        <i class="feather icon-percent"></i> {{ $doc['t']('sections.discount') }}
                    </a>
                </li>

                @if ($config['shipping'])
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab"
                           href="#section-shipping" role="tab">
                            <i class="feather icon-truck"></i> {{ $doc['t']('sections.shipping') }}
                        </a>
                    </li>
                @endif

                @if ($config['attachments'])
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" data-bs-toggle="tab"
                           href="#section-documents" role="tab">
                            <i class="feather icon-paperclip"></i> {{ $doc['t']('sections.documents') }}
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        <div class="sl-doc-card__body">
            <div class="tab-content">

                {{-- ── الخصم على العرض ── --}}
                <div class="tab-pane active" id="section-discount" role="tabpanel">
                    {{-- القيمة ونوعُها في مجموعةٍ واحدة كما في «إنشاء فاتورة
                         بيع»: رقمُ خصمٍ بلا نوعه لا يُقرأ، وحقلان متباعدان
                         يُقرآن حقلين مستقلّين. والنوع يبقى `select` عاديّاً —
                         `select2` داخل `input-group` يكسر ارتفاع المجموعة. --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sl-field">
                                <label for="discount_amount">{{ $doc['t']('fields.discount_amount') }}</label>

                                <div class="input-group">
                                    <input type="number" id="discount_amount" name="discount_amount" min="0" step="0.01"
                                           class="form-control sl-settle @error('discount_amount') is-invalid @enderror"
                                           value="{{ $cur('discount_amount', 0) }}">

                                    <select id="discount_type" name="discount_type" style="max-width:9rem"
                                            class="form-control sl-settle @error('discount_type') is-invalid @enderror">
                                        @foreach (['amount', 'percentage'] as $discountType)
                                            <option value="{{ $discountType }}"
                                                @selected($cur('discount_type', 'amount') === $discountType)>
                                                {{ $doc['t']('discount_types.'.$discountType) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('discount_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- ── الكوبون ──
                             حقلٌ نصّيّ لا مُنتقٍ: الكوبون رمزٌ يحمله العميل
                             ويُملى على البائع، وعرضُ الكوبونات كلِّها في
                             قائمةٍ يكشف ما لم يُعطَ له. والفحصُ في الخادم
                             عند الحفظ — ورمزٌ مرفوض لا يُسقط المستند، يُقال
                             سببُه في رسالة الحفظ. --}}
                        @if (($invoiceSettings->enable_coupons ?? false))
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="coupon_code">
                                        {{ __('sales_invoice.coupon.label') }}
                                        <span class="sl-hint d-inline">({{ $doc['t']('ui.optional') }})</span>
                                    </label>

                                    {{-- «تحقّق» يفحصه في الخادم ويردّ المبلغَ
                                         أو سببَ الرفض، ويُعاد حساب الإجماليات
                                         به. والشاشة يجب أن تُري ما سيحفظه
                                         الخادم — وإلّا رأى المستخدم إجمالياً
                                         وحُفظ غيرُه. --}}
                                    <div class="input-group">
                                        <input type="text" id="coupon_code" name="coupon_code" maxlength="64"
                                               class="form-control sl-settle @error('coupon_code') is-invalid @enderror"
                                               placeholder="{{ __('sales_invoice.coupon.placeholder') }}"
                                               value="{{ $cur('coupon_code') }}">

                                        <button type="button" id="coupon-check" class="btn btn-outline-primary"
                                                style="flex:0 0 auto">
                                            {{ __('sales_invoice.coupon.apply') }}
                                        </button>
                                    </div>

                                    <small class="sl-hint">{{ __('sales_invoice.coupon.hint') }}</small>
                                    <small id="coupon-feedback" class="sl-hint d-block" style="display:none"></small>

                                    @error('coupon_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── الشحن ── --}}
                @if ($config['shipping'])
                    <div class="tab-pane" id="section-shipping" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="shipping_cost">{{ $doc['t']('fields.shipping_cost') }}</label>

                                    <input type="number" id="shipping_cost" name="shipping_cost" min="0" step="0.01"
                                           class="form-control sl-settle @error('shipping_cost') is-invalid @enderror"
                                           value="{{ $cur('shipping_cost', 0) }}">

                                    @error('shipping_cost')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            {{-- الضريبة تُختار ومبلغُها يحسبه الخادم: كان
                                 الحقل مبلغاً حرّاً يكتبه المستخدم، فيُحفَظ
                                 شحنٌ بمئةٍ وضريبةُ شحنٍ بخمسين لا تقابل نسبةً
                                 مضبوطة، ثمّ تخالف الفاتورةَ بعد التحويل --}}
                            <div class="col-md-6">
                                <div class="sl-field">
                                    <label for="shipping_tax_id">
                                        {{ $doc['t']('fields.shipping_tax') }}
                                        <span class="sl-hint d-inline">({{ $doc['t']('ui.optional') }})</span>
                                    </label>

                                    <select id="shipping_tax_id" name="shipping_tax_id"
                                            class="form-control sl-settle @error('shipping_tax_id') is-invalid @enderror">
                                        <option value="">{{ $doc['t']('items.no_tax') }}</option>
                                        @foreach ($taxes as $tax)
                                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                data-name="{{ $tax->name }}"
                                                @selected((string) $cur('shipping_tax_id') === (string) $tax->id)>
                                                {{ $tax->name }} ({{ $tax->tax }}%)
                                            </option>
                                        @endforeach
                                    </select>

                                    <small class="sl-hint">{{ $doc['t']('fields.shipping_tax_hint') }}</small>

                                    @error('shipping_tax_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── المرفقات ── --}}
                @if ($config['attachments'])
                    <div class="tab-pane" id="section-documents" role="tabpanel">
                        <div class="sl-field">
                            <label for="attachments">{{ $doc['t']('fields.attachments') }}</label>

                            <input type="file" name="attachments" id="attachments" class="d-none"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">

                            <div class="sl-upload" id="uploadArea" role="button" tabindex="0">
                                <i class="feather icon-upload-cloud"></i>
                                <span class="sl-upload__title">
                                    {{ $doc['t']('upload.click') }}
                                    <span class="sl-hint">{{ $doc['t']('upload.or') }}</span>
                                    {{ $doc['t']('upload.browse') }}
                                </span>
                                <span class="sl-upload__hint">{{ $doc['t']('upload.hint') }}</span>
                                <div id="selectedFile" class="text-success small d-none"></div>
                            </div>

                            @if (! empty($defaults['attachments']))
                                <div class="d-flex align-items-center mt-2" style="gap:.5rem;flex-wrap:wrap">
                                    <a href="{{ asset('storage/'.$defaults['attachments']) }}" target="_blank"
                                       rel="noopener" class="btn btn-outline-secondary btn-sm">
                                        <i class="feather icon-paperclip"></i>
                                        {{ $doc['t']('upload.current') }}
                                    </a>
                                    <span class="sl-hint">{{ $doc['t']('upload.replace') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         الملاحظات وأزرار الحفظ
         ══════════════════════════════════════════════════════════════ --}}
    <div class="sl-doc-card">
        <div class="sl-doc-card__head">
            <h6 class="sl-doc-card__title">
                <i class="feather icon-edit-3"></i> {{ $doc['t']('sections.notes') }}
            </h6>
        </div>

        <div class="sl-doc-card__body">
            <div class="sl-field">
                <label for="notes">{{ $doc['t']('fields.notes') }}</label>

                <textarea id="notes" name="notes" rows="5"
                          class="form-control @error('notes') is-invalid @enderror"
                          placeholder="{{ $doc['t']('fields.notes_placeholder') }}">{{ $cur('notes') }}</textarea>

                @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="sl-doc-card__foot">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ $isRtl ? 'right' : 'left' }}"></i>
                {{ $doc['t']('ui.back') }}
            </a>

            <div class="sl-toolbar sl-toolbar--end">
                <button type="button" class="btn btn-outline-danger" id="cancelBtn">
                    <i class="feather icon-x"></i> {{ $doc['t']('ui.cancel') }}
                </button>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="feather icon-save"></i>
                    {{ $isEdit ? $doc['t']('ui.update') : $doc['t']('ui.save') }}
                </button>
            </div>
        </div>
    </div>
</form>

{{-- ══════════════════════════════════════════════════════════════════
     قالب الصفّ — خارج النموذج عمداً: `<template>` داخله يظلّ جزءاً من
     الشجرة، وحقوله تُرسَل فارغةً مع الحفظ.
     ══════════════════════════════════════════════════════════════════ --}}
<template id="rowTemplate">
    <tr class="sl-row">
        <td class="num"><span class="sl-row__index">1</span></td>

        <td>
            <div class="sl-select-add">
                <select class="form-control sl-input sl-calc sl-select2" data-field="product_id" required>
                    <option value="">{{ $doc['t']('items.select_item') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>

                {{-- الصنف غير موجود؟ رابطٌ يفتح شاشة إضافته في تبويبٍ جديد،
                     على غرار «إضافة عميل» أعلى النموذج --}}
                @permission('stock.products.edit')
                    <a href="{{ route('products.create') }}" target="_blank" rel="noopener"
                       class="btn btn-outline-primary sl-select-add__btn"
                       title="{{ __('sales_invoice.actions.quick_add_product') }}">
                        <i class="feather icon-plus"></i>
                    </a>
                @endpermission
            </div>
        </td>

        <td><input type="text" class="form-control sl-input" data-field="description"></td>

        <td>
            {{-- `invoice_items.quantity` صار `decimal(15,4)`، فالكسر يُحفَظ
                 كما يُكتب. كان `step="1"` لأنّ العمود كان `INTEGER`:
                 كسرٌ فيه يُبتَر عند الحفظ أو تردّه القاعدة --}}
            <input type="number" class="form-control sl-input sl-calc" data-field="quantity"
                   min="0.0001" step="any" inputmode="decimal" value="1" required>
        </td>

        {{-- الوحدة — مخفيّةٌ حتى يُختار صنفٌ له وحدات. صنفٌ بلا وحدات لا
             يُسأل عن اختيارٍ لا خيار فيه، فيبقى العمود فارغاً في صفّه.

             والحقلان المخفيّان يرافقانها لا يُحسبان منها في الشاشة:
             الخادم يعيد حلّ الوحدة بـ`ProductUnits::resolve()` ولا يثق
             بما يصله، لكنّ إرسالهما يُبقي للاسم مصدراً حين تكون الوحدةُ
             من وحدات الصنف بلا صفٍّ في `product_units`. --}}
        <td>
            <select class="form-control sl-input unit-select" data-field="product_unit_id"
                    style="display:none">
                <option value="">{{ $doc['t']('items.base_unit') }}</option>
            </select>
            <input type="hidden" class="sl-input" data-field="conversion_factor" value="1">
            <input type="hidden" class="sl-input" data-field="unit_name" value="">
        </td>

        <td>
            <input type="number" class="form-control sl-input sl-calc" data-field="unit_price"
                   min="0" step="0.01" value="0">
        </td>

        {{-- خصمُ البند أُزيل من الشاشة بطلب المستخدم: الخصمُ من تبويب «الخصم
             والتسوية» وحده. الحقلان مخفيّان لا محذوفان — `sales-document-form.js`
             يسمّيهما ويقرؤهما بـ`data-field`، وعروضُ الحسم الآليّة تُطبَّق على
             البند متى كان خصمُه صفراً، والمستندُ القديم يحمل خصمَه كما حُفظ. --}}
        <input type="hidden" class="sl-input sl-calc" data-field="discount" value="0">
        <input type="hidden" class="sl-input sl-calc" data-field="discount_type" value="amount">

        <td>
            <select class="form-control sl-input sl-calc" data-field="tax_1_id">
                <option value="">{{ $doc['t']('items.no_tax') }}</option>
                @foreach ($taxes as $tax)
                    <option value="{{ $tax->id }}">{{ $tax->name }} ({{ $tax->tax }}%)</option>
                @endforeach
            </select>
        </td>

        @if ($config['second_tax'])
            <td>
                <select class="form-control sl-input sl-calc" data-field="tax_2_id">
                    <option value="">{{ $doc['t']('items.no_tax') }}</option>
                    @foreach ($taxes as $tax)
                        <option value="{{ $tax->id }}">{{ $tax->name }} ({{ $tax->tax }}%)</option>
                    @endforeach
                </select>
            </td>
        @endif

        <td class="num"><span class="sl-line-total">{{ CurrencySettings::format(0) }}</span></td>

        <td>
            <button type="button" class="sl-row__remove"
                    aria-label="{{ $doc['t']('items.remove_row') }}"
                    title="{{ $doc['t']('items.remove_row') }}">
                <i class="feather icon-trash-2"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
    @php
        /*
         * إعدادات النموذج تُجمَّع هنا لا داخل `@json([...])`.
         *
         * مُحلّل وسائط Blade يوازن الأقواس الهلالية `()` وحدها ولا يرى
         * المربّعة `[]`. فمصفوفةٌ متعدّدة الأسطر داخل `@json(` تُقطَع عند
         * أوّل موضعٍ تتوازن فيه الهلالية، فيخرج `json_encode([` بلا `]`
         * تقابله ويسقط الطلب بخطأ 500. المتغيّر الواحد يمرّ سليماً.
         *
         * الإعدادات كلّها من الخادم: لا رمز عملة ولا كسور ولا نصّ عربي في
         * ملف JS، فتبديلها في شاشات الإعدادات يسري بلا لمس الملف.
         */
        $formConfig = [
            'formId' => 'salesDocumentForm',
            'currency' => [
                'decimals' => CurrencySettings::decimals(),
                'thousands' => CurrencySettings::thousandsSeparator(),
                'decimal' => CurrencySettings::decimalSeparator(),
                'symbol' => CurrencySettings::symbol(),
                /* موضع الرمز من «العملات» نفسِها لا من اتّجاه اللغة:
                   `positive_format = prefix` يضع الرمز أوّلاً حتى في
                   العربية، وكان JS يخالف ما يطبعه الخادم في السطر نفسه. */
                'symbolFirst' => str_starts_with(
                    ltrim(CurrencySettings::format(0)),
                    CurrencySettings::symbol(),
                ),
            ],
            'i18n' => [
                'confirm_save' => $doc['t']('js.confirm_save'),
                'confirm_save_text' => $doc['t']('js.confirm_save_text'),
                'confirm_yes' => $doc['t']('js.confirm_yes'),
                'confirm_cancel' => $doc['t']('js.confirm_cancel'),
                'confirm_discard' => $doc['t']('js.confirm_discard'),
                'confirm_discard_text' => $doc['t']('js.confirm_discard_text'),
                'saving' => $doc['t']('js.saving'),
                'need_client' => $doc['t']('js.need_client'),
                'need_date' => $doc['t']('js.need_date'),
                'need_items' => $doc['t']('js.need_items'),
                'need_product' => $doc['t']('js.need_product'),
                'need_quantity' => $doc['t']('js.need_quantity'),
                'incomplete' => $doc['t']('js.incomplete'),
                'remove_confirm' => $doc['t']('items.remove_confirm'),
                'last_row' => $doc['t']('items.last_row'),
                'base_unit' => $doc['t']('items.base_unit'),
            ],
            'taxRates' => $taxRates,
            'taxTypes' => $taxTypes,
            'products' => $productMap,
            'dateFieldId' => 'quote_date',
            'clientFieldId' => 'client_id',
            'rows' => $rows,
            'secondTax' => (bool) $config['second_tax'],

            /* مسارُ فحص الكوبون — أو `null` فلا يعمل الزرّ ولا يوجد أصلاً.
               لكلّ مستندٍ مسارُه بصلاحيته (`questions.check_coupon` و
               `sales_orders.check_coupon`)، والمنطقُ واحدٌ في
               `AppliesSalesCoupons::checkCoupon()`. */
            'couponCheckUrl' => ($invoiceSettings->enable_coupons ?? false)
                && \Illuminate\Support\Facades\Route::has($doc['routes']['check_coupon'])
                    ? route($doc['routes']['check_coupon'])
                    : null,
        ];
    @endphp

    <script>
        window.salesDocumentForm = @json($formConfig);
    </script>

    {{-- الإصدار بزمن التعديل يكسر كاش المتصفّح متى عُدِّل الملف — نظير ما
         تفعله شاشة الفاتورة بـ `invoice-calculator.js`. بدونه يبقى المتصفّح
         على نسخةٍ قديمة من الحاسبة، فتظهر الإجماليات ناقصةَ الأسطر
         المشروطة («الضريبة» و«الخصم») بينما الإجمالي النهائي يشملها. --}}
    {{-- عروضُ الحسم السارية — تقرؤها الحاسبة فتُساوي المعروضَ بالمحفوظ --}}
    @include('sales::partials.discount-offers')

    @php $salesDocumentFormVersion = @filemtime(public_path('assets/js/sales-document-form.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/sales-document-form.js') }}?v={{ $salesDocumentFormVersion }}"></script>

    {{-- الضريبة الشاملة على المستند — تكوينُ الـ JS وتحميل حاسبتها --}}
    @include('partials.comprehensive-tax-script')

@endpush
