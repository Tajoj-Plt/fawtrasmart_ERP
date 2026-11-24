{{--
    تقرير أهداف العملاء — على نمط تقارير العملاء المعتمد (أعمار ديون
    الفواتير): ترويسةٌ فاتحة، شبكةُ فلترة، أربعةُ مؤشّرات، حلقةُ توزيع
    العملاء على الأهداف بجدولها، ثمّ جدولُ العملاء.

    ما لم يتغيّر:
    • الحقولُ الأربعة (تابع لـ، الوضع، الهدف، البحث) بأسمائها وقيمها.
    • الجدولُ مرقَّمٌ من الخادم (خمسون صفّاً) — لا ترقيمَ في المتصفّح؛
      روابطُ الصفحات في ذيل البطاقة.
    • التصديرُ والطباعةُ يجلبان كلَّ الصفوف المطابقة (`export=1`) إلى
      `#targetsExportTable` المخفيّ ثمّ يُطلقان الأداةَ المشتركة.
    • التوزيعُ من `$targets->clients_count` و`$stats` نفسِهما — لا استعلامَ جديد.
--}}
@extends('client::master')

@section('title', 'تقرير أهداف العملاء')

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* روابطُ ترقيم الخادم (pagination.stk) بمقاس أزرار ag-page */
        .rpt .ag-pager .pagination { display: flex; flex-wrap: wrap; gap: 4px; margin: 0; padding: 0; }
        .rpt .ag-pager .page-item .page-link {
            min-width: 32px; height: 32px; padding: 0 8px; margin: 0;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: .8rem; font-weight: 700; box-shadow: none;
            border: 1px solid var(--rpt-line); background: var(--rpt-surface); color: var(--rpt-ink);
        }
        .rpt .ag-pager .page-item:not(.active):not(.disabled) .page-link:hover { border-color: var(--rpt-brand); color: var(--rpt-brand); }
        .rpt .ag-pager .page-item.active .page-link { background: var(--rpt-brand); border-color: var(--rpt-brand); color: var(--rpt-on-brand); }
        .rpt .ag-pager .page-item.disabled .page-link { opacity: .45; }
        .rpt .ag-applied { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: .8rem; }
    </style>
@endsection

