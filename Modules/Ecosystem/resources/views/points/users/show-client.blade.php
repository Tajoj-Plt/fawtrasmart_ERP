@extends('ecosystem::master')

@section('title', 'نقاط العميل: ' . ($client->full_name ?: $client->trade_name))

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')
    
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">نقاط العميل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.users.clients') }}">نقاط العملاء</a></li>
                            <li class="breadcrumb-item active">{{ $client->full_name ?: $client->trade_name }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.users.clients') }}" class="pts-btn pts-btn-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="content-body">
        {{-- شريط تقدم النقاط التراكمية --}}
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.monthly-tier-progress', ['monthlyPointsData' => $monthlyPointsData, 'progressTitle' => 'تقدم النقاط التراكمية'])
            </div>
        </div>

        {{-- شريط الإنجازات التراكمية --}}
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.achievements-ribbon', [
                    'availableAchievements' => $availableAchievements,
                    'userAchievements' => $userAchievements,
                    'ribbonTitle' => 'الإنجازات التراكمية',
                    'ribbonSubtitle' => 'إنجازاتك منذ البداية - تراكمية',
                ])
            </div>
        </div>

        <!-- بطاقة معلومات العميل مع المستوى -->
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-left">
                        <div class="avatar-wrapper mb-2">
                            @if($client->avatar)
                                <img src="{{ asset($client->avatar) }}" alt="{{ $client->full_name }}" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover;">
                            @else
                                <div class="avatar-placeholder rounded-circle shadow d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 2.5rem; color: #fff;">
                                    {{ substr($client->first_name ?? $client->trade_name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <h4 class="mb-1 font-weight-bold">{{ $client->full_name ?: $client->trade_name }}</h4>
                        <p class="text-muted mb-1"><i class="feather icon-mail mr-1"></i>{{ $client->email }}</p>
                        <p class="text-muted mb-2"><i class="feather icon-phone mr-1"></i>{{ $client->phone ?: $client->mobile }}</p>
                        
                        @if($currentTier)
                            <span class="badge badge-pill px-3 py-2 shadow-sm" style="background: linear-gradient(135deg, {{ $currentTier->color }} 0%, {{ $currentTier->color }}dd 100%); color: #fff; font-size: 1rem;">
                                <i class="feather icon-award mr-1"></i> {{ $currentTier->name }}
                            </span>
                        @else
                            <span class="badge badge-pill badge-secondary px-3 py-2" style="font-size: 1rem;">
                                <i class="feather icon-user mr-1"></i> بدون مستوى
                            </span>
                        @endif
                    </div>

                    <div class="col-md-5">
                        <div class="client-details">
                            @if($client->trade_name)
                                <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted"><i class="feather icon-briefcase mr-1"></i> الاسم التجاري</span>
                                    <strong>{{ $client->trade_name }}</strong>
                                </div>
                            @endif
                            
                            @if($client->referral_code)
                                <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted"><i class="feather icon-link mr-1"></i> كود الإحالة</span>
                                    <strong class="text-primary">{{ $client->referral_code }}</strong>
                                </div>
                            @endif
                            
                            @if($client->referrals_count !== null)
                                <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                    <span class="text-muted"><i class="feather icon-users mr-1"></i> عدد الإحالات</span>
                                    <strong>{{ $client->referrals_count }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- المستوى التالي -->
                        @if($nextTier)
                            @php
                                $currentPoints = $stats['current_balance'] ?? 0;
                                $currentTierMin = $currentTier ? $currentTier->min_points : 0;
                                $nextTierMin = $nextTier->min_points;
                                $pointsNeededForNextTier = $pointsToNextTier ?? 0;
                                
                                // حساب نسبة التقدم (صافي النقاط: مكتسب - مستخدم)
                                $range = $nextTierMin - $currentTierMin;
                                if ($range > 0) {
                                    $progress = (($currentPoints - $currentTierMin) / $range) * 100;
                                    $progressToNextTier = min(100, max(0, $progress));
                                } else {
                                    $progressToNextTier = 0;
                                }
                            @endphp
                            <div class="next-tier-card p-3 rounded" style="background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);">
                                <h6 class="text-muted mb-2"><i class="feather icon-target mr-1"></i> المستوى التالي</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge mr-2" style="background-color: {{ $nextTier->color }}; color: #fff;">
                                        {{ $nextTier->name }}
                                    </span>
                                    <span class="text-muted">{{ number_format($nextTier->min_points) }} نقطة</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: {{ $progressToNextTier }}%; background: linear-gradient(90deg, {{ $nextTier->color }} 0%, {{ $nextTier->color }}aa 100%);"
                                         aria-valuenow="{{ $progressToNextTier }}"></div>
                                </div>
                                <small class="text-muted">
                                    <i class="feather icon-zap"></i> {{ number_format($pointsNeededForNextTier) }} نقطة للترقية
                                </small>
                            </div>
                        @else
                            <div class="max-tier-card p-3 rounded text-center" style="background: linear-gradient(135deg, #28c76f22 0%, #28c76f11 100%); border: 1px solid #28c76f44;">
                                <i class="feather icon-award text-success" style="font-size: 2rem;"></i>
                                <h6 class="text-success mt-2 mb-0">أعلى مستوى!</h6>
                                <small class="text-muted">لقد وصلت للقمة</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات الإحصائيات الحديثة -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">الرصيد الحالي</div>
                <div class="stat-value">{{ number_format($stats['current_balance'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>نقطة متاحة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-trending-up"></i>
                </div>
                <div class="stat-label">إجمالي المكتسب</div>
                <div class="stat-value">{{ number_format($stats['total_earned'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-plus-circle"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-shopping-bag"></i>
                </div>
                <div class="stat-label">إجمالي المستخدم</div>
                <div class="stat-value">{{ number_format($stats['total_spent'] ?? 0) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-minus-circle"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-file-text"></i>
                </div>
                <div class="stat-label">إجمالي المعاملات</div>
                <div class="stat-value">{{ number_format($stats['total_transactions']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-list"></i>
                    <span>معاملة</span>
                </div>
            </div>
        </div>

        {{-- تحذير انتهاء صلاحية النقاط --}}
        @include('ecosystem::points.partials.expiry-warning-banner')

        <!-- التبويبات -->
        <div class="card table-card-modern">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs pts-nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#transactions" role="tab">
                            <i class="feather icon-list"></i> معاملات النقاط
                            <span class="badge badge-primary badge-pill">{{ $transactions->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#upgrades" role="tab">
                            <i class="feather icon-trending-up"></i> سجل الترقيات
                            <span class="badge badge-info badge-pill">{{ $upgradeLogs->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tiers" role="tab">
                            <i class="feather icon-award"></i> المستويات
                            <span class="badge badge-warning badge-pill">{{ $allTiers->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#achievements" role="tab">
                            <i class="feather icon-target"></i> الإنجازات
                            <span class="badge badge-success badge-pill">{{ $userAchievements->count() }}</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- تبويب المعاملات -->
                    <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                        <!-- فلاتر المعاملات -->
                        <div class="pts-filter-section">
                            <div class="filter-title">
                                <i class="feather icon-filter"></i>
                                خيارات التصفية
                            </div>
                            <form method="GET" action="{{ route('ecosystem.points.users.clients.show', $client->id) }}">
                                <div class="row">
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3 mb-lg-0">
                                        <div class="pts-form-group">
                                            <label>النوع</label>
                                            <select name="points_type" class="pts-form-control pts-select">
                                                <option value="">كل الأنواع</option>
                                                <option value="earned" {{ request('points_type') == 'earned' ? 'selected' : '' }}>مكتسبة</option>
                                                <option value="spent" {{ request('points_type') == 'spent' ? 'selected' : '' }}>مستخدمة</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3 mb-lg-0">
                                        <div class="pts-form-group">
                                            <label>الحالة</label>
                                            <select name="status" class="pts-form-control pts-select">
                                                <option value="">كل الحالات</option>
                                                @foreach($statuses as $status)
                                                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                                        {{ \App\Enums\TransactionStatus::from($status)->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 col-sm-12 d-flex align-items-end">
                                        <div class="pts-btn-group w-100">
                                            <button type="submit" class="pts-btn pts-btn-primary">
                                                <i class="feather icon-search"></i> بحث
                                            </button>
                                            <a href="{{ route('ecosystem.points.users.clients.show', $client->id) }}" class="pts-btn pts-btn-secondary">
                                                <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="pts-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 12%;">النوع</th>
                                        <th style="width: 12%;">النقاط</th>
                                        <th style="width: 12%;">الرصيد بعد</th>
                                        <th style="width: 30%;">الوصف</th>
                                        <th style="width: 12%;">الحالة</th>
                                        <th style="width: 17%;">التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $transaction)
                                        <tr>
                                            <td class="cell-id">{{ $transaction->id }}</td>
                                            <td>
                                                <span class="pts-badge pts-badge-{{ $transaction->points > 0 ? 'success' : 'warning' }}">
                                                    <i class="feather icon-{{ $transaction->points > 0 ? 'plus' : 'minus' }}-circle"></i>
                                                    {{ $transaction->action_type }}
                                                </span>
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
                                                <span class="cell-balance">{{ number_format($transaction->balance_after ?? 0) }}</span>
                                            </td>
                                            <td class="cell-description">{{ Str::limit($transaction->description ?? $transaction->action_type, 45) }}</td>
                                            <td>
                                                @php
                                                    $statusValue = is_object($transaction->status) ? $transaction->status->value : $transaction->status;
                                                @endphp
                                                @switch($statusValue)
                                                    @case('pending')
                                                        <span class="pts-badge pts-badge-warning">معلق</span>
                                                        @break
                                                    @case('completed')
                                                        <span class="pts-badge pts-badge-success">مكتمل</span>
                                                        @break
                                                    @case('cancelled')
                                                        <span class="pts-badge pts-badge-danger">ملغي</span>
                                                        @break
                                                    @default
                                                        <span class="pts-badge pts-badge-secondary">{{ $statusValue }}</span>
                                                @endswitch
                                            </td>
                                            <td class="cell-date">
                                                <i class="feather icon-clock"></i>
                                                {{ $transaction->created_at->format('Y-m-d H:i') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="pts-empty-state">
                                                    <i class="feather icon-inbox"></i>
                                                    <p>لا توجد معاملات</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="pts-pagination">
                            {{ $transactions->appends(request()->query())->links() }}
                        </div>
                    </div>

                    <!-- تبويب الترقيات -->
                    <div class="tab-pane fade" id="upgrades" role="tabpanel">
                        @if($upgradeLogs->count() > 0)
                            <div class="pts-timeline">
                                @foreach($upgradeLogs as $log)
                                    <div class="pts-timeline-item">
                                        <div class="pts-timeline-marker {{ $log->old_tier_id < $log->new_tier_id ? 'success' : 'danger' }}">
                                            <i class="feather {{ $log->old_tier_id < $log->new_tier_id ? 'icon-arrow-up' : 'icon-arrow-down' }}"></i>
                                        </div>
                                        <div class="pts-timeline-content">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                <div class="mb-2">
                                                    <h6 class="mb-2">
                                                        <span class="pts-badge" style="background: {{ $log->oldTier->color ?? '#ccc' }}; color: #fff;">
                                                            {{ $log->oldTier->name ?? 'لا يوجد' }}
                                                        </span>
                                                        <i class="feather icon-arrow-left mx-2 text-muted"></i>
                                                        <span class="pts-badge" style="background: {{ $log->newTier->color ?? '#ccc' }}; color: #fff;">
                                                            {{ $log->newTier->name ?? 'غير معروف' }}
                                                        </span>
                                                    </h6>
                                                    <p class="text-muted mb-1">
                                                        <i class="feather icon-star"></i>
                                                        النقاط عند الترقية: <strong>{{ number_format($log->points_at_upgrade) }}</strong>
                                                    </p>
                                                    @if($log->reason ?? false)
                                                        <p class="text-muted mb-0 small">
                                                            <i class="feather icon-info"></i> {{ $log->reason }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <div class="text-left">
                                                    @php
                                                        $upgradeTypeValue = is_object($log->upgrade_type) ? $log->upgrade_type->value : $log->upgrade_type;
                                                    @endphp
                                                    <span class="pts-badge {{ $upgradeTypeValue === 'automatic' ? 'pts-badge-info' : 'pts-badge-primary' }}">
                                                        <i class="feather {{ $upgradeTypeValue === 'automatic' ? 'icon-zap' : 'icon-user' }}"></i>
                                                        {{ $upgradeTypeValue === 'automatic' ? 'تلقائي' : 'يدوي' }}
                                                    </span>
                                                    <div class="text-muted small mt-2">
                                                        <i class="feather icon-clock"></i>
                                                        {{ $log->created_at->format('Y-m-d H:i') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="pts-empty-state-full">
                                <i class="feather icon-award"></i>
                                <h5>لا توجد ترقيات سابقة</h5>
                                <p>سيظهر سجل الترقيات هنا عند تغيير المستوى</p>
                            </div>
                        @endif
                    </div>

                    <!-- تبويب المستويات -->
                    <div class="tab-pane fade" id="tiers" role="tabpanel">
                        <div class="row">
                            @foreach($allTiers as $tier)
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="pts-tier-card {{ $currentTier && $currentTier->id == $tier->id ? 'active' : '' }}">
                                        @if($currentTier && $currentTier->id == $tier->id)
                                            <div class="pts-tier-current-badge">
                                                <i class="feather icon-check"></i> المستوى الحالي
                                            </div>
                                        @endif
                                        <div class="pts-tier-icon" style="background: linear-gradient(135deg, {{ $tier->color }} 0%, {{ $tier->color }}aa 100%);">
                                            <i class="feather icon-award"></i>
                                        </div>
                                        <h5 class="pts-tier-name">{{ $tier->name }}</h5>
                                        <div class="pts-tier-points">
                                            <span class="text-primary">{{ number_format($tier->min_points) }}</span>
                                            <span class="text-muted mx-2">-</span>
                                            <span class="text-success">{{ $tier->max_points ? number_format($tier->max_points) : '∞' }}</span>
                                            <small class="d-block text-muted mt-1">نقطة</small>
                                        </div>
                                        <div class="pts-tier-benefits">
                                            @if($tier->points_multiplier && $tier->points_multiplier != 1)
                                                <span class="pts-benefit-tag success">
                                                    <i class="feather icon-x"></i> {{ $tier->points_multiplier }} مضاعف
                                                </span>
                                            @endif
                                            @if($tier->discount_percentage)
                                                <span class="pts-benefit-tag primary">
                                                    <i class="feather icon-percent"></i> {{ $tier->discount_percentage }}% خصم
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- تبويب الإنجازات -->
                    <div class="tab-pane fade" id="achievements" role="tabpanel">
                        @if($userAchievements->count() > 0)
                            <!-- إحصائيات الإنجازات -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #28c76f11 0%, #28c76f22 100%); border: 1px solid #28c76f33;">
                                        <h3 class="text-success font-weight-bold mb-0">{{ $userAchievements->count() }}</h3>
                                        <small class="text-muted">إنجاز محقق</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #7367f011 0%, #7367f022 100%); border: 1px solid #7367f033;">
                                        <h3 class="text-primary font-weight-bold mb-0">{{ number_format($userAchievements->sum('points_awarded')) }}</h3>
                                        <small class="text-muted">نقاط من الإنجازات</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #ff9f4311 0%, #ff9f4322 100%); border: 1px solid #ff9f4333;">
                                        <h3 class="text-warning font-weight-bold mb-0">
                                            {{ $availableAchievements->count() > 0 ? round(($userAchievements->pluck('achievement_id')->unique()->count() / $availableAchievements->count()) * 100) : 0 }}%
                                        </h3>
                                        <small class="text-muted">نسبة الإنجاز</small>
                                    </div>
                                </div>
                            </div>

                            <!-- سجل الإنجازات -->
                            <div class="pts-timeline">
                                @foreach($userAchievements as $ua)
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
                                <div class="empty-icon">
                                    <i class="feather icon-target"></i>
                                </div>
                                <h4>لا توجد إنجازات بعد</h4>
                                <p>سيتم عرض الإنجازات هنا عند تحقيقها</p>
                                @if($availableAchievements->count() > 0)
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="feather icon-info"></i> يوجد {{ $availableAchievements->count() }} إنجاز متاح للتحقيق
                                        </small>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- الإنجازات المتاحة -->
                        @if($availableAchievements->count() > 0)
                            <hr class="my-4">
                            <h6 class="mb-3"><i class="feather icon-target"></i> جميع الإنجازات المتاحة</h6>
                            <div class="row">
                                @foreach($availableAchievements as $achievement)
                                    @php
                                        $isAchieved = $userAchievements->where('achievement_id', $achievement->id)->count() > 0;
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
                </div>
            </div>
        </div>
    </div>
@endsection