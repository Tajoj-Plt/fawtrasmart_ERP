@extends('client::master')

@section('title')
    اضافة عميل
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

        /* «وضع العميل» — إلزاميّته مفتاحٌ في نفس مجموعة «الحقول الإلزامية»،
           والقيمة المختارة سلفاً هي الوضع الافتراضي المضبوط في «إعدادات
           وحدات العملاء ← العملاء»، فمن لا يغيّرها يحصل على ما كانت
           الشاشةُ تكتبه تلقائياً قبل وجود هذا الحقل. */
        $requireRegistrationStage = ClientSettingsHelper::clientRequireRegistrationStage();
        $defaultRegistrationStageId = ClientSettingsHelper::defaultRegistrationStageId();
        $registrationStages = \App\Models\Statuses::section('registration_stage')->ordered()->get();

        /* حقلا «الصورة» و«الموقع» من نفس المصدر: تفعيلُهما من «الحقول
           الإضافية» في «إعدادات وحدات العملاء»، وإلزامُهما — حين مفعَّلَين —
           من مفتاحٍ آخر في نفس الشاشة. القالب والمتحكّم (`store()`،
           `ClientRequest`) يقرآن المصدر نفسه فلا يفترقان. */
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
            'title' => 'إضافة عميل',
            'icon' => 'user-plus',
            'sub' => __('client_settings.module_settings.subtitle'),
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'إضافة عميل' => null,
            ],
        ])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً، كما في «إضافة مورد» --}}
        @include('client::components.guide', [
            'key' => 'clients.create',
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

        <form id="clientForm" action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- حقلين مخفيين لتخزين الإحداثيات -->
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">

            {{-- ══════════ تخطيط النموذج ══════════
                 كان النموذج عمودين متجاورين: «بيانات العميل» طويلٌ (العنوان والخريطة
                 وقائمة الاتصال) و«بيانات الحساب» قصير، فيبقى تحت الثاني فراغٌ بطول
                 الشاشة، والحقولُ الإلزامية مفرّقةٌ بين البطاقتين.
                 صار ثلاثَ بطاقاتٍ بعرض الصفحة وشبكةً كثيفة:
                   ١. «البيانات الأساسية» — كلُّ حقلٍ قد تُلزمه «إعدادات وحدات العملاء».
                      الإلزاميُّ فعلاً يتقدّم بـ`order` (صنف `cl-field--opt` على غيره)،
                      فتبقى الحقول الإلزامية متجاورةً مهما تغيّرت الإعدادات.
                   ٢. «بيانات إضافية» — العنوان والتفاصيل التي لا إلزام عليها.
                   ٣. «قائمة الاتصال» — جهاتُ الاتصال متجاورةً في شبكة.
                 كلُّ `id` و`name` و`.form-group` تقرؤه سكربتات الشاشة بقي كما هو. --}}
            <div class="card cl-form-bar">
                <div class="card-body">
                    <span class="cl-form-bar__note">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</span>
                    <div class="cl-form-bar__actions">
                        <a href="{{ route('clients.index') }}" class="btn btn-outline-danger">
                            <i class="fa fa-ban"></i> الغاء
                        </a>
                        <button type="submit" class="btn btn-outline-primary" id="submitBtn">
                            <i class="fa fa-save"></i> حفظ
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
                                    class="form-control" value="{{ old('trade_name') }}"
                                    @if($requireTradeName) required @endif>
                            </div>
                        </div>

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
                                    'selectedBranchId' => old('client_tree_branch_id'),
                                ])
                            </div>
                        </div>

                        <!-- الهاتف والجوال -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requirePhone) }}">
                            <div class="form-group">
                                <label for="phone">الهاتف @if($requirePhone)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="phone" id="phone" class="form-control"
                                    value="{{ old('phone') }}"
                                    @if($requirePhone) required @endif>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireMobile) }}">
                            <div class="form-group">
                                <label for="mobile">جوال @if($requireMobile)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="mobile" id="mobile" class="form-control"
                                    value="{{ old('mobile') }}"
                                    @if($requireMobile) required @endif>
                            </div>
                        </div>

                        <!-- البريد الإلكتروني -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireEmail) }}">
                            <div class="form-group">
                                <label for="email">البريد الإلكتروني @if($requireEmail)<span class="text-danger">*</span>@endif</label>
                                <input type="email" id="email" class="form-control"
                                    name="email" value="{{ old('email') }}">
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
                                            <option value="{{ $category->id }}">{{ $category->name }}
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

                        {{-- وضع العميل — القائمة تُدار من: إعدادات وحدات العملاء ←
                             العملاء ← «أوضاع العملاء»، والافتراضي منها يُختار سلفاً
                             هنا. إلزاميّته من مفتاح «وضع العميل مطلوب» في نفس
                             الشاشة، وحين لا يكون إلزامياً ويُترَك فارغاً يكتب
                             `store()` الوضعَ الافتراضي. --}}
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireRegistrationStage) }}">
                            <div class="form-group">
                                <label for="registration_stage_id">
                                    وضع العميل @if($requireRegistrationStage)<span class="text-danger">*</span>@endif
                                    @if (hasPermission('settings_customer_followup_status') || hasPermission('clients_edit_client_settings'))
                                        <a href="{{ route('clients.registrationStages') }}" target="_blank" rel="noopener"
                                            class="small" title="إدارة قائمة أوضاع العملاء">
                                            <i class="feather icon-settings"></i> إدارة القائمة
                                        </a>
                                    @endif
                                </label>
                                <select class="form-control select2" name="registration_stage_id" id="registration_stage_id"
                                    @if($requireRegistrationStage) required @endif>
                                    <option value="">@if($requireRegistrationStage)اختر وضع العميل@else— بدون —@endif</option>
                                    @foreach ($registrationStages as $stage)
                                        <option value="{{ $stage->id }}"
                                            {{ (int) old('registration_stage_id', $defaultRegistrationStageId) === $stage->id ? 'selected' : '' }}>
                                            {{ $stage->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if (! $requireRegistrationStage)
                                    <small class="text-muted d-block">يُترَك فارغاً ليأخذ العميلُ الوضعَ الافتراضي من إعدادات وحدات العملاء.</small>
                                @endif
                            </div>
                        </div>

                        <!-- الرقم الضريبي والسجل التجاري -->
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireTaxNumber) }}">
                            <div class="form-group">
                                <label for="tax_number">الرقم الضريبي @if($requireTaxNumber)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="tax_number" id="tax_number"
                                    class="form-control" value="{{ old('tax_number') }}">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6{{ $optionalField($requireCommercialRegistration) }}">
                            <div class="form-group">
                                <label for="commercial_registration">سجل تجاري @if($requireCommercialRegistration)<span class="text-danger">*</span>@endif</label>
                                <input type="text" name="commercial_registration"
                                    id="commercial_registration" class="form-control"
                                    value="{{ old('commercial_registration') }}"
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
                                <label for="attachments" class="form-label">صورة العميل <span class="text-danger">*</span></label>
                                <input id="attachments" type="file" name="attachments"
                                    class="form-control d-none @error('attachments') is-invalid @enderror"
                                    accept="image/*" capture="environment">

                                <!-- منطقة الرفع -->
                                <div class="upload-area border rounded p-3 text-center position-relative bg-light"
                                    onclick="document.getElementById('attachments').click()" style="cursor: pointer; border: 2px dashed #007bff !important;">
                                    <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                        <i class="fas fa-camera fa-2x text-primary"></i>
                                        <p class="mb-0 text-primary fw-bold">اضغط هنا لفتح الكاميرا أو اختيار صورة</p>
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

                                <!-- معاينة الصورة -->
                                <div id="imagePreview" class="mt-3" style="display: none;">
                                    <div class="file-item border rounded p-3 bg-light">
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
                                                    <i class="fas fa-trash"></i>
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
                        <!-- الاسم الأول والأخير -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="first_name">الاسم الأول</label>
                                <input type="text" name="first_name" id="first_name"
                                    class="form-control" value="{{ old('first_name') }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="last_name">الاسم الأخير</label>
                                <input type="text" name="last_name" id="last_name"
                                    class="form-control" value="{{ old('last_name') }}">
                            </div>
                        </div>

                        <!-- طريقة الفاتورة -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="printing_method">طريقة الفاتورة</label>
                                <select class="form-control" id="printing_method"
                                    name="printing_method">
                                    <option value="1"
                                        {{ old('printing_method') == 1 ? 'selected' : '' }}>الطباعة
                                    </option>
                                    <option value="2"
                                        {{ old('printing_method') == 2 ? 'selected' : '' }}>ارسل عبر
                                        البريد</option>
                                </select>
                            </div>
                        </div>

                        <!-- العملة: افتراضية SAR بلا حقلٍ في النموذج -->
                        <input type="hidden" name="currency" value="SAR">

                        <!-- البلد -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="country">البلد</label>
                                <select name="country" id="country" class="form-control">
                                    <option value="SA" {{ old('country') == 'SA' ? 'selected' : '' }}>
                                        المملكة العربية السعودية (SA)</option>
                                </select>
                            </div>
                        </div>

                        <!-- عنوان الشارع -->
                        <div class="col-xl-6 col-lg-8 col-md-12">
                            <div class="form-group">
                                <label for="street1">عنوان الشارع</label>
                                <input type="text" name="street1" id="street1"
                                    class="form-control" value="{{ old('street1') }}">
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
                                    value="{{ old('building_number') }}">
                                @error('building_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <!-- الحي -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="neighborhood_name_manual">الحي</label>
                                <input type="text" name="neighborhood_name_manual" id="neighborhood_name_manual"
                                    class="form-control" placeholder="كتابة اسم الحي يدوياً">
                            </div>
                        </div>

                        <!-- المدينة والمنطقة والرمز البريدي -->
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="city">المدينة</label>
                                <input type="text" name="city" id="city"
                                    class="form-control" value="{{ old('city') }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="region">المنطقة</label>
                                <input type="text" name="region" id="region"
                                    class="form-control" value="{{ old('region') }}">
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="postal_code">الرمز البريدي</label>
                                <input type="text" name="postal_code" id="postal_code"
                                    class="form-control" value="{{ old('postal_code') }}">
                            </div>
                        </div>

                        {{-- الموظفين المسؤولين — نفس صلاحية `clients_assign_employees` التي
                             يتحقّق منها `ClientController::store()` قبل حفظ الإسناد. --}}
                        @if (hasPermission('clients_assign_employees'))
                            <div class="col-xl-6 col-lg-8 col-md-12">
                                <div class="form-group">
                                    <label for="employee_client_id" class="form-label">الموظفين
                                        المسؤولين</label>
                                    <div class="cl-select-add">
                                        <select id="employee_select" class="form-control">
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
                                    {{-- الحقل الحقيقي الذي سترسله للباك إند --}}
                                    <div id="selected_employees"></div>

                                    {{-- هنا سيظهر الموظفون المختارون --}}
                                    <ul id="employee_list" class="mt-2 list-group"></ul>

                                    @error('employee_client_id')
                                        <div class="invalid-feedback">{{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <!-- الملاحظات -->
                        <div class="col-12">
                            <label for="notes">الملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" style="resize: vertical;">{{ old('notes') }}</textarea>
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
                    {{-- الحقول الديناميكية تُضاف هنا — الحاوية بلا مسافاتٍ داخلها كي
                         يطابقها `:empty` فيُطوى جسم البطاقة ما دامت فارغة --}}
                    <div class="contact-fields-container" id="contactContainer"></div>
                </div>
            </div>
        </form>
    </div>
@endsection


@section('scripts')
    {{-- حقول الهاتف والجوال: رقمٌ سعودي يبدأ بصفر وطولُه 10 — نظيرُ App\Rules\SaudiPhone --}}
    <script src="{{ asset('assets/js/saudi-phone.js') }}?v=1"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Google Maps API -->
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initGoogleMaps"
        async defer></script>

    <script>
        // متغيرات الخريطة
        let map;
        let marker;
        let searchBox;
        let contactCounter = 0;
        let selectedEmployeeIds = [];
        let isGoogleMapsLoaded = false;
        let isMapInitialized = false;

        // نفس مصدر التحقّق في الخادم (ClientRequest، ClientSettingsHelper) —
        // لا يُشترَط شيءٌ هنا لم يُشترَط هناك، ولا العكس
        const imageFieldEnabled = @json($imageFieldEnabled);
        const locationRequired = @json($locationRequired);

        // دالة تهيئة Google Maps (callback function)
        function initGoogleMaps() {
            isGoogleMapsLoaded = true;
            console.log('Google Maps تم تحميله بنجاح');
        }

        // دالة إضافة حقول جهة اتصال جديدة
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

        // دالة حذف حقول جهة اتصال
        function removeContactFields(button) {
            const contactGroup = button.closest('.contact-fields-group');
            contactGroup.remove();
        }

        // دالة تهيئة الخريطة
        function initMap(lat = 24.7136, lng = 46.6753) {
            if (!isGoogleMapsLoaded) {
                console.log('انتظار تحميل Google Maps...');
                setTimeout(() => initMap(lat, lng), 100); // Reduced timeout
                return;
            }

            const mapContainer = document.getElementById('map-container');
            const mapElement = document.getElementById('map');

            if (!mapElement) {
                console.error('عنصر الخريطة غير موجود');
                return;
            }

            // إظهار الخريطة أولاً
            mapContainer.style.display = 'block';

            // تعيين الإحداثيات في الحقول المخفية
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Create map immediately without timeout
            // إنشاء الخريطة
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

            // إنشاء علامة
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

            // حدث عند سحب العلامة
            marker.addListener('dragend', function() {
                const newLat = marker.getPosition().lat();
                const newLng = marker.getPosition().lng();
                document.getElementById('latitude').value = newLat;
                document.getElementById('longitude').value = newLng;
                fetchAddressFromCoordinates(newLat, newLng);
            });

            // حدث عند النقر على الخريطة
            map.addListener('click', function(event) {
                const newLat = event.latLng.lat();
                const newLng = event.latLng.lng();
                updateMapPosition(newLat, newLng);
            });

            isMapInitialized = true;
            console.log('تم تهيئة الخريطة بنجاح');

            // جلب العنوان من الإحداثيات
            fetchAddressFromCoordinates(lat, lng);
        }

        // دالة تحديث موقع الخريطة
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

        // دالة جلب العنوان من الإحداثيات
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

                    // تحديث حقول العنوان تلقائياً
                    const region = getAddressComponent(addressComponents, 'administrative_area_level_1');
                    const city = getAddressComponent(addressComponents, 'locality') || getAddressComponent(
                        addressComponents, 'administrative_area_level_2');
                    const postalCode = getAddressComponent(addressComponents, 'postal_code');
                    const street1 = getAddressComponent(addressComponents, 'route');
                    const neighborhoodName = getAddressComponent(addressComponents, 'neighborhood') ||
                                    getAddressComponent(addressComponents, 'sublocality_level_1') ||
                                    getAddressComponent(addressComponents, 'sublocality');

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

        // دالة مساعدة لجلب مكونات العنوان
        function getAddressComponent(components, type) {
            const component = components.find(c => c.types.includes(type));
            return component ? component.long_name : '';
        }

        // دالة طلب إذن الموقع وإظهار الخريطة
        function requestLocationPermission() {
            // التحقق من تحميل Google Maps أولاً
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

        // إدارة الموظفين
        function initEmployeeManagement() {
            const employeeSelect = document.getElementById('employee_select');
            const employeeList = document.getElementById('employee_list');
            const selectedEmployees = document.getElementById('selected_employees');

            if (employeeSelect) {
                employeeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const employeeId = selectedOption.value;
                    const employeeName = selectedOption.dataset.name;

                    if (employeeId && !selectedEmployeeIds.includes(employeeId)) {
                        selectedEmployeeIds.push(employeeId);

                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        li.textContent = employeeName;

                        const removeBtn = document.createElement('button');
                        removeBtn.textContent = 'حذف';
                        removeBtn.className = 'btn btn-sm btn-danger';
                        removeBtn.onclick = () => {
                            li.remove();
                            selectedEmployeeIds = selectedEmployeeIds.filter(id => id !== employeeId);
                            updateHiddenInputs();
                        };

                        li.appendChild(removeBtn);
                        employeeList.appendChild(li);
                        updateHiddenInputs();
                    }

                    this.value = '';
                });
            }

            function updateHiddenInputs() {
                selectedEmployees.innerHTML = '';
                selectedEmployeeIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_client_id[]';
                    input.value = id;
                    selectedEmployees.appendChild(input);
                });
            }
        }

        // معالجة إرسال النموذج بـ AJAX
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
                console.log('Form submit triggered');

                // إزالة كل علامات الخطأ السابقة — ومعها رسائل الخادم المحقونة
                clearFieldErrors(form);

                // 1. التحقق من الصورة أولاً — إلزامية فقط إذا كان حقلها مفعّلاً
                const attachmentsField = document.getElementById('attachments');

                if (imageFieldEnabled && attachmentsField && attachmentsField.files.length === 0) {
                    scrollToUploadArea('يرجى رفع صورة للعميل');
                    return;
                }

                // 2. التحقق من الموقع — إلزامي فقط إذا كانت إعدادات وحدات
                //    العملاء تشترطه (حقل «الموقع» مفعّل و«الموقع مطلوب» مفعّل)
                if (locationRequired) {
                    const latitude = document.getElementById('latitude').value;
                    const longitude = document.getElementById('longitude').value;

                    if (!latitude || !longitude) {
                        scrollToMapArea('يرجى تحديد موقع العميل على الخريطة');
                        return;
                    }
                }

                // 3. التحقق من باقي الحقول الإلزامية (required)
                const requiredFields = form.querySelectorAll('[required]');
                let firstInvalidField = null;

                // التحقق من كل حقل إلزامي
                for (let field of requiredFields) {
                    // تجاهل حقل الصورة لأنه تم التحقق منه أعلاه
                    if (field.id === 'attachments') continue;

                    let isEmpty = false;

                    if (field.type === 'file') {
                        isEmpty = field.files.length === 0;
                    } else if (field.tagName === 'SELECT') {
                        isEmpty = !field.value || field.value === '';
                    } else {
                        isEmpty = !field.value || field.value.trim() === '';
                    }

                    if (isEmpty) {
                        firstInvalidField = field;
                        break;
                    }
                }

                // إذا وجد حقل فارغ
                if (firstInvalidField) {
                    const fieldId = firstInvalidField.id;
                    const fieldLabel = getFieldLabel(firstInvalidField);

                    // التحقق إذا كان حقل رفع ملف
                    if (firstInvalidField.type === 'file') {
                        scrollToUploadArea(fieldLabel + ' مطلوب');
                    } else {
                        scrollToField(fieldId, fieldLabel + ' مطلوب');
                    }
                    return;
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
                    .then(response => {
                        // التحقق من نوع الرد
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            return response.json();
                        } else {
                            // إذا كان رد redirect (HTML)، نعيد التوجيه مباشرة
                            return response.text().then(text => ({
                                success: true,
                                message: 'تم إضافة العميل بنجاح!',
                                isRedirect: true,
                                redirectUrl: response.url
                            }));
                        }
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
                                } else if (data.isRedirect) {
                                    location.reload();
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
                        submitBtn.innerHTML = '<i class="fa fa-save"></i> حفظ';
                    });
            });
        }

        // تهيئة الصفحة عند التحميل
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
            };
        });

        // دالة التمرير للحقل الفارغ مع إظهار تنبيه
        function scrollToField(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.classList.add('is-invalid');
                field.focus();

                // إضافة تأثير وميض
                field.style.boxShadow = '0 0 10px rgba(255, 0, 0, 0.5)';
                setTimeout(() => {
                    field.style.boxShadow = '';
                }, 2000);

                Swal.fire({
                    icon: 'error',
                    title: 'حقل مطلوب',
                    text: message,
                    confirmButtonText: 'موافق'
                });
            }
        }

        // دالة للحصول على اسم الحقل من الـ label
        function getFieldLabel(field) {
            // البحث عن label بالـ for attribute
            let label = document.querySelector(`label[for="${field.id}"]`);

            if (label) {
                // إزالة النجمة الحمراء من النص
                return label.textContent.replace('*', '').trim();
            }

            // البحث عن label في نفس الـ parent
            const parent = field.closest('.form-group') || field.closest('.col-md-12') || field.closest('.col-12');
            if (parent) {
                label = parent.querySelector('label');
                if (label) {
                    return label.textContent.replace('*', '').trim();
                }
            }

            // إذا لم يجد label، استخدم اسم الحقل
            return field.name || field.id || 'هذا الحقل';
        }

        // دالة التمرير لمنطقة رفع الصورة
        window.scrollToUploadArea = function(message) {
            console.log('scrollToUploadArea called:', message);
            const uploadArea = document.querySelector('.upload-area');
            console.log('uploadArea found:', uploadArea);
            if (uploadArea) {
                uploadArea.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // إضافة تأثير وميض
                uploadArea.style.borderColor = '#dc3545';
                uploadArea.style.boxShadow = '0 0 15px rgba(220, 53, 69, 0.5)';
                uploadArea.style.animation = 'pulse 0.5s ease-in-out 3';

                setTimeout(() => {
                    uploadArea.style.borderColor = '#007bff';
                    uploadArea.style.boxShadow = '';
                    uploadArea.style.animation = '';
                }, 3000);

                Swal.fire({
                    icon: 'error',
                    title: 'صورة العميل مطلوبة',
                    text: message,
                    confirmButtonText: 'اختيار صورة'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('attachments').click();
                    }
                });
            }
        }

        // دالة التمرير لمنطقة الخريطة
        window.scrollToMapArea = function(message) {
            console.log('scrollToMapArea called:', message);
            const mapBtn = document.getElementById('showMapBtn');
            console.log('mapBtn found:', mapBtn);

            if (mapBtn) {
                // التمرير للزر
                mapBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // إضافة تأثير وميض للزر
                mapBtn.classList.add('btn-danger');
                mapBtn.classList.remove('btn-outline-primary');
                mapBtn.style.boxShadow = '0 0 15px rgba(220, 53, 69, 0.5)';
                mapBtn.style.animation = 'pulse 0.5s ease-in-out 3';

                setTimeout(() => {
                    mapBtn.classList.remove('btn-danger');
                    mapBtn.classList.add('btn-outline-primary');
                    mapBtn.style.boxShadow = '';
                    mapBtn.style.animation = '';
                }, 3000);

                Swal.fire({
                    icon: 'error',
                    title: 'الموقع مطلوب',
                    text: message,
                    confirmButtonText: 'تحديد الموقع'
                }).then((result) => {
                    if (result.isConfirmed) {
                        mapBtn.click();
                    }
                });
            }
        }

        window.initGoogleMaps = initGoogleMaps;
    </script>

    <style>
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
@endsection
