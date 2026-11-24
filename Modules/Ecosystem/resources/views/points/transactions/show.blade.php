@extends('ecosystem::master')

@section('title', 'عرض معاملة النقاط')



@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">
<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            <i class="feather icon-activity"></i>
            معاملة #{{ $transaction->id }}
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.transactions.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            <a href="{{ route('ecosystem.points.transactions.edit', $transaction->id) }}" class="pts-btn pts-btn-primary">
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
            <div class="pts-hero-label">المستخدم</div>
            <h2 class="pts-hero-title">
                @if($transaction->user_type->value == 'employee')
                    {{ $transaction->employee?->full_name ?? 'غير محدد' }}
                @else
                    {{ $transaction->client?->full_name ?? 'غير محدد' }}
                @endif
            </h2>
            <span class="pts-badge pts-badge-info">
                <i class="feather icon-{{ $transaction->user_type->value == 'employee' ? 'user' : 'users' }}"></i>
                {{ $transaction->user_type->label() }}
            </span>
            <span class="pts-badge {{ $transaction->status->value == 'completed' ? 'pts-badge-success' : ($transaction->status->value == 'cancelled' ? 'pts-badge-danger' : 'pts-badge-warning') }}">
                {{ $transaction->status->label() }}
            </span>
        </div>
        <div class="pts-hero-points">
            <div class="pts-points-value {{ $transaction->points > 0 ? 'positive' : 'negative' }}">
                {{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}
            </div>
            <div class="pts-points-label">نقطة</div>
        </div>
    </div>

    <!-- Balance Flow -->
    <div class="pts-card">
        <div class="pts-card-body">
            <div class="pts-balance-flow">
                <div class="pts-balance-box">
                    <div class="pts-balance-box-value">{{ number_format($transaction->balance_before) }}</div>
                    <div class="pts-balance-box-label">قبل</div>
                </div>
                <div class="pts-balance-arrow">→</div>
                <div class="pts-balance-change {{ $transaction->points > 0 ? 'positive' : 'negative' }}">
                    {{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}
                </div>
                <div class="pts-balance-arrow">→</div>
                <div class="pts-balance-box">
                    <div class="pts-balance-box-value">{{ number_format($transaction->balance_after) }}</div>
                    <div class="pts-balance-box-label">بعد</div>
                </div>
            </div>
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
                        <span class="pts-info-label">نوع الإجراء</span>
                        <span class="pts-info-value">
                            <code class="pts-hero-code">{{ $transaction->action_type }}</code>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الفرع</span>
                        <span class="pts-info-value">{{ $transaction->branch?->name ?? 'غير محدد' }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحالة</span>
                        <span class="pts-info-value">
                            <span class="pts-badge {{ $transaction->status->value == 'completed' ? 'pts-badge-success' : ($transaction->status->value == 'cancelled' ? 'pts-badge-danger' : 'pts-badge-warning') }}">
                                {{ $transaction->status->label() }}
                            </span>
                        </span>
                    </div>
                    @if($transaction->description)
                    <div class="pts-divider"></div>
                    <div class="pts-description">{{ $transaction->description }}</div>
                    @endif
                    @if($transaction->expires_at)
                    <div class="pts-divider"></div>
                    <div class="pts-alert {{ $transaction->expires_at->isPast() ? 'pts-alert-warning' : 'pts-alert-info' }}">
                        <i class="feather icon-clock"></i>
                        <div>
                            <strong>تنتهي الصلاحية:</strong> {{ $transaction->expires_at->format('Y-m-d') }}
                            @if($transaction->expires_at->isPast())
                                <span class="pts-badge pts-badge-danger">منتهي</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            @if($transaction->reference_type && $transaction->reference_id)
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-link"></i>
                        المرجع
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">النوع</span>
                        <span class="pts-info-value">{{ class_basename($transaction->reference_type) }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الرقم</span>
                        <span class="pts-info-value">#{{ $transaction->reference_id }}</span>
                    </div>
                </div>
            </div>
            @endif
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
                            <span class="pts-timeline-date">{{ $transaction->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @if($transaction->created_at->format('Y-m-d H:i') != $transaction->updated_at->format('Y-m-d H:i'))
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-edit-2"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">آخر تحديث</p>
                            <span class="pts-timeline-date">{{ $transaction->updated_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($transaction->creator)
                    <div class="pts-divider"></div>
                    <div class="pts-user">
                        <div class="pts-user-avatar">
                            {{ substr($transaction->creator->name ?? 'U', 0, 1) }}
                        </div>
                        <div>
                            <div class="pts-user-name">{{ $transaction->creator->name ?? 'غير محدد' }}</div>
                            <div class="pts-user-type">المنشئ</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    <button type="button" class="pts-btn {{ $transaction->status->value == 'completed' ? 'pts-btn-outline' : 'pts-btn-primary' }}" style="width: 100%;" id="toggleStatusBtn">
                        <i class="feather icon-{{ $transaction->status->value == 'completed' ? 'x-circle' : 'check-circle' }}"></i>
                        {{ $transaction->status->value == 'completed' ? 'إلغاء المعاملة' : 'تأكيد المعاملة' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" action="{{ route('ecosystem.points.transactions.destroy', $transaction->id) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const action = '{{ $transaction->status->value }}' == 'completed' ? 'إلغاء' : 'تأكيد';
        Swal.fire({
            title: action + ' المعاملة؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('{{ route("ecosystem.points.transactions.toggle-status", $transaction->id) }}', {
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
            title: 'حذف المعاملة؟',
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
