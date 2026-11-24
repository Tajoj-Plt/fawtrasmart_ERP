{{--
    نموذج الدور المشترك بين الإنشاء والتعديل.

    المتغيّرات المتوقَّعة: `permissionTree` (شجرة: قسم ← مجموعة ← صلاحية،
    يبنيها `RolesController::permissionTree()` من أعمدة التصنيف في جدول
    `permissions`)، `assigned` (أسماء الصلاحيات المُسنَدة حالياً أو من
    `old()`)، و`role` اختياري عند التعديل.

    كانت الصلاحيات تُعرَض بأسمائها الخام (`inv_manage_inventory_transfer`)
    تحت عنوانٍ لاتيني مشتقٍّ من بادئة الاسم — وثلاثمئة «مجموعة» بصلاحيةٍ
    واحدة لكلٍّ منها. صارت مستويين: القسم بوصفه، والمجموعة داخله، ولكل
    صلاحيةٍ تسميتها العربية والاسم الخام في `title` لمن يحتاجه.
--}}

@php
    $assignedIndex = array_flip($assigned);
    $totalPermissions = collect($permissionTree)->sum('count');
    $totalGroups = collect($permissionTree)->sum(fn($module) => count($module['groups']));
@endphp

<div class="stk-metrics">
    <div class="stk-metric">
        <span class="stk-metric__icon"><i class="feather icon-key"></i></span>
        <span class="stk-metric__label">الصلاحيات المتاحة</span>
        <span class="stk-metric__value stk-num">{{ $totalPermissions }}</span>
    </div>
    <div class="stk-metric">
        <span class="stk-metric__icon"><i class="feather icon-check-square"></i></span>
        <span class="stk-metric__label">المُحدَّدة الآن</span>
        <span class="stk-metric__value stk-num" id="selectedCount">{{ count($assigned) }}</span>
    </div>
    <div class="stk-metric">
        <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
        <span class="stk-metric__label">الأقسام</span>
        <span class="stk-metric__value stk-num">{{ count($permissionTree) }}</span>
    </div>
    <div class="stk-metric">
        <span class="stk-metric__icon"><i class="feather icon-grid"></i></span>
        <span class="stk-metric__label">المجموعات</span>
        <span class="stk-metric__value stk-num">{{ $totalGroups }}</span>
    </div>
</div>

