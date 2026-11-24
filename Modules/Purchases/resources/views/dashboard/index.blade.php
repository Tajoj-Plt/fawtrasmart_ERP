{{--
    لوحة مؤشّرات المشتريات — بنمط تصميم لوحة العملاء
    (`Modules/Client/resources/views/dashboard/client/index.blade.php`):
    شريطُ فلترةٍ واحد، صفّا مؤشّرات، بطاقاتٌ برأسٍ فيه أيقونةٌ وزرُّ
    «من أين تأتي هذه البيانات؟»، وكلُّ لونٍ من متغيّرات `--app-*`.

    كل رقمٍ هنا يأتي جاهزاً من `PurchaseDashboardMetrics` — والشاشة لا
    تحسب شيئاً. وهي نظيرةُ لوحة المبيعات بطاقةً ببطاقة، فالأرقامُ تُقرأ
    بالطريقة نفسها في الشاشتين.
--}}
@extends('purchases::master')

@section('disable-dashboard-ecommerce', true)

@section('title')
    مؤشرات أداء المشتريات
@endsection

@php
    /*
     * لا رقم يُنسَّق هنا يدوياً ولا لونَ مكتوب:
     *   المبلغ  ← CurrencySettings (الرمز والمنازل والفواصل من الإعدادات)
     *   التاريخ ← LanguageSettings::dateFormat()
     *   اللون   ← متغيّرات --app-* التي يبثّها ThemeSettings
     */
    $money = fn ($value) => \App\Support\CurrencySettings::number($value);
    $currency = \App\Support\CurrencySettings::symbol();
    $dateFormat = \App\Support\LanguageSettings::dateFormat() ?: 'Y-m-d';
    $showDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->translatedFormat($dateFormat) : '—';

    $deltaBadge = function (?array $delta) {
        if (! $delta || $delta['percent'] === null) {
            return ['class' => 'pd-delta-flat', 'icon' => 'minus', 'text' => 'لا مقارنة'];
        }

        return [
            'class' => 'pd-delta-'.$delta['direction'],
            'icon' => $delta['direction'] === 'up' ? 'trending-up' : ($delta['direction'] === 'down' ? 'trending-down' : 'minus'),
            'text' => ($delta['percent'] > 0 ? '+' : '').number_format($delta['percent'], 1).'%',
        ];
    };

    // لونٌ لكل نوع حركة — مفاتيحه من SupplierLedger::classify()
    $kindColors = [
        'purchase' => 'var(--pd-primary)',
        'return' => 'var(--pd-danger)',
        'debit_note' => 'var(--pd-info)',
        'payment' => 'var(--pd-success)',
        'other' => 'var(--pd-muted)',
    ];

    $periodTabs = $periodTabs + ['custom' => 'مخصص'];
    $isCustom = $period->key === 'custom';

    // جسر المستحق: ما بين «صافي المشتريات − المدفوع» والرصيد المعروض
    $closing = round($outstanding['outstanding'] - $outstanding['credit'], 2);
    $adjust = round((float) ($totals['adjustments'] ?? 0), 2);
    $opening = round($closing - ($totals['net_purchases'] - $totals['payments'] + $adjust), 2);
    $bridge = 'الرصيد حتى '.$showDate($period->to);
    $bridgeParts = [];
    if (abs($adjust) > 0.009) {
        $bridgeParts[] = ($adjust > 0 ? '+ ' : '− ').'تسويات '.$money(abs($adjust));
    }
    if (abs($opening) > 0.009) {
        $bridgeParts[] = ($opening > 0 ? '+ ' : '− ').'رصيد سابق '.$money(abs($opening));
    }
    if ($bridgeParts) {
        $bridge .= ' · = الصافي − المدفوع '.implode(' ', $bridgeParts);
    }
    if ($outstanding['credit'] > 0.009) {
        $bridge .= ' · وعند موردين آخرين لنا '.$money($outstanding['credit']);
    }
    $kpiRow1 = [
        [
            'label' => 'إجمالي المشتريات',
            'segment' => 'docs.purchase',
            'value' => $money($totals['purchases']),
            'unit' => $currency,
            'icon' => 'shopping-cart',
            'color' => 'var(--pd-primary)',
            'foot' => $totals['purchases_count'].' فاتورة',
            'delta' => $deltaBadge($deltas['purchases'] ?? null),
        ],
        [
            'label' => 'صافي المشتريات',
            'segment' => 'suppliers.net',
            'value' => $money($totals['net_purchases']),
            'unit' => $currency,
            'icon' => 'check-circle',
            'color' => 'var(--pd-success)',
            'foot' => 'بعد المرتجعات والإشعارات',
            'delta' => $deltaBadge($deltas['net_purchases'] ?? null),
        ],
        [
            'label' => 'متوسط الفاتورة',
            'segment' => 'docs.purchase',
            'value' => $money($totals['average_invoice']),
            'unit' => $currency,
            'icon' => 'bar-chart-2',
            'color' => 'var(--pd-primary)',
            'foot' => 'من صافي المشتريات',
            'delta' => $deltaBadge($deltas['purchases_count'] ?? null),
        ],
        [
            // المرتجعُ والإشعارُ المدين رقمٌ واحد — والحاشيةُ تفصلهما
            'label' => 'المرتجعات والإشعارات',
            'segment' => 'docs.returns',
            'value' => $money($totals['returns_total']),
            'unit' => $currency,
            'icon' => 'corner-up-left',
            'color' => 'var(--pd-danger)',
            'foot' => $totals['returns_count'].' مرتجعاً '.$money($totals['returns'])
                .' · '.$totals['debit_notes_count'].' إشعاراً '.$money($totals['debit_notes']),
            'delta' => $deltaBadge($deltas['returns_total'] ?? null),
        ],
        [
            'label' => 'المدفوع للموردين',
            'segment' => 'docs.payment',
            'value' => $money($totals['payments']),
            'unit' => $currency,
            'icon' => 'credit-card',
            'color' => 'var(--pd-info)',
            'foot' => $totals['payments_count'].' سند',
            'delta' => $deltaBadge($deltas['payments'] ?? null),
        ],
        [
            'label' => 'المستحق للموردين',
            'segment' => 'suppliers.creditors',
            'value' => $money($outstanding['outstanding']),
            'unit' => $currency,
            'icon' => 'alert-circle',
            'color' => 'var(--pd-warning)',
            /* الرصيدُ يُقرأ من دفتر الموردين كلِّه لا من مستندين اثنين:
                 المستحق = افتتاحي + صافي المشتريات − المدفوع + تسويات
               والتسويات قيودٌ على حساب المورّد ليست فاتورةً ولا سنداً (مصروفٌ
               قُيّد عليه، سندُ قبضٍ منه…). وكانت البطاقةُ تعرض الناتجَ بلا
               أطرافه، فلا يطابقه «صافي المشتريات − المدفوع». فيُعرض الفرقُ
               باسمه ومقداره ليُقرأ الطرحُ من البطاقة نفسها. */
            'foot' => $bridge,
            'delta' => null,
        ],
    ];

    $kpiRow2 = [
        [
            'label' => 'عدد الفواتير',
            'segment' => 'docs.purchase',
            'value' => number_format($totals['purchases_count']),
            'unit' => null,
            'icon' => 'file-text',
            'color' => 'var(--pd-primary)',
            'foot' => 'فواتير شراء مرحَّلة',
        ],
        [
            'label' => 'نسبة الارتجاع',
            'segment' => 'docs.returns',
            'value' => number_format($totals['return_rate'], 2).'%',
            'unit' => null,
            'icon' => 'rotate-ccw',
            'color' => 'var(--pd-danger)',
            'foot' => 'من إجمالي المشتريات',
        ],
        [
            'label' => 'نسبة السداد',
            'segment' => 'docs.payment',
            'value' => number_format($totals['payment_rate'], 1).'%',
            'unit' => null,
            'icon' => 'percent',
            'color' => 'var(--pd-info)',
            'foot' => 'المدفوع من صافي المشتريات',
        ],
        [
            'label' => 'موردون دائنون',
            'segment' => 'suppliers.creditors',
            'value' => number_format($outstanding['suppliers']),
            'unit' => null,
            'icon' => 'users',
            'color' => 'var(--pd-warning)',
            'foot' => 'لهم رصيدٌ مستحق',
        ],
        [
            'label' => 'رصيدٌ لنا عند الموردين',
            'segment' => 'suppliers.credit',
            'value' => $money($outstanding['credit']),
            'unit' => $currency,
            'icon' => 'arrow-down-left',
            'color' => 'var(--pd-success)',
            'foot' => 'دُفع زيادةً أو مقدَّماً',
        ],
        [
            'label' => 'أوامر شراء معلّقة',
            'segment' => 'orders.open',
            'value' => number_format($openOrders['count']),
            'unit' => null,
            'icon' => 'clock',
            'color' => 'var(--pd-muted)',
            'foot' => 'بقيمة '.$money($openOrders['amount']).' '.$currency.' — خارج المجاميع',
        ],
    ];
@endphp

