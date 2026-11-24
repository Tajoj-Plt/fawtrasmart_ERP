@extends('ecosystem::master')

@section('title', 'عرض الإنجاز')

@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            @include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '2rem'])
            {{ $achievement->name }}
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.achievements.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            <a href="{{ route('ecosystem.points.achievements.edit', $achievement->id) }}" class="pts-btn pts-btn-primary">
                <i class="feather icon-edit-2"></i>
                تعديل
            </a>
            <button type="button" class="pts-btn pts-btn-danger pts-btn-icon" id="deleteBtn" title="حذف">
                <i class="feather icon-trash-2"></i>
            </button>
        </div>
    </div>

    <!-- Hero -->
    <div class="pts-hero" style="background: linear-gradient(135deg, {{ $achievement->color ?? '#FFD700' }}15 0%, #fff 100%);">
        <div class="pts-hero-info">
            <div class="pts-hero-label">الإنجاز</div>
            <h2 class="pts-hero-title">
                {{ $achievement->name }}
                @if($achievement->name_en)
                    <small class="text-muted">({{ $achievement->name_en }})</small>
                @endif
            </h2>
            <p class="text-muted mt-2">{{ $achievement->description }}</p>
            <div style="margin-top: 0.75rem;">
                <span class="pts-badge pts-badge-{{ $achievement->user_type->value === 'client' ? 'info' : 'warning' }}">
                    <i class="feather icon-{{ $achievement->user_type->value === 'client' ? 'users' : 'user' }}"></i>
                    {{ $achievement->user_type->label() }}
                </span>
                <span class="pts-badge {{ $achievement->is_active ? 'pts-badge-success' : 'pts-badge-secondary' }}" style="margin-right: 0.5rem;">
                    <span class="pts-status-dot {{ $achievement->is_active ? 'active' : 'inactive' }}"></span>
                    {{ $achievement->is_active ? 'نشط' : 'غير نشط' }}
                </span>
                @if($achievement->is_hidden)
                    <span class="pts-badge pts-badge-warning" style="margin-right: 0.5rem;">
                        <i class="feather icon-eye-off"></i> مخفي
                    </span>
                @endif
                @if($achievement->is_one_time)
                    <span class="pts-badge pts-badge-primary" style="margin-right: 0.5rem;">
                        مرة واحدة
                    </span>
                @else
                    <span class="pts-badge pts-badge-info" style="margin-right: 0.5rem;">
                        متكرر
                    </span>
                @endif
            </div>
        </div>
        <div class="pts-hero-points">
            @if($achievement->image)
                <img src="{{ asset('storage/' . $achievement->image) }}" alt="{{ $achievement->name }}" 
                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid {{ $achievement->color ?? '#FFD700' }};">
            @else
                <span style="width: 100px; height: 100px; border-radius: 50%; background: {{ $achievement->color ?? '#FFD700' }}; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    @include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '3rem'])
                </span>
            @endif
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row" style="margin-bottom: 1.25rem;">
        <div class="stat-card-modern success">
            <div class="stat-icon">
                <i class="feather icon-star"></i>
            </div>
            <div class="stat-label">نقاط المكافأة</div>
            <div class="stat-value">{{ number_format($achievement->bonus_points) }}</div>
            <div class="stat-trend">
                نقطة
            </div>
        </div>

        <div class="stat-card-modern info">
            <div class="stat-icon">
                <i class="feather icon-users"></i>
            </div>
            <div class="stat-label">عدد المحققين</div>
            <div class="stat-value">{{ number_format($stats['total_earned']) }}</div>
            <div class="stat-trend">
                مستخدم
            </div>
        </div>

        <div class="stat-card-modern primary">
            <div class="stat-icon">
                <i class="feather icon-award"></i>
            </div>
            <div class="stat-label">النقاط الممنوحة</div>
            <div class="stat-value">{{ number_format($stats['total_points_awarded']) }}</div>
            <div class="stat-trend">
                نقطة
            </div>
        </div>

        <div class="stat-card-modern warning">
            <div class="stat-icon">
                <i class="feather icon-percent"></i>
            </div>
            <div class="stat-label">الفئة</div>
            <div class="stat-value" style="font-size: 1.25rem;">{{ $achievement->category }}</div>
            <div class="stat-trend">
                تصنيف
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
            <!-- Condition Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-target"></i>
                        شروط الإنجاز
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-check-square"></i>
                            نوع الشرط
                        </span>
                        <span class="pts-info-value">
                            {{ $achievement->condition_type }}
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-sliders"></i>
                            المعامل
                        </span>
                        <span class="pts-info-value">
                            {{ $achievement->condition_operator->label() }}
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-hash"></i>
                            القيمة المطلوبة
                        </span>
                        <span class="pts-info-value">
                            <strong class="text-primary">{{ number_format($achievement->condition_value) }}</strong>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">
                            <i class="feather icon-calendar"></i>
                            الفترة الزمنية
                        </span>
                        <span class="pts-info-value">
                            {{ $achievement->condition_period->label() }}
                        </span>
                    </div>

                    <div class="alert alert-light mt-3">
                        <i class="feather icon-info"></i>
                        <strong>الشرط الكامل:</strong><br>
                        يجب أن يكون {{ $achievement->condition_type }} 
                        {{ $achievement->condition_operator->label() }} 
                        <strong>{{ number_format($achievement->condition_value) }}</strong>
                        خلال {{ $achievement->condition_period->label() }}
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-bar-chart-2"></i>
                        إحصائيات التحقيق
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-balance-flow">
                        <div class="pts-balance-box">
                            <div class="pts-balance-box-value" style="color: #28c76f;">{{ number_format($stats['clients_earned']) }}</div>
                            <div class="pts-balance-box-label">عملاء حققوا الإنجاز</div>
                        </div>
                        <div class="pts-balance-arrow">
                            <i class="feather icon-plus"></i>
                        </div>
                        <div class="pts-balance-box">
                            <div class="pts-balance-box-value" style="color: #ff9f43;">{{ number_format($stats['employees_earned']) }}</div>
                            <div class="pts-balance-box-label">موظفين حققوا الإنجاز</div>
                        </div>
                        <div class="pts-balance-arrow">
                            <i class="feather icon-arrow-left"></i>
                        </div>
                        <div class="pts-balance-box" style="background: #7367f015; border-radius: 10px;">
                            <div class="pts-balance-box-value" style="color: #7367f0;">{{ number_format($stats['total_earned']) }}</div>
                            <div class="pts-balance-box-label">إجمالي المحققين</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
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
                            <span class="pts-timeline-title">تاريخ الإنشاء</span>
                            <span class="pts-timeline-date">{{ $achievement->created_at->format('Y/m/d') }}</span>
                            <span class="pts-timeline-time">{{ $achievement->created_at->format('H:i') }}</span>
                        </div>
                    </div>
                    @if($achievement->created_at != $achievement->updated_at)
                        <div class="pts-timeline-item">
                            <div class="pts-timeline-dot"></div>
                            <div class="pts-timeline-content">
                                <span class="pts-timeline-title">آخر تحديث</span>
                                <span class="pts-timeline-date">{{ $achievement->updated_at->format('Y/m/d') }}</span>
                                <span class="pts-timeline-time">{{ $achievement->updated_at->format('H:i') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Settings Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-settings"></i>
                        الإعدادات
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحالة</span>
                        <span class="pts-info-value">
                            <span class="pts-badge {{ $achievement->is_active ? 'pts-badge-success' : 'pts-badge-danger' }}">
                                {{ $achievement->is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الرؤية</span>
                        <span class="pts-info-value">
                            <span class="pts-badge {{ $achievement->is_hidden ? 'pts-badge-warning' : 'pts-badge-info' }}">
                                {{ $achievement->is_hidden ? 'مخفي' : 'ظاهر' }}
                            </span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">التكرار</span>
                        <span class="pts-info-value">
                            <span class="pts-badge {{ $achievement->is_one_time ? 'pts-badge-primary' : 'pts-badge-info' }}">
                                {{ $achievement->is_one_time ? 'مرة واحدة' : 'متكرر' }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    <button type="button" id="toggleStatusBtn" class="btn btn-{{ $achievement->is_active ? 'warning' : 'success' }} btn-block mb-2">
                        <i class="feather icon-{{ $achievement->is_active ? 'x' : 'check' }}-circle"></i>
                        {{ $achievement->is_active ? 'تعطيل' : 'تفعيل' }} الإنجاز
                    </button>
                    <a href="{{ route('ecosystem.points.achievements.edit', $achievement->id) }}" class="btn btn-primary btn-block">
                        <i class="feather icon-edit"></i>
                        تعديل التفاصيل
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" action="{{ route('ecosystem.points.achievements.destroy', $achievement->id) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const action = {{ $achievement->is_active ? 'true' : 'false' }} ? 'تعطيل' : 'تفعيل';
        Swal.fire({
            title: action + ' الإنجاز؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('{{ route("ecosystem.points.achievements.toggle-status", $achievement->id) }}', {
                    _token: '{{ csrf_token() }}'
                }).done(function(response) {
                    location.reload();
                });
            }
        });
    });

    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'حذف الإنجاز؟',
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
