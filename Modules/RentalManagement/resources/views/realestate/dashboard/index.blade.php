@extends('rentalmanagement::realestate.master')

@section('title', 'لوحة تحكم العقارات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (canAny(['realestate.buildings.create', 'realestate.units.create', 'realestate.contracts.create', 'realestate.maintenance.create', 'realestate.payments.create', 'realestate.listings.create']))
                <div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="feather icon-plus"></i> جديد
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @permission('realestate.buildings.create')
                            <a class="dropdown-item" href="{{ route('realestate.buildings.create') }}"><i class="feather icon-home"></i> إضافة عقار</a>
                        @endpermission
                        @permission('realestate.units.create')
                            <a class="dropdown-item" href="{{ route('realestate.units.create') }}"><i class="feather icon-grid"></i> إضافة وحدة</a>
                        @endpermission
                        @permission('realestate.listings.create')
                            <a class="dropdown-item" href="{{ route('realestate.listings.create') }}"><i class="feather icon-tag"></i> عرض عقاري</a>
                        @endpermission
                        @permission('realestate.contracts.create')
                            <a class="dropdown-item" href="{{ route('realestate.contracts.create') }}"><i class="feather icon-file-text"></i> إنشاء عقد</a>
                        @endpermission
                        @permission('realestate.payments.create')
                            <a class="dropdown-item" href="{{ route('realestate.payments.create') }}"><i class="feather icon-credit-card"></i> تسجيل دفعة</a>
                        @endpermission
                        @permission('realestate.maintenance.create')
                            <a class="dropdown-item" href="{{ route('realestate.maintenance.create') }}"><i class="feather icon-tool"></i> طلب صيانة</a>
                        @endpermission
                    </div>
                </div>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => 'أهلاً '.auth()->user()->name,
            'icon' => 'home',
            'sub' => \Carbon\Carbon::now()->translatedFormat('l، d F Y').' — نظرة عامّة على المحفظة العقارية',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ماذا ندير؟ ما المتاح؟ --}}
        @include('rentalmanagement::realestate.components.kpis', ['items' => array_values(array_filter([
            ['label' => 'العقارات', 'value' => number_format($kpis['buildings']), 'icon' => 'home', 'tone' => 'info',
                'url' => hasPermission('realestate.buildings.view') ? route('realestate.buildings.index') : null],
            ['label' => 'الوحدات', 'value' => number_format($kpis['units']), 'icon' => 'grid', 'tone' => 'info',
                'url' => hasPermission('realestate.units.view') ? route('realestate.units.index') : null],
            ['label' => 'مؤجرة', 'value' => number_format($kpis['rented']), 'icon' => 'key', 'tone' => 'success',
                'url' => hasPermission('realestate.units.view') ? route('realestate.units.index', ['occupancy_status' => 'rented']) : null],
            ['label' => 'شاغرة', 'value' => number_format($kpis['vacant']), 'icon' => 'unlock', 'tone' => $kpis['vacant'] ? 'warning' : 'success',
                'url' => hasPermission('realestate.units.view') ? route('realestate.units.index', ['occupancy_status' => 'vacant']) : null],
            ['label' => 'نسبة الإشغال', 'value' => $kpis['occupancy'].'%', 'icon' => 'pie-chart', 'tone' => $kpis['occupancy'] >= 85 ? 'success' : ($kpis['occupancy'] >= 60 ? 'warning' : 'danger')],
            $full ? ['label' => 'قيمة العقود السارية (سنوياً)', 'value' => $money($kpis['active_value']), 'icon' => 'file-text', 'tone' => 'primary',
                'note' => $kpis['active_contracts'].' عقداً سارياً',
                'url' => hasPermission('realestate.contracts.view') ? route('realestate.contracts.index', ['status' => 'active']) : null] : null,
        ]))])

        @unless ($full)
            <div class="alert alert-info">تظهر لك المؤشرات العامة فقط — تفاصيل التحصيل والتنبيهات تتطلّب صلاحية «عرض لوحة تحكم العقارات».</div>
        @endunless

        @if ($full)
            <div class="row">
                {{-- ماذا يجب تحصيله؟ --}}
                @if ($showMoney)
                    <div class="col-lg-4 col-12">
                        <div class="card h-100">
                            <div class="card-header"><h4 class="card-title"><i class="feather icon-credit-card"></i> تحصيل هذا الشهر</h4></div>
                            <div class="card-body">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>مستحق الفترة</dt><dd class="stk-num">{{ $money($collection['due']) }}</dd></div>
                                    <div class="stk-def__row"><dt>المحصَّل (دفعات مرحّلة)</dt><dd class="stk-num text-success">{{ $money($collection['collected']) }}</dd></div>
                                    <div class="stk-def__row">
                                        <dt>المتأخرات حتى اليوم</dt>
                                        <dd class="stk-num {{ $collection['overdue'] > 0 ? 'text-danger' : '' }}">
                                            @permission('realestate.payments.schedules')
                                                <a href="{{ route('realestate.payments.schedules', ['bucket' => 'overdue']) }}" class="{{ $collection['overdue'] > 0 ? 'text-danger' : '' }}">{{ $money($collection['overdue']) }}</a>
                                            @else
                                                {{ $money($collection['overdue']) }}
                                            @endpermission
                                        </dd>
                                    </div>
                                </dl>
                                <div class="d-flex justify-content-between mt-1 mb-50"><small>نسبة تحصيل مستحق الشهر</small><small class="stk-num">{{ $collection['rate'] }}%</small></div>
                                <div class="re-progress"><span style="width:{{ min(100, $collection['rate']) }}%"></span></div>
                                <small class="text-muted d-block mt-1">النسبة = المسدَّد من استحقاقات هذا الشهر ÷ إجماليها. «المحصَّل» يشمل ما قُبض هذا الشهر عن أيّ استحقاق.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="card h-100">
                            <div class="card-header"><h4 class="card-title"><i class="feather icon-trending-up"></i> المحصَّل — آخر 6 أشهر</h4></div>
                            <div class="card-body"><div id="re-trend-chart" style="min-height:230px"></div></div>
                        </div>
                    </div>
                @endif

                {{-- حالة الوحدات --}}
                <div class="col-lg-{{ $showMoney ? 4 : 12 }} col-12">
                    <div class="card h-100">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-grid"></i> حالة الوحدات</h4></div>
                        <div class="card-body">
                            @foreach (V::OCCUPANCY as $status => $meta)
                                @php $count = (int) ($byStatus[$status] ?? 0); $pct = $kpis['units'] ? round($count / $kpis['units'] * 100) : 0; @endphp
                                <div class="mb-1">
                                    <div class="d-flex justify-content-between">
                                        <span><span class="badge badge-light-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></span>
                                        <span class="stk-num">{{ $count }} <small class="text-muted">({{ $pct }}%)</small></span>
                                    </div>
                                    <div class="re-progress mt-50"><span class="bg-{{ $meta['tone'] }}" style="width:{{ $pct }}%"></span></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- أين عقاراتي؟ --}}
            @if ($mapPoints->isNotEmpty())
                <div class="card mt-2">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-map"></i> كل العقارات المسجَّلة</h4>
                        <div class="d-flex align-items-center" style="gap:.75rem">
                            @if ($mapMissing > 0)
                                <small class="text-muted">{{ number_format($mapMissing) }} عقار بلا موقع</small>
                            @endif
                            <a href="{{ route('realestate.buildings.map') }}" class="btn btn-sm btn-outline-primary"><i class="feather icon-maximize"></i> الخريطة الكاملة</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="dashMap" style="height:360px;border-radius:.5rem;border:1px solid rgba(0,0,0,.1)"></div>
                        <p class="text-danger d-none mt-1 mb-0" id="dashMapError">تعذّر تحميل الخريطة — تحقّق من اتصال الإنترنت.</p>
                    </div>
                </div>
            @endif

            {{-- ما الذي يحتاج إجراءً الآن؟ --}}
            <div class="card mt-2">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-alert-triangle"></i> تحتاج انتباهك</h4>
                    <small class="text-muted">تُشتقّ آلياً من العقود والدفعات والعروض والصيانة</small>
                </div>
                <div class="card-body">
                    @if (empty($attention))
                        @include('rentalmanagement::realestate.components.empty', ['icon' => 'check-circle', 'title' => 'لا شيء يحتاج تدخّلاً', 'hint' => 'لا دفعات متأخرة ولا عقود مهمَلة ولا صيانة متجاوزة لموعدها'])
                    @else
                        <div class="re-attention">
                            @foreach ($attention as $item)
                                <div class="re-attention__item">
                                    <div class="re-attention__head">
                                        <span><i class="feather icon-{{ $item['icon'] }} text-{{ $item['tone'] }}"></i> {{ $item['label'] }}</span>
                                        <a href="{{ $item['url'] }}" class="badge badge-light-{{ $item['tone'] }}">{{ $item['count'] }}</a>
                                    </div>
                                    <ul class="re-attention__rows">
                                        @foreach ($item['rows'] as $row)
                                            <li>
                                                @switch($item['key'])
                                                    @case('overdue') {{ $row->contract->tenant->display_name ?? '—' }} — {{ $money($row->balance, $row->contract->currency ?? null) }} منذ {{ $row->due_date->format('Y-m-d') }} @break
                                                    @case('expiring') {{ $row->code }} — {{ $row->tenant->display_name ?? '—' }} — بعد {{ max(0, $row->days_left) }} يوماً @break
                                                    @case('pending') {{ $row->code }} — {{ $row->tenant->display_name ?? '—' }} @break
                                                    @case('maintenance') {{ $row->code }} — {{ $row->title }} @break
                                                    @case('listings') {{ $row->code }} — {{ $row->title }} @break
                                                    @case('vacant') {{ $row->full_label }} — منذ {{ $row->vacant_since?->format('Y-m-d') }} @break
                                                @endswitch
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="row">
                {{-- العقود القريبة --}}
                <div class="col-lg-7 col-12">
                    <div class="card stk-table-card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> عقود تنتهي قريباً <span class="stk-count">{{ $expiring->count() }}</span></h6>
                        </div>
                        @if ($expiring->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا عقود قريبة الانتهاء', 'hint' => 'ضمن مدّة التنبيه المضبوطة في الإعدادات'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العقد</th><th>المستأجر</th><th>الوحدة</th><th>الانتهاء</th><th class="text-end">القيمة</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach ($expiring as $contract)
                                            <tr>
                                                <td><a href="{{ route('realestate.contracts.show', $contract->id) }}">{{ $contract->code }}</a></td>
                                                <td>{{ $contract->tenant->display_name ?? '—' }}</td>
                                                <td>{{ $contract->unit->full_label ?? '—' }}</td>
                                                <td><span class="badge badge-light-{{ $contract->days_left <= 15 ? 'danger' : 'warning' }}">{{ $contract->end_date->format('Y-m-d') }} · {{ max(0, $contract->days_left) }} يوماً</span></td>
                                                <td class="text-end re-num">{{ $money($contract->annual_value, $contract->currency) }}</td>
                                                <td class="text-end">
                                                    @permission('realestate.contracts.renew')
                                                        @if ($contract->status === 'active')
                                                            <a href="{{ route('realestate.contracts.renew', $contract->id) }}" class="btn btn-sm btn-outline-primary">تجديد</a>
                                                        @endif
                                                    @endpermission
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- العروض --}}
                <div class="col-lg-5 col-12">
                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-tag"></i> العروض العقارية</h4></div>
                        <div class="card-body">
                            <div class="stk-metrics">
                                @foreach ([['نشطة', $listings['active'], 'check-circle'], ['للإيجار', $listings['rent'], 'key'], ['للبيع', $listings['sale'], 'dollar-sign'], ['طلبات نشطة', $listings['requests'] ?? 0, 'search'],
                                    ['استفسارات', $listings['inquiries'], 'message-circle'], ['معاينات', $listings['viewings'], 'eye'], ['تحوّلت لعقد', $listings['converted'], 'file-text']] as [$label, $value, $icon])
                                    <div class="stk-metric">
                                        <span class="stk-metric__icon"><i class="feather icon-{{ $icon }}"></i></span>
                                        <span class="stk-metric__value stk-num">{{ number_format($value) }}</span>
                                        <span class="stk-metric__label">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- أداء العقارات --}}
            @if ($performance->isNotEmpty())
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أداء العقارات</h6>
                        @if (hasPermission('realestate.reports.view'))
                            <a href="{{ route('realestate.reports.show', 'occupancy') }}" class="btn btn-sm btn-outline-secondary">التقرير الكامل</a>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>العقار</th><th class="text-center">الوحدات</th><th style="width:26%">الإشغال</th>@if ($showMoney)<th class="text-end">قيمة العقود السارية</th>@endif<th class="text-center">صيانة مفتوحة</th></tr></thead>
                            <tbody>
                                @foreach ($performance as $building)
                                    @php $rate = $building->units_count ? round($building->rented_count / $building->units_count * 100) : 0; @endphp
                                    <tr>
                                        <td><a href="{{ route('realestate.buildings.show', $building->id) }}">{{ $building->name }}</a></td>
                                        <td class="text-center stk-num">{{ $building->units_count }}</td>
                                        <td>
                                            <div class="d-flex align-items-center" style="gap:.5rem">
                                                <div class="re-progress flex-grow-1"><span style="width:{{ $rate }}%"></span></div>
                                                <small class="stk-num">{{ $rate }}%</small>
                                            </div>
                                        </td>
                                        @if ($showMoney)<td class="text-end re-num">{{ $money($building->active_value) }}</td>@endif
                                        <td class="text-center">
                                            @if ($building->open_maintenance)
                                                <span class="badge badge-light-warning">{{ $building->open_maintenance }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection

@section('scripts')
    @if ($mapPoints->isNotEmpty())
        @include('rentalmanagement::realestate.components.map-provider')
        <script>
            const dashPoints = @json($mapPoints);

            reMap.init(function (api) {
                const map = api.map(document.getElementById('dashMap'), { center: dashPoints[0], zoom: 11 });
                const markers = {};

                function card(p) {
                    const $card = $('<div dir="rtl" style="min-width:200px;text-align:right"></div>');
                    $card.append($('<strong class="d-block"></strong>').text(p.name), $('<small class="text-muted d-block"></small>').text(p.code));
                    if (p.address) $card.append($('<div class="mt-25"></div>').text(p.address));
                    $card.append($('<div class="mt-25"></div>').text('الوحدات: ' + p.units + ' · الشاغرة: ' + p.vacant));
                    if (p.url) $card.append($('<div class="mt-50"></div>').append($('<a></a>').attr('href', p.url).text('فتح العقار')));

                    return $card[0];
                }

                dashPoints.forEach(function (p) {
                    markers[p.id] = map.marker(p, { title: p.name });
                    markers[p.id].onClick(() => markers[p.id].popup(card(p)));
                });

                dashPoints.length === 1 ? map.setView(dashPoints[0], 15) : map.fit(dashPoints);
            }, function () {
                $('#dashMap').hide();
                $('#dashMapError').removeClass('d-none');
            });
        </script>
    @endif

    @if ($full && $showMoney && $trend->isNotEmpty())
        <script>
            $(function () {
                if (typeof ApexCharts === 'undefined') return;

                new ApexCharts(document.querySelector('#re-trend-chart'), {
                    chart: { type: 'bar', height: 230, toolbar: { show: false }, fontFamily: 'inherit' },
                    series: [{ name: 'المحصَّل', data: @json($trend->pluck('collected')) }],
                    xaxis: { categories: @json($trend->pluck('label')) },
                    yaxis: { labels: { formatter: v => Number(v).toLocaleString('en-US') } },
                    plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                    dataLabels: { enabled: false },
                    colors: [getComputedStyle(document.documentElement).getPropertyValue('--app-primary').trim() || '#7367f0'],
                    tooltip: { y: { formatter: v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) } },
                }).render();
            });
        </script>
    @endif
@endsection