@section('css')
    <style>
        #purchases-dashboard {
            --pd-surface: var(--app-surface, #fff);
            --pd-border: var(--app-border, #e6e9f0);
            --pd-text: var(--app-text, #4b4b4b);
            --pd-muted: var(--app-muted, #7b8194);
            --pd-radius: var(--app-radius, 8px);
            --pd-primary: var(--app-primary, #7367f0);
            --pd-success: var(--app-success, #28c76f);
            --pd-danger: var(--app-danger, #ea5455);
            --pd-warning: var(--app-warning, #ff9f43);
            --pd-info: var(--app-info, #00cfe8);
            font-family: var(--app-font-family, 'Cairo', sans-serif);
        }

        #purchases-dashboard .pd-toolbar {
            background: var(--pd-surface);
            border: 1px solid var(--pd-border);
            border-radius: calc(var(--pd-radius) + 4px);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        #purchases-dashboard .pd-title {
            font-weight: 700; color: var(--pd-text); margin: 0 0 .2rem; font-size: 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        #purchases-dashboard .pd-subtitle { color: var(--pd-muted); font-size: .8rem; margin: 0 0 .75rem; }

        #purchases-dashboard .pd-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }
        #purchases-dashboard .pd-tab {
            border: 1px solid var(--pd-border); background: transparent; color: var(--pd-muted);
            border-radius: var(--pd-radius); padding: .42rem .95rem; font-size: .85rem; font-weight: 600; cursor: pointer;
        }
        #purchases-dashboard .pd-tab.active { background: var(--pd-primary); border-color: var(--pd-primary); color: #fff; }

        #purchases-dashboard .pd-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin: -.4rem 0 .85rem; font-size: .78rem; }
        #purchases-dashboard .pd-crumbs a { color: var(--pd-primary); text-decoration: none; }
        #purchases-dashboard .pd-crumbs a:hover { text-decoration: underline; }
        #purchases-dashboard .pd-crumbs strong { color: var(--pd-text); }
        #purchases-dashboard .pd-crumb-sep { color: var(--pd-muted); opacity: .6; }

        #purchases-dashboard .pd-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        #purchases-dashboard .pd-field { display: flex; flex-direction: column; gap: .25rem; }
        #purchases-dashboard .pd-field > label { font-size: .74rem; color: var(--pd-muted); margin: 0; font-weight: 600; }
        #purchases-dashboard .pd-field .form-control,
        #purchases-dashboard .pd-field .custom-select,
        #purchases-dashboard .pd-field select {
            height: var(--app-input-height, 38px); font-size: .84rem;
            border-radius: var(--pd-radius); border: 1px solid var(--pd-border);
            background: var(--pd-surface); color: var(--pd-text);
            padding: .25rem .6rem; min-width: 165px;
        }

        #purchases-dashboard .pd-switch {
            display: inline-flex; align-items: center; gap: .4rem;
            height: var(--app-input-height, 38px); padding: 0 .75rem;
            border: 1px solid var(--pd-border); border-radius: var(--pd-radius);
            background: var(--pd-surface); font-size: .82rem; font-weight: 600;
            color: var(--pd-muted); cursor: pointer; margin: 0; white-space: nowrap;
        }
        #purchases-dashboard .pd-switch input { accent-color: var(--pd-primary); width: 15px; height: 15px; cursor: pointer; }
        #purchases-dashboard .pd-scope-count { font-style: normal; font-size: .72rem; font-weight: 700; color: var(--pd-primary); direction: ltr; }

        #purchases-dashboard .pd-apply {
            height: var(--app-input-height, 38px); border-radius: var(--app-button-radius, 6px); border: none;
            background: var(--pd-primary); color: #fff; font-size: .84rem; font-weight: 600; padding: 0 1.1rem; cursor: pointer;
        }
        #purchases-dashboard .pd-reset {
            height: var(--app-input-height, 38px); display: inline-flex; align-items: center; gap: .3rem;
            border-radius: var(--app-button-radius, 6px); border: 1px solid var(--pd-border);
            background: var(--pd-surface); color: var(--pd-muted); font-size: .84rem; padding: 0 .9rem; cursor: pointer; text-decoration: none;
        }

        #purchases-dashboard .pd-notice {
            border-radius: var(--pd-radius); padding: .6rem .9rem; font-size: .82rem; margin-bottom: 1rem;
            border: 1px solid var(--pd-border); color: var(--pd-muted); background: var(--pd-surface);
        }
        #purchases-dashboard .pd-notice.warning {
            border-color: color-mix(in srgb, var(--pd-warning) 45%, transparent);
            background: color-mix(in srgb, var(--pd-warning) 10%, transparent);
            color: var(--pd-warning);
        }
        #purchases-dashboard .pd-notice.danger {
            border-color: color-mix(in srgb, var(--pd-danger) 45%, transparent);
            background: color-mix(in srgb, var(--pd-danger) 10%, transparent);
            color: var(--pd-danger);
        }
        #purchases-dashboard .pd-notice.success {
            border-color: color-mix(in srgb, var(--pd-success) 45%, transparent);
            background: color-mix(in srgb, var(--pd-success) 10%, transparent);
            color: var(--pd-success);
        }

        /* ---------- المؤشرات ---------- */
        #purchases-dashboard .pd-kpi {
            background: var(--pd-surface); border: 1px solid var(--pd-border);
            border-radius: calc(var(--pd-radius) + 4px); padding: var(--app-card-padding, 1rem);
            height: 100%; transition: transform .18s ease;
        }
        #purchases-dashboard .pd-kpi:hover { transform: translateY(-3px); }
        #purchases-dashboard .pd-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
        #purchases-dashboard .pd-kpi-icon { width: 40px; height: 40px; border-radius: var(--pd-radius); display: flex; align-items: center; justify-content: center; }
        #purchases-dashboard .pd-kpi-label { color: var(--pd-muted); font-size: .78rem; font-weight: 600; margin: 0 0 .25rem; }
        #purchases-dashboard .pd-kpi-value { font-size: 1.3rem; font-weight: 700; color: var(--pd-text); margin: 0; direction: ltr; text-align: start; }
        #purchases-dashboard .pd-kpi-value small { font-size: .72rem; color: var(--pd-muted); font-weight: 600; }
        #purchases-dashboard .pd-kpi-foot { margin-top: .4rem; font-size: .74rem; color: var(--pd-muted); }

        #purchases-dashboard .pd-delta {
            display: inline-flex; align-items: center; gap: .2rem; padding: .12rem .45rem;
            border-radius: 6px; font-weight: 700; font-size: .73rem; direction: ltr;
        }
        #purchases-dashboard .pd-delta-up { background: color-mix(in srgb, var(--pd-success) 14%, transparent); color: var(--pd-success); }
        #purchases-dashboard .pd-delta-down { background: color-mix(in srgb, var(--pd-danger) 14%, transparent); color: var(--pd-danger); }
        #purchases-dashboard .pd-delta-flat { background: color-mix(in srgb, var(--pd-muted) 14%, transparent); color: var(--pd-muted); }

        /* ---------- البطاقات ---------- */
        #purchases-dashboard .pd-card {
            background: var(--pd-surface); border: 1px solid var(--pd-border);
            border-radius: calc(var(--pd-radius) + 4px); margin-bottom: 1.25rem;
            height: calc(100% - 1.25rem); display: flex; flex-direction: column;
        }
        #purchases-dashboard .pd-card-head { padding: 1rem 1.15rem .4rem; display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        #purchases-dashboard .pd-card-head h4 { font-size: 1rem; font-weight: 700; color: var(--pd-text); margin: 0; display: flex; align-items: center; gap: .45rem; }
        #purchases-dashboard .pd-card-head h4 i { color: var(--pd-primary); }
        #purchases-dashboard .pd-card-head small { color: var(--pd-muted); font-size: .76rem; display: block; margin-top: .15rem; }
        #purchases-dashboard .pd-card-body { padding: .4rem 1.15rem 1.1rem; flex: 1; }

        /* أيقونة «من أين تأتي هذه البيانات؟» — تُظهر/تُخفي .pd-info-panel أسفلها */
        #purchases-dashboard .pd-info-toggle {
            background: none; border: none; padding: .2rem .35rem; line-height: 1;
            border-radius: 5px; color: var(--pd-muted); cursor: pointer; flex-shrink: 0;
        }
        #purchases-dashboard .pd-info-toggle:hover { color: var(--pd-primary); background: color-mix(in srgb, var(--pd-primary) 10%, transparent); }
        #purchases-dashboard .pd-info-panel {
            background: color-mix(in srgb, var(--pd-primary) 6%, transparent);
            border: 1px solid var(--pd-border); border-radius: var(--pd-radius);
            padding: .6rem .8rem; margin-bottom: .7rem; font-size: .78rem; line-height: 1.7; color: var(--pd-muted);
        }
        #purchases-dashboard .pd-info-panel code {
            background: color-mix(in srgb, var(--pd-text) 8%, transparent);
            padding: .05rem .3rem; border-radius: 4px; font-size: .74rem; direction: ltr; display: inline-block;
        }

        #purchases-dashboard .pd-table { width: 100%; font-size: .84rem; margin: 0; color: var(--pd-text); }
        #purchases-dashboard .pd-table thead th {
            background: color-mix(in srgb, var(--pd-primary) 5%, transparent);
            color: var(--pd-muted); font-weight: 700; font-size: .76rem; border: none; padding: .6rem .55rem; white-space: nowrap;
        }
        #purchases-dashboard .pd-table tbody td { padding: .55rem .55rem; border-top: 1px solid var(--pd-border); vertical-align: middle; }
        #purchases-dashboard .pd-num { direction: ltr; text-align: start; font-variant-numeric: tabular-nums; white-space: nowrap; }

        #purchases-dashboard .pd-empty { text-align: center; color: var(--pd-muted); padding: 2rem 1rem; font-size: .86rem; }
        #purchases-dashboard .pd-empty i { display: block; font-size: 1.8rem; margin-bottom: .5rem; opacity: .45; }

        #purchases-dashboard .pd-badge { display: inline-block; padding: .16rem .5rem; border-radius: 6px; font-size: .72rem; font-weight: 700; }

        #purchases-dashboard .pd-drill { color: var(--pd-primary); text-decoration: none; font-weight: 600; }
        #purchases-dashboard .pd-drill:hover { text-decoration: underline; }

        #purchases-dashboard .pd-bar { height: 6px; border-radius: 4px; background: color-mix(in srgb, var(--pd-muted) 15%, transparent); overflow: hidden; min-width: 60px; margin-top: .25rem; }
        #purchases-dashboard .pd-bar span { display: block; height: 100%; border-radius: 4px; }

        #purchases-dashboard .pd-recon {
            display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; padding: .85rem 1.15rem;
            background: color-mix(in srgb, var(--pd-primary) 4%, transparent);
            border-top: 1px solid var(--pd-border);
            border-radius: 0 0 calc(var(--pd-radius) + 4px) calc(var(--pd-radius) + 4px);
            font-size: .82rem; color: var(--pd-muted);
        }
        #purchases-dashboard .pd-recon b { color: var(--pd-text); direction: ltr; display: inline-block; }

        /* حاوية الرسم LTR والصفحة RTL: ApexCharts يحسب مواضع نصوص المحاور على
           افتراض LTR، و`dir="rtl"` الموروث يقلب مرساة النص فتُرسم الأسماء فوق
           الأعمدة. والأسماء تبقى يميناً بـ`opposite`، والعربية تُعرض سليمةً. */
        #purchases-dashboard [id^="pd"][id$="Chart"] { direction: ltr; }

        /* ---------- الشجرة: الآباء والأبناء ---------- */
        #purchases-dashboard .pd-tree-actions { display: flex; align-items: center; gap: .35rem; flex-shrink: 0; }
        #purchases-dashboard .pd-tree-all {
            border: 1px solid var(--pd-border); background: transparent; color: var(--pd-muted);
            border-radius: var(--pd-radius); padding: .2rem .6rem; font-size: .74rem; font-weight: 600; cursor: pointer;
        }
        #purchases-dashboard .pd-tree-all:hover { border-color: var(--pd-primary); color: var(--pd-primary); }
        #purchases-dashboard .pd-tree-root { font-weight: 600; color: var(--pd-muted); font-size: .85rem; }
        #purchases-dashboard .pd-tree-name { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
        #purchases-dashboard .pd-tree-caret {
            background: none; border: none; padding: 0; width: 20px; height: 20px; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--pd-muted); cursor: pointer; border-radius: 4px;
        }
        #purchases-dashboard .pd-tree-caret:hover { color: var(--pd-primary); background: color-mix(in srgb, var(--pd-primary) 10%, transparent); }
        #purchases-dashboard .pd-tree-caret i { transition: transform .15s ease; }
        #purchases-dashboard .pd-tree-caret[aria-expanded="true"] i { transform: rotate(-90deg); }
        html[dir="ltr"] #purchases-dashboard .pd-tree-caret[aria-expanded="false"] i { transform: rotate(180deg); }
        #purchases-dashboard .pd-tree-leaf { width: 20px; flex-shrink: 0; display: inline-block; }
        #purchases-dashboard .pd-tree tr.pd-tree-d0 td { background: color-mix(in srgb, var(--pd-primary) 4%, transparent); font-weight: 700; }
        #purchases-dashboard .pd-tree tr.pd-tree-d2 td,
        #purchases-dashboard .pd-tree tr.pd-tree-d3 td { font-size: .8rem; }
        #purchases-dashboard .pd-tree-own { display: block; color: var(--pd-muted); font-size: .72rem; font-weight: 400; padding-inline-start: 1.6rem; }
        #purchases-dashboard .pd-node-type {
            display: inline-block; padding: 0 .35rem; border-radius: .25rem; font-size: .68rem; line-height: 1.7; font-weight: 600;
            background: color-mix(in srgb, var(--pd-muted) 12%, transparent); color: var(--pd-muted);
        }
        #purchases-dashboard .pd-tree-chart-title { font-size: .8rem; color: var(--pd-muted); font-weight: 700; margin: .2rem 0 .4rem; }

        /* ---------- «ماذا وراء هذا الرقم؟» — نسخةُ لوحة المبيعات ---------- */
        #purchases-dashboard [data-segment] { cursor: pointer; }
        #purchases-dashboard [data-segment]:focus-visible { outline: 2px solid var(--pd-primary); outline-offset: 2px; }
        #purchases-dashboard .pd-kpi[data-segment]:hover { border-color: var(--pd-primary); }
        #purchases-dashboard .pd-table tbody tr[data-segment]:hover td,
        #purchases-dashboard .pd-table tbody td[data-segment]:hover { background: color-mix(in srgb, var(--pd-primary) 6%, transparent); }
        #purchases-dashboard .pd-recon [data-segment]:hover b,
        #purchases-dashboard .pd-tree-own[data-segment]:hover { color: var(--pd-primary); text-decoration: underline; }

        #purchases-dashboard .pd-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 1990; }
        #purchases-dashboard .pd-drawer {
            position: fixed; inset-block: 0; inset-inline-end: 0; width: min(920px, 100%); z-index: 1991;
            background: var(--pd-surface); box-shadow: -12px 0 40px rgba(15, 23, 42, .18);
            display: flex; flex-direction: column;
        }
        #purchases-dashboard .pd-drawer-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem;
            padding: 1rem 1.25rem .5rem; border-bottom: 1px solid var(--pd-border);
        }
        #purchases-dashboard .pd-drawer-head h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--pd-text); }
        #purchases-dashboard .pd-drawer-head p { margin: .25rem 0 0; font-size: .8rem; color: var(--pd-muted); line-height: 1.6; }
        #purchases-dashboard .pd-drawer-close {
            background: none; border: none; font-size: 1.6rem; line-height: 1; color: var(--pd-muted); cursor: pointer; padding: 0 .25rem;
        }
        #purchases-dashboard .pd-drawer-tools {
            display: flex; align-items: center; gap: .6rem; padding: .7rem 1.25rem; border-bottom: 1px solid var(--pd-border);
        }
        #purchases-dashboard .pd-drawer-tools input[type="search"] {
            flex: 1; border: 1px solid var(--pd-border); border-radius: var(--pd-radius);
            padding: .35rem .7rem; font-size: .84rem; background: transparent; color: var(--pd-text);
        }
        #purchases-dashboard .pd-drawer-count { font-size: .82rem; font-weight: 700; color: var(--pd-primary); white-space: nowrap; }
        #purchases-dashboard .pd-drawer-body { flex: 1; overflow: auto; padding: 0 1.25rem 1rem; }
        #purchases-dashboard .pd-drawer-body .pd-table thead th { position: sticky; top: 0; background: var(--pd-surface); box-shadow: inset 0 -1px 0 var(--pd-border); z-index: 1; }
        #purchases-dashboard .pd-drawer-body .pd-table tfoot td { font-weight: 700; border-top: 2px solid var(--pd-border); padding: .6rem .55rem; background: color-mix(in srgb, var(--pd-primary) 5%, transparent); }
        #purchases-dashboard .pd-drawer-body .pd-table a { color: var(--pd-primary); font-weight: 600; text-decoration: none; }
        #purchases-dashboard .pd-drawer-body .pd-table a:hover { text-decoration: underline; }
        #purchases-dashboard .pd-long { max-width: 34ch; white-space: normal; line-height: 1.5; }
        #purchases-dashboard .pd-drawer-more { display: block; margin: .8rem auto 0; }
        #purchases-dashboard .pd-drawer-btn {
            border: 1px solid var(--pd-border); background: transparent; color: var(--pd-muted);
            border-radius: var(--pd-radius); padding: .25rem .7rem; font-size: .78rem; font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        #purchases-dashboard .pd-drawer-btn:hover { border-color: var(--pd-primary); color: var(--pd-primary); }
    </style>
