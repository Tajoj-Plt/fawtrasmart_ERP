{{--
    مظهرُ تقارير العملاء — مصدرٌ واحدٌ لكلّ شاشاتها.

    يُستدعى من قسم css بدل ورقة `rpt-*` مباشرةً (يحمّلها هو أوّلاً)،
    ويضيف فوقها صفوفَ `ag-*` المعتمدة من تصميم «أعمار ديون الفواتير»:

      ag-head / ag-crumbs / ag-chips   الترويسةُ الفاتحة   ← partials.report-head
      ag-tabs / ag-tab                 أزرارُ الفترات      ← partials.report-period-tabs
      ag-filter-grid / ag-span-all     شبكةُ الحقول (أربعةٌ في السطر)
      ag-range                         حقلا «من/إلى» في خانةٍ واحدة
      ag-filter-foot / ag-quick__actions / ag-btn-soft   ذيلُ الفلترة وأزرارُه
      ag-kpis / ag-kpi                 بطاقاتُ المؤشّرات بأيقونة
      ag-charts / ag-canvas / ag-dist / ag-legend / ag-donut   الرسوم
      ag-table-head / ag-tools / ag-filter-chip                رأسُ الجدول
      ag-bucket / ag-days / ag-actions-btn / ag-link           خلايا الجدول
      ag-pager                         ترقيمُ الصفحات      ← partials.report-pager

    كلُّ لونٍ من `--rpt-*` (أي من «مظهر النظام»)؛ الألوانُ الثابتة تُمرَّر
    لكلّ عنصرٍ في `--c` حين تكون معنىً (شريحة، نوع) لا مظهراً.
--}}
@include('sales::sals_report.report_partials.styles')
<style>
    /* ==================== الترويسة ==================== */
    .rpt .ag-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
        background: var(--rpt-surface); border: 1px solid var(--rpt-line);
        border-radius: var(--rpt-radius); padding: 16px 20px; margin-bottom: 16px;
    }
    .rpt .ag-head__title { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .rpt .ag-head__icon {
        width: 48px; height: 48px; border-radius: 12px; flex: none;
        display: grid; place-items: center; font-size: 1.25rem;
        background: var(--rpt-brand); color: var(--rpt-on-brand);
    }
    .rpt .ag-head h1 { font-size: 1.35rem; font-weight: 800; margin: 0 0 3px; color: var(--rpt-ink); }
    .rpt .ag-head p { margin: 0; color: var(--rpt-muted); font-size: .84rem; }
    .rpt .ag-head__side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    .rpt .ag-crumbs { display: flex; align-items: center; gap: 8px; font-size: .8rem; color: var(--rpt-muted); }
    .rpt .ag-crumbs a { color: var(--rpt-muted); text-decoration: none; }
    .rpt .ag-crumbs a:hover { color: var(--rpt-brand); }
    .rpt .ag-crumbs i { font-size: .62rem; opacity: .7; }
    .rpt .ag-crumbs span { color: var(--rpt-ink); font-weight: 700; }
    .rpt .ag-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .rpt .ag-chip {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px;
        background: var(--rpt-brand-soft); color: var(--rpt-brand); font-size: .76rem; font-weight: 700;
    }

    /* ==================== الفلترة ==================== */
    /* أزرارُ الفترات بمقاس لوحة العملاء (`cd-tab`) — صغيرةٌ فوق الحقول */
    .rpt .ag-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 14px; }
    .rpt .ag-tab {
        border: 1px solid var(--rpt-line); background: transparent; color: var(--rpt-muted);
        border-radius: var(--app-button-radius, 8px); padding: .42rem .95rem;
        font-size: .85rem; font-weight: 600; line-height: 1.2; cursor: pointer;
    }
    .rpt .ag-tab:hover { border-color: var(--rpt-brand); color: var(--rpt-brand); }
    .rpt .ag-tab.is-active { background: var(--rpt-brand); border-color: var(--rpt-brand); color: var(--rpt-on-brand); }
    /* تقاريرُ المبيعات تجعل الفتراتِ الجاهزةَ روابطَ يحسب الخادمُ مداها */
    .rpt a.ag-tab { display: inline-flex; align-items: center; text-decoration: none; }
    /* لونُ الروابط العامّ في القالب (`a:not(.btn):not(.nav-link)…`) أثقلُ من صنفَي الزرّ */
    .rpt .ag-tabs a.ag-tab:not(.btn):not(.is-active) { color: var(--rpt-muted); }
    .rpt .ag-tabs a.ag-tab:not(.btn):not(.is-active):hover { color: var(--rpt-brand); }
    .rpt .ag-tabs a.ag-tab.is-active:not(.btn) { color: var(--rpt-on-brand); }

    /* كلُّ الحقول في شبكةٍ واحدةٍ متساوية: أربعةٌ في السطر */
    .rpt .ag-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px 14px; align-items: end; }
    .rpt .ag-filter-grid > .rpt-field { margin-bottom: 0; min-width: 0; }
    .rpt .ag-span-all { grid-column: 1 / -1; }
    .rpt .ag-span-all .rpt-field { margin-bottom: 0; }
    .rpt .ag-span-all .rpt-tree-col { margin-top: 4px; }
    .rpt .ag-range { display: flex; align-items: center; gap: 6px; }
    .rpt .ag-range .form-control { min-width: 0; }
    .rpt .ag-range__sep { color: var(--rpt-muted); font-size: .8rem; }

    .rpt .ag-filter-foot {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px;
        margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--rpt-line);
    }
    .rpt .ag-filter-foot .form-check { margin: 0; font-size: .84rem; color: var(--rpt-ink); }
    .rpt .ag-checks { display: flex; flex-wrap: wrap; gap: 8px 24px; }
    .rpt .ag-quick__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-inline-start: auto; }
    .rpt .ag-quick__actions .btn {
        height: 38px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        border-radius: var(--app-button-radius, 8px); font-weight: 700; font-size: .84rem;
    }
    .rpt .ag-btn-soft { border: 1px solid var(--rpt-line); background: var(--rpt-surface); color: var(--rpt-ink); }
    .rpt .ag-btn-soft:hover { border-color: var(--rpt-brand); color: var(--rpt-brand); }

    @media (max-width: 1199.98px) {
        .rpt .ag-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .rpt .ag-filter-grid { grid-template-columns: minmax(0, 1fr); }
        .rpt .ag-head__side { align-items: flex-start; }
    }

    /* ==================== المؤشّرات ==================== */
    .rpt .ag-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 16px; }
    .rpt .ag-kpi {
        --c: var(--rpt-brand);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        background: var(--rpt-surface); border: 1px solid var(--rpt-line);
        border-radius: var(--rpt-radius); padding: 18px 20px; min-width: 0;
    }
    .rpt .ag-kpi[data-filter] { cursor: pointer; }
    .rpt .ag-kpi[data-filter]:hover { border-color: var(--c); }
    .rpt .ag-kpi.is-active { border-color: var(--c); box-shadow: inset 0 0 0 1px var(--c); }
    .rpt .ag-kpi__label { font-size: .8rem; font-weight: 700; color: var(--rpt-muted); }
    .rpt .ag-kpi__value {
        font-size: 1.6rem; font-weight: 800; color: var(--rpt-ink); line-height: 1.25;
        margin-top: 6px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere;
    }
    .rpt .ag-kpi__sub { font-size: .74rem; color: var(--rpt-muted); margin-top: 2px; }
    .rpt .ag-kpi__icon {
        width: 52px; height: 52px; border-radius: 14px; flex: none;
        display: grid; place-items: center; font-size: 1.3rem;
        color: var(--c); background: color-mix(in srgb, var(--c) 14%, transparent);
    }

    /* ==================== الرسوم ==================== */
    .rpt .ag-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .rpt .ag-charts > .rpt-card { min-width: 0; }
    .rpt .ag-charts--single { grid-template-columns: minmax(0, 1fr); }
    .rpt .ag-hint { font-size: .72rem; color: var(--rpt-muted); font-weight: 600; }
    .rpt .ag-canvas { position: relative; height: 290px; }
    .rpt .ag-dist { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 16px; align-items: center; }
    .rpt .ag-legend { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .rpt .ag-legend td { padding: 9px 6px; border-bottom: 1px solid var(--rpt-line); color: var(--rpt-ink); }
    .rpt .ag-legend tr:last-child td { border-bottom: 0; }
    .rpt .ag-legend__row[data-filter] { cursor: pointer; }
    .rpt .ag-legend__row[data-filter]:hover td, .rpt .ag-legend__row:focus-visible td { background: var(--rpt-brand-soft); }
    .rpt .ag-legend__row:focus-visible { outline: none; }
    .rpt .ag-legend__row.is-active td { background: color-mix(in srgb, var(--c) 13%, transparent); font-weight: 700; }
    .rpt .ag-dot {
        display: inline-block; width: 10px; height: 10px; border-radius: 50%;
        background: var(--c); vertical-align: middle; margin-inline-end: 8px;
    }
    .rpt .ag-legend__amount { font-weight: 800; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rpt .ag-legend__pct { color: var(--rpt-muted); text-align: end; white-space: nowrap; width: 48px; }
    .rpt .ag-donut { position: relative; height: 250px; }
    .rpt .ag-donut__center {
        position: absolute; inset: 0; pointer-events: none; text-align: center;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .rpt .ag-donut__center strong { font-size: 1.1rem; font-weight: 800; color: var(--rpt-ink); font-variant-numeric: tabular-nums; }
    .rpt .ag-donut__center small { font-size: .72rem; color: var(--rpt-muted); }
    @media (max-width: 991.98px) { .rpt .ag-charts { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .rpt .ag-dist { grid-template-columns: minmax(0, 1fr); } }

    /* ==================== الجدول ==================== */
    .rpt .ag-table-head h4 { display: flex; align-items: center; gap: 8px; }
    .rpt .ag-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .rpt .ag-tools .btn { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; border-radius: var(--app-button-radius, 8px); }
    .rpt .ag-filter-chip {
        --c: var(--rpt-brand);
        display: inline-flex; align-items: center; gap: 6px; border: 0; cursor: pointer;
        border-radius: 999px; padding: 5px 12px; font-size: .76rem; font-weight: 700;
        color: var(--c); background: color-mix(in srgb, var(--c) 14%, transparent);
    }
    .rpt .ag-days { font-weight: 800; color: var(--c); white-space: nowrap; }
    .rpt .ag-bucket {
        --c: var(--rpt-muted);
        display: inline-block; padding: 3px 10px; border-radius: 999px; white-space: nowrap;
        font-size: .72rem; font-weight: 700; color: var(--c);
        background: color-mix(in srgb, var(--c) 14%, transparent);
    }
    .rpt .ag-actions-btn {
        display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; font-size: .85rem;
        border: 1px solid var(--rpt-line); border-radius: 8px; background: var(--rpt-surface); color: var(--rpt-muted);
    }
    .rpt .ag-actions-btn:hover { border-color: var(--rpt-brand); color: var(--rpt-brand); }
    .rpt .ag-link, .rpt .ag-invoice-link { color: inherit; text-decoration: none; }
    .rpt .ag-link:hover, .rpt .ag-invoice-link:hover { color: var(--rpt-brand); }
    .rpt .ag-table td.rpt-name { min-width: 180px; }

    /* ترقيمُ الصفحات يُخفي بالصنف لا بخاصية العرض — أداةُ التصدير تتخطّى
       الصفَّ المخفيَّ بالخاصية وحدَها، فيُصدَّر الجدولُ كلُّه لا الصفحة */
    .rpt tr.ag-hidden { display: none; }

    .rpt .ag-pager {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
        padding: 12px 18px; border-top: 1px solid var(--rpt-line); font-size: .8rem; color: var(--rpt-muted);
    }
    .rpt .ag-pager[hidden] { display: none !important; }
    .rpt .ag-page-size {
        display: inline-flex; align-items: center; gap: 6px; margin: 0;
        font-size: .8rem; font-weight: 600; color: var(--rpt-muted); white-space: nowrap;
    }
    .rpt .ag-page-size select {
        width: auto; height: 31px; padding: 0 8px; font-size: .8rem;
        border: 1px solid var(--rpt-line); border-radius: var(--app-button-radius, 8px);
    }
    .rpt .ag-pages { display: flex; flex-wrap: wrap; gap: 4px; }
    .rpt .ag-page {
        min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; font-size: .8rem; font-weight: 700;
        border: 1px solid var(--rpt-line); background: var(--rpt-surface); color: var(--rpt-ink);
    }
    .rpt .ag-page:hover:not(:disabled) { border-color: var(--rpt-brand); color: var(--rpt-brand); }
    .rpt .ag-page.is-active { background: var(--rpt-brand); border-color: var(--rpt-brand); color: var(--rpt-on-brand); }
    .rpt .ag-page:disabled { opacity: .45; cursor: default; }
    .rpt .ag-page--gap { border: 0; background: transparent; min-width: auto; }

    @media print {
        .rpt .ag-head__side, .rpt .ag-tools, .rpt .ag-pager, .rpt .ag-extra, .rpt .ag-tabs { display: none !important; }
        .rpt tr.ag-hidden { display: table-row !important; }
    }
</style>
