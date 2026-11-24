{{-- 
    شريط الإنجازات - يعرض الإنجازات المتاحة كشريط أفقي مع حالة التحقيق
    المتغيرات المطلوبة:
    - $availableAchievements: جميع الإنجازات المتاحة للمستخدم
    - $userAchievements: الإنجازات المحققة
    - $ribbonTitle: عنوان الشريط (اختياري)
    - $ribbonSubtitle: عنوان فرعي (اختياري)
--}}

@if(isset($availableAchievements) && $availableAchievements->count() > 0)
@php
    $achievedIds = $userAchievements->pluck('achievement_id')->unique()->toArray();
    $achievedCount = count(array_intersect($achievedIds, $availableAchievements->pluck('id')->toArray()));
    $totalCount = $availableAchievements->count();
    $progressPercent = $totalCount > 0 ? round(($achievedCount / $totalCount) * 100) : 0;
@endphp

<div class="card achievements-ribbon-card mb-3">
    <div class="card-body p-3">
        {{-- رأس الشريط --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <div class="ribbon-icon-wrapper ml-2">
                    <i class="feather icon-award"></i>
                </div>
                <div>
                    <h6 class="mb-0 font-weight-bold" style="font-size: 0.95rem;">
                        {{ $ribbonTitle ?? 'الإنجازات' }}
                    </h6>
                    <small class="text-muted">
                        {{ $ribbonSubtitle ?? ($achievedCount . ' من ' . $totalCount . ' إنجاز محقق') }}
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <div class="ribbon-progress-mini ml-2">
                    <div class="progress" style="width: 80px; height: 6px; border-radius: 3px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: {{ $progressPercent }}%; background: linear-gradient(90deg, #28c76f, #48da89);"
                             aria-valuenow="{{ $progressPercent }}"></div>
                    </div>
                </div>
                <span class="badge badge-pill badge-light-success" style="font-size: 0.75rem;">
                    {{ $progressPercent }}%
                </span>
            </div>
        </div>

        {{-- شريط الإنجازات الأفقي --}}
        <div class="achievements-ribbon-scroll">
            <div class="achievements-ribbon-track">
                @foreach($availableAchievements->sortBy('sort_order') as $achievement)
                    @php
                        $isAchieved = in_array($achievement->id, $achievedIds);
                        $achievementDate = null;
                        if ($isAchieved) {
                            $ua = $userAchievements->where('achievement_id', $achievement->id)->first();
                            $achievementDate = $ua ? ($ua->achieved_at ?? $ua->created_at) : null;
                        }
                    @endphp
                    <div class="ribbon-achievement-item {{ $isAchieved ? 'achieved' : 'locked' }}" 
                         title="{{ $achievement->name }}: {{ $achievement->description }}{{ $isAchieved && $achievementDate ? ' - تم في ' . $achievementDate->format('Y/m/d') : '' }}"
                         data-toggle="tooltip" data-placement="top">
                        <div class="ribbon-badge-circle" style="{{ $isAchieved ? 'background: linear-gradient(135deg, ' . ($achievement->color ?? '#28c76f') . ' 0%, ' . ($achievement->color ?? '#28c76f') . 'cc 100%); box-shadow: 0 4px 12px ' . ($achievement->color ?? '#28c76f') . '44;' : 'background: #e8e8e8; opacity: 0.5;' }}">
                            @if($achievement->icon)
                                @include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '1.2rem'])
                            @else
                                <i class="feather icon-award" style="font-size: 1.2rem; color: {{ $isAchieved ? '#fff' : '#999' }};"></i>
                            @endif
                            @if($isAchieved)
                                <div class="ribbon-check-mark">
                                    <i class="feather icon-check"></i>
                                </div>
                            @endif
                        </div>
                        <div class="ribbon-achievement-name {{ $isAchieved ? '' : 'text-muted' }}">
                            {{ Str::limit($achievement->name, 12) }}
                        </div>
                        @if($isAchieved)
                            <div class="ribbon-achievement-points">
                                +{{ $achievement->bonus_points }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<style>
.achievements-ribbon-card {
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 50%, #f0f4f8 100%);
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    overflow: hidden;
}

.ribbon-icon-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
}

.achievements-ribbon-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #ccc transparent;
    padding-bottom: 4px;
}

.achievements-ribbon-scroll::-webkit-scrollbar {
    height: 4px;
}

.achievements-ribbon-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.achievements-ribbon-scroll::-webkit-scrollbar-thumb {
    background: #ddd;
    border-radius: 2px;
}

.achievements-ribbon-track {
    display: flex;
    gap: 16px;
    padding: 4px 2px;
    min-width: max-content;
}

.ribbon-achievement-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 70px;
    max-width: 80px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.ribbon-achievement-item:hover {
    transform: translateY(-3px);
}

.ribbon-achievement-item.locked {
    filter: grayscale(0.3);
}

.ribbon-badge-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s ease;
    color: #fff;
}

.ribbon-achievement-item.achieved .ribbon-badge-circle {
    animation: ribbonPulse 2s infinite;
}

@keyframes ribbonPulse {
    0%, 100% { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    50% { box-shadow: 0 6px 20px rgba(0,0,0,0.25); }
}

.ribbon-check-mark {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #28c76f;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    border: 2px solid #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.ribbon-achievement-name {
    font-size: 0.7rem;
    font-weight: 600;
    text-align: center;
    margin-top: 6px;
    line-height: 1.2;
    word-break: break-word;
    color: #333;
}

.ribbon-achievement-points {
    font-size: 0.65rem;
    font-weight: 700;
    color: #28c76f;
    margin-top: 2px;
}
</style>
@endif
