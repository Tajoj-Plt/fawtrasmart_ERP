@extends('ecosystem::master')

@section('title', 'تقرير المكافآت')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير المكافآت والاستبدال</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير المكافآت</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.rewards') }}">
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
                                    <label>نوع المكافأة</label>
                                    <select name="reward_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($rewardTypes as $type)
                                            <option value="{{ $type }}" {{ request('reward_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلق</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>موافق عليه</option>
                                        <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>تم التسليم</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
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

        <!-- إحصائيات -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-gift"></i></div>
                <div class="stat-label">إجمالي الطلبات</div>
                <div class="stat-value">{{ number_format($stats['total_redemptions']) }}</div>
                <div class="stat-trend up"><i class="feather icon-shopping-bag"></i> <span>طلب استبدال</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">تم التسليم</div>
                <div class="stat-value">{{ number_format($stats['delivered_count']) }}</div>
                <div class="stat-trend up"><i class="feather icon-truck"></i> <span>مكافأة مسلّمة</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">قيد الانتظار</div>
                <div class="stat-value">{{ number_format($stats['pending_count']) }}</div>
                <div class="stat-trend up"><i class="feather icon-loader"></i> <span>معلق</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                <div class="stat-label">ملغي</div>
                <div class="stat-value">{{ number_format($stats['cancelled_count']) }}</div>
                <div class="stat-trend up"><i class="feather icon-slash"></i> <span>ملغي</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">النقاط المستخدمة</div>
                <div class="stat-value">{{ number_format($stats['total_points_used']) }}</div>
                <div class="stat-trend up"><i class="feather icon-minus-circle"></i> <span>نقطة</span></div>
            </div>
        </div>

        <div class="row">
            <!-- توزيع الحالات -->
            <div class="col-md-4">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> توزيع الحالات</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اتجاه الاستبدال اليومي -->
            <div class="col-md-8">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> اتجاه الاستبدال اليومي</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="dailyRedemptionsChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- أكثر المكافآت طلباً -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> أكثر المكافآت طلباً</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المكافأة</th>
                                <th>عدد الطلبات</th>
                                <th>النقاط المستخدمة</th>
                                <th>شريط التقدم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topRewards as $i => $item)
                                @php $maxCount = $topRewards->max('redemption_count') ?: 1; @endphp
                                <tr>
                                    <td>
                                        @if($i < 3)
                                            <span class="badge badge-{{ ['warning','light-primary','light-info'][$i] }}">{{ $i + 1 }}</span>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </td>
                                    <td><strong>{{ $item->reward?->name ?? '-' }}</strong></td>
                                    <td>{{ number_format($item->redemption_count) }}</td>
                                    <td><span class="badge badge-light-info">{{ number_format($item->total_points) }}</span></td>
                                    <td style="width: 200px;">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: {{ ($item->redemption_count / $maxCount) * 100 }}%"></div>
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

        <!-- مخزون المكافآت -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-package"></i> مخزون المكافآت وحالتها</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المكافأة</th>
                                <th>النوع</th>
                                <th>النقاط المطلوبة</th>
                                <th>الكمية المتاحة</th>
                                <th>عدد الاستبدالات</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rewardAvailability as $reward)
                                <tr>
                                    <td><strong>{{ $reward->name }}</strong></td>
                                    <td><span class="badge badge-light-info">{{ $reward->reward_type }}</span></td>
                                    <td>{{ number_format($reward->points_required) }}</td>
                                    <td>
                                        @if($reward->quantity_available !== null)
                                            <span class="badge badge-light-{{ $reward->quantity_available > 10 ? 'success' : ($reward->quantity_available > 0 ? 'warning' : 'danger') }}">
                                                {{ $reward->quantity_available }}
                                            </span>
                                        @else
                                            <span class="text-muted">غير محدود</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($reward->redemptions_count) }}</td>
                                    <td>
                                        <span class="badge-modern {{ $reward->is_active ? 'active' : 'inactive' }}">
                                            <i class="feather icon-{{ $reward->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                            {{ $reward->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- آخر الاستبدالات -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-clock"></i> آخر 20 عملية استبدال</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>المستخدم</th>
                                <th>المكافأة</th>
                                <th>النقاط</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRedemptions as $red)
                                @php
                                    $statusColors = ['pending' => 'warning', 'approved' => 'info', 'processing' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger'];
                                    $statusLabels = ['pending' => 'معلق', 'approved' => 'موافق عليه', 'processing' => 'قيد التجهيز', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'];
                                    $redStatus = $red->status instanceof \BackedEnum ? $red->status->value : ($red->status ?? 'pending');
                                @endphp
                                <tr>
                                    <td><code>{{ $red->redemption_number ?? $red->id }}</code></td>
                                    <td>
                                        @if($red->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $red->employee->full_name }}
                                        @elseif($red->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $red->client->full_name ?? $red->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $red->reward?->name ?? '-' }}</td>
                                    <td><span class="badge badge-light-info">{{ number_format($red->points_used) }}</span></td>
                                    <td><span class="badge badge-light-{{ $statusColors[$redStatus] ?? 'secondary' }}">{{ $statusLabels[$redStatus] ?? $redStatus }}</span></td>
                                    <td><small>{{ $red->created_at->format('Y-m-d H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 text-muted">لا توجد عمليات استبدال</p>
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
    // Status Chart
    var statusData = @json($redemptionsByStatus);
    var statusLabels = { pending: 'معلق', approved: 'موافق', processing: 'قيد التجهيز', delivered: 'تم التسليم', cancelled: 'ملغي' };
    var statusColors = { pending: '#ff9f43', approved: '#00cfe8', processing: '#7367f0', delivered: '#28c76f', cancelled: '#ea5455' };

    if (Object.keys(statusData).length > 0) {
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statusData).map(k => statusLabels[k] || k),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: Object.keys(statusData).map(k => statusColors[k] || '#b8c2cc')
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', rtl: true } }
            }
        });
    }

    // Daily Redemptions Chart
    var dailyData = @json($dailyRedemptions);
    if (dailyData.length > 0) {
        new Chart(document.getElementById('dailyRedemptionsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [
                    {
                        label: 'عدد الطلبات',
                        data: dailyData.map(d => d.count),
                        backgroundColor: 'rgba(115, 103, 240, 0.7)',
                        borderColor: '#7367f0',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'النقاط المستخدمة',
                        data: dailyData.map(d => d.points),
                        type: 'line',
                        borderColor: '#ff9f43',
                        backgroundColor: 'rgba(255, 159, 67, 0.1)',
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
