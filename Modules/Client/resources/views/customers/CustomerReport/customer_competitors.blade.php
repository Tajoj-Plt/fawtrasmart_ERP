{{--
    تقرير المنافسين — ما رصده المندوبون عند العملاء في الزيارات.
    المتحكّم: `CustomerCompetitorReportController`. عرضان: الملخّص (منافسٌ لكلّ صفّ)
    والتفاصيل (كلُّ رصد). التصديرُ والطباعةُ للعرض الحاليّ كلِّه (`export=rows`).
--}}
@extends('client::master')

@section('title')
    تقرير المنافسين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .cp-photos a { display: inline-block; margin-inline-end: 4px; }
        .rpt .cp-photos img { width: 30px; height: 30px; object-fit: cover; border-radius: 6px; border: 1px solid var(--rpt-line); }
        .rpt .cp-path { font-size: 11px; color: var(--rpt-muted); }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
    </style>
@endsection

@section('content')
@php
    $isSummary = $reportView === 'summary';
    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $pill = fn ($model, string $empty) => $model
        ? '<span class="rpt-pill" style="background:'.e($model->color ?: '#6c757d').'; color:#fff">'.e($model->name).'</span>'
        : '<span class="text-muted small">'.e($empty).'</span>';
@endphp

<div class="rpt">
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'binoculars',
        'headTitle' => 'تقرير المنافسين',
        'headSubtitle' => 'المنافسون الذين رصدهم المندوبون عند العملاء — كم مرّة، وعند من، وبكم أصل، وفي أيّ جهة',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.competitors',
        'headGuide' => [
            'title' => 'من أين تأتي هذه الأرقام؟',
            'lead' => 'من قسم «رصد المنافسين» في ملاحظة الزيارة: المندوب يختار المنافس ويكتب عدد أصوله عند العميل وسعره ويرفق صورة.',
            'blocks' => [[
                '«الأصول» عددُ ما للمنافس عند العميل (ثلاجات، استاندات…) كما كتبه المندوب.',
                '«تابع لـ» جهةُ العميل في شجرة الفروع، وفرعُ الشريط العلوي يحصر التقرير كبقية تقارير العملاء.',
            ]],
            'facts' => [],
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ClientReport.competitors') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    @include('client::customers.CustomerReport.partials.period-selector', [
                        'periodContext' => $periodContext ?? [],
                        'startId' => 'from_date',
                        'endId' => 'to_date',
                        'cols' => '',
                        'yearTitle' => 'السنة المالية',
                        'periodTitle' => 'الفترة المحاسبية',
                    ])

                    <div class="rpt-field">
                        <label for="competitor_id">المنافس</label>
                        <select name="competitor_id" id="competitor_id" class="form-control rpt-select2">
                            <option value="">كل المنافسين</option>
                            @foreach ($competitors as $competitor)
                                <option value="{{ $competitor->id }}" @selected((string) request('competitor_id') === (string) $competitor->id)>{{ $competitor->name }}{{ $competitor->is_active ? '' : ' (معطَّل)' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="observer_id">من رصد</label>
                        <select name="observer_id" id="observer_id" class="form-control rpt-select2">
                            <option value="">كل المندوبين</option>
                            @foreach ($observers as $observer)
                                <option value="{{ $observer->id }}" @selected((string) request('observer_id') === (string) $observer->id)>{{ $observer->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', ['name' => 'customer', 'selectedClient' => $selectedClient ?? null, 'label' => 'العميل'])
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة العميل</label>
                        <select name="status" id="status" class="form-control rpt-select2">
                            <option value="">كل الحالات</option>
                            @include('reports.partials.status-filter-options')
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.stage-select', ['registrationStages' => $registrationStages ?? collect(), 'label' => 'وضع العميل'])
                    </div>

                    <div class="rpt-field">
                        <label for="category_client">تصنيف العميل</label>
                        <select name="category_client" id="category_client" class="form-control rpt-select2">
                            <option value="">جميع التصنيفات</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_client') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-list"></i> الملخّص</button>
                            <button type="submit" name="view" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-th-list"></i> كل الأرصاد</button>
                        </div>
                    </div>

                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ClientReport.competitors') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">الأرصاد</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['observations']) }}</bdi></div><div class="ag-kpi__sub">رصدٌ في الفترة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-binoculars"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">المنافسون المرصودون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['competitors']) }}</bdi></div><div class="ag-kpi__sub">منافس</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-bullseye"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">العملاء</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['clients']) }}</bdi></div><div class="ag-kpi__sub">عميلٌ رُصد عنده منافس</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div><div class="ag-kpi__label">أصول المنافسين</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['assets']) }}</bdi></div><div class="ag-kpi__sub">كما كتبها المندوبون</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-boxes"></i></span>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'ملخّص المنافسين' : 'كل الأرصاد' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? number_format($summary->count()).' منافس' : number_format($details->total()).' رصد' }}</span>
            </h4>
            <div class="ag-tools no-print">
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>المنافس</th><th>الأرصاد</th><th>العملاء</th><th>الأصول</th>
                            <th>متوسط السعر</th><th>أكثر جهة رُصد فيها</th><th>عدد الجهات</th><th>آخر رصد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name"><strong>{{ $row['competitor'] }}</strong></td>
                                <td class="rpt-num">{{ number_format($row['observations']) }}</td>
                                <td class="rpt-num">{{ number_format($row['clients']) }}</td>
                                <td class="rpt-num">{{ number_format($row['assets']) }}</td>
                                <td class="rpt-num">{{ $row['avg_price'] !== null ? number_format($row['avg_price'], 2) : '—' }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['top_affiliation'] ?? '—' }}</span></td>
                                <td class="rpt-num">{{ number_format($row['affiliations']) }}</td>
                                <td class="rpt-num">{{ $row['last'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty"><i class="fas fa-inbox"></i> لا أرصاد منافسين في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>التاريخ</th><th>المنافس</th><th>العميل</th><th>الحالة</th><th>الوضع</th>
                            <th>تابع لـ</th><th>الأصول</th><th>السعر</th><th>من رصد</th><th>ملاحظة</th><th>صور</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($details as $row)
                            <tr>
                                <td>{{ $details->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">{{ $row['date'] }}</td>
                                <td><strong>{{ $row['competitor'] }}</strong></td>
                                <td class="rpt-name">
                                    <a href="{{ route('clients.show', $row['client_id']) }}" target="_blank" rel="noopener" class="rpt-client-link">{{ $row['client'] }}</a>
                                    @if ($row['code'] !== '')<span class="text-muted"> - {{ $row['code'] }}</span>@endif
                                </td>
                                <td>{!! $pill($row['status'], 'بدون حالة') !!}</td>
                                <td>{!! $pill($row['stage'], 'بدون وضع') !!}</td>
                                <td>{{ $row['affiliation'] }}<div class="cp-path">{{ $row['path'] }}</div></td>
                                <td class="rpt-num">{{ number_format($row['assets']) }}</td>
                                <td class="rpt-num">{{ $row['price'] !== null ? number_format($row['price'], 2) : '—' }}</td>
                                <td>{{ $row['observer'] }}</td>
                                <td style="max-width:220px; white-space:normal">{{ $row['notes'] ?: '—' }}</td>
                                <td class="cp-photos">
                                    @forelse ($row['photos'] as $photo)
                                        <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="صورة الرصد" loading="lazy"></a>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لا أرصاد منافسين في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if (! $isSummary && $details->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $details->firstItem() }} إلى {{ $details->lastItem() }} من {{ number_format($details->total()) }} رصد</small>
                {{ $details->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('reports.partials.fetch-rows-tools', [
        'frTitle' => $isSummary ? 'تقرير المنافسين — الملخّص' : 'تقرير المنافسين — كل الأرصاد',
        'frInfo' => $periodLabel,
        'frHeaders' => $isSummary
            ? ['#', 'المنافس', 'الأرصاد', 'العملاء', 'الأصول', 'متوسط السعر', 'أكثر جهة رُصد فيها', 'عدد الجهات', 'آخر رصد']
            : ['#', 'التاريخ', 'المنافس', 'العميل', 'الحالة', 'الوضع', 'تابع لـ', 'الأصول', 'السعر', 'من رصد', 'ملاحظة'],
    ])
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
                $('.rpt-select2').select2({ dir: 'rtl', allowClear: true, width: '100%', placeholder: 'اختر...' });
            }
        });
    </script>
@endsection
