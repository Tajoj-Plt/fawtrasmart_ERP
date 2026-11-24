@extends('ecosystem::master')

@section('title', 'إنجازات العميل')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إنجازات العميل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.user-achievements.index') }}">إنجازات المستخدمين</a></li>
                            <li class="breadcrumb-item active">إنجازات العميل</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.user-achievements.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-award"></i>
                </div>
                <div class="stat-label">إجمالي الإنجازات</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>إنجاز</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">إجمالي النقاط</div>
                <div class="stat-value">{{ number_format($stats['total_points']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-calendar"></i>
                </div>
                <div class="stat-label">هذا الشهر</div>
                <div class="stat-value">{{ number_format($stats['this_month']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-clock"></i>
                    <span>إنجاز</span>
                </div>
            </div>
        </div>

        <!-- جدول الإنجازات -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> قائمة الإنجازات</h4>
                <span class="badge-count">{{ $userAchievements->total() }} إنجاز</span>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="8%">الأيقونة</th>
                                <th width="25%">الإنجاز</th>
                                <th width="15%">الفئة</th>
                                <th width="12%">النقاط</th>
                                <th width="15%">تاريخ التحقيق</th>
                                <th width="10%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userAchievements as $userAchievement)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if($userAchievement->achievement)
                                            @if($userAchievement->achievement->image)
                                                <img src="{{ asset('storage/' . $userAchievement->achievement->image) }}" 
                                                     alt="{{ $userAchievement->achievement->name }}"
                                                     style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                                            @else
                                                <span style="width: 35px; height: 35px; border-radius: 8px; background: {{ $userAchievement->achievement->color ?? '#f0f0f0' }}; display: flex; align-items: center; justify-content: center;">
                                                    @include('ecosystem::points.partials.achievement-icon', ['icon' => $userAchievement->achievement->icon])
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if($userAchievement->achievement)
                                            <strong>{{ $userAchievement->achievement->name }}</strong>
                                            @if($userAchievement->achievement->name_en)
                                                <br><small class="text-muted">{{ $userAchievement->achievement->name_en }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if($userAchievement->achievement)
                                            <span class="badge badge-light-secondary">{{ $userAchievement->achievement->category }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            <i class="feather icon-star"></i> {{ number_format($userAchievement->points_awarded) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="feather icon-calendar"></i>
                                            {{ $userAchievement->achieved_at->format('Y/m/d') }}
                                        </span>
                                        <br>
                                        <small class="text-muted">{{ $userAchievement->achieved_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('ecosystem.points.user-achievements.show', $userAchievement->id) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="feather icon-award font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد إنجازات</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $userAchievements, 'label' => 'إنجاز'])
            </div>
        </div>
    </div>
@endsection
