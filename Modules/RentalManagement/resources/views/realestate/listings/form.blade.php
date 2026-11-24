@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $listing->exists;
    $kind = old('listing_kind', $listing->listing_kind ?: 'offer');
    $kindWord = $kind === 'request' ? 'الطلب' : 'العرض';
    $pageTitle = $editing ? 'تعديل '.$kindWord.': '.$listing->title : ($kind === 'request' ? 'طلب عقاري جديد' : 'عرض عقاري جديد');
    // المميزات تُحفظ قائمةً وتُحرَّر سطراً لكلّ ميزة
    $featuresText = old('features', is_array($listing->features) ? implode("\n", $listing->features) : (string) $listing->features);
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'plus-circle',
            'sub' => 'يُحفظ مسودةً ثم يُفعَّل من صفحته حين يكتمل — الوحدة اختيارية ويمكن إدخال تفاصيل خارجية',
            'crumbs' => ['الرئيسية' => url('/'), 'العروض العقارية' => route('realestate.listings.index'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.listings.show', $listing->id) : route('realestate.listings.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="listingForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form id="listingForm" method="POST" enctype="multipart/form-data" novalidate data-re-validate
              action="{{ $editing ? route('realestate.listings.update', $listing->id) : route('realestate.listings.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-home"></i> المعروض</h4>
                            <span class="badge badge-light-secondary stk-num">{{ $editing ? $listing->code : $code }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>النوع</label>
                                        {{-- هذا النموذج للعروض؛ طلبات العملاء تُسجَّل من شاشتها --}}
                                        <input type="hidden" id="listing_kind" name="listing_kind" value="offer">
                                        <input type="text" class="form-control" value="عرض عقاري" readonly>
                                        <small class="text-muted">عميلٌ يبحث عن عقار؟ سجّله من <a href="{{ route('realestate.listings.create', ['kind' => 'request']) }}">طلبات العملاء</a>.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="unit_id">الوحدة (اختيارية)</label>
                                        <select id="unit_id" name="unit_id" class="form-control select2">
                                            <option value="">اختر الوحدة…</option>
                                            @foreach ($units as $unit) <option value="{{ $unit->id }}" data-building="{{ $unit->building_id }}" @selected((string) old('unit_id', $listing->unit_id) === (string) $unit->id)>{{ $unit->full_label }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                {{-- العقار يُشتقّ من الوحدة إن اختيرت؛ ويُختار وحده لعرض العقار كاملاً --}}
                                <div class="col-md-4" id="buildingField">
                                    <div class="form-group">
                                        <label for="building_id">أو العقار كاملاً</label>
                                        <select id="building_id" name="building_id" class="form-control select2">
                                            <option value="">اختر العقار…</option>
                                            @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) old('building_id', $listing->building_id) === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                        </select>
                                        <small class="text-muted">اتركهما فارغين لعرضٍ خارجي.</small>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="title" class="stk-req">العنوان</label>
                                        <input type="text" id="title" name="title" class="form-control" data-required="1" value="{{ old('title', $listing->title) }}" maxlength="191">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="listing_type" class="stk-req">للإيجار / للبيع</label>
                                        <select id="listing_type" name="listing_type" class="form-control">
                                            @foreach (V::LISTING_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('listing_type', $listing->listing_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- تفاصيل خارجية: تظهر حين لا وحدة مسجّلة، أو دائماً للطلب --}}
                    <div class="card" id="externalCard">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> <span id="externalTitle">تفاصيل العقار (خارجي)</span></h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="property_type">نوع العقار</label>
                                        <select id="property_type" name="property_type" class="form-control">
                                            <option value="">—</option>
                                            @foreach (V::UNIT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('property_type', $listing->property_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6"><div class="form-group"><label for="city">المدينة</label>
                                    <input type="text" id="city" name="city" class="form-control" maxlength="100" value="{{ old('city', $listing->city) }}"></div></div>
                                <div class="col-md-4 col-6"><div class="form-group"><label for="district">الحي / المنطقة</label>
                                    <input type="text" id="district" name="district" class="form-control" maxlength="100" value="{{ old('district', $listing->district) }}"></div></div>
                                <div class="col-md-6"><div class="form-group"><label for="address">العنوان</label>
                                    <input type="text" id="address" name="address" class="form-control" maxlength="255" value="{{ old('address', $listing->address) }}"></div></div>
                                <div class="col-md-2 col-4"><div class="form-group"><label for="area_sqm">المساحة م²</label>
                                    <input type="number" step="0.01" min="0" id="area_sqm" name="area_sqm" class="form-control" value="{{ old('area_sqm', $listing->area_sqm) }}"></div></div>
                                <div class="col-md-2 col-4"><div class="form-group"><label for="bedrooms">غرف النوم</label>
                                    <input type="number" min="0" id="bedrooms" name="bedrooms" class="form-control" value="{{ old('bedrooms', $listing->bedrooms) }}"></div></div>
                                <div class="col-md-2 col-4"><div class="form-group"><label for="bathrooms">دورات المياه</label>
                                    <input type="number" min="0" id="bathrooms" name="bathrooms" class="form-control" value="{{ old('bathrooms', $listing->bathrooms) }}"></div></div>
                                <div class="col-md-6"><div class="form-group"><label for="contact_name" id="contactNameLabel">اسم صاحب العرض / مقدّم الطلب</label>
                                    <input type="text" id="contact_name" name="contact_name" class="form-control" maxlength="191" value="{{ old('contact_name', $listing->contact_name) }}"></div></div>
                                <div class="col-md-6"><div class="form-group"><label for="contact_phone">الهاتف</label>
                                    <input type="text" id="contact_phone" name="contact_phone" class="form-control" dir="ltr" maxlength="40" value="{{ old('contact_phone', $listing->contact_phone) }}"></div></div>
                                <div class="col-12"><div class="form-group"><label for="client_id">عميل من الملف المركزي (اختياري)</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => $listing->client_id, 'placeholder' => 'بلا ربط بعميل…'])</div></div>
                                <div class="col-md-6 requestOnly"><div class="form-group"><label for="budget_min">أدنى ميزانية</label>
                                    <input type="number" step="0.01" min="0" id="budget_min" name="budget_min" class="form-control" value="{{ old('budget_min', $listing->budget_min) }}"></div></div>
                                <div class="col-md-6 requestOnly"><div class="form-group"><label for="budget_max">أعلى ميزانية</label>
                                    <input type="number" step="0.01" min="0" id="budget_max" name="budget_max" class="form-control" value="{{ old('budget_max', $listing->budget_max) }}"></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-dollar-sign"></i> السعر والعمولة</h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-6">
                                    <div class="form-group">
                                        <label for="asking_price" id="askingLabel" class="stk-req">السعر المطلوب</label>
                                        <input type="number" step="0.01" min="0" id="asking_price" name="asking_price" class="form-control" data-required="1" value="{{ old('asking_price', $listing->asking_price) }}">
                                    </div>
                                </div>
                                <div class="col-md-3 col-6" id="rentPeriodField">
                                    <div class="form-group">
                                        <label for="rent_period" class="stk-req">فترة الإيجار</label>
                                        <select id="rent_period" name="rent_period" class="form-control">
                                            @foreach (V::RENT_PERIODS as $value => $meta) <option value="{{ $value }}" @selected(old('rent_period', $listing->rent_period ?: 'yearly') === $value)>{{ $meta['label'] }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="form-group">
                                        <label for="commission_type">نوع العمولة</label>
                                        <select id="commission_type" name="commission_type" class="form-control">
                                            <option value="">بلا عمولة</option>
                                            @foreach (V::COMMISSION_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('commission_type', $listing->commission_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="form-group">
                                        <label for="commission_value">قيمة العمولة</label>
                                        <input type="number" step="0.01" min="0" id="commission_value" name="commission_value" class="form-control" value="{{ old('commission_value', $listing->commission_value) }}"
                                               @if (old('commission_type', $listing->commission_type) === 'percent') max="100" @endif>
                                        <small class="text-muted" id="commissionHint">النسبة لا تتجاوز 100%.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> النشر والمسؤول</h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="publish_date">تاريخ النشر</label>
                                        <input type="date" id="publish_date" name="publish_date" class="form-control" value="{{ old('publish_date', $listing->publish_date?->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="expiry_date">انتهاء الصلاحية</label>
                                        <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="{{ old('expiry_date', $listing->expiry_date?->format('Y-m-d')) }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="assigned_agent_id">المسوّق المسؤول</label>
                                        <select id="assigned_agent_id" name="assigned_agent_id" class="form-control select2">
                                            <option value="">اختر مستخدماً…</option>
                                            @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) old('assigned_agent_id', $listing->assigned_agent_id) === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-align-right"></i> الوصف والمميزات</h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label for="description">الوصف</label>
                                        <textarea id="description" name="description" rows="6" class="form-control" maxlength="10000">{{ old('description', $listing->description) }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="features">المميزات</label>
                                        <textarea id="features" name="features" rows="6" class="form-control" maxlength="2000" placeholder="إطلالة بحرية&#10;موقف خاص&#10;قريبة من المترو">{{ $featuresText }}</textarea>
                                        <small class="text-muted">ميزة واحدة في كل سطر.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-image"></i> الصور</h4></div>
                        <div class="card-body">
                            @include('rentalmanagement::realestate.components.files', ['name' => 'images', 'label' => 'الصور (الأولى غلاف العرض)', 'model' => $listing, 'images' => true])
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card re-summary">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-grid"></i> ملخّص الوحدة</h4></div>
                        <div class="card-body">
                            <p class="text-muted mb-0" id="unitSummaryEmpty">اختر وحدة لعرض حالتها وإيجارها الإرشادي.</p>
                            <dl class="stk-def d-none" id="unitSummary">
                                <div class="stk-def__row"><dt>الوحدة</dt><dd data-info="label">—</dd></div>
                                <div class="stk-def__row"><dt>الإشغال</dt><dd><span class="badge" data-info="occupancy">—</span></dd></div>
                                <div class="stk-def__row"><dt>الإيجار الإرشادي</dt><dd class="re-num" data-info="base_rent">—</dd></div>
                                <div class="stk-def__row"><dt>المساحة</dt><dd class="stk-num" data-info="area">—</dd></div>
                                <div class="stk-def__row"><dt>المالك</dt><dd data-info="owner_name">—</dd></div>
                                <div class="stk-def__row"><dt>قابلة للتسويق</dt><dd><span class="badge" data-info="is_marketable">—</span></dd></div>
                            </dl>
                            <div class="alert alert-warning mt-1 mb-0 d-none" id="unitSummaryWarning" role="alert"></div>

                            {{-- تحويل الوحدة إلى عرض: يملأ العنوان والسعر والوصف والمميزات من بيانات الوحدة --}}
                            <div class="d-none mt-1" id="convertUnitBox">
                                <button type="button" class="btn btn-outline-primary btn-block" id="convertUnit">
                                    <i class="feather icon-repeat"></i> تحويل الوحدة إلى عرض عقاري
                                </button>
                                <div class="custom-control custom-checkbox mt-1 d-none" id="copyUnitImagesWrap">
                                    <input type="checkbox" class="custom-control-input" id="copy_unit_images" name="copy_unit_images" value="1" @checked(old('copy_unit_images'))>
                                    <label class="custom-control-label" for="copy_unit_images">استعمل صور الوحدة في العرض (<span id="unitImagesCount" class="stk-num">0</span>)</label>
                                </div>
                                <small class="text-muted d-block mt-50">يُعبَّأ العنوان والسعر والوصف والمميزات من بيانات الوحدة، وتبقى قابلة للتعديل قبل الحفظ.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.unit-filter')
    <script>
        $(function () {
            reUnitFilter('#building_id', '#unit_id', { syncBuilding: false });
            const infoUrl = @json(route('realestate.units.info', '__ID__'));
            const periods = @json(collect(V::RENT_PERIODS)->map(fn ($m) => $m['label']));
            const money = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // العرض/الطلب + وحدة/خارجي: تبديل الحقول والتسميات
            function toggleKind() {
                const request = $('#listing_kind').val() === 'request';
                const hasPlace = !!($('#unit_id').val() || $('#building_id').val());

                $('#externalCard').toggleClass('d-none', !request && hasPlace);
                $('.requestOnly').toggleClass('d-none', !request);
                $('#externalTitle').text(request ? 'تفاصيل الطلب' : 'تفاصيل العقار (خارجي)');
                $('#contactNameLabel').text(request ? 'اسم مقدّم الطلب' : 'اسم صاحب العرض');
                $('#askingLabel').text(request ? 'السعر المستهدف (اختياري)' : 'السعر المطلوب').toggleClass('stk-req', !request);
                $('#asking_price').attr('data-required', request ? null : '1');
                $('#convertUnitBox').toggleClass('d-none', request || !currentUnit);
            }

            function toggleType() {
                const rent = $('#listing_type').val() === 'rent';
                $('#rentPeriodField').toggleClass('d-none', !rent);
                $('#rent_period').prop('disabled', !rent);
            }

            var currentUnit = null;
            let autoConvert = @json(! $listing->exists && request()->boolean('convert') && ! old('title'));

            // يكتب فوق ما في الحقول — لذلك يُسأل المستخدم إن كان فيها ما كتبه بيده
            function convertUnit() {
                if (!currentUnit) return;

                const draft = currentUnit.listing_draft || {};
                const sale = $('#listing_type').val() === 'sale';

                const apply = function () {
                    $('#title').val((sale ? 'للبيع: ' : 'للإيجار: ') + (draft.title || currentUnit.label || ''));
                    $('#description').val(draft.description || '');
                    $('#features').val((draft.features || []).join('\n'));

                    if (!sale && currentUnit.base_rent) {
                        $('#asking_price').val(currentUnit.base_rent);
                        if (currentUnit.rent_period) $('#rent_period').val(currentUnit.rent_period);
                    }

                    if (draft.images_count) $('#copy_unit_images').prop('checked', true);

                    Swal.fire({ icon: 'success', title: 'حُوّلت الوحدة إلى عرض', text: 'راجع البيانات ثم احفظ العرض.', timer: 1800, showConfirmButton: false });
                };

                const dirty = $('#description').val().trim() || $('#features').val().trim();
                if (!dirty) return apply();

                Swal.fire({
                    icon: 'question', title: 'استبدال ما كتبته؟', text: 'سيُستبدل العنوان والوصف والمميزات ببيانات الوحدة.',
                    showCancelButton: true, confirmButtonText: 'نعم، استبدل', cancelButtonText: 'إلغاء',
                }).then(result => { if (result.isConfirmed) apply(); });
            }

            function resetSummary() {
                currentUnit = null;
                $('#convertUnitBox').addClass('d-none');
                $('#copy_unit_images').prop('checked', false);
                $('#unitSummary, #unitSummaryWarning').addClass('d-none');
                $('#unitSummaryEmpty').removeClass('d-none').text('اختر وحدة لعرض حالتها وإيجارها الإرشادي.');
            }

            function loadUnit(prefill) {
                const id = $('#unit_id').val();

                // العقار يُشتقّ من الوحدة في الخادم، فلا معنى لاختياره معها
                $('#buildingField').toggleClass('d-none', !!id);

                if (!id) return resetSummary();

                $.ajax({ url: infoUrl.replace('__ID__', id), headers: { Accept: 'application/json' } })
                    .done(function (unit) {
                        const occupancy = unit.occupancy || {};
                        const warnings = [];
                        const imagesCount = (unit.listing_draft || {}).images_count || 0;

                        currentUnit = unit;
                        $('#convertUnitBox').removeClass('d-none'); toggleKind();
                        $('#copyUnitImagesWrap').toggleClass('d-none', !imagesCount);
                        $('#unitImagesCount').text(imagesCount);
                        if (prefill) $('#copy_unit_images').prop('checked', false);

                        $('[data-info="label"]').text(unit.label || '—');
                        $('[data-info="occupancy"]').attr('class', 'badge badge-light-' + (occupancy.tone || 'secondary')).text(occupancy.label || '—');
                        $('[data-info="base_rent"]').text(money(unit.base_rent) + (periods[unit.rent_period] ? ' / ' + periods[unit.rent_period] : ''));
                        $('[data-info="area"]').text(unit.area ? unit.area + ' م²' : '—');
                        $('[data-info="owner_name"]').text(unit.owner_name || '—');
                        $('[data-info="is_marketable"]').attr('class', 'badge badge-light-' + (unit.is_marketable ? 'success' : 'danger')).text(unit.is_marketable ? 'نعم' : 'لا');

                        if (!unit.is_marketable) warnings.push('الوحدة موسومة «غير قابلة للتسويق».');
                        if (occupancy.label && occupancy.tone !== 'info') warnings.push('الوحدة ليست شاغرة حالياً — سيُطلب تأكيد عند النشر.');

                        $('#unitSummaryWarning').toggleClass('d-none', !warnings.length).text(warnings.join(' '));
                        $('#unitSummaryEmpty').addClass('d-none');
                        $('#unitSummary').removeClass('d-none');

                        // قادمٌ من زرّ «تحويل إلى عرض عقاري» في صفحة الوحدة
                        if (autoConvert) { autoConvert = false; return convertUnit(); }

                        if (!prefill) return;

                        if (!$('#asking_price').val() && unit.base_rent) {
                            $('#asking_price').val(unit.base_rent);
                            if (unit.rent_period && $('#listing_type').val() === 'rent') $('#rent_period').val(unit.rent_period);
                        }

                        if (!$('#title').val().trim() && unit.label) {
                            $('#title').val(($('#listing_type').val() === 'sale' ? 'للبيع: ' : 'للإيجار: ') + unit.label);
                        }
                    })
                    .fail(function () {
                        $('#unitSummary, #unitSummaryWarning').addClass('d-none');
                        $('#unitSummaryEmpty').removeClass('d-none').text('تعذّر تحميل بيانات الوحدة.');
                    });
            }

            $('#listing_type').on('change', toggleType);
            $('#listing_kind').on('change', toggleKind);
            $('#building_id').on('change', toggleKind);
            $('#unit_id').on('change', () => { loadUnit(true); toggleKind(); });
            $('#convertUnit').on('click', convertUnit);

            toggleType();
            loadUnit(false);
            toggleKind();
        });
    </script>
@endsection
