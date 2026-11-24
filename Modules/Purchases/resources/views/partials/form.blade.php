{{--
    نموذج مستند دورة المشتريات — مشترك بين الإنشاء والتعديل في الأقسام كلّها.

    ما يأتي من الإعدادات هنا:
      • الرقم         ← «تسلسل أرقام المستندات»
      • نِسَب الضريبة  ← «إعدادات الضريبة»
      • العملة والكسور والفواصل ← «العملات»، وإلا إعدادات اللغة
      • الألوان والخط ← «مظهر النظام» عبر متغيّرات CSS

    @param string $action            رابط الإرسال
    @param bool $isEdit
    @param string $backUrl
    @param string $code
    @param string $documentLabel
    @param \Illuminate\Support\Collection $products
    @param \Illuminate\Support\Collection $suppliers
    @param \Illuminate\Support\Collection $taxes
    @param array $rows               بنود محفوظة/معادة
    @param array $config             مفاتيح العرض: title, supplier_mode, due_date,
                                     valid_days, prices, attachments
--}}
@php
    use Modules\Purchases\Services\PurchaseSettings;
    use App\Enums\ProductType;
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use Modules\Purchases\Support\ProductUnits;

    $config = array_merge([
        'title' => false,
        'supplier_mode' => 'none',   // none | single | multi
        'due_date' => true,
        'valid_days' => false,
        'prices' => true,
        'attachments' => false,
        'date_field' => 'order_date',
        // حقل الفرع — يظهر فقط للأقسام التي يحمل مستندها عمود branch_id
        'branch' => false,
        // اسم حقل نسبة الضريبة يختلف بين الأقسام: «طلبات الشراء» تستقبل
        // `tax_rate`، والقسمان الآخران يستقبلان `tax_1` مباشرة
        'tax_field' => 'tax_rate',
        // معرّف الضريبة المختارة — منه يعرف الخادم النسبة **والنوع**
        // (متضمَّنة في السعر أم مضافة فوقه). كان غائباً عن النموذج بينما
        // المتحكّمات تقرأه، فكانت الضريبة تسقط من الحساب كلّه.
        'tax_id_field' => 'tax_1_id',
        // أقسام التسعير الإضافية: الشحن والخصم العامّ والتسوية
        'settlement' => false,
        // ما تنفرد به فاتورة الشراء دون بقيّة مستندات الدورة
        'terms' => false,          // مهلة السداد بالأيام
        'second_tax' => false,     // ضريبة ثانية لكل بند
        'payment' => false,        // تبويب الدفع: الدفعة المقدَّمة والخزينة
        'receiving' => false,      // تبويب التسليم: حالة الاستلام وتاريخه
        // حقل المستودع — للمستندات التي تُدخل البضاعة أو تُخرجها فعلاً.
        // كان غائباً عن النموذج كلِّه بينما الخادم يقرأ `store_house_id`
        // ويفحص عليه صلاحيةَ «إنشاء فاتورة» — انظر PurchaseWarehouse.
        'warehouse' => false,
        // المستند المرجعي: مرتجع الشراء والإشعار المدين يصدران عن فاتورة،
        // فيحملان معرّفها إلى الخادم ويعرضان رقمها للقارئ
        'reference' => false,      // none | 'locked' مربوط لا يُغيَّر | 'optional'
        // تبويب الاسترداد — ما يُردّ من المورّد مقابل المرتجع، وخزينته
        'refund' => false,
        // مفردات تبويب حركة البضاعة.
        //
        // الفاتورة **تستلم** البضاعة، والمرتجع والإشعار المدين **يردّانها**:
        // يُنشئان إذن إخراج ويخصمان الأصناف. فالحقول واحدة والتسمية تنقلب،
        // ولذلك تأتي من هنا لا مكتوبةً في النموذج.
        'receiving_words' => 'invoice',   // invoice | goods
        // مفتاح إعداد «حالة يدوية لـ…» في خيارات المشتريات — مثل
        // manual_purchase_quotes أو manual_purchase_orders_status. عند
        // تفعيله يظهر حقل الحالة هنا، وإلا فلا حقل ولا فرق ملحوظ. الشاشة
        // التي تمرّره وحدها تعرف هل يقرأ متحكّمها `update()` هذا الحقل عند
        // التعديل أيضاً — فمن لا يدعمه في التعديل يُمرّره فقط من create
        'manual_status_setting' => null,
        // مفتاح إعداد «اعتبار … مسلَّماً/مستلَماً افتراضياً» — return_default_received
        // أو notice_default_received. عند تفعيله يكتب المتحكّم حالة الاستلام
        // دائماً بصرف النظر عمّا يختاره المستخدم في خانة الاستلام هنا؛ فتُعرَض
        // ملاحظة تحت الخانة تشرح ذلك بدل أن يظنّ المستخدم أن تركها فارغة يُبقي
        // المستند «معلَّقاً»
        'receiving_default_setting' => null,
        // نظيره لتبويبة الإيداع — default_paid_invoices، يفرض الدفع الكامل
        // تلقائياً بصرف النظر عن خانة «الدفعة الكاملة» هنا
        'payment_default_setting' => null,
        // مفتاح إعداد «حالة يدوية لفاتورة الشراء» — manual_invoice_status.
        //
        // لا يُخلط بـ`manual_status_setting` أعلاه: ذاك حالةُ اعتماد المستند
        // (تحت المراجعة/معتمد/مرفوض) في حقل `status`، وهذا حالةُ السداد
        // (مدفوعة/جزئياً/غير مدفوعة) في حقل `payment_status` — يشتقّها الخادم
        // من المبالغ ما لم يُفعَّل هذا الإعداد فتصير كما يضعها المُدخِل
        'invoice_status_setting' => null,
    ], $config ?? []);

    // «خيارات المشتريات» تقيّد ما تطلبه الشاشة ولا تفرض ما لم تطلبه:
    // شاشة لا تعرف التسوية أصلاً لا يُظهرها لها الإعداد، وشاشةٌ تعرفها
    // يُخفيها عنها إن أُطفئ. فالمنطقان يجتمعان بـ«و» لا يتغالبان.
    //
    // والحالتان تُفرَّقان قبل الدمج: شاشةٌ طلبت التسوية فأطفأها الإعداد
    // ليست كشاشةٍ لم تطلبها أصلاً. فالأولى يظهر فيها سطرٌ يقول أين المفتاح
    // — التبويبة الغائبة بلا كلمة تُقرأ «عُطبت الشاشة» لا «أُطفئ إعداد»،
    // وهذا ما حدث فعلاً: بُلِّغ عن «الخصم العام ممسوح» والسبب مفتاحٌ مطفأ.
    $settlementRequested = (bool) $config['settlement'];
    $config['settlement'] = $config['settlement'] && PurchaseSettings::enabled('enable_settlement');
    $settlementOffBySetting = $settlementRequested && ! $config['settlement'];
    $showTotalDiscount = PurchaseSettings::enabled('total_discounts');
    $showManualStatus = $config['manual_status_setting'] && PurchaseSettings::enabled($config['manual_status_setting']);
    $forcedReceived = $config['receiving_default_setting'] && PurchaseSettings::enabled($config['receiving_default_setting']);
    $forcedPaid = $config['payment_default_setting'] && PurchaseSettings::enabled($config['payment_default_setting']);
    $showInvoiceStatus = $config['invoice_status_setting'] && PurchaseSettings::enabled($config['invoice_status_setting']);

    // مفردات تبويب حركة البضاعة — تُحلّ مرّةً وتُستعمل في التبويب وحقوله
    $receivingWords = $config['receiving_words'] === 'goods'
        ? [
            'tab' => __('purchase_cycle.goods.tab'),
            'status' => __('purchase_cycle.goods.status'),
            'confirm' => __('purchase_cycle.goods.confirm'),
            'date' => __('purchase_cycle.goods.date'),
            'hint' => __('purchase_cycle.goods.hint'),
        ]
        : [
            'tab' => __('purchase_cycle.invoice.receiving_status'),
            'status' => __('purchase_cycle.invoice.receiving_status'),
            'confirm' => __('purchase_cycle.invoice.receive'),
            'date' => __('purchase_cycle.invoice.received_date'),
            'hint' => __('purchase_cycle.invoice.receiving_hint'),
        ];

    $dateField = $config['date_field'];
    $taxField = $config['tax_field'];
    $taxIdField = $config['tax_id_field'];
    $taxes = $taxes ?? collect();

    // وحدات المنتجات — كما في شاشة فواتير الشراء: يظهر الحقل للمنتج الذي
    // عُرِّفت له وحدات، ويبقى مخفيّاً لغيره
    $unitsMap = ProductUnits::map($products ?? collect());
