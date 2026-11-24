{{-- شريط تقدم النقاط الشهرية مع المستويات --}}
@php
    $data = $monthlyPointsData ?? [];
    $tierPositions = $data['tier_positions'] ?? [];
    $monthlyPoints = $data['monthly_points'] ?? 0;
    $overallProgress = $data['overall_progress'] ?? 0;
    $currentTierMonthly = $data['current_tier'] ?? null;
    $nextTierMonthly = $data['next_tier'] ?? null;
    $pointsToNext = $data['points_to_next_tier'] ?? 0;
    $pointsToMax = $data['points_to_max_tier'] ?? 0;
    $maxTier = $data['max_tier'] ?? null;
    $monthName = $data['month_name'] ?? now()->translatedFormat('F Y');
    
    // إعادة حساب المواقع
    $maxPoints = 0;
    foreach($tierPositions as $tier) {
        if ($tier['min_points'] > $maxPoints) {
            $maxPoints = $tier['min_points'];
        }
    }
    $maxPoints = $maxPoints > 0 ? $maxPoints * 1.15 : 1000;
@endphp

<div class="pts-monthly-progress-card">
    <div class="pts-monthly-header">
        <div class="pts-monthly-title">
            <div class="pts-monthly-icon">
                <i class="feather icon-trending-up"></i>
            </div>
            <div>
                <h4>{{ $progressTitle ?? 'تقدم النقاط الشهرية' }}</h4>
                <span class="pts-monthly-period">{{ $monthName }}</span>
            </div>
        </div>
        <div class="pts-monthly-points-badge">
            <i class="feather icon-star"></i>
            <span class="pts-monthly-points-value">{{ number_format($monthlyPoints) }}</span>
            <span class="pts-monthly-points-label">نقطة صافي</span>
        </div>
    </div>
    
    <div class="pts-monthly-body">
        {{-- شريط التقدم الرئيسي --}}
        <div class="pts-progress-container">
            <div class="pts-progress-track">
                <div class="pts-progress-fill" style="width: {{ min($overallProgress, 100) }}%;"></div>
                
                {{-- علامات المستويات --}}
                @foreach($tierPositions as $index => $tier)
                    @php
                        $position = $maxPoints > 0 ? ($tier['min_points'] / $maxPoints) * 100 : 0;
                        $position = max(2, min($position, 98));
                    @endphp
                    <div class="pts-tier-marker" style="left: {{ $position }}%;">
                        <div class="pts-tier-marker-dot {{ $tier['is_current'] ? 'current' : ($tier['is_achieved'] ? 'achieved' : '') }}" 
                             style="--tier-color: {{ $tier['color'] }};">
                            @if($tier['is_current'])
                                <i class="feather icon-star"></i>
                            @elseif($tier['is_achieved'])
                                <i class="feather icon-check"></i>
                            @endif
                        </div>
                        <div class="pts-tier-marker-info">
                            <span class="pts-tier-marker-name" style="color: {{ $tier['color'] }};">{{ $tier['name'] }}</span>
                            <span class="pts-tier-marker-points">{{ number_format($tier['min_points']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        
        {{-- بطاقات المعلومات --}}
        <div class="pts-tier-info-cards">
            <div class="pts-tier-info-card current">
                <div class="pts-tier-info-icon" style="background: {{ $currentTierMonthly->color ?? '#7367f0' }}20; color: {{ $currentTierMonthly->color ?? '#7367f0' }};">
                    <i class="feather icon-award"></i>
                </div>
                <div class="pts-tier-info-details">
                    <span class="pts-tier-info-label">المستوى الحالي</span>
                    <span class="pts-tier-info-value" style="color: {{ $currentTierMonthly->color ?? '#5e5873' }};">
                        {{ $currentTierMonthly->name ?? 'بدون مستوى' }}
                    </span>
                </div>
            </div>
            
            @if($nextTierMonthly)
                <div class="pts-tier-info-card next">
                    <div class="pts-tier-info-icon" style="background: {{ $nextTierMonthly->color }}20; color: {{ $nextTierMonthly->color }};">
                        <i class="feather icon-arrow-up-circle"></i>
                    </div>
                    <div class="pts-tier-info-details">
                        <span class="pts-tier-info-label">المستوى التالي</span>
                        <span class="pts-tier-info-value" style="color: {{ $nextTierMonthly->color }};">
                            {{ $nextTierMonthly->name }}
                        </span>
                    </div>
                </div>
                
                <div class="pts-tier-info-card remaining">
                    <div class="pts-tier-info-icon" style="background: #28c76f20; color: #28c76f;">
                        <i class="feather icon-target"></i>
                    </div>
                    <div class="pts-tier-info-details">
                        <span class="pts-tier-info-label">المتبقي للترقية</span>
                        <span class="pts-tier-info-value" style="color: #28c76f;">
                            {{ number_format($pointsToNext) }} <small>نقطة</small>
                        </span>
                    </div>
                </div>

                <div class="pts-tier-info-card progress-percent">
                    <div class="pts-tier-info-icon" style="background: #00cfe820; color: #00cfe8;">
                        <i class="feather icon-percent"></i>
                    </div>
                    <div class="pts-tier-info-details">
                        <span class="pts-tier-info-label">نسبة الإنجاز</span>
                        <span class="pts-tier-info-value" style="color: #00cfe8;">
                            {{ number_format($overallProgress, 1) }}%
                        </span>
                    </div>
                </div>
            @else
                <div class="pts-tier-info-card max-reached">
                    <div class="pts-tier-info-icon" style="background: linear-gradient(135deg, #ffd700, #ff8c00); color: #fff;">
                        <i class="feather icon-award"></i>
                    </div>
                    <div class="pts-tier-info-details">
                        <span class="pts-tier-info-label">أعلى مستوى</span>
                        <span class="pts-tier-info-value" style="color: #ff8c00;">
                            تم الوصول! 🎉
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
/* ===================================
   Monthly Progress Card - Modern Design
   =================================== */
.pts-monthly-progress-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid #f0f0f0;
}

.pts-monthly-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
    border-bottom: 1px solid #eee;
    flex-wrap: wrap;
    gap: 1rem;
}

.pts-monthly-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pts-monthly-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.25rem;
    box-shadow: 0 4px 15px rgba(115, 103, 240, 0.3);
}

