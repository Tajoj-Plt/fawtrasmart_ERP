{{-- بانر تحذير انتهاء صلاحية النقاط --}}
@if(isset($batchStats) && $batchStats['expiring_soon_count'] > 0)
    <div class="row mb-3">
        <div class="col-12">
            <div class="expiry-warning-banner" style="
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 50%, #fdcb6e 100%);
                border: 1px solid #f39c12;
                border-radius: 12px;
                padding: 20px 25px;
                position: relative;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(243, 156, 18, 0.15);
            ">
                {{-- أيقونة خلفية --}}
                <div style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); opacity: 0.1; font-size: 5rem;">
                    <i class="feather icon-clock"></i>
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center">
                        <div class="expiry-icon-wrapper ml-3" style="
                            width: 55px;
                            height: 55px;
                            border-radius: 50%;
                            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                            box-shadow: 0 3px 10px rgba(243, 156, 18, 0.3);
                        ">
                            <i class="feather icon-alert-triangle" style="color: #fff; font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 font-weight-bold" style="color: #856404;">
                                <i class="feather icon-clock ml-1"></i>
                                نقاط ستنتهي صلاحيتها قريباً!
                            </h5>
                            <p class="mb-0" style="color: #856404; font-size: 0.95rem;">
                                <strong>{{ number_format($batchStats['expiring_soon_points']) }}</strong> نقطة
                                في <strong>{{ $batchStats['expiring_soon_count'] }}</strong> دفعة
                                ستنتهي خلال الـ 30 يوم القادمة
                                @if($batchStats['nearest_expiry'])
                                    — أقرب انتهاء:
                                    <strong class="text-danger">{{ $batchStats['nearest_expiry']->translatedFormat('d M Y') }}</strong>
                                    @php
                                        $daysLeft = now()->diffInDays($batchStats['nearest_expiry'], false);
                                    @endphp
                                    @if($daysLeft <= 3)
                                        <span class="badge badge-danger badge-pill mr-1" style="animation: pulse 1.5s infinite;">
                                            بعد {{ $daysLeft }} {{ $daysLeft == 1 ? 'يوم' : 'أيام' }} فقط!
                                        </span>
                                    @elseif($daysLeft <= 7)
                                        <span class="badge badge-warning badge-pill mr-1">
                                            بعد {{ $daysLeft }} أيام
                                        </span>
                                    @endif
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="{{ route('ecosystem.points.batches.index', ['expiring_soon' => 1]) }}" 
                           class="btn btn-warning btn-sm shadow-sm" 
                           style="border-radius: 8px; font-weight: 600;">
                            <i class="feather icon-eye ml-1"></i> عرض الدفعات
                        </a>
                    </div>
                </div>

                {{-- تفاصيل الدفعات المنتهية قريباً --}}
                @if(isset($expiringBatches) && $expiringBatches->count() > 0)
                    <div class="mt-3 pt-3" style="border-top: 1px solid rgba(133, 100, 4, 0.2);">
                        <div class="row">
                            @foreach($expiringBatches->take(4) as $batch)
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="expiry-batch-card p-2 rounded" style="
                                        background: rgba(255, 255, 255, 0.6);
                                        border: 1px solid rgba(243, 156, 18, 0.3);
                                        border-radius: 8px !important;
                                    ">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="font-weight-bold" style="color: #856404;">
                                                {{ number_format($batch->remaining_points) }} نقطة
                                            </span>
                                            @php
                                                $batchDays = max(0, now()->diffInDays($batch->expires_at, false));
                                            @endphp
                                            <span class="badge {{ $batchDays <= 3 ? 'badge-danger' : ($batchDays <= 7 ? 'badge-warning' : 'badge-info') }} badge-pill" style="font-size: 0.75rem;">
                                                @if($batchDays == 0)
                                                    اليوم!
                                                @elseif($batchDays == 1)
                                                    غداً
                                                @else
                                                    {{ $batchDays }} يوم
                                                @endif
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="feather icon-calendar" style="font-size: 0.7rem;"></i>
                                            {{ $batch->expires_at->translatedFormat('d/m/Y') }}
                                        </small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($expiringBatches->count() > 4)
                            <div class="text-center mt-2">
                                <small style="color: #856404;">
                                    و {{ $expiringBatches->count() - 4 }} دفعات أخرى...
                                    <a href="{{ route('ecosystem.points.batches.index', ['expiring_soon' => 1]) }}" style="color: #e67e22;">عرض الكل</a>
                                </small>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
@endif

{{-- إحصائيات الدفعات المختصرة --}}
@if(isset($batchStats) && $batchStats['active_batches'] > 0)
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="stat-card-modern info" style="flex: unset; width: 100%;">
                <div class="stat-icon">
                    <i class="feather icon-layers"></i>
                </div>
                <div class="stat-label">دفعات نشطة</div>
                <div class="stat-value">{{ $batchStats['active_batches'] }}</div>
                <div class="stat-trend">
                    <i class="feather icon-package"></i>
                    <span>دفعة</span>
                </div>
            </div>
        </div>

        @if($batchStats['expiring_soon_count'] > 0)
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="stat-card-modern warning" style="flex: unset; width: 100%;">
                    <div class="stat-icon">
                        <i class="feather icon-clock"></i>
                    </div>
                    <div class="stat-label">تنتهي قريباً</div>
                    <div class="stat-value">{{ number_format($batchStats['expiring_soon_points']) }}</div>
                    <div class="stat-trend">
                        <i class="feather icon-alert-triangle"></i>
                        <span>{{ $batchStats['expiring_soon_count'] }} دفعة</span>
                    </div>
                </div>
            </div>
        @endif

        @if($batchStats['total_expired'] > 0)
            <div class="col-md-3 col-sm-6 mb-2">
                <div class="stat-card-modern danger" style="flex: unset; width: 100%;">
                    <div class="stat-icon">
                        <i class="feather icon-x-circle"></i>
                    </div>
                    <div class="stat-label">نقاط منتهية</div>
                    <div class="stat-value">{{ number_format($batchStats['total_expired']) }}</div>
                    <div class="stat-trend">
                        <i class="feather icon-trash-2"></i>
                        <span>نقطة انتهت</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
