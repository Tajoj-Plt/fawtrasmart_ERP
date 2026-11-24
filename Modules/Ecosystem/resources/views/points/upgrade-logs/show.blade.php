@extends('ecosystem::master')

@section('title', 'عرض سجل الترقية')

@section('content')
    <link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

    <div class="pts-container">
        <!-- Header -->
        <div class="pts-header">
            <h1 class="pts-header-title">
                <i class="feather icon-file-text"></i>
                سجل الترقية
            </h1>
            <div class="pts-header-actions">
                <a href="{{ route('ecosystem.points.upgrade-logs.index') }}" class="pts-btn pts-btn-outline">
                    <i class="feather icon-arrow-right"></i>
                    رجوع
                </a>
            </div>
        </div>

        <!-- Hero -->
        <div class="pts-hero">
            <div class="pts-user-display">
                <div class="pts-user-avatar-large">
                    @if ($log->user_type?->value === 'employee')
                        {{ substr($log->employee?->first_name ?? 'غ', 0, 1) }}
                    @else
                        {{ substr($log->client?->first_name ?? ($log->client?->trade_name ?? 'غ'), 0, 1) }}
                    @endif
                </div>
                <div class="pts-user-details">
                    @if ($log->user_type?->value === 'employee')
                        <div class="pts-user-name-large">{{ $log->employee?->first_name }}
                            {{ $log->employee?->middle_name }}</div>
                        @if ($log->employee?->branch)
                            <div class="pts-user-branch">{{ $log->employee->branch->name }}</div>
                        @endif
                    @else
                        <div class="pts-user-name-large">
                            {{ $log->client?->trade_name ?? $log->client?->first_name . ' ' . $log->client?->last_name }}
                        </div>
                        @if ($log->client?->branch)
                            <div class="pts-user-branch">{{ $log->client->branch->name }}</div>
                        @endif
                    @endif
                </div>
            </div>
            <div class="pts-hero-points">
                <div class="pts-points-value {{ $log->isUpgrade() ? 'positive' : 'negative' }}">
                    {{ $log->points_at_upgrade > 0 ? '+' : '' }}{{ number_format($log->points_at_upgrade) }}
                </div>
                <div class="pts-points-label">نقطة</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="pts-grid pts-grid-2">
            <!-- Main Info -->
            <div>
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-info"></i>
                            تفاصيل الترقية
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-info-row">
                            <span class="pts-info-label">نوع المستخدم</span>
                            <span class="pts-info-value">
                                <span
                                    class="pts-badge pts-badge-{{ $log->user_type->value === 'client' ? 'primary' : 'success' }}">
                                    <i
                                        class="feather icon-{{ $log->user_type?->value === 'client' ? 'users' : 'user' }}"></i>
                                    {{ $log->user_type?->label() ?? ($log->user_type?->value ?? 'غير محدد') }}
                                </span>
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">نوع الترقية</span>
                            <span class="pts-info-value">
                                <span
                                    class="pts-badge pts-badge-{{ $log->upgrade_type->value === 'automatic' ? 'info' : 'warning' }}">
                                    <i
                                        class="feather icon-{{ $log->upgrade_type?->value === 'automatic' ? 'zap' : 'edit-2' }}"></i>
                                    {{ $log->upgrade_type?->label() ?? ($log->upgrade_type?->value ?? 'غير محدد') }}
                                </span>
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">النقاط عند الترقية</span>
                            <span class="pts-info-value">
                                <span class="pts-badge {{ $log->isUpgrade() ? 'pts-badge-success' : 'pts-badge-danger' }}">
                                    <i class="feather icon-{{ $log->isUpgrade() ? 'arrow-up' : 'arrow-down' }}"></i>
                                    {{ $log->points_at_upgrade > 0 ? '+' : '' }}{{ number_format($log->points_at_upgrade) }}
                                    نقطة
                                </span>
                            </span>
                        </div>
                        @if ($log->notes)
                            <div class="pts-divider"></div>
                            <div class="pts-description">
                                <strong>الملاحظات:</strong><br>
                                {{ $log->notes }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tier Comparison -->
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-shuffle"></i>
                            مقارنة المستويات
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-tier-comparison">
                            <div class="pts-tier-item {{ $log->isUpgrade() ? 'upgrade' : 'downgrade' }}">
                                <div class="pts-tier-header">
                                    <h4>المستوى السابق</h4>
                                    @if ($log->oldTier)
                                        <span class="pts-badge pts-badge-secondary">{{ $log->oldTier->name }}</span>
                                    @else
                                        <span class="pts-badge pts-badge-secondary">غير متوفر</span>
                                    @endif
                                </div>
                                @if ($log->oldTier)
                                    <ul class="pts-tier-benefits">
                                        <li>الحد الأدنى: {{ number_format($log->oldTier->min_points) }} نقطة</li>
                                        @if ($log->oldTier->benefits && count($log->oldTier->benefits) > 0)
                                            @foreach ($log->oldTier->benefits as $benefit)
                                                @if (trim($benefit))
                                                    <li>{{ trim($benefit) }}</li>
                                                @endif
                                            @endforeach
                                        @endif
                                    </ul>
                                @endif
                            </div>

                            <div class="pts-tier-arrow">
                                <i
                                    class="feather icon-{{ $log->isUpgrade() ? 'arrow-up' : 'arrow-down' }} {{ $log->isUpgrade() ? 'text-success' : 'text-danger' }}"></i>
                            </div>

                            <div class="pts-tier-item {{ $log->isUpgrade() ? 'upgrade' : 'downgrade' }}">
                                <div class="pts-tier-header">
                                    <h4>المستوى الجديد</h4>
                                    @if ($log->newTier)
                                        <span
                                            class="pts-badge pts-badge-{{ $log->isUpgrade() ? 'success' : 'danger' }}">{{ $log->newTier->name }}</span>
                                    @else
                                        <span class="pts-badge pts-badge-secondary">غير متوفر</span>
                                    @endif
                                </div>
                                @if ($log->newTier)
                                    <ul class="pts-tier-benefits">
                                        <li>الحد الأدنى: {{ number_format($log->newTier->min_points) }} نقطة</li>
                                        @if ($log->newTier->benefits && count($log->newTier->benefits) > 0)
                                            @foreach ($log->newTier->benefits as $benefit)
                                                @if (trim($benefit))
                                                    <li>{{ trim($benefit) }}</li>
                                                @endif
                                            @endforeach
                                        @endif
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-clock"></i>
                            السجل
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-timeline-item">
                            <div class="pts-timeline-icon">
                                <i class="feather icon-{{ $log->isUpgrade() ? 'arrow-up' : 'arrow-down' }}"></i>
                            </div>
                            <div class="pts-timeline-content">
                                <p class="pts-timeline-title">{{ $log->isUpgrade() ? 'تمت الترقية' : 'تمت التخفيض' }}</p>
                                <span
                                    class="pts-timeline-date">{{ $log->created_at ? $log->created_at->format('Y-m-d H:i') : 'غير محدد' }}</span>
                            </div>
                        </div>

                        @if ($log->oldTier && $log->newTier)
                            <div class="pts-timeline-item">
                                <div class="pts-timeline-icon">
                                    <i class="feather icon-{{ $log->isUpgrade() ? 'trending-up' : 'trending-down' }}"></i>
                                </div>
                                <div class="pts-timeline-content">
                                    <p class="pts-timeline-title">
                                        {{ $log->isUpgrade() ? 'ترقية' : 'تخفيض' }} من
                                        <strong>{{ $log->oldTier->name }}</strong> إلى
                                        <strong>{{ $log->newTier->name }}</strong>
                                    </p>
                                    <span class="pts-timeline-date">
                                        فرق: {{ abs($log->newTier->min_points - $log->oldTier->min_points) }} نقطة
                                    </span>
                                </div>
                            </div>
                        @endif

                        <div class="pts-divider"></div>
                        <div class="pts-user">
                            <div class="pts-user-avatar">
                                {{ $log->upgradedBy ? substr(trim($log->upgradedBy->first_name . ' ' . $log->upgradedBy->middle_name . ' ' . $log->upgradedBy->nickname), 0, 1) : substr('غير متوفر', 0, 1) }}
                            </div>
                            <div>
                                <div class="pts-user-name">
                                    {{ $log->upgradedBy ? trim($log->upgradedBy->first_name . ' ' . $log->upgradedBy->middle_name . ' ' . $log->upgradedBy->nickname) : 'غير متوفر' }}
                                </div>
                                <div class="pts-user-type">الموظف الذي قام بالترقية</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-clipboard"></i>
                            معلومات إضافية
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-info-row">
                            <span class="pts-info-label">معرف السجل</span>
                            <span class="pts-info-value">ID: {{ $log->id }}</span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">تاريخ الإنشاء</span>
                            <span
                                class="pts-info-value">{{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'غير محدد' }}</span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">تاريخ التحديث</span>
                            <span
                                class="pts-info-value">{{ $log->updated_at ? $log->updated_at->format('Y-m-d H:i:s') : 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
