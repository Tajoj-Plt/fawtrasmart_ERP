@extends('branches::layouts.master')

{{-- كل متغيّرات العرض في كتلة واحدة أعلى الملف.
     السبب: Blade يلتقط كتل PHP الخام قبل أن يزيل التعليقات، فخلط الشكل
     السطري مع شكل الكتلة في ملف واحد يجعله يبدأ الكتلة من الموضع الخطأ،
     فيبقى فاتح الكتلة غير مُصرَّف ويسقط المتغيّر وقت العرض. --}}
@php
    $parentIdValue = old('parent_id', $branch->parent_id);
    $currentType = $branch->node_type ?: ($branch->is_territory ? 'group' : 'branch');
    $selectedType = old('node_type', $currentType);
    $nodeTypes = [
        'region' => [__('branches.node_types.region'), 'icon-map', 'success', __('branches.node_types.region_hint')],
        'branch' => [__('branches.node_types.branch'), 'icon-home', 'primary', __('branches.node_types.branch_hint')],
        'group'  => [__('branches.node_types.group'), 'icon-users', 'info', __('branches.node_types.group_hint')],
        'outlet' => [__('branches.node_types.outlet'), 'icon-shopping-bag', 'warning', __('branches.node_types.outlet_hint')],
    ];

    /* نفس قاعدة شاشة الإضافة: الشاشةُ تتكلّم بنوع ما تعدّله، و«الفرع الرئيسي»
       والحدُّ الائتمانيّ يظهران للفرع وحده. */
    $saveLabels = [
        'branch' => __('branches.form.save_of_branch'),
        'region' => __('branches.form.save_of_region'),
        'group' => __('branches.form.save_of_group'),
        'outlet' => __('branches.form.save_of_outlet'),
    ];
    $mainLabels = [
        'branch' => __('branches.form.is_main_branch_branch'),
        'region' => __('branches.form.is_main_branch_region'),
        'group' => __('branches.form.is_main_branch_group'),
        'outlet' => __('branches.form.is_main_branch_outlet'),
    ];
@endphp

@section('title', __('branches.form.edit_of', ['type' => $branch->nodeTypeLabel(), 'name' => $branch->name]))

