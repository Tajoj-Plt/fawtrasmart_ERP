@extends('client::master')

@section('content')

<link rel="stylesheet" href="{{ asset('css/client/relestion_mang_client.css') }}?v=31">
{{-- طبقة المظهر على نمط شاشات خط السير — بعد الورقة الأصلية، تحت .rm-rb فقط --}}
<link rel="stylesheet" href="{{ asset('css/client/relestion_mang_client_rb.css') }}?v=5">
{{-- تبويب «ملاحظات الزيارات» — مشتركٌ مع شاشة العميل --}}
<link rel="stylesheet" href="{{ asset('assets/css/visit_note_cards.css') }}?v=4">
{{-- أصناف rb-* نفسها التي تستعملها شاشات خط السير --}}
@include('client::Itinerary.components.route-builder-styles')

<style>
    /* Vuexy theme overrides — ضرورية فقط */
    html body .content .content-wrapper { padding: 0 !important; }

    /* الهيدر يظهر لكن بشكل نحيف — نوفّر المساحة بدون فقدان قائمة المستخدم والإشعارات.
       المسافة المحجوزة (padding-top) لازم تساوي: الهامش العلوي + ارتفاع الشريط. */
    html body .header-navbar {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        z-index: 10000 !important;
    }
    html body .header-navbar.floating-nav {
        margin: .35rem .9rem 0 !important;
        min-height: 3rem !important;
    }
    html body .header-navbar .navbar-container {
        min-height: 3rem !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }
    html body .header-navbar-shadow { display: none !important; }

    /* المسافة تحت الرأس = أسفل الشريط الثابت نفسه (`--crm-top` يقيسه السكربت أدناه)،
       و`.content-wrapper` بلا هامش الثيم العلوي (84px) — كان يترك فراغاً فوق الصفحة. */
    html body .content.app-content { overflow: visible !important; padding-top: var(--crm-top, 4.6rem) !important; }
    html body .content.app-content .content-wrapper { margin-top: 0 !important; }
    html body .content.app-content .content-wrapper > .content-header { display: none !important; }

    @media (max-width: 768px) {
        html body .content { margin-right: 0 !important; margin-left: 0 !important; min-height: auto !important; }
        html body .content .content-wrapper { padding: 0 !important; }
        html body .header-navbar.floating-nav { margin: .35rem .5rem 0 !important; }
        body.vertical-layout.vertical-menu-modern.menu-collapsed .app-content { margin-left: 0 !important; padding-top: 3.7rem !important; }
    }

    /* Checkbox — تجاوز ثيم Vuexy */
    .rm-page-wrapper .rm-client-check {
        flex: 0 0 22px !important; width: 22px !important; height: 22px !important;
        min-width: 22px !important; min-height: 22px !important;
        position: relative !important; z-index: 5 !important;
    }
    .rm-page-wrapper .rm-client-check .rm-client-checkbox {
        position: absolute !important; inset: 0 !important; opacity: 0 !important;
        cursor: pointer !important; z-index: 2 !important; margin: 0 !important;
    }

    /* منع الثيم من تصغير خطوط الصفحة */
    .rm-page-wrapper .rm-table,
    .rm-page-wrapper .rm-table td,
    .rm-page-wrapper .rm-table th { font-size: inherit; }
</style>

<!-- Backdrop للموبايل -->
<div class="rm-sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileSidebar()"></div>

<div class="rm-page-wrapper rm-rb crm" id="route-builder" dir="rtl">

@php
    // الجذور شجرات منفصلة، و total_client_count محسوب تراكمياً في الكنترولر
    $totalClientsCount = $regionGroups->sum(
        fn($g) => $g->total_client_count ?? 0
    );

    // عدد الفروع والمناطق والمجموعات في الشجرة المعروضة
    $countTreeNodes = function ($groups) use (&$countTreeNodes) {
        $n = 0;
        foreach ($groups ?? [] as $g) {
            $n += 1 + ($g->childGroups ? $countTreeNodes($g->childGroups) : 0);
        }

        return $n;
    };
    $treeNodesCount = $countTreeNodes($regionGroups);
@endphp

<script>
/** `--crm-top`: أسفل شريط الرأس الثابت + 6px — ارتفاعه يتغيّر بمحتواه (اسم المستخدم، الفرع) */
(function () {
    function syncTop() {
        var nav = document.querySelector('.header-navbar');
        if (!nav || (window.innerWidth && window.innerWidth <= 768)) return;
        var bottom = Math.ceil(nav.getBoundingClientRect().bottom);
        if (bottom > 0) document.documentElement.style.setProperty('--crm-top', (bottom + 6) + 'px');
    }
    syncTop();
    window.addEventListener('load', syncTop);
    window.addEventListener('resize', syncTop);
    var nav = document.querySelector('.header-navbar');
    if (nav && window.ResizeObserver) new ResizeObserver(syncTop).observe(nav);
})();

/** تهيئة Select2 لجميع قوائم الفلترة (ثابتة + شجرة الفروع) */
window.initRmFilterSelect2 = function(context) {
    if (!window.jQuery || !$.fn.select2) return;

    const $scope = context ? $(context) : $('.rm-filters-panel');
    $scope.find('select.select2, select.rm-filter-select2').each(function() {
        const $el = $(this);
        if ($el.data('select2')) {
            $el.select2('destroy');
        }
    });

    $scope.find('select.select2, select.rm-filter-select2').each(function() {
        const $el = $(this);
        const placeholder = $el.data('placeholder')
            || $el.find('option[value=""]').first().text()
            || 'اختر...';

        $el.select2({
            dir: 'rtl',
            width: '100%',
            allowClear: true,
            placeholder: placeholder,
            dropdownCssClass: 'rm-filter-select2-dropdown',
            language: {
                noResults: function() { return 'لا توجد نتائج'; },
                searching: function() { return 'جاري البحث...'; }
            }
        });

        $el.toggleClass('has-value', !!$el.val());
        $el.off('change.select2rm').on('change.select2rm', function() {
            $(this).toggleClass('has-value', !!$(this).val());
        });
    });
};

/**
 * تغيير عرض شجرة العملاء بالسحب — يُحفظ العرض في المتصفح.
 * الاتجاه RTL: الشجرة على اليمين، فالسحب لليسار يوسّعها.
 */
