@extends('client::master')

@section('title')
    تعديل عميل
@stop

@section('content')
    @php
        use App\Helpers\ClientSettingsHelper;

        /* ما تفرضه «إعدادات وحدات العملاء» ← تبويب العملاء على هذه الشاشة —
           انظر `ClientRequest::rules()` حيث القاعدة الفعلية نفسها. النجمةُ
           هنا والقاعدةُ هناك يقرآن المصدر ذاته، فلا يفترقان.
           (نفسُ نمط `supplier_management/create.blade.php`.) */
        $requirePhone = ClientSettingsHelper::clientRequirePhone();
        $requireMobile = ClientSettingsHelper::clientRequireMobile();
        $requireTaxNumber = ClientSettingsHelper::clientRequireTaxNumber();
        $requireEmail = ClientSettingsHelper::clientRequireEmail();
        $uniquePhone = ClientSettingsHelper::clientUniquePhone();
        $typeScope = ClientSettingsHelper::clientTypeScope();
        $requireTradeName = ClientSettingsHelper::clientRequireTradeName();
        $requireCommercialRegistration = ClientSettingsHelper::clientRequireCommercialRegistration();
        $requireCategory = ClientSettingsHelper::clientRequireCategory();
        // «وضع العميل» — نفس مفتاح الإلزام الذي يقرؤه نموذج الإضافة و`ClientRequest`
        $requireRegistrationStage = ClientSettingsHelper::clientRequireRegistrationStage();

        /* نفسُ حساب «إضافة عميل» — انظر التعليق هناك. */
        $imageFieldEnabled = ClientSettingsHelper::isImageFieldEnabled();
        $locationFieldEnabled = (bool) ($GeneralClientSettings->firstWhere('key', 'location')->is_active ?? false);
        $locationRequired = $locationFieldEnabled && ClientSettingsHelper::isLocationRequired();
        /* حقلٌ غير إلزامي يتأخّر في «البيانات الأساسية» فتتجاور الإلزامية —
           انظر `client::partials.client-form-style` */
        $optionalField = fn (bool $required) => $required ? '' : ' cl-field--opt';
    @endphp

    @include('client::partials.settings-theme')
    @include('components.hint-tips')
    @include('client::partials.client-form-style')

    <div class="cl-scope">
        @include('client::components.page-head', [
            'title' => 'تعديل عميل',
            'icon' => 'edit-3',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'تعديل' => null,
            ],
        ])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً، كما في «إضافة مورد» --}}
        @include('client::components.guide', [
            'key' => 'clients.edit',
            'open' => false,
            'title' => __('client_settings.guide.form.title'),
            'lead' => __('client_settings.guide.form.lead'),
            'blocks' => [
                __('client_settings.guide.form.required.title') => [
                    __('client_settings.guide.form.required.a'),
                    __('client_settings.guide.form.required.b'),
                    __('client_settings.guide.form.required.c'),
                ],
                __('client_settings.guide.form.balance.title') => [
                    __('client_settings.guide.form.balance.a'),
                    __('client_settings.guide.form.balance.b'),
                ],
            ],
            'facts' => [
                __('client_settings.guide.form.facts.account'),
                __('client_settings.guide.form.facts.fields'),
            ],
        ])

        <form id="clientForm" action="{{ route('clients.update', $client->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- حقلين مخفيين لتخزين الإحداثيات -->
            <input type="hidden" name="latitude" id="latitude" value="{{ $location->latitude ?? '' }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $location->longitude ?? '' }}">

            {{-- ══════════ تخطيط النموذج ══════════
                 نفسُ تخطيط «إضافة عميل» — انظر التعليق هناك: ثلاثُ بطاقاتٍ بعرض
                 الصفحة، والحقولُ الإلزامية متجاورةٌ أولاً في «البيانات الأساسية»
                 (`cl-field--opt` على غير الإلزامي، والتنسيق في
                 `client::partials.client-form-style`).
                 كلُّ `id` و`name` و`.form-group` تقرؤه سكربتات الشاشة بقي كما هو. --}}
            <div class="card cl-form-bar">
                <div class="card-body">
                    <span class="cl-form-bar__note">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</span>
                    <div class="cl-form-bar__actions">
                        <a href="{{ route('clients.index') }}" class="btn btn-outline-danger">
                            <i class="fa fa-ban"></i> الغاء
                        </a>
                        <button type="submit" class="btn btn-outline-primary" id="submitBtn">
                            <i class="fa fa-save"></i> حفظ التعديلات
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="fa fa-id-card"></i> البيانات الأساسية</h4>
                    <span class="cl-card-hint">الحقول الإلزامية أولاً</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 cl-form-grid">
                        <!-- الاسم التجاري -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireTradeName) }}">
                            <div class="form-group">
                                <label for="trade_name">الاسم التجاري @if($requireTradeName)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="trade_name" id="trade_name"
                                    class="form-control"
                                    value="{{ old('trade_name', $client->trade_name) }}"
                                    @if($requireTradeName) required @endif>
                            </div>
                        </div>

                        @php
                            $defaultTreeBranchId = old(
                                'client_tree_branch_id',
                                $client->resolveTreeNode()?->id,
                            );
                        @endphp
                        <!-- «العميل تابع لـ»: اختيارٌ واحد يغني عن حقلَي المجموعة والفرع -->
                        {{-- عرضُ ثمانية أعمدة: المسار الكامل طويل، وتلميحُ القائمة يبقى سطراً واحداً فلا يطول
                             هذا العمود عن جيرانه ويترك فراغاً تحتهم؛ و«إضافة جديد» رابطٌ في التسمية لا زرٌّ تحت الحقل --}}
                        <div class="col-lg-8 col-md-12">
                            <div class="form-group">
                                <label for="client_tree_branch_id">
                                    العميل تابع لـ <span class="text-danger">*</span>
                                    @permission('branches.create')
                                        @if (Route::has('branches.create'))
                                            <a href="{{ route('branches.create') }}" target="_blank" rel="noopener"
                                                class="small" title="إضافة فرع أو منطقة أو مجموعة أو منفذ">
                                                <i class="feather icon-plus"></i> إضافة جديد
                                            </a>
                                        @endif
                                    @endpermission
                                </label>
                                @include('client::partials.branch-tree-picker', [
                                    'clientBranchTreeNodes' => $clientBranchTreeNodes,
                                    'selectedBranchId' => $defaultTreeBranchId,
                                ])
                            </div>
                        </div>

                        <!-- الهاتف والجوال -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requirePhone) }}">
                            <div class="form-group">
                                <label for="phone">الهاتف @if($requirePhone)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="phone" id="phone" class="form-control"
                                    value="{{ old('phone', $client->phone) }}"
                                    @if($requirePhone) required @endif>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireMobile) }}">
                            <div class="form-group">
                                <label for="mobile">جوال @if($requireMobile)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="mobile" id="mobile" class="form-control"
                                    value="{{ old('mobile', $client->mobile) }}"
                                    @if($requireMobile) required @endif>
                            </div>
                        </div>

                        <!-- البريد الإلكتروني -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireEmail) }}">
                            <div class="form-group">
                                <label for="email">البريد الإلكتروني @if($requireEmail)<span class="text-danger">*</span>@endif</label>
                                <input type="email" id="email" class="form-control"
                                    name="email" value="{{ old('email', $client->email) }}">
                            </div>
                        </div>

                        <!-- التصنيف -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireCategory) }}">
                            <div class="form-group">
                                <label for="category_id">التصنيف @if($requireCategory)<span class="text-danger">*</span>@endif</label>
                                <div class="cl-select-add">
                                    <select class="form-control select2" name="category_id" id="category_id"
                                        @if($requireCategory) required @endif>
                                        <option value="">اختر التصنيف</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ old('category_id', $client->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if (canAny(['settings_customer_categories', 'clients_edit_client_settings']))
                                        @if (Route::has('categoriesClient.create'))
                                            <a href="{{ route('categoriesClient.create') }}" target="_blank" rel="noopener"
                                                class="btn btn-outline-primary cl-select-add__btn" title="إضافة تصنيف جديد">
                                                <i class="feather icon-plus"></i>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{--
                            وضع العميل — وسمٌ إضافي لا يؤثّر على حالة العميل
                            التشغيلية إطلاقاً (انظر ClientController::store()،
                            القسم 3 من docs/CRM_Customer_Status_Gap_Analysis_Plan.md)،
                            فلا معنى لحصرها بصلاحية اعتماد الحالة كما كانت — أيّ
                            مستخدمٍ يصل شاشة التعديل أصلاً يقدر يغيّرها.

                            القائمة قابلة للتعديل من: إعدادات وحدات العملاء ←
                            العملاء ← «أوضاع العملاء» (رابط سريع بجانب السيلكت).
                        --}}
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireRegistrationStage) }}">
                            <div class="form-group">
                                <label for="registration_stage_id">
                                    وضع العميل @if($requireRegistrationStage)<span class="text-danger">*</span>@endif
                                    @if (hasPermission('settings_customer_followup_status') || hasPermission('clients_edit_client_settings'))
                                        <a href="{{ route('clients.registrationStages') }}" target="_blank"
                                            class="small" title="إدارة قائمة أوضاع العملاء">
                                            <i class="feather icon-settings"></i> إدارة القائمة
                                        </a>
                                    @endif
                                </label>
                                <select class="form-control select2" name="registration_stage_id" id="registration_stage_id"
                                    @if($requireRegistrationStage) required @endif>
                                    <option value="">@if($requireRegistrationStage)اختر وضع العميل@else— بدون —@endif</option>
                                    @foreach (\App\Models\Statuses::section('registration_stage')->ordered()->get() as $stage)
                                        <option value="{{ $stage->id }}"
                                            {{ (int) old('registration_stage_id', $client->registration_stage_id) === $stage->id ? 'selected' : '' }}>
                                            {{ $stage->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- الرقم الضريبي والسجل التجاري -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireTaxNumber) }}">
                            <div class="form-group">
                                <label for="tax_number">الرقم الضريبي @if($requireTaxNumber)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="tax_number" id="tax_number"
                                    class="form-control"
                                    value="{{ old('tax_number', $client->tax_number) }}">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireCommercialRegistration) }}">
                            <div class="form-group">
                                <label for="commercial_registration">سجل تجاري @if($requireCommercialRegistration)<span class="text-danger">*</span>@else (اختياري) @endif</label>
                                <input type="text" name="commercial_registration"
                                    id="commercial_registration" class="form-control"
                                    value="{{ old('commercial_registration', $client->commercial_registration) }}"
                                    @if($requireCommercialRegistration) required @endif>
                            </div>
                        </div>

                        {{-- حُذف «الحد الائتماني» و«المدة الائتمانية» من نموذج
                             العميل: صارا يُضبطان من زرّ «الحد الائتماني» في
                             شاشة عرض العميل — موضعٌ واحدٌ لسياسةٍ واحدة بدل
                             حقلين في نموذجٍ طويل. --}}

                        <!-- موقع العميل على الخريطة -->
                        @if ($locationFieldEnabled)
                            <div class="col-lg-4 col-md-6 cl-field--block">
                                <label class="d-block mb-1">
                                    موقع العميل على الخريطة
                                    @if ($locationRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <button type="button" class="btn btn-outline-primary mb-2"
                                    id="showMapBtn">
                                    <i class="feather icon-map"></i> إظهار الخريطة
                                </button>
                                <button type="button" class="btn btn-outline-info mb-2 mr-1"
                                    id="detectLocationBtn" style="display: none;">
                                    <i class="feather icon-navigation"></i> تحديد موقعي
                                </button>

                                <div id="map-container" style="display: none;">
                                    <div id="map" style="height: 400px; width: 100%;"></div>
                                </div>
                            </div>
                        @endif

                        <!-- صورة العميل مع ضغط تلقائي — تظهر فقط إذا فُعِّل حقل «صورة» في إعدادات وحدات العملاء -->
                        @if ($imageFieldEnabled)
                        <div class="col-lg-4 col-md-6 cl-field--block">
                            <div class="form-group">
                                <label for="attachments" class="form-label">صورة العميل</label>

                                <!-- عرض الصورة الحالية -->
                                @if($client->attachments)
                                    <div class="current-image mb-2 p-2 border rounded bg-light d-flex align-items-center gap-2">
                                        <img src="{{ asset('assets/uploads/' . $client->attachments) }}"
                                             alt="صورة العميل"
                                             class="img-thumbnail"
                                             style="max-width: 120px; max-height: 120px;"
                                             id="currentImage">
                                        <span class="text-muted small"><i class="fa fa-image"></i> الصورة الحالية</span>
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-2 py-2">
                                        <i class="fa fa-exclamation-triangle"></i> لا توجد صورة للعميل - يُنصح بإضافة صورة
                                    </div>
                                @endif

                                <input id="attachments" type="file" name="attachments"
                                    class="form-control d-none @error('attachments') is-invalid @enderror"
                                    accept="image/*" capture="environment">

                                <!-- منطقة الرفع -->
                                <div class="upload-area border rounded p-3 text-center position-relative bg-light"
                                    onclick="document.getElementById('attachments').click()" style="cursor: pointer; border: 2px dashed #007bff !important;">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                        <i class="fas fa-camera fa-2x text-primary"></i>
                                        <p class="mb-0 text-primary fw-bold">اضغط هنا لفتح الكاميرا أو اختيار صورة جديدة</p>
                                        <small class="text-muted">يتم ضغط الصور تلقائياً — يُسمح فقط بالصور (jpeg, png, jpg, gif)</small>
                                    </div>
                                </div>

                                <!-- إعدادات الضغط -->
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6">
                                        <label for="imageQuality" class="form-label small">جودة ضغط الصورة</label>
                                        <select id="imageQuality" class="form-select form-select-sm">
                                            <option value="0.8">عالية جداً (80%)</option>
                                            <option value="0.6" selected>عالية (60%)</option>
                                            <option value="0.4">متوسطة (40%)</option>
                                            <option value="0.2">منخفضة (20%)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="maxImageSize" class="form-label small">الحد الأقصى لحجم الصورة</label>
                                        <select id="maxImageSize" class="form-select form-select-sm">
                                            <option value="1920">1920px (عالية الدقة)</option>
                                            <option value="1280" selected>1280px (دقة متوسطة)</option>
                                            <option value="800">800px (دقة منخفضة)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- شريط التقدم -->
                                <div id="compressionProgress" class="mt-3 d-none">
                                    <label class="form-label small">جاري ضغط الصورة:</label>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                             role="progressbar" style="width: 0%" id="progressBar"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block" id="progressText">يتم ضغط الصورة...</small>
                                </div>

                                @error('attachments')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror

                                <!-- معاينة الصورة الجديدة -->
                                <div id="imagePreview" class="mt-3" style="display: none;">
                                    <div class="file-item border rounded p-3 bg-light">
                                        <label class="d-block text-success mb-2"><i class="fa fa-check-circle"></i> الصورة الجديدة (ستستبدل الحالية):</label>
                                        <div class="row align-items-center">
                                            <div class="col-3">
                                                <img id="previewImg" src="" alt="معاينة الصورة" class="img-thumbnail" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                            </div>
                                            <div class="col-6">
                                                <h6 class="mb-1 text-truncate" id="fileName">اسم الملف</h6>
                                                <small class="text-muted">صورة</small>
                                                <div class="small mt-1">
                                                    <span class="text-muted">الحجم الأصلي: </span>
                                                    <span id="originalFileSize">0 KB</span>
                                                    <span class="mx-1">→</span>
                                                    <span class="text-success" id="compressedFileSize">0 KB</span>
                                                    <span class="badge bg-primary ms-2" id="savingsPercent">0% توفير</span>
                                                </div>
                                            </div>
                                            <div class="col-3 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeImage()">
                                                    <i class="fas fa-trash"></i> إلغاء
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ملخص الضغط -->
                                <div id="compressionSummary" class="mt-3 d-none">
                                    <div class="alert alert-success border-0 py-2">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted d-block">الحجم الأصلي</small>
                                                <span class="fw-bold" id="summaryOriginal">0 KB</span>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">بعد الضغط</small>
                                                <span class="fw-bold text-success" id="summaryCompressed">0 KB</span>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">نسبة التوفير</small>
                                                <span class="fw-bold text-primary" id="summarySavings">0%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="fa fa-map-marker-alt"></i> بيانات إضافية</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        {{-- رقم الكود — للعرض فقط. تُولَّد قيمتُه تسلسلياً عند إنشاء العميل
                             (`next_document_number('customer')` من «الترقيم المتسلسل»)، فلا
                             حقل `name` هنا كي لا يصل به تعديلٌ يدوي يكسر التسلسل أو يصطدم برقمٍ
                             آخر — الكنترولر يستثنيه من التحديث أيضاً كحماية إضافية. --}}
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="code">رقم الكود</label>
                                <input type="text" id="code" class="form-control" readonly
                                    value="{{ $client->code }}">
                            </div>
                        </div>

                        <!-- الاسم الأول والأخير -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="first_name">الاسم الأول</label>
                                <input type="text" name="first_name" id="first_name"
                                    class="form-control"
                                    value="{{ old('first_name', $client->first_name) }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="last_name">الاسم الأخير</label>
                                <input type="text" name="last_name" id="last_name"
                                    class="form-control"
                                    value="{{ old('last_name', $client->last_name) }}">
                            </div>
                        </div>

                        <!-- طريقة الفاتورة -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="printing_method">طريقة الفاتورة</label>
                                <select class="form-control" id="printing_method"
                                    name="printing_method">
                                    <option value="1"
                                        {{ old('printing_method', $client->printing_method) == 1 ? 'selected' : '' }}>
                                        الطباعة</option>
                                    <option value="2"
                                        {{ old('printing_method', $client->printing_method) == 2 ? 'selected' : '' }}>
                                        ارسل عبر البريد</option>
                                </select>
                            </div>
                        </div>

                        <!-- العملة: بلا حقلٍ في النموذج، تبقى على قيمة العميل الحالية -->
                        <input type="hidden" name="currency" value="{{ old('currency', $client->currency ?? 'SAR') }}">

                        {{-- «الحد الأقصى للكمية» كان مدفوناً داخل شرط
                             `credit_limit` — حقلٌ لا صلةَ له بالائتمان يختفي
                             بإطفاء حقلٍ آخر. صار مستقلاً بذاته. --}}
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="item_quantity_limit">الحد الأقصى للكمية (لكل بند)</label>
                                <input type="number" name="item_quantity_limit"
                                    id="item_quantity_limit" class="form-control"
                                    value="{{ old('item_quantity_limit', $client->item_quantity_limit ?? 0) }}">
                                <small class="text-muted">أدخل 0 لعدم وجود حد</small>
                            </div>
                        </div>

                        <!-- البلد -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="country">البلد</label>
                                <select name="country" id="country" class="form-control">
                                    <option value="SA"
                                        {{ old('country', $client->country) == 'SA' ? 'selected' : '' }}>
                                        المملكة العربية السعودية (SA)</option>
                                </select>
                            </div>
                        </div>

                        <!-- عنوان الشارع -->
                        <div class="col-xl-6 col-lg-8 col-md-12">
                            <div class="form-group">
                                <label for="street1">عنوان الشارع</label>
                                <input type="text" name="street1" id="street1"
                                    class="form-control"
                                    value="{{ old('street1', $client->street1) }}">
                            </div>
                        </div>
                        {{-- رقم المبنى من العنوان الوطني: تطلبه هيئة الزكاة والضريبة
                             والجمارك في فاتورة العميل ذي الرقم الضريبي --}}
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="building_number">رقم المبنى</label>
                                <input type="text" name="building_number" id="building_number" inputmode="numeric"
                                    maxlength="4" class="form-control @error('building_number') is-invalid @enderror"
                                    style="direction:ltr;text-align:left" placeholder="0000"
                                    value="{{ old('building_number', $client->building_number) }}">
                                @error('building_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <!-- الحي -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="neighborhood_name_manual">الحي</label>
                                <input type="text" name="neighborhood_name_manual" id="neighborhood_name_manual"
                                    class="form-control" value="{{ old('neighborhood_name_manual', $client->neighborhood->name ?? '') }}" placeholder="كتابة اسم الحي يدوياً">
                            </div>
                        </div>

                        <!-- المدينة والمنطقة والرمز البريدي -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="city">المدينة</label>
                                <input type="text" name="city" id="city"
                                    class="form-control" value="{{ old('city', $client->city) }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="region">المنطقة</label>
                                <input type="text" name="region" id="region"
                                    class="form-control"
                                    value="{{ old('region', $client->region) }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="postal_code">الرمز البريدي</label>
                                <input type="text" name="postal_code" id="postal_code"
                                    class="form-control"
                                    value="{{ old('postal_code', $client->postal_code) }}">
                            </div>
                        </div>

                        {{-- الموظفين المسؤولين — انظر نفس التعليق في create.blade.php --}}
                        @if (hasPermission('clients_assign_employees'))
                            <div class="col-xl-6 col-lg-8 col-md-12">
                                <div class="form-group">
                                    <label for="employee_client_id" class="form-label">الموظفين
                                        المسؤولين</label>
                                    <div class="cl-select-add">
                                        <select id="employee_select" class="form-control select2">
                                            <option value="">اختر الموظف</option>
                                            {{-- القائمة من «أقسام الموظفين المسموح تعيينهم للعملاء» —
                                                 `ClientController::employeesForClientAssignment()` — مجمَّعةً بالقسم --}}
                                            @foreach ($employees->groupBy(fn ($candidate) => $candidate->department->name ?? 'بلا قسم') as $departmentName => $departmentEmployees)
                                                <optgroup label="{{ $departmentName }}">
                                                    @foreach ($departmentEmployees as $employee)
                                                        <option value="{{ $employee->id }}"
                                                            data-name="{{ $employee->full_name }}">
                                                            {{ $employee->full_name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        @permission('employees.create')
                                            @if (Route::has('employee.create'))
                                                <a href="{{ route('employee.create') }}" target="_blank" rel="noopener"
                                                    class="btn btn-outline-primary cl-select-add__btn" title="إضافة موظف جديد">
                                                    <i class="feather icon-plus"></i>
                                                </a>
                                            @endif
                                        @endpermission
                                    </div>

                                    @php
                                        $assignableDepartmentIds = \App\Helpers\ClientSettingsHelper::clientEmployeeDepartmentIds();
                                        $assignableDepartmentNames = $assignableDepartmentIds
                                            ? \App\Models\Department::whereIn('id', $assignableDepartmentIds)->pluck('name')->implode('، ')
                                            : null;
                                    @endphp
                                    <small class="text-muted d-block mt-1">
                                        <i class="feather icon-info"></i>
                                        @if ($assignableDepartmentNames)
                                            موظفو الأقسام: {{ $assignableDepartmentNames }} — من «إعدادات وحدات العملاء ← الموظفون».
                                        @else
                                            كل الأقسام — لم تُحدَّد أقسامٌ في «إعدادات وحدات العملاء ← الموظفون».
                                        @endif
                                        @permission('clients_module_settings_view')
                                            <a href="{{ route('clients.module_settings', ['tab' => 'employees']) }}" target="_blank" rel="noopener">تعديل الأقسام</a>
                                        @endpermission
                                    </small>
                                    {{-- الحقل الحقيقي الذي يتم إرساله --}}
                                    <div id="selected_employees">
                                        @foreach ($client->employees as $assigned)
                                            <input type="hidden" name="employee_client_id[]"
                                                value="{{ $assigned->id }}">
                                        @endforeach
                                    </div>

                                    {{-- عرض الموظفين المسؤولين الحاليين --}}
                                    <ul id="employee_list" class="mt-2 list-group">
                                        @foreach ($client->employees as $assigned)
                                            <li data-employee-id="{{ $assigned->id }}"
                                                class="list-group-item d-flex justify-content-between align-items-center">
                                                {{ $assigned->full_name }}
                                                <button type="button"
                                                    class="btn btn-sm btn-danger remove-employee"
                                                    data-id="{{ $assigned->id }}">حذف</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <!-- الملاحظات -->
                        <div class="col-12">
                            <label for="notes">الملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" style="resize: vertical;">{{ old('notes', $client->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الاتصال -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="fa fa-address-book"></i> قائمة الاتصال</h4>
                    <button type="button" class="btn btn-outline-success btn-sm"
                        onclick="addContactFields()">
                        <i class="feather icon-plus"></i> إضافة جهة اتصال
                    </button>
                </div>
                <div class="card-body cl-contacts-body">
                    {{-- الحاوية بلا مسافاتٍ حين لا جهات اتصال، كي يطابقها `:empty` فيُطوى جسم البطاقة --}}
                    <div class="contact-fields-container" id="contactContainer">@foreach ($client->contacts ?? [] as $index => $contact)<div class="contact-fields-group mb-3 p-3 border rounded">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label>الاسم الأول</label>
                                    <input type="text" class="form-control"
                                        name="contacts[{{ $index }}][first_name]"
                                        value="{{ $contact->first_name }}"
                                        placeholder="الاسم الأول">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label>الاسم الأخير</label>
                                    <input type="text" class="form-control"
                                        name="contacts[{{ $index }}][last_name]"
                                        value="{{ $contact->last_name }}"
                                        placeholder="الاسم الأخير">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label>البريد الإلكتروني</label>
                                    <input type="email" class="form-control"
                                        name="contacts[{{ $index }}][email]"
                                        value="{{ $contact->email }}"
                                        placeholder="البريد الإلكتروني">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label>الهاتف</label>
                                    <input type="tel" class="form-control"
                                        name="contacts[{{ $index }}][phone]"
                                        value="{{ $contact->phone }}"
                                        placeholder="الهاتف">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label>جوال</label>
                                    <input type="tel" class="form-control"
                                        name="contacts[{{ $index }}][mobile]"
                                        value="{{ $contact->mobile }}"
                                        placeholder="جوال">
                                </div>
                                <div class="col-md-6 mb-2 text-right">
                                    <button type="button"
                                        class="btn btn-danger mt-2"
                                        onclick="removeContactFields(this)">
                                        <i class="fa fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>@endforeach</div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{-- حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه 10 — نظيرُ App\Rules\SaudiPhone --}}
    <script src="{{ asset('assets/js/saudi-phone.js') }}?v=1"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initGoogleMaps"
        async defer></script>

    <script>
        let map;
        let marker;
        let searchBox;
        let contactCounter = {{ $client->contacts ? $client->contacts->count() : 0 }};
        let selectedEmployeeIds = @json($client->employees ? $client->employees->pluck('id')->toArray() : []);
        let isGoogleMapsLoaded = false;
        let isMapInitialized = false;

        // نفس مصدر التحقّق في الخادم (ClientRequest، ClientSettingsHelper)
        const imageFieldEnabled = @json($imageFieldEnabled);
        const locationRequired = @json($locationRequired);
        const requireTradeName = @json($requireTradeName);

        function initGoogleMaps() {
            isGoogleMapsLoaded = true;
            console.log('Google Maps تم تحميله بنجاح');
        }

        function addContactFields() {
            contactCounter++;
            const contactContainer = document.getElementById('contactContainer');
            const newContactGroup = document.createElement('div');
            newContactGroup.className = 'contact-fields-group mb-3 p-3 border rounded';
            newContactGroup.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label>الاسم الأول</label>
                        <input type="text" class="form-control" name="contacts[${contactCounter}][first_name]" placeholder="الاسم الأول">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label>الاسم الأخير</label>
                        <input type="text" class="form-control" name="contacts[${contactCounter}][last_name]" placeholder="الاسم الأخير">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label>البريد الإلكتروني</label>
                        <input type="email" class="form-control" name="contacts[${contactCounter}][email]" placeholder="البريد الإلكتروني">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label>الهاتف</label>
                        <input type="tel" class="form-control" name="contacts[${contactCounter}][phone]" placeholder="الهاتف">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label>جوال</label>
                        <input type="tel" class="form-control" name="contacts[${contactCounter}][mobile]" placeholder="جوال">
                    </div>
                    <div class="col-md-6 mb-2 text-right">
                        <button type="button" class="btn btn-danger mt-2" onclick="removeContactFields(this)">
                            <i class="fa fa-trash"></i> حذف
                        </button>
                    </div>
                </div>
                <hr>
            `;
            contactContainer.appendChild(newContactGroup);
        }

        function removeContactFields(button) {
            const contactGroup = button.closest('.contact-fields-group');
            contactGroup.remove();
        }

        /* ══════ الموظفون المسؤولون ══════

           `updateHiddenInputs` هنا لا داخل `initEmployeeManagement()`: كانت
           معرَّفةً في نطاق تلك الدالّة و`removeEmployee` العامّة تناديها،
           فكلُّ ضغطةِ «حذف» تنتهي بـ ReferenceError ولا يُحذف شيء. */
        function updateHiddenInputs() {
            const selectedEmployees = document.getElementById('selected_employees');
            if (!selectedEmployees) return;

            selectedEmployees.innerHTML = '';
            selectedEmployeeIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'employee_client_id[]';
                input.value = id;
                selectedEmployees.appendChild(input);
            });
        }

        function removeEmployee(button, employeeId) {
            const li = button.closest('li');
            if (li) li.remove();
            selectedEmployeeIds = selectedEmployeeIds.filter(id => parseInt(id, 10) !== parseInt(employeeId, 10));
            updateHiddenInputs();
        }

        function initMap(lat = {{ $location->latitude ?? 24.7136 }}, lng = {{ $location->longitude ?? 46.6753 }}) {
            if (!isGoogleMapsLoaded) {
                console.log('انتظار تحميل Google Maps...');
                setTimeout(() => initMap(lat, lng), 100);
                return;
            }

            const mapContainer = document.getElementById('map-container');
            const mapElement = document.getElementById('map');

            if (!mapElement) {
                console.error('عنصر الخريطة غير موجود');
                return;
            }

            mapContainer.style.display = 'block';
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Create map immediately without timeout
            map = new google.maps.Map(mapElement, {
                center: {
                    lat,
                    lng
                },
                zoom: 15,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            });

            marker = new google.maps.Marker({
                position: {
                    lat,
                    lng
                },
                map: map,
                draggable: true,
                title: 'موقع العميل',
                animation: google.maps.Animation.DROP
            });

            marker.addListener('dragend', function() {
                const newLat = marker.getPosition().lat();
                const newLng = marker.getPosition().lng();
                document.getElementById('latitude').value = newLat;
                document.getElementById('longitude').value = newLng;
                fetchAddressFromCoordinates(newLat, newLng);
            });

            map.addListener('click', function(event) {
                const newLat = event.latLng.lat();
                const newLng = event.latLng.lng();
                updateMapPosition(newLat, newLng);
            });

            isMapInitialized = true;
            console.log('تم تهيئة الخريطة بنجاح');
        }

        function updateMapPosition(lat, lng) {
            if (!map || !marker) return;
            map.setCenter({
                lat,
                lng
            });
            marker.setPosition({
                lat,
                lng
            });
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            fetchAddressFromCoordinates(lat, lng);
        }

        function fetchAddressFromCoordinates(lat, lng) {
            if (!isGoogleMapsLoaded) return;

            const geocoder = new google.maps.Geocoder();
            const latLng = {
                lat,
                lng
            };

            geocoder.geocode({
                location: latLng
            }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const addressComponents = results[0].address_components;
                    const region = getAddressComponent(addressComponents, 'administrative_area_level_1');
                    const city = getAddressComponent(addressComponents, 'locality') || getAddressComponent(
                        addressComponents, 'administrative_area_level_2');
                    const postalCode = getAddressComponent(addressComponents, 'postal_code');
                    const street1 = getAddressComponent(addressComponents, 'route');
                    const neighborhoodName = getAddressComponent(addressComponents, 'neighborhood');

                    if (region) document.getElementById('region').value = region;
                    if (city) document.getElementById('city').value = city;
                    if (postalCode) document.getElementById('postal_code').value = postalCode;
                    if (street1) document.getElementById('street1').value = street1;
                    // تعبئة حقل الحي اليدوي تلقائياً
                    if (neighborhoodName && document.getElementById('neighborhood_name_manual')) {
                        document.getElementById('neighborhood_name_manual').value = neighborhoodName;
                    }
                }
            });
        }

        function getAddressComponent(components, type) {
            const component = components.find(c => c.types.includes(type));
            return component ? component.long_name : '';
        }

        function requestLocationPermission() {
            if (!isGoogleMapsLoaded) {
                Swal.fire({
                    icon: 'info',
                    title: 'جاري التحميل...',
                    text: 'جاري تحميل الخريطة، يرجى الانتظار قليلاً',
                    timer: 1000, // Reduced timer
                    showConfirmButton: false
                });
                setTimeout(requestLocationPermission, 500); // Reduced timeout
                return;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (!isMapInitialized) {
                            initMap(position.coords.latitude, position.coords.longitude);
                        } else {
                            updateMapPosition(position.coords.latitude, position.coords.longitude);
                        }
                    },
                    (error) => {
                        console.warn('خطأ في تحديد الموقع:', error);
                        Swal.fire({
                            icon: 'info',
                            title: 'تحديد الموقع',
                            text: 'سيتم عرض الخريطة بموقع افتراضي (الرياض). يمكنك تحريك العلامة أو النقر على الخريطة لتحديد الموقع.',
                            confirmButtonText: 'موافق'
                        });

                        if (!isMapInitialized) {
                            initMap();
                        }
                    }, {
                        timeout: 10000, // Add timeout option
                        enableHighAccuracy: true // Enable high accuracy
                    }
                );
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'تحديد الموقع',
                    text: 'سيتم عرض الخريطة بموقع افتراضي. يمكنك النقر على الخريطة لتحديد الموقع.',
                    confirmButtonText: 'موافق'
                });

                if (!isMapInitialized) {
                    initMap();
                }
            }
        }

        // دالة تحديد الموقع الحالي للمستخدم
        function detectMyLocation() {
            if (!isGoogleMapsLoaded) {
                Swal.fire({
                    icon: 'info',
                    title: 'جاري التحميل...',
                    text: 'جاري تحميل الخريطة، يرجى الانتظار قليلاً',
                    timer: 1000, // Reduced timer
                    showConfirmButton: false
                });

                setTimeout(detectMyLocation, 500); // Reduced timeout
                return;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        // إذا لم تكن الخريطة مهيئة بعد، نهيئها أولاً
                        if (!isMapInitialized) {
                            initMap(position.coords.latitude, position.coords.longitude);
                        } else {
                            // إذا كانت الخريطة مهيئة، نحدث موقع العلامة
                            updateMapPosition(position.coords.latitude, position.coords.longitude);
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'تم تحديد الموقع',
                            text: 'تم تحديد موقعك الحالي على الخريطة',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    },
                    (error) => {
                        console.warn('خطأ في تحديد الموقع:', error);
                        let errorMessage = 'تعذر تحديد موقعك الحالي. ';

                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += 'تم رفض طلب تحديد الموقع.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += 'معلومات الموقع غير متاحة.';
                                break;
                            case error.TIMEOUT:
                                errorMessage += 'انتهت مدة طلب تحديد الموقع.';
                                break;
                            default:
                                errorMessage += 'حدث خطأ غير معروف.';
                                break;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في تحديد الموقع',
                            text: errorMessage,
                            confirmButtonText: 'موافق'
                        });
                    }, {
                        timeout: 10000, // Add timeout option
                        enableHighAccuracy: true // Enable high accuracy
                    }
                );
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'غير مدعوم',
                    text: 'متصفحك لا يدعم خاصية تحديد الموقع',
                    confirmButtonText: 'موافق'
                });
            }
        }

        function initEmployeeManagement() {
            const employeeSelect = document.getElementById('employee_select');
            const employeeList = document.getElementById('employee_list');

            if (!employeeSelect || !employeeList) return;

            function addEmployee(rawId, name) {
                const employeeId = parseInt(rawId, 10);
                if (!employeeId || selectedEmployeeIds.some(id => parseInt(id, 10) === employeeId)) {
                    return;
                }

                selectedEmployeeIds.push(employeeId);

                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.setAttribute('data-employee-id', employeeId);
                li.textContent = name || '';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.textContent = 'حذف';
                removeBtn.className = 'btn btn-sm btn-danger remove-employee';
                removeBtn.setAttribute('data-id', employeeId);

                li.appendChild(removeBtn);
                employeeList.appendChild(li);
                updateHiddenInputs();
            }

            function onSelectChange() {
                const option = employeeSelect.options[employeeSelect.selectedIndex];
                if (!option) return;

                addEmployee(option.value, option.dataset.name || option.textContent.trim());

                // إعادةُ القائمة إلى «اختر الموظف» — مع select2 لا يكفي
                // `value = ''` وحدَه، فالواجهةُ المرسومة لا تعرف بالتغيير
                employeeSelect.value = '';
                if (window.jQuery && jQuery(employeeSelect).data('select2')) {
                    jQuery(employeeSelect).val('').trigger('change.select2');
                }
            }

            /* select2 يُطلق `change` عبر jQuery، و`jQuery.trigger` لا توقظ
               مستمعاً مضافاً بـ`addEventListener` — فالسطر الذي كان هنا لم
               يكن يعمل إطلاقاً في هذه الشاشة (القائمة `select2` هنا، وبلا
               `select2` في شاشة الإضافة — ولهذا كانت الإضافة تعمل والتعديل
               لا). نستمع بالطريقتين، ونمنع التكرار بحارس `addEmployee`. */
            if (window.jQuery) {
                jQuery(employeeSelect).on('change select2:select', onSelectChange);
            } else {
                employeeSelect.addEventListener('change', onSelectChange);
            }

            /* حذفٌ بالتفويض: أزرارُ الموظفين المعيَّنين مسبقاً يرسمها Blade
               بـ`class="remove-employee"` بلا `onclick`، فلم يكن لها مستمعٌ
               من الأصل — لا يُحذَف موظفٌ قديم أبداً. */
            employeeList.addEventListener('click', function(event) {
                const button = event.target.closest('.remove-employee');
                if (!button) return;

                event.preventDefault();
                removeEmployee(button, button.dataset.id);
            });

            updateHiddenInputs();
        }

        /* ══════ أخطاء الخادم تُعلَّم على حقولها ══════

           كان ردُّ التحقّق (٤٢٢) يُعرَض نصّاً مجموعاً في نافذة واحدة: يقرأ
           المستخدم «هذا البريد مستعمَل» ولا يعرف أيَّ حقلٍ يصلح، وتبقى
           الحقول كلُّها بيضاء. وأخطاءُ الخادم هي وحدها التي لا يستطيع
           المتصفّح توقّعها — كتفرّد البريد — فهي أَولى ما يُشار إليه.

           `js-server-error` تميّز الرسائل المحقونة عن رسائل Blade الثابتة،
           فتُمسَح وحدها في المحاولة التالية. */

        function clearFieldErrors(form) {
            form.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
                el.style.boxShadow = '';
            });
            form.querySelectorAll('.js-server-error').forEach(el => el.remove());
        }

        function fieldByName(form, name) {
            // contacts.0.email ← يصل بهذا الشكل ويُكتب في الصفحة contacts[0][email]
            const bracket = name.replace(/\.(\w+)/g, '[$1]');

            return form.querySelector('[name="' + name + '"]')
                || form.querySelector('[name="' + bracket + '"]')
                || document.getElementById(name);
        }

        function applyServerErrors(form, errors) {
            clearFieldErrors(form);

            const list = [];
            let firstField = null;

            Object.entries(errors || {}).forEach(([name, messages]) => {
                const message = Array.isArray(messages) ? messages[0] : messages;
                list.push(message);

                const field = fieldByName(form, name);
                if (!field) return;

                field.classList.add('is-invalid');

                const holder = field.closest('.form-group') || field.parentElement;
                const note = document.createElement('div');
                note.className = 'invalid-feedback d-block js-server-error';
                note.textContent = message;
                holder.appendChild(note);

                if (!firstField) firstField = field;
            });

            if (firstField) {
                firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstField.focus();
                firstField.style.boxShadow = '0 0 10px rgba(255, 0, 0, 0.5)';
                setTimeout(() => { firstField.style.boxShadow = ''; }, 2000);
            }

            return { anchored: !!firstField, list: list };
        }

        function reportSaveFailure(form, data) {
            const report = applyServerErrors(form, data.errors);
            const lines = report.list.length ? report.list : [data.message || 'حدث خطأ أثناء حفظ البيانات'];

            Swal.fire({
                icon: 'error',
                title: report.anchored ? 'لم يُحفَظ — راجع الحقول المعلَّمة' : 'لم يُحفَظ',
                html: lines.map(m => '<div style="text-align:right">• ' + m + '</div>').join(''),
                confirmButtonText: 'موافق'
            });
        }

        function handleFormSubmission() {
            const form = document.getElementById('clientForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                clearFieldErrors(form);

                const tradeName = document.getElementById('trade_name').value.trim();
                const treeBranchId = document.getElementById('client_tree_branch_id').value;

                if ((requireTradeName && !tradeName) || !treeBranchId) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        text: 'يرجى ملء جميع الحقول المطلوبة',
                        confirmButtonText: 'موافق'
                    });
                    return;
                }

                // الموقع إلزامي فقط إذا كانت إعدادات وحدات العملاء تشترطه
                if (locationRequired) {
                    const latitude = document.getElementById('latitude').value;
                    const longitude = document.getElementById('longitude').value;

                    if (!latitude || !longitude) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تحديد الموقع مطلوب',
                            text: 'يرجى تحديد موقع العميل على الخريطة قبل الحفظ!',
                            confirmButtonText: 'إظهار الخريطة',
                            showCancelButton: true,
                            cancelButtonText: 'إلغاء'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                requestLocationPermission();
                            }
                        });
                        return;
                    }
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...';

                const formData = new FormData(form);

                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        }
                    })
                    /* ردٌّ ليس JSON (صفحة خطأ ٥٠٠ مثلاً) كان يُسقط `json()`
                       فيقع في `catch` ويقول «خطأ في الشبكة» — والشبكة سليمة.
                       يُقال للمستخدم ما جرى فعلاً. */
                    .then(response => {
                        const type = response.headers.get('content-type') || '';

                        if (type.includes('application/json')) {
                            return response.json();
                        }

                        return {
                            success: false,
                            message: 'ردٌّ غير متوقّع من الخادم (' + response.status + '). لم تُحفَظ التعديلات.'
                        };
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم بنجاح!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                if (data.redirect_url) {
                                    window.location.href = data.redirect_url;
                                }
                            });
                        } else {
                            // الحقلُ المعطوب يُعلَّم ويُمرَّر إليه المؤشّر
                            reportSaveFailure(form, data);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الشبكة',
                            text: 'حدث خطأ أثناء إرسال البيانات. يرجى المحاولة مرة أخرى.',
                            confirmButtonText: 'موافق'
                        });
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa fa-save"></i> حفظ التعديلات';
                    });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const showMapBtn = document.getElementById('showMapBtn');
            const detectLocationBtn = document.getElementById('detectLocationBtn');

            if (showMapBtn) {
                showMapBtn.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري تحميل الخريطة...';
                    this.disabled = true;

                    requestLocationPermission();

                    // إظهار زر تحديد الموقع بعد تهيئة الخريطة
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        if (detectLocationBtn) {
                            detectLocationBtn.style.display = 'inline-block';
                        }
                    }, 500); // Reduced timeout
                });
            }

            // إضافة مستمع الحدث لزر تحديد الموقع
            if (detectLocationBtn) {
                detectLocationBtn.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري تحديد الموقع...';
                    this.disabled = true;

                    detectMyLocation();

                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 500); // Reduced timeout
                });
            }

            initEmployeeManagement();
            handleFormSubmission();

            // متغير لتخزين الملف المضغوط
            let compressedFile = null;

            // معالجة اختيار الصورة مع الضغط
            const attachmentsInput = document.getElementById('attachments');
            if (attachmentsInput) {
                attachmentsInput.addEventListener('change', async function() {
                    const file = this.files[0];
                    if (!file) return;

                    // التحقق من نوع الملف
                    if (!file.type.match('image.*')) {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'يجب اختيار ملف صورة فقط',
                            confirmButtonText: 'موافق'
                        });
                        this.value = '';
                        return;
                    }

                    // إظهار شريط التقدم
                    showProgress(true);
                    updateProgress(30, 'جاري قراءة الصورة...');

                    try {
                        // ضغط الصورة
                        const result = await compressImage(file);
                        compressedFile = result.file;

                        updateProgress(100, 'تم ضغط الصورة بنجاح');

                        setTimeout(() => {
                            showProgress(false);
                            showImagePreview(result);
                        }, 500);

                    } catch (error) {
                        console.error('خطأ في ضغط الصورة:', error);
                        showProgress(false);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'حدث خطأ أثناء ضغط الصورة',
                            confirmButtonText: 'موافق'
                        });
                    }
                });
            }

            // دالة إظهار/إخفاء شريط التقدم
            function showProgress(show) {
                const progressDiv = document.getElementById('compressionProgress');
                if (show) {
                    progressDiv.classList.remove('d-none');
                } else {
                    progressDiv.classList.add('d-none');
                }
            }

            // دالة تحديث شريط التقدم
            function updateProgress(percent, text) {
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                progressBar.style.width = percent + '%';
                progressText.textContent = text;
            }

            // دالة ضغط الصورة
            function compressImage(file) {
                return new Promise((resolve, reject) => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const img = new Image();

                    img.onload = function() {
                        updateProgress(50, 'جاري ضغط الصورة...');

                        const quality = parseFloat(document.getElementById('imageQuality').value);
                        const maxSize = parseInt(document.getElementById('maxImageSize').value);

                        // حساب الأبعاد الجديدة
                        let { width, height } = img;
                        const ratio = Math.min(maxSize / width, maxSize / height);

                        if (ratio < 1) {
                            width *= ratio;
                            height *= ratio;
                        }

                        canvas.width = width;
                        canvas.height = height;

                        // رسم وضغط الصورة
                        ctx.drawImage(img, 0, 0, width, height);

                        updateProgress(80, 'جاري إنشاء الملف المضغوط...');

                        canvas.toBlob((blob) => {
                            if (!blob) {
                                reject(new Error('فشل في إنشاء الصورة المضغوطة'));
                                return;
                            }

                            // إنشاء ملف جديد من blob
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });

                            // إنشاء معاينة
                            const previewUrl = URL.createObjectURL(blob);

                            resolve({
                                file: compressedFile,
                                originalSize: file.size,
                                compressedSize: blob.size,
                                preview: previewUrl,
                                name: file.name
                            });
                        }, 'image/jpeg', quality);
                    };

                    img.onerror = function() {
                        reject(new Error('فشل في قراءة الصورة'));
                    };

                    img.src = URL.createObjectURL(file);
                });
            }

            // دالة عرض معاينة الصورة
            function showImagePreview(result) {
                const previewDiv = document.getElementById('imagePreview');
                const previewImg = document.getElementById('previewImg');
                const fileName = document.getElementById('fileName');
                const originalFileSize = document.getElementById('originalFileSize');
                const compressedFileSize = document.getElementById('compressedFileSize');
                const savingsPercent = document.getElementById('savingsPercent');
                const summaryDiv = document.getElementById('compressionSummary');

                previewImg.src = result.preview;
                fileName.textContent = result.name;
                originalFileSize.textContent = formatFileSize(result.originalSize);
                compressedFileSize.textContent = formatFileSize(result.compressedSize);

                const savings = ((result.originalSize - result.compressedSize) / result.originalSize * 100).toFixed(1);
                savingsPercent.textContent = savings + '% توفير';

                // تحديث ملخص الضغط
                document.getElementById('summaryOriginal').textContent = formatFileSize(result.originalSize);
                document.getElementById('summaryCompressed').textContent = formatFileSize(result.compressedSize);
                document.getElementById('summarySavings').textContent = savings + '%';

                previewDiv.style.display = 'block';
                summaryDiv.classList.remove('d-none');

                // إخفاء الصورة الحالية إذا وجدت
                const currentImage = document.getElementById('currentImage');
                if (currentImage) {
                    currentImage.style.opacity = '0.5';
                }

                // تحديث حقل input بالملف المضغوط
                updateFileInput(result.file);
            }

            // دالة تحديث حقل الملف بالملف المضغوط
            function updateFileInput(file) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                document.getElementById('attachments').files = dataTransfer.files;
            }

            // دالة تنسيق حجم الملف
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // جعل دالة إزالة الصورة متاحة عالمياً
            window.removeImage = function() {
                const attachmentsInput = document.getElementById('attachments');
                attachmentsInput.value = '';
                compressedFile = null;

                document.getElementById('imagePreview').style.display = 'none';
                document.getElementById('compressionSummary').classList.add('d-none');

                // إعادة إظهار الصورة الحالية
                const currentImage = document.getElementById('currentImage');
                if (currentImage) {
                    currentImage.style.opacity = '1';
                }
            };
        });

        window.initGoogleMaps = initGoogleMaps;
    </script>
@endsection
