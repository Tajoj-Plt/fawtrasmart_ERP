{{--
    الهيكل التنظيمي — شجرةُ المدير المباشر داخل إطار تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::organization`. الشجرةُ كما هي
    (`_tree_node` يستدعي نفسه)، والتصديرُ والطباعةُ صفوفٌ مسطّحة بمستوياتها
    (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    الهيكل التنظيمي
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .org-tree, .rpt .org-tree ul { list-style: none; margin: 0; padding: 0; }
        .rpt .org-tree ul { margin-inline-start: 1.1rem; padding-inline-start: 1rem; border-inline-start: 1px dashed var(--rpt-line); }
        .rpt .org-tree li { margin: .35rem 0; }
        .rpt .org-tree details > summary { list-style: none; cursor: pointer; }
        .rpt .org-tree details > summary::-webkit-details-marker { display: none; }
        .rpt .org-node {
            display: inline-flex; align-items: center; gap: .6rem; flex-wrap: wrap;
            padding: .45rem .75rem; border: 1px solid var(--rpt-line);
            border-radius: 10px; background: var(--rpt-surface); max-width: 100%;
        }
        .rpt .org-node__caret { color: var(--rpt-muted); transition: transform .15s ease; font-size: .8rem; }
        .rpt .org-tree details:not([open]) > summary .org-node__caret { transform: rotate(90deg); }
        .rpt .org-node__name { font-weight: 700; color: var(--rpt-ink); }
        .rpt .org-node__meta { font-size: .8rem; color: var(--rpt-muted); }
        .rpt .org-node__kids { font-size: .72rem; color: var(--rpt-brand); background: var(--rpt-brand-soft); border-radius: 999px; padding: .05rem .5rem; }
    </style>
@endsection

@section('content')
<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'project-diagram',
        'headTitle' => 'الهيكل التنظيمي',
        'headSubtitle' => 'شجرة «المدير المباشر» ومن يتبعه، مبنيّة من ملفات الموظفين',
        'headGuideKey' => 'hr-org-chart.index',
        'headGuide' => [
            'title' => 'كيف تُبنى الشجرة؟',
            'lead' => 'من حقل «المدير المباشر» في ملف كل موظف، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                'كل موظف يُعلَّق تحت من اختير «مديراً مباشراً» في ملفه. ومن لا مدير له — أو مديره خارج النطاق أو الفلترة — يظهر في أعلى الشجرة.',
                'تعديل الشجرة يكون من ملف الموظف (حقل «المدير المباشر»)، لا من هذه الشاشة.',
                'التصدير والطباعة يُخرجان الشجرة صفوفاً: كل موظف بمستواه وعدد تابعيه المباشرين.',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.organization') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])
                    @include('hr::reports.employees._status_field')
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.organization')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">موظفون في الشجرة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">في هذا النطاق</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">في أعلى الشجرة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['roots']) }}</bdi></div><div class="ag-kpi__sub">بلا مدير ضمن النطاق</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-crown"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">مديرون لهم تابعون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['managers']) }}</bdi></div><div class="ag-kpi__sub">مدير مباشر لموظف واحد أو أكثر</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-tie"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">أقسام ممثَّلة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['departments']) }}</bdi></div><div class="ag-kpi__sub">قسم</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-sitemap"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الشجرة ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-project-diagram"></i> شجرة المدير المباشر <span class="rpt-pill rpt-pill-secondary">{{ number_format($totals['employees']) }} موظف</span></h4>
            <div class="ag-tools no-print">
                @if ($roots->isNotEmpty())
                    <button type="button" class="btn btn-sm ag-btn-soft" data-org-toggle="open"><i class="fas fa-expand-alt"></i> توسيع الكل</button>
                    <button type="button" class="btn btn-sm ag-btn-soft" data-org-toggle="close"><i class="fas fa-compress-alt"></i> طيّ الكل</button>
                @endif
                @if ($canExport)
                    <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                @endif
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-card__body">
            @if ($orphanedByCycle)
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    يوجد {{ $employees->count() }} موظفاً ضمن نطاقك، لكن تعذّر رسم الشجرة:
                    بيانات المدير المباشر لهؤلاء الموظفين تحتوي حلقة (موظفٌ يتبع مديراً يتبعه هو بدوره
                    مباشرةً أو عبر سلسلة)، فلا يوجد بينهم أي موظف بلا مدير ليكون جذراً للشجرة.
                    راجع حقل "المدير المباشر" في ملفات هؤلاء الموظفين لتصحيح السلسلة.
                </div>
            @elseif ($roots->isEmpty())
                <div class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</div>
            @else
                <ul class="org-tree" id="org-tree">
                    @foreach ($roots as $root)
                        @include('hr::reports.employees._tree_node', ['node' => $root, 'childrenOf' => $childrenOf, 'nodeCanShow' => $canShowEmployee])
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'الهيكل التنظيمي',
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => ['#', 'الموظف', 'الرقم', 'المسمى', 'القسم', 'الفرع', 'المستوى', 'تابعون مباشرون', 'الحالة'],
    ])
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-org-toggle]');
            if (! button) {
                return;
            }
            var open = button.getAttribute('data-org-toggle') === 'open';
            document.querySelectorAll('#org-tree details').forEach(function (node) {
                node.open = open;
            });
        });
    </script>
@endsection
