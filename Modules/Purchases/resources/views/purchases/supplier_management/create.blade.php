@extends('purchases::master')

@section('title')
    اضافة مورد
@stop

@section('content')
    @php
        use Modules\Purchases\Services\PurchaseSettings;

        // ما تفرضه إعدادات المشتريات (قسم «الموردون») على هذه الشاشة —
        // انظر SupplierRequest::settingRules() حيث القاعدة الفعلية نفسها
        $requireSupplierName = PurchaseSettings::enabled('supplier_require_name');
        $requireMobile = PurchaseSettings::enabled('supplier_require_mobile');
        $requireTaxNumber = PurchaseSettings::enabled('supplier_require_tax_number');
        $requireCommercialRegistration = PurchaseSettings::enabled('supplier_require_commercial_registration');
        $uniqueTaxNumber = PurchaseSettings::enabled('supplier_unique_tax_number');
        $uniqueMobile = PurchaseSettings::enabled('supplier_unique_mobile');
        $uniqueEmail = PurchaseSettings::enabled('supplier_unique_email');
    @endphp
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')
    @include('purchases::purchases.supplier_management.partials.supplier-form-style')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'إضافة مورد',
            'icon' => 'truck',
            'sub' => 'يُفتح للمورد حسابٌ في شجرة الحسابات عند الحفظ',
            'crumbs' => [
                'المشتريات' => route('purchases.dashboard'),
                'الموردون' => route('SupplierManagement.index'),
                'إضافة مورد' => null,
            ],
        ])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'suppliers.create',
            'title' => __('purchase_cycle.guide.supplier.form.title'),
            'lead' => __('purchase_cycle.guide.supplier.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.supplier.required.title') => [
                    __('purchase_cycle.guide.supplier.required.a'),
                    __('purchase_cycle.guide.supplier.required.b'),
                    __('purchase_cycle.guide.supplier.required.c'),
                ],
                __('purchase_cycle.guide.supplier.balance.title') => [
                    __('purchase_cycle.guide.supplier.balance.a'),
                    __('purchase_cycle.guide.supplier.balance.b'),
                    __('purchase_cycle.guide.supplier.balance.c'),
                    __('purchase_cycle.guide.supplier.balance.d'),
                ],
            ],
            'facts' => [
                __('purchase_cycle.guide.supplier.facts.account'),
                __('purchase_cycle.guide.supplier.facts.balance'),
            ],
        ])

        <form id="supplierForm" action="{{ route('SupplierManagement.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card sl-form-bar">
                <div class="card-body">
                    <span class="sl-form-bar__note">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</span>

                    <div class="sl-form-bar__actions">
                        <a href="{{ route('SupplierManagement.index') }}" class="btn btn-outline-danger">
                            <i class="fa fa-ban"></i> الغاء
                        </a>
                        <button type="button" id="saveBtn" class="btn btn-outline-primary">
                            <i class="fa fa-save"></i> حفظ
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- بطاقاتٌ بعرض الصفحة لا عمودان متجاوران — على قياس «إضافة
                     عميل». كان العمودان يتفاوتان طولاً فيبقى تحت الأقصر
                     فراغٌ بطول الشاشة، وتتفرّق الحقولُ الإلزامية بين
                     البطاقتين. والشبكةُ في الداخل كثيفة (ثلاثةٌ في السطر)
                     فلا يتمدّد حقلُ نصٍّ قصير على نصف الشاشة. --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">بيانات دليل المورد </h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="form-body">
                                    <div class="row">
                                        <!-- الاسم التجاري -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="trade_name">الاسم التجاري @if($requireSupplierName)<span class="text-danger">*</span>@endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="trade_name" id="trade_name"
                                                        class="form-control @error('trade_name') is-invalid @enderror"
                                                        value="{{ old('trade_name') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-briefcase"></i>
                                                    </div>
                                                </div>
                                                @error('trade_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                @if($requireSupplierName)
                                                    <small class="form-text text-muted">إلزامي وفق إعدادات المشتريات — الاسم التجاري أو الاسم الأول والأخير معاً، أيّهما يكفي.</small>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- الاسم الأول والأخير -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="first_name">الاسم الأول</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="first_name" id="first_name"
                                                        class="form-control" value="{{ old('first_name') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-user"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="last_name">الاسم الأخير</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="last_name" id="last_name"
                                                        class="form-control" value="{{ old('last_name') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-user"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- الهاتف والجوال -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="phone">الهاتف</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="phone" id="phone" class="form-control"
                                                        value="{{ old('phone') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-phone"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="mobile">جوال @if($requireMobile)<span class="text-danger">*</span>@endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="mobile" id="mobile"
                                                        class="form-control @error('mobile') is-invalid @enderror"
                                                        value="{{ old('mobile') }}" @if($requireMobile) required @endif>
                                                    <div class="form-control-position">
                                                        <i class="feather icon-smartphone"></i>
                                                    </div>
                                                </div>
                                                @error('mobile')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                @if($uniqueMobile)
                                                    <small class="form-text text-muted">يجب ألا يتكرر مع مورّد آخر — وفق إعدادات المشتريات.</small>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- عنوان الشارع -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="street1">عنوان الشارع 1</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="street1" id="street1" class="form-control"
                                                        value="{{ old('street1') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map-pin"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- المدينة والمنطقة والرمز البريدي -->
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="city">المدينة</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="city" id="city"
                                                        class="form-control" value="{{ old('city') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="region">المنطقة</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="region" id="region"
                                                        class="form-control" value="{{ old('region') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="postal_code">الرمز البريدي</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="postal_code" id="postal_code"
                                                        class="form-control" value="{{ old('postal_code') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-mail"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- البلد -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="country">البلد</label>
                                                <select name="country" id="country" class="form-control">
                                                    <option value="SA" {{ old('country') == 'SA' ? 'selected' : '' }}>
                                                        المملكة العربية السعودية (SA)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- الرقم الضريبي والسجل التجاري -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="tax_number">الرقم الضريبي @if($requireTaxNumber)<span class="text-danger">*</span>@else (اختياري) @endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="tax_number" id="tax_number"
                                                        class="form-control @error('tax_number') is-invalid @enderror"
                                                        value="{{ old('tax_number') }}" @if($requireTaxNumber) required @endif>
                                                    <div class="form-control-position">
                                                        <i class="feather icon-file-text"></i>
                                                    </div>
                                                </div>
                                                @error('tax_number')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                @if($uniqueTaxNumber)
                                                    <small class="form-text text-muted">يجب ألا يتكرر مع مورّد آخر — وفق إعدادات المشتريات.</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="commercial_registration">سجل تجاري @if($requireCommercialRegistration)<span class="text-danger">*</span>@else (اختياري) @endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="commercial_registration"
                                                        id="commercial_registration" class="form-control @error('commercial_registration') is-invalid @enderror"
                                                        value="{{ old('commercial_registration') }}" @if($requireCommercialRegistration) required @endif>
                                                    <div class="form-control-position">
                                                        <i class="feather icon-file"></i>
                                                    </div>
                                                </div>
                                                @error('commercial_registration')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h4 class="card-title">قائمة الاتصال</h4>
                                                </div>
                                                <div class="card-content">
                                                    <div class="card-body">
                                                        <div class="contact-fields-container" id="contactContainer">
                                                            <!-- الحقول الديناميكية ستضاف هنا -->
                                                        </div>
                                                        <div class="text-right mt-1">
                                                            <button type="button"
                                                                class="btn btn-outline-success mr-1 mb-1"
                                                                onclick="addContactFields()">
                                                                <i class="feather icon-plus"></i> إضافة جهة اتصال
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">بيانات الحساب</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="form-body">
                                    <div class="row">
                                        <!-- رقم الكود -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="number_suply">رقم المورد</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" id="number_suply" class="form-control"
                                                        name="number_suply" value="{{ $nextNumber }}" readonly
                                                        style="background-color: #f8f8f8; cursor: not-allowed;">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-hash"></i>
                                                    </div>
                                                </div>
                                                <small class="text-muted">يتم إنشاء الرقم تلقائياً</small>
                                            </div>
                                        </div>

                                        <!-- العملة -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="currency">العملة</label>
                                                {{-- العملة من «العملات» لا تُختار: تُعرَض للقراءة
                                                     وتُرسَل مخفيّةً — `readonly` لا يعمل على select
                                                     و`disabled` لا يُرسِل. --}}
                                                @php $currencyCode = old('currency') ?: \App\Support\CurrencySettings::code(); @endphp
                                                <input type="text" id="currency" class="form-control @error('currency') is-invalid @enderror"
                                                    value="{{ $currencyCode }}" readonly
                                                    style="background-color: #f8f8f8; cursor: not-allowed;">
                                                <input type="hidden" name="currency" value="{{ $currencyCode }}">
                                                @error('currency')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- الموظف المسؤول -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="employee_id">الموظف المسؤول</label>
                                                <select name="employee_id" id="employee_id" class="form-control select2">
                                                    <option value="">— بلا موظّف مسؤول —</option>
                                                    @foreach ($employees ?? [] as $employee)
                                                        <option value="{{ $employee->id }}"
                                                            @selected(old('employee_id', auth()->user()->employee_id) == $employee->id)>
                                                            {{ $employee->full_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">من يُنسَب إليه هذا المورّد في الشاشات والتقارير</small>
                                                @error('employee_id')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- المورد تابع لـ -->
                                        @php
                                            $canSelectBranch = \App\Support\Scopes::canSelectBranch(auth()->user());
                                        @endphp
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                @if ($canSelectBranch)
                                                    <div class="row">
                                                        @include('reports.partials.affiliation-tree', [
                                                            'treeNodes' => \Modules\Purchases\Support\HeaderPickers::treeNodes(),
                                                            'name' => 'branch_id',
                                                            'value' => old('branch_id', \App\Support\Scopes::resolveBranchId(auth()->user())),
                                                            'showExact' => false,
                                                            'label' => 'المورد تابع لـ',
                                                        ])
                                                    </div>
                                                @else
                                                    <label for="branch_id">المورد تابع لـ</label>
                                                    <input type="text" class="form-control" disabled
                                                        value="{{ \App\Models\Branch::pathLabelFor(\App\Support\Scopes::resolveBranchId(auth()->user())) ?? __('branches.node_filter.unassigned') }}">
                                                @endif
                                                @if ($canSelectBranch)
                                                    <small class="text-muted d-block mt-1">
                                                        اختر الفرع أو المنطقة أو المنفذ الذي يتبع له المورد — اكتب للبحث داخل القائمة.
                                                        {{-- غير موجود؟ رابطٌ يفتح شاشة الإضافة في تبويبٍ جديد بدل مغادرة
                                                             نموذج المورد وضياع ما أُدخل. خارج `input-group` لأن select2 يكسره --}}
                                                        @permission('branches')
                                                            <a href="{{ route('branches.create') }}" target="_blank" rel="noopener">
                                                                <i class="fas fa-plus"></i> إضافة جديد
                                                            </a>
                                                        @endpermission
                                                    </small>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- البريد الإلكتروني -->
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="email">البريد الإلكتروني</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="email" id="email" class="form-control @error('email') is-invalid @enderror"
                                                        name="email" value="{{ old('email') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-mail"></i>
                                                    </div>
                                                </div>
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                @if($uniqueEmail)
                                                    <small class="form-text text-muted">يجب ألا يتكرر مع مورّد آخر — وفق إعدادات المشتريات.</small>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- الملاحظات -->
                                        <div class="col-md-12 mb-3">
                                            <label for="notes">الملاحظات</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="5" style="resize: none;">{{ old('notes') }}</textarea>
                                        </div>

                                        <!-- المرفقات -->
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="attachments">المرفقات</label>
                                                <input type="file" name="attachments" id="attachments" class="d-none">
                                                <div class="upload-area border rounded p-3 text-center position-relative"
                                                    onclick="document.getElementById('attachments').click()">
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                        <i class="fas fa-cloud-upload-alt text-primary"></i>
                                                        <span class="text-primary">اضغط هنا</span>
                                                        <span>أو</span>
                                                        <span class="text-primary">اختر من جهازك</span>
                                                    </div>
                                                    <div class="position-absolute end-0 top-50 translate-middle-y me-3">
                                                        <i class="fas fa-file-alt fs-3 text-secondary"></i>
                                                    </div>
                                                    <div id="file-name" class="mt-2 text-muted small"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <script>
        // إضافة متغير contactCounter في بداية السكريبت
        let contactCounter = 0;

        // عرض رسائل الجلسة باستخدام SweetAlert2
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: '{{ session("success") }}',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'حدث خطأ!',
                text: '{{ session("error") }}',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#dc3545'
            });
        @endif

        @if($errors->any())
            let errorMessages = '';
            @foreach($errors->all() as $error)
                errorMessages += '• {{ $error }}\n';
            @endforeach

            Swal.fire({
                icon: 'error',
                title: 'يرجى تصحيح الأخطاء التالية:',
                text: errorMessages,
                confirmButtonText: 'موافق',
                confirmButtonColor: '#dc3545'
            });
        @endif


        /* ══════ التحقّق قبل التأكيد ══════
           الزرُّ يمنع الإرسالَ الأصلي ويرسل بـ`form.submit()` — وهذا يتخطّى
           فحصَ `required` في المتصفّح كلَّه، فكان سؤالُ «هل تريد الحفظ؟» يظهر
           والحقولُ الإلزامية فارغة، ثمّ يردّ الخادم. فيُفحص النموذجُ هنا أوّلاً:
           الحقلُ الناقص يُبرَز ويُفتح تبويبُه ويُوجَّه إليه المؤشّر برسالة
           المتصفّح نفسِها، ولا يُسأل المستخدم شيئاً حتى يكتمل. وقاعدةُ الاسم
           (التجاري أو الأول+الأخير) تُبلَّغ بالطريقة نفسِها لا بنافذة. */
        function firstInvalidField(form) {
            @if($requireSupplierName)
            const tn = form.querySelector('#trade_name');
            const fn = form.querySelector('#first_name');
            const ln = form.querySelector('#last_name');
            const nameOk = tn.value.trim() || (fn.value.trim() && ln.value.trim());
            tn.setCustomValidity(nameOk ? '' : 'يرجى إدخال الاسم التجاري، أو الاسم الأول والأخير معاً');
            @endif

            if (form.checkValidity()) return null;

            return form.querySelector(':invalid');
        }

        function focusInvalid(form, field) {
            const pane = field.closest('.tab-pane');
            if (pane && !pane.classList.contains('active')) {
                const link = document.querySelector('[data-bs-toggle="tab"][href="#' + pane.id + '"], [data-toggle="tab"][href="#' + pane.id + '"], [data-bs-toggle="tab"][data-bs-target="#' + pane.id + '"]');
                if (link) {
                    if (window.bootstrap && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance(link).show();
                    else if (window.jQuery) jQuery(link).tab('show');
                }
            }

            field.classList.add('is-invalid');
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // بعد فتح التبويب — الحقلُ المخفي لا يستقبل رسالةَ المتصفّح
            setTimeout(function () { form.reportValidity(); field.focus(); }, 150);
        }

        // يزول التمييزُ حين يبدأ المستخدم التصحيح
        document.querySelectorAll('#supplierForm .form-control').forEach(function (el) {
            el.addEventListener('input', function () {
                el.classList.remove('is-invalid');
                if (el.id === 'trade_name' || el.id === 'first_name' || el.id === 'last_name') {
                    const tn = document.getElementById('trade_name');
                    if (tn) tn.setCustomValidity('');
                }
            });
        });

        // Enter داخل حقلٍ يُرسل النموذجَ أصلياً متجاوزاً الزرَّ — يُحوَّل إلى الزرّ
        document.getElementById('supplierForm').addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById('saveBtn').click();
        });

        // التعامل مع زر الحفظ
        document.getElementById('saveBtn').addEventListener('click', function(e) {
            e.preventDefault();

            // التحقّق من الإلزامي كلِّه — يوجّه إلى الحقل الناقص ولا يسأل شيئاً
            const invalidField = firstInvalidField(document.getElementById('supplierForm'));
            if (invalidField) {
                focusInvalid(document.getElementById('supplierForm'), invalidField);
                return;
            }

            // رسالة التأكيد قبل الحفظ
            Swal.fire({
                title: 'تأكيد الحفظ',
                text: 'هل أنت متأكد من رغبتك في حفظ بيانات المورد؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // عرض رسالة التحميل
                    Swal.fire({
                        title: 'جاري الحفظ...',
                        text: 'يرجى الانتظار',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // إرسال النموذج
                    document.getElementById('supplierForm').submit();
                }
            });
        });

        // دالة إضافة حقول جهة اتصال جديدة
        function addContactFields() {
            contactCounter++;
            const contactContainer = document.getElementById('contactContainer');
            const newContactGroup = document.createElement('div');
            newContactGroup.className = 'contact-fields-group mb-3 p-3 border rounded';
            newContactGroup.style.borderColor = '#e3e6ef';
            newContactGroup.style.backgroundColor = '#f8f9ff';
            newContactGroup.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-primary">جهة اتصال ${contactCounter}</h6>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeContactFields(this)">
                        <i class="fa fa-trash"></i> حذف
                    </button>
                </div>
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
                </div>
            `;
            contactContainer.appendChild(newContactGroup);

            // رسالة تأكيد الإضافة
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'تم إضافة جهة اتصال جديدة',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        }

        // دالة حذف حقول جهة اتصال
        function removeContactFields(button) {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم حذف بيانات جهة الاتصال نهائياً',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    const contactGroup = button.closest('.contact-fields-group');
                    contactGroup.remove();

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'تم حذف جهة الاتصال',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                }
            });
        }

        // التعامل مع تحديد الملف
        document.getElementById('attachments').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileNameDiv = document.getElementById('file-name');

            if (fileName) {
                fileNameDiv.textContent = `تم اختيار: ${fileName}`;
                fileNameDiv.style.color = '#28a745';

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'تم اختيار الملف بنجاح',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            } else {
                fileNameDiv.textContent = '';
            }
        });

        // إضافة استماع للأحداث عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('الصفحة محملة بنجاح مع SweetAlert2');

            // إضافة تأثيرات بصرية للحقول المطلوبة
            const requiredFields = document.querySelectorAll('input[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '#28a745';
                    }
                });
            });
        });
    </script>
@endsection
