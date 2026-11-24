@extends('ecosystem::master')

@section('title', 'تقرير النقاط الشامل')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير النقاط الشامل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير النقاط</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.reports.export', 'points') }}?date_from={{ $dateFrom }}&date_to={{ $dateTo }}" class="btn btn-outline-success waves-effect waves-light">
                <i class="feather icon-download"></i> تصدير
            </a>
            <button class="btn btn-outline-secondary waves-effect waves-light" onclick="window.print()">
                <i class="feather icon-printer"></i> طباعة
            </button>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- قسم الفلاتر المتقدمة -->
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.points') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><i class="feather icon-calendar"></i> من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><i class="feather icon-calendar"></i> إلى تاريخ</label>
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
                                    <label>نوع الإجراء</label>
                                    <select name="action_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($actionTypes as $type)
                                            <option value="{{ $type }}" {{ request('action_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتملة</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلقة</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغاة</option>
                                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>منتهية</option>
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
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>حد أدنى للنقاط</label>
                                    <input type="number" name="min_points" class="form-control" value="{{ request('min_points') }}" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>حد أقصى للنقاط</label>
                                    <input type="number" name="max_points" class="form-control" value="{{ request('max_points') }}" placeholder="∞">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>بحث نصي</label>
                                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="بحث بالاسم، نوع الإجراء، الملاحظات...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                                        <a href="{{ route('ecosystem.points.reports.points') }}" class="btn btn-outline-secondary"><i class="feather icon-refresh-cw"></i> إعادة تعيين</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- إحصائيات رئيسية -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-hash"></i></div>
                <div class="stat-label">إجمالي المعاملات</div>
                <div class="stat-value">{{ number_format($stats['total_transactions']) }}</div>
                <div class="stat-trend up"><i class="feather icon-activity"></i> <span>معاملة</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">النقاط المكتسبة</div>
                <div class="stat-value">{{ number_format($stats['total_earned']) }}</div>
                <div class="stat-trend up"><i class="feather icon-plus"></i> <span>نقطة مكتسبة</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-trending-down"></i></div>
                <div class="stat-label">النقاط المستخدمة</div>
                <div class="stat-value">{{ number_format($stats['total_spent']) }}</div>
                <div class="stat-trend up"><i class="feather icon-minus"></i> <span>نقطة مستخدمة</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-bar-chart-2"></i></div>
                <div class="stat-label">صافي النقاط</div>
                <div class="stat-value">{{ number_format($stats['net_points']) }}</div>
                <div class="stat-trend {{ $stats['net_points'] >= 0 ? 'up' : 'down' }}">
                    <i class="feather icon-{{ $stats['net_points'] >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                    <span>{{ $stats['net_points'] >= 0 ? 'إيجابي' : 'سلبي' }}</span>
                </div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">موظفين نشطين</div>
                <div class="stat-value">{{ number_format($stats['unique_employees']) }}</div>
                <div class="stat-trend up"><i class="feather icon-user"></i> <span>+ {{ $stats['unique_clients'] }} عميل</span></div>
            </div>
        </div>

        <!-- الرسم البياني - اتجاه النقاط اليومي -->
        <div class="card table-card-modern no-print">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> اتجاه النقاط اليومي</h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <canvas id="dailyTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- النقاط حسب نوع الإجراء -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> النقاط حسب نوع الإجراء</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="actionTypeChart" height="200"></canvas>
                        </div>
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>نوع الإجراء</th>
                                        <th>العدد</th>
                                        <th>النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pointsByAction as $action)
                                        <tr>
                                            <td><code>{{ $action->action_type }}</code></td>
                                            <td>{{ number_format($action->count) }}</td>
                                            <td>
                                                <span class="badge badge-light-{{ $action->total_points >= 0 ? 'success' : 'danger' }}">
                                                    {{ number_format($action->total_points) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أفضل المستخدمين -->
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-award"></i> أفضل 10 موظفين</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الموظف</th>
                                        <th>المعاملات</th>
                                        <th>النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topEmployees as $i => $item)
                                        <tr>
                                            <td>
                                                @if($i < 3)
                                                    <span class="badge badge-{{ ['warning','light-primary','light-info'][$i] }}">{{ $i + 1 }}</span>
                                                @else
                                                    {{ $i + 1 }}
                                                @endif
                                            </td>
                                            <td>{{ $item->employee?->full_name ?? '-' }}</td>
                                            <td>{{ number_format($item->transactions_count) }}</td>
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

                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-users"></i> أفضل 10 عملاء</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>العميل</th>
                                        <th>المعاملات</th>
                                        <th>النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topClients as $i => $item)
                                        <tr>
                                            <td>
                                                @if($i < 3)
                                                    <span class="badge badge-{{ ['warning','light-primary','light-info'][$i] }}">{{ $i + 1 }}</span>
                                                @else
                                                    {{ $i + 1 }}
                                                @endif
                                            </td>
                                            <td>{{ $item->client?->full_name ?? $item->client?->trade_name ?? '-' }}</td>
                                            <td>{{ number_format($item->transactions_count) }}</td>
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
        </div>

        <!-- جدول المعاملات التفصيلي -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> تفاصيل المعاملات</h4>
                <span class="badge-count">{{ $transactions->total() }} معاملة</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>النوع</th>
                                <th>الإجراء</th>
                                <th>النقاط</th>
                                <th>الرصيد قبل</th>
                                <th>الرصيد بعد</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $t)
                                <tr>
                                    <td>{{ $t->id }}</td>
                                    <td>
                                        @if($t->employee)
                                            <span class="badge badge-light-success">موظف</span>
                                            {{ $t->employee->full_name }}
                                        @elseif($t->client)
                                            <span class="badge badge-light-primary">عميل</span>
                                            {{ $t->client->full_name ?? $t->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    @php $utVal = $t->user_type instanceof \BackedEnum ? $t->user_type->value : $t->user_type; @endphp
                                    <td><span class="badge badge-light-{{ $utVal === 'employee' ? 'success' : 'primary' }}">{{ $utVal === 'employee' ? 'موظف' : 'عميل' }}</span></td>
                                    <td><code>{{ $t->action_type }}</code></td>
                                    <td>
                                        <span class="badge badge-{{ $t->points >= 0 ? 'success' : 'danger' }}">
                                            {{ $t->points > 0 ? '+' : '' }}{{ number_format($t->points) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($t->balance_before ?? 0) }}</td>
                                    <td>{{ number_format($t->balance_after ?? 0) }}</td>
                                    <td>
                                        @php
                                            $statusColors = ['completed' => 'success', 'pending' => 'warning', 'cancelled' => 'danger', 'expired' => 'secondary'];
                                            $statusLabels = ['completed' => 'مكتملة', 'pending' => 'معلقة', 'cancelled' => 'ملغاة', 'expired' => 'منتهية'];
                                            $stVal = $t->status instanceof \BackedEnum ? $t->status->value : ($t->status ?? 'pending');
                                        @endphp
                                        <span class="badge badge-light-{{ $statusColors[$stVal] ?? 'secondary' }}">{{ $statusLabels[$stVal] ?? $stVal }}</span>
                                    </td>
                                    <td><small>{{ $t->created_at->format('Y-m-d H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد معاملات لعرضها</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $transactions, 'label' => 'معاملة'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(function() {
    // Daily Trend Chart
    var dailyData = @json($dailyTrend);
    if (dailyData.length > 0) {
        new Chart(document.getElementById('dailyTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [
                    {
                        label: 'النقاط المكتسبة',
                        data: dailyData.map(d => d.earned),
                        borderColor: '#28c76f',
                        backgroundColor: 'rgba(40, 199, 111, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'النقاط المستخدمة',
                        data: dailyData.map(d => d.spent),
                        borderColor: '#ea5455',
                        backgroundColor: 'rgba(234, 84, 85, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', rtl: true } },
                scales: {
                    y: { beginAtZero: true },
                    x: { reverse: false }
                }
            }
        });
    }

    // Action Type Chart
    var actionData = @json($pointsByAction);
    if (actionData.length > 0) {
        var colors = ['#7367f0','#28c76f','#ff9f43','#00cfe8','#ea5455','#b8c2cc','#1e1e1e','#82868b','#5a8dee','#39da8a'];
        new Chart(document.getElementById('actionTypeChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: actionData.map(d => d.action_type),
                datasets: [{
                    data: actionData.map(d => Math.abs(d.total_points)),
                    backgroundColor: colors.slice(0, actionData.length)
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', rtl: true } }
            }
        });
    }
});
</script>
@endsection
