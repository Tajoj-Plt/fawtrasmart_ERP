{{--
    أنماطُ لوحة الفروع — نسخةٌ من أنماط لوحة المبيعات
    (`sales::dashboard.partials.styles`) بأصنافها نفسِها (`sd-*`)، تحت
    `#branches-dashboard` بدل معرّف لوحة المبيعات.

    نسخةٌ لا استدعاء: وحدةٌ معطَّلة تُسقط قوالبها، فلوحةُ الفروع لا تتوقّف
    على تشغيل المبيعات. وأيُّ تعديلٍ في الأصل يُنقل إلى هنا بلصقٍ مباشر
    مع تبديل المعرّف وحدَه.

    وما يخصّ الفروع دون المبيعات في آخر الملف، تحت عنوانه.
--}}
    <style>
        #branches-dashboard {
            --sd-surface: var(--app-surface, #fff);
            --sd-border: var(--app-border, #e6e9f0);
            --sd-text: var(--app-text, #4b4b4b);
            --sd-muted: var(--app-muted, #7b8194);
            --sd-radius: var(--app-radius, 8px);
            --sd-primary: var(--app-primary, #7367f0);
            --sd-success: var(--app-success, #28c76f);
            --sd-danger: var(--app-danger, #ea5455);
            --sd-warning: var(--app-warning, #ff9f43);
            --sd-info: var(--app-info, #00cfe8);
            font-family: var(--app-font-family, 'Cairo', sans-serif);
        }

        #branches-dashboard .sd-toolbar {
            background: var(--sd-surface);
            border: 1px solid var(--sd-border);
            border-radius: calc(var(--sd-radius) + 4px);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        #branches-dashboard .sd-title {
            font-weight: 700; color: var(--sd-text); margin: 0 0 .2rem; font-size: 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        #branches-dashboard .sd-subtitle { color: var(--sd-muted); font-size: .8rem; margin: 0 0 .75rem; }

        #branches-dashboard .sd-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }
        #branches-dashboard .sd-tab {
            border: 1px solid var(--sd-border); background: transparent; color: var(--sd-muted);
            border-radius: var(--sd-radius); padding: .42rem .95rem; font-size: .85rem; font-weight: 600; cursor: pointer;
        }
        #branches-dashboard .sd-tab.active { background: var(--sd-primary); border-color: var(--sd-primary); color: #fff; }

        #branches-dashboard .sd-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: -.4rem 0 .85rem; font-size: .78rem; }
        #branches-dashboard .sd-crumbs a { color: var(--sd-primary); text-decoration: none; }
        #branches-dashboard .sd-crumbs a:hover { text-decoration: underline; }
        #branches-dashboard .sd-crumbs strong { color: var(--sd-text); }
        #branches-dashboard .sd-crumb-sep { color: var(--sd-muted); opacity: .6; }

        #branches-dashboard .sd-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        #branches-dashboard .sd-field { display: flex; flex-direction: column; gap: .25rem; }
        #branches-dashboard .sd-field > label { font-size: .74rem; color: var(--sd-muted); margin: 0; font-weight: 600; }
        #branches-dashboard .sd-field .form-control,
        #branches-dashboard .sd-field .custom-select,
        #branches-dashboard .sd-field select {
            height: var(--app-input-height, 38px); font-size: .84rem;
            border-radius: var(--sd-radius); border: 1px solid var(--sd-border);
            background: var(--sd-surface); color: var(--sd-text);
            padding: .25rem .6rem; min-width: 165px;
        }

        #branches-dashboard .sd-switch {
            display: inline-flex; align-items: center; gap: .4rem;
            height: var(--app-input-height, 38px); padding: 0 .75rem;
            border: 1px solid var(--sd-border); border-radius: var(--sd-radius);
            background: var(--sd-surface); font-size: .82rem; font-weight: 600;
            color: var(--sd-muted); cursor: pointer; margin: 0; white-space: nowrap;
        }
        #branches-dashboard .sd-switch input { accent-color: var(--sd-primary); width: 15px; height: 15px; cursor: pointer; }
        #branches-dashboard .sd-scope-count { font-style: normal; font-size: .72rem; font-weight: 700; color: var(--sd-primary); direction: ltr; }

        #branches-dashboard .sd-apply {
            height: var(--app-input-height, 38px); border-radius: var(--app-button-radius, 6px); border: none;
            background: var(--sd-primary); color: #fff; font-size: .84rem; font-weight: 600; padding: 0 1.1rem; cursor: pointer;
        }
        #branches-dashboard .sd-reset {
            height: var(--app-input-height, 38px); display: inline-flex; align-items: center; gap: .3rem;
            border-radius: var(--app-button-radius, 6px); border: 1px solid var(--sd-border);
            background: var(--sd-surface); color: var(--sd-muted); font-size: .84rem; padding: 0 .9rem; cursor: pointer; text-decoration: none;
        }

        #branches-dashboard .sd-notice {
            border-radius: var(--sd-radius); padding: .6rem .9rem; font-size: .82rem; margin-bottom: 1rem;
            border: 1px solid var(--sd-border); color: var(--sd-muted); background: var(--sd-surface);
        }
        #branches-dashboard .sd-notice.warning {
            border-color: color-mix(in srgb, var(--sd-warning) 45%, transparent);
            background: color-mix(in srgb, var(--sd-warning) 10%, transparent);
            color: var(--sd-warning);
        }
        #branches-dashboard .sd-notice.danger {
            border-color: color-mix(in srgb, var(--sd-danger) 45%, transparent);
            background: color-mix(in srgb, var(--sd-danger) 10%, transparent);
            color: var(--sd-danger);
        }
        #branches-dashboard .sd-notice.success {
            border-color: color-mix(in srgb, var(--sd-success) 45%, transparent);
            background: color-mix(in srgb, var(--sd-success) 10%, transparent);
            color: var(--sd-success);
        }

        /* ---------- المؤشرات ---------- */
        #branches-dashboard .sd-kpi {
            background: var(--sd-surface); border: 1px solid var(--sd-border);
            border-radius: calc(var(--sd-radius) + 4px); padding: var(--app-card-padding, 1rem);
            height: 100%; transition: transform .18s ease;
        }
        #branches-dashboard .sd-kpi:hover { transform: translateY(-3px); }
        #branches-dashboard .sd-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
        #branches-dashboard .sd-kpi-icon { width: 40px; height: 40px; border-radius: var(--sd-radius); display: flex; align-items: center; justify-content: center; }
        #branches-dashboard .sd-kpi-label { color: var(--sd-muted); font-size: .78rem; font-weight: 600; margin: 0 0 .25rem; }
        #branches-dashboard .sd-kpi-value { font-size: 1.3rem; font-weight: 700; color: var(--sd-text); margin: 0; direction: ltr; text-align: start; }
        #branches-dashboard .sd-kpi-value small { font-size: .72rem; color: var(--sd-muted); font-weight: 600; }
        #branches-dashboard .sd-kpi-foot { margin-top: .4rem; font-size: .74rem; color: var(--sd-muted); }

        #branches-dashboard .sd-delta {
            display: inline-flex; align-items: center; gap: .2rem; padding: .12rem .45rem;
            border-radius: 6px; font-weight: 700; font-size: .73rem; direction: ltr;
        }
        #branches-dashboard .sd-delta-up { background: color-mix(in srgb, var(--sd-success) 14%, transparent); color: var(--sd-success); }
        #branches-dashboard .sd-delta-down { background: color-mix(in srgb, var(--sd-danger) 14%, transparent); color: var(--sd-danger); }
        #branches-dashboard .sd-delta-flat { background: color-mix(in srgb, var(--sd-muted) 14%, transparent); color: var(--sd-muted); }

        /* ---------- البطاقات ---------- */
        #branches-dashboard .sd-card {
            background: var(--sd-surface); border: 1px solid var(--sd-border);
            border-radius: calc(var(--sd-radius) + 4px); margin-bottom: 1.25rem;
            height: calc(100% - 1.25rem); display: flex; flex-direction: column;
        }
        #branches-dashboard .sd-card-head { padding: 1rem 1.15rem .4rem; display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        #branches-dashboard .sd-card-head h4 { font-size: 1rem; font-weight: 700; color: var(--sd-text); margin: 0; display: flex; align-items: center; gap: .45rem; }
        #branches-dashboard .sd-card-head h4 i { color: var(--sd-primary); }
        #branches-dashboard .sd-card-head small { color: var(--sd-muted); font-size: .76rem; display: block; margin-top: .15rem; }
        #branches-dashboard .sd-card-body { padding: .4rem 1.15rem 1.1rem; flex: 1; }

        /* أيقونة «من أين تأتي هذه البيانات؟» — تُظهر/تُخفي .sd-info-panel أسفلها */
        #branches-dashboard .sd-info-toggle {
            background: none; border: none; padding: .2rem .35rem; line-height: 1;
            border-radius: 5px; color: var(--sd-muted); cursor: pointer; flex-shrink: 0;
        }
        #branches-dashboard .sd-info-toggle:hover { color: var(--sd-primary); background: color-mix(in srgb, var(--sd-primary) 10%, transparent); }
        #branches-dashboard .sd-info-panel {
            background: color-mix(in srgb, var(--sd-primary) 6%, transparent);
            border: 1px solid var(--sd-border); border-radius: var(--sd-radius);
            padding: .6rem .8rem; margin-bottom: .7rem; font-size: .78rem; line-height: 1.7; color: var(--sd-muted);
        }

        #branches-dashboard .sd-table { width: 100%; font-size: .84rem; margin: 0; color: var(--sd-text); }
        #branches-dashboard .sd-table thead th {
            background: color-mix(in srgb, var(--sd-primary) 5%, transparent);
            color: var(--sd-muted); font-weight: 700; font-size: .76rem; border: none; padding: .6rem .55rem; white-space: nowrap;
        }
        #branches-dashboard .sd-table tbody td { padding: .55rem .55rem; border-top: 1px solid var(--sd-border); vertical-align: middle; }
        #branches-dashboard .sd-num { direction: ltr; text-align: start; font-variant-numeric: tabular-nums; white-space: nowrap; }

        #branches-dashboard .sd-empty { text-align: center; color: var(--sd-muted); padding: 2rem 1rem; font-size: .86rem; }
        #branches-dashboard .sd-empty i { display: block; font-size: 1.8rem; margin-bottom: .5rem; opacity: .45; }

        #branches-dashboard .sd-badge { display: inline-block; padding: .16rem .5rem; border-radius: 6px; font-size: .72rem; font-weight: 700; }

        #branches-dashboard .sd-drill { color: var(--sd-primary); text-decoration: none; font-weight: 600; }
        #branches-dashboard .sd-drill:hover { text-decoration: underline; }

        #branches-dashboard .sd-bar { height: 6px; border-radius: 4px; background: color-mix(in srgb, var(--sd-muted) 15%, transparent); overflow: hidden; min-width: 60px; margin-top: .25rem; }
        #branches-dashboard .sd-bar span { display: block; height: 100%; border-radius: 4px; }

        /* جدول المندوبين: حاشيةٌ تحت الرقم، وخليّةُ الهدف بشريطها */
        #branches-dashboard .sd-rep-sub { display: block; font-size: .7rem; font-weight: 400; color: var(--sd-muted); white-space: nowrap; margin-top: .1rem; }
        #branches-dashboard .sd-rep-target { min-width: 155px; }

        /* خليّةُ «المحصَّل» — صندوقٌ بارزٌ كالمحفظة، فهو الرقمُ الأهمّ في البطاقة */
        #branches-dashboard .sd-wallet-box {
            display: inline-block; background: color-mix(in srgb, var(--sd-success) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--sd-success) 30%, transparent);
            border-radius: var(--sd-radius); padding: .35rem .7rem;
        }
        #branches-dashboard .sd-wallet-box b { color: var(--sd-success); }

        /* ---------- جدول «أداء المندوبين» — احترافيٌّ: صفوفٌ مخطّطة، تمريرٌ أفقيٌّ مضبوط داخل وعاءٍ مقوَّس ---------- */
        #branches-dashboard .sd-rep-table-wrap {
            background: var(--sd-surface); border: 1px solid var(--sd-border);
            border-radius: calc(var(--sd-radius) + 6px); overflow: hidden;
        }
        #branches-dashboard .sd-rep-table-wrap .table-responsive { margin: 0; }
        #branches-dashboard .sd-rep-table { font-size: .83rem; }
        #branches-dashboard .sd-rep-table thead th {
            background: color-mix(in srgb, var(--sd-primary) 6%, var(--sd-surface));
            box-shadow: inset 0 -1px 0 var(--sd-border);
            padding: .7rem .75rem;
        }
        #branches-dashboard .sd-rep-table thead th.is-sorted { color: var(--sd-primary); }
        #branches-dashboard .sd-rep-table tbody tr:nth-child(even) td {
            background: color-mix(in srgb, var(--sd-muted) 3.5%, transparent);
        }
        #branches-dashboard .sd-rep-table tbody tr:hover td {
            background: color-mix(in srgb, var(--sd-primary) 6%, transparent);
        }
        /* صفٌّ متفاوتُ الارتفاع (خليّةُ الزيارات أطولُ من البقية) يُحاذى من الأعلى لا الوسط —
           وحدَه هذا يمنع تكدُّس المحتوى القصير في منتصف صفٍّ طويل، فيبدو كلُّ شيءٍ مرتَّباً لا متداخلاً */
        #branches-dashboard .sd-rep-table tbody td { padding: .9rem .75rem; vertical-align: top; }
        #branches-dashboard .sd-rep-table td.is-sorted { background: color-mix(in srgb, var(--sd-primary) 5%, transparent); }
        #branches-dashboard .sd-rep-table tfoot td {
            font-weight: 700; border-top: 2px solid var(--sd-border); padding: .8rem .75rem; vertical-align: top;
            background: color-mix(in srgb, var(--sd-primary) 5%, transparent);
        }
        #branches-dashboard .sd-rep-name { font-weight: 700; color: var(--sd-text); cursor: pointer; white-space: nowrap; }
        #branches-dashboard .sd-rep-name:hover { color: var(--sd-primary); text-decoration: underline; }
        #branches-dashboard .sd-rep-table .sd-rank { width: 44px; text-align: center; }
        /* الرقمُ الرئيسُ في خليّته وحاشيتُه تحته بمسافةٍ واضحة — لا يلتصقان */
        #branches-dashboard .sd-rep-table td > .sd-num,
        #branches-dashboard .sd-rep-table td > .sd-wallet-box { display: inline-block; margin-bottom: .2rem; }
        #branches-dashboard .sd-rep-table .sd-rep-sub { margin-top: .25rem; }
        #branches-dashboard .sd-rep-table .sd-rep-target { padding-top: .1rem; }
        #branches-dashboard .sd-rep-table .sd-rep-target .sd-bar { margin: .35rem 0; }

        #branches-dashboard .sd-recon {
            display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; padding: .85rem 1.15rem;
            background: color-mix(in srgb, var(--sd-primary) 4%, transparent);
            border-top: 1px solid var(--sd-border);
            border-radius: 0 0 calc(var(--sd-radius) + 4px) calc(var(--sd-radius) + 4px);
            font-size: .82rem; color: var(--sd-muted);
        }
        #branches-dashboard .sd-recon b { color: var(--sd-text); direction: ltr; display: inline-block; }

        /* حاوية الرسم LTR والصفحة RTL: ApexCharts يحسب مواضع نصوص المحاور على
           افتراض LTR، و`dir="rtl"` الموروث يقلب مرساة النص فتُرسم الأسماء فوق
           الأعمدة. والأسماء تبقى يميناً بـ`opposite`، والعربية تُعرض سليمةً داخل
           الحاوية لأن اتجاه النص يُحسم بحروفه لا بالحاوية. */
        #branches-dashboard [id^="sd"][id$="Chart"] { direction: ltr; }

        /* ---------- الشجرة: الآباء والأبناء ---------- */
        #branches-dashboard .sd-tree-actions { display: flex; align-items: center; gap: .35rem; flex-shrink: 0; }
        #branches-dashboard .sd-tree-all {
            border: 1px solid var(--sd-border); background: transparent; color: var(--sd-muted);
            border-radius: var(--sd-radius); padding: .2rem .6rem; font-size: .74rem; font-weight: 600; cursor: pointer;
        }
        #branches-dashboard .sd-tree-all:hover { border-color: var(--sd-primary); color: var(--sd-primary); }
        #branches-dashboard .sd-tree-root { font-weight: 600; color: var(--sd-muted); font-size: .85rem; }
        #branches-dashboard .sd-tree-name { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
        #branches-dashboard .sd-tree-caret {
            background: none; border: none; padding: 0; width: 20px; height: 20px; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--sd-muted); cursor: pointer; border-radius: 4px;
        }
        #branches-dashboard .sd-tree-caret:hover { color: var(--sd-primary); background: color-mix(in srgb, var(--sd-primary) 10%, transparent); }
        #branches-dashboard .sd-tree-caret i { transition: transform .15s ease; }
        #branches-dashboard .sd-tree-caret[aria-expanded="true"] i { transform: rotate(-90deg); }
        html[dir="ltr"] #branches-dashboard .sd-tree-caret[aria-expanded="false"] i { transform: rotate(180deg); }
        #branches-dashboard .sd-tree-leaf { width: 20px; flex-shrink: 0; display: inline-block; }
        #branches-dashboard .sd-tree tr.sd-tree-d0 td { background: color-mix(in srgb, var(--sd-primary) 4%, transparent); font-weight: 700; }
        #branches-dashboard .sd-tree tr.sd-tree-d2 td,
        #branches-dashboard .sd-tree tr.sd-tree-d3 td { font-size: .8rem; }
        #branches-dashboard .sd-tree-own { display: block; color: var(--sd-muted); font-size: .72rem; font-weight: 400; padding-inline-start: 1.6rem; }
        #branches-dashboard .sd-node-type {
            display: inline-block; padding: 0 .35rem; border-radius: .25rem; font-size: .68rem; line-height: 1.7; font-weight: 600;
            background: color-mix(in srgb, var(--sd-muted) 12%, transparent); color: var(--sd-muted);
        }
        #branches-dashboard .sd-tree-chart-title { font-size: .8rem; color: var(--sd-muted); font-weight: 700; margin: .2rem 0 .4rem; }

        /* ---------- كل رقمٍ يفتح ما وراءه — بنمط لوحة العملاء ---------- */
        #branches-dashboard [data-segment] { cursor: pointer; }
        #branches-dashboard [data-segment]:focus-visible { outline: 2px solid var(--sd-primary); outline-offset: 2px; }
        #branches-dashboard .sd-kpi[data-segment]:hover { border-color: var(--sd-primary); }
        #branches-dashboard .sd-kpi-open { font-size: .72rem; opacity: 0; transition: opacity .15s; }
        #branches-dashboard .sd-kpi[data-segment]:hover .sd-kpi-open { opacity: 1; color: var(--sd-primary); }
        #branches-dashboard .sd-table tbody tr[data-segment]:hover td,
        #branches-dashboard .sd-table tbody td[data-segment]:hover { background: color-mix(in srgb, var(--sd-primary) 6%, transparent); }
        /* خانةُ الزيارات: سطرٌ للزيارات وسطرٌ لخطّ السير، كلُّ رقمٍ بعنوانه */
        #branches-dashboard .sd-visits { min-width: 150px; }
        #branches-dashboard .sd-visit-line { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: .25rem 0; }
        #branches-dashboard .sd-visit-line:first-child { margin-top: 0; }
        #branches-dashboard .sd-visit-title { font-size: .72rem; color: var(--sd-muted); font-weight: 700; min-width: 48px; }
        #branches-dashboard .sd-pill {
            display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .5rem; border-radius: 6px;
            font-size: .7rem; white-space: nowrap; border: 1px solid transparent;
            background: color-mix(in srgb, var(--sd-muted) 12%, transparent); color: var(--sd-muted);
        }
        #branches-dashboard .sd-pill.is-total { background: color-mix(in srgb, var(--sd-text) 8%, transparent); color: var(--sd-text); }
        #branches-dashboard .sd-pill.is-success { background: color-mix(in srgb, var(--sd-success) 14%, transparent); color: var(--sd-success); }
        #branches-dashboard .sd-pill.is-danger { background: color-mix(in srgb, var(--sd-danger) 14%, transparent); color: var(--sd-danger); }
        #branches-dashboard .sd-pill.is-primary { background: color-mix(in srgb, var(--sd-primary) 14%, transparent); color: var(--sd-primary); }
        #branches-dashboard .sd-pill[data-segment]:hover { border-color: currentColor; }

        #branches-dashboard .sd-kpi-extra { color: var(--sd-warning); font-weight: 600; }
        #branches-dashboard .sd-kpi-extra:hover,
        #branches-dashboard .sd-recon [data-segment]:hover b,
        #branches-dashboard .sd-tree-own[data-segment]:hover,
        #branches-dashboard .sd-rep-link[data-segment]:hover { color: var(--sd-primary); text-decoration: underline; }

        #branches-dashboard .sd-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 1990; }
        #branches-dashboard .sd-drawer {
            position: fixed; top: 0; bottom: 0; inset-inline-end: 0; width: min(1080px, 96vw);
            background: var(--sd-surface); z-index: 1991; display: flex; flex-direction: column;
            box-shadow: 0 0 40px rgba(15, 23, 42, .25);
        }
        #branches-dashboard .sd-drawer-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
            padding: 1rem 1.25rem .6rem; border-bottom: 1px solid var(--sd-border);
        }
        #branches-dashboard .sd-drawer-head h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--sd-text); }
        #branches-dashboard .sd-drawer-head p { margin: .25rem 0 0; font-size: .8rem; color: var(--sd-muted); line-height: 1.6; }
        #branches-dashboard .sd-drawer-close {
            border: 1px solid var(--sd-border); background: transparent; color: var(--sd-muted);
            width: 34px; height: 34px; border-radius: 50%; font-size: 1.2rem; line-height: 1; cursor: pointer; flex-shrink: 0;
        }
        #branches-dashboard .sd-drawer-tools {
            display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .7rem 1.25rem;
            border-bottom: 1px solid var(--sd-border);
        }
        #branches-dashboard .sd-drawer-tools input[type="search"] {
            flex: 1; min-width: 200px; height: 38px; border: 1px solid var(--sd-border);
            border-radius: var(--sd-radius); padding: .25rem .7rem; font-size: .85rem; background: var(--sd-surface); color: var(--sd-text);
        }
        #branches-dashboard .sd-drawer-count { font-size: .82rem; font-weight: 700; color: var(--sd-primary); white-space: nowrap; }
        #branches-dashboard .sd-drawer-body { flex: 1; overflow: auto; padding: 0 1.25rem 1rem; }
        #branches-dashboard .sd-drawer-body .sd-table thead th { position: sticky; top: 0; background: var(--sd-surface); box-shadow: inset 0 -1px 0 var(--sd-border); z-index: 1; }
        #branches-dashboard .sd-drawer-body .sd-table tfoot td { font-weight: 700; border-top: 2px solid var(--sd-border); padding: .6rem .55rem; background: color-mix(in srgb, var(--sd-primary) 5%, transparent); }
        #branches-dashboard .sd-drawer-body .sd-table a { color: var(--sd-primary); font-weight: 600; text-decoration: none; }
        #branches-dashboard .sd-drawer-body .sd-table a:hover { text-decoration: underline; }
        #branches-dashboard .sd-long { min-width: 180px; max-width: 340px; white-space: normal; line-height: 1.6; }
        #branches-dashboard .sd-drawer-more { display: block; margin: .8rem auto 0; }
        /* ═══════════════ فروع: ما ليس في لوحة المبيعات ═══════════════ */

        /* رأسُ شريط الفلترة: العنوانُ في جهةٍ وأزرارُ الشاشة في الأخرى.
           لوحةُ المبيعات بلا أزرار، وهذه لها ثلاثة (الهيكل، التصدير، عنصر جديد). */
        #branches-dashboard .sd-toolbar-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: .75rem 1rem; flex-wrap: wrap;
        }
        #branches-dashboard .sd-toolbar-head .sd-subtitle { max-width: 78ch; line-height: 1.7; }
        #branches-dashboard .sd-toolbar-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
        #branches-dashboard #bxd-scope-form .sd-filters { margin-top: .25rem; }

        /* تسميةُ مُنتقي الجهة بقياس تسميات الحقول في الشريط */
        #branches-dashboard .sd-toolbar .rpt-field > label { font-size: .74rem; color: var(--sd-muted); font-weight: 600; }
        #branches-dashboard .sd-toolbar .rpt-field select,
        #branches-dashboard .sd-toolbar .rpt-field .form-control {
            height: var(--app-input-height, 38px); font-size: .84rem;
            border-radius: var(--sd-radius); border: 1px solid var(--sd-border);
        }

        /* مؤشّرٌ هو رابطٌ: يبقى بهيئة البطاقة لا بهيئة الروابط */
        #branches-dashboard a.sd-kpi { display: block; text-decoration: none; color: inherit; }

        /* تنبيهُ السلامة: عنوانٌ ثمّ قائمة، والنصُّ بلون النصّ لا بلون الحالة.
           و`!important` لازمة: ورقةُ ألوان القالب تلوّن كلَّ ما صنفُه `warning`
           بها، فتخرج الفقرةُ كلُّها برتقاليّة. */
        #branches-dashboard .sd-notice.warning { color: var(--sd-text) !important; line-height: 1.8; }
        #branches-dashboard .sd-notice.warning > strong { display: block; color: var(--sd-warning); margin-bottom: .2rem; }
        #branches-dashboard .sd-notice .sl-tag { margin-inline-start: .2rem; }

        /* تنبيهُ الحصر على فرع الشريط: عنوانُه بلون الهوية وشرحُه بلون النصّ */
        #branches-dashboard .sd-notice.bxd-header-scope {
            color: var(--sd-text); line-height: 1.8;
            border-color: color-mix(in srgb, var(--sd-primary) 35%, transparent);
            background: color-mix(in srgb, var(--sd-primary) 6%, transparent);
        }
        #branches-dashboard .sd-notice.bxd-header-scope > strong { display: block; color: var(--sd-primary); }

        /* بطاقةُ الجهة المختارة: شريطٌ جانبيّ بلون نوعها */
        #branches-dashboard .sd-card.bxd-focus { height: auto; border-inline-start: 3px solid var(--sd-primary); }
        #branches-dashboard .sd-card.bxd-focus--success { border-inline-start-color: var(--sd-success); }
        #branches-dashboard .sd-card.bxd-focus--info { border-inline-start-color: var(--sd-info); }
        #branches-dashboard .sd-card.bxd-focus--warning { border-inline-start-color: var(--sd-warning); }
        #branches-dashboard .sd-card.bxd-focus .sd-card-body { padding: 1rem 1.15rem; }
        #branches-dashboard .bxd-focus__head { margin-bottom: 0; }

        /* ألوانُ أيقونات مفتاح الدائرة */
        #branches-dashboard .bxd-tone--primary { color: var(--sd-primary); }
        #branches-dashboard .bxd-tone--success { color: var(--sd-success); }
        #branches-dashboard .bxd-tone--info { color: var(--sd-info); }
        #branches-dashboard .bxd-tone--warning { color: var(--sd-warning); }
        #branches-dashboard .bxd-legend { margin-top: .75rem; }

        /* فلاترُ جدول المستوى: البحثُ يأخذ ما بقي من السطر، والعدّادُ في آخره */
        #branches-dashboard .bxd-level-filters { margin-bottom: .9rem; }
        #branches-dashboard .bxd-level-filters__search { flex: 1 1 240px; }
        #branches-dashboard .bxd-level-filters__search .form-control { width: 100%; }
        #branches-dashboard .bxd-level-filters .sd-drawer-count { margin-inline-start: auto; align-self: center; }

        /* الحاشيةُ تحت سطر الحالة الفارغة */
        #branches-dashboard .sd-empty small { margin-top: .3rem; font-size: .78rem; opacity: .85; }
        /* أيقونةُ الزرّ داخل الحالة الفارغة تبقى بقياس الزرّ: قاعدةُ الأيقونة
           الكبيرة فوق النصّ كانت تطالها فتتضخّم داخل الزرّ */
        #branches-dashboard .sd-empty .btn i { display: inline; font-size: inherit; margin: 0; opacity: 1; }

        /* الشجرة: السهمُ قبل أيقونة النوع، وتفصيلُ العدد بكلماته */
        #branches-dashboard .bxd-table .bx-node { align-items: flex-start; }
        #branches-dashboard .bxd-table .sd-tree-caret,
        #branches-dashboard .bxd-table .sd-tree-leaf { margin-top: 5px; }
        #branches-dashboard .bxd-split b { color: var(--sd-text); font-weight: 700; }
        #branches-dashboard .bxd-pair { flex-wrap: nowrap; white-space: nowrap; }
        /* صفُّ أعلى مستوى يُميَّز بأرضيّته واسمِه، لا بتثخين أرقامه كلِّها */
        #branches-dashboard .sd-tree tr.sd-tree-d0 td { font-weight: inherit; }
        #branches-dashboard .sd-tree tr.sd-tree-d0 .sd-drill { font-weight: 700; }

        /* الطباعة: الصفوفُ التي أخفاها فلترُ الجدول تُطبع — الورقةُ تحمل
           المستوى كاملاً لا ما وافق بحثاً عابراً */
        @media print {
            #branches-dashboard .bxd-row[hidden] { display: table-row !important; }
        }

        /* الرسمان: المعرّفُ يبدأ بـ`bxd` لا بـ`sd`، فقاعدةُ الأصل لا تطالهما */
        #branches-dashboard [id^="bxd"][id$="Chart"] { direction: ltr; }
    </style>