@section('content')
@php
    $agTargetFilter = (string) request('customer_target_id', '');
    $agTotalClients = (int) $stats['total_clients'];
    $agEvaluatedPct = $agTotalClients > 0 ? round($stats['evaluated_clients'] / $agTotalClients * 100) : 0;
    $agUnevaluatedPct = $agTotalClients > 0 ? round($stats['unevaluated_clients'] / $agTotalClients * 100) : 0;

    /* توزيعُ العملاء: كلُّ هدفٍ بلونه، ثمّ «بدون هدف» — قيمةُ `filter` هي
       قيمةُ `customer_target_id` نفسُها التي يقرؤها المتحكّم */
    $agDist = [];
    foreach ($targets as $agTarget) {
        $agDist[] = [
            'filter' => (string) $agTarget->id,
            'label' => $agTarget->name,
            'value' => (int) $agTarget->clients_count,
            'color' => $agTarget->color ?: '#64748b',
        ];
    }
    $agDist[] = [
        'filter' => 'no_target',
        'label' => 'بدون هدف / غير مقيّم',
        'value' => (int) $stats['unevaluated_clients'],
        'color' => '#94a3b8',
    ];
    $agDistTotal = array_sum(array_column($agDist, 'value'));
    foreach ($agDist as $agI => $agRow) {
        $agDist[$agI]['pct'] = $agDistTotal > 0 ? round($agRow['value'] / $agDistTotal * 100) : 0;
    }
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'bullseye',
        'headTitle' => 'تقرير أهداف العملاء',
        'headSubtitle' => 'عرض توزيع العملاء وتصنيفهم على أهداف التقييم',
        'headExtra' => $headerNode ? 'الفرع النشط: '.$headerNode->name : null,
        'headGuideKey' => 'client-reports.customer-targets',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ClientReport.customerTargets') }}" id="targetReportForm">
                <div class="ag-filter-grid">
                    {{-- الحالةُ (نشط/موقوف) فلترٌ مستقلٌّ عن الوضع — `applyClientFilters` يقرأ `status` --}}
                    <div class="rpt-field">
                        <label for="filter_status">حالة العميل</label>
                        <select name="status" class="form-control rpt-select2" id="filter_status">
                            <option value="">— كل الحالات —</option>
                            @include('reports.partials.status-filter-options')
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.stage-select', [
                            'registrationStages' => $registrationStages ?? collect(),
                            'label' => 'وضع العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        <label for="filter_target">هدف العميل</label>
                        <select name="customer_target_id" class="form-control rpt-select2" id="filter_target">
                            <option value="">— كل الأهداف —</option>
                            <option value="no_target" {{ request('customer_target_id') == 'no_target' ? 'selected' : '' }}>
                                بدون هدف / غير مقيّم
                            </option>
                            @foreach($targets as $target)
                                <option value="{{ $target->id }}" {{ request('customer_target_id') == $target->id ? 'selected' : '' }}>
                                    {{ $target->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="filter_search">بحث بالاسم</label>
                        <input type="text" name="search" class="form-control" id="filter_search"
                               value="{{ request('search') }}" placeholder="اكتب اسم العميل أو الكود للبحث...">
                    </div>

                    {{-- «تابع لـ» — نفسُ مُنتقي بقيّة تقارير العملاء؛ يفتح على فرع
                         الهيدر: هو ما تقرؤه الصفحةُ حين لا يُختار غيرُه --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => $treeNodes ?? [],
                                'currentNode' => $headerNode?->id,
                            ])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    @if(request()->hasAny(['tree_node', 'status', 'registration_stage', 'customer_target_id', 'search']) || $headerNode)
                        <div class="ag-applied">
                            <span class="text-muted fw-bold">الفلاتر المطبقة حالياً:</span>
                            @if(request('status') && ($tgStatus = ($statuses ?? collect())->firstWhere('id', (int) request('status'))))
                                <span class="ag-bucket" style="--c:{{ $tgStatus->color ?: '#64748b' }}">الحالة: {{ $tgStatus->name }}</span>
                            @endif
                            @if(request('tree_node'))
                                <span class="ag-bucket" style="--c:#3b82f6">تابع لـ: {{ optional(\App\Models\Branch::find(request('tree_node')))->name }}</span>
                            @elseif($headerNode)
                                <span class="ag-bucket" style="--c:#3b82f6">الفرع النشط: {{ $headerNode->name }}</span>
                            @endif
                            @if(request('customer_target_id') == 'no_target')
                                <span class="ag-bucket">بدون هدف</span>
                            @elseif(request('customer_target_id'))
                                <span class="ag-bucket" style="--c:{{ optional($targets->find(request('customer_target_id')))->color ?? '#666' }}">
                                    {{ optional($targets->find(request('customer_target_id')))->name }}
                                </span>
                            @endif
                            @if(request('search'))
                                <span class="ag-bucket" style="--c:#06b6d4">بحث عن: "{{ request('search') }}"</span>
                            @endif
                            <span class="text-muted fw-bold">({{ $clients->total() }} عميل مطابق)</span>
                        </div>
                    @endif
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ClientReport.customerTargets') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">إجمالي العملاء</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($stats['total_clients']) }}</bdi></div>
                <div class="ag-kpi__sub">عميل ضمن نطاق الصفحة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">عملاء مقيّمين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($stats['evaluated_clients']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $agEvaluatedPct }}% من العملاء</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">بدون هدف / غير مقيّم</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($stats['unevaluated_clients']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $agUnevaluatedPct }}% من العملاء</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#eab308">
            <div>
                <div class="ag-kpi__label">مستويات التقييم</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($targets->count()) }}</bdi></div>
                <div class="ag-kpi__sub">هدف تقييم معرَّف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-layer-group"></i></span>
        </div>
    </div>

    {{-- ===================== توزيع العملاء على الأهداف ===================== --}}
    <div class="ag-charts ag-charts--single">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع العملاء بالتفصيل</h4>
                <span class="ag-hint no-print">اضغط على هدف لعرض عملائه فقط</span>
            </div>
            <div class="rpt-card__body">
                @if ($agDistTotal > 0)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($agDist as $agRow)
                                    <tr class="ag-legend__row {{ $agTargetFilter === $agRow['filter'] ? 'is-active' : '' }}"
                                        style="--c:{{ $agRow['color'] }}" data-filter="{{ $agRow['filter'] }}" tabindex="0"
                                        title="{{ $agTargetFilter === $agRow['filter'] ? 'إلغاء فلتر هذا الهدف' : 'عرض عملاء هذا الهدف فقط' }}">
                                        <td><span class="ag-dot"></span>{{ $agRow['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ number_format($agRow['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $agRow['pct'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="targetsDonut" aria-label="توزيع العملاء على الأهداف" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($agDistTotal) }}</strong>
                                <small>عميل</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا عملاء لعرض توزيعهم</div>
                @endif
            </div>
        </div>
    </div>

    {{-- جدولُ التصدير — يُملأ بكلّ الصفوف المطابقة ساعةَ الطلب، لا بصفحة
         الخمسين المعروضة. الترويسةُ نفسُ ترويسة `ajax_table`. --}}
    <table id="targetsExportTable" hidden>
        <thead>
            <tr>
                <th>كود العميل</th>
                <th>اسم العميل</th>
                <th>الحالة</th>
                <th>الوضع</th>
                <th>الفرع</th>
                <th>الموظف المسؤول</th>
                <th>الهدف / التصنيف الحالي</th>
                <th>تاريخ آخر تقييم</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
    <span id="targetsExcelRun" hidden></span>
    <span id="targetsPrintRun" hidden></span>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> عملاء الأهداف
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($clients->total()) }} عميل</span>
            </h4>
            <div class="ag-tools no-print">
                @permission('clients_edit_client_settings')
                    <a href="{{ route('client.customer_targets.index') }}" class="btn btn-sm ag-btn-soft">
                        <i class="fas fa-cog"></i> إعدادات وأدوات التقييم
                    </a>
                @endpermission
                <button type="button" class="btn btn-sm ag-btn-soft" id="targetsExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="targetsPrint"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            @include('client::customers.customer_targets.ajax_table')
        </div>

        {{-- ترقيمُ الخادم في ذيل البطاقة بمظهر ag-pager (بلا data-rows: لا ترقيمَ في المتصفّح) --}}
        @if($clients->hasPages())
            <div class="ag-pager no-print">
                <span class="ag-pager__info">عرض {{ $clients->firstItem() }} – {{ $clients->lastItem() }} من أصل {{ $clients->total() }} عميل</span>
                {{ $clients->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
    @php
        $stageFilter = request('registration_stage');
        $targetFilter = request('customer_target_id');

        $exportInfo = array_filter([
            'تابع لـ' => request('tree_node')
                ? optional(\App\Models\Branch::find(request('tree_node')))->name
                : ($headerNode?->name ?? 'كل الفروع'),
            'وضع العميل' => $stageFilter === 'no_stage'
                ? 'بلا وضع'
                : optional(($registrationStages ?? collect())->firstWhere('id', $stageFilter))->name,
            'هدف العميل' => $targetFilter === 'no_target'
                ? 'بدون هدف / غير مقيّم'
                : optional($targets->firstWhere('id', $targetFilter))->name,
            'بحث' => request('search'),
        ]);

        $exportCards = array_merge([
            ['title' => 'إجمالي العملاء', 'value' => (string) $stats['total_clients']],
            ['title' => 'عملاء مقيّمين', 'value' => (string) $stats['evaluated_clients']],
            ['title' => 'بدون هدف / غير مقيّم', 'value' => (string) $stats['unevaluated_clients']],
        ], $targets->map(fn ($t) => ['title' => $t->name, 'value' => (string) $t->clients_count])->all());
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    @include('stock::components.report-tools')

    <script>
        const AG_DIST = @json($agDist);
        const AG_DIST_TOTAL = @json($agDistTotal);

        $(function () {
            if ($.fn.select2) {
                $('.rpt-select2').each(function () {
                    var $el = $(this);
                    if ($el.data('select2')) { $el.select2('destroy'); }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function () { return "لا توجد نتائج"; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            /* صفُّ الهدف في جدول التوزيع أو قطعتُه في الحلقة يفلتر الجدول به؛
               النقرُ على الهدف المفلتَر نفسِه يُلغي الفلتر */
            $(document).on('click', '.ag-legend__row[data-filter]', function () {
                applyTargetFilter(String($(this).data('filter')));
            });
            $(document).on('keydown', '.ag-legend__row[data-filter]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyTargetFilter(String($(this).data('filter'))); }
            });

            /* الأداةُ المشتركة مربوطةٌ بمُطلِقَين مخفيَّين، والزرّان الظاهران
               يجلبان كلَّ الصفوف أوّلاً ثمّ يُطلقانها. */
            stkReportTools({
                table: '#targetsExportTable',
                fileName: 'تقرير_أهداف_العملاء',
                title: 'تقرير أهداف العملاء',
                info: @json($exportInfo),
                cards: @json($exportCards),
                exportButton: '#targetsExcelRun',
                printButton: '#targetsPrintRun',
                descriptionColumn: 1,
            });

            // الرابطُ الحاليّ بفلاتره كلِّها، بلا رقم صفحة
            var EXPORT_URL = @json(request()->fullUrlWithQuery(['export' => 1, 'page' => null]));

            function loadRows() {
                return fetch(EXPORT_URL, { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var body = document.querySelector('#targetsExportTable tbody');
                        body.innerHTML = '';
                        doc.querySelectorAll('tbody tr').forEach(function (tr) {
                            body.appendChild(document.importNode(tr, true));
                        });
                    });
            }

            function failed() {
                if (typeof showMessage === 'function') {
                    showMessage('تعذّر تحميل صفوف التقرير', 'error');
                } else {
                    alert('تعذّر تحميل صفوف التقرير');
                }
            }

            $('#targetsExcel').on('click', function () {
                var $btn = $(this).prop('disabled', true);

                loadRows()
                    .then(function () { document.getElementById('targetsExcelRun').click(); })
                    .catch(failed)
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#targetsPrint').on('click', function () {
                /* النافذةُ تُفتح الآن مع النقرة: بعد انتظار الجلب يعدّها المتصفّح
                   نافذةً منبثقةً بلا نقرة فيحجبها. ثمّ تُسلَّم لـ`printTable()`
                   بدل أن تفتح هي نافذتها. */
                var win = window.open('', '', 'height=900,width=1400');
                var $btn = $(this).prop('disabled', true);

                loadRows()
                    .then(function () {
                        var nativeOpen = window.open;
                        window.open = function () { return win; };

                        try {
                            document.getElementById('targetsPrintRun').click();
                        } finally {
                            window.open = nativeOpen;
                        }
                    })
                    .catch(function () {
                        if (win) win.close();
                        failed();
                    })
                    .finally(function () { $btn.prop('disabled', false); });
            });

            renderTargetsDonut();
        });

        function applyTargetFilter(filter) {
            var $select = $('#filter_target');
            $select.val($select.val() === filter ? '' : filter);
            $('#targetReportForm').submit();
        }

        function renderTargetsDonut() {
            var el = document.getElementById('targetsDonut');
            if (!el || typeof Chart === 'undefined' || !(AG_DIST_TOTAL > 0)) return;

            var theme = agChartTheme();
            var chart = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: AG_DIST.map(function (d) { return d.label; }),
                    datasets: [{
                        data: AG_DIST.map(function (d) { return d.value; }),
                        backgroundColor: AG_DIST.map(function (d) { return d.color; }),
                        borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '66%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { rtl: true, callbacks: { label: function (ctx) {
                            var d = AG_DIST[ctx.dataIndex];
                            return ' ' + agWhole(d.value) + ' عميل (' + d.pct + '%)';
                        } } }
                    }
                }
            });

            el.style.cursor = 'pointer';
            el.addEventListener('click', function (evt) {
                var hit = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                if (hit.length) applyTargetFilter(AG_DIST[hit[0].index].filter);
            });
        }
    </script>
@endsection
