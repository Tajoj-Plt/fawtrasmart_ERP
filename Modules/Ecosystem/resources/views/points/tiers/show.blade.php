@extends('ecosystem::master')

@section('title', 'عرض مستوى النقاط')

@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            <i class="{{ $tier->icon ?? 'feather icon-award' }}"></i>
            {{ $tier->name }}
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.tiers.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            <a href="{{ route('ecosystem.points.tiers.edit', $tier->id) }}" class="pts-btn pts-btn-primary">
                <i class="feather icon-edit-2"></i>
                تعديل
            </a>
            <button type="button" class="pts-btn pts-btn-danger pts-btn-icon" id="deleteBtn" title="حذف">
                <i class="feather icon-trash-2"></i>
            </button>
        </div>
    </div>

    <!-- Hero -->
    <div class="pts-hero" style="background: linear-gradient(135deg, {{ $tier->color ?? '#5c6bc0' }}15 0%, #fff 100%);">
        <div class="pts-hero-info">
            <div class="pts-hero-label">مستوى النقاط</div>
            <h2 class="pts-hero-title">
                {{ $tier->name }}
                @if($tier->name_en)
                    <small class="text-muted">({{ $tier->name_en }})</small>
                @endif
            </h2>
            <div style="margin-top: 0.75rem;">
                <span class="pts-badge pts-badge-{{ $tier->user_type->value === 'client' ? 'info' : 'warning' }}">
                    <i class="feather icon-{{ $tier->user_type->value === 'client' ? 'users' : 'user' }}"></i>
                    {{ $tier->user_type->label() }}
                </span>
                <span class="pts-badge {{ $tier->is_active ? 'pts-badge-success' : 'pts-badge-secondary' }}" style="margin-right: 0.5rem;">
                    <span class="pts-status-dot {{ $tier->is_active ? 'active' : 'inactive' }}"></span>
                    {{ $tier->is_active ? 'نشط' : 'غير نشط' }}
                </span>
            </div>
        </div>
        <div class="pts-hero-points">
            @if($tier->image)
                <img src="{{ asset('storage/' . $tier->image) }}" alt="{{ $tier->name }}" 
                     style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid {{ $tier->color ?? '#5c6bc0' }};">
            @else
                <div style="width: 80px; height: 80px; border-radius: 50%; background: {{ $tier->color ?? '#5c6bc0' }}; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="{{ $tier->icon ?? 'feather icon-award' }}" style="font-size: 2rem; color: #fff;"></i>
                </div>
            @endif
        </div>
    </div>

    <!-- Points Range Card -->
    <div class="pts-card" style="margin-bottom: 1.25rem;">
        <div class="pts-card-body">
            <div class="pts-balance-flow">
                <div class="pts-balance-box">
                    <div class="pts-balance-box-value" style="color: {{ $tier->color ?? '#5c6bc0' }};">{{ number_format($tier->min_points) }}</div>
                    <div class="pts-balance-box-label">الحد الأدنى</div>
                </div>
                <div class="pts-balance-arrow">
                    <i class="feather icon-arrow-left"></i>
                </div>
                <div class="pts-balance-box" style="background: {{ $tier->color ?? '#5c6bc0' }}15; border-radius: 10px;">
                    <div class="pts-balance-box-value" style="color: {{ $tier->color ?? '#5c6bc0' }};">{{ $tier->max_points ? number_format($tier->max_points) : '∞' }}</div>
                    <div class="pts-balance-box-label">الحد الأقصى</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
            <!-- Rewards Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-gift"></i>
                        المكافآت والمزايا
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">مضاعف النقاط</span>
                        <span class="pts-info-value">
                            @if($tier->hasMultiplier())
                                <span class="pts-badge pts-badge-success">
                                    <i class="feather icon-trending-up"></i>
                                    x{{ $tier->points_multiplier }}
                                </span>
                                <small class="text-muted mr-1">{{ ($tier->points_multiplier - 1) * 100 }}% نقاط إضافية</small>
                            @else
                                <span class="text-muted">x1 (بدون مضاعفة)</span>
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">نسبة الخصم</span>
                        <span class="pts-info-value">
                            @if($tier->hasDiscount())
                                <span class="pts-badge pts-badge-info">
                                    <i class="feather icon-percent"></i>
                                    {{ $tier->discount_percentage }}%
                                </span>
                            @else
                                <span class="text-muted">بدون خصم</span>
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">دعم أولوية</span>
                        <span class="pts-info-value">
                            @if($tier->priority_support)
                                <span class="pts-badge pts-badge-success">
                                    <i class="feather icon-check"></i> نعم
                                </span>
                            @else
                                <span class="pts-badge pts-badge-secondary">
                                    <i class="feather icon-x"></i> لا
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">شحن مجاني</span>
                        <span class="pts-info-value">
                            @if($tier->free_shipping)
                                <span class="pts-badge pts-badge-success">
                                    <i class="feather icon-check"></i> نعم
                                </span>
                            @else
                                <span class="pts-badge pts-badge-secondary">
                                    <i class="feather icon-x"></i> لا
                                </span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Additional Benefits -->
            @if($tier->benefits && count($tier->benefits) > 0)
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-star"></i>
                            مزايا إضافية
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <ul style="margin: 0; padding-right: 1.25rem;">
                            @foreach($tier->benefits as $benefit)
                                <li style="padding: 0.5rem 0; color: #555;">
                                    <i class="feather icon-check-circle text-success"></i>
                                    {{ $benefit }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Next Tier -->
            @if($nextTier)
                <div class="pts-card">
                    <div class="pts-card-header">
                        <h3 class="pts-card-title">
                            <i class="feather icon-arrow-up-circle"></i>
                            المستوى التالي
                        </h3>
                    </div>
                    <div class="pts-card-body">
                        <div class="pts-user">
                            <div class="pts-user-avatar" style="background: {{ $nextTier->color ?? '#e8eaf6' }};">
                                <i class="{{ $nextTier->icon ?? 'feather icon-award' }}" style="color: {{ $nextTier->color ? '#fff' : '#5c6bc0' }};"></i>
                            </div>
                            <div>
                                <div class="pts-user-name">{{ $nextTier->name }}</div>
                                <div class="pts-user-type">يبدأ من {{ number_format($nextTier->min_points) }} نقطة</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Timeline -->
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
                            <i class="feather icon-plus"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تم الإنشاء</p>
                            <span class="pts-timeline-date">{{ $tier->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @if($tier->created_at != $tier->updated_at)
                        <div class="pts-timeline-item">
                            <div class="pts-timeline-icon">
                                <i class="feather icon-edit-2"></i>
                            </div>
                            <div class="pts-timeline-content">
                                <p class="pts-timeline-title">آخر تحديث</p>
                                <span class="pts-timeline-date">{{ $tier->updated_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Statistics -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-bar-chart-2"></i>
                        الإحصائيات
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">الترتيب</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-secondary">#{{ $tier->sort_order }}</span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الترقيات</span>
                        <span class="pts-info-value">{{ $tier->upgrades->count() }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">التخفيضات</span>
                        <span class="pts-info-value">{{ $tier->downgrades->count() }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">المكافآت</span>
                        <span class="pts-info-value">{{ $tier->rewards->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    <button type="button" class="pts-btn {{ $tier->is_active ? 'pts-btn-outline' : 'pts-btn-primary' }}" style="width: 100%;" id="toggleStatusBtn">
                        <i class="feather icon-{{ $tier->is_active ? 'pause' : 'play' }}"></i>
                        {{ $tier->is_active ? 'تعطيل المستوى' : 'تفعيل المستوى' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" action="{{ route('ecosystem.points.tiers.destroy', $tier->id) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const action = {{ $tier->is_active ? 'true' : 'false' }} ? 'تعطيل' : 'تفعيل';
        Swal.fire({
            title: action + ' المستوى؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('{{ route("ecosystem.points.tiers.toggle-status", $tier->id) }}', {
                    _token: '{{ csrf_token() }}'
                }).done(function(response) {
                    if (response.success) {
                        window.location.reload();
                    }
                });
            }
        });
    });

    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'حذف المستوى؟',
            text: 'لا يمكن التراجع عن هذا الإجراء',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'حذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteForm').submit();
            }
        });
    });
});
</script>
@endpush
