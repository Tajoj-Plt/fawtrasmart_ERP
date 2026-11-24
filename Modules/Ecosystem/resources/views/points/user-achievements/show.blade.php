@extends('ecosystem::master')

@section('title', 'تفاصيل الإنجاز')

@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            @if($userAchievement->achievement)
                @include('ecosystem::points.partials.achievement-icon', ['icon' => $userAchievement->achievement->icon, 'size' => '2rem'])
            @else
                @include('ecosystem::points.partials.achievement-icon', ['icon' => null, 'size' => '2rem'])
            @endif
            تفاصيل الإنجاز
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.user-achievements.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            @if($userAchievement->transaction)
                <a href="{{ route('ecosystem.points.transactions.show', $userAchievement->transaction->id) }}" class="pts-btn pts-btn-primary">
                    <i class="feather icon-credit-card"></i>
                    عرض المعاملة
                </a>
            @endif
        </div>
    </div>

    <!-- Hero -->
    <div class="pts-hero" style="background: linear-gradient(135deg, {{ $userAchievement->achievement->color ?? '#FFD700' }}15 0%, #fff 100%);">
        <div class="pts-hero-info">
            <div class="pts-hero-label">الإنجاز المحقق</div>
            @if($userAchievement->achievement)
                <h2 class="pts-hero-title">
                    {{ $userAchievement->achievement->name }}
                    @if($userAchievement->achievement->name_en)
                        <small class="text-muted">({{ $userAchievement->achievement->name_en }})</small>
                    @endif
                </h2>
                <p class="text-muted mt-2">{{ $userAchievement->achievement->description }}</p>
            @else
                <h2 class="pts-hero-title">إنجاز محذوف</h2>
            @endif
            <div style="margin-top: 0.75rem;">
                <span class="pts-badge pts-badge-{{ $userAchievement->user_type->value === 'client' ? 'info' : 'warning' }}">
                    <i class="feather icon-{{ $userAchievement->user_type->value === 'client' ? 'users' : 'user' }}"></i>
                    {{ $userAchievement->user_type->label() }}
                </span>
                <span class="pts-badge pts-badge-success" style="margin-right: 0.5rem;">
                    <i class="feather icon-star"></i> {{ number_format($userAchievement->points_awarded) }} نقطة
                </span>
            </div>
        </div>
        <div class="pts-hero-points">
            @if($userAchievement->achievement && $userAchievement->achievement->image)
                <img src="{{ asset('storage/' . $userAchievement->achievement->image) }}" alt="{{ $userAchievement->achievement->name }}" 
                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid {{ $userAchievement->achievement->color ?? '#FFD700' }};">
            @elseif($userAchievement->achievement)
                <span style="width: 100px; height: 100px; border-radius: 50%; background: {{ $userAchievement->achievement->color ?? '#FFD700' }}; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    @include('ecosystem::points.partials.achievement-icon', ['icon' => $userAchievement->achievement->icon, 'size' => '3rem'])
                </span>
            @endif
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
            <!-- User Info Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-user"></i>
                        معلومات المستخدم
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-user-check"></i>
                            الاسم
                        </span>
                        <span class="pts-info-value">
                            @if($userAchievement->user_type->value == 'employee')
                                @if($userAchievement->employee)
                                    <strong>{{ $userAchievement->employee->first_name }} {{ $userAchievement->employee->last_name }}</strong>
                                @else
                                    <span class="text-muted">موظف محذوف</span>
                                @endif
                            @else
                                @if($userAchievement->client)
                                    <strong>{{ $userAchievement->client->full_name ?: $userAchievement->client->trade_name }}</strong>
                                @else
                                    <span class="text-muted">عميل محذوف</span>
                                @endif
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-tag"></i>
                            نوع المستخدم
                        </span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-{{ $userAchievement->user_type->value === 'client' ? 'info' : 'warning' }}">
                                {{ $userAchievement->user_type->label() }}
                            </span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-map-pin"></i>
                            الفرع
                        </span>
                        <span class="pts-info-value">
                            @php
                                $branch = null;
                                if($userAchievement->user_type->value == 'employee' && $userAchievement->employee) {
                                    $branch = $userAchievement->employee->branch;
                                } elseif($userAchievement->user_type->value == 'client' && $userAchievement->client) {
                                    $branch = $userAchievement->client->branch;
                                }
                            @endphp
                            @if($branch)
                                {{ $branch->branch_name }}
                            @else
                                <span class="text-muted">غير متوفر</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Achievement Details Card -->
            @if($userAchievement->achievement)
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-award"></i>
                            تفاصيل الإنجاز
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-info-row">
                            <span class="pts-info-label">
                                <i class="feather icon-tag"></i>
                                الفئة
                            </span>
                            <span class="pts-info-value">
                                <span class="pts-badge pts-badge-secondary">{{ $userAchievement->achievement->category }}</span>
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">
                                <i class="feather icon-target"></i>
                                نوع الشرط
                            </span>
                            <span class="pts-info-value">
                                {{ $userAchievement->achievement->condition_type }}
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">
                                <i class="feather icon-sliders"></i>
                                الشرط
                            </span>
                            <span class="pts-info-value">
                                {{ $userAchievement->achievement->condition_operator->label() }} 
                                <strong class="text-primary">{{ number_format($userAchievement->achievement->condition_value) }}</strong>
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">
                                <i class="feather icon-calendar"></i>
                                الفترة
                            </span>
                            <span class="pts-info-value">
                                {{ $userAchievement->achievement->condition_period->label() }}
                            </span>
                        </div>
                        <div class="pts-info-row">
                            <span class="pts-info-label">
                                <i class="feather icon-repeat"></i>
                                التكرار
                            </span>
                            <span class="pts-info-value">
                                <span class="pts-badge {{ $userAchievement->achievement->is_one_time ? 'pts-badge-primary' : 'pts-badge-info' }}">
                                    {{ $userAchievement->achievement->is_one_time ? 'مرة واحدة' : 'متكرر' }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Points Info -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-star"></i>
                        معلومات النقاط
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">النقاط الممنوحة</span>
                        <span class="pts-info-value">
                            <strong class="text-success" style="font-size: 1.5rem;">
                                <i class="feather icon-star"></i> {{ number_format($userAchievement->points_awarded) }}
                            </strong>
                        </span>
                    </div>
                    @if($userAchievement->transaction)
                        <div class="pts-info-row">
                            <span class="pts-info-label">رقم المعاملة</span>
                            <span class="pts-info-value">
                                <a href="{{ route('ecosystem.points.transactions.show', $userAchievement->transaction->id) }}" class="text-primary">
                                    #{{ $userAchievement->transaction->id }}
                                </a>
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Timeline -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-clock"></i>
                        التوقيت
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-dot"></div>
                        <div class="pts-timeline-content">
                            <span class="pts-timeline-title">تاريخ التحقيق</span>
                            <span class="pts-timeline-date">{{ $userAchievement->achieved_at->format('Y/m/d') }}</span>
                            <span class="pts-timeline-time">{{ $userAchievement->achieved_at->format('H:i') }}</span>
                        </div>
                    </div>
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-dot"></div>
                        <div class="pts-timeline-content">
                            <span class="pts-timeline-title">تاريخ التسجيل</span>
                            <span class="pts-timeline-date">{{ $userAchievement->created_at->format('Y/m/d') }}</span>
                            <span class="pts-timeline-time">{{ $userAchievement->created_at->format('H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    @if($userAchievement->user_type->value == 'employee' && $userAchievement->employee_id)
                        <a href="{{ route('ecosystem.points.user-achievements.employee', $userAchievement->employee_id) }}" class="btn btn-primary btn-block mb-2">
                            <i class="feather icon-list"></i>
                            جميع إنجازات الموظف
                        </a>
                    @elseif($userAchievement->user_type->value == 'client' && $userAchievement->client_id)
                        <a href="{{ route('ecosystem.points.user-achievements.client', $userAchievement->client_id) }}" class="btn btn-primary btn-block mb-2">
                            <i class="feather icon-list"></i>
                            جميع إنجازات العميل
                        </a>
                    @endif
                    @if($userAchievement->achievement)
                        <a href="{{ route('ecosystem.points.achievements.show', $userAchievement->achievement->id) }}" class="btn btn-outline-primary btn-block">
                            <i class="feather icon-award"></i>
                            عرض تفاصيل الإنجاز
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
