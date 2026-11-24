{{--
    ══════════════════════════════════════════════════════════════════════
    نظام تصميم شاشات إعدادات العملاء
    ══════════════════════════════════════════════════════════════════════

    نقلٌ للغة التصميم المستقرّة في وحدة المبيعات
    (`sales::partials.settings-theme` — البادئة `sl-`) إلى وحدة العملاء
    بالبادئة `cl-`، على نفس ما فعلته المبيعات حين نقلتها عن «الأذون
    المخزنية» (`stk-`)، والمشتريات (`po-`). نسخةٌ مستقلّة لكل وحدة لا نسخة
    واحدة مشتركة — وحدة معطَّلة في `modules_statuses.json` تُسقط قوالبها،
    فقالبٌ في العملاء يشير إلى وحدة أخرى ينكسر بمجرّد إطفائها.

    كل لون وخطّ وزاوية وظلّ هنا يشير إلى متغيّرات `--app-*` التي تحقنها
    `App\Support\ThemeSettings` في ترويسة الصفحة. فما يُضبط في «مظهر
    النظام» — اللون الأساسي، والخطّ، واستدارة الحواف، والوضع الليلي —
    يسري على هذه الشاشات بلا سطر واحد يُعدَّل هنا. ولا لون مثبَّت في
    هذا الملف إلّا الأسود الشفّاف في الظلال.

    الاتجاه: لا `left`/`right` في القواعد، بل `inline-start`/`inline-end`،
    فالشاشة تنقلب مع `dir` الذي تضبطه `App\Support\LanguageSettings` بلا
    ورقة أنماط ثانية للإنجليزية.

    الاستعمال: `@include('client::partials.settings-theme')` في أول
    `@section('content')`. الاستدعاء المكرّر لا يضرّ — الحارس أدناه يمنع
    تكرار الوسم إن ضمّت الشاشة قالبين كلاهما يستدعيه.

    مقصور على شاشات الإعدادات عمداً: لا يُستدعى من `client::master`، فبقيّة
    شاشات العملاء تبقى على مظهرها حتى تُنقل هي الأخرى.
--}}

