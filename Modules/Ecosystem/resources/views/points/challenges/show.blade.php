@extends('ecosystem::master')

@section('title', 'عرض التحدي - ' . $challenge->name)

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    @php
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
            } else {
                $statusColor = 'primary';
                $statusText = 'نشط';
            }
        } else {
            $statusColor = 'danger';
            $statusText = 'غير نشط';
        }

        $daysRemaining = $challenge->isOngoing() ? $challenge->getDaysRemaining() : 0;
        $totalDays = $challenge->starts_at && $challenge->ends_at ? $challenge->starts_at->diffInDays($challenge->ends_at) : 0;
        $elapsedDays = $challenge->starts_at ? now()->diffInDays($challenge->starts_at) : 0;
        $timeProgress = $totalDays > 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;

        $isTeamChallenge = $challenge->challenge_type === \App\Enums\ChallengeType::TEAM
            || (is_string($challenge->challenge_type) && $challenge->challenge_type === 'team');
        $challengeUserTypeValue = $challenge->user_type instanceof \App\Enums\UserType
            ? $challenge->user_type->value
            : (string)$challenge->user_type;
    @endphp

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">عرض التحدي</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                            <li class="breadcrumb-item active">{{ $challenge->name }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-4 col-12">
            <div class="pts-btn-group" style="justify-content: flex-end;">
                <a href="{{ route('ecosystem.points.challenges.index') }}" class="pts-btn pts-btn-secondary">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                <a href="{{ route('ecosystem.points.challenges.edit', $challenge) }}" class="pts-btn pts-btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
                <button type="button" class="pts-btn pts-btn-danger" id="deleteBtn" style="padding: 0.75rem 1rem;">
                    <i class="feather icon-trash-2"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- بطاقة بيانات التحدي الرئيسية -->
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-left">
                        <div class="avatar-wrapper mb-2">
                            @if($challenge->image)
                                <img src="{{ asset('storage/' . $challenge->image) }}" alt="{{ $challenge->name }}"
                                     class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #7367f0;">
                            @else
                                <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto"
                                     style="width: 100px; height: 100px; background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%); font-size: 2.5rem; color: #fff;">
                                    <i class="feather icon-target"></i>
                                </div>
                            @endif
                        </div>
                        <h4 class="mb-1 font-weight-bold">{{ $challenge->name }}</h4>
                        @if($challenge->name_en)
                            <p class="text-muted mb-2">{{ $challenge->name_en }}</p>
                        @endif
                        <span class="pts-badge pts-badge-{{ $statusColor }}" style="font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                            <span class="pts-status-dot {{ $challenge->is_active && $challenge->isOngoing() ? 'active' : 'inactive' }}"></span>
                            {{ $statusText }}
                        </span>
                    </div>

                    <div class="col-md-5">
                        <div class="employee-details">
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-tag mr-1"></i> نوع التحدي</span>
                                <strong>
                                    <span class="pts-badge pts-badge-primary">
                                        {{ $challenge->challenge_type instanceof \App\Enums\ChallengeType ? $challenge->challenge_type->label() : $challenge->challenge_type }}
                                    </span>
                                </strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-award mr-1"></i> نوع المنافسة</span>
                                <strong>
                                    <span class="pts-badge pts-badge-secondary">
                                        {{ $challenge->competition_type instanceof \App\Enums\CompetitionType ? $challenge->competition_type->label() : $challenge->competition_type }}
                                    </span>
                                </strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-target mr-1"></i> الهدف</span>
                                <div>
                                    @foreach($challenge->getTargetActionLabels() as $label)
                                        <span class="badge badge-light-primary" style="font-size: 0.8rem; margin: 1px;">{{ $label }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted"><i class="feather icon-hash mr-1"></i> القيمة المستهدفة</span>
                                <strong>{{ number_format($challenge->target_value) }} {{ $challenge->target_unit }}</strong>
                            </div>
                            <div class="detail-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-star mr-1"></i> المكافأة</span>
                                <strong class="text-success">{{ number_format($challenge->reward_points) }} نقطة</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        @if($challenge->isOngoing())
                            <div class="p-3 rounded" style="background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);">
                                <h6 class="text-muted mb-2"><i class="feather icon-clock mr-1"></i> الوقت المتبقي</h6>
                                <div class="text-center mb-2">
                                    @if($daysRemaining > 0)
                                        <span class="font-weight-bold text-primary" style="font-size: 2rem;">{{ $daysRemaining }}</span>
                                        <span class="text-muted"> يوم</span>
                                    @else
                                        <span class="font-weight-bold text-warning" style="font-size: 1.5rem;">آخر يوم!</span>
                                    @endif
                                </div>
                                <div class="progress mb-2" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{ $timeProgress }}%; background: linear-gradient(90deg, #7367f0 0%, #ce9ffc 100%);"
                                         aria-valuenow="{{ $timeProgress }}"></div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">{{ $challenge->starts_at ? $challenge->starts_at->format('Y/m/d') : '' }}</small>
                                    <small class="text-muted">{{ $challenge->ends_at ? $challenge->ends_at->format('Y/m/d') : '' }}</small>
                                </div>
                            </div>
                        @elseif($challenge->hasEnded())
                            <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #ff9f4322 0%, #ff9f4311 100%); border: 1px solid #ff9f4344;">
                                <i class="feather icon-flag text-warning" style="font-size: 2rem;"></i>
                                <h6 class="text-warning mt-2 mb-1">انتهى التحدي</h6>
                                <small class="text-muted">{{ $challenge->ends_at ? $challenge->ends_at->format('Y/m/d H:i') : '' }}</small>
                                @if($challenge->results_announced_at)
                                    <div class="mt-2">
                                        <span class="pts-badge pts-badge-success">
                                            <i class="feather icon-check-circle"></i> تم إعلان النتائج
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @elseif($challenge->isUpcoming())
                            <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #00cfe822 0%, #00cfe811 100%); border: 1px solid #00cfe844;">
                                <i class="feather icon-calendar text-info" style="font-size: 2rem;"></i>
                                <h6 class="text-info mt-2 mb-1">لم يبدأ بعد</h6>
                                <small class="text-muted">يبدأ: {{ $challenge->starts_at ? $challenge->starts_at->format('Y/m/d H:i') : '-' }}</small>
                            </div>
                        @else
                            <div class="p-3 rounded text-center" style="background: linear-gradient(135deg, #ea545522 0%, #ea545511 100%); border: 1px solid #ea545544;">
                                <i class="feather icon-slash text-danger" style="font-size: 2rem;"></i>
                                <h6 class="text-danger mt-2 mb-0">غير نشط</h6>
                            </div>
                        @endif

                        {{-- علامات إضافية --}}
                        <div class="mt-2 d-flex flex-wrap gap-2 justify-content-center" style="gap: 0.5rem;">
                            @if($challenge->is_featured)
                                <span class="pts-badge pts-badge-warning"><i class="feather icon-star"></i> مميز</span>
                            @endif
                            @if($challenge->auto_enroll)
                                <span class="pts-badge pts-badge-info"><i class="feather icon-refresh-cw"></i> تسجيل تلقائي</span>
                            @endif
                            @if($challenge->branch)
                                <span class="pts-badge pts-badge-secondary"><i class="feather icon-map-pin"></i> {{ $challenge->branch->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="stats-row">
            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-users"></i></div>
                <div class="stat-label">المشاركون</div>
                <div class="stat-value">{{ number_format($challengeStats['total_participants']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-user-check"></i>
                    <span>مسجل</span>
                </div>
            </div>

            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                <div class="stat-label">أكملوا التحدي</div>
                <div class="stat-value">{{ number_format($challengeStats['completed']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-percent"></i>
                    <span>{{ $challengeStats['total_participants'] > 0 ? round(($challengeStats['completed'] / $challengeStats['total_participants']) * 100) : 0 }}%</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-activity"></i></div>
                <div class="stat-label">متوسط التقدم</div>
                <div class="stat-value">{{ number_format($challengeStats['avg_progress'], 1) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-bar-chart-2"></i>
                    <span>{{ $challenge->target_unit ?? 'وحدة' }}</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-star"></i></div>
                <div class="stat-label">نقاط موزعة</div>
                <div class="stat-value">{{ number_format($challengeStats['points_distributed']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-gift"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

        <!-- التبويبات -->
        <div class="card table-card-modern">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs pts-nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="participants-tab" data-toggle="tab" href="#participants" role="tab">
                            <i class="feather icon-users"></i> المشاركون
                            <span class="badge badge-primary badge-pill">{{ $challengeStats['total_participants'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-tab" data-toggle="tab" href="#details" role="tab">
                            <i class="feather icon-info"></i> التفاصيل والقواعد
                        </a>
                    </li>
                    @if($teams && $teams->count() > 0)
                    <li class="nav-item">
                        <a class="nav-link" id="teams-tab" data-toggle="tab" href="#teamsTab" role="tab">
                            <i class="feather icon-flag"></i> الفرق
                            <span class="badge badge-info badge-pill">{{ $teams->count() }}</span>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link" id="transactions-tab" data-toggle="tab" href="#transactionsTab" role="tab">
                            <i class="feather icon-list"></i> معاملات التحدي
                            <span class="badge badge-warning badge-pill">{{ $challengeStats['total_transactions'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="actions-tab" data-toggle="tab" href="#actionsTab" role="tab">
                            <i class="feather icon-zap"></i> إجراءات
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- تبويب المشاركين -->
                    <div class="tab-pane fade show active" id="participants" role="tabpanel">
                        {{-- زر إضافة مشارك --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="text-muted">
                                    <i class="feather icon-users"></i>
                                    إجمالي {{ $challengeStats['total_participants'] }} مشارك |
                                    <span class="text-success">{{ $challengeStats['completed'] }} مكتمل</span> |
                                    <span class="text-info">{{ $challengeStats['in_progress'] }} قيد التنفيذ</span>
                                </span>
                            </div>
                            <div class="pts-btn-group">
                                <a href="{{ route('ecosystem.points.challenges.leaderboard', $challenge) }}" class="pts-btn pts-btn-sm pts-btn-secondary">
                                    <i class="feather icon-bar-chart-2"></i> المتصدرون
                                </a>
                                @if(!$isTeamChallenge)
                                <a href="{{ route('ecosystem.points.challenges.bulk-enroll-form', $challenge) }}" class="pts-btn pts-btn-sm pts-btn-success">
                                    <i class="feather icon-users"></i> إضافة مشاركين
                                </a>
                                <button type="button" class="pts-btn pts-btn-sm pts-btn-primary" data-toggle="modal" data-target="#enrollModal">
                                    <i class="feather icon-user-plus"></i> تسجيل مشارك
                                </button>
                                @else
                                <a href="{{ route('ecosystem.points.challenges.teams.index', ['challenge_id' => $challenge->id]) }}" class="pts-btn pts-btn-sm pts-btn-info">
                                    <i class="feather icon-users"></i> إدارة الفرق
                                </a>
                                @endif
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="pts-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 22%;">المشارك</th>
                                        <th style="width: 10%;">النوع</th>
                                        <th style="width: 15%;">التقدم</th>
                                        <th style="width: 18%;">النسبة</th>
                                        <th style="width: 10%;">النقاط</th>
                                        <th style="width: 10%;">الحالة</th>
                                        <th style="width: 10%;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($participants as $index => $participant)
                                        @php
                                            $progressPct = $participant->target_value_snapshot > 0
                                                ? round(($participant->current_progress / $participant->target_value_snapshot) * 100, 1)
                                                : 0;
                                            $progressBarWidth = min(100, $progressPct);
                                        @endphp
                                        <tr>
                                            <td class="cell-id">
                                                @if($participant->rank)
                                                    @if($participant->rank === 1)
                                                        <span style="font-size: 1.2rem;">🥇</span>
                                                    @elseif($participant->rank === 2)
                                                        <span style="font-size: 1.2rem;">🥈</span>
                                                    @elseif($participant->rank === 3)
                                                        <span style="font-size: 1.2rem;">🥉</span>
                                                    @else
                                                        <span class="pts-badge pts-badge-secondary">{{ $participant->rank }}</span>
                                                    @endif
                                                @else
                                                    {{ $participants->firstItem() + $index }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if($participant->employee)
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                             style="width: 32px; height: 32px; background: rgba(255, 159, 67, 0.12); color: #ff9f43; font-size: 0.85rem; flex-shrink: 0;">
                                                            <i class="feather icon-user"></i>
                                                        </div>
                                                        <span class="font-weight-bold">{{ $participant->employee->full_name ?? $participant->employee->first_name }}</span>
                                                    @elseif($participant->client)
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                             style="width: 32px; height: 32px; background: rgba(0, 207, 232, 0.12); color: #00cfe8; font-size: 0.85rem; flex-shrink: 0;">
                                                            <i class="feather icon-users"></i>
                                                        </div>
                                                        <span class="font-weight-bold">{{ $participant->client->full_name ?? $participant->client->first_name }}</span>
                                                    @else
                                                        <span class="text-muted">غير معروف</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pts-badge pts-badge-{{ $participant->employee_id ? 'warning' : 'info' }}">
                                                    {{ $participant->employee_id ? 'موظف' : 'عميل' }}
                                                </span>
                                            </td>
                                            <td>
                                                <strong>{{ number_format($participant->current_progress) }}</strong>
                                                <small class="text-muted">/ {{ number_format($participant->target_value_snapshot) }}</small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 ml-2" style="height: 8px; border-radius: 4px;">
                                                        <div class="progress-bar"
                                                             role="progressbar"
                                                             style="width: {{ $progressBarWidth }}%; background: linear-gradient(90deg, {{ $progressPct >= 100 ? '#28c76f' : ($progressPct >= 50 ? '#00cfe8' : '#ff9f43') }} 0%, {{ $progressPct >= 100 ? '#48da89' : ($progressPct >= 50 ? '#1edfe8' : '#ffb976') }} 100%);"
                                                             aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <span class="font-weight-bold" style="min-width: 42px; text-align: left; font-size: 0.85rem; color: {{ $progressPct >= 100 ? '#28c76f' : '#5e5873' }};">{{ $progressPct }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($participant->points_awarded)
                                                    <span class="cell-points-positive">
                                                        <i class="feather icon-star"></i>
                                                        {{ number_format($participant->points_awarded) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($participant->is_completed)
                                                    <span class="pts-badge pts-badge-success">
                                                        <i class="feather icon-check"></i> مكتمل
                                                    </span>
                                                @else
                                                    <span class="pts-badge pts-badge-secondary">قيد التنفيذ</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$isTeamChallenge && !$participant->points_awarded)
                                                    <form action="{{ route('ecosystem.points.challenges.remove-participant', [$challenge, $participant]) }}"
                                                          method="POST" class="remove-participant-form d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="pts-btn pts-btn-sm pts-btn-danger" title="إزالة" style="padding: 0.35rem 0.6rem;">
                                                            <i class="feather icon-x"></i>
                                                        </button>
                                                    </form>
                                                @elseif($isTeamChallenge)
                                                    <span class="text-muted" title="يتم من إدارة الفرق"><i class="feather icon-lock"></i></span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="pts-empty-state">
                                                    <div class="empty-icon">
                                                        <i class="feather icon-users"></i>
                                                    </div>
                                                    <h5>لا يوجد مشاركون بعد</h5>
                                                    @if($isTeamChallenge)
                                                        <p>هذا تحدي فرق - يتم إضافة المشاركين من خلال إدارة الفرق</p>
                                                        <a href="{{ route('ecosystem.points.challenges.teams.index', ['challenge_id' => $challenge->id]) }}" class="pts-btn pts-btn-info mt-2">
                                                            <i class="feather icon-users"></i> إدارة الفرق
                                                        </a>
                                                    @else
                                                        <p>قم بتسجيل المشاركين عبر صفحة إضافة المشاركين</p>
                                                        <a href="{{ route('ecosystem.points.challenges.bulk-enroll-form', $challenge) }}" class="pts-btn pts-btn-success mt-2">
                                                            <i class="feather icon-users"></i> إضافة مشاركين
                                                        </a>
                                                        <button type="button" class="pts-btn pts-btn-primary mt-2 mr-1" data-toggle="modal" data-target="#enrollModal">
                                                            <i class="feather icon-user-plus"></i> تسجيل مشارك واحد
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @include('ecosystem::points.partials._smart-pagination', ['paginator' => $participants, 'label' => 'مشارك', 'style' => 'pts'])
                    </div>

                    <!-- تبويب التفاصيل -->
                    <div class="tab-pane fade" id="details" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="font-weight-bold mb-3"><i class="feather icon-info mr-1 text-primary"></i> معلومات التحدي</h6>
                                <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #eee; border-radius: 12px;">
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">الهدف</span>
                                        <div>
                                            @foreach($challenge->getTargetActionLabels() as $label)
                                                <span class="badge badge-light-primary" style="font-size: 0.8rem; margin: 1px;">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">القيمة المستهدفة</span>
                                        <strong>{{ number_format($challenge->target_value) }} {{ $challenge->target_unit }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">المكافأة الأولى</span>
                                        <strong class="text-success"><i class="feather icon-star text-warning"></i> {{ number_format($challenge->reward_points) }} نقطة</strong>
                                    </div>
                                    @if($challenge->reward_points_2nd)
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">المركز الثاني</span>
                                        <strong>{{ number_format($challenge->reward_points_2nd) }} نقطة</strong>
                                    </div>
                                    @endif
                                    @if($challenge->reward_points_3rd)
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">المركز الثالث</span>
                                        <strong>{{ number_format($challenge->reward_points_3rd) }} نقطة</strong>
                                    </div>
                                    @endif
                                    @if($challenge->max_winners)
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">أقصى فائزين</span>
                                        <strong>{{ $challenge->max_winners }}</strong>
                                    </div>
                                    @endif
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">البداية</span>
                                        <strong>{{ $challenge->starts_at ? $challenge->starts_at->format('Y/m/d H:i') : '-' }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">النهاية</span>
                                        <strong>{{ $challenge->ends_at ? $challenge->ends_at->format('Y/m/d H:i') : '-' }}</strong>
                                    </div>
                                    @if($challenge->minTier)
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span class="text-muted">الحد الأدنى للمستوى</span>
                                        <strong>{{ $challenge->minTier->name }}</strong>
                                    </div>
                                    @endif
                                    @if($challenge->creator)
                                    <div class="d-flex justify-content-between py-2">
                                        <span class="text-muted">أنشأه</span>
                                        <strong>{{ $challenge->creator->name }}</strong>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                @if($challenge->description)
                                    <h6 class="font-weight-bold mb-3"><i class="feather icon-file-text mr-1 text-primary"></i> الوصف</h6>
                                    <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #eee; border-radius: 12px; line-height: 1.8;">
                                        {!! nl2br(e($challenge->description)) !!}
                                    </div>
                                @endif

                                @if($challenge->rules)
                                    <h6 class="font-weight-bold mb-3 {{ $challenge->description ? 'mt-4' : '' }}"><i class="feather icon-book-open mr-1 text-primary"></i> القواعد</h6>
                                    <div class="p-3 rounded" style="background: linear-gradient(135deg, #fff8e1 0%, #fff 100%); border: 1px solid #ffe08266; border-radius: 12px; line-height: 1.8;">
                                        {!! nl2br(e($challenge->rules)) !!}
                                    </div>
                                @endif

                                @if(!$challenge->description && !$challenge->rules)
                                    <div class="pts-empty-state" style="min-height: 200px;">
                                        <div class="empty-icon">
                                            <i class="feather icon-file-text"></i>
                                        </div>
                                        <h5>لا يوجد وصف أو قواعد</h5>
                                        <p>يمكنك إضافتها من صفحة التعديل</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- تبويب الفرق -->
                    @if($teams && $teams->count() > 0)
                    <div class="tab-pane fade" id="teamsTab" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted"><i class="feather icon-flag"></i> إجمالي {{ $teams->count() }} فريق</span>
                            <a href="{{ route('ecosystem.points.challenges.teams.index', ['challenge_id' => $challenge->id]) }}" class="pts-btn pts-btn-sm pts-btn-primary">
                                <i class="feather icon-settings"></i> إدارة الفرق
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="pts-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 30%;">الفريق</th>
                                        <th style="width: 20%;">القائد</th>
                                        <th style="width: 15%;">الأعضاء</th>
                                        <th style="width: 15%;">التقدم</th>
                                        <th style="width: 15%;">النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($teams as $i => $team)
                                    <tr>
                                        <td class="cell-id">{{ $i + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center ml-2"
                                                     style="width: 32px; height: 32px; background: rgba(115, 103, 240, 0.12); color: #7367f0; font-size: 0.85rem; flex-shrink: 0;">
                                                    <i class="feather icon-flag"></i>
                                                </div>
                                                <strong>{{ $team->name }}</strong>
                                            </div>
                                        </td>
                                        <td>{{ $team->leader ? $team->leader->full_name : '-' }}</td>
                                        <td>
                                            <span class="pts-badge pts-badge-primary">{{ $team->members_count }} عضو</span>
                                        </td>
                                        <td><strong>{{ number_format($team->total_progress) }}</strong></td>
                                        <td>
                                            <span class="cell-points-positive">{{ number_format($team->total_points_earned ?? 0) }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- تبويب معاملات التحدي -->
                    <div class="tab-pane fade" id="transactionsTab" role="tabpanel">
                        <div class="table-card-modern">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-weight-bold mb-0">
                                    <i class="feather icon-list text-warning"></i> سجل معاملات التحدي
                                </h5>
                                <span class="pts-badge warning">{{ $challengeTransactions->total() }} معاملة</span>
                            </div>

                            @if($challengeTransactions->count() > 0)
                            <div class="table-responsive">
                                <table class="table pts-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>المستخدم</th>
                                            <th>نوع الإجراء</th>
                                            <th>التقدم</th>
                                            <th>النقاط</th>
                                            <th>المبلغ</th>
                                            <th>الملاحظة</th>
                                            <th>التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($challengeTransactions as $index => $ct)
                                        <tr>
                                            <td>{{ $challengeTransactions->firstItem() + $index }}</td>
                                            <td>
                                                <span class="font-weight-bold">{{ $ct->getUserName() }}</span>
                                                <br>
                                                <small class="text-muted">
                                                    {{ $ct->user_type == \App\Enums\UserType::EMPLOYEE ? 'موظف' : 'عميل' }}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="pts-badge info">{{ $ct->getActionLabel() }}</span>
                                            </td>
                                            <td>
                                                <span class="text-muted">{{ $ct->progress_before }}</span>
                                                <i class="feather icon-arrow-left mx-1 text-success"></i>
                                                <span class="font-weight-bold text-success">{{ $ct->progress_after }}</span>
                                                <br>
                                                <small class="text-muted">(+{{ $ct->progress_value }})</small>
                                            </td>
                                            <td>
                                                @if($ct->points)
                                                    <span class="font-weight-bold text-primary">{{ number_format($ct->points) }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($ct->amount > 0)
                                                    <span class="font-weight-bold">{{ number_format($ct->amount, 2) }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $ct->description ?? '-' }}</small>
                                            </td>
                                            <td>
                                                <small>{{ $ct->created_at->format('Y/m/d') }}</small>
                                                <br>
                                                <small class="text-muted">{{ $ct->created_at->format('h:i A') }}</small>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @include('ecosystem::points.partials._smart-pagination', ['paginator' => $challengeTransactions, 'label' => 'معاملة', 'style' => 'pts', 'appends' => request()->except('transactions_page')])

                            @else
                            <div class="text-center py-5">
                                <i class="feather icon-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                <p class="text-muted mt-3">لا توجد معاملات مسجلة لهذا التحدي بعد</p>
                                <small class="text-muted">ستظهر المعاملات هنا عند إنشاء معاملات نقاط مرتبطة بهذا التحدي</small>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- تبويب الإجراءات -->
                    <div class="tab-pane fade" id="actionsTab" role="tabpanel">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="text-center mb-4">
                                    <h5 class="font-weight-bold"><i class="feather icon-zap text-primary"></i> إجراءات سريعة</h5>
                                    <p class="text-muted">إدارة التحدي والمشاركين</p>
                                </div>

                                <div class="row">
                                {{-- تسجيل مشارك --}}
                                    @if(!$isTeamChallenge)
                                    <div class="col-md-6 mb-3">
                                        <a href="{{ route('ecosystem.points.challenges.bulk-enroll-form', $challenge) }}" class="text-decoration-none">
                                            <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, #28c76f11 0%, #28c76f22 100%); border: 1px solid #28c76f33; border-radius: 12px;">
                                                <i class="feather icon-users text-success" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-1 font-weight-bold text-dark">إضافة مشاركين</h6>
                                                <small class="text-muted">إضافة عدة مشاركين دفعة واحدة</small>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, #7367f011 0%, #7367f022 100%); border: 1px solid #7367f033; border-radius: 12px; cursor: pointer;" data-toggle="modal" data-target="#enrollModal">
                                            <i class="feather icon-user-plus text-primary" style="font-size: 2rem;"></i>
                                            <h6 class="mt-2 mb-1 font-weight-bold">تسجيل مشارك</h6>
                                            <small class="text-muted">إضافة مشارك واحد من النافذة</small>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- لوحة المتصدرين --}}
                                    <div class="col-md-6 mb-3">
                                        <a href="{{ route('ecosystem.points.challenges.leaderboard', $challenge) }}" class="text-decoration-none">
                                            <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, #ff9f4311 0%, #ff9f4322 100%); border: 1px solid #ff9f4333; border-radius: 12px;">
                                                <i class="feather icon-bar-chart-2 text-warning" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-1 font-weight-bold text-dark">لوحة المتصدرين</h6>
                                                <small class="text-muted">عرض الترتيب التفصيلي</small>
                                            </div>
                                        </a>
                                    </div>

                                    @if($challenge->challenge_type instanceof \App\Enums\ChallengeType && $challenge->challenge_type === \App\Enums\ChallengeType::TEAM)
                                    {{-- إدارة الفرق --}}
                                    <div class="col-md-6 mb-3">
                                        <a href="{{ route('ecosystem.points.challenges.teams.index', ['challenge_id' => $challenge->id]) }}" class="text-decoration-none">
                                            <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, #00cfe811 0%, #00cfe822 100%); border: 1px solid #00cfe833; border-radius: 12px;">
                                                <i class="feather icon-users text-info" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-1 font-weight-bold text-dark">إدارة الفرق</h6>
                                                <small class="text-muted">تعديل وإدارة فرق التحدي</small>
                                            </div>
                                        </a>
                                    </div>
                                    @endif

                                    @if($challenge->hasEnded() && !$challenge->results_announced_at)
                                    {{-- إنهاء وتوزيع --}}
                                    <div class="col-md-6 mb-3">
                                        <form action="{{ route('ecosystem.points.challenges.finalize', $challenge) }}" method="POST" id="finalizeForm">
                                            @csrf
                                            <div class="p-3 rounded text-center h-100" style="background: linear-gradient(135deg, #28c76f11 0%, #28c76f22 100%); border: 1px solid #28c76f33; border-radius: 12px; cursor: pointer;" id="finalizeCard">
                                                <i class="feather icon-award text-success" style="font-size: 2rem;"></i>
                                                <h6 class="mt-2 mb-1 font-weight-bold">إنهاء وتوزيع الجوائز</h6>
                                                <small class="text-muted">حساب الترتيب وتوزيع النقاط</small>
                                            </div>
                                        </form>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal تسجيل مشارك --}}
    <div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%); color: #fff; border: none;">
                    <h5 class="modal-title"><i class="feather icon-user-plus"></i> تسجيل مشارك جديد</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form action="{{ route('ecosystem.points.challenges.enroll', $challenge) }}" method="POST">
                    @csrf
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div class="form-group">
                            <label for="enroll_user_type" class="font-weight-bold">
                                <i class="feather icon-users mr-1"></i> نوع المستخدم <span class="text-danger">*</span>
                            </label>
                            <select id="enroll_user_type" name="user_type" class="form-control" required style="border-radius: 8px; padding: 0.75rem;"
                                @if($challengeUserTypeValue !== 'both') disabled @endif>
                                @if($challengeUserTypeValue === 'employee')
                                    <option value="employee" selected>👤 موظف</option>
                                @elseif($challengeUserTypeValue === 'client')
                                    <option value="client" selected>🛒 عميل</option>
                                @else
                                    <option value="">اختر النوع</option>
                                    <option value="employee">👤 موظف</option>
                                    <option value="client">🛒 عميل</option>
                                @endif
                            </select>
                            @if($challengeUserTypeValue !== 'both')
                                <input type="hidden" name="user_type" value="{{ $challengeUserTypeValue }}">
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="enroll_user_id" class="font-weight-bold">
                                <i class="feather icon-user mr-1"></i> المستخدم <span class="text-danger">*</span>
                            </label>
                            <select id="enroll_user_id" name="user_id" class="form-control" required style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">اختر أولاً نوع المستخدم</option>
                            </select>
                        </div>
                        @if($teams && $teams->count() > 0)
                        <div class="form-group">
                            <label for="enroll_team_id" class="font-weight-bold">
                                <i class="feather icon-flag mr-1"></i> الفريق
                            </label>
                            <select id="enroll_team_id" name="team_id" class="form-control" style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">بدون فريق</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #eee;">
                        <button type="button" class="pts-btn pts-btn-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="submit" class="pts-btn pts-btn-primary">
                            <i class="feather icon-check"></i> تسجيل
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // حذف التحدي
    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا التحدي نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("ecosystem.points.challenges.destroy", $challenge) }}';
                var token = document.createElement('input');
                token.type = 'hidden'; token.name = '_token'; token.value = '{{ csrf_token() }}';
                var method = document.createElement('input');
                method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
                form.appendChild(token);
                form.appendChild(method);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // إنهاء التحدي
    $('#finalizeCard').on('click', function() {
        Swal.fire({
            title: 'إنهاء التحدي وتوزيع الجوائز',
            text: 'سيتم حساب الترتيب النهائي وتوزيع النقاط. لا يمكن التراجع عن هذا!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'نعم، وزّع الجوائز',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('finalizeForm').submit();
            }
        });
    });

    // دالة تحميل المستخدمين
    function loadUsers(userType) {
        var $userSelect = $('#enroll_user_id');
        $userSelect.empty().append('<option value="">جاري التحميل...</option>');

        if (!userType) {
            $userSelect.empty().append('<option value="">اختر أولاً نوع المستخدم</option>');
            return;
        }

        $.ajax({
            url: '{{ route("ecosystem.points.challenges.get-users") }}',
            data: { user_type: userType, challenge_id: '{{ $challenge->id }}' },
            success: function(users) {
                $userSelect.empty().append('<option value="">اختر المستخدم</option>');
                users.forEach(function(user) {
                    $userSelect.append('<option value="' + user.id + '">' + user.name + '</option>');
                });
            },
            error: function() {
                $userSelect.empty().append('<option value="">خطأ في تحميل البيانات</option>');
            }
        });
    }

    // تحميل المستخدمين عند تغيير النوع في modal
    $('#enroll_user_type').on('change', function() {
        loadUsers($(this).val());
    });

    // تحميل تلقائي إذا كان نوع المستخدم محدد مسبقاً
    $('#enrollModal').on('shown.bs.modal', function() {
        var preselected = $('#enroll_user_type').val();
        if (preselected) {
            loadUsers(preselected);
        }
    });

    // إزالة مشارك
    $('.remove-participant-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'إزالة المشارك؟',
            text: 'هل تريد إزالة هذا المشارك من التحدي؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، أزل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endpush
