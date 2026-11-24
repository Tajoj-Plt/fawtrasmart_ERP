{{--
    مظهر القائمة الجانبية لوحدة نقاط البيع.

    **لماذا هذا الملف؟**

    بنية القائمة هنا صارت على قياس المخزون، لكن **شكلها** كان يبقى
    مختلفاً: مظهر شريط المخزون لا يأتي من قالبه بل من ورقة أنماط
    تُحمَّل في `stock::master` — `stock::layouts.partials.stock-theme`،
    وفيها ما يلوّن `.main-menu`. ووحدة نقاط البيع لا تُحمّل شيئاً
    يقابلها، فتبقى قائمتها على مظهر Vuexy الأصلي.

    وفي مظهر Vuexy عطلٌ ظاهر: «مظهر النظام» يجعل خلفية الشريط داكنة
    ويلوّن نصّ الروابط بـ`--app-sidebar-text` (لونٌ فاتح مقصودٌ لخلفية
    داكنة)، بينما القائمة الفرعية `ul.menu-content` تبقى **بيضاء** من
    القالب — فيصير النصّ الفاتح على أبيض، أي غير مقروء.

    هذه نسخة عن كتلة الشريط الجانبي في `stock-theme` بالبادئة
    `--pos-sidebar-*`. نسخة لا مشاركة: قالبٌ هنا يشير إلى `stock::` أو
    `sales::` ينكسر بمجرّد إطفاء تلك الوحدة في `modules_statuses.json`.

    كل لون هنا يشير إلى متغيّرات `--app-*` التي تحقنها
    `App\Support\ThemeSettings`، فتبديل المظهر في الإعدادات يسري على
    القائمة بلا سطر يُعدَّل. ولا لون مثبَّت إلّا الأسود الشفّاف في الظلال.

    يُحمَّل من `pos::master` وحده — القائمة تُعرَض في كل شاشة، فلا يصحّ
    أن يُترك لكل شاشة أن تستدعيه.

    @see \Modules\Stock\resources\views\layouts\partials\stock-theme.blade.php الأصل
--}}

@once
    <style id="pos-sidebar-theme">
        /* ═══════════════ ألوان مشتقّة ═══════════════
           يقبل الثيم لونين للشريط فقط، وهذا التصميم يحتاج تدرّجاً بينهما:
           تُشتقّ الدرجات بـ`color-mix` من نفس المتغيّرين فتتبع الإعداد. */
        :root {
            --pos-sidebar-sub-bg: color-mix(in srgb, var(--app-sidebar-bg) 88%, #000);
            --pos-sidebar-line: color-mix(in srgb, var(--app-sidebar-text) 16%, transparent);
            --pos-sidebar-dim: color-mix(in srgb, var(--app-sidebar-text) 72%, transparent);
        }

        .main-menu .navbar-header {
            border-bottom: 1px solid var(--pos-sidebar-line);
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
            background: var(--pos-sidebar-sub-bg) !important;
            padding: .25rem 0 .4rem;
            border-radius: var(--app-radius);
            margin: .15rem .55rem .35rem;
            box-shadow: inset 0 1px 0 var(--pos-sidebar-line);
        }

        .main-menu .navigation li > ul.menu-content li > a {
            color: var(--pos-sidebar-dim) !important;
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

        /* البند الجاري */
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

        /* الأب المفتوح: يبقى مميَّزاً ليُعرف موضع الصفحة في الشجرة */
        .main-menu .navigation > li.open > a,
        .main-menu .navigation > li.has-sub.open > a {
            background: var(--app-sidebar-hover) !important;
            color: var(--app-sidebar-text) !important;
            border-radius: var(--app-radius);
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
            color: var(--pos-sidebar-dim) !important;
        }

        .main-menu .navigation-header {
            color: var(--pos-sidebar-dim) !important;
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
            color: var(--pos-sidebar-dim) !important;
            background: transparent !important;
        }

        body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li.active > a,
        body.menu-collapsed .main-menu:not(.expanded) .navigation li > ul.menu-content li.active > a i {
            color: var(--app-on-primary) !important;
        }

        /* شريط تمرير رفيع يذوب في الخلفية بدل الشريط الافتراضي العريض */
        .main-menu-content::-webkit-scrollbar { width: 5px; }
        .main-menu-content::-webkit-scrollbar-thumb {
            background: var(--pos-sidebar-line);
            border-radius: 4px;
        }
        .main-menu-content::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--app-sidebar-text) 34%, transparent);
        }

        @media (prefers-reduced-motion: reduce) {
            .main-menu .navigation li > ul.menu-content li > a { transition: none !important; }
        }

        /*
            قفلُ بند القائمة — ميزةٌ خارج الباقة.

            البندُ يبقى ظاهراً ويُخفَّت وحسب: إخفاؤه يجعل المنشأة لا تعرف
            أن الميزة موجودة أصلاً، فلا تسأل عنها ولا تشتريها.

            والتخفيتُ يزول عند التمرير كي يبقى النصُّ مقروءاً لمن أراد أن
            يقرأ التلميح.
        */
        /*
            `.stk-plan-locked` و`.stk-plan-lock__icon` معرَّفتان في
            [stock-theme] الذي يستورده `master` — فلا تُكرَّران هنا. والمكرَّرُ
            في ملفّين يفترقان بعد أوّل تعديلٍ يُنسى أحدُهما.

            الباقي هنا ضبطُ الموضع في قائمةٍ جانبية بعينها: الأيقونةُ تطفو
            يساراً في بندٍ نصُّه يمينيّ، وهو ما لا يعنيه نظامُ التصميم.
        */
        .main-menu .stk-plan-lock__icon {
            float: left;
            margin-top: .18rem;
        }
    </style>
@endonce
