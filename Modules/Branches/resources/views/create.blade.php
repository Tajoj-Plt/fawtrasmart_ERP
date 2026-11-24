@extends('branches::layouts.master')

{{-- كل متغيّرات العرض في كتلة واحدة أعلى الملف.
     السبب: Blade يلتقط كتل PHP الخام قبل أن يزيل التعليقات، فخلط الشكل
     السطري مع شكل الكتلة في ملف واحد يجعله يبدأ الكتلة من الموضع الخطأ،
     فيبقى فاتح الكتلة غير مُصرَّف ويسقط المتغيّر وقت العرض. --}}
@php
    $presetParentId = old('parent_id', $presetParent?->id);
    $selectedType = old('node_type', $presetParent ? 'region' : 'branch');
    $nodeTypes = [
        'region' => [__('branches.node_types.region'), 'icon-map', 'success', __('branches.node_types.region_hint')],
        'branch' => [__('branches.node_types.branch'), 'icon-home', 'primary', __('branches.node_types.branch_hint')],
        'group'  => [__('branches.node_types.group'), 'icon-users', 'info', __('branches.node_types.group_hint')],
        'outlet' => [__('branches.node_types.outlet'), 'icon-shopping-bag', 'warning', __('branches.node_types.outlet_hint')],
    ];

    /* الشاشةُ تتكلّم بنوع ما يُضاف، لا بـ«فرع» في كل حال.

       كانت الترويسةُ «إضافة فرع جديد» وزرُّ الحفظ «حفظ الفرع» ورسالةُ النجاح
       «تم إضافة الفرع» مهما اختار المستخدم — فمن يضيف منطقةً تقول له الشاشةُ
       ثلاثَ مرّاتٍ إنه أضاف فرعاً. ومفتاحُ «الفرع الرئيسي» والحدُّ الائتماني
       كانا معروضَين للمناطق والمجموعات وهما بلا معنًى فيها. */
    $createTitles = [
        'branch' => __('branches.form.create_of_branch'),
        'region' => __('branches.form.create_of_region'),
        'group' => __('branches.form.create_of_group'),
        'outlet' => __('branches.form.create_of_outlet'),
    ];
    $saveLabels = [
        'branch' => __('branches.form.save_of_branch'),
        'region' => __('branches.form.save_of_region'),
        'group' => __('branches.form.save_of_group'),
        'outlet' => __('branches.form.save_of_outlet'),
    ];
    $mainLabels = [
        'branch' => __('branches.form.set_as_main_branch'),
        'region' => __('branches.form.set_as_main_region'),
        'group' => __('branches.form.set_as_main_group'),
        'outlet' => __('branches.form.set_as_main_outlet'),
    ];
    $screenTitle = $presetParent
        ? __('branches.form.create_under', ['name' => $presetParent->name])
        : ($createTitles[$selectedType] ?? __('branches.form.create_title'));
@endphp

@section('title', $screenTitle)

