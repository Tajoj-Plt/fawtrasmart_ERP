{{--
    نظام تصميم شاشات الحجوزات — البادئة `rsv-`.

    **منقولٌ من `sales::partials.settings-theme` حرفاً بحرف**، بتبديل
    البادئة وحدَها (`sl-` ← `rsv-`). وهو اصطلاح النظام: تقارير المخزون
    تستعمل التصميمَ نفسَه ببادئةٍ ثالثة — «بادئةٌ مختلفة لتصميمٍ واحد».

    **ولماذا نُسخ ولم يُستعار؟** `@include('sales::…')` يعمل اليوم ويكسر
    غداً: تعطيلُ المبيعات في `modules_statuses.json` يُسقط تسجيلَ قوالبها
    فتنهار شاشاتُ الحجوزات بخطأ «view not found». وهو الدرسُ المكتوب في
    ترويسة `sales::sitting.index` نفسِها بعد أن استعارت لوحةَ وحدة
    الإعدادات ثمّ نُقلت إلى وحدتها.

    ويلي التصميمَ المنقولَ **إضافاتُ الوحدة** وحدها في آخر الملفّ:
    الجدول والشارة والمبلغ والفترات المتاحة وخطّ حياة الحجز — أصنافٌ لا
    نظير لها في المبيعات.
--}}

