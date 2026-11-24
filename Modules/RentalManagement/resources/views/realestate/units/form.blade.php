@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $unit->exists;
    $pageTitle = $editing ? 'تعديل الوحدة '.$unit->unit_no : 'إضافة وحدة جديدة';
    $hasContract = $editing && $unit->activeContract()->exists();
    $locked = $hasContract;
    $isAuto = (bool) $unit->is_auto;
    $invalid = fn (string $field) => $errors->has($field) ? 'is-invalid' : '';
    $canAddFloor = hasPermission('realestate.buildings.edit');

    // ما يحتاجه السكربت عن كل مبنى: طوابقه (صفوف re_floors) واستخدامه وهل له طوابق أصلاً
    $buildingMeta = $buildings->mapWithKeys(fn ($b) => [$b->id => [
        'name' => $b->name,
        'usage' => $b->usage_type,
        'hasFloors' => $b->hasFloors(),
        'floors' => $b->floors->map(fn ($f) => ['id' => $f->id, 'label' => $f->label, 'number' => (int) $f->number])->values(),
    ]]);
    $typeMeta = collect(V::UNIT_TYPES)->map(fn ($meta) => ['label' => $meta['label'], 'usage' => $meta['usage']]);
    $currentUsage = old('usage_type', $unit->usage_type ?: 'residential');
    $currentType = old('unit_type', $unit->unit_type);
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'plus-circle',
            'sub' => 'رقم الوحدة فريدٌ داخل المبنى · استخدام الوحدة يحدّد نوع عقدها وضريبته',
            'crumbs' => ['الرئيسية' => url('/'), 'الوحدات' => route('realestate.units.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.units.show', $unit->id) : route('realestate.units.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="unitForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ الوحدة</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        @if ($buildings->isEmpty())
            <div class="alert alert-warning">لا مبانٍ متعددة الوحدات في نطاقك — <a href="{{ route('realestate.buildings.create') }}">أضف عمارة أولاً</a>. (الفيلا والأرض والمنشأة المستقلة تُنشأ وحدتها معها تلقائياً.)</div>
        @endif

        @if ($isAuto)
            <div class="alert alert-info">هذه الوحدة أُنشئت تلقائياً مع عقارها الوحيد الوحدة: نوعها ومبناها واستخدامها تتبع العقار وتُغيَّر من «تعديل العقار».</div>
        @endif

        <div class="re-steps" role="tablist">
            @foreach (['المعلومات الأساسية', 'المواصفات', 'المرافق والعدادات', 'الحالة والتسعير', 'الصور والملاحظات'] as $i => $label)
                <button type="button" class="re-steps__item" data-step="{{ $i }}">{{ $label }}</button>
            @endforeach
        </div>

        <form id="unitForm" method="POST" enctype="multipart/form-data" novalidate data-re-validate
              action="{{ $editing ? route('realestate.units.update', $unit->id) : route('realestate.units.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    {{-- 1. الأساسية والموقع --}}
                    <div class="re-step" data-step="0">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="building_id" class="stk-req">المبنى</label>
                                    <select id="building_id" name="building_id" class="form-control select2 {{ $invalid('building_id') }}" data-required="1">
                                        <option value="">اختر المبنى…</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) old('building_id', $unit->building_id) === (string) $building->id)>{{ $building->name }} — {{ $building->code }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6" id="floorWrap">
                                <div class="form-group">
                                    <label for="floor_id">الطابق</label>
                                    <div class="input-group">
                                        <select id="floor_id" name="floor_id" class="form-control {{ $invalid('floor_id') }}" data-selected="{{ old('floor_id', $unit->floor_id) }}">
                                            <option value="">— بلا طابق —</option>
                                        </select>
                                        @if ($canAddFloor)
                                            <button type="button" class="btn btn-outline-secondary" id="addFloor" title="إضافة طابق لهذا المبنى" aria-label="إضافة طابق"><i class="feather icon-plus"></i></button>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block" id="floorHint"></small>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="unit_no" class="stk-req">رقم الوحدة</label>
                                    <input type="text" id="unit_no" name="unit_no" class="form-control {{ $invalid('unit_no') }}" data-required="1" maxlength="50" value="{{ old('unit_no', $unit->unit_no) }}">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="usage_type" class="stk-req">استخدام الوحدة</label>
                                    <select id="usage_type" name="usage_type" class="form-control {{ $invalid('usage_type') }}">
                                        @foreach (V::UNIT_USAGE_TYPES as $value => $meta) <option value="{{ $value }}" @selected($currentUsage === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                    <small class="text-muted d-block" id="usageHint"></small>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="unit_type" class="stk-req">نوع الوحدة</label>
                                    <select id="unit_type" name="unit_type" class="form-control {{ $invalid('unit_type') }}" data-selected="{{ $currentType }}"
                                            @if ($isAuto) style="pointer-events:none;opacity:.65" tabindex="-1" @endif>
                                        @foreach (V::UNIT_TYPES as $value => $meta) <option value="{{ $value }}" @selected($currentType === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                    <small class="text-muted d-block">تُعرض أنواع الاستخدام المختار فقط.</small>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="area_sqm">المساحة (م²)</label>
                                    <input type="number" step="0.01" min="0" id="area_sqm" name="area_sqm" class="form-control {{ $invalid('area_sqm') }}" value="{{ old('area_sqm', $unit->area_sqm) }}">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="orientation">الواجهة / الاتجاه</label>
                                    <select id="orientation" name="orientation" class="form-control">
                                        <option value="">—</option>
                                        @foreach (V::ORIENTATIONS as $value => $meta) <option value="{{ $value }}" @selected(old('orientation', $unit->orientation) === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. المواصفات: تتبدّل بحسب الاستخدام --}}
                    <div class="re-step" data-step="1">
                        <div class="row re-spec-residential">
                            @foreach ([['bedrooms', 'غرف النوم'], ['bathrooms', 'دورات المياه'], ['living_rooms', 'الصالات'], ['kitchens', 'المطابخ']] as [$field, $label])
                                <div class="col-md col-6">
                                    <div class="form-group">
                                        <label for="{{ $field }}">{{ $label }}</label>
                                        <input type="number" min="0" id="{{ $field }}" name="{{ $field }}" class="form-control {{ $invalid($field) }}" value="{{ old($field, $unit->$field ?? 0) }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="row re-spec-commercial">
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="frontage_m">الواجهة (م)</label>
                                    <input type="number" step="0.01" min="0" id="frontage_m" name="frontage_m" class="form-control {{ $invalid('frontage_m') }}" value="{{ old('frontage_m', $unit->frontage_m) }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="ceiling_height_m">ارتفاع السقف (م)</label>
                                    <input type="number" step="0.01" min="0" id="ceiling_height_m" name="ceiling_height_m" class="form-control {{ $invalid('ceiling_height_m') }}" value="{{ old('ceiling_height_m', $unit->ceiling_height_m) }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="openings_count">عدد الفتحات</label>
                                    <input type="number" min="0" id="openings_count" name="openings_count" class="form-control {{ $invalid('openings_count') }}" value="{{ old('openings_count', $unit->openings_count) }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group">
                                    <label for="permitted_activity">النشاط المسموح</label>
                                    <input type="text" id="permitted_activity" name="permitted_activity" class="form-control {{ $invalid('permitted_activity') }}" maxlength="255" placeholder="مثال: مطعم، صيدلية…" value="{{ old('permitted_activity', $unit->permitted_activity) }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="parking_count">المواقف</label>
                                    <input type="number" min="0" id="parking_count" name="parking_count" class="form-control {{ $invalid('parking_count') }}" value="{{ old('parking_count', $unit->parking_count ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6 re-spec-residential">
                                <div class="form-group">
                                    <label for="furnished_status">التأثيث</label>
                                    <select id="furnished_status" name="furnished_status" class="form-control">
                                        @foreach (V::FURNISHED as $value => $meta) <option value="{{ $value }}" @selected(old('furnished_status', $unit->furnished_status) === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="unit_condition" class="stk-req">حالة الوحدة الفنية</label>
                                    <select id="unit_condition" name="unit_condition" class="form-control">
                                        @foreach (V::UNIT_CONDITIONS as $value => $meta) <option value="{{ $value }}" @selected(old('unit_condition', $unit->unit_condition) === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea id="description" name="description" rows="3" class="form-control" maxlength="5000">{{ old('description', $unit->description) }}</textarea>
                        </div>
                    </div>

                    {{-- 3. المرافق والعدادات --}}
                    <div class="re-step" data-step="2">
                        <label class="d-block mb-1">المرافق والخدمات</label>
                        @if ($amenities->isEmpty())
                            <p class="text-muted">لا مرافق معرَّفة —
                                @permission('realestate.settings.edit') <a href="{{ route('realestate.settings.index') }}" target="_blank" rel="noopener">أضفها من الإعدادات</a>. @else اطلب من المدير إضافتها من الإعدادات. @endpermission
                            </p>
                        @else
                            <div class="row mb-1">
                                @foreach ($amenities as $amenity)
                                    <div class="col-md-3 col-6 mb-50">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="amenity_{{ $amenity->id }}" name="amenities[]" value="{{ $amenity->id }}"
                                                   @checked(in_array($amenity->id, (array) old('amenities', $selectedAmenities)))>
                                            <label class="custom-control-label" for="amenity_{{ $amenity->id }}">{{ $amenity->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="electricity_meter">رقم عداد الكهرباء</label>
                                    <input type="text" id="electricity_meter" name="electricity_meter" class="form-control" dir="ltr" maxlength="100" value="{{ old('electricity_meter', $unit->electricity_meter) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="water_meter">رقم عداد المياه</label>
                                    <input type="text" id="water_meter" name="water_meter" class="form-control" dir="ltr" maxlength="100" value="{{ old('water_meter', $unit->water_meter) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. الحالة والتسعير --}}
                    <div class="re-step" data-step="3">
                        @if ($locked)
                            <div class="alert alert-info">على الوحدة عقدٌ سارٍ، فحالة إشغالها واستخدامها يملكهما العقد ولا يتغيّران من هنا.</div>
                        @endif
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="occupancy_status" class="stk-req">حالة الإشغال</label>
                                    <select id="occupancy_status" name="occupancy_status" class="form-control {{ $invalid('occupancy_status') }}" @if ($locked) style="pointer-events:none;opacity:.65" tabindex="-1" @endif>
                                        @foreach (V::OCCUPANCY as $value => $meta)
                                            {{-- «مؤجرة» نتيجة عقدٍ سارٍ، لا خيارٌ يدوي --}}
                                            @continue($value === 'rented' && $unit->occupancy_status !== 'rented')
                                            <option value="{{ $value }}" @selected(old('occupancy_status', $unit->occupancy_status) === $value)>{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @unless ($locked) <small class="text-muted d-block">«مؤجرة» تُكتب تلقائياً عند سريان عقدٍ على الوحدة.</small> @endunless
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="availability_from">تاريخ الإتاحة</label>
                                    <input type="date" id="availability_from" name="availability_from" class="form-control" value="{{ old('availability_from', $unit->availability_from?->format('Y-m-d')) }}">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6 d-flex align-items-center">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" name="is_marketable" value="1" @checked(old('is_marketable', $unit->is_marketable))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">قابلة للتسويق</span>
                                        <span class="stk-switch__hint">نشر عرضٍ لوحدةٍ غير قابلة يُردّ بتحذير</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="base_rent">السعر الإرشادي ({{ \App\Support\CurrencySettings::symbol() }})</label>
                                    <input type="number" step="0.01" min="0" id="base_rent" name="base_rent" class="form-control {{ $invalid('base_rent') }}" value="{{ old('base_rent', $unit->base_rent ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="rent_period" class="stk-req">دورية السعر</label>
                                    <select id="rent_period" name="rent_period" class="form-control">
                                        @foreach (V::RENT_PERIODS as $value => $meta) <option value="{{ $value }}" @selected(old('rent_period', $unit->rent_period) === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 5. الصور والملاحظات --}}
                    <div class="re-step" data-step="4">
                        @include('rentalmanagement::realestate.components.files', ['name' => 'images', 'label' => 'صور الوحدة', 'model' => $unit, 'images' => true])
                        <div class="form-group">
                            <label for="notes">ملاحظات داخلية</label>
                            <textarea id="notes" name="notes" rows="3" class="form-control" maxlength="5000">{{ old('notes', $unit->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="re-step-nav">
                        <button type="button" class="btn btn-outline-secondary" data-step-prev><i class="feather icon-arrow-right"></i> السابق</button>
                        <span>
                            <button type="button" class="btn btn-outline-primary" data-step-next>التالي <i class="feather icon-arrow-left"></i></button>
                            <button type="submit" class="btn btn-primary d-none" data-step-last><i class="feather icon-check"></i> حفظ الوحدة</button>
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.stepper-scripts')
    <script>
        $(function () {
            const buildings = @json($buildingMeta);
            const unitTypes = @json($typeMeta);
            const usageLabels = @json(collect(V::USAGE_TYPES)->map(fn ($m) => $m['label']));
            const floorUrl = @json(route('realestate.buildings.floors.store', '__ID__'));
            const token = @json(csrf_token());
            // وحدة عليها عقد سارٍ، أو وحدة تلقائية: استخدامها لا يتغيّر من هنا
            const usageFrozen = @json($hasContract || $isAuto);
            const typeFrozen = @json($isAuto);

            const $building = $('#building_id'), $floor = $('#floor_id'), $usage = $('#usage_type'), $type = $('#unit_type');
            const current = () => buildings[$building.val()] || null;
            const lock = ($el, on) => $el.css({ 'pointer-events': on ? 'none' : '', opacity: on ? .65 : '' }).attr('tabindex', on ? -1 : null);

            // الطوابق صفوفٌ في المبنى المختار: تُملأ عند كل تغيير، ويُشرح سبب فراغها
            function fillFloors() {
                const building = current();
                const selected = String($floor.data('selected') || '');

                $floor.find('option:not(:first)').remove();
                (building ? building.floors : []).forEach(f => $floor.append(new Option(f.label, f.id, false, String(f.id) === selected)));

                const land = $type.val() === 'land';
                $('#floorWrap').toggle((!building || building.hasFloors) && !land);
                $('#addFloor').prop('disabled', !building);
                $('#floorHint').text(!building ? 'اختر المبنى أولاً لتظهر طوابقه.'
                    : (!building.floors.length ? 'لا طوابق مسجّلة لهذا المبنى' + ($('#addFloor').length ? ' — أضف طابقاً بزرّ «+».' : ' — تُضاف من صفحة المبنى.') : ''));
            }

            // مبنى سكني أو تجاري يقفل استخدام وحداته عليه؛ المختلط يُختار لكل وحدة
            function syncUsage() {
                const building = current();
                const fixed = building && building.usage !== 'mixed';

                if (fixed && !usageFrozen) $usage.val(building.usage);
                lock($usage, fixed || usageFrozen);
                $('#usageHint').text(usageFrozen ? 'لا يتغيّر استخدام هذه الوحدة من هنا.'
                    : (fixed ? 'مقفل: المبنى ' + usageLabels[building.usage] + '، فوحداته كلها كذلك.'
                        : (building ? 'المبنى مختلط: اختر استخدام هذه الوحدة.' : '')));
                fillTypes();
            }

            // قائمة النوع تعرض أنواع الاستخدام المختار فقط
            function fillTypes() {
                const usage = $usage.val();
                const selected = $type.val() || String($type.data('selected') || '');

                if (!typeFrozen) {
                    $type.empty();
                    Object.keys(unitTypes).forEach(function (value) {
                        const meta = unitTypes[value];
                        const offered = value === 'other' ? value === selected : (meta.usage === null || meta.usage === usage);
                        if (offered) $type.append(new Option(meta.label, value, false, value === selected));
                    });
                    if (!$type.val()) $type.prop('selectedIndex', 0);
                }

                swapSpecs();
            }

            // حقول المواصفات تتبدّل: غرف وتأثيث للسكني، واجهة وسقف وفتحات ونشاط للتجاري
            function swapSpecs() {
                const usage = $usage.val(), land = $type.val() === 'land';
                $('.re-spec-residential').toggle(usage === 'residential' && !land);
                $('.re-spec-commercial').toggle(usage === 'commercial');
                fillFloors();
            }

            // select2 يطلق change على العنصر الأصلي، ويُستمع لأحداثه أيضاً احتياطاً
            $building.on('change select2:select select2:clear', function () { $floor.data('selected', ''); syncUsage(); });
            $usage.on('change', function () { $type.data('selected', ''); fillTypes(); });
            $type.on('change', swapSpecs);
            $floor.on('change', function () { $floor.data('selected', $floor.val()); });

            // مبنى بلا طوابق؟ تُضاف من هنا دون مغادرة النموذج
            $('#addFloor').on('click', function () {
                const building = current();
                if (!building) return Swal.fire({ icon: 'info', title: 'اختر المبنى أولاً' });
                if (!building.hasFloors) return Swal.fire({ icon: 'info', title: 'الأرض لا طوابق لها' });

                const next = building.floors.length ? Math.max.apply(null, building.floors.map(f => f.number)) + 1 : 0;

                Swal.fire({
                    title: 'طابق جديد — ' + building.name,
                    html: '<input type="number" id="swFloorNumber" class="swal2-input" placeholder="رقم الطابق (0 = الأرضي)" value="' + next + '">'
                        + '<input type="text" id="swFloorName" class="swal2-input" maxlength="100" placeholder="اسم اختياري (الميزانين، السطح…)">',
                    showCancelButton: true, confirmButtonText: 'إضافة', cancelButtonText: 'تراجع',
                    preConfirm: function () {
                        const number = document.getElementById('swFloorNumber').value;
                        if (number === '' || isNaN(parseInt(number, 10))) return Swal.showValidationMessage('اكتب رقم الطابق');
                        return { number: parseInt(number, 10), name: document.getElementById('swFloorName').value.trim() };
                    },
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({ url: floorUrl.replace('__ID__', $building.val()), method: 'POST', headers: { Accept: 'application/json' },
                        data: { _token: token, number: result.value.number, name: result.value.name } })
                        .done(function (response) {
                            building.floors.push({ id: response.id, label: response.label, number: parseInt(response.number, 10) });
                            building.floors.sort((a, b) => a.number - b.number);
                            $floor.data('selected', response.id);
                            fillFloors();
                        })
                        .fail(function (xhr) {
                            const body = xhr.responseJSON || {};
                            Swal.fire({ icon: 'error', title: 'تعذّرت إضافة الطابق', text: body.errors ? Object.values(body.errors).flat().join('\n') : (body.message || '') });
                        });
                });
            });

            syncUsage();
        });
    </script>
@endsection