<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-tag"></i> بيانات الدور</h6>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-6">
                <label for="role_name" class="stk-req">اسم الدور</label>
                <input type="text" id="role_name" name="name" class="form-control"
                    value="{{ old('name', $role->name ?? '') }}" required maxlength="190"
                    placeholder="مثال: مدير مبيعات">
                <small class="stk-help">اسمٌ يميّز الدور في قوائم اختيار أدوار المستخدمين.</small>
            </div>

            {{--
                القائمة الجانبية الموحّدة.

                الأصل أن لكل وحدةٍ قائمتَها: من انتقل إلى «إدارة العملاء» فقد
                من أمامه شاشات المبيعات حتى يعود عبر الترويسة. وهذا يناسب من
                يعمل في وحدةٍ واحدة، ويُعطِّل من يعمل عبر الوحدات في يومه.

                والحقل الخفي قبل المربّع مقصود: المربّع غير المؤشَّر لا يُرسَل
                أصلاً، فلولا الخفيُّ لتعذّر **إطفاء** المفتاح بعد إشعاله.
            --}}
            <div class="form-group col-md-6">
                <label>القائمة الجانبية</label>
                <input type="hidden" name="unified_sidebar" value="0">
                <label class="stk-switch" for="unified_sidebar">
                    <input type="checkbox" id="unified_sidebar" name="unified_sidebar" value="1"
                        @checked(old('unified_sidebar', $role->unified_sidebar ?? false))>
                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                    <span class="stk-switch__label">
                        <span class="stk-switch__title">
                            <i class="feather icon-menu"></i> قائمة جانبية موحّدة
                        </span>
                        <span class="stk-switch__hint">
                            تُعرَض القائمة الجامعة في كل شاشات النظام لأصحاب هذا الدور،
                            فينتقلون من أي صفحةٍ إلى أي صفحةٍ يملكون صلاحيتها بلا رجوع.
                            وهي مسوَّرةٌ بالصلاحيات بنداً بنداً — لا تُظهر ما ليس للدور.
                            مطفأةً تبقى لكل وحدةٍ قائمتُها كما هو الحال اليوم.
                        </span>
                    </span>
                </label>
            </div>

            {{--
                قناةُ عمل الدور.

                المندوبُ الميداني عملُه زياراتٌ وفواتيرُ من الجوّال، ولا شأن
                له بشاشات النظام. وحصرُه في التطبيق يُغلق عنه الموقعَ وحده —
                وتبقى واجهةُ التطبيق مفتوحةً له كما هي.

                والحقل الخفي قبل المربّع مقصودٌ كنظيره أعلاه: غيرُ المؤشَّر لا
                يُرسَل، فلولاه لتعذّر إطفاءُ المفتاح بعد إشعاله.
            --}}
            <div class="form-group col-md-6">
                <label>قناة العمل</label>
                <input type="hidden" name="mobile_only" value="0">
                <label class="stk-switch" for="mobile_only">
                    <input type="checkbox" id="mobile_only" name="mobile_only" value="1"
                        @checked(old('mobile_only', $role->mobile_only ?? false))>
                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                    <span class="stk-switch__label">
                        <span class="stk-switch__title">
                            <i class="feather icon-smartphone"></i> التطبيق فقط — بلا دخول للموقع
                        </span>
                        <span class="stk-switch__hint">
                            أصحابُ هذا الدور يعملون من تطبيق الجوّال وحده: تسجيلُ الدخول إلى
                            الموقع يُرفض برسالةٍ صريحة، وأيُّ جلسةٍ قائمةٍ تُغلَق عند أوّل طلب.
                            <strong>وصلاحياتُهم في التطبيق لا تتغيّر</strong> — المنعُ على الموقع لا على النظام.
                            مطفأً — وهو الافتراض — يدخلون الموقعَ كغيرهم.
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</div>

