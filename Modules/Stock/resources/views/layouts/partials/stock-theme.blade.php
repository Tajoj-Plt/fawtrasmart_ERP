{{--
    مظهر شاشات المخزون — مبنيّ بالكامل على متغيّرات «مظهر النظام».

    **لماذا ورقة خاصّة بالوحدة؟**

    `layouts.partials.theme-styles` يحقن المتغيّرات ويلوّن الشريط الجانبي
    وحده. أمّا قالب Vuexy الأصلي فيلوّن البطاقات والجداول والنماذج بألوانه
    هو، فلا يظهر أثر الثيم عليها إطلاقاً — ومتغيّر `--app-table-stripe`
    كان مُعرَّفاً بلا مستعمل واحد.

    وفي الشريط الجانبي عطل أوضح: الثيم يجعل الخلفية داكنة ويلوّن نصّ
    الروابط بـ`--app-sidebar-text` (لونٌ فاتح مقصودٌ لخلفية داكنة)، لكن
    القائمة الفرعية `ul.menu-content` تبقى **بيضاء** من قالب Vuexy —
    فيصير النصّ الفاتح على أبيض، أي غير مقروء.

    كل ما هنا يشير إلى متغيّرات الثيم لا إلى ألوان مكتوبة، فتبديل المظهر
    في الإعدادات يسري على هذه الشاشات كما يسري على غيرها.

    وهو الآن الثيم المعتمد لأكثر من وحدة: يُستدعى من `stock::master`
    و`account::master` معاً — مظهرٌ واحد للنظام لا مظهرٌ لكل وحدة.
--}}

<style id="stock-theme">
    /* ═══════════════ ألوان مشتقّة ═══════════════
       يقبل الثيم لونين للشريط فقط، وهذا التصميم يحتاج تدرّجاً بينهما:
       تُشتقّ الدرجات بـcolor-mix من نفس المتغيّرين فتتبع الإعداد. */
    :root {
        --stk-sidebar-sub-bg: color-mix(in srgb, var(--app-sidebar-bg) 88%, #000);
        --stk-sidebar-line: color-mix(in srgb, var(--app-sidebar-text) 16%, transparent);
        --stk-sidebar-dim: color-mix(in srgb, var(--app-sidebar-text) 72%, transparent);
        --stk-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 4px 12px rgba(16, 24, 40, .05);
        --stk-shadow-lg: 0 2px 4px rgba(16, 24, 40, .05), 0 12px 28px rgba(16, 24, 40, .08);
    }

    /* ═══════════════ الشريط الجانبي ═══════════════ */

    .main-menu .navbar-header {
        border-bottom: 1px solid var(--stk-sidebar-line);
        padding-bottom: .35rem;
    }

    .main-menu .brand-text {
        font-weight: 700;
        letter-spacing: .2px;
    }

    /* القائمة الفرعية: كانت بيضاء فيختفي نصُّها الفاتح فوقها */
    .main-menu .navigation li > ul.menu-content,
    .main-menu .navigation li.has-sub > ul.menu-content,
    .main-menu.menu-light .navigation li > ul.menu-content {
        background: var(--stk-sidebar-sub-bg) !important;
        padding: .25rem 0 .4rem;
        border-radius: var(--app-radius);
        margin: .15rem .55rem .35rem;
        box-shadow: inset 0 1px 0 var(--stk-sidebar-line);
    }

    .main-menu .navigation li > ul.menu-content li > a {
        color: var(--stk-sidebar-dim) !important;
        padding: .5rem 1.15rem !important;
        font-size: .875rem;
        border-radius: var(--app-radius);
        margin: 1px .4rem;
        transition: background .15s ease, color .15s ease, padding .15s ease;
    }

    .main-menu .navigation li > ul.menu-content li > a:hover {
        color: var(--app-sidebar-text) !important;
        background: var(--app-sidebar-hover) !important;
        padding-inline-start: 1.45rem !important;
    }

    /* البند الجاري: شريط رفيع بلون الهوية بدل تعبئة كاملة تُثقل القائمة */
    .main-menu .navigation li > ul.menu-content li.active > a,
    .main-menu .navigation li > ul.menu-content li > a.active {
        color: var(--app-on-primary) !important;
        background: var(--app-primary) !important;
        font-weight: 600;
        box-shadow: 0 2px 8px var(--app-primary-glow);
    }

    .main-menu .navigation li > ul.menu-content li > a i {
        color: inherit !important;
        font-size: .5rem;
        opacity: .55;
        vertical-align: middle;
    }

    .main-menu .navigation li > ul.menu-content li.active > a i {
        opacity: 1;
    }

    /* الأب المفتوح: يبقى مميَّزاً ليُعرف موضع الصفحة في الشجرة.
       `sidebar-group-active` يضيفه app-menu.js على أب البند الجاري، وقالب
       Vuexy يلوّنه `#f5f5f5` (معدٌّ لقائمةٍ بيضاء) بلا `!important` فيظهر
       شريطاً فاتحاً فوق الخلفية الداكنة — نضمّه هنا لنفس معالجة `.open`. */
    .main-menu .navigation > li.open > a,
    .main-menu .navigation > li.has-sub.open > a,
    .main-menu .navigation > li.sidebar-group-active > a {
        background: var(--app-sidebar-hover) !important;
        color: var(--app-sidebar-text) !important;
        border-radius: var(--app-radius);
    }

    /* عنصر <li> النشط نفسه: يلوّنه Vuewy `#F0F0F0` (صنف `.navigation > li .active`)
       ونحن نلوّن `<a>` بداخله فقط — فيبقى «مربعٌ» فاتح حول البند فوق الشريط
       الداكن. تصفيره هنا يُبقي التمييز على `<a>` وحده كما هو مقصود. */
    .main-menu .navigation li.active,
    .main-menu .navigation li > ul.menu-content li.active,
    .main-menu.menu-light .navigation > li .active {
        background: transparent !important;
        box-shadow: none !important;
    }

    .main-menu .navigation > li > a {
        border-radius: var(--app-radius);
        margin: 2px .55rem;
        padding: .62rem .95rem !important;
        transition: background .15s ease, color .15s ease;
    }

    .main-menu .navigation > li > a > i {
        font-size: 1.05rem;
    }

    /* سهم الفتح: كان يرث لوناً باهتاً فلا يكاد يُرى */
    .main-menu .navigation > li.has-sub > a:after {
        color: var(--stk-sidebar-dim) !important;
    }

    .main-menu .navigation-header {
        color: var(--stk-sidebar-dim) !important;
        text-transform: none;
        letter-spacing: .04em;
        font-size: .72rem;
        font-weight: 700;
        opacity: .75;
        padding: 1rem 1.35rem .35rem;
    }

    /* الوضع المغلق: القالب يُلوّن أيقونات القائمة الفرعية بلونٍ فاتح
       معدٍّ لخلفية بيضاء، فتظهر بيضاء فوق الشريط الداكن */
    body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li > a i,
    body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li > a {
        color: var(--stk-sidebar-dim) !important;
        background: transparent !important;
    }

    body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li.active > a,
    body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li.active > a i {
        color: var(--app-on-primary) !important;
    }

    /* شريط تمرير رفيع يذوب في الخلفية بدل الشريط الافتراضي العريض */
    .main-menu-content::-webkit-scrollbar { width: 5px; }
    .main-menu-content::-webkit-scrollbar-thumb {
        background: var(--stk-sidebar-line);
        border-radius: 4px;
    }
    .main-menu-content::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--app-sidebar-text) 34%, transparent);
    }

