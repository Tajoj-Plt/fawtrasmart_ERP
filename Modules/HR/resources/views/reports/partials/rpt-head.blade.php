{{--
    ترويسةُ تقرير الموارد البشرية — نفسُ ترويسة تقارير العملاء، ومسارُها إلى
    «تقارير الموارد البشرية» لا «تقارير العملاء»، ثم شريطُ حالة الفترة.

    @param string      $headIcon, $headTitle, $headSubtitle, $headPeriod
    @param string|null $headGuideKey
    @param array|null  $headGuide   title / lead / blocks / facts
    @param array       $periodContext
--}}
@include('client::customers.CustomerReport.partials.report-head', [
    'headIcon' => $headIcon ?? 'chart-bar',
    'headTitle' => $headTitle ?? '',
    'headSubtitle' => $headSubtitle ?? null,
    'headPeriod' => $headPeriod ?? null,
    'headGuideKey' => $headGuideKey ?? 'hr-reports.screen',
    'headGuide' => $headGuide ?? [
        'title' => 'من أين تأتي الأرقام؟',
        'lead' => 'التقرير يقرأ الموظفين في نطاق صلاحيتك فقط، وفرعُ الشريط العلوي و«تابع لـ» يحصرانه.',
        'blocks' => [[
            'التصدير والطباعة يشملان كل الصفوف لا الصفحة الظاهرة وحدها.',
            'اختر فترة محاسبية أو سنة مالية لتملأ التاريخين تلقائياً.',
        ]],
        'facts' => [],
    ],
    'headCrumbLabel' => 'تقارير الموارد البشرية',
    'headCrumbUrl' => route('hr.reports.index'),
])

@include('hr::reports.partials.rpt-tabs')

@once
    <style>
        /* «تابع لـ» آخرَ شبكة الفلترة، كتقارير العملاء */
        .rpt .ag-filter-grid > .hr-tree-cell { order: 90; }
        /* صفُّ الجدول سطرٌ واحد — والجدولُ العريض يُمرَّر أفقياً بدل أن تتكسّر خلاياه */
        .rpt .ag-table td { white-space: nowrap; }
        .rpt .ag-table td.rpt-name, .rpt .ag-table td.hr-wrap { white-space: normal; min-width: 170px; }
        .rpt .ag-table .rpt-pill { white-space: nowrap; }
    </style>
@endonce

@include('layouts.alerts.success')
@include('layouts.alerts.error')
@include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])
