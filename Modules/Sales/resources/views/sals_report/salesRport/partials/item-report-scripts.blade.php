{{-- رسما «مبيعات البنود» و«مبيعات الأصناف» — يقرآن `window.ITEM_REPORT_CHARTS` من partials.item-report-common --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@include('client::customers.CustomerReport.partials.report-charts')
@include('sales::sals_report.report_partials.scripts', ['cascadeBranches' => $cascadeBranches ?? []])
<script>
    $(function () {
        var data = window.ITEM_REPORT_CHARTS || { bars: { labels: [], values: [] }, donut: [] };
        if (typeof Chart === 'undefined') return;

        var bar = document.getElementById('groupsChart');
        if (bar && data.bars.labels.length) {
            var theme = agChartTheme();
            new Chart(bar.getContext('2d'), {
                type: 'bar',
                data: { labels: data.bars.labels, datasets: [{ data: data.bars.values, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 48 }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    layout: { padding: { top: 22 } },
                    plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agMoney(c.parsed.y); } } } },
                    scales: {
                        x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 45, autoSkip: true,
                             callback: function (v) { var l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                        y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                             ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                    }
                },
                plugins: data.bars.labels.length <= 12 ? [agValueLabels(data.bars.values)] : []
            });
        }

        var donut = document.getElementById('mixDonut');
        if (donut && data.donut.length) {
            var t = agChartTheme();
            new Chart(donut, {
                type: 'doughnut',
                data: {
                    labels: data.donut.map(function (s) { return s.label; }),
                    datasets: [{ data: data.donut.map(function (s) { return s.value; }), backgroundColor: data.donut.map(function (s) { return s.color; }), borderColor: t.surface, borderWidth: 3, hoverOffset: 6 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '66%',
                    plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                        var s = data.donut[c.dataIndex]; return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                    } } } }
                }
            });
        }
    });
</script>
