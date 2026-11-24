@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $building->exists;
    $pageTitle = $editing ? 'تعديل: '.$building->name : 'إضافة عقار';
    $autoUnit = $autoUnit ?? new \Modules\RentalManagement\Models\Unit(['rent_period' => 'yearly', 'furnished_status' => 'unfurnished']);
    $ownership = old('ownership', $editing ? ($building->isOwn() ? 'own' : 'managed') : ($building->ownership ?: 'managed'));
    $locateLater = old('locate_later', $editing && ! ($building->latitude && $building->longitude));
    $currencySymbol = \App\Support\CurrencySettings::symbol();
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'plus-circle',
            'sub' => 'النوع يحدّد شكل العقار (وحداته وطوابقه)، والاستخدام يحدّد حكم وحداته (نوع العقد والضريبة)',
            'crumbs' => ['الرئيسية' => url('/'), 'المباني والمجمعات' => route('realestate.buildings.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.buildings.show', $building->id) : route('realestate.buildings.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="buildingForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form id="buildingForm" method="POST" enctype="multipart/form-data" novalidate data-re-validate
              action="{{ $editing ? route('realestate.buildings.update', $building->id) : route('realestate.buildings.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            {{-- تحذير التكرار (الاسم والموقع نفسيهما): الحفظ مرةً ثانية تأكيدٌ صريح --}}
            @if ($errors->has('confirm_duplicate')) <input type="hidden" name="confirm_duplicate" value="1"> @endif

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                    <span class="badge badge-light-secondary stk-num">{{ $editing ? $building->code : $code }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- أول حقلين: النوع والاستخدام — وبقية النموذج يتشكّل حسبهما --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="entity_type" class="stk-req">نوع العقار</label>
                                <select id="entity_type" name="entity_type" class="form-control">
                                    @foreach (V::ENTITY_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('entity_type', $building->entity_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                                <small class="text-muted d-block" id="entityHint"></small>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="usage_type" class="stk-req">الاستخدام</label>
                                <select id="usage_type" name="usage_type" class="form-control">
                                    @foreach (V::USAGE_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('usage_type', $building->usage_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                                <small class="text-muted d-block" id="usageHint"></small>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 re-standalone">
                            <div class="form-group">
                                <label for="unit_unit_type" class="stk-req">نوع المنشأة</label>
                                <select id="unit_unit_type" name="unit[unit_type]" class="form-control">
                                    @foreach (V::STANDALONE_UNIT_TYPES as $value) <option value="{{ $value }}" @selected(old('unit.unit_type', $autoUnit->unit_type) === $value)>{{ V::label(V::UNIT_TYPES, $value) }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name_ar" class="stk-req">الاسم بالعربية</label>
                                <input type="text" id="name_ar" name="name_ar" class="form-control" data-required="1" value="{{ old('name_ar', $building->name_ar) }}" maxlength="191">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name_en">الاسم بالإنجليزية</label>
                                <input type="text" id="name_en" name="name_en" class="form-control" dir="ltr" value="{{ old('name_en', $building->name_en) }}" maxlength="191">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status" class="stk-req">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    @foreach (V::BUILDING_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(old('status', $building->status) === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="complex_id">يتبع مجمعاً</label>
                                <div class="input-group">
                                    <select id="complex_id" name="complex_id" class="form-control">
                                        <option value="">— مبنى مستقل —</option>
                                        @foreach ($complexes as $complex) <option value="{{ $complex->id }}" @selected((string) old('complex_id', $building->complex_id) === (string) $complex->id)>{{ $complex->name_ar }}</option> @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="addComplex" title="مجمع جديد"><i class="feather icon-plus"></i></button>
                                </div>
                                <small class="text-muted"><a href="{{ route('realestate.buildings.complexes.index') }}" target="_blank" rel="noopener">إدارة المجمعات</a></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-users"></i> الملكية والإدارة</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ownership" class="stk-req">ملكية المبنى</label>
                                <select id="ownership" name="ownership" class="form-control">
                                    @foreach (V::OWNERSHIP as $value => $meta) <option value="{{ $value }}" @selected($ownership === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                                <small class="text-muted d-block" id="ownershipHint"></small>
                            </div>
                        </div>
                        <div class="col-md-6" id="ownerWrap">
                            <div class="form-group">
                                <label for="owner_id" class="stk-req">المالك</label>
                                @include('rentalmanagement::realestate.components.owner-select', ['name' => 'owner_id', 'owners' => $owners, 'selected' => $building->owner_id, 'placeholder' => 'اختر المالك…'])
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="manager_user_id">مدير العقار</label>
                                <select id="manager_user_id" name="manager_user_id" class="form-control select2">
                                    <option value="">اختر مستخدماً…</option>
                                    @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) old('manager_user_id', $building->manager_user_id) === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="default_treasury_account_id">الخزينة الافتراضية للمبنى</label>
                                <select id="default_treasury_account_id" name="default_treasury_account_id" class="form-control select2">
                                    <option value="">— بلا (تُختار يدوياً عند كل دفعة) —</option>
                                    @foreach ($treasuries as $treasury) <option value="{{ $treasury->id }}" @selected((string) old('default_treasury_account_id', $building->default_treasury_account_id) === (string) $treasury->id)>{{ $treasury->name }}</option> @endforeach
                                </select>
                                <small class="text-muted">تُقترح تلقائياً عند تسجيل دفعةٍ لأي وحدةٍ في هذا المبنى، وتبقى قابلةً للتغيير.</small>
                            </div>
                        </div>
                        @php
                            $canCreateWarehouse = hasPermission('stock.warehouses.create');
                            // السؤال اختياري فعلاً (التحقق لا يفرضه): الافتراضي «لا» ولا نجمة إلزام
                            $warehouseMode = old('warehouse_mode', $building->store_house_id ? 'existing' : 'none');
                        @endphp
                        <div class="col-12">
                            <div class="form-group mb-1">
                                <label class="d-block">هل تريد مستودعاً لهذا المبنى؟</label>
                                <div class="d-flex flex-wrap" style="gap: .5rem 1.5rem">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="warehouse_mode_none" name="warehouse_mode" value="none" class="custom-control-input" @checked($warehouseMode === 'none')>
                                        <label class="custom-control-label" for="warehouse_mode_none">لا</label>
                                    </div>
                                    @if ($canCreateWarehouse && ! $building->store_house_id)
                                        <div class="custom-control custom-radio">
                                            <input type="radio" id="warehouse_mode_new" name="warehouse_mode" value="new" class="custom-control-input" @checked($warehouseMode === 'new')>
                                            <label class="custom-control-label" for="warehouse_mode_new">نعم — أنشئ مستودعاً باسم المبنى في قسم المستودعات</label>
                                        </div>
                                    @endif
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="warehouse_mode_existing" name="warehouse_mode" value="existing" class="custom-control-input" @checked($warehouseMode === 'existing')>
                                        <label class="custom-control-label" for="warehouse_mode_existing">نعم — اربطه بمستودعٍ موجود</label>
                                    </div>
                                </div>
                                <div class="text-muted small mt-50" id="warehouseNewHint" style="display: none">
                                    يُنشأ عند الحفظ مستودعٌ نشط باسم المبنى مع حسابه المحاسبي تحت حساب «المخزون»، ويُربط بالمبنى تلقائياً.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="warehouseExistingWrap" style="display: none">
                            <div class="form-group">
                                <label for="store_house_id">مستودع المبنى</label>
                                <select id="store_house_id" name="store_house_id" class="form-control select2" style="width: 100%">
                                    <option value="">اختر المستودع…</option>
                                    @foreach ($storeHouses as $storeHouse) <option value="{{ $storeHouse->id }}" @selected((string) old('store_house_id', $building->store_house_id) === (string) $storeHouse->id)>{{ $storeHouse->name }}</option> @endforeach
                                </select>
                                <small class="text-muted">من مستودعات المخزون — تُحفظ فيه قطع الصيانة ومواد المبنى.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> الموقع والمواصفات</h4></div>
                <div class="card-body">
                    <div class="row">
                        @foreach ([['country', 'الدولة'], ['city', 'المدينة'], ['district', 'الحي']] as [$field, $label])
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    <input type="text" id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field, $building->$field) }}" maxlength="100">
                                </div>
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="address">العنوان</label>
                                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $building->address) }}" maxlength="255">
                            </div>
                        </div>
                        {{-- الموقع يُختار من الخريطة: نقرةٌ أو سحبُ المؤشّر أو بحثٌ بالاسم، والإحداثيات تُكتب وحدها --}}
                        <div class="col-12">
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox" class="custom-control-input" id="locate_later" name="locate_later" value="1" @checked($locateLater)>
                                <label class="custom-control-label" for="locate_later">تحديد الموقع لاحقاً <small class="text-muted">(يُحفظ العقار بلا إحداثيات؛ والأرض موقعها اختياري دائماً)</small></label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="d-block" id="mapLabel" for="mapSearch">موقع العقار على الخريطة</label>
                                <div class="d-flex flex-wrap mb-50" style="gap:.5rem">
                                    <input type="text" id="mapSearch" class="form-control" style="flex:1 1 260px" placeholder="ابحث عن حيّ أو شارع أو معلم…" autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary" id="mapLocate"><i class="feather icon-crosshair"></i> موقعي الحالي</button>
                                </div>
                                <div id="buildingMap" style="height:360px;border-radius:.5rem;border:1px solid rgba(0,0,0,.1)"></div>
                                <small class="text-muted d-block mt-50" id="mapHint">انقر على الخريطة لتحديد موقع العقار، أو اسحب المؤشّر لضبطه.</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="latitude" class="re-loc-label">خط العرض</label>
                                <input type="number" step="any" id="latitude" name="latitude" class="form-control" dir="ltr" value="{{ old('latitude', $building->latitude) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="longitude" class="re-loc-label">خط الطول</label>
                                <input type="number" step="any" id="longitude" name="longitude" class="form-control" dir="ltr" value="{{ old('longitude', $building->longitude) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="floors_count">عدد الطوابق</label>
                                <input type="number" min="0" max="200" id="floors_count" name="floors_count" class="form-control" value="{{ old('floors_count', $building->floors_count ?? 0) }}">
                                <small class="text-muted">{{ $editing ? 'رفع العدد يُنشئ الطوابق الناقصة، وإنقاصه يحذف أعلى الطوابق الخالية من الوحدات.' : 'تُنشأ الطوابق تلقائياً من الأرضي، وهي ما يظهر عند إضافة وحدة.' }}</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-multi">
                            <div class="form-group">
                                <label for="expected_units_count">عدد الوحدات المتوقع</label>
                                <input type="number" min="0" id="expected_units_count" name="expected_units_count" class="form-control" value="{{ old('expected_units_count', $building->expected_units_count) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="total_area">المساحة الكلية (م²)</label>
                                <input type="number" step="0.01" min="0" id="total_area" name="total_area" class="form-control" value="{{ old('total_area', $building->total_area) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="construction_year_hijri">سنة البناء (هجري)</label>
                                <input type="number" min="1200" max="1600" id="construction_year_hijri" name="construction_year_hijri" class="form-control" placeholder="مثال: 1440" value="{{ old('construction_year_hijri', $building->construction_year_hijri) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="construction_year">سنة البناء (ميلادي)</label>
                                <input type="number" min="1800" max="{{ date('Y') + 5 }}" id="construction_year" name="construction_year" class="form-control" placeholder="مثال: 2019" value="{{ old('construction_year', $building->construction_year) }}">
                                <small class="text-muted">أدخل أحد التقويمين ويُحسب الآخر تقريبياً، ويمكنك تصحيحه.</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="description">الوصف</label>
                                <textarea id="description" name="description" rows="3" class="form-control" maxlength="5000">{{ old('description', $building->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> الصك والرخص <span class="re-land">والمخطط</span></h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="deed_no">رقم الصك</label>
                                <input type="text" id="deed_no" name="deed_no" class="form-control" dir="ltr" maxlength="100" value="{{ old('deed_no', $building->deed_no) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="deed_date">تاريخ الصك</label>
                                <input type="date" id="deed_date" name="deed_date" class="form-control" value="{{ old('deed_date', $building->deed_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-land">
                            <div class="form-group">
                                <label for="plan_no">رقم المخطط</label>
                                <input type="text" id="plan_no" name="plan_no" class="form-control" dir="ltr" maxlength="100" value="{{ old('plan_no', $building->plan_no) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-land">
                            <div class="form-group">
                                <label for="plot_no">رقم القطعة</label>
                                <input type="text" id="plot_no" name="plot_no" class="form-control" dir="ltr" maxlength="100" value="{{ old('plot_no', $building->plot_no) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-land">
                            <div class="form-group">
                                <label for="street_width">عرض الشارع (م)</label>
                                <input type="number" step="0.01" min="0" id="street_width" name="street_width" class="form-control" value="{{ old('street_width', $building->street_width) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="building_permit_no">رقم رخصة البناء</label>
                                <input type="text" id="building_permit_no" name="building_permit_no" class="form-control" dir="ltr" maxlength="100" value="{{ old('building_permit_no', $building->building_permit_no) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="municipal_license_no">رقم رخصة البلدية</label>
                                <input type="text" id="municipal_license_no" name="municipal_license_no" class="form-control" dir="ltr" maxlength="100" value="{{ old('municipal_license_no', $building->municipal_license_no) }}">
                            </div>
                        </div>
                        <div class="col-12 re-land">
                            <div class="form-group">
                                <label for="boundaries">الحدود والأطوال</label>
                                <textarea id="boundaries" name="boundaries" rows="2" class="form-control" maxlength="2000" placeholder="شمالاً: شارع عرض 20م بطول 30م · جنوباً: …">{{ old('boundaries', $building->boundaries) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- عقارٌ وحيد الوحدة (فيلا، أرض، منشأة مستقلة): وحدته تُنشأ معه وتُحرَّر من هنا --}}
            <div class="card re-single">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-grid"></i> بيانات الوحدة</h4>
                    <span class="badge badge-light-info">تُنشأ وحدة واحدة تلقائياً مع العقار</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="unit_area_sqm">المساحة المؤجَّرة (م²)</label>
                                <input type="number" step="0.01" min="0" id="unit_area_sqm" name="unit[area_sqm]" class="form-control" value="{{ old('unit.area_sqm', $autoUnit->area_sqm) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="unit_base_rent">السعر الإرشادي ({{ $currencySymbol }})</label>
                                <input type="number" step="0.01" min="0" id="unit_base_rent" name="unit[base_rent]" class="form-control" value="{{ old('unit.base_rent', $autoUnit->base_rent) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="unit_rent_period">دورية السعر</label>
                                <select id="unit_rent_period" name="unit[rent_period]" class="form-control">
                                    @foreach (V::RENT_PERIODS as $value => $meta) <option value="{{ $value }}" @selected(old('unit.rent_period', $autoUnit->rent_period ?: 'yearly') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row re-unit-residential">
                        @foreach ([['bedrooms', 'غرف النوم'], ['bathrooms', 'دورات المياه'], ['living_rooms', 'الصالات'], ['kitchens', 'المطابخ']] as [$field, $label])
                            <div class="col-md col-6">
                                <div class="form-group">
                                    <label for="unit_{{ $field }}">{{ $label }}</label>
                                    <input type="number" min="0" id="unit_{{ $field }}" name="unit[{{ $field }}]" class="form-control" value="{{ old('unit.'.$field, $autoUnit->$field ?? 0) }}">
                                </div>
                            </div>
                        @endforeach
                        <div class="col-md col-6">
                            <div class="form-group">
                                <label for="unit_furnished_status">التأثيث</label>
                                <select id="unit_furnished_status" name="unit[furnished_status]" class="form-control">
                                    @foreach (V::FURNISHED as $value => $meta) <option value="{{ $value }}" @selected(old('unit.furnished_status', $autoUnit->furnished_status ?: 'unfurnished') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row re-unit-commercial">
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="unit_frontage_m">الواجهة (م)</label>
                                <input type="number" step="0.01" min="0" id="unit_frontage_m" name="unit[frontage_m]" class="form-control" value="{{ old('unit.frontage_m', $autoUnit->frontage_m) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="unit_ceiling_height_m">ارتفاع السقف (م)</label>
                                <input type="number" step="0.01" min="0" id="unit_ceiling_height_m" name="unit[ceiling_height_m]" class="form-control" value="{{ old('unit.ceiling_height_m', $autoUnit->ceiling_height_m) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 re-not-land">
                            <div class="form-group">
                                <label for="unit_openings_count">عدد الفتحات</label>
                                <input type="number" min="0" id="unit_openings_count" name="unit[openings_count]" class="form-control" value="{{ old('unit.openings_count', $autoUnit->openings_count) }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label for="unit_permitted_activity">النشاط المسموح</label>
                                <input type="text" id="unit_permitted_activity" name="unit[permitted_activity]" class="form-control" maxlength="255" value="{{ old('unit.permitted_activity', $autoUnit->permitted_activity) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-image"></i> الصور والمستندات</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">@include('rentalmanagement::realestate.components.files', ['name' => 'images', 'label' => 'صور العقار', 'model' => $building, 'images' => true])</div>
                        <div class="col-md-6">@include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'مستندات (صكّ الملكية، الرخص…)', 'model' => $building])</div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.map-provider')
    <script>
        // اختيار الموقع من الخريطة — وإن تعذّر تحميلها بقي الحقلان يُكتبان يدوياً
        reMap.init(function (api) {
            const $lat = $('#latitude'), $lng = $('#longitude');
            const saved = $lat.val() && $lng.val() ? { lat: parseFloat($lat.val()), lng: parseFloat($lng.val()) } : null;
            const map = api.map(document.getElementById('buildingMap'), { center: saved || { lat: 24.7136, lng: 46.6753 }, zoom: saved ? 16 : 6 });
            const marker = map.marker(saved, { draggable: true });

            function place(point, pan) {
                marker.setPosition(point);
                $lat.val(point.lat.toFixed(7)).removeClass('is-invalid');
                $lng.val(point.lng.toFixed(7)).removeClass('is-invalid');
                if (pan) map.panTo(point, 16);

                // العنوان يُقترح من الموقع — ولا يُكتب فوق ما أدخله المستخدم
                api.geocode(point, function (suggest) {
                    Object.keys(suggest).forEach(function (field) {
                        const $field = $('#' + field);
                        if (suggest[field] && (!$field.val().trim() || $field.data('auto'))) $field.val(suggest[field]).data('auto', true);
                    });
                });
            }

            $('#country, #city, #district, #address').off('.remap').on('input.remap', function () { $(this).data('auto', false); });

            map.onClick(p => place(p));
            marker.onDragEnd(p => place(p));
            api.search(document.getElementById('mapSearch'), p => place(p, true));

            // كتابة الإحداثيات يدوياً تحرّك المؤشّر
            $lat.add($lng).off('.remap').on('change.remap', function () {
                const point = { lat: parseFloat($lat.val()), lng: parseFloat($lng.val()) };
                if (isNaN(point.lat) || isNaN(point.lng)) return;
                marker.setPosition(point);
                map.panTo(point, 16);
            });

            // Enter في حقل البحث لا يُرسل النموذج
            $('#mapSearch').on('keydown.remap', e => { if (e.key === 'Enter') e.preventDefault(); });

            $('#mapLocate').off('.remap').on('click.remap', function () {
                if (!navigator.geolocation) return Swal.fire({ icon: 'info', title: 'المتصفّح لا يدعم تحديد الموقع' });
                navigator.geolocation.getCurrentPosition(
                    p => place({ lat: p.coords.latitude, lng: p.coords.longitude }, true),
                    () => Swal.fire({ icon: 'warning', title: 'تعذّر تحديد موقعك', text: 'اسمح للمتصفّح بالوصول إلى الموقع أو حدّده على الخريطة.' }));
            });
        }, function () {
            $('#buildingMap, #mapSearch, #mapLocate').hide();
            $('#mapHint').addClass('text-danger').text('تعذّر تحميل الخريطة — اكتب خطّ العرض وخطّ الطول يدوياً.');
        });

        // سنة البناء: تقويمان متزامنان. لا يُكتب فوق قيمةٍ أدخلها المستخدم بيده في الحقل الآخر.
        (function () {
            const $h = $('#construction_year_hijri'), $g = $('#construction_year');
            const toHijri = g => Math.round((g - 622) * 33 / 32);
            const toGregorian = h => Math.round(h * 32 / 33 + 622);

            function sync($from, $to, convert, min, max) {
                $from.on('input', function () {
                    const value = parseInt(this.value, 10);
                    if ($to.val() && !$to.data('auto')) return;
                    if (!value || value < min || value > max) return $to.data('auto') ? $to.val('') : null;
                    $to.val(convert(value)).data('auto', true);
                });
                $to.on('input', function () { $(this).data('auto', false); });
            }

            sync($g, $h, toHijri, 1800, 2200);
            sync($h, $g, toGregorian, 1200, 1600);
        })();

        // النموذج يتشكّل حسب النوع والاستخدام: الأرض بلا طوابق ولا سنة بناء وتُظهر المخطط،
        // والعقار وحيد الوحدة (فيلا، أرض، منشأة مستقلة) يُظهر حقول وحدته ولا يكون مختلطاً
        (function () {
            const single = @json(V::SINGLE_UNIT_ENTITY_TYPES);
            const hints = {
                building: 'وحدات متعددة: تُنشأ الطوابق عند الحفظ ثم تُضاف الوحدات.',
                villa: 'وحدة واحدة تُنشأ تلقائياً بنوع «فيلا».',
                land: 'وحدة واحدة تُنشأ تلقائياً بنوع «أرض»، بلا طوابق.',
                standalone: 'مستودع أو معرض أو ورشة: وحدة واحدة تُنشأ تلقائياً بنوعها.',
            };
            const $type = $('#entity_type'), $usage = $('#usage_type'), $ownership = $('#ownership'), $later = $('#locate_later');

            function reshape() {
                const type = $type.val(), isLand = type === 'land', isSingle = single.indexOf(type) !== -1;

                // وحيد الوحدة لا يكون مختلطاً
                $usage.find('option[value="mixed"]').prop('disabled', isSingle).prop('hidden', isSingle);
                if (isSingle && $usage.val() === 'mixed') $usage.val('residential');

                const usage = $usage.val();

                $('.re-land').toggle(isLand);
                $('.re-not-land').toggle(!isLand);
                $('.re-multi').toggle(!isSingle);
                $('.re-single').toggle(isSingle);
                $('.re-standalone').toggle(type === 'standalone');
                $('.re-unit-residential').toggle(isSingle && !isLand && usage === 'residential');
                $('.re-unit-commercial').toggle(isSingle && usage === 'commercial');
                if (isSingle && isLand) $('.re-unit-commercial .re-not-land').hide();

                $('#entityHint').text(hints[type] || '');
                $('#usageHint').text(isSingle ? 'استخدام العقار هو استخدام وحدته: يحدّد نوع العقد والضريبة.'
                    : (usage === 'mixed' ? 'مختلط: يُختار استخدام كل وحدة عند إضافتها.' : 'وحدات المبنى كلها تأخذ هذا الاستخدام.'));

                // الموقع اختياري للأرض ولمن اختار «تحديد الموقع لاحقاً»
                const optional = isLand || $later.is(':checked');
                $('#latitude, #longitude').attr('data-required', optional ? null : '1').removeClass(optional ? 'is-invalid' : '');
                $('.re-loc-label, #mapLabel').toggleClass('stk-req', !optional);
            }

            // مملوك للمنشأة: لا مالك. لمالكٍ خارجي: المالك إلزامي
            function syncOwnership() {
                const own = $ownership.val() === 'own';
                $('#ownerWrap').toggle(!own);
                $('#owner_id').attr('data-required', own ? null : '1');
                if (own) $('#owner_id').val('').trigger('change');
                $('#ownershipHint').text(own ? 'إيجار وحداته إيرادٌ للمنشأة نفسها، ولا عمولة إدارة.' : 'الإيجار يُقيَّد لحساب المالك، وللمكتب عمولة الإدارة.');
            }

            $type.add($usage).add($later).on('change', reshape);
            $ownership.on('change', syncOwnership);
            reshape();
            syncOwnership();
        })();

        // سؤال المستودع: «جديد» يُظهر التنبيه، و«موجود» يُظهر القائمة
        function syncWarehouseMode() {
            const mode = $('input[name=warehouse_mode]:checked').val();
            $('#warehouseExistingWrap').toggle(mode === 'existing');
            $('#warehouseNewHint').toggle(mode === 'new');
        }
        $('input[name=warehouse_mode]').on('change', syncWarehouseMode);
        syncWarehouseMode();

        $('#addComplex').on('click', function () {
            Swal.fire({ title: 'مجمع جديد', input: 'text', inputLabel: 'اسم المجمع', showCancelButton: true, confirmButtonText: 'إضافة', cancelButtonText: 'تراجع',
                inputValidator: v => (!v || !v.trim()) ? 'اكتب اسم المجمع' : undefined })
                .then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({ url: @json(route('realestate.buildings.complexes.store')), method: 'POST', headers: { Accept: 'application/json' },
                        data: { _token: @json(csrf_token()), name_ar: result.value, city: $('#city').val(), usage_type: $('#usage_type').val() } })
                        .done(r => $('#complex_id').append(new Option(r.name, r.id, true, true)))
                        .fail(x => Swal.fire({ icon: 'error', title: 'تعذّرت الإضافة', text: (x.responseJSON || {}).message || '' }));
                });
        });
    </script>
@endsection
