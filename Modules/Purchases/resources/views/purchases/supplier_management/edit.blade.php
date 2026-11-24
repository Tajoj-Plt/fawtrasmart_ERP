@extends('purchases::master')

@section('title')
    تعديل المورد
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
            'title' => 'تعديل مورد: '.$supplier->trade_name,
            'icon' => 'truck',
            'sub' => 'التعديل لا يمسّ حساب المورد في شجرة الحسابات ولا رصيده',
            'crumbs' => [
                'المشتريات' => route('purchases.dashboard'),
                'الموردون' => route('SupplierManagement.index'),
                'تعديل' => null,
            ],
        ])

        {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
        @include('purchases::components.guide', [
            'key' => 'suppliers.edit',
            'title' => __('purchase_cycle.guide.supplier.form.title'),
            'lead' => __('purchase_cycle.guide.supplier.what_is'),
            'open' => false,
            'blocks' => [
                __('purchase_cycle.guide.supplier.required.title') => [
                    __('purchase_cycle.guide.supplier.required.a'),
                    __('purchase_cycle.guide.supplier.required.b'),
                    __('purchase_cycle.guide.supplier.required.c'),
                ],
                __('purchase_cycle.guide.supplier.inactive.title') => [
                    __('purchase_cycle.guide.supplier.inactive.a'),
                    __('purchase_cycle.guide.supplier.inactive.b'),
                ],
            ],
        ])

        <form id="supplierUpdateForm" action="{{ route('SupplierManagement.update', $supplier->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- أزرار الحفظ والإلغاء -->
            <div class="card sl-form-bar">
                <div class="card-body">
                    <span class="sl-form-bar__note">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</span>

                    <div class="sl-form-bar__actions">
                        <a href="{{ route('SupplierManagement.index') }}" class="btn btn-outline-danger" id="cancelBtn">
                            <i class="fa fa-ban"></i> الغاء
                        </a>
                        <button type="button" class="btn btn-outline-primary" id="updateBtn">
                            <i class="fa fa-save"></i> تحديث
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- بطاقاتٌ بعرض الصفحة لا عمودان متجاوران — على قياس «تعديل
                     عميل». كان العمودان يتفاوتان طولاً فيبقى تحت الأقصر
                     فراغٌ بطول الشاشة، وتتفرّق الحقولُ الإلزامية بين
                     البطاقتين. والشبكةُ في الداخل كثيفة (ثلاثةٌ في السطر)
                     فلا يتمدّد حقلُ نصٍّ قصير على نصف الشاشة. --}}
                <!-- بيانات المورد -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">بيانات المورد</h4>
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
                                                        value="{{ old('trade_name', $supplier->trade_name ?? '') }}">
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
                                                        class="form-control @error('first_name') is-invalid @enderror"
                                                        value="{{ old('first_name', $supplier->first_name ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-user"></i>
                                                    </div>
                                                </div>
                                                @error('first_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="last_name">الاسم الأخير</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="last_name" id="last_name"
                                                        class="form-control @error('last_name') is-invalid @enderror"
                                                        value="{{ old('last_name', $supplier->last_name ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-user"></i>
                                                    </div>
                                                </div>
                                                @error('last_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- الهاتف والجوال -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="phone">الهاتف</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="phone" id="phone"
                                                        class="form-control @error('phone') is-invalid @enderror"
                                                        value="{{ old('phone', $supplier->phone ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-phone"></i>
                                                    </div>
                                                </div>
                                                @error('phone')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="mobile">جوال @if($requireMobile)<span class="text-danger">*</span>@endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="mobile" id="mobile"
                                                        class="form-control @error('mobile') is-invalid @enderror"
                                                        value="{{ old('mobile', $supplier->mobile ?? '') }}" @if($requireMobile) required @endif>
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
                                                    <input type="text" name="street1" id="street1"
                                                        class="form-control @error('street1') is-invalid @enderror"
                                                        value="{{ old('street1', $supplier->street1 ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map-pin"></i>
                                                    </div>
                                                </div>
                                                @error('street1')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- المدينة والمنطقة والرمز البريدي -->
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="city">المدينة</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="city" id="city"
                                                        class="form-control @error('city') is-invalid @enderror"
                                                        value="{{ old('city', $supplier->city ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map"></i>
                                                    </div>
                                                </div>
                                                @error('city')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="region">المنطقة</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="region" id="region"
                                                        class="form-control @error('region') is-invalid @enderror"
                                                        value="{{ old('region', $supplier->region ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-map"></i>
                                                    </div>
                                                </div>
                                                @error('region')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label for="postal_code">الرمز البريدي</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="postal_code" id="postal_code"
                                                        class="form-control @error('postal_code') is-invalid @enderror"
                                                        value="{{ old('postal_code', $supplier->postal_code ?? '') }}">
                                                    <div class="form-control-position">
                                                        <i class="feather icon-mail"></i>
                                                    </div>
                                                </div>
                                                @error('postal_code')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- البلد -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="country">البلد</label>
                                                <select name="country" id="country" class="form-control @error('country') is-invalid @enderror">
                                                    <option value="">اختر البلد</option>
                                                    <option value="SA" {{ old('country', $supplier->country ?? '') == 'SA' ? 'selected' : '' }}>
                                                        المملكة العربية السعودية (SA)
                                                    </option>
                                                </select>
                                                @error('country')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- الرقم الضريبي والسجل التجاري -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="tax_number">الرقم الضريبي @if($requireTaxNumber)<span class="text-danger">*</span>@else (اختياري) @endif</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" name="tax_number" id="tax_number"
                                                        class="form-control @error('tax_number') is-invalid @enderror"
                                                        value="{{ old('tax_number', $supplier->tax_number ?? '') }}" @if($requireTaxNumber) required @endif>
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
                                                        id="commercial_registration"
                                                        class="form-control @error('commercial_registration') is-invalid @enderror"
                                                        value="{{ old('commercial_registration', $supplier->commercial_registration ?? '') }}" @if($requireCommercialRegistration) required @endif>
                                                    <div class="form-control-position">
                                                        <i class="feather icon-file"></i>
                                                    </div>
                                                </div>
                                                @error('commercial_registration')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- قائمة الاتصال -->
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h4 class="card-title">قائمة الاتصال</h4>
                                                </div>
                                                <div class="card-content">
                                                    <div class="card-body">
                                                        <div class="contact-fields-container" id="contactContainer">
                                                            <!-- حقول جهات الاتصال الموجودة -->
                                                            @if(isset($supplier->contacts) && count($supplier->contacts) > 0)
                                                                @foreach($supplier->contacts as $index => $contact)
                                                                    <div class="contact-fields-group mb-3 p-3 border rounded" style="background-color: #f8f9ff; border-color: #e3e6ef;">
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <h6 class="mb-0 text-primary">جهة اتصال {{ $index + 1 }}</h6>
                                                                            <button type="button" class="btn btn-danger btn-sm" onclick="removeContactFields(this)">
                                                                                <i class="fa fa-trash"></i> حذف
                                                                            </button>
                                                                        </div>
                                                                        <div class="row">
                                                                            <div class="col-md-6 mb-2">
                                                                                <label>الاسم الأول</label>
                                                                                <input type="text" class="form-control"
                                                                                    name="contacts[{{ $index }}][first_name]"
                                                                                    value="{{ old('contacts.'.$index.'.first_name', $contact->first_name ?? '') }}"
                                                                                    placeholder="الاسم الأول">
                                                                            </div>
                                                                            <div class="col-md-6 mb-2">
                                                                                <label>الاسم الأخير</label>
                                                                                <input type="text" class="form-control"
                                                                                    name="contacts[{{ $index }}][last_name]"
                                                                                    value="{{ old('contacts.'.$index.'.last_name', $contact->last_name ?? '') }}"
                                                                                    placeholder="الاسم الأخير">
                                                                            </div>
                                                                        </div>
                                                                        <div class="row">
                                                                            <div class="col-md-6 mb-2">
                                                                                <label>البريد الإلكتروني</label>
                                                                                <input type="email" class="form-control"
                                                                                    name="contacts[{{ $index }}][email]"
                                                                                    value="{{ old('contacts.'.$index.'.email', $contact->email ?? '') }}"
                                                                                    placeholder="البريد الإلكتروني">
                                                                            </div>
                                                                            <div class="col-md-6 mb-2">
                                                                                <label>الهاتف</label>
                                                                                <input type="tel" class="form-control"
                                                                                    name="contacts[{{ $index }}][phone]"
                                                                                    value="{{ old('contacts.'.$index.'.phone', $contact->phone ?? '') }}"
                                                                                    placeholder="الهاتف">
                                                                            </div>
                                                                        </div>
                                                                        <div class="row">
                                                                            <div class="col-md-6 mb-2">
                                                                                <label>جوال</label>
                                                                                <input type="tel" class="form-control"
                                                                                    name="contacts[{{ $index }}][mobile]"
                                                                                    value="{{ old('contacts.'.$index.'.mobile', $contact->mobile ?? '') }}"
                                                                                    placeholder="جوال">
                                                                            </div>
                                                                        </div>
                                                                        @if($contact->id ?? false)
                                                                            <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $contact->id }}">
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                        <div class="text-right mt-1">
                                                            <button type="button" class="btn btn-outline-success mr-1 mb-1" onclick="addContactFields()">
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

                <!-- بيانات الحساب -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">بيانات الحساب</h4>
                        </div>
                        <div class="card-content">
                            <div class="card-body">
                                <div class="form-body">
                                    <div class="row">
                                        <!-- رقم المورد -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="number_suply">رقم المورد</label>
                                                <div class="position-relative has-icon-left">
                                                    <input type="text" id="number_suply" class="form-control"
                                                        name="number_suply"
                                                        value="{{ old('number_suply', $supplier->number_suply ?? '') }}"
                                                        readonly
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
                                                {{-- العملة لا تُختار: المحفوظة مع المورد (أو
                                                     الافتراضية من «العملات» إن خلت) تُعرَض للقراءة
                                                     وتُرسَل مخفيّةً — `readonly` لا يعمل على select
                                                     و`disabled` لا يُرسِل. --}}
                                                @php $currencyCode = old('currency', $supplier->currency ?? '') ?: \App\Support\CurrencySettings::code(); @endphp
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
                                                            @selected(old('employee_id', $supplier->employee_id) == $employee->id)>
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
                                                            'value' => old('branch_id', $supplier->branch_id ?? \App\Support\Scopes::resolveBranchId(auth()->user())),
                                                            'showExact' => false,
                                                            'label' => 'المورد تابع لـ',
                                                        ])
                                                    </div>
                                                @else
                                                    <label for="branch_id">المورد تابع لـ</label>
                                                    <input type="text" class="form-control" disabled
                                                        value="{{ \App\Models\Branch::pathLabelFor($supplier->branch_id ?? \App\Support\Scopes::resolveBranchId(auth()->user())) ?? __('branches.node_filter.unassigned') }}">
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
                                                    <input type="email" id="email"
                                                        class="form-control @error('email') is-invalid @enderror"
                                                        name="email"
                                                        value="{{ old('email', $supplier->email ?? '') }}">
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
                                            <div class="form-group">
                                                <label for="notes">الملاحظات</label>
                                                <textarea class="form-control @error('notes') is-invalid @enderror"
                                                    id="notes" name="notes" rows="5"
                                                    style="resize: vertical;">{{ old('notes', $supplier->notes ?? '') }}</textarea>
                                                @error('notes')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- المرفقات -->
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group">
                                                <label for="attachments">المرفقات الجديدة</label>
                                                <input type="file" name="attachments" id="attachments"
                                                    class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                <div class="upload-area border rounded p-4 text-center position-relative"
                                                    onclick="document.getElementById('attachments').click()"
                                                    style="cursor: pointer; border: 2px dashed #007bff !important;">
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                        <i class="fas fa-cloud-upload-alt text-primary fs-2"></i>
                                                        <div>
                                                            <span class="text-primary font-weight-bold">اضغط هنا لاختيار ملف جديد</span>
                                                            <br>
                                                            <small class="text-muted">أو اسحب الملف هنا</small>
                                                        </div>
                                                    </div>
                                                    <div id="file-name" class="mt-2 text-muted small"></div>
                                                </div>

                                                @if(isset($supplier->attachments) && count($supplier->attachments) > 0)
                                                    <div class="mt-3">
                                                        <h6 class="text-primary">الملفات الحالية:</h6>
                                                        <div class="border rounded p-2" style="background-color: #f8f9fa;">
                                                            @foreach($supplier->attachments as $attachment)
                                                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="fa fa-file text-primary me-2"></i>
                                                                        <span>{{ $attachment->filename ?? 'ملف مرفق' }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <a href="{{ $attachment->url ?? '#' }}" target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                                                            <i class="fa fa-eye"></i> عرض
                                                                        </a>
                                                                        <a href="{{ $attachment->download_url ?? '#' }}" class="btn btn-sm btn-outline-success">
                                                                            <i class="fa fa-download"></i> تحميل
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
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
        // متغير عداد جهات الاتصال
        let contactCounter = {{ isset($supplier->contacts) ? count($supplier->contacts) : 0 }};
        let formChanged = false;

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
        document.querySelectorAll('#supplierUpdateForm .form-control').forEach(function (el) {
            el.addEventListener('input', function () {
                el.classList.remove('is-invalid');
                if (el.id === 'trade_name' || el.id === 'first_name' || el.id === 'last_name') {
                    const tn = document.getElementById('trade_name');
                    if (tn) tn.setCustomValidity('');
                }
            });
        });

        // Enter داخل حقلٍ يُرسل النموذجَ أصلياً متجاوزاً الزرَّ — يُحوَّل إلى الزرّ
        document.getElementById('supplierUpdateForm').addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById('updateBtn').click();
        });

        // التعامل مع زر التحديث
        document.getElementById('updateBtn').addEventListener('click', function(e) {
            e.preventDefault();

            // التحقّق من الإلزامي كلِّه — يوجّه إلى الحقل الناقص ولا يسأل شيئاً
            const invalidField = firstInvalidField(document.getElementById('supplierUpdateForm'));
            if (invalidField) {
                focusInvalid(document.getElementById('supplierUpdateForm'), invalidField);
                return;
            }

            // رسالة التأكيد قبل التحديث
            Swal.fire({
                title: 'تأكيد التحديث',
                text: 'هل أنت متأكد من رغبتك في تحديث بيانات المورد؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، حدث البيانات',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // عرض رسالة التحميل
                    Swal.fire({
                        title: 'جاري التحديث...',
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

                    /* `form.submit()` لا يُطلق حدثَ `submit` فلا يبلغ مستمعَه
                       الذي يصفّر `formChanged` — فكان تحذيرُ «مغادرة الصفحة»
                       يظهر بعد حفظٍ ناجح. يُصفَّر هنا قبل الإرسال. */
                    formChanged = false;
                    document.getElementById('supplierUpdateForm').submit();
                }
            });
        });

        // التعامل مع زر الإلغاء
        document.getElementById('cancelBtn').addEventListener('click', function(e) {
            if (formChanged) {
                e.preventDefault();
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'سيتم فقدان جميع التغييرات غير المحفوظة',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، اخرج بدون حفظ',
                    cancelButtonText: 'العودة للتعديل'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formChanged = false;
                        window.location.href = this.href;
                    }
                });
            }
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
                    <h6 class="mb-0 text-primary">جهة اتصال جديدة ${contactCounter}</h6>
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

            // تحديد أن النموذج تم تغييره
            formChanged = true;

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
                    formChanged = true;

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
                formChanged = true;

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

        // تتبع التغييرات في النموذج
        document.addEventListener('DOMContentLoaded', function() {
            console.log('تم تحميل صفحة تعديل المورد بنجاح مع SweetAlert2');

            // تتبع التغييرات في جميع الحقول
            const formInputs = document.querySelectorAll('#supplierUpdateForm input, #supplierUpdateForm select, #supplierUpdateForm textarea');

            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    formChanged = true;
                });

                input.addEventListener('input', function() {
                    formChanged = true;
                });
            });

            // منع فقدان البيانات عند الخروج من الصفحة
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = 'لديك تغييرات غير محفوظة. هل أنت متأكد من رغبتك في مغادرة الصفحة؟';
                }
            });

            // إزالة التحذير عند الإرسال
            const form = document.getElementById('supplierUpdateForm');
            form.addEventListener('submit', function() {
                formChanged = false;
            });

            // إضافة تأثيرات بصرية للحقول المطلوبة
            const requiredFields = document.querySelectorAll('input[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = '#dc3545';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                    } else {
                        this.style.borderColor = '#28a745';
                        this.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
                    }
                });

                field.addEventListener('focus', function() {
                    this.style.borderColor = '#007bff';
                    this.style.boxShadow = '0 0 0 0.2rem rgba(0, 123, 255, 0.25)';
                });
            });

            // تحسين تجربة المستخدم مع الحقول العادية
            const allInputs = document.querySelectorAll('.form-control');
            allInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
        });

        // دالة التحقق من التغييرات
        function hasFormChanged() {
            return formChanged;
        }

        // إظهار رسالة ترحيب عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // يمكن إضافة رسالة ترحيب إضافية هنا إذا لزم الأمر
        });
    </script>

    <style>
        /* ما بقي هنا: ما لا يغطّيه نظام التصميم ولا `supplier-form-style`.
           وحُذف منه ما كان يكرّره أو يخالفه بألوانٍ مثبَّتة — `#007bff`
           و`#f8f9fa` و`#e3e6ef` و`#dee2e6` — فتبقى الشاشة زرقاء في الوضع
           الليلي ومخالِفةً للون النظام الأساسي أيّاً كان. كما حُذفت قواعدُ
           `.btn` و`.card` العامّة: كانت بلا نطاق فتسري على الصفحة كلّها
           وتزاحم ما يضبطه نظام التصميم لهما. */

        .position-relative.focused .form-control-position i { color: var(--app-primary, #7367f0); }

        .is-invalid { border-color: var(--app-danger, #ea5455); }

        .invalid-feedback { display: block; }

        /* مظهر الملفات الحالية */
        .border.rounded.p-2 { border: 1px solid var(--sl-line) !important; }

        .border-bottom.py-2:last-child {
            border-bottom: none !important;
        }

        /* تحسين responsive للموبايل */
        @media (max-width: 768px) {
            .contact-fields-group .row .col-md-6 {
                margin-bottom: 1rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }

            .upload-area {
                padding: 2rem 1rem !important;
            }
        }

        /* تحسين الأنيميشن */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-fields-group {
            animation: fadeIn 0.3s ease;
        }
    </style>
@endsection
