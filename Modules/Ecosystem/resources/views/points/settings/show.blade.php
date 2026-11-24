@extends('ecosystem::master')

@section('title', 'عرض إعداد النقاط')




@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            <i class="feather icon-settings"></i>
            إعداد النقاط
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.settings.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            <a href="{{ route('ecosystem.points.settings.edit', $setting->id) }}" class="pts-btn pts-btn-primary">
                <i class="feather icon-edit-2"></i>
                تعديل
            </a>
            <button type="button" class="pts-btn pts-btn-danger pts-btn-icon" id="deleteBtn" title="حذف">
                <i class="feather icon-trash-2"></i>
            </button>
        </div>
    </div>

    <!-- Hero -->
    <div class="pts-hero">
        <div class="pts-hero-info">
            <div class="pts-hero-label">اسم الإجراء</div>
            <h2 class="pts-hero-title">{{ $setting->action_name }}</h2>
            <code class="pts-hero-code">{{ $setting->action_type }}</code>
            <span class="pts-badge {{ $setting->is_active ? 'pts-badge-success' : 'pts-badge-secondary' }}" style="margin-right: 0.5rem;">
                <span class="pts-status-dot {{ $setting->is_active ? 'active' : 'inactive' }}"></span>
                {{ $setting->is_active ? 'نشط' : 'غير نشط' }}
            </span>
            @php
                $actionEnum = \App\Enums\ActionType::tryFrom($setting->action_type);
            @endphp
            @if($actionEnum && $actionEnum->isNegative())
            <span class="pts-badge pts-badge-danger" style="margin-right: 0.25rem;">
                <i class="feather icon-arrow-down"></i> إجراء سلبي
            </span>
            @endif
        </div>
        <div class="pts-hero-points">
            <div class="pts-points-value {{ $setting->points > 0 ? 'positive' : 'negative' }}">
                {{ $setting->points > 0 ? '+' : '' }}{{ number_format($setting->points) }}
            </div>
            <div class="pts-points-label">نقطة</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-info"></i>
                        التفاصيل
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">نوع المستخدم</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-info">
                                <i class="feather icon-{{ $setting->user_type->value === 'client' ? 'users' : 'user' }}"></i>
                                {{ $setting->user_type->label() }}
                            </span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الفرع</span>
                        <span class="pts-info-value">{{ $setting->branch?->name ?? 'جميع الفروع' }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">النقاط الافتراضية</span>
                        <span class="pts-info-value">
                            <span class="pts-badge {{ $setting->isPositive() ? 'pts-badge-success' : 'pts-badge-danger' }}">
                                <i class="feather icon-{{ $setting->isPositive() ? 'arrow-up' : 'arrow-down' }}"></i>
                                {{ $setting->isPositive() ? 'إضافة' : 'خصم' }} {{ number_format($setting->getAbsolutePoints()) }} نقطة
                            </span>
                        </span>
                    </div>
                    @if($setting->description)
                    <div class="pts-divider"></div>
                    <div class="pts-description">{{ $setting->description }}</div>
                    @endif
                </div>
            </div>

            {{-- بطاقة حالة التعطيل --}}
            @if($setting->isDisabled())
            <div class="pts-card" style="border-right: 4px solid #dc3545;">
                <div class="pts-card-header">
                    <h3 class="pts-card-title" style="color: #dc3545;">
                        <i class="feather icon-alert-triangle"></i>
                        الإعداد معطّل
                    </h3>
                </div>
                <div class="pts-card-body">
                    @if($setting->disabled_reason)
                    <div class="pts-info-row">
                        <span class="pts-info-label">سبب التعطيل</span>
                        <span class="pts-info-value">{{ $setting->disabled_reason }}</span>
                    </div>
                    @endif
                    @if($setting->disabled_at)
                    <div class="pts-info-row">
                        <span class="pts-info-label">تاريخ التعطيل</span>
                        <span class="pts-info-value">{{ $setting->disabled_at->format('Y-m-d H:i') }}</span>
                    </div>
                    @endif
                    @if(!$setting->disabled_reason && !$setting->disabled_at)
                    <p class="text-muted mb-0">تم تعطيل هذا الإعداد بدون ذكر سبب.</p>
                    @endif
                </div>
            </div>
            @endif

            <!-- Calculation Settings Card -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-calculator"></i>
                        طريقة حساب النقاط
                    </h3>
                </div>
                <div class="pts-card-body">
                    @php
                        $calcType = $setting->calculation_type instanceof \App\Enums\CalculationType 
                            ? $setting->calculation_type 
                            : \App\Enums\CalculationType::tryFrom($setting->calculation_type ?? 'fixed');
                    @endphp
                    <div class="pts-info-row">
                        <span class="pts-info-label">نوع الحساب</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-{{ $calcType?->color() ?? 'primary' }}">
                                <i class="feather {{ $calcType?->icon() ?? 'icon-hash' }}"></i>
                                {{ $calcType?->label() ?? 'ثابت' }}
                            </span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">طريقة الحساب</span>
                        <span class="pts-info-value">{{ $setting->getCalculationDescription() }}</span>
                    </div>
                    @if($setting->calculation_value)
                    <div class="pts-info-row">
                        <span class="pts-info-label">قيمة الحساب</span>
                        <span class="pts-info-value">{{ number_format($setting->calculation_value, 4) }}</span>
                    </div>
                    @endif
                    @if($setting->min_amount)
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحد الأدنى للمعاملة</span>
                        <span class="pts-info-value">{{ number_format($setting->min_amount, 2) }} ريال</span>
                    </div>
                    @endif
                    @if($setting->max_amount)
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحد الأقصى للمعاملة</span>
                        <span class="pts-info-value">{{ number_format($setting->max_amount, 2) }} ريال</span>
                    </div>
                    @endif
                    @if($setting->max_points)
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحد الأقصى للنقاط</span>
                        <span class="pts-info-value">{{ number_format($setting->max_points) }} نقطة</span>
                    </div>
                    @endif

                    @if($calcType === \App\Enums\CalculationType::TIERED && !empty($setting->tiers_config))
                    <div class="pts-divider"></div>
                    <h5 class="mb-2"><i class="feather icon-layers"></i> المستويات</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>من (ريال)</th>
                                    <th>إلى (ريال)</th>
                                    <th>النقاط</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($setting->tiers_config as $tier)
                                <tr>
                                    <td>{{ number_format($tier['min'] ?? 0, 2) }}</td>
                                    <td>{{ isset($tier['max']) && $tier['max'] !== null ? number_format($tier['max'], 2) : 'غير محدود' }}</td>
                                    <td><span class="badge badge-success">{{ $tier['points'] ?? 0 }} نقطة</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
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
                            <span class="pts-timeline-date">{{ $setting->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @if($setting->created_at != $setting->updated_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-edit-2"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">آخر تحديث</p>
                            <span class="pts-timeline-date">{{ $setting->updated_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($setting->disabled_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon" style="background-color: #dc3545;">
                            <i class="feather icon-pause"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تم التعطيل</p>
                            <span class="pts-timeline-date">{{ $setting->disabled_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($setting->creator)
                    <div class="pts-divider"></div>
                    <div class="pts-user">
                        <div class="pts-user-avatar">
                            {{ substr($setting->creator->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="pts-user-name">{{ $setting->creator->name }}</div>
                            <div class="pts-user-type">المنشئ</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    <button type="button" class="pts-btn {{ $setting->is_active ? 'pts-btn-outline' : 'pts-btn-primary' }}" style="width: 100%;" id="toggleStatusBtn">
                        <i class="feather icon-{{ $setting->is_active ? 'pause' : 'play' }}"></i>
                        {{ $setting->is_active ? 'تعطيل الإعداد' : 'تفعيل الإعداد' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" action="{{ route('ecosystem.points.settings.destroy', $setting->id) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const isCurrentlyActive = {{ $setting->is_active ? 'true' : 'false' }};
        const action = isCurrentlyActive ? 'تعطيل' : 'تفعيل';

        if (isCurrentlyActive) {
            // عند التعطيل — اطلب السبب
            Swal.fire({
                title: 'تعطيل الإعداد؟',
                input: 'textarea',
                inputLabel: 'سبب التعطيل (اختياري)',
                inputPlaceholder: 'اذكر سبب تعطيل هذا الإعداد...',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، عطّل',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ route("ecosystem.points.settings.toggle-status", $setting->id) }}', {
                        _token: '{{ csrf_token() }}',
                        disabled_reason: result.value || null
                    }).done(function(response) {
                        if (response.success) window.location.reload();
                    });
                }
            });
        } else {
            // عند التفعيل — تأكيد بسيط
            Swal.fire({
                title: 'تفعيل الإعداد؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، فعّل',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('{{ route("ecosystem.points.settings.toggle-status", $setting->id) }}', {
                        _token: '{{ csrf_token() }}'
                    }).done(function(response) {
                        if (response.success) window.location.reload();
                    });
                }
            });
        }
    });

    $('#deleteBtn').on('click', function() {
        Swal.fire({
            title: 'حذف الإعداد؟',
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
