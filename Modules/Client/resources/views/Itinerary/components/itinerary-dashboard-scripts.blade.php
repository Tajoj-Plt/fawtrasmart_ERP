@php
    $dashboardStatus = [
        ['label' => 'مكتملة', 'value' => $kpi['completed'], 'color' => '#16a34a'],
        ['label' => 'جارية', 'value' => $kpi['started'], 'color' => '#d97706'],
        ['label' => 'قادمة', 'value' => $kpi['upcoming'], 'color' => '#6366f1'],
        ['label' => 'متأخرة', 'value' => $kpi['overdue'], 'color' => '#f97316'],
        ['label' => 'فائتة', 'value' => $kpi['missed'], 'color' => '#e11d48'],
        ['label' => 'ملغاة', 'value' => $kpi['cancelled'], 'color' => '#94a3b8'],
    ];
@endphp
<script>
    window.ITINERARY_DASHBOARD = {
        nodes: @json($nodes),
        nodeId: @json($filters['node_id']),
        status: @json($dashboardStatus),
        trend: @json($trend),
    };
</script>
<script>
    (function () {
        'use strict';

        const cfg = window.ITINERARY_DASHBOARD;
        const $ = (id) => document.getElementById(id);
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const LEVELS = ['region', 'branch', 'group', 'outlet'];
        const nodeById = new Map(cfg.nodes.map((node) => [Number(node.id), node]));
        const levelSelects = LEVELS.map((level) => document.querySelector(`#route-builder .rb-level[data-level="${level}"]`));

        // ═══════════════ التصفية: المنطقة ← الفرع ← المجموعة ← المنفذ ═══════════════

        function isUnder(node, ancestorId) {
            let cursor = node;
            for (let guard = 0; cursor && guard < 30; guard++) {
                if (Number(cursor.parent_id) === ancestorId) return true;
                cursor = nodeById.get(Number(cursor.parent_id));
            }
            return false;
        }

        function fillLevel(index) {
            const select = levelSelects[index];
            const allLabel = select.options[0].textContent;
            let ancestor = null;
            for (let i = index - 1; i >= 0; i--) {
                if (levelSelects[i].value) { ancestor = Number(levelSelects[i].value); break; }
            }
            const options = cfg.nodes.filter((node) => node.display_type === LEVELS[index] && (ancestor === null || isUnder(node, ancestor)));
            select.innerHTML = `<option value="">${esc(allLabel)}</option>`
                + options.map((node) => `<option value="${node.id}" title="${esc(node.tree_label)}">${esc(node.name)}</option>`).join('');
            select.disabled = options.length === 0;
        }

        function effectiveNode() {
            for (let i = levelSelects.length - 1; i >= 0; i--) {
                if (levelSelects[i].value) return Number(levelSelects[i].value);
            }
            return null;
        }

        const chain = [];
        for (let cursor = nodeById.get(Number(cfg.nodeId)), guard = 0; cursor && guard < 30; guard++) {
            chain.unshift(cursor);
            cursor = nodeById.get(Number(cursor.parent_id));
        }
        LEVELS.forEach((level, index) => {
            fillLevel(index);
            const match = chain.find((node) => node.display_type === level);
            if (match && [...levelSelects[index].options].some((option) => Number(option.value) === Number(match.id))) {
                levelSelects[index].value = String(match.id);
            }
        });

        levelSelects.forEach((select, index) => select.addEventListener('change', () => {
            for (let i = index + 1; i < levelSelects.length; i++) fillLevel(i);
            $('rb-node').value = effectiveNode() || '';
        }));

        $('rb-filters').addEventListener('submit', () => {
            $('rb-filters').querySelectorAll('input, select').forEach((field) => {
                if (!field.name || !field.value) field.disabled = true;
            });
        });

        // ═══════════════ الرسوم ═══════════════

        if (typeof window.ApexCharts === 'undefined') return;

        const font = getComputedStyle(document.getElementById('route-builder')).fontFamily || 'Cairo, sans-serif';
        const empty = (el, text) => { el.innerHTML = `<div class="rb-empty rb-empty--block" dir="rtl"><p>${text}</p></div>`; };

        const statusEl = $('rb-status-chart');
        const status = cfg.status.filter((item) => item.value > 0);
        if (!status.length) {
            empty(statusEl, 'لا زيارات في هذه الفترة.');
        } else {
            new ApexCharts(statusEl, {
                chart: { type: 'donut', height: 290, fontFamily: font, toolbar: { show: false } },
                series: status.map((item) => item.value),
                labels: status.map((item) => item.label),
                colors: status.map((item) => item.color),
                dataLabels: { enabled: false },
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: { show: true, label: 'الإجمالي', formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('en-US') },
                            },
                        },
                    },
                },
            }).render();
        }

        const trendEl = $('rb-trend-chart');
        if (!cfg.trend.some((day) => day.total > 0)) {
            empty(trendEl, 'لا زيارات في هذه الفترة.');
        } else {
            new ApexCharts(trendEl, {
                chart: { type: 'bar', height: 290, fontFamily: font, toolbar: { show: false } },
                series: [
                    { name: 'المخطَّطة', data: cfg.trend.map((day) => day.total) },
                    { name: 'المكتملة', data: cfg.trend.map((day) => day.completed) },
                ],
                xaxis: { categories: cfg.trend.map((day) => day.label), labels: { rotate: -45, hideOverlappingLabels: true } },
                yaxis: { opposite: true, labels: { formatter: (value) => Math.round(value) } },
                colors: ['#c7d2fe', '#16a34a'],
                plotOptions: { bar: { columnWidth: cfg.trend.length > 20 ? '80%' : '55%', borderRadius: 3 } },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { borderColor: '#eef2f7' },
                tooltip: { shared: true, intersect: false },
            }).render();
        }
    })();
</script>