<div class="card stk-table-card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-shield"></i> الصلاحيات
            <span class="stk-count" id="selectedCountBadge">{{ count($assigned) }}</span>
        </h6>

        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
            <input type="search" id="permissionSearch" class="form-control form-control-sm"
                placeholder="ابحث في الصلاحيات…" style="min-width:220px" aria-label="بحث في الصلاحيات">

            <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAll">
                <i class="feather icon-maximize-2"></i> فتح الأقسام
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">
                <i class="feather icon-x-circle"></i> إلغاء التحديد
            </button>
        </div>
    </div>

    <div class="card-body">
        @forelse ($permissionTree as $module)
            @php
                $moduleChecked = 0;
                foreach ($module['groups'] as $group) {
                    foreach ($group['permissions'] as $permission) {
                        if (isset($assignedIndex[$permission['name']])) {
                            $moduleChecked++;
                        }
                    }
                }
            @endphp

            <section class="perm-module" data-module="{{ $module['key'] }}" data-collapsed="1">
                <header class="perm-module__head">
                    <button type="button" class="perm-module__toggle" aria-expanded="false"
                        aria-controls="module-body-{{ $module['key'] }}">
                        <i class="feather icon-chevron-down"></i>
                    </button>

                    <span class="perm-module__icon"><i class="feather icon-{{ $module['icon'] }}"></i></span>

                    <span class="perm-module__titles">
                        <span class="perm-module__title">
                            {{ $module['label'] }}
                            <span class="stk-count perm-module__count" data-total="{{ $module['count'] }}">
                                {{ $moduleChecked }}/{{ $module['count'] }}
                            </span>
                        </span>
                        <span class="perm-module__sub">{{ $module['description'] }}</span>
                    </span>

                    <button type="button" class="btn btn-sm btn-outline-secondary module-toggle"
                        data-module="{{ $module['key'] }}">
                        <i class="feather icon-check-square"></i> تحديد القسم
                    </button>
                </header>

                <div class="perm-module__body" id="module-body-{{ $module['key'] }}">
                    @php $currentSection = null; @endphp
                    @foreach ($module['groups'] as $group)
                        @if (! empty($group['section_label']) && $group['section'] !== $currentSection)
                            @php $currentSection = $group['section']; @endphp
                            <h5 class="perm-section-title" data-section="{{ $group['section'] }}">{{ $group['section_label'] }}</h5>
                        @endif
                        @php
                            $groupChecked = collect($group['permissions'])
                                ->filter(fn($permission) => isset($assignedIndex[$permission['name']]))
                                ->count();
                        @endphp

                        <div class="stk-section perm-group" data-group="{{ $group['key'] }}" data-section="{{ $group['section'] ?? '' }}">
                            <div class="stk-section__head">
                                <i class="feather icon-{{ $group['icon'] }}"></i>

                                <h6 class="stk-section__title">
                                    {{ $group['label'] }}
                                    @if ($group['description'])
                                        <span class="perm-group__sub">{{ $group['description'] }}</span>
                                    @endif
                                </h6>

                                <span class="stk-count perm-group-count" data-total="{{ count($group['permissions']) }}">
                                    {{ $groupChecked }}/{{ count($group['permissions']) }}
                                </span>

                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto group-toggle"
                                    data-group="{{ $group['key'] }}">
                                    <i class="feather icon-check-square"></i> تحديد المجموعة
                                </button>
                            </div>

                            <div class="stk-picks">
                                @foreach ($group['permissions'] as $permission)
                                    <label class="stk-pick perm-item" title="{{ $permission['name'] }}"
                                        data-search="{{ mb_strtolower($permission['label'].' '.$permission['name']) }}">
                                        <input type="checkbox" class="perm-checkbox" name="permissions[]"
                                            value="{{ $permission['name'] }}"
                                            @checked(isset($assignedIndex[$permission['name']]))>
                                        <span class="stk-pick__box"><i class="feather icon-check"></i></span>
                                        <span class="stk-pick__label">{{ $permission['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="stk-empty">
                <span class="stk-empty__icon"><i class="feather icon-shield-off"></i></span>
                <h6 class="stk-empty__title">لا توجد صلاحيات في النظام</h6>
                <p class="stk-empty__hint">
                    شغّل <span class="stk-num">php artisan permissions:sync-catalog</span> لزرع الصلاحيات وتصنيفها.
                </p>
            </div>
        @endforelse

        <div class="stk-empty d-none" id="permissionSearchEmpty">
            <span class="stk-empty__icon"><i class="feather icon-search"></i></span>
            <h6 class="stk-empty__title">لا صلاحية مطابقة</h6>
            <p class="stk-empty__hint">جرّب كلمةً أخرى — البحث يشمل التسمية العربية والاسم الخام معاً.</p>
        </div>
    </div>
</div>

@include('sitting::roles._module-styles')

@push('scripts')
    <script>
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('.perm-checkbox'));
            var modules = Array.prototype.slice.call(document.querySelectorAll('.perm-module'));
            var groups = Array.prototype.slice.call(document.querySelectorAll('.perm-group'));
            var searchBox = document.getElementById('permissionSearch');
            var searchEmpty = document.getElementById('permissionSearchEmpty');

            function itemsOf(scope) {
                return Array.prototype.slice.call(scope.querySelectorAll('.perm-item'));
            }

            function visibleIn(scope) {
                return itemsOf(scope).filter(function (item) { return ! item.classList.contains('d-none'); });
            }

            /** عدّادات الرأس والأقسام والمجموعات — مصدرها كلها حالة الخانات نفسها */
            function refreshCounts() {
                var total = boxes.filter(function (box) { return box.checked; }).length;

                ['selectedCount', 'selectedCountBadge'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.textContent = total;
                });

                [['.perm-module', '.perm-module__count'], ['.perm-group', '.perm-group-count']]
                    .forEach(function (pair) {
                        document.querySelectorAll(pair[0]).forEach(function (scope) {
                            var counter = scope.querySelector(pair[1]);
                            if (! counter) return;

                            var checked = itemsOf(scope).filter(function (item) {
                                return item.querySelector('.perm-checkbox').checked;
                            }).length;

                            counter.textContent = checked + '/' + counter.dataset.total;
                        });
                    });
            }

            /** يملأ النطاق ما لم يكن ممتلئاً، وإلا يفرغه — على الظاهر منه فقط */
            function toggleScope(scope) {
                var visible = visibleIn(scope);
                if (! visible.length) return;

                var allOn = visible.every(function (item) { return item.querySelector('.perm-checkbox').checked; });
                visible.forEach(function (item) { item.querySelector('.perm-checkbox').checked = ! allOn; });
                refreshCounts();
            }

            document.querySelectorAll('.module-toggle').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var scope = button.closest('.perm-module');
                    if (scope) toggleScope(scope);
                });
            });

            document.querySelectorAll('.group-toggle').forEach(function (button) {
                button.addEventListener('click', function () {
                    var scope = button.closest('.perm-group');
                    if (scope) toggleScope(scope);
                });
            });

            document.querySelectorAll('.perm-module__toggle').forEach(function (button) {
                button.addEventListener('click', function () {
                    var scope = button.closest('.perm-module');
                    var collapsed = scope.getAttribute('data-collapsed') === '1';

                    scope.setAttribute('data-collapsed', collapsed ? '0' : '1');
                    button.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
                });
            });

            boxes.forEach(function (box) { box.addEventListener('change', refreshCounts); });

            var clearAll = document.getElementById('clearAll');
            if (clearAll) {
                clearAll.addEventListener('click', function () {
                    boxes.forEach(function (box) { box.checked = false; });
                    refreshCounts();
                });
            }

            var collapseAll = document.getElementById('collapseAll');
            if (collapseAll) {
                collapseAll.addEventListener('click', function () {
                    var anyOpen = modules.some(function (m) { return m.getAttribute('data-collapsed') !== '1'; });

                    modules.forEach(function (m) {
                        m.setAttribute('data-collapsed', anyOpen ? '1' : '0');
                        var toggle = m.querySelector('.perm-module__toggle');
                        if (toggle) toggle.setAttribute('aria-expanded', anyOpen ? 'false' : 'true');
                    });

                    collapseAll.innerHTML = anyOpen
                        ? '<i class="feather icon-maximize-2"></i> فتح الأقسام'
                        : '<i class="feather icon-minimize-2"></i> طيّ الأقسام';
                });
            }

            if (searchBox) {
                searchBox.addEventListener('input', function () {
                    var term = searchBox.value.trim().toLowerCase();
                    var anyVisible = false;

                    groups.forEach(function (group) {
                        var shown = 0;

                        itemsOf(group).forEach(function (item) {
                            var hit = term === '' || item.dataset.search.indexOf(term) !== -1;
                            item.classList.toggle('d-none', ! hit);
                            if (hit) shown++;
                        });

                        group.classList.toggle('d-none', shown === 0);
                    });

                    // عنوانُ القسم الفرعي يُخفى إذا خفيت مجموعاتُه كلُّها
                    document.querySelectorAll('.perm-section-title').forEach(function (heading) {
                        var module = heading.closest('.perm-module');
                        var visible = module ? module.querySelectorAll('.perm-group[data-section="' + heading.dataset.section + '"]:not(.d-none)').length : 1;
                        heading.classList.toggle('d-none', visible === 0);
                    });

                    modules.forEach(function (module) {
                        var shown = module.querySelectorAll('.perm-group:not(.d-none)').length;
                        module.classList.toggle('d-none', shown === 0);

                        // البحث يفتح ما طواه المستخدم، وإلا بحث في المطويّ بلا نتيجةٍ مرئية
                        if (shown > 0 && term !== '') {
                            module.setAttribute('data-collapsed', '0');
                        }

                        if (shown > 0) anyVisible = true;
                    });

                    if (searchEmpty) {
                        searchEmpty.classList.toggle('d-none', anyVisible || modules.length === 0);
                    }
                });
            }

            refreshCounts();
        })();
    </script>
@endpush
