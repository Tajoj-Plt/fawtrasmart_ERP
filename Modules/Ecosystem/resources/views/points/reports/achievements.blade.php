@extends('ecosystem::master')

@section('title', 'تقرير الإنجازات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير الإنجازات والشارات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير الإنجازات</li>
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
                <h4 class="card-title"><i class="fa fa-filter"></i> فلاتر متقدمة</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.reports.achievements') }}">
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>التصنيف</label>
                                    <select name="category" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
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

        <!-- إحصائيات -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-award"></i></div>
                <div class="stat-label">إجمالي الفتح</div>
                <div class="stat-value">{{ number_format($stats['total_unlocked']) }}</div>
                <div class="stat-trend up"><i class="feather icon-unlock"></i> <span>إنجاز تم فتحه</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">النقاط الممنوحة</div>
                <div class="stat-value">{{ number_format($stats['total_points_awarded']) }}</div>
                <div class="stat-trend up"><i class="feather icon-gift"></i> <span>نقطة مكافأة</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-user"></i></div>
                <div class="stat-label">موظفين حققوا</div>
                <div class="stat-value">{{ number_format($stats['unique_employees']) }}</div>
                <div class="stat-trend up"><i class="feather icon-users"></i> <span>+ {{ $stats['unique_clients'] }} عميل</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-target"></i></div>
                <div class="stat-label">إنجازات نشطة</div>
                <div class="stat-value">{{ $stats['active_achievements'] }} / {{ $stats['total_achievements'] }}</div>
                <div class="stat-trend up"><i class="feather icon-check"></i> <span>إنجاز متاح</span></div>
            </div>
        </div>

        <div class="row">
            <!-- الإنجازات حسب التصنيف -->
            <div class="col-md-5">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> الإنجازات حسب التصنيف</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اتجاه الفتح الشهري -->
            <div class="col-md-7">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> اتجاه الفتح الشهري</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="monthlyUnlocksChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- أكثر الإنجازات تحقيقاً -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> أكثر الإنجازات تحقيقاً</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الإنجاز</th>
                                        <th>عدد الفتح</th>
                                        <th>النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topAchievements as $i => $item)
                                        <tr>
                                            <td>
                                                @if($i < 3)
                                                    <span class="badge badge-{{ ['warning','light-primary','light-info'][$i] }}">{{ $i + 1 }}</span>
                                                @else
                                                    {{ $i + 1 }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($item->achievement)
                                                    <span class="mr-1">{{ $item->achievement->icon }}</span>
                                                    <strong>{{ $item->achievement->name }}</strong>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ number_format($item->unlock_count) }}</td>
                                            <td><span class="badge badge-light-success">{{ number_format($item->total_points) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">لا توجد بيانات</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أصعب الإنجازات -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-lock"></i> الإنجازات الأصعب (الأقل تحقيقاً)</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>الإنجاز</th>
                                        <th>التصنيف</th>
                                        <th>النقاط</th>
                                        <th>عدد المحققين</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($hardestAchievements as $ach)
                                        <tr>
                                            <td>
                                                <span class="mr-1">{{ $ach->icon }}</span>
                                                <strong>{{ $ach->name }}</strong>
                                                @if($ach->is_hidden) <span class="badge badge-light-secondary">سري</span> @endif
                                            </td>
                                            <td><span class="badge badge-light-info">{{ $ach->category ?? '-' }}</span></td>
                                            <td><span class="badge badge-light-warning">+{{ number_format($ach->bonus_points) }}</span></td>
                                            <td>
                                                <span class="badge badge-light-{{ $ach->user_achievements_count > 0 ? 'success' : 'danger' }}">
                                                    {{ $ach->user_achievements_count }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">لا توجد بيانات</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- أفضل الموظفين في الإنجازات -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-users"></i> أفضل الموظفين في الإنجازات</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الموظف</th>
                                <th>عدد الإنجازات</th>
                                <th>النقاط المكتسبة</th>
                                <th>شريط</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $maxAch = $topEmployeeAchievers->max('achievements_count') ?: 1; @endphp
                            @forelse($topEmployeeAchievers as $i => $item)
                                <tr>
                                    <td>
                                        @if($i < 3)
                                            <span class="badge badge-{{ ['warning','light-primary','light-info'][$i] }}" style="font-size: 1.1rem;">
                                                {{ ['🥇','🥈','🥉'][$i] }}
                                            </span>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </td>
                                    <td><strong>{{ $item->employee?->full_name ?? '-' }}</strong></td>
                                    <td><span class="badge badge-light-primary">{{ $item->achievements_count }}</span></td>
                                    <td><span class="badge badge-light-success">{{ number_format($item->total_points) }}</span></td>
                                    <td style="width: 200px;">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: {{ ($item->achievements_count / $maxAch) * 100 }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">لا توجد بيانات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- آخر الإنجازات المفتوحة -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-clock"></i> آخر 20 إنجاز تم فتحه</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>الإنجاز</th>
                                <th>المستخدم</th>
                                <th>النقاط</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUnlocks as $unlock)
                                <tr>
                                    <td>
                                        <span class="mr-1">{{ $unlock->achievement?->icon }}</span>
                                        <strong>{{ $unlock->achievement?->name ?? '-' }}</strong>
                                    </td>
                                    <td>
                                        @if($unlock->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $unlock->employee->full_name }}
                                        @elseif($unlock->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $unlock->client->full_name ?? $unlock->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="badge badge-light-success">+{{ number_format($unlock->points_awarded) }}</span></td>
                                    <td><small>{{ $unlock->achieved_at ? \Carbon\Carbon::parse($unlock->achieved_at)->format('Y-m-d H:i') : '-' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 text-muted">لا توجد إنجازات</p>
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
    // Category Chart
    var catData = @json($achievementsByCategory);
    var colors = ['#7367f0','#28c76f','#ff9f43','#00cfe8','#ea5455','#b8c2cc','#82868b','#5a8dee'];
    if (Object.keys(catData).length > 0) {
        new Chart(document.getElementById('categoryChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(catData),
                datasets: [{
                    data: Object.values(catData),
                    backgroundColor: colors.slice(0, Object.keys(catData).length)
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', rtl: true } }
            }
        });
    }

    // Monthly Unlocks Chart
    var monthlyData = @json($monthlyUnlocks);
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyUnlocksChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'عدد الفتح',
                        data: monthlyData.map(d => d.count),
                        backgroundColor: 'rgba(115, 103, 240, 0.7)',
                        borderColor: '#7367f0',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'النقاط الممنوحة',
                        data: monthlyData.map(d => d.points),
                        type: 'line',
                        borderColor: '#28c76f',
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', rtl: true } },
                scales: {
                    y: { beginAtZero: true, position: 'right' },
                    y1: { beginAtZero: true, position: 'left', grid: { drawOnChartArea: false } }
                }
            }
        });
    }
});
</script>
@endsection
