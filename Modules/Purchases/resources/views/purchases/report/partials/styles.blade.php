{{--
    أنماط تقارير المشتريات — مصدر واحد لمظهر الشاشات الثلاث.

    **لماذا؟** كانت كل شاشة تقرير تحمل ثلاثمئة سطر CSS خاصّة بها، بألوانٍ
    مكتوبةٍ رقماً (`#7367f0`، `#667eea`) لا صلة لها بما يضبطه المسؤول في
    شاشة «مظهر النظام». فمن غيّر اللون الأساسي إلى الأخضر بقيت تقاريره
    بنفسجية، ومن اختار الوضع الليلي حصل على بطاقاتٍ بيضاء بنصٍّ فاتح.

    كل لونٍ هنا يقرأ متغيّر `--app-*` الذي تحقنه `App\Support\ThemeSettings`
    في الترويسة، وله بديلٌ فاتح إن لم يُضبط — فالشاشة تتبع الإعدادات دون أن
    تنكسر قبل ضبطها. والقياسات تتبع `--app-radius` و`--app-font-family`
    كذلك، فالتقرير جزءٌ من النظام لا جزيرةٌ فيه.

    الطباعة: تُقصى الألوان المشتقّة ويُفرض أبيضُ الورق وأسودُ الحبر — فما
    يظهر على الشاشة داكناً لا يستنزف الحبر ولا يخرج نصّاً على نصّ.
--}}
<style>
    .rpt {
        --rpt-brand: var(--app-primary, #7367f0);
        --rpt-brand-soft: var(--app-primary-soft, rgba(115, 103, 240, .12));
        --rpt-on-brand: var(--app-on-primary, #fff);
        --rpt-ink: var(--app-text, #2f3349);
        --rpt-muted: var(--app-muted, #6e6b7b);
        --rpt-line: var(--app-border, #e8e6f0);
        --rpt-surface: var(--app-surface, #fff);
        --rpt-sunken: var(--app-primary-soft, #f6f5ff);
        --rpt-ok: var(--app-success, #28c76f);
        --rpt-bad: var(--app-danger, #ea5455);
        --rpt-warn: var(--app-warning, #ff9f43);
        --rpt-info: var(--app-info, #00cfe8);
        --rpt-radius: var(--app-radius, 12px);
        font-family: var(--app-font-family, 'Cairo', 'Tajawal', 'Segoe UI', sans-serif);
    }

    .rpt * { font-family: inherit; }

    /* ==================== الخط ====================

       نفس خط تقارير المبيعات (Cairo). المبيعات تفرضه مكتوباً في القالب؛
       هنا يُقرأ من `--app-font-family` التي تحقنها `ThemeSettings` — وهي
       Cairo افتراضاً، فالشاشتان متطابقتان، ومن غيّر الخط في «مظهر النظام»
       تبعته التقارير بدل أن تشذّ عنه.

       ملف الخط نفسه تُحمّله `layouts/partials/theme-styles` من Google Fonts،
       فلا حاجة إلى `@import` ثانٍ يؤخّر أول رسم للصفحة. */
    .rpt, .rpt h1, .rpt h2, .rpt h3, .rpt h4, .rpt h5, .rpt h6,
    .rpt table, .rpt .btn, .rpt .form-control, .rpt select, .rpt textarea,
    .rpt .select2-container, .rpt .select2-container .select2-selection__rendered {
        font-family: var(--app-font-family, 'Cairo', sans-serif) !important;
    }

    /* ==================== الأزرار ====================

       نفس أزرار تقارير المبيعات شكلاً ومقاساً — `period-tabs` و
       `btn-view-toggle` بنفس أسماء الأصناف هناك، ليتطابق المظهر حرفياً.
       الفارق الوحيد أن اللون يأتي من الإعدادات لا مكتوباً `#2563eb`. */
    .period-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .period-tabs .btn {
        flex: 1;
        min-width: 80px;
        border-radius: var(--app-button-radius, 8px);
        padding: 10px 12px;
        font-weight: 600;
        font-size: .85rem;
        transition: all .3s ease;
        text-decoration: none;
    }

    .period-tabs .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
    }

    .period-tabs .btn.btn-primary {
        background: var(--rpt-brand);
        border-color: var(--rpt-brand);
        color: var(--rpt-on-brand);
        box-shadow: 0 4px 15px var(--app-primary-glow, rgba(115, 103, 240, .4));
    }

    .period-tabs .btn.btn-outline-primary {
        border: 1px solid var(--rpt-line);
        color: var(--rpt-muted);
        background: transparent;
    }

    .period-tabs .btn.btn-outline-primary:hover {
        background: var(--rpt-brand-soft);
        border-color: var(--rpt-brand);
        color: var(--rpt-brand);
    }

    .btn-view-toggle {
        padding: 8px 20px;
        border-radius: var(--app-button-radius, 6px);
    }

    /* صفّ أزرار الإجراءات — بنفس ترتيب شاشة المبيعات وتباعدها */
    .rpt-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* ==================== الترويسة ==================== */

    .rpt-hero {
        background: linear-gradient(115deg, var(--rpt-brand) 0%, var(--app-primary-hover, #5b52d6) 100%);
        border-radius: var(--rpt-radius);
        padding: 20px 24px;
        color: var(--rpt-on-brand);
        margin-bottom: 16px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .rpt-hero h1 { font-size: 1.4rem; font-weight: 800; margin: 0 0 4px; color: inherit; }
    .rpt-hero p { margin: 0; opacity: .88; font-size: .85rem; }
    .rpt-hero__meta { display: flex; flex-wrap: wrap; gap: 8px; }
    .rpt-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255, 255, 255, .18);
        border: 1px solid rgba(255, 255, 255, .32);
        border-radius: 999px; padding: 5px 14px;
        font-size: .8rem; font-weight: 700; color: inherit;
    }

    /* ==================== البطاقات ==================== */

    .rpt-card {
        background: var(--rpt-surface);
        border: 1px solid var(--rpt-line);
        border-radius: var(--rpt-radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .rpt-card__head {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px;
        padding: 12px 18px; border-bottom: 1px solid var(--rpt-line);
    }
    .rpt-card__head h4 { margin: 0; font-size: .98rem; font-weight: 700; color: var(--rpt-ink); }
    .rpt-card__body { padding: 16px 18px; }

    /* ==================== الفلاتر ==================== */

    .rpt-periods { display: flex; flex-wrap: wrap; gap: 8px; }
    .rpt-periods a {
        flex: 1 1 100px; text-align: center; padding: 9px 10px;
        border-radius: calc(var(--rpt-radius) * .7);
        border: 1px solid var(--rpt-line); background: transparent;
        color: var(--rpt-ink); font-weight: 700; font-size: .84rem;
        text-decoration: none; transition: .18s;
    }
    .rpt-periods a:hover { border-color: var(--rpt-brand); color: var(--rpt-brand); }
    .rpt-periods a.is-active {
        background: var(--rpt-brand); border-color: var(--rpt-brand);
        color: var(--rpt-on-brand);
    }

    .rpt-field { margin-bottom: 12px; }
    .rpt-field > label {
        display: block; font-size: .77rem; font-weight: 700;
        color: var(--rpt-muted); margin-bottom: 5px;
    }
    .rpt-field .form-control, .rpt-field select {
        border-radius: calc(var(--rpt-radius) * .55);
        border: 1px solid var(--rpt-line); font-size: .85rem;
    }

    /* ==================== المؤشرات ==================== */

    .rpt-kpis {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px; margin-bottom: 16px;
    }
    .rpt-kpi {
        background: var(--rpt-surface); border: 1px solid var(--rpt-line);
        border-radius: var(--rpt-radius); padding: 14px 16px;
        position: relative; overflow: hidden;
    }
    /* الشريط الجانبي يتبع اتجاه اللغة، فيبقى إلى يمين البطاقة في العربية */
    .rpt-kpi::before {
        content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
        width: 4px; background: var(--k, var(--rpt-brand));
    }
    .rpt-kpi__label {
        font-size: .76rem; color: var(--rpt-muted); font-weight: 600;
        display: flex; align-items: center; gap: 6px;
    }
    .rpt-kpi__value {
        font-size: 1.25rem; font-weight: 800; color: var(--rpt-ink);
        margin-top: 6px; direction: ltr; text-align: start;
        font-variant-numeric: tabular-nums;
    }
    .rpt-kpi__sub { font-size: .71rem; color: var(--rpt-muted); margin-top: 2px; }

    /* ==================== شريط المعادلة ==================== */

    .rpt-equation {
        display: flex; flex-wrap: wrap; align-items: stretch;
        background: var(--rpt-surface); border: 1px solid var(--rpt-line);
        border-radius: var(--rpt-radius); overflow: hidden; margin-bottom: 16px;
    }
    .rpt-equation__item {
        flex: 1 1 140px; padding: 12px 14px; text-align: center;
        border-inline-end: 1px solid var(--rpt-line);
    }
    .rpt-equation__item:last-child { border-inline-end: none; background: var(--rpt-sunken); }
    .rpt-equation__op {
        flex: 0 0 30px; display: flex; align-items: center; justify-content: center;
        color: var(--rpt-muted); font-weight: 800; font-size: 1.05rem;
        border-inline-end: 1px solid var(--rpt-line);
    }
    .rpt-equation small { display: block; font-size: .71rem; color: var(--rpt-muted); margin-bottom: 4px; }
    .rpt-equation strong { font-size: .98rem; direction: ltr; display: block; font-variant-numeric: tabular-nums; }

    /* ==================== الجداول ==================== */

    .rpt-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
    .rpt-table thead th {
        background: var(--app-table-header-bg, var(--rpt-sunken));
        color: var(--app-table-header-text, var(--rpt-ink));
        font-weight: 700; padding: 11px 8px; text-align: center;
        border-bottom: 2px solid var(--rpt-line); white-space: nowrap; font-size: .78rem;
        position: sticky; top: 0; z-index: 2;
    }
    .rpt-table thead th a { color: inherit; text-decoration: none; }
    .rpt-table thead th a:hover { color: var(--rpt-brand); }
    .rpt-table tbody td {
        padding: 10px 8px; text-align: center; vertical-align: middle;
        border-bottom: 1px solid var(--rpt-line); color: var(--rpt-ink);
    }
    .rpt-table tbody tr:hover td { background: var(--rpt-brand-soft); }
    .rpt-table tfoot td {
        padding: 12px 8px; text-align: center; background: var(--rpt-sunken);
        font-weight: 800; border-top: 2px solid var(--rpt-line); color: var(--rpt-ink);
    }
    .rpt-num { direction: ltr; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rpt-name { text-align: start !important; font-weight: 700; color: var(--rpt-ink); }
    .rpt-name small { display: block; font-weight: 500; color: var(--rpt-muted); font-size: .72rem; }

    /* الجدول الطويل يمرَّر داخل إطاره لا بالصفحة كلّها */
    .rpt-scroll { overflow-x: auto; max-width: 100%; }

    /* ==================== الشارات والألوان ==================== */

    .rpt-pill {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: .72rem; font-weight: 700; white-space: nowrap;
    }
    .rpt-pill-success   { background: var(--app-success-soft, rgba(40, 199, 111, .12)); color: var(--rpt-ok); }
    .rpt-pill-danger    { background: var(--app-danger-soft, rgba(234, 84, 85, .12)); color: var(--rpt-bad); }
    .rpt-pill-warning   { background: rgba(255, 159, 67, .14); color: var(--rpt-warn); }
    .rpt-pill-info      { background: rgba(0, 207, 232, .14); color: var(--rpt-info); }
    .rpt-pill-secondary { background: var(--rpt-brand-soft); color: var(--rpt-muted); }
    .rpt-pill-primary   { background: var(--rpt-brand-soft); color: var(--rpt-brand); }

    .rpt-pos  { color: var(--rpt-ok); }
    .rpt-neg  { color: var(--rpt-bad); }
    .rpt-zero { color: var(--rpt-muted); opacity: .65; }

    /* ==================== الكتل التفصيلية ==================== */

    .rpt-block {
        border: 1px solid var(--rpt-line); border-radius: var(--rpt-radius);
        margin-bottom: 16px; overflow: hidden; background: var(--rpt-surface);
    }
    .rpt-block > header {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
        gap: 10px; padding: 12px 18px; background: var(--rpt-sunken);
        border-bottom: 1px solid var(--rpt-line);
    }
    .rpt-block > header h5 { margin: 0; font-size: .96rem; font-weight: 800; color: var(--rpt-ink); }
    .rpt-mini { display: flex; flex-wrap: wrap; border-bottom: 1px solid var(--rpt-line); }
    .rpt-mini > div {
        flex: 1 1 125px; padding: 10px 12px; text-align: center;
        border-inline-end: 1px solid var(--rpt-line);
    }
    .rpt-mini > div:last-child { border-inline-end: none; }
    .rpt-mini small { display: block; font-size: .7rem; color: var(--rpt-muted); }
    .rpt-mini strong { font-size: .9rem; direction: ltr; display: block; margin-top: 2px; font-variant-numeric: tabular-nums; }

    /* تلوين صفوف الحركة بنوعها — أخفُّ من أن يزاحم النص، وكافٍ للتمييز */
    .rpt-row-purchase   td { background: rgba(115, 103, 240, .04); }
    .rpt-row-return     td { background: rgba(234, 84, 85, .05); }
    .rpt-row-payment    td { background: rgba(40, 199, 111, .05); }
    .rpt-row-debit_note td { background: rgba(255, 159, 67, .06); }

    .rpt-empty { text-align: center; padding: 46px 16px; color: var(--rpt-muted); }
    .rpt-empty i { font-size: 2.3rem; opacity: .35; display: block; margin-bottom: 10px; }

    /* ==================== ترويسة المطبوع ==================== */

    /* لا تظهر على الشاشة — الشاشة لها ترويستها الملوّنة */
    .rpt-print-only { display: none; }

    /* ==================== الطباعة ==================== */

    @media print {
        .no-print, .rpt-periods, .rpt-filters,
        .main-menu, .header-navbar, .content-header, footer, .buy-now { display: none !important; }

        .rpt-print-only { display: block !important; }

        /* الورق أبيض والحبر أسود مهما كان مظهر الشاشة */
        .rpt {
            --rpt-surface: #fff; --rpt-ink: #000; --rpt-muted: #555;
            --rpt-line: #bbb; --rpt-sunken: #f2f2f2; --rpt-brand-soft: transparent;
        }
        body, .app-content, .content-wrapper, .content-body, .rpt-card, .rpt-block {
            background: #fff !important; color: #000 !important;
        }
        .rpt-hero {
            background: #fff !important; color: #000 !important;
            border: 1px solid #333; border-radius: 0;
        }
        .rpt-hero h1, .rpt-hero p { color: #000 !important; }
        .rpt-chip { background: transparent; border-color: #333; color: #000; }

        .rpt-table thead th { background: #eee !important; color: #000 !important; }
        .rpt-table thead th, .rpt-table tbody td, .rpt-table tfoot td { border-color: #999 !important; }
        .rpt-table tbody tr:hover td { background: transparent !important; }
        .rpt-scroll { overflow: visible !important; }

        .rpt-kpi, .rpt-card, .rpt-block, .rpt-equation { break-inside: avoid; page-break-inside: avoid; }
        .rpt-table thead { display: table-header-group; }
        .rpt-table tfoot { display: table-footer-group; }

        /* الشارات الملوّنة تُطبع إطاراً لا مساحةً مصمتة */
        .rpt-pill { background: transparent !important; border: 1px solid #666; color: #000 !important; }

        @page { margin: 12mm 8mm; }
    }
</style>