@endsection

@section('content')
    @include('components.hint-tips')
    <div class="content-body">
        <section id="purchases-dashboard">

            {{-- ==================== ابدأ من هنا: دليل قسم المشتريات ==================== --}}
            @include('purchases::dashboard._start-guide')

            {{-- ══════════ إرشادُ اللوحة ══════════ مطويّةٌ افتراضياً: من يفتح
                 لوحةَ المؤشّرات جاء لأرقامها. تُفتح بنقرة وتتذكّر ذلك بعدها. --}}
            @include('purchases::components.guide', [
                'key' => 'purchases-dashboard.index',
                'title' => 'من أين تأتي أرقام هذه اللوحة؟',
                'lead' => 'اللوحةُ لا تحسب رقماً بنفسها: كلُّ ما فيها مشتقٌّ من **دفتر أستاذ الموردين** — القيودُ المرحَّلة على حسابات الموردين — فما تعرضه هنا وما يعرضه أيُّ تقرير مشترياتٍ رقمٌ واحد.',
                'open' => false,
                'blocks' => [
                    'مصدرُ الأرقام' => [
                        'المشتريات = الطرفُ الدائن لقيود فواتير الشراء، والمرتجعات والإشعارات المدينة = طرفُها المدين، والمدفوع = صافي قيود السداد بما فيها **إلغاءُ الدفعة** والشيكُ الصادر ورفضُه.',
                        'فاتورةٌ لم تُرحَّل بعد لا تُحتسب هنا؛ وحالتا السداد والاستلام وحدهما تُقرآن من الفاتورة نفسها لأنهما خاصّيتها لا خاصّيةُ القيد.',
                    ],
                    'تابع لـ' => [
                        'الحركةُ تُنسب إلى **فرع فاتورتها**، وما لا فاتورة له (سدادٌ غير مربوط بفاتورة) إلى الفرع المختوم على قيده — محورٌ واحد في كل بطاقات اللوحة، فمجموعُ الفروع يساوي إجماليَّ المنشأة.',
                        'مستخدمُ فرعٍ لا يخرج من شجرته: «الكل» لا يظهر إلّا لمن كان على الفرع الرئيسي.',
                    ],
                    'الفترة والمقارنة' => [
                        'نسبةُ التغيّر تقارن الفترةَ المختارة بالفترة السابقة لها — الشهرُ بالشهر الماضي كاملاً، والمخصَّصةُ بمدّةٍ بطولها قبلها.',
                        'إن وقعت الفترةُ في فترةٍ محاسبيةٍ مقفلة ظهر تنبيهٌ بذلك أعلى المؤشّرات.',
                    ],
                ],
            ])

            {{-- ==================== الفلترة ==================== --}}
            <div class="pd-toolbar">
                <h4 class="pd-title"><i class="feather icon-shopping-bag"></i> مؤشرات أداء المشتريات</h4>
                <p class="pd-subtitle">
                    {{ $period->labelWithDates() }}
                    @if ($selectedBranchId)
                        — {{ $includeChildren && $descendantCount > 0 ? 'هذا و'.$descendantCount.' مما يتبعه' : 'هذا وحده' }}
                    @endif
                </p>

                @if ($branchLineage->isNotEmpty())
                    {{-- المسار من الجذر إلى العقدة الحالية — كل حلقةٍ رابطٌ يصعد إليها --}}
                    <nav class="pd-crumbs">
                        {{-- «الكل» للرئيسي وحده: مستخدمُ فرعٍ لا يخرج عمّا يتبع له --}}
                        @if ($isMainBranch)
                            <a href="{{ request()->fullUrlWithQuery(['branch_id' => null]) }}">— الكل —</a>
                        @endif
                        @foreach ($branchLineage as $node)
                            @if (! $loop->first || $isMainBranch)
                                <span class="pd-crumb-sep">/</span>
                            @endif
                            @if ($loop->last)
                                <strong>{{ $node->name }}</strong>
                            @else
                                <a href="{{ request()->fullUrlWithQuery(['branch_id' => $node->id]) }}">{{ $node->name }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <div class="pd-tabs">
                    @foreach ($periodTabs as $key => $label)
                        <button type="button" class="pd-tab {{ $period->key === $key ? 'active' : '' }}" data-period="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('purchases.dashboard') }}" class="pd-filters" id="pdFilterForm">
                    <input type="hidden" name="period" id="pdPeriod" value="{{ $period->key }}">

                    <div class="pd-field" data-pd-custom style="{{ $isCustom ? '' : 'display:none;' }}">
                        <label for="pdFrom">من</label>
                        <input type="date" class="form-control" name="date_from" id="pdFrom" value="{{ request('date_from') }}">
                    </div>
                    <div class="pd-field" data-pd-custom style="{{ $isCustom ? '' : 'display:none;' }}">
                        <label for="pdTo">إلى</label>
                        <input type="date" class="form-control" name="date_to" id="pdTo" value="{{ request('date_to') }}">
                    </div>

                    {{-- التنقّل في الشجرة نزولاً: القائمة تعرض أبناء العقدة الحالية
                         لا كل العقد، والمسارُ أعلاها يعود صعوداً --}}
                    @if ($hasChildBranches)
                        <div class="pd-field">
                            <label for="pdBranch">تابع لـ</label>
                            <select name="branch_id" id="pdBranch" class="custom-select">
                                <option value="">{{ $selectedBranchId ? '— رجوع لهذا كاملاً —' : '— الكل —' }}</option>
                                @foreach ($branchOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($selectedBranchId)
                        <div class="pd-field">
                            <label>النطاق</label>
                            <label class="pd-switch" title="ضمّ حركة كل ما يتبع له من فروعٍ ومناطق ومجموعات">
                                {{-- المربّع غير المؤشَّر لا يُرسَل، فالحقل المخفي يحمل «لا» صراحةً --}}
                                <input type="hidden" name="include_children" value="0">
                                <input type="checkbox" name="include_children" value="1" id="pdIncludeChildren" {{ $includeChildren ? 'checked' : '' }}>
                                <span>يشمل ما يتبعه</span>
                                @if ($includeChildren && $descendantCount > 0)
                                    <em class="pd-scope-count">+{{ $descendantCount }}</em>
                                @endif
                            </label>
                        </div>
                    @endif

                    <button type="submit" class="pd-apply"><i class="feather icon-filter"></i> تصفية</button>
                    <a href="{{ route('purchases.dashboard') }}" class="pd-reset"><i class="feather icon-refresh-cw"></i> تحديث</a>
                </form>
            </div>

            @if (session('error'))
                <div class="pd-notice danger">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="pd-notice success">{{ session('success') }}</div>
            @endif

            @if ($periodNotice)
                <div class="pd-notice {{ $periodNotice['level'] }}">{{ $periodNotice['message'] }}</div>
            @endif

            {{-- ==================== المؤشرات: صفّان من 6 ==================== --}}
            @foreach ([$kpiRow1, $kpiRow2] as $kpiRow)
                <div class="row">
                    @foreach ($kpiRow as $kpi)
                        <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-1">
                            <div class="pd-kpi" @if (! empty($kpi['segment'])) data-segment="{{ $kpi['segment'] }}" role="button" tabindex="0" title="اضغط لعرض التفاصيل" @endif>
                                <div class="pd-kpi-top">
                                    <div class="pd-kpi-icon" style="background: color-mix(in srgb, {{ $kpi['color'] }} 14%, transparent);">
                                        <i class="feather icon-{{ $kpi['icon'] }}" style="color: {{ $kpi['color'] }};"></i>
                                    </div>
                                    @if (! empty($kpi['delta']))
                                        <span class="pd-delta {{ $kpi['delta']['class'] }}">
                                            <i class="feather icon-{{ $kpi['delta']['icon'] }}"></i>
                                            {{ $kpi['delta']['text'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="pd-kpi-label">{{ $kpi['label'] }}</p>
                                <p class="pd-kpi-value">{{ $kpi['value'] }} @if ($kpi['unit'])<small>{{ $kpi['unit'] }}</small>@endif</p>
                                <div class="pd-kpi-foot">{{ $kpi['foot'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- ==================== الاتجاه / حالة السداد ==================== --}}
            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-trending-up"></i> اتجاه المشتريات والسداد</h4>
                                <small>مقارنة ما اشتُري بما سُدِّد خلال {{ $period->label }}</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                <b>المشتريات</b> الطرفُ الدائن لقيود فواتير الشراء المرحَّلة <code>PI</code> على حسابات الموردين،
                                و<b>المدفوع</b> صافي قيود السداد <code>PAY</code> <code>PV</code> مطروحاً منه إلغاءُ الدفعة <code>PCAN</code>،
                                ومعه الشيكُ الصادر <code>PCHQ</code> ورفضُه <code>PCHQR</code>. الوحدةُ يومٌ للفترة القصيرة وشهرٌ لما زاد على ٦٢ يوماً.
                            </div>
                            @if (count($trend['labels'] ?? []) > 0)
                                <div id="pdTrendChart" style="min-height: 330px;"></div>
                            @else
                                <div class="pd-empty"><i class="feather icon-bar-chart-2"></i> لا توجد حركة مشتريات في هذه الفترة</div>
                            @endif
                        </div>
                        <div class="pd-recon">
                            <div data-segment="docs.purchase" role="button" tabindex="0">إجمالي المشتريات <b>{{ $money($totals['purchases']) }}</b></div>
                            <div data-segment="docs.return" role="button" tabindex="0">− المرتجعات <b>{{ $money($totals['returns']) }}</b></div>
                            <div data-segment="docs.debit_note" role="button" tabindex="0">− الإشعارات المدينة <b>{{ $money($totals['debit_notes']) }}</b></div>
                            <div data-segment="suppliers.net" role="button" tabindex="0">= الصافي <b>{{ $money($totals['net_purchases']) }}</b></div>
                            <div data-segment="docs.payment" role="button" tabindex="0">− المدفوع <b>{{ $money($totals['payments']) }}</b></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-pie-chart"></i> حالة سداد الفواتير</h4>
                                <small>فواتير الفترة بمبالغها</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                من جدول <code>purchase_invoices</code> — فواتيرُ الشراء وحدها، فأمرُ الشراء لا يُدفع — بتاريخ الفاتورة وحالة السداد
                                المخزَّنة عليها. المبلغ <code>grand_total</code> شامل الضريبة، والفرعُ فرعُ الفاتورة — المحورُ نفسُه في بطاقة المشتريات.
                            </div>
                            @if (collect($paymentStatusMix)->sum('documents') > 0)
                                <div id="pdPaymentStatusChart"></div>
                                <table class="pd-table mt-1">
                                    <tbody>
                                        @foreach ($paymentStatusMix as $row)
                                            <tr @if ($row['documents'] > 0) data-segment="status.{{ $row['key'] }}" role="button" tabindex="0" @endif>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="pd-num">{{ $row['documents'] }}</td>
                                                <td class="pd-num">{{ $money($row['amount']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="pd-empty"><i class="feather icon-file-text"></i> لا توجد فواتير في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== الموردون ==================== --}}
            <div class="row">
                <div class="col-lg-7 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-truck"></i> أعلى الموردين</h4>
                                <small>بصافي المشتريات بعد المرتجعات والإشعارات</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                صافي كل مورّد = مشترياته − المرتجعات − الإشعارات المدينة خلال الفترة، من القيود على حسابه.
                                و<b>المستحق</b> رصيدُه من القيود حتى نهاية الفترة — دائنٌ موجب يعني «له علينا»، وسالبٌ «لنا عنده».
                            </div>
                            @if ($topSuppliers->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="pd-table">
                                        <thead>
                                            <tr>
                                                <th>المورّد</th>
                                                <th class="pd-num">الفواتير</th>
                                                <th class="pd-num">المشتريات</th>
                                                <th class="pd-num">المرتجعات</th>
                                                <th class="pd-num">الصافي</th>
                                                <th class="pd-num">المستحق</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $maxNet = max($topSuppliers->max('net_purchases'), 0.01);
                                            @endphp
                                            @foreach ($topSuppliers as $supplier)
                                                <tr data-segment="supplier.{{ $supplier['supplier_id'] }}" role="button" tabindex="0">
                                                    <td>
                                                        <div>{{ $supplier['name'] }}</div>
                                                        <div class="pd-bar"><span style="width: {{ max(2, ($supplier['net_purchases'] / $maxNet) * 100) }}%; background: var(--pd-primary);"></span></div>
                                                    </td>
                                                    <td class="pd-num">{{ $supplier['invoices'] }}</td>
                                                    <td class="pd-num">{{ $money($supplier['purchases']) }}</td>
                                                    <td class="pd-num" style="color: var(--pd-danger);">{{ $money($supplier['returns_total']) }}</td>
                                                    <td class="pd-num font-weight-bold">{{ $money($supplier['net_purchases']) }}</td>
                                                    <td class="pd-num" style="color: {{ $supplier['balance'] > 0 ? 'var(--pd-warning)' : 'var(--pd-success)' }};">{{ $money($supplier['balance']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="pd-empty"><i class="feather icon-users"></i> لا توجد مشتريات من موردين في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-alert-circle"></i> أعلى الموردين مستحقاً</h4>
                                <small>الرصيد المستحق حتى {{ $showDate($period->to) }}</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                رصيدُ كل مورّد من القيود المرحَّلة على حسابه حتى نهاية الفترة — لا <code>accounts.balance</code> التراكمي الذي لا يعرف فترةً ولا فرعاً.
                                يُعرض مَن رصيدُه دائن، أي مستحقٌّ له علينا.
                            </div>
                            @if ($topDebtors->isNotEmpty())
                                <div id="pdDebtorsChart" style="min-height: 300px;"></div>
                            @else
                                <div class="pd-empty"><i class="feather icon-check-circle"></i> لا مستحقّات للموردين</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== الشجرة: الآباء والأبناء ==================== --}}
            @if ($branchTree->isNotEmpty())
                <div class="row">
                    <div class="col-12">
                        <div class="pd-card">
                            <div class="pd-card-head">
                                <div>
                                    <h4><i class="feather icon-git-branch"></i> الإحصائيات حسب الشجرة
                                        @if ($selectedBranchId && $branchLineage->isNotEmpty())
                                            <span class="pd-tree-root">— {{ $branchLineage->last()->name }}</span>
                                        @endif
                                    </h4>
                                    <small>كل أبٍ بمجموعه شاملاً ما تحته، وأبناؤه تحته — اضغط السهم لفتح المستوى التالي</small>
                                </div>
                                <div class="pd-tree-actions">
                                    <button type="button" class="pd-tree-all" data-pd-tree-all="1">فتح الكل</button>
                                    <button type="button" class="pd-tree-all" data-pd-tree-all="0">طيّ الكل</button>
                                    <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                                </div>
                            </div>
                            <div class="pd-card-body">
                                <div class="pd-info-panel" hidden>كل صفٍّ عنصرٌ من الشجرة ورقمُه مجموعُه شاملاً كلَّ ما تحته مهما عمق، فلا يُجمع العمود — الجذورُ وحدها مجموعُها يساوي البطاقات. و«مباشرةً» حركةُ العنصر نفسِه دون أبنائه. والحركةُ تُنسب إلى فرع الفاتورة، وما لا فاتورة له إلى الفرع المختوم على قيده. والاسمُ رابطٌ يفتح اللوحة على ذلك العنصر.</div>
                                <div class="row">
                                    <div class="col-xl-{{ $branchBreakdown->isNotEmpty() ? '7' : '12' }} col-12">
                                        <div class="table-responsive">
                                            <table class="pd-table pd-tree" id="pdTree">
                                                <thead>
                                                    <tr>
                                                        <th>الاسم</th>
                                                        <th class="pd-num">الفواتير</th>
                                                        <th class="pd-num">المشتريات</th>
                                                        <th class="pd-num">المرتجعات</th>
                                                        <th class="pd-num">الصافي</th>
                                                        <th class="pd-num">المدفوع</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $maxTreeNet = max($branchTree->where('depth', 0)->max('net_purchases'), 0.01);
                                                    @endphp
                                                    @foreach ($branchTree as $row)
                                                        {{-- الجذور ظاهرةٌ مفتوحةً على أبنائها، وما دون ذلك مطويّ حتى يُفتح --}}
                                                        <tr data-tree-id="{{ $row['branch_id'] ?? 'none' }}" data-tree-parent="{{ $row['parent_id'] ?? '' }}"
                                                            class="pd-tree-d{{ min($row['depth'], 3) }}" @if ($row['depth'] > 1) hidden @endif>
                                                            <td style="padding-inline-start: calc(.55rem + {{ $row['depth'] }} * 1.25rem);">
                                                                <div class="pd-tree-name">
                                                                    @if ($row['has_children'])
                                                                        <button type="button" class="pd-tree-caret" data-pd-tree-toggle aria-expanded="{{ $row['depth'] === 0 ? 'true' : 'false' }}"><i class="feather icon-chevron-left"></i></button>
                                                                    @else
                                                                        <span class="pd-tree-leaf"></span>
                                                                    @endif
                                                                    @if ($row['branch_id'])
                                                                        <a class="pd-drill" href="{{ request()->fullUrlWithQuery(['branch_id' => $row['branch_id']]) }}">{{ $row['name'] }}</a>
                                                                    @else
                                                                        <span>{{ $row['name'] }}</span>
                                                                    @endif
                                                                    @if ($row['type'])
                                                                        <span class="pd-node-type">{{ $row['type'] }}</span>
                                                                    @endif
                                                                </div>
                                                                @if ($row['has_children'] && abs($row['own_net']) > 0.009)
                                                                    <small class="pd-tree-own" data-segment="suppliers.net" data-segment-params="{{ json_encode(['node' => $row['branch_id'] ?? 'none', 'node_scope' => 'direct']) }}" role="button" tabindex="0">مباشرةً: {{ $money($row['own_net']) }}</small>
                                                                @endif
                                                                @if ($row['depth'] === 0)
                                                                    <div class="pd-bar"><span style="width: {{ max(2, (max($row['net_purchases'], 0) / $maxTreeNet) * 100) }}%; background: var(--pd-primary);"></span></div>
                                                                @endif
                                                            </td>
                                                            @php
                                                                // العقدةُ وما تحتها — و«غير محدّد» نقيضُ العقد لا عقدة
                                                                $nodeParams = json_encode(['node' => $row['branch_id'] ?? 'none', 'node_scope' => 'tree']);
                                                            @endphp
                                                            <td class="pd-num" data-segment="docs.purchase" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $row['invoices'] }}</td>
                                                            <td class="pd-num" data-segment="docs.purchase" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $money($row['purchases']) }}</td>
                                                            <td class="pd-num" data-segment="docs.returns" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0" style="color: var(--pd-danger);">{{ $money($row['returns_total']) }}</td>
                                                            <td class="pd-num font-weight-bold" data-segment="suppliers.net" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $money($row['net_purchases']) }}</td>
                                                            <td class="pd-num" data-segment="docs.payment" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0" style="color: var(--pd-info);">{{ $money($row['payments']) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @if ($branchBreakdown->isNotEmpty())
                                        <div class="col-xl-5 col-12">
                                            <h6 class="pd-tree-chart-title">مقارنة المستوى الأول</h6>
                                            <div id="pdBranchChart" style="min-height: 320px;"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ==================== الموظفون ==================== --}}
            <div class="row">
                <div class="col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-user"></i> أداء الموظفين</h4>
                                <small>المشتريات والسداد لكل موظّف</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                الموظّف هو منشئُ القيد <code>created_by_employee</code> — من دفتر مشتريات الموظفين نفسه الذي يغذّي تقرير مشتريات الموظفين،
                                فالرقمان واحد. القيودُ بلا منشئ لا تظهر هنا.
                            </div>
                            @if ($purchasesByEmployee->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="pd-table">
                                        <thead>
                                            <tr>
                                                <th>الموظّف</th>
                                                <th class="pd-num">الفواتير</th>
                                                <th class="pd-num">المشتريات</th>
                                                <th class="pd-num">المدفوع</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($purchasesByEmployee as $employee)
                                                @php $empKey = $employee['employee_id'] ?: 0; @endphp
                                                <tr>
                                                    <td data-segment="emp.{{ $empKey }}.all" role="button" tabindex="0">{{ $employee['name'] }}</td>
                                                    <td class="pd-num" data-segment="emp.{{ $empKey }}.purchase" role="button" tabindex="0">{{ $employee['invoices'] }}</td>
                                                    <td class="pd-num font-weight-bold" data-segment="emp.{{ $empKey }}.purchase" role="button" tabindex="0">{{ $money($employee['purchases']) }}</td>
                                                    <td class="pd-num" data-segment="emp.{{ $empKey }}.payment" role="button" tabindex="0" style="color: var(--pd-info);">{{ $money($employee['payments']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="pd-empty"><i class="feather icon-user"></i> لا توجد حركة لموظّفين في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== الاستلام / الأصناف ==================== --}}
            <div class="row">
                <div class="col-lg-5 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-package"></i> حالة استلام المشتريات</h4>
                                <small>كم وصل فعلاً مما اشتُري في الفترة</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                من جدول <code>purchase_invoices</code> بحالة الاستلام المخزَّنة على الفاتورة. وأوامرُ الشراء المعلّقة ليست مستنداً ماليّاً
                                فلا تدخل أيَّ مجموع — تظهر في بطاقتها أعلى اللوحة التزاماً قادماً.
                            </div>
                            @if (collect($receivingMix)->sum('documents') > 0)
                                <div id="pdReceivingChart"></div>
                                <table class="pd-table mt-1">
                                    <tbody>
                                        @foreach ($receivingMix as $row)
                                            <tr @if ($row['documents'] > 0) data-segment="receiving.{{ $row['key'] }}" role="button" tabindex="0" @endif>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="pd-num">{{ $row['documents'] }}</td>
                                                <td class="pd-num">{{ $money($row['amount']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="pd-empty"><i class="feather icon-package"></i> لا توجد فواتير في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-box"></i> أعلى الأصناف شراءً</h4>
                                <small>القيمة صافية من الضريبة المستردّة</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                من دفتر أستاذ المنتجات نفسه الذي يغذّي تقرير مشتريات الأصناف — بنودُ فواتير الشراء بعد المرتجعات، صافيةً من الضريبة المستردّة.
                            </div>
                            @if ($topProducts->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="pd-table">
                                        <thead>
                                            <tr>
                                                <th>الصنف</th>
                                                <th class="pd-num">الكمية</th>
                                                <th class="pd-num">متوسط السعر</th>
                                                <th class="pd-num">صافي القيمة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($topProducts as $product)
                                                <tr @if (! empty($product['product_id'])) data-segment="product.{{ $product['product_id'] }}" role="button" tabindex="0" @endif>
                                                    <td>
                                                        <div>{{ $product['name'] }}</div>
                                                        <small style="color: var(--pd-muted);">{{ $product['category'] }}</small>
                                                    </td>
                                                    <td class="pd-num">{{ number_format($product['quantity'], 0) }}</td>
                                                    <td class="pd-num">{{ $money($product['avg_price']) }}</td>
                                                    <td class="pd-num font-weight-bold">{{ $money($product['net_amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="pd-empty"><i class="feather icon-box"></i> لا توجد أصناف مشتراة في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== آخر الحركات ==================== --}}
            <div class="row">
                <div class="col-12">
                    <div class="pd-card">
                        <div class="pd-card-head">
                            <div>
                                <h4><i class="feather icon-activity"></i> آخر الحركات</h4>
                                <small>فواتير ومرتجعات وإشعارات وسداد الفترة</small>
                            </div>
                            <button type="button" class="pd-info-toggle" data-pd-info-toggle title="من أين تأتي هذه البيانات؟" aria-label="من أين تأتي هذه البيانات؟"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="pd-card-body">
                            <div class="pd-info-panel" hidden>
                                آخرُ القيود المرحَّلة على حسابات الموردين في الفترة — من الدفتر نفسه الذي تُحسب منه البطاقات، فالسدادُ يظهر هنا
                                والفاتورةُ غير المرحَّلة لا تظهر.
                            </div>
                            @if ($recentMovements->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="pd-table">
                                        <thead>
                                            <tr>
                                                <th>المستند</th>
                                                <th>النوع</th>
                                                <th>المورّد</th>
                                                <th>تابع لـ</th>
                                                <th class="pd-num">التاريخ</th>
                                                <th class="pd-num">المبلغ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentMovements as $movement)
                                                @php
                                                    $tone = $kindColors[$movement['kind']] ?? 'var(--pd-muted)';
                                                @endphp
                                                <tr data-segment="supplier.{{ $movement['supplier_id'] }}" role="button" tabindex="0">
                                                    <td class="font-weight-bold">
                                                        @if (! empty($movement['url']))
                                                            <a class="pd-drill" href="{{ $movement['url'] }}" target="_blank" rel="noopener">{{ $movement['code'] }}</a>
                                                        @else
                                                            {{ $movement['code'] }}
                                                        @endif
                                                    </td>
                                                    <td><span class="pd-badge" style="background: color-mix(in srgb, {{ $tone }} 14%, transparent); color: {{ $tone }};">{{ $movement['kind_label'] }}</span></td>
                                                    <td>{{ $movement['supplier_name'] }}</td>
                                                    <td style="color: var(--pd-muted);">{{ $movement['branch_name'] }}</td>
                                                    <td class="pd-num">{{ $showDate($movement['date']) }}</td>
                                                    <td class="pd-num">{{ $money($movement['amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="pd-empty"><i class="feather icon-inbox"></i> لا توجد حركة في هذه الفترة</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== اللوحة الجانبية: ما وراء الرقم ==================== --}}
            <div class="pd-drawer-backdrop" id="pdDrawerBackdrop" data-pd-drawer-close hidden></div>
            <aside class="pd-drawer" id="pdDrawer" role="dialog" aria-modal="true" aria-labelledby="pdDrawerTitle" hidden>
                <header class="pd-drawer-head">
                    <div>
                        <h5 id="pdDrawerTitle"></h5>
                        <p id="pdDrawerHint"></p>
                    </div>
                    <button type="button" class="pd-drawer-close" data-pd-drawer-close aria-label="إغلاق">&times;</button>
                </header>
                <div class="pd-drawer-tools">
                    <input type="search" id="pdDrawerSearch" placeholder="بحث بالاسم أو الكود أو الجوال…" autocomplete="off">
                    <span class="pd-drawer-count" id="pdDrawerCount"></span>
                    <button type="button" class="pd-drawer-btn" id="pdDrawerCsv"><i class="feather icon-download"></i> تصدير CSV</button>
                </div>
                <div class="pd-drawer-body" id="pdDrawerBody"></div>
            </aside>

        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            // ==================== «من أين تأتي هذه البيانات؟» ====================
            document.querySelectorAll('#purchases-dashboard [data-pd-info-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var panel = btn.closest('.pd-card').querySelector('.pd-info-panel');
                    if (panel) { panel.hidden = !panel.hidden; }
                });
            });

            // ==================== الفلترة ====================
            var form = document.getElementById('pdFilterForm');
            var periodInput = document.getElementById('pdPeriod');
            var fromInput = document.getElementById('pdFrom');
            var toInput = document.getElementById('pdTo');
            var customFields = document.querySelectorAll('#purchases-dashboard [data-pd-custom]');

            document.querySelectorAll('#purchases-dashboard .pd-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var period = tab.getAttribute('data-period');
                    var isCustom = period === 'custom';

                    periodInput.value = period;
                    customFields.forEach(function (el) { el.style.display = isCustom ? '' : 'none'; });

                    if (isCustom) {
                        document.querySelectorAll('#purchases-dashboard .pd-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
                        fromInput.focus();

                        return;
                    }

                    // زرُّ الفترة يلغي التاريخين، وإلا تقدّما عليه في الخادم
                    fromInput.value = '';
                    toInput.value = '';
                    form.submit();
                });
            });

            ['pdBranch', 'pdIncludeChildren'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.addEventListener('change', function () { form.submit(); }); }
            });

            // ==================== الشجرة: فتح وطيّ الأبناء ====================
            var tree = document.getElementById('pdTree');
            if (tree) {
                var childRows = function (id) { return tree.querySelectorAll('tr[data-tree-parent="' + id + '"]'); };

                // طيُّ عقدةٍ يُخفي نسلَها كلَّه؛ وفتحُها يُظهر أبناءها وحدهم
                var setOpen = function (row, open) {
                    var caret = row.querySelector('[data-pd-tree-toggle]');
                    if (!caret) { return; }
                    caret.setAttribute('aria-expanded', open ? 'true' : 'false');
                    childRows(row.getAttribute('data-tree-id')).forEach(function (child) {
                        child.hidden = !open;
                        if (!open) { setOpen(child, false); }
                    });
                };

                tree.addEventListener('click', function (e) {
                    var caret = e.target.closest('[data-pd-tree-toggle]');
                    if (!caret) { return; }
                    setOpen(caret.closest('tr'), caret.getAttribute('aria-expanded') !== 'true');
                });

                document.querySelectorAll('#purchases-dashboard [data-pd-tree-all]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var open = btn.getAttribute('data-pd-tree-all') === '1';
                        tree.querySelectorAll('tr[data-tree-parent=""]').forEach(function (root) {
                            if (!open) { setOpen(root, false); return; }
                            (function openAll(row) {
                                setOpen(row, true);
                                childRows(row.getAttribute('data-tree-id')).forEach(openAll);
                            })(root);
                        });
                    });
                });
            }

            // ==================== «ماذا وراء هذا الرقم؟» ====================
            // كل عنصرٍ يحمل `data-segment` يطلب `purchases.dashboard.segment` بفلاتر
            // اللوحة — نسخةُ لوحة المبيعات، والصفُّ فيها مورّدٌ لا مستند.
            var dashboardRoot = document.getElementById('purchases-dashboard');
            var segmentUrl = @json(route('purchases.dashboard.segment'));
            var dashboardFilters = @json(request()->except(['segment']));
            var drillDecimals = @json(\App\Support\CurrencySettings::decimals());
            var drawer = document.getElementById('pdDrawer');
            var backdrop = document.getElementById('pdDrawerBackdrop');
            var drawerBody = document.getElementById('pdDrawerBody');
            var drawerSearch = document.getElementById('pdDrawerSearch');
            var drawerCount = document.getElementById('pdDrawerCount');
            var drawerTitle = document.getElementById('pdDrawerTitle');
            var drawerHint = document.getElementById('pdDrawerHint');
            var PAGE = 200;
            var drill = { data: null, filtered: [], shown: PAGE, request: 0, trigger: null };

            var esc = function (value) {
                return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            };
            var drillMoney = function (v) {
                return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: drillDecimals, maximumFractionDigits: drillDecimals });
            };
            var drillTones = { 'purchase': '--pd-primary', 'return': '--pd-danger', 'debit_note': '--pd-info', 'payment': '--pd-success', 'adjustment': '--pd-muted' };

            var cell = function (col, row) {
                var v = row[col.key];
                var linked = function (url) { return url ? '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(v) + '</a>' : esc(v || '—'); };
                switch (col.type) {
                    case 'doc': return linked(row.url);
                    case 'supplier': return linked(row.supplier_url);
                    case 'kind':
                        var tone = 'var(' + (drillTones[row.kind] || '--pd-muted') + ')';
                        return '<span class="pd-badge" style="background: color-mix(in srgb, ' + tone + ' 14%, transparent); color: ' + tone + ';">' + esc(v) + '</span>';
                    // شارةُ حالة المورّد — لونُها في `<المفتاح>_color`
                    case 'status':
                        var hue = esc(row[col.key + '_color'] || '#999999');
                        return '<span class="pd-badge" style="background: color-mix(in srgb, ' + hue + ' 16%, transparent); color: ' + hue + ';">' + esc(v || '—') + '</span>';
                    case 'since':
                        return (v === null || v === undefined)
                            ? '<span style="color: var(--pd-muted);">لا حركة</span>'
                            : '<span class="pd-num">' + esc(row.last_movement) + '</span> <small class="text-muted">(' + v + ' يوم)</small>';
                    case 'money': return '<span class="pd-num">' + drillMoney(v) + '</span>';
                    case 'number': return '<span class="pd-num">' + Number(v || 0).toLocaleString('en-US') + '</span>';
                    case 'date': return v ? '<span class="pd-num">' + esc(v) + '</span>' : '—';
                    case 'longtext': return v ? '<div class="pd-long">' + esc(v) + '</div>' : '—';
                    default: return (v === null || v === undefined || v === '') ? '—' : esc(v);
                }
            };

            var renderRows = function () {
                var data = drill.data;
                if (!data) { return; }

                // الصفُّ مورّدٌ في كل القوائم إلّا حركةَ مورّدٍ بعينه
                var unit = data.unit === 'suppliers' ? ' مورّد' : ' سجل';
                drawerCount.textContent = drill.filtered.length === data.rows.length
                    ? data.rows.length.toLocaleString('en-US') + unit
                    : drill.filtered.length.toLocaleString('en-US') + ' من ' + data.rows.length.toLocaleString('en-US') + unit;

                if (!drill.filtered.length) {
                    drawerBody.innerHTML = '<div class="pd-empty"><i class="feather icon-inbox"></i>' + (data.rows.length ? 'لا نتائج للبحث.' : 'لا توجد سجلات وراء هذا الرقم.') + '</div>';
                    return;
                }

                var head = '<tr><th>#</th>' + data.columns.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('') + '</tr>';
                var body = drill.filtered.slice(0, drill.shown).map(function (row, i) {
                    return '<tr><td class="pd-num">' + (i + 1) + '</td>' + data.columns.map(function (c) { return '<td>' + cell(c, row) + '</td>'; }).join('') + '</tr>';
                }).join('');

                // المجموعُ على كل ما طابق البحث لا على الصفحة الظاهرة — فيطابق الرقمَ المنقور
                var foot = '';
                if (data.columns.some(function (c) { return c.sum; })) {
                    foot = '<tfoot><tr><td></td>' + data.columns.map(function (c, i) {
                        if (!c.sum) { return '<td>' + (i === 0 ? 'المجموع' : '') + '</td>'; }
                        var total = drill.filtered.reduce(function (a, r) { return a + (Number(r[c.key]) || 0); }, 0);
                        return '<td><span class="pd-num">' + (c.type === 'money' ? drillMoney(total) : total.toLocaleString('en-US', { maximumFractionDigits: 2 })) + '</span></td>';
                    }).join('') + '</tr></tfoot>';
                }

                var more = drill.filtered.length > drill.shown
                    ? '<button type="button" class="pd-drawer-btn pd-drawer-more" id="pdDrawerMore">عرض المزيد (' + (drill.filtered.length - drill.shown).toLocaleString('en-US') + ' متبقٍّ)</button>'
                    : '';

                drawerBody.innerHTML = '<div class="table-responsive"><table class="pd-table"><thead>' + head + '</thead><tbody>' + body + '</tbody>' + foot + '</table></div>' + more;
            };

            var applySearch = function () {
                var q = drawerSearch.value.trim().toLowerCase();
                drill.shown = PAGE;
                drill.filtered = !q ? drill.data.rows.slice() : drill.data.rows.filter(function (r) {
                    return [r.code, r.name, r.supplier, r.phone, r.status, r.employee, r.description, r.kind_label].some(function (x) {
                        return String(x || '').toLowerCase().indexOf(q) !== -1;
                    });
                });
                renderRows();
            };

            var closeDrawer = function () {
                drawer.hidden = true;
                backdrop.hidden = true;
                document.body.style.overflow = '';
                drill.request++;
                if (drill.trigger && document.contains(drill.trigger)) { drill.trigger.focus(); }
            };

            var openSegment = function (segment, trigger, extra) {
                drill.trigger = trigger || null;
                drill.data = null;
                var requestId = ++drill.request;

                drawerTitle.textContent = 'جارٍ التحميل…';
                drawerHint.textContent = '';
                drawerCount.textContent = '';
                drawerSearch.value = '';
                drawerBody.innerHTML = '<div class="pd-empty"><i class="feather icon-loader"></i>جارٍ جلب التفاصيل…</div>';
                drawer.hidden = false;
                backdrop.hidden = false;
                document.body.style.overflow = 'hidden';

                var params = new URLSearchParams();
                Object.keys(dashboardFilters).forEach(function (k) {
                    var v = dashboardFilters[k];
                    if (Array.isArray(v)) { v.forEach(function (x) { params.append(k + '[]', x); }); }
                    else if (v !== null && v !== '') { params.append(k, v); }
                });
                params.set('segment', segment);
                if (trigger && trigger.getAttribute('data-segment-params')) {
                    try { extra = Object.assign({}, JSON.parse(trigger.getAttribute('data-segment-params')), extra || {}); }
                    catch (e) { /* معاملاتٌ تالفة لا تمنع القائمة */ }
                }
                Object.keys(extra || {}).forEach(function (k) { params.set(k, extra[k]); });

                fetch(segmentUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) { throw new Error(r.status === 403 ? 'لا تملك صلاحية عرض هذه القائمة.' : 'تعذّر جلب القائمة (' + r.status + ').'); }
                        return r.json();
                    })
                    .then(function (data) {
                        if (requestId !== drill.request) { return; }
                        drill.data = data;
                        drawerTitle.textContent = data.title;
                        drawerHint.textContent = data.hint || '';
                        drawerSearch.placeholder = data.unit === 'suppliers'
                            ? 'بحث بالاسم أو الكود أو الجوال…'
                            : 'بحث بالمستند أو الموظف أو البيان…';
                        drill.filtered = data.rows.slice();
                        drill.shown = PAGE;
                        renderRows();
                        drawerSearch.focus();
                    })
                    .catch(function (err) {
                        if (requestId !== drill.request) { return; }
                        drawerTitle.textContent = 'تعذّر التحميل';
                        drawerBody.innerHTML = '<div class="pd-empty"><i class="feather icon-alert-triangle"></i>' + esc(err.message) + '</div>';
                    });
            };

            dashboardRoot.addEventListener('click', function (e) {
                if (e.target.closest('[data-pd-drawer-close]')) { closeDrawer(); return; }
                if (e.target.closest('#pdDrawerMore')) { drill.shown += PAGE; renderRows(); return; }
                if (e.target.closest('#pdDrawer')) { return; }

                var el = e.target.closest('[data-segment]');
                var control = e.target.closest('a[href], button');
                // رابطُ الاسم في الشجرة وأزرارُها ورابطُ المستند تعمل كما هي
                if (!el || (control && el.contains(control))) { return; }
                e.preventDefault();
                openSegment(el.getAttribute('data-segment'), el);
            });

            dashboardRoot.addEventListener('keydown', function (e) {
                if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-segment]')) {
                    e.preventDefault();
                    openSegment(e.target.getAttribute('data-segment'), e.target);
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !drawer.hidden) { closeDrawer(); }
            });

            drawerSearch.addEventListener('input', function () { if (drill.data) { applySearch(); } });

            document.getElementById('pdDrawerCsv').addEventListener('click', function () {
                if (!drill.data || !drill.filtered.length) { return; }
                var cols = drill.data.columns;
                var quote = function (v) { return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; };
                var lines = [cols.map(function (c) { return quote(c.label); }).join(',')];
                drill.filtered.forEach(function (row) {
                    lines.push(cols.map(function (c) { return quote(row[c.key]); }).join(','));
                });
                var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (drill.data.title || 'purchases').replace(/[\\/:*?"<>|]/g, '-') + '.csv';
                document.body.appendChild(a);
                a.click();
                setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
            });

            if (typeof ApexCharts === 'undefined') { return; }

            /* الألوان من إعدادات المظهر: المتغيّر يُسنَد إلى خاصيّة لونٍ حقيقية
               على عنصر مسبار فيعيد المتصفّح rgb صريحاً بدل نصّ var() */
            var root = document.getElementById('purchases-dashboard');
            var probe = document.createElement('span');
            probe.style.display = 'none';
            root.appendChild(probe);
            var SENTINEL = 'rgb(1, 2, 3)';
            var color = function (name, fallback) {
                probe.style.color = SENTINEL;
                probe.style.color = 'var(' + name + ')';
                var resolved = getComputedStyle(probe).color;

                return (!resolved || resolved === SENTINEL) ? fallback : resolved;
            };

            var palette = {
                primary: color('--pd-primary', '#7367f0'),
                success: color('--pd-success', '#28c76f'),
                danger: color('--pd-danger', '#ea5455'),
                warning: color('--pd-warning', '#ff9f43'),
                info: color('--pd-info', '#00cfe8'),
                muted: color('--pd-muted', '#7b8194'),
                border: color('--pd-border', '#e6e9f0'),
                font: getComputedStyle(root).fontFamily || 'Cairo, sans-serif'
            };

            var decimals = @json(\App\Support\CurrencySettings::decimals());
            var symbol = @json(\App\Support\CurrencySettings::symbol());
            var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

            var money = function (value) {
                return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            };

            var base = {
                chart: { fontFamily: palette.font, toolbar: { show: false } },
                dataLabels: { enabled: false },
                grid: { borderColor: palette.border, strokeDashArray: 4 },
                tooltip: { y: { formatter: function (v) { return money(v) + ' ' + symbol; } } },
                legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: palette.muted } }
            };

            /*
             * الرسمُ بعد تحميل الخطّ، وإعادتُه إن تأخّر الخطّ عنه: ApexCharts
             * يقيس عرضَ أسماء المحور لحظةَ الرسم، فإن سبق خطَّ Cairo قاسها
             * بالخطّ البديل الأضيق ثم انزلقت فوق الأعمدة حين وصل الخطّ.
             */
            var charts = [];
            var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
            var render = function (selector, options) {
                var el = document.querySelector(selector);
                if (!el) { return; }
                fontsReady.then(function () {
                    var chart = new ApexCharts(el, options);
                    chart.render();
                    charts.push(chart);
                });
            };

            if (document.fonts && document.fonts.addEventListener) {
                document.fonts.addEventListener('loadingdone', function () {
                    charts.forEach(function (chart) { chart.updateOptions({}, true, false); });
                });
            }

            /* عنوان التلميح من الفئات صراحةً — تركُه لاستنتاج المكتبة يطبع
               «undefined» متى تعذّر ربط النقطة بفئتها */
            var titledTooltip = function (names) {
                return {
                    y: base.tooltip.y,
                    x: { formatter: function (value, opts) { var i = opts && opts.dataPointIndex; return (i != null && names[i]) ? names[i] : value; } }
                };
            };

            /* تسميات المحور تُبنى لكل رسم على حدة: المكتبة تعدّل ما يُمرَّر إليها */
            var categoryLabels = function () {
                return {
                    show: true, trim: true, hideOverlappingLabels: false, rotate: -30, rotateAlways: false, maxHeight: 70,
                    style: { colors: palette.muted, fontSize: '11px', fontFamily: palette.font }
                };
            };

            /* أرقام المحور مختصرة (50K، 1.2M) — بالمنازل العشرية كانت تُقصّ إلى
               «50,000…». والتلميح يبقى بالمبلغ كاملاً من `base.tooltip`. */
            var compact = (window.Intl && Intl.NumberFormat)
                ? new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })
                : null;
            var moneyAxis = function () {
                return { labels: { style: { colors: palette.muted, fontSize: '11px' }, formatter: function (v) { return compact ? compact.format(Number(v || 0)) : money(v); } } };
            };

            var donut = function (selector, rows, colors, group) {
                if (!rows.some(function (r) { return r.documents > 0; })) { return; }
                render(selector, Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'donut', height: 250,
                        // نقرُ شريحةٍ يفتح موردي فواتيرها
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = rows[config.dataPointIndex];
                                if (row && group) { openSegment(group + '.' + row.key); }
                            }
                        }
                    }),
                    series: rows.map(function (r) { return r.amount; }),
                    labels: rows.map(function (r) { return r.label; }),
                    colors: colors,
                    legend: { position: 'bottom', fontSize: '12px', labels: { colors: palette.muted } },
                    plotOptions: { pie: { donut: { labels: { show: true, total: {
                        show: true, label: 'الإجمالي', fontSize: '13px', color: palette.muted,
                        formatter: function (w) { return money(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); }
                    } } } } }
                }));
            };

            /* اتجاه المشتريات والسداد */
            var trend = @json($trend);
            if (trend.labels && trend.labels.length) {
                render('#pdTrendChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'area', height: 330,
                        // نقرُ يومٍ أو شهرٍ يفتح حركته — التلميحُ مشترك فلا سلسلةَ بعينها
                        events: {
                            click: function (event, ctx, config) {
                                var i = config ? config.dataPointIndex : -1;
                                if (i === undefined || i === null || i < 0 || !trend.buckets || !trend.buckets[i]) { return; }
                                var kind = config.seriesIndex === 0 ? 'purchase' : (config.seriesIndex === 1 ? 'payment' : 'movement');
                                openSegment('trend.' + trend.buckets[i] + '.' + kind);
                            }
                        }
                    }),
                    series: [
                        { name: 'المشتريات', data: trend.purchases },
                        { name: 'المدفوع', data: trend.payments }
                    ],
                    colors: [palette.primary, palette.info],
                    stroke: { curve: 'smooth', width: 3 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .05, stops: [0, 90, 100] } },
                    xaxis: { categories: trend.labels, labels: categoryLabels() },
                    yaxis: Object.assign({ opposite: isRtl }, moneyAxis())
                }));
            }

            /* حالة السداد والاستلام */
            donut('#pdPaymentStatusChart', @json($paymentStatusMix), [palette.success, palette.warning, palette.danger, palette.info], 'status');
            donut('#pdReceivingChart', @json($receivingMix), [palette.success, palette.warning, palette.danger], 'receiving');

            /* أعلى الموردين مستحقاً */
            var debtors = @json($topDebtors);
            var debtorNames = debtors.map(function (r) { return r.name; });
            if (debtors.length) {
                render('#pdDebtorsChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'bar', height: 300,
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = debtors[config.dataPointIndex];
                                if (row) { openSegment('supplier.' + row.supplier_id); }
                            }
                        }
                    }),
                    states: { active: { filter: { type: 'none' } } },
                    series: [{ name: 'المستحق', data: debtors.map(function (r) { return r.balance; }) }],
                    colors: [palette.warning],
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
                    xaxis: { categories: debtorNames, labels: moneyAxis().labels },
                    yaxis: { opposite: isRtl, labels: { maxWidth: 240, style: { colors: palette.muted, fontSize: '11px', fontFamily: palette.font } } },
                    legend: { show: false },
                    tooltip: titledTooltip(debtorNames)
                }));
            }

            /* مقارنة ما يتبع */
            var branches = @json($branchBreakdown);
            var branchNames = branches.map(function (r) { return r.name; });
            if (branches.length > 0) {
                render('#pdBranchChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'bar', height: Math.max(260, branches.length * 56 + 70),
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = branches[config.dataPointIndex];
                                if (!row) { return; }
                                openSegment(config.seriesIndex === 1 ? 'docs.payment' : 'suppliers.net', null, row.branch_id === null
                                    ? { node: 'none', node_scope: 'tree' }
                                    : { node: row.branch_id, node_scope: row.is_self ? 'direct' : 'tree' });
                            }
                        }
                    }),
                    states: { active: { filter: { type: 'none' } } },
                    series: [
                        { name: 'صافي المشتريات', data: branches.map(function (r) { return r.net_purchases; }) },
                        { name: 'المدفوع', data: branches.map(function (r) { return r.payments; }) }
                    ],
                    colors: [palette.primary, palette.info],
                    /* أفقيٌّ لا عمودي: الأسماء العربية الطويلة تُقرأ كاملةً على محور
                       الأسماء، وكانت تحت الأعمدة تُقصّ وتتزاحم */
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
                    xaxis: { categories: branchNames, labels: moneyAxis().labels },
                    yaxis: { opposite: isRtl, labels: { maxWidth: 260, style: { colors: palette.muted, fontSize: '12px', fontFamily: palette.font } } },
                    grid: { borderColor: palette.border, strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                    tooltip: titledTooltip(branchNames)
                }));
            }

            var resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () { charts.forEach(function (chart) { chart.render(); }); }, 250);
            });
        })();
    </script>
@endpush