@section('content')
    <x-branches::page-head
        :title="$screenTitle"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.form.breadcrumb_add') => null]" />

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

        {{--
            ══ نموذجٌ من بطاقتين لا أربع ══

            كانت الشاشة أربع بطاقاتٍ متتالية — «الأب» وحدها بطاقةٌ لحقلٍ
            واحد، ثم «بيانات العنصر»، ثم «الاتصال والعنوان»، ثم «إضافية» —
            فأكثرُ الصفحة عناوينُ بطاقاتٍ وهوامشُها، ولا يُرى منها في الشاشة
            الواحدة إلّا الثلث. صارت بطاقتين: «التعريف» فيها الأب والاسم
            والكود والنوع والمفاتيح، و«التفاصيل» فيها الاتصال والعنوان
            والإضافي مفصولةً بعناوين أقسامٍ رفيعة لا ببطاقاتٍ كاملة.
        --}}
        <form action="{{ route('branches.store') }}" method="POST" id="branchForm" class="bx-compact">
            @csrf

            {{-- ══ التعريف: الأب يحدّد الكود، والنوع يحدّد ما يلزم بعده ══ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-git-branch"></i> {{ __('branches.form.node_data') }}</h4>
                    <small class="sl-help mb-0">{{ __('branches.form.code_example') }}</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- الأب أولاً: هو ما يشتقّ منه الكود --}}
                        @if ($presetParent)
                            <div class="col-12 mb-1">
                                <div class="bx-parent-ctx d-flex align-items-center flex-wrap" style="gap:.6rem">
                                    <span class="bx-pick__icon bx-pick__icon--primary">
                                        <i class="feather icon-git-branch"></i>
                                    </span>
                                    <div class="flex-grow-1" style="min-width:180px">
                                        <div class="sl-media__title">{{ $presetParent->name }}</div>
                                        <small class="sl-help">
                                            {{ __('branches.common.path') }}: {{ $presetParent->lineagePathLabel() }}
                                            · {{ __('branches.common.code') }}: <span class="stk-num">{{ $presetParent->code ?: '—' }}</span>
                                        </small>
                                    </div>
                                    @can('branches.create')
                                        <a href="{{ route('branches.create') }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="feather icon-refresh-cw"></i> {{ __('branches.form.change_parent') }}
                                        </a>
                                    @endcan
                                    <input type="hidden" name="parent_id" value="{{ $presetParent->id }}">
                                </div>
                            </div>
                        @else
                            <div class="col-lg-5 col-md-6 col-12">
                                <div class="form-group">
                                    <label for="parent_id">{{ __('branches.form.choose_parent') }}</label>
                                    <select id="parent_id" name="parent_id" class="form-control select2">
                                        <option value="">{{ __('branches.form.root_option') }}</option>
                                        @foreach ($parentOptions as $opt)
                                            <option value="{{ $opt['id'] }}"
                                                {{ (string) $presetParentId === (string) $opt['id'] ? 'selected' : '' }}>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="{{ $presetParent ? 'col-lg-8 col-md-7' : 'col-lg-4 col-md-6' }} col-12">
                            <div class="form-group">
                                <label for="name" class="sl-req"><span id="name-label">{{ __('branches.form.branch_name') }}</span></label>
                                <input type="text" id="name" class="form-control" name="name"
                                       placeholder="{{ __('branches.form.name_placeholder') }}" value="{{ old('name') }}" required maxlength="255">
                            </div>
                        </div>

                        <div class="{{ $presetParent ? 'col-lg-4 col-md-5' : 'col-lg-3 col-md-12' }} col-12">
                            <div class="form-group">
                                <label>{{ __('branches.form.code_to_grant') }}</label>
                                {{-- خبرٌ لا حقل: الكود يُولّده الخادم من موضع العنصر في الشجرة --}}
                                <div>
                                    <span class="bx-code-chip">
                                        <i class="feather icon-hash"></i>
                                        <span id="code_preview">{{ $codePreviewIfRoot }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group" id="node_type_group">
                                <label class="sl-req mb-0">{{ __('branches.common.type') }}</label>
                                <small class="sl-help">{{ __('branches.node_types.explain') }}</small>
                                <div class="row mt-0">
                                    @foreach ($nodeTypes as $value => [$label, $icon, $tone, $desc])
                                        <div class="col-lg-3 col-md-6 col-12 mb-1">
                                            {{-- بطاقةُ اختيارٍ لا حبّةُ اختيار: لكل نوعٍ
                                                 سطرُ شرحٍ يفرّقه عن أخيه. --}}
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

                        {{-- مفتاح نظام التصميم: العنوانُ وسطرُ الشرح داخل المفتاح نفسه --}}
                        <div class="@can('branches.toggle_status') col-md-6 @else col-12 @endcan col-12">
                            <div class="stk-choice h-100">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="is_main" name="is_main" value="1"
                                           {{ old('is_main') ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title" id="isMainLabel">{{ $mainLabels[$selectedType] ?? __('branches.form.set_as_main') }}</span>
                                        <span class="stk-switch__hint">{{ __('branches.form.main_hint') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- الحالة محروسةٌ بصلاحية `branches.toggle_status` نفسها التي
                             يحرسها مسار `updateStatus` — وإلّا لصار «الإنشاء» بابَ
                             تعطيلٍ لمن مُنع من التعطيل. --}}
                        @can('branches.toggle_status')
                            <div class="col-md-6 col-12">
                                <div class="stk-choice h-100">
                                    <label class="stk-switch mb-0">
                                        {{-- مربّعُ الاختيار غير المؤشَّر لا يُرسَل أصلاً، فبلا هذا
                                             الحقل المخفيّ لا يصل فرقٌ بين «عطّله» و«لم يمسّه». --}}
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" id="is_active" name="is_active" value="1"
                                               {{ old('is_active', 1) ? 'checked' : '' }}>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">{{ __('branches.form.status_active') }}</span>
                                            <span class="stk-switch__hint">{{ __('branches.form.status_hint') }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>

            {{--
                ══ التفاصيل ══

                أقسامٌ رفيعة لا بطاقاتٌ كاملة. و«اختيارية» التي كانت في عنوان
                البطاقة كانت كذباً: أعمدة `phone` و`address1` و`city` و`country`
                كلّها `NOT NULL`، و`ConvertEmptyStringsToNull` يحوّل الفارغ إلى
                `null` — فمن صدّقها سقط حفظُه بخطأ SQL خام. صار لكلّ حقلٍ
                علامتُه الصادقة، تتبع نوع العنصر.
            --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-map-pin"></i> {{ __('branches.form.contact_card') }}
                        <span class="sl-count">{{ __('branches.form.contact_all_optional') }}</span>
                    </h4>
                    <div class="d-flex flex-wrap" style="gap:.4rem">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="map-toggle-btn" onclick="toggleMap()">
                            <i class="feather icon-map"></i> {{ __('branches.form.map_title') }}
                        </button>
                    </div>
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
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="phone">{{ __('branches.form.phone') }}</label>
                                <input type="text" inputmode="tel" id="phone" class="form-control" name="phone"
                                       placeholder="{{ __('branches.form.phone_placeholder') }}"
                                       value="{{ old('phone') }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="mobile">{{ __('branches.form.mobile') }}</label>
                                <input type="text" inputmode="tel" id="mobile" class="form-control" name="mobile"
                                       placeholder="{{ __('branches.form.mobile_placeholder') }}"
                                       value="{{ old('mobile') }}" maxlength="50">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="country">{{ __('branches.form.country') }}</label>
                                <input type="text" id="country" class="form-control" name="country"
                                       value="{{ old('country') }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="city">{{ __('branches.form.city') }}</label>
                                <input type="text" id="city" class="form-control" name="city"
                                       value="{{ old('city') }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="region">{{ __('branches.form.region') }}</label>
                                <input type="text" id="region" class="form-control" name="region"
                                       value="{{ old('region') }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="address1">{{ __('branches.form.address1') }}</label>
                                <input type="text" id="address1" class="form-control" name="address1"
                                       value="{{ old('address1') }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="address2">{{ __('branches.form.address2') }}</label>
                                <input type="text" id="address2" class="form-control" name="address2"
                                       value="{{ old('address2') }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <div class="form-group">
                                <label for="credit_limit">{{ __('branches.form.credit_limit') }}</label>
                                <input type="number" id="credit_limit" class="form-control stk-num" name="credit_limit"
                                       value="{{ old('credit_limit', 0) }}" step="0.01" min="0">
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group mb-0">
                                <label for="work_hours">{{ __('branches.form.work_hours') }}</label>
                                <textarea id="work_hours" class="form-control" name="work_hours" rows="2"
                                          placeholder="{{ __('branches.form.work_hours_placeholder') }}">{{ old('work_hours') }}</textarea>
                            </div>
                        </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="form-group mb-0">
                                <label for="description">{{ __('branches.form.description') }}</label>
                                <textarea id="description" class="form-control" name="description" rows="2"
                                          maxlength="1000">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div id="map-container" style="display:none" class="mt-1">
                        <div class="input-group mb-1">
                            <input type="text" id="search-location" class="form-control" placeholder="{{ __('branches.form.map_search') }}">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" onclick="searchLocation()">
                                    <i class="feather icon-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="map" style="height:320px;width:100%;border:1px solid #ebe9f1;border-radius:.428rem"></div>
                        <small class="sl-help">{{ __('branches.form.map_hint') }}</small>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                </div>
            </div>

            <div class="bx-sticky">
                <small class="sl-help mb-0">{{ __('branches.common.required_note') }}</small>
                <div class="d-flex flex-wrap" style="gap:.5rem">
                    <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="feather icon-save"></i>
                        <span id="saveBtnLabel">{{ $saveLabels[$selectedType] ?? __('branches.form.save_branch') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
{{-- الخريطة تُحمَّل من القالب الرئيسي؛ تحميلها ثانية هنا تكرار مدفوع. --}}
<script>
    let map, marker, geocoder, searchBox;

    const branchCodePreviewByParent = @json($codePreviewByParentId ?? []);
    const branchCodePreviewRoot = @json($codePreviewIfRoot ?? '—');
    const presetParentId = @json($presetParent?->id);

    const typeLabelMap = {
        branch: @json(__('branches.form.name_of_branch')), region: @json(__('branches.form.name_of_region')),
        group: @json(__('branches.form.name_of_group')), outlet: @json(__('branches.form.name_of_outlet'))
    };


    // الشاشةُ كلُّها تتبع النوع: الترويسة، وزرُّ الحفظ، واسمُ الحقل
    const createTitleMap = @json($createTitles);
    const saveLabelMap = @json($saveLabels);
    const mainLabelMap = @json($mainLabels);
    const hasPresetParent = @json((bool) $presetParent);

    function applyNodeType(value) {
        const label = typeLabelMap[value] || @json(__('branches.common.name'));
        document.getElementById('name-label').textContent = label;
        document.getElementById('name').setAttribute('placeholder', @json(__('branches.common.enter', ['label' => ':l'])).replace(':l', label));

        // مع أبٍ مثبَّت تبقى الترويسة «إضافة عنصر تابع لـ…» فهي تسمّي الأب لا النوع
        if (!hasPresetParent && createTitleMap[value]) {
            const head = document.querySelector('.js-page-title');
            if (head) head.textContent = createTitleMap[value];
            document.title = createTitleMap[value];
        }

        const saveLabel = document.getElementById('saveBtnLabel');
        if (saveLabel && saveLabelMap[value]) saveLabel.textContent = saveLabelMap[value];

        const mainLabel = document.getElementById('isMainLabel');
        if (mainLabel && mainLabelMap[value]) mainLabel.textContent = mainLabelMap[value];


    }

    function refreshCodePreview(parentValue) {
        document.getElementById('code_preview').textContent = parentValue
            ? (branchCodePreviewByParent[parentValue] !== undefined ? branchCodePreviewByParent[parentValue] : '—')
            : branchCodePreviewRoot;
    }

    // select2 يُطلق حدث jQuery فقط، لا حدثاً أصلياً في الـ DOM — لذلك نربط عبر jQuery.
    $(function () {
        var parentSel = document.getElementById('parent_id');
        if (parentSel) {
            $('#parent_id').on('change', function () { refreshCodePreview(this.value); });
            refreshCodePreview(parentSel.value);
        } else {
            // الأب ثابت (جئنا من الشجرة)
            refreshCodePreview(String(presetParentId));
        }

        document.querySelectorAll('input[name="node_type"]').forEach(function (r) {
            r.addEventListener('change', function () { applyNodeType(this.value); });
        });
        applyNodeType(document.querySelector('input[name="node_type"]:checked').value);
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

        // بطاقات النوع تُخفي الراديو فعلياً (opacity:0) لتصميمها كبطاقات قابلة
        // للنقر؛ متصفحات مثل كروم ترفض إظهار فقاعة required على عنصر مخفي
        // فتصمت عن الحفظ دون أي إشعار — لذلك نتحقق يدوياً بدل الاعتماد عليها.
        // لا رسالة منبثقة هنا بطلب المستخدم — فقط تمييز الحقل والتمرير إليه.
        if (!document.querySelector('input[name="node_type"]:checked')) {
            e.preventDefault();
            var typeGroup = document.getElementById('node_type_group');
            typeGroup.classList.add('bx-field-invalid');
            typeGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () { typeGroup.classList.remove('bx-field-invalid'); }, 2500);
            return;
        }

        // تعطيل الزر يمنع الإرسال المزدوج الذي كان ينشئ عنصرين متطابقين
        var btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('branches.form.saving'));
    });

    // ═══ خرائط جوجل ═══
    function initMap() {
        const fallback = { lat: 24.7136, lng: 46.6753 };
        map = new google.maps.Map(document.getElementById('map'), {
            center: fallback, zoom: 12, mapTypeControl: true, streetViewControl: false
        });
        marker = new google.maps.Marker({ position: fallback, map: map, draggable: true });
        geocoder = new google.maps.Geocoder();

        marker.addListener('dragend', function () {
            updatePosition(marker.getPosition());
            reverseGeocode(marker.getPosition());
        });
        map.addListener('click', function (ev) {
            marker.setPosition(ev.latLng);
            updatePosition(ev.latLng);
            reverseGeocode(ev.latLng);
        });

        const input = document.getElementById('search-location');
        searchBox = new google.maps.places.SearchBox(input);
        searchBox.addListener('places_changed', function () {
            const places = searchBox.getPlaces();
            if (!places.length || !places[0].geometry) return;
            const loc = places[0].geometry.location;
            marker.setPosition(loc);
            map.setCenter(loc);
            map.setZoom(16);
            updatePosition(loc);
            fillAddress(places[0]);
        });

        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (lat && lng) {
            const saved = { lat: parseFloat(lat), lng: parseFloat(lng) };
            marker.setPosition(saved);
            map.setCenter(saved);
        }
    }

    function updatePosition(latLng) {
        document.getElementById('latitude').value = latLng.lat();
        document.getElementById('longitude').value = latLng.lng();
    }

    function reverseGeocode(latLng) {
        geocoder.geocode({ location: latLng }, function (results, status) {
            if (status === 'OK' && results[0]) fillAddress(results[0]);
        });
    }

    function fillAddress(result) {
        const parts = { street_number: '', route: '', neighborhood: '', locality: '', admin: '', country: '' };
        (result.address_components || []).forEach(function (c) {
            if (c.types.includes('street_number')) parts.street_number = c.long_name;
            else if (c.types.includes('route')) parts.route = c.long_name;
            else if (c.types.includes('neighborhood') || c.types.includes('sublocality')) parts.neighborhood = c.long_name;
            else if (c.types.includes('locality')) parts.locality = c.long_name;
            else if (c.types.includes('administrative_area_level_1')) parts.admin = c.long_name;
            else if (c.types.includes('country')) parts.country = c.long_name;
        });

        // لا نطمس ما كتبه المستخدم — نملأ الفارغ فقط
        const setIfEmpty = function (id, val) {
            const el = document.getElementById(id);
            if (el && !el.value && val) el.value = val;
        };
        setIfEmpty('address1', [parts.street_number, parts.route].filter(Boolean).join(' '));
        setIfEmpty('address2', parts.neighborhood);
        setIfEmpty('city', parts.locality);
        setIfEmpty('region', parts.admin);
        setIfEmpty('country', parts.country);

        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: @json(__('branches.form.address_updated')), showConfirmButton: false, timer: 2200 });
    }

    function toggleMap() {
        const box = document.getElementById('map-container');
        if (box.style.display === 'none') {
            box.style.display = 'block';
            setTimeout(function () {
                if (typeof google === 'object' && google.maps) {
                    if (!map) initMap();
                    else google.maps.event.trigger(map, 'resize');
                } else {
                    Swal.fire({ icon: 'error', title: @json(__('branches.form.map_failed')),
                                text: @json(__('branches.form.map_failed_text')),
                                confirmButtonColor: '#7367f0' });
                }
            }, 120);
        } else {
            box.style.display = 'none';
        }
    }

    function searchLocation() {
        const input = document.getElementById('search-location');
        if (!input.value.trim()) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'warning',
                        title: @json(__('branches.form.map_search_empty')), showConfirmButton: false, timer: 2000 });
            return;
        }
        google.maps.event.trigger(input, 'focus');
    }
</script>
@endsection