@endphp

<form class="form" id="purchaseOrderForm" method="POST" enctype="multipart/form-data" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- مرجعُ المستند الأصل عند التحويل من مستندٍ آخر (`order_id` من طلب
         الشراء، `quotation_id` من طلب عرض السعر، ...) — كان يُمرَّر في رابط
         `create` كـ query string فقط، فتفتحه الشاشةُ وتملأ حقولَها منه، ثم
         يضيع عند الإرسال: لا حقل هنا يحمله والنموذج لا يُرسل عنوانَ الصفحة.
         فلا يُحدَّث المستندُ الأصل بعد التحويل، ولا تُحفَظ صلةُ الوليد بأصله
         حيثما يقرؤها الكنترولر (`$request->has('order_id')` وما شابه).
         السطر أدناه هو الإصلاح المشترك لكل شاشةٍ تمرّر `hidden_fields`. --}}
    @foreach ($hidden_fields ?? [] as $hiddenName => $hiddenValue)
        @if (filled($hiddenValue))
            <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
        @endif
    @endforeach

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('layouts.alerts.error')

    {{-- شريطُ السياق (العملة والرقم) كان هنا، فيقع بعد لوحة الإرشاد —
         وشاشةُ المبيعات تضعه قبلها. فصار قالباً مستقلّاً تستدعيه كلُّ
         شاشةٍ بعد ترويستها: `purchases::partials.doc-context`. --}}

    {{-- بيانات المستند --}}
    <div class="po-card">
        <div class="po-card__head">
            <h6 class="po-card__title">
                <i class="feather icon-file-text"></i>{{ $documentLabel }}
            </h6>
            <span class="po-hint">{{ __('purchase_orders.required_note') }}</span>
        </div>

        <div class="po-card__body">
            <div class="row g-3">
                @if ($config['title'])
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="title">
                                {{ __('purchase_cycle.fields.title') }} <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="title" id="title" required
                                value="{{ old('title', $defaults['title'] ?? '') }}"
                                placeholder="{{ __('purchase_orders.placeholders.title') }}">
                        </div>
                    </div>
                @endif

                {{-- الرقم يأتي من «تسلسل أرقام المستندات» ولا يُكتب هنا:
                     الحقل للقراءة فقط. المتحكّمات تستبدله على أي حال إن كان
                     محجوزاً أو فارغاً، فكتابته كانت تَعِد بما لا يُنفَّذ. --}}
                <div class="col-lg-3 col-md-6">
                    <div class="po-field">
                        <label for="code">{{ __('purchase_cycle.fields.code') }}</label>
                        <input type="text" class="form-control po-locked" name="code" id="code"
                            value="{{ old('code', $code) }}" readonly
                            title="{{ __('purchase_orders.placeholders.code') }}">
                        <span class="po-hint">
                            <i class="feather icon-lock me-1"></i>{{ __('purchase_orders.placeholders.code') }}
                        </span>
                    </div>
                </div>

                @if ($showManualStatus)
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="status">{{ __('purchase_cycle.fields.status') }}</label>
                            <select name="status" id="status" class="form-control">
                                @foreach (['Under Review', 'approval', 'disagree'] as $statusOption)
                                    <option value="{{ $statusOption }}"
                                        @selected(old('status', $defaults['status'] ?? 'Under Review') === $statusOption)>
                                        {{ __('purchase_cycle.statuses.'.$statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if ($config['reference'])
                    {{-- المستند المرجعي.

                         `locked`: المرتجع تابعٌ لفاتورةٍ بعينها — يُعرَض رقمها
                         للقارئ ويُرسَل معرّفها مخفيّاً، ولا يُبدَّل من الشاشة
                         فلا يُنسَب المرتجع إلى غير فاتورته.

                         `optional`: الإشعار المدين قد يصدر عن فاتورة وقد لا
                         يصدر، فتُعرَض قائمة الفواتير اختياراً. --}}
                    @if ($config['reference'] === 'optional')
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="reference_id">{{ $config['reference_label'] ?? __('purchase_cycle.debit_note.source_invoice') }}</label>
                                <select name="reference_id" id="reference_id" class="form-control po-select2">
                                    <option value="">{{ __('purchase_cycle.empty.unknown') }}</option>
                                    @foreach ($referenceInvoices ?? [] as $reference)
                                        <option value="{{ $reference->id }}"
                                            @selected(old('reference_id', $defaults['reference_id'] ?? null) == $reference->id)>
                                            {{ $reference->code }} — {{ $reference->supplier->trade_name ?? __('purchase_cycle.empty.unknown') }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="po-hint">{{ __('purchase_cycle.debit_note.source_hint') }}</span>
                            </div>
                        </div>
                    @else
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="reference_code">{{ $config['reference_label'] ?? __('purchase_cycle.return.source_invoice') }}</label>
                                {{-- المعروض `readonly` لا `disabled`: المعطَّل لا يُرسَل،
                                     والمرسَل هنا هو المعرّف المخفيّ على أي حال --}}
                                <input type="text" class="form-control" id="reference_code" readonly
                                    value="{{ $defaults['reference_code'] ?? '' }}">
                                <input type="hidden" name="reference_id"
                                    value="{{ old('reference_id', $defaults['reference_id'] ?? '') }}">
                                <span class="po-hint">{{ __('purchase_cycle.return.source_hint') }}</span>
                            </div>
                        </div>
                    @endif
                @endif

                @if ($config['supplier_mode'] === 'single')
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="supplier_id">
                                {{ __('purchase_cycle.fields.supplier') }} <span class="required">*</span>
                            </label>
                            <div class="po-select-add">
                                {{-- select2: القائمة الأصلية لا تُظهر المختار بوضوح
                                     حين يطول عدد الموردين، والبحث فيها متعذّر --}}
                                <select name="supplier_id" id="supplier_id" class="form-control po-select2" required>
                                    <option value="">{{ __('purchase_cycle.fields.supplier') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            @selected(old('supplier_id', $defaults['supplier_id'] ?? null) == $supplier->id)>
                                            {{ $supplier->name ?? $supplier->trade_name }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- المورّد غير موجود؟ رابطٌ يفتح شاشة إضافة مورد في
                                     تبويبٍ جديد بدل إجبار المستخدم على مغادرة
                                     النموذج الذي يملؤه وضياع ما أدخله --}}
                                @permission('purchases.suppliers.create')
                                    <a href="{{ route('SupplierManagement.create') }}" target="_blank" rel="noopener"
                                        class="btn btn-outline-primary po-select-add__btn"
                                        title="{{ __('purchase_cycle.actions.quick_add_supplier') }}">
                                        <i class="feather icon-plus"></i>
                                    </a>
                                @endpermission
                            </div>
                        </div>
                    </div>
                @elseif ($config['supplier_mode'] === 'multi')
                    <div class="col-lg-6 col-md-12">
                        <div class="po-field">
                            <label for="supplier_id">
                                {{ __('purchase_cycle.fields.suppliers') }} <span class="required">*</span>
                            </label>
                            <div class="po-select-add">
                                {{-- الاختيار متعدّد: طلب التسعير يُرسَل إلى أكثر من مورّد.
                                     select2 يعرض المختارين كوسوم ظاهرة بدل قائمة
                                     بمربّع تمرير لا يُعرف منها ما اختير --}}
                                <select name="supplier_id[]" id="supplier_id" class="form-control po-select2" multiple
                                    required>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            @selected(in_array($supplier->id, (array) old('supplier_id', $defaults['supplier_id'] ?? [])))>
                                            {{ $supplier->name ?? $supplier->trade_name }}
                                        </option>
                                    @endforeach
                                </select>

                                @permission('purchases.suppliers.create')
                                    <a href="{{ route('SupplierManagement.create') }}" target="_blank" rel="noopener"
                                        class="btn btn-outline-primary po-select-add__btn"
                                        title="{{ __('purchase_cycle.actions.quick_add_supplier') }}">
                                        <i class="feather icon-plus"></i>
                                    </a>
                                @endpermission
                            </div>
                        </div>
                    </div>
                @endif

                <div class="col-lg-3 col-md-6">
                    <div class="po-field">
                        <label for="{{ $dateField }}">
                            {{ __('purchase_cycle.fields.date') }} <span class="required">*</span>
                        </label>
                        <input type="date" class="form-control" name="{{ $dateField }}" id="{{ $dateField }}" required
                            value="{{ old($dateField, $defaults[$dateField] ?? date('Y-m-d')) }}">
                    </div>
                </div>

                @if ($config['due_date'])
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="due_date">{{ __('purchase_cycle.fields.due_date') }}</label>
                            <input type="date" class="form-control" name="due_date" id="due_date"
                                value="{{ old('due_date', $defaults['due_date'] ?? '') }}">
                        </div>
                    </div>
                @endif

                @if ($config['valid_days'])
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="valid_days">{{ __('purchase_cycle.fields.valid_days') }}</label>
                            <input type="number" min="0" step="1" class="form-control" name="valid_days"
                                id="valid_days" value="{{ old('valid_days', $defaults['valid_days'] ?? 30) }}">
                        </div>
                    </div>
                @endif

                @if ($config['terms'])
                    <div class="col-lg-3 col-md-6">
                        <div class="po-field">
                            <label for="terms">{{ __('purchase_cycle.invoice.terms') }}</label>
                            <input type="number" min="0" step="1" class="form-control" name="terms" id="terms"
                                value="{{ old('terms', $defaults['terms'] ?? 0) }}">
                            <span class="po-hint">{{ __('purchase_cycle.invoice.terms_hint') }}</span>
                        </div>
                    </div>
                @endif

                @if ($config['branch'])
                    @php
                        $branchDefault = old('branch_id', $defaults['branch_id'] ?? \App\Support\Scopes::resolveBranchId(auth()->user()));
                    @endphp
                    @if (\App\Support\Scopes::canSelectBranch(auth()->user()))
                        {{-- «تابع لـ» شجرةً يتنقّل فيها مستوىً مستوى — المُنتقي نفسُه في
                             التقارير والفهارس، على عقد نطاقه وفرع الشريط
                             (`HeaderPickers::treeNodes`). كانت قائمةً مسطّحةً بكل
                             العقد، وصاحبُ الفرع يرى فيها عقدةً واحدة. --}}
                        <div class="col-12">
                            <div class="po-field">
                                <div class="row">
                                    @include('reports.partials.affiliation-tree', [
                                        'treeNodes' => \Modules\Purchases\Support\HeaderPickers::treeNodes(),
                                        'name' => 'branch_id',
                                        'value' => $branchDefault,
                                        'showExact' => false,
                                        'label' => __('purchase_cycle.fields.branch'),
                                    ])
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="branch_id">{{ __('purchase_cycle.fields.branch') }}</label>
                                <input type="text" class="form-control" disabled
                                    value="{{ \App\Models\Branch::pathLabelFor($branchDefault) ?? __('branches.node_filter.unassigned') }}">
                            </div>
                        </div>
                    @endif
                @endif

            </div>
        </div>
    </div>

    {{-- البنود --}}
    <div class="po-card">
        <div class="po-card__head">
            <h6 class="po-card__title">
                <i class="feather icon-package"></i>{{ __('purchase_orders.items.heading') }}
            </h6>
            @if ($config['prices'])
                <span class="po-hint">
                    <i class="feather icon-dollar-sign me-1"></i>{{ CurrencySettings::name() }} ({{ CurrencySettings::symbol() }})
                </span>
            @endif
        </div>

        {{-- الضريبة على كل الأصناف — تظهر فقط حين تُعلَّم ضريبةٌ «شاملةً» في إعدادات الضريبة --}}
        @include('partials.comprehensive-tax-field')

        <div class="table-responsive">
            {{-- `po-table` يجلب هيئة جداول القسم — رأسٌ بأحرفٍ كبيرة وفواصلُ
                 خفيفة وقلبٌ إلى بطاقات على الجوّال — و`po-doc-table` تزيد
                 ما يخصّ جدول الإدخال وحده (حقولٌ داخل الخلايا، سطرُ تلميح،
                 زرُّ حذفٍ بلا إطار). `modern-table` باقٍ فلا يُكسَر ما قد
                 يشير إليه من خارج القسم. --}}
            <table class="table modern-table po-table po-doc-table mb-0" id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 46px;">{{ __('purchase_orders.items.row') }}</th>
                        <th style="min-width: 200px;">
                            {{ __('purchase_orders.items.item') }} <span class="required">*</span>
                        </th>
                        <th style="min-width: 150px;">{{ __('purchase_orders.items.description') }}</th>
                        <th style="width: 132px;">
                            {{ __('purchase_orders.items.quantity') }} <span class="required">*</span>
                        </th>
                        {{-- الوحدة عمودٌ قائمٌ بذاته لا سطرٌ تحت الصنف: كانت
                             القائمة تُحشر أسفل مُنتقي الصنف فيطول أوّلُ عمودٍ
                             ويتفاوت ارتفاع الصفوف، والكميةُ تُقرأ بلا وحدتها
                             في العمود المجاور. وهو ترتيب «إنشاء فاتورة
                             مبيعات» نفسه: الكمية ثمّ وحدتها ثمّ سعرها. --}}
                        <th style="width: 150px;">{{ __('purchase_orders.items.unit') }}</th>
                        @if ($config['prices'])
                            <th style="width: 130px;">{{ __('purchase_orders.items.unit_price') }}</th>
                            {{-- لا عمودَ لخصم البند — الخصمُ من تبويب «الخصم والتسوية» وحده --}}
                            <th style="width: 150px;">{{ __('purchase_orders.items.tax') }}</th>
                            @if ($config['second_tax'])
                                <th style="width: 150px;">{{ __('purchase_cycle.invoice.second_tax') }}</th>
                            @endif
                            <th class="num" style="width: 130px;">{{ __('purchase_orders.items.total') }}</th>
                        @endif
                        <th style="width: 46px;"></th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div class="po-card__foot">
            <button type="button" class="btn btn-outline-primary btn-sm" id="addRowBtn">
                <i class="feather icon-plus me-1"></i>{{ __('purchase_orders.items.add_row') }}
            </button>

            {{-- صندوق الإجماليات — المجموع والإجمالي النهائي ثابتان، وما
                 بينهما (الخصم والشحن والضريبة والإيداع والمسترد والمتبقي)
                 أسطرٌ مشروطة: `data-total-row` يمسكها السكربت فيُظهرها متى
                 صار للقيمة وجود ويُخفيها متى عادت صفراً. تبدأ مخفيّةً لأن
                 النموذج يبدأ بلا بنود، وأوّل `recalculate()` يفصل فيها. --}}
            @if ($config['prices'])
                <div class="po-totals">
                    <div class="po-totals__row">
                        <span>{{ __('purchase_orders.totals.subtotal') }}</span>
                        <span data-total="subtotal">{{ CurrencySettings::format(0) }}</span>
                    </div>
                    @if ($showTotalDiscount)
                        <div class="po-totals__row d-none" data-total-row="discount">
                            <span>{{ __('purchase_orders.totals.discount') }}</span>
                            <span data-total="discount">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                    @if ($config['settlement'])
                        <div class="po-totals__row d-none" data-total-row="shipping">
                            <span>{{ __('purchase_orders.totals.shipping') }}</span>
                            <span data-total="shipping">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                    <div class="po-totals__row d-none" data-total-row="tax">
                        <span>{{ __('purchase_orders.totals.tax') }}</span>
                        <span data-total="tax">{{ CurrencySettings::format(0) }}</span>
                    </div>
                    @if ($config['settlement'])
                        {{-- ضريبةُ الشحن سطرٌ مستقلّ لأنّها تُرحَّل إلى حسابٍ
                             مستقلّ («ضريبة شحن مشتريات») — فالمعروضُ يطابق القيد. --}}
                        <div class="po-totals__row d-none" data-total-row="shipping_tax">
                            <span>{{ __('purchase_orders.totals.shipping_tax') }}</span>
                            <span data-total="shipping_tax">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                    @if ($config['payment'])
                        <div class="po-totals__row d-none" data-total-row="deposit">
                            <span>{{ __('purchase_orders.totals.deposit') }}</span>
                            <span data-total="deposit">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                    @if ($config['refund'])
                        <div class="po-totals__row d-none" data-total-row="refund">
                            <span>{{ __('purchase_orders.totals.refund') }}</span>
                            <span data-total="refund">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                    <div class="po-totals__row po-totals__row--grand">
                        <span>{{ __('purchase_orders.totals.grand_total') }}</span>
                        <span data-total="grand">{{ CurrencySettings::format(0) }}</span>
                    </div>
                    @if ($config['payment'] || $config['refund'])
                        <div class="po-totals__row d-none" data-total-row="remaining">
                            <span>{{ __('purchase_orders.totals.remaining') }}</span>
                            <span data-total="remaining">{{ CurrencySettings::format(0) }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- تبويبة التسوية مطلوبةٌ في هذه الشاشة لكنّ الإعداد يُخفيها: يُقال ذلك
         صراحةً. وبغيره يبحث المستخدم عن «الخصم العام» فلا يجده ويظنّ الشاشة
         ناقصةً، ولا شيء في الصفحة يدلّه على المفتاح ولا على مكانه. --}}
    @if ($settlementOffBySetting)
        <div class="po-hint d-block mb-3" data-hint-keep>
            <i class="feather icon-info me-1"></i>{{ __('purchase_cycle.settlement.disabled_by_setting') }}
        </div>
    @endif

    {{-- التفاصيل الإضافية — تبويبات كاملة كما في الفاتورة --}}
    @if ($config['settlement'] || $config['payment'] || $config['receiving'] || $config['refund'])
        <div class="po-card">
            <div class="po-card__head p-0">
                <ul class="nav po-tabs w-100" role="tablist">
                    {{-- تبويبة الخصم --}}
                    @if ($config['settlement'])
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#section-discount" role="tab">
                                <i class="feather icon-percent me-1"></i>{{ __('purchase_cycle.settlement.tab_discount') }}
                            </a>
                        </li>
                    @endif

                    {{-- تبويبة الشحن (الوصيل) --}}
                    @if ($config['settlement'])
                        <li class="nav-item">
                            <a class="nav-link @if (!$config['settlement']) active @endif" data-bs-toggle="tab" href="#section-shipping" role="tab">
                                <i class="feather icon-truck me-1"></i>{{ __('purchase_cycle.settlement.tab_shipping') }}
                            </a>
                        </li>
                    @endif

                    {{-- تبويبة الإيداع --}}
                    @if ($config['payment'])
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-payment" role="tab">
                                <i class="feather icon-dollar-sign me-1"></i>{{ __('purchase_cycle.invoice.payments') }}
                            </a>
                        </li>
                    @endif

                    {{-- تبويبة الاسترداد --}}
                    @if ($config['refund'])
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-refund" role="tab">
                                <i class="feather icon-rotate-ccw me-1"></i>{{ __('purchase_cycle.return.refund_tab') }}
                            </a>
                        </li>
                    @endif

                    {{-- تبويبة التسليم --}}
                    @if ($config['receiving'])
                        <li class="nav-item">
                            <a class="nav-link @if (!$config['settlement'] && !$config['attachments'] && !$config['payment'] && !$config['refund']) active @endif" data-bs-toggle="tab" href="#section-receiving" role="tab">
                                <i class="feather icon-package me-1"></i>{{ $receivingWords['tab'] }}
                            </a>
                        </li>
                    @endif

                    {{-- تبويبة المرفقات --}}
                    @if ($config['attachments'])
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#section-documents" role="tab">
                                <i class="feather icon-paperclip me-1"></i>{{ __('purchase_cycle.settlement.tab_documents') }}
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="tab-content">
                {{-- تبويبة الخصم --}}
                @if ($config['settlement'])
                    <div class="tab-pane active" id="section-discount" role="tabpanel">
                        <div class="po-card__body">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="discount_amount">{{ __('purchase_cycle.settlement.discount_amount') }}</label>
                                        <input type="number" class="form-control po-settle" name="discount_amount"
                                            id="discount_amount" min="0" step="0.01"
                                            value="{{ old('discount_amount', $defaults['discount_amount'] ?? 0) }}">
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="discount_type">{{ __('purchase_cycle.settlement.discount_type') }}</label>
                                        <select name="discount_type" id="discount_type" class="form-control po-settle">
                                            <option value="amount" @selected(old('discount_type', $defaults['discount_type'] ?? 'amount') === 'amount')>
                                                {{ __('purchase_cycle.settlement.amount') }}
                                            </option>
                                            <option value="percentage" @selected(old('discount_type', $defaults['discount_type'] ?? '') === 'percentage')>
                                                {{ __('purchase_cycle.settlement.percentage') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- تبويبة الشحن (الوصيل) --}}
                @if ($config['settlement'])
                    <div class="tab-pane" id="section-shipping" role="tabpanel">
                        <div class="po-card__body">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="tax_id">{{ __('purchase_cycle.settlement.tax_type') }}</label>
                                        {{-- `data-rate` مثل منتقيات ضريبة البنود: بدونها لا
                                             تعرف حاسبةُ الفوتر نسبةَ ضريبة الشحن، فكانت
                                             تُحذَف من المعروض وتظهر في القيد وحده. --}}
                                        <select class="form-control po-settle" name="tax_id" id="tax_id">
                                            <option value="" data-rate="0">{{ __('purchase_cycle.settlement.choose_tax') }}</option>
                                            @foreach ($taxes as $tax)
                                                <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                                    @selected(old('tax_id', $defaults['tax_id'] ?? null) == $tax->id)>
                                                    {{ $tax->name }} ({{ $tax->tax }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="po-hint">
                                            <i class="feather icon-info me-1"></i>{{ __('purchase_cycle.settlement.choose_tax_hint') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="shipping_cost">{{ __('purchase_cycle.settlement.shipping_cost') }}</label>
                                        <input type="number" class="form-control po-settle" name="shipping_cost"
                                            id="shipping_cost" min="0" step="0.01"
                                            value="{{ old('shipping_cost', $defaults['shipping_cost'] ?? 0) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- تبويبة الإيداع --}}
                @if ($config['payment'])
                    <div class="tab-pane" id="section-payment" role="tabpanel">
                        <div class="po-card__body">
                            {{-- خانة «الدفعة الكاملة» + الخزينة: نفس ترتيب تبويبة
                                 التسليم (checkbox+hint في عمود، وحقل داعم واحد
                                 بجانبها) — الخزينة تبقى ظاهرة دوماً لأن المبلغ
                                 يحتاج وجهة حتى لو كان محسوباً تلقائياً --}}
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-4 col-md-6">
                                    <div class="po-field">
                                        <label>{{ __('purchase_cycle.invoice.advance_payment') }}</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_full_advance_payment"
                                                value="1" id="is_full_advance_payment"
                                                @checked(old('is_full_advance_payment', $defaults['is_full_advance_payment'] ?? false))>
                                            <label class="form-check-label" for="is_full_advance_payment">
                                                {{ __('purchase_cycle.invoice.full_advance_payment') }}
                                            </label>
                                        </div>
                                        <span class="po-hint">
                                            <i class="feather icon-info me-1"></i>{{ __('purchase_cycle.invoice.full_advance_payment_hint') }}
                                        </span>
                                        @if ($forcedPaid)
                                            <span class="po-hint text-warning d-block mt-1">
                                                <i class="feather icon-alert-triangle me-1"></i>{{ __('purchase_cycle.invoice.payment_forced_by_setting') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="advance_payment_treasury_id">{{ __('purchase_cycle.invoice.treasury') }}</label>
                                        <select name="advance_payment_treasury_id" id="advance_payment_treasury_id"
                                            class="form-control po-select2">
                                            <option value="">{{ __('purchase_cycle.invoice.select_treasury') }}</option>
                                            @foreach ($treasuries ?? [] as $treasury)
                                                <option value="{{ $treasury->id }}"
                                                    @selected(old('advance_payment_treasury_id', $defaults['treasury_id'] ?? null) == $treasury->id)>
                                                    {{ $treasury->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                @if ($showInvoiceStatus)
                                    {{-- حالة السداد يدوياً — لا تظهر إلا حين يُفعَّل
                                         إعداد «حالة يدوية لفاتورة الشراء». وبغيره
                                         يشتقّها الخادم من المبالغ المدفوعة --}}
                                    <div class="col-lg-3 col-md-6">
                                        <div class="po-field">
                                            <label for="payment_status">{{ __('purchase_cycle.invoice.payment_status') }}</label>
                                            @php
                                                // فواتير قديمة تحمل `partial` بدل `partially_paid`
                                                // (انظر $paymentVariants في شاشة العرض) — بلا التسوية
                                                // لا يطابقها أي خيار فتُقرأ «غير مدفوعة» خطأً
                                                $currentPaymentStatus = old('payment_status', $defaults['payment_status'] ?? 'unpaid');
                                                $currentPaymentStatus = $currentPaymentStatus === 'partial' ? 'partially_paid' : $currentPaymentStatus;
                                            @endphp
                                            <select name="payment_status" id="payment_status" class="form-control">
                                                @foreach (['unpaid', 'partially_paid', 'paid'] as $statusOption)
                                                    <option value="{{ $statusOption }}"
                                                        @selected($currentPaymentStatus === $statusOption)>
                                                        {{ __('purchase_cycle.invoice.'.($statusOption === 'partially_paid' ? 'partial' : $statusOption)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="po-hint text-warning d-block mt-1">
                                                <i class="feather icon-alert-triangle me-1"></i>{{ __('purchase_cycle.invoice.manual_status_hint') }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- بقيّة الحقول: تلزم فقط للدفع الجزئي، فتُخفى حين
                                 تُفعَّل «الدفعة الكاملة» (public/assets/js/purchase-orders-form.js) --}}
                            <div class="row g-3 mt-0" id="payment-detail-fields">
                                <div class="col-lg-6 col-md-12">
                                    <div class="po-field">
                                        <label for="advance_payment">{{ __('purchase_cycle.invoice.advance_payment') }} - {{ __('purchase_cycle.invoice.amount') }}</label>
                                        <input type="number" class="form-control po-settle" name="advance_payment"
                                            id="advance_payment" min="0" step="0.01"
                                            value="{{ old('advance_payment', $defaults['advance_payment'] ?? 0) }}">
                                    </div>
                                </div>

                                <div class="col-lg-6 col-md-12">
                                    <div class="po-field">
                                        <label for="advance_payment_type">{{ __('purchase_cycle.invoice.advance_payment_type') }}</label>
                                        <select name="advance_payment_type" id="advance_payment_type" class="form-control po-settle">
                                            <option value="amount" @selected(old('advance_payment_type', $defaults['advance_payment_type'] ?? 'amount') === 'amount')>
                                                {{ __('purchase_cycle.settlement.amount') }}
                                            </option>
                                            <option value="percentage" @selected(old('advance_payment_type', $defaults['advance_payment_type'] ?? '') === 'percentage')>
                                                {{ __('purchase_cycle.settlement.percentage') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-md-12">
                                    <div class="po-field">
                                        <label for="advance_payment_method">{{ __('purchase_cycle.invoice.payment_method') }}</label>
                                        <select name="advance_payment_method" id="advance_payment_method"
                                            class="form-control">
                                            <option value="">{{ __('purchase_cycle.invoice.select_payment_method') }}</option>
                                            @foreach ($paymentMethods ?? [] as $method)
                                                <option value="{{ $method->id ?? $method->name }}"
                                                    @selected(old('advance_payment_method', $defaults['payment_method'] ?? null) == ($method->id ?? $method->name))>
                                                    {{ $method->name ?? $method }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-md-12">
                                    <div class="po-field">
                                        <label for="advance_reference_number">{{ __('purchase_cycle.invoice.advance_reference_number') }}</label>
                                        <input type="text" class="form-control" name="advance_reference_number"
                                            id="advance_reference_number"
                                            value="{{ old('advance_reference_number', $defaults['reference_number'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- تبويبة الاسترداد --}}
                @if ($config['refund'])
                    <div class="tab-pane" id="section-refund" role="tabpanel">
                        <div class="po-card__body">
                            <div class="row g-3">
                                <div class="col-lg-4 col-md-6">
                                    <div class="po-field">
                                        <label for="refund_amount">{{ __('purchase_cycle.return.refund_amount') }}</label>
                                        <input type="number" class="form-control po-settle" name="refund_amount"
                                            id="refund_amount" min="0" step="0.01"
                                            value="{{ old('refund_amount', $defaults['refund_amount'] ?? '') }}">
                                        <span class="po-hint">{{ __('purchase_cycle.return.refund_amount_hint') }}</span>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6">
                                    <div class="po-field">
                                        <label for="refund_treasury_id">{{ __('purchase_cycle.return.refund_treasury') }}</label>
                                        <select name="refund_treasury_id" id="refund_treasury_id"
                                            class="form-control po-select2">
                                            <option value="">{{ __('purchase_cycle.invoice.select_treasury') }}</option>
                                            @foreach ($treasuries ?? [] as $treasury)
                                                <option value="{{ $treasury->id }}"
                                                    @selected(old('refund_treasury_id', $defaults['treasury_id'] ?? null) == $treasury->id)>
                                                    {{ $treasury->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6">
                                    <div class="po-field">
                                        <label for="payment_method">{{ __('purchase_cycle.invoice.payment_method') }}</label>
                                        <input type="text" class="form-control" name="payment_method"
                                            id="payment_method"
                                            value="{{ old('payment_method', $defaults['payment_method'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- تبويبة التسليم --}}
                @if ($config['receiving'])
                    <div class="tab-pane @if (!$config['settlement'] && !$config['attachments'] && !$config['payment'] && !$config['refund']) active @endif" id="section-receiving" role="tabpanel">
                        <div class="po-card__body">
                            <div class="row g-3 align-items-start">
                                <div class="col-lg-4 col-md-6">
                                    <div class="po-field">
                                        <label>{{ $receivingWords['status'] }}</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_received"
                                                value="1" id="is_received"
                                                @checked(old('is_received', $defaults['is_received'] ?? false))>
                                            <label class="form-check-label" for="is_received">
                                                {{ $receivingWords['confirm'] }}
                                            </label>
                                        </div>
                                        <span class="po-hint">
                                            <i class="feather icon-info me-1"></i>{{ $receivingWords['hint'] }}
                                        </span>
                                        @if ($forcedReceived)
                                            <span class="po-hint text-warning d-block mt-1">
                                                <i class="feather icon-alert-triangle me-1"></i>{{ __('purchase_cycle.receiving_forced_by_setting') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6">
                                    <div class="po-field">
                                        <label for="received_date">{{ $receivingWords['date'] }}</label>
                                        <input type="date" class="form-control" name="received_date"
                                            id="received_date"
                                            value="{{ old('received_date', $defaults['received_date'] ?? '') }}">
                                    </div>
                                </div>

                                {{-- المستودع: إلى أين تدخل البضاعة (أو من أين
                                     تخرج في المرتجع). كان الخادم يحسمه وحدَه
                                     — «المُرسَل، ثمّ مستودع الموظّف، ثمّ
                                     الرئيسيّ» — والشاشة لا ترسل شيئاً، فتدخل
                                     البضاعةُ الرئيسيَّ دائماً بلا أن يعرف
                                     المُدخِل أين ذهبت. --}}
                                @if ($config['warehouse'] && ! empty($warehouses) && count($warehouses))
                                    <div class="col-lg-4 col-md-6">
                                        <div class="po-field">
                                            <label for="store_house_id">{{ __('purchase_cycle.receiving.warehouse') }}</label>
                                            <select class="form-control po-select2" name="store_house_id"
                                                id="store_house_id">
                                                @foreach ($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}"
                                                        @selected(old('store_house_id', $defaults['store_house_id'] ?? null) == $warehouse->id)>
                                                        {{ $warehouse->name }}@if ($warehouse->major) — {{ __('purchase_cycle.receiving.warehouse_main') }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="po-hint">
                                                <i class="feather icon-info me-1"></i>{{ __('purchase_cycle.receiving.warehouse_hint') }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- تبويبة المرفقات (في النهاية) --}}
                @if ($config['attachments'])
                    <div class="tab-pane" id="section-documents" role="tabpanel">
                        <div class="po-card__body">
                            <div class="po-field">
                                <label for="attachments">{{ __('purchase_cycle.settlement.upload_new') }}</label>
                                <input type="file" name="attachments" id="attachments" class="d-none"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="po-upload" id="uploadArea" role="button" tabindex="0">
                                    <i class="feather icon-upload-cloud"></i>
                                    <div>
                                        <span class="text-primary">{{ __('purchase_orders.upload.click') }}</span>
                                        <span class="po-hint">{{ __('purchase_orders.upload.or') }}</span>
                                        <span class="text-primary">{{ __('purchase_orders.upload.browse') }}</span>
                                    </div>
                                    <small class="po-hint">{{ __('purchase_orders.upload.hint') }}</small>
                                    <div id="selectedFile" class="text-success small d-none"></div>
                                </div>

                                @if (! empty($defaults['attachments']))
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <a href="{{ asset('storage/'.$defaults['attachments']) }}" target="_blank"
                                            rel="noopener" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-paperclip me-1"></i>{{ __('purchase_orders.upload.current') }}
                                        </a>
                                        <span class="po-hint">{{ __('purchase_orders.upload.replace') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="po-card">
        <div class="po-card__body">
            <div class="row g-3">
                <div class="{{ $config['attachments'] && ! $config['settlement'] ? 'col-lg-7' : 'col-12' }}">
                    <div class="po-field">
                        <label for="tinyMCE">{{ __('purchase_cycle.fields.notes') }}</label>
                        {{-- `id="tinyMCE"` لا `id="notes"` — مطابقةً لحقل الملاحظات
                             في «إنشاء فاتورة مبيعات».

                             **وهو اليوم مربّعُ نصٍّ عاديّ في الشاشتين معاً.** تهيئةُ
                             المحرّر الوحيدة تنتقي هذا المعرّف
                             (`public/assets/js/scripts.js`)، غير أنّ ذلك الملفّ لا
                             يُحمَّل لا في `sales::master` ولا في `purchases::master`
                             — يحمّله `revolving_invoices/create` وحده. فشاشةُ
                             المبيعات تحمل المعرّف بلا محرّر، وشيفرتُها تحتاط لذلك
                             صراحةً: `if (editor) … else notesField.value = …`.

                             فالمعرّفُ هنا يوحّد البنية ويجعل الشاشتين تلتقطان المحرّر
                             معاً يومَ تُحمَّل تهيئتُه، لا يُنشئه اليوم.

                             و`name="notes"` هو ما يُرسَل ولم يتغيّر، ولا شيء في
                             `purchase-orders-form.js` يقرأ الحقلَ بمعرّفه. --}}
                        <textarea class="form-control" name="notes" id="tinyMCE" rows="5"
                            placeholder="{{ __('purchase_orders.placeholders.notes') }}">{{ old('notes', $defaults['notes'] ?? '') }}</textarea>
                    </div>
                </div>

                @if ($config['attachments'] && ! $config['settlement'])
                    <div class="col-lg-5">
                        <div class="po-field">
                            <label for="attachments">{{ __('purchase_cycle.fields.attachments') }}</label>
                            <input type="file" name="attachments" id="attachments" class="d-none"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="po-upload" id="uploadArea" role="button" tabindex="0">
                                <i class="feather icon-upload-cloud"></i>
                                <div>
                                    <span class="text-primary">{{ __('purchase_orders.upload.click') }}</span>
                                    <span class="po-hint">{{ __('purchase_orders.upload.or') }}</span>
                                    <span class="text-primary">{{ __('purchase_orders.upload.browse') }}</span>
                                </div>
                                <small class="po-hint">{{ __('purchase_orders.upload.hint') }}</small>
                                <div id="selectedFile" class="text-success small d-none"></div>
                            </div>

                            @if (! empty($defaults['attachments']))
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <a href="{{ asset('storage/'.$defaults['attachments']) }}" target="_blank"
                                        rel="noopener" class="btn btn-outline-secondary btn-sm">
                                        <i class="feather icon-paperclip me-1"></i>{{ __('purchase_orders.upload.current') }}
                                    </a>
                                    <span class="po-hint">{{ __('purchase_orders.upload.replace') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="po-card__foot">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ LanguageSettings::isRtl() ? 'right' : 'left' }} me-1"></i>{{ __('purchase_cycle.actions.back') }}
            </a>

            <div class="po-toolbar">
                <button type="button" class="btn btn-outline-danger" id="cancelBtn">
                    <i class="feather icon-x me-1"></i>{{ __('purchase_orders.actions.cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="feather icon-save me-1"></i>{{ $isEdit ? __('purchase_orders.actions.update') : __('purchase_orders.actions.save') }}
                </button>
            </div>
        </div>
    </div>
</form>

{{-- قالب الصف — خارج النموذج فلا تُرسَل حقوله فارغةً مع الحفظ --}}
<template id="rowTemplate">
    <tr class="po-row">
        <td class="num" data-label="{{ __('purchase_orders.items.row') }}"><span class="po-row__index">1</span></td>
        <td data-label="{{ __('purchase_orders.items.item') }}">
            <div class="po-select-add">
                <select class="form-control po-input po-product" data-field="product_id" required>
                    <option value="">{{ __('purchase_orders.placeholders.select_item') }}</option>
                    {{-- الصنف بلا رصيد — الخدمي والرقمي — يُشترى ويُقيَّد في
                         الدفتر ولا يدخل المستودع. تُعلَّم أنواعه هنا فيعرف
                         القارئ قبل الحفظ لماذا لن يظهر البند في الإذن --}}
                    @foreach ($products as $product)
                        @php
                            $productType = ProductType::from_value($product->type ?? null);
                            $productLabel = $productType->tracksStock()
                                ? $product->name
                                : $product->name.' — '.$productType->label();
                        @endphp
                        <option value="{{ $product->id }}"
                            data-price="{{ $product->purchase_price ?? 0 }}"
                            data-stockless="{{ $productType->tracksStock() ? 0 : 1 }}">{{ $productLabel }}</option>
                    @endforeach
                </select>

                {{-- الصنف غير موجود؟ رابطٌ يفتح شاشة إضافة صنف في تبويبٍ جديد
                     — على غرار «إضافة مورد» أعلاه — بدل إجبار المستخدم على
                     مغادرة النموذج الذي يملؤه --}}
                @permission('stock.products.edit')
                    <a href="{{ route('products.create') }}" target="_blank" rel="noopener"
                        class="btn btn-outline-primary po-select-add__btn"
                        title="{{ __('purchase_orders.actions.quick_add_product') }}">
                        <i class="feather icon-plus"></i>
                    </a>
                @endpermission
            </div>

            {{-- معامل التحويل إلى الوحدة الأساسية يرافق البند إلى الخادم،
                 وبه تُحسب الكمية بالوحدة الأساسية. مخفيّان، فيبقيان مع
                 مُنتقي الصنف وإن انتقلت قائمةُ الوحدة إلى عمودها. --}}
            <input type="hidden" class="po-input po-unit-factor" data-field="conversion_factor" value="1">
            <input type="hidden" class="po-input po-unit-name" data-field="unit_name" value="">
        </td>
        <td data-label="{{ __('purchase_orders.items.description') }}">
            <input type="text" class="form-control po-input" data-field="description">
        </td>
        <td data-label="{{ __('purchase_orders.items.quantity') }}">
            <input type="number" class="form-control po-input po-calc" data-field="quantity" min="1" step="1"
                value="1" required placeholder="{{ __('purchase_orders.placeholders.quantity') }}">
        </td>

        {{-- الوحدة: تظهر للمنتج الذي عُرِّفت له وحدات وحده، وتُملأ من
             خريطة الوحدات عند اختيار الصنف. وصنفٌ بلا وحدات لا يُسأل عن
             اختيارٍ لا خيار فيه، فتحلّ شرطةٌ محلَّ القائمة في خليّته. --}}
        <td data-label="{{ __('purchase_orders.items.unit') }}">
            <select class="form-control po-input po-unit" data-field="product_unit_id"
                style="display: none;" aria-label="{{ __('purchase_orders.items.unit') }}"></select>

            <span class="po-unit-none">&mdash;</span>

            {{-- «= ٢٤٠٠ حبة» — ما تُساويه الكمية بالوحدة الأساسية. لا
                 «معامل التحويل: ١٢٠»، فالمعامل مكتوبٌ أصلاً في اسم الخيار
                 («كرتون × ١٢٠») فيكون سطراً يكرّر ما فوقه. --}}
            <small class="po-base-qty po-hint-line" style="display: none;"></small>
        </td>
        @if ($config['prices'])
            <td data-label="{{ __('purchase_orders.items.unit_price') }}">
                <input type="number" class="form-control po-input po-calc" data-field="unit_price" min="0"
                    step="0.01" value="0" placeholder="{{ __('purchase_orders.placeholders.unit_price') }}">
            </td>
            {{-- خصمُ البند أُزيل من الشاشة بطلب المستخدم: الخصمُ من تبويب «الخصم
                 والتسوية» وحده، كما في المبيعات. الحقولُ الثلاثة مخفيّةٌ لا
                 محذوفة — `purchase-orders-form.js` يسمّيها بـ`data-field`
                 والمتحكّمات تقرؤها بالاسم.

                 البندُ الجديد بصفر، والبندُ المحفوظ أو المنسوخ يحمل خصمَه
                 **نسبةً** من سطره (`OrderLine::discountFields()`): فلا يتغيّر
                 الإجماليُّ بحفظ التعديل، ويتناسب الخصمُ مع كمية المرتجع
                 والإشعار المدين. و`discount_amount` نسخةٌ يكتبها السكربت
                 مطابقةً للمحسوب. --}}
            <input type="hidden" class="po-input po-calc" data-field="discount" value="0">
            <input type="hidden" class="po-input" data-field="discount_type" value="amount">
            <input type="hidden" class="po-input po-discount-mirror" data-field="discount_amount" value="0">
            <td data-label="{{ __('purchase_orders.items.tax') }}">
                {{-- القيمة معرّف الضريبة، والنسبة والنوع في سمات الخيار:
                     الشاشة تحسب المتضمَّنة استخراجاً لا إضافةً --}}
                <select class="form-control po-input po-calc" data-field="{{ $taxIdField }}">
                    <option value="" data-rate="0" data-included="0">
                        {{ __('purchase_orders.items.no_tax') }}
                    </option>
                    @foreach ($taxes as $tax)
                        <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                            data-included="{{ $tax->type === 'included' ? 1 : 0 }}">
                            {{ $tax->name }} ({{ $tax->tax + 0 }}%
                            @if ($tax->type === 'included')
                                — {{ __('purchase_orders.items.tax_included') }}
                            @endif
                            )
                        </option>
                    @endforeach
                </select>
                {{-- النسبة تُرسَل مرافقةً للمعرّف: العمود `tax_1` يخزّنها --}}
                <input type="hidden" class="po-input po-tax-rate" data-field="{{ $taxField }}" value="0">
            </td>

            @if ($config['second_tax'])
                <td data-label="{{ __('purchase_cycle.invoice.second_tax') }}">
                    <select class="form-control po-input po-calc po-tax2" data-field="tax_2_id">
                        <option value="" data-rate="0" data-included="0">
                            {{ __('purchase_orders.items.no_tax') }}
                        </option>
                        @foreach ($taxes as $tax)
                            <option value="{{ $tax->id }}" data-rate="{{ $tax->tax }}"
                                data-included="{{ $tax->type === 'included' ? 1 : 0 }}">
                                {{ $tax->name }} ({{ $tax->tax + 0 }}%
                                @if ($tax->type === 'included')
                                    — {{ __('purchase_orders.items.tax_included') }}
                                @endif
                                )
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" class="po-input po-tax2-rate" data-field="tax_2" value="0">
                </td>
            @endif

            <td class="num" data-label="{{ __('purchase_orders.items.total') }}">
                <span class="po-row__total"></span>
            </td>
        @endif


        <td class="num">
            {{-- زرٌّ بلا إطار: عمودُه ٤٦ بكسل، و`btn-outline-danger` يملؤه
                 حدّاً أحمر في كل صفّ. الإطار يظهر عند المرور وحده — كما في
                 «إنشاء فاتورة مبيعات». --}}
            <button type="button" class="btn po-row__remove"
                aria-label="{{ __('purchase_orders.items.remove_row') }}"
                title="{{ __('purchase_orders.items.remove_row') }}">
                <i class="feather icon-trash-2"></i>
            </button>
        </td>
    </tr>
</template>

@push('css')
@endpush

@push('scripts')
    @php
        // `@json` تقصّ المصفوفة الحرفية متعدّدة الأسطر عند أول فاصلة
        $poFormConfig = [
            'currency' => [
                'symbol' => CurrencySettings::symbol(),
                'decimals' => CurrencySettings::decimals(),
                'thousands' => CurrencySettings::thousandsSeparator(),
                'decimal' => CurrencySettings::decimalSeparator(),
                'suffix' => LanguageSettings::isRtl(),
            ],
            'rows' => array_values($rows ?? []),
            'i18n' => [
                'lastRow' => __('purchase_orders.confirm.last_row'),
                // بندٌ محوَّل من مستندٍ سابق — لا يُعدَّل ولا يُحذف
                'lockedRow' => __('purchase_orders.items.locked_row'),
                'removeTitle' => __('purchase_orders.confirm.remove_row_title'),
                'removeText' => __('purchase_orders.confirm.remove_row_text'),
                'saveTitle' => __('purchase_orders.confirm.save_title'),
                /* النصّ يُسمّي المستند الذي تحفظه هذه الشاشة: الملفّ مشتركٌ
                   بين أربع عشرة شاشة، فكان يسأل «هل أنت متأكد من حفظ طلب
                   الشراء؟» في الفاتورة والمرتجع والإشعار وعرض السعر جميعاً */
                'saveText' => $isEdit
                    ? __('purchase_orders.confirm.update_text', ['document' => $documentLabel])
                    : __('purchase_orders.confirm.save_text', ['document' => $documentLabel]),
                'cancelTitle' => __('purchase_orders.confirm.cancel_title'),
                'cancelText' => __('purchase_orders.confirm.cancel_text'),
                'yes' => __('purchase_orders.confirm.yes'),
                'no' => __('purchase_orders.confirm.no'),
                'ok' => __('purchase_orders.confirm.ok'),
                'saving' => __('purchase_orders.confirm.saving'),
                'pleaseWait' => __('purchase_orders.confirm.please_wait'),
                'itemsRequired' => __('purchase_orders.validation.items_required'),
                'titleRequired' => __('purchase_orders.validation.title_required'),
                'orderDateRequired' => __('purchase_orders.validation.order_date_required'),
                'unit' => __('purchase_orders.items.unit'),
                // يُستعمَل في «= ٢٤٠٠ <اسم الوحدة>» حين لا يُعرَف اسم
                // الوحدة الأساسية للصنف
                'baseUnit' => __('purchase_orders.items.base_unit'),
                'itemRequired' => __('purchase_orders.validation.item_required', ['row' => '__ROW__']),
                'quantityRequired' => __('purchase_orders.validation.quantity_required', ['row' => '__ROW__']),
            ],
            'dateFieldId' => $dateField,
            'taxField' => $taxField,
            'taxIdField' => $taxIdField,
            'requireTitle' => (bool) $config['title'],
            'settlement' => (bool) $config['settlement'],
            // تبويبتا الإيداع والاسترداد لا توجدان في كل الشاشات، فالسكربت
            // يحتاج أن يعرف أيّهما موجودة ليحسب المدفوع والمتبقي
            'payment' => (bool) $config['payment'],
            'refund' => (bool) $config['refund'],
            // معرّف المنتج ← وحداته؛ المنتجات بلا وحدات غائبة عن الخريطة
            'units' => $unitsMap,
        ];
    @endphp

    <script>
        // إعدادات العملة والنصوص تُحقن من الخادم، فتتبع الشاشةُ الإعدادات
        // واللغة النشطة بلا أي نصّ مكتوب في ملفّ JavaScript.
        window.purchaseOrderForm = @json($poFormConfig);
    </script>

    @if (($config['supplier_mode'] ?? null) === 'single' && ($config['reference'] ?? null) === 'optional')
        {{-- «الفاتورة المرجعية» تتبع المورّدَ المختار — لا فواتير المنشأة
             كلَّها في قائمةٍ واحدة ثابتة. تُشترَط الحالتان معاً
             (`supplier_mode=single` و`reference=optional`) فلا يعمل هذا
             إلا في شاشة الإشعار الدائن حين تُفتَح بلا فاتورةٍ أصلٍ محدَّدة
             — المرتجعُ مرجعُه مقفلٌ دائماً فلا يصله هذا السكربت أصلاً. --}}
        <script>
            (function () {
                var $supplier = window.jQuery && window.jQuery('#supplier_id');
                var $reference = window.jQuery && window.jQuery('#reference_id');

                if (!$supplier || !$reference || !$supplier.length || !$reference.length) {
                    return;
                }

                var urlTemplate = @json(route('CityNotices.invoicesForSupplier', ['supplierId' => '__ID__']));
                var emptyLabel = @json(__('purchase_cycle.empty.unknown'));

                function refreshSelect2($el) {
                    if (typeof $el.trigger === 'function') {
                        $el.trigger('change');
                    }
                }

                function loadInvoices(supplierId, keepSelected) {
                    var current = keepSelected ? $reference.val() : null;
                    $reference.empty().append(window.jQuery('<option>').val('').text(emptyLabel));

                    if (!supplierId) {
                        refreshSelect2($reference);

                        return;
                    }

                    window.jQuery.getJSON(urlTemplate.replace('__ID__', supplierId))
                        .done(function (data) {
                            (data.invoices || []).forEach(function (invoice) {
                                $reference.append(window.jQuery('<option>').val(invoice.id).text(invoice.label));
                            });

                            if (current) {
                                $reference.val(current);
                            }

                            refreshSelect2($reference);
                        })
                        .fail(function () {
                            refreshSelect2($reference);
                        });
                }

                $supplier.on('change', function () {
                    loadInvoices(window.jQuery(this).val(), false);
                });

                // إعادة تحميلٍ أوّليّة إن وصل مورّدٌ مختاراً مسبقاً — بعد
                // فشل تحقّقٍ يعيد الشاشة بقيم `old()`
                if ($supplier.val()) {
                    loadInvoices($supplier.val(), true);
                }
            })();
        </script>
    @endif

    @php $purchaseOrdersFormVersion = @filemtime(public_path('assets/js/purchase-orders-form.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/purchase-orders-form.js') }}?v={{ $purchaseOrdersFormVersion }}"></script>

    {{-- الضريبة الشاملة على المستند — تكوينُ الـ JS وتحميل حاسبتها --}}
    @include('partials.comprehensive-tax-script')
@endpush
