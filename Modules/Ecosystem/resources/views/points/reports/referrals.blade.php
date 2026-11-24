@extends('ecosystem::master')

@section('title', 'تقرير الإحالات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير الإحالات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير الإحالات</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.referrals') }}">
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلق</option>
                                        <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>مؤكد</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                                        <a href="{{ route('ecosystem.points.reports.referrals') }}" class="btn btn-outline-secondary"><i class="feather icon-refresh-cw"></i></a>
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
                <div class="stat-icon"><i class="feather icon-share-2"></i></div>
                <div class="stat-label">إجمالي الإحالات</div>
                <div class="stat-value">{{ number_format($stats['total_referrals']) }}</div>
                <div class="stat-trend up"><i class="feather icon-link"></i> <span>إحالة</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">إحالات مؤكدة</div>
                <div class="stat-value">{{ number_format($stats['confirmed_referrals']) }}</div>
                <div class="stat-trend up"><i class="feather icon-user-check"></i> <span>مؤكدة</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-clock"></i></div>
                <div class="stat-label">قيد الانتظار</div>
                <div class="stat-value">{{ number_format($stats['pending_referrals']) }}</div>
                <div class="stat-trend up"><i class="feather icon-loader"></i> <span>معلق</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-percent"></i></div>
                <div class="stat-label">معدل التحويل</div>
                <div class="stat-value">{{ $stats['conversion_rate'] }}%</div>
                <div class="stat-trend up"><i class="feather icon-trending-up"></i> <span>نسبة النجاح</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">النقاط الموزعة</div>
                <div class="stat-value">{{ number_format($stats['total_referrer_points'] + $stats['total_referred_points']) }}</div>
                <div class="stat-trend up"><i class="feather icon-gift"></i> <span>محيل: {{ number_format($stats['total_referrer_points']) }}</span></div>
            </div>
        </div>

        <div class="row">
            <!-- اتجاه الإحالات الشهري -->
            <div class="col-md-8">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> اتجاه الإحالات الشهري</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="monthlyReferralsChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>

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
        </div>

        <!-- أفضل المحيلين -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> أفضل 10 محيلين</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المُحيل</th>
                                <th>عدد الإحالات</th>
                                <th>النقاط المكتسبة</th>
                                <th>شريط</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $maxRef = $topReferrers->max('referral_count') ?: 1; @endphp
                            @forelse($topReferrers as $i => $ref)
                                <tr>
                                    <td>
                                        @if($i < 3)
                                            <span style="font-size: 1.2rem;">{{ ['🥇','🥈','🥉'][$i] }}</span>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </td>
                                    <td><strong>{{ $ref->referrer?->full_name ?? $ref->referrer?->trade_name ?? '-' }}</strong></td>
                                    <td><span class="badge badge-light-primary">{{ $ref->referral_count }}</span></td>
                                    <td><span class="badge badge-light-success">{{ number_format($ref->total_points) }}</span></td>
                                    <td style="width: 200px;">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: {{ ($ref->referral_count / $maxRef) * 100 }}%"></div>
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

        <!-- إعدادات الإحالة النشطة -->
        @if($activeSettings->isNotEmpty())
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-settings"></i> إعدادات الإحالة النشطة</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>نقاط المُحيل</th>
                                <th>نقاط المُحال</th>
                                <th>نوع التأكيد</th>
                                <th>الحد الشهري</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeSettings as $s)
                                <tr>
                                    <td><span class="badge badge-light-success">{{ $s->referrer_points }}</span></td>
                                    <td><span class="badge badge-light-primary">{{ $s->referred_points }}</span></td>
                                    <td>{{ $s->confirmation_type }}</td>
                                    <td>{{ $s->max_referrals_per_month ?? '∞' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- سجل الإحالات -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> سجل الإحالات</h4>
                <span class="badge-count">{{ $referrals->total() }} إحالة</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>المُحيل</th>
                                <th>المُحال</th>
                                <th>الكود</th>
                                <th>نقاط المحيل</th>
                                <th>نقاط المحال</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($referrals as $r)
                                @php
                                    $stColors = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger'];
                                    $stLabels = ['pending' => 'معلق', 'confirmed' => 'مؤكد', 'cancelled' => 'ملغي'];
                                @endphp
                                <tr>
                                    <td>{{ $r->referrer?->full_name ?? $r->referrer?->trade_name ?? '-' }}</td>
                                    <td>{{ $r->referred?->full_name ?? $r->referred?->trade_name ?? '-' }}</td>
                                    <td><code>{{ $r->referral_code }}</code></td>
                                    <td><span class="badge badge-light-success">{{ $r->referrer_points ?? 0 }}</span></td>
                                    <td><span class="badge badge-light-primary">{{ $r->referred_points ?? 0 }}</span></td>
                                    <td><span class="badge badge-light-{{ $stColors[$r->status] ?? 'secondary' }}">{{ $stLabels[$r->status] ?? $r->status }}</span></td>
                                    <td><small>{{ $r->created_at->format('Y-m-d H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 text-muted">لا توجد إحالات</p>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $referrals, 'label' => 'إحالة'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(function() {
    // Monthly Chart
    var monthlyData = @json($monthlyReferrals);
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyReferralsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'إجمالي',
                        data: monthlyData.map(d => d.total),
                        backgroundColor: 'rgba(115, 103, 240, 0.7)',
                        borderColor: '#7367f0',
                        borderWidth: 1
                    },
                    {
                        label: 'مؤكدة',
                        data: monthlyData.map(d => d.confirmed),
                        backgroundColor: 'rgba(40, 199, 111, 0.7)',
                        borderColor: '#28c76f',
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

    // Status Chart
    var statusData = @json($referralsByStatus);
    var statusLabels = { pending: 'معلق', confirmed: 'مؤكد', cancelled: 'ملغي' };
    var statusColors = { pending: '#ff9f43', confirmed: '#28c76f', cancelled: '#ea5455' };

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
            options: { responsive: true, plugins: { legend: { position: 'bottom', rtl: true } } }
        });
    }
});
</script>
@endsection
