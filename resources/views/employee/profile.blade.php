@extends('ecosystem::master')

@section('title', 'بروفايل الموظف - ' . $employee->name)

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">البروفايل الشخصي</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">لوحة التحكم</a></li>
                            <li class="breadcrumb-item active">بروفايل الموظف</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- فلتر الشهر --}}
        @if(isset($availableMonths) && count($availableMonths) > 0)
        <div class="row mb-3">
            <div class="col-12">
                <div class="card ep-month-filter-card mb-0">
                    <div class="card-body py-2 px-3">
                        <form id="monthFilterForm" action="{{ route('employee.profile') }}" method="GET" class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center">
                                <div class="ep-filter-icon ml-2">
                                    <i class="feather icon-calendar"></i>
                                </div>
                                <div>
                                    <span class="font-weight-bold" style="font-size: 0.9rem;">عرض بيانات شهر:</span>
                                    @if(!$isCurrentMonth)
                                        <span class="badge badge-pill badge-warning mr-1" style="font-size: 0.7rem;">
                                            <i class="feather icon-clock"></i> بيانات سابقة
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="hidden" name="month" id="filter_month" value="{{ $filterMonth }}">
                                <input type="hidden" name="year" id="filter_year" value="{{ $filterYear }}">
                                <select name="month_year" id="month_year_select" class="form-control form-control-sm ep-month-select" style="min-width: 180px;">
                                    @foreach($availableMonths as $m)
                                        <option value="{{ $m['month'] }}-{{ $m['year'] }}"
                                            {{ ($m['month'] == $filterMonth && $m['year'] == $filterYear) ? 'selected' : '' }}>
                                            {{ $m['label'] }}
                                            @if($m['month'] == now()->month && $m['year'] == now()->year)
                                                (الحالي)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @if(!$isCurrentMonth)
                                    <a href="{{ route('employee.profile') }}" class="btn btn-sm btn-outline-primary mr-2" style="border-radius: 8px; white-space: nowrap;">
                                        <i class="feather icon-refresh-cw"></i> الشهر الحالي
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- شريط تقدم النقاط الشهرية --}}
        @if(isset($monthlyPointsData))
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.monthly-tier-progress', ['monthlyPointsData' => $monthlyPointsData])
            </div>
        </div>
        @endif

        {{-- شريط الإنجازات --}}
        @if(isset($availableAchievements) && $availableAchievements->count() > 0)
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.achievements-ribbon', [
                    'availableAchievements' => $availableAchievements,
                    'userAchievements' => $achievements,
                    'ribbonTitle' => 'إنجازاتي',
                    'ribbonSubtitle' => 'الإنجازات المحققة - تتبع تقدمك',
                ])
            </div>
        </div>
        @endif

        {{-- بطاقة معلومات الموظف --}}
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-left">
                        <div class="avatar-wrapper mb-2">
                            @if($employee->employee_photo)
                                <img src="{{ asset($employee->employee_photo) }}" alt="{{ $employee->name }}" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover;">
                            @else
                                <div class="avatar-placeholder rounded-circle shadow d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2.5rem; color: #fff;">
                                    {{ mb_substr($employee->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <h4 class="mb-1 font-weight-bold">{{ $employee->name }}</h4>
                        <p class="text-muted mb-2"><i class="feather icon-mail mr-1"></i>{{ $employee->email }}</p>

                        @if($currentTier)
                            <span class="badge badge-pill px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, {{ $currentTier->color ?? '#7367f0' }} 0%, {{ $currentTier->color ?? '#7367f0' }}dd 100%); color: #fff; font-size: 1rem;">
                                <i class="feather icon-award mr-1"></i> {{ $currentTier->name }}
                            </span>
                        @else
                            <span class="badge badge-pill badge-secondary px-3 py-2" style="font-size: 1rem;">
                                <i class="feather icon-user mr-1"></i> بدون مستوى
                            </span>
                        @endif
                    </div>

                    <div class="col-md-5">
                        <div class="employee-details">
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-grid mr-1"></i> القسم</span>
                                <strong>{{ $employee->department?->name ?? '-' }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-map-pin mr-1"></i> الفرع</span>
                                <strong>{{ $employee->branch?->name ?? '-' }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-file-text mr-1"></i> عدد المعاملات</span>
                                <strong>{{ number_format($transactions->total()) }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-award mr-1"></i> الإنجازات</span>
                                <strong>{{ $totalAchievements }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        @if($nextTier)
                            <div class="next-tier-card p-3 rounded" style="background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);">
                                <h6 class="text-muted mb-2"><i class="feather icon-target mr-1"></i> المستوى التالي</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge mr-2" style="background-color: {{ $nextTier->color ?? '#7367f0' }}; color: #fff;">
                                        {{ $nextTier->name }}
                                    </span>
                                    <span class="text-muted">{{ number_format($nextTier->min_points) }} نقطة</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{ $progressToNextTier }}%; background: linear-gradient(90deg, {{ $nextTier->color ?? '#7367f0' }} 0%, {{ $nextTier->color ?? '#7367f0' }}aa 100%);"
                                         aria-valuenow="{{ $progressToNextTier }}"></div>
                                </div>
                                <small class="text-muted">
                                    <i class="feather icon-zap"></i>
                                    @php
                                        $pointsNeeded = $nextTier->min_points - $currentBalance;
                                    @endphp
                                    {{ number_format(max(0, $pointsNeeded)) }} نقطة للترقية
                                </small>
                            </div>
                        @else
                            <div class="max-tier-card p-3 rounded text-center" style="background: linear-gradient(135deg, #28c76f22 0%, #28c76f11 100%); border: 1px solid #28c76f44;">
                                <i class="feather icon-award text-success" style="font-size: 2rem;"></i>
                                <h6 class="text-success mt-2 mb-0">أعلى مستوى!</h6>
                                <small class="text-muted">لقد وصلت للقمة 🎉</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- بطاقات الإحصائيات --}}
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">الرصيد الحالي</div>
                <div class="stat-value">{{ number_format($currentBalance) }}</div>
                <div class="stat-trend up"><i class="feather icon-activity"></i> <span>نقطة متاحة</span></div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">إجمالي المكتسب</div>
                <div class="stat-value">{{ number_format($totalPointsEarned) }}</div>
                <div class="stat-trend up"><i class="feather icon-plus-circle"></i> <span>نقطة</span></div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-shopping-bag"></i></div>
                <div class="stat-label">إجمالي المستخدم</div>
                <div class="stat-value">{{ number_format($totalPointsSpent) }}</div>
                <div class="stat-trend"><i class="feather icon-minus-circle"></i> <span>نقطة</span></div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-trending-up"></i></div>
                <div class="stat-label">نقاط الشهر المحدد</div>
                <div class="stat-value">{{ number_format($pointsThisMonth) }}</div>
                <div class="stat-trend up"><i class="feather icon-calendar"></i> <span>{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->translatedFormat('F Y') }}</span></div>
            </div>
        </div>

        {{-- تحذير انتهاء الصلاحية --}}
        @include('ecosystem::points.partials.expiry-warning-banner')

        {{-- التبويبات الرئيسية --}}
        <div class="card table-card-modern ep-tabs-card">
            <div class="card-header ep-tabs-header">
                <ul class="nav nav-tabs card-header-tabs pts-nav-tabs nav-fill ep-nav" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="transactions-tab" data-toggle="tab" href="#transactions" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-list"></i></div>
                            <span class="ep-tab-text">المعاملات</span>
                            <span class="ep-tab-badge primary">{{ $transactions->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="rewards-tab" data-toggle="tab" href="#rewards" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-gift"></i></div>
                            <span class="ep-tab-text">المكافآت</span>
                            <span class="ep-tab-badge warning">{{ $availableRewards->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="challenges-tab" data-toggle="tab" href="#challenges" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-target"></i></div>
                            <span class="ep-tab-text">التحديات</span>
                            <span class="ep-tab-badge info">{{ $challengeStats['active'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="achievements-tab" data-toggle="tab" href="#achievements" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-award"></i></div>
                            <span class="ep-tab-text">الإنجازات</span>
                            <span class="ep-tab-badge success">{{ $totalAchievements }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="redemptions-tab" data-toggle="tab" href="#redemptions" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-shopping-cart"></i></div>
                            <span class="ep-tab-text">طلباتي</span>
                            <span class="ep-tab-badge danger">{{ $redemptions->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tiers-tab" data-toggle="tab" href="#tiers" role="tab">
                            <div class="ep-tab-icon"><i class="feather icon-layers"></i></div>
                            <span class="ep-tab-text">المستويات</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">

                    {{-- ==================== تبويب المعاملات ==================== --}}
                    <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                        <div class="table-responsive">
                            <table class="pts-table">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">التاريخ</th>
                                        <th style="width: 15%;">نوع العملية</th>
                                        <th style="width: 12%;">النقاط</th>
                                        <th style="width: 12%;">الرصيد بعد</th>
                                        <th style="width: 12%;">الحالة</th>
                                        <th style="width: 34%;">الوصف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $transaction)
                                        <tr>
                                            <td class="cell-date">
                                                <i class="feather icon-clock"></i>
                                                {{ $transaction->created_at->format('Y-m-d H:i') }}
                                            </td>
                                            <td>
                                                <span class="pts-badge pts-badge-secondary">{{ $transaction->action_type }}</span>
                                            </td>
                                            <td>
                                                @if($transaction->points > 0)
                                                    <span class="cell-points-positive">
                                                        <i class="feather icon-arrow-up"></i>
                                                        +{{ number_format($transaction->points) }}
                                                    </span>
                                                @else
                                                    <span class="cell-points-negative">
                                                        <i class="feather icon-arrow-down"></i>
                                                        {{ number_format($transaction->points) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="cell-balance">{{ number_format($transaction->balance_after) }}</span>
                                            </td>
                                            <td>
                                                @if($transaction->status && method_exists($transaction->status, 'label'))
                                                    <span class="pts-badge pts-badge-{{ $transaction->status->color() }}">
                                                        {{ $transaction->status->label() }}
                                                    </span>
                                                @elseif($transaction->status == 'completed' || ($transaction->status && $transaction->status->value == 'completed'))
                                                    <span class="pts-badge pts-badge-success">مكتمل</span>
                                                @elseif($transaction->status == 'pending' || ($transaction->status && $transaction->status->value == 'pending'))
                                                    <span class="pts-badge pts-badge-warning">معلق</span>
                                                @else
                                                    <span class="pts-badge pts-badge-danger">ملغي</span>
                                                @endif
                                            </td>
                                            <td class="cell-description">
                                                {{ Str::limit($transaction->description, 45) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="pts-empty-state">
                                                    <div class="empty-icon"><i class="feather icon-inbox"></i></div>
                                                    <h5>لا توجد معاملات</h5>
                                                    <p>لم يتم تسجيل أي معاملات نقاط بعد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @include('ecosystem::points.partials._smart-pagination', ['paginator' => $transactions, 'label' => 'معاملة'])
                    </div>

                    {{-- ==================== تبويب المكافآت ==================== --}}
                    <div class="tab-pane fade" id="rewards" role="tabpanel">
                        {{-- إحصائيات المكافآت --}}
                        <div class="ep-mini-stats mb-4">
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);">
                                    <i class="feather icon-star"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-primary">{{ number_format($currentBalance) }}</span>
                                    <span class="ep-mini-stat-label">نقاطك الحالية</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);">
                                    <i class="feather icon-check-circle"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-success">{{ $rewardStats['completed'] }}</span>
                                    <span class="ep-mini-stat-label">مكافآت مستلمة</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #ff9f43 0%, #ffb976 100%);">
                                    <i class="feather icon-clock"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-warning">{{ $rewardStats['pending'] }}</span>
                                    <span class="ep-mini-stat-label">طلبات معلقة</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #ea5455 0%, #f08182 100%);">
                                    <i class="feather icon-minus-circle"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-danger">{{ number_format($rewardStats['total_points_spent']) }}</span>
                                    <span class="ep-mini-stat-label">نقاط مستخدمة</span>
                                </div>
                            </div>
                        </div>

                        {{-- المكافآت المتاحة --}}
                        <div class="ep-section-header mb-3">
                            <div class="ep-section-icon" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);">
                                <i class="feather icon-gift"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 font-weight-bold">المكافآت المتاحة</h6>
                                @if($isCurrentMonth)
                                    <small class="text-muted">اضغط على المكافأة لطلبها</small>
                                @else
                                    <small class="text-danger"><i class="feather icon-alert-circle"></i> الاستبدال متاح فقط للشهر الحالي</small>
                                @endif
                            </div>
                        </div>
                        @if(!$isCurrentMonth)
                            <div class="alert alert-warning text-center" style="border-radius: 12px; border: none;">
                                <i class="feather icon-info" style="font-size: 1.2rem;"></i>
                                <p class="mb-1 font-weight-bold">أنت تعرض بيانات شهر سابق</p>
                                <small>طلب استبدال المكافآت متاح فقط لنقاط الشهر الحالي. <a href="{{ route('employee.profile') }}">العودة للشهر الحالي</a></small>
                            </div>
                        @endif
                        @if($availableRewards->isNotEmpty())
                            <div class="row">
                                @foreach($availableRewards as $reward)
                                    @php
                                        $canAfford = $isCurrentMonth && $currentBalance >= $reward->points_required;
                                    @endphp
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="ep-reward-card {{ $canAfford ? 'affordable' : 'locked' }} {{ !$isCurrentMonth ? 'past-month' : '' }}"
                                             @if($isCurrentMonth)
                                             data-reward-id="{{ $reward->id }}"
                                             data-reward-name="{{ e($reward->name) }}"
                                             data-reward-points="{{ $reward->points_required }}"
                                             data-reward-desc="{{ e($reward->description ?? '') }}"
                                             data-reward-type="{{ e($reward->reward_type->label() ?? '') }}"
                                             @endif
                                             >
                                            @if($canAfford)
                                                <div class="ep-reward-badge"><i class="feather icon-check"></i> متاح</div>
                                            @endif
                                            <div class="ep-reward-icon">
                                                {!! $reward->icon ?? '🎁' !!}
                                            </div>
                                            <h6 class="ep-reward-name">{{ $reward->name }}</h6>
                                            @if($reward->description)
                                                <p class="ep-reward-desc">{{ Str::limit($reward->description, 40) }}</p>
                                            @endif
                                            <div class="ep-reward-type">
                                                <span class="pts-badge pts-badge-secondary" style="font-size: 0.7rem;">{{ $reward->reward_type->label() }}</span>
                                            </div>
                                            <div class="ep-reward-cost {{ $canAfford ? 'affordable' : '' }}">
                                                <i class="feather icon-star"></i> {{ number_format($reward->points_required) }} نقطة
                                            </div>
                                            @if(!$canAfford)
                                                <div class="ep-reward-locked">
                                                    <i class="feather icon-lock"></i>
                                                    تحتاج {{ number_format($reward->points_required - $currentBalance) }} نقطة
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="pts-empty-state-full">
                                <div class="empty-icon"><i class="feather icon-gift"></i></div>
                                <h4>لا توجد مكافآت متاحة</h4>
                                <p>استمر في جمع النقاط! ستظهر المكافآت المتاحة هنا</p>
                            </div>
                        @endif
                    </div>

                    {{-- ==================== تبويب التحديات ==================== --}}
                    <div class="tab-pane fade" id="challenges" role="tabpanel">
                        {{-- إحصائيات التحديات --}}
                        <div class="ep-mini-stats mb-4">
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #00cfe8 0%, #1edff0 100%);">
                                    <i class="feather icon-zap"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-info">{{ $challengeStats['active'] }}</span>
                                    <span class="ep-mini-stat-label">تحديات نشطة</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);">
                                    <i class="feather icon-check-circle"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-success">{{ $challengeStats['completed'] }}</span>
                                    <span class="ep-mini-stat-label">مكتملة</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);">
                                    <i class="feather icon-users"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-primary">{{ $challengeStats['total_participated'] }}</span>
                                    <span class="ep-mini-stat-label">إجمالي المشاركات</span>
                                </div>
                            </div>
                            <div class="ep-mini-stat">
                                <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #ff9f43 0%, #ffb976 100%);">
                                    <i class="feather icon-star"></i>
                                </div>
                                <div class="ep-mini-stat-info">
                                    <span class="ep-mini-stat-value text-warning">{{ number_format($challengeStats['total_points_from_challenges']) }}</span>
                                    <span class="ep-mini-stat-label">نقاط من التحديات</span>
                                </div>
                            </div>
                        </div>

                        {{-- التحديات النشطة --}}
                        @if($activeChallenges->isNotEmpty())
                            <h6 class="mb-3 font-weight-bold text-success"><i class="feather icon-zap mr-1"></i> التحديات النشطة</h6>
                            @foreach($activeChallenges as $participation)
                                @if($participation->challenge)
                                <div class="card border-success mb-3" style="border-radius: 12px; border-right: 4px solid #28c76f !important; background: linear-gradient(135deg, #f0fff4 0%, #fff 100%);">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="font-weight-bold mb-1">
                                                    <i class="feather icon-target text-success mr-1"></i>
                                                    {{ $participation->challenge->name }}
                                                </h6>
                                                @if($participation->challenge->description)
                                                    <p class="text-muted mb-2 small">{{ $participation->challenge->description }}</p>
                                                @endif
                                            </div>
                                            <div class="text-left">
                                                <span class="pts-badge pts-badge-success">
                                                    <i class="feather icon-star"></i> {{ number_format($participation->challenge->reward_points ?? 0) }} نقطة
                                                </span>
                                                <div class="mt-1">
                                                    <span class="pts-badge pts-badge-info">جارٍ</span>
                                                </div>
                                            </div>
                                        </div>
                                        @if($participation->current_progress !== null && $participation->challenge->target_value)
                                            @php
                                                $progress = min(100, ($participation->current_progress / $participation->challenge->target_value) * 100);
                                            @endphp
                                            <div class="progress mb-2" style="height: 10px; border-radius: 5px;">
                                                <div class="progress-bar" role="progressbar"
                                                     style="width: {{ $progress }}%; background: linear-gradient(90deg, #28c76f 0%, #48da89 100%);"
                                                     aria-valuenow="{{ $participation->current_progress }}"
                                                     aria-valuemin="0"
                                                     aria-valuemax="{{ $participation->challenge->target_value }}">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    التقدم: <strong>{{ $participation->current_progress }}</strong> / {{ $participation->challenge->target_value }}
                                                </small>
                                                <small class="font-weight-bold {{ $progress >= 100 ? 'text-success' : 'text-primary' }}">
                                                    {{ number_format($progress, 1) }}%
                                                </small>
                                            </div>
                                        @endif
                                        <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: 12px;">
                                            @if($participation->challenge->start_date || $participation->challenge->end_date)
                                                <small class="text-muted">
                                                    <i class="feather icon-calendar mr-1"></i>
                                                    {{ $participation->challenge->start_date?->format('Y-m-d') }} — {{ $participation->challenge->end_date?->format('Y-m-d') }}
                                                </small>
                                            @endif
                                            @if($participation->challenge->end_date)
                                                @php
                                                    $daysRemaining = max(0, now()->diffInDays($participation->challenge->end_date, false));
                                                @endphp
                                                <span class="badge {{ $daysRemaining <= 3 ? 'badge-danger' : ($daysRemaining <= 7 ? 'badge-warning' : 'badge-info') }} badge-pill">
                                                    {{ $daysRemaining }} يوم متبقي
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        @endif

                        {{-- التحديات المكتملة --}}
                        @if($completedChallenges->isNotEmpty())
                            <h6 class="mb-3 mt-4 font-weight-bold text-muted"><i class="feather icon-check-circle mr-1"></i> التحديات المكتملة</h6>
                            @foreach($completedChallenges as $participation)
                                @if($participation->challenge)
                                <div class="card border-light mb-2" style="border-radius: 12px; opacity: 0.85;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="feather icon-check-circle text-success mr-1"></i>
                                                    {{ $participation->challenge->name }}
                                                </h6>
                                                @if($participation->completed_at)
                                                    <small class="text-muted">
                                                        <i class="feather icon-clock mr-1"></i>
                                                        اكتمل في: {{ $participation->completed_at->format('Y-m-d') }}
                                                    </small>
                                                @endif
                                            </div>
                                            <div class="text-left">
                                                @if($participation->points_awarded > 0)
                                                    <span class="pts-badge pts-badge-success">
                                                        +{{ number_format($participation->points_awarded) }} نقطة
                                                    </span>
                                                @endif
                                                <span class="pts-badge pts-badge-success">مكتمل ✓</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        @endif

                        @if($activeChallenges->isEmpty() && $completedChallenges->isEmpty())
                            <div class="pts-empty-state-full">
                                <div class="empty-icon"><i class="feather icon-target"></i></div>
                                <h4>لا توجد تحديات</h4>
                                <p>لم تشارك في أي تحديات بعد</p>
                            </div>
                        @endif
                    </div>

                    {{-- ==================== تبويب الإنجازات ==================== --}}
                    <div class="tab-pane fade" id="achievements" role="tabpanel">
                        @if($achievements->isNotEmpty())
                            {{-- إحصائيات الإنجازات --}}
                            <div class="ep-mini-stats mb-4">
                                <div class="ep-mini-stat">
                                    <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);">
                                        <i class="feather icon-award"></i>
                                    </div>
                                    <div class="ep-mini-stat-info">
                                        <span class="ep-mini-stat-value text-success">{{ $achievements->count() }}</span>
                                        <span class="ep-mini-stat-label">إنجاز محقق</span>
                                    </div>
                                </div>
                                <div class="ep-mini-stat">
                                    <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);">
                                        <i class="feather icon-star"></i>
                                    </div>
                                    <div class="ep-mini-stat-info">
                                        <span class="ep-mini-stat-value text-primary">{{ number_format($achievements->sum('points_awarded')) }}</span>
                                        <span class="ep-mini-stat-label">نقاط من الإنجازات</span>
                                    </div>
                                </div>
                                <div class="ep-mini-stat">
                                    <div class="ep-mini-stat-icon" style="background: linear-gradient(135deg, #ff9f43 0%, #ffb976 100%);">
                                        <i class="feather icon-trending-up"></i>
                                    </div>
                                    <div class="ep-mini-stat-info">
                                        @php
                                            $achievedUniqueIds = $achievements->pluck('achievement_id')->unique()->count();
                                            $totalAvailable = $availableAchievements->count();
                                            $achievementProgressPercent = $totalAvailable > 0 ? round(($achievedUniqueIds / $totalAvailable) * 100) : 0;
                                        @endphp
                                        <span class="ep-mini-stat-value text-warning">{{ $achievementProgressPercent }}%</span>
                                        <span class="ep-mini-stat-label">نسبة الإنجاز</span>
                                    </div>
                                </div>
                            </div>

                            {{-- سجل الإنجازات --}}
                            <div class="ep-section-header mb-3">
                                <div class="ep-section-icon" style="background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);">
                                    <i class="feather icon-award"></i>
                                </div>
                                <h6 class="mb-0 font-weight-bold">الإنجازات المحققة</h6>
                            </div>
                            <div class="pts-timeline">
                                @foreach($achievements as $ua)
                                    @if($ua->achievement)
                                        <div class="pts-timeline-item">
                                            <div class="pts-timeline-marker success">
                                                <i class="feather icon-award"></i>
                                            </div>
                                            <div class="pts-timeline-content">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                    <div class="mb-2">
                                                        <h6 class="mb-2 d-flex align-items-center">
                                                            @if($ua->achievement->icon)
                                                                <span style="margin-left: 8px;">@include('ecosystem::points.partials.achievement-icon', ['icon' => $ua->achievement->icon])</span>
                                                            @endif
                                                            <strong>{{ $ua->achievement->name }}</strong>
                                                        </h6>
                                                        @if($ua->achievement->description)
                                                            <p class="text-muted mb-1 small">{{ $ua->achievement->description }}</p>
                                                        @endif
                                                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                                            @if($ua->achievement->category)
                                                                <span class="pts-badge pts-badge-secondary">
                                                                    <i class="feather icon-tag"></i> {{ $ua->achievement->category }}
                                                                </span>
                                                            @endif
                                                            <span class="pts-badge pts-badge-success">
                                                                <i class="feather icon-star"></i> +{{ number_format($ua->points_awarded) }} نقطة
                                                            </span>
                                                            @if($ua->achievement_value)
                                                                <span class="pts-badge pts-badge-info">
                                                                    <i class="feather icon-bar-chart-2"></i> القيمة: {{ number_format($ua->achievement_value) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="text-left">
                                                        <div class="text-muted small">
                                                            <i class="feather icon-clock ml-1"></i>
                                                            {{ $ua->achieved_at ? $ua->achieved_at->format('Y-m-d H:i') : $ua->created_at->format('Y-m-d H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="pts-empty-state-full">
                                <div class="empty-icon"><i class="feather icon-target"></i></div>
                                <h4>لا توجد إنجازات بعد</h4>
                                <p>سيتم عرض الإنجازات هنا عند تحقيقها</p>
                            </div>
                        @endif

                        {{-- جميع الإنجازات المتاحة --}}
                        @if($availableAchievements->count() > 0)
                            <hr class="my-4">
                            <h6 class="mb-3 font-weight-bold"><i class="feather icon-target mr-1"></i> جميع الإنجازات المتاحة</h6>
                            @php
                                $achievedAchievementIds = $achievements->pluck('achievement_id')->unique()->toArray();
                            @endphp
                            <div class="row">
                                @foreach($availableAchievements as $achievement)
                                    @php
                                        $isAchieved = in_array($achievement->id, $achievedAchievementIds);
                                    @endphp
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="card border {{ $isAchieved ? 'border-success' : 'border-light' }} h-100" style="{{ $isAchieved ? 'background: #28c76f08;' : 'opacity: 0.7;' }}">
                                            <div class="card-body p-3 text-center">
                                                @if($isAchieved)
                                                    <div class="position-absolute" style="top: 8px; left: 8px;">
                                                        <span class="badge badge-success badge-pill px-2 py-1" style="font-size: 0.7rem;">
                                                            <i class="feather icon-check"></i> محقق
                                                        </span>
                                                    </div>
                                                @endif
                                                <div class="mb-2">
                                                    @include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '2rem'])
                                                </div>
                                                <h6 class="mb-1" style="font-size: 0.85rem;">{{ $achievement->name }}</h6>
                                                @if($achievement->description)
                                                    <small class="text-muted d-block mb-2">{{ Str::limit($achievement->description, 50) }}</small>
                                                @endif
                                                <span class="pts-badge pts-badge-{{ $isAchieved ? 'success' : 'secondary' }}" style="font-size: 0.75rem;">
                                                    <i class="feather icon-star"></i> {{ number_format($achievement->bonus_points) }} نقطة
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ==================== تبويب طلبات المكافآت ==================== --}}
                    <div class="tab-pane fade" id="redemptions" role="tabpanel">
                        @if($redemptions->isNotEmpty())
                            <div class="pts-timeline">
                                @foreach($redemptions as $redemption)
                                    @php
                                        $statusVal = is_object($redemption->status) ? $redemption->status->value : $redemption->status;
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'approved' => 'info',
                                            'rejected' => 'danger',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'في الانتظار',
                                            'approved' => 'تمت الموافقة',
                                            'rejected' => 'مرفوض',
                                            'delivered' => 'تم التسليم',
                                            'cancelled' => 'ملغي',
                                        ];
                                        $statusIcons = [
                                            'pending' => 'icon-clock',
                                            'approved' => 'icon-thumbs-up',
                                            'rejected' => 'icon-x',
                                            'delivered' => 'icon-check',
                                            'cancelled' => 'icon-x-circle',
                                        ];
                                    @endphp
                                    <div class="pts-timeline-item">
                                        <div class="pts-timeline-marker {{ $statusColors[$statusVal] ?? 'secondary' }}">
                                            <i class="feather {{ $statusIcons[$statusVal] ?? 'icon-gift' }}"></i>
                                        </div>
                                        <div class="pts-timeline-content">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                <div class="mb-2">
                                                    <h6 class="mb-1 font-weight-bold">
                                                        <i class="feather icon-gift mr-1"></i>
                                                        {{ $redemption->reward->name ?? 'مكافأة محذوفة' }}
                                                    </h6>
                                                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                                        <span class="pts-badge pts-badge-warning">
                                                            <i class="feather icon-star"></i> {{ number_format($redemption->points_used) }} نقطة
                                                        </span>
                                                        @if($redemption->quantity > 1)
                                                            <span class="pts-badge pts-badge-secondary">
                                                                الكمية: {{ $redemption->quantity }}
                                                            </span>
                                                        @endif
                                                        <span class="pts-badge pts-badge-{{ $statusColors[$statusVal] ?? 'secondary' }}">
                                                            {{ $statusLabels[$statusVal] ?? $statusVal }}
                                                        </span>
                                                    </div>
                                                    @if($redemption->notes)
                                                        <p class="text-muted small mt-1 mb-0">{{ $redemption->notes }}</p>
                                                    @endif
                                                </div>
                                                <div class="text-left">
                                                    <div class="text-muted small">
                                                        <i class="feather icon-clock ml-1"></i>
                                                        {{ $redemption->created_at->format('Y-m-d H:i') }}
                                                    </div>
                                                    @if($redemption->approver)
                                                        <div class="text-muted small mt-1">
                                                            <i class="feather icon-user ml-1"></i>
                                                            {{ $redemption->approver->name ?? '' }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="pts-empty-state-full">
                                <div class="empty-icon"><i class="feather icon-shopping-cart"></i></div>
                                <h4>لا توجد طلبات مكافآت</h4>
                                <p>اطلب مكافأة من تبويب "المكافآت" وسيظهر طلبك هنا</p>
                            </div>
                        @endif
                    </div>

                    {{-- ==================== تبويب المستويات ==================== --}}
                    <div class="tab-pane fade" id="tiers" role="tabpanel">
                        @if($allTiers->isNotEmpty())
                            <div class="row">
                                @foreach($allTiers as $tier)
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="pts-tier-card {{ $currentTier && $currentTier->id == $tier->id ? 'active' : '' }}">
                                            @if($currentTier && $currentTier->id == $tier->id)
                                                <div class="current-badge">
                                                    <i class="feather icon-check"></i> مستواك الحالي
                                                </div>
                                            @endif
                                            <div class="tier-icon" style="background: linear-gradient(135deg, {{ $tier->color ?? '#7367f0' }} 0%, {{ $tier->color ?? '#7367f0' }}99 100%);">
                                                <i class="feather icon-award"></i>
                                            </div>
                                            <h5 class="tier-name">{{ $tier->name }}</h5>
                                            <div class="tier-points">
                                                <span class="min">{{ number_format($tier->min_points) }}</span>
                                                <span class="separator">-</span>
                                                <span class="max">{{ $tier->max_points ? number_format($tier->max_points) : '∞' }}</span>
                                            </div>
                                            <div class="tier-unit">نقطة</div>
                                            @if($tier->benefits && count($tier->benefits) > 0)
                                                <div class="tier-benefits">
                                                    @foreach(array_slice($tier->benefits, 0, 3) as $benefit)
                                                        <span class="benefit-tag light">
                                                            <i class="feather icon-check-circle"></i>{{ $benefit }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @php
                                                $tierReached = $currentBalance >= $tier->min_points;
                                            @endphp
                                            @if($tierReached && (!$currentTier || $currentTier->id != $tier->id))
                                                <div class="mt-2">
                                                    <span class="pts-badge pts-badge-success" style="font-size: 0.75rem;">
                                                        <i class="feather icon-check"></i> تم تجاوزه
                                                    </span>
                                                </div>
                                            @elseif(!$tierReached)
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        تحتاج {{ number_format($tier->min_points - $currentBalance) }} نقطة إضافية
                                                    </small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- سجل الترقيات --}}
                            @if($tierUpgrades->isNotEmpty())
                                <hr class="my-4">
                                <h6 class="mb-3 font-weight-bold"><i class="feather icon-trending-up mr-1"></i> سجل الترقيات</h6>
                                <div class="pts-timeline">
                                    @foreach($tierUpgrades as $upgrade)
                                        <div class="pts-timeline-item">
                                            <div class="pts-timeline-marker {{ $upgrade->isUpgrade() ? 'success' : 'danger' }}">
                                                <i class="feather {{ $upgrade->isUpgrade() ? 'icon-arrow-up' : 'icon-arrow-down' }}"></i>
                                            </div>
                                            <div class="pts-timeline-content">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                    <div class="mb-2">
                                                        <h6 class="mb-2">
                                                            @if($upgrade->oldTier)
                                                                <span class="pts-badge" style="background-color: {{ $upgrade->oldTier->color }}22; color: {{ $upgrade->oldTier->color }};">
                                                                    {{ $upgrade->oldTier->name }}
                                                                </span>
                                                            @else
                                                                <span class="pts-badge pts-badge-secondary">بدون مستوى</span>
                                                            @endif
                                                            <i class="feather icon-arrow-left mx-2 text-muted"></i>
                                                            <span class="pts-badge" style="background-color: {{ $upgrade->newTier->color }}22; color: {{ $upgrade->newTier->color }};">
                                                                {{ $upgrade->newTier->name }}
                                                            </span>
                                                        </h6>
                                                        <p class="text-muted mb-0">
                                                            <i class="feather icon-star ml-1"></i>
                                                            النقاط عند الترقية: <strong>{{ number_format($upgrade->points_at_upgrade) }}</strong>
                                                        </p>
                                                    </div>
                                                    <div class="text-left">
                                                        @if($upgrade->upgrade_type)
                                                            <span class="pts-badge {{ $upgrade->upgrade_type->value == 'automatic' ? 'pts-badge-info' : 'pts-badge-warning' }}">
                                                                <i class="feather {{ $upgrade->upgrade_type->value == 'automatic' ? 'icon-zap' : 'icon-user' }}"></i>
                                                                {{ $upgrade->upgrade_type->label() }}
                                                            </span>
                                                        @endif
                                                        <div class="text-muted small mt-2">
                                                            <i class="feather icon-clock ml-1"></i>
                                                            {{ $upgrade->created_at->format('Y-m-d H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="pts-empty-state-full">
                                <div class="empty-icon"><i class="feather icon-layers"></i></div>
                                <h4>لا توجد مستويات</h4>
                                <p>لم يتم إعداد المستويات بعد</p>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- مودال طلب المكافأة --}}
    <div class="modal fade" id="requestRewardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); color: white; border: none; padding: 1.5rem 2rem;">
                    <h5 class="modal-title" style="font-weight: 600;">
                        <i class="feather icon-gift"></i>
                        طلب مكافأة
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('employee.profile.request-reward') }}" method="POST" id="rewardForm">
                    @csrf
                    <div class="modal-body" style="padding: 2rem;">
                        <input type="hidden" name="reward_id" id="modal_reward_id">
                        <input type="hidden" name="quantity" value="1">

                        <div class="text-center mb-4">
                            <div class="ep-modal-icon-wrapper mx-auto mb-3">
                                <div id="modal_reward_icon"></div>
                            </div>
                            <h5 id="modal_reward_name" class="font-weight-bold mb-1"></h5>
                            <span id="modal_reward_type_badge" class="pts-badge pts-badge-secondary mb-2 d-inline-block"></span>
                            <p class="text-muted small mb-0" id="modal_reward_desc"></p>
                        </div>

                        <div class="ep-modal-stats mb-4">
                            <div class="ep-modal-stat">
                                <div class="ep-modal-stat-icon" style="background: rgba(115, 103, 240, 0.1); color: #7367f0;">
                                    <i class="feather icon-shopping-bag"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">التكلفة</small>
                                    <strong id="modal_reward_points" class="text-primary" style="font-size: 1.1rem;"></strong> <small>نقطة</small>
                                </div>
                            </div>
                            <div class="ep-modal-stat-divider"></div>
                            <div class="ep-modal-stat">
                                <div class="ep-modal-stat-icon" style="background: rgba(40, 199, 111, 0.1); color: #28c76f;">
                                    <i class="feather icon-star"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">رصيدك</small>
                                    <strong class="text-success" style="font-size: 1.1rem;">{{ number_format($currentBalance) }}</strong> <small>نقطة</small>
                                </div>
                            </div>
                        </div>

                        <div id="modal_balance_after" class="alert alert-info text-center mb-3" style="display: none; border-radius: 12px; border: none; background: rgba(40, 199, 111, 0.08);">
                            <i class="feather icon-info"></i>
                            <span>الرصيد المتبقي بعد الطلب: <strong id="modal_remaining_points" class="text-success"></strong> نقطة</span>
                        </div>

                        <div id="modal_insufficient_points" class="alert alert-danger text-center mb-3" style="display: none; border-radius: 12px; border: none;">
                            <i class="feather icon-alert-circle"></i>
                            <strong>نقاط غير كافية!</strong>
                            <p class="mb-0 small">تحتاج <span id="modal_points_needed" class="font-weight-bold"></span> نقطة إضافية</p>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold" style="font-size: 0.85rem;">
                                <i class="feather icon-message-square mr-1"></i> ملاحظات (اختياري)
                            </label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="أضف أي ملاحظات إضافية..." style="border-radius: 12px; border: 2px solid #eee; transition: border-color 0.2s;" onfocus="this.style.borderColor='#7367f0'" onblur="this.style.borderColor='#eee'"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 2px solid #f5f5f5; padding: 1rem 2rem; gap: 10px;">
                        <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius: 10px; min-width: 100px;">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="modal_submit_btn" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); border: none; border-radius: 10px; min-width: 160px; padding: 10px 20px; font-weight: 600;">
                            <i class="feather icon-check"></i> تأكيد الطلب
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- أنماط تحسين التصميم --}}
    <style>
    /* ===================================
       Employee Profile Enhanced Styles
       =================================== */

    /* Month Filter Card */
    .ep-month-filter-card {
        border-radius: 14px !important;
        box-shadow: 0 4px 15px rgba(115, 103, 240, 0.08) !important;
        border: 1px solid rgba(115, 103, 240, 0.12) !important;
        background: linear-gradient(135deg, #fafbff 0%, #f5f3ff 100%);
    }
    .ep-filter-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1rem;
    }
    .ep-month-select {
        border-radius: 10px !important;
        border: 2px solid #e8e6f0 !important;
        font-weight: 600 !important;
        padding: 0.4rem 0.8rem !important;
        transition: border-color 0.2s !important;
        background: #fff !important;
    }
    .ep-month-select:focus {
        border-color: #7367f0 !important;
        box-shadow: 0 0 0 2px rgba(115, 103, 240, 0.15) !important;
    }
    .ep-reward-card.past-month {
        opacity: 0.6;
        cursor: not-allowed !important;
        pointer-events: none;
    }

    /* Enhanced Tabs Card */
    .ep-tabs-card {
        border-radius: 20px !important;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06) !important;
    }
    .ep-tabs-header {
        background: #fff !important;
        padding: 0 !important;
        border-bottom: none !important;
    }
    .ep-nav {
        border-bottom: 2px solid #f0f0f0 !important;
        padding: 0 1rem;
    }
    .ep-nav .nav-link {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        gap: 6px !important;
        padding: 1.2rem 1rem !important;
        border: none !important;
        border-bottom: 3px solid transparent !important;
        background: transparent !important;
        color: #b4b7bd !important;
        transition: all 0.3s ease !important;
        border-radius: 0 !important;
        position: relative;
    }
    .ep-nav .nav-link:hover {
        color: #7367f0 !important;
        background: rgba(115, 103, 240, 0.03) !important;
    }
    .ep-nav .nav-link.active {
        color: #7367f0 !important;
        border-bottom-color: #7367f0 !important;
        background: rgba(115, 103, 240, 0.04) !important;
    }
    .ep-tab-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f5f5;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    .ep-nav .nav-link.active .ep-tab-icon {
        background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(115, 103, 240, 0.3);
    }
    .ep-nav .nav-link:hover .ep-tab-icon {
        background: rgba(115, 103, 240, 0.1);
        color: #7367f0;
    }
    .ep-tab-text {
        font-size: 0.8rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .ep-tab-badge {
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 700;
        line-height: 1.4;
    }
    .ep-tab-badge.primary { background: rgba(115, 103, 240, 0.12); color: #7367f0; }
    .ep-tab-badge.success { background: rgba(40, 199, 111, 0.12); color: #28c76f; }
    .ep-tab-badge.warning { background: rgba(255, 159, 67, 0.12); color: #ff9f43; }
    .ep-tab-badge.info { background: rgba(0, 207, 232, 0.12); color: #00cfe8; }
    .ep-tab-badge.danger { background: rgba(234, 84, 85, 0.12); color: #ea5455; }

    /* Mini Stats Row (inside tabs) */
    .ep-mini-stats {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }
    .ep-mini-stat {
        flex: 1;
        min-width: 160px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        background: #fff;
        border-radius: 14px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        transition: all 0.25s ease;
    }
    .ep-mini-stat:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        transform: translateY(-2px);
    }
    .ep-mini-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .ep-mini-stat-info {
        display: flex;
        flex-direction: column;
    }
    .ep-mini-stat-value {
        font-size: 1.3rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .ep-mini-stat-label {
        font-size: 0.75rem;
        color: #82868b;
        font-weight: 500;
    }

    /* Section Headers */
    .ep-section-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .ep-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    /* Reward Cards */
    .ep-reward-card {
        background: #fff;
        border-radius: 16px;
        border: 2px solid #f0f0f0;
        padding: 24px 16px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        height: 100%;
    }
    .ep-reward-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }
    .ep-reward-card.affordable {
        border-color: #28c76f55;
        background: linear-gradient(135deg, #f0fff4 0%, #fff 100%);
    }
    .ep-reward-card.affordable:hover {
        border-color: #28c76f;
        box-shadow: 0 12px 30px rgba(40, 199, 111, 0.15);
    }
    .ep-reward-card.locked {
        opacity: 0.75;
    }
    .ep-reward-card.locked:hover {
        opacity: 1;
    }
    .ep-reward-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);
        color: #fff;
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 8px;
        font-weight: 600;
    }
    .ep-reward-icon {
        font-size: 3rem;
        margin-bottom: 12px;
        line-height: 1;
    }
    .ep-reward-name {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 4px;
        color: #5e5873;
    }
    .ep-reward-desc {
        font-size: 0.75rem;
        color: #b4b7bd;
        margin-bottom: 8px;
    }
    .ep-reward-type {
        margin-bottom: 10px;
    }
    .ep-reward-cost {
        font-size: 0.85rem;
        font-weight: 700;
        color: #82868b;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 6px 0;
        border-top: 1px solid #f0f0f0;
    }
    .ep-reward-cost.affordable {
        color: #28c76f;
    }
    .ep-reward-locked {
        font-size: 0.7rem;
        color: #ea5455;
        margin-top: 6px;
    }

    /* Modal Enhancements */
    .ep-modal-icon-wrapper {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f8f7ff 0%, #eee9ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
    }
    .ep-modal-stats {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 20px;
        background: #f8f9fa;
        border-radius: 14px;
    }
    .ep-modal-stat {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ep-modal-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .ep-modal-stat-divider {
        width: 1px;
        height: 40px;
        background: #e0e0e0;
    }

    /* Responsive for Tabs */
    @media (max-width: 768px) {
        .ep-nav {
            overflow-x: auto;
            flex-wrap: nowrap !important;
            -webkit-overflow-scrolling: touch;
        }
        .ep-nav .nav-item {
            flex: 0 0 auto;
        }
        .ep-nav .nav-link {
            padding: 0.8rem 0.7rem !important;
        }
        .ep-tab-icon {
            width: 30px;
            height: 30px;
            font-size: 0.85rem;
        }
        .ep-tab-text {
            font-size: 0.7rem;
        }
        .ep-mini-stat {
            min-width: calc(50% - 12px);
        }
        .ep-modal-stats {
            flex-direction: column;
        }
        .ep-modal-stat-divider {
            width: 100%;
            height: 1px;
        }
    }
    </style>
@endsection

@push('scripts')
<script>
// Month filter auto-submit
$(document).ready(function() {
    $('#month_year_select').on('change', function() {
        var parts = $(this).val().split('-');
        if (parts.length === 2) {
            $('#filter_month').val(parts[0]);
            $('#filter_year').val(parts[1]);
            $('#monthFilterForm').submit();
        }
    });

    // Reward card click handler (using data attributes to avoid HTML escaping issues)
    $(document).on('click', '.ep-reward-card[data-reward-id]', function() {
        var $card = $(this);
        var rewardIcon = $card.find('.ep-reward-icon').html().trim();
        showRewardModal(
            parseInt($card.data('reward-id')),
            $card.data('reward-name'),
            parseInt($card.data('reward-points')),
            rewardIcon,
            $card.data('reward-desc') || '',
            $card.data('reward-type') || ''
        );
    });
});

function showRewardModal(rewardId, rewardName, rewardPoints, rewardIcon, rewardDesc, rewardType) {
    document.getElementById('modal_reward_id').value = rewardId;
    document.getElementById('modal_reward_name').textContent = rewardName;
    document.getElementById('modal_reward_points').textContent = rewardPoints.toLocaleString();
    document.getElementById('modal_reward_icon').innerHTML = rewardIcon;
    document.getElementById('modal_reward_desc').textContent = rewardDesc || '';

    // Reward type badge
    var typeBadge = document.getElementById('modal_reward_type_badge');
    if (rewardType) {
        typeBadge.textContent = rewardType;
        typeBadge.style.display = 'inline-block';
    } else {
        typeBadge.style.display = 'none';
    }

    var employeePoints = {{ $currentBalance }};
    var submitBtn = document.getElementById('modal_submit_btn');
    var insufficientDiv = document.getElementById('modal_insufficient_points');
    var balanceAfterDiv = document.getElementById('modal_balance_after');
    var remainingPoints = document.getElementById('modal_remaining_points');
    var pointsNeeded = document.getElementById('modal_points_needed');

    if (employeePoints < rewardPoints) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="feather icon-x"></i> نقاط غير كافية';
        submitBtn.style.background = '#82868b';
        insufficientDiv.style.display = 'block';
        if (balanceAfterDiv) balanceAfterDiv.style.display = 'none';
        pointsNeeded.textContent = (rewardPoints - employeePoints).toLocaleString();
    } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="feather icon-check"></i> تأكيد الطلب';
        submitBtn.style.background = 'linear-gradient(135deg, #7367f0 0%, #9e95f5 100%)';
        insufficientDiv.style.display = 'none';
        if (balanceAfterDiv) {
            balanceAfterDiv.style.display = 'block';
            remainingPoints.textContent = (employeePoints - rewardPoints).toLocaleString();
        }
    }

    $('#requestRewardModal').modal('show');
}
</script>
@endpush
