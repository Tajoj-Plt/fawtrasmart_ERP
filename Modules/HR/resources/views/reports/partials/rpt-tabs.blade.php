{{--
    تبويباتُ التقرير — عروضُ المجموعة الواحدة في شريطٍ أعلى الصفحة بدل بلاطاتٍ
    متفرّقة في اللوحة ({@see \Modules\HR\Support\HrReportCatalog}).
    يُستدعى من `rpt-head` تلقائياً؛ ويختفي حين لا يكون للتقرير إلا عرضٌ واحد.
    الفلاترُ المشتركة (المدى والقسم والموظف…) تنتقل مع التبويب.
--}}
@php
    $rtRoute = Route::currentRouteName();
    $rtGroup = \Modules\HR\Support\HrReportCatalog::groupFor($rtRoute);
    $rtTabs = $rtGroup ? \Modules\HR\Support\HrReportCatalog::visibleTabs($rtGroup) : [];
    $rtKeep = array_filter(request()->only([
        'from_date', 'to_date', 'fiscal_year_id', 'accounting_period_id', 'period',
        'department_id', 'job_title_id', 'branch_id', 'employee_id', 'employee_status',
    ]), fn ($v) => $v !== null && $v !== '');
@endphp

@if (count($rtTabs) > 1)
    <div class="rpt-card no-print hr-rpt-tabs">
        <div class="rpt-card__body">
            <div class="ag-tabs" role="tablist" aria-label="{{ $rtGroup['title'] }}">
                <span class="hr-rpt-tabs__label"><i class="fas fa-{{ $rtGroup['icon'] }}"></i> {{ $rtGroup['title'] }}</span>
                @foreach ($rtTabs as $tab)
                    <a href="{{ route($tab['route'], $rtKeep) }}" role="tab"
                       class="ag-tab {{ $tab['route'] === $rtRoute ? 'is-active' : '' }}"
                       @if ($tab['route'] === $rtRoute) aria-selected="true" @endif>{{ $tab['title'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
    @once
        <style>
            .rpt .hr-rpt-tabs .rpt-card__body { padding: .6rem .9rem; }
            .rpt .hr-rpt-tabs .ag-tabs { margin: 0; align-items: center; flex-wrap: wrap; }
            .rpt .hr-rpt-tabs .ag-tab { text-decoration: none; }
            .rpt .hr-rpt-tabs__label { font-weight: 700; color: var(--rpt-ink); margin-inline-end: .5rem; font-size: 13px; }
        </style>
    @endonce
@endif
