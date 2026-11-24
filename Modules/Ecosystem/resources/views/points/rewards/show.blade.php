@extends('ecosystem::master')

@section('title', 'عرض المكافأة: ' . $reward->name)

@section('content')
    {{-- استيراد الأنماط الحديثة --}}
    @include('ecosystem::points.partials.points-modern-styles')

    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تفاصيل المكافأة</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.rewards.index') }}">المكافآت</a></li>
                            <li class="breadcrumb-item active">{{ $reward->name }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <div class="pts-btn-group" style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                <a href="{{ route('ecosystem.points.rewards.index') }}" class="pts-btn pts-btn-secondary">
                    <i class="feather icon-arrow-right"></i> رجوع
                </a>
                <a href="{{ route('ecosystem.points.rewards.edit', $reward) }}" class="pts-btn pts-btn-primary">
                    <i class="feather icon-edit"></i> تعديل
                </a>
                <button type="button" class="pts-btn pts-btn-{{ $reward->is_active ? 'warning' : 'success' }}" id="toggleStatusBtn">
                    <i class="feather icon-{{ $reward->is_active ? 'pause' : 'play' }}"></i>
                    {{ $reward->is_active ? 'إيقاف' : 'تفعيل' }}
                </button>
                <button type="button" class="pts-btn pts-btn-danger" id="deleteBtn">
                    <i class="feather icon-trash-2"></i> حذف
                </button>
            </div>
        </div>
    </div>

    <div class="content-body">
        {{-- بطاقة المكافأة الرئيسية --}}
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    {{-- الصورة --}}
                    <div class="col-md-3 text-center" style="border-left: 1px solid #f0f0f0;">
                        @if($reward->image)
                            <img src="{{ asset('uploads/rewards/' . $reward->image) }}"
                                 alt="{{ $reward->name }}"
                                 class="rounded-circle shadow mb-2"
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        @else
                            <div class="rounded-circle shadow d-flex align-items-center justify-content-center mx-auto mb-2"
                                 style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 3rem; color: #fff;">
                                <i class="feather icon-gift"></i>
                            </div>
                        @endif
                        <h4 class="mb-1 font-weight-bold" style="color: #5e5873;">{{ $reward->name }}</h4>
                        @if($reward->name_en)
                            <small class="text-muted d-block mb-2">{{ $reward->name_en }}</small>
                        @endif
                        <div class="d-flex justify-content-center gap-2 flex-wrap" style="gap: 6px;">
                            <span class="pts-badge pts-badge-{{ $reward->is_active ? 'success' : 'danger' }}">
                                <i class="feather icon-{{ $reward->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                {{ $reward->is_active ? 'نشطة' : 'غير نشطة' }}
                            </span>
                            @if($reward->is_featured)
                                <span class="pts-badge pts-badge-warning">
                                    <i class="feather icon-star"></i> مميزة
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- التفاصيل --}}
                    <div class="col-md-5">
                        <div class="rwd-details-list">
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-users mr-1"></i> نوع المستخدم</span>
                                <span class="pts-badge pts-badge-{{ $reward->user_type->value === 'client' ? 'primary' : 'info' }}">
                                    {{ $reward->user_type->label() }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-tag mr-1"></i> نوع المكافأة</span>
                                <span class="pts-badge pts-badge-primary">
                                    {{ $reward->reward_type->icon() }} {{ $reward->reward_type->label() }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-star mr-1"></i> النقاط المطلوبة</span>
                                <strong style="color: #7367f0;">{{ number_format($reward->points_required) }} نقطة</strong>
                            </div>
                            @if($reward->reward_value)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-dollar-sign mr-1"></i> قيمة المكافأة</span>
                                <strong>
                                    @if(in_array($reward->reward_type->value, ['discount_percentage']))
                                        {{ $reward->reward_value }}%
                                    @else
                                        {{ number_format($reward->reward_value, 2) }}
                                    @endif
                                </strong>
                            </div>
                            @endif
                            @if($reward->product)
                            <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid #f0f0f0;">
                                <span class="text-muted"><i class="feather icon-box mr-1"></i> المنتج</span>
                                <strong>{{ $reward->product->name }}</strong>
                            </div>
                            @endif
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="feather icon-map-pin mr-1"></i> الفرع</span>
                                @if($reward->branch)
                                    <strong>{{ $reward->branch->name }}</strong>
                                @else
                                    <span class="pts-badge pts-badge-success">جميع الفروع</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- الإحصائيات والتواريخ --}}
                    <div class="col-md-4">
                        <div class="rwd-side-info">
                            {{-- معاينة البطاقة --}}
                            <div class="rwd-preview-card mb-3">
                                <i class="feather icon-gift"></i>
                                <h5 class="mb-1">{{ $reward->name }}</h5>
                                <p class="mb-1">{{ number_format($reward->points_required) }} نقطة</p>
                                <span class="rwd-preview-badge">{{ $reward->reward_type->label() }}</span>
                            </div>

                            {{-- التواريخ --}}
                            <div class="rwd-dates">
                                <div class="d-flex justify-content-between py-1">
                                    <small class="text-muted"><i class="feather icon-plus-circle mr-1"></i> الإنشاء</small>
                                    <small>{{ $reward->created_at->format('Y/m/d') }}</small>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <small class="text-muted"><i class="feather icon-edit-3 mr-1"></i> آخر تحديث</small>
                                    <small>{{ $reward->updated_at->format('Y/m/d H:i') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الوصف --}}
        @if($reward->description)
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <h6 class="text-muted mb-2"><i class="feather icon-file-text mr-1"></i> الوصف</h6>
                <p class="mb-0" style="color: #5e5873; line-height: 1.8;">{{ $reward->description }}</p>
            </div>
        </div>
        @endif

        {{-- بطاقات الإحصائيات --}}
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-package"></i></div>
                <div class="stat-label">الكمية المتاحة</div>
                <div class="stat-value">
                    @if($reward->quantity_available)
                        {{ number_format($reward->quantity_available) }}
                    @else
                        <i class="feather icon-infinity" style="font-size: 1.5rem;"></i>
                    @endif
                </div>
                <div class="stat-trend">
                    @if($reward->quantity_available)
                        @if($reward->quantity_available <= 5)
                            <i class="feather icon-alert-triangle"></i> <span style="color: #ea5455;">كمية منخفضة!</span>
                        @else
                            <i class="feather icon-check"></i> <span>متوفرة</span>
                        @endif
                    @else
                        <i class="feather icon-check"></i> <span>غير محدودة</span>
                    @endif
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon"><i class="feather icon-user"></i></div>
                <div class="stat-label">الحد لكل مستخدم</div>
                <div class="stat-value">{{ $reward->max_per_user ?? 1 }}</div>
                <div class="stat-trend"><i class="feather icon-repeat"></i> <span>مرة/مرات</span></div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon"><i class="feather icon-shield"></i></div>
                <div class="stat-label">الحد الأدنى للمستوى</div>
                <div class="stat-value" style="font-size: 1.25rem;">
                    @if($reward->minTier)
                        {{ $reward->minTier->name }}
                    @else
                        الكل
                    @endif
                </div>
                <div class="stat-trend">
                    @if($reward->minTier)
                        <i class="feather icon-layers"></i> <span>{{ $reward->minTier->user_type->label() }}</span>
                    @else
                        <i class="feather icon-check-circle"></i> <span>جميع المستويات</span>
                    @endif
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon"><i class="feather icon-bar-chart-2"></i></div>
                <div class="stat-label">مرات الاستبدال</div>
                <div class="stat-value">{{ number_format($reward->times_redeemed ?? 0) }}</div>
                <div class="stat-trend"><i class="feather icon-trending-up"></i> <span>مرة</span></div>
            </div>
        </div>

        {{-- فترة الصلاحية --}}
        <div class="card profile-card-modern mb-3">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="feather icon-calendar mr-1"></i> فترة الصلاحية</h6>
                <div class="row">
                    <div class="col-md-6 text-center" style="border-left: 1px solid #f0f0f0;">
                        <div class="rwd-date-block">
                            <div class="rwd-date-icon" style="background: rgba(40, 199, 111, 0.1); color: #28c76f;">
                                <i class="feather icon-play-circle"></i>
                            </div>
                            <span class="rwd-date-label">تاريخ البداية</span>
                            @if($reward->starts_at)
                                <span class="rwd-date-value">{{ $reward->starts_at->format('Y/m/d') }}</span>
                                <small class="text-muted">{{ $reward->starts_at->format('H:i') }}</small>
                            @else
                                <span class="rwd-date-value" style="color: #28c76f;">فورياً</span>
                                <small class="text-muted">متاحة الآن</small>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="rwd-date-block">
                            <div class="rwd-date-icon" style="background: {{ $reward->expires_at && $reward->expires_at->isPast() ? 'rgba(234,84,85,0.1)' : 'rgba(130,134,139,0.1)' }}; color: {{ $reward->expires_at && $reward->expires_at->isPast() ? '#ea5455' : '#82868b' }};">
                                <i class="feather icon-{{ $reward->expires_at && $reward->expires_at->isPast() ? 'alert-circle' : 'stop-circle' }}"></i>
                            </div>
                            <span class="rwd-date-label">تاريخ الانتهاء</span>
                            @if($reward->expires_at)
                                <span class="rwd-date-value {{ $reward->expires_at->isPast() ? 'text-danger' : '' }}">{{ $reward->expires_at->format('Y/m/d') }}</span>
                                <small class="text-muted">{{ $reward->expires_at->format('H:i') }}</small>
                                @if($reward->expires_at->isPast())
                                    <span class="pts-badge pts-badge-danger mt-1" style="font-size: 0.7rem;">منتهية الصلاحية</span>
                                @elseif($reward->expires_at->diffInDays(now()) <= 7)
                                    <span class="pts-badge pts-badge-warning mt-1" style="font-size: 0.7rem;">تنتهي قريباً</span>
                                @endif
                            @else
                                <span class="rwd-date-value" style="color: #28c76f;">بدون انتهاء</span>
                                <small class="text-muted">صالحة دائماً</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الشروط والأحكام --}}
        @if($reward->terms_conditions && count($reward->terms_conditions) > 0)
        <div class="card table-card-modern mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="feather icon-file-text mr-1"></i> الشروط والأحكام</h6>
            </div>
            <div class="card-body">
                <div class="rwd-terms-list">
                    @foreach($reward->terms_conditions as $index => $term)
                        @if($term)
                        <div class="rwd-term-item">
                            <div class="rwd-term-number">{{ $index + 1 }}</div>
                            <span>{{ $term }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Hidden Forms --}}
    <form id="deleteForm" action="{{ route('ecosystem.points.rewards.destroy', $reward) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form id="toggleStatusForm" action="{{ route('ecosystem.points.rewards.toggle-status', $reward) }}" method="POST" class="d-none">
        @csrf
    </form>

    <style>
    /* ===================================
       Reward Show - Modern Design
       =================================== */
    .rwd-preview-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        color: #fff;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    .rwd-preview-card i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    .rwd-preview-card h5 {
        margin: 0;
        font-weight: 600;
        color: #fff;
    }
    .rwd-preview-card p {
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .rwd-preview-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
    }
    .rwd-dates {
        background: #f8f8f8;
        border-radius: 10px;
        padding: 0.75rem 1rem;
    }

    /* Date Block */
    .rwd-date-block {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 1rem;
    }
    .rwd-date-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }
    .rwd-date-label {
        font-size: 0.85rem;
        color: #b4b7bd;
        font-weight: 500;
    }
    .rwd-date-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #5e5873;
    }

    /* Terms List */
    .rwd-terms-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .rwd-term-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0.75rem 1rem;
        background: #f8f8f8;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .rwd-term-item:hover {
        background: #f0f0f0;
    }
    .rwd-term-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .rwd-term-item span {
        color: #5e5873;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    @media (max-width: 768px) {
        .rwd-preview-card {
            margin-bottom: 1rem;
        }
    }
    </style>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const isActive = {{ $reward->is_active ? 'true' : 'false' }};
        const action = isActive ? 'إيقاف' : 'تفعيل';

        Swal.fire({
            title: action + ' المكافأة؟',
            text: 'هل أنت متأكد من ' + action + ' هذه المكافأة؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: isActive ? '#f5a623' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، ' + action,
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#toggleStatusForm').submit();
            }
        });
    });

    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'حذف المكافأة؟',
            text: 'هل أنت متأكد من حذف هذه المكافأة؟ لا يمكن التراجع عن هذا الإجراء!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteForm').submit();
            }
        });
    });
});
</script>
@endpush
