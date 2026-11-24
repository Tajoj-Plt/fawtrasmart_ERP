@extends('ecosystem::master')

@section('title', 'لوحة تحكم النظام البيئي')

@section('css')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>


@section('content')
    <!-- رأس الصفحة -->
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">
                        <i class="feather icon-activity"></i> لوحة تحكم النظام البيئي
                    </h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">لوحة التحكم</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <button class="btn btn-outline-success waves-effect waves-light" onclick="window.print()">
                <i class="feather icon-printer"></i> طباعة
            </button>
            <button class="btn btn-outline-primary waves-effect waves-light" onclick="exportDashboard()">
                <i class="feather icon-download"></i> تصدير
            </button>
        </div>
    </div>

    <div class="content-body">
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
                    <form method="GET" action="{{ route('ecosystem.dashboard') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="feather icon-calendar"></i> من تاريخ</label>
                                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><i class="feather icon-calendar"></i> إلى تاريخ</label>
                                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>عرض سريع</label>
                                    <select class="form-control" id="quickFilter" onchange="applyQuickFilter()">
                                        <option value="">اختر فترة</option>
                                        <option value="today">اليوم</option>
                                        <option value="week">هذا الأسبوع</option>
                                        <option value="month">هذا الشهر</option>
                                        <option value="year">هذا العام</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                                        <a href="{{ route('ecosystem.dashboard') }}" class="btn btn-outline-secondary"><i class="feather icon-refresh-cw"></i> إعادة تعيين</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- إحصائيات رئيسية بالتصميم الحديث -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">إجمالي النقاط الممنوحة</div>
                <div class="stat-value">{{ number_format($stats['total_points_awarded']) }}</div>
                <div class="stat-trend up"><i class="feather icon-plus"></i> <span>نقطة مكتسبة</span></div>
            </div>
            <div class="stat-card-modern danger">
                <div class="stat-icon"><i class="feather icon-trending-down"></i></div>
                <div class="stat-label">النقاط المستبدلة</div>
                <div class="stat-value">{{ number_format($stats['total_points_redeemed']) }}</div>
                <div class="stat-trend"><i class="feather icon-minus"></i> <span>نقطة مستخدمة</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-bar-chart-2"></i></div>
                <div class="stat-label">صافي النقاط</div>
                <div class="stat-value">{{ number_format($stats['total_points_awarded'] - $stats['total_points_redeemed']) }}</div>
                <div class="stat-trend up"><i class="feather icon-activity"></i> <span>الرصيد الصافي</span></div>
            </div>
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">المستخدمون النشطون</div>
                <div class="stat-value">{{ number_format($stats['active_users']) }}</div>
                <div class="stat-trend up"><i class="feather icon-user-check"></i> <span>في الفترة المحددة</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-gift"></i></div>
                <div class="stat-label">المكافآت المطلوبة</div>
                <div class="stat-value">{{ number_format($stats['total_rewards_claimed']) }}</div>
                <div class="stat-trend"><i class="feather icon-clock"></i> <span>{{ $stats['pending_redemptions'] }} معلقة</span></div>
            </div>
        </div>

        <!-- إحصائيات معدلات النشاط -->
        <div class="stats-row">
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">معدل الإكمال</div>
                <div class="stat-value">{{ $activityRates['completion_rate'] }}%</div>
                <div class="stat-trend up"><i class="feather icon-trending-up"></i> <span>من المعاملات</span></div>
            </div>
            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-percent"></i></div>
                <div class="stat-label">معدل الاستبدال</div>
                <div class="stat-value">{{ $activityRates['redemption_rate'] }}%</div>
                <div class="stat-trend"><i class="feather icon-repeat"></i> <span>من النقاط</span></div>
            </div>
            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-user"></i></div>
                <div class="stat-label">متوسط النقاط/مستخدم</div>
                <div class="stat-value">{{ number_format($activityRates['avg_points_per_user']) }}</div>
                <div class="stat-trend up"><i class="feather icon-activity"></i> <span>نقطة</span></div>
            </div>
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-hash"></i></div>
                <div class="stat-label">متوسط المعاملات/مستخدم</div>
                <div class="stat-value">{{ number_format($activityRates['avg_transactions_per_user'], 1) }}</div>
                <div class="stat-trend up"><i class="feather icon-activity"></i> <span>معاملة</span></div>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="row">
            <!-- رسم بياني للنقاط -->
            <div class="col-lg-8 col-12">
                <div class="card table-card-modern no-print">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-bar-chart-2"></i> النقاط الممنوحة والمستبدلة (آخر 7 أيام)</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div id="pointsChart" class="chart-container"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- توزيع المستويات -->
            <div class="col-lg-4 col-12">
                <div class="card table-card-modern no-print">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> توزيع المستخدمين حسب المستوى</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div id="tierPieChart" class="chart-container"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- نمو المستخدمين النشطين -->
        <div class="row">
            <div class="col-12">
                <div class="card table-card-modern no-print">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-trending-up"></i> نمو المستخدمين النشطين (آخر 30 يوم)</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="userGrowthChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- النقاط حسب نوع الإجراء والفروع -->
        <div class="row">
            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> توزيع النقاط حسب الإجراء</h4>
                        <span class="badge-count">Top 10</span>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="actionChart" height="200"></canvas>
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
                                    @foreach($pointsByAction->take(10) as $action)
                                        <tr>
                                            <td><code>{{ $action->action_type }}</code></td>
                                            <td>{{ number_format($action->count) }}</td>
                                            <td><span class="badge badge-light-success">{{ number_format($action->total_points) }}</span></td>
                                        </tr>
                                    @endforeach
                                    @if($pointsByAction->isEmpty())
                                        <tr><td colspan="3" class="text-center text-muted">لا توجد بيانات</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card table-card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-map-pin"></i> النشاط حسب الفروع</h4>
                        <span class="badge-count">Top 10</span>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <canvas id="branchChart" height="200"></canvas>
                        </div>
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>الفرع</th>
                                        <th>المعاملات</th>
                                        <th>النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($branchActivity->take(10) as $branch)
                                        <tr>
                                            <td>{{ $branch['branch'] }}</td>
                                            <td>{{ number_format($branch['transactions']) }}</td>
                                            <td><span class="badge badge-light-info">{{ number_format($branch['points']) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- إحصائيات إضافية -->
        <div class="row">
            <!-- الإنجازات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-award"></i> الإنجازات</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>إنجازات محققة</span>
                            <strong>{{ number_format($stats['achievements_unlocked']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>إنجازات نادرة</span>
                            <strong class="text-warning">{{ number_format($additionalStats['rare_achievements'] ?? 0) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>معدل الإنجاز</span>
                            <strong class="text-success">{{ $additionalStats['achievement_rate'] ?? 0 }}%</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- التحديات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-target"></i> التحديات</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>تحديات نشطة</span>
                            <strong class="text-primary">{{ $stats['active_challenges'] }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>مشاركين</span>
                            <strong>{{ number_format($additionalStats['total_challenge_participants'] ?? 0) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>تحديات منتهية</span>
                            <strong class="text-muted">{{ number_format($additionalStats['completed_challenges'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الإحالات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-users"></i> الإحالات</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>إجمالي الإحالات</span>
                            <strong>{{ number_format($stats['total_referrals']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>إحالات مؤكدة</span>
                            <strong class="text-success">{{ number_format($stats['confirmed_referrals'] ?? 0) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>قيد الانتظار</span>
                            <strong class="text-warning">{{ number_format($stats['pending_referrals'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إحصائيات عامة -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-bar-chart"></i> إحصائيات عامة</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>مستخدمون نشطون</span>
                            <strong>{{ number_format($stats['active_users']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>معاملات النقاط</span>
                            <strong class="text-success">{{ number_format($stats['total_points_awarded'] + $stats['total_points_redeemed']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>نسبة الاستبدال</span>
                            <strong class="text-info">{{ $stats['total_points_awarded'] > 0 ? round(($stats['total_points_redeemed'] / $stats['total_points_awarded']) * 100, 1) : 0 }}%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- إحصائيات الاستبدالات -->
        <div class="row">
            <div class="col-md-8">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-award"></i> أفضل المكافآت المطلوبة</h4>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topRewards as $i => $reward)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $reward['name'] }}</td>
                                            <td><span class="badge badge-light-primary">{{ number_format($reward['count']) }}</span></td>
                                            <td><span class="badge badge-light-danger">{{ number_format($reward['points']) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-gift"></i> حالة الاستبدالات</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>إجمالي الطلبات</span>
                            <strong>{{ number_format($redemptionStats['total']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="feather icon-clock text-warning"></i> قيد الانتظار</span>
                            <strong class="text-warning">{{ number_format($redemptionStats['pending']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="feather icon-check text-success"></i> مقبولة</span>
                            <strong class="text-success">{{ number_format($redemptionStats['approved']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="feather icon-package text-info"></i> تم التسليم</span>
                            <strong class="text-info">{{ number_format($redemptionStats['delivered']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="feather icon-x text-danger"></i> ملغاة</span>
                            <strong class="text-danger">{{ number_format($redemptionStats['cancelled']) }}</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>النقاط المستخدمة</span>
                            <strong class="text-primary">{{ number_format($redemptionStats['total_points_used']) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- آخر الإنجازات المحققة -->
        <div class="row">
            <div class="col-12">
                <div class="card table-card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-star"></i> آخر الإنجازات المحققة</h4>
                        <span class="badge-count">{{ $recentAchievements->count() }} إنجاز</span>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>الإنجاز</th>
                                        <th>النقاط</th>
                                        <th>الوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentAchievements as $achievement)
                                        <tr>
                                            <td>{{ $achievement['user'] }}</td>
                                            <td>
                                                <i class="feather icon-award text-warning"></i>
                                                <strong>{{ $achievement['achievement'] }}</strong>
                                            </td>
                                            <td><span class="badge badge-light-success">+{{ number_format($achievement['points']) }}</span></td>
                                            <td><small class="text-muted">{{ $achievement['time'] }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- أفضل المستخدمين -->
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-star"></i> أفضل المستخدمين</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المستخدم</th>
                                        <th>النقاط</th>
                                        <th>المستوى</th>
                                        <th>الإنجازات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topUsers as $index => $user)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar" style="background: {{ ['#7367f0', '#28c76f', '#ff9f43', '#ea5455', '#00cfe8'][$index % 5] }}">
                                                    {{ substr($user['name'], 0, 1) }}
                                                </div>
                                                <span>{{ $user['name'] }}</span>
                                            </div>
                                        </td>
                                        <td><strong class="text-success">{{ number_format($user['points']) }}</strong></td>
                                        <td>
                                            @php
                                                $tierName = $user['tier'];
                                                $tierClass = 'silver'; // default
                                                if (stripos($tierName, 'ماس') !== false || stripos($tierName, 'diamond') !== false) {
                                                    $tierClass = 'diamond';
                                                } elseif (stripos($tierName, 'بلاتين') !== false || stripos($tierName, 'platinum') !== false) {
                                                    $tierClass = 'platinum';
                                                } elseif (stripos($tierName, 'ذهب') !== false || stripos($tierName, 'gold') !== false) {
                                                    $tierClass = 'gold';
                                                } elseif (stripos($tierName, 'فض') !== false || stripos($tierName, 'silver') !== false) {
                                                    $tierClass = 'silver';
                                                } elseif (stripos($tierName, 'برونز') !== false || stripos($tierName, 'bronze') !== false) {
                                                    $tierClass = 'bronze';
                                                }
                                            @endphp
                                            <span class="tier-badge tier-{{ $tierClass }}">
                                                {{ $tierName }}
                                            </span>
                                        </td>
                                        <td><span class="badge badge-light-warning">{{ $user['achievements'] }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- آخر المعاملات -->
            <div class="col-lg-6 col-12">
                <div class="card table-card-modern">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="feather icon-activity"></i> آخر المعاملات</h4>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>الإجراء</th>
                                        <th>النقاط</th>
                                        <th>الوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTransactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction['user'] }}</td>
                                        <td><small class="text-muted">{{ $transaction['action'] }}</small></td>
                                        <td>
                                            <span class="badge badge-{{ strpos($transaction['points'], '+') !== false ? 'success' : 'danger' }}">
                                                {{ $transaction['points'] }}
                                            </span>
                                        </td>
                                        <td><small class="text-muted">{{ $transaction['time'] }}</small></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- التحديات النشطة -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-zap"></i> التحديات النشطة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($activeChallenges as $challenge)
                            <div class="col-md-4">
                                <div class="card border">
                                    <div class="card-body">
                                        <h5 class="mb-1">{{ $challenge['name'] }}</h5>
                                        <p class="text-muted mb-2"><i class="feather icon-users"></i> {{ $challenge['participants'] }} مشارك</p>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar progress-bar-animated bg-success" 
                                                 role="progressbar" 
                                                 style="width: {{ $challenge['progress'] }}%"
                                                 aria-valuenow="{{ $challenge['progress'] }}" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">{{ $challenge['progress'] }}% مكتمل</small>
                                            <small class="text-warning"><i class="feather icon-clock"></i> {{ $challenge['ends'] }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // رسم بياني للنقاط (خط)
    var pointsChartOptions = {
        series: [{
            name: 'نقاط ممنوحة',
            data: @json($pointsEarnedData)
        }, {
            name: 'نقاط مستبدلة',
            data: @json($pointsRedeemedData)
        }],
        chart: {
            type: 'line',
            height: 350,
            fontFamily: 'Cairo, Arial, sans-serif',
            toolbar: {
                show: true
            }
        },
        colors: ['#28c76f', '#ea5455'],
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        xaxis: {
            categories: @json($labels)
        },
        yaxis: {
            title: {
                text: 'النقاط'
            }
        },
        legend: {
            position: 'top'
        },
        grid: {
            borderColor: '#f1f1f1'
        }
    };

    var pointsChart = new ApexCharts(document.querySelector("#pointsChart"), pointsChartOptions);
    pointsChart.render();

    // رسم بياني دائري للمستويات
    var tierData = @json($tierDistribution);
    var tierLabels = Object.keys(tierData);
    var tierValues = Object.values(tierData);

    var tierPieOptions = {
        series: tierValues,
        chart: {
            type: 'donut',
            height: 350,
            fontFamily: 'Cairo, Arial, sans-serif'
        },
        labels: tierLabels,
        colors: ['#c94b4b', '#d7d2cc', '#ffd89b', '#84fab0', '#667eea'],
        legend: {
            position: 'bottom'
        },
        dataLabels: {
            enabled: true
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true
                        },
                        value: {
                            show: true
                        },
                        total: {
                            show: true,
                            label: 'المستخدمين',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            }
        }
    };

    var tierPieChart = new ApexCharts(document.querySelector("#tierPieChart"), tierPieOptions);
    tierPieChart.render();

    // رسم بياني لنمو المستخدمين (Chart.js)
    var userGrowthData = @json($userGrowth);
    if (userGrowthData.length > 0) {
        new Chart(document.getElementById('userGrowthChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: userGrowthData.map(d => d.date),
                datasets: [
                    {
                        label: 'موظفين نشطين',
                        data: userGrowthData.map(d => d.employees),
                        borderColor: '#28c76f',
                        backgroundColor: 'rgba(40, 199, 111, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'عملاء نشطين',
                        data: userGrowthData.map(d => d.clients),
                        borderColor: '#7367f0',
                        backgroundColor: 'rgba(115, 103, 240, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // رسم بياني للنقاط حسب الإجراء (Chart.js - Doughnut)
    var actionData = @json($pointsByAction);
    if (actionData.length > 0) {
        var colors = ['#7367f0','#28c76f','#ff9f43','#00cfe8','#ea5455','#b8c2cc','#1e1e1e','#82868b','#5a8dee','#39da8a'];
        new Chart(document.getElementById('actionChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: actionData.map(d => d.action_type),
                datasets: [{
                    data: actionData.map(d => d.total_points),
                    backgroundColor: colors.slice(0, actionData.length)
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // رسم بياني للفروع (Chart.js - Bar)
    var branchData = @json($branchActivity);
    if (branchData.length > 0) {
        new Chart(document.getElementById('branchChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: branchData.map(d => d.branch),
                datasets: [{
                    label: 'النقاط',
                    data: branchData.map(d => d.points),
                    backgroundColor: 'rgba(0, 207, 232, 0.7)',
                    borderColor: '#00cfe8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});

// دالة التصدير
function exportDashboard() {
    const dateFrom = document.querySelector('input[name="start_date"]')?.value || '';
    const dateTo = document.querySelector('input[name="end_date"]')?.value || '';
    window.location.href = `{{ route('ecosystem.dashboard') }}?export=pdf&start_date=${dateFrom}&end_date=${dateTo}`;
}

// دالة الفلتر السريع
function applyQuickFilter() {
    const filter = document.getElementById('quickFilter').value;
    const today = new Date();
    let startDate, endDate = today.toISOString().split('T')[0];
    
    switch(filter) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            startDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.setMonth(today.getMonth() - 1)).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.setFullYear(today.getFullYear() - 1)).toISOString().split('T')[0];
            break;
        default:
            return;
    }
    
    document.querySelector('input[name="start_date"]').value = startDate;
    document.querySelector('input[name="end_date"]').value = endDate;
    document.getElementById('filterForm').submit();
}
</script>
@endsection