@section('content')
    <x-branches::page-head
        :title="__('branches.form.edit_of', ['type' => $branch->nodeTypeLabel(), 'name' => $branch->name])"
        :subtitle="__('branches.form.edit_subtitle')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), $branch->name => route('branches.show', $branch->id), __('branches.form.breadcrumb_edit') => null]">
        <x-slot:actions>
            @can('branches.show')
                <a href="{{ route('branches.show', $branch->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-eye"></i> {{ __('branches.common.view_details') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-branches::page-head>

    <div class="content-body">
        @if ($errors->any())
            <x-branches::explain tone="danger" :title="__('branches.form.errors_title')">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-branches::explain>
        @endif

        <form action="{{ route('branches.update', $branch->id) }}" method="POST" id="branchForm">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-git-branch"></i> {{ __('branches.form.position_in_tree') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="name" class="sl-req"><span id="name-label">{{ __('branches.form.branch_name') }}</span></label>
                                <input type="text" id="name" class="form-control" name="name"
                                       value="{{ old('name', $branch->name) }}" required maxlength="255">
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="parent_id">{{ __('branches.form.parent_card') }}</label>
                                <select id="parent_id" name="parent_id" class="form-control select2">
                                    <option value="" {{ $parentIdValue === null || $parentIdValue === '' ? 'selected' : '' }}>
                                        {{ __('branches.form.root_option') }}
                                    </option>
                                    @foreach ($parentOptions as $opt)
                                        <option value="{{ $opt['id'] }}"
                                            {{ (string) $parentIdValue === (string) $opt['id'] ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="sl-help">
                                    {{ __('branches.form.edit_parent_hint') }}
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="code">{{ __('branches.form.code_in_tree') }}</label>
                                <input type="text" id="code" class="form-control" name="code"
                                       value="{{ old('code', $branch->code) }}" readonly>
                                <small class="sl-help">
                                    {{ __('branches.form.code_regen_hint') }}
                                </small>
                            </div>
                        </div>

                        {{-- «الرئيسي» متاحٌ لكل الأنواع بطلب المستخدم --}}
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label class="d-block">{{ __('branches.form.main_branch_label') }}</label>
                                <div class="stk-choice">
                                    <label class="stk-switch mb-0">
                                        <input type="checkbox" id="is_main" name="is_main" value="1"
                                               {{ old('is_main', $branch->is_main) ? 'checked' : '' }}>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title" id="isMainLabel">{{ $mainLabels[$selectedType] ?? __('branches.form.is_main_branch') }}</span>
                                            <span class="stk-switch__hint">{{ __('branches.form.is_main_hint') }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-0" id="node_type_group">
                                <label class="sl-req">{{ __('branches.common.type') }}</label>
                                <div class="row">
                                    @foreach ($nodeTypes as $value => [$label, $icon, $tone, $desc])
                                        <div class="col-lg-3 col-md-6 col-12 mb-1">
                                            {{-- بطاقةُ اختيارٍ لا حبّةُ اختيار: لكل نوعٍ
                                                 سطرُ شرحٍ يفرّقه عن أخيه، و`stk-pick`
                                                 الحبّةُ لا تتّسع لسطر. --}}
                                            <label class="bx-pick h-100 d-block">
                                                <input type="radio" name="node_type" value="{{ $value }}"
                                                       {{ $selectedType === $value ? 'checked' : '' }} required>
                                                <span class="bx-pick__head">
                                                    <span class="bx-pick__icon bx-pick__icon--{{ $tone }}">
                                                        <i class="feather {{ $icon }}"></i>
                                                    </span>
                                                    <span class="bx-pick__title">{{ $label }}</span>
                                                </span>
                                                <small class="bx-pick__hint">{{ $desc }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-phone"></i> {{ __('branches.form.contact_card') }}
                        <span class="sl-count">{{ __('branches.form.contact_all_optional') }}</span>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- ══ الحقول التي يقرّرها النوع ══

                             الفرعُ والمنفذُ موضعان حقيقيّان: لهما هاتفٌ وعنوانٌ وساعاتُ
                             عمل، وأربعةٌ منها إلزامية. والمنطقةُ والمجموعةُ تقسيمان
                             تنظيميّان لا مكان لهما — فلا يُطلب منهما شيءٌ من ذلك،
                             ويُطوى البابُ كلُّه بدل أن يُعرض عشرةَ حقولٍ لا معنى لها.

                             ولا تُنزَع الحقولُ من النموذج: صفوفٌ قديمة تحمل هاتفاً
                             وعنواناً على مناطقَ ومجموعات (٣٣ صفّاً هنا)، ونزعُها كان
                             يمسحها عند أوّل حفظ. تُطوى فقط، وتُفتح بزرّ — وتُفتح
                             وحدَها إن كان فيها ما يُعرض.

                             و`d-none` لا `hidden`: أعمدةُ الشبكة تُعرَّف بـ`display:block`
                             فتتجاوز `[hidden]` تماماً. --}}
                        <div class="col-12" id="contact-body">
                            <div class="row">
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="phone">{{ __('branches.form.phone') }}</label>
                                <input type="text" inputmode="tel" id="phone" class="form-control" name="phone"
                                       value="{{ old('phone', $branch->phone) }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="mobile">{{ __('branches.form.mobile') }}</label>
                                <input type="text" inputmode="tel" id="mobile" class="form-control" name="mobile"
                                       value="{{ old('mobile', $branch->mobile) }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="credit_limit">{{ __('branches.form.credit_limit') }}</label>
                                <input type="number" id="credit_limit" class="form-control" name="credit_limit"
                                       value="{{ old('credit_limit', $branch->credit_limit) }}" step="0.01" min="0">
                                <small class="sl-help">{{ __('branches.form.credit_limit_hint') }}</small>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="country">{{ __('branches.form.country') }}</label>
                                <input type="text" id="country" class="form-control" name="country"
                                       value="{{ old('country', $branch->country) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="city">{{ __('branches.form.city') }}</label>
                                <input type="text" id="city" class="form-control" name="city"
                                       value="{{ old('city', $branch->city) }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="region">{{ __('branches.form.region') }}</label>
                                <input type="text" id="region" class="form-control" name="region"
                                       value="{{ old('region', $branch->region) }}">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="address1">{{ __('branches.form.address1') }}</label>
                                <input type="text" id="address1" class="form-control" name="address1"
                                       value="{{ old('address1', $branch->address1) }}">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="address2">{{ __('branches.form.address2') }}</label>
                                <input type="text" id="address2" class="form-control" name="address2"
                                       value="{{ old('address2', $branch->address2) }}">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="work_hours">{{ __('branches.form.work_hours') }}</label>
                                <textarea id="work_hours" class="form-control" name="work_hours" rows="3">{{ old('work_hours', $branch->work_hours) }}</textarea>
                            </div>
                        </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="description">{{ __('branches.form.description') }}</label>
                                <textarea id="description" class="form-control" name="description" rows="3"
                                          maxlength="1000">{{ old('description', $branch->description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <p class="sl-help">
                        @if ($branch->geoLocation)
                            {{ __('branches.form.saved_location') }}
                            <a href="https://maps.google.com/?q={{ $branch->geoLocation->latitude }},{{ $branch->geoLocation->longitude }}"
                               target="_blank" rel="noopener noreferrer" class="stk-num">
                                {{ $branch->geoLocation->latitude }}, {{ $branch->geoLocation->longitude }}
                            </a>
                            {{ __('branches.form.clear_location_hint') }}
                        @else
                            {{ __('branches.form.no_location') }}
                        @endif
                    </p>

                    <button type="button" class="btn btn-outline-primary" onclick="toggleMap()">
                        <i class="feather icon-map-pin"></i>
                        {{ $branch->geoLocation ? __('branches.form.edit_map_title') : __('branches.form.map_title') }}
                    </button>
                    <div id="map-container" style="display:none" class="mt-1">
                        <div class="input-group mb-1">
                            <input type="text" id="search-location" class="form-control" placeholder="{{ __('branches.form.map_search') }}">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" onclick="searchLocation()">
                                    <i class="feather icon-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="map" style="height:380px;width:100%;border:1px solid #ebe9f1;border-radius:.428rem"></div>
                    </div>
                    <input type="hidden" id="latitude" name="latitude"
                           value="{{ old('latitude', $branch->geoLocation->latitude ?? '') }}">
                    <input type="hidden" id="longitude" name="longitude"
                           value="{{ old('longitude', $branch->geoLocation->longitude ?? '') }}">
                </div>

            </div>

            <div class="bx-sticky">
                <small class="sl-help">{{ __('branches.common.required_note') }}</small>
                <div class="d-flex flex-wrap" style="gap:.5rem">
                    <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="feather icon-save"></i>
                        <span id="saveBtnLabel">{{ $saveLabels[$selectedType] ?? __('branches.form.save_changes') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
    let map, marker, geocoder, searchBox;

    const typeLabelMap = {
        branch: @json(__('branches.form.name_of_branch')), region: @json(__('branches.form.name_of_region')),
        group: @json(__('branches.form.name_of_group')), outlet: @json(__('branches.form.name_of_outlet'))
    };
    /*
        نفسُ قاعدة شاشة الإضافة: الفرعُ والمنفذُ موضعان حقيقيّان لهما هاتفٌ
        وعنوان، والمنطقةُ والمجموعةُ تقسيمٌ تنظيميّ. والخادم يفرضها بـ
        `required_if`، فلو لم تظهر هنا لأفرغ المستخدم الهاتف ثم رُدَّ بخطأ
        لا يدلّه على أنّ النوع هو ما جعله إلزامياً.
    */

    const saveLabelMap = @json($saveLabels);
    const mainLabelMap = @json($mainLabels);


    function applyNodeType(value) {
        document.getElementById('name-label').textContent = typeLabelMap[value] || @json(__('branches.common.name'));

        const saveLabel = document.getElementById('saveBtnLabel');
        if (saveLabel && saveLabelMap[value]) saveLabel.textContent = saveLabelMap[value];

        const mainLabel = document.getElementById('isMainLabel');
        if (mainLabel && mainLabelMap[value]) mainLabel.textContent = mainLabelMap[value];

        // الترويسةُ تتبع النوع المختار لا النوع المحفوظ — من يحوّل فرعاً إلى
        // منطقة يرى العنوان يتغيّر قبل الحفظ فيعرف ما سيحدث
        const headTitle = document.querySelector('.js-page-title');
        if (headTitle && typeLabelMap[value]) {
            headTitle.textContent = @json(__('branches.form.edit_of', ['type' => ':t', 'name' => ':n']))
                .replace(':t', typeLabelMap[value])
                .replace(':n', @json($branch->name));
        }


    }

    $(function () {
        document.querySelectorAll('input[name="node_type"]').forEach(function (r) {
            r.addEventListener('change', function () { applyNodeType(this.value); });
        });
        var checked = document.querySelector('input[name="node_type"]:checked');
        if (checked) applyNodeType(checked.value);
    });

    document.getElementById('branchForm').addEventListener('submit', function (e) {
        const name = document.getElementById('name');
        if (!name.value.trim()) {
            e.preventDefault();
            name.classList.add('is-invalid');
            name.focus();
            name.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // بطاقات النوع تُخفي الراديو فعلياً (opacity:0)، فمتصفحات مثل كروم لا
        // تُظهر فقاعة required على عنصر مخفي وتصمت عن الحفظ دون أي إشعار.
        // لا رسالة منبثقة هنا بطلب المستخدم — فقط تمييز الحقل والتمرير إليه.
        if (!document.querySelector('input[name="node_type"]:checked')) {
            e.preventDefault();
            var typeGroup = document.getElementById('node_type_group');
            typeGroup.classList.add('bx-field-invalid');
            typeGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () { typeGroup.classList.remove('bx-field-invalid'); }, 2500);
            return;
        }

        var btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('branches.form.saving'));
    });

    function initMap() {
        const start = (document.getElementById('latitude').value && document.getElementById('longitude').value)
            ? { lat: parseFloat(document.getElementById('latitude').value),
                lng: parseFloat(document.getElementById('longitude').value) }
            : { lat: 24.7136, lng: 46.6753 };

        map = new google.maps.Map(document.getElementById('map'), {
            center: start, zoom: 14, mapTypeControl: true, streetViewControl: false
        });
        marker = new google.maps.Marker({ position: start, map: map, draggable: true });
        geocoder = new google.maps.Geocoder();

        marker.addListener('dragend', function () { updatePosition(marker.getPosition()); });
        map.addListener('click', function (ev) { marker.setPosition(ev.latLng); updatePosition(ev.latLng); });

        const input = document.getElementById('search-location');
        searchBox = new google.maps.places.SearchBox(input);
        searchBox.addListener('places_changed', function () {
            const places = searchBox.getPlaces();
            if (!places.length || !places[0].geometry) return;
            const loc = places[0].geometry.location;
            marker.setPosition(loc); map.setCenter(loc); map.setZoom(16);
            updatePosition(loc);
        });
    }

    function updatePosition(latLng) {
        document.getElementById('latitude').value = latLng.lat();
        document.getElementById('longitude').value = latLng.lng();
    }

    function toggleMap() {
        const box = document.getElementById('map-container');
        if (box.style.display === 'none') {
            box.style.display = 'block';
            setTimeout(function () {
                if (typeof google === 'object' && google.maps) {
                    if (!map) initMap(); else google.maps.event.trigger(map, 'resize');
                } else {
                    Swal.fire({ icon: 'error', title: @json(__('branches.form.map_failed')),
                                text: @json(__('branches.form.map_failed_text')),
                                confirmButtonColor: '#7367f0' });
                }
            }, 120);
        } else { box.style.display = 'none'; }
    }

    function searchLocation() {
        const input = document.getElementById('search-location');
        if (input.value.trim()) google.maps.event.trigger(input, 'focus');
    }
</script>
@endsection