(function() {
    const STORAGE_KEY = 'rmSidebarWidth';
    const DEFAULT_W = 480;
    const MIN_W = 320;

    function maxWidth() {
        // نترك مساحة كافية للوحة التفاصيل
        return Math.max(MIN_W, Math.round(window.innerWidth * 0.62));
    }

    function applyWidth(px, persist) {
        const wrapper = document.querySelector('.rm-page-wrapper');
        if (!wrapper) return;
        const w = Math.min(maxWidth(), Math.max(MIN_W, Math.round(px)));
        wrapper.style.setProperty('--rm-sidebar-w', w + 'px');
        if (persist) {
            try { localStorage.setItem(STORAGE_KEY, String(w)); } catch (e) {}
        }
        return w;
    }

    function restoreWidth() {
        let saved = null;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        applyWidth(saved ? parseInt(saved, 10) || DEFAULT_W : DEFAULT_W, false);
    }

    function initSidebarResizer() {
        restoreWidth();

        const resizer = document.getElementById('sidebarResizer');
        const sidebar = document.getElementById('groupsListContainer');
        const wrapper = document.querySelector('.rm-page-wrapper');
        if (!resizer || !sidebar || !wrapper) return;

        let dragging = false;

        function onMove(clientX) {
            // حافة الشجرة اليمنى ثابتة، فالعرض = المسافة من المؤشر حتى تلك الحافة
            const right = sidebar.getBoundingClientRect().right;
            applyWidth(right - clientX, false);
        }

        function stop() {
            if (!dragging) return;
            dragging = false;
            wrapper.classList.remove('rm-resizing');
            applyWidth(sidebar.getBoundingClientRect().width, true);
        }

        resizer.addEventListener('pointerdown', function(e) {
            if (window.innerWidth <= 768) return;
            dragging = true;
            wrapper.classList.add('rm-resizing');
            resizer.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        resizer.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            onMove(e.clientX);
        });

        resizer.addEventListener('pointerup', stop);
        resizer.addEventListener('pointercancel', stop);
        resizer.addEventListener('lostpointercapture', stop);

        // نقرة مزدوجة → إرجاع للعرض الافتراضي
        resizer.addEventListener('dblclick', function() {
            applyWidth(DEFAULT_W, true);
        });

        // إبقاء العرض ضمن الحدود عند تغيير حجم النافذة
        window.addEventListener('resize', function() {
            applyWidth(sidebar.getBoundingClientRect().width, false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarResizer);
    } else {
        initSidebarResizer();
    }
})();

/** طيّ لوحة الفلاتر — يعمل على الجوال والديسكتوب، وتُحفظ الحالة على الديسكتوب */
(function() {
    const KEY = 'rmFiltersCollapsed';

    function syncIcon(hidden) {
        const icon = document.getElementById('mobileFilterIcon');
        if (!icon) return;
        icon.classList.toggle('fa-chevron-down', hidden);
        icon.classList.toggle('fa-chevron-up', !hidden);
    }

    function setCollapsed(hidden, persist) {
        const body = document.querySelector('.rm-filters-panel-body');
        if (!body) return;
        const isMobile = window.innerWidth <= 768;
        body.classList.toggle(isMobile ? 'rm-mobile-hidden' : 'rm-collapsed', hidden);
        syncIcon(hidden);
        if (persist && !isMobile) {
            try { localStorage.setItem(KEY, hidden ? '1' : '0'); } catch (e) {}
        }
    }

    window.toggleMobileFilters = function() {
        const body = document.querySelector('.rm-filters-panel-body');
        if (!body) return;
        const cls = window.innerWidth <= 768 ? 'rm-mobile-hidden' : 'rm-collapsed';
        setCollapsed(!body.classList.contains(cls), true);
    };

    function init() {
        if (window.innerWidth > 768) {
            let saved = null;
            try { saved = localStorage.getItem(KEY); } catch (e) {}
            setCollapsed(saved === '1', false);
        } else {
            syncIcon(true);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

    <!-- ===== رأس الصفحة — rb-head ===== -->
    <header class="rb-head">
        <div class="rb-head__title">
            <span class="rb-head__icon"><i class="fas fa-handshake"></i></span>
            <div>
                <h1>إدارة علاقات العملاء</h1>
                <p>
                    <span class="crm-head-text">ملف كل عميل في مكان واحد: الملاحظات والزيارات والفواتير والمدفوعات وكشف الحساب</span>
                    @if(!empty($selectedBranchLabel))
                        <span class="rb-badge" title="الفرع المحدد"><i class="fas fa-sitemap"></i> {{ Str::limit($selectedBranchLabel, 36) }}</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="rb-head__actions">
            <div class="crm-nav">
                <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="prevButton" title="العميل السابق">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="nextButton" title="العميل التالي">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            <button type="button" class="rb-btn rb-btn--success" id="bulkAddToItineraryBtn" style="display:none;" onclick="showBulkAddModal()">
                <i class="fas fa-route"></i>
                <span class="d-none d-sm-inline">إضافة لخط السير</span>
                <span class="crm-count"><span id="selectedCount">0</span></span>
            </button>
            <button type="button" class="rb-btn rb-btn--light rb-btn--icon" id="clearSelectionBtn" style="display:none;" onclick="clearAllSelections()" title="إلغاء التحديد">
                <i class="fas fa-times"></i>
            </button>
            <button type="button" class="rb-btn rb-btn--light rb-btn--icon d-md-none" id="mobileToggleBtn" onclick="toggleMobileSidebar()" title="شجرة العملاء">
                <i class="fas fa-list"></i>
            </button>
            <a href="{{ route('clients.create') }}" class="rb-btn rb-btn--primary" title="إضافة عميل جديد">
                <i class="fas fa-user-plus"></i>
                <span class="d-none d-sm-inline">عميل جديد</span>
            </a>
        </div>
    </header>

    <!-- ===== الإحصاءات — rb-stat ===== -->
    <div class="rb-stats crm-stats">
        <div class="rb-stat rb-stat--navy">
            <div>
                <span class="rb-stat__label">إجمالي العملاء</span>
                <strong>{{ number_format($totalClientsCount) }}</strong>
                <span class="rb-stat__hint">{{ !empty($selectedBranchLabel) ? 'في ' . Str::limit($selectedBranchLabel, 28) : 'في كل الفروع' }}</span>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="rb-stat rb-stat--blue">
            <div>
                <span class="rb-stat__label">الظاهرون الآن</span>
                <strong data-crm-mirror="visible">{{ number_format($totalClientsCount) }}</strong>
                <span class="rb-stat__hint">حسب البحث والتصفية</span>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-filter"></i></span>
        </div>
        <div class="rb-stat rb-stat--green">
            <div>
                <span class="rb-stat__label">المحدَّدون</span>
                <strong data-crm-mirror="selected">0</strong>
                <span class="rb-stat__hint">للإضافة الجماعية لخط السير</span>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-check-double"></i></span>
        </div>
        <div class="rb-stat rb-stat--amber">
            <div>
                <span class="rb-stat__label">الفروع والمجموعات</span>
                <strong>{{ number_format($treeNodesCount) }}</strong>
                <span class="rb-stat__hint">في شجرة العملاء</span>
            </div>
            <span class="rb-stat__icon"><i class="fas fa-sitemap"></i></span>
        </div>
    </div>

    <!-- ===== التصفية — rb-form ===== -->
    <div class="rm-filters-panel crm-filters rb-card">
        <div class="rb-picker__title" onclick="toggleMobileFilters()" title="إظهار / إخفاء التصفية">
            <span class="crm-filters__name"><i class="fas fa-sliders-h"></i> تصفية العملاء</span>
            <span class="crm-filters__toggle"><i class="fas fa-chevron-up rm-filters-toggle-icon" id="mobileFilterIcon"></i></span>
        </div>

        <div class="rm-filters-panel-body rm-mobile-hidden">
            <div class="rm-filters-grid-compact">
                <div class="rm-fg-item rm-fg-search">
                    <label class="rm-fg-label" for="searchInput">بحث</label>
                    <div class="rm-filter-field rm-filter-field-search" style="padding:0; border:none; background:transparent; height:auto;">
                        <select id="searchInput" class="select2 rm-filter-select2"
                                data-placeholder="بحث: اسم، هاتف، كود..." aria-label="بحث عن عميل">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <div class="rm-fg-item rm-fg-status">
                    <label class="rm-fg-label" for="statusFilter">الحالة</label>
                    <select id="statusFilter" class="select2 rm-filter-select2"
                            data-placeholder="الكل" aria-label="فلتر الحالة">
                        <option value=""></option>
                        <option value="all">الكل</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                        @if ($legacyStatusOptions->isNotEmpty())
                            <optgroup label="حالات قديمة">
                                @foreach ($legacyStatusOptions as $status)
                                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <div class="rm-fg-item rm-fg-stage">
                    <label class="rm-fg-label" for="stageFilter">الوضع</label>
                    <select id="stageFilter" class="select2 rm-filter-select2"
                            data-placeholder="الكل" aria-label="فلتر الوضع">
                        <option value=""></option>
                        <option value="all">الكل</option>
                        @foreach ($stageOptions as $stage)
                            <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                        @endforeach
                        <option value="none">بلا وضع</option>
                    </select>
                </div>

                {{-- مستويات شجرة الفروع تنسدل داخل نفس الشبكة (display:contents) --}}
                @include('client::partials.branch-cascade-filter')
            </div>
        </div>

        <div class="rm-filters-panel-foot" id="activeFiltersBar" style="display:none;">
            <div class="rm-active-chips" id="activeChips"></div>
            <span class="rm-results-count" id="resultsCount" aria-live="polite">
                <i class="fas fa-users"></i>
                <strong id="resultsVisible">0</strong>/<strong id="resultsTotal">0</strong>
            </span>
            <button type="button" class="rm-clear-all-btn" id="clearAllFiltersBtn">
                <i class="fas fa-broom"></i> مسح الكل
            </button>
        </div>
    </div>

    <!-- ===== المحتوى الرئيسي ===== -->
    <div class="rm-content-area">

        <!-- ===== الشجرة مع التصفية — rb-picker ===== -->
        <aside class="rm-sidebar" id="groupsListContainer">
            <div class="rm-sidebar-header">
                <div class="rm-sidebar-header-top">
                    <div class="rm-sidebar-title">
                        <span class="rm-sidebar-title-icon" aria-hidden="true">
                            <i class="fas fa-sitemap"></i>
                        </span>
                        <div>
                            <h6>شجرة العملاء</h6>
                            <small>افتح الفرع ثم اختر العميل</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rm-sidebar-count">{{ $totalClientsCount }}</span>
                        <button type="button" class="rm-sidebar-close d-md-none" onclick="closeMobileSidebar()" aria-label="إغلاق">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- قائمة المجموعات -->
            <div class="rm-sidebar-scroll">
                @if (!empty($regionGroups) && count($regionGroups) > 0)
                    <div class="rm-accordion rm-branch-tree" id="groupsAccordion">
                        @foreach ($regionGroups as $group)
                            @include('client::partials.group_tree_item', ['group' => $group])
                        @endforeach
                    </div>
                @else
                    <div class="rm-welcome-state">
                        <div class="rm-welcome-illustration">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h5>لا توجد مجموعات</h5>
                        <p>لم يتم إضافة أي مجموعات بعد</p>
                    </div>
                @endif
            </div>
        </aside>

        <!-- ===== مقبض تغيير عرض الشجرة (اسحب لليسار/اليمين · نقرة مزدوجة للإرجاع) ===== -->
        <div class="rm-resizer d-none d-md-block" id="sidebarResizer" role="separator"
             aria-orientation="vertical" aria-label="تغيير عرض شجرة العملاء"
             title="اسحب لتغيير العرض — نقرة مزدوجة للإرجاع للوضع الافتراضي"></div>

        <!-- ===== لوحة التفاصيل ===== -->
        <main class="rm-details-panel" id="clientDetails">

            <!-- زر الرجوع للقائمة على الجوال -->
            <button type="button" class="rm-mobile-back-btn" id="mobileBackBtn" onclick="closeMobileDetail()" style="display:none;">
                <i class="fas fa-arrow-right"></i>
                الرجوع لقائمة العملاء
            </button>

            <div class="rm-loading-overlay" id="loadingOverlay" style="display:none;">
                <div class="text-center">
                    <div class="rm-spinner mb-3"></div>
                    <div class="rm-loading-text">جاري التحميل...</div>
                </div>
            </div>

            <!-- الرسالة الترحيبية -->
            <div id="defaultMessage" class="rm-welcome-state">
                <div class="rm-welcome-illustration">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h5>اختر عميلاً لعرض ملفه</h5>
                <p>انقر على أي عميل من شجرة العملاء لعرض ملاحظاته وزياراته وفواتيره ومدفوعاته وكشف حسابه.</p>
                <ul class="crm-tips">
                    <li>
                        <span class="crm-tips__icon crm-tips__icon--blue"><i class="fas fa-search"></i></span>
                        <div><strong>ضيّق القائمة</strong><small>ابحث بالاسم أو الهاتف أو الكود، أو صفِّ بالحالة والفرع</small></div>
                    </li>
                    <li>
                        <span class="crm-tips__icon crm-tips__icon--green"><i class="fas fa-check-double"></i></span>
                        <div><strong>حدّد عدة عملاء</strong><small>وأضفهم دفعةً واحدة إلى خط السير</small></div>
                    </li>
                    <li>
                        <span class="crm-tips__icon crm-tips__icon--navy"><i class="fas fa-exchange-alt"></i></span>
                        <div><strong>تنقّل بسرعة</strong><small>زرّا السابق والتالي أعلى الصفحة ينقلانك بين العملاء</small></div>
                    </li>
                </ul>
            </div>

            <div id="clientDetailsContent"></div>

        </main>
    </div>

    {{-- بطاقتا «الظاهرون» و«المحدَّدون» تعكسان عدّادات الشاشة نفسها دون لمس منطقها --}}
    <script>
    (function () {
        function mirror(key, value) {
            document.querySelectorAll('[data-crm-mirror="' + key + '"]').forEach(function (el) { el.textContent = value; });
        }

        function watch() {
            var visible = document.getElementById('resultsVisible');
            var total = document.getElementById('resultsTotal');
            var bulk = document.getElementById('bulkAddToItineraryBtn');
            var selected = document.getElementById('selectedCount');

            function syncSelected() {
                mirror('selected', bulk && bulk.style.display !== 'none' ? (selected.textContent || '0') : '0');
            }

            function syncVisible() {
                if (total && total.textContent !== '0') mirror('visible', visible.textContent);
            }

            var text = { childList: true, characterData: true, subtree: true };
            if (visible) new MutationObserver(syncVisible).observe(visible, text);
            if (total) new MutationObserver(syncVisible).observe(total, text);
            if (selected) new MutationObserver(syncSelected).observe(selected, text);
            if (bulk) new MutationObserver(syncSelected).observe(bulk, { attributes: true, attributeFilter: ['style'] });
            syncSelected();
            syncVisible();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', watch);
        } else {
            watch();
        }
    })();
    </script>
    <!-- ===== شريط الإجراءات السفلي على الجوال ===== -->
    <div class="rm-mobile-bottom-bar" id="mobileBottomBar">
        <span class="rm-bottom-count">
            <i class="fas fa-check-circle"></i> <span id="mobileSelectedCount">0</span>
        </span>
        <button type="button" class="rm-mobile-bb-btn rm-mobile-bb-add" onclick="showBulkAddModal()">
            <i class="fas fa-route"></i> إضافة
        </button>
        <button type="button" class="rm-mobile-bb-btn rm-mobile-bb-clear" onclick="clearAllSelections()" aria-label="إلغاء التحديد">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- Containers for Modals -->
<div id="itineraryModalContainer"></div>
<div id="quickAddModalContainer"></div>
<div id="notificationModalContainer"></div>
<div id="bulkAddModalContainer"></div>
<div id="appointmentModalContainer"></div>

@endsection


@section('scripts')
    <script>
        const currentUserId = {{ auth()->id() }};
        console.log('Script loaded successfully! User:', currentUserId);

        /* صلاحيات إجراءات CRM — يُبنى مرة واحدة من السيرفر لأن الإجراءات
           السريعة وأزرار الملاحظات/المواعيد تُولَّد بالكامل في JS من بيانات
           AJAX، فلا يمكن حراستها بـ @@permission داخل Blade مباشرة. */
        window.CRM_PERMS = {
            canAddNote: @json(hasPermission('client_follow_up_add_notes_attachments_appointments_all') || hasPermission('client_follow_up_add_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_add_notes_attachments_appointments_own')),
            canEditAllNotes: @json(hasPermission('client_follow_up_edit_delete_notes_attachments_appointments_all')),
            canEditOwnNotes: @json(hasPermission('client_follow_up_edit_delete_notes_attachments_appointments_own')),
            canViewAppointments: @json(hasPermission('client_follow_up_view_notes_attachments_appointments_all') || hasPermission('client_follow_up_view_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_view_notes_attachments_appointments_own')),
            canAddAppointment: @json(hasPermission('client_follow_up_add_notes_attachments_appointments_all') || hasPermission('client_follow_up_add_notes_attachments_appointments_assigned') || hasPermission('client_follow_up_add_notes_attachments_appointments_own')),
            canAddItinerary: @json(hasPermission('clients_itinerary_add')),
            canSendNotification: @json(hasPermission('clients_crm_send_notification')),
        };

        // ===== القوالب الخاصة بالروابط لتجنب المسارات الثابتة =====
        const clientShowUrlTemplate = "{{ route('clients.show', ['id' => 'CLIENT_ID']) }}";
        const clientEditUrlTemplate = "{{ route('clients.edit', ['id' => 'CLIENT_ID']) }}";
        const clientNoteCreateUrlTemplate = "{{ route('appointment.notes.create', ['id' => 'CLIENT_ID']) }}";
        const clientNoteEditUrlTemplate = "{{ route('clients.notes.edit', ['noteId' => 'NOTE_ID']) }}";
        const clientNotePrintUrlTemplate = "{{ route('clients.notes.print', ['noteId' => 'NOTE_ID']) }}";

        function getClientShowUrl(id) {
            return clientShowUrlTemplate.replace('CLIENT_ID', id);
        }

        function getClientEditUrl(id) {
            return clientEditUrlTemplate.replace('CLIENT_ID', id);
        }

        // ===== نظام تحديد العملاء المتعدد =====
        let selectedClients = []; // قائمة العملاء المحددين
        let selectedGroupId = null; // المجموعة المحددة

        // دالة تحديث/إلغاء تحديد عميل
        function toggleClientSelection(clientId, clientName, isChecked, groupId) {
            if (isChecked) {
                // إضافة للقائمة إذا لم يكن موجوداً
                if (!selectedClients.some(c => c.id == clientId)) {
                    selectedClients.push({ id: clientId, name: clientName, groupId: groupId });
                }
                // تتبع المجموعة المحددة
                if (groupId) {
                    selectedGroupId = groupId;
                }
            } else {
                // إزالة من القائمة
                selectedClients = selectedClients.filter(c => c.id != clientId);
                // تحديث المجموعة المحددة من العملاء المتبقين
                if (selectedClients.length > 0) {
                    selectedGroupId = selectedClients[0].groupId;
                } else {
                    selectedGroupId = null;
                }
            }
            updateSelectionUI();
        }

        // تحديث عرض زر الإضافة وعداد التحديد
        function updateSelectionUI() {
            const bulkBtn = document.getElementById('bulkAddToItineraryBtn');
            const clearBtn = document.getElementById('clearSelectionBtn');
            const countSpan = document.getElementById('selectedCount');
            const mobileBar = document.getElementById('mobileBottomBar');
            const mobileCount = document.getElementById('mobileSelectedCount');

            if (selectedClients.length > 0) {
                bulkBtn.style.display = 'inline-block';
                clearBtn.style.display = 'inline-block';
                countSpan.textContent = selectedClients.length;
                // الشريط السفلي على الجوال
                if (mobileBar) mobileBar.classList.add('show');
                if (mobileCount) mobileCount.textContent = selectedClients.length;
            } else {
                bulkBtn.style.display = 'none';
                clearBtn.style.display = 'none';
                if (mobileBar) mobileBar.classList.remove('show');
            }

            // تحديث حالة الـ checkboxes
            document.querySelectorAll('.client-checkbox').forEach(checkbox => {
                const clientId = checkbox.dataset.clientId;
                checkbox.checked = selectedClients.some(c => c.id == clientId);
            });

            // تحديث حالة "تحديد الكل" لكل مجموعة
            updateGroupSelectAllStates();
        }

        // إلغاء جميع التحديدات
        function clearAllSelections() {
            selectedClients = [];
            selectedGroupId = null;
            updateSelectionUI();
        }

        // ===== تحديد كل عملاء مجموعة معينة =====
        function toggleGroupSelection(groupId, isChecked) {
            // الفلترة تُخفي عناصر العملاء بـ display:none دون أن تمسّ الـ checkbox
            // نفسه، فـ«تحديد كل العملاء» كان يحدّد المخفيّين (غير المطابقين
            // للفلتر) أيضاً. نقتصر هنا على عناصر العميل الظاهرة فعلاً.
            const groupCheckboxes = Array.from(document.querySelectorAll(
                `.client-checkbox[data-group-id="${groupId}"]`
            )).filter(checkbox => {
                const item = checkbox.closest('.client-item');
                return item && item.style.display !== 'none';
            });

            groupCheckboxes.forEach(checkbox => {
                const clientId = checkbox.dataset.clientId;
                const clientName = checkbox.dataset.clientName || '';

                if (isChecked) {
                    if (!selectedClients.some(c => c.id == clientId)) {
                        selectedClients.push({
                            id: clientId,
                            name: clientName,
                            groupId: groupId
                        });
                    }
                } else {
                    selectedClients = selectedClients.filter(c => c.id != clientId);
                }
            });

            // تتبع المجموعة الحالية
            if (isChecked && groupCheckboxes.length > 0) {
                selectedGroupId = groupId;
            } else if (selectedClients.length > 0) {
                selectedGroupId = selectedClients[0].groupId;
            } else {
                selectedGroupId = null;
            }

            updateSelectionUI();

            /* شارة «تحديد كل العملاء» والعدّادات كانت تتحدّث بعد الفلترة بتأخيرٍ
               مقصود (330ms، انظر updateResultsCount) — نقرةٌ على «تحديد كل
               العملاء» خلال تلك النافذة تُحدِّد العدد الصحيح فعلاً (يُحسب هنا من
               الظاهر حالاً لا من الشارة) لكن الشارة نفسها تبقى تعرض الرقم القديم
               للحظة، فيبدو العددان متعارضين. تُحدَّث فوراً هنا بدل الانتظار. */
            if (typeof window.updateGroupCounts === 'function') {
                window.updateGroupCounts();
            }
        }

        // ===== تحديث حالة checkbox "تحديد الكل" لكل مجموعة =====
        function updateGroupSelectAllStates() {
            document.querySelectorAll('.group-select-all-checkbox').forEach(groupCheckbox => {
                const groupId = groupCheckbox.dataset.groupId;
                // الحالة (محدَّد/جزئي) تُحسَب من العملاء الظاهرين وحدهم، حتى تطابق
                // ما يفعله toggleGroupSelection فعلياً عند وجود فلتر نشط
                const clientCheckboxes = Array.from(document.querySelectorAll(
                    `.client-checkbox[data-group-id="${groupId}"]`
                )).filter(checkbox => {
                    const item = checkbox.closest('.client-item');
                    return item && item.style.display !== 'none';
                });

                if (clientCheckboxes.length === 0) {
                    groupCheckbox.checked = false;
                    groupCheckbox.indeterminate = false;
                } else {
                    const checkedCount = Array.from(clientCheckboxes).filter(c => c.checked).length;
                    if (checkedCount === 0) {
                        groupCheckbox.checked = false;
                        groupCheckbox.indeterminate = false;
                    } else if (checkedCount === clientCheckboxes.length) {
                        groupCheckbox.checked = true;
                        groupCheckbox.indeterminate = false;
                    } else {
                        groupCheckbox.checked = false;
                        groupCheckbox.indeterminate = true;
                    }
                }

                // تظليل الصف عند التحديد الكامل
                const wrapper = groupCheckbox.closest('.rm-group-select-all');
                if (wrapper) {
                    wrapper.classList.toggle('all-selected', groupCheckbox.checked);
                }
            });
        }

        // فلتر الفرع يتم من السيرفر عبر branch_id وإعادة تحميل الصفحة
        function filterByBranch(branchId) {
            const url = new URL(@json(route('clients.mang_client')), window.location.origin);
            if (branchId && branchId !== 'all') {
                url.searchParams.set('branch_id', String(branchId));
            }
            window.location.href = url.toString();
        }

        function getActiveBranchFilterLabel() {
            @if(!empty($selectedBranchLabel))
                return @json($selectedBranchLabel);
            @endif
            const first = document.querySelector('#branchCascadeContainer select[data-level="0"]');
            if (!first || !first.value) return '';
            return first.options[first.selectedIndex]?.text?.trim() || '';
        }

        function clearBranchCascadeFilter() {
            filterByBranch('');
        }

        // ===== شجرة الفروع في الشريط الجانبي =====
        function getTerritoryItems() {
            return document.querySelectorAll('#groupsAccordion .rm-accordion-item[data-group-id]');
        }

        function getBranchNodes() {
            return document.querySelectorAll('#groupsAccordion .rm-branch-node');
        }

        function expandCollapseElement(collapseEl, button, open) {
            if (!collapseEl || !button) return;
            if (open) {
                collapseEl.classList.add('show');
                button.setAttribute('aria-expanded', 'true');
            } else {
                collapseEl.classList.remove('show');
                button.setAttribute('aria-expanded', 'false');
            }
        }

        function expandBranchAncestors(element) {
            let container = element.closest('.rm-branch-children');
            while (container) {
                container.classList.add('show');
                const branchNode = container.closest('.rm-branch-node');
                if (!branchNode) {
                    break;
                }
                const header = branchNode.querySelector(':scope > .rm-branch-header');
                if (header) {
                    header.setAttribute('aria-expanded', 'true');
                }
                container = branchNode.parentElement ? branchNode.parentElement.closest('.rm-branch-children') : null;
            }
        }

        function branchNodeHasVisibleDescendants(branchNode) {
            const childBranches = branchNode.querySelectorAll(':scope > .rm-branch-children > .rm-branch-node');
            if (childBranches.length > 0) {
                return true;
            }

            const territories = branchNode.querySelectorAll('.rm-territory-item[data-group-id]');
            return Array.from(territories).some(item => item.style.display !== 'none');
        }

        function updateBranchNodeVisibility() {
            const branchNodes = Array.from(getBranchNodes()).reverse();
            branchNodes.forEach(branchNode => {
                const hasChildBranches = branchNode.querySelectorAll(':scope > .rm-branch-children > .rm-branch-node').length > 0;
                if (hasChildBranches) {
                    branchNode.style.display = '';
                    return;
                }
                branchNode.style.display = branchNodeHasVisibleDescendants(branchNode) ? '' : 'none';
            });
        }

        // clientsCache/CACHE_DURATION/isCacheValid صارت في الجزء المشترك (crm_shared_scripts_details) — يستعملها selectClient المشترك أيضاً
        const groupClientsCache = new Map(); // Cache لعملاء المجموعات (خاص بشجرة سطح المكتب)

        // دالة مبسطة لتحميل العملاء
        window.loadGroupClientsNow = function(groupId) {
            console.log('loadGroupClientsNow called with groupId:', groupId);
            const collapseElement = document.getElementById(`collapseGroup${groupId}`);

            // إذا تم تحميلها سابقاً، لا تحمل مرة أخرى
            if (collapseElement && collapseElement.dataset.loaded === 'true') {
                console.log('Already loaded, skipping...');
                return;
            }

            console.log('Setting timeout to load clients...');
            setTimeout(function() {
                loadGroupClients(groupId, null);
            }, 150);
        };

        // دالة لتحميل عملاء المجموعة عند فتح Accordion
        function loadGroupClients(groupId, button) {
            console.log('loadGroupClients called with groupId:', groupId);

            const collapseElement = document.getElementById(`collapseGroup${groupId}`);
            const containerElement = document.getElementById(`groupClientsContainer${groupId}`);

            console.log('collapseElement:', collapseElement);
            console.log('containerElement:', containerElement);

            // التحقق إذا تم تحميل البيانات مسبقاً
            if (collapseElement.dataset.loaded === 'true') {
                console.log('Data already loaded, skipping...');
                return;
            }

            // التحقق من الـ cache
            const cachedData = groupClientsCache.get(groupId);
            if (cachedData && isCacheValid(cachedData.timestamp)) {
                console.log('Using cached data for group:', groupId);
                renderGroupClients(groupId, cachedData.data);
                collapseElement.dataset.loaded = 'true';
                return;
            }

            console.log('Fetching data from server...');

            // جلب البيانات عبر AJAX
            $.ajax({
                url: '{{ route("clients.get-group-clients") }}',
                method: 'GET',
                data: { group_id: groupId },
                timeout: 20000,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    console.log('AJAX Success Response:', response);

                    if (response.success && response.clients) {
                        console.log('Clients count:', response.clients.length);

                        // حفظ في الـ cache
                        groupClientsCache.set(groupId, {
                            data: response.clients,
                            timestamp: Date.now()
                        });

                        renderGroupClients(groupId, response.clients);
                        collapseElement.dataset.loaded = 'true';
                    } else {
                        console.error('Response not successful or no clients:', response);
                        containerElement.innerHTML = `
                            <div class="p-3 text-center text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p class="mb-0 small">حدث خطأ في تحميل العملاء</p>
                            </div>
                        `;
                    }
                },
                error: function(xhr, textStatus) {
                    console.error('AJAX Error:', {xhr, textStatus});

                    let errorMsg = 'حدث خطأ في تحميل العملاء';
                    if (textStatus === 'timeout') {
                        errorMsg = 'انتهت مهلة التحميل. الرجاء المحاولة مرة أخرى.';
                    }

                    containerElement.innerHTML = `
                        <div class="p-3 text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p class="mb-0 small">${errorMsg}</p>
                            <button class="btn btn-sm btn-primary mt-2" onclick="retryLoadGroup(${groupId})">
                                <i class="fas fa-sync-alt"></i> إعادة المحاولة
                            </button>
                        </div>
                    `;
                }
            });
        }

        // دالة لإعادة المحاولة
        function retryLoadGroup(groupId) {
            const collapseElement = document.getElementById(`collapseGroup${groupId}`);
            const button = document.querySelector(`[data-bs-target="#collapseGroup${groupId}"]`);
            collapseElement.dataset.loaded = 'false';
            loadGroupClients(groupId, button);
        }

        // دالة لعرض عملاء المجموعة
        function renderGroupClients(groupId, clients) {
            console.log('renderGroupClients called with:', {groupId, clientsCount: clients?.length});

            const container = document.getElementById(`groupClientsContainer${groupId}`);

            if (!container) {
                console.error('Container not found:', `groupClientsContainer${groupId}`);
                return;
            }

            if (!clients || clients.length === 0) {
                console.log('No clients to display');
                container.innerHTML = `
                    <div class="rm-empty-group">
                        <i class="fas fa-inbox d-block mb-1" style="font-size:1.5rem; opacity:0.4;"></i>
                        <p>لا يوجد عملاء في هذه المجموعة</p>
                    </div>
                `;
                return;
            }

            const avatarColors = ['avatar-c1','avatar-c2','avatar-c3','avatar-c4','avatar-c5','avatar-c6','avatar-c7','avatar-c8'];
            // سطر "تحديد كل العملاء" للمجموعة
            let html = `
                <div class="rm-group-select-all" onclick="event.stopPropagation();">
                    <div class="rm-client-check rm-group-check-wrap">
                        <input class="rm-client-checkbox group-select-all-checkbox"
                               type="checkbox"
                               id="groupSelectAll${groupId}"
                               data-group-id="${groupId}"
                               onchange="toggleGroupSelection(${groupId}, this.checked)">
                        <span class="rm-client-check-face" aria-hidden="true"></span>
                    </div>
                    <label for="groupSelectAll${groupId}" class="rm-group-select-label">
                        <i class="fas fa-check-double"></i>
                        تحديد كل العملاء
                        <span class="rm-group-select-count">(${clients.length})</span>
                    </label>
                </div>
            `;
            clients.forEach(client => {
                const clientName = client.trade_name || client.name || '?';
                const escapedName = clientName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const isChecked = selectedClients.some(c => c.id == client.id) ? 'checked' : '';
                const initials = clientName.charAt(0).toUpperCase();
                const colorClass = avatarColors[client.id % 8];
                const statusBadge = client.status_color && client.status_name
                    ? `<span class="rm-client-status-badge" style="background-color: ${client.status_color};">${client.status_name}</span>`
                    : '';
                // وضع العميل (مرحلة التسجيل) — بلا وضعٍ يظهر بدلاً من الشارة الملوّنة
                // شارةٌ رماديّة صريحة، لا شارة غائبة
                const stageBadge = client.stage_color && client.stage_name
                    ? `<span class="rm-client-status-badge" style="background-color: ${client.stage_color};" title="وضع العميل">${client.stage_name}</span>`
                    : `<span class="rm-client-status-badge rm-client-status-badge--muted" title="وضع العميل">بدون وضع</span>`;
                // فئة العميل (هدف العميل)
                const targetBadge = client.target_color && client.target_name
                    ? `<span class="rm-client-status-badge" style="background-color: ${client.target_color};" title="فئة العميل">${client.target_name}</span>`
                    : '';
                const phoneHtml = client.phone
                    ? `<span>${client.phone}</span><span class="rm-meta-sep">|</span>`
                    : '';
                const codeHtml = `<span>كود: ${client.code || '-'}</span>`;

                html += `
                    <div class="rm-client-item client-item"
                        data-client-id="${client.id}"
                        data-status="${client.status_id || 'no-status'}"
                        data-stage="${client.stage_id || 'none'}"
                        data-group-id="${groupId}"
                        data-branch-id="${client.branch_id || ''}"
                        onclick="selectClient(${client.id})">

                        <!-- صف علوي: مربع التحديد + الأيقونة + الاسم -->
                        <div class="rm-client-row-top">
                            <!-- مربع التحديد -->
                            <div class="rm-client-check" onclick="event.stopPropagation();">
                                <input class="rm-client-checkbox client-checkbox" type="checkbox"
                                       id="client_${client.id}"
                                       data-client-id="${client.id}"
                                       data-client-name="${escapedName}"
                                       data-group-id="${groupId}"
                                       ${isChecked}
                                       onchange="toggleClientSelection(${client.id}, '${escapedName}', this.checked, ${groupId})">
                                <span class="rm-client-check-face" aria-hidden="true"></span>
                            </div>

                            <!-- أيقونة العميل -->
                            <a href="${getClientShowUrl(client.id)}" target="_blank" onclick="event.stopPropagation();" class="rm-client-avatar ${colorClass}" title="عرض بيانات العميل">${initials}</a>

                            <!-- اسم العميل -->
                            <div class="rm-client-name">${clientName}</div>
                        </div>

                        <!-- صف سفلي: البادج + الكود والجوال -->
                        <div class="rm-client-row-bottom">
                            <!-- البادج -->
                            <div class="rm-client-status">
                                ${statusBadge}
                                ${stageBadge}
                                ${targetBadge}
                            </div>

                            <!-- الكود والجوال -->
                            <div class="rm-client-meta">
                                ${phoneHtml}
                                ${codeHtml}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            console.log('Rendered', clients.length, 'clients');

            // تحديث حالة "تحديد الكل" بعد الرسم
            updateGroupSelectAllStates();
            // عملاءُ هذه المجموعة وصلوا بعد الفلترة: يُفلتَرون وتُعاد العدّادات
            if (window.rmApplyFilters) window.rmApplyFilters();
        }

        // دالة لاختيار الفرع وعرض تفاصيله
        function selectBranch(branchId) {
            // Store the selected branch ID for navigation
            window.currentBranchId = branchId;

            // Get the branch data from the DOM
            const branchElement = document.querySelector(`[data-branch-id="${branchId}"]`);
            const branchButton = branchElement?.querySelector('.accordion-button');

            if (!branchButton) return;

            // إخفاء الرسالة الافتراضية
            const defaultMessage = document.getElementById('defaultMessage');
            const branchDetailsSection = document.getElementById('branchDetailsSection');

            if (defaultMessage) {
                defaultMessage.style.display = 'none';
            }
            if (branchDetailsSection) {
                branchDetailsSection.style.display = 'block';
            }

            // جلب البيانات من العناصر المرئية
            const branchName = branchButton.querySelector('span')?.textContent || 'غير معروف';

            // يمكنك إضافة AJAX هنا لجلب تفاصيل الفرع من الـ Server
            // للآن سنستخدم البيانات المتاحة

            // عرض بيانات الفرع
            document.getElementById('branchName').textContent = branchName;
            document.getElementById('branchCode').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchPhone').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchMobile').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchAddress').textContent = '--'; // يمكن إضافة من DB
            document.getElementById('branchCity').textContent = '--'; // يمكن إضافة من DB

            // عد الموظفين والمجموعات
            const groupsAccordion = document.getElementById(`groupsAccordion${branchId}`);
            const groupsCount = groupsAccordion ? groupsAccordion.querySelectorAll('.rm-accordion-item').length : 0;

            document.getElementById('branchEmployees').textContent = '0'; // يمكن إضافة من DB
            document.getElementById('branchGroups').textContent = groupsCount;
        }

        // دالة wrapper للنقر على العميل
        function selectClientNow(clientId) {
            console.log('selectClientNow called:', clientId);
            selectClient(clientId);
        }

        @include('client::partials.crm_shared_scripts_details')
        // دالة البحث والفلترة المحسنة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - Setting up event listeners...');

            if (typeof window.initRmFilterSelect2 === 'function') {
                window.initRmFilterSelect2(document.querySelector('.rm-filters-panel'));
            }
            if (typeof window.bootClientBranchCascade === 'function') {
                window.bootClientBranchCascade();
            }

            // إبراز الـ select عند اختيار قيمة (لون نص أغمق)
            document.querySelectorAll('.rm-filter-select').forEach(sel => {
                const sync = () => sel.classList.toggle('has-value', !!sel.value);
                sync();
                sel.addEventListener('change', sync);
            });

            // Event delegation للعملاء - لا يحتاج onclick
            document.addEventListener('click', function(e) {
                // تجاهل النقر على checkbox أو أيقونة العميل
                if (e.target.closest('.rm-client-check') || e.target.closest('.rm-client-avatar')) return;
                const clientItem = e.target.closest('.client-item');
                if (clientItem && clientItem.dataset.clientId) {
                    const clientId = parseInt(clientItem.dataset.clientId);
                    console.log('Client item clicked:', clientId);
                    selectClientNow(clientId);
                }
            });

            // إضافة event listeners لأزرار الفروع
            document.querySelectorAll('[data-bs-toggle="collapse"][data-branch-id]').forEach(button => {
                button.addEventListener('click', function() {
                    const branchId = this.getAttribute('data-branch-id');
                    console.log('Branch clicked:', branchId);
                    selectBranch(branchId);
                });
            });

            // إضافة event listeners لجميع المجموعات
            // نستخدم حدث Bootstrap "show.bs.collapse" بدلاً من click حتى لا نتداخل مع Bootstrap
            const groupButtons = document.querySelectorAll('[data-bs-toggle="collapse"][data-group-id]');
            console.log('Found group buttons:', groupButtons.length);

            groupButtons.forEach(button => {
                const groupId = button.getAttribute('data-group-id');
                const collapseEl = document.getElementById(`collapseGroup${groupId}`);
                if (!collapseEl) return;

                // تحميل العملاء عند أول فتح للمجموعة فقط
                collapseEl.addEventListener('show.bs.collapse', function() {
                    console.log('Group opening:', groupId);
                    loadGroupClients(groupId, button);
                });
            });

            // المجموعات تبدأ مطوية - المستخدم يفتحها يدوياً

            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const stageFilter = document.getElementById('stageFilter');
            const groupFilter = document.getElementById('groupFilter');
            const mobileToggleBtn = document.getElementById('mobileToggleBtn');
            const expandAllBtn = document.getElementById('expandAllBranches');
            const collapseAllBtn = document.getElementById('collapseAllBranches');

            // دالة فتح جميع الفروع والمجموعات
            if (expandAllBtn) {
                expandAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('#groupsAccordion .rm-group-header, #groupsAccordion .rm-branch-header').forEach(button => {
                        const target = button.getAttribute('data-bs-target');
                        if (target) {
                            const collapse = document.querySelector(target);
                            expandCollapseElement(collapse, button, true);
                        }
                    });
                });
            }

            // دالة طي جميع الفروع والمجموعات
            if (collapseAllBtn) {
                collapseAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('#groupsAccordion .rm-group-header, #groupsAccordion .rm-branch-header').forEach(button => {
                        const target = button.getAttribute('data-bs-target');
                        if (target) {
                            const collapse = document.querySelector(target);
                            expandCollapseElement(collapse, button, false);
                        }
                    });
                });
            }

            // دالة الفلترة المركبة مع تحسين الأداء
            let filterTimeout;
            function applyFilters() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    const statusValue = statusFilter ? statusFilter.value.toString() : '';
                    const stageValue = stageFilter ? stageFilter.value.toString() : '';
                    const groupValue = groupFilter ? groupFilter.value.toString() : '';
                    const searchClientId = ($searchInput && $searchInput.length) ? ($searchInput.val() || '') : '';
                    const searchGroupId = searchClientId && $searchInput.data('selected-group-id') ? $searchInput.data('selected-group-id').toString() : '';

                    console.log('Filtering with - Status:', statusValue, 'Group:', groupValue, 'SearchClient:', searchClientId);

                    const clientItems = document.querySelectorAll('.client-item');
                    const territoryItems = getTerritoryItems();

                    console.log('Found client items:', clientItems.length);
                    console.log('Found territory items:', territoryItems.length);

                    // استخدام DocumentFragment لتحسين الأداء
                    requestAnimationFrame(() => {
                        // فلترة المناطق (المجموعات) أولاً إذا تم اختيار مجموعة معينة أو عميل
                        if (groupValue !== '' || searchGroupId !== '') {
                            territoryItems.forEach(accordionItem => {
                                const accordionGroupId = accordionItem.getAttribute('data-group-id');
                                let shouldShow = true;
                                if (groupValue !== '' && accordionGroupId !== groupValue) shouldShow = false;
                                if (searchGroupId !== '' && accordionGroupId !== searchGroupId) shouldShow = false;

                                if (shouldShow) {
                                    accordionItem.style.display = '';
                                    const button = accordionItem.querySelector('.rm-group-header');
                                    const collapse = accordionItem.querySelector('.accordion-collapse');
                                    expandCollapseElement(collapse, button, true);
                                    expandBranchAncestors(accordionItem);
                                } else {
                                    accordionItem.style.display = 'none';
                                }
                            });
                        } else {
                            territoryItems.forEach(accordionItem => {
                                accordionItem.style.display = '';
                            });
                        }

                        // فلترة العملاء داخل المجموعات المرئية
                        clientItems.forEach(item => {
                            let showItem = true;

                            // فلترة بالعميل المبحوث
                            if (searchClientId !== '' && showItem) {
                                const clientId = (item.getAttribute('data-client-id') || '').toString();
                                if (clientId !== searchClientId) {
                                    showItem = false;
                                }
                            }

                            // فلترة الحالة - التحقق من data-status
                            if (statusValue !== '' && showItem) {
                                const clientStatus = (item.getAttribute('data-status') || '').toString();
                                if (clientStatus !== statusValue) {
                                    showItem = false;
                                }
                            }

                            // فلترة الوضع — مستقلّةٌ عن الحالة، و`none` = بلا وضع
                            if (stageValue !== '' && showItem) {
                                const clientStage = (item.getAttribute('data-stage') || 'none').toString();
                                if (clientStage !== stageValue) {
                                    showItem = false;
                                }
                            }

                            // فلترة المجموعة - التحقق من data-group-id
                            if (groupValue !== '' && showItem) {
                                const clientGroup = (item.getAttribute('data-group-id') || '').toString();
                                if (clientGroup !== groupValue) {
                                    showItem = false;
                                }
                            }

                            item.style.display = showItem ? '' : 'none';
                        });

                        // تحديث إظهار/إخفاء المناطق بناءً على العملاء المرئيين (فقط إذا لم يتم اختيار مجموعة)
                        if (groupValue === '') {
                            territoryItems.forEach(accordionItem => {
                                const clientsInGroup = accordionItem.querySelectorAll('.client-item');
                                let hasVisibleClients = false;

                                clientsInGroup.forEach(client => {
                                    if (client.style.display !== 'none') {
                                        hasVisibleClients = true;
                                    }
                                });

                                // إذا كان هناك فلتر حالة أو وضع أو بحث، أخفف المناطق الفارغة
                                if (statusValue !== '' || stageValue !== '' || searchClientId !== '') {
                                    accordionItem.style.display = hasVisibleClients ? '' : 'none';

                                    if (hasVisibleClients) {
                                        const button = accordionItem.querySelector('.rm-group-header');
                                        const collapse = accordionItem.querySelector('.accordion-collapse');
                                        expandCollapseElement(collapse, button, true);
                                        expandBranchAncestors(accordionItem);
                                    }
                                }
                            });
                        }

                        updateBranchNodeVisibility();
                        // حالة «تحديد الكل» تُبنى على الظاهرين وحدهم، فتتغيّر مع الفلتر
                        // حتى لو لم يتغيّر التحديد نفسه
                        updateGroupSelectAllStates();
                        /* العدّادات من هنا، بعد الإخفاء نفسه: مؤقّتُ 330ms المستقلّ كان
                           يسبق هذا الإطار أحياناً (شجرةٌ بآلاف العملاء، أو تحميل الصفحة
                           مع فلترٍ محفوظ) فيعدّ قبل الفلترة ويُبقي الإجماليات كاملة. */
                        updateResultsCount();
                    });
                }, 300); // تأخير 300ms لتقليل عدد الاستدعاءات
            }
            // `renderGroupClients` خارج هذا النطاق: مجموعةٌ تُحمَّل لاحقاً تُفلتَر وتُعَدّ عبره
            window.rmApplyFilters = applyFilters;

            const $searchInput = $('#searchInput');
            if ($searchInput.length) {
                $searchInput.select2({
                    ajax: {
                        url: "{{ route('crm.search-client-node') }}",
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return { results: data.results };
                        },
                        cache: true
                    },
                    placeholder: 'بحث: اسم، هاتف، كود...',
                    minimumInputLength: 2,
                    dir: 'rtl',
                    allowClear: true
                });

                $searchInput.on('select2:select', function (e) {
                    var data = e.params.data;
                    if (data.group_id) {
                        $searchInput.data('selected-group-id', data.group_id);
                    }
                    
                    // استدعاء الفلترة لإخفاء الآخرين فوراً
                    applyFilters();

                    if (data.group_id) {
                        const accordionItem = document.querySelector(`.rm-accordion-item[data-group-id="${data.group_id}"]`);
                        if (accordionItem) {
                            const button = accordionItem.querySelector('.rm-group-header');
                            const collapse = accordionItem.querySelector('.accordion-collapse');
                            if (collapse && button) {
                                // طلب تحميل العملاء إذا لم يكونوا محملين مسبقاً
                                loadGroupClientsNow(data.group_id);
                                
                                if (!collapse.classList.contains('show')) {
                                    expandCollapseElement(collapse, button, true);
                                    expandBranchAncestors(accordionItem);
                                }
                                
                                // انتظار تحميل العملاء عبر AJAX
                                setTimeout(() => {
                                    selectClientNow(data.id);
                                    applyFilters(); // تطبيق الفلترة مجدداً بعد التحميل
                                    const clientEl = document.querySelector(`.client-item[data-client-id="${data.id}"]`);
                                    if (clientEl) {
                                        clientEl.scrollIntoView({behavior: 'smooth', block: 'center'});
                                    }
                                    // عرض التفاصيل على الجوال
                                    if (window.innerWidth <= 768) {
                                        openMobileDetail();
                                    }
                                }, 1500); // زيادة الوقت قليلاً لضمان التحميل
                            }
                        }
                    }
                });

                $searchInput.on('change', function() {
                    if (!$searchInput.val()) {
                        $searchInput.removeData('selected-group-id');
                    }
                    toggleSearchClearVisibility();
                    applyFilters();
                });
            }

            /* «الكل» عنصرٌ ظاهرٌ في القائمة (select2 يُخفي الخيار الفارغ لأنه نصُّ
               التلميح): اختيارُه يمسح الفلتر نفسه، فتبقى القيمة الفارغة وحدها تعني
               «بلا فلتر» في كل ما يقرأ القائمة (الفلترة، الشارات، الحفظ). */
            [statusFilter, stageFilter].forEach(sel => {
                if (!sel || !window.jQuery) return;
                $(sel).on('change.rmall', function () {
                    if (this.value === 'all') {
                        $(this).val('').trigger('change');
                    }
                });
            });

            [statusFilter, stageFilter].forEach(sel => {
                if (!sel) return;
                sel.addEventListener('change', applyFilters);
                if (window.jQuery) {
                    $(sel).on('change.select2filter', applyFilters);
                }
            });
            if (groupFilter) {
                groupFilter.addEventListener('change', applyFilters);
                if (window.jQuery) {
                    $(groupFilter).on('change.select2filter', applyFilters);
                }
            }

            // ===== تجربة المستخدم: فلاتر نشطة + عداد + اختصار + استمرارية =====
            const activeBar      = document.getElementById('activeFiltersBar');
            const activeChips    = document.getElementById('activeChips');
            const resultsCountEl = document.getElementById('resultsCount');
            const resultsVisible = document.getElementById('resultsVisible');
            const resultsTotal   = document.getElementById('resultsTotal');
            const clearAllBtn    = document.getElementById('clearAllFiltersBtn');
            const searchClearBtn = document.getElementById('searchClearBtn');
            const searchKbd      = document.getElementById('searchKbd');
            const FILTERS_KEY    = 'rmClientFilters_v1';

            // ===== استرجاع الفلاتر المحفوظة من جلسة سابقة =====
            try {
                const saved = JSON.parse(localStorage.getItem(FILTERS_KEY) || '{}');
                if (saved && typeof saved === 'object') {
                    if (saved.search && searchInput) searchInput.value = saved.search;
                    /* حالةٌ محفوظةٌ لم تعد في القائمة (صارت «وضعاً» مثلاً) تُسقَط لا تُفرَض:
                       قيمةٌ غير موجودة كانت تُخفي العملاء كلّهم بلا شارةٍ تشرح السبب. */
                    const hasOption = (sel, v) => sel && [...sel.options].some(o => o.value === String(v));
                    if (saved.status && hasOption(statusFilter, saved.status)) {
                        statusFilter.value = saved.status;
                        if (window.jQuery) $(statusFilter).val(saved.status).trigger('change.select2');
                    }
                    if (saved.stage && hasOption(stageFilter, saved.stage)) {
                        stageFilter.value = saved.stage;
                        if (window.jQuery) $(stageFilter).val(saved.stage).trigger('change.select2');
                    }
                    if (saved.group && groupFilter) {
                        groupFilter.value = saved.group;
                        if (window.jQuery) $(groupFilter).val(saved.group).trigger('change.select2');
                    }
                    document.querySelectorAll('.rm-filter-select').forEach(sel => {
                        sel.classList.toggle('has-value', !!sel.value);
                    });
                }
            } catch (e) { /* تجاهل أخطاء التحليل */ }

            // ===== حفظ الفلاتر =====
            function persistFilters() {
                try {
                    localStorage.setItem(FILTERS_KEY, JSON.stringify({
                        search: searchInput ? searchInput.value : '',
                        status: statusFilter ? statusFilter.value : '',
                        stage:  stageFilter  ? stageFilter.value  : '',
                        group:  groupFilter  ? groupFilter.value  : ''
                    }));
                } catch (e) {}
            }

            // ===== رسم شارات (chips) للفلاتر النشطة =====
            function getSelectLabel(selectEl) {
                if (!selectEl || !selectEl.options || selectEl.selectedIndex < 0) return '';
                return selectEl.options[selectEl.selectedIndex].text.trim();
            }

            function renderActiveChips() {
                if (!activeChips || !activeBar) return;
                const chips = [];

                if (searchInput && searchInput.value && searchInput.value.trim()) {
                    chips.push({
                        key: 'search',
                        label: 'بحث',
                        value: getSelectLabel(searchInput)
                    });
                }
                if (statusFilter && statusFilter.value) {
                    chips.push({
                        key: 'status',
                        label: 'الحالة',
                        value: getSelectLabel(statusFilter)
                    });
                }
                if (stageFilter && stageFilter.value) {
                    chips.push({
                        key: 'stage',
                        label: 'الوضع',
                        value: getSelectLabel(stageFilter)
                    });
                }
                const branchLabel = typeof getActiveBranchFilterLabel === 'function'
                    ? getActiveBranchFilterLabel()
                    : '';
                if (branchLabel) {
                    chips.push({
                        key: 'branch',
                        label: 'الفرع',
                        value: branchLabel
                    });
                }
                if (groupFilter && groupFilter.value) {
                    chips.push({
                        key: 'group',
                        label: 'المجموعة',
                        value: getSelectLabel(groupFilter)
                    });
                }

                activeChips.innerHTML = chips.map(c => `
                    <button type="button" class="rm-filter-chip" data-filter-key="${c.key}" title="إزالة هذا الفلتر">
                        <span class="rm-chip-label">${c.label}:</span>
                        <span class="rm-chip-value">${escapeHtml(c.value)}</span>
                        <span class="rm-chip-x" aria-hidden="true"><i class="fas fa-times"></i></span>
                    </button>
                `).join('');

                activeBar.style.display = chips.length > 0 ? 'flex' : 'none';
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // ===== تحديث عداد النتائج + رسالة "لا نتائج" =====
            function updateResultsCount() {
                const allItems = document.querySelectorAll('.client-item');
                const visible  = Array.from(allItems).filter(i => i.style.display !== 'none').length;
                if (resultsVisible) resultsVisible.textContent = visible;
                if (resultsTotal)   resultsTotal.textContent   = allItems.length;
                if (resultsCountEl) resultsCountEl.classList.toggle('is-zero', visible === 0 && allItems.length > 0);

                renderNoResultsHint(visible, allItems.length);

                // العدد الإجمالي أعلى الشجرة — كان ثابتاً من السيرفر فلا يتغيّر مع الفلتر
                const sidebarCount = document.querySelector('.rm-sidebar-count');
                if (sidebarCount) sidebarCount.textContent = visible;

                updateGroupCounts();
            }

            /* عدّادا كل مجموعة — شارة رأسها (تراكميّ: هي وكل ما تحتها) وشارة
               «تحديد كل العملاء» (عملاؤها المباشرون وحدهم) — كانا ثابتين من
               السيرفر عند أول تحميل فيبقيان يعرضان إجمالي المجموعة نفسه بعد
               أي فلترة، بلا علاقة بعدد الظاهرين فعلاً. */
            function updateGroupCounts() {
                document.querySelectorAll('.rm-accordion-item[data-group-id]').forEach(item => {
                    const groupId = item.getAttribute('data-group-id');

                    const rollupItems = item.querySelectorAll('.client-item');
                    const rollupVisible = Array.from(rollupItems).filter(el => el.style.display !== 'none').length;
                    const headerCount = item.querySelector(':scope > .rm-group-header .rm-group-count');
                    if (headerCount) headerCount.textContent = rollupVisible;

                    const directContainer = document.getElementById('groupClientsContainer' + groupId);
                    if (directContainer) {
                        const directItems = directContainer.querySelectorAll('.client-item');
                        const directVisible = Array.from(directItems).filter(el => el.style.display !== 'none').length;
                        const selectCount = directContainer.querySelector('.rm-group-select-count');
                        if (selectCount) selectCount.textContent = '(' + directVisible + ')';
                    }
                });
            }
            // على window: toggleGroupSelection معرّفةٌ خارج نطاق DOMContentLoaded
            // هذا (يستدعيها onclick= في HTML)، ولا ترى هذه الدالة إلا عبر window
            window.updateGroupCounts = updateGroupCounts;

            function hasActiveFilters() {
                return !!(
                    (searchInput && searchInput.value.trim()) ||
                    (statusFilter && statusFilter.value) ||
                    (stageFilter && stageFilter.value) ||
                    (typeof getActiveBranchFilterLabel === 'function' && getActiveBranchFilterLabel()) ||
                    (groupFilter && groupFilter.value)
                );
            }

            function renderNoResultsHint(visible, total) {
                const scrollEl = document.querySelector('.rm-sidebar-scroll');
                if (!scrollEl) return;
                let hint = scrollEl.querySelector('#rmNoResults');

                if (visible === 0 && total > 0 && hasActiveFilters()) {
                    if (!hint) {
                        hint = document.createElement('div');
                        hint.id = 'rmNoResults';
                        hint.className = 'rm-no-results';
                        hint.innerHTML = `
                            <i class="fas fa-search-minus"></i>
                            <h6>لا توجد نتائج مطابقة</h6>
                            <p>جرّب تعديل الفلاتر أو مسحها لعرض جميع العملاء</p>
                            <button type="button" class="rm-clear-all-btn" data-action="clear-all">
                                <i class="fas fa-broom"></i> مسح جميع الفلاتر
                            </button>
                        `;
                        scrollEl.prepend(hint);
                    }
                } else if (hint) {
                    hint.remove();
                }
            }

            // ===== مسح فلتر واحد / مسح الكل =====
            function clearOneFilter(key) {
                if (key === 'search' && searchInput) {
                    searchInput.value = '';
                    if (window.jQuery) $(searchInput).val(null).trigger('change');
                }
                if (key === 'status' && statusFilter) {
                    statusFilter.value = '';
                    if (window.jQuery) $(statusFilter).val('').trigger('change');
                }
                if (key === 'stage' && stageFilter) {
                    stageFilter.value = '';
                    if (window.jQuery) $(stageFilter).val('').trigger('change');
                }
                if (key === 'branch') {
                    if (typeof clearBranchCascadeFilter === 'function') {
                        clearBranchCascadeFilter();
                        return;
                    }
                    if (typeof filterByBranch === 'function') {
                        filterByBranch('');
                        return;
                    }
                }
                if (key === 'group' && groupFilter) {
                    groupFilter.value = '';
                    if (window.jQuery) $(groupFilter).val('').trigger('change');
                }
                afterFilterChange();
            }

            function clearAllFilters() {
                const hadBranch = typeof getActiveBranchFilterLabel === 'function' && getActiveBranchFilterLabel();
                if (searchInput) {
                    searchInput.value = '';
                    if (window.jQuery) $(searchInput).val(null).trigger('change');
                }
                if (statusFilter) {
                    statusFilter.value = '';
                    if (window.jQuery) $(statusFilter).val('').trigger('change');
                }
                if (stageFilter) {
                    stageFilter.value = '';
                    if (window.jQuery) $(stageFilter).val('').trigger('change');
                }
                if (groupFilter) {
                    groupFilter.value = '';
                    if (window.jQuery) $(groupFilter).val('').trigger('change');
                }
                if (hadBranch) {
                    if (typeof clearBranchCascadeFilter === 'function') {
                        clearBranchCascadeFilter();
                    } else if (typeof filterByBranch === 'function') {
                        filterByBranch('');
                    }
                    return;
                }
                afterFilterChange();
            }

            function afterFilterChange() {
                document.querySelectorAll('.rm-filter-select').forEach(sel => {
                    sel.classList.toggle('has-value', !!sel.value);
                });
                renderActiveChips();
                persistFilters();
                applyFilters();
                // عداد النتائج يتحدّث بعد انتهاء applyFilters (300ms)
                setTimeout(updateResultsCount, 330);
                toggleSearchClearVisibility();
            }

            // ===== زر مسح البحث × =====
            function toggleSearchClearVisibility() {
                if (!searchInput || !searchClearBtn) return;
                const hasText = searchInput.value.length > 0;
                searchClearBtn.style.display = hasText ? 'inline-flex' : 'none';
                if (searchKbd) searchKbd.style.display = hasText ? 'none' : '';
            }
            if (searchClearBtn) {
                searchClearBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    if (window.jQuery) $(searchInput).val(null).trigger('change');
                    afterFilterChange();
                });
            }

            // ===== ربط الأحداث الإضافية =====
            /* القوائم عليها select2، وهو يطلق `change` عبر jQuery لا حدثاً أصليّاً —
               فمستمعُ `addEventListener` وحده لم يكن يسمع اختيار المستخدم: لا شارة
               للفلتر ولا حفظ له. مستمعُ jQuery يسمع الاثنين. */
            [searchInput, statusFilter, stageFilter, groupFilter].forEach(el => {
                if (!el) return;
                const onFilterInput = () => {
                    renderActiveChips();
                    persistFilters();
                    setTimeout(updateResultsCount, 330);
                    toggleSearchClearVisibility();
                };
                if (el !== searchInput && window.jQuery) {
                    $(el).on('change.rmchips', onFilterInput);
                } else {
                    el.addEventListener(el === searchInput ? 'input' : 'change', onFilterInput);
                }
            });

            // مسح فلتر فردي عبر النقر على شارة
            if (activeChips) {
                activeChips.addEventListener('click', (e) => {
                    const chip = e.target.closest('.rm-filter-chip');
                    if (!chip) return;
                    const key = chip.dataset.filterKey;
                    clearOneFilter(key);
                });
            }

            // مسح الكل
            if (clearAllBtn) clearAllBtn.addEventListener('click', clearAllFilters);

            // مسح الكل من رسالة "لا نتائج"
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="clear-all"]')) clearAllFilters();
            });

            // اختصار لوحة المفاتيح: Ctrl/⌘ + K للتركيز على البحث
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    if (window.jQuery && $('#searchInput').length) {
                        $('#searchInput').select2('open');
                    }
                }
                if (e.key === 'Escape') {
                    if (window.jQuery && $('#searchInput').length) {
                        $('#searchInput').select2('close');
                    }
                }
            });

            // تهيئة أوّلية
            renderActiveChips();
            toggleSearchClearVisibility();
            setTimeout(() => {
                // إن وفجدت قيم محفوظة، طبّقها على القائمة
                if (hasActiveFilters()) applyFilters();
                setTimeout(updateResultsCount, 330);
            }, 0);

            // تحسين أزرار التنقل
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');

            if (prevButton && nextButton) {
                prevButton.addEventListener('click', function() {
                    const currentSelected = document.querySelector('.client-item.selected');
                    if (currentSelected) {
                        const allVisibleClients = Array.from(document.querySelectorAll('.client-item'))
                            .filter(item => item.style.display !== 'none');
                        const currentIndex = allVisibleClients.indexOf(currentSelected);

                        if (currentIndex > 0) {
                            const prevClient = allVisibleClients[currentIndex - 1];
                            const clientId = prevClient.dataset.clientId;
                            selectClient(clientId);
                        }
                    }
                });

                nextButton.addEventListener('click', function() {
                    const currentSelected = document.querySelector('.client-item.selected');
                    if (currentSelected) {
                        const allVisibleClients = Array.from(document.querySelectorAll('.client-item'))
                            .filter(item => item.style.display !== 'none');
                        const currentIndex = allVisibleClients.indexOf(currentSelected);

                        if (currentIndex < allVisibleClients.length - 1) {
                            const nextClient = allVisibleClients[currentIndex + 1];
                            const clientId = nextClient.dataset.clientId;
                            selectClient(clientId);
                        }
                    }
                });
            }

            // المجموعات تبدأ مطوية - لا نفتح أي شيء تلقائياً

            // تهيئة حالة "تحديد الكل" لكل مجموعة عند تحميل الصفحة
            updateGroupSelectAllStates();

        }); // نهاية DOMContentLoaded

        // تحسين الأكورديون لفتح المجموعة عند البحث
        function expandGroupWithVisibleClients() {
            getTerritoryItems().forEach(accordionItem => {
                const clientsInGroup = accordionItem.querySelectorAll('.client-item');
                const hasVisibleClients = Array.from(clientsInGroup).some(client =>
                    client.style.display !== 'none'
                );

                if (hasVisibleClients) {
                    const collapseElement = accordionItem.querySelector('.accordion-collapse');
                    const button = accordionItem.querySelector('.rm-group-header');
                    expandCollapseElement(collapseElement, button, true);
                    expandBranchAncestors(accordionItem);
                }
            });

            updateBranchNodeVisibility();
        }

        // ===== دوال التنقل على الجوال (Master-Detail) =====
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('groupsListContainer');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
            closeMobileDetail();
        }

        function closeMobileSidebar() {
            const sidebar = document.getElementById('groupsListContainer');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        }

        // فتح صفحة التفاصيل على الجوال
        function openMobileDetail() {
            if (window.innerWidth <= 768) {
                const wrapper = document.querySelector('.rm-page-wrapper');
                const backBtn = document.getElementById('mobileBackBtn');
                if (wrapper) wrapper.classList.add('mobile-detail-open');
                if (backBtn) backBtn.style.display = 'flex';
                // إخفاء الشريط الجانبي إذا كان مفتوحاً
                closeMobileSidebar();
                // التمرير للأعلى داخل التفاصيل
                const details = document.getElementById('clientDetails');
                if (details) details.scrollTop = 0;
            }
        }

        // إغلاق التفاصيل والرجوع للقائمة
        function closeMobileDetail() {
            const wrapper = document.querySelector('.rm-page-wrapper');
            const backBtn = document.getElementById('mobileBackBtn');
            if (wrapper) wrapper.classList.remove('mobile-detail-open');
            if (backBtn) backBtn.style.display = 'none';
        }

        // عند اختيار عميل على الجوال — افتح التفاصيل تلقائياً
        document.addEventListener('click', function(event) {
            const clientItem = event.target.closest('.rm-client-item');
            // تجاهل النقر على checkbox أو الأفاتار (روابط داخلية)
            const isCheckbox = event.target.closest('.rm-client-check');
            const isAvatar = event.target.closest('.rm-client-avatar');
            if (clientItem && !isCheckbox && !isAvatar && window.innerWidth <= 768) {
                openMobileDetail();
            }
        });

        // معالج تغيير حجم الشاشة
        window.addEventListener('resize', function() {
            const groupsList = document.getElementById('groupsListContainer');
            const wrapper = document.querySelector('.rm-page-wrapper');

            if (groupsList) {
                // إعادة ضبط ستايلات inline إن وجدت
                groupsList.style.position = '';
                groupsList.style.display = '';
                groupsList.classList.remove('show-mobile');
                const backdrop = document.getElementById('sidebarBackdrop');
                if (backdrop) backdrop.classList.remove('show');
            }

            // عند الانتقال للديسكتوب: أزل حالة mobile-detail-open
            if (window.innerWidth > 768 && wrapper) {
                wrapper.classList.remove('mobile-detail-open');
                const backBtn = document.getElementById('mobileBackBtn');
                if (backBtn) backBtn.style.display = 'none';
            }

            // إعادة تحميل الخريطة إذا كانت موجودة
            if (typeof mapManager !== 'undefined' && mapManager.map) {
                setTimeout(() => {
                    google.maps.event.trigger(mapManager.map, 'resize');
                }, 300);
            }
        });

        @include('client::partials.crm_shared_scripts_modals')
    </script>

@endsection