@once
    <style>
        /* ═══════════════════════════════════════════════════════════
           الرموز — مسافات وزوايا وظلال وخطوط فاصلة
           ═══════════════════════════════════════════════════════════ */

        :root {
            --rsv-1: .25rem;  --rsv-2: .5rem;   --rsv-3: .75rem;
            --rsv-4: 1rem;    --rsv-5: 1.5rem;  --rsv-6: 2rem;

            --rsv-r: calc(var(--app-radius, 6px) + 2px);
            --rsv-r-lg: calc(var(--app-radius, 6px) + 6px);

            /* ظلال ناعمة متدرّجة بدل ظلّ القالب الواحد الثقيل */
            --rsv-e1: 0 1px 2px rgba(16, 24, 40, .04);
            --rsv-e2: 0 1px 3px rgba(16, 24, 40, .06), 0 1px 2px rgba(16, 24, 40, .04);
            --rsv-e3: 0 4px 12px rgba(16, 24, 40, .07), 0 2px 4px rgba(16, 24, 40, .04);
            --rsv-e4: 0 12px 32px rgba(16, 24, 40, .10), 0 4px 8px rgba(16, 24, 40, .05);

            --rsv-line: var(--app-border, #e4e7ec);
            --rsv-line-soft: color-mix(in srgb, var(--app-border, #e4e7ec) 55%, transparent);
            --rsv-tint: color-mix(in srgb, var(--app-primary, #7367f0) 4%, var(--app-surface, #fff));
        }

        /* ═══════════════ الأرضية ═══════════════ */

        .rsv-scope { color: var(--app-text, #4b4b4b); }

        /* ═══════════════ ترويسة الصفحة ═══════════════ */

        .rsv-page-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-4); flex-wrap: wrap;
            margin-bottom: var(--rsv-5);
        }

        .rsv-page-head__titles { min-width: 0; display: flex; align-items: center; gap: var(--rsv-3); }

        .rsv-page-head__icon {
            flex: none; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--rsv-r);
            background: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            font-size: 1.15rem;
            box-shadow: 0 4px 12px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .rsv-page-head__title {
            font-size: 1.35rem; font-weight: var(--app-heading-weight, 700); line-height: 1.25;
            color: var(--app-text, #4b4b4b); margin: 0 0 .15rem; letter-spacing: -.01em;
        }

        .rsv-page-head__sub { color: var(--app-muted, #6e6b7b); font-size: .82rem; margin: 0; }

        .rsv-page-head .breadcrumb {
            margin: .15rem 0 0; padding: 0; background: none; font-size: .78rem;
        }
        .rsv-page-head .breadcrumb-item + .breadcrumb-item::before {
            color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        .rsv-page-head__actions { display: flex; gap: var(--rsv-2); flex-wrap: wrap; align-items: center; }

        /* ═══════════════ الأزرار ═══════════════
           حجم واحد، ووزن واحد، ومسافة أيقونة واحدة. مقصورة على `.rsv-scope`
           كي لا تُعاد صياغة أزرار الترويسة العامّة والشريط الجانبي. */

        .rsv-scope .btn {
            --btn-bg: transparent;
            --btn-fg: var(--app-text, #4b4b4b);
            --btn-bd: var(--rsv-line);
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            height: 38px; padding: 0 var(--rsv-4);
            font-size: .86rem; font-weight: 600; line-height: 1;
            border: 1px solid var(--btn-bd);
            border-radius: var(--rsv-r);
            background: var(--btn-bg); color: var(--btn-fg);
            box-shadow: var(--rsv-e1);
            transition: background .16s ease, border-color .16s ease, color .16s ease,
                        box-shadow .16s ease, transform .16s ease;
            white-space: nowrap;
        }

        .rsv-scope .btn > i, .rsv-scope .btn > svg { font-size: .95em; line-height: 1; }
        .rsv-scope .btn i.mr-1, .rsv-scope .btn i.mr-50,
        .rsv-scope .btn i.me-1, .rsv-scope .btn i.me-2 { margin: 0 !important; }

        .rsv-scope .btn:hover { transform: translateY(-1px); box-shadow: var(--rsv-e3); }
        .rsv-scope .btn:active { transform: translateY(0); box-shadow: var(--rsv-e1); }
        .rsv-scope .btn:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .rsv-scope .btn-sm { height: 32px; padding: 0 var(--rsv-3); font-size: .8rem; }
        .rsv-scope .btn-lg { height: 44px; padding: 0 var(--rsv-5); font-size: .92rem; }

        .rsv-scope .btn-primary,
        .rsv-scope .btn-primary:not(:disabled):not(.disabled) {
            --btn-bg: var(--app-primary, #7367f0);
            --btn-fg: var(--app-on-primary, #fff);
            --btn-bd: var(--app-primary, #7367f0);
        }
        .rsv-scope .btn-primary:hover, .rsv-scope .btn-primary:focus,
        .rsv-scope .btn-primary:not(:disabled):not(.disabled):active {
            background: var(--app-primary-hover, #5e50ee) !important;
            border-color: var(--app-primary-hover, #5e50ee) !important;
            color: var(--app-on-primary, #fff) !important;
            box-shadow: 0 6px 16px var(--app-primary-glow, rgba(115, 103, 240, .3)) !important;
        }

        .rsv-scope .btn-success { --btn-bg: var(--app-success, #28c76f); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-success, #28c76f); }
        .rsv-scope .btn-danger  { --btn-bg: var(--app-danger, #ea5455);  --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-danger, #ea5455); }
        .rsv-scope .btn-info    { --btn-bg: var(--app-info, #00cfe8);    --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-info, #00cfe8); }
        .rsv-scope .btn-warning { --btn-bg: var(--app-warning, #ff9f43); --btn-fg: var(--app-on-primary, #fff); --btn-bd: var(--app-warning, #ff9f43); }
        .rsv-scope .btn-secondary, .rsv-scope .btn-light { --btn-bg: var(--app-surface, #fff); --btn-fg: var(--app-text, #4b4b4b); }

        .rsv-scope .btn-outline-primary   { --btn-fg: var(--app-primary, #7367f0); --btn-bd: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent); }
        .rsv-scope .btn-outline-secondary { --btn-fg: var(--app-muted, #6e6b7b);   --btn-bd: var(--rsv-line); }
        .rsv-scope .btn-outline-danger    { --btn-fg: var(--app-danger, #ea5455);  --btn-bd: color-mix(in srgb, var(--app-danger, #ea5455) 40%, transparent); }
        .rsv-scope .btn-outline-success   { --btn-fg: var(--app-success, #28c76f); --btn-bd: color-mix(in srgb, var(--app-success, #28c76f) 40%, transparent); }
        .rsv-scope .btn-outline-info      { --btn-fg: var(--app-info, #00cfe8);    --btn-bd: color-mix(in srgb, var(--app-info, #00cfe8) 40%, transparent); }
        .rsv-scope .btn-outline-warning   { --btn-fg: var(--app-warning, #ff9f43); --btn-bd: color-mix(in srgb, var(--app-warning, #ff9f43) 40%, transparent); }

        .rsv-scope .btn-outline-primary:hover   { background: var(--app-primary, #7367f0); color: var(--app-on-primary, #fff); border-color: var(--app-primary, #7367f0); }
        .rsv-scope .btn-outline-secondary:hover { background: var(--app-body-bg, #f8f8f8); color: var(--app-text, #4b4b4b); }
        .rsv-scope .btn-outline-danger:hover    { background: var(--app-danger, #ea5455);  color: var(--app-on-primary, #fff); border-color: var(--app-danger, #ea5455); }
        .rsv-scope .btn-outline-success:hover   { background: var(--app-success, #28c76f); color: var(--app-on-primary, #fff); border-color: var(--app-success, #28c76f); }
        .rsv-scope .btn-outline-info:hover      { background: var(--app-info, #00cfe8);    color: var(--app-on-primary, #fff); border-color: var(--app-info, #00cfe8); }
        .rsv-scope .btn-outline-warning:hover   { background: var(--app-warning, #ff9f43); color: var(--app-on-primary, #fff); border-color: var(--app-warning, #ff9f43); }

        /* زرّ أيقونة مربّع — لأعمدة الإجراءات */
        .rsv-actions { display: inline-flex; gap: .3rem; }
        .rsv-scope .rsv-actions .btn { width: 34px; height: 34px; padding: 0; box-shadow: none; }
        .rsv-scope .rsv-actions .btn:hover { box-shadow: var(--rsv-e2); }

        .rsv-scope .btn-group > .btn { box-shadow: none; }
        .rsv-scope .btn-group > .btn:hover { transform: none; }

        .rsv-scope .dropdown-menu {
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r);
            box-shadow: var(--rsv-e4);
            padding: var(--rsv-1) 0;
            font-size: .86rem;
            background: var(--app-surface, #fff);
        }
        .rsv-scope .dropdown-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .5rem var(--rsv-4);
            color: var(--app-text, #4b4b4b);
        }
        .rsv-scope .dropdown-item:hover,
        .rsv-scope .dropdown-item:focus {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        /* ═══════════════ البطاقات ═══════════════ */

        .rsv-scope .card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r-lg);
            box-shadow: var(--rsv-e2);
            margin-bottom: var(--rsv-4);
            overflow: hidden;
            transition: box-shadow .2s ease;
        }

        .rsv-scope .card:hover { box-shadow: var(--rsv-e3); }
        .rsv-scope .card .card-content { background: transparent; }

        .rsv-scope .card > .card-header,
        .rsv-scope .card .card-content > .card-header {
            background: transparent;
            border-bottom: 1px solid var(--rsv-line-soft);
            padding: var(--rsv-4) var(--rsv-5);
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-3); flex-wrap: wrap;
        }

        .rsv-scope .card .card-title {
            font-size: .98rem; font-weight: 700; color: var(--app-text, #4b4b4b);
            margin: 0; display: flex; align-items: center; gap: var(--rsv-2);
        }
        .rsv-scope .card .card-title i { color: var(--app-primary, #7367f0); font-size: .95em; }

        .rsv-scope .card .card-body { padding: var(--rsv-5); }

        /* ═══════════════ البطاقات الإحصائية ═══════════════ */

        .rsv-stats {
            display: grid; gap: var(--rsv-4);
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            margin-bottom: var(--rsv-5);
        }

        .rsv-stat {
            display: flex; align-items: center; gap: var(--rsv-4);
            padding: var(--rsv-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r-lg);
            box-shadow: var(--rsv-e2);
        }
        .rsv-stat__icon {
            flex: none; width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--rsv-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.25rem;
        }
        .rsv-stat__icon--success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .rsv-stat__icon--danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   color: var(--app-danger, #ea5455); }
        .rsv-stat__icon--warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .rsv-stat__icon--info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    color: var(--app-info, #00cfe8); }

        .rsv-stat__body { min-width: 0; }
        .rsv-stat__value {
            font-size: 1.55rem; font-weight: 700; line-height: 1.1;
            color: var(--app-text, #4b4b4b); font-variant-numeric: tabular-nums; margin: 0;
        }
        .rsv-stat__label { font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: .2rem 0 0; line-height: 1.5; }

        /* ═══════════════ الجداول ═══════════════ */

        .rsv-table-card > .card-body,
        .rsv-table-card .card-content > .card-body { padding: 0; }

        .rsv-scope .table {
            color: var(--app-text, #4b4b4b);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .rsv-scope .table thead th {
            background: var(--rsv-tint);
            color: var(--app-muted, #6e6b7b);
            font-size: .74rem; font-weight: 700;
            letter-spacing: .04em; text-transform: uppercase;
            padding: var(--rsv-3) var(--rsv-5);
            border: 0 !important;
            border-bottom: 1px solid var(--rsv-line) !important;
            white-space: nowrap;
            vertical-align: middle;
        }

        .rsv-scope .table tbody td {
            padding: var(--rsv-4) var(--rsv-5);
            border: 0 !important;
            border-bottom: 1px solid var(--rsv-line-soft) !important;
            vertical-align: middle;
            font-size: .875rem;
        }

        .rsv-scope .table tbody tr:last-child td { border-bottom: 0 !important; }

        /* صفّ الإجماليات — يُقرأ إجمالاً لا صفّاً كبقيّة الصفوف: أرضيةٌ
           خفيفة وخطٌّ أعلى أثخن يفصله عن التفاصيل فوقه */
        .rsv-scope .table tfoot td,
        .rsv-scope .table tfoot th {
            padding: var(--rsv-4) var(--rsv-5);
            background: var(--rsv-tint);
            border: 0 !important;
            border-top: 2px solid var(--rsv-line) !important;
            font-weight: 700;
            font-size: .875rem;
            vertical-align: middle;
            font-variant-numeric: tabular-nums;
        }
        .rsv-scope .table-hover tbody tr { transition: background .14s ease; }
        .rsv-scope .table-hover tbody tr:hover { background: var(--app-primary-soft, rgba(115, 103, 240, .12)) !important; }
        .rsv-scope .table-responsive { border: 0; }
        .rsv-num, .rsv-scope .table td[data-num] { font-variant-numeric: tabular-nums; }

        /* رأس بطاقة الجدول */
        .rsv-table-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-3); flex-wrap: wrap;
            padding: var(--rsv-4) var(--rsv-5);
            border-bottom: 1px solid var(--rsv-line-soft);
        }
        .rsv-table-head__title {
            display: flex; align-items: center; gap: var(--rsv-2);
            font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .rsv-table-head__title i { color: var(--app-primary, #7367f0); }

        .rsv-count {
            display: inline-flex; align-items: center;
            padding: .1rem .5rem; border-radius: 999px;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
        }

        /* خلية اسم + أيقونة */
        .rsv-media { display: flex; align-items: center; gap: var(--rsv-3); min-width: 0; }
        .rsv-media__thumb {
            flex: none; width: 40px; height: 40px;
            border-radius: var(--rsv-r); object-fit: cover;
            border: 1px solid var(--rsv-line); background: var(--app-body-bg, #f8f8f8);
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--app-muted, #6e6b7b); font-size: .95rem;
        }
        .rsv-media__body { min-width: 0; }
        .rsv-media__title { font-weight: 600; color: var(--app-text, #4b4b4b); display: block; line-height: 1.35; }
        .rsv-media__sub {
            font-size: .75rem; color: var(--app-muted, #6e6b7b); display: block;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شارة الحالة ═══════════════
           النقطة إلى جانب اللون: اللون وحده لا يكفي مَن لا يميّزه. */

        .rsv-state {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .22rem .62rem; border-radius: 999px;
            font-size: .74rem; font-weight: 600; line-height: 1.4;
        }
        .rsv-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex: none; }
        .rsv-state--on  { color: var(--app-success, #28c76f); background: var(--app-success-soft, rgba(40, 199, 111, .12)); }
        .rsv-state--off { color: var(--app-muted, #6e6b7b);   background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); }
        .rsv-state--neutral { color: var(--app-info, #00cfe8); background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); }
        .rsv-state--neutral::before { display: none; }

        .rsv-tags { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }

        .rsv-scope .badge {
            display: inline-flex; align-items: center;
            padding: .28em .65em; border-radius: 999px;
            font-size: .73rem; font-weight: 600; letter-spacing: 0;
        }
        .rsv-scope .badge-primary, .rsv-scope .badge-light-primary { background: var(--app-primary-soft, rgba(115, 103, 240, .12)); color: var(--app-primary, #7367f0); }
        .rsv-scope .badge-success, .rsv-scope .badge-light-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .rsv-scope .badge-danger,  .rsv-scope .badge-light-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));  color: var(--app-danger, #ea5455); }
        .rsv-scope .badge-info,    .rsv-scope .badge-light-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent); color: var(--app-info, #00cfe8); }
        .rsv-scope .badge-warning, .rsv-scope .badge-light-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }
        .rsv-scope .badge-secondary, .rsv-scope .badge-light {
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 12%, transparent); color: var(--app-muted, #6e6b7b);
        }

        /* ═══════════════ ذيل الجدول والترقيم ═══════════════ */

        .rsv-table-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-4); flex-wrap: wrap;
            padding: var(--rsv-3) var(--rsv-5);
            border-top: 1px solid var(--rsv-line-soft);
            background: var(--rsv-tint);
        }
        .rsv-table-foot__count {
            font-size: .8rem; color: var(--app-muted, #6e6b7b); margin: 0;
            font-variant-numeric: tabular-nums;
        }

        .rsv-scope .pagination { gap: .3rem; margin: 0; flex-wrap: wrap; }

        .rsv-scope .pagination .page-item .page-link,
        .rsv-scope .pagination .page-link {
            min-width: 34px; height: 34px;
            display: inline-flex; align-items: center; justify-content: center;
            margin: 0; padding: 0 .55rem;
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r) !important;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .82rem; font-weight: 600; font-variant-numeric: tabular-nums;
            box-shadow: none;
            transition: background .14s ease, border-color .14s ease, color .14s ease;
        }

        .rsv-scope .pagination .page-item .page-link:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 35%, transparent);
            color: var(--app-primary, #7367f0);
        }

        .rsv-scope .pagination .page-item.active .page-link {
            background: var(--app-primary, #7367f0); border-color: var(--app-primary, #7367f0);
            color: var(--app-on-primary, #fff);
            box-shadow: 0 3px 10px var(--app-primary-glow, rgba(115, 103, 240, .3));
        }

        .rsv-scope .pagination .page-item.disabled .page-link {
            background: var(--app-surface, #fff); color: var(--app-muted, #6e6b7b); opacity: .5;
        }

        /* مُنتقي عدد السجلّات: عنصر واحد مضموم، لا ثلاثة عناصر متجاورة */
        .rsv-perpage {
            display: inline-flex; align-items: stretch;
            border: 1px solid var(--rsv-line);
            border-radius: 999px;
            background: var(--app-surface, #fff);
            overflow: hidden;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .rsv-perpage:focus-within {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .rsv-perpage label,
        .rsv-perpage__unit {
            display: inline-flex; align-items: center;
            margin: 0; padding: .32rem .8rem;
            font-size: .78rem; font-weight: 600; color: var(--app-muted, #6e6b7b);
            background: var(--rsv-tint);
            white-space: nowrap;
        }
        .rsv-perpage__select {
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
        .rsv-perpage__select:focus { background-color: var(--rsv-tint); }

        /* ═══════════════ النماذج ═══════════════ */

        .rsv-scope .form-group { margin-bottom: var(--rsv-4); }

        /* `:not(.rsv-switch)` مقصود: مفتاح التبديل وسمُه `<label>` أيضاً،
           وهذه القاعدة أعلى تخصيصاً (0,1,1) من `.rsv-switch` (0,1,0) فكانت
           تفرض عليه `display:block` — فيسقط النصّ تحت المسار بدل أن يقف
           بجانبه، وتنفصل الأسطر بـ`margin-bottom`. */
        .rsv-scope label:not(.rsv-switch),
        .rsv-scope .form-group > label:not(.rsv-switch) {
            display: block;
            font-size: .82rem; font-weight: 600; color: var(--app-text, #4b4b4b);
            margin-bottom: .4rem;
        }

        .rsv-scope .form-control, .rsv-scope .custom-select, .rsv-scope .form-select,
        .rsv-scope .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 0 var(--rsv-3);
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r);
            color: var(--app-text, #4b4b4b);
            font-size: .875rem;
            box-shadow: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .rsv-scope textarea.form-control { height: auto; padding: var(--rsv-3); min-height: 90px; }

        .rsv-scope .form-control:focus, .rsv-scope .custom-select:focus, .rsv-scope .form-select:focus,
        .rsv-scope .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
            outline: 0;
        }

        .rsv-scope .form-control::placeholder { color: var(--app-muted, #6e6b7b); opacity: .7; }
        .rsv-scope .form-control.is-invalid { border-color: var(--app-danger, #ea5455); }
        .rsv-scope .invalid-feedback { font-size: .76rem; color: var(--app-danger, #ea5455); margin-top: .3rem; }

        .rsv-scope .input-group-text {
            background: var(--rsv-tint); border: 1px solid var(--rsv-line);
            color: var(--app-muted, #6e6b7b); border-radius: var(--rsv-r);
        }

        .rsv-scope .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px; color: var(--app-text, #4b4b4b); padding: 0;
        }
        .rsv-scope .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }

        /* أقسام النموذج */
        .rsv-section { margin-bottom: var(--rsv-5); }
        .rsv-section:last-child { margin-bottom: 0; }
        .rsv-section__head {
            display: flex; align-items: center; gap: var(--rsv-2);
            padding-bottom: var(--rsv-2); margin-bottom: var(--rsv-4);
            border-bottom: 1px solid var(--rsv-line-soft);
        }
        .rsv-section__head i { color: var(--app-primary, #7367f0); font-size: .9rem; }
        .rsv-section__title { font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0; }

        .rsv-req::after { content: ' *'; color: var(--app-danger, #ea5455); font-weight: 700; }
        /* ═══════ قائمةٌ بجانبها زرُّ إضافة ═══════

           العميل غير مسجَّل؟ زرٌّ يفتح شاشة إضافته في تبويبٍ جديد بدل إجبار
           المستخدم على مغادرة النموذج الذي يملؤه وضياع ما أدخله. منقولةٌ عن
           `po-select-add` في وحدة المشتريات، بالبادئة `rsv-`. */
        .rsv-select-add { display: flex; align-items: stretch; gap: .4rem; }
        .rsv-select-add > select,
        .rsv-select-add > .select2 { flex: 1 1 auto; min-width: 0; }
        .rsv-select-add__btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.35rem; padding: 0;
        }
        /* قائمةٌ متعدّدةٌ تطول مع كلّ اختيار — الزرُّ يبقى بطول سطرٍ واحد في أعلاها */
        .rsv-select-add--top { align-items: flex-start; }
        .rsv-select-add--top .rsv-select-add__btn { height: 2.35rem; }

        .rsv-help { display: block; font-size: .75rem; color: var(--app-muted, #6e6b7b); margin-top: .3rem; }

        /* ذيل النموذج: الشرح في جهة والأزرار في الأخرى. حين يخلو من الشرح
           تنزلق الأزرار إلى النهاية وحدها بفضل `space-between`، فلا حاجة
           إلى صنف ثانٍ لحالتين من الشيء الواحد. */
        .rsv-form-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-3); flex-wrap: wrap;
            padding: var(--rsv-4) var(--rsv-5);
            border-top: 1px solid var(--rsv-line-soft);
            background: var(--rsv-tint);
        }
        .rsv-form-foot > :only-child { margin-inline-start: auto; }

        /* مستقلاًّ عن بطاقة — يحتاج حافّته واستدارته وظلّه بنفسه */
        .rsv-scope > .rsv-form-foot,
        .rsv-scope form > .rsv-form-foot {
            border: 1px solid var(--rsv-line); border-radius: var(--rsv-r-lg);
            box-shadow: var(--rsv-e2); margin-bottom: var(--rsv-5);
        }

        /* ═══════════════ الفلاتر ═══════════════ */

        .rsv-filters .card-body { padding: var(--rsv-5); }
        .rsv-filters__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-3); flex-wrap: wrap; margin-bottom: var(--rsv-4);
        }
        .rsv-filters__title {
            display: flex; align-items: center; gap: var(--rsv-2);
            font-weight: 700; font-size: .9rem; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .rsv-filters__title i { color: var(--app-primary, #7367f0); }
        .rsv-filters__foot {
            display: flex; gap: var(--rsv-2); justify-content: flex-end;
            padding-top: var(--rsv-4); margin-top: var(--rsv-1);
            border-top: 1px dashed var(--rsv-line);
        }

        /* ═══════════════ بلاطات الإعدادات ═══════════════ */

        .rsv-tiles { display: grid; gap: var(--rsv-4); grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }

        .rsv-tile {
            display: flex; align-items: flex-start; gap: var(--rsv-3);
            padding: var(--rsv-5);
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r-lg);
            box-shadow: var(--rsv-e2);
            color: inherit; text-decoration: none;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            position: relative; overflow: hidden;
        }
        .rsv-tile::before {
            content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
            width: 3px; background: var(--app-primary, #7367f0);
            transform: scaleY(0); transform-origin: top; transition: transform .2s ease;
        }
        .rsv-tile:hover {
            transform: translateY(-2px); box-shadow: var(--rsv-e4);
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 28%, var(--rsv-line));
            color: inherit; text-decoration: none;
        }
        .rsv-tile:hover::before { transform: scaleY(1); }
        .rsv-tile:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .rsv-tile__icon {
            flex: none; width: 46px; height: 46px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--rsv-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.2rem;
        }
        .rsv-tile__body { min-width: 0; }
        .rsv-tile__title { font-weight: 700; font-size: .95rem; color: var(--app-text, #4b4b4b); margin: 0 0 .2rem; }
        .rsv-tile__hint  { font-size: .78rem; color: var(--app-muted, #6e6b7b); margin: 0; line-height: 1.6; }

        .rsv-tile__kids { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .55rem; }
        .rsv-tile__kid {
            font-size: .72rem; font-weight: 600;
            padding: .12rem .5rem; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 10%, transparent);
            color: var(--app-muted, #6e6b7b); text-decoration: none;
        }
        .rsv-tile__kid:hover {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

        .rsv-group { margin-bottom: var(--rsv-5); }
        .rsv-group__head {
            display: flex; align-items: center; gap: var(--rsv-2);
            margin-bottom: var(--rsv-3);
        }
        .rsv-group__head i { color: var(--app-primary, #7367f0); }
        .rsv-group__title { font-size: .95rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0; }

        /* ═══════════════ مفتاح التبديل ═══════════════ */

        /* الوسم `label` في المُنتقي مقصود لا زائد: يرفع التخصيص إلى (0,1,1)
           فيغلب أيَّ قاعدةٍ عامّة على `label` داخل `.rsv-scope`، ويبقى
           المفتاح صفّاً واحداً: مسارٌ ثم نصّه بجانبه. */
        .rsv-scope label.rsv-switch,
        .rsv-switch {
            display: flex; align-items: flex-start; gap: var(--rsv-3);
            margin: 0; cursor: pointer; user-select: none;
            font-size: .87rem; font-weight: 600;
        }
        .rsv-switch > input { position: absolute; opacity: 0; width: 0; height: 0; }

        .rsv-switch__track {
            flex: none; position: relative;
            width: 42px; height: 24px; border-radius: 999px;
            background: color-mix(in srgb, var(--app-muted, #6e6b7b) 28%, transparent);
            transition: background .18s ease;
            margin-top: 2px;
        }
        .rsv-switch__thumb {
            position: absolute; top: 3px; inset-inline-start: 3px;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--app-surface, #fff);
            box-shadow: var(--rsv-e2);
            transition: transform .18s ease;
        }
        .rsv-switch > input:checked + .rsv-switch__track { background: var(--app-primary, #7367f0); }
        .rsv-switch > input:checked + .rsv-switch__track .rsv-switch__thumb { transform: translateX(-18px); }
        html[dir="ltr"] .rsv-switch > input:checked + .rsv-switch__track .rsv-switch__thumb { transform: translateX(18px); }
        .rsv-switch > input:focus-visible + .rsv-switch__track { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }
        .rsv-switch > input:disabled + .rsv-switch__track { opacity: .5; cursor: not-allowed; }

        /* مفتاحٌ بلا نصّ — في خليّة جدول لا في صفّ إعداد. العنوان يحمله
           رأسُ العمود، فتكراره في كل صفّ حشو. `aria-label` على المدخل
           يبقى واجباً: قارئ الشاشة لا يقرأ رأس العمود مع كل خليّة. */
        .rsv-scope label.rsv-switch--bare,
        .rsv-switch--bare { display: inline-flex; align-items: center; justify-content: center; }
        .rsv-switch--bare .rsv-switch__track { margin-top: 0; }

        .rsv-switch__label { display: flex; flex-direction: column; gap: .1rem; }
        .rsv-switch__title { font-size: .87rem; font-weight: 600; color: var(--app-text, #4b4b4b); }
        .rsv-switch__hint  { font-size: .75rem; color: var(--app-muted, #6e6b7b); line-height: 1.5; }

        .rsv-switch-row {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: var(--rsv-4);
            padding: var(--rsv-3) 0;
            border-bottom: 1px solid var(--rsv-line-soft);
        }
        .rsv-switch-row:last-child { border-bottom: 0; }

        /* ═══════════════ الحالة الفارغة ═══════════════ */

        .rsv-empty { text-align: center; padding: var(--rsv-6) var(--rsv-5); color: var(--app-muted, #6e6b7b); }
        .rsv-empty__icon {
            width: 60px; height: 60px; margin: 0 auto var(--rsv-4);
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: 1.6rem;
        }
        .rsv-empty__title { color: var(--app-text, #4b4b4b); font-weight: 700; font-size: 1rem; margin-bottom: .35rem; }
        .rsv-empty__hint  { font-size: .85rem; margin: 0 0 var(--rsv-4); }

        /* ═══════════════ التنبيهات ═══════════════ */

        .rsv-scope .alert {
            border: 1px solid transparent; border-radius: var(--rsv-r);
            padding: var(--rsv-3) var(--rsv-4); font-size: .85rem;
        }
        .rsv-scope .alert-success { background: var(--app-success-soft, rgba(40, 199, 111, .12)); border-color: color-mix(in srgb, var(--app-success, #28c76f) 30%, transparent); color: var(--app-success, #28c76f); }
        .rsv-scope .alert-danger  { background: var(--app-danger-soft, rgba(234, 84, 85, .12));   border-color: color-mix(in srgb, var(--app-danger, #ea5455) 30%, transparent);  color: var(--app-danger, #ea5455); }
        .rsv-scope .alert-warning { background: color-mix(in srgb, var(--app-warning, #ff9f43) 12%, transparent); border-color: color-mix(in srgb, var(--app-warning, #ff9f43) 30%, transparent); color: var(--app-warning, #ff9f43); }
        .rsv-scope .alert-info    { background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);    border-color: color-mix(in srgb, var(--app-info, #00cfe8) 30%, transparent);    color: var(--app-info, #00cfe8); }

        /* ═══════════════ تبويبات ═══════════════

           رابطٌ لكل تبويب لا زرّ: التبويب هنا صفحةٌ بعنوانها الخاصّ
           (`?type=collections`)، فيُفتح في تبويب جديد ويُحفظ في المفضّلة.
           الخطّ السفلي يتبع اللون الأساسي للمظهر. */

        .rsv-tabs {
            display: flex; gap: var(--rsv-1); flex-wrap: wrap;
            border-bottom: 2px solid var(--rsv-line);
            margin-bottom: var(--rsv-5);
        }
        .rsv-tab {
            display: inline-flex; align-items: center; gap: var(--rsv-2);
            padding: var(--rsv-3) var(--rsv-4);
            margin-bottom: -2px;
            border-bottom: 3px solid transparent;
            color: var(--app-muted, #6e6b7b);
            font-size: .9rem; font-weight: 600; text-decoration: none;
            transition: color .14s ease, border-color .14s ease;
        }
        .rsv-tab:hover { color: var(--app-primary, #7367f0); text-decoration: none; }
        .rsv-tab.is-active { color: var(--app-primary, #7367f0); border-bottom-color: var(--app-primary, #7367f0); }
        .rsv-tab:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: -2px; }

        /* ═══════════════ شريط سياق إعدادات النظام ═══════════════

           بندٌ واحد لكل إعداد عامّ تعمل به الشاشة: اسمه، وقيمته الحالية،
           ورابطٌ إلى الشاشة التي تضبطه. الشبكة تنساب بـ`auto-fill` فلا
           يبقى بندٌ وحيدٌ في سطر على الشاشات المتوسّطة.

           البند كلّه رابط، والقيمة `<span>` داخله: لا رابط داخل رابط. */

        .rsv-ctx { margin-bottom: var(--rsv-5); }

        .rsv-ctx__head {
            display: flex; align-items: baseline; gap: var(--rsv-2);
            flex-wrap: wrap; margin-bottom: var(--rsv-3);
        }
        .rsv-ctx__title {
            display: flex; align-items: center; gap: var(--rsv-2);
            font-size: .88rem; font-weight: 700; color: var(--app-text, #4b4b4b); margin: 0;
        }
        .rsv-ctx__title i { color: var(--app-primary, #7367f0); }
        .rsv-ctx__hint { font-size: .76rem; color: var(--app-muted, #6e6b7b); margin: 0; }

        .rsv-ctx__grid {
            display: grid; gap: var(--rsv-2);
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        }

        .rsv-ctx__item {
            display: flex; align-items: center; gap: var(--rsv-3);
            padding: var(--rsv-2) var(--rsv-3);
            border: 1px solid var(--rsv-line-soft); border-radius: var(--rsv-r);
            background: var(--app-surface, #fff);
            color: inherit; text-decoration: none;
            transition: border-color .14s ease, box-shadow .14s ease, transform .14s ease;
        }
        a.rsv-ctx__item:hover {
            border-color: color-mix(in srgb, var(--app-primary, #7367f0) 40%, transparent);
            box-shadow: var(--rsv-e2); transform: translateY(-1px);
            color: inherit; text-decoration: none;
        }
        a.rsv-ctx__item:focus-visible { outline: 2px solid var(--app-primary, #7367f0); outline-offset: 2px; }

        .rsv-ctx__icon {
            width: 32px; height: 32px; flex: none;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--rsv-r); font-size: .9rem;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }
        .rsv-ctx__item--on  .rsv-ctx__icon { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--app-success, #28c76f); }
        .rsv-ctx__item--off .rsv-ctx__icon { background: color-mix(in srgb, var(--app-warning, #ff9f43) 14%, transparent); color: var(--app-warning, #ff9f43); }

        .rsv-ctx__body { min-width: 0; }
        .rsv-ctx__label { display: block; font-size: .72rem; color: var(--app-muted, #6e6b7b); line-height: 1.4; }
        .rsv-ctx__value {
            display: block; font-size: .82rem; font-weight: 600;
            color: var(--app-text, #4b4b4b); line-height: 1.4;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ شريط البحث في اللوحة ═══════════════ */

        .rsv-hubsearch { position: relative; max-width: 420px; margin-bottom: var(--rsv-5); }
        .rsv-hubsearch input {
            width: 100%; height: 42px;
            padding-inline-start: 2.4rem; padding-inline-end: var(--rsv-3);
            border: 1px solid var(--rsv-line); border-radius: 999px;
            background: var(--app-surface, #fff); color: var(--app-text, #4b4b4b);
            font-size: .88rem;
        }
        .rsv-hubsearch input:focus {
            outline: 0; border-color: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .rsv-hubsearch i {
            position: absolute; inset-inline-start: .9rem; top: 50%; transform: translateY(-50%);
            color: var(--app-muted, #6e6b7b); font-size: .95rem; pointer-events: none;
        }

        /* ═══════════════ الطباعة ═══════════════ */

        @media print {
            .rsv-page-head__actions, .rsv-filters, .rsv-table-foot, .rsv-perpage, .rsv-ctx { display: none !important; }
            .rsv-scope .card { box-shadow: none; border-color: #ddd; }
        }

        /* ═══════════════ الشاشات الصغيرة ═══════════════ */

        @media (max-width: 575.98px) {
            .rsv-page-head { align-items: flex-start; }
            .rsv-page-head__actions { width: 100%; }
            .rsv-page-head__actions .btn { flex: 1 1 auto; }
            .rsv-scope .table thead th, .rsv-scope .table tbody td { padding-inline: var(--rsv-3); }
        }

        /* ═══════════════ لوحة الإرشادات ═══════════════

           شاشات الإعدادات تعرض مفاتيح تبديلٍ بعناوين مختصرة، ولا موضع
           فيها لشرح ما يفعله القسم كلُّه ولا لعلاقته بإعدادٍ آخر. هذه
           اللوحة موضعُ ذلك: `<details>` أصلاً — تُطوى بلا سطر سكربت،
           وتُفتح بالوسم `open` أوّل مرّة. */

        .rsv-guide {
            background: var(--app-primary-soft, rgba(115, 103, 240, .08));
            border: 1px solid var(--rsv-line);
            border-inline-start: 3px solid var(--app-primary, #7367f0);
            border-radius: var(--rsv-r-lg);
            margin-bottom: var(--rsv-4);
        }

        .rsv-guide > summary {
            display: flex; align-items: center; gap: .5rem;
            padding: var(--rsv-3) var(--rsv-5);
            font-weight: 700; font-size: .9rem;
            color: var(--app-text, #4b4b4b);
            cursor: pointer;
            list-style: none;
        }

        /* المثلّث الافتراضي يختلف بين المتصفّحات ولا ينقلب مع `dir` */
        .rsv-guide > summary::-webkit-details-marker { display: none; }
        .rsv-guide > summary::marker { content: ''; }

        .rsv-guide > summary::after {
            content: '';
            inline-size: .5rem; block-size: .5rem;
            margin-inline-start: auto;
            border-inline-end: 2px solid var(--app-muted, #6e6b7b);
            border-block-end: 2px solid var(--app-muted, #6e6b7b);
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .rsv-guide[open] > summary::after { transform: rotate(-135deg); }

        .rsv-guide__body {
            padding: 0 var(--rsv-5) var(--rsv-4);
            font-size: .85rem; line-height: 1.9;
            color: var(--app-muted, #6e6b7b);
        }

        .rsv-guide__body h6 {
            font-size: .82rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
            margin: var(--rsv-3) 0 .2rem;
        }

        .rsv-guide__body p { margin: 0 0 .35rem; }

        /* سطر الحقيقة: ما هو مضبوطٌ الآن فعلاً، لا شرحاً نظرياً */
        .rsv-guide__fact {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r-sm, .35rem);
            padding: .3rem .6rem;
            font-weight: 600;
            color: var(--app-text, #4b4b4b);
        }

        /* شارة عدد المفاتيح في لسان التبويب */
        .rsv-scope .rsv-doc-tabs .badge { margin-inline-start: .35rem; }
    
        /* ═════════════════════════════════════════════════════════════
           إضافاتُ وحدة الحجوزات — ما لا نظير له في تصميم المبيعات
           ═════════════════════════════════════════════════════════════ */

        /* ─────────── البطاقة ─────────── */
        /* شاشاتُ الوحدة كُتبت على `rsv-card` قبل نقل التصميم. تُبنى الآن
           برموزه نفسِها (`--rsv-line`, `--rsv-r`, `--rsv-e2`) فلا تفترق
           عن `card` في الشاشات المنقولة. */
        .rsv-card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r-lg);
            box-shadow: var(--rsv-e2);
            margin-bottom: var(--rsv-5);
        }

        .rsv-card__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--rsv-3); flex-wrap: wrap;
            padding: var(--rsv-3) var(--rsv-4);
            border-bottom: 1px solid var(--rsv-line-soft);
        }

        .rsv-card__title {
            margin: 0; font-size: 1rem; font-weight: 700;
            display: flex; align-items: center; gap: var(--rsv-2);
        }

        .rsv-card__body { padding: var(--rsv-4); }

        /* ─────────── الجدول ─────────── */
        .rsv-table { width: 100%; margin: 0; }

        .rsv-table thead th {
            background: var(--rsv-tint);
            font-size: .82rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
            border-bottom: 1px solid var(--rsv-line) !important;
            white-space: nowrap;
            padding: var(--rsv-2) var(--rsv-3);
        }

        .rsv-table tbody td {
            padding: var(--rsv-2) var(--rsv-3);
            vertical-align: middle; font-size: .87rem;
            border-top: 1px solid var(--rsv-line-soft);
        }

        .rsv-table tbody tr:hover { background: var(--rsv-tint); }

        /* الجدولُ العريض يمرّر داخل حاويته لا في الصفحة كلّها — وإلّا انزلق
           الشريطُ الجانبي مع المحتوى في الشاشات الضيّقة */
        .rsv-scroll { overflow-x: auto; }

        /* ─────────── شارة الحالة ─────────── */
        /* لونُها يأتي من `ReservationStatus::color()` سِمةً سطرية، فلا
           يُعرَّف هنا لونٌ لكل حالة يفترق عن التعداد بعد أوّل تعديل */
        .rsv-badge {
            display: inline-block; padding: .28rem .6rem;
            border-radius: 999px; font-size: .74rem; font-weight: 700;
            color: #fff; white-space: nowrap;
        }

        .rsv-money { font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .rsv-muted { color: var(--app-text-muted, #6e6b7b); font-size: .8rem; }

        /* تلميحُ الحقل — تحت الحقل لا في عنوانه */
        .rsv-hint {
            display: block; font-size: .76rem;
            color: var(--app-text-muted, #6e6b7b);
            margin-top: var(--rsv-1);
        }

        /* ─────────── أيقونة التلميح (علامة تعجّب) ───────────
           بديلُ سطر الإرشاد تحت الحقل (طلب المستخدم 2026-09-20): أيقونةٌ
           بجوار العنوان تُظهر النصَّ فقاعةً عند المرور أو التركيز.
           CSS خالص — لا يعتمد على تهيئة Bootstrap Tooltip (غير مهيّأة
           في الوحدة أصلاً). `left:50%; translateX(-50%)` توسيطٌ يصحّ في
           الاتجاهين، والفقاعة فوق الأيقونة كي لا يقصّها أسفلُ البطاقة. */
        .rsv-tip {
            position: relative; display: inline-flex;
            align-items: center; justify-content: center;
            margin-inline-start: 6px; color: #f59e0b;
            font-size: .85em; cursor: help; outline: none; vertical-align: middle;
        }
        .rsv-tip::after {
            content: attr(data-tip);
            position: absolute; bottom: calc(100% + 9px); left: 50%;
            transform: translateX(-50%) translateY(3px);
            background: #1f2937; color: #fff;
            padding: 8px 12px; border-radius: 8px;
            font-family: inherit; font-size: .76rem; font-weight: 400; line-height: 1.7;
            width: max-content; max-width: 280px; white-space: normal; text-align: start;
            direction: rtl;
            opacity: 0; visibility: hidden; pointer-events: none;
            transition: opacity .15s ease, transform .15s ease;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .18); z-index: 60;
        }
        .rsv-tip::before {
            content: ''; position: absolute; bottom: calc(100% + 3px); left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent; border-top-color: #1f2937;
            opacity: 0; visibility: hidden; transition: opacity .15s ease; z-index: 60;
        }
        .rsv-tip:hover::after, .rsv-tip:focus::after,
        .rsv-tip:hover::before, .rsv-tip:focus::before {
            opacity: 1; visibility: visible; transform: translateX(-50%);
        }

        /* ─────────── الفترات المتاحة ─────────── */
        .rsv-slots {
            display: flex; flex-wrap: wrap; gap: var(--rsv-1);
            max-height: 190px; overflow-y: auto;
        }

        .rsv-slot {
            border: 1px solid var(--rsv-line);
            background: var(--app-surface, #fff);
            border-radius: var(--rsv-r-sm, 6px);
            padding: .3rem .65rem; font-size: .82rem;
            cursor: pointer; transition: all .15s;
        }

        .rsv-slot:hover { border-color: var(--app-primary, #7367f0); color: var(--app-primary, #7367f0); }

        .rsv-slot.is-active {
            background: var(--app-primary, #7367f0);
            border-color: var(--app-primary, #7367f0);
            color: #fff;
        }

        /* ─────────── خطّ حياة الحجز ─────────── */
        .rsv-timeline { list-style: none; padding: 0; margin: 0; }

        .rsv-timeline li {
            position: relative;
            padding-inline-start: var(--rsv-5);
            padding-bottom: var(--rsv-4);
            border-inline-start: 2px solid var(--rsv-line);
            margin-inline-start: var(--rsv-1);
        }

        .rsv-timeline li:last-child { border-inline-start-color: transparent; padding-bottom: 0; }

        .rsv-timeline li::before {
            content: ''; position: absolute;
            inset-inline-start: -6px; top: 3px;
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--app-primary, #7367f0);
        }

        /* ─────────── شجرة «تابع لـ» ─────────── */
        /* المُنتقي مشتركٌ مع التقارير ويأتي بأصنافه `rpt-*` — تُنسَّق هنا
           لتطابق حقولَ الوحدة، ولا يُعاد كتابتُه */
        .rsv-scope .rpt-field { margin-bottom: var(--rsv-2); }

        .rsv-scope .rpt-field > label {
            font-size: .82rem; font-weight: 600;
            margin-bottom: var(--rsv-1);
            color: var(--app-text, #4b4b4b);
        }

        .rsv-scope .rpt-tree-col { margin-bottom: var(--rsv-2); }
        .rsv-scope .rpt-tree-col label { font-size: .76rem; margin-bottom: .2rem; }
        .rsv-scope .rpt-tree-select { font-size: .87rem; }

        /* ─────────── select2 داخل الوحدة ─────────── */
        /* ارتفاعُ حاوية select2 لا يطابق `form-select` افتراضياً، فتختلف
           أطوالُ الحقول في الصفّ الواحد */
        .rsv-scope .select2-container--default .select2-selection--single {
            height: calc(1.5em + .75rem + 2px);
            border: 1px solid var(--rsv-line);
            border-radius: var(--rsv-r);
        }

        .rsv-scope .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + .75rem);
            padding-inline-start: var(--rsv-3);
        }

        .rsv-scope .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + .75rem);
        }

        /* ─────────── الطباعة ─────────── */
        @media print {
            .rsv-no-print, .main-menu, .header-navbar, .footer { display: none !important; }
            .rsv-card, .card { box-shadow: none; border-color: #ccc; }
        }
    </style>
@endonce
