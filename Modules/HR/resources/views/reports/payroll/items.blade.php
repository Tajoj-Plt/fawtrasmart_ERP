{{--
    مكونات الراتب / البدلات — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::itemsReport()`. بنودُ الرواتب
    (`payroll_run_items`): «مكونات الراتب» كلُّ الأنواع مجمَّعةً بالنوع واسم البند،
    و«البدلات» بنودُ الاستحقاق وحدها مجمَّعةً بنوع البدل في الكتالوج.
    عرضان: لكل بند (مجمَّع) أو لكل سطر (موظف + شهر + بند).
--}}
@extends('hr::master')

@php
    $title = $allowancesOnly ? 'البدلات' : 'مكونات الراتب';
    $route = $allowancesOnly ? 'hr.reports.payroll.allowances' : 'hr.reports.payroll.components';
@endphp

@section('title')
    {{ $title }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $money = fn ($v) => number_format((float) $v, 2);
    $pager = $isSummary ? $groups : $rows;
    $canOpenRun = Route::has('payroll-runs.show') && hasPermission('disbursements.show');
    $typeTone = ['basic' => 'rpt-pill-secondary', 'addition' => 'rpt-pill-success', 'retro' => 'rpt-pill-success', 'deduction' => 'rpt-pill-danger', 'advance' => 'rpt-pill-warning', 'employer' => 'rpt-pill-secondary'];
    $byType = $totals['by_type'];
    $tAmount = fn ($k) => $byType[$k]['amount'] ?? 0;
    $tCount = fn ($k) => $byType[$k]['count'] ?? 0;
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => $allowancesOnly ? 'hand-holding-usd' : 'list-ul',
        'headTitle' => $title,
        'headSubtitle' => $allowancesOnly
            ? 'البدلات والإضافات وحدها (سكن، نقل، ...) دون الراتب الأساسي، مجمَّعة بنوع البدل: كم موظفاً أخذه وكم مرة وكم مبلغه'
            : 'كل بنود الرواتب: الأساسي والبدلات والخصم وأقساط السلف ومساهمة المنشأة، مجمَّعة باسم البند',
        'headPeriod' => $periodLabel,
        'headGuideKey' => $allowancesOnly ? 'hr-reports.payroll-allowances' : 'hr-reports.payroll-components',
        'headGuide' => [
            'title' => 'من أين تأتي الأرقام؟',
            'lead' => 'من بنود كل راتب في «سجل صرف الرواتب» — البند كما كُتب في الراتب نفسه.',
            'blocks' => [$allowancesOnly ? [
                'البدل يُجمع باسم نوعه في «أنواع مكونات الراتب» إن كان مربوطاً به، وإلا باسمه كما كُتب في الراتب.',
                'الراتب الأساسي والفروقات بأثر رجعي والخصم والسلف ليست هنا — تجدها في «مكونات الراتب».',
                'بنود الرواتب الملغاة لا تدخل إلا إذا اخترتها من «حالة الصرف».',
            ] : [
                '«مساهمة المنشأة» (مثل حصة التأمينات) تدفعها المنشأة فوق الراتب ولا تُخصم من الموظف.',
                '«قسط سلفة» = ما اقتُطع من الراتب سداداً لسلفة. و«خصم» = غياب أو تأخير أو جزاء أو غيره.',
                'الراتب الذي لم يُدفع بعد قد لا تظهر بنوده كلها: تُكتب البنود نهائياً يوم الصرف.',
                'بنود الرواتب الملغاة لا تدخل إلا إذا اخترتها من «حالة الصرف».',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route($route),
        'views' => [
            'summary' => [$allowancesOnly ? 'لكل بدل' : 'لكل بند', 'layer-group'],
            'details' => ['لكل موظف وشهر', 'list'],
        ],
        'selects' => array_values(array_filter([
            $allowancesOnly ? null : ['name' => 'item_type', 'label' => 'نوع البند', 'options' => ['' => 'كل الأنواع'] + $typeLabels],
            ['name' => 'status', 'label' => 'حالة الصرف', 'options' => $runStatusOptions],
        ])),
        'texts' => [['name' => 'item_name', 'label' => $allowancesOnly ? 'اسم البدل' : 'اسم البند', 'placeholder' => 'جزء من الاسم']],
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        @if ($allowancesOnly)
            <div class="ag-kpi" style="--c:#10b981">
                <div><div class="ag-kpi__label">إجمالي البدلات</div><div class="ag-kpi__value"><bdi>{{ $money($totals['amount']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['lines']) }} بند في الرواتب</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#3b82f6">
                <div><div class="ag-kpi__label">أنواع البدلات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['kinds']) }}</bdi></div><div class="ag-kpi__sub">نوع</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-tags"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#8b5cf6">
                <div><div class="ag-kpi__label">الموظفون المستفيدون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['employees']) }}</bdi></div><div class="ag-kpi__sub">موظف</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#f59e0b">
                <div><div class="ag-kpi__label">أكبر بدل</div><div class="ag-kpi__value"><bdi>{{ $totals['top'] ? $money($totals['top']['amount']) : '—' }}</bdi></div><div class="ag-kpi__sub">{{ $totals['top']['name'] ?? 'لا بدلات' }}</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-trophy"></i></span>
            </div>
        @else
            <div class="ag-kpi" style="--c:#10b981">
                <div><div class="ag-kpi__label">الأساسي والبدلات</div><div class="ag-kpi__value"><bdi>{{ $money($tAmount('basic') + $tAmount('addition') + $tAmount('retro')) }}</bdi></div><div class="ag-kpi__sub">أساسي {{ $money($tAmount('basic')) }} · بدلات {{ $money($tAmount('addition')) }}{{ $tAmount('retro') > 0 ? ' · فروقات '.$money($tAmount('retro')) : '' }}</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-plus-circle"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#ef4444">
                <div><div class="ag-kpi__label">الخصم</div><div class="ag-kpi__value"><bdi>{{ $money($tAmount('deduction')) }}</bdi></div><div class="ag-kpi__sub">{{ $tCount('deduction') }} بند</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-minus-circle"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#f59e0b">
                <div><div class="ag-kpi__label">أقساط السلف</div><div class="ag-kpi__value"><bdi>{{ $money($tAmount('advance')) }}</bdi></div><div class="ag-kpi__sub">{{ $tCount('advance') }} قسط</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-credit-card"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#64748b">
                <div><div class="ag-kpi__label">مساهمة المنشأة</div><div class="ag-kpi__value"><bdi>{{ $money($tAmount('employer')) }}</bdi></div><div class="ag-kpi__sub">فوق الراتب — لا تُخصم من الموظف</div></div>
                <span class="ag-kpi__icon"><i class="fas fa-building"></i></span>
            </div>
        @endif
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? ($allowancesOnly ? 'البدلات لكل نوع' : 'البنود مجمَّعة') : 'البنود لكل موظف وشهر' }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $isSummary ? 'بند' : 'سطر' }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @if ($isSummary)
                        @forelse ($groups as $g)
                            @php
                                $link = route($route, array_merge(request()->except(['page', 'view', 'export']), array_filter([
                                    'view' => 'details',
                                    'item_type' => $allowancesOnly ? null : $g['type'],
                                    'item_name' => $g['raw_name'],
                                ])));
                            @endphp
                            <tr>
                                <td>{{ $groups->firstItem() + $loop->index }}</td>
                                @unless ($allowancesOnly)
                                    <td><span class="rpt-pill {{ $typeTone[$g['type']] ?? 'rpt-pill-secondary' }}">{{ $g['type_label'] }}</span></td>
                                @endunless
                                <td class="rpt-name"><a href="{{ $link }}">{{ $g['name'] }}</a></td>
                                <td class="rpt-num">{{ number_format($g['employees']) }}</td>
                                <td class="rpt-num">{{ number_format($g['count']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($g['amount']) }}</strong></td>
                                @if ($allowancesOnly)
                                    <td class="rpt-num">{{ $totals['amount'] > 0 ? round($g['amount'] / $totals['amount'] * 100, 1).'%' : '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا بنود في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @else
                        @forelse ($rows as $r)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($canOpenRun)
                                        <a href="{{ route('payroll-runs.show', $r['run_id']) }}" target="_blank" rel="noopener">{{ $r['employee'] }}</a>
                                    @else
                                        {{ $r['employee'] }}
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $r['department'] }}</span></td>
                                <td class="rpt-num">{{ $r['period'] }}</td>
                                <td><span class="rpt-pill {{ $typeTone[$r['type']] ?? 'rpt-pill-secondary' }}">{{ $r['type_label'] }}</span></td>
                                <td>{{ $r['name'] }}</td>
                                <td class="rpt-num"><strong>{{ $money($r['amount']) }}</strong></td>
                                <td><span class="rpt-pill {{ $r['tone'] }}">{{ $r['stage_label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا بنود في هذا المدى والنطاق</td></tr>
                        @endforelse
                    @endif
                </tbody>
                @if ($pager->total() > 0 && ($allowancesOnly || request('item_type')))
                    {{-- جمعُ أنواعٍ مختلفة (إضافة + خصم) لا معنى له؛ فالإجمالي للبدلات أو لنوعٍ واحدٍ مختار --}}
                    <tfoot>
                        <tr>
                            @if ($isSummary)
                                <td colspan="{{ $allowancesOnly ? 2 : 3 }}"><strong>الإجمالي</strong></td>
                                <td class="rpt-num"><strong>{{ number_format($totals['employees']) }}</strong></td>
                                <td class="rpt-num"><strong>{{ number_format($totals['lines']) }}</strong></td>
                                <td class="rpt-num"><strong>{{ $money($totals['amount']) }}</strong></td>
                                @if ($allowancesOnly)<td class="rpt-num"><strong>100%</strong></td>@endif
                            @else
                                <td colspan="6"><strong>الإجمالي — {{ number_format($totals['lines']) }} سطر</strong></td>
                                <td class="rpt-num"><strong>{{ $money($totals['amount']) }}</strong></td>
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($pager->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من {{ number_format($pager->total()) }}</small>
                {{ $pager->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => $title.($isSummary ? '' : ' — لكل موظف وشهر'),
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
