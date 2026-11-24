{{--
    صفُّ الرسمين — كتقريرَي «أرصدة العملاء» و«أعمار الديون»: أعمدةٌ لأعلى عشرة،
    وحلقةُ توزيعٍ بجانبها مفتاحٌ بالعدد والمبلغ والنسبة.

    @param array $hrCharts [
        'bar'   => ['title' => '', 'icon' => 'chart-bar', 'color' => '#ef4444', 'money' => false,
                    'unit' => 'يوم', 'items' => [['name' => 'الاسم الكامل', 'value' => 12.5], ...]],
        'donut' => ['title' => '', 'icon' => 'chart-pie', 'unit' => 'موظف', 'money' => false,
                    'groups' => [['label' => '', 'count' => 3, 'sum' => 120.0 (اختياري), 'color' => '#10b981'], ...]],
    ]
    الرسمُ الفارغ يُظهر رسالةً بدل لوحةٍ بيضاء. الأعمدةُ تُقصّ إلى عشرة.
--}}
@php
    $hcBar = $hrCharts['bar'] ?? null;
    $hcDonut = $hrCharts['donut'] ?? null;
    $hcId = 'hrc'.substr(md5(($hcBar['title'] ?? '').($hcDonut['title'] ?? '')), 0, 6);

    $hcBarItems = collect($hcBar['items'] ?? [])->filter(fn ($i) => (float) ($i['value'] ?? 0) > 0)
        ->sortByDesc('value')->take(10)->values()
        ->map(fn ($i) => ['name' => (string) $i['name'], 'short' => \Illuminate\Support\Str::limit((string) $i['name'], 14, '…'), 'value' => round((float) $i['value'], 2)])
        ->all();

    $hcGroups = collect($hcDonut['groups'] ?? [])->filter(fn ($g) => (float) ($g['count'] ?? 0) > 0)->values();
    $hcTotal = (float) $hcGroups->sum('count');
    $hcGroups = $hcGroups->map(fn ($g) => $g + [
        'pct' => $hcTotal > 0 ? round($g['count'] * 100 / $hcTotal, 1) : 0,
        'sum' => $g['sum'] ?? null,
    ])->all();
    $hcMoneyDonut = (bool) ($hcDonut['money'] ?? false);
@endphp

@if ($hcBar || $hcDonut)
    <div class="ag-charts">
        @if ($hcBar)
            <div class="rpt-card">
                <div class="rpt-card__head"><h4><i class="fas fa-{{ $hcBar['icon'] ?? 'chart-bar' }}"></i> {{ $hcBar['title'] }}</h4></div>
                <div class="rpt-card__body">
                    @if (count($hcBarItems) > 0)
                        <div class="ag-canvas"><canvas id="{{ $hcId }}Bar" aria-label="{{ $hcBar['title'] }}" role="img"></canvas></div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا بيانات لعرضها</div>
                    @endif
                </div>
            </div>
        @endif

        @if ($hcDonut)
            <div class="rpt-card">
                <div class="rpt-card__head"><h4><i class="fas fa-{{ $hcDonut['icon'] ?? 'chart-pie' }}"></i> {{ $hcDonut['title'] }}</h4></div>
                <div class="rpt-card__body">
                    @if ($hcTotal > 0)
                        <div class="ag-dist">
                            <table class="ag-legend">
                                <tbody>
                                    @foreach ($hcGroups as $g)
                                        <tr class="ag-legend__row" style="--c:{{ $g['color'] }}">
                                            <td><span class="ag-dot"></span>{{ $g['label'] }} <span class="text-muted">({{ $hcMoneyDonut ? number_format($g['count'], 2) : number_format($g['count']) }})</span></td>
                                            <td class="ag-legend__amount">@if ($g['sum'] !== null)<bdi>{{ number_format($g['sum'], 2) }}</bdi>@endif</td>
                                            <td class="ag-legend__pct">{{ $g['pct'] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="ag-donut">
                                <canvas id="{{ $hcId }}Donut" aria-label="{{ $hcDonut['title'] }}" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $hcMoneyDonut ? number_format($hcTotal, 0) : number_format($hcTotal) }}</strong>
                                    <small>{{ $hcDonut['unit'] ?? '' }}</small>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا بيانات لعرضها</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @include('client::customers.CustomerReport.partials.report-charts')
    @endonce
    <script>
        (function () {
            var BAR = @json($hcBarItems), GROUPS = @json($hcGroups);
            var barMoney = @json((bool) ($hcBar['money'] ?? false)), barUnit = @json($hcBar['unit'] ?? ''), barColor = @json($hcBar['color'] ?? '#3b82f6');
            var donutUnit = @json($hcDonut['unit'] ?? ''), donutMoney = @json($hcMoneyDonut);
            function draw() {
                if (typeof Chart === 'undefined' || typeof agChartTheme === 'undefined') { return setTimeout(draw, 150); }
                var theme = agChartTheme(), fmt = barMoney ? agMoney : agWhole;
                var barEl = document.getElementById(@json($hcId.'Bar'));
                if (barEl && BAR.length) {
                    var values = BAR.map(function (r) { return r.value; });
                    new Chart(barEl, {
                        type: 'bar',
                        data: { labels: BAR.map(function (r) { return r.short; }), datasets: [{ data: values, backgroundColor: barColor, borderRadius: 6, maxBarThickness: 48 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false, layout: { padding: { top: 22 } },
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: {
                                title: function (items) { return BAR[items[0].dataIndex].name; },
                                label: function (c) { return ' ' + fmt(BAR[c.dataIndex].value) + ' ' + barUnit; }
                            } } },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 10 }, maxRotation: 45, autoSkip: false } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false }, ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: [agValueLabels(values, fmt)]
                    });
                }
                var donutEl = document.getElementById(@json($hcId.'Donut'));
                if (donutEl && GROUPS.length) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: { labels: GROUPS.map(function (g) { return g.label; }), datasets: [{ data: GROUPS.map(function (g) { return g.count; }), backgroundColor: GROUPS.map(function (g) { return g.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                            var g = GROUPS[c.dataIndex];
                            return ' ' + (donutMoney ? agMoney(g.count) : agWhole(g.count)) + ' ' + donutUnit + ' (' + g.pct + '%)' + (g.sum !== null ? ' · ' + agMoney(g.sum) : '');
                        } } } } }
                    });
                }
            }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', draw); } else { draw(); }
        })();
    </script>
@endif
