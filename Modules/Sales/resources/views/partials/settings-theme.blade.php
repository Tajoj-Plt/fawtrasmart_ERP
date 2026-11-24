{{--
    ══════════════════════════════════════════════════════════════════════
    نظام تصميم شاشات إعدادات المبيعات
    ══════════════════════════════════════════════════════════════════════

    نقلٌ للغة التصميم المستقرّة في «الأذون المخزنية»
    (`stock::layouts.partials.stock-theme` — البادئة `stk-`) إلى وحدة
    المبيعات بالبادئة `sl-`، على نفس ما فعلته وحدة المشتريات حين نقلتها
    بالبادئة `po-` في `purchases::partials.style`. ثلاث نسخ من اللغة
    الواحدة لا نسخة واحدة مشتركة — وهذا اختيار الوحدات المستقلّة: وحدة
    معطَّلة في `modules_statuses.json` تُسقط قوالبها، فقالبٌ في المبيعات
    يشير إلى `stock::` ينكسر بمجرّد إطفاء المخزون.

    كل لون وخطّ وزاوية وظلّ هنا يشير إلى متغيّرات `--app-*` التي تحقنها
    `App\Support\ThemeSettings` في ترويسة الصفحة. فما يُضبط في «مظهر
    النظام» — اللون الأساسي، والخطّ، واستدارة الحواف، والوضع الليلي —
    يسري على هذه الشاشات بلا سطر واحد يُعدَّل هنا. ولا لون مثبَّت في
    هذا الملف إلّا الأسود الشفّاف في الظلال.

    الاتجاه: لا `left`/`right` في القواعد، بل `inline-start`/`inline-end`،
    فالشاشة تنقلب مع `dir` الذي تضبطه `App\Support\LanguageSettings` بلا
    ورقة أنماط ثانية للإنجليزية.

    الاستعمال: `@include('sales::partials.settings-theme')` في أول
    `@section('content')`. الاستدعاء المكرّر لا يضرّ — الحارس أدناه يمنع
    تكرار الوسم إن ضمّت الشاشة قالبين كلاهما يستدعيه.

    مقصور على شاشات الإعدادات عمداً: لا يُستدعى من `sales::master`، فبقيّة
    شاشات المبيعات تبقى على مظهرها حتى تُنقل هي الأخرى.
--}}

