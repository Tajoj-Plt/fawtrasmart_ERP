@extends('ecosystem::master')

@section('title', 'تقرير انتهاء الصلاحية')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير انتهاء صلاحية النقاط</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير الصلاحية</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.expiry') }}">
                        <div class="row">
                            <div class="col-md-3">
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
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>أيام للانتهاء</label>
                                    <select name="days_to_expiry" class="form-control">
                                        <option value="7" {{ $daysToExpiry == 7 ? 'selected' : '' }}>7 أيام</option>
                                        <option value="14" {{ $daysToExpiry == 14 ? 'selected' : '' }}>14 يوم</option>
                                        <option value="30" {{ $daysToExpiry == 30 ? 'selected' : '' }}>30 يوم</option>
                                        <option value="60" {{ $daysToExpiry == 60 ? 'selected' : '' }}>60 يوم</option>
                                        <option value="90" {{ $daysToExpiry == 90 ? 'selected' : '' }}>90 يوم</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                                        <a href="{{ route('ecosystem.points.reports.expiry') }}" class="btn btn-outline-secondary"><i class="feather icon-refresh-cw"></i></a>
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
                <div class="stat-icon"><i class="feather icon-package"></i></div>
                <div class="stat-label">دفعات نشطة</div>
                <div class="stat-value">{{ number_format($stats['total_active_batches']) }}</div>
                <div class="stat-trend up"><i class="feather icon-database"></i> <span>دفعة</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">نقاط متبقية</div>
                <div class="stat-value">{{ number_format($stats['total_remaining_points']) }}</div>
                <div class="stat-trend up"><i class="feather icon-check"></i> <span>نقطة صالحة</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-alert-triangle"></i></div>
                <div class="stat-label">تنتهي قريباً</div>
                <div class="stat-value">{{ number_format($stats['expiring_soon']) }}</div>
                <div class="stat-trend up"><i class="feather icon-clock"></i> <span>{{ number_format($stats['expiring_soon_points']) }} نقطة</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-x-octagon"></i></div>
                <div class="stat-label">منتهية الصلاحية</div>
                <div class="stat-value">{{ number_format($stats['expired_total']) }}</div>
                <div class="stat-trend up"><i class="feather icon-trash"></i> <span>{{ number_format($stats['expired_points_total']) }} نقطة</span></div>
            </div>
        </div>

        <!-- جدول الانتهاء حسب الشهر -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-bar-chart-2"></i> الانتهاء المتوقع حسب الشهر</h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <canvas id="expiryByMonthChart" height="100"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>الشهر</th>
                                <th>عدد الدفعات</th>
                                <th>النقاط المتبقية</th>
                                <th>شريط</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $maxMonthPoints = $expiryByMonth->max('remaining_points') ?: 1; @endphp
                            @forelse($expiryByMonth as $month)
                                <tr>
                                    <td><strong>{{ $month->month }}</strong></td>
                                    <td><span class="badge badge-light-info">{{ $month->count }}</span></td>
                                    <td><span class="badge badge-light-warning">{{ number_format($month->remaining_points) }}</span></td>
                                    <td style="width: 200px;">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: {{ ($month->remaining_points / $maxMonthPoints) * 100 }}%"></div>
                                        </div>
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

        <!-- دفعات تنتهي قريباً -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="feather icon-alert-triangle text-warning"></i>
                    دفعات تنتهي خلال {{ $daysToExpiry }} يوم
                </h4>
                <span class="badge-count">{{ $expiringBatches->total() }} دفعة</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>النقاط الأصلية</th>
                                <th>المتبقية</th>
                                <th>المستخدمة</th>
                                <th>تنتهي في</th>
                                <th>المتبقي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expiringBatches as $batch)
                                @php
                                    $daysLeft = now()->diffInDays(\Carbon\Carbon::parse($batch->expires_at), false);
                                @endphp
                                <tr>
                                    <td>
                                        @if($batch->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $batch->employee->full_name }}
                                        @elseif($batch->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $batch->client->full_name ?? $batch->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ number_format($batch->original_points) }}</td>
                                    <td><span class="badge badge-light-warning">{{ number_format($batch->remaining_points) }}</span></td>
                                    <td>{{ number_format($batch->used_points ?? 0) }}</td>
                                    <td><small>{{ \Carbon\Carbon::parse($batch->expires_at)->format('Y-m-d') }}</small></td>
                                    <td>
                                        <span class="badge badge-{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : 'info') }}">
                                            {{ max(0, (int) $daysLeft) }} يوم
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-check-circle" style="font-size: 3rem; color: #28c76f;"></i>
                                        <p class="mt-2 text-muted">لا توجد دفعات تنتهي قريباً</p>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $expiringBatches, 'label' => 'دفعة'])
            </div>
        </div>

        <!-- آخر الدفعات المنتهية -->
        @if($recentlyExpired->isNotEmpty())
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-x-octagon text-danger"></i> آخر 20 دفعة منتهية</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>النقاط الأصلية</th>
                                <th>النقاط المنتهية</th>
                                <th>تاريخ الانتهاء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentlyExpired as $batch)
                                <tr>
                                    <td>
                                        @if($batch->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $batch->employee->full_name }}
                                        @elseif($batch->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $batch->client->full_name ?? $batch->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ number_format($batch->original_points) }}</td>
                                    <td><span class="badge badge-danger">{{ number_format($batch->expired_points ?? 0) }}</span></td>
                                    <td><small>{{ \Carbon\Carbon::parse($batch->expires_at)->format('Y-m-d') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(function() {
    var monthlyData = @json($expiryByMonth);
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('expiryByMonthChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'عدد الدفعات',
                        data: monthlyData.map(d => d.count),
                        backgroundColor: 'rgba(255, 159, 67, 0.7)',
                        borderColor: '#ff9f43',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'النقاط المتبقية',
                        data: monthlyData.map(d => d.remaining_points),
                        type: 'line',
                        borderColor: '#ea5455',
                        backgroundColor: 'rgba(234, 84, 85, 0.1)',
                        fill: true,
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