@once
    <style>
        /* ═══════════════════════════════════════════════════════════
           الرموز — مسافات وزوايا وظلال وخطوط فاصلة
           ═══════════════════════════════════════════════════════════ */

        :root {
            --cl-1: .25rem;  --cl-2: .5rem;   --cl-3: .75rem;
            --cl-4: 1rem;    --cl-5: 1.5rem;  --cl-6: 2rem;

            --cl-r: calc(var(--app-radius, 6px) + 2px);
            --cl-r-lg: calc(var(--app-radius, 6px) + 6px);

            /* ظلال ناعمة متدرّجة بدل ظلّ القالب الواحد الثقيل */
            --cl-e1: 0 1px 2px rgba(16, 24, 40, .04);
            --cl-e2: 0 1px 3px rgba(16, 24, 40, .06), 0 1px 2px rgba(16, 24, 40, .04);
            --cl-e3: 0 4px 12px rgba(16, 24, 40, .07), 0 2px 4px rgba(16, 24, 40, .04);
            --cl-e4: 0 12px 32px rgba(16, 24, 40, .10), 0 4px 8px rgba(16, 24, 40, .05);

            --cl-line: var(--app-border, #e4e7ec);
            --cl-line-soft: color-mix(in srgb, var(--app-border, #e4e7ec) 55%, transparent);
            --cl-tint: color-mix(in srgb, var(--app-primary, #7367f0) 4%, var(--app-surface, #fff));
        }

        /* ═══════════════ الأرضية ═══════════════ */

        .cl-scope { color: var(--app-text, #4b4b4b); }

        /* ═══════════════ ترويسة الصفحة ═══════════════ */

        .cl-page-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-4); flex-wrap: wrap;
            margin-bottom: var(--cl-5);
        }

        .cl-page-head__titles { min-width: 0; display: flex; align-items: center; gap: var(--cl-3); }

        .cl-page-head__icon {
            flex: none; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--cl-r);
            background: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            font-size: 1.15rem;
            box-shadow: 0 4px 12px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .cl-page-head__title {
            font-size: 1.35rem; font-weight: var(--app-heading-weight, 700); line-height: 1.25;
            color: var(--app-text, #4b4b4b); margin: 0 0 .15rem; letter-spacing: -.01em;
        }

        .cl-page-head__sub { color: var(--app-muted, #6e6b7b); font-size: .82rem; margin: 0; }

        .cl-page-head .breadcrumb {
            margin: .15rem 0 0; padding: 0; background: none; font-size: .78rem;
        }
        .cl-page-head .breadcrumb-item + .breadcrumb-item::before {
            color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        .cl-page-head__actions { display: flex; gap: var(--cl-2); flex-wrap: wrap; align-items: center; }

        /* ═══════════════ الأزرار ═══════════════
           حجم واحد، ووزن واحد، ومسافة أيقونة واحدة. مقصورة على `.cl-scope`
           كي لا تُعاد صياغة أزرار الترويسة العامّة والشريط الجانبي. */

        .cl-scope .btn {
            --btn-bg: transparent;
            --btn-fg: var(--app-text, #4b4b4b);
            --btn-bd: var(--cl-line);
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            height: 38px; padding: 0 var(--cl-4);
            font-size: .86rem; font-weight: 600; line-height: 1;
            border: 1px solid var(--btn-bd);
            border-radius: var(--cl-r);
            background: var(--btn-bg); color: var(--btn-fg);
            box-shadow: var(--cl-e1);
            transition: background .16s ease, border-color .16s ease, color .16s ease,
                        box-shadow .16s ease, transform .16s ease;
            white-space: nowrap;
        }

        .cl-scope .btn > i, .cl-scope .btn > svg { font-size: .95em; line-height: 1; }
        .cl-scope .btn i.mr-1, .cl-scope .btn i.mr-50,
        .cl-scope .btn i.me-1, .cl-scope .btn i.me-2 { margin: 0 !important; }

        .cl-scope .btn:hover { transform: translateY(-1px); box-shadow: var(--cl-e3); }
        .cl-scope .btn:active { transform: translateY(0); box-shadow: var(--cl-e1); }
        .cl-scope .btn:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .cl-scope .btn-sm { height: 32px; padding: 0 var(--cl-3); font-size: .8rem; }
        .cl-scope .btn-lg { height: 44px; padding: 0 var(--cl-5); font-size: .92rem; }

        .cl-scope .btn-primary,
        .cl-scope .btn-primary:not(:disabled):not(.disabled) {
            --btn-bg: var(--app-primary, #7367f0);
            --btn-fg: var(--app-on-primary, #fff);
            --btn-bd: var(--app-primary, #7367f0);
        }
        .cl-scope .btn-primary:hover, .cl-scope .btn-primary:focus,
        .cl-scope .btn-primary:not(:disabled):not(.disabled):active {
            background: var(--app-primary-hover, #5e50ee) !important;
            border-color: var(--app-primary-hover, #5e50ee) !important;
            color: var(--app-on-primary, #fff) !important;
            box-shadow: 0 6px 16px var(--app-primary-glow, rgba(115, 103, 240, .3)) !important;
        }

        .cl-scope .btn-success { --btn-bg: var(--app-success, #28c76f); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-success, #28c76f); }
        .cl-scope .btn-danger  { --btn-bg: var(--app-danger, #ea5455);  --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-danger, #ea5455); }
        .cl-scope .btn-info    { --btn-bg: var(--app-info, #00cfe8);    --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-info, #00cfe8); }
        .cl-scope .btn-warning { --btn-bg: var(--app-warning, #ff9f43); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-warning, #ff9f43); }
        .cl-scope .btn-secondary, .cl-scope .btn-light { --btn-bg: var(--app-surface, #fff); --btn-fg: var(--app-text, #4b4b4b); }

        .cl-scope .btn-outline-primary   { --btn-fg: var(--app-primary, #7367f0); --btn-bd: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent); }
        .cl-scope .btn-outline-secondary { --btn-fg: var(--app-muted, #6e6b7b);   --btn-bd: var(--cl-line); }
        .cl-scope .btn-outline-danger    { --btn-fg: var(--app-danger, #ea5455);  --btn-bd: color-mix(in srgb, var(--app-danger, #ea5455) 40%, transparent); }
        .cl-scope .btn-outline-success   { --btn-fg: var(--app-success, #28c76f); --btn-bd: color-mix(in srgb, var(--app-success, #28c76f) 40%, transparent); }
        .cl-scope .btn-outline-info      { --btn-fg: var(--app-info, #00cfe8);    --btn-bd: color-mix(in srgb, var(--app-info, #00cfe8) 40%, transparent); }
        .cl-scope .btn-outline-warning   { --btn-fg: var(--app-warning, #ff9f43); --btn-bd: color-mix(in srgb, var(--app-warning, #ff9f43) 40%, transparent); }

        .cl-scope .btn-outline-primary:hover   { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); border-color: var(--app-primary, #7367f0); }
        .cl-scope .btn-outline-secondary:hover { background: var(--app-body-bg, #f8f8f8); color: var(--app-text, #4b4b4b); }
        .cl-scope .btn-outline-danger:hover    { background: var(--app-danger, #ea5455);  color: var(--app-on-primary, #fff); border-color: var(--app-danger, #ea5455); }
        .cl-scope .btn-outline-success:hover   { background: var(--app-success, #28c76f); color: var(--app-on-primary, #fff); border-color: var(--app-success, #28c76f); }
        .cl-scope .btn-outline-info:hover      { background: var(--app-info, #00cfe8);    color: var(--app-on-primary, #fff); border-color: var(--app-info, #00cfe8); }
        .cl-scope .btn-outline-warning:hover   { background: var(--app-warning, #ff9f43); color: var(--app-on-primary, #fff); border-color: var(--app-warning, #ff9f43); }

        /* زرّ أيقونة مربّع — لأعمدة الإجراءات */
        .cl-actions { display: inline-flex; gap: .3rem; }
        .cl-scope .cl-actions .btn { width: 34px; height: 34px; padding: 0; box-shadow: none; }
        .cl-scope .cl-actions .btn:hover { box-shadow: var(--cl-e2); }

        .cl-scope .btn-group > .btn { box-shadow: none; }
        .cl-scope .btn-group > .btn:hover { transform: none; }

        .cl-scope .dropdown-menu {
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r);
            box-shadow: var(--cl-e4);
            padding: var(--cl-1) 0;
            font-size: .86rem;
            background: var(--app-surface, #fff);
        }
        .cl-scope .dropdown-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .5rem var(--cl-4);
            color: var(--app-text, #4b4b4b);
        }
        .cl-scope .dropdown-item:hover,
        .cl-scope .dropdown-item:focus {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        /* ═══════════════ البطاقات ═══════════════ */

        .cl-scope .card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r-lg);
            box-shadow: var(--cl-e2);
            margin-bottom: var(--cl-4);
            overflow: hidden;
            transition: box-shadow .2s ease;
        }

        .cl-scope .card:hover { box-shadow: var(--cl-e3); }
        .cl-scope .card .card-content { background: transparent; }

        .cl-scope .card > .card-header,
        .cl-scope .card .card-content > .card-header {
            background: transparent;
            border-bottom: 1px solid var(--cl-line-soft);
            padding: var(--cl-4) var(--cl-5);
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-3); flex-wrap: wrap;
        }

        .cl-scope .card .card-title {
            font-size: .98rem; font-weight: 700; color: var(--app-text, #4b4b4b);
            margin: 0; display: flex; align-items: center; gap: var(--cl-2);
        }
        .cl-scope .card .card-title i { color: var(--app-primary, #7367f0); font-size: .95em; }

        .cl-scope .card .card-body { padding: var(--cl-5); }

        /* ═══════════════ البطاقات الإحصائية ═══════════════ */

        .cl-stats {
            display: grid; gap: var(--cl-4);
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-bottom: var(--cl-5);
        }

        .cl-stat {
            display: flex; align-items: center; gap: var(--cl-4);
            padding: var(--cl-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r-lg);
            box-shadow: var(--cl-e2);
        }
        .cl-stat__icon {
            flex: none; width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--cl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.25rem;
        }
        .cl-stat__icon--success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .cl-stat__icon--danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   color: var(--app-danger, #ea5455); }
        .cl-stat__icon--warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .cl-stat__icon--info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    color: var(--app-info, #00cfe8); }

        .cl-stat__body { min-width: 0; }
        .cl-stat__value {
            font-size: 1.55rem; font-weight: 700; line-height: 1.1;
            color: var(--app-text, #4b4b4b); font-variant-numeric: tabular-nums; margin: 0;
        }
        .cl-stat__label { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: .2rem 0 0; line-height: 1.5; }

        /* ═══════════════ الجداول ═══════════════ */

        .cl-table-card > .card-body,
        .cl-table-card .card-content > .card-body { padding: 0; }

        .cl-scope .table {
            color: var(--app-text, #4b4b4b);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .cl-scope .table thead th {
            background: var(--cl-tint);
            color: var(--app-muted, #6e6b7b);
            font-size: .74rem; font-weight: 700;
            letter-spacing: .04em; text-transform: uppercase;
            padding: var(--cl-3) var(--cl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--cl-line) !important;
            white-space: nowrap;
            vertical-align: middle;
        }

        .cl-scope .table tbody td {
            padding: var(--cl-4) var(--cl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--cl-line-soft) !important;
            vertical-align: middle;
            font-size: .875rem;
        }

        .cl-scope .table tbody tr:last-child td { border-bottom: 0 !important; }

        /* ═══════════ كثافة الجداول العملياتية ═══════════

           `--cl-4 --cl-5` (١rem × ١٫٥rem) مقاسٌ مُعايَرٌ لجداول الإعدادات:
           ثمانيةُ أعمدةٍ وصفوفٌ قليلة. وجداولُ شاشات العملاء أكثفُ من ذلك —
           كشفُ الحساب وتبويباتُ ملفّ العميل تتجاوز اثني عشر عموداً — ومع
           هذه الحشوة يخرج الصفُّ ضِعفَ ارتفاعه فتُقرأ الشاشة أثقلَ ممّا
           كانت قبل التوحيد.

           فالحشوةُ تنقص مع كثافة الجدول، لا مع نوع الشاشة: `table-sm`
           تكفي وسماً للجداول الضيّقة، و`.cl-dense` لمن أراد التصريح. */

        .cl-scope .table-sm thead th,
        .cl-scope .cl-dense thead th { padding: var(--cl-2) var(--cl-3); }

        .cl-scope .table-sm tbody td,
        .cl-scope .table-sm tfoot td,
        .cl-scope .table-sm tfoot th,
        .cl-scope .cl-dense tbody td,
        .cl-scope .cl-dense tfoot td,
        .cl-scope .cl-dense tfoot th {
            padding: var(--cl-2) var(--cl-3);
            font-size: .82rem;
        }

        /* الجداول ذات الحواف التي يرسمها القالبُ الأصلي تبقى على حوافّها:
           `table-bordered` وسمٌ صريح من كاتب الشاشة لا افتراضٌ نغلبه */
        .cl-scope .table-bordered td,
        .cl-scope .table-bordered th {
            border: 1px solid var(--cl-line) !important;
        }

        /* صفّ الإجماليات — يُقرأ إجمالاً لا صفّاً كبقيّة الصفوف: أرضيةٌ
           خفيفة وخطٌّ أعلى أثخن يفصله عن التفاصيل فوقه */
        .cl-scope .table tfoot td,
        .cl-scope .table tfoot th {
            padding: var(--cl-4) var(--cl-5);
            background: var(--cl-tint);
            border: 0 !important;
            border-top: 2px solid var(--cl-line) !important;
            font-weight: 700;
            font-size: .875rem;
            vertical-align: middle;
            font-variant-numeric: tabular-nums;
        }
        .cl-scope .table-hover tbody tr { transition: background .14s ease; }
        .cl-scope .table-hover tbody tr:hover { background: var(--app-primary-soft, rgba(115, 103, 240, .12)) !important; }
        .cl-scope .table-responsive { border: 0; }
        .cl-num, .cl-scope .table td[data-num] { font-variant-numeric: tabular-nums; }

        /* رأس بطاقة الجدول */
        .cl-table-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-3); flex-wrap: wrap;
            padding: var(--cl-4) var(--cl-5);
            border-bottom: 1px solid var(--cl-line-soft);
        }
        .cl-table-head__title {
            display: flex; align-items: center; gap: var(--cl-2);
            font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .cl-table-head__title i { color: var(--app-primary, #7367f0); }

        .cl-count {
            display: inline-flex; align-items: center;
            padding: .1rem .5rem; border-radius: 999px;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
        }

        /* خلية اسم + أيقونة */
        .cl-media { display: flex; align-items: center; gap: var(--cl-3); min-width: 0; }
        .cl-media__thumb {
            flex: none; width: 40px; height: 40px;
            border-radius: var(--cl-r); object-fit: cover;
            border: 1px solid var(--cl-line); background: var(--app-body-bg, #f8f8f8);
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--app-muted, #6e6b7b); font-size: .95rem;
        }
        .cl-media__body { min-width: 0; }
        .cl-media__title { font-weight: 600; color: var(--app-text, #4b4b4b); display: block; line-height: 1.35; }
        .cl-media__sub {
            font-size: .75rem; color: var(--app-muted, #6e6b7b); display: block;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شارة الحالة ═══════════════
           النقطة إلى جانب اللون: اللون وحده لا يكفي مَن لا يميّزه. */

        .cl-state {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .22rem .62rem; border-radius: 999px;
            font-size: .74rem; font-weight: 600; line-height: 1.4;
        }
        .cl-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex: none; }
        .cl-state--on  { color: var(--app-success, #28c76f); background: var(--app-success-soft, rgba(40, 199, 111, .12)); }
        .cl-state--off { color: var(--app-muted, #6e6b7b);   background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); }
        .cl-state--neutral { color: var(--app-info, #00cfe8); background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); }
        .cl-state--neutral::before { display: none; }
        .cl-state--warning { color: var(--app-warning, #ff9f43); background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); }
        .cl-state--danger  { color: var(--app-danger, #ea5455);  background: var(--app-danger-soft, rgba(234, 84, 85, .12)); }

        /* زرُّ تصفيةٍ مختار داخل مجموعة أزرار (تصفية مواعيد ملف العميل) */
        .cl-scope .btn-outline-primary.active { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); border-color: var(--app-primary, #7367f0); }

        .cl-tags { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }

        .cl-scope .badge {
            display: inline-flex; align-items: center;
            padding: .28em .65em; border-radius: 999px;
            font-size: .73rem; font-weight: 600; letter-spacing: 0;
        }
        .cl-scope .badge-primary, .cl-scope .badge-light-primary { background: var(--app-primary-soft, rgba(115, 103, 240, .12)); color: var(--app-primary, #7367f0); }
        .cl-scope .badge-success, .cl-scope .badge-light-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .cl-scope .badge-danger,  .cl-scope .badge-light-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));  color: var(--app-danger, #ea5455); }
        .cl-scope .badge-info,    .cl-scope .badge-light-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); color: var(--app-info, #00cfe8); }
        .cl-scope .badge-warning, .cl-scope .badge-light-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .cl-scope .badge-secondary, .cl-scope .badge-light {
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); color: var(--app-muted, #6e6b7b);
        }

        /* ═══════════════ ذيل الجدول والترقيم ═══════════════ */

        .cl-table-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-4); flex-wrap: wrap;
            padding: var(--cl-3) var(--cl-5);
            border-top: 1px solid var(--cl-line-soft);
            background: var(--cl-tint);
        }
        .cl-table-foot__count {
            font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: 0;
            font-variant-numeric: tabular-nums;
        }

        .cl-scope .pagination { gap: .3rem; margin: 0; flex-wrap: wrap; }

        .cl-scope .pagination .page-item .page-link,
        .cl-scope .pagination .page-link {
            min-width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            margin: 0; padding: 0 .55rem;
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r) !important;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .82rem; font-weight: 600; font-variant-numeric: tabular-nums;
            box-shadow: none;
            transition: background .14s ease, border-color .14s ease, color .14s ease;
        }

        .cl-scope .pagination .page-item .page-link:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 35%, transparent);
            color: var(--app-primary, #7367f0);
        }

        .cl-scope .pagination .page-item.active .page-link {
            background: var(--app-primary, #7367f0); border-color: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            box-shadow: 0 3px 10px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .cl-scope .pagination .page-item.disabled .page-link {
            background: var(--app-surface, #fff); color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        /* مُنتقي عدد السجلّات: عنصر واحد مضموم، لا ثلاثة عناصر متجاورة */
        .cl-perpage {
            display: inline-flex; align-items: stretch;
            border: 1px solid var(--cl-line);
            border-radius: 999px;
            background: var(--app-surface, #fff);
            overflow: hidden;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .cl-perpage:focus-within {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .cl-perpage label,
        .cl-perpage__unit {
            display: inline-flex; align-items: center;
            margin: 0; padding: .32rem .8rem;
            font-size: .78rem; font-weight: 600; color: var(--app-muted, #6e6b7b);
            background: var(--cl-tint);
            white-space: nowrap;
        }
        .cl-perpage__select {
            appearance: none; -webkit-appearance: none;
            border: 0; outline: 0; box-shadow: none;
            padding: .32rem 1.5rem .32rem .7rem;
            font-size: .84rem; font-weight: 700; color: var(--app-text, #4b4b4b);
            background: var(--app-surface, #fff);
            font-variant-numeric: tabular-nums;
            cursor: pointer;
            /* السهم مرسومٌ في الخلفية: `appearance:none` يُزيل سهم المتصفّح */
            background-image: linear-gradient(45deg, transparent 50%, var(--app-muted, #6e6b7b) 50%),
                              linear-gradient(135deg, var(--app-muted, #6e6b7b) 50%, transparent 50%);
            background-position: .62rem calc(50% + 1px), .9rem calc(50% + 1px);
            background-size: 5px 5px, 5px 5px;
            background-repeat: no-repeat;
        }
        .cl-perpage__select:focus { background-color: var(--cl-tint); }

        /* ═══════════════ النماذج ═══════════════ */

        .cl-scope .form-group { margin-bottom: var(--cl-4); }

        /* `:not(.cl-switch)` مقصود: مفتاح التبديل وسمُه `<label>` أيضاً،
           وهذه القاعدة أعلى تخصيصاً (0,1,1) من `.cl-switch` (0,1,0) فكانت
           تفرض عليه `display:block` — فيسقط النصّ تحت المسار بدل أن يقف
           بجانبه، وتنفصل الأسطر بـ`margin-bottom`. */
        .cl-scope label:not(.cl-switch),
        .cl-scope .form-group > label:not(.cl-switch) {
            display: block;
            font-size: .82rem; font-weight: 600; color: var(--app-text, #4b4b4b);
            margin-bottom: .4rem;
        }

        .cl-scope .form-control, .cl-scope .custom-select, .cl-scope .form-select,
        .cl-scope .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 0 var(--cl-3);
            background: var(--app-surface, #fff);
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r);
            color: var(--app-text, #4b4b4b);
            font-size: .875rem;
            box-shadow: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .cl-scope textarea.form-control { height: auto; padding: var(--cl-3); min-height: 90px; }

        .cl-scope .form-control:focus, .cl-scope .custom-select:focus, .cl-scope .form-select:focus,
        .cl-scope .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
            outline: 0;
        }

        .cl-scope .form-control::placeholder { color: var(--app-muted, #6e6b7b); opacity: .7; }
        .cl-scope .form-control.is-invalid { border-color: var(--app-danger, #ea5455); }
        .cl-scope .invalid-feedback { font-size: .76rem; color: var(--app-danger, #ea5455); margin-top: .3rem; }

        .cl-scope .input-group-text {
            background: var(--cl-tint); border: 1px solid var(--cl-line);
            color: var(--app-muted, #6e6b7b); border-radius: var(--cl-r);
        }

        .cl-scope .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px; color: var(--app-text, #4b4b4b); padding: 0;
        }
        .cl-scope .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }

        /* أقسام النموذج */
        .cl-section { margin-bottom: var(--cl-5); }
        .cl-section:last-child { margin-bottom: 0; }
        .cl-section__head {
            display: flex; align-items: center; gap: var(--cl-2);
            padding-bottom: var(--cl-2); margin-bottom: var(--cl-4);
            border-bottom: 1px solid var(--cl-line-soft);
        }
        .cl-section__head i { color: var(--app-primary, #7367f0); font-size: .9rem; }
        .cl-section__title { font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0; }

        .cl-req::after { content: ' *'; color: var(--app-danger, #ea5455); font-weight: 700; }
        /* ═══════ قائمةٌ بجانبها زرُّ إضافة ═══════

           العميل غير مسجَّل؟ زرٌّ يفتح شاشة إضافته في تبويبٍ جديد بدل إجبار
           المستخدم على مغادرة النموذج الذي يملؤه وضياع ما أدخله. منقولةٌ عن
           `po-select-add` في وحدة المشتريات، بالبادئة `cl-`. */
        .cl-select-add { display: flex; align-items: stretch; gap: .4rem; }
        .cl-select-add > select,
        .cl-select-add > .select2 { flex: 1 1 auto; min-width: 0; }
        .cl-select-add__btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.35rem; padding: 0;
        }

        .cl-help { display: block; font-size: .75rem; color: var(--app-muted, #6e6b7b); margin-top: .3rem; }

        /* ذيل النموذج: الشرح في جهة والأزرار في الأخرى. حين يخلو من الشرح
           تنزلق الأزرار إلى النهاية وحدها بفضل `space-between`، فلا حاجة
           إلى صنف ثانٍ لحالتين من الشيء الواحد. */
        .cl-form-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-3); flex-wrap: wrap;
            padding: var(--cl-4) var(--cl-5);
            border-top: 1px solid var(--cl-line-soft);
            background: var(--cl-tint);
        }
        .cl-form-foot > :only-child { margin-inline-start: auto; }

        /* مستقلاًّ عن بطاقة — يحتاج حافّته واستدارته وظلّه بنفسه */
        .cl-scope > .cl-form-foot,
        .cl-scope form > .cl-form-foot {
            border: 1px solid var(--cl-line); border-radius: var(--cl-r-lg);
            box-shadow: var(--cl-e2); margin-bottom: var(--cl-5);
        }

        /* ═══════════════ الفلاتر ═══════════════ */

        .cl-filters .card-body { padding: var(--cl-5); }
        .cl-filters__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--cl-3); flex-wrap: wrap; margin-bottom: var(--cl-4);
        }
        .cl-filters__title {
            display: flex; align-items: center; gap: var(--cl-2);
            font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .cl-filters__title i { color: var(--app-primary, #7367f0); }
        .cl-filters__foot {
            display: flex; gap: var(--cl-2); justify-content: flex-end;
            padding-top: var(--cl-4); margin-top: var(--cl-1);
            border-top: 1px dashed var(--cl-line);
        }

        /* ═══════════════ بلاطات الإعدادات ═══════════════ */

        .cl-tiles { display: grid; gap: var(--cl-4); grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }

        .cl-tile {
            display: flex; align-items: flex-start; gap: var(--cl-3);
            padding: var(--cl-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r-lg);
            box-shadow: var(--cl-e2);
            color: inherit; text-decoration: none;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            position: relative; overflow: hidden;
        }
        .cl-tile::before {
            content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
            width: 3px; background: var(--app-primary, #7367f0);
            transform: scaleY(0); transform-origin: top; transition: transform .2s ease;
        }
        .cl-tile:hover {
            transform: translateY(-2px); box-shadow: var(--cl-e4);
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 28%, var(--cl-line));
            color: inherit; text-decoration: none;
        }
        .cl-tile:hover::before { transform: scaleY(1); }
        .cl-tile:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .cl-tile__icon {
            flex: none; width: 46px; height: 46px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--cl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.2rem;
        }
        .cl-tile__body { min-width: 0; }
        .cl-tile__title { font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0 0 .2rem; }
        .cl-tile__hint  { font-size: .78rem; color: var(--app-muted, #6e6b7b); margin: 0; line-height: 1.6; }

        .cl-tile__kids { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .55rem; }
        .cl-tile__kid {
            font-size: .72rem; font-weight: 600;
            padding: .12rem .5rem; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 10%, transparent);
            color: var(--app-muted, #6e6b7b); text-decoration: none;
        }
        .cl-tile__kid:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        .cl-group { margin-bottom: var(--cl-5); }
        .cl-group__head {
            display: flex; align-items: center; gap: var(--cl-2);
            margin-bottom: var(--cl-3);
        }
        .cl-group__head i { color: var(--app-primary, #7367f0); }
        .cl-group__title { font-size: .95rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0; }

        /* ═══════════════ مفتاح التبديل ═══════════════ */

        /* الوسم `label` في المُنتقي مقصود لا زائد: يرفع التخصيص إلى (0,1,1)
           فيغلب أيَّ قاعدةٍ عامّة على `label` داخل `.cl-scope`، ويبقى
           المفتاح صفّاً واحداً: مسارٌ ثم نصّه بجانبه. */
        .cl-scope label.cl-switch,
        .cl-switch {
            display: flex; align-items: flex-start; gap: var(--cl-3);
            margin: 0; cursor: pointer; user-select: none;
            font-size: .87rem; font-weight: 600;
        }
        .cl-switch > input { position: absolute; opacity: 0; width: 0; height: 0; }

        .cl-switch__track {
            flex: none; position: relative;
            width: 42px; height: 24px; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 28%, transparent);
            transition: background .18s ease;
            margin-top: 2px;
        }
        .cl-switch__thumb {
            position: absolute; top: 3px; inset-inline-start: 3px;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--app-surface, #fff);
            box-shadow: var(--cl-e2);
            transition: transform .18s ease;
        }
        .cl-switch > input:checked + .cl-switch__track { background: var(--app-primary, #7367f0); }
        .cl-switch > input:checked + .cl-switch__track .cl-switch__thumb { transform: translateX(-18px); }
        html[dir="ltr"] .cl-switch > input:checked + .cl-switch__track .cl-switch__thumb { transform: translateX(18px); }
        .cl-switch > input:focus-visible + .cl-switch__track { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }
        .cl-switch > input:disabled + .cl-switch__track { opacity: .5; cursor: not-allowed; }

        /* مفتاحٌ بلا نصّ — في خليّة جدول لا في صفّ إعداد. العنوان يحمله
           رأسُ العمود، فتكراره في كل صفّ حشو. `aria-label` على المدخل
           يبقى واجباً: قارئ الشاشة لا يقرأ رأس العمود مع كل خليّة. */
        .cl-scope label.cl-switch--bare,
        .cl-switch--bare { display: inline-flex; align-items: center; justify-content: center; }
        .cl-switch--bare .cl-switch__track { margin-top: 0; }

        .cl-switch__label { display: flex; flex-direction: column; gap: .1rem; }
        .cl-switch__title { font-size: .87rem; font-weight: 600; color: var(--app-text, #4b4b4b); }
        .cl-switch__hint  { font-size: .75rem; color: var(--app-muted, #6e6b7b); line-height: 1.5; }

        .cl-switch-row {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: var(--cl-4);
            padding: var(--cl-3) 0;
            border-bottom: 1px solid var(--cl-line-soft);
        }
        .cl-switch-row:last-child { border-bottom: 0; }

        /* ═══════════════ الحالة الفارغة ═══════════════ */

        .cl-empty { text-align: center; padding: var(--cl-6) var(--cl-5); color: var(--app-muted, #6e6b7b); }
        .cl-empty__icon {
            width: 60px; height: 60px; margin: 0 auto var(--cl-4);
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.6rem;
        }
        .cl-empty__title { color: var(--app-text, #4b4b4b); font-weight: 700; font-size: 1rem; margin-bottom: .35rem; }
        .cl-empty__hint  { font-size: .85rem; margin: 0 0 var(--cl-4); }

        /* ═══════════════ التنبيهات ═══════════════ */

        .cl-scope .alert {
            border: 1px solid transparent; border-radius: var(--cl-r);
            padding: var(--cl-3) var(--cl-4); font-size: .85rem;
        }
        .cl-scope .alert-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); border-color: color-mix(in srgb, var(--app-success, #28c76f) 30%, transparent); color: var(--app-success, #28c76f); }
        .cl-scope .alert-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   border-color: color-mix(in srgb, var(--app-danger, #ea5455) 30%, transparent);  color: var(--app-danger, #ea5455); }
        .cl-scope .alert-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 12%, transparent); border-color: color-mix(in srgb, var(--app-warning, #ff9f43) 30%, transparent); color: var(--app-warning, #ff9f43); }
        .cl-scope .alert-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    border-color: color-mix(in srgb, var(--app-info, #00cfe8) 30%, transparent);    color: var(--app-info, #00cfe8); }

        /* ═══════════════ تبويبات ═══════════════

           رابطٌ لكل تبويب لا زرّ: التبويب هنا صفحةٌ بعنوانها الخاصّ
           (`?type=collections`)، فيُفتح في تبويب جديد ويُحفظ في المفضّلة.
           الخطّ السفلي يتبع اللون الأساسي للمظهر. */

        .cl-tabs {
            display: flex; gap: var(--cl-1); flex-wrap: wrap;
            border-bottom: 2px solid var(--cl-line);
            margin-bottom: var(--cl-5);
        }
        .cl-tab {
            display: inline-flex; align-items: center; gap: var(--cl-2);
            padding: var(--cl-3) var(--cl-4);
            margin-bottom: -2px;
            border-bottom: 3px solid transparent;
            color: var(--app-muted, #6e6b7b);
            font-size: .9rem; font-weight: 600; text-decoration: none;
            transition: color .14s ease, border-color .14s ease;
        }
        .cl-tab:hover { color: var(--app-primary, #7367f0); text-decoration: none; }
        .cl-tab.is-active { color: var(--app-primary, #7367f0); border-bottom-color: var(--app-primary, #7367f0); }
        .cl-tab:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: -2px; }

        /* ═══════════════ شريط سياق إعدادات النظام ═══════════════

           بندٌ واحد لكل إعداد عامّ تعمل به الشاشة: اسمه، وقيمته الحالية،
           ورابطٌ إلى الشاشة التي تضبطه. الشبكة تنساب بـ`auto-fill` فلا
           يبقى بندٌ وحيدٌ في سطر على الشاشات المتوسّطة.

           البند كلّه رابط، والقيمة `<span>` داخله: لا رابط داخل رابط. */

        .cl-ctx { margin-bottom: var(--cl-5); }

        .cl-ctx__head {
            display: flex; align-items: baseline; gap: var(--cl-2);
            flex-wrap: wrap; margin-bottom: var(--cl-3);
        }
        .cl-ctx__title {
            display: flex; align-items: center; gap: var(--cl-2);
            font-size: .88rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .cl-ctx__title i { color: var(--app-primary, #7367f0); }
        .cl-ctx__hint { font-size: .76rem; color: var(--app-muted, #6e6b7b); margin: 0; }

        .cl-ctx__grid {
            display: grid; gap: var(--cl-2);
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        }

        .cl-ctx__item {
            display: flex; align-items: center; gap: var(--cl-3);
            padding: var(--cl-2) var(--cl-3);
            border: 1px solid var(--cl-line-soft); border-radius: var(--cl-r);
            background: var(--app-surface, #fff);
            color: inherit; text-decoration: none;
            transition: border-color .14s ease, box-shadow .14s ease, transform .14s ease;
        }
        a.cl-ctx__item:hover {
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent);
            box-shadow: var(--cl-e2); transform: translateY(-1px);
            color: inherit; text-decoration: none;
        }
        a.cl-ctx__item:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .cl-ctx__icon {
            width: 32px; height: 32px; flex: none;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--cl-r); font-size: .9rem;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }
        .cl-ctx__item--on  .cl-ctx__icon { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .cl-ctx__item--off .cl-ctx__icon { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }

        .cl-ctx__body { min-width: 0; }
        .cl-ctx__label { display: block; font-size: .72rem; color: var(--app-muted, #6e6b7b); line-height: 1.4; }
        .cl-ctx__value {
            display: block; font-size: .82rem; font-weight: 600;
            color: var(--app-text, #4b4b4b); line-height: 1.4;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شريط البحث في اللوحة ═══════════════ */

        .cl-hubsearch { position: relative; max-width: 420px; margin-bottom: var(--cl-5); }
        .cl-hubsearch input {
            width: 100%; height: 42px;
            padding-inline-start: 2.4rem; padding-inline-end: var(--cl-3);
            border: 1px solid var(--cl-line); border-radius: 999px;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .88rem;
        }
        .cl-hubsearch input:focus {
            outline: 0; border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .cl-hubsearch i {
            position: absolute; inset-inline-start: .9rem; top: 50%; transform: translateY(-50%);
            color: var(--app-muted, #6e6b7b); font-size: .95rem; pointer-events: none;
        }

        /* ═══════════════ الطباعة ═══════════════ */

        @media print {
            .cl-page-head__actions, .cl-filters, .cl-table-foot, .cl-perpage, .cl-ctx { display: none !important; }
            .cl-scope .card { box-shadow: none; border-color: #ddd; }
        }

        /* ═══════════════ الشاشات الصغيرة ═══════════════ */

        @media (max-width: 575.98px) {
            .cl-page-head { align-items: flex-start; }
            .cl-page-head__actions { width: 100%; }
            .cl-page-head__actions .btn { flex: 1 1 auto; }
            .cl-scope .table thead th, .cl-scope .table tbody td { padding-inline: var(--cl-3); }
        }

        /* ═══════════════ لوحة الإرشادات ═══════════════

           شاشات الإعدادات تعرض مفاتيح تبديلٍ بعناوين مختصرة، ولا موضع
           فيها لشرح ما يفعله القسم كلُّه ولا لعلاقته بإعدادٍ آخر. هذه
           اللوحة موضعُ ذلك: `<details>` أصلاً — تُطوى بلا سطر سكربت،
           وتُفتح بالوسم `open` أوّل مرّة. */

        .cl-guide {
            background: var(--app-primary-soft, rgba(115, 103, 240, .08));
            border: 1px solid var(--cl-line);
            border-inline-start: 3px solid var(--app-primary, #7367f0);
            border-radius: var(--cl-r-lg);
            margin-bottom: var(--cl-4);
        }

        .cl-guide > summary {
            display: flex; align-items: center; gap: .5rem;
            padding: var(--cl-3) var(--cl-5);
            font-weight: 700; font-size: .9rem;
            color: var(--app-text, #4b4b4b);
            cursor: pointer;
            list-style: none;
        }

        /* المثلّث الافتراضي يختلف بين المتصفّحات ولا ينقلب مع `dir` */
        .cl-guide > summary::-webkit-details-marker { display: none; }
        .cl-guide > summary::marker { content: ''; }

        .cl-guide > summary::after {
            content: '';
            inline-size: .5rem; block-size: .5rem;
            margin-inline-start: auto;
            border-inline-end: 2px solid var(--app-muted, #6e6b7b);
            border-block-end: 2px solid var(--app-muted, #6e6b7b);
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .cl-guide[open] > summary::after { transform: rotate(-135deg); }

        .cl-guide__body {
            padding: 0 var(--cl-5) var(--cl-4);
            font-size: .85rem; line-height: 1.9;
            color: var(--app-muted, #6e6b7b);
        }

        .cl-guide__body h6 {
            font-size: .82rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
            margin: var(--cl-3) 0 .2rem;
        }

        .cl-guide__body p { margin: 0 0 .35rem; }

        /* سطر الحقيقة: ما هو مضبوطٌ الآن فعلاً، لا شرحاً نظرياً */
        .cl-guide__fact {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--cl-line);
            border-radius: var(--cl-r-sm, .35rem);
            padding: .3rem .6rem;
            font-weight: 600;
            color: var(--app-text, #4b4b4b);
        }

        /* شارة عدد المفاتيح في لسان التبويب */
        .cl-scope .cl-doc-tabs .badge { margin-inline-start: .35rem; }
    </style>
@endonce