/* ═══════════════════════════════════════════════════════════
       نظام التصميم — إيقاع مسافات واحد ومقياس ظلال واحد

       قالب Vuexy يفرض حشوات وظلالاً وحوافّ من عصر آخر: بطاقات بظلال
       ثقيلة، وجداول بحدود مزدوجة، وأزرار بأحجام مختلفة في كل شاشة.
       هنا يُعاد ضبطها كلها على إيقاع واحد.
       ═══════════════════════════════════════════════════════════ */

    :root {
        --stk-1: .25rem;  --stk-2: .5rem;   --stk-3: .75rem;
        --stk-4: 1rem;    --stk-5: 1.5rem;  --stk-6: 2rem;

        --stk-r: calc(var(--app-radius) + 2px);
        --stk-r-lg: calc(var(--app-radius) + 6px);

        /* ظلال ناعمة متدرّجة بدل ظلّ Vuexy الواحد الثقيل */
        --stk-e1: 0 1px 2px rgba(16,24,40,.04);
        --stk-e2: 0 1px 3px rgba(16,24,40,.06), 0 1px 2px rgba(16,24,40,.04);
        --stk-e3: 0 4px 12px rgba(16,24,40,.07), 0 2px 4px rgba(16,24,40,.04);
        --stk-e4: 0 12px 32px rgba(16,24,40,.10), 0 4px 8px rgba(16,24,40,.05);

        --stk-line: var(--app-border);
        --stk-line-soft: color-mix(in srgb, var(--app-border) 55%, transparent);
        --stk-tint: color-mix(in srgb, var(--app-primary) 4%, var(--app-surface));
    }

    /* ═══════════════ الأرضية ═══════════════ */

    body { background: var(--app-body-bg); color: var(--app-text); }

    .app-content, .content-wrapper { padding-top: var(--stk-4); }

    .header-navbar.navbar-shadow { box-shadow: var(--stk-e1) !important; }

    /* ═══════════════ ترويسة الصفحة ═══════════════ */

    .stk-page-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--stk-4); flex-wrap: wrap;
        margin-bottom: var(--stk-5);
    }

    .stk-page-head__titles { min-width: 0; display: flex; align-items: center; gap: var(--stk-3); }

    .stk-page-head__icon {
        flex: none; width: 44px; height: 44px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: var(--app-primary);
        color: var(--app-on-primary);
        font-size: 1.15rem;
        box-shadow: 0 4px 12px var(--app-primary-glow);
    }

    .stk-page-head__title {
        font-size: 1.35rem; font-weight: 700; line-height: 1.25;
        color: var(--app-text); margin: 0 0 .15rem; letter-spacing: -.01em;
    }

    .stk-page-head__sub { color: var(--app-muted); font-size: .82rem; margin: 0; }

    .stk-page-head .breadcrumb {
        margin: .15rem 0 0; padding: 0; background: none; font-size: .78rem;
    }
    .stk-page-head .breadcrumb-item + .breadcrumb-item::before { color: var(--app-muted); opacity: .5; }

    .stk-page-head__actions { display: flex; gap: var(--stk-2); flex-wrap: wrap; align-items: center; }

    /* ═══════════════ الأزرار ═══════════════
       حجم واحد، ووزن واحد، ومسافة أيقونة واحدة. */

    .btn {
        --btn-bg: transparent; --btn-fg: var(--app-text); --btn-bd: var(--stk-line);
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        height: 38px; padding: 0 var(--stk-4);
        font-size: .86rem; font-weight: 600; line-height: 1;
        border: 1px solid var(--btn-bd);
        border-radius: var(--stk-r);
        background: var(--btn-bg); color: var(--btn-fg);
        box-shadow: var(--stk-e1);
        transition: background .16s ease, border-color .16s ease, color .16s ease,
                    box-shadow .16s ease, transform .16s ease;
        white-space: nowrap;
    }

    .btn > i, .btn > svg { font-size: .95em; line-height: 1; }
    .btn.mr-1, .btn.mr-50 { margin-inline-end: var(--stk-2) !important; }
    .btn i.mr-1, .btn i.mr-50, .btn i.me-1, .btn i.me-2 { margin: 0 !important; }

    .btn:hover { transform: translateY(-1px); box-shadow: var(--stk-e3); }
    .btn:active { transform: translateY(0); box-shadow: var(--stk-e1); }
    .btn:focus-visible { outline: 2px solid var(--app-primary); outline-offset: 2px; }

    .btn-sm { height: 32px; padding: 0 var(--stk-3); font-size: .8rem; }
    .btn-lg { height: 44px; padding: 0 var(--stk-5); font-size: .92rem; }

    .btn-primary, .btn-primary:not(:disabled):not(.disabled) {
        --btn-bg: var(--app-primary); --btn-fg: var(--app-on-primary); --btn-bd: var(--app-primary);
    }
    .btn-primary:hover, .btn-primary:focus, .btn-primary:not(:disabled):not(.disabled):active {
        background: var(--app-primary-hover) !important;
        border-color: var(--app-primary-hover) !important;
        color: var(--app-on-primary) !important;
        box-shadow: 0 6px 16px var(--app-primary-glow) !important;
    }

    .btn-success { --btn-bg: var(--app-success); --btn-fg: var(--app-on-primary); --btn-bd: var(--app-success); }
    .btn-danger  { --btn-bg: var(--app-danger);  --btn-fg: var(--app-on-primary); --btn-bd: var(--app-danger); }
    .btn-info    { --btn-bg: var(--app-info);    --btn-fg: var(--app-on-primary); --btn-bd: var(--app-info); }
    .btn-warning { --btn-bg: var(--app-warning); --btn-fg: var(--app-on-primary); --btn-bd: var(--app-warning); }
    .btn-secondary, .btn-light { --btn-bg: var(--app-surface); --btn-fg: var(--app-text); }

    .btn-outline-primary   { --btn-fg: var(--app-primary); --btn-bd: color-mix(in srgb, var(--app-primary) 40%, transparent); }
    .btn-outline-secondary { --btn-fg: var(--app-muted);   --btn-bd: var(--stk-line); }
    .btn-outline-danger    { --btn-fg: var(--app-danger);  --btn-bd: color-mix(in srgb, var(--app-danger) 40%, transparent); }
    .btn-outline-success   { --btn-fg: var(--app-success); --btn-bd: color-mix(in srgb, var(--app-success) 40%, transparent); }
    .btn-outline-info      { --btn-fg: var(--app-info);    --btn-bd: color-mix(in srgb, var(--app-info) 40%, transparent); }
    .btn-outline-warning   { --btn-fg: var(--app-warning); --btn-bd: color-mix(in srgb, var(--app-warning) 40%, transparent); }

    .btn-outline-primary:hover   { background: var(--app-primary); color: var(--app-on-primary); border-color: var(--app-primary); }
    .btn-outline-secondary:hover { background: var(--app-body-bg); color: var(--app-text); }
    .btn-outline-danger:hover    { background: var(--app-danger);  color: var(--app-on-primary); border-color: var(--app-danger); }
    .btn-outline-success:hover   { background: var(--app-success); color: var(--app-on-primary); border-color: var(--app-success); }
    .btn-outline-info:hover      { background: var(--app-info);    color: var(--app-on-primary); border-color: var(--app-info); }
    .btn-outline-warning:hover   { background: var(--app-warning); color: var(--app-on-primary); border-color: var(--app-warning); }

    /* زر أيقونة مربّع — لأعمدة الإجراءات */
    .stk-actions { display: inline-flex; gap: .3rem; }
    .stk-actions .btn { width: 34px; height: 34px; padding: 0; box-shadow: none; }
    .stk-actions .btn:hover { box-shadow: var(--stk-e2); }

    .btn-group > .btn { box-shadow: none; }
    .btn-group > .btn:hover { transform: none; }

    /* ═══════════════ البطاقات ═══════════════ */

    .card {
        background: var(--app-surface);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r-lg);
        box-shadow: var(--stk-e2);
        margin-bottom: var(--stk-4);
        overflow: hidden;
        transition: box-shadow .2s ease;
    }

    .card:hover { box-shadow: var(--stk-e3); }
    .card .card-content { background: transparent; }

    .card > .card-header,
    .card .card-content > .card-header {
        background: transparent;
        border-bottom: 1px solid var(--stk-line-soft);
        padding: var(--stk-4) var(--stk-5);
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--stk-3); flex-wrap: wrap;
    }

    .card .card-title {
        font-size: .98rem; font-weight: 700; color: var(--app-text);
        margin: 0; display: flex; align-items: center; gap: var(--stk-2);
    }
    .card .card-title i { color: var(--app-primary); font-size: .95em; }

    .card .card-body { padding: var(--stk-5); }
    .card .card-body + .card-body { padding-top: 0; }

    /* ── البطاقات الإحصائية ──
       بطاقات اللوحة كانت أيقونةً فوق رقمٍ فوق نصّ في عمود، بحشوة سفلية
       صفر (`pb-0`) فتلتصق بحافّتها. صارت صفّاً متوازناً: أيقونة إلى جانب
       الرقم، والرقم بأرقام مستقيمة تُقرأ في شبكة. */

    .card.stat-card .card-header {
        flex-direction: row !important;
        align-items: center !important;
        gap: var(--stk-4);
        padding: var(--stk-5) !important;
        border-bottom: 0;
    }

    .card.stat-card .avatar {
        flex: none;
        width: 48px; height: 48px;
        margin: 0 !important;
        border-radius: var(--stk-r) !important;
        display: flex; align-items: center; justify-content: center;
    }

    .card.stat-card .avatar-content { display: flex; align-items: center; justify-content: center; }
    .card.stat-card .avatar i { font-size: 1.3rem !important; }

    .card.stat-card h2 {
        font-size: 1.55rem; font-weight: 700; line-height: 1.1;
        margin: 0 !important; color: var(--app-text);
        font-variant-numeric: tabular-nums;
    }

    .card.stat-card p {
        font-size: .8rem; color: var(--app-muted);
        margin: .2rem 0 0 !important; line-height: 1.4;
    }

    /* الرقم والنصّ عمودٌ واحد إلى جانب الأيقونة */
    .card.stat-card .card-header > h2 { order: 2; }
    .card.stat-card .card-header > p  { order: 3; }
    .card.stat-card .card-header {
        display: grid !important;
        grid-template-columns: auto 1fr;
        grid-template-rows: auto auto;
        align-content: center;
    }
    .card.stat-card .avatar { grid-row: 1 / span 2; grid-column: 1; }
    .card.stat-card .card-header > h2 { grid-column: 2; align-self: end; }
    .card.stat-card .card-header > p  { grid-column: 2; align-self: start; }

    /* بطاقة إحصائية مبنية بترميزنا */
    .stk-stat { display: flex; align-items: center; gap: var(--stk-4); padding: var(--stk-5); }
    .stk-stat__icon {
        flex: none; width: 48px; height: 48px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: var(--app-primary-soft); color: var(--app-primary);
        font-size: 1.25rem;
    }
    .stk-stat__value {
        font-size: 1.55rem; font-weight: 700; line-height: 1.1;
        color: var(--app-text); font-variant-numeric: tabular-nums; margin: 0;
    }
    .stk-stat__label { font-size: .8rem; color: var(--app-muted); margin: .2rem 0 0; }

    /* ═══════════════ الجداول ═══════════════
       بلا حدود مزدوجة، وبمسافة تنفّس، وأرقام مستقيمة. */

    .stk-table-card > .card-body,
    .stk-table-card .card-content > .card-body { padding: 0; }

    .table {
        color: var(--app-text);
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead th {
        background: var(--stk-tint);
        color: var(--app-muted);
        font-size: .74rem; font-weight: 700;
        letter-spacing: .04em; text-transform: uppercase;
        padding: var(--stk-3) var(--stk-5);
        border: 0 !important;
        border-bottom: 1px solid var(--stk-line) !important;
        white-space: nowrap;
        vertical-align: middle;
    }

    .table tbody td {
        padding: var(--stk-4) var(--stk-5);
        border: 0 !important;
        border-bottom: 1px solid var(--stk-line-soft) !important;
        vertical-align: middle;
        font-size: .875rem;
    }

    .table tbody tr:last-child td { border-bottom: 0 !important; }

    .table-striped tbody tr:nth-of-type(odd),
    .table.table-striped tbody tr:nth-of-type(odd) { background: var(--app-table-stripe); }

    .table-hover tbody tr { transition: background .14s ease; }
    .table-hover tbody tr:hover { background: var(--app-primary-soft) !important; }

    .table td.text-center, .table th.text-center { text-align: center; }
    .table .stk-num, .table td[data-num] { font-variant-numeric: tabular-nums; }

    .table-responsive { border: 0; }

    /* رأس بطاقة الجدول */
    .stk-table-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--stk-3); flex-wrap: wrap;
        padding: var(--stk-4) var(--stk-5);
        border-bottom: 1px solid var(--stk-line-soft);
    }
    .stk-table-head__title {
        display: flex; align-items: center; gap: var(--stk-2);
        font-weight: 700; font-size: .95rem; color: var(--app-text); margin: 0;
    }
    .stk-table-head__title i { color: var(--app-primary); }

    .stk-count {
        display: inline-flex; align-items: center;
        padding: .1rem .5rem; border-radius: 999px;
        background: var(--app-primary-soft); color: var(--app-primary);
        font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
    }

    /* خلية اسم + صورة */
    .stk-media { display: flex; align-items: center; gap: var(--stk-3); min-width: 0; }
    .stk-media__thumb {
        flex: none; width: 40px; height: 40px;
        border-radius: var(--stk-r); object-fit: cover;
        border: 1px solid var(--stk-line); background: var(--app-body-bg);
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--app-muted); font-size: .95rem;
    }
    .stk-media__body { min-width: 0; }
    .stk-media__title { font-weight: 600; color: var(--app-text); display: block; line-height: 1.35; }
    .stk-media__sub {
        font-size: .75rem; color: var(--app-muted); display: block;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* شارة حالة بنقطة */
    .stk-state {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .22rem .62rem; border-radius: 999px;
        font-size: .74rem; font-weight: 600; line-height: 1.4;
    }
    .stk-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex: none; }
    .stk-state--on  { color: var(--app-success); background: var(--app-success-soft); }
    .stk-state--off { color: var(--app-muted);   background: color-mix(in srgb, var(--app-muted) 12%, transparent); }
    /* «محايد» لِما ليس له حالة أصلاً — كخدمةٍ لا مخزون لها */
    .stk-state--neutral { color: var(--app-info); background: color-mix(in srgb, var(--app-info) 12%, transparent); }
    .stk-state--neutral::before { display: none; }

    /* «تحذير» لِما يحتاج انتباهاً دون أن يكون عطلاً — كصنفٍ بلغ حدّ الطلب */
    .stk-state--warn { color: var(--app-warning); background: color-mix(in srgb, var(--app-warning) 14%, transparent); }

    /* مجموعة شارات في خلية واحدة: تتلفّ ولا تتراصّ */
    .stk-tags { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }

    /* وسمٌ حرّ يكتبه المستخدم — محايد اللون، لا يزاحم شارات الحالة */
    .stk-tag {
        display: inline-flex; align-items: center;
        padding: .2rem .55rem; border-radius: 999px;
        font-size: .74rem; font-weight: 500; line-height: 1.4;
        color: var(--app-body); background: var(--app-surface2, rgba(127, 127, 127, .12));
        border: 1px solid var(--app-border);
    }

    /* كمية بارزة في عمود ضيّق */
    .stk-qty { font-size: 1.05rem; font-weight: 700; color: var(--app-success); line-height: 1.2; }

    /* سعرا الشراء والبيع تحت بعضهما بترويسةٍ صغيرة لكلٍّ منهما */
    .stk-price { display: inline-flex; flex-direction: column; gap: .25rem; align-items: stretch; }
    .stk-price__row {
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        font-size: .82rem; font-weight: 600; color: var(--app-text);
    }
    .stk-price__tag {
        font-size: .68rem; font-weight: 600; color: var(--app-muted);
        padding: .1rem .4rem; border-radius: 4px;
        background: color-mix(in srgb, var(--app-muted) 10%, transparent);
    }
    .stk-price__row--sale { color: var(--app-success); }
    .stk-price__row--sale .stk-price__tag { color: var(--app-success); background: var(--app-success-soft); }

    /* ═══════════════ الشارات ═══════════════ */

    .badge {
        display: inline-flex; align-items: center;
        padding: .28em .65em; border-radius: 999px;
        font-size: .73rem; font-weight: 600; letter-spacing: 0;
    }
    {{--
        الشارات المصمتة (`.badge-success`…) خلفيّتها مصمتة من `theme-styles`
        بـ`!important` (انظر `App\Support\ThemeSettings::css()`) ونصّها
        أبيض من Bootstrap افتراضاً — فلا تُلمس هنا. كانت هذه القاعدة تجمعها
        مع `.badge-light-*` وتصبغ نصّها بلون التمييز نفسه، فيصير النص بلون
        الخلفية المصمتة نفسه: شارةٌ ملوّنة بلا نصٍّ مقروء. فقط الشارات
        «الفاتحة» (خلفية شفافة خفيفة) تحتاج نصّاً بلون التمييز.
    --}}
    .badge-light-primary { background: var(--app-primary-soft); color: var(--app-primary); }
    .badge-light-success { background: var(--app-success-soft); color: var(--app-success); }
    .badge-light-danger  { background: var(--app-danger-soft);  color: var(--app-danger); }
    .badge-light-info    { background: color-mix(in srgb, var(--app-info) 12%, transparent); color: var(--app-info); }
    .badge-light-warning { background: color-mix(in srgb, var(--app-warning) 14%, transparent); color: var(--app-warning); }
    .badge-secondary, .badge-light {
        background: color-mix(in srgb, var(--app-muted) 12%, transparent); color: var(--app-muted);
    }

    /* ═══════════════ الترقيم ═══════════════ */

    .stk-table-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--stk-4); flex-wrap: wrap;
        padding: var(--stk-3) var(--stk-5);
        border-top: 1px solid var(--stk-line-soft);
        background: var(--stk-tint);
    }
    .stk-table-foot__count { font-size: .8rem; color: var(--app-muted); margin: 0; font-variant-numeric: tabular-nums; }

    .pagination { gap: .3rem; margin: 0; flex-wrap: wrap; }

    .pagination .page-item .page-link,
    .pagination .page-link {
        min-width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        margin: 0; padding: 0 .55rem;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r) !important;
        background: var(--app-surface); color: var(--app-text);
        font-size: .82rem; font-weight: 600; font-variant-numeric: tabular-nums;
        box-shadow: none;
        transition: background .14s ease, border-color .14s ease, color .14s ease;
    }

    .pagination .page-item .page-link:hover {
        background: var(--app-primary-soft);
        border-color: color-mix(in srgb, var(--app-primary) 35%, transparent);
        color: var(--app-primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--app-primary); border-color: var(--app-primary);
        color: var(--app-on-primary);
        box-shadow: 0 3px 10px var(--app-primary-glow);
    }

    .pagination .page-item.disabled .page-link {
        background: var(--app-surface); color: var(--app-muted); opacity: .5;
    }

    /* ═══════════════ النماذج ═══════════════ */

    .form-group { margin-bottom: var(--stk-4); }

    /*
        مفتاحُ التبديل مستثنى — وإلّا انهار.

        `.form-group > label` نوعيّتُه (0,1,1) و`.stk-switch` (0,1,0)، فكان
        `display:block` يغلب `display:flex` مهما كان الترتيب: يصير المفتاحُ
        كتلةً، فيعود شريطُه `inline` ويسقط عرضُه وارتفاعُه إلى صفر، ويظهر
        مربّعُ الاختيار الخام تحت التسمية. وكلُّ مفتاحٍ وُضع مباشرةً داخل
        `.form-group` في النظام كان مكسوراً هكذا — ومن وضعه أعمقَ نجا، لأن
        `label` وحدَه (0,0,1) لا يغلب الصنف.

        وهذا ما عالجته ثيماتُ العملاء والمبيعات والمشتريات في نسخها الخاصّة
        بـ`:not(.cl-switch)` و`:not(.sl-switch)` — والأصلُ أولى بالعلاج.
        وللمفتاح خطُّه وهوامشُه في قواعده، فلا يفقد شيئاً بالاستثناء.
    */
    label:not(.stk-switch), .form-group > label:not(.stk-switch) {
        display: block;
        font-size: .82rem; font-weight: 600; color: var(--app-text);
        margin-bottom: .4rem;
    }

    .form-control, .custom-select,
    .select2-container--default .select2-selection--single {
        height: 40px;
        padding: 0 var(--stk-3);
        background: var(--app-surface);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        color: var(--app-text);
        font-size: .875rem;
        box-shadow: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    textarea.form-control { height: auto; padding: var(--stk-3); min-height: 90px; }

    .form-control:focus, .custom-select:focus,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--app-primary);
        box-shadow: 0 0 0 3px var(--app-primary-soft);
        outline: 0;
    }

    .form-control::placeholder { color: var(--app-muted); opacity: .7; }
    .form-control.is-invalid { border-color: var(--app-danger); }

    /*
        و`select2` يُخفي القائمةَ الأصلية ويرسم بديلَها أخاً لها — فحدُّ
        الخطأ يُرسم على عنصرٍ لا يراه أحد. فيُنقل إلى البديل.
    */
    select.is-invalid + .select2-container .select2-selection {
        border-color: var(--app-danger);
    }
    .invalid-feedback { font-size: .76rem; color: var(--app-danger); margin-top: .3rem; }

    .input-group-text {
        background: var(--stk-tint); border: 1px solid var(--stk-line);
        color: var(--app-muted); border-radius: var(--stk-r);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px; color: var(--app-text); padding: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
    .select2-dropdown {
        border-color: var(--stk-line); border-radius: var(--stk-r);
        box-shadow: var(--stk-e4); overflow: hidden;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: var(--app-primary); color: var(--app-on-primary);
    }

    /* أقسام النموذج */
    .stk-section { margin-bottom: var(--stk-5); }
    .stk-section:last-child { margin-bottom: 0; }
    .stk-section__head {
        display: flex; align-items: center; gap: var(--stk-2);
        padding-bottom: var(--stk-2); margin-bottom: var(--stk-4);
        border-bottom: 1px solid var(--stk-line-soft);
    }
    .stk-section__head i { color: var(--app-primary); font-size: .9rem; }
    .stk-section__title { font-weight: 700; font-size: .9rem; color: var(--app-text); margin: 0; }

    .stk-req::after { content: ' *'; color: var(--app-danger); font-weight: 700; }
    .stk-help { display: block; font-size: .75rem; color: var(--app-muted); margin-top: .3rem; }

    /*
        أيقونةُ الشرح: بديلُ سطرِ `stk-help` الدائم تحت الحقل.

        شاشةٌ فيها عشرون حقلاً وتحت كلٍّ سطرا شرحٍ يصير نصُّها أكثرَ من
        مُدخَلاتها، فتتوقّف العينُ عن قراءته أصلاً. فما كان تفصيلاً للفهم
        انتقل إلى `title` يُقرأ عند الحاجة، وبقي تحت الحقل ما يغيّر قراراً
        أو يمنع الحفظ.
    */
    .stk-tip {
        font-size: .78rem;
        color: var(--app-muted);
        cursor: help;
        margin-inline-start: .2rem;
        vertical-align: middle;
    }
    .stk-tip:hover { color: var(--app-primary); }

    /*
        شريطُ نسبةٍ داخل صفّ — لتوزيعٍ يُقرأ بالنظرة قبل الرقم.

        الرقمُ وحده يقول «١٢ حركة»، والشريطُ يقول «هذه ذروةُ الوردية».
        وهما معاً لا أحدهما: الشريطُ يُقارن، والرقمُ يُحاسَب عليه.

        والعرضُ يُمرَّر بـ`style` لأنه بيانٌ لا تنسيق.
    */
    .stk-bar {
        position: relative;
        height: 1.15rem;
        background: var(--stk-tint);
        border-radius: 3px;
        overflow: hidden;
        min-width: 3rem;
    }
    .stk-bar__fill {
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        background: var(--app-primary);
        opacity: .75;
        border-radius: inherit;
    }
    .stk-bar__text {
        position: relative;
        display: block;
        padding: 0 .35rem;
        font-size: .7rem;
        line-height: 1.15rem;
        color: var(--app-text);
        font-variant-numeric: tabular-nums;
    }
    .stk-bar--success .stk-bar__fill { background: var(--app-success); }
    .stk-bar--danger .stk-bar__fill { background: var(--app-danger); }
    .stk-bar--warning .stk-bar__fill { background: var(--app-warning); }

    /*
        الحقول التلقائية: رقم الإذن، والرقم التسلسلي للصنف، وكود التصنيف.

        الخادم يُصدرها من إعدادات «الترقيم المتسلسل» ويُهمل ما يصل من
        النموذج. فلو بقي الحقل يبدو قابلاً للكتابة لكتب فيه المستخدمُ رقماً
        ثم رآه يتبدّل بعد الحفظ بلا تفسير. المظهر هنا يقول ما يفعله الخادم:
        حقلٌ مقفل، بخلفية باهتة ومؤشّر «ممنوع»، وقفلٌ في السطر تحته.
    */
    input.stk-locked,
    input.stk-locked:focus {
        background-color: var(--stk-tint);
        border-color: var(--stk-line);
        color: var(--app-muted);
        cursor: not-allowed;
        box-shadow: none;
    }
    .stk-help--locked i { font-size: .7rem; margin-inline-end: .2rem; }

    /*
        «المتاح في المستودع» تحت قائمة الصنف في بنود الإذن.

        أربع حالاتٍ لها أربعة ألوان، لأنّ الفرق بينها فرقُ قرار: رصيدٌ صفرٌ
        حقيقي غير رصيدٍ لم يُقرأ بعد، وكلاهما غير رصيدٍ تعذّرت قراءته.
    */
    .stk-avail {
        display: block; font-size: .75rem; margin-top: .3rem;
        color: var(--app-muted);
    }
    .stk-avail__value { font-weight: 700; }
    .stk-avail__note:not(:empty) { margin-inline-start: .3rem; }
    .stk-avail[data-state="loading"] { opacity: .65; }
    .stk-avail[data-state="ready"] .stk-avail__value { color: var(--app-success); }
    .stk-avail[data-state="short"],
    .stk-avail[data-state="short"] .stk-avail__value { color: var(--app-danger); }
    .stk-avail[data-state="error"],
    .stk-avail[data-state="error"] .stk-avail__value { color: var(--app-warning, var(--app-danger)); }

    .stk-form-foot {
        display: flex; gap: var(--stk-2); justify-content: flex-end;
        padding: var(--stk-4) var(--stk-5);
        border-top: 1px solid var(--stk-line-soft);
        background: var(--stk-tint);
    }

    /* ═══════════════ الفلاتر ═══════════════ */

    .stk-filters .card-body { padding: var(--stk-5); }
    .stk-filters__head {
        display: flex; align-items: center; justify-content: space-between;
        gap: var(--stk-3); flex-wrap: wrap; margin-bottom: var(--stk-4);
    }
    .stk-filters__title {
        display: flex; align-items: center; gap: var(--stk-2);
        font-weight: 700; font-size: .9rem; color: var(--app-text); margin: 0;
    }
    .stk-filters__title i { color: var(--app-primary); }
    .stk-filters__foot {
        display: flex; gap: var(--stk-2); justify-content: flex-end;
        padding-top: var(--stk-4); margin-top: var(--stk-1);
        border-top: 1px dashed var(--stk-line);
    }

    /* ═══════════════ بلاطات الإعدادات ═══════════════ */

    .stk-tiles { display: grid; gap: var(--stk-4); grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }

    .stk-tile {
        display: flex; align-items: flex-start; gap: var(--stk-3);
        padding: var(--stk-5);
        background: var(--app-surface);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r-lg);
        box-shadow: var(--stk-e2);
        color: inherit; text-decoration: none;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        position: relative; overflow: hidden;
    }
    .stk-tile::before {
        content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
        width: 3px; background: var(--app-primary);
        transform: scaleY(0); transform-origin: top; transition: transform .2s ease;
    }
    .stk-tile:hover {
        transform: translateY(-2px); box-shadow: var(--stk-e4);
        border-color: color-mix(in srgb, var(--app-primary) 28%, var(--stk-line));
        color: inherit; text-decoration: none;
    }
    .stk-tile:hover::before { transform: scaleY(1); }
    .stk-tile:focus-visible { outline: 2px solid var(--app-primary); outline-offset: 2px; }

    .stk-tile__icon {
        flex: none; width: 46px; height: 46px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: var(--app-primary-soft); color: var(--app-primary);
        font-size: 1.2rem;
    }
    .stk-tile__body { min-width: 0; }
    .stk-tile__title { font-weight: 700; font-size: .95rem; color: var(--app-text); margin: 0 0 .2rem; }
    .stk-tile__hint  { font-size: .78rem; color: var(--app-muted); margin: 0; line-height: 1.6; }

    /* ═══════════════ ميزة خارج الباقة ═══════════════
       تُعرض ولا تُخفى: إخفاءُ ما لم يُشترَ يجعل المنشأة لا تعرف أنه موجود
       فلا تسأل عنه ولا تشتريه. والمظهر هنا يقول «موجودٌ ومقفول» لا
       «معطَّل» — فالبلاطة تبقى قابلة للضغط وتقود إلى شاشة الاشتراك. */

    .stk-tile--locked { border-style: dashed; }
    .stk-tile--locked .stk-tile__title,
    .stk-tile--locked .stk-tile__hint { color: var(--app-muted); }
    .stk-tile--locked::before { background: var(--app-danger, #EA5455); }

    /* أحمر لا برتقالي: البرتقالي لغةُ **الاقتراب** من الحدّ — وهو ما تقوله
       شارة الاستهلاك عند ٨٠٪. أمّا القفل فحالةٌ قاطعة: الباب مغلق لا يكاد
       يُغلق. وتمييزهما باللون يجعل الفرق يُدرَك بلا قراءة. */
    .stk-tile--locked .stk-tile__icon {
        background: color-mix(in srgb, var(--app-danger, #EA5455) 12%, transparent);
        color: var(--app-danger, #EA5455);
    }

    .stk-plan-badge {
        display: inline-flex; align-items: center; gap: .25rem;
        margin-inline-start: .4rem; padding: .1rem .45rem;
        border-radius: 999px; font-size: .68rem; font-weight: 600; line-height: 1.5;
        background: color-mix(in srgb, var(--app-danger, #EA5455) 14%, transparent);
        color: var(--app-danger, #EA5455);
        vertical-align: middle;
    }

    /* شارةُ الاستهلاك تفترق عن شارة القفل باللون:
       برتقاليٌّ لغةُ **الاقتراب** من الحدّ (٨٠٪ فما فوق)، وأحمرُ لبلوغه.
       والقفلُ أحمرُ أيضاً لأنه حالةٌ قاطعة، فيتّحد المعنى: الأحمر بابٌ
       مغلق، والبرتقاليُّ بابٌ يكاد. */
    .stk-plan-badge--warn {
        background: color-mix(in srgb, var(--app-warning, #FF9F43) 16%, transparent);
        color: var(--app-warning, #FF9F43);
    }

    .stk-plan-badge--full {
        background: var(--app-danger, #EA5455);
        color: #fff;
    }

    /* ═══════════════ زرّ «أنشئ أوّلاً» ═══════════════
       يظهر تحت قائمةٍ منسدلة فارغة وحدها. مظهرُه خفيفٌ عمداً: هو دعوةٌ
       مساعِدة لا فعلٌ رئيسيّ في الشاشة، فلا يزاحم زرّ الحفظ بصرياً. */

    .stk-create-first {
        display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .6rem;
        margin-top: .4rem;
    }

    .stk-create-first__btn {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .22rem .6rem;
        border: 1px dashed color-mix(in srgb, var(--app-primary) 45%, transparent);
        border-radius: 999px;
        background: color-mix(in srgb, var(--app-primary) 8%, transparent);
        color: var(--app-primary);
        font-size: .76rem; font-weight: 600; line-height: 1.7;
        text-decoration: none; cursor: pointer;
        transition: background .14s ease, border-color .14s ease;
    }
    .stk-create-first__btn:hover {
        background: color-mix(in srgb, var(--app-primary) 16%, transparent);
        border-color: var(--app-primary);
        color: var(--app-primary); text-decoration: none;
    }
    .stk-create-first__btn i { font-size: .8rem; }

    .stk-create-first__btn--locked {
        border-color: color-mix(in srgb, var(--app-danger, #EA5455) 45%, transparent);
        background: color-mix(in srgb, var(--app-danger, #EA5455) 10%, transparent);
        color: var(--app-danger, #EA5455);
    }
    .stk-create-first__btn--locked:hover {
        background: color-mix(in srgb, var(--app-danger, #EA5455) 18%, transparent);
        border-color: var(--app-danger, #EA5455);
        color: var(--app-danger, #EA5455);
    }

    .stk-create-first__btn--muted {
        border-color: var(--stk-line); background: transparent;
        color: var(--app-muted); cursor: not-allowed;
    }

    .stk-create-first__hint { font-size: .73rem; color: var(--app-muted); line-height: 1.7; }

    /* قفل بند القائمة الجانبية — لا يُغيّر ارتفاع السطر */
    .stk-plan-lock__icon {
        float: left; margin-top: .18rem;
        font-size: .82rem; opacity: 1;
        color: var(--app-danger, #EA5455);
    }
    .stk-plan-locked > a { opacity: .72; }
    .stk-plan-locked > a:hover { opacity: 1; }

    /* ═══════════════ جدول شجريّ ═══════════════
       المستوى يُرسَم إزاحةً محسوبة في الترميز لا بفئة لكل عمق، فالشجرة
       تعمق بلا حدّ. وخطّ رفيع يربط الابن بأبيه بصرياً. */

    .stk-tree__cell { display: flex; align-items: center; gap: var(--stk-2); min-width: 0; }

    .stk-tree__toggle {
        flex: none; width: 22px; height: 22px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 0; background: transparent;
        border-radius: var(--app-radius);
        color: var(--app-muted); font-size: .78rem;
        cursor: pointer;
        transition: transform .18s ease, background .14s ease, color .14s ease;
    }
    .stk-tree__toggle:hover { background: var(--app-primary-soft); color: var(--app-primary); }
    .stk-tree__toggle.is-open { transform: rotate(-180deg); }
    .stk-tree__toggle.is-empty { cursor: default; }
    .stk-tree__toggle:focus-visible { outline: 2px solid var(--app-primary); outline-offset: 1px; }

    .stk-tree__icon {
        flex: none; width: 28px; height: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: var(--app-radius);
        background: color-mix(in srgb, var(--app-muted) 10%, transparent);
        color: var(--app-muted); font-size: .82rem;
    }
    .stk-tree__icon.is-parent { background: var(--app-primary-soft); color: var(--app-primary); }

    .stk-tree__name {
        min-width: 0; font-size: .875rem; color: var(--app-text);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .stk-tree__name.is-parent { font-weight: 700; }

    .stk-tree tbody tr[data-level="0"] > td { background: color-mix(in srgb, var(--app-primary) 2%, transparent); }

    /* ═══════════════ شاشة الوحدات ═══════════════
       الوحدات علاقة رياضية لا قائمة سجلّات: وحدةٌ مرجعٌ وبقيّةٌ تُنسب إليها.
       الجدول كان يعرض «معامل التحويل: 12» في عمود، فلا يُفهم منه ماذا يساوي
       ماذا. هنا المرجع بطاقةٌ بارزة، وكل وحدة فرعية معادلةٌ تُقرأ. */

    .stk-base-card {
        display: flex; align-items: center; gap: var(--stk-4);
        padding: var(--stk-5);
        margin-bottom: var(--stk-5);
        border-radius: var(--stk-r-lg);
        background: linear-gradient(135deg,
            var(--app-primary) 0%,
            color-mix(in srgb, var(--app-primary) 72%, #000) 100%);
        color: var(--app-on-primary);
        box-shadow: 0 10px 28px var(--app-primary-glow);
    }

    .stk-base-card__mark {
        flex: none; width: 56px; height: 56px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: rgba(255, 255, 255, .16);
        font-size: 1.5rem;
    }

    .stk-base-card__body { flex: 1; min-width: 0; }
    .stk-base-card__eyebrow {
        display: block; font-size: .72rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase; opacity: .78;
    }
    .stk-base-card__name { font-size: 1.5rem; font-weight: 700; margin: .1rem 0 .2rem; line-height: 1.2; }
    .stk-base-card__hint { font-size: .82rem; opacity: .82; margin: 0; }
    .stk-base-card__actions .btn {
        background: rgba(255, 255, 255, .14);
        border-color: rgba(255, 255, 255, .3);
        color: var(--app-on-primary);
    }
    .stk-base-card__actions .btn:hover { background: rgba(255, 255, 255, .24); color: var(--app-on-primary); }

    /* شبكة الوحدات الفرعية */
    .stk-unit-grid {
        display: grid; gap: var(--stk-4);
        grid-template-columns: repeat(auto-fill, minmax(248px, 1fr));
    }

    .stk-unit-card {
        background: var(--app-surface);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r-lg);
        box-shadow: var(--stk-e2);
        overflow: hidden;
        transition: box-shadow .18s ease, transform .18s ease;
    }
    .stk-unit-card:hover { box-shadow: var(--stk-e3); transform: translateY(-2px); }

    .stk-unit-card__head {
        display: flex; align-items: center; gap: var(--stk-2);
        padding: var(--stk-3) var(--stk-4);
        border-bottom: 1px solid var(--stk-line-soft);
    }
    .stk-unit-card__icon {
        flex: none; width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: var(--app-primary-soft); color: var(--app-primary);
        font-size: .9rem;
    }
    .stk-unit-card__name {
        flex: 1; min-width: 0; font-weight: 700; font-size: .92rem; color: var(--app-text);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .stk-unit-card__head .stk-actions .btn { width: 28px; height: 28px; font-size: .74rem; }
    .stk-unit-card__factor { font-size: .74rem; color: var(--app-muted); white-space: nowrap; }

    /* حين تحمل البطاقة نموذجاً مباشرةً (شاشتا إضافة المنتج وتعديله) */
    .stk-unit-card > .row { margin: 0; padding: var(--stk-4); }
    .stk-unit-card + .stk-unit-card { margin-top: var(--stk-4); }
    .stk-unit-card label { font-size: .78rem; }

    /* مفتاح مصغَّر لداخل البطاقات */
    .stk-switch--sm .stk-switch__track { width: 34px; height: 20px; }
    .stk-switch--sm .stk-switch__thumb { width: 14px; height: 14px; }
    .stk-switch--sm > input:checked + .stk-switch__track .stk-switch__thumb { transform: translateX(-14px); }
    html[dir="ltr"] .stk-switch--sm > input:checked + .stk-switch__track .stk-switch__thumb { transform: translateX(14px); }
    .stk-switch--sm .stk-switch__title { font-size: .78rem; }

    /* معادلة التحويل */
    .stk-equation {
        display: flex; align-items: center; justify-content: center; gap: var(--stk-3);
        padding: var(--stk-4);
        background: var(--stk-tint);
    }
    .stk-equation__side {
        display: flex; flex-direction: column; align-items: center; gap: .1rem;
        min-width: 0; text-align: center;
    }
    .stk-equation__side b {
        font-size: 1.25rem; font-weight: 700; color: var(--app-text); line-height: 1.1;
    }
    .stk-equation__side small {
        font-size: .72rem; color: var(--app-muted);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 90px;
    }
    .stk-equation__side--to b { color: var(--app-primary); }
    .stk-equation__eq {
        flex: none; font-size: 1.1rem; font-weight: 700; color: var(--app-muted); opacity: .6;
    }

    /* معاينة حيّة فوق نموذج الوحدة */
    .stk-preview {
        text-align: center;
        padding: var(--stk-5) var(--stk-4) var(--stk-4);
        margin-bottom: var(--stk-4);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r-lg);
        background: var(--stk-tint);
        transition: background .2s ease, border-color .2s ease;
    }
    .stk-preview__label {
        display: block; font-size: .72rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase;
        color: var(--app-muted); margin-bottom: var(--stk-2);
    }
    .stk-preview.is-base {
        background: var(--app-primary-soft);
        border-color: color-mix(in srgb, var(--app-primary) 35%, transparent);
    }
    .stk-preview .stk-equation { background: transparent; padding: 0; }
    .stk-equation--lg .stk-equation__side b { font-size: 1.9rem; }
    .stk-equation--lg .stk-equation__side small { font-size: .8rem; max-width: 140px; }
    .stk-equation--lg .stk-equation__eq { font-size: 1.4rem; }

    /* صندوق الخيار أسفل النموذج */
    .stk-choice {
        padding: var(--stk-4);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--stk-tint);
    }

    /* منتقي لون: مربّع اللون وقيمته السداسية جنباً إلى جنب */
    .stk-color { display: inline-flex; align-items: center; gap: .5rem; }
    .stk-color__input {
        width: 44px; height: 32px; padding: 2px;
        border: 1px solid var(--stk-line); border-radius: var(--stk-r);
        background: var(--app-surface); cursor: pointer;
    }
    .stk-color__value { font-size: .8rem; color: var(--app-muted); text-transform: uppercase; }

    /* ورقة معاينة: قالب طباعة معروضٌ داخل الشاشة */
    .stk-paper {
        max-width: 760px; margin: 0 auto;
        padding: var(--stk-6);
        background: #fff; color: #2f3349;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        box-shadow: var(--stk-e2);
        overflow-x: auto;
    }
    .stk-paper table { width: 100%; }

    /* صورة مصغَّرة داخل خلية جدول */
    .stk-thumb {
        width: 40px; height: 40px; object-fit: cover;
        border-radius: var(--stk-r);
        border: 1px solid var(--stk-line);
        transition: transform .16s ease, border-color .16s ease;
    }
    .stk-thumb:hover { transform: scale(1.08); border-color: var(--app-primary); }


    /* ═══════════════ القوائم المنسدلة ═══════════════

       **العطل**

       `.card` أعلاه تحمل `overflow: hidden` — وهي ضرورية لتبقى الزوايا
       مستديرة على الجداول والصور التي تلامس حوافّها. لكن بوتستراب يضع
       قائمة `dropdown-menu` **داخل** البطاقة، فتقصّها البطاقة: تظهر
       نصفَ قائمةٍ معلّقة عند الحافّة أو تختفي كلّياً. و`.table-responsive`
       تضيف `overflow-x: auto` فتقصّ أفقياً كذلك.

       والجداول في المخزون كلّها أزرارُ إجراءاتها قوائمُ منسدلة داخل
       بطاقاتٍ داخل جداول متجاوبة — أي أنّ العطل يصيبها جميعاً.

       **العلاج**

       مظهرٌ موحَّد للقائمة هنا، وسكربتٌ في ذيل الملف يجعل بوتستراب
       يضعها بـ`position: fixed` فتخرج من كل ما يقصّ. */

    /* stk-dropdown-fix */
    .dropdown-menu {
        z-index: 1060;
        min-width: 190px;
        padding: .35rem;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--app-surface);
        box-shadow: var(--stk-e3);
    }

    .dropdown-menu .dropdown-item {
        display: flex; align-items: center; gap: .55rem;
        padding: .5rem .7rem;
        border-radius: 8px;
        font-size: .85rem; font-weight: 600;
        color: var(--app-text);
        transition: background .14s ease, color .14s ease;
    }

    .dropdown-menu .dropdown-item i { font-size: .95rem; color: var(--app-muted); }

    .dropdown-menu .dropdown-item:hover,
    .dropdown-menu .dropdown-item:focus {
        background: var(--app-primary-soft);
        color: var(--app-primary);
    }

    .dropdown-menu .dropdown-item:hover i { color: var(--app-primary); }

    .dropdown-menu .dropdown-item.text-danger:hover,
    .dropdown-menu .dropdown-item.text-danger:focus {
        background: var(--app-danger-soft);
        color: var(--app-danger);
    }

    .dropdown-menu .dropdown-item.text-danger:hover i { color: var(--app-danger); }

    /* زرّ الحذف داخل نموذج: يرثه بوتستراب زرّاً لا بنداً */
    .dropdown-menu form { margin: 0; }
    .dropdown-menu form .dropdown-item { width: 100%; text-align: inherit; border: 0; background: none; }

    .dropdown-menu .dropdown-divider { margin: .3rem .2rem; border-color: var(--stk-line-soft); }

    /* ═══════════════ حقل كمّية بزرّين ═══════════════
       إدخال الكمّيّات أثناء الجرد يجري بيدٍ واحدة على شاشة صغيرة، فزرّا
       الزيادة والنقصان أسرع من الكتابة. */

    .stk-stepper {
        display: inline-flex; align-items: stretch;
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--app-surface);
        overflow: hidden;
        width: 100%; max-width: 190px;
    }
    .stk-stepper:focus-within {
        border-color: var(--app-primary);
        box-shadow: 0 0 0 3px var(--app-primary-soft);
    }
    .stk-stepper__btn {
        flex: none; width: 34px; border: 0;
        background: var(--stk-tint); color: var(--app-muted);
        font-size: .78rem; cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    .stk-stepper__btn:hover { background: var(--app-primary-soft); color: var(--app-primary); }
    .stk-stepper__input {
        flex: 1; min-width: 0; border: 0; outline: 0;
        text-align: center; font-weight: 700; font-size: .88rem;
        color: var(--app-text); background: transparent;
        padding: .35rem .2rem;
        /* الأسهم الأصلية تزاحم الزرّين */
        -moz-appearance: textfield;
    }
    .stk-stepper__input::-webkit-outer-spin-button,
    .stk-stepper__input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    /* ═══════════════ اختيار متعدّد ═══════════════
       عشرات المربّعات في شبكة أعمدة تُقرأ صفوفاً متقطّعة. هنا رقائق تلتفّ،
       والمُختار منها يتلوّن كاملاً فيُرى من مسافة. */

    .stk-picks { display: flex; flex-wrap: wrap; gap: var(--stk-2); }

    .stk-pick {
        display: inline-flex; align-items: center; gap: .5rem;
        margin: 0; padding: .42rem .8rem;
        border: 1px solid var(--stk-line);
        border-radius: 999px;
        background: var(--app-surface);
        cursor: pointer; user-select: none;
        transition: border-color .15s ease, background .15s ease, color .15s ease;
    }
    .stk-pick:hover { border-color: color-mix(in srgb, var(--app-primary) 40%, transparent); }
    .stk-pick > input { position: absolute; opacity: 0; width: 0; height: 0; }

    .stk-pick__box {
        flex: none; width: 16px; height: 16px; border-radius: 4px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid var(--stk-line);
        background: var(--app-surface);
        font-size: .6rem; color: transparent;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }
    .stk-pick__label { font-size: .83rem; font-weight: 600; color: var(--app-text); }

    .stk-pick > input:checked ~ .stk-pick__box {
        background: var(--app-primary); border-color: var(--app-primary); color: #fff;
    }
    .stk-pick:has(> input:checked) {
        background: var(--app-primary-soft);
        border-color: color-mix(in srgb, var(--app-primary) 45%, transparent);
    }
    .stk-pick:has(> input:checked) .stk-pick__label { color: var(--app-primary); }
    .stk-pick > input:focus-visible ~ .stk-pick__box { outline: 2px solid var(--app-primary); outline-offset: 2px; }

    /* بلاطة الإضافة في نهاية الشبكة */
    .stk-unit-add {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: var(--stk-2); min-height: 132px;
        border: 2px dashed var(--stk-line);
        border-radius: var(--stk-r-lg);
        color: var(--app-muted); text-decoration: none;
        font-size: .85rem; font-weight: 600;
        transition: border-color .18s ease, color .18s ease, background .18s ease;
    }
    .stk-unit-add:hover {
        border-color: color-mix(in srgb, var(--app-primary) 45%, transparent);
        background: var(--app-primary-soft);
        color: var(--app-primary); text-decoration: none;
    }
    .stk-unit-add > i { font-size: 1.4rem; }

    /* ═══════════════ صفحة التفاصيل ═══════════════ */

    .stk-avatar-lg {
        display: inline-flex; align-items: center; justify-content: center;
        width: 88px; height: 88px;
        border-radius: var(--stk-r-lg);
        object-fit: cover;
        background: var(--app-primary-soft); color: var(--app-primary);
        border: 1px solid var(--stk-line);
        font-size: 2rem;
    }

    /* شريط مقاييس أسفل بطاقة الهوية */
    .stk-metrics {
        display: grid; grid-auto-flow: column; grid-auto-columns: 1fr;
        border-top: 1px solid var(--stk-line-soft);
        background: var(--stk-tint);
    }
    .stk-metric {
        display: flex; flex-direction: column; align-items: center; gap: .1rem;
        padding: var(--stk-3) var(--stk-2);
    }
    .stk-metric + .stk-metric { border-inline-start: 1px solid var(--stk-line-soft); }
    .stk-metric__value {
        font-size: 1.15rem; font-weight: 700; color: var(--app-text);
        font-variant-numeric: tabular-nums; line-height: 1.2;
    }
    .stk-metric__label { font-size: .74rem; color: var(--app-muted); }
    .stk-metric__icon {
        width: 30px; height: 30px; border-radius: 8px; margin-bottom: .15rem;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--app-primary-soft); color: var(--app-primary); font-size: .82rem;
    }

    /* ── نبرة البطاقة ──
       الرقم وحده لا يقول إن كان جيّداً: «٦ فروع» لا معنى لها حتى تُقرأ
       بجانب «منها ٢ متوقّفة». والنبرة تلوّن الأيقونة فتُميَّز البطاقات
       بلمحةٍ قبل قراءة أرقامها. */
    .stk-metric--success .stk-metric__icon { background: var(--app-success-soft); color: var(--app-success); }
    .stk-metric--info    .stk-metric__icon { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); color: var(--app-info, #00cfe8); }
    .stk-metric--warning .stk-metric__icon { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
    .stk-metric--danger  .stk-metric__icon { background: color-mix(in srgb, var(--app-danger, #ea5455) 12%, transparent); color: var(--app-danger, #ea5455); }

    /* ── سطر الملاحظة ──
       جاء من `bx-stat` في وحدة الفروع حين وُحِّد النظامان: كان الأغنى
       فرُفع إلى المشترك بدل أن يُسقَط، فربحته الشاشاتُ الأخرى كذلك. */
    .stk-metric__note {
        display: inline-flex; align-items: center; gap: .25rem;
        margin-top: .25rem; font-size: .68rem; line-height: 1.5;
        color: var(--app-muted);
    }
    .stk-metric__note i { font-size: .7rem; }
    .stk-metric__note.up   { color: var(--app-success); }
    .stk-metric__note.down { color: var(--app-danger, #ea5455); }

    /* الشريط نفسه بطاقةً قائمة بذاتها أعلى الصفحة، لا ذيلاً لبطاقة أخرى.
       يلتفّ على الشاشات الضيّقة بدل أن ينضغط ستّ خاناتٍ في سطر. */
    .stk-metrics {
        grid-auto-flow: row; grid-auto-columns: auto;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r-lg);
        box-shadow: var(--stk-e2);
        background: var(--app-surface);
        margin-bottom: var(--stk-5);
        overflow: hidden;
    }
    .stk-metric { border-inline-start: 1px solid var(--stk-line-soft); }
    .stk-metric:first-child { border-inline-start: 0; }

    /* صورة المنتج في صفحة العرض */
    .stk-photo {
        max-width: 100%; max-height: 220px;
        border-radius: var(--stk-r-lg);
        border: 1px solid var(--stk-line-soft);
        background: var(--stk-tint);
    }

    /* صفّ الإجماليات أسفل جدول الكميات */
    .stk-total-row td { background: var(--stk-tint); font-weight: 700; }

    /* قائمة تعريف: مفتاح وقيمة في صفّ */
    .stk-def { margin: 0; }
    .stk-def__row {
        display: grid; grid-template-columns: 150px 1fr; gap: var(--stk-3);
        padding: var(--stk-3) 0;
        border-bottom: 1px solid var(--stk-line-soft);
    }
    .stk-def__row:last-child { border-bottom: 0; padding-bottom: 0; }
    .stk-def__row:first-child { padding-top: 0; }
    .stk-def dt { font-size: .82rem; color: var(--app-muted); font-weight: 600; margin: 0; }
    .stk-def dd { font-size: .875rem; color: var(--app-text); margin: 0; min-width: 0; }
    .stk-def dd a { color: var(--app-primary); font-weight: 600; }

    @media (max-width: 575px) {
        .stk-def__row { grid-template-columns: 1fr; gap: .15rem; }
    }

    /* بطاقات تفاصيل مضغوطة — شاشة عرض المنتج ونظائرها: خمسون حقلاً في
       عمودٍ واحد برؤوس بطاقاتٍ كاملة كانت تصنع تبويبةً لا تنتهي.
       عمودان على الشاشات الواسعة، وصفوفٌ ورؤوسٌ وهوامش أخفّ */
    .stk-card--tight { margin-bottom: .75rem; }
    .stk-card--tight > .card-header { padding: .55rem 1rem; min-height: 0; }
    .stk-card--tight > .card-header .card-title { font-size: .88rem; margin: 0; }
    .stk-card--tight > .card-body { padding: .65rem 1rem .75rem; }

    .stk-def--compact { display: grid; grid-template-columns: 1fr; column-gap: 1.75rem; }
    .stk-def--compact .stk-def__row {
        grid-template-columns: 118px 1fr; gap: .45rem;
        padding: .3rem 0;
    }
    .stk-def--compact dt { font-size: .76rem; }
    .stk-def--compact dd { font-size: .8rem; }

    @media (min-width: 768px) {
        .stk-def--compact { grid-template-columns: 1fr 1fr; }
        /* في شبكة العمودين «آخر صف» لم يعد واحداً — يتساوى الجميع */
        .stk-def--compact .stk-def__row:first-child { padding-top: .3rem; }
        .stk-def--compact .stk-def__row:last-child { padding-bottom: .3rem; }
    }

    @media (max-width: 575px) {
        .stk-def--compact .stk-def__row { grid-template-columns: 1fr; gap: .1rem; }
    }

    /* صيغة أفقية من القائمة نفسها: أربع قيمٍ قصيرة داخل بطاقة ضيّقة
       أوضح متجاورةً منها في أربعة أسطر */
    .stk-def--inline { display: flex; flex-wrap: wrap; gap: .35rem .5rem; }
    .stk-def--inline .stk-def__row {
        display: inline-flex; align-items: baseline; gap: .35rem;
        grid-template-columns: none; border: 0; padding: .2rem .55rem;
        border-radius: 6px; background: var(--stk-tint);
        font-size: .78rem; color: var(--app-muted);
    }
    .stk-def--inline .stk-def__row b { color: var(--app-text); font-weight: 700; }

    /* ═══════════════ تبويبات ترويسة الجدول ═══════════════ */

    .stk-tabs { border: 0; gap: .2rem; flex-wrap: wrap; margin: 0; }
    .stk-tabs .nav-link {
        border: 0; border-radius: 8px; padding: .4rem .9rem;
        font-size: .84rem; font-weight: 600; color: var(--app-muted);
        background: transparent; transition: background .15s, color .15s;
    }
    .stk-tabs .nav-link:hover { background: var(--stk-tint); color: var(--app-text); }
    .stk-tabs .nav-link.active { background: var(--app-primary-soft); color: var(--app-primary); }

    /* ═══════════════ جدول بترويسة مجمَّعة ═══════════════
       «الوارد» و«المنصرف» خمسة أعمدة لكلٍّ منهما. الفاصل الرأسي واللون
       الخفيف هما ما يمنع العين من الضياع بين اثني عشر عموداً. */

    .stk-grouped thead tr:first-child th { font-size: .8rem; letter-spacing: .02em; }
    .stk-group--in  { background: var(--app-success-soft) !important; color: var(--app-success) !important; }
    .stk-group--out { background: var(--app-danger-soft)  !important; color: var(--app-danger)  !important; }

    /* عمود الإجمالي داخل كل مجموعة: أثقل ممّا حوله وله حدّ يفصله عن التالي */
    .stk-grouped .stk-group__sum {
        font-weight: 700;
        background: var(--stk-tint);
        border-inline-end: 2px solid var(--stk-line);
    }
    .stk-grouped th:first-child, .stk-grouped td:first-child {
        border-inline-end: 2px solid var(--stk-line);
    }

    /* ═══════════════ الطباعة ═══════════════
       تقارير المخزون تُطبع كثيراً. الشريط الجانبي والترويسة والأزرار لا مكان
       لها على الورق، والحدود تصير رمادية داكنة لتظهر في الطباعة أحادية اللون. */

    .stk-print-head { display: none; }

    .stk-writein {
        display: block; min-height: 1.4rem;
        border-bottom: 1px dashed var(--stk-line);
    }

    @media print {
        .main-menu, .header-navbar, .footer,
        .stk-page-head, .stk-filters, .stk-table-foot,
        .breadcrumb, .btn, .dropdown { display: none !important; }

        .app-content, .content-wrapper, .content-body { margin: 0 !important; padding: 0 !important; }

        .stk-print-head {
            display: block; text-align: center;
            padding: 0 0 .6rem; margin-bottom: .6rem;
            border-bottom: 2px solid #000;
        }
        .stk-print-head h3 { margin: 0; font-size: 15pt; }
        .stk-print-head p { margin: .2rem 0 0; font-size: 9pt; color: #444; }

        .card, .stk-table-card {
            box-shadow: none !important; border: 0 !important;
            margin: 0 !important; break-inside: auto;
        }
        .table { font-size: 9pt; }
        .table th, .table td { border: 1px solid #999 !important; padding: 4px 6px !important; }
        .table thead th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stk-writein { border-bottom: 0; min-height: 1.1rem; }
        tr { break-inside: avoid; }
    }

    /*
        مُنتقي الجلسة في ترويسة بطاقة — «أيُّ درجٍ يُدار الآن؟»

        في الترويسة لا في النموذج: هو يُبدّل سياقَ الصفحة كلِّها (الجدولُ
        والبطاقاتُ والنموذج معاً)، لا حقلاً من حقول الحركة.
    */
    .stk-session-pick { display: flex; align-items: center; gap: .4rem; margin: 0; }

    .stk-session-pick label {
        margin: 0; font-size: .8rem; font-weight: 600;
        color: var(--app-text); opacity: .7; white-space: nowrap;
    }

    .stk-session-pick select {
        width: auto; min-width: 190px;
        height: 32px; padding: 0 .55rem;
        font-size: .82rem;
    }

    /* نطاق صلاحية: قائمة «لمن؟» وما يتبعها في إطارٍ واحد */
    .stk-scope {
        padding: var(--stk-4);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--stk-tint);
        height: 100%;
    }
    .stk-scope__target { margin-top: var(--stk-3) !important; }

    /* بلاطة بلا وجهة بعد: تُقرأ معطَّلةً ولا تدعو للضغط */
    .stk-tile--soon {
        cursor: default; opacity: .62;
        background: var(--stk-tint);
    }
    .stk-tile--soon:hover { transform: none; box-shadow: none; border-color: var(--stk-line); }

    /* مُنتقي عدد السجلّات: عنصر واحد مضموم، لا ثلاثة عناصر متجاورة */
    .stk-perpage {
        display: inline-flex; align-items: stretch;
        border: 1px solid var(--stk-line);
        border-radius: 999px;
        background: var(--app-surface);
        overflow: hidden;
        transition: border-color .16s ease, box-shadow .16s ease;
    }
    .stk-perpage:focus-within {
        border-color: var(--app-primary);
        box-shadow: 0 0 0 3px var(--app-primary-soft);
    }
    .stk-perpage label,
    .stk-perpage__unit {
        display: inline-flex; align-items: center;
        margin: 0; padding: .32rem .8rem;
        font-size: .78rem; font-weight: 600; color: var(--app-muted);
        background: var(--stk-tint);
        white-space: nowrap;
    }
    .stk-perpage__select {
        appearance: none; -webkit-appearance: none;
        border: 0; outline: 0; box-shadow: none;
        padding: .32rem 1.5rem .32rem .7rem;
        font-size: .84rem; font-weight: 700; color: var(--app-text);
        background: var(--app-surface);
        font-variant-numeric: tabular-nums;
        cursor: pointer;
        /* السهم مرسومٌ في الخلفية: `appearance:none` يُزيل سهم المتصفّح */
        background-image: linear-gradient(45deg, transparent 50%, var(--app-muted) 50%),
                          linear-gradient(135deg, var(--app-muted) 50%, transparent 50%);
        background-position: calc(0.62rem) calc(50% + 1px), calc(0.9rem) calc(50% + 1px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
    }
    .stk-perpage__select:focus { background-color: var(--stk-tint); }

    /* ═══════════════ حركة المخزون ═══════════════ */

    /* كميّة داخلة أو خارجة: السهم واللون معاً — اللون وحده لا يكفي مَن لا يميّزه */
    .stk-move {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: 1rem; font-weight: 700; line-height: 1.3;
    }
    .stk-move--success { color: var(--app-success); }
    .stk-move--danger  { color: var(--app-danger); }
    .stk-move--muted   { color: var(--app-muted); }

    .stk-balance { font-size: 1rem; font-weight: 700; color: var(--app-text); }

    /* ═══════════════ الباركود ═══════════════ */

    .stk-barcode {
        display: inline-block; padding: var(--stk-5) var(--stk-6);
        border: 1px dashed var(--stk-line); border-radius: var(--stk-radius);
        background: #fff;   /* الباركود يُقرأ بالماسح: الخلفية بيضاء دائماً */
    }
    .stk-barcode__svg { max-width: 100%; height: auto; }
    .stk-barcode__meta {
        margin-top: var(--stk-3); display: flex; flex-direction: column; gap: .2rem;
        font-size: .85rem; color: #1a1a1a;
    }
    .stk-barcode__price { font-weight: 700; color: var(--app-success); }

    /* ═══════════════ الخطّ الزمني ═══════════════
       سجلّ النشاط وحركة المخزون: عمودٌ رأسيّ تتعلّق عليه الأحداث. */

    .stk-timeline { list-style: none; margin: 0; padding: 0; position: relative; }
    .stk-timeline::before {
        content: ''; position: absolute; top: .5rem; bottom: .5rem;
        inset-inline-start: 15px; width: 2px; background: var(--stk-line-soft);
    }
    .stk-timeline__item {
        position: relative; display: flex; gap: var(--stk-4);
        padding: 0 0 var(--stk-5) 0;
    }
    .stk-timeline__item:last-child { padding-bottom: 0; }

    .stk-timeline__dot {
        flex: none; width: 32px; height: 32px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .8rem; z-index: 1;
        border: 2px solid var(--app-card, #fff);
        background: color-mix(in srgb, var(--app-muted) 14%, transparent);
        color: var(--app-muted);
    }
    .stk-timeline__dot--in  { background: var(--app-success-soft); color: var(--app-success); }
    .stk-timeline__dot--out { background: var(--app-danger-soft);  color: var(--app-danger); }
    .stk-timeline__dot--log { background: var(--app-primary-soft); color: var(--app-primary); }

    .stk-timeline__body { min-width: 0; flex: 1; }
    .stk-timeline__head {
        margin: 0 0 .4rem; font-size: .875rem; line-height: 1.7; color: var(--app-text);
        display: flex; flex-wrap: wrap; align-items: baseline; gap: .35rem;
    }
    .stk-timeline__head a { color: var(--app-primary); font-weight: 600; }
    .stk-timeline__qty { font-weight: 700; color: var(--app-primary); }
    .stk-timeline__time { margin-inline-start: auto; font-size: .75rem; color: var(--app-muted); }

    .stk-timeline__text { font-size: .85rem; color: var(--app-text); line-height: 1.8; }
    .stk-timeline__text p:last-child { margin-bottom: 0; }

    .stk-timeline__meta {
        margin: .45rem 0 0; display: flex; flex-wrap: wrap; gap: .25rem .9rem;
        font-size: .74rem; color: var(--app-muted);
    }

    /* رأس يوم: يفصل السجلّات ويُبقي التاريخ ظاهراً وأنت تمرّ عليها */
    .stk-timeline__day {
        position: relative; z-index: 1; margin: 0 0 var(--stk-4);
        display: flex; align-items: center;
    }
    .stk-timeline__day:not(:first-child) { margin-top: var(--stk-3); }
    .stk-timeline__day span {
        padding: .28rem .8rem; border-radius: 999px;
        background: var(--stk-tint); border: 1px solid var(--stk-line-soft);
        font-size: .78rem; font-weight: 600; color: var(--app-text);
    }

    /* ═══════════════ رفع الصورة ═══════════════
       كان حقل الملف عارياً بلا معاينة: تختار صورةً ولا ترى ما اخترت. */

    .stk-upload {
        display: flex; align-items: center; justify-content: center;
        width: 100%; min-height: 190px; margin: 0;
        padding: var(--stk-4);
        border: 2px dashed var(--stk-line);
        border-radius: var(--stk-r-lg);
        background: var(--stk-tint);
        cursor: pointer; overflow: hidden; position: relative;
        transition: border-color .18s ease, background .18s ease;
    }
    .stk-upload:hover {
        border-color: color-mix(in srgb, var(--app-primary) 45%, transparent);
        background: var(--app-primary-soft);
    }
    .stk-upload__preview { max-width: 100%; max-height: 220px; border-radius: var(--stk-r); display: block; }
    .stk-upload__placeholder {
        display: flex; flex-direction: column; align-items: center; gap: .35rem;
        text-align: center; color: var(--app-muted);
    }
    .stk-upload__placeholder > i { font-size: 1.9rem; color: var(--app-primary); margin-bottom: .3rem; }
    .stk-upload__title { font-size: .86rem; font-weight: 600; color: var(--app-text); }
    .stk-upload__hint  { font-size: .74rem; }

    /* ═══════════════ مفتاح التبديل ═══════════════
       كان `custom-switch` من القالب: صغير وبلا شرح لما يفعله. */

    .stk-switch {
        display: flex; align-items: flex-start; gap: var(--stk-3);
        margin: 0; cursor: pointer; user-select: none;
    }
    .stk-switch > input { position: absolute; opacity: 0; width: 0; height: 0; }

    .stk-switch__track {
        flex: none; position: relative;
        width: 42px; height: 24px; border-radius: 999px;
        background: color-mix(in srgb, var(--app-muted) 28%, transparent);
        transition: background .18s ease;
        margin-top: 2px;
    }
    .stk-switch__thumb {
        position: absolute; top: 3px; inset-inline-start: 3px;
        width: 18px; height: 18px; border-radius: 50%;
        background: var(--app-surface);
        box-shadow: var(--stk-e2);
        transition: transform .18s ease;
    }
    .stk-switch > input:checked + .stk-switch__track { background: var(--app-primary); }
    .stk-switch > input:checked + .stk-switch__track .stk-switch__thumb { transform: translateX(-18px); }
    html[dir="ltr"] .stk-switch > input:checked + .stk-switch__track .stk-switch__thumb { transform: translateX(18px); }
    .stk-switch > input:focus-visible + .stk-switch__track { outline: 2px solid var(--app-primary); outline-offset: 2px; }

    .stk-switch__label { display: flex; flex-direction: column; gap: .1rem; }
    .stk-switch__title { font-size: .87rem; font-weight: 600; color: var(--app-text); }
    .stk-switch__hint  { font-size: .75rem; color: var(--app-muted); line-height: 1.5; }

    /* ═══════════════ بطاقة وحدة القياس ═══════════════ */

    .stk-unit {
        display: flex; align-items: center; gap: var(--stk-3);
        padding: var(--stk-3) var(--stk-4);
        border: 1px solid var(--stk-line);
        border-radius: var(--stk-r);
        background: var(--app-surface);
        margin-bottom: var(--stk-2);
        transition: border-color .16s ease, box-shadow .16s ease;
    }
    .stk-unit:hover { box-shadow: var(--stk-e2); }
    .stk-unit.is-base { border-color: color-mix(in srgb, var(--app-success) 42%, transparent); background: var(--app-success-soft); }

    .stk-unit__icon {
        flex: none; width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r);
        background: var(--app-primary-soft); color: var(--app-primary);
    }
    .stk-unit.is-base .stk-unit__icon { background: color-mix(in srgb, var(--app-success) 16%, transparent); color: var(--app-success); }

    .stk-unit__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .1rem; }
    .stk-unit__name { font-weight: 600; font-size: .88rem; color: var(--app-text); display: flex; align-items: center; gap: .4rem; }
    .stk-unit__meta { font-size: .76rem; color: var(--app-muted); font-variant-numeric: tabular-nums; }
    .stk-unit__badge {
        font-size: .68rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px;
        background: var(--app-success); color: var(--app-on-primary);
    }
    .stk-unit__remove { width: 32px; height: 32px; padding: 0; flex: none; box-shadow: none; }

    /* ═══════════════ الحالة الفارغة ═══════════════ */

    .stk-empty { text-align: center; padding: var(--stk-6) var(--stk-5); color: var(--app-muted); }
    .stk-empty__icon {
        width: 60px; height: 60px; margin: 0 auto var(--stk-4);
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; background: var(--app-primary-soft); color: var(--app-primary);
        font-size: 1.6rem;
    }
    .stk-empty__title { color: var(--app-text); font-weight: 700; font-size: 1rem; margin-bottom: .35rem; }
    .stk-empty__hint  { font-size: .85rem; margin: 0 0 var(--stk-4); }

    /* ═══════════════ التنبيهات والنوافذ ═══════════════ */

    .alert {
        border: 1px solid transparent; border-radius: var(--stk-r);
        padding: var(--stk-3) var(--stk-4); font-size: .85rem;
        display: flex; align-items: center; gap: var(--stk-2);
    }
    .alert-success { background: var(--app-success-soft); border-color: color-mix(in srgb, var(--app-success) 30%, transparent); color: var(--app-success); }
    .alert-danger  { background: var(--app-danger-soft);  border-color: color-mix(in srgb, var(--app-danger) 30%, transparent);  color: var(--app-danger); }
    .alert-warning { background: color-mix(in srgb, var(--app-warning) 12%, transparent); border-color: color-mix(in srgb, var(--app-warning) 30%, transparent); color: var(--app-warning); }
    .alert-info    { background: color-mix(in srgb, var(--app-info) 12%, transparent);    border-color: color-mix(in srgb, var(--app-info) 30%, transparent);    color: var(--app-info); }

    .modal-content { border: 0; border-radius: var(--stk-r-lg); box-shadow: var(--stk-e4); overflow: hidden; }
    .modal-header  { border-bottom: 1px solid var(--stk-line-soft); padding: var(--stk-4) var(--stk-5); }
    .modal-header .modal-title { font-weight: 700; font-size: 1rem; }
    .modal-body    { padding: var(--stk-5); }
    .modal-footer  { border-top: 1px solid var(--stk-line-soft); padding: var(--stk-3) var(--stk-5); gap: var(--stk-2); }
    .modal-footer > * { margin: 0 !important; }

    /* ═══════════════ التبويبات ═══════════════ */

    .nav-tabs { border-bottom: 1px solid var(--stk-line); gap: var(--stk-1); }
    .nav-tabs .nav-link {
        color: var(--app-muted); border: 0; border-bottom: 2px solid transparent;
        font-weight: 600; font-size: .87rem; padding: var(--stk-3) var(--stk-4);
        border-radius: 0; background: transparent;
    }
    .nav-tabs .nav-link:hover { color: var(--app-text); border-bottom-color: var(--stk-line); }
    .nav-tabs .nav-link.active {
        color: var(--app-primary); background: transparent;
        border-bottom-color: var(--app-primary);
    }

    /* شريط تمرير أفقي أوضح للجداول العريضة — الافتراضي رفيعٌ يصعب الإمساك به */
    .stk-scroll { scrollbar-width: thin; scrollbar-color: var(--app-primary) var(--stk-line); }
    .stk-scroll::-webkit-scrollbar { height: 12px; }
    .stk-scroll::-webkit-scrollbar-track { background: var(--stk-line); border-radius: 10px; }
    .stk-scroll::-webkit-scrollbar-thumb {
        background: var(--app-primary); border-radius: 10px;
        border: 2px solid transparent; background-clip: content-box;
    }
    .stk-scroll::-webkit-scrollbar-thumb:hover { background: var(--app-primary); opacity: .8; }

    /* ═══════════════ تخفيف الحركة ═══════════════ */
    @media (prefers-reduced-motion: reduce) {
        .btn, .card, .table-hover tbody tr, .stk-tile, .stk-tile::before,
        .pagination .page-link, .form-control,
        .main-menu .navigation li > ul.menu-content li > a { transition: none !important; }
        .btn:hover, .stk-tile:hover { transform: none; }
    }

    /* وزنُ التأكيد — كان `text-bold-600` من ثيم Vuexy مكتوباً في القوالب،
       وهو صنفٌ برقمٍ مثبَّت لا يقرأ متغيّراتِ الثيم ولا يتبع خطَّه. */
    .stk-strong { font-weight: 600; color: var(--app-heading, inherit); }


    /* ═══════════ وسمُ «ضريبي» ═══════════
       يميّز السندَ الذي اختِيرت له ضريبة. لونُه محايدٌ لا أخضرُ ولا أحمر:
       الضريبيّةُ صفةٌ لا حالةُ نجاحٍ ولا خطأ، وتلوينُها بلونِ حالةٍ يُقرأ
       خطأً في جدولٍ فيه أعمدةُ حالةٍ بالفعل. */
    .tax-mark {
        display: inline-flex; align-items: center; gap: .25rem;
        padding: .1rem .45rem;
        border: 1px solid color-mix(in srgb, var(--app-info, #00cfe8) 40%, transparent);
        border-radius: 999px;
        background: color-mix(in srgb, var(--app-info, #00cfe8) 10%, transparent);
        color: var(--app-info, #00cfe8);
        font-size: .68rem; font-weight: 600; line-height: 1.5;
        vertical-align: middle; white-space: nowrap;
    }

    .tax-mark i { font-size: .72rem; }

    /* صيغةُ الطباعة: بلا خلفيةٍ ولا لون — الطابعةُ الحرارية أحادية،
       والخلفيةُ الملوّنة تخرج رماديةً تحجب النص. */
    .tax-mark-print {
        font-weight: 700; font-size: .78rem; white-space: nowrap;
    }


    /* ═══════════ عارضُ المرفقات ═══════════
       بطاقةٌ لكل ملف: أيقونةٌ بنوعه واسمُه وحجمُه وأفعالُه. الرابطُ النصّيُّ
       وحدَه لا يقول نوعَ الملف ولا حجمَه ولا إن كان موجوداً. */
    .atl { display: flex; flex-direction: column; gap: .5rem; }

    .atl__item {
        display: flex; align-items: center; gap: .7rem;
        padding: .5rem .65rem;
        border: 1px solid var(--stk-line); border-radius: var(--stk-r);
        background: var(--app-surface);
    }
    .atl__item--missing { border-style: dashed; opacity: .75; }

    .atl__thumb {
        flex: 0 0 44px; width: 44px; height: 44px;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--stk-r); overflow: hidden;
        background: var(--stk-tint); color: var(--app-muted);
    }
    .atl__thumb img { width: 100%; height: 100%; object-fit: cover; }

    .atl__body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: .1rem; }
    .atl__name {
        font-weight: 600; font-size: .85rem;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .atl__meta { font-size: .72rem; color: var(--app-muted); }

    .atl__actions { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .25rem; }
    .atl__btn {
        display: inline-flex; align-items: center; gap: .25rem;
        padding: .12rem .5rem;
        border: 1px solid var(--stk-line); border-radius: 999px;
        background: transparent; color: var(--app-primary);
        font-size: .72rem; font-weight: 600; line-height: 1.6;
        text-decoration: none; cursor: pointer;
    }
    .atl__btn:hover { background: var(--app-primary-soft, rgba(115,103,240,.1)); text-decoration: none; }

    .atl__preview {
        border: 1px solid var(--stk-line); border-radius: var(--stk-r); overflow: hidden;
    }
    .atl__preview iframe { display: block; width: 100%; height: 520px; border: 0; }

    @media print { .atl__actions, .atl__preview { display: none; } }


    /* ═══════════ ملخّصُ أخطاء النموذج ═══════════
       أحمرُ صريح في رأس الشاشة: الرسالةُ تحت الحقل تُفوَّت في نموذجٍ أطولَ
       من الشاشة، فيظنّ المستخدمُ أن زرّ الحفظ لا يعمل. */
    .stk-form-errors {
        margin-bottom: var(--stk-4, 1rem);
        padding: .75rem 1rem;
        border: 1px solid var(--app-danger, #EA5455);
        border-inline-start-width: 4px;
        border-radius: var(--stk-r);
        background: color-mix(in srgb, var(--app-danger, #EA5455) 7%, transparent);
        color: var(--app-danger, #EA5455);
    }

    .stk-form-errors__head {
        display: flex; align-items: center; gap: .4rem;
        font-size: .92rem; margin-bottom: .35rem;
    }

    .stk-form-errors__list {
        margin: 0; padding-inline-start: 1.2rem;
        font-size: .84rem; line-height: 1.9; color: var(--app-body, inherit);
    }

    /* الحقلُ الخاطئ يُعلَّم أيضاً: الملخّصُ يقول ما الخطأ وهذا يقول أين */
    .form-control.is-invalid, .is-invalid > .form-control {
        border-color: var(--app-danger, #EA5455);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--app-danger, #EA5455) 15%, transparent);
    }

</style>

{{--
    القوائم المنسدلة تخرج من كل ما يقصّها — وتلتصق بزرّها.

    **العطل الأصلي**

    `.card` تحمل `overflow: hidden` — ضرورية لتبقى الزوايا مستديرة على
    الجداول التي تلامس حوافّها. وبوتستراب يضع `dropdown-menu` **داخل**
    البطاقة بـ`position: absolute`، والعنصر المطلق يقصّه أيّ سلفٍ عليه
    `overflow: hidden`. فتظهر القائمة مقصوصةً عند حافّة الجدول.

    **محاولتان فاشلتان قبل هذه**

    ١) `data-bs-strategy="fixed"` — سمةٌ **لا يقرؤها بوتستراب على `Dropdown`
       أصلاً**: خياراته `autoClose` و`boundary` و`display` و`offset`
       و`popperConfig` و`reference` لا غير. فلم تفعل شيئاً.

    ٢) نقلُ القائمة إلى `<body>` مع `popperConfig.strategy = 'fixed'` —
       أخرجها من القصّ فعلاً، لكن **تحديث Popper الأول غير متزامن**: يضع
       أوّلاً `inset: 0 0 auto auto` (الزاوية العليا في اتّجاه الصفحة) ثم
       يُصحّح الموضع بـ`transform` في دورةٍ لاحقة. فتظهر القائمة لحظةً —
       أو تبقى — في زاوية الشاشة بعيدةً عن زرّها.

    **العلاج**

    لا Popper أصلاً لهذه القوائم: `display: 'static'` يجعل بوتستراب يتخطّاه،
    ثم نحسب الموضع بأنفسنا من مستطيل الزرّ **تزامنياً** في `show` — فلا
    انتظار ولا وميض ولا اعتماد على تفاصيل Popper الداخلية.

    والحساب يراعي اتّجاه الصفحة: `dropdown-menu-end` تُحاذي حافّة القائمة
    البعيدة بحافّة الزرّ البعيدة، ثم يُقصّ الموضع داخل النافذة، ويُقلَب
    إلى أعلى الزرّ إن ضاق ما تحته.

    **ثمنُ النقل، ومن يدفعه**

    نقلُ القائمة إلى `<body>` يضمن ارتسامها فوق كل شيء، لكنّه يُخرج أزرارها
    من نسل حاويتها — فيتوقّف كل معالِج نقرٍ **مفوَّض** إلى تلك الحاوية عن
    المطابقة. تُقرأ النقرة عندئذٍ كأنها `href="#"` وحدها: لا فعل، ولا خطأ
    في الطرفية، ولا شيء يدلّ على السبب.

    الثمن يُدفع في موضع الربط لا هنا: المعالِجات مربوطةٌ بـ`document`
    (`$(document).on('click', '.approve-btn', …)`) لا بحاويةٍ داخل الصفحة.
    و`document` أصلٌ لكل شيء، فيصحّ التفويض أينما حلّت القائمة.

    ويُعاد الحساب مع التمرير وتغيير المقاس ما دامت مفتوحة، لأن `fixed`
    منسوبةٌ إلى النافذة لا إلى الصفحة.
--}}
<script>
    (function () {
        'use strict';

        var GAP = 4;
        var open = null;   // { menu, toggle, home, next }

        function isClipped(element) {
            return !! element.closest('.card, .table-responsive, .modal-body');
        }

        function place() {
            if (! open) {
                return;
            }

            var menu = open.menu;
            var rect = open.toggle.getBoundingClientRect();
            var width = menu.offsetWidth;
            var height = menu.offsetHeight;

            // المحاذاة الأفقية: `-end` تحاذي الحافّة البعيدة عن اتّجاه القراءة
            var rtl = getComputedStyle(document.documentElement).direction === 'rtl';
            var alignEnd = menu.classList.contains('dropdown-menu-end');
            var left = (alignEnd !== rtl) ? rect.right - width : rect.left;

            if (alignEnd && rtl) {
                left = rect.right - width;
            } else if (! alignEnd && rtl) {
                left = rect.left;
            }

            left = Math.max(GAP, Math.min(left, window.innerWidth - width - GAP));

            // رأسياً: تحت الزرّ، وتُقلَب فوقه إن ضاق ما تحته
            var top = rect.bottom + GAP;

            if (top + height > window.innerHeight - GAP && rect.top - height - GAP > GAP) {
                top = rect.top - height - GAP;
            }

            top = Math.max(GAP, Math.min(top, window.innerHeight - height - GAP));

            menu.style.position = 'fixed';
            menu.style.left = Math.round(left) + 'px';
            menu.style.top = Math.round(top) + 'px';
            menu.style.right = 'auto';
            menu.style.bottom = 'auto';
            menu.style.margin = '0';
            menu.style.transform = 'none';
        }

        /* بوتستراب يتخطّى Popper حين يكون `display` ساكناً */
        document.addEventListener('mousedown', function (event) {
            var toggle = event.target.closest('[data-bs-toggle="dropdown"]');

            if (! toggle || toggle.dataset.stkDropdownReady) {
                return;
            }

            if (typeof bootstrap === 'undefined' || ! bootstrap.Dropdown) {
                return;
            }

            if (! isClipped(toggle)) {
                return;
            }

            toggle.dataset.stkDropdownReady = '1';

            // التهيئة قبل بوتستراب، فيتبنّى `getOrCreateInstance` نسختنا
            bootstrap.Dropdown.getOrCreateInstance(toggle, { display: 'static' });
        }, true);

        document.addEventListener('show.bs.dropdown', function (event) {
            var toggle = event.target;
            var container = toggle.closest('.dropdown, .btn-group, .dropup');
            var menu = container ? container.querySelector(':scope > .dropdown-menu') : null;

            if (! menu || ! isClipped(menu)) {
                return;
            }

            open = { menu: menu, toggle: toggle, home: menu.parentNode, next: menu.nextSibling };

            /*
                القائمة تُنقل إلى `<body>`.

                النقل يضمن أن ترتسم فوق كل ما في الصفحة مهما تعمّق موضعها
                في جداولَ وبطاقاتٍ متداخلة، ولا تقصّها بطاقةٌ ذات
                `overflow: hidden` ولا يعلوها عنصرٌ في سياق تراصٍّ أعلى.

                وله ثمنٌ يجب أن يُدفع في مكانٍ آخر: كل معالِج نقرٍ
                **مفوَّض** إلى حاويةٍ داخل الصفحة — مثل
                `$(document).on('click', '.approve-btn', …)` —
                يتوقّف عن المطابقة، لأن الزرّ لم يعد من نسل تلك الحاوية.
                فتُقرأ النقرة كأنها `href="#"` وحدها: لا فعل ولا خطأ ولا
                شيء يشير إلى السبب.

                ولهذا صارت تلك المعالِجات مربوطةً بـ`document` لا بحاوية:
                `document` أصلٌ لكل شيء، فتعمل أينما حلّت القائمة.
            */
            document.body.appendChild(menu);
            place();
        });

        // القياس بعد الرسم: العرض والارتفاع لا يُعرفان قبل ظهورها
        document.addEventListener('shown.bs.dropdown', place);

        document.addEventListener('hidden.bs.dropdown', function () {
            if (! open) {
                return;
            }

            var menu = open.menu;

            menu.style.position = '';
            menu.style.left = '';
            menu.style.top = '';
            menu.style.right = '';
            menu.style.bottom = '';
            menu.style.margin = '';
            menu.style.transform = '';

            // العودة إلى موضعها الأصلي من الشجرة
            open.home.insertBefore(menu, open.next);
            open = null;
        });

        // `fixed` منسوبةٌ إلى النافذة: التمرير يُزيح الزرّ لا القائمة
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
    })();
</script>