.pts-monthly-title h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #5e5873;
}

.pts-monthly-period {
    font-size: 0.85rem;
    color: #a8a8a8;
}

.pts-monthly-points-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #fff8e1 0%, #fffde7 100%);
    padding: 10px 20px;
    border-radius: 30px;
    border: 1px solid #ffe082;
}

.pts-monthly-points-badge i {
    color: #ffc107;
    font-size: 1.1rem;
}

.pts-monthly-points-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #7367f0;
}

.pts-monthly-points-label {
    font-size: 0.85rem;
    color: #888;
}

.pts-monthly-body {
    padding: 2rem 1.5rem;
}

/* Progress Track */
.pts-progress-container {
    padding: 2.5rem 1rem 4rem;
    position: relative;
}

.pts-progress-track {
    position: relative;
    height: 10px;
    background: linear-gradient(90deg, #f0f0f0 0%, #e8e8e8 100%);
    border-radius: 10px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
}

.pts-progress-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: linear-gradient(90deg, #7367f0 0%, #9e95f5 50%, #ce9ffc 100%);
    border-radius: 10px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 10px rgba(115, 103, 240, 0.4);
}

/* Tier Markers */
.pts-tier-marker {
    position: absolute;
    top: 50%;
    transform: translateX(-50%);
    z-index: 10;
}

.pts-tier-marker-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: -14px;
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 0.7rem;
}

.pts-tier-marker-dot:hover {
    transform: scale(1.2);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.pts-tier-marker-dot.achieved {
    background: var(--tier-color);
    border-color: var(--tier-color);
    color: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}

.pts-tier-marker-dot.current {
    background: var(--tier-color);
    border-color: var(--tier-color);
    color: #fff;
    box-shadow: 0 0 0 5px rgba(115, 103, 240, 0.2), 0 4px 15px rgba(0,0,0,0.2);
    animation: tierPulse 2s infinite;
}

@keyframes tierPulse {
    0%, 100% { box-shadow: 0 0 0 5px rgba(115, 103, 240, 0.2), 0 4px 15px rgba(0,0,0,0.2); }
    50% { box-shadow: 0 0 0 10px rgba(115, 103, 240, 0.1), 0 4px 15px rgba(0,0,0,0.2); }
}

.pts-tier-marker-info {
    position: absolute;
    top: 22px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    white-space: nowrap;
}

.pts-tier-marker-name {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 2px;
}

.pts-tier-marker-points {
    display: block;
    font-size: 0.7rem;
    color: #999;
}

/* Info Cards */
.pts-tier-info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f0f0f0;
}

.pts-tier-info-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: #fafafa;
    border-radius: 12px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.pts-tier-info-card:hover {
    background: #f5f5f5;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.pts-tier-info-card.max-reached {
    background: linear-gradient(135deg, #fffde7 0%, #fff8e1 100%);
    border-color: #ffe082;
}

.pts-tier-info-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.pts-tier-info-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pts-tier-info-label {
    font-size: 0.8rem;
    color: #888;
}

.pts-tier-info-value {
    font-size: 1rem;
    font-weight: 600;
}

.pts-tier-info-value small {
    font-weight: 400;
    color: #999;
    font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 768px) {
    .pts-monthly-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pts-monthly-points-badge {
        width: 100%;
        justify-content: center;
    }
    
    .pts-progress-container {
        padding: 2rem 0.5rem 3.5rem;
    }
    
    .pts-tier-marker-dot {
        width: 22px;
        height: 22px;
        margin-top: -11px;
        font-size: 0.6rem;
    }
    
    .pts-tier-marker-name {
        font-size: 0.7rem;
    }
    
    .pts-tier-marker-points {
        font-size: 0.6rem;
    }
    
    .pts-tier-info-cards {
        grid-template-columns: 1fr;
    }
}
</style>
