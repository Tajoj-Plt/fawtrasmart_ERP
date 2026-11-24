@extends('ecosystem::master')

@section('title', 'تفاصيل نقاط الموظف - ' . $employee->full_name)

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')
    
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تفاصيل نقاط الموظف</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.users.employees') }}">نقاط الموظفين</a></li>
                            <li class="breadcrumb-item active">{{ $employee->full_name }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.users.employees') }}" class="pts-btn pts-btn-secondary">
                <i class="feather icon-arrow-right"></i> رجوع للقائمة
            </a>
        </div>
    </div>

    <div class="content-body">
        {{-- شريط تقدم النقاط الشهرية --}}
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.monthly-tier-progress', ['monthlyPointsData' => $monthlyPointsData])
            </div>
        </div>

        {{-- شريط الإنجازات الشهرية --}}
        <div class="row mb-3">
            <div class="col-12">
                @include('ecosystem::points.partials.achievements-ribbon', [
                    'availableAchievements' => $availableAchievements,
                    'userAchievements' => $userAchievements,
                    'ribbonTitle' => 'إنجازات الشهر',
                    'ribbonSubtitle' => 'الإنجازات المحققة هذا الشهر - تُعاد شهرياً',
                ])
            </div>
        </div>

        <!-- بطاقة معلومات الموظف مع المستوى -->
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-left">
                        <div class="avatar-wrapper mb-2">
                            @if($employee->employee_photo)
                                <img src="{{ asset('assets/uploads/employee/' . $employee->employee_photo) }}" alt="{{ $employee->full_name }}" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover;">
                            @else
                                <div class="avatar-placeholder rounded-circle shadow d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2.5rem; color: #fff;">
                                    {{ substr($employee->first_name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <h4 class="mb-1 font-weight-bold">{{ $employee->full_name }}</h4>
                        <p class="text-muted mb-2"><i class="feather icon-mail mr-1"></i>{{ $employee->email }}</p>
                        
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
                        <div class="employee-details">
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-grid mr-1"></i> القسم</span>
                                <strong>{{ $employee->department?->name ?? '-' }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-map-pin mr-1"></i> الفرع</span>
                                <strong>{{ $employee->branch?->name ?? '-' }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-file-text mr-1"></i> عدد المعاملات</span>
                                <strong>{{ number_format($employeeStats['transactions_count']) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- المستوى التالي -->
                        @if($nextTier && $pointsToNextTier)
                            <div class="next-tier-card p-3 rounded" style="background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);">
                                <h6 class="text-muted mb-2"><i class="feather icon-target mr-1"></i> المستوى التالي</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge mr-2" style="background-color: {{ $nextTier->color }}; color: #fff;">
                                        {{ $nextTier->name }}
                                    </span>
                                    <span class="text-muted">{{ number_format($nextTier->min_points) }} نقطة</span>
                                </div>
                                @php
                                    $netMonthPoints = $employeeStats['current_balance'] ?? 0;
                                    $progress = $currentTier ? (($netMonthPoints - $currentTier->min_points) / ($nextTier->min_points - $currentTier->min_points)) * 100 : 0;
                                    $progress = min(100, max(0, $progress));
                                @endphp
                                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: {{ $progress }}%; background: linear-gradient(90deg, {{ $nextTier->color }} 0%, {{ $nextTier->color }}aa 100%);"
                                         aria-valuenow="{{ $progress }}"></div>
                                </div>
                                <small class="text-muted">
                                    <i class="feather icon-zap"></i> {{ number_format($pointsToNextTier) }} نقطة للترقية
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
                <div class="stat-value">{{ number_format($employeeStats['current_balance']) }}</div>
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
                <div class="stat-value">{{ number_format($employeeStats['total_earned']) }}</div>
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
                <div class="stat-value">{{ number_format($employeeStats['total_spent']) }}</div>
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
                <div class="stat-value">{{ number_format($employeeStats['transactions_count']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-list"></i>
                    <span>معاملة</span>
                </div>
            </div>
        </div>

        <!-- بطاقات إحصائيات الشهر -->
        <div class="stats-row mb-3">
            <div class="stat-card-modern success" style="flex: 1;">
                <div class="stat-icon">
                    <i class="feather icon-arrow-up-circle"></i>
                </div>
                <div class="stat-label">مكتسب هذا الشهر</div>
                <div class="stat-value">{{ number_format($employeeStats['this_month_earned']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-calendar"></i>
                    <span>{{ now()->translatedFormat('F') }}</span>
                </div>
            </div>

            <div class="stat-card-modern danger" style="flex: 1;">
                <div class="stat-icon">
                    <i class="feather icon-arrow-down-circle"></i>
                </div>
                <div class="stat-label">مستخدم هذا الشهر</div>
                <div class="stat-value">{{ number_format($employeeStats['this_month_spent']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-calendar"></i>
                    <span>{{ now()->translatedFormat('F') }}</span>
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
                        <a class="nav-link active" id="transactions-tab" data-toggle="tab" href="#transactions" role="tab">
                            <i class="feather icon-list"></i> معاملات النقاط
                            <span class="badge badge-primary badge-pill">{{ $transactions->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="upgrades-tab" data-toggle="tab" href="#upgrades" role="tab">
                            <i class="feather icon-trending-up"></i> سجل الترقيات
                            <span class="badge badge-info badge-pill">{{ $tierUpgrades->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tiers-tab" data-toggle="tab" href="#tiers" role="tab">
                            <i class="feather icon-award"></i> المستويات
                            <span class="badge badge-warning badge-pill">{{ $allTiers->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="achievements-tab" data-toggle="tab" href="#achievements" role="tab">
                            <i class="feather icon-target"></i> الإنجازات
                            <span class="badge badge-success badge-pill">{{ $userAchievements->count() }}</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- تبويب معاملات النقاط -->
                    <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                        <!-- فلاتر -->
                        <div class="pts-filter-section">
                            <div class="filter-title">
                                <i class="feather icon-filter"></i>
                                خيارات التصفية
                            </div>
                            <form method="GET" action="{{ route('ecosystem.points.users.employees.show', $employee->id) }}">
                                <div class="row">
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3 mb-lg-0">
                                        <div class="pts-form-group">
                                            <label>الشهر</label>
                                            <select name="month" class="pts-form-control pts-select" onchange="this.form.submit()">
                                                @foreach($months as $m)
                                                    <option value="{{ $m['month'] }}" data-year="{{ $m['year'] }}" {{ ($filterMonth == $m['month'] && $filterYear == $m['year']) ? 'selected' : '' }}>
                                                        {{ $m['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="year" value="{{ $filterYear }}">
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3 mb-lg-0">
                                        <div class="pts-form-group">
                                            <label>النوع</label>
                                            <select name="points_type" class="pts-form-control pts-select">
                                                <option value="">كل الأنواع</option>
                                                <option value="earned" {{ request('points_type') == 'earned' ? 'selected' : '' }}>مكتسبة</option>
                                                <option value="spent" {{ request('points_type') == 'spent' ? 'selected' : '' }}>مستخدمة</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3 mb-lg-0">
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
                                    <div class="col-lg-5 col-md-12 col-sm-12 d-flex align-items-end">
                                        <div class="pts-btn-group w-100">
                                            <button type="submit" class="pts-btn pts-btn-primary">
                                                <i class="feather icon-search"></i> بحث
                                            </button>
                                            <a href="{{ route('ecosystem.points.users.employees.show', $employee->id) }}" class="pts-btn pts-btn-secondary">
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
                                                <span class="pts-badge pts-badge-{{ $transaction->status->color() }}">
                                                    {{ $transaction->status->label() }}
                                                </span>
                                            </td>
                                            <td class="cell-description">
                                                {{ Str::limit($transaction->description, 45) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="pts-empty-state">
                                                    <div class="empty-icon">
                                                        <i class="feather icon-inbox"></i>
                                                    </div>
                                                    <h5>لا توجد معاملات</h5>
                                                    <p>لم يتم تسجيل أي معاملات نقاط بعد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @include('ecosystem::points.partials._smart-pagination', ['paginator' => $transactions, 'label' => 'معاملة', 'style' => 'pts'])
                    </div>

                    <!-- تبويب سجل الترقيات -->
                    <div class="tab-pane fade" id="upgrades" role="tabpanel">
                        @if($tierUpgrades->count() > 0)
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
                                                    <span class="pts-badge {{ $upgrade->upgrade_type->value == 'automatic' ? 'pts-badge-info' : 'pts-badge-warning' }}">
                                                        <i class="feather {{ $upgrade->upgrade_type->value == 'automatic' ? 'icon-zap' : 'icon-user' }}"></i>
                                                        {{ $upgrade->upgrade_type->label() }}
                                                    </span>
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
                        @else
                            <div class="pts-empty-state-full">
                                <div class="empty-icon">
                                    <i class="feather icon-award"></i>
                                </div>
                                <h4>لا يوجد سجل ترقيات</h4>
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
                                            <div class="current-badge">
                                                <i class="feather icon-check"></i> المستوى الحالي
                                            </div>
                                        @endif
                                        <div class="tier-icon" style="background: linear-gradient(135deg, {{ $tier->color }} 0%, {{ $tier->color }}99 100%);">
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

@push('scripts')
<script>
    $(document).ready(function() {
        // عند تغيير الشهر، تحديث السنة من data-year
        $('select[name="month"]').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            var year = selectedOption.data('year');
            $('input[name="year"]').val(year);
        });
    });
</script>
@endpush
