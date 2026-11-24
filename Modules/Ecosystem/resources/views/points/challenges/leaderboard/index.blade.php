@extends('ecosystem::master')

@section('title', 'لوحة المتصدرين')

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-12 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">لوحة المتصدرين</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                            <li class="breadcrumb-item active">لوحة المتصدرين</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- بطاقات الإحصائيات -->
        <div class="stats-row mb-3">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-target"></i></div>
                <div class="stat-label">إجمالي التحديات</div>
                <div class="stat-value">{{ number_format($stats['total_challenges']) }}</div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">التحديات النشطة</div>
                <div class="stat-value">{{ number_format($stats['active_challenges']) }}</div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">إجمالي المشاركين</div>
                <div class="stat-value">{{ number_format($stats['total_participants']) }}</div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">نقاط موزعة</div>
                <div class="stat-value">{{ number_format($stats['total_points_distributed']) }}</div>
            </div>
        </div>

        <!-- فلاتر البحث -->
        <div class="card mb-3" style="border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            <div class="card-body">
                <form method="GET" action="{{ route('ecosystem.points.challenges.leaderboard.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="font-weight-bold"><i class="feather icon-filter mr-1"></i> حالة التحدي</label>
                            <select name="status" class="form-control" style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">الكل</option>
                                <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>جاري</option>
                                <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>قادم</option>
                                <option value="ended" {{ request('status') == 'ended' ? 'selected' : '' }}>منتهي</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="pts-btn pts-btn-primary w-100">
                                <i class="feather icon-search"></i> بحث
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- قائمة التحديات -->
        @forelse($challenges as $challenge)
            @php
                $participants = \App\Models\Ecosystem\ChallengeParticipant::where('challenge_id', $challenge->id)
                    ->with(['employee', 'client'])
                    ->orderByDesc('current_progress')
                    ->limit(10)
                    ->get();

                $statusColor = 'secondary';
                $statusText = 'غير نشط';
                if ($challenge->is_active) {
                    if ($challenge->isOngoing()) {
                        $statusColor = 'success';
                        $statusText = 'جاري';
                    } elseif ($challenge->isUpcoming()) {
                        $statusColor = 'info';
                        $statusText = 'قادم';
                    } elseif ($challenge->hasEnded()) {
                        $statusColor = 'warning';
                        $statusText = 'منتهي';
                    }
                }
            @endphp

            <div class="card profile-card-modern mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center border-left">
                            <div class="avatar-wrapper mb-2">
                                @if($challenge->image)
                                    <img src="{{ asset('storage/' . $challenge->image) }}" alt="{{ $challenge->name }}"
                                         class="rounded-circle shadow" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #7367f0;">
                                @else
                                    <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto"
                                         style="width: 80px; height: 80px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2rem; color: #fff;">
                                        <i class="feather icon-target"></i>
                                    </div>
                                @endif
                            </div>
                            <h5 class="font-weight-bold mb-1">{{ $challenge->name }}</h5>
                            <span class="pts-badge pts-badge-{{ $statusColor }}" style="font-size: 0.85rem;">
                                {{ $statusText }}
                            </span>
                            <div class="mt-2">
                                <a href="{{ route('ecosystem.points.challenges.show', $challenge) }}" class="pts-btn pts-btn-sm pts-btn-primary">
                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="font-weight-bold mb-0"><i class="feather icon-bar-chart-2 mr-1"></i> أفضل 10 مشاركين</h6>
                                <div>
                                    <span class="pts-badge pts-badge-primary">
                                        <i class="feather icon-star"></i> {{ number_format($challenge->reward_points) }} نقطة
                                    </span>
                                </div>
                            </div>

                            @if($participants->count() > 0)
                                <div class="row">
                                    @foreach($participants->take(3) as $index => $participant)
                                        @php
                                            $progressPct = $participant->target_value_snapshot > 0
                                                ? min(100, round(($participant->current_progress / $participant->target_value_snapshot) * 100, 1))
                                                : 0;

                                            $medalColors = ['#FFD700', '#C0C0C0', '#CD7F32'];  // Gold, Silver, Bronze
                                            $medal = ['🥇', '🥈', '🥉'][$index] ?? '';
                                        @endphp
                                        
                                        <div class="col-md-4 mb-3">
                                            <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, {{ $medalColors[$index] }}22 0%, {{ $medalColors[$index] }}11 100%); border: 2px solid {{ $medalColors[$index] }}66; border-radius: 12px;">
                                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">{{ $medal }}</div>
                                                <h6 class="font-weight-bold mb-1">
                                                    @if($participant->employee)
                                                        {{ $participant->employee->full_name }}
                                                    @elseif($participant->client)
                                                        {{ $participant->client->full_name }}
                                                    @endif
                                                </h6>
                                                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                                                    <div class="progress-bar" style="width: {{ $progressPct }}%; background: {{ $medalColors[$index] }};"></div>
                                                </div>
                                                <small class="font-weight-bold">{{ number_format($participant->current_progress) }} / {{ number_format($participant->target_value_snapshot) }}</small>
                                                @if($participant->points_awarded)
                                                    <div class="mt-2">
                                                        <span class="pts-badge pts-badge-success">
                                                            <i class="feather icon-star"></i> {{ number_format($participant->points_awarded) }} نقطة
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if($participants->count() > 3)
                                    <div class="table-responsive mt-3">
                                        <table class="pts-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 10%;">الترتيب</th>
                                                    <th style="width: 30%;">المشارك</th>
                                                    <th style="width:  30%;">التقدم</th>
                                                    <th style="width: 15%;">النقاط</th>
                                                    <th style="width: 15%;">الحالة</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($participants->slice(3) as $index => $participant)
                                                    @php
                                                        $progressPct = $participant->target_value_snapshot > 0
                                                            ? min(100, round(($participant->current_progress / $participant->target_value_snapshot) * 100, 1))
                                                            : 0;
                                                    @endphp
                                                    <tr>
                                                        <td class="cell-id">{{ $index + 4 }}</td>
                                                        <td>
                                                            @if($participant->employee)
                                                                <i class="feather icon-user text-warning"></i> {{ $participant->employee->full_name }}
                                                            @elseif($participant->client)
                                                                <i class="feather icon-users text-info"></i> {{ $participant->client->full_name }}
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="progress flex-grow-1 ml-2" style="height: 8px; border-radius: 4px;">
                                                                    <div class="progress-bar" style="width: {{ $progressPct }}%; background: #7367f0;"></div>
                                                                </div>
                                                                <span class="font-weight-bold" style="min-width: 80px; font-size: 0.85rem;">{{ number_format($participant->current_progress) }} ({{ $progressPct }}%)</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if($participant->points_awarded)
                                                                <span class="cell-points-positive">{{ number_format($participant->points_awarded) }}</span>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($participant->is_completed)
                                                                <span class="pts-badge pts-badge-success">مكتمل</span>
                                                            @else
                                                                <span class="pts-badge pts-badge-secondary">قيد التنفيذ</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @else
                                <div class="pts-empty-state" style="padding: 2rem;">
                                    <div class="empty-icon">
                                        <i class="feather icon-users"></i>
                                    </div>
                                    <h5>لا يوجد مشاركون بعد</h5>
                                    <p>لم يتم تسجيل مشاركين في هذا التحدي</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    <div class="pts-empty-state-full">
                        <i class="feather icon-target"></i>
                        <h5>لا توجد تحديات</h5>
                        <p>لم يتم إنشاء أي تحديات بعد</p>
                        <a href="{{ route('ecosystem.points.challenges.create') }}" class="pts-btn pts-btn-primary mt-2">
                            <i class="feather icon-plus"></i> إنشاء تحدي جديد
                        </a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