@once
    <style>
        /* ═══════════════════════════════════════════════════════════
           الرموز — مسافات وزوايا وظلال وخطوط فاصلة
           ═══════════════════════════════════════════════════════════ */

        :root {
            --sl-1: .25rem;  --sl-2: .5rem;   --sl-3: .75rem;
            --sl-4: 1rem;    --sl-5: 1.5rem;  --sl-6: 2rem;

            --sl-r: calc(var(--app-radius, 6px) + 2px);
            --sl-r-lg: calc(var(--app-radius, 6px) + 6px);

            /* ظلال ناعمة متدرّجة بدل ظلّ القالب الواحد الثقيل */
            --sl-e1: 0 1px 2px rgba(16, 24, 40, .04);
            --sl-e2: 0 1px 3px rgba(16, 24, 40, .06), 0 1px 2px rgba(16, 24, 40, .04);
            --sl-e3: 0 4px 12px rgba(16, 24, 40, .07), 0 2px 4px rgba(16, 24, 40, .04);
            --sl-e4: 0 12px 32px rgba(16, 24, 40, .10), 0 4px 8px rgba(16, 24, 40, .05);

            --sl-line: var(--app-border, #e4e7ec);
            --sl-line-soft: color-mix(in srgb, var(--app-border, #e4e7ec) 55%, transparent);
            --sl-tint: color-mix(in srgb, var(--app-primary, #7367f0) 4%, var(--app-surface, #fff));
        }

        /* ═══════════════ الأرضية ═══════════════ */

        .sl-scope { color: var(--app-text, #4b4b4b); }

        /* ═══════════════ ترويسة الصفحة ═══════════════ */

        .sl-page-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-4); flex-wrap: wrap;
            margin-bottom: var(--sl-5);
        }

        .sl-page-head__titles { min-width: 0; display: flex; align-items: center; gap: var(--sl-3); }

        .sl-page-head__icon {
            flex: none; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r);
            background: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            font-size: 1.15rem;
            box-shadow: 0 4px 12px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .sl-page-head__title {
            font-size: 1.35rem; font-weight: var(--app-heading-weight, 700); line-height: 1.25;
            color: var(--app-text, #4b4b4b); margin: 0 0 .15rem; letter-spacing: -.01em;
        }

        .sl-page-head__sub { color: var(--app-muted, #6e6b7b); font-size: .82rem; margin: 0; }

        .sl-page-head .breadcrumb {
            margin: .15rem 0 0; padding: 0; background: none; font-size: .78rem;
        }
        .sl-page-head .breadcrumb-item + .breadcrumb-item::before {
            color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        .sl-page-head__actions { display: flex; gap: var(--sl-2); flex-wrap: wrap; align-items: center; }

        /* ═══════════════ الأزرار ═══════════════
           حجم واحد، ووزن واحد، ومسافة أيقونة واحدة. مقصورة على `.sl-scope`
           كي لا تُعاد صياغة أزرار الترويسة العامّة والشريط الجانبي. */

        .sl-scope .btn {
            --btn-bg: transparent;
            --btn-fg: var(--app-text, #4b4b4b);
            --btn-bd: var(--sl-line);
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            height: 38px; padding: 0 var(--sl-4);
            font-size: .86rem; font-weight: 600; line-height: 1;
            border: 1px solid var(--btn-bd);
            border-radius: var(--sl-r);
            background: var(--btn-bg); color: var(--btn-fg);
            box-shadow: var(--sl-e1);
            transition: background .16s ease, border-color .16s ease, color .16s ease,
                        box-shadow .16s ease, transform .16s ease;
            white-space: nowrap;
        }

        .sl-scope .btn > i, .sl-scope .btn > svg { font-size: .95em; line-height: 1; }
        .sl-scope .btn i.mr-1, .sl-scope .btn i.mr-50,
        .sl-scope .btn i.me-1, .sl-scope .btn i.me-2 { margin: 0 !important; }

        .sl-scope .btn:hover { transform: translateY(-1px); box-shadow: var(--sl-e3); }
        .sl-scope .btn:active { transform: translateY(0); box-shadow: var(--sl-e1); }
        .sl-scope .btn:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .sl-scope .btn-sm { height: 32px; padding: 0 var(--sl-3); font-size: .8rem; }
        .sl-scope .btn-lg { height: 44px; padding: 0 var(--sl-5); font-size: .92rem; }

        .sl-scope .btn-primary,
        .sl-scope .btn-primary:not(:disabled):not(.disabled) {
            --btn-bg: var(--app-primary, #7367f0);
            --btn-fg: var(--app-on-primary, #fff);
            --btn-bd: var(--app-primary, #7367f0);
        }
        .sl-scope .btn-primary:hover, .sl-scope .btn-primary:focus,
        .sl-scope .btn-primary:not(:disabled):not(.disabled):active {
            background: var(--app-primary-hover, #5e50ee) !important;
            border-color: var(--app-primary-hover, #5e50ee) !important;
            color: var(--app-on-primary, #fff) !important;
            box-shadow: 0 6px 16px var(--app-primary-glow, rgba(115, 103, 240, .3)) !important;
        }

        .sl-scope .btn-success { --btn-bg: var(--app-success, #28c76f); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-success, #28c76f); }
        .sl-scope .btn-danger  { --btn-bg: var(--app-danger, #ea5455);  --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-danger, #ea5455); }
        .sl-scope .btn-info    { --btn-bg: var(--app-info, #00cfe8);    --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-info, #00cfe8); }
        .sl-scope .btn-warning { --btn-bg: var(--app-warning, #ff9f43); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-warning, #ff9f43); }
        .sl-scope .btn-secondary, .sl-scope .btn-light { --btn-bg: var(--app-surface, #fff); --btn-fg: var(--app-text, #4b4b4b); }

        .sl-scope .btn-outline-primary   { --btn-fg: var(--app-primary, #7367f0); --btn-bd: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent); }
        .sl-scope .btn-outline-secondary { --btn-fg: var(--app-muted, #6e6b7b);   --btn-bd: var(--sl-line); }
        .sl-scope .btn-outline-danger    { --btn-fg: var(--app-danger, #ea5455);  --btn-bd: color-mix(in srgb, var(--app-danger, #ea5455) 40%, transparent); }
        .sl-scope .btn-outline-success   { --btn-fg: var(--app-success, #28c76f); --btn-bd: color-mix(in srgb, var(--app-success, #28c76f) 40%, transparent); }
        .sl-scope .btn-outline-info      { --btn-fg: var(--app-info, #00cfe8);    --btn-bd: color-mix(in srgb, var(--app-info, #00cfe8) 40%, transparent); }
        .sl-scope .btn-outline-warning   { --btn-fg: var(--app-warning, #ff9f43); --btn-bd: color-mix(in srgb, var(--app-warning, #ff9f43) 40%, transparent); }

        .sl-scope .btn-outline-primary:hover   { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); border-color: var(--app-primary, #7367f0); }
        .sl-scope .btn-outline-secondary:hover { background: var(--app-body-bg, #f8f8f8); color: var(--app-text, #4b4b4b); }
        .sl-scope .btn-outline-danger:hover    { background: var(--app-danger, #ea5455);  color: var(--app-on-primary, #fff); border-color: var(--app-danger, #ea5455); }
        .sl-scope .btn-outline-success:hover   { background: var(--app-success, #28c76f); color: var(--app-on-primary, #fff); border-color: var(--app-success, #28c76f); }
        .sl-scope .btn-outline-info:hover      { background: var(--app-info, #00cfe8);    color: var(--app-on-primary, #fff); border-color: var(--app-info, #00cfe8); }
        .sl-scope .btn-outline-warning:hover   { background: var(--app-warning, #ff9f43); color: var(--app-on-primary, #fff); border-color: var(--app-warning, #ff9f43); }

        /* زرّ أيقونة مربّع — لأعمدة الإجراءات */
        .sl-actions { display: inline-flex; gap: .3rem; }
        .sl-scope .sl-actions .btn { width: 34px; height: 34px; padding: 0; box-shadow: none; }
        .sl-scope .sl-actions .btn:hover { box-shadow: var(--sl-e2); }

        .sl-scope .btn-group > .btn { box-shadow: none; }
        .sl-scope .btn-group > .btn:hover { transform: none; }

        .sl-scope .dropdown-menu {
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r);
            box-shadow: var(--sl-e4);
            padding: var(--sl-1) 0;
            font-size: .86rem;
            background: var(--app-surface, #fff);
        }
        .sl-scope .dropdown-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .5rem var(--sl-4);
            color: var(--app-text, #4b4b4b);
        }
        .sl-scope .dropdown-item:hover,
        .sl-scope .dropdown-item:focus {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        /* ═══════════════ البطاقات ═══════════════ */

        .sl-scope .card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2);
            margin-bottom: var(--sl-4);
            overflow: hidden;
            transition: box-shadow .2s ease;
        }

        .sl-scope .card:hover { box-shadow: var(--sl-e3); }
        .sl-scope .card .card-content { background: transparent; }

        .sl-scope .card > .card-header,
        .sl-scope .card .card-content > .card-header {
            background: transparent;
            border-bottom: 1px solid var(--sl-line-soft);
            padding: var(--sl-4) var(--sl-5);
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap;
        }

        .sl-scope .card .card-title {
            font-size: .98rem; font-weight: 700; color: var(--app-text, #4b4b4b);
            margin: 0; display: flex; align-items: center; gap: var(--sl-2);
        }
        .sl-scope .card .card-title i { color: var(--app-primary, #7367f0); font-size: .95em; }

        .sl-scope .card .card-body { padding: var(--sl-5); }

        /* ═══════════════ البطاقات الإحصائية ═══════════════ */

        .sl-stats {
            display: grid; gap: var(--sl-4);
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-bottom: var(--sl-5);
        }

        .sl-stat {
            display: flex; align-items: center; gap: var(--sl-4);
            padding: var(--sl-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2);
        }
        .sl-stat__icon {
            flex: none; width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.25rem;
        }
        .sl-stat__icon--success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .sl-stat__icon--danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   color: var(--app-danger, #ea5455); }
        .sl-stat__icon--warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .sl-stat__icon--info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    color: var(--app-info, #00cfe8); }

        .sl-stat__body { min-width: 0; }
        .sl-stat__value {
            font-size: 1.55rem; font-weight: 700; line-height: 1.1;
            color: var(--app-text, #4b4b4b); font-variant-numeric: tabular-nums; margin: 0;
        }
        .sl-stat__label { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: .2rem 0 0; line-height: 1.5; }

        /* ═══════════════ الجداول ═══════════════ */

        .sl-table-card > .card-body,
        .sl-table-card .card-content > .card-body { padding: 0; }

        .sl-scope .table {
            color: var(--app-text, #4b4b4b);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sl-scope .table thead th {
            background: var(--sl-tint);
            color: var(--app-muted, #6e6b7b);
            font-size: .74rem; font-weight: 700;
            letter-spacing: .04em; text-transform: uppercase;
            padding: var(--sl-3) var(--sl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--sl-line) !important;
            white-space: nowrap;
            vertical-align: middle;
        }

        .sl-scope .table tbody td {
            padding: var(--sl-4) var(--sl-5);
            border: 0 !important;
            border-bottom: 1px solid var(--sl-line-soft) !important;
            vertical-align: middle;
            font-size: .875rem;
        }

        .sl-scope .table tbody tr:last-child td { border-bottom: 0 !important; }

        /* صفّ الإجماليات — يُقرأ إجمالاً لا صفّاً كبقيّة الصفوف: أرضيةٌ
           خفيفة وخطٌّ أعلى أثخن يفصله عن التفاصيل فوقه */
        .sl-scope .table tfoot td,
        .sl-scope .table tfoot th {
            padding: var(--sl-4) var(--sl-5);
            background: var(--sl-tint);
            border: 0 !important;
            border-top: 2px solid var(--sl-line) !important;
            font-weight: 700;
            font-size: .875rem;
            vertical-align: middle;
            font-variant-numeric: tabular-nums;
        }
        .sl-scope .table-hover tbody tr { transition: background .14s ease; }
        .sl-scope .table-hover tbody tr:hover { background: var(--app-primary-soft, rgba(115, 103, 240, .12)) !important; }
        .sl-scope .table-responsive { border: 0; }
        .sl-num, .sl-scope .table td[data-num] { font-variant-numeric: tabular-nums; }

        /* رأس بطاقة الجدول */
        .sl-table-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap;
            padding: var(--sl-4) var(--sl-5);
            border-bottom: 1px solid var(--sl-line-soft);
        }
        .sl-table-head__title {
            display: flex; align-items: center; gap: var(--sl-2);
            font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .sl-table-head__title i { color: var(--app-primary, #7367f0); }

        .sl-count {
            display: inline-flex; align-items: center;
            padding: .1rem .5rem; border-radius: 999px;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
        }

        /* خلية اسم + أيقونة */
        .sl-media { display: flex; align-items: center; gap: var(--sl-3); min-width: 0; }
        .sl-media__thumb {
            flex: none; width: 40px; height: 40px;
            border-radius: var(--sl-r); object-fit: cover;
            border: 1px solid var(--sl-line); background: var(--app-body-bg, #f8f8f8);
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--app-muted, #6e6b7b); font-size: .95rem;
        }
        .sl-media__body { min-width: 0; }
        .sl-media__title { font-weight: 600; color: var(--app-text, #4b4b4b); display: block; line-height: 1.35; }
        .sl-media__sub {
            font-size: .75rem; color: var(--app-muted, #6e6b7b); display: block;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شارة الحالة ═══════════════
           النقطة إلى جانب اللون: اللون وحده لا يكفي مَن لا يميّزه. */

        .sl-state {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .22rem .62rem; border-radius: 999px;
            font-size: .74rem; font-weight: 600; line-height: 1.4;
        }
        .sl-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex: none; }
        .sl-state--on  { color: var(--app-success, #28c76f); background: var(--app-success-soft, rgba(40, 199, 111, .12)); }
        .sl-state--off { color: var(--app-muted, #6e6b7b);   background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); }
        .sl-state--neutral { color: var(--app-info, #00cfe8); background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); }
        .sl-state--neutral::before { display: none; }

        .sl-tags { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }

        .sl-scope .badge {
            display: inline-flex; align-items: center;
            padding: .28em .65em; border-radius: 999px;
            font-size: .73rem; font-weight: 600; letter-spacing: 0;
        }
        .sl-scope .badge-primary, .sl-scope .badge-light-primary { background: var(--app-primary-soft, rgba(115, 103, 240, .12)); color: var(--app-primary, #7367f0); }
        .sl-scope .badge-success, .sl-scope .badge-light-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .sl-scope .badge-danger,  .sl-scope .badge-light-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));  color: var(--app-danger, #ea5455); }
        .sl-scope .badge-info,    .sl-scope .badge-light-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); color: var(--app-info, #00cfe8); }
        .sl-scope .badge-warning, .sl-scope .badge-light-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .sl-scope .badge-secondary, .sl-scope .badge-light {
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); color: var(--app-muted, #6e6b7b);
        }

        /* ═══════════════ ذيل الجدول والترقيم ═══════════════ */

        .sl-table-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-4); flex-wrap: wrap;
            padding: var(--sl-3) var(--sl-5);
            border-top: 1px solid var(--sl-line-soft);
            background: var(--sl-tint);
        }
        .sl-table-foot__count {
            font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: 0;
            font-variant-numeric: tabular-nums;
        }

        .sl-scope .pagination { gap: .3rem; margin: 0; flex-wrap: wrap; }

        .sl-scope .pagination .page-item .page-link,
        .sl-scope .pagination .page-link {
            min-width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            margin: 0; padding: 0 .55rem;
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r) !important;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .82rem; font-weight: 600; font-variant-numeric: tabular-nums;
            box-shadow: none;
            transition: background .14s ease, border-color .14s ease, color .14s ease;
        }

        .sl-scope .pagination .page-item .page-link:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 35%, transparent);
            color: var(--app-primary, #7367f0);
        }

        .sl-scope .pagination .page-item.active .page-link {
            background: var(--app-primary, #7367f0); border-color: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            box-shadow: 0 3px 10px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .sl-scope .pagination .page-item.disabled .page-link {
            background: var(--app-surface, #fff); color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        /* مُنتقي عدد السجلّات: عنصر واحد مضموم، لا ثلاثة عناصر متجاورة */
        .sl-perpage {
            display: inline-flex; align-items: stretch;
            border: 1px solid var(--sl-line);
            border-radius: 999px;
            background: var(--app-surface, #fff);
            overflow: hidden;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .sl-perpage:focus-within {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .sl-perpage label,
        .sl-perpage__unit {
            display: inline-flex; align-items: center;
            margin: 0; padding: .32rem .8rem;
            font-size: .78rem; font-weight: 600; color: var(--app-muted, #6e6b7b);
            background: var(--sl-tint);
            white-space: nowrap;
        }
        .sl-perpage__select {
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
        .sl-perpage__select:focus { background-color: var(--sl-tint); }

        /* ═══════════════ النماذج ═══════════════ */

        .sl-scope .form-group { margin-bottom: var(--sl-4); }

        /* `:not(.sl-switch)` مقصود: مفتاح التبديل وسمُه `<label>` أيضاً،
           وهذه القاعدة أعلى تخصيصاً (0,1,1) من `.sl-switch` (0,1,0) فكانت
           تفرض عليه `display:block` — فيسقط النصّ تحت المسار بدل أن يقف
           بجانبه، وتنفصل الأسطر بـ`margin-bottom`. */
        .sl-scope label:not(.sl-switch),
        .sl-scope .form-group > label:not(.sl-switch) {
            display: block;
            font-size: .82rem; font-weight: 600; color: var(--app-text, #4b4b4b);
            margin-bottom: .4rem;
        }

        .sl-scope .form-control, .sl-scope .custom-select, .sl-scope .form-select,
        .sl-scope .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 0 var(--sl-3);
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r);
            color: var(--app-text, #4b4b4b);
            font-size: .875rem;
            box-shadow: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .sl-scope textarea.form-control { height: auto; padding: var(--sl-3); min-height: 90px; }

        .sl-scope .form-control:focus, .sl-scope .custom-select:focus, .sl-scope .form-select:focus,
        .sl-scope .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
            outline: 0;
        }

        .sl-scope .form-control::placeholder { color: var(--app-muted, #6e6b7b); opacity: .7; }
        .sl-scope .form-control.is-invalid { border-color: var(--app-danger, #ea5455); }
        .sl-scope .invalid-feedback { font-size: .76rem; color: var(--app-danger, #ea5455); margin-top: .3rem; }

        .sl-scope .input-group-text {
            background: var(--sl-tint); border: 1px solid var(--sl-line);
            color: var(--app-muted, #6e6b7b); border-radius: var(--sl-r);
        }

        .sl-scope .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px; color: var(--app-text, #4b4b4b); padding: 0;
        }
        .sl-scope .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }

        /* أقسام النموذج */
        .sl-section { margin-bottom: var(--sl-5); }
        .sl-section:last-child { margin-bottom: 0; }
        .sl-section__head {
            display: flex; align-items: center; gap: var(--sl-2);
            padding-bottom: var(--sl-2); margin-bottom: var(--sl-4);
            border-bottom: 1px solid var(--sl-line-soft);
        }
        .sl-section__head i { color: var(--app-primary, #7367f0); font-size: .9rem; }
        .sl-section__title { font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0; }

        .sl-req::after { content: ' *'; color: var(--app-danger, #ea5455); font-weight: 700; }
        /* ═══════ قائمةٌ بجانبها زرُّ إضافة ═══════

           العميل غير مسجَّل؟ زرٌّ يفتح شاشة إضافته في تبويبٍ جديد بدل إجبار
           المستخدم على مغادرة النموذج الذي يملؤه وضياع ما أدخله. منقولةٌ عن
           `po-select-add` في وحدة المشتريات، بالبادئة `sl-`. */
        .sl-select-add { display: flex; align-items: stretch; gap: .4rem; }
        .sl-select-add > select,
        .sl-select-add > .select2 { flex: 1 1 auto; min-width: 0; }
        .sl-select-add__btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.35rem; padding: 0;
        }

        .sl-help { display: block; font-size: .75rem; color: var(--app-muted, #6e6b7b); margin-top: .3rem; }

        /* ذيل النموذج: الشرح في جهة والأزرار في الأخرى. حين يخلو من الشرح
           تنزلق الأزرار إلى النهاية وحدها بفضل `space-between`، فلا حاجة
           إلى صنف ثانٍ لحالتين من الشيء الواحد. */
        .sl-form-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap;
            padding: var(--sl-4) var(--sl-5);
            border-top: 1px solid var(--sl-line-soft);
            background: var(--sl-tint);
        }
        .sl-form-foot > :only-child { margin-inline-start: auto; }

        /* مستقلاًّ عن بطاقة — يحتاج حافّته واستدارته وظلّه بنفسه */
        .sl-scope > .sl-form-foot,
        .sl-scope form > .sl-form-foot {
            border: 1px solid var(--sl-line); border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2); margin-bottom: var(--sl-5);
        }

        /* ═══════════════ الفلاتر ═══════════════ */

        .sl-filters .card-body { padding: var(--sl-5); }
        .sl-filters__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap; margin-bottom: var(--sl-4);
        }
        .sl-filters__title {
            display: flex; align-items: center; gap: var(--sl-2);
            font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .sl-filters__title i { color: var(--app-primary, #7367f0); }
        .sl-filters__foot {
            display: flex; gap: var(--sl-2); justify-content: flex-end;
            padding-top: var(--sl-4); margin-top: var(--sl-1);
            border-top: 1px dashed var(--sl-line);
        }

        /* ═══════════════ بلاطات الإعدادات ═══════════════ */

        .sl-tiles { display: grid; gap: var(--sl-4); grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }

        .sl-tile {
            display: flex; align-items: flex-start; gap: var(--sl-3);
            padding: var(--sl-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2);
            color: inherit; text-decoration: none;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            position: relative; overflow: hidden;
        }
        .sl-tile::before {
            content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
            width: 3px; background: var(--app-primary, #7367f0);
            transform: scaleY(0); transform-origin: top; transition: transform .2s ease;
        }
        .sl-tile:hover {
            transform: translateY(-2px); box-shadow: var(--sl-e4);
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 28%, var(--sl-line));
            color: inherit; text-decoration: none;
        }
        .sl-tile:hover::before { transform: scaleY(1); }
        .sl-tile:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .sl-tile__icon {
            flex: none; width: 46px; height: 46px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.2rem;
        }
        .sl-tile__body { min-width: 0; }
        .sl-tile__title { font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0 0 .2rem; }
        .sl-tile__hint  { font-size: .78rem; color: var(--app-muted, #6e6b7b); margin: 0; line-height: 1.6; }

        .sl-tile__kids { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .55rem; }
        .sl-tile__kid {
            font-size: .72rem; font-weight: 600;
            padding: .12rem .5rem; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 10%, transparent);
            color: var(--app-muted, #6e6b7b); text-decoration: none;
        }
        .sl-tile__kid:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        .sl-group { margin-bottom: var(--sl-5); }
        .sl-group__head {
            display: flex; align-items: center; gap: var(--sl-2);
            margin-bottom: var(--sl-3);
        }
        .sl-group__head i { color: var(--app-primary, #7367f0); }
        .sl-group__title { font-size: .95rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0; }

        /* ═══════════════ مفتاح التبديل ═══════════════ */

        /* الوسم `label` في المُنتقي مقصود لا زائد: يرفع التخصيص إلى (0,1,1)
           فيغلب أيَّ قاعدةٍ عامّة على `label` داخل `.sl-scope`، ويبقى
           المفتاح صفّاً واحداً: مسارٌ ثم نصّه بجانبه. */
        .sl-scope label.sl-switch,
        .sl-switch {
            display: flex; align-items: flex-start; gap: var(--sl-3);
            margin: 0; cursor: pointer; user-select: none;
            font-size: .87rem; font-weight: 600;
        }
        .sl-switch > input { position: absolute; opacity: 0; width: 0; height: 0; }

        .sl-switch__track {
            flex: none; position: relative;
            width: 42px; height: 24px; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 28%, transparent);
            transition: background .18s ease;
            margin-top: 2px;
        }
        .sl-switch__thumb {
            position: absolute; top: 3px; inset-inline-start: 3px;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--app-surface, #fff);
            box-shadow: var(--sl-e2);
            transition: transform .18s ease;
        }
        .sl-switch > input:checked + .sl-switch__track { background: var(--app-primary, #7367f0); }
        .sl-switch > input:checked + .sl-switch__track .sl-switch__thumb { transform: translateX(-18px); }
        html[dir="ltr"] .sl-switch > input:checked + .sl-switch__track .sl-switch__thumb { transform: translateX(18px); }
        .sl-switch > input:focus-visible + .sl-switch__track { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }
        .sl-switch > input:disabled + .sl-switch__track { opacity: .5; cursor: not-allowed; }

        /* مفتاحٌ بلا نصّ — في خليّة جدول لا في صفّ إعداد. العنوان يحمله
           رأسُ العمود، فتكراره في كل صفّ حشو. `aria-label` على المدخل
           يبقى واجباً: قارئ الشاشة لا يقرأ رأس العمود مع كل خليّة. */
        .sl-scope label.sl-switch--bare,
        .sl-switch--bare { display: inline-flex; align-items: center; justify-content: center; }
        .sl-switch--bare .sl-switch__track { margin-top: 0; }

        .sl-switch__label { display: flex; flex-direction: column; gap: .1rem; }
        .sl-switch__title { font-size: .87rem; font-weight: 600; color: var(--app-text, #4b4b4b); }
        .sl-switch__hint  { font-size: .75rem; color: var(--app-muted, #6e6b7b); line-height: 1.5; }

        .sl-switch-row {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: var(--sl-4);
            padding: var(--sl-3) 0;
            border-bottom: 1px solid var(--sl-line-soft);
        }
        .sl-switch-row:last-child { border-bottom: 0; }

        /* ═══════════════ الحالة الفارغة ═══════════════ */

        .sl-empty { text-align: center; padding: var(--sl-6) var(--sl-5); color: var(--app-muted, #6e6b7b); }
        .sl-empty__icon {
            width: 60px; height: 60px; margin: 0 auto var(--sl-4);
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.6rem;
        }
        .sl-empty__title { color: var(--app-text, #4b4b4b); font-weight: 700; font-size: 1rem; margin-bottom: .35rem; }
        .sl-empty__hint  { font-size: .85rem; margin: 0 0 var(--sl-4); }

        /* ═══════════════ التنبيهات ═══════════════ */

        .sl-scope .alert {
            border: 1px solid transparent; border-radius: var(--sl-r);
            padding: var(--sl-3) var(--sl-4); font-size: .85rem;
        }
        .sl-scope .alert-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); border-color: color-mix(in srgb, var(--app-success, #28c76f) 30%, transparent); color: var(--app-success, #28c76f); }
        .sl-scope .alert-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   border-color: color-mix(in srgb, var(--app-danger, #ea5455) 30%, transparent);  color: var(--app-danger, #ea5455); }
        .sl-scope .alert-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 12%, transparent); border-color: color-mix(in srgb, var(--app-warning, #ff9f43) 30%, transparent); color: var(--app-warning, #ff9f43); }
        .sl-scope .alert-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    border-color: color-mix(in srgb, var(--app-info, #00cfe8) 30%, transparent);    color: var(--app-info, #00cfe8); }

        /* ═══════════════ تبويبات ═══════════════

           رابطٌ لكل تبويب لا زرّ: التبويب هنا صفحةٌ بعنوانها الخاصّ
           (`?type=collections`)، فيُفتح في تبويب جديد ويُحفظ في المفضّلة.
           الخطّ السفلي يتبع اللون الأساسي للمظهر. */

        .sl-tabs {
            display: flex; gap: var(--sl-1); flex-wrap: wrap;
            border-bottom: 2px solid var(--sl-line);
            margin-bottom: var(--sl-5);
        }
        .sl-tab {
            display: inline-flex; align-items: center; gap: var(--sl-2);
            padding: var(--sl-3) var(--sl-4);
            margin-bottom: -2px;
            border-bottom: 3px solid transparent;
            color: var(--app-muted, #6e6b7b);
            font-size: .9rem; font-weight: 600; text-decoration: none;
            transition: color .14s ease, border-color .14s ease;
        }
        .sl-tab:hover { color: var(--app-primary, #7367f0); text-decoration: none; }
        .sl-tab.is-active { color: var(--app-primary, #7367f0); border-bottom-color: var(--app-primary, #7367f0); }
        .sl-tab:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: -2px; }

        /* ═══════════════ شريط سياق إعدادات النظام ═══════════════

           بندٌ واحد لكل إعداد عامّ تعمل به الشاشة: اسمه، وقيمته الحالية،
           ورابطٌ إلى الشاشة التي تضبطه. الشبكة تنساب بـ`auto-fill` فلا
           يبقى بندٌ وحيدٌ في سطر على الشاشات المتوسّطة.

           البند كلّه رابط، والقيمة `<span>` داخله: لا رابط داخل رابط. */

        .sl-ctx { margin-bottom: var(--sl-5); }

        .sl-ctx__head {
            display: flex; align-items: baseline; gap: var(--sl-2);
            flex-wrap: wrap; margin-bottom: var(--sl-3);
        }
        .sl-ctx__title {
            display: flex; align-items: center; gap: var(--sl-2);
            font-size: .88rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .sl-ctx__title i { color: var(--app-primary, #7367f0); }
        .sl-ctx__hint { font-size: .76rem; color: var(--app-muted, #6e6b7b); margin: 0; }

        .sl-ctx__grid {
            display: grid; gap: var(--sl-2);
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        }

        .sl-ctx__item {
            display: flex; align-items: center; gap: var(--sl-3);
            padding: var(--sl-2) var(--sl-3);
            border: 1px solid var(--sl-line-soft); border-radius: var(--sl-r);
            background: var(--app-surface, #fff);
            color: inherit; text-decoration: none;
            transition: border-color .14s ease, box-shadow .14s ease, transform .14s ease;
        }
        a.sl-ctx__item:hover {
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent);
            box-shadow: var(--sl-e2); transform: translateY(-1px);
            color: inherit; text-decoration: none;
        }
        a.sl-ctx__item:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .sl-ctx__icon {
            width: 32px; height: 32px; flex: none;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--sl-r); font-size: .9rem;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }
        .sl-ctx__item--on  .sl-ctx__icon { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .sl-ctx__item--off .sl-ctx__icon { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }

        .sl-ctx__body { min-width: 0; }
        .sl-ctx__label { display: block; font-size: .72rem; color: var(--app-muted, #6e6b7b); line-height: 1.4; }
        .sl-ctx__value {
            display: block; font-size: .82rem; font-weight: 600;
            color: var(--app-text, #4b4b4b); line-height: 1.4;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شريط البحث في اللوحة ═══════════════ */

        .sl-hubsearch { position: relative; max-width: 420px; margin-bottom: var(--sl-5); }
        .sl-hubsearch input {
            width: 100%; height: 42px;
            padding-inline-start: 2.4rem; padding-inline-end: var(--sl-3);
            border: 1px solid var(--sl-line); border-radius: 999px;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .88rem;
        }
        .sl-hubsearch input:focus {
            outline: 0; border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .sl-hubsearch i {
            position: absolute; inset-inline-start: .9rem; top: 50%; transform: translateY(-50%);
            color: var(--app-muted, #6e6b7b); font-size: .95rem; pointer-events: none;
        }

        /* ═══════════════ الطباعة ═══════════════ */

        @media print {
            .sl-page-head__actions, .sl-filters, .sl-table-foot, .sl-perpage, .sl-ctx { display: none !important; }
            .sl-scope .card { box-shadow: none; border-color: #ddd; }
        }

        /* ═══════════════ الشاشات الصغيرة ═══════════════ */

        @media (max-width: 575.98px) {
            .sl-page-head { align-items: flex-start; }
            .sl-page-head__actions { width: 100%; }
            .sl-page-head__actions .btn { flex: 1 1 auto; }
            .sl-scope .table thead th, .sl-scope .table tbody td { padding-inline: var(--sl-3); }
        }

        /* ═══════════════ لوحة الإرشادات ═══════════════

           شاشات الإعدادات تعرض مفاتيح تبديلٍ بعناوين مختصرة، ولا موضع
           فيها لشرح ما يفعله القسم كلُّه ولا لعلاقته بإعدادٍ آخر. هذه
           اللوحة موضعُ ذلك: `<details>` أصلاً — تُطوى بلا سطر سكربت،
           وتُفتح بالوسم `open` أوّل مرّة. */

        .sl-guide {
            background: var(--app-primary-soft, rgba(115, 103, 240, .08));
            border: 1px solid var(--sl-line);
            border-inline-start: 3px solid var(--app-primary, #7367f0);
            border-radius: var(--sl-r-lg);
            margin-bottom: var(--sl-4);
        }

        .sl-guide > summary {
            display: flex; align-items: center; gap: .5rem;
            padding: var(--sl-3) var(--sl-5);
            font-weight: 700; font-size: .9rem;
            color: var(--app-text, #4b4b4b);
            cursor: pointer;
            list-style: none;
        }

        /* المثلّث الافتراضي يختلف بين المتصفّحات ولا ينقلب مع `dir` */
        .sl-guide > summary::-webkit-details-marker { display: none; }
        .sl-guide > summary::marker { content: ''; }

        .sl-guide > summary::after {
            content: '';
            inline-size: .5rem; block-size: .5rem;
            margin-inline-start: auto;
            border-inline-end: 2px solid var(--app-muted, #6e6b7b);
            border-block-end: 2px solid var(--app-muted, #6e6b7b);
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .sl-guide[open] > summary::after { transform: rotate(-135deg); }

        .sl-guide__body {
            padding: 0 var(--sl-5) var(--sl-4);
            font-size: .85rem; line-height: 1.9;
            color: var(--app-muted, #6e6b7b);
        }

        .sl-guide__body h6 {
            font-size: .82rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
            margin: var(--sl-3) 0 .2rem;
        }

        .sl-guide__body p { margin: 0 0 .35rem; }

        /* سطر الحقيقة: ما هو مضبوطٌ الآن فعلاً، لا شرحاً نظرياً */
        .sl-guide__fact {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-sm, .35rem);
            padding: .3rem .6rem;
            font-weight: 600;
            color: var(--app-text, #4b4b4b);
        }

        /* شارة عدد المفاتيح في لسان التبويب */
        .sl-scope .sl-doc-tabs .badge { margin-inline-start: .35rem; }
    </style>
@endonce
