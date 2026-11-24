{{--
    سمةُ لوحة تحكّم الموظفين — **منقولةٌ حرفياً** من لوحة العملاء
    (`client::dashboard.client.index`) ولا يتغيّر فيها إلا النطاق:
    `#client-dashboard` ← `#hr-dashboard`. فالتصميمُ واحدٌ بالفعل لا بالشبه.

    ولم تُستخرَج من قالب العملاء إلى مشترَكٍ واحد: ذاك يعني تعديل لوحةٍ
    تعمل من أجل ترتيبٍ داخليّ. فإن غُيِّر تصميمُ لوحة العملاء يُعاد توليد
    هذا الملف منها — بنفس الاستبدال الواحد.

    أصنافُ `cd-` كما هي كي يكون الشكلُ والسلوكُ واحداً: البطاقات، والمؤشّرات،
    وقوائم الشرائح، والجداول، واللوحة الجانبية.
--}}
<style>

    #hr-dashboard {
        --cd-surface: var(--app-surface, #fff);
        --cd-border: var(--app-border, #e6e9f0);
        --cd-text: var(--app-text, #4b4b4b);
        --cd-muted: var(--app-muted, #7b8194);
        --cd-radius: var(--app-radius, 8px);
        --cd-primary: var(--app-primary, #7367f0);
        --cd-success: var(--app-success, #28c76f);
        --cd-danger: var(--app-danger, #ea5455);
        --cd-warning: var(--app-warning, #ff9f43);
        --cd-info: var(--app-info, #00cfe8);
        font-family: var(--app-font-family, 'Cairo', sans-serif);
    }

    #hr-dashboard .cd-toolbar {
        background: var(--cd-surface);
        border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px);
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }

    #hr-dashboard .cd-title {
        font-weight: 700; color: var(--cd-text); margin: 0 0 .75rem; font-size: 1.1rem;
        display: flex; align-items: center; gap: .5rem;
    }

    #hr-dashboard .cd-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }

    #hr-dashboard .cd-tab {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-muted);
        border-radius: var(--cd-radius); padding: .42rem .95rem; font-size: .85rem; font-weight: 600; cursor: pointer;
    }

    #hr-dashboard .cd-tab.active { background: var(--cd-primary); border-color: var(--cd-primary); color: #fff; }

    /* شارةُ نوع العنصر — بنفس ألوان شجرة الفروع */
    .cd-node-type {
        display: inline-block; margin-inline-start: .25rem; padding: 0 .35rem; border-radius: .25rem;
        font-size: .68rem; line-height: 1.7; background: #eef0f4; color: #4b5563; vertical-align: middle;
    }

    #hr-dashboard .cd-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: -.4rem 0 .85rem; font-size: .78rem; }
    #hr-dashboard .cd-crumbs a { color: var(--cd-primary); text-decoration: none; }
    #hr-dashboard .cd-crumbs a:hover { text-decoration: underline; }
    #hr-dashboard .cd-crumbs strong { color: var(--cd-text); }
    #hr-dashboard .cd-crumb-sep { color: var(--cd-muted); opacity: .6; }

    #hr-dashboard .cd-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
    #hr-dashboard .cd-field { display: flex; flex-direction: column; gap: .25rem; }
    #hr-dashboard .cd-field > label { font-size: .74rem; color: var(--cd-muted); margin: 0; font-weight: 600; }

    #hr-dashboard .cd-field .form-control,
    #hr-dashboard .cd-field .custom-select,
    #hr-dashboard .cd-field select {
        height: var(--app-input-height, 38px); font-size: .84rem; border-radius: var(--cd-radius);
        border: 1px solid var(--cd-border); background: var(--cd-surface); color: var(--cd-text);
        padding: .25rem .6rem; min-width: 165px;
    }
    #hr-dashboard .cd-field input.cd-days-input { min-width: 0; width: 120px; }

    #hr-dashboard .cd-switch {
        display: inline-flex; align-items: center; gap: .4rem;
        height: var(--app-input-height, 38px); padding: 0 .75rem;
        border: 1px solid var(--cd-border); border-radius: var(--cd-radius);
        background: var(--cd-surface); font-size: .82rem; font-weight: 600;
        color: var(--cd-muted); cursor: pointer; margin: 0; white-space: nowrap;
    }
    #hr-dashboard .cd-switch input { accent-color: var(--cd-primary); width: 15px; height: 15px; cursor: pointer; }
    #hr-dashboard .cd-scope-count { font-style: normal; font-size: .72rem; font-weight: 700; color: var(--cd-primary); }

    #hr-dashboard .cd-apply {
        height: var(--app-input-height, 38px); border-radius: var(--app-button-radius, 6px); border: none;
        background: var(--cd-primary); color: #fff; font-size: .84rem; font-weight: 600; padding: 0 1.1rem; cursor: pointer;
    }

    #hr-dashboard .cd-reset {
        height: var(--app-input-height, 38px); display: inline-flex; align-items: center; gap: .3rem;
        border-radius: var(--app-button-radius, 6px); border: 1px solid var(--cd-border);
        background: var(--cd-surface); color: var(--cd-muted); font-size: .84rem; padding: 0 .9rem;
        cursor: pointer; text-decoration: none;
    }

    #hr-dashboard .cd-note-text { margin-top: .25rem; font-size: .8rem; line-height: 1.6; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    #hr-dashboard .cd-range-note { margin-top: .7rem; font-size: .76rem; color: var(--cd-muted); }

    /* ---------- العناصر القابلة للنقر ---------- */
    #hr-dashboard [data-segment] { cursor: pointer; }
    #hr-dashboard [data-segment]:focus-visible { outline: 2px solid var(--cd-primary); outline-offset: 2px; }

    /* ---------- المؤشرات ---------- */
    #hr-dashboard .cd-kpi {
        background: var(--cd-surface); border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px); padding: var(--app-card-padding, 1rem);
        height: 100%; transition: transform .18s ease, border-color .18s ease; position: relative;
    }
    #hr-dashboard .cd-kpi[data-segment]:hover { transform: translateY(-3px); border-color: var(--cd-primary); }
    #hr-dashboard .cd-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
    #hr-dashboard .cd-kpi-icon { width: 40px; height: 40px; border-radius: var(--cd-radius); display: flex; align-items: center; justify-content: center; }
    #hr-dashboard .cd-kpi-open { font-size: .7rem; color: var(--cd-muted); opacity: 0; transition: opacity .15s; }
    #hr-dashboard .cd-kpi[data-segment]:hover .cd-kpi-open { opacity: 1; color: var(--cd-primary); }
    #hr-dashboard .cd-kpi-label { color: var(--cd-muted); font-size: .78rem; font-weight: 600; margin: 0 0 .25rem; }
    #hr-dashboard .cd-kpi-value { font-size: 1.3rem; font-weight: 700; color: var(--cd-text); margin: 0; direction: ltr; text-align: start; }
    #hr-dashboard .cd-kpi-foot { margin-top: .4rem; font-size: .74rem; color: var(--cd-muted); line-height: 1.5; }

    /* ---------- البطاقات ---------- */
    #hr-dashboard .cd-card {
        background: var(--cd-surface); border: 1px solid var(--cd-border);
        border-radius: calc(var(--cd-radius) + 4px); margin-bottom: 1.25rem;
        height: calc(100% - 1.25rem); display: flex; flex-direction: column;
    }
    #hr-dashboard .cd-card-head { padding: 1rem 1.15rem .4rem; display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    #hr-dashboard .cd-card-head h4 { font-size: 1rem; font-weight: 700; color: var(--cd-text); margin: 0; }
    #hr-dashboard .cd-card-head small { color: var(--cd-muted); font-size: .76rem; }
    #hr-dashboard .cd-card-body { padding: .4rem 1.15rem 1.1rem; flex: 1; }
    #hr-dashboard .cd-card-tip { font-size: .72rem; color: var(--cd-muted); margin: -.1rem 0 .4rem; }

    #hr-dashboard .cd-link-btn {
        background: none; border: none; padding: 0; color: var(--cd-primary); font-size: .78rem; font-weight: 600; cursor: pointer;
    }

    #hr-dashboard .cd-info-toggle {
        background: none; border: none; padding: .2rem .35rem; line-height: 1;
        border-radius: 5px; color: var(--cd-muted); cursor: pointer; flex-shrink: 0;
    }
    #hr-dashboard .cd-info-toggle:hover { color: var(--cd-primary); background: color-mix(in srgb, var(--cd-primary) 10%, transparent); }
    #hr-dashboard .cd-info-panel {
        background: color-mix(in srgb, var(--cd-primary) 6%, transparent); border: 1px solid var(--cd-border);
        border-radius: var(--cd-radius); padding: .6rem .8rem; margin-bottom: .7rem;
        font-size: .78rem; line-height: 1.7; color: var(--cd-muted);
    }
    #hr-dashboard .cd-info-panel code {
        background: color-mix(in srgb, var(--cd-text) 8%, transparent); padding: .05rem .3rem;
        border-radius: 4px; font-size: .74rem; direction: ltr; display: inline-block;
    }

    /* قائمةُ الشرائح تحت كل مخطَّط — الرقمُ والتعريفُ ومفتاحُ القائمة */
    #hr-dashboard .cd-seg-list { list-style: none; margin: .5rem 0 0; padding: 0; }
    #hr-dashboard .cd-subhead { margin: 1rem 0 0; padding-top: .8rem; border-top: 1px dashed var(--cd-border); font-size: .88rem; font-weight: 700; color: var(--cd-text); display: flex; justify-content: space-between; align-items: baseline; }
    #hr-dashboard .cd-subhead small { font-weight: 600; color: var(--cd-muted); font-size: .72rem; }
    #hr-dashboard .cd-seg-list li {
        display: flex; align-items: center; gap: .5rem; padding: .38rem .45rem;
        border-radius: var(--cd-radius); font-size: .8rem; color: var(--cd-text);
    }
    #hr-dashboard .cd-seg-list li:hover { background: color-mix(in srgb, var(--cd-primary) 7%, transparent); }
    #hr-dashboard .cd-seg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    #hr-dashboard .cd-seg-label { flex: 1; min-width: 0; }
    #hr-dashboard .cd-seg-label small { display: block; color: var(--cd-muted); font-size: .7rem; line-height: 1.4; }
    #hr-dashboard .cd-seg-val { font-weight: 700; direction: ltr; white-space: nowrap; font-variant-numeric: tabular-nums; }
    #hr-dashboard .cd-seg-pct { color: var(--cd-muted); font-size: .72rem; min-width: 44px; text-align: end; direction: ltr; }

    #hr-dashboard .cd-table { width: 100%; font-size: .84rem; margin: 0; color: var(--cd-text); }
    #hr-dashboard .cd-table thead th {
        background: color-mix(in srgb, var(--cd-primary) 5%, transparent);
        color: var(--cd-muted); font-weight: 700; font-size: .76rem; border: none;
        padding: .6rem .55rem; white-space: nowrap;
    }
    #hr-dashboard .cd-table tbody td { padding: .55rem .55rem; border-top: 1px solid var(--cd-border); vertical-align: middle; }
    #hr-dashboard .cd-table tbody tr[data-segment]:hover td { background: color-mix(in srgb, var(--cd-primary) 6%, transparent); }
    #hr-dashboard .cd-table a { color: var(--cd-primary); font-weight: 600; text-decoration: none; }
    #hr-dashboard .cd-table a:hover { text-decoration: underline; }
    #hr-dashboard .cd-num { direction: ltr; text-align: start; font-variant-numeric: tabular-nums; white-space: nowrap; }
    #hr-dashboard .cd-long { min-width: 200px; max-width: 360px; white-space: normal; line-height: 1.6; }

    #hr-dashboard .cd-empty { text-align: center; color: var(--cd-muted); padding: 2rem 1rem; font-size: .86rem; }
    #hr-dashboard .cd-empty i { display: block; font-size: 1.8rem; margin-bottom: .5rem; opacity: .45; }
    /* إضافةٌ خاصّةٌ بلوحة الموظفين: تفسيرُ القائمة الفارغة تحت رسالتها */
    #hr-dashboard .cd-empty-why { margin: .75rem auto 0; max-width: 34rem; font-size: .8rem; line-height: 1.9; color: var(--cd-muted); background: color-mix(in srgb, var(--cd-muted) 7%, transparent); border-radius: .6rem; padding: .7rem .9rem; }

    #hr-dashboard .cd-badge { display: inline-block; padding: .16rem .5rem; border-radius: 6px; font-size: .72rem; font-weight: 700; }

    /* مركز التنبيهات */
    #hr-dashboard .cd-notif-list { display: flex; flex-direction: column; gap: .5rem; }
    #hr-dashboard .cd-notif-item {
        display: flex; align-items: center; gap: .65rem; padding: .6rem .7rem;
        border-radius: var(--cd-radius); border: 1px solid var(--cd-border);
    }
    #hr-dashboard a.cd-notif-item--link,
    #hr-dashboard .cd-notif-item[data-segment] { text-decoration: none; transition: background-color .15s, border-color .15s; }
    #hr-dashboard a.cd-notif-item--link:hover,
    #hr-dashboard .cd-notif-item[data-segment]:hover { background: color-mix(in srgb, var(--cd-primary) 6%, transparent); border-color: var(--cd-primary); }
    #hr-dashboard .cd-notif-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    #hr-dashboard .cd-notif-label { flex: 1; font-size: .84rem; color: var(--cd-text); }
    #hr-dashboard .cd-notif-count { font-weight: 700; font-size: .95rem; direction: ltr; }

    #hr-dashboard .cd-map { height: 420px; border-radius: var(--cd-radius); overflow: hidden; }
    #hr-dashboard .cd-map-head-actions { display: flex; align-items: center; gap: .6rem; }
    #hr-dashboard .cd-map-toggle {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-text);
        border-radius: var(--cd-radius); padding: .22rem .7rem; font-size: .78rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: .3rem; line-height: 1.6;
    }
    #hr-dashboard .cd-map-toggle:hover { border-color: var(--cd-primary); color: var(--cd-primary); }

    #hr-dashboard .cd-mini-stat { text-align: center; padding: .5rem; border-radius: var(--cd-radius); }
    #hr-dashboard .cd-mini-stat[data-segment]:hover { background: color-mix(in srgb, var(--cd-primary) 7%, transparent); }
    #hr-dashboard .cd-mini-stat .val { font-size: 1.15rem; font-weight: 700; color: var(--cd-text); direction: ltr; }
    #hr-dashboard .cd-mini-stat .lbl { font-size: .74rem; color: var(--cd-muted); }

    /* ---------- اللوحة الجانبية: عملاءُ الشريحة ---------- */
    #hr-dashboard .cd-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 1990; }
    #hr-dashboard .cd-drawer {
        position: fixed; top: 0; bottom: 0; inset-inline-end: 0; width: min(1080px, 96vw);
        background: var(--cd-surface); z-index: 1991; display: flex; flex-direction: column;
        box-shadow: 0 0 40px rgba(15, 23, 42, .25);
    }
    #hr-dashboard .cd-drawer-head {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
        padding: 1rem 1.25rem .6rem; border-bottom: 1px solid var(--cd-border);
    }
    #hr-dashboard .cd-drawer-head h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--cd-text); }
    #hr-dashboard .cd-drawer-head p { margin: .25rem 0 0; font-size: .8rem; color: var(--cd-muted); line-height: 1.6; }
    #hr-dashboard .cd-drawer-close {
        border: 1px solid var(--cd-border); background: transparent; color: var(--cd-muted);
        width: 34px; height: 34px; border-radius: 50%; font-size: 1.2rem; line-height: 1; cursor: pointer; flex-shrink: 0;
    }
    #hr-dashboard .cd-drawer-tools {
        display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .7rem 1.25rem;
        border-bottom: 1px solid var(--cd-border);
    }
    #hr-dashboard .cd-drawer-tools input[type="search"] {
        flex: 1; min-width: 200px; height: 38px; border: 1px solid var(--cd-border);
        border-radius: var(--cd-radius); padding: .25rem .7rem; font-size: .85rem; background: var(--cd-surface); color: var(--cd-text);
    }
    #hr-dashboard .cd-drawer-count { font-size: .82rem; font-weight: 700; color: var(--cd-primary); white-space: nowrap; }
    #hr-dashboard .cd-drawer-body { flex: 1; overflow: auto; padding: 0 1.25rem 1rem; }
    #hr-dashboard .cd-drawer-body .cd-table thead th { position: sticky; top: 0; background: var(--cd-surface); box-shadow: inset 0 -1px 0 var(--cd-border); z-index: 1; }
    #hr-dashboard .cd-drawer-more { display: block; margin: .8rem auto 0; }
</style>
