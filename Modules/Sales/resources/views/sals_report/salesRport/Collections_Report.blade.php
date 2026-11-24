@extends('sales::master')

@section('title')
    تقرير التحصيلات
@stop

{{--
    القسم هنا `css` لا `styles`: `sales::master` يكدّس `styles` بـ`@stack`
    ولا يلتقط الأقسام، ويمرّر `@yield('css')` عبر `layouts.head_rtl` — فكان
    كلُّ CSS هذه الشاشة يسقط صامتاً بلا خطأ ولا تحذير.

    ══════════ التصميم ══════════
    رأسٌ بعنوانٍ ومسار، ثمّ شريطُ فلاترٍ واحد (الفترة، العرض، طريقة التحصيل،
    التجميع) و«خيارات إضافية» تطوي البقيّة، ثمّ أربعُ بطاقات، ثمّ مخطّطان
    (حسب طريقة التحصيل، وحسب معيار التجميع)، ثمّ جدولُ «تفاصيل التحصيلات»
    مرقَّماً بصفحات. العروضُ الأخرى (ملخّص، شهري، تغيّر المستويات) باقيةٌ
    كما كانت وتُختار من «العرض».

    كلُّ لونٍ يقرأ `--app-*` من «مظهر النظام» (`App\Support\ThemeSettings`)،
    فيتبع الوضعَ الليلي. الأصناف `cr-*` خاصّةٌ بهذه الشاشة.
--}}
@section('css')
    {{-- مظهرُ تقارير العملاء نفسُه: rpt-* ثمّ ag-* (الفلترة، الجدول، الصفحات) --}}
    @include('client::customers.CustomerReport.partials.report-theme')

    <style>
        .rpt .rpt-note {
            display: flex; align-items: center; gap: .5rem;
            padding: 14px 16px; margin-bottom: 16px;
            background: color-mix(in srgb, var(--app-info, #00cfe8) 12%, transparent);
            color: var(--app-text, #2f3349);
            border: 1px solid color-mix(in srgb, var(--app-info, #00cfe8) 35%, transparent);
            border-inline-start: 4px solid var(--app-info, #00cfe8);
            border-radius: var(--app-radius, 8px);
        }
        .rpt .rpt-note i { color: var(--app-info, #00cfe8); }
        .rpt .rpt-note__title { font-weight: 700; margin-bottom: .25rem; color: var(--app-text, #2f3349); }
        .rpt .rpt-note p { font-size: .92rem; margin: 0; color: var(--app-muted, #6e6b7b); }
        .rpt .rpt-chip { font-size: .75rem; font-weight: 600; border: 1px solid transparent; border-radius: var(--app-radius, 6px); }
        .rpt .rpt-chip--warn {
            background: color-mix(in srgb, var(--app-warning, #ff9f43) 15%, transparent);
            color: var(--app-warning, #ff9f43);
            border-color: color-mix(in srgb, var(--app-warning, #ff9f43) 35%, transparent);
        }
        .rpt .rpt-cell-btn { padding: 1px 5px; font-size: .72rem; line-height: 1.5; border-radius: var(--app-button-radius, 6px); }

        /* ═══════════ جداول التحصيل الأربعة — تصميمٌ واحدٌ مضغوط (cr-) ═══════════
           كانت التفاصيلُ سبعةَ عشر عموداً بشارةٍ مصمتةٍ في كلّ خليّة، فاتّسع الجدولُ
           فراغاً ولوناً. الآن: خليّةٌ من سطرين (رئيسيٌّ وثانويٌّ باهت) تجمع ما كان
           عموداً مستقلاً (الكود والحالة والفرع تحت العميل، إجماليُّ الفاتورة ومتبقّيها
           تحت رقمها، الخزينةُ ونوعُها معاً)، وشاراتٌ شفّافةٌ خفيفة، وحشوٌ صغير. */
        .rpt .rpt-table.rpt-table--cr { font-size: .78rem; }
        .rpt .rpt-table.rpt-table--cr thead th { padding: 6px 6px; font-size: .7rem; font-weight: 700; white-space: nowrap; text-align: center; line-height: 1.3; }
        .rpt .rpt-table.rpt-table--cr tbody td,
        .rpt .rpt-table.rpt-table--cr tfoot td { padding: 3px 6px; font-size: .78rem; line-height: 1.3; vertical-align: middle; text-align: center; white-space: normal; }
        .rpt .rpt-table.rpt-table--cr tbody tr.cr-row td { height: 34px; }
        .rpt .rpt-table--cr tbody tr:nth-child(even):not(.rpt-group):not(.rpt-subtotal) td { background: color-mix(in srgb, var(--rpt-line, #e8e6f0) 18%, transparent); }
        .rpt .rpt-table--cr tbody tr.cr-row:hover td { background: var(--rpt-brand-soft, rgba(115, 103, 240, .08)); }
        .rpt .rpt-table--cr tfoot td { font-weight: 700; background: var(--rpt-brand-soft, rgba(115, 103, 240, .08)); border-top: 2px solid var(--rpt-line, #e8e6f0); padding: 5px 6px; }
        .rpt .rpt-table--cr .cr-start, .rpt .rpt-table--cr th.cr-start { text-align: start; }
        .rpt .rpt-table--cr .cr-nowrap { white-space: nowrap; }
        .rpt .rpt-table--cr .cr-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* أعمدةُ التفاصيل — عرضٌ على قدر المحتوى لا حدٌّ أدنى فضفاض */
        .rpt .rpt-table--cr .cr-col-n { width: 34px; color: var(--rpt-muted); font-size: .7rem; }
        .rpt .rpt-table--cr .cr-col-date { width: 84px; white-space: nowrap; font-size: .72rem; }
        .rpt .rpt-table--cr .cr-col-client { min-width: 170px; max-width: 240px; }
        .rpt .rpt-table--cr .cr-col-doc { min-width: 108px; white-space: nowrap; }
        .rpt .rpt-table--cr .cr-col-emp { max-width: 130px; }
        .rpt .rpt-table--cr .cr-col-acc { max-width: 150px; }
        .rpt .rpt-table--cr .cr-col-cat { white-space: nowrap; }
        .rpt .rpt-table--cr .cr-col-amt { min-width: 92px; white-space: nowrap; }
        .rpt .rpt-table--cr .cr-col-kind { white-space: nowrap; }
        .rpt .rpt-table--cr .cr-col-note { max-width: 150px; color: var(--rpt-muted); font-size: .7rem; }
        .rpt .rpt-table--cr .cr-col-act { width: 34px; padding-inline: 2px; }
        .rpt .rpt-table--cr .cr-col-share { min-width: 140px; }
        /* السطران داخل الخليّة */
        .rpt .rpt-table--cr .cr-primary { font-weight: 600; color: var(--rpt-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25; }
        .rpt .rpt-table--cr .cr-secondary { font-size: .68rem; color: var(--rpt-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25; margin-top: 1px; font-weight: 500; }
        .rpt .rpt-table--cr .cr-dot { display: inline-block; width: 3px; height: 3px; border-radius: 50%; background: currentColor; margin: 0 4px; vertical-align: middle; opacity: .55; }
        .rpt .rpt-table--cr .cr-status { color: var(--s, #6c757d); font-weight: 700; }
        .rpt .rpt-table--cr .cr-status::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--s, #6c757d); margin-inline-end: 3px; vertical-align: middle; }
        .rpt .rpt-table--cr .cr-more { display: inline-block; font-size: .6rem; font-weight: 700; padding: 0 5px; line-height: 14px; border-radius: 7px; background: var(--rpt-brand-soft, rgba(115, 103, 240, .12)); color: var(--rpt-brand, #7367f0); margin-inline-start: 3px; vertical-align: middle; }
        .rpt .rpt-table--cr .cr-method { font-weight: 700; }
        .rpt .rpt-table--cr .cr-method.is-cash { color: var(--rpt-ok, #28c76f); }
        .rpt .rpt-table--cr .cr-method.is-bank { color: var(--rpt-info, #00cfe8); }
        /* شارةُ الفئة: خلفيةٌ شفّافة بلون الفئة بدل الحشوة المصمتة */
        .rpt .rpt-table--cr .cr-tag {
            display: inline-block; font-size: .66rem; font-weight: 700; padding: 0 6px; line-height: 16px; border-radius: 8px; white-space: nowrap;
            color: var(--c, #6c757d); background: color-mix(in srgb, var(--c, #6c757d) 13%, transparent); border: 1px solid color-mix(in srgb, var(--c, #6c757d) 32%, transparent);
        }
        .rpt .rpt-table--cr .rpt-pill { font-size: .66rem; padding: 1px 6px; line-height: 1.3; white-space: nowrap; }
        .rpt .rpt-table--cr .cr-arrow { margin: 0 3px; font-size: .72rem; vertical-align: middle; }
        .rpt .rpt-table--cr .cr-row--out td { color: var(--rpt-muted); }
        .rpt .rpt-table--cr .cr-indent { display: inline-block; width: calc(var(--lvl, 0) * 14px); }
        .rpt .rpt-table--cr .cr-share { position: relative; height: 16px; background: color-mix(in srgb, var(--rpt-line, #e8e6f0) 45%, transparent); border-radius: 8px; overflow: hidden; }
        .rpt .rpt-table--cr .cr-share__bar { position: absolute; inset-inline-start: 0; top: 0; bottom: 0; background: var(--rpt-brand-soft, rgba(115, 103, 240, .25)); border-radius: 8px; }
        .rpt .rpt-table--cr .cr-share__pct { position: relative; font-size: .66rem; font-weight: 700; line-height: 16px; }
        .rpt .rpt-table--cr .rpt-cell--neg { color: var(--rpt-bad, #ea5455); background: var(--app-danger-soft, rgba(234, 84, 85, .1)); font-weight: 700; }
        .rpt .rpt-table--cr .cr-foot-count td { font-weight: 500; background: transparent; border-top-width: 1px; }
        .rpt .rpt-table--cr tbody tr.rpt-group td { text-align: start; padding: 4px 8px; font-size: .72rem; }
        .rpt .rpt-table--cr tbody tr.rpt-subtotal td { padding: 3px 8px; font-size: .7rem; }
        .rpt .rpt-table--cr .ag-actions-btn { width: 24px; height: 24px; font-size: .7rem; }
        .rpt .cr-report .ag-pager { padding: 6px 12px; }
        .rpt .cr-report .ag-table-head { padding-top: 8px; padding-bottom: 8px; }
        .rpt .cr-report .ag-table-head h4 { font-size: .92rem; }
        /* روابطُ الصفحات من الخادم بمظهر أزرار `ag-page` نفسِه */
        .rpt .ag-pages a.ag-page { text-decoration: none; }
        .rpt .ag-pages .ag-page.is-disabled { pointer-events: none; opacity: .45; }
        .rpt .ag-pages .ag-page--gap { border: 0; background: transparent; }

        /* ═══════════ الشبكة الشهرية (mx-) — على نموذج 2026-09-22 ═══════════
           عمودُ العميل ورأسُ الشهور وصفُّ الإجمالي مثبَّتةٌ ثلاثتُها؛ خليّةُ الشهر رقمٌ
           بكسورٍ مصغَّرة على أربع درجاتٍ من تظليل لون النظام، والزياراتُ سطرٌ باهتٌ
           تحته (الفائتةُ حمراء)، والصفرُ نقطةٌ خافتة، والزرُّ لا يظهر إلا عند المرور. */
        .rpt .mx-scroll { max-height: 72vh; overflow: auto; overscroll-behavior: contain; }
        .rpt .rpt-table.mx-table { font-size: .78rem; border-collapse: separate; border-spacing: 0; min-width: 100%; font-variant-numeric: tabular-nums; }
        .rpt .rpt-table.mx-table thead th {
            position: sticky; top: 0; z-index: 6; padding: 7px 8px 5px; font-size: .74rem; font-weight: 700; white-space: nowrap; text-align: center; line-height: 1.25;
            background: var(--app-table-header-bg, var(--app-primary-soft, #f6f5ff)); color: var(--rpt-ink); border-bottom: 1px solid var(--rpt-line-strong, var(--rpt-line, #d5dbe3));
        }
        .rpt .rpt-table.mx-table thead th .mx-sub { display: block; font-size: .64rem; font-weight: 500; color: var(--rpt-muted); margin-top: 1px; }
        .rpt .rpt-table.mx-table .mx-q { border-inline-start: 1px solid var(--rpt-line, #e8e6f0); }
        .rpt .rpt-table.mx-table tbody td { padding: 5px 8px; line-height: 1.25; vertical-align: middle; text-align: center; white-space: nowrap; border-bottom: 1px solid var(--rpt-line, #e8e6f0); background: var(--rpt-surface, #fff); }
        .rpt .rpt-table.mx-table tbody tr.mx-row td { height: 52px; }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover td { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 5%, var(--rpt-surface, #fff)); }
        /* الأعمدةُ المثبَّتة: الرقمُ والعميل، ورأسُها فوق كلِّ شيء */
        .rpt .rpt-table.mx-table .mx-fix { position: sticky; z-index: 4; }
        .rpt .rpt-table.mx-table thead .mx-fix, .rpt .rpt-table.mx-table tfoot .mx-fix { z-index: 8; }
        .rpt .rpt-table.mx-table .mx-col-n { inset-inline-start: 0; width: 42px; min-width: 42px; max-width: 42px; color: var(--rpt-muted); font-size: .7rem; }
        .rpt .rpt-table.mx-table .mx-col-client { inset-inline-start: 42px; min-width: 290px; max-width: 290px; text-align: start; border-inline-end: 1px solid var(--rpt-line-strong, #d5dbe3); box-shadow: -6px 0 10px -8px rgba(16, 24, 40, .18); }
        .rpt .rpt-table.mx-table .mx-name { font-weight: 700; color: var(--rpt-ink); font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25; }
        .rpt .rpt-table.mx-table .mx-name .rpt-client-link { border-bottom: 0; color: var(--rpt-brand-ink, var(--rpt-brand, #7367f0)); }
        .rpt .rpt-table.mx-table .mx-meta { display: flex; align-items: center; gap: 6px; margin-top: 3px; font-size: .68rem; color: var(--rpt-muted); white-space: nowrap; overflow: hidden; }
        .rpt .rpt-table.mx-table .mx-meta .mx-code { font-weight: 600; color: var(--rpt-ink); opacity: .75; }
        .rpt .rpt-table.mx-table .mx-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--rpt-line-strong, #c2c9d1); flex: 0 0 3px; }
        .rpt .rpt-table.mx-table .mx-meta .cr-tag { font-size: .64rem; }
        /* آخر فاتورة / عهدة — نصٌّ بسطرين بلا أيقونات */
        .rpt .rpt-table.mx-table .mx-col-last { min-width: 150px; text-align: start; border-inline-end: 1px solid var(--rpt-line, #e8e6f0); }
        .rpt .rpt-table.mx-table .mx-last { display: flex; align-items: baseline; gap: 6px; font-size: .74rem; line-height: 1.3; color: var(--rpt-ink); }
        .rpt .rpt-table.mx-table .mx-last b { font-weight: 700; }
        .rpt .rpt-table.mx-table .mx-last b small { font-size: .62rem; color: var(--rpt-muted); font-weight: 500; }
        .rpt .rpt-table.mx-table .mx-last small { color: var(--rpt-muted); font-size: .64rem; }
        .rpt .rpt-table.mx-table .mx-last--dep b { color: var(--rpt-warn, #e0921a); }
        .rpt .rpt-table.mx-table .mx-last--dep.is-zero b { color: var(--rpt-muted); }
        /* خلايا الشهور */
        .rpt .rpt-table.mx-table .mx-col-m { min-width: 90px; }
        .rpt .rpt-table.mx-table .mx-cell { position: relative; min-width: 90px; }
        .rpt .rpt-table.mx-table .mx-v { font-size: .78rem; font-weight: 600; color: var(--rpt-ink); line-height: 1.2; }
        .rpt .rpt-table.mx-table .mx-v small { font-size: .62rem; color: var(--rpt-muted); font-weight: 500; }
        .rpt .rpt-table.mx-table .mx-vis { font-size: .62rem; color: var(--rpt-muted); opacity: .85; margin-top: 2px; line-height: 1.2; }
        .rpt .rpt-table.mx-table .mx-vis .miss { color: var(--rpt-bad, #ea5455); font-weight: 700; }
        .rpt .rpt-table.mx-table .mx-cell.is-h1 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 7%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-cell.is-h2 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 15%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-cell.is-h3 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 26%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-cell.is-h4 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 38%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-cell.is-h3 .mx-v, .rpt .rpt-table.mx-table .mx-cell.is-h4 .mx-v { font-weight: 700; color: color-mix(in srgb, var(--rpt-brand, #7367f0) 70%, #000); }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover td.mx-cell.is-h1 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 11%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover td.mx-cell.is-h2 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 19%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover td.mx-cell.is-h3 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 30%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover td.mx-cell.is-h4 { background: color-mix(in srgb, var(--rpt-brand, #7367f0) 42%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-cell.is-neg { background: var(--app-danger-soft, rgba(234, 84, 85, .1)); }
        .rpt .rpt-table.mx-table .mx-cell.is-neg .mx-v { color: var(--rpt-bad, #ea5455); font-weight: 700; }
        .rpt .rpt-table.mx-table .mx-zero { color: var(--rpt-line-strong, #cbd2d9); font-size: .8rem; line-height: 1.2; }
        .rpt .rpt-table.mx-table .mx-btn {
            position: absolute; inset-inline-end: 4px; bottom: 4px; width: 18px; height: 18px; padding: 0; border: 0; border-radius: 5px; font-size: .58rem; line-height: 18px;
            background: var(--rpt-brand, #7367f0); color: #fff; opacity: 0; transition: opacity .12s;
        }
        .rpt .rpt-table.mx-table .mx-cell:hover .mx-btn, .rpt .rpt-table.mx-table .mx-btn:focus-visible { opacity: 1; }
        /* الإجمالي وحصّتُه */
        .rpt .rpt-table.mx-table .mx-col-total { min-width: 124px; border-inline-start: 1px solid var(--rpt-line-strong, #d5dbe3); background: color-mix(in srgb, var(--rpt-line, #e8e6f0) 18%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table .mx-total { font-size: .84rem; font-weight: 800; color: var(--rpt-brand-ink, var(--rpt-brand, #7367f0)); line-height: 1.2; }
        .rpt .rpt-table.mx-table .mx-total small { font-size: .62rem; color: var(--rpt-muted); font-weight: 500; }
        .rpt .rpt-table.mx-table .mx-total.rpt-neg { color: var(--rpt-bad, #ea5455); }
        .rpt .rpt-table.mx-table .mx-share { display: block; height: 4px; margin: 5px 8px 0; border-radius: 2px; background: var(--rpt-line, #e8ecf0); overflow: hidden; }
        .rpt .rpt-table.mx-table .mx-share i { display: block; height: 100%; background: var(--rpt-brand, #7367f0); border-radius: 2px; }
        .rpt .rpt-table.mx-table .mx-pct { font-size: .62rem; color: var(--rpt-muted); margin-top: 3px; line-height: 1.2; }
        .rpt .rpt-table.mx-table .mx-col-act { width: 44px; min-width: 44px; padding-inline: 4px; }
        .rpt .rpt-table.mx-table .mx-col-act .ag-actions-btn { width: 26px; height: 26px; font-size: .72rem; opacity: .5; }
        .rpt .rpt-table.mx-table tbody tr.mx-row:hover .ag-actions-btn { opacity: 1; }
        /* صفُّ الإجمالي مثبَّتٌ أسفل الشبكة */
        .rpt .rpt-table.mx-table tfoot td { position: sticky; bottom: 0; z-index: 5; padding: 8px 8px; text-align: center; white-space: nowrap; font-weight: 800; font-size: .8rem; color: var(--rpt-ink); background: color-mix(in srgb, var(--rpt-brand, #7367f0) 9%, var(--rpt-surface, #fff)); border-top: 2px solid color-mix(in srgb, var(--rpt-brand, #7367f0) 30%, var(--rpt-surface, #fff)); }
        .rpt .rpt-table.mx-table tfoot .mx-grand { color: var(--rpt-brand-ink, var(--rpt-brand, #7367f0)); font-size: .9rem; }
        .rpt .rpt-table.mx-table tfoot .mx-foot-lbl { display: block; font-weight: 700; }
        .rpt .rpt-table.mx-table tfoot .mx-foot-lbl small { display: block; font-weight: 500; color: var(--rpt-muted); font-size: .64rem; }
        /* مفتاحُ الألوان في رأس البطاقة */
        .rpt .mx-legend { display: inline-flex; align-items: center; gap: 10px; margin-inline-end: 10px; padding-inline-end: 12px; border-inline-end: 1px solid var(--rpt-line, #e8e6f0); font-size: .68rem; color: var(--rpt-muted); font-weight: 500; }
        .rpt .mx-legend .mx-scale { display: inline-flex; gap: 2px; }
        .rpt .mx-legend .mx-scale i { display: block; width: 15px; height: 9px; border-radius: 2px; }
        .rpt .mx-legend .mx-key { display: inline-flex; align-items: center; gap: 4px; }
        .rpt .mx-legend .mx-key b { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        @media print {
            .rpt .mx-scroll { max-height: none; overflow: visible; }
            .rpt .rpt-table.mx-table .mx-btn, .rpt .rpt-table.mx-table .mx-share, .rpt .mx-legend { display: none; }
            .rpt .rpt-table.mx-table thead th, .rpt .rpt-table.mx-table tfoot td, .rpt .rpt-table.mx-table .mx-fix { position: static; box-shadow: none; }
        }

        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }

        /* ═══════════════════════ تصميم الشاشة (cr-) ═══════════════════════ */
        .cr {
            --cr-brand: var(--app-primary, #1e63e9);
            --cr-brand-soft: color-mix(in srgb, var(--cr-brand) 10%, transparent);
            --cr-ink: var(--app-text, #1f2a44);
            --cr-muted: var(--app-muted, #7a8599);
            --cr-line: var(--app-border, #e6eaf2);
            --cr-surface: var(--app-surface, #fff);
            --cr-ok: var(--app-success, #22a06b);
            --cr-bad: var(--app-danger, #e5484d);
            --cr-warn: var(--app-warning, #f5a524);
            --cr-violet: #7c6cf2;
            --cr-radius: var(--app-radius, 12px);
        }
        .cr .cr-panel {
            background: var(--cr-surface);
            border: 1px solid var(--cr-line);
            border-radius: var(--cr-radius);
            box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
            margin-bottom: 16px;
        }

        /* ——— الرأس ——— */
        .cr .cr-head { display: flex; align-items: center; gap: 12px; margin: 4px 0 16px; }
        .cr .cr-head__icon {
            width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
            background: var(--cr-brand-soft); color: var(--cr-brand); font-size: 1.35rem;
        }
        .cr .cr-head__actions { margin-inline-start: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .cr .cr-head__actions .btn { border-radius: 10px; }
        .cr .cr-view-group .btn { border-radius: 8px; }
        .cr .cr-table .cr-badge { display: inline-block; font-size: .7rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; white-space: nowrap; }
        .cr .cr-table tr.cr-subtotal td { background: color-mix(in srgb, var(--cr-brand) 3%, var(--cr-surface)); font-weight: 800; }
        .cr .cr-head h1 { font-size: 1.35rem; font-weight: 800; color: var(--cr-ink); margin: 0; }
        .cr .cr-crumbs { display: flex; gap: 6px; align-items: center; font-size: .78rem; color: var(--cr-muted); margin-top: 2px; }
        .cr .cr-crumbs a { color: var(--cr-muted); }
        .cr .cr-crumbs a:hover { color: var(--cr-brand); }
        .cr .cr-crumbs .is-current { color: var(--cr-brand); font-weight: 600; }

        /* ——— شريط الفلاتر ——— */
        .cr .cr-filters { padding: 14px 16px; }
        .cr .cr-bar { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
        .cr .cr-field { display: flex; flex-direction: column; gap: 6px; min-width: 150px; flex: 1 1 150px; }
        .cr .cr-field label { font-size: .78rem; font-weight: 700; color: var(--cr-ink); margin: 0; }
        .cr .cr-field .form-control { height: 40px; border-radius: 8px; border-color: var(--cr-line); font-size: .85rem; }
        .cr .cr-more-btn, .cr .cr-submit {
            height: 44px; border-radius: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; padding: 0 18px;
        }
        .cr .cr-more-btn { background: var(--cr-surface); border: 1px solid var(--cr-line); color: var(--cr-ink); }
        .cr .cr-more-btn i { color: var(--cr-brand); font-size: 1.1rem; }
        .cr .cr-more-btn[aria-expanded="true"] { border-color: var(--cr-brand); background: var(--cr-brand-soft); }
        .cr .cr-more-btn .cr-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--cr-brand); }
        .cr .cr-submit { background: var(--cr-brand); color: var(--app-on-primary, #fff); border: 0; min-width: 150px; justify-content: center; }
        .cr .cr-submit:hover { filter: brightness(1.06); color: var(--app-on-primary, #fff); }
        .cr .cr-more { border-top: 1px dashed var(--cr-line); margin-top: 14px; padding-top: 14px; }
        .cr .cr-more .period-tabs { margin-bottom: 10px; }
        .cr .cr-more label { font-size: .78rem; font-weight: 700; }
        .cr .cr-more__foot { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }

        /* ——— البطاقات ——— */
        .cr .cr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
        .cr .cr-kpi { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px; margin: 0; }
        .cr .cr-kpi__label { font-size: .8rem; color: var(--cr-muted); font-weight: 600; }
        .cr .cr-kpi__value { font-size: 1.55rem; font-weight: 800; color: var(--cr-ink); line-height: 1.3; margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
        .cr .cr-kpi__unit { font-size: .75rem; color: var(--cr-muted); }
        .cr .cr-kpi__icon {
            width: 52px; height: 52px; flex: 0 0 52px; border-radius: 12px; display: grid; place-items: center; font-size: 1.5rem;
            background: color-mix(in srgb, var(--cr-tone) 12%, transparent); color: var(--cr-tone);
        }
        .cr .cr-tone-brand { --cr-tone: var(--cr-brand); }
        .cr .cr-tone-violet { --cr-tone: var(--cr-violet); }
        .cr .cr-tone-ok { --cr-tone: var(--cr-ok); }
        .cr .cr-tone-bad { --cr-tone: var(--cr-bad); }
        .cr .cr-tone-warn { --cr-tone: var(--cr-warn); }
        /* صفُّ البطاقات الستّ — صغيرةٌ وفي سطرٍ واحد */
        .cr .cr-kpis--six { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
        .cr .cr-kpis--six .cr-kpi { padding: 10px 12px; gap: 8px; }
        .cr .cr-kpis--six .cr-kpi__body { min-width: 0; }
        .cr .cr-kpis--six .cr-kpi__label { font-size: .7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr .cr-kpis--six .cr-kpi__value { font-size: 1.12rem; margin: 2px 0 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr .cr-kpis--six .cr-kpi__value small { font-weight: 500; color: var(--cr-muted); }
        .cr .cr-kpis--six .cr-kpi__unit { font-size: .66rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr .cr-kpis--six .cr-kpi__icon { width: 36px; height: 36px; flex-basis: 36px; font-size: 1.05rem; border-radius: 9px; }
        .cr .cr-kpi--brand .cr-kpi__value { color: var(--cr-brand); }
        .cr .cr-kpi--bad .cr-kpi__value { color: var(--cr-bad); }
        .cr .cr-kpi--warn .cr-kpi__value { color: var(--cr-warn); }
        @media (max-width: 1400px) { .cr .cr-kpis--six { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 768px) { .cr .cr-kpis--six { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        /* ——— المخطّطات ——— */
        .cr .cr-charts { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 16px; margin-bottom: 16px; }
        .cr .cr-chart { padding: 16px 18px; margin: 0; min-height: 300px; }
        .cr .cr-chart__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
        .cr .cr-chart__head h4 { font-size: 1rem; font-weight: 800; color: var(--cr-ink); margin: 0; }
        .cr .cr-legend-chips { display: flex; gap: 6px; flex-wrap: wrap; }
        .cr .cr-legend-chip { font-size: .72rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; background: var(--cr-brand-soft); color: var(--cr-ink); display: inline-flex; align-items: center; gap: 5px; }
        .cr .cr-legend-chip i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        /* ApexCharts داخل صفحةٍ RTL: الحاوية LTR وإلّا انقلبت الأسماء فوق الأعمدة */
        .cr .cr-apex { direction: ltr; }
        .cr .cr-chart { container-type: inline-size; }
        .cr .cr-donut { display: grid; grid-template-columns: minmax(0, 1fr) 200px; gap: 12px; align-items: center; }
        @container (max-width: 520px) {
            .cr .cr-donut { grid-template-columns: minmax(0, 1fr); }
            .cr .cr-donut #crGroupChart { order: -1; }
        }
        .cr .cr-legend { width: 100%; font-size: .8rem; border-collapse: collapse; table-layout: fixed; }
        .cr .cr-legend td.name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr .cr-legend td.num { width: 96px; }
        .cr .cr-legend td { padding: 7px 4px; border-bottom: 1px solid var(--cr-line); color: var(--cr-ink); vertical-align: middle; }
        .cr .cr-legend tr:last-child td { border-bottom: 0; }
        .cr .cr-legend .cr-swatch { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .cr .cr-legend .num { font-weight: 700; text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cr .cr-legend .pct { color: var(--cr-muted); text-align: end; width: 48px; }
        .cr .cr-empty-chart { display: grid; place-items: center; min-height: 220px; color: var(--cr-muted); font-size: .85rem; }

        /* ——— الجدول ——— */
        .cr .cr-card { padding: 0; }
        .cr .cr-card__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; }
        .cr .cr-card__head h4 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--cr-ink); }
        .cr .cr-card__tools { display: flex; gap: 6px; }
        .cr .cr-card__tools .btn { border-radius: 8px; font-size: .78rem; padding: 5px 10px; }
        .cr .cr-table-wrap { padding: 0 16px; }
        .cr .cr-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
        .cr .cr-table thead th {
            background: var(--app-table-header-bg, color-mix(in srgb, var(--cr-brand) 6%, var(--cr-surface)));
            color: var(--cr-ink); font-weight: 700; padding: 11px 10px; text-align: center; white-space: nowrap;
            border-top: 1px solid var(--cr-line); border-bottom: 1px solid var(--cr-line);
        }
        .cr .cr-table thead th:first-child { border-inline-start: 1px solid var(--cr-line); border-start-start-radius: 8px; }
        .cr .cr-table thead th:last-child { border-inline-end: 1px solid var(--cr-line); border-start-end-radius: 8px; }
        .cr .cr-table tbody td {
            padding: 9px 10px; text-align: center; color: var(--cr-ink); border-bottom: 1px solid var(--cr-line);
            vertical-align: middle; font-variant-numeric: tabular-nums;
        }
        .cr .cr-table tbody td:first-child { border-inline-start: 1px solid var(--cr-line); }
        .cr .cr-table tbody td:last-child { border-inline-end: 1px solid var(--cr-line); }
        .cr .cr-table tbody tr.cr-row:hover td { background: var(--cr-brand-soft); }
        .cr .cr-table tbody tr.cr-row td { white-space: nowrap; }
        .cr .cr-table td.cr-client { text-align: start; min-width: 170px; white-space: normal !important; }
        .cr .cr-table .cr-sub { display: block; font-size: .7rem; color: var(--cr-muted); margin-top: 2px; }
        .cr .cr-table .cr-inv { font-weight: 600; direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
        .cr .cr-table .cr-more-inv { font-size: .68rem; background: var(--cr-brand-soft); color: var(--cr-brand); border-radius: 10px; padding: 1px 6px; margin-inline-start: 4px; }
        .cr .cr-table .is-neg { color: var(--cr-bad); font-weight: 700; }
        .cr .cr-table .is-due { color: var(--cr-bad); }
        .cr .cr-table tr.cr-grp td {
            background: color-mix(in srgb, var(--cr-brand) 4%, var(--cr-surface)); text-align: start; font-size: .78rem; color: var(--cr-ink);
        }
        .cr .cr-table tr.cr-grp .cr-grp__sum { float: inline-end; font-weight: 800; color: var(--cr-brand); }
        .cr .cr-table tfoot td { padding: 10px; font-weight: 800; text-align: center; color: var(--cr-ink); }
        .cr .cr-view-btn {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; font-size: .74rem; font-weight: 700;
            background: var(--cr-brand); color: var(--app-on-primary, #fff); border: 0; border-radius: 6px;
        }
        .cr .cr-view-btn:hover { filter: brightness(1.08); }
        .cr .cr-view-btn[disabled] { opacity: .45; cursor: not-allowed; }
        .cr .cr-foot { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px 16px; }
        .cr .cr-foot__total { margin-inline-start: auto; font-size: .78rem; color: var(--cr-muted); }
        .cr .cr-pagesize { width: 72px; height: 32px; border-radius: 8px; border: 1px solid var(--cr-line); background: var(--cr-surface); color: var(--cr-ink); font-size: .78rem; }
        .cr .cr-pager { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .cr .cr-pager button {
            min-width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--cr-line); background: var(--cr-surface);
            color: var(--cr-ink); font-size: .78rem; font-weight: 600;
        }
        .cr .cr-pager button.is-active { background: var(--cr-brand); border-color: var(--cr-brand); color: var(--app-on-primary, #fff); }
        .cr .cr-pager button:disabled { opacity: .45; }
        .cr .cr-pager .cr-gap { color: var(--cr-muted); padding: 0 2px; }


        .rpt .ag-tabs--views { margin-bottom: 0; }
        .rpt .ag-tab i { margin-inline-end: 4px; }
        .rpt .cr-filters-card { margin-bottom: 16px; }
        /* ═══════════ جداولُ مضغوطة — بلا مساحاتٍ فارغة ═══════════
           حشوُ الخلايا والشارات أصغر، والعرضُ على قدر المحتوى لا حدٌّ أدنى ثابت */
        .rpt .rpt-table { font-size: .76rem; }
        .rpt .rpt-table thead th { padding: 7px 6px; font-size: .72rem; }
        .rpt .rpt-table tbody td { padding: 5px 6px; line-height: 1.35; white-space: nowrap; }
        .rpt .rpt-table tfoot td { padding: 7px 6px; }
        .rpt .rpt-table .rpt-pill { padding: 1px 7px; font-size: .66rem; line-height: 1.5; }
        .rpt .rpt-table .rpt-name { font-weight: 600; }
        .rpt .rpt-table .rpt-name small { display: inline; margin-inline-start: 6px; font-size: .66rem; }
        .rpt .ag-table td.rpt-name { white-space: nowrap; min-width: 0; max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
        .rpt .ag-table td.cr-note { max-width: 150px; overflow: hidden; text-overflow: ellipsis; color: var(--rpt-muted); }
        .rpt .ag-table td .text-muted.rpt-num { display: inline; margin-inline-start: 4px; font-size: .64rem !important; }
        .rpt .ag-table .ag-actions-btn { width: 26px; height: 26px; font-size: .75rem; }
        .rpt .rpt-table tbody tr.rpt-group td { padding: 6px 8px; font-size: .74rem; }
        .rpt .rpt-table tbody tr.rpt-subtotal td { padding: 4px 8px; font-size: .72rem; }
        .rpt .ag-table tr.rpt-group .rpt-pill { margin-inline-start: 4px; }
        .rpt .ag-table-head { padding-top: 10px; padding-bottom: 10px; }
        .rpt .ag-pager { padding: 8px 14px; }

        /* ——— أزرار الفترة في رأس الصفحة: شريطٌ صغير ——— */
        .cr .cr-periods {
            margin-inline-start: auto; display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px;
            background: var(--cr-surface); border: 1px solid var(--cr-line); border-radius: 22px;
        }
        .cr .cr-periods .btn {
            padding: 3px 12px; font-size: .72rem; font-weight: 600; line-height: 1.6; border-radius: 18px; border: 0;
            min-width: 0; box-shadow: none; transform: none;
        }
        .cr .cr-periods .btn-outline-primary { background: transparent; color: var(--cr-muted); }
        .cr .cr-periods .btn-outline-primary:hover { background: var(--cr-brand-soft); color: var(--cr-brand); }
        .cr .cr-periods .btn-primary { background: var(--cr-brand); color: var(--app-on-primary, #fff); }
        .cr .cr-periods + .cr-head__actions { margin-inline-start: 0; }
        .cr .cr-head { flex-wrap: wrap; }
        .cr .cr-head__actions .btn { padding: 6px 12px; font-size: .78rem; }

        /* ——— جدول التفاصيل: أخفّ وأضيق ——— */
        .cr .cr-table-wrap .rpt-scroll { max-height: 72vh; overflow: auto; border: 1px solid var(--cr-line); border-radius: 10px; }
        .cr .cr-table { font-size: .76rem; }
        .cr .cr-table thead th {
            position: sticky; top: 0; z-index: 2; padding: 9px 8px; font-size: .72rem; color: var(--cr-muted);
            border-top: 0; border-inline: 0 !important; border-radius: 0 !important;
        }
        .cr .cr-table tbody td { padding: 7px 8px; border-inline: 0 !important; }
        .cr .cr-table tbody tr.cr-row:nth-of-type(even) td { background: color-mix(in srgb, var(--cr-ink) 2%, var(--cr-surface)); }
        .cr .cr-table tbody tr.cr-row:hover td { background: var(--cr-brand-soft); }
        .cr .cr-table .badge {
            font-size: .68rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; white-space: nowrap;
        }
        .cr .cr-table .cr-badge { font-size: .66rem; padding: 2px 8px; border-radius: 20px; }
        .cr .cr-table td.cr-client { min-width: 190px; }
        .cr .cr-table td.cr-client .rpt-client-link { font-weight: 600; }
        .cr .cr-table td.cr-note { max-width: 180px; overflow: hidden; text-overflow: ellipsis; color: var(--cr-muted); }
        .cr .cr-table td.cr-amount { font-weight: 800; }
        .cr .cr-table tr.cr-grp td {
            padding: 8px 12px; font-size: .76rem; font-weight: 600;
            box-shadow: inset -3px 0 0 var(--cr-brand);
        }
        [dir="ltr"] .cr .cr-table tr.cr-grp td { box-shadow: inset 3px 0 0 var(--cr-brand); }
        .cr .cr-table tr.cr-subtotal td { padding: 6px 12px; font-size: .74rem; color: var(--cr-muted); background: var(--cr-surface); }
        .cr .cr-table tr.cr-subtotal td[style*="color"] { color: var(--cr-ok) !important; }
        .cr .cr-table tfoot td {
            position: sticky; bottom: 0; z-index: 2;
            background: color-mix(in srgb, var(--cr-brand) 8%, var(--cr-surface)); border-top: 1px solid var(--cr-line);
        }
        .cr .cr-actions { display: inline-flex; gap: 4px; }
        .cr .cr-icon-btn {
            width: 28px; height: 28px; display: inline-grid; place-items: center; border-radius: 7px; font-size: .85rem;
            border: 1px solid var(--cr-line); background: var(--cr-surface); color: var(--cr-brand); padding: 0;
        }
        .cr .cr-icon-btn:hover { background: var(--cr-brand); color: var(--app-on-primary, #fff); border-color: var(--cr-brand); text-decoration: none; }
        .cr .cr-icon-btn--solid { background: var(--cr-brand); color: var(--app-on-primary, #fff); border-color: var(--cr-brand); }

        @media (max-width: 1199px) {
            .cr .cr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cr .cr-charts { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 575px) {
            .cr .cr-kpis { grid-template-columns: minmax(0, 1fr); }
            .cr .cr-donut { grid-template-columns: minmax(0, 1fr); }
        }

        /* ═══════════ نافذة «ملاحظات الفاتورة» ═══════════
           خارج `.cr` (النافذة تُنقل إلى body) فمتغيّراتها معرَّفةٌ عليها. */
        .cr-nm {
            --cr-brand: var(--app-primary, #1e63e9);
            --cr-brand-soft: color-mix(in srgb, var(--cr-brand) 10%, transparent);
            --cr-ink: var(--app-text, #1f2a44);
            --cr-muted: var(--app-muted, #7a8599);
            --cr-line: var(--app-border, #e6eaf2);
            --cr-surface: var(--app-surface, #fff);
            --cr-ok: var(--app-success, #22a06b);
            --cr-bad: var(--app-danger, #e5484d);
            --cr-warn: var(--app-warning, #f5a524);
            border: 0; border-radius: 14px; overflow: hidden; background: var(--cr-surface); color: var(--cr-ink);
            font-family: var(--app-font-family, 'Cairo', sans-serif);
        }
        .cr-nm__head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--cr-line); }
        .cr-nm__title { display: flex; align-items: center; gap: 10px; }
        .cr-nm__title h5 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--cr-ink); }
        .cr-nm__title .cr-ico { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; background: var(--cr-brand-soft); color: var(--cr-brand); }
        .cr-nm__close { background: transparent; border: 0; font-size: 1.5rem; line-height: 1; color: var(--cr-muted); }
        .cr-nm__body { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 16px; padding: 16px 20px 20px; }
        .cr-nm__box { border: 1px solid var(--cr-line); border-radius: 12px; background: var(--cr-surface); }
        .cr-nm__info { padding: 16px; display: flex; flex-direction: column; gap: 16px; align-self: start; }
        .cr-nm__item { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .cr-nm__item small { display: block; font-size: .72rem; color: var(--cr-muted); }
        .cr-nm__item strong { display: block; font-size: .88rem; font-weight: 700; color: var(--cr-ink); word-break: break-word; }
        .cr-nm__item a { color: var(--cr-ink); }
        .cr-nm__item a:hover { color: var(--cr-brand); }
        .cr-nm__item .cr-ico, .cr-nm__sum .cr-ico {
            width: 34px; height: 34px; flex: 0 0 34px; border-radius: 9px; display: grid; place-items: center; background: var(--cr-brand-soft); color: var(--cr-brand);
        }
        .cr-nm__sum { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); padding: 12px 6px; margin-bottom: 14px; }
        .cr-nm__sum > div { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 4px 14px; }
        .cr-nm__sum > div + div { border-inline-start: 1px solid var(--cr-line); }
        .cr-nm__sum small { display: block; font-size: .74rem; color: var(--cr-muted); }
        .cr-nm__sum strong { font-size: 1.1rem; font-weight: 800; color: var(--cr-ink); font-variant-numeric: tabular-nums; }
        .cr-nm__sum .is-ok .cr-ico { color: var(--cr-ok); background: color-mix(in srgb, var(--cr-ok) 12%, transparent); }
        .cr-nm__sum .is-due strong { color: var(--cr-bad); }
        .cr-nm__split { display: grid; grid-template-columns: minmax(0, 1fr) 230px; gap: 14px; }
        .cr-nm__notes { padding: 14px 16px; min-height: 260px; }
        .cr-nm__sec { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .cr-nm__sec h6 { margin: 0; font-weight: 800; font-size: .92rem; color: var(--cr-ink); display: flex; align-items: center; gap: 6px; }
        .cr-nm__sec h6 i { color: var(--cr-brand); }
        .cr-nm__sec span { font-size: .72rem; color: var(--cr-muted); }
        .cr-nm__add { padding: 14px; align-self: start; display: flex; flex-direction: column; gap: 10px; }
        .cr-nm__add-chip {
            align-self: center; display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 700;
            color: var(--cr-brand); border: 1px solid color-mix(in srgb, var(--cr-brand) 35%, transparent); border-radius: 8px; padding: 4px 12px; background: var(--cr-surface);
        }
        .cr-nm__add textarea { min-height: 110px; resize: vertical; border-radius: 8px; border: 1px solid var(--cr-line); font-size: .82rem; background: var(--cr-surface); color: var(--cr-ink); }
        .cr-nm__save { background: var(--cr-brand); color: var(--app-on-primary, #fff); border: 0; border-radius: 8px; height: 38px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .cr-nm__save:disabled { opacity: .6; }
        .cr-nm__msg { font-size: .74rem; }
        .cr-nm__msg.is-bad { color: var(--cr-bad); }
        .cr-nm__msg.is-ok { color: var(--cr-ok); }

        /* الخط الزمني: النقطة على بداية السطر، والتاريخ في آخره */
        .cr-tl { position: relative; max-height: 58vh; overflow-y: auto; padding-inline-start: 22px; }
        .cr-tl::before { content: ""; position: absolute; inset-inline-start: 6px; top: 8px; bottom: 8px; width: 2px; background: var(--cr-line); }
        .cr-tl__item { position: relative; margin-bottom: 12px; }
        .cr-tl__dot { position: absolute; inset-inline-start: -21px; top: 16px; width: 12px; height: 12px; border-radius: 50%; background: var(--cr-brand); box-shadow: 0 0 0 3px var(--cr-surface); }
        .cr-tl__box { border: 1px solid var(--cr-line); border-radius: 10px; padding: 12px 14px; background: color-mix(in srgb, var(--cr-brand) 2%, var(--cr-surface)); }
        .cr-tl__item.is-related .cr-tl__box { border-color: color-mix(in srgb, var(--cr-brand) 45%, transparent); box-shadow: 0 0 0 1px color-mix(in srgb, var(--cr-brand) 20%, transparent); }
        .cr-tl__top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .cr-tl__text { font-size: .84rem; font-weight: 600; color: var(--cr-ink); white-space: pre-wrap; line-height: 1.6; }
        .cr-tl__text.is-empty { color: var(--cr-muted); font-weight: 400; }
        .cr-tl__time { font-size: .7rem; color: var(--cr-muted); white-space: nowrap; }
        .cr-tl__meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .cr-chip { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: color-mix(in srgb, var(--cr-muted) 12%, transparent); color: var(--cr-ink); }
        .cr-chip i { font-size: .72rem; }
        .cr-chip--brand { background: var(--cr-brand-soft); color: var(--cr-brand); }
        .cr-chip--ok { background: color-mix(in srgb, var(--cr-ok) 12%, transparent); color: var(--cr-ok); }
        .cr-chip--bad { background: color-mix(in srgb, var(--cr-bad) 12%, transparent); color: var(--cr-bad); }
        .cr-chip--warn { background: color-mix(in srgb, var(--cr-warn) 16%, transparent); color: color-mix(in srgb, var(--cr-warn) 75%, var(--cr-ink)); }
        a.cr-chip:hover { text-decoration: none; filter: brightness(.95); }
        .cr-tl__sec { margin-top: 10px; }
        .cr-tl__sec-title { font-size: .72rem; font-weight: 800; color: var(--cr-muted); margin-bottom: 6px; }
        .cr-comp { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .cr-comp__item { display: flex; align-items: center; gap: 8px; border: 1px solid var(--cr-line); border-radius: 8px; padding: 6px; background: var(--cr-surface); }
        .cr-comp__thumb { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 6px; object-fit: cover; cursor: zoom-in; background: var(--cr-brand-soft); display: grid; place-items: center; color: var(--cr-muted); }
        .cr-comp__name { font-size: .76rem; font-weight: 700; color: var(--cr-ink); line-height: 1.3; }
        .cr-comp__qty { font-size: .7rem; color: var(--cr-muted); }
        .cr-thumbs { display: flex; flex-wrap: wrap; gap: 6px; }
        .cr-thumbs img { width: 58px; height: 58px; object-fit: cover; border-radius: 8px; border: 1px solid var(--cr-line); cursor: zoom-in; }
        .cr-nm__state { text-align: center; color: var(--cr-muted); padding: 40px 10px; font-size: .85rem; }
        .cr-nm__info { max-height: 70vh; overflow-y: auto; gap: 12px; }
        .cr-tl__item.is-activity .cr-tl__box { padding: 8px 12px; background: var(--cr-surface); }
        .cr-tl__item.is-activity .cr-tl__dot { width: 10px; height: 10px; inset-inline-start: -20px; top: 14px; }
        .cr-tl--invoice .cr-tl__dot { background: var(--cr-ok); }
        .cr-tl--collection .cr-tl__dot { background: var(--cr-brand); }
        .cr-tl--return .cr-tl__dot { background: var(--cr-bad); }
        .cr-tl--visit .cr-tl__dot { background: var(--cr-warn); }
        .cr-tl__item.is-activity .cr-tl__text { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-weight: 400; }
        .cr-act-num { font-size: .74rem; color: var(--cr-muted); direction: ltr; unicode-bidi: isolate; }
        .cr-act-num a { color: var(--cr-brand); }
        .cr-act-amt { font-size: .82rem; font-weight: 800; color: var(--cr-ink); font-variant-numeric: tabular-nums; }
        .cr-act-note { margin-top: 6px; font-size: .76rem; color: var(--cr-muted); white-space: pre-wrap; }
        .cr-nm__notes { min-height: 320px; }
        .cr-tl__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--cr-line); font-size: .72rem; color: var(--cr-muted); }
        .cr-tl__acts { display: flex; gap: 6px; }
        .cr-tl__acts form { margin: 0; }
        .cr-nm .ag-actions-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; padding: 0; font-size: .8rem; border: 1px solid var(--cr-line); border-radius: 8px; background: var(--cr-surface); color: var(--cr-muted); }
        .cr-nm .ag-actions-btn:hover { border-color: var(--cr-brand); color: var(--cr-brand); text-decoration: none; }
        .cr-nm .cr-act-danger:hover { border-color: var(--cr-bad); color: var(--cr-bad); }
        .cr-tl { max-height: 62vh; }

        /* ═══════════ النافذةُ مضغوطة (2026-09-28) ═══════════
           كانت `modal-xl` بعمودٍ جانبيٍّ للبيانات وعمودٍ لإضافة الملاحظة؛ صارت
           `modal-lg` بعمودٍ واحد: بياناتُ العميل شبكةٌ صغيرة، ثمّ الملخّص، ثمّ
           الخطّ الزمني، ثمّ سطرُ الإضافة. */
        .cr-nm--compact .cr-nm__head { padding: 8px 14px; }
        .cr-nm--compact .cr-nm__title h5 { font-size: .92rem; }
        .cr-nm--compact .cr-nm__title .cr-ico { width: 28px; height: 28px; border-radius: 8px; }
        .cr-nm--compact .cr-nm__body { display: block; padding: 10px 14px 12px; overflow-y: auto; }
        .cr-nm--compact .cr-nm__box { border-radius: 9px; }
        .cr-nm--compact .cr-nm__info { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px 14px; padding: 8px 12px; margin-bottom: 8px; max-height: none; overflow: visible; }
        .cr-nm--compact .cr-nm__item .cr-ico { display: none; }
        .cr-nm--compact .cr-nm__item small { font-size: .64rem; }
        .cr-nm--compact .cr-nm__item strong { font-size: .78rem; line-height: 1.35; }
        .cr-nm--compact .cr-nm__sum { padding: 6px 2px; margin-bottom: 8px; }
        .cr-nm--compact .cr-nm__sum > div { padding: 2px 12px; }
        .cr-nm--compact .cr-nm__sum small { font-size: .66rem; }
        .cr-nm--compact .cr-nm__sum strong { font-size: .92rem; }
        .cr-nm--compact .cr-nm__sum .cr-ico { width: 26px; height: 26px; flex-basis: 26px; border-radius: 7px; font-size: .8rem; }
        .cr-nm--compact .cr-nm__split { display: block; }
        .cr-nm--compact .cr-nm__notes { padding: 8px 12px; min-height: 0; }
        .cr-nm--compact .cr-nm__sec { margin-bottom: 8px; }
        .cr-nm--compact .cr-nm__sec h6 { font-size: .82rem; }
        .cr-nm__scope { display: inline-flex; gap: 2px; padding: 2px; border-radius: 7px; background: color-mix(in srgb, var(--cr-ink) 6%, var(--cr-surface)); margin-inline-end: auto; margin-inline-start: 10px; }
        .cr-nm__scope[hidden] { display: none; }
        .cr-nm__scope button { border: 0; background: transparent; border-radius: 5px; padding: 2px 10px; font-size: .7rem; font-weight: 700; color: var(--cr-muted); cursor: pointer; }
        .cr-nm__scope button.is-active { background: var(--cr-surface); color: var(--cr-brand); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
        .cr-nm--compact .cr-tl { max-height: 44vh; padding-inline-start: 18px; }
        .cr-nm--compact .cr-tl__item { margin-bottom: 8px; }
        .cr-nm--compact .cr-tl__dot { inset-inline-start: -17px; top: 12px; width: 10px; height: 10px; }
        .cr-nm--compact .cr-tl__item.is-activity .cr-tl__dot { inset-inline-start: -16px; top: 11px; width: 8px; height: 8px; }
        .cr-nm--compact .cr-tl__box { padding: 8px 10px; border-radius: 8px; }
        .cr-nm--compact .cr-tl__item.is-activity .cr-tl__box { padding: 5px 10px; }
        .cr-nm--compact .cr-tl__text { font-size: .78rem; line-height: 1.5; }
        .cr-nm--compact .cr-tl__meta { margin-top: 5px; gap: 4px; }
        .cr-nm--compact .cr-chip { font-size: .66rem; padding: 2px 7px; }
        .cr-nm--compact .cr-tl__sec { margin-top: 6px; }
        .cr-nm--compact .cr-thumbs img { width: 44px; height: 44px; border-radius: 6px; }
        .cr-nm--compact .cr-comp { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 6px; }
        .cr-nm--compact .cr-comp__thumb { width: 34px; height: 34px; flex-basis: 34px; }
        .cr-nm--compact .cr-tl__foot { margin-top: 6px; padding-top: 5px; }
        .cr-nm--compact .ag-actions-btn { width: 26px; height: 26px; font-size: .72rem; }
        .cr-nm--compact .cr-nm__state { padding: 22px 10px; font-size: .8rem; }
        .cr-nm--compact .cr-nm__add { flex-direction: row; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px; margin-top: 8px; }
        .cr-nm--compact .cr-nm__add[hidden] { display: none; }
        .cr-nm--compact .cr-nm__add-chip { display: none; }
        .cr-nm--compact .cr-nm__add textarea { flex: 1 1 260px; min-height: 34px; height: 34px; padding: 6px 10px; font-size: .78rem; }
        .cr-nm--compact .cr-nm__save { height: 34px; padding: 0 16px; font-size: .78rem; }
        .cr-nm--compact .cr-nm__msg { flex: 1 1 100%; }
        .cr-nm--compact .cr-nm__msg:empty { display: none; }

        @media (max-width: 991px) {
            .cr-nm__body { grid-template-columns: minmax(0, 1fr); }
            .cr-nm__split { grid-template-columns: minmax(0, 1fr); }
            .cr-nm__sum { grid-template-columns: minmax(0, 1fr); }
            .cr-nm__sum > div + div { border-inline-start: 0; border-top: 1px solid var(--cr-line); }
        }

        /* ═══════════ التصميم المضغوط (cx-) — 2026-09-28 ═══════════
           الرأسُ يحمل أنواعَ العرض والتصدير، والفلاترُ سطرٌ واحد والباقي مطويّ،
           والبطاقاتُ الستّ في سطر، وثلاثةُ مخطّطاتٍ قصيرة — فيبدأ الجدولُ في
           الشاشة الأولى. كلُّ لونٍ من «مظهر النظام» كبقيّة الشاشة. */
        .cr.cx { --cx-soft: color-mix(in srgb, var(--cr-ink) 5%, var(--cr-surface)); font-size: .8rem; }
        .cr.cx .cx-num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
        .cr.cx .cx-card { background: var(--cr-surface); border: 1px solid var(--cr-line); border-radius: 10px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }

        /* الرأس */
        .cr.cx .cx-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 2px 0 10px; }
        .cr.cx .cx-head h1 { font-size: 1.1rem; font-weight: 800; color: var(--cr-ink); margin: 0; line-height: 1.3; }
        .cr.cx .cx-head .cr-crumbs { font-size: .72rem; margin-top: 0; }
        .cr.cx .cx-sp { flex: 1 1 auto; }
        .cr.cx .cx-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: var(--cx-soft); }
        .cr.cx .cx-tab { border: 0; background: transparent; padding: 5px 12px; border-radius: 7px; font-size: .78rem; font-weight: 700; color: var(--cr-muted); cursor: pointer; white-space: nowrap; line-height: 1.5; }
        .cr.cx .cx-tab i { margin-inline-end: 4px; font-size: .74rem; }
        .cr.cx .cx-tab:hover { color: var(--cr-brand); }
        .cr.cx .cx-tab.is-active { background: var(--cr-surface); color: var(--cr-brand); box-shadow: 0 1px 2px rgba(16, 24, 40, .1); }
        .cr.cx .cx-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--cr-line); background: var(--cr-surface); color: var(--cr-ink); font-size: .78rem; font-weight: 700; white-space: nowrap; cursor: pointer; text-decoration: none; }
        .cr.cx .cx-btn:hover { border-color: var(--cr-brand); color: var(--cr-brand); text-decoration: none; }
        .cr.cx .cx-btn--pri { background: var(--cr-brand); border-color: var(--cr-brand); color: var(--app-on-primary, #fff); }
        .cr.cx .cx-btn--pri:hover { filter: brightness(1.07); color: var(--app-on-primary, #fff); }
        .cr.cx .cx-btn--ghost { background: transparent; }
        .cr.cx .cx-btn[aria-expanded="true"] { border-color: var(--cr-brand); background: var(--cr-brand-soft); color: var(--cr-brand); }
        .cr.cx .cx-badge { min-width: 17px; padding: 0 5px; border-radius: 20px; background: var(--cr-brand); color: var(--app-on-primary, #fff); font-size: .64rem; line-height: 17px; text-align: center; }
        .cr.cx .cx-badge.is-zero { background: var(--cx-soft); color: var(--cr-muted); }

        /* الفلاتر */
        .cr.cx .cx-filters { padding: 9px 12px; margin-bottom: 10px; }
        .cr.cx .cx-frow { display: flex; align-items: flex-end; gap: 8px; flex-wrap: wrap; }
        .cr.cx .cx-chips { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: var(--cx-soft); margin: 0; }
        .cr.cx .cx-chips .ag-tab { border: 0; background: transparent; border-radius: 7px; padding: 4px 8px; font-size: .74rem; font-weight: 700; color: var(--cr-muted); white-space: nowrap; line-height: 1.5; min-width: 0; box-shadow: none; }
        .cr.cx .cx-chips .ag-tab.is-active { background: var(--cr-brand); color: var(--app-on-primary, #fff); }
        .cr.cx .cx-f { display: flex; flex-direction: column; gap: 2px; min-width: 0; margin: 0; }
        .cr.cx .cx-f > label, .cr.cx .cx-adv label { font-size: .68rem; font-weight: 700; color: var(--cr-muted); margin: 0 0 2px; }
        .cr.cx .cx-f .form-control, .cr.cx .cx-adv .form-control { height: 32px; min-height: 32px; padding: 0 8px; border-radius: 8px; border-color: var(--cr-line); font-size: .78rem; background: var(--cr-surface); color: var(--cr-ink); }
        .cr.cx .cx-filters .select2-container .select2-selection--single { height: 32px; min-height: 32px; border-radius: 8px; border-color: var(--cr-line); }
        .cr.cx .cx-filters .select2-container .select2-selection--single .select2-selection__rendered { line-height: 30px; font-size: .78rem; padding-inline: 8px 22px; }
        .cr.cx .cx-filters .select2-container .select2-selection--single .select2-selection__arrow { height: 30px; }
        .cr.cx .cx-range { display: flex; align-items: center; gap: 4px; }
        .cr.cx .cx-range input { width: 132px; }
        .cr.cx .cx-range span { color: var(--cr-muted); font-size: .72rem; }
        /* سطران مرتَّبان: الفتراتُ والأزرارُ أوّلاً، ثمّ الحقولُ تملأ العرض */
        .cr.cx .cx-frow > .cx-chips { order: 1; }
        .cr.cx .cx-frow > .cx-sp { order: 2; }
        .cr.cx .cx-frow > .cx-btn { order: 3; }
        .cr.cx .cx-frow > .cx-break { order: 4; flex: 0 0 100%; height: 0; }
        .cr.cx .cx-frow > .cx-f { order: 5; flex: 1 1 160px; }
        .cr.cx .cx-frow > .cx-f:first-of-type { flex: 0 0 auto; }
        .cr.cx .cx-rank a.nm { text-decoration: none; border: 0; }
        .cr.cx .cx-adv { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px 10px; margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--cr-line); }
        .cr.cx .cx-adv[hidden], .cr.cx .cx-guide[hidden] { display: none; }
        .cr.cx .cx-adv .cx-all { grid-column: 1 / -1; }
        .cr.cx .cx-adv .form-group { margin-bottom: 0; }
        .cr.cx .cx-guide { margin-top: 9px; padding: 8px 12px; border-radius: 8px; background: var(--cr-brand-soft); color: var(--cr-ink); font-size: .78rem; line-height: 1.7; }

        /* البطاقات */
        .cr.cx .cx-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }
        .cr.cx .cx-kpi { position: relative; overflow: hidden; display: flex; align-items: center; gap: 9px; padding: 8px 11px; min-width: 0; }
        .cr.cx .cx-kpi::before { content: ""; position: absolute; inset-inline-start: 0; top: 0; bottom: 0; width: 3px; background: var(--cr-tone); }
        .cr.cx .cx-kpi__ico { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 8px; display: grid; place-items: center; font-size: .95rem; background: color-mix(in srgb, var(--cr-tone) 12%, transparent); color: var(--cr-tone); }
        .cr.cx .cx-kpi__body { min-width: 0; }
        .cr.cx .cx-kpi__l { font-size: .7rem; font-weight: 700; color: var(--cr-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr.cx .cx-kpi__v { font-size: 1.02rem; font-weight: 800; color: var(--cr-ink); line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-variant-numeric: tabular-nums; }
        .cr.cx .cx-kpi__v small { font-weight: 500; color: var(--cr-muted); }
        .cr.cx .cx-kpi__u { font-size: .66rem; color: var(--cr-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr.cx .cx-kpi.is-main .cx-kpi__v { color: var(--cr-brand); font-size: 1.14rem; }

        /* المخطّطات */
        .cr.cx .cx-charts { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 4fr) minmax(0, 3fr); gap: 8px; margin-bottom: 10px; }
        .cr.cx .cx-panel { padding: 9px 12px 8px; min-width: 0; }
        .cr.cx .cx-ph { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; min-height: 24px; }
        .cr.cx .cx-ph h4 { font-size: .8rem; font-weight: 800; color: var(--cr-ink); margin: 0; white-space: nowrap; }
        .cr.cx .cx-ph small { font-size: .68rem; color: var(--cr-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cr.cx .cx-seg { display: inline-flex; padding: 2px; border-radius: 7px; background: var(--cx-soft); margin-inline-start: auto; }
        .cr.cx .cx-seg button { border: 0; background: transparent; border-radius: 5px; padding: 1px 8px; font-size: .68rem; font-weight: 700; color: var(--cr-muted); cursor: pointer; }
        .cr.cx .cx-seg button.is-active { background: var(--cr-surface); color: var(--cr-brand); box-shadow: 0 1px 2px rgba(16, 24, 40, .1); }
        .cr.cx .cx-chart { position: relative; height: 178px; direction: ltr; }
        .cr.cx .cx-state { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; direction: rtl; color: var(--cr-muted); font-size: .76rem; background: var(--cr-surface); }
        .cr.cx .cx-state .cx-btn { margin-top: 6px; height: 28px; }
        .cr.cx .cx-sk { position: absolute; inset: 6px 0 0; border-radius: 8px; background: linear-gradient(90deg, var(--cx-soft) 25%, color-mix(in srgb, var(--cr-ink) 2%, var(--cr-surface)) 37%, var(--cx-soft) 63%); background-size: 400% 100%; animation: cx-sk 1.2s ease infinite; }
        @keyframes cx-sk { 0% { background-position: 100% 0; } 100% { background-position: 0 0; } }
        .cr.cx .cx-rank { list-style: none; margin: 0; padding: 0; height: 178px; display: flex; flex-direction: column; justify-content: space-between; }
        .cr.cx .cx-rank li { display: grid; grid-template-columns: 16px minmax(0, 1fr) auto; gap: 0 6px; align-items: center; font-size: .74rem; }
        .cr.cx .cx-rank .n { color: var(--cr-muted); font-size: .66rem; text-align: center; }
        .cr.cx .cx-rank .nm { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700; color: var(--cr-ink); }
        .cr.cx .cx-rank a.nm:hover { color: var(--cr-brand); text-decoration: none; }
        .cr.cx .cx-rank .am { font-weight: 800; color: var(--cr-ink); }
        .cr.cx .cx-rank .bar { grid-column: 2 / 4; height: 3px; border-radius: 3px; background: var(--cx-soft); overflow: hidden; }
        .cr.cx .cx-rank .bar i { display: block; height: 100%; border-radius: 3px; background: var(--cr-brand); }
        .cr.cx .cx-rank .pc { color: var(--cr-muted); font-size: .64rem; font-weight: 600; margin-inline-start: 4px; }
        .cr.cx .cx-rank--method .n i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--c, var(--cr-brand)); }
        .cr.cx .cx-rank--method .bar i { background: var(--c, var(--cr-brand)); }
        .cr.cx .cx-rank .cx-more { cursor: pointer; }
        .cr.cx .cx-rank .cx-more .nm { color: var(--cr-brand); }
        .cr.cx .cx-rank .cx-more:hover .nm { text-decoration: underline; }
        .cr.cx .cx-rank.is-open { display: block; overflow-y: auto; padding-inline-end: 4px; }
        .cr.cx .cx-rank.is-open li { margin-bottom: 6px; }
        .cr.cx .cx-rank .cx-sub { padding-inline-start: 14px; }
        .cr.cx .cx-rank .cx-sub .nm { font-weight: 600; }
        .cr.cx .cx-rank .cx-none { display: block; text-align: center; color: var(--cr-muted); margin: auto; }

        /* رأسُ الجدول: بحثٌ سريع */
        .cr.cx .cr-report { margin-bottom: 0; }
        .cr.cx .cx-search { position: relative; margin: 0; }
        .cr.cx .cx-search input { height: 30px; width: 230px; border: 1px solid var(--cr-line); border-radius: 8px; padding: 0 28px 0 26px; font-size: .76rem; background: var(--cr-surface); color: var(--cr-ink); }
        .cr.cx .cx-search input:focus { outline: 2px solid var(--cr-brand-soft); border-color: var(--cr-brand); }
        .cr.cx .cx-search > i { position: absolute; inset-inline-start: 9px; top: 9px; font-size: .72rem; color: var(--cr-muted); }
        .cr.cx .cx-search a { position: absolute; inset-inline-end: 8px; top: 6px; color: var(--cr-muted); font-size: .8rem; text-decoration: none; }
        .cr.cx .cx-searching { font-size: .7rem; color: var(--cr-muted); }
        .cr.cx .rpt-scroll:not(.mx-scroll) { max-height: 66vh; overflow: auto; }
        .cr.cx .rpt-table--cr thead th { position: sticky; top: 0; z-index: 3; }
        .cr.cx .rpt-table--cr tfoot td { position: sticky; bottom: 0; z-index: 3; background: color-mix(in srgb, var(--cr-brand) 8%, var(--cr-surface)); }

        @media (max-width: 1280px) {
            .cr.cx .cx-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .cr.cx .cx-charts { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .cr.cx .cx-charts .cx-panel:first-child { grid-column: 1 / -1; }
            .cr.cx .cx-adv { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .cr.cx .cx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cr.cx .cx-charts { grid-template-columns: minmax(0, 1fr); }
            .cr.cx .cx-adv { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .cr.cx .cx-search input { width: 160px; }
            .cr.cx .cx-range input { width: 118px; }
        }
        @media print { .cr.cx .cx-charts, .cr.cx .cx-filters, .cr.cx .cx-tabs { display: none; } }
    </style>
@endsection

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="no-print">
        @include('sales::components.guide', [
            'key' => 'sales-reports.collections',
            'title' => __('sales_reports.guide.hub.title'),
            'lead' => __('sales_reports.guide.hub.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_reports.guide.hub.a'),
                    __('sales_reports.guide.hub.b'),
                    __('sales_reports.guide.hub.c'),
                    __('sales_reports.guide.hub.d'),
                ],
            ],
        ])
    </div>

    @include('sales::partials.table-menu')

    @php
        $isDetails = ! $isSummary && ! $isMatrix && $viewType !== 'target_changes';
        $chartGroupKey = $groupBy === 'branch_tree' ? 'branch' : $groupBy;
        $groupLabel = $groupByOptions[$chartGroupKey] ?? 'العميل';
        $advancedActive = ! empty($employeeId) || ! empty($clientId) || ($statusId ?? 'all') !== 'all'
            || ! empty($targetId) || ! empty($categoryId) || ! empty($yearFilter) || ! empty($registrationStageId)
            || (($targetChangeFilter ?? 'all') !== 'all') || request()->filled('tree_node');
        $dashboard = $dashboard ?? [];
        $currencyLabel = 'ريال';
        $methodColors = ['cash' => 'brand', 'bank' => 'ok', 'other' => 'muted'];
        /* رابطُ العميل — نفسُ وسم `reports.partials.client-link` مكتوباً مباشرة.
           الجزئيةُ في حلقةٍ من ألف صفّ كانت تكلّف ٩ ملّي ثانية لكلّ صفّ: كلُّ
           تضمينٍ يُطلق مؤلّفات العرض العامّة (ومنها استعلامُ زيارات اليوم)،
           فكانت الصفحةُ تُرسم في خمس عشرة ثانية. */
        $crClientLink = function ($id, $name, $fallback = '-') {
            $label = trim((string) $name) !== '' ? $name : $fallback;
            if (! $id) {
                return e($label);
            }

            return '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.e($label).'</a>';
        };
    @endphp

    <div class="content-body rpt cr cx">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ الرأس: العنوان + أنواع العرض + الأدوات ══════════
             أزرارُ العرض خارج النموذج وتُرسله (`form="filterForm"`)، فيتغيّر
             العرضُ مع بقاء الفلاتر كلِّها. الزرُّ يكتب قيمتَه في الحقل المخفيّ
             `view_type` ولا يحمل اسماً: الزرُّ يسبق النموذجَ في الصفحة فتُرسَل
             قيمتُه أوّلاً ثمّ الحقلُ المخفيّ، وPHP تأخذ الأخيرة — فكان كلُّ عرضٍ
             يفتح «تفاصيل». --}}
        @php
            $cxViews = [
                'details' => ['تفاصيل', 'fa fa-th-list', $isDetails],
                'summary' => ['ملخص', 'fa fa-list', $viewType === 'summary'],
                'matrix' => ['شهري', 'fa fa-table', $viewType === 'matrix'],
                'target_changes' => ['تغيّر المستويات', 'fas fa-exchange-alt', $viewType === 'target_changes'],
            ];
            // عددُ الفلاتر الإضافية المفعَّلة — يظهر على زرّها، ويفتحها إن كان فيها شيء
            $cxAdvCount = collect([
                ! empty($employeeId), ! empty($clientId), ($statusId ?? 'all') !== 'all', ! empty($targetId), ! empty($categoryId),
                ($targetChangeFilter ?? 'all') !== 'all', ! empty($registrationStageId), request()->filled('tree_node'),
            ])->filter()->count() + count($nodeFilters ?? []);
        @endphp
        <div class="cx-head">
            <div>
                <h1>تقرير التحصيلات</h1>
                <nav class="cr-crumbs" aria-label="breadcrumb">
                    @if (Route::has('dashboard'))
                        <a href="{{ route('dashboard') }}">الرئيسية</a>
                    @else
                        <span>الرئيسية</span>
                    @endif
                    <i class="feather icon-chevron-left"></i>
                    <a href="{{ route('salesReports.index') }}">التقارير</a>
                    <i class="feather icon-chevron-left"></i>
                    <span class="is-current">تقرير التحصيلات</span>
                </nav>
            </div>

            <div class="cx-tabs no-print" role="group" aria-label="نوع العرض">
                @foreach ($cxViews as $cxViewKey => [$cxViewLabel, $cxViewIcon, $cxViewActive])
                    <button type="submit" form="filterForm" data-view="{{ $cxViewKey }}" onclick="document.getElementById('crViewType').value = this.dataset.view; var crPg = document.querySelector('#filterForm [name=per_page]'); if (crPg) { crPg.remove(); }" class="cx-tab {{ $cxViewActive ? 'is-active' : '' }}"><i class="{{ $cxViewIcon }}"></i>{{ $cxViewLabel }}</button>
                @endforeach
            </div>

            <span class="cx-sp"></span>

            <div class="no-print" style="display:inline-flex;gap:6px;flex-wrap:wrap">
                <button type="button" class="cx-btn cx-btn--ghost" data-cr-toggle="#cxGuide" aria-expanded="false"><i class="feather icon-info"></i> دليل التقييم</button>
                <button type="button" class="cx-btn" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="cx-btn" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        {{-- ══════════ الفلاتر: سطرٌ واحد، والباقي مطويٌّ تحت «فلاتر إضافية» ══════════ --}}
        <div class="cx-card cx-filters no-print">
            <form method="GET" action="{{ route('salesReports.collections') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period }}">
                {{-- العرضُ الحالي يبقى مع «عرض التقرير»؛ أزرارُ العرض في الرأس تتقدّم عليه --}}
                <input type="hidden" name="view_type" id="crViewType" value="{{ $isDetails ? 'details' : $viewType }}">
                @if (request()->filled('per_page'))
                    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                @endif

                <div class="cx-frow">
                    <div class="ag-tabs cx-chips" role="group" aria-label="فترات سريعة">
                        @foreach (['1' => 'اليوم', '7' => 'أسبوع', '30' => 'شهر', '90' => '3 أشهر', '180' => '6 أشهر', '365' => 'سنة', 'custom' => 'مخصص'] as $periodKey => $periodLabel)
                            <button type="button" class="ag-tab period-btn {{ (string) $period === (string) $periodKey ? 'is-active' : '' }}" data-period="{{ $periodKey }}">{{ $periodLabel }}</button>
                        @endforeach
                    </div>

                    <div class="cx-f">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="cx-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ" value="{{ $startDate->format('Y-m-d') }}" {{ ! empty($yearFilter) ? 'readonly' : '' }}>
                            <span>إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ" value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="cx-f cx-w-year">
                        <label for="year_filter">فلترة بالسنة</label>
                        <select name="year_filter" id="year_filter" class="form-control rpt-select2">
                            <option value="">(تحديد يدوي)</option>
                            @for ($y = now()->year + 1; $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ isset($yearFilter) && $yearFilter == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="cx-f cx-w-method">
                        <label for="payment_method">طريقة التحصيل</label>
                        <select name="payment_method" id="payment_method" class="form-control rpt-select2">
                            <option value="all" {{ ($paymentMethod ?? 'all') === 'all' ? 'selected' : '' }}>الكل</option>
                            <option value="cash" {{ ($paymentMethod ?? '') === 'cash' ? 'selected' : '' }}>نقدي (كل الخزائن)</option>
                            <option value="bank" {{ ($paymentMethod ?? '') === 'bank' ? 'selected' : '' }}>تحويل بنكي (كل البنوك)</option>
                            @foreach (['cash' => 'الخزائن', 'bank' => 'البنوك'] as $methodKey => $methodGroupLabel)
                                @if (! empty($paymentAccounts[$methodKey]) && count($paymentAccounts[$methodKey]))
                                    <optgroup label="{{ $methodGroupLabel }}">
                                        @foreach ($paymentAccounts[$methodKey] as $account)
                                            <option value="account:{{ $account->id }}" {{ ($paymentMethod ?? '') === 'account:'.$account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-f cx-w-group">
                        <label for="group_by">تجميع حسب</label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" {{ $groupBy == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <span class="cx-sp"></span>
                    <span class="cx-break"></span>

                    <button type="button" class="cx-btn" data-cr-toggle="#cxAdv" aria-expanded="{{ $cxAdvCount > 0 ? 'true' : 'false' }}"><i class="feather icon-sliders"></i> فلاتر إضافية <span class="cx-badge {{ $cxAdvCount > 0 ? '' : 'is-zero' }}">{{ $cxAdvCount }}</span></button>
                    <button type="submit" class="cx-btn cx-btn--pri"><i class="fas fa-search"></i> عرض التقرير</button>
                    <a href="{{ route('salesReports.collections') }}" class="cx-btn cx-btn--ghost"><i class="fas fa-redo"></i> إعادة تعيين</a>
                </div>

                <div class="cx-adv" id="cxAdv" @if ($cxAdvCount === 0) hidden @endif>
                    <div class="cx-f">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" data-placeholder="كل الموظفين" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ $employeeId == $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-f">
                        @include('reports.partials.client-select', [
                            'name' => 'client_id',
                            'url' => route('salesReports.collections.searchClients'),
                            'selectedClient' => $clientId ? \App\Models\Client::find($clientId) : null,
                            'label' => 'العميل',
                        ])
                    </div>

                    <div class="cx-f">
                        <label>الحالة</label>
                        <select name="status_id" class="form-control rpt-select2">
                            {{-- «الكل» أوّلاً وافتراضاً — رقمُ لوحة المبيعات؛ استبعادُ الموقوفين اختيار --}}
                            <option value="all" {{ $statusId == 'all' ? 'selected' : '' }}>الكل</option>
                            <option value="except_suspended" {{ $statusId == 'except_suspended' ? 'selected' : '' }}>الكل (ما عدا الموقوفين)</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" {{ $statusId == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-f">
                        <label>تارجت العميل</label>
                        <select name="target_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($customerTargets ?? [] as $target)
                                <option value="{{ $target->id }}" {{ (isset($targetId) && $targetId == $target->id) ? 'selected' : '' }}>{{ $target->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-f">
                        <label>تصنيف العميل</label>
                        <select name="category_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($categoriesClient ?? [] as $category)
                                <option value="{{ $category->id }}" {{ (isset($categoryId) && $categoryId == $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cx-f">
                        <label>تغير مستوى العميل</label>
                        <select name="target_change_filter" class="form-control rpt-select2">
                            <option value="all" {{ ($targetChangeFilter ?? 'all') == 'all' ? 'selected' : '' }}>الكل</option>
                            <option value="downgrade" {{ ($targetChangeFilter ?? 'all') == 'downgrade' ? 'selected' : '' }}>تراجع المستوى فقط</option>
                            <option value="upgrade" {{ ($targetChangeFilter ?? 'all') == 'upgrade' ? 'selected' : '' }}>تقدم المستوى فقط</option>
                            <option value="changed" {{ ($targetChangeFilter ?? 'all') == 'changed' ? 'selected' : '' }}>تغير المستوى (تقدم أو تراجع)</option>
                        </select>
                    </div>

                    {{-- وضعُ العميل — نفسُ حقل تقارير العملاء (مرحلةُ التسجيل، منفصلةٌ عن الحالة) --}}
                    <div class="cx-f">
                        @include('client::customers.CustomerReport.partials.stage-select', [
                            'registrationStages' => $registrationStages ?? collect(),
                            'label' => 'وضع العميل',
                        ])
                    </div>

                    {{-- الفرع / المنطقة / المجموعة / المنفذ — كلٌّ بنوع عقدته الصحيح --}}
                    @foreach (['branch' => ['الفرع', 'كل الفروع'], 'region' => ['المنطقة', 'كل المناطق'], 'group' => ['المجموعة', 'كل المجموعات'], 'outlet' => ['المنفذ', 'كل المنافذ']] as $nodeType => [$nodeLabel, $nodeAll])
                        <div class="cx-f">
                            <label for="node_{{ $nodeType }}">{{ $nodeLabel }}</label>
                            <select name="node_{{ $nodeType }}" id="node_{{ $nodeType }}" class="form-control rpt-select2" data-placeholder="{{ $nodeAll }}">
                                <option value="">{{ $nodeAll }}</option>
                                @foreach ($nodeTypeOptions[$nodeType] ?? [] as $nodeOption)
                                    <option value="{{ $nodeOption['id'] }}" {{ (int) ($nodeFilters[$nodeType] ?? 0) === $nodeOption['id'] ? 'selected' : '' }}>{{ $nodeOption['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="cx-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="cx-guide" id="cxGuide" hidden>
                    <strong>دليل التقييم الديناميكي للعملاء (إعادة التقييم):</strong>
                    يظهر في هذا التقرير <strong>التقييم الحالي</strong> للعميل بناءً على سلوك السداد الفعلي.
                    إذا كان العميل يسدد مثلاً (شهر و شهر لا)، فمتوسط أيام التحصيل لديه يكون <strong>60 يوماً</strong>.
                    بمجرد أن يسدد الفواتير المتأخرة، يقوم النظام تلقائياً <strong>بإعادة تقييمه</strong> للهدف المناسب (مثلاً عميل ذهبي) إذا انطبقت عليه شروط هذا الهدف.
                </div>
            </form>
        </div>

        {{-- ══════════ البطاقات — معادلةٌ تُقرأ من اليمين ══════════
             إجماليُّ السندات − الملغاة − المردود = الصافي (= «التحصيلات» في لوحة المبيعات).

             كانت «إجمالي سندات القبض» مطروحاً منها الملغاةُ أصلاً (رقمُ لوحة العملاء)،
             ثمّ تُعرض الملغاةُ بطاقةً بجانبها — فمن طرحها طرحها مرّتين ولم يصل إلى
             الصافي: ١٬١٤٠٬٤٣١ − ٧٬١٣٧ − ١٥٬٨٦٨ ≠ ١٬١٣٣٬٢٩٤. صارت السنداتُ كلُّها
             كما حُرّرت، والطرحُ مكتوبٌ تحت الصافي.

             و«المتبقي للتحصيل» كان متبقّيَ فواتير سنداتِ المدى وحدَها (رقمٌ لا
             يطابق شيئاً في اللوحة)؛ صار المديونيةَ القائمة من الأستاذ. --}}
        @php
            $crCancelledRows = $collectionsData->where('is_cancellation', true)->filter(fn ($r) => (float) $r['amount'] < 0);
            $crReceiptRows = $collectionsData->where('is_return', false)->where('is_cancellation', false);
            $crGross = (float) $crReceiptRows->sum('amount');
            $crCancelled = abs((float) $crCancelledRows->sum('amount'));
            $crReturns = (float) ($statistics['total_returns'] ?? 0);
            $crNet = (float) ($dashboard['net'] ?? 0);
            $crAverage = $crReceiptRows->count() > 0 ? $crGross / $crReceiptRows->count() : 0;
            $crDebt = $dashboard['debt'] ?? ['amount' => 0, 'clients' => 0, 'as_of' => null, 'basis' => 'scope'];
            $crDebtUnit = number_format((int) $crDebt['clients']).' عميل مدين في '.($crDebt['as_of'] ?? '')
                .match ($crDebt['basis']) { 'client' => ' — رصيد العميل', 'rows' => ' — لعملاء التقرير', default => ' — كلوحة المبيعات' };
            $crKpis = [
                ['label' => 'صافي التحصيلات', 'value' => number_format($crNet, 2), 'unit' => number_format($crGross, 2).' − '.number_format($crCancelled, 2).' − '.number_format($crReturns, 2), 'hint' => 'إجمالي السندات − الملغاة − المردود للعميل = «التحصيلات» في لوحة المبيعات', 'icon' => 'icon-check-circle', 'tone' => 'brand', 'main' => true],
                ['label' => 'إجمالي سندات القبض', 'value' => number_format($crGross, 2), 'unit' => number_format($crReceiptRows->count()).' سند'.($crAverage > 0 ? ' · متوسط '.number_format($crAverage, 2) : ''), 'hint' => 'كلُّ سندات القبض في الفترة كما حُرّرت، ومنها ما أُلغي لاحقاً', 'icon' => 'icon-dollar-sign', 'tone' => 'ok'],
                ['label' => '− سندات ملغاة', 'value' => number_format($crCancelled, 2), 'unit' => number_format($crCancelledRows->count()).' سند أُلغي', 'hint' => 'تُطرح من إجمالي السندات', 'icon' => 'icon-x-circle', 'tone' => 'warn'],
                ['label' => '− المردود للعميل', 'value' => number_format($crReturns, 2), 'unit' => number_format((int) ($statistics['return_count'] ?? 0)).' مرتجع / إشعار رُدّ نقداً', 'hint' => 'ما رُدّ للعميل نقداً عن مرتجع أو إشعار دائن — يُطرح', 'icon' => 'icon-corner-down-left', 'tone' => 'bad'],
                ['label' => 'المديونية القائمة', 'value' => number_format((float) $crDebt['amount'], 2), 'unit' => $crDebtUnit, 'hint' => 'رصيد العملاء المدينين في الأستاذ في نهاية الفترة (لا يتجاوز اليوم) — نفسُ «الذمم» في لوحة المبيعات ولوحة العملاء', 'icon' => 'icon-clock', 'tone' => 'violet'],
                ['label' => 'العملاء المحصَّل منهم', 'value' => number_format((int) ($dashboard['client_count'] ?? 0)).' <small>عميل</small>', 'unit' => number_format((int) ($dashboard['invoice_count'] ?? 0)).' فاتورة سُدّدت'.((($dashboard['unlinked_count'] ?? 0) > 0) ? ' · '.number_format($dashboard['unlinked_count']).' سند على الحساب بلا فاتورة' : ''), 'hint' => 'عملاءٌ لهم سندُ قبضٍ في الفترة، والفواتيرُ التي سدّدتها السندات، والسنداتُ المقبوضة على الحساب دون ربطٍ بفاتورة', 'icon' => 'icon-users', 'tone' => 'brand', 'raw' => true],
            ];
        @endphp
        <div class="cx-kpis">
            @foreach ($crKpis as $kpi)
                <div class="cx-card cx-kpi cr-tone-{{ $kpi['tone'] }} {{ ! empty($kpi['main']) ? 'is-main' : '' }}" @if (! empty($kpi['hint'])) title="{{ $kpi['hint'] }}" @endif>
                    <span class="cx-kpi__ico"><i class="feather {{ $kpi['icon'] }}"></i></span>
                    <div class="cx-kpi__body">
                        <div class="cx-kpi__l">{{ $kpi['label'] }}</div>
                        <div class="cx-kpi__v" title="{{ strip_tags($kpi['value']) }}">@if (! empty($kpi['raw'])){!! $kpi['value'] !!}@else{{ $kpi['value'] }}@endif</div>
                        <div class="cx-kpi__u" title="{{ $kpi['unit'] }}">{{ $kpi['unit'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ══════════ المخطّطات ══════════
             تُرسم في المتصفّح من بيانات الصفحة: هيكلٌ رماديٌّ حتى تُرسم، ورسالةٌ
             وزرُّ إعادة المحاولة إن تعذّر الرسم، و«لا توجد تحصيلات» للفترة الفارغة. --}}
        <div class="cx-charts no-print">
            <div class="cx-card cx-panel">
                <div class="cx-ph">
                    {{-- شهريٌّ وحده بطلب المستخدم — حُذف مفتاح يومي/أسبوعي --}}
                    <h4 id="crTrendTitle">التحصيل الشهري</h4>
                    <small id="crTrendSub"></small>
                </div>
                <div class="cx-chart" id="crTrendChart"><div class="cx-sk"></div></div>
            </div>

            <div class="cx-card cx-panel">
                <div class="cx-ph">
                    <h4>التحصيل حسب الموظف</h4>
                    <small>
                        @foreach ($dashboard['method_totals'] ?? [] as $methodKey => $methodTotal)
                            @if (abs($methodTotal['amount']) >= 0.005)
                                {{ $methodTotal['label'] }} <span class="cx-num">{{ number_format($methodTotal['amount'], 2) }}</span>@if (! $loop->last) · @endif
                            @endif
                        @endforeach
                    </small>
                </div>
                {{-- تحصيلُ كلِّ موظف (من حرّر السند) لا الخزائن — طلبُ المستخدم. قائمةٌ لا
                     مخطّط: تعرض الاسمَ والمبلغَ والحصّة كاملة، و«موظفون آخرون» يُفتح على أسمائه --}}
                <ul class="cx-rank cx-rank--method" id="crMethodList"></ul>
            </div>

            <div class="cx-card cx-panel">
                <div class="cx-ph">
                    <h4>الأعلى تحصيلاً</h4>
                    <div class="cx-seg" id="crRankSeg" role="group" aria-label="الأعلى حسب">
                        <button type="button" class="is-active" data-k="client">عميل</button>
                        <button type="button" data-k="region">منطقة</button>
                    </div>
                </div>
                <ul class="cx-rank" id="crRank"></ul>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════
             تصميمٌ واحدٌ مضغوطٌ للعروض الأربعة: رأسُ بطاقةٍ بالعنوان والعدّادات
             والأدوات، شريطُ الصفحات فوق الجدول وتحته حيث يُرقَّم في الخادم، خليّةٌ
             من سطرين (رئيسيٌّ وثانويٌّ باهت) بدل عمودٍ لكلّ معلومة — فالتفاصيلُ
             أحدَ عشرَ عموداً بدل سبعةَ عشر — وذيلٌ بالإجماليات على البيانات كلِّها
             لا الصفحةِ وحدَها. --}}
        @php
            $crMonths = [
                1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
                7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
            ];
            $crIsLevels = ! $isDetails && ! $isSummary && ! $isMatrix;
            $crTitle = $isDetails
                ? 'تفاصيل التحصيلات حسب '.($groupByOptions[$groupBy] ?? 'التصنيف')
                : ($isSummary ? 'ملخص التحصيلات حسب '.($groupByOptions[$groupBy] ?? '')
                : ($isMatrix ? 'التحصيلات الشهرية حسب '.($groupByOptions[$groupBy] ?? '') : 'تغيّر مستويات العملاء'));
            $crIcon = $isDetails ? 'list' : ($isSummary ? 'layer-group' : ($isMatrix ? 'calendar-alt' : 'exchange-alt'));
            $crPager = $isDetails ? ($detailPager ?? null) : ($isMatrix ? ($matrixPager ?? null) : null);
            $crPagerUnit = $isDetails ? 'تحصيل' : ($groupBy === 'client' ? 'عميل' : 'صفّ');
            $crPageSizes = $isDetails ? [25 => '25', 50 => '50', 100 => '100', 200 => '200', 0 => 'الكل'] : [25 => '25', 50 => '50', 100 => '100', 0 => 'الكل'];
            $crRowsTotal = $crPager['total'] ?? (is_countable($reportData) ? count($reportData) : 0);
            /* شارةٌ ملوّنةٌ خفيفة (خلفيةٌ شفّافة بلون الفئة) بدل الحشوة المصمتة — أخفُّ في جدولٍ مزدحم */
            $crTag = fn ($text, $color) => '<span class="cr-tag" style="--c:'.e(str_starts_with((string) $color, '#') ? $color : '#6c757d').'">'.e($text).'</span>';
        @endphp

        <div class="rpt-card cr-report" id="reportCard">
            <div class="rpt-card__head ag-table-head">
                <h4>
                    <i class="fas fa-{{ $crIcon }}"></i> {{ $crTitle }}
                    <span class="rpt-pill rpt-pill-secondary">{{ number_format($crRowsTotal) }} {{ $isDetails ? 'تحصيل' : ($isMatrix || $isSummary ? ($groupBy === 'client' ? 'عميل' : 'صفّ') : 'عميل') }}</span>
                    @if ($isDetails)
                        <span class="rpt-pill rpt-pill-secondary">{{ number_format((int) ($dashboard['client_count'] ?? 0)) }} عميل</span>
                        <span class="rpt-pill rpt-pill-secondary">{{ number_format((int) ($dashboard['invoice_count'] ?? 0)) }} فاتورة</span>
                    @endif
                </h4>
                <div class="ag-tools no-print">
                    @if ($isMatrix)
                        <span class="mx-legend no-print">
                            <span>كثافة التحصيل</span>
                            <span class="mx-scale"><i style="background:color-mix(in srgb, var(--rpt-brand, #7367f0) 7%, #fff)"></i><i style="background:color-mix(in srgb, var(--rpt-brand, #7367f0) 15%, #fff)"></i><i style="background:color-mix(in srgb, var(--rpt-brand, #7367f0) 26%, #fff)"></i><i style="background:color-mix(in srgb, var(--rpt-brand, #7367f0) 38%, #fff)"></i></span>
                            @if ($groupBy === 'client')
                                <span class="mx-key"><b style="background:var(--rpt-muted, #98a2b3)"></b> زيارات الشهر</span>
                                <span class="mx-key"><b style="background:var(--rpt-bad, #ea5455)"></b> زيارات فائتة</span>
                            @endif
                        </span>
                    @endif
                    @if ($crPager)
                        <label class="ag-page-size no-print" for="crPageSize">
                            <span>عدد الصفوف</span>
                            <select id="crPageSize" class="form-control">
                                @foreach ($crPageSizes as $sizeValue => $sizeLabel)
                                    <option value="{{ $sizeValue }}" @selected((int) $crPager['per_page'] === $sizeValue)>{{ $sizeLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    {{-- بحثٌ سريع في الخادم على الصفوف كلِّها — Enter يُرسل نموذج الفلاتر ومعه `q` --}}
                    @if (($quickSearch ?? '') !== '')
                        <span class="cx-searching">نتائج «{{ $quickSearch }}»</span>
                    @endif
                    <label class="cx-search no-print" for="crQuickSearch">
                        <i class="fas fa-search"></i>
                        <input type="search" id="crQuickSearch" name="q" form="filterForm" value="{{ $quickSearch ?? '' }}" placeholder="بحث سريع: عميل، كود، سند، فاتورة، موظف…" aria-label="بحث سريع في الجدول" autocomplete="off">
                        @if (($quickSearch ?? '') !== '')
                            <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" title="مسح البحث" aria-label="مسح البحث">&times;</a>
                        @endif
                    </label>
                </div>
            </div>

            {{-- شريطُ الصفحات تحت الجدول وحدَه (طلبُ المستخدم) --}}

            {{-- ─────────────── التفاصيل ─────────────── --}}
            @if ($isDetails)
                @php
                    $groupCounts = $detailRows->countBy('group_key');
                    $rowsList = ($detailRowsPage ?? $detailRows)->values();
                    $rowNumber = $crPager ? max(0, (int) $crPager['from'] - 1) : 0;
                    $previousGroup = null;
                    $detailCols = 11;
                @endphp
                <div class="rpt-scroll">
                    <table class="rpt-table ag-table rpt-table--cr" id="reportTable">
                        <thead>
                            <tr>
                                <th class="cr-col-n">#</th>
                                <th class="cr-col-date">التاريخ</th>
                                <th class="cr-col-client cr-start">العميل</th>
                                <th class="cr-col-doc">الفاتورة / السند</th>
                                <th class="cr-col-emp cr-start">الموظف</th>
                                <th class="cr-col-acc cr-start">الحساب</th>
                                <th class="cr-col-cat">الفئة</th>
                                <th class="cr-col-amt">المبلغ</th>
                                <th class="cr-col-kind">النوع</th>
                                <th class="cr-col-note cr-start">ملاحظة</th>
                                <th class="no-print cr-col-act"><span class="sr-only">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            @forelse ($rowsList as $rowIndex => $row)
                                @php
                                    $rowNumber++;
                                    $codes = $row['invoice_codes'] ?? [];
                                    $hasInvoices = ! empty($codes);
                                    $isOut = ! empty($row['is_return']) || ! empty($row['is_cancellation']);
                                    $rowAmount = (float) $row['amount'];
                                    $rowTotal = (float) ($row['invoice_total'] ?? 0);
                                    $rowRemaining = (float) ($row['invoice_remaining'] ?? 0);
                                    if (! empty($row['is_return'])) {
                                        [$rowNote, $rowNoteClass] = ['مرتجع', 'rpt-pill-danger'];
                                    } elseif (! empty($row['is_cancellation'])) {
                                        [$rowNote, $rowNoteClass] = [$rowAmount < 0 ? 'إلغاء سند' : 'سند ملغى', 'rpt-pill-danger'];
                                    } elseif (! $hasInvoices) {
                                        [$rowNote, $rowNoteClass] = ['رصيد سابق', 'rpt-pill-info'];
                                    } elseif ($rowAmount + 0.009 < $rowTotal) {
                                        [$rowNote, $rowNoteClass] = ['دفعة جزئية', 'rpt-pill-warning'];
                                    } else {
                                        [$rowNote, $rowNoteClass] = ['سداد كامل', 'rpt-pill-success'];
                                    }
                                    $rowDate = \Carbon\Carbon::parse($row['date']);
                                    $clientKnown = ! empty($row['client_id']);
                                    $nextRow = $rowsList[$rowIndex + 1] ?? null;
                                    $isGroupEnd = ! $nextRow || $nextRow['group_key'] !== $row['group_key'];
                                    $statusColor = str_starts_with((string) ($row['client_status_color'] ?? ''), '#') ? $row['client_status_color'] : '#6c757d';
                                    $isBank = ($row['method_key'] ?? 'cash') === 'bank';
                                    $rowNoteText = trim((string) ($row['note'] ?? ''));
                                    $docTitle = trim(($hasInvoices ? 'الفواتير: '.implode('، ', $codes) : '').(! empty($row['entry_reference']) ? ' — سند '.$row['entry_reference'] : ''), ' —');
                                @endphp
                                @if ($row['group_key'] !== $previousGroup)
                                    @php $previousGroup = $row['group_key']; @endphp
                                    <tr class="rpt-group cr-grp" data-group="{{ $row['group_key'] }}">
                                        <td colspan="{{ $detailCols }}">
                                            <i class="fas fa-folder-open"></i>
                                            {{ $groupByOptions[$groupBy] ?? 'التصنيف' }}:
                                            {!! $crClientLink($groupBy === 'client' && $clientKnown ? $row['client_id'] : null, $row['group_name'], 'غير محدد') !!}
                                            <span class="rpt-pill rpt-pill-secondary">{{ number_format($groupCounts[$row['group_key']] ?? 0) }} تحصيل</span>
                                            <span class="rpt-pill rpt-pill-primary rpt-num">{{ number_format((float) $row['group_amount'], 2) }}</span>
                                        </td>
                                    </tr>
                                @endif
                                <tr class="cr-row {{ $isOut ? 'cr-row--out' : '' }}" data-row="1" data-group="{{ $row['group_key'] }}">
                                    <td class="cr-col-n">{{ $rowNumber }}</td>
                                    <td class="cr-col-date rpt-num">{{ $rowDate->format('Y-m-d') }}</td>
                                    <td class="cr-col-client cr-start">
                                        <div class="cr-primary">{!! $crClientLink($clientKnown ? $row['client_id'] : null, $row['client_name'] ?? '') !!}</div>
                                        <div class="cr-secondary">
                                            <span class="rpt-num">{{ $row['client_code'] ?? '-' }}</span>
                                            <span class="cr-dot"></span>
                                            <span class="cr-status" style="--s:{{ $statusColor }}">{{ $row['client_status'] ?? 'غير محدد' }}</span>
                                            @if (empty($row['branch_unspecified']))
                                                <span class="cr-dot"></span>
                                                <span class="cr-ellipsis" title="{{ $row['branch_name'] }}">{{ $row['branch_name'] }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="cr-col-doc" title="{{ $docTitle }}">
                                        @if ($hasInvoices)
                                            <div class="cr-primary rpt-num">{{ $codes[0] }}@if (count($codes) > 1) <span class="cr-more">+{{ count($codes) - 1 }}</span>@endif</div>
                                            <div class="cr-secondary rpt-num">{{ number_format($rowTotal, 2) }}@if (! $isOut && $rowRemaining > 0.009)<span class="cr-dot"></span><span class="rpt-neg">متبقٍ {{ number_format($rowRemaining, 2) }}</span>@endif</div>
                                        @elseif (! empty($row['entry_reference']))
                                            <div class="cr-primary rpt-num">سند {{ $row['entry_reference'] }}</div>
                                        @else
                                            <div class="cr-primary text-muted">—</div>
                                        @endif
                                    </td>
                                    <td class="cr-col-emp cr-start cr-ellipsis" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</td>
                                    <td class="cr-col-acc cr-start">
                                        <div class="cr-primary" title="{{ $row['treasury_account'] }}">{{ $row['treasury_account'] }}</div>
                                        <div class="cr-secondary"><span class="cr-method {{ $isBank ? 'is-bank' : 'is-cash' }}">{{ $row['account_type'] ?? ($isBank ? 'بنك' : 'خزينة') }}</span></div>
                                    </td>
                                    <td class="cr-col-cat">
                                        <div class="cr-primary">
                                            @if (isset($row['target_change']) && $row['target_change'] !== 'none')
                                                {!! $crTag($row['client_previous_category'] ?? 'غير مقيّم', $row['client_previous_category_color'] ?? null) !!}<i class="fas fa-long-arrow-alt-left cr-arrow text-{{ $row['target_change'] == 'upgrade' ? 'success' : 'danger' }}" title="{{ $row['target_change'] == 'upgrade' ? 'تقدم في المستوى' : 'تراجع في المستوى' }}"></i>
                                            @endif
                                            {!! $crTag($row['client_category'] ?? 'غير مقيّم', $row['client_category_color'] ?? null) !!}
                                        </div>
                                        @if (empty($row['real_category_unspecified']))
                                            <div class="cr-secondary">{{ $row['client_real_category'] }}</div>
                                        @endif
                                    </td>
                                    <td class="cr-col-amt rpt-num {{ $rowAmount < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ number_format($rowAmount, 2) }}</strong></td>
                                    <td class="cr-col-kind"><span class="rpt-pill {{ $rowNoteClass }}">{{ $rowNote }}</span></td>
                                    <td class="cr-col-note cr-start cr-ellipsis" title="{{ $rowNoteText }}">{{ $rowNoteText !== '' ? $rowNoteText : '—' }}</td>
                                    <td class="no-print cr-col-act">
                                        @if ($clientKnown)
                                            <div class="dropdown">
                                                <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات"><i class="fas fa-ellipsis-v"></i></button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a href="#" class="dropdown-item client-notes-btn" data-client-id="{{ $row['client_id'] }}" data-client-name="{{ $row['client_name'] }}">
                                                        <i class="fas fa-comments me-2 text-info"></i> ملاحظات العميل
                                                    </a>
                                                    @if (! empty($row['invoice_ids'][0]))
                                                        <a class="dropdown-item" href="{{ route('invoices.show', $row['invoice_ids'][0]) }}" target="_blank" rel="noopener"><i class="fas fa-eye me-2 text-primary"></i> عرض الفاتورة</a>
                                                    @endif
                                                    <a class="dropdown-item" href="{{ route('clients.show', $row['client_id']) }}" target="_blank" rel="noopener"><i class="fas fa-user me-2 text-info"></i> ملف العميل</a>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($isGroupEnd)
                                    <tr class="rpt-subtotal cr-subtotal" data-group="{{ $row['group_key'] }}">
                                        <td colspan="7" class="cr-start">إجمالي {{ $row['group_name'] ?: 'غير محدد' }}</td>
                                        <td class="cr-col-amt rpt-num {{ ((float) $row['group_amount']) < 0 ? 'rpt-neg' : 'rpt-pos' }}">{{ number_format((float) $row['group_amount'], 2) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="{{ $detailCols }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                            @endforelse
                        </tbody>
                        @if ($rowsList->count() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="cr-start">الإجمالي العام <span class="cr-secondary">({{ number_format($crRowsTotal) }} تحصيل)</span></td>
                                    <td class="cr-col-amt rpt-num rpt-pos">{{ number_format((float) ($totals['amount'] ?? 0), 2) }}</td>
                                    <td colspan="3" class="cr-start"><span class="cr-secondary" title="ما بقي على الفواتير التي سدّدتها سنداتُ هذه الفترة — لا المديونيةُ كلُّها">المتبقي على فواتير هذه السندات</span> <span class="rpt-num">{{ number_format((float) ($dashboard['remaining'] ?? 0), 2) }}</span></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @include('sales::sals_report.salesRport.partials.collections-pager', ['pager' => $crPager, 'unit' => 'تحصيل'])

            {{-- ─────────────── الملخّص ─────────────── --}}
            @elseif ($isSummary)
                @php
                    $summaryTotal = max((float) ($totals['amount'] ?? 0), 0.0001);
                    $summaryRow = 0;
                @endphp
                <div class="rpt-scroll">
                    <table class="rpt-table ag-table rpt-table--cr rpt-table--cr-summary" id="reportTable">
                        <thead>
                            <tr>
                                <th class="cr-col-n">#</th>
                                <th class="cr-start">{{ $groupByOptions[$groupBy] ?? 'التصنيف' }}</th>
                                <th>عدد التحصيلات</th>
                                <th class="cr-col-amt">المبلغ</th>
                                <th class="cr-col-share">النسبة من الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($listRows ?? $reportData) as $groupKey => $data)
                                @php
                                    $summaryRow++;
                                    $share = ((float) ($data['amount'] ?? 0)) / $summaryTotal * 100;
                                @endphp
                                <tr class="cr-row">
                                    <td class="cr-col-n">{{ $summaryRow }}</td>
                                    <td class="cr-start">
                                        @if (isset($data['level']) && $data['level'] > 0)
                                            <span class="cr-indent" style="--lvl:{{ (int) $data['level'] }}"></span><i class="fa fa-level-down fa-rotate-180 text-muted"></i>
                                        @endif
                                        {!! $crClientLink($groupBy === 'client' ? ($data['client_id'] ?? (is_numeric($groupKey) ? $groupKey : null)) : null, $data['name'], 'غير محدد') !!}
                                        @if (isset($data['node_type']) && $groupBy === 'branch_tree')
                                            <span class="rpt-pill rpt-pill-secondary">{{ $data['node_type'] }}</span>
                                        @endif
                                    </td>
                                    <td class="rpt-num">{{ number_format((int) $data['count']) }}</td>
                                    <td class="cr-col-amt rpt-num {{ ((float) ($data['amount'] ?? 0)) < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ number_format((float) ($data['amount'] ?? 0), 2) }}</strong></td>
                                    <td class="cr-col-share">
                                        <div class="cr-share"><span class="cr-share__bar" style="width:{{ max(0, min(100, $share)) }}%"></span><span class="cr-share__pct rpt-num">{{ rtrim(rtrim(number_format(max(0, $share), 1), '0'), '.') }}%</span></div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات للعرض</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($reportData) > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="cr-start">الإجمالي العام</td>
                                    <td class="rpt-num">{{ number_format((int) ($totals['count'] ?? 0)) }}</td>
                                    <td class="cr-col-amt rpt-num rpt-pos">{{ number_format((float) ($totals['amount'] ?? 0), 2) }}</td>
                                    <td class="cr-col-share rpt-num">100%</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

            {{-- ─────────────── الشهري (المصفوفة) ───────────────
                 شبكةٌ ماليّة على النموذج الذي اعتمده المستخدم (2026-09-22): عمودُ العميل
                 مثبَّت (الاسم، وتحته الكود والحالة والفئة في سطرٍ باهت)، ورأسُ الشهور مثبَّت
                 وتحت كلِّ شهرٍ عددُ عملائه، وخليّةُ الشهر رقمٌ بكسورٍ مصغَّرة على أربع درجاتٍ
                 من تظليل لون النظام بقدر المبلغ، والزياراتُ سطرٌ باهتٌ تحته (والفائتةُ
                 حمراء)، والصفرُ نقطةٌ خافتة، وزرُّ التفاصيل يظهر عند المرور فقط، والإجماليُّ
                 مع شريطِ حصّته ونسبتِها، وصفُّ الإجمالي وحدَه مثبَّتٌ أسفل الشبكة. --}}
            @elseif ($isMatrix)
                @php
                    $monthlyTotals = array_fill(1, 12, 0.0);
                    $monthlyCounts = array_fill(1, 12, 0);
                    foreach ($reportData as $allData) {
                        for ($m = 1; $m <= 12; $m++) {
                            $mv = (float) ($allData['months'][$m] ?? 0);
                            $monthlyTotals[$m] += $mv;
                            if (abs($mv) > 0.009) {
                                $monthlyCounts[$m]++;
                            }
                        }
                    }
                    $matrixList = $matrixRows ?? $reportData;
                    $rowNum = $crPager ? max(0, (int) $crPager['from'] - 1) : 0;
                    $mxIsClient = $groupBy === 'client';
                    $mxFixedCols = $mxIsClient ? 3 : 2;
                    /* أعمدةُ الشهور: ما عليه حركةٌ داخل مدى الفلترة وحدَه — لا اثنا عشر شهراً ثابتة */
                    $mxMonths = array_filter($crMonths, fn ($name, $m) => abs($monthlyTotals[$m]) > 0.009 || $monthlyCounts[$m] > 0, ARRAY_FILTER_USE_BOTH);
                    if ($mxMonths === []) {
                        $mxMonths = $crMonths;
                    }
                    $mxCols = $mxFixedCols + count($mxMonths) + 1 + ($mxIsClient ? 1 : 0);
                    $mxGrand = max(abs((float) ($totals['amount'] ?? 0)), 0.0001);
                    /* أقصى مبلغِ شهرٍ في الصفحة — درجةُ التظليل (١–٤) نسبةٌ إليه بجذرٍ تربيعي كي لا يبتلع الكبيرُ الصغار */
                    $mxMax = 0.0;
                    foreach ($matrixList as $mxRow) {
                        for ($m = 1; $m <= 12; $m++) {
                            $mxMax = max($mxMax, (float) ($mxRow['months'][$m] ?? 0));
                        }
                    }
                    $mxLevel = function (float $v) use ($mxMax): int {
                        if ($v <= 0.009 || $mxMax <= 0) {
                            return 0;
                        }
                        $r = sqrt($v / $mxMax);

                        return $r > 0.75 ? 4 : ($r > 0.5 ? 3 : ($r > 0.25 ? 2 : 1));
                    };
                    /* الرقمُ الصحيح بارزٌ والكسورُ مصغَّرةٌ باهتة */
                    $mxNum = function (float $v): string {
                        [$int, $dec] = explode('.', number_format(abs($v), 2));

                        return ($v < 0 ? '−' : '').$int.'<small>.'.$dec.'</small>';
                    };
                    $mxVisits = function (int $done, int $missed): string {
                        $parts = [];
                        if ($done === 1) {
                            $parts[] = 'زيارة';
                        } elseif ($done === 2) {
                            $parts[] = 'زيارتان';
                        } elseif ($done >= 3 && $done <= 10) {
                            $parts[] = $done.' زيارات';
                        } elseif ($done > 10) {
                            $parts[] = $done.' زيارة';
                        }
                        if ($missed > 0) {
                            $parts[] = '<span class="miss">'.$missed.' فائتة</span>';
                        }

                        return implode(' · ', $parts);
                    };
                @endphp
                <div class="rpt-scroll mx-scroll">
                    <table class="rpt-table mx-table" id="reportTable">
                        <thead>
                            <tr>
                                <th class="mx-fix mx-col-n">#</th>
                                <th class="mx-fix mx-col-client cr-start">{{ $groupByOptions[$groupBy] ?? 'التصنيف' }}</th>
                                @if ($mxIsClient)
                                    <th class="mx-col-last cr-start">آخر فاتورة / عهدة</th>
                                @endif
                                @foreach ($mxMonths as $m => $name)
                                    <th class="mx-col-m {{ in_array($m, [4, 7, 10], true) ? 'mx-q' : '' }}">{{ $name }}<span class="mx-sub">{{ $monthlyCounts[$m] ? number_format($monthlyCounts[$m]).' '.($mxIsClient ? 'عميل' : 'صفّ') : '—' }}</span></th>
                                @endforeach
                                <th class="mx-col-total">الإجمالي<span class="mx-sub">وحصّته من المجموع</span></th>
                                @if ($mxIsClient)
                                    <th class="no-print mx-col-act"><span class="sr-only">إجراءات</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($matrixList as $groupKey => $data)
                                @php
                                    $rowNum++;
                                    $mxTotal = (float) ($data['total'] ?? 0);
                                    $mxShare = max(0, min(100, $mxTotal / $mxGrand * 100));
                                    $mxStatusColor = str_starts_with((string) ($data['client_status_color'] ?? ''), '#') ? $data['client_status_color'] : '#6c757d';
                                    $mxRealCat = (string) ($data['client_real_category'] ?? '');
                                    $mxHasInvoice = isset($data['last_invoice_amount']) && $data['last_invoice_amount'] > 0;
                                    $mxHasDeposit = ! empty($data['has_deposit']);
                                @endphp
                                {{-- معرّفُ العميل واسمُه على الصفّ مرّةً واحدة؛ أزرارُ الشهور تقرؤهما منه --}}
                                <tr class="mx-row" data-client-id="{{ $mxIsClient ? ($data['client_id'] ?? '') : '' }}" data-client-name="{{ $data['name'] }}">
                                    <td class="mx-fix mx-col-n">{{ $rowNum }}</td>
                                    <td class="mx-fix mx-col-client cr-start">
                                        <div class="mx-name">
                                            @if (isset($data['level']) && $data['level'] > 0)
                                                <span class="cr-indent" style="--lvl:{{ (int) $data['level'] }}"></span><i class="fa fa-level-down fa-rotate-180 text-muted"></i>
                                            @endif
                                            {!! $crClientLink($mxIsClient && ! empty($data['client_id']) ? $data['client_id'] : null, $data['name'], 'غير محدد') !!}
                                            @if (isset($data['node_type']) && $groupBy === 'branch_tree')
                                                <span class="rpt-pill rpt-pill-secondary">{{ $data['node_type'] }}</span>
                                            @endif
                                        </div>
                                        @if ($mxIsClient)
                                            <div class="mx-meta">
                                                <span class="mx-code rpt-num">{{ $data['client_code'] ?? '-' }}</span>
                                                <i class="mx-dot"></i>
                                                <span class="cr-status" style="--s:{{ $mxStatusColor }}">{{ $data['client_status'] ?? 'غير محدد' }}</span>
                                                <i class="mx-dot"></i>
                                                @if (isset($data['target_change']) && $data['target_change'] !== 'none')
                                                    {!! $crTag($data['client_previous_category'] ?? 'غير مقيّم', $data['client_previous_category_color'] ?? null) !!}<i class="fa fa-long-arrow-left cr-arrow text-{{ $data['target_change'] == 'upgrade' ? 'success' : 'danger' }}" title="{{ $data['target_change'] == 'upgrade' ? 'تقدم في المستوى' : 'تراجع في المستوى' }}"></i>
                                                @endif
                                                {!! $crTag($data['client_category'] ?? 'غير مقيّم', $data['client_category_color'] ?? '#3b82f6') !!}
                                                @if ($mxRealCat !== '' && ! str_starts_with($mxRealCat, 'غير محدد'))
                                                    <i class="mx-dot"></i><span>{{ $mxRealCat }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    @if ($mxIsClient)
                                        <td class="mx-col-last cr-start">
                                            @if ($mxHasInvoice)
                                                <div class="mx-last" title="آخر فاتورة #{{ $data['last_invoice_id'] ?? '' }}"><b class="rpt-num">{!! $mxNum((float) $data['last_invoice_amount']) !!}</b><small class="rpt-num">{{ $data['last_invoice_date'] ?? '' }}</small></div>
                                            @endif
                                            @if ($mxHasDeposit)
                                                <div class="mx-last mx-last--dep {{ (int) $data['last_deposit'] === 0 ? 'is-zero' : '' }}" title="آخر جردٍ للعهدة — {{ $data['last_deposit_date'] ?? '' }}"><b class="rpt-num">{{ number_format((int) $data['last_deposit']) }}</b><small>عهدة</small><small class="rpt-num">{{ $data['last_deposit_date'] ?? '' }}</small></div>
                                            @endif
                                            @if (! $mxHasInvoice && ! $mxHasDeposit)
                                                <span class="mx-zero">·</span>
                                            @endif
                                        </td>
                                    @endif
                                    @foreach ($mxMonths as $m => $name)
                                        @php
                                            $mVal = (float) ($data['months'][$m] ?? 0);
                                            $visitStatus = $data['visits'][$m] ?? null;
                                            $visitsDone = is_array($visitStatus) ? (int) ($visitStatus['active'] ?? 0) : 0;
                                            $visitsMissed = is_array($visitStatus) ? (int) ($visitStatus['unactive'] ?? 0) : 0;
                                            $cellActive = abs($mVal) > 0.009 || $visitsDone > 0 || $visitsMissed > 0;
                                            $cellClass = $mVal < -0.009 ? 'is-neg' : ($mVal > 0.009 ? 'is-h'.$mxLevel($mVal) : '');
                                            $visitsLabel = $mxIsClient ? $mxVisits($visitsDone, $visitsMissed) : '';
                                        @endphp
                                        <td class="mx-cell {{ $cellClass }} {{ $cellActive ? 'has' : '' }} {{ in_array($m, [4, 7, 10], true) ? 'mx-q' : '' }}" data-month="{{ $m }}">
                                            @if (abs($mVal) > 0.009)
                                                <div class="mx-v rpt-num">{!! $mxNum($mVal) !!}</div>
                                            @else
                                                <div class="mx-zero">·</div>
                                            @endif
                                            @if ($visitsLabel !== '')
                                                <div class="mx-vis">{!! $visitsLabel !!}</div>
                                            @endif
                                            @if ($mxIsClient && ! empty($data['client_id']) && $cellActive)
                                                <button type="button" class="month-details-btn mx-btn" title="تفاصيل {{ $name }} (فواتير، تحصيلات، زيارات)"><i class="fa fa-list"></i></button>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="mx-col-total">
                                        <div class="mx-total rpt-num {{ $mxTotal < 0 ? 'rpt-neg' : '' }}">{!! $mxNum($mxTotal) !!}</div>
                                        <span class="mx-share"><i style="width:{{ $mxShare }}%"></i></span>
                                        <div class="mx-pct rpt-num">{{ rtrim(rtrim(number_format($mxShare, 1), '0'), '.') }}%</div>
                                    </td>
                                    @if ($mxIsClient)
                                        <td class="no-print mx-col-act">
                                            @if (! empty($data['client_id']))
                                                <div class="dropdown">
                                                    <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $data['name'] }}" title="إجراءات"><i class="fas fa-ellipsis-v"></i></button>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item client-notes-btn" href="#" data-client-id="{{ $data['client_id'] }}" data-client-name="{{ $data['name'] }}"><i class="feather icon-file-text"></i> ملاحظات العميل</a>
                                                        <a class="dropdown-item add-to-itinerary-btn" href="#" data-client-id="{{ $data['client_id'] }}" data-client-name="{{ $data['name'] }}"><i class="feather icon-map"></i> إضافة إلى خط السير</a>
                                                        <a class="dropdown-item send-notification-btn" href="#" data-client-id="{{ $data['client_id'] }}" data-client-name="{{ $data['name'] }}" data-client-code="{{ $data['client_code'] ?? '' }}"><i class="feather icon-bell"></i> إرسال إشعار</a>
                                                        <a class="dropdown-item" href="{{ route('clients.show', $data['client_id']) }}" target="_blank" rel="noopener"><i class="feather icon-user"></i> ملف العميل</a>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $mxCols }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات للعرض</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($reportData) > 0)
                            {{-- الإجماليُّ من الصفوف كلِّها لا الصفحة وحدَها، مثبَّتٌ أسفل الشبكة؛ عددُ عملاء كلِّ شهرٍ تحت اسمه في الرأس --}}
                            <tfoot>
                                <tr class="mx-foot-total">
                                    <td class="mx-fix mx-col-n"></td>
                                    <td class="mx-fix mx-col-client cr-start"><span class="mx-foot-lbl">إجمالي الشهر<small>{{ number_format(count($reportData)) }} {{ $mxIsClient ? 'عميل' : 'صفّ' }} · من كل الصفحات</small></span></td>
                                    @if ($mxIsClient)<td class="mx-col-last"></td>@endif
                                    @foreach ($mxMonths as $m => $name)
                                        <td class="rpt-num {{ $monthlyTotals[$m] < -0.009 ? 'rpt-neg' : '' }} {{ in_array($m, [4, 7, 10], true) ? 'mx-q' : '' }}">{{ abs($monthlyTotals[$m]) > 0.009 ? number_format($monthlyTotals[$m], 2) : '—' }}</td>
                                    @endforeach
                                    <td class="mx-col-total rpt-num mx-grand">{{ number_format((float) ($totals['amount'] ?? 0), 2) }}</td>
                                    @if ($mxIsClient)<td class="no-print"></td>@endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @include('sales::sals_report.salesRport.partials.collections-pager', ['pager' => $crPager, 'unit' => $crPagerUnit])

            {{-- ─────────────── المستويات (تغيّر الفئة) ─────────────── --}}
            @else
                @php $levelRow = 0; @endphp
                <div class="rpt-scroll">
                    <table class="rpt-table ag-table rpt-table--cr" id="reportTable">
                        <thead>
                            <tr>
                                <th class="cr-col-n">#</th>
                                <th class="cr-col-client cr-start">العميل</th>
                                <th class="cr-start">الفرع</th>
                                <th>الفئة السابقة</th>
                                <th>التغيّر</th>
                                <th>الفئة الحالية</th>
                                <th class="cr-col-amt">تحصيلات الفترة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($listRows ?? $reportData) as $row)
                                @php $levelRow++; @endphp
                                <tr class="cr-row">
                                    <td class="cr-col-n">{{ $levelRow }}</td>
                                    <td class="cr-col-client cr-start">
                                        <div class="cr-primary">{!! $crClientLink($row['client_id'] ?? null, $row['client_name'] ?? '') !!}</div>
                                        <div class="cr-secondary rpt-num">{{ $row['client_code'] ?? '-' }}</div>
                                    </td>
                                    <td class="cr-start cr-nowrap">{{ $row['branch_name'] }}</td>
                                    <td>{!! $crTag($row['client_previous_category'] ?? 'غير مقيّم', $row['client_previous_category_color'] ?? null) !!}</td>
                                    <td>
                                        @if ($row['target_change'] === 'upgrade')
                                            <span class="rpt-pill rpt-pill-success"><i class="fa fa-arrow-up"></i> ترقية</span>
                                        @elseif ($row['target_change'] === 'downgrade')
                                            <span class="rpt-pill rpt-pill-danger"><i class="fa fa-arrow-down"></i> تراجع</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{!! $crTag($row['client_category'] ?? 'غير مقيّم', $row['client_category_color'] ?? '#3b82f6') !!}</td>
                                    <td class="cr-col-amt rpt-num rpt-pos"><strong>{{ number_format((float) ($row['total_collections'] ?? 0), 2) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="rpt-empty"><i class="fas fa-inbox"></i> لا يوجد عملاء تغيّرت مستوياتهم خلال الفترة المحددة</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($reportData) > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="cr-start">الإجمالي العام <span class="cr-secondary">({{ number_format(count($reportData)) }} عميل)</span></td>
                                    <td class="cr-col-amt rpt-num rpt-pos"><strong>{{ number_format((float) ($totals['amount'] ?? 0), 2) }}</strong></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>


        {{-- ══════════ نافذة «ملاحظات الفاتورة» ══════════ --}}
        <div class="modal fade" id="crNotesModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content cr-nm cr-nm--compact">
                    <div class="cr-nm__head">
                        <div class="cr-nm__title">
                            <span class="cr-ico"><i class="feather icon-file-text"></i></span>
                            <h5 id="crNmTitle">ملاحظات العميل</h5>
                        </div>
                        <button type="button" class="cr-nm__close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">&times;</button>
                    </div>
                    <div class="cr-nm__body">
                        <aside class="cr-nm__box cr-nm__info" id="crNmInfo"></aside>

                        <div>
                            <div class="cr-nm__box cr-nm__sum">
                                <div class="is-ok">
                                    <div><small id="crNmPaidLabel">المحصل</small><strong id="crNmPaid">—</strong> <small>{{ $currencyLabel }}</small></div>
                                    <span class="cr-ico"><i class="feather icon-check-circle"></i></span>
                                </div>
                                <div id="crNmRemainingBox">
                                    <div><small id="crNmRemainingLabel">المتبقي</small><strong id="crNmRemaining">—</strong> <small>{{ $currencyLabel }}</small></div>
                                    <span class="cr-ico"><i class="feather icon-clock"></i></span>
                                </div>
                                <div>
                                    <div><small id="crNmTotalLabel">إجمالي الفاتورة</small><strong id="crNmTotal">—</strong> <small>{{ $currencyLabel }}</small></div>
                                    <span class="cr-ico"><i class="feather icon-bar-chart-2"></i></span>
                                </div>
                            </div>

                            <div class="cr-nm__split">
                                <section class="cr-nm__box cr-nm__notes">
                                    <div class="cr-nm__sec">
                                        <h6><i class="feather icon-message-square"></i> الملاحظات</h6>
                                        <div class="cr-nm__scope" id="crNmScope" role="group" aria-label="نطاق الملاحظات" hidden>
                                            <button type="button" class="is-active" data-scope="month">ملاحظات الشهر</button>
                                            <button type="button" data-scope="all">كل الملاحظات</button>
                                        </div>
                                        <span id="crNmCount"></span>
                                    </div>
                                    <div class="cr-tl" id="crNmList">
                                        <div class="cr-nm__state">جاري التحميل…</div>
                                    </div>
                                </section>

                                <section class="cr-nm__box cr-nm__add" id="crNmAdd">
                                    <span class="cr-nm__add-chip"><i class="feather icon-plus-square"></i> إضافة ملاحظة</span>
                                    <textarea id="crNmText" class="form-control" maxlength="2000" placeholder="أكتب ملاحظة هنا..."></textarea>
                                    <button type="button" class="cr-nm__save" id="crNmSave"><i class="feather icon-send"></i> حفظ</button>
                                    <div class="cr-nm__msg" id="crNmMsg" role="status"></div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lightbox Modal لعرض الصور دون مغادرة الصفحة --}}
        <div class="modal fade" id="imageLightboxModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 9999;">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                    <div class="modal-header border-0 p-0 justify-content-end">
                        <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity:1; text-shadow:none; font-size:2rem; background:rgba(0,0,0,0.5); border-radius:50%; width:40px; height:40px; line-height:40px; text-align:center; margin-bottom:10px;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-0 text-center">
                        <img id="lightboxImage" src="" alt="مرفق" style="max-width:100%; max-height:80vh; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    @php
        $crChartData = [
            'methodBars' => $dashboard['method_bars'] ?? [],
            'employeeBars' => $dashboard['employee_bars'] ?? [],
            'groupSlices' => $dashboard['group_slices'] ?? [],
            'trend' => (object) ($dashboard['trend'] ?? []),
            'top' => $dashboard['top'] ?? [],
            'net' => (float) ($dashboard['net'] ?? 0),
        ];
        $crExportMeta = [
            'groupLabel' => $groupLabel,
            'showGroup' => $isDetails && $groupBy !== 'client',
            'period' => 'من '.$startDate->format('Y-m-d').' إلى '.$endDate->format('Y-m-d'),
            'net' => (float) ($dashboard['net'] ?? 0),
            // المديونيةُ القائمة من الأستاذ — البطاقةُ نفسُها في الشاشة
            'remaining' => (float) ($dashboard['debt']['amount'] ?? 0),
            'clients' => (int) ($dashboard['client_count'] ?? 0),
            'invoices' => (int) ($dashboard['invoice_count'] ?? 0),
        ];
        $crRoutes = [
            'notes' => route('salesReports.collections.notes'),
            'store' => route('salesReports.collections.storeNote'),
            'from' => $startDate->format('Y-m-d'),
            'csrf' => csrf_token(),
            'apex' => asset('app-assets/vendors/js/charts/apexcharts.min.js'),
            'client' => route('clients.show', '__ID__'),
            'to' => $endDate->format('Y-m-d'),
        ];
    @endphp

    <script>
        function formatNumber(n, decimals = 2) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        function crEsc(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        const CR_CHARTS = @json($crChartData);
        const CR_DETAILS = @json($isDetails);
        const CR_MATRIX = @json((bool) ($isMatrix ?? false));
        // مدى التقرير وأسماءُ الشهور — لنافذة تفاصيل الشهر في المصفوفة (كانت تُكرَّر على كلّ زرّ)
        const CR_RANGE = { from: '{{ $startDate->format('Y-m-d') }}', to: '{{ $endDate->format('Y-m-d') }}' };
        const CR_MONTHS = { 1: 'يناير', 2: 'فبراير', 3: 'مارس', 4: 'أبريل', 5: 'مايو', 6: 'يونيو', 7: 'يوليو', 8: 'أغسطس', 9: 'سبتمبر', 10: 'أكتوبر', 11: 'نوفمبر', 12: 'ديسمبر' };
        /* صفوفُ التصدير والطباعة تُجلب عند أوّل نقرٍ من `?export=rows` لا تُضمَّن
           في الصفحة: كانت تُكتب JSON لكلّ صفٍّ بجانب صفّه فتثقل صفحةَ السنة
           بعشرات الميغابايت. الطلبُ يحمل فلاتر الشاشة نفسَها بلا رقم صفحة. */
        let CR_EXPORT_ROWS = null;
        function crLoadExportRows() {
            if (CR_EXPORT_ROWS) { return Promise.resolve(CR_EXPORT_ROWS); }
            var url = new URL(window.location.href);
            url.searchParams.set('export', 'rows');
            url.searchParams.delete('page');
            return fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
                .then(function (j) { CR_EXPORT_ROWS = Array.isArray(j.rows) ? j.rows : []; return CR_EXPORT_ROWS; });
        }
        const CR_EXPORT_META = @json($crExportMeta);
        const CR_ROUTES = @json($crRoutes);

        function crToken(name, fallback) {
            const v = getComputedStyle(document.querySelector('.cr') || document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        }

        // =====================================
        // المخطّطات: تحميلٌ ← رسمٌ، أو رسالةُ خطأٍ بزرّ إعادة المحاولة
        // =====================================
        const crCharts = {};

        function crChartState(id, html) {
            const el = document.getElementById(id);
            if (!el) { return null; }
            if (crCharts[id]) { try { crCharts[id].destroy(); } catch (e) { /* رُسم نصفَ رسم */ } delete crCharts[id]; }
            el.innerHTML = html || '';
            return el;
        }

        function crChartFailed(id, message) {
            crChartState(id, '<div class="cx-state"><div>تعذّر رسم المخطّط<br><small>' + crEsc(message) + '</small><br><button type="button" class="cx-btn" onclick="crDrawCharts()">إعادة المحاولة</button></div></div>');
        }

        function crRenderChart(id, options) {
            const el = crChartState(id, '');
            if (!el) { return; }
            try {
                crCharts[id] = new ApexCharts(el, options);
                const done = crCharts[id].render();
                if (done && typeof done.catch === 'function') {
                    done.catch(function (e) { crChartFailed(id, e && e.message ? e.message : 'خطأ غير معروف'); });
                }
            } catch (e) {
                crChartFailed(id, e && e.message ? e.message : 'خطأ غير معروف');
            }
        }

        /* المكتبةُ تأتي من القالب العام؛ إن لم تصل (ملفٌّ لم يُحمَّل) تُطلب مرّةً من مسارها */
        function crEnsureApex() {
            if (typeof ApexCharts !== 'undefined') { return Promise.resolve(); }
            return new Promise(function (resolve, reject) {
                const tag = document.createElement('script');
                tag.src = CR_ROUTES.apex;
                tag.onload = function () { typeof ApexCharts !== 'undefined' ? resolve() : reject(new Error('مكتبة الرسوم لم تُحمَّل')); };
                tag.onerror = function () { reject(new Error('تعذّر تحميل مكتبة الرسوم')); };
                document.head.appendChild(tag);
            });
        }

        function crTrendPoints(mode) {
            const days = Object.keys(CR_CHARTS.trend || {}).sort();
            if (mode === 'day') { return days.map(function (d) { return [d, CR_CHARTS.trend[d]]; }); }
            const buckets = {};
            days.forEach(function (d) {
                let key = d.slice(0, 7) + '-01';
                if (mode === 'week') {
                    // الأسبوعُ يبدأ السبت
                    const dt = new Date(d + 'T00:00:00');
                    dt.setDate(dt.getDate() - ((dt.getDay() + 1) % 7));
                    key = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
                }
                buckets[key] = (buckets[key] || 0) + CR_CHARTS.trend[d];
            });
            return Object.keys(buckets).sort().map(function (k) { return [k, buckets[k]]; });
        }

        function crDrawTrend(mode) {
            const points = crTrendPoints(mode);
            const sub = document.getElementById('crTrendSub');
            const title = document.getElementById('crTrendTitle');
            if (title) { title.textContent = { day: 'التحصيل اليومي', week: 'التحصيل الأسبوعي', month: 'التحصيل الشهري' }[mode]; }
            if (!points.length) {
                if (sub) { sub.textContent = ''; }
                crChartState('crTrendChart', '<div class="cx-state">لا توجد تحصيلات في هذه الفترة</div>');
                return;
            }
            const unit = { day: 'يوم', week: 'أسبوع', month: 'شهر' }[mode];
            const total = points.reduce(function (a, p) { return a + p[1]; }, 0);
            // المتوسّطُ وحده — الإجماليُّ حُذف من ترويسة المخطّط بطلب المستخدم
            if (sub) { sub.textContent = 'متوسط ' + formatNumber(total / points.length, 0) + ' / ' + unit; }
            const brand = crToken('--app-primary', '#1e63e9');
            const bad = crToken('--app-danger', '#e5484d');
            const muted = crToken('--app-muted', '#7a8599');
            /* اليوميُّ خطٌّ مستقيمُ القطع (المنحنى الأملس يرسم قيعاناً وقمماً لم تقع)،
               والأسبوعيُّ والشهريُّ أعمدة: كلُّ عمودٍ مجموعُ مدّته. والمحورُ من الصفر
               كي لا يُقرأ فرقُ ١٠٪ هبوطاً إلى القاع. */
            const bars = mode !== 'day' || points.length <= 2;
            const label = function (key) {
                const parts = key.split('-');
                return mode === 'month' ? parts[1] + '/' + parts[0].slice(2) : parts[2] + '/' + parts[1];
            };
            const values = points.map(function (p) { return Math.round(p[1] * 100) / 100; });
            const low = Math.min.apply(null, values.concat([0]));
            crRenderChart('crTrendChart', {
                chart: { type: bars ? 'bar' : 'area', height: 178, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: crToken('--app-font-family', 'Cairo, sans-serif'), parentHeightOffset: 0, animations: { speed: 300 } },
                series: [{ name: 'صافي التحصيل', data: values }],
                colors: [function (ctx) { return ctx.value < 0 ? bad : brand; }],
                stroke: { width: bars ? 0 : 2, curve: 'straight' },
                plotOptions: { bar: { columnWidth: points.length > 20 ? '80%' : '55%', borderRadius: 2 } },
                dataLabels: { enabled: false },
                markers: { size: !bars && points.length <= 40 ? 2.5 : 0, hover: { size: 4 } },
                fill: bars ? { opacity: .9 } : { type: 'gradient', gradient: { opacityFrom: .25, opacityTo: .02 } },
                grid: { borderColor: 'rgba(125,133,153,.18)', strokeDashArray: 3, padding: { top: -14, right: 6, left: 6, bottom: -4 } },
                xaxis: {
                    categories: points.map(function (p) { return label(p[0]); }),
                    tickAmount: Math.min(points.length - 1, 8) || undefined,
                    labels: { rotate: 0, trim: false, hideOverlappingLabels: true, style: { colors: muted, fontSize: '10px' } },
                    axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false }
                },
                yaxis: { min: low, forceNiceScale: true, labels: { style: { colors: muted, fontSize: '10px' }, formatter: function (v) { return new Intl.NumberFormat('en', { notation: 'compact' }).format(v); } } },
                tooltip: {
                    x: { formatter: function (v, o) { const p = points[o.dataPointIndex]; return p ? (mode === 'week' ? 'أسبوع يبدأ ' : '') + (mode === 'month' ? p[0].slice(0, 7) : p[0]) : v; } },
                    y: { formatter: function (v) { return formatNumber(v) + ' ريال'; } }
                }
            });
        }

        function crDrawMethod() {
            const el = document.getElementById('crMethodList');
            if (!el) { return; }
            const bars = CR_CHARTS.employeeBars || [];
            if (!bars.length) {
                el.innerHTML = '<li class="cx-none">لا توجد تحصيلات في هذه الفترة</li>';
                return;
            }
            const tone = { cash: crToken('--app-primary', '#1e63e9'), bank: crToken('--app-success', '#22a06b'), other: crToken('--app-muted', '#7a8599') };
            const kind = { cash: 'صافي تحصيل الموظف', bank: 'صافي تحصيل الموظف', other: 'مجموع بقيّة الموظفين' };
            const max = Math.max.apply(null, bars.map(function (b) { return Math.abs(b.amount); }).concat([1]));
            const net = CR_CHARTS.net || 0;
            const line = function (b, extra) {
                const share = net > 0 ? formatNumber(b.amount / net * 100, 1) + '%' : '';
                return '<li ' + (extra || '') + ' style="--c:' + crEsc(tone[b.method] || tone.cash) + '" title="' + crEsc(b.name) + ' — ' + crEsc(kind[b.method] || '') + ' — ' + formatNumber(b.amount) + ' ريال' + (share ? ' (' + share + ')' : '') + (b.count ? ' — ' + b.count + ' سند' : '') + '">'
                    + '<span class="n"><i></i></span><span class="nm">' + crEsc(b.name) + '</span>'
                    + '<span class="am cx-num">' + formatNumber(b.amount, 0) + '<span class="pc">' + share + '</span></span>'
                    + '<span class="bar"><i style="width:' + Math.max(2, Math.abs(b.amount) / max * 100) + '%"></i></span></li>';
            };
            /* صفُّ «حسابات أخرى» يُفتح على حساباته: كلٌّ باسمه ومبلغه وحصّته،
               ومجموعُها هو رقمُ الصفّ. */
            const open = el.dataset.open === '1';
            el.classList.toggle('is-open', open);
            el.innerHTML = bars.map(function (b) {
                if (!b.items || !b.items.length) { return line(b); }
                const head = line({ name: (open ? '▾ ' : '◂ ') + b.name, method: 'other', amount: b.amount }, 'class="cx-more" role="button" tabindex="0" aria-expanded="' + open + '"');
                return head + (open ? b.items.map(function (item) { return line(item, 'class="cx-sub"'); }).join('') : '');
            }).join('');
        }

        $(document).on('click keydown', '#crMethodList .cx-more', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') { return; }
            event.preventDefault();
            const el = document.getElementById('crMethodList');
            el.dataset.open = el.dataset.open === '1' ? '0' : '1';
            crDrawMethod();
            if (el.dataset.open === '1') { el.scrollTop = el.scrollHeight; }
        });

        function crDrawRank(key) {
            const el = document.getElementById('crRank');
            if (!el) { return; }
            const list = (CR_CHARTS.top && CR_CHARTS.top[key]) || [];
            if (!list.length) {
                el.innerHTML = '<li class="cx-none">لا توجد مبالغ موجبة لعرضها</li>';
                return;
            }
            const max = Math.max.apply(null, list.map(function (x) { return x.amount; }).concat([1]));
            const net = CR_CHARTS.net || 0;
            el.innerHTML = list.map(function (item, index) {
                const share = net > 0 ? ' (' + formatNumber(item.amount / net * 100, 1) + '%)' : '';
                const name = item.client_id
                    ? '<a class="nm" target="_blank" rel="noopener" href="' + crEsc(CR_ROUTES.client.replace('__ID__', item.client_id)) + '">' + crEsc(item.name) + '</a>'
                    : '<span class="nm">' + crEsc(item.name) + '</span>';
                return '<li title="' + crEsc(item.name) + ' — ' + formatNumber(item.amount) + share + '"><span class="n cx-num">' + (index + 1) + '</span>' + name
                    + '<span class="am cx-num">' + formatNumber(item.amount, 0) + '</span><span class="bar"><i style="width:' + Math.max(3, item.amount / max * 100) + '%"></i></span></li>';
            }).join('');
        }

        function crActiveKey(id, fallback) {
            const on = document.querySelector('#' + id + ' button.is-active');
            return on ? on.dataset.k : fallback;
        }

        function crDrawCharts() {
            crDrawRank(crActiveKey('crRankSeg', 'client'));
            crDrawMethod();
            crChartState('crTrendChart', '<div class="cx-sk"></div>');
            crEnsureApex().then(function () {
                crDrawTrend('month');
            }).catch(function (e) {
                crChartFailed('crTrendChart', e.message);
            });
        }

        function crBindSegments() {
            [['crTrendSeg', crDrawTrend], ['crRankSeg', crDrawRank]].forEach(function (pair) {
                const box = document.getElementById(pair[0]);
                if (!box) { return; }
                box.addEventListener('click', function (event) {
                    const button = event.target.closest('button');
                    if (!button) { return; }
                    box.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === button); });
                    pair[1](button.dataset.k);
                });
            });
        }

        // =====================================
        // ترقيم صفحات جدول التفاصيل — يُخفي بالصنف فيبقى التصدير كاملاً
        // =====================================
        function crInitPager() {
            /* الترقيمُ صار في الخادم: الصفحةُ تحمل صفوفَها وحدَها، وشريطُ الصفحات
               روابطُ مرسومةٌ في القالب. كان الجدولُ يحمل الصفوفَ كلَّها ويُخفي ما ليس
               في الصفحة بصنف، فصفحةُ سنةٍ كاملة تجاوزت خمسين ميغابايت. لم يبقَ
               للسكربت إلّا حجمُ الصفحة: تغييرُه يعيد التحميل من الصفحة الأولى. */
            const sizeEl = document.getElementById('crPageSize');
            if (!sizeEl) { return; }
            sizeEl.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('per_page', sizeEl.value);
                url.searchParams.delete('page');
                window.location.assign(url.toString());
            });
        }

        // =====================================
        // نافذة الملاحظات
        // =====================================
        const crNotes = { clientId: null, invoiceId: null, codes: [] };

        function crImageLink(url, cls) {
            return `<img src="${crEsc(url)}" alt="صورة" loading="lazy" class="${cls || ''}" data-cr-lightbox="${crEsc(url)}" onerror="crImageMissing(this)">`;
        }

        // صورةٌ حُذف ملفُّها: تُزال، ويُخفى قسمُ «الصور» إن لم يبقَ فيه شيء
        function crImageMissing(img) {
            const thumbs = img.closest('.cr-thumbs');
            if (img.classList.contains('cr-comp__thumb')) {
                const ph = document.createElement('span');
                ph.className = 'cr-comp__thumb';
                ph.innerHTML = '<i class="feather icon-image"></i>';
                img.replaceWith(ph);
                return;
            }
            img.remove();
            if (thumbs && !thumbs.querySelector('img')) {
                const sec = thumbs.closest('.cr-tl__sec');
                if (sec) sec.hidden = true;
            }
        }

        function crNoteHtml(c) {
            const chips = [];
            chips.push(`<span class="cr-chip"><i class="feather icon-user"></i> ${crEsc(c.employee)}</span>`);
            if (c.related) chips.push('<span class="cr-chip cr-chip--brand"><i class="feather icon-link"></i> على هذه الفاتورة</span>');
            if (c.visit && c.visit.number) chips.push(`<span class="cr-chip"><i class="fas fa-hashtag"></i> <bdi>${crEsc(c.visit.number)}</bdi></span>`);
            if (c.entries > 1) chips.push(`<span class="cr-chip" title="ملاحظة الزيارة وملاحظات مستنداتها في بطاقةٍ واحدة"><i class="fas fa-layer-group"></i> ملاحظات مدمجة: ${crEsc(c.entries)}</span>`);
            if (c.process) chips.push(`<span class="cr-chip">${crEsc(c.process)}</span>`);
            if (c.visit) {
                const tone = c.visit.tone === 'done' ? 'ok' : (c.visit.tone === 'cancel' ? 'bad' : 'warn');
                chips.push(`<span class="cr-chip cr-chip--${tone}"><i class="fa ${crEsc(c.visit.icon)}"></i> ${crEsc(c.visit.label)}</span>`);
            }
            if (c.site_type) chips.push(`<span class="cr-chip">${crEsc(c.site_type)}</span>`);
            if (c.custody && c.custody.count !== null && c.custody.count !== undefined) {
                chips.push(`<span class="cr-chip cr-chip--warn"><i class="feather icon-package"></i> العهدة: ${crEsc(c.custody.count)} ${crEsc(c.custody.label)}</span>`);
            }

            let docs = '';
            if (c.documents && c.documents.length) {
                docs = `<div class="cr-tl__sec"><div class="cr-tl__sec-title">المستندات</div><div class="cr-tl__meta">` +
                    c.documents.map(d => `<a class="cr-chip ${d.tone === 'out' ? 'cr-chip--bad' : 'cr-chip--ok'}" href="${crEsc(d.url)}" target="_blank" rel="noopener">
                        <i class="fa ${crEsc(d.icon)}"></i> ${crEsc(d.kind)} ${crEsc(d.number)}${d.amount !== null ? ' — ' + formatNumber(d.amount) : ''}</a>`).join('') +
                    `</div></div>`;
            }

            let comps = '';
            if (c.competitors && c.competitors.length) {
                comps = `<div class="cr-tl__sec"><div class="cr-tl__sec-title">المنافسون (${c.competitors.length})</div><div class="cr-comp">` +
                    c.competitors.map(o => `<div class="cr-comp__item">
                        ${o.photo_url ? crImageLink(o.photo_url, 'cr-comp__thumb') : '<span class="cr-comp__thumb"><i class="feather icon-image"></i></span>'}
                        <div><div class="cr-comp__name">${crEsc(o.name)}</div><div class="cr-comp__qty">${crEsc(o.asset_count)} ${crEsc(o.asset_label)}</div></div>
                    </div>`).join('') + `</div></div>`;
            } else if (c.no_competitor) {
                comps = '<div class="cr-tl__sec"><span class="cr-chip"><i class="feather icon-slash"></i> لا يوجد منافس</span></div>';
            }

            let images = '';
            if (c.images && c.images.length) {
                images = `<div class="cr-tl__sec"><div class="cr-tl__sec-title">الصور (${c.images.length})</div><div class="cr-thumbs">` +
                    c.images.map(u => crImageLink(u)).join('') + `</div></div>`;
            }

            let files = '';
            if (c.files && c.files.length) {
                files = `<div class="cr-tl__sec"><div class="cr-tl__sec-title">المرفقات (${c.files.length})</div><div class="cr-tl__meta">` +
                    c.files.map(f => `<a class="cr-chip" href="${crEsc(f.url)}" target="_blank" rel="noopener"><i class="feather icon-paperclip"></i> ${crEsc(f.name)}</a>`).join('') +
                    `</div></div>`;
            }

            // أزرارُ بطاقة شاشة العميل: طباعة، تعديل، حذف (بالصلاحية)
            const attachCount = (c.images ? c.images.length : 0) + (c.files ? c.files.length : 0);
            let actions = `<div class="cr-tl__foot"><span>${attachCount ? `<i class="fas fa-paperclip"></i> مرفقات: ${attachCount}` : ''}</span><div class="cr-tl__acts">`;
            if (c.print_url) actions += `<a class="ag-actions-btn" href="${crEsc(c.print_url)}" target="_blank" rel="noopener" title="طباعة"><i class="fas fa-print"></i></a>`;
            if (c.edit_url) actions += `<a class="ag-actions-btn" href="${crEsc(c.edit_url)}" target="_blank" rel="noopener" title="تعديل"><i class="fas fa-edit"></i></a>`;
            if (c.destroy_url) actions += `<form action="${crEsc(c.destroy_url)}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟')"><input type="hidden" name="_token" value="${crEsc(CR_ROUTES.csrf)}"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="ag-actions-btn cr-act-danger" title="حذف"><i class="fas fa-trash"></i></button></form>`;
            actions += `</div></div>`;

            const text = (c.description || '').trim();
            return `<div class="cr-tl__item ${c.related ? 'is-related' : ''}">
                <span class="cr-tl__dot"></span>
                <div class="cr-tl__box">
                    <div class="cr-tl__top">
                        <div class="cr-tl__text ${text ? '' : 'is-empty'}">${text ? crEsc(text) : 'بلا نصّ'}</div>
                        <span class="cr-tl__time">${crEsc(c.date_label || '')}${c.time_label ? ' ' + crEsc(c.time_label) : ''}</span>
                    </div>
                    <div class="cr-tl__meta">${chips.join('')}</div>
                    ${docs}${comps}${images}${files}
                    ${actions}
                </div>
            </div>`;
        }

        // حركةٌ مالية أو زيارة — سطرٌ مضغوط في الخط الزمني نفسه
        function crActivityHtml(a) {
            const tone = { invoice: 'ok', collection: 'brand', return: 'bad', visit: 'warn' }[a.kind] || '';
            const icon = { invoice: 'icon-file-text', collection: 'icon-dollar-sign', return: 'icon-corner-down-left', visit: 'icon-map-pin' }[a.kind] || 'icon-activity';
            const number = a.number
                ? (a.url ? `<a href="${crEsc(a.url)}" target="_blank" rel="noopener">${crEsc(a.number)}</a>` : crEsc(a.number))
                : '';
            return `<div class="cr-tl__item is-activity cr-tl--${a.kind}">
                <span class="cr-tl__dot"></span>
                <div class="cr-tl__box">
                    <div class="cr-tl__top">
                        <div class="cr-tl__text">
                            <span class="cr-chip cr-chip--${tone}"><i class="feather ${icon}"></i> ${crEsc(a.label)}</span>
                            ${number ? `<span class="cr-act-num">${number}</span>` : ''}
                            ${a.amount !== null && a.amount !== undefined ? `<strong class="cr-act-amt">${formatNumber(a.amount)}</strong>` : ''}
                            ${a.account ? `<span class="cr-chip">${crEsc(a.account)}</span>` : ''}
                        </div>
                        <span class="cr-tl__time">${crEsc(a.date || '')}${a.time ? ' ' + crEsc(a.time) : ''}</span>
                    </div>
                    ${a.note ? `<div class="cr-act-note">${crEsc(a.note)}</div>` : ''}
                </div>
            </div>`;
        }

        function crRenderCards(payload) {
            crNotes.payload = payload;
            const list = document.getElementById('crNmList');
            /* نافذةُ الشهر: ملاحظاتُ الشهر افتراضاً، و«كل الملاحظات» بزرّ — الحمولةُ
               تحمل ملاحظاتِ العميل كلَّها كما في شاشته، معلَّماً منها ما في الشهر. */
            const isMonth = crNotes.mode === 'month';
            const allCards = payload.cards || [];
            const monthCards = allCards.filter(c => c.in_range);
            const cards = isMonth && crNotes.scope !== 'all' ? monthCards : allCards;
            const activities = payload.activities || [];
            const scopeBox = document.getElementById('crNmScope');
            scopeBox.hidden = !isMonth;
            if (isMonth) {
                scopeBox.querySelector('[data-scope="month"]').textContent = `ملاحظات الشهر (${monthCards.length})`;
                scopeBox.querySelector('[data-scope="all"]').textContent = `كل الملاحظات (${allCards.length})`;
                scopeBox.querySelectorAll('button').forEach(b => b.classList.toggle('is-active', b.dataset.scope === (crNotes.scope === 'all' ? 'all' : 'month')));
            }
            const parts = [];
            if (cards.length) parts.push(`${cards.length} ملاحظة`);
            if (activities.length) parts.push(`${activities.length} حركة`);
            document.getElementById('crNmCount').textContent = parts.join(' · ');

            document.getElementById('crNmAdd').hidden = !payload.can_add;

            if (payload.summary && crNotes.mode !== 'invoice') {
                document.getElementById('crNmPaid').textContent = formatNumber(payload.summary.collected);
                document.getElementById('crNmRemaining').textContent = formatNumber(payload.summary.remaining);
                document.getElementById('crNmTotal').textContent = formatNumber(payload.summary.sales);
                document.getElementById('crNmRemainingBox').classList.toggle('is-due', payload.summary.remaining > 0);
            }
            crFillInfo(payload);

            const items = cards.map(c => ({ ts: c.timestamp || 0, html: crNoteHtml(c) }))
                .concat(activities.map(a => ({ ts: a.ts || 0, html: crActivityHtml(a) })))
                .sort((x, y) => y.ts - x.ts);

            if (!items.length) {
                list.innerHTML = `<div class="cr-nm__state">${isMonth && crNotes.scope !== 'all' ? 'لا توجد ملاحظات ولا حركات لهذا العميل في هذا الشهر' + (allCards.length ? ' — اضغط «كل الملاحظات» لعرض بقيّة ملاحظاته' : '') : 'لا توجد ملاحظات مسجّلة لهذا العميل'}</div>`;
                return;
            }
            list.innerHTML = items.map(i => i.html).join('');
        }

        // لوحة البيانات على جانب النافذة — تتبدّل بحسب المدخل
        function crFillInfo(payload) {
            const d = crNotes.row || {};
            const c = (payload && payload.client) || {};
            const range = (payload && payload.range) || {};
            const clientName = c.url
                ? `<a href="${crEsc(c.url)}" target="_blank" rel="noopener">${crEsc(c.name || d.clientName || '')}</a>`
                : crEsc(d.clientName || '—');
            const items = [];
            if (crNotes.mode === 'invoice') {
                items.push(['رقم الفاتورة', crNotes.codes.length ? crEsc(crNotes.codes.join('، ')) : '—', 'icon-file']);
                items.push(['العميل', clientName, 'icon-user']);
                items.push(['تاريخ التحصيل', crEsc(d.date || '—'), 'icon-calendar']);
                items.push(['طريقة التحصيل', crEsc(d.method || '—'), 'icon-credit-card']);
            } else {
                items.push(['العميل', clientName, 'icon-user']);
                if (crNotes.mode === 'month') {
                    items.push(['الشهر', crEsc(range.month || d.monthName || '—'), 'icon-calendar']);
                } else if (range.from) {
                    items.push(['الفترة', `${crEsc(range.from)} — ${crEsc(range.to)}`, 'icon-calendar']);
                }
            }
            if (payload && payload.client) {
                if (c.code) items.push(['كود العميل', crEsc(c.code), 'icon-hash']);
                items.push(['تابع لـ', crEsc(c.branch), 'icon-home']);
                items.push(['المندوب', crEsc(c.employee), 'icon-briefcase']);
                items.push(['الحالة', `<span class="cr-chip" style="background:${crEsc(c.status_color)}22;color:${crEsc(c.status_color)}">${crEsc(c.status)}</span>`, 'icon-flag']);
                items.push(['الرصيد', `${formatNumber(c.balance)} ريال`, 'icon-credit-card']);
                if (c.last_visit) items.push(['آخر زيارة', `${crEsc(c.last_visit)} ${c.last_visit_done ? '<span class="cr-chip cr-chip--ok">مكتملة</span>' : `<span class="cr-chip cr-chip--${c.last_visit_tone === 'danger' ? 'bad' : 'warn'}">${crEsc(c.last_visit_label || 'لم تتم')}</span>`}`, 'icon-map-pin']);
            }
            document.getElementById('crNmInfo').innerHTML = items.map(([label, value, icon]) => `
                <div class="cr-nm__item">
                    <div><small>${label}</small><strong>${value}</strong></div>
                    <span class="cr-ico"><i class="feather ${icon}"></i></span>
                </div>`).join('');
        }

        /**
         * mode: invoice (زرّ «عرض» في صفّ السند) | client (ملاحظات العميل) | month (زرّ الشهر)
         */
        function crOpenNotes(data) {
            crNotes.mode = data.mode || 'client';
            crNotes.scope = 'month';
            crNotes.payload = null;
            document.getElementById('crNmScope').hidden = true;
            crNotes.clientId = data.clientId;
            crNotes.invoiceId = data.invoiceId || '';
            crNotes.codes = data.codes || [];
            crNotes.month = data.month || '';
            crNotes.dateFrom = data.dateFrom || CR_ROUTES.from;
            crNotes.dateTo = data.dateTo || CR_ROUTES.to;
            crNotes.row = data;

            const titles = { invoice: 'ملاحظات الفاتورة', client: 'ملاحظات العميل', month: 'ملاحظات الشهر' + (data.monthName ? ' — ' + data.monthName : '') };
            document.getElementById('crNmTitle').textContent = titles[crNotes.mode];
            document.getElementById('crNmPaidLabel').textContent = 'المحصل';
            document.getElementById('crNmRemainingLabel').textContent = 'المتبقي';
            document.getElementById('crNmTotalLabel').textContent = crNotes.mode === 'invoice' ? 'إجمالي الفاتورة' : 'إجمالي الفواتير';

            const isInvoice = crNotes.mode === 'invoice';
            document.getElementById('crNmPaid').textContent = isInvoice ? (data.amount || '—') : '…';
            document.getElementById('crNmRemaining').textContent = isInvoice ? (data.remaining || '—') : '…';
            document.getElementById('crNmTotal').textContent = isInvoice ? (data.total || '—') : '…';
            const remainingNumber = parseFloat(String(data.remaining || '').replace(/,/g, ''));
            document.getElementById('crNmRemainingBox').classList.toggle('is-due', isInvoice && remainingNumber > 0);
            crFillInfo(null);
            document.getElementById('crNmText').value = '';
            document.getElementById('crNmMsg').textContent = '';
            document.getElementById('crNmCount').textContent = '';
            document.getElementById('crNmList').innerHTML = '<div class="cr-nm__state">جاري التحميل…</div>';

            $('#crNotesModal').modal('show');

            $.ajax({
                url: CR_ROUTES.notes,
                type: 'GET',
                data: {
                    client_id: crNotes.clientId,
                    invoice_codes: crNotes.codes,
                    mode: crNotes.mode,
                    month: crNotes.month,
                    date_from: crNotes.dateFrom,
                    date_to: crNotes.dateTo
                },
                success: crRenderCards,
                error: function (xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'تعذّر جلب الملاحظات';
                    document.getElementById('crNmList').innerHTML = `<div class="cr-nm__state">${crEsc(msg)}</div>`;
                }
            });
        }

        $(document).on('click', '#crNmScope button', function () {
            crNotes.scope = this.dataset.scope;
            if (crNotes.payload) { crRenderCards(crNotes.payload); }
        });

        // «ملاحظات العميل» — في جدول التفاصيل وقائمة العرض الشهري
        $(document).on('click', '.client-notes-btn', function (e) {
            e.preventDefault();
            crOpenNotes({ mode: 'client', clientId: $(this).data('client-id'), clientName: $(this).data('client-name') });
        });

        // زرّ الشهر في العرض الشهري
        /* العميلُ على الصفّ والشهرُ على الخليّة (المصفوفة الشهرية) — لا على كلّ زرّ؛
           المدى ثابتٌ للصفحة كلِّها في `CR_RANGE`. */
        $(document).on('click', '.month-details-btn', function () {
            const $b = $(this);
            const $tr = $b.closest('tr');
            const month = Number($b.closest('td').data('month') || $b.data('month'));
            crOpenNotes({
                mode: 'month',
                clientId: $tr.data('client-id') || $b.data('client-id'),
                clientName: $tr.data('client-name') || $b.data('client-name'),
                month: month,
                monthName: CR_MONTHS[month] || $b.data('month-name'),
                dateFrom: CR_RANGE.from,
                dateTo: CR_RANGE.to
            });
        });

        $(document).on('click', '#crNmSave', function () {
            const $btn = $(this);
            const $msg = $('#crNmMsg');
            const text = $.trim($('#crNmText').val());
            if (!text) {
                $msg.removeClass('is-ok').addClass('is-bad').text('اكتب نصّ الملاحظة أوّلاً');
                return;
            }
            $btn.prop('disabled', true);
            $.ajax({
                url: CR_ROUTES.store,
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    client_id: crNotes.clientId,
                    invoice_id: crNotes.invoiceId || null,
                    invoice_codes: crNotes.codes,
                    mode: crNotes.mode,
                    month: crNotes.month || '',
                    date_from: crNotes.dateFrom || '',
                    date_to: crNotes.dateTo || '',
                    description: text
                },
                success: function (payload) {
                    $('#crNmText').val('');
                    $msg.removeClass('is-bad').addClass('is-ok').text(payload.message || 'تمت الإضافة');
                    crRenderCards(payload);
                },
                error: function (xhr) {
                    const r = xhr.responseJSON || {};
                    const first = r.errors ? Object.values(r.errors)[0][0] : null;
                    $msg.removeClass('is-ok').addClass('is-bad').text(first || r.error || 'تعذّر حفظ الملاحظة');
                },
                complete: function () { $btn.prop('disabled', false); }
            });
        });

        $(document).on('click', '[data-cr-lightbox]', function () {
            openImageLightbox($(this).attr('data-cr-lightbox'));
        });

        function openImageLightbox(imageSrc) {
            $('#lightboxImage').attr('src', imageSrc);
            $('#imageLightboxModal').modal('show');
        }

        $(document).ready(function () {
            // نافذتا الملاحظات والصور خارج إطار `.content-body` كي لا يقصّهما
            $('#crNotesModal, #imageLightboxModal').appendTo('body');

            crInitPager();
            crBindSegments();
            /* الرسمُ لا ينتظر الخطوط: `document.fonts.ready` قد يتأخّر أو لا يُحسم
               حين يتعذّر تحميل خطّ، فتبقى المخطّطاتُ فارغةً بلا رسالة. */
            crDrawCharts();

            $(document).on('click', '[data-cr-toggle]', function () {
                const target = document.querySelector(this.dataset.crToggle);
                if (!target) return;
                target.hidden = !target.hidden;
                this.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
            });

            if ($.fn.select2) {
                $('.rpt-select2').each(function () {
                    var $el = $(this);
                    if ($el.data('select2')) {
                        $el.select2('destroy');
                    }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function () { return 'لا توجد نتائج'; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            $('#printBtn').on('click', printCollectionsReport);
            $('#exportExcel').on('click', exportCollectionsReport);

            $('.period-btn').on('click', function () {
                $('#year_filter').val('').trigger('change');
                changePeriod($(this).data('period'));
            });

            $('#year_filter').on('change', function () {
                if ($(this).val() !== '') {
                    $('#date_from, #date_to').prop('readonly', true);
                    $('.period-btn').removeClass('is-active');
                    $('#periodInput').val('year_filter');
                } else {
                    $('#date_from, #date_to').prop('readonly', false);
                }
            });
            if ($('#year_filter').val() !== '') {
                $('#date_from, #date_to').prop('readonly', true);
            }

            // تعديل التاريخ يدوياً = فترةٌ مخصّصة
            $('#date_from, #date_to').on('change', function () {
                if ($('#periodInput').val() !== 'year_filter') {
                    $('#periodInput').val('custom');
                    $('.period-btn').removeClass('is-active');
                    $('.period-btn[data-period="custom"]').addClass('is-active');
                }
            });
        });

        function changePeriod(period) {
            period = String(period);
            $('#periodInput').val(period);
            $('.period-btn').removeClass('is-active');
            $(`.period-btn[data-period="${period}"]`).addClass('is-active');
            if (period !== 'custom') {
                $('#date_from').val('');
                $('#date_to').val('');
                $('#filterForm').submit();
            } else {
                $('#date_from').focus();
            }
        }

        // =====================================
        // تصدير Excel
        // =====================================
        function exportCollectionsReport() {
            // صفوفُ التفاصيل أو المصفوفة تُجلب أوّلاً إن لم تكن قد جُلبت، ثمّ يُعاد النداء
            if ((CR_DETAILS || CR_MATRIX) && !CR_EXPORT_ROWS) {
                crLoadExportRows()
                    .then(function () { exportCollectionsReport(); })
                    .catch(function () { alert('تعذّر تحميل صفوف التصدير — أعد المحاولة'); });
                return;
            }
            var totals = {
                count: {{ (int) ($totals['count'] ?? 0) }},
                amount: {{ (float) ($totals['amount'] ?? 0) }}
            };
            var tableData = [];
            var tableHeaders = [];
            var columnWidths = [];
            var summaryValues = [];
            var summaryCellStyles = [];
            var tableTitle = 'تقرير التحصيلات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }}';

            @if ($isDetails)
                tableTitle = 'تفاصيل التحصيلات حسب ' + CR_EXPORT_META.groupLabel;
                tableHeaders = ['التاريخ', 'رقم الفاتورة', 'الموظف', 'العميل', 'الفئة الحالية', 'التصنيف', 'الحالة', 'نوع الحساب', 'الخزينة/البنك', 'الفرع', 'الملاحظات', 'المبلغ', 'المتبقي', 'إجمالي الفاتورة', 'نوع الدفعة'];
                columnWidths = [12, 14, 16, 22, 14, 14, 12, 12, 16, 16, 22, 14, 14, 14, 16];
                var lastCol = tableHeaders.length - 1;
                var emptyRow = function () { return tableHeaders.map(function () { return ''; }); };
                var nullStyles = function () { return tableHeaders.map(function () { return null; }); };

                CR_EXPORT_ROWS.forEach(function (r, i) {
                    var prev = CR_EXPORT_ROWS[i - 1];
                    var next = CR_EXPORT_ROWS[i + 1];
                    if (!prev || prev.group_key !== r.group_key) {
                        var head = emptyRow();
                        head[0] = r.group;
                        var headStyles = nullStyles();
                        headStyles[0] = { bold: true };
                        tableData.push({ values: head, mergeFrom: 0, mergeTo: lastCol, background: 'grayLight', cellStyles: headStyles });
                    }
                    var values = [r.date, r.invoice, r.employee, r.client, r.category, r.real_category, r.status, r.account_type, r.account, r.branch, r.note,
                        formatNumber(r.amount), r.remaining === null ? '-' : formatNumber(r.remaining), r.total === null ? '-' : formatNumber(r.total), r.kind];
                    var styles = nullStyles();
                    styles[11] = { color: r.amount < 0 ? 'danger' : 'success' };
                    tableData.push({ values: values, cellStyles: styles });
                    if (!next || next.group_key !== r.group_key) {
                        var sub = emptyRow();
                        sub[0] = 'إجمالي ' + r.group;
                        sub[11] = formatNumber(r.group_amount);
                        var subStyles = nullStyles();
                        subStyles[0] = { bold: true };
                        subStyles[11] = { bold: true, color: 'success' };
                        tableData.push({ values: sub, mergeFrom: 0, mergeTo: 10, cellStyles: subStyles });
                    }
                });

                summaryValues = emptyRow();
                summaryValues[0] = 'الإجمالي العام';
                summaryValues[11] = formatNumber(totals.amount) + ' ر.س';
                summaryValues[12] = formatNumber(CR_EXPORT_META.remaining);
                summaryCellStyles = nullStyles();
                summaryCellStyles[0] = { bold: true };
                summaryCellStyles[11] = { color: 'success', bold: true };
                summaryCellStyles[12] = { bold: true };
            @else
            @if(isset($isMatrix) && $isMatrix)
                tableHeaders = [
                    '{{ $groupByOptions[$groupBy] ?? "التصنيف" }}',
                    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
                    'الإجمالي السنوي'
                ];
                columnWidths = [40, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 20];

                /* صفوفُ المصفوفة كلُّها من `?export=rows` (CR_EXPORT_ROWS) لا من الصفحة:
                   كانت تُكتب هنا لكلّ عميلٍ كتلةُ JavaScript، فبلغت صفحةُ السنة أربعة
                   ميغابايت من التصدير وحدَه. الإجمالياتُ الشهرية تُحسب من الصفوف. */
                var mxTotals = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
                (CR_EXPORT_ROWS || []).forEach(function (r) {
                    var monthValues = [];
                    for (var m = 1; m <= 12; m++) {
                        var v = Number((r.months && r.months[m]) || 0);
                        mxTotals[m] += v;
                        monthValues.push(formatNumber(v));
                    }
                    var mxStyles = [{ alignment: { horizontal: 'right' } }];
                    for (var s = 1; s <= 12; s++) { mxStyles.push({ alignment: { horizontal: 'center' } }); }
                    mxStyles.push({ alignment: { horizontal: 'center' }, color: 'success', bold: true });
                    tableData.push({
                        values: [r.name].concat(monthValues, [formatNumber(Number(r.total || 0))]),
                        cellStyles: mxStyles
                    });
                });

                summaryValues = ['الإجمالي العام'];
                for (var t = 1; t <= 12; t++) { summaryValues.push(formatNumber(mxTotals[t])); }
                summaryValues.push(formatNumber(totals.amount) + ' ر.س');

                summaryCellStyles = [{ bold: true }];
                @for($m=1; $m<=12; $m++)
                    summaryCellStyles.push({ bold: true });
                @endfor
                summaryCellStyles.push({ color: 'success', bold: true });

            @elseif($isSummary)
                tableHeaders = ['{{ $groupByOptions[$groupBy] ?? "التصنيف" }}', 'عدد التحصيلات', 'إجمالي المبلغ'];
                columnWidths = [40, 20, 25];

                @if(!empty($reportData))
                @foreach($reportData as $groupKey => $data)
                tableData.push({
                    values: [
                        {!! json_encode($data['name'] ?? $groupKey) !!},
                        '{{ $data["count"] ?? 0 }}',
                        formatNumber({{ (float)($data['amount'] ?? 0) }})
                    ],
                    cellStyles: [
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                    ]
                });
                @endforeach
                @endif

                summaryValues = ['الإجمالي العام', totals.count, formatNumber(totals.amount) + ' ر.س'];
                summaryCellStyles = [
                    { bold: true },
                    { bold: true },
                    { color: 'success', bold: true }
                ];
            @elseif(isset($viewType) && $viewType === 'target_changes')
                {{--
                    وضع «تغير المستويات» كان يصدَّر عبر فرع الملخّص (name/count/amount)
                    بينما بياناته مسطّحة بحقول مختلفة تماماً (client_code, client_name, …) —
                    فيخرج الملفّ بخلايا فارغة/undefined لا الجدول المعروض فعلاً.
                --}}
                tableHeaders = ['كود العميل', 'اسم العميل', 'الفرع', 'الفئة السابقة', 'حالة التغيير', 'الفئة الحالية', 'إجمالي تحصيلات الفترة'];
                columnWidths = [14, 24, 16, 16, 16, 16, 20];

                @if(!empty($reportData))
                @foreach($reportData as $row)
                tableData.push({
                    values: [
                        {!! json_encode($row['client_code'] ?? '') !!},
                        {!! json_encode($row['client_name'] ?? '') !!},
                        {!! json_encode($row['branch_name'] ?? '') !!},
                        {!! json_encode($row['client_previous_category'] ?? '') !!},
                        {!! json_encode($row['target_change'] === 'upgrade' ? 'ترقية / تقدم' : ($row['target_change'] === 'downgrade' ? 'تراجع مستوى' : '-')) !!},
                        {!! json_encode($row['client_category'] ?? '') !!},
                        formatNumber({{ (float)($row['total_collections'] ?? 0) }})
                    ],
                    cellStyles: [null, null, null, null, null, null, { color: 'success', bold: true }]
                });
                @endforeach
                @endif

                summaryValues = ['الإجمالي العام', '', '', '', '', '', formatNumber(totals.amount) + ' ر.س'];
                summaryCellStyles = [{ bold: true }, null, null, null, null, null, { color: 'success', bold: true }];
            @endif
            @endif

            var config = {
                fileName: 'تقرير_التحصيلات',
                mainTitle: tableTitle,
                subTitle: 'نظام المبيعات المتكامل',
                reportInfo: {
                    'الفترة': CR_EXPORT_META.period,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },
                statisticsCards: [
                    { title: 'صافي التحصيلات', value: formatNumber(CR_EXPORT_META.net), unit: ' ' + ((window.AppCurrency && window.AppCurrency.symbol) || 'ر.س'), borderColor: 'success' },
                    { title: 'المديونية القائمة', value: formatNumber(CR_EXPORT_META.remaining), unit: '', borderColor: 'danger' },
                    { title: 'عدد العملاء', value: CR_EXPORT_META.clients, unit: 'عميل', borderColor: 'primary' },
                    { title: 'عدد الفواتير', value: CR_EXPORT_META.invoices, unit: 'فاتورة', borderColor: 'primary' }
                ],
                tableTitle: tableTitle,
                tableHeaders: tableHeaders,
                columnWidths: columnWidths,
                tableData: tableData,
                summaryRows: [{ values: summaryValues, cellStyles: summaryCellStyles }],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            if (typeof exportToExcelProfessional === 'function') {
                exportToExcelProfessional(config);
            } else {
                alert('دالة التصدير غير متوفرة');
            }
        }

        // =====================================
        // الطباعة — التفاصيل كاملةً لا الصفحة الظاهرة وحدها
        // =====================================
        function printCollectionsReport() {
            // الطباعةُ تطبع الصفوفَ كلَّها لا صفحةَ الجدول وحدَها — تُجلب أوّلاً
            if (CR_DETAILS && !CR_EXPORT_ROWS) {
                crLoadExportRows()
                    .then(function () { printCollectionsReport(); })
                    .catch(function () { alert('تعذّر تحميل صفوف الطباعة — أعد المحاولة'); });
                return;
            }
            var theadHTML = '';
            var bodyHTML = '';
            var footHTML = '';

            @if ($isDetails)
                var heads = ['#', 'التاريخ', 'رقم الفاتورة', 'الموظف', 'العميل', 'الفئة', 'التصنيف', 'الحالة', 'نوع الحساب', 'الخزينة/البنك', 'الفرع', 'الملاحظات', 'المبلغ', 'المتبقي', 'إجمالي الفاتورة', 'نوع الدفعة'];
                theadHTML = heads.map(function (h) { return '<th>' + h + '</th>'; }).join('');
                CR_EXPORT_ROWS.forEach(function (r, i) {
                    var prev = CR_EXPORT_ROWS[i - 1];
                    var next = CR_EXPORT_ROWS[i + 1];
                    if (!prev || prev.group_key !== r.group_key) {
                        bodyHTML += '<tr style="background:#eef1f6"><td colspan="' + heads.length + '" style="text-align:right"><strong>' + crEsc(CR_EXPORT_META.groupLabel) + ': ' + crEsc(r.group) + '</strong></td></tr>';
                    }
                    var cells = [i + 1, r.date, crEsc(r.invoice), crEsc(r.employee), crEsc(r.client), crEsc(r.category), crEsc(r.real_category), crEsc(r.status),
                        crEsc(r.account_type), crEsc(r.account), crEsc(r.branch), crEsc(r.note), formatNumber(r.amount),
                        r.remaining === null ? '-' : formatNumber(r.remaining), r.total === null ? '-' : formatNumber(r.total), crEsc(r.kind)];
                    bodyHTML += '<tr>' + cells.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
                    if (!next || next.group_key !== r.group_key) {
                        bodyHTML += '<tr style="font-weight:bold"><td colspan="12" style="text-align:right">إجمالي ' + crEsc(r.group) + '</td><td>' + formatNumber(r.group_amount) + '</td><td colspan="3"></td></tr>';
                    }
                });
                footHTML = '<tr><td colspan="12">الإجمالي العام</td><td>' + formatNumber({{ (float) ($totals['amount'] ?? 0) }}) + '</td><td>' + formatNumber(CR_EXPORT_META.remaining) + '</td><td colspan="2"></td></tr>';
            @else
                theadHTML = $('#reportTable thead tr').first().html() || '';
                $('#reportTable tbody tr').each(function () { bodyHTML += '<tr>' + $(this).html() + '</tr>'; });
                footHTML = $('#reportTable tfoot').html() || '';
            @endif

            var printWindow = window.open('', '', 'height=800,width=1400');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <meta charset="UTF-8">
                    <title>تقرير التحصيلات</title>
                    <style>
                        body { font-family: 'Tahoma', sans-serif; direction: rtl; padding: 20px; }
                        h1 { text-align: center; color: #333; margin: 0 0 12px; }
                        .info-bar { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; padding: 10px; background: #f5f5f5; font-size: 13px; }
                        table { width: 100%; border-collapse: collapse; font-size: 12px; }
                        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: center; }
                        th { background: {{ \App\Support\ThemeSettings::palette()['--app-primary'] ?? '#1e63e9' }}; color: #fff; }
                        tfoot td { background: #f8f9fa; font-weight: bold; }
                        .no-print, button, .dropdown { display: none !important; }
                    </style>
                </head>
                <body>
                    <h1>تقرير التحصيلات</h1>
                    <div class="info-bar">
                        <span>الفترة: ${crEsc(CR_EXPORT_META.period)}</span>
                        <span>إجمالي التحصيلات: ${formatNumber(CR_EXPORT_META.net)} ريال</span>
                        <span>المتبقي: ${formatNumber(CR_EXPORT_META.remaining)} ريال</span>
                        <span>${CR_EXPORT_META.clients} عميل | ${CR_EXPORT_META.invoices} فاتورة</span>
                    </div>
                    <table>
                        <thead><tr>${theadHTML}</tr></thead>
                        <tbody>${bodyHTML}</tbody>
                        <tfoot>${footHTML}</tfoot>
                    </table>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function () { printWindow.focus(); printWindow.print(); }, 500);
        }
    </script>

    @include('sales::sals_report.salesRport.partials.crm_actions_modals')

@endsection
