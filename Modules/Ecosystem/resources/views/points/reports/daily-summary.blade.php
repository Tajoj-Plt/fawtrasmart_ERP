@extends('ecosystem::master')

@section('title', 'الملخص اليومي')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">الملخص اليومي الشامل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">الملخص اليومي</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.reports.export', 'daily-summary') }}?date_from={{ $dateFrom }}&date_to={{ $dateTo }}" class="btn btn-outline-success waves-effect waves-light">
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.daily-summary') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                                </div>
                            </div>
                            <div class="col-md-3">
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

        <!-- إحصائيات إجمالية -->
        <div class="stats-row">
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">النقاط المكتسبة</div>
                <div class="stat-value">{{ number_format($totals['total_earned']) }}</div>
                <div class="stat-trend up"><i class="feather icon-plus"></i> <span>مكتسبة</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-trending-down"></i></div>
                <div class="stat-label">النقاط المستخدمة</div>
                <div class="stat-value">{{ number_format($totals['total_spent']) }}</div>
                <div class="stat-trend up"><i class="feather icon-minus"></i> <span>مستخدمة</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">المنتهية</div>
                <div class="stat-value">{{ number_format($totals['total_expired']) }}</div>
                <div class="stat-trend up"><i class="feather icon-x"></i> <span>منتهية</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-bar-chart-2"></i></div>
                <div class="stat-label">صافي التغيير</div>
                <div class="stat-value">{{ number_format($totals['net_change']) }}</div>
                <div class="stat-trend {{ $totals['net_change'] >= 0 ? 'up' : 'down' }}">
                    <i class="feather icon-{{ $totals['net_change'] >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                    <span>{{ $totals['net_change'] >= 0 ? 'إيجابي' : 'سلبي' }}</span>
                </div>
            </div>
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-activity"></i></div>
                <div class="stat-label">إجمالي المعاملات</div>
                <div class="stat-value">{{ number_format($totals['transactions_count']) }}</div>
                <div class="stat-trend up"><i class="feather icon-users"></i> <span>{{ $totals['unique_users'] }} مستخدم</span></div>
            </div>
        </div>

        <!-- إحصائيات ثانوية -->
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="card" style="border-right: 4px solid #ff9f43;">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted" style="font-size: 0.8rem;">الترقيات</div>
                            <div style="font-size: 1.4rem; font-weight: 600;">{{ number_format($totals['tier_upgrades']) }}</div>
                        </div>
                        <i class="feather icon-trending-up text-warning" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-right: 4px solid #7367f0;">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted" style="font-size: 0.8rem;">الاستبدالات</div>
                            <div style="font-size: 1.4rem; font-weight: 600;">{{ number_format($totals['redemptions_count']) }}</div>
                        </div>
                        <i class="feather icon-gift text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-right: 4px solid #28c76f;">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted" style="font-size: 0.8rem;">الإنجازات</div>
                            <div style="font-size: 1.4rem; font-weight: 600;">{{ number_format($totals['achievements_unlocked']) }}</div>
                        </div>
                        <i class="feather icon-award text-success" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-right: 4px solid #00cfe8;">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted" style="font-size: 0.8rem;">الإحالات</div>
                            <div style="font-size: 1.4rem; font-weight: 600;">{{ number_format($totals['referrals_confirmed']) }}</div>
                        </div>
                        <i class="feather icon-share-2 text-info" style="font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- الرسم البياني اليومي -->
        <div class="card table-card-modern no-print">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> الاتجاه اليومي</h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <canvas id="dailySummaryChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- جدول الملخص اليومي التفصيلي -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> الملخص اليومي التفصيلي</h4>
                <span class="badge-count">{{ $summaries->total() }} يوم</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>النوع</th>
                                <th>المكتسبة</th>
                                <th>المستخدمة</th>
                                <th>المنتهية</th>
                                <th>التعديلات</th>
                                <th>الصافي</th>
                                <th>المعاملات</th>
                                <th>المستخدمين</th>
                                <th>الترقيات</th>
                                <th>الاستبدال</th>
                                <th>الإنجازات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summaries as $s)
                                @php $net = $s->getNetPointsChange(); @endphp
                                <tr>
                                    <td><strong>{{ $s->date->format('Y-m-d') }}</strong></td>
                                    <td>
                                        <span class="badge badge-light-{{ ($s->user_type?->value ?? '') === 'client' ? 'primary' : 'success' }}">
                                            {{ $s->user_type?->label() ?? '-' }}
                                        </span>
                                    </td>
                                    <td><span class="text-success">+{{ number_format($s->total_earned) }}</span></td>
                                    <td><span class="text-danger">-{{ number_format($s->total_spent) }}</span></td>
                                    <td><span class="text-warning">-{{ number_format($s->total_expired) }}</span></td>
                                    <td>{{ number_format($s->total_adjusted) }}</td>
                                    <td>
                                        <span class="badge badge-light-{{ $net >= 0 ? 'success' : 'danger' }}">
                                            {{ $net >= 0 ? '+' : '' }}{{ number_format($net) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($s->transactions_count) }}</td>
                                    <td>{{ number_format($s->unique_users) }}</td>
                                    <td>
                                        @if($s->new_tier_upgrades > 0)
                                            <span class="badge badge-light-warning">{{ $s->new_tier_upgrades }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($s->redemptions_count > 0)
                                            <span class="badge badge-light-info">{{ $s->redemptions_count }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($s->achievements_unlocked > 0)
                                            <span class="badge badge-light-success">{{ $s->achievements_unlocked }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد بيانات ملخصة لهذه الفترة</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $summaries, 'label' => 'يوم'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(function() {
    var chartData = @json($chartData);
    if (chartData.length > 0) {
        new Chart(document.getElementById('dailySummaryChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [
                    {
                        label: 'المكتسبة',
                        data: chartData.map(d => d.earned),
                        borderColor: '#28c76f',
                        backgroundColor: 'rgba(40, 199, 111, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'المستخدمة',
                        data: chartData.map(d => d.spent),
                        borderColor: '#ea5455',
                        backgroundColor: 'rgba(234, 84, 85, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'المنتهية',
                        data: chartData.map(d => d.expired),
                        borderColor: '#ff9f43',
                        backgroundColor: 'rgba(255, 159, 67, 0.05)',
                        fill: false,
                        tension: 0.4,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'المعاملات',
                        data: chartData.map(d => d.transactions),
                        type: 'bar',
                        backgroundColor: 'rgba(115, 103, 240, 0.3)',
                        borderColor: '#7367f0',
                        borderWidth: 1,
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
