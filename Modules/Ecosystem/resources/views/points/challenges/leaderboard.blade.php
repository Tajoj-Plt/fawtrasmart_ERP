@extends('ecosystem::master')

@section('title', 'لوحة المتصدرين')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">لوحة المتصدرين</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.index') }}">التحديات</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.challenges.show', $challenge) }}">{{ $challenge->name }}</a></li>
                        <li class="breadcrumb-item active">المتصدرون</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12">
        <a href="{{ route('ecosystem.points.challenges.show', $challenge) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> رجوع للتحدي
        </a>
    </div>
</div>

<div class="content-body">
    @php
        // حساب عدد الفائزين حسب نوع المنافسة
        $competitionTypeValue = $challenge->competition_type instanceof \App\Enums\CompetitionType
            ? $challenge->competition_type->value
            : (string)$challenge->competition_type;

        switch($competitionTypeValue) {
            case 'target':
                $topCount = 1;
                $competitionLabel = 'هدف محدد (فائز واحد)';
                break;
            case 'ranking':
                $topCount = $challenge->max_winners ?? 3;
                $competitionLabel = 'ترتيب (أفضل ' . $topCount . ')';
                break;
            case 'first_n':
                $topCount = $challenge->max_winners ?? 3;
                $competitionLabel = 'أول ' . $topCount . ' فائزين';
                break;
            default:
                $topCount = 3;
                $competitionLabel = 'ترتيب';
        }

        // ألوان المراكز (ذهبي، فضي، برونزي، ثم ألوان متنوعة)
        $podiumColors = ['#FFD700', '#C0C0C0', '#CD7F32', '#7367f0', '#00cfe8', '#ff9f43', '#28c76f', '#ea5455', '#636363', '#1e9ff2'];
        $podiumMedals = ['🥇', '🥈', '🥉'];
    @endphp

    {{-- معلومات التحدي --}}
    <div class="card bg-gradient-primary text-white mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="text-white mb-0">{{ $challenge->name }}</h3>
                    <p class="mb-0 mt-1" style="opacity:0.8;">
                        الهدف: {{ number_format($challenge->target_value) }} {{ $challenge->target_unit ?? '' }} |
                        المكافأة: {{ number_format($challenge->reward_points) }} نقطة |
                        نوع المنافسة: {{ $competitionLabel }}
                    </p>
                </div>
                <div class="text-center">
                    @if($challenge->isOngoing())
                        <span class="badge badge-success badge-lg" style="font-size: 1rem; padding: 8px 16px;">
                            <i class="feather icon-clock"></i> متبقي {{ $challenge->getDaysRemaining() }} يوم
                        </span>
                    @elseif($challenge->hasEnded())
                        <span class="badge badge-warning badge-lg" style="font-size: 1rem; padding: 8px 16px;">
                            <i class="feather icon-flag"></i> انتهى
                        </span>
                    @else
                        <span class="badge badge-info badge-lg" style="font-size: 1rem; padding: 8px 16px;">
                            <i class="feather icon-calendar"></i> قادم
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- أفضل المتصدرين (ديناميكي حسب نوع المنافسة) --}}
    @php
        $topItems = $isTeamChallenge ? ($teams ?? collect()) : ($participants ?? collect());
        $showTopCount = min($topCount, $topItems->count());

        // حساب حجم الأعمدة
        if ($showTopCount == 1) {
            $colClass = 'col-md-6';
        } elseif ($showTopCount == 2) {
            $colClass = 'col-md-5';
        } elseif ($showTopCount <= 3) {
            $colClass = 'col-md-4';
        } elseif ($showTopCount == 4) {
            $colClass = 'col-md-3';
        } else {
            $colClass = 'col-md-4';
        }
    @endphp
    @if($showTopCount >= 1)
    <div class="row mb-2 justify-content-center">
        @foreach($topItems->take($showTopCount) as $index => $top)
            @php
                $color = $podiumColors[$index] ?? '#636363';
                if ($isTeamChallenge) {
                    $topProgress = $top->total_progress;
                    $topTarget = $challenge->target_value ?? 0;
                    $topName = $top->name;
                    $topSubtitle = $top->members_count . ' أعضاء';
                } else {
                    $topProgress = $top->current_progress;
                    $topTarget = $top->target_value_snapshot;
                    $topName = $top->employee ? ($top->employee->full_name ?? $top->employee->first_name) : ($top->client ? ($top->client->full_name ?? $top->client->first_name) : 'غير معروف');
                    $topSubtitle = $top->employee_id ? 'موظف' : 'عميل';
                }
                $progressPct = $topTarget > 0
                    ? round(($topProgress / $topTarget) * 100, 1) : 0;
                $progressBarWidth = min(100, $progressPct);
            @endphp
            <div class="{{ $colClass }} mb-1">
                <div class="card text-center" style="border-top: 3px solid {{ $color }};">
                    <div class="card-body">
                        @if($index < 3)
                            <div style="font-size: 2.5rem;">{{ $podiumMedals[$index] }}</div>
                        @else
                            <div>
                                <span class="badge badge-pill" style="background: {{ $color }}; color: #fff; font-size: 1.3rem; width: 45px; height: 45px; line-height: 30px; padding: 8px;">
                                    {{ $index + 1 }}
                                </span>
                            </div>
                        @endif
                        <h5 class="mt-1 mb-0">{{ $topName }}</h5>
                        <small class="text-muted">{{ $topSubtitle }}</small>
                        <div class="mt-1">
                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                <div class="progress-bar" style="width: {{ $progressBarWidth }}%; background: {{ $color }};"
                                     role="progressbar"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-50">
                                <small>{{ number_format($topProgress) }} / {{ number_format($topTarget) }}</small>
                                <small class="font-weight-bold">{{ $progressPct }}%</small>
                            </div>
                        </div>
                        @php
                            $pointsAwarded = $isTeamChallenge ? ($top->members ? $top->members->sum('points_awarded') : 0) : $top->points_awarded;
                        @endphp
                        @if($pointsAwarded)
                            <div class="mt-1">
                                <span class="badge badge-warning" style="font-size: 0.9rem;">
                                    <i class="feather icon-star"></i> {{ number_format($pointsAwarded) }} نقطة
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- جدول كامل --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">
                <i class="feather icon-bar-chart-2"></i>
                {{ $isTeamChallenge ? 'ترتيب الفرق' : 'الترتيب الكامل' }}
            </h4>
            <span class="badge badge-light-primary" style="font-size: 0.85rem; padding: 6px 12px;">
                <i class="feather icon-award"></i> {{ $competitionLabel }}
            </span>
        </div>
        <div class="card-content">
            <div class="table-responsive">
                @if($isTeamChallenge && $teams)
                {{-- جدول الفرق --}}
                <table class="table modern-table mb-0">
                    <thead>
                        <tr>
                            <th width="5%">المركز</th>
                            <th width="25%">الفريق</th>
                            <th width="10%">الأعضاء</th>
                            <th width="20%">التقدم</th>
                            <th width="15%">النسبة</th>
                            <th width="10%">نقاط</th>
                            <th width="15%">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teams as $index => $team)
                            @php
                                $teamTarget = $challenge->target_value ?? 0;
                                $teamProgressPct = $teamTarget > 0
                                    ? round(($team->total_progress / $teamTarget) * 100, 1) : 0;
                                $teamBarWidth = min(100, $teamProgressPct);
                                $rankDisplay = $team->rank ?? ($teams->firstItem() + $index);
                                $teamPointsAwarded = $team->members ? $team->members->sum('points_awarded') : 0;
                            @endphp
                            <tr class="{{ $rankDisplay <= $topCount ? 'table-light' : '' }}">
                                <td>
                                    @if($rankDisplay <= 3 && isset($podiumMedals[$rankDisplay - 1]))
                                        <span style="font-size: 1.3rem;">{{ $podiumMedals[$rankDisplay - 1] }}</span>
                                    @elseif($rankDisplay <= $topCount)
                                        <span class="badge badge-pill" style="background: {{ $podiumColors[$rankDisplay - 1] ?? '#636363' }}; color: #fff; font-size: 0.85rem; padding: 5px 10px;">{{ $rankDisplay }}</span>
                                    @else
                                        <span class="badge badge-light-secondary">{{ $rankDisplay }}</span>
                                    @endif
                                </td>
                                <td>
                                    <i class="feather icon-users text-primary"></i>
                                    <strong>{{ $team->name }}</strong>
                                    @if($team->leader)
                                        <br><small class="text-muted">القائد: {{ $team->leader->full_name ?? $team->leader->first_name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light-primary">{{ $team->members_count }} عضو</span>
                                </td>
                                <td>
                                    <strong>{{ number_format($team->total_progress) }}</strong>
                                    <small class="text-muted">/ {{ number_format($teamTarget) }}</small>
                                </td>
                                <td>
                                    <div class="progress" style="height: 8px; border-radius: 4px;">
                                        <div class="progress-bar bg-{{ $teamProgressPct >= 100 ? 'success' : ($teamProgressPct >= 50 ? 'info' : 'warning') }}"
                                             style="width: {{ $teamBarWidth }}%"></div>
                                    </div>
                                    <small>{{ $teamProgressPct }}%</small>
                                </td>
                                <td>
                                    @if($teamPointsAwarded)
                                        <span class="text-success font-weight-bold">
                                            <i class="feather icon-star"></i> {{ number_format($teamPointsAwarded) }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($team->is_completed)
                                        <span class="badge badge-success">
                                            <i class="feather icon-check"></i> مكتمل
                                        </span>
                                    @else
                                        <span class="badge badge-light-secondary">قيد التنفيذ</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <p class="text-muted mb-0">لا توجد فرق بعد</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- أعضاء الفرق بالتفصيل --}}
                @if($teams->count() > 0)
                <div class="mt-3">
                    <h5 class="px-2"><i class="feather icon-user"></i> أعضاء الفرق</h5>
                    @foreach($teams as $team)
                        @if($team->members && $team->members->count() > 0)
                        <div class="px-2 mb-2">
                            <h6 class="text-muted">{{ $team->name }}</h6>
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>العضو</th>
                                        <th>التقدم</th>
                                        <th>النقاط</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($team->members->sortByDesc('current_progress') as $member)
                                    <tr>
                                        <td>
                                            @if($member->employee)
                                                <i class="feather icon-user text-warning"></i>
                                                {{ $member->employee->full_name ?? $member->employee->first_name }}
                                            @elseif($member->client)
                                                <i class="feather icon-users text-info"></i>
                                                {{ $member->client->full_name ?? $member->client->first_name }}
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ number_format($member->current_progress) }}</strong>
                                            <small class="text-muted">/ {{ number_format($member->target_value_snapshot) }}</small>
                                        </td>
                                        <td>
                                            @if($member->points_awarded)
                                                <span class="text-success">{{ number_format($member->points_awarded) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($member->is_completed)
                                                <span class="badge badge-success badge-sm">مكتمل</span>
                                            @else
                                                <span class="badge badge-light-secondary badge-sm">قيد التنفيذ</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endforeach
                </div>
                @endif

                @else
                {{-- جدول المشاركين الأفراد --}}
                <table class="table modern-table mb-0">
                    <thead>
                        <tr>
                            <th width="5%">المركز</th>
                            <th width="25%">المشارك</th>
                            <th width="10%">النوع</th>
                            <th width="20%">التقدم</th>
                            <th width="15%">النسبة</th>
                            <th width="10%">نقاط</th>
                            <th width="15%">الإكمال</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants as $index => $participant)
                            @php
                                $progressPct = $participant->target_value_snapshot > 0
                                    ? round(($participant->current_progress / $participant->target_value_snapshot) * 100, 1) : 0;
                                $progressBarWidth = min(100, $progressPct);
                                $rankDisplay = $participant->rank ?? ($participants->firstItem() + $index);
                            @endphp
                            <tr class="{{ $rankDisplay <= $topCount ? 'table-light' : '' }}">
                                <td>
                                    @if($rankDisplay <= 3 && isset($podiumMedals[$rankDisplay - 1]))
                                        <span style="font-size: 1.3rem;">{{ $podiumMedals[$rankDisplay - 1] }}</span>
                                    @elseif($rankDisplay <= $topCount)
                                        <span class="badge badge-pill" style="background: {{ $podiumColors[$rankDisplay - 1] ?? '#636363' }}; color: #fff; font-size: 0.85rem; padding: 5px 10px;">{{ $rankDisplay }}</span>
                                    @else
                                        <span class="badge badge-light-secondary">{{ $rankDisplay }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($participant->employee)
                                        <i class="feather icon-user text-warning"></i>
                                        <strong>{{ $participant->employee->full_name ?? $participant->employee->first_name }}</strong>
                                    @elseif($participant->client)
                                        <i class="feather icon-users text-info"></i>
                                        <strong>{{ $participant->client->full_name ?? $participant->client->first_name }}</strong>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light-{{ $participant->employee_id ? 'warning' : 'info' }}">
                                        {{ $participant->employee_id ? 'موظف' : 'عميل' }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ number_format($participant->current_progress) }}</strong>
                                    <small class="text-muted">/ {{ number_format($participant->target_value_snapshot) }}</small>
                                </td>
                                <td>
                                    <div class="progress" style="height: 8px; border-radius: 4px;">
                                        <div class="progress-bar bg-{{ $progressPct >= 100 ? 'success' : ($progressPct >= 50 ? 'info' : 'warning') }}"
                                             style="width: {{ $progressBarWidth }}%"></div>
                                    </div>
                                    <small>{{ $progressPct }}%</small>
                                </td>
                                <td>
                                    @if($participant->points_awarded)
                                        <span class="text-success font-weight-bold">
                                            <i class="feather icon-star"></i> {{ number_format($participant->points_awarded) }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($participant->is_completed)
                                        <span class="badge badge-success">
                                            <i class="feather icon-check"></i> مكتمل
                                        </span>
                                        @if($participant->completed_at)
                                            <br><small class="text-muted">{{ $participant->completed_at->format('m/d H:i') }}</small>
                                        @endif
                                    @else
                                        <span class="badge badge-light-secondary">قيد التنفيذ</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <p class="text-muted mb-0">لا يوجد مشاركون بعد</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @endif
            </div>

            @php
                $paginationSource = ($isTeamChallenge && $teams) ? $teams : $participants;
            @endphp
            @include('ecosystem::points.partials._smart-pagination', ['paginator' => $paginationSource, 'label' => 'نتيجة'])
        </div>
    </div>
</div>
@endsection
