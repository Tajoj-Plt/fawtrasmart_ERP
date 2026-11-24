@extends('ecosystem::master')

@section('title', 'تقرير المستويات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير المستويات والترقيات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير المستويات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <button class="btn btn-outline-secondary waves-effect waves-light" onclick="window.print()">
                <i class="feather icon-printer"></i> طباعة
            </button>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- فلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.reports.tiers') }}">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>إلى تاريخ</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="employee" {{ request('user_type') == 'employee' ? 'selected' : '' }}>موظف</option>
                                        <option value="client" {{ request('user_type') == 'client' ? 'selected' : '' }}>عميل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- إحصائيات الترقيات -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-layers"></i></div>
                <div class="stat-label">إجمالي المستويات</div>
                <div class="stat-value">{{ $tiers->count() }}</div>
                <div class="stat-trend up"><i class="feather icon-grid"></i> <span>مستوى معرّف</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">إجمالي الترقيات</div>
                <div class="stat-value">{{ number_format($upgradeStats->total_upgrades ?? 0) }}</div>
                <div class="stat-trend up"><i class="feather icon-arrow-up"></i> <span>ترقية</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-zap"></i></div>
                <div class="stat-label">ترقيات تلقائية</div>
                <div class="stat-value">{{ number_format($upgradeStats->auto_upgrades ?? 0) }}</div>
                <div class="stat-trend up"><i class="feather icon-cpu"></i> <span>تلقائي</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-edit"></i></div>
                <div class="stat-label">ترقيات يدوية</div>
                <div class="stat-value">{{ number_format($upgradeStats->manual_upgrades ?? 0) }}</div>
                <div class="stat-trend up"><i class="feather icon-user"></i> <span>يدوي</span></div>
            </div>
        </div>

        <div class="row">
            <!-- توزيع المستويات -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> توزيع المستويات</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="tierDistributionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اتجاه الترقيات الشهري -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-bar-chart-2"></i> اتجاه الترقيات الشهري</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="monthlyUpgradesChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تفاصيل كل مستوى -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> تفاصيل المستويات</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المستوى</th>
                                <th>نوع المستخدم</th>
                                <th>نطاق النقاط</th>
                                <th>المضاعف</th>
                                <th>الخصم</th>
                                <th>عدد الموظفين</th>
                                <th>عدد العملاء</th>
                                <th>الترقيات إليه</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tiers as $tier)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($tier->icon)
                                                <span class="mr-1" style="font-size: 1.3rem;">{{ $tier->icon }}</span>
                                            @endif
                                            <span style="color: {{ $tier->color ?? '#333' }}; font-weight: 600;">{{ $tier->name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $tier->user_type?->value === 'client' ? 'primary' : 'success' }}">
                                            {{ $tier->user_type?->label() ?? '-' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($tier->min_points) }} - {{ $tier->max_points ? number_format($tier->max_points) : '∞' }}</td>
                                    <td><span class="badge badge-light-warning">{{ $tier->points_multiplier }}x</span></td>
                                    <td>{{ $tier->discount_percentage ?? 0 }}%</td>
                                    <td>
                                        <span class="badge badge-light-success">{{ $employeeTierDistribution[$tier->id] ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">{{ $clientTierDistribution[$tier->id] ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info">{{ $upgradesPerTier[$tier->id] ?? 0 }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- آخر الترقيات -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-clock"></i> آخر 20 ترقية</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>من مستوى</th>
                                <th>إلى مستوى</th>
                                <th>النوع</th>
                                <th>النقاط عند الترقية</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUpgrades as $upgrade)
                                <tr>
                                    <td>
                                        @if($upgrade->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $upgrade->employee->full_name }}
                                        @elseif($upgrade->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $upgrade->client->full_name ?? $upgrade->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span style="color: {{ $upgrade->oldTier?->color ?? '#999' }};">
                                            {{ $upgrade->oldTier?->icon }} {{ $upgrade->oldTier?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: {{ $upgrade->newTier?->color ?? '#333' }}; font-weight: 600;">
                                            {{ $upgrade->newTier?->icon }} {{ $upgrade->newTier?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $upgrade->upgrade_type === 'automatic' ? 'info' : 'warning' }}">
                                            {{ $upgrade->upgrade_type === 'automatic' ? 'تلقائي' : 'يدوي' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($upgrade->points_at_upgrade ?? 0) }}</td>
                                    <td><small>{{ $upgrade->created_at->format('Y-m-d H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 text-muted">لا توجد ترقيات</p>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(function() {
    var tiers = @json($tiers);
    var empDist = @json($employeeTierDistribution);
    var clientDist = @json($clientTierDistribution);

    // Tier Distribution Chart
    if (tiers.length > 0) {
        var labels = tiers.map(t => t.name);
        var empData = tiers.map(t => empDist[t.id] || 0);
        var clientData = tiers.map(t => clientDist[t.id] || 0);
        var tierColors = tiers.map(t => t.color || '#7367f0');

        new Chart(document.getElementById('tierDistributionChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'موظفين',
                        data: empData,
                        backgroundColor: 'rgba(40, 199, 111, 0.7)',
                        borderColor: '#28c76f',
                        borderWidth: 1
                    },
                    {
                        label: 'عملاء',
                        data: clientData,
                        backgroundColor: 'rgba(115, 103, 240, 0.7)',
                        borderColor: '#7367f0',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', rtl: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Monthly Upgrades Chart
    var monthlyData = @json($monthlyUpgrades);
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyUpgradesChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: 'عدد الترقيات',
                    data: monthlyData.map(d => d.count),
                    borderColor: '#ff9f43',
                    backgroundColor: 'rgba(255, 159, 67, 0.15)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', rtl: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});
</script>
@endsection
