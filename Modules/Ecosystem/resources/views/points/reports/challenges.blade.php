@extends('ecosystem::master')

@section('title', 'تقرير التحديات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير التحديات والمسابقات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item">التقارير</li>
                            <li class="breadcrumb-item active">تقرير التحديات</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.reports.challenges') }}">
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
                                        <option value="both" {{ request('user_type') == 'both' ? 'selected' : '' }}>الجميع</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع التحدي</label>
                                    <select name="challenge_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="individual" {{ request('challenge_type') == 'individual' ? 'selected' : '' }}>فردي</option>
                                        <option value="team" {{ request('challenge_type') == 'team' ? 'selected' : '' }}>فريق</option>
                                        <option value="branch" {{ request('challenge_type') == 'branch' ? 'selected' : '' }}>فرع</option>
                                        <option value="department" {{ request('challenge_type') == 'department' ? 'selected' : '' }}>قسم</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>مسودة</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتمل</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
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
                <div class="stat-icon"><i class="feather icon-target"></i></div>
                <div class="stat-label">إجمالي التحديات</div>
                <div class="stat-value">{{ number_format($stats['total_challenges']) }}</div>
                <div class="stat-trend up"><i class="feather icon-flag"></i> <span>تحدي</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-play"></i></div>
                <div class="stat-label">تحديات نشطة</div>
                <div class="stat-value">{{ number_format($stats['active_challenges']) }}</div>
                <div class="stat-trend up"><i class="feather icon-zap"></i> <span>جارية الآن</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">تحديات مكتملة</div>
                <div class="stat-value">{{ number_format($stats['completed_challenges']) }}</div>
                <div class="stat-trend up"><i class="feather icon-award"></i> <span>مكتمل</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">إجمالي المشاركين</div>
                <div class="stat-value">{{ number_format($stats['total_participants']) }}</div>
                <div class="stat-trend up"><i class="feather icon-user-plus"></i> <span>مشارك</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-percent"></i></div>
                <div class="stat-label">معدل الإكمال</div>
                <div class="stat-value">{{ $stats['avg_completion_rate'] }}%</div>
                <div class="stat-trend up"><i class="feather icon-bar-chart"></i> <span>النقاط: {{ number_format($stats['total_points_awarded']) }}</span></div>
            </div>
        </div>

        <div class="row">
            <!-- نوع التحديات -->
            <div class="col-md-4">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> أنواع التحديات</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="challengeTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نوع المنافسة -->
            <div class="col-md-4">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> أنواع المنافسة</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="competitionTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اتجاه التحديات -->
            <div class="col-md-4">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> الاتجاه الشهري</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="monthlyChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- قائمة التحديات -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة التحديات</h4>
                <span class="badge-count">{{ $challenges->total() }} تحدي</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>التحدي</th>
                                <th>النوع</th>
                                <th>المنافسة</th>
                                <th>المشاركين</th>
                                <th>النقاط الموزعة</th>
                                <th>الهدف</th>
                                <th>الحالة</th>
                                <th>الفترة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($challenges as $ch)
                                @php
                                    $statusColors = ['draft' => 'secondary', 'active' => 'success', 'completed' => 'primary', 'cancelled' => 'danger'];
                                    $statusLabels = ['draft' => 'مسودة', 'active' => 'نشط', 'completed' => 'مكتمل', 'cancelled' => 'ملغي'];
                                    $typeLabels = ['individual' => 'فردي', 'team' => 'فريق', 'branch' => 'فرع', 'department' => 'قسم'];
                                    $compLabels = ['target' => 'هدف', 'ranking' => 'ترتيب', 'first_n' => 'أول N'];
                                    
                                    // Extract enum values safely
                                    $chStatus = isset($ch->status) ? ($ch->status instanceof \BackedEnum ? $ch->status->value : $ch->status) : 'draft';
                                    $chType = isset($ch->challenge_type) ? ($ch->challenge_type instanceof \BackedEnum ? $ch->challenge_type->value : $ch->challenge_type) : '';
                                    $chCompType = isset($ch->competition_type) ? ($ch->competition_type instanceof \BackedEnum ? $ch->competition_type->value : $ch->competition_type) : '';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('ecosystem.points.challenges.show', $ch->id) }}">
                                            <strong>{{ $ch->name }}</strong>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-light-info">{{ $typeLabels[$chType] ?? $chType }}</span></td>
                                    <td><span class="badge badge-light-warning">{{ $compLabels[$chCompType] ?? $chCompType }}</span></td>
                                    <td><span class="badge badge-light-primary">{{ $ch->participants_count ?? 0 }}</span></td>
                                    <td><span class="badge badge-light-success">{{ number_format($ch->participants_sum_points_awarded ?? 0) }}</span></td>
                                    <td>{{ number_format($ch->target_value ?? 0) }}</td>
                                    <td><span class="badge badge-light-{{ $statusColors[$chStatus] ?? 'secondary' }}">{{ $statusLabels[$chStatus] ?? $chStatus }}</span></td>
                                    <td>
                                        <small>
                                            {{ $ch->starts_at ? \Carbon\Carbon::parse($ch->starts_at)->format('m/d') : '?' }}
                                            -
                                            {{ $ch->ends_at ? \Carbon\Carbon::parse($ch->ends_at)->format('m/d') : '?' }}
                                        </small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                        <p class="mt-2 text-muted">لا توجد تحديات</p>
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $challenges, 'label' => 'تحدي'])
            </div>
        </div>

        <!-- أفضل المشاركين -->
        <div class="card table-card-modern">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> أفضل 15 مشارك في التحديات</h4>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>التحديات المشارك بها</th>
                                <th>التحديات المكتملة</th>
                                <th>النقاط</th>
                                <th>معدل الإكمال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topParticipants as $i => $p)
                                <tr>
                                    <td>
                                        @if($i < 3)
                                            <span style="font-size: 1.2rem;">{{ ['🥇','🥈','🥉'][$i] }}</span>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($p->employee)
                                            <span class="badge badge-light-success">موظف</span> {{ $p->employee->full_name }}
                                        @elseif($p->client)
                                            <span class="badge badge-light-primary">عميل</span> {{ $p->client->full_name ?? $p->client->trade_name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="badge badge-light-info">{{ $p->challenges_joined }}</span></td>
                                    <td><span class="badge badge-light-success">{{ $p->challenges_completed }}</span></td>
                                    <td><span class="badge badge-light-warning">{{ number_format($p->total_points) }}</span></td>
                                    <td>
                                        @php $rate = $p->challenges_joined > 0 ? round($p->challenges_completed / $p->challenges_joined * 100) : 0; @endphp
                                        <div class="d-flex align-items-center">
                                            <div class="progress mr-1" style="width: 80px; height: 6px;">
                                                <div class="progress-bar bg-{{ $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') }}" style="width: {{ $rate }}%"></div>
                                            </div>
                                            <small>{{ $rate }}%</small>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">لا توجد بيانات</td></tr>
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
    var typeLabels = { individual: 'فردي', team: 'فريق', branch: 'فرع', department: 'قسم' };
    var compLabels = { target: 'هدف', ranking: 'ترتيب', first_n: 'أول N' };
    var colors = ['#7367f0','#28c76f','#ff9f43','#00cfe8','#ea5455'];

    // Challenge Type Chart
    var typeData = @json($challengeTypeDistribution);
    if (Object.keys(typeData).length > 0) {
        new Chart(document.getElementById('challengeTypeChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(typeData).map(k => typeLabels[k] || k),
                datasets: [{ data: Object.values(typeData), backgroundColor: colors }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', rtl: true } } }
        });
    }

    // Competition Type Chart
    var compData = @json($competitionTypeDistribution);
    if (Object.keys(compData).length > 0) {
        new Chart(document.getElementById('competitionTypeChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(compData).map(k => compLabels[k] || k),
                datasets: [{ data: Object.values(compData), backgroundColor: ['#00cfe8','#ff9f43','#ea5455'] }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', rtl: true } } }
        });
    }

    // Monthly Chart
    var monthlyData = @json($monthlyChallenges);
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: 'عدد التحديات',
                    data: monthlyData.map(d => d.count),
                    backgroundColor: 'rgba(115, 103, 240, 0.7)',
                    borderColor: '#7367f0',
                    borderWidth: 1
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
});
</script>
@endsection
