@extends('ecosystem::master')

@section('title', 'تفاصيل الإشعار')

@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            <i class="feather icon-bell"></i>
            تفاصيل الإشعار #{{ $notification_log->id }}
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.notification-logs.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            @if(!$notification_log->isRead() && !$notification_log->isFailed())
            <button type="button" class="pts-btn pts-btn-primary" id="markReadBtn">
                <i class="feather icon-check"></i>
                تعليم كمقروء
            </button>
            @endif
        </div>
    </div>

    <!-- Hero -->
    <div class="pts-hero">
        <div class="pts-hero-info">
            <div class="pts-hero-label">عنوان الإشعار</div>
            <h2 class="pts-hero-title">{{ $notification_log->title }}</h2>
            @if($notification_log->template)
                <code class="pts-hero-code">{{ $notification_log->template->event_type }}</code>
            @endif
            <span class="pts-badge pts-badge-{{ $notification_log->status?->color() ?? 'secondary' }}" style="margin-right: 0.5rem;">
                <span class="pts-status-dot {{ $notification_log->isRead() ? 'active' : ($notification_log->isFailed() ? 'inactive' : '') }}"></span>
                {{ $notification_log->status?->label() ?? '-' }}
            </span>
        </div>
        <div class="pts-hero-points">
            <div class="pts-points-value positive">
                <i class="feather icon-{{ $notification_log->channel?->value === 'push' ? 'bell' : ($notification_log->channel?->value === 'sms' ? 'smartphone' : ($notification_log->channel?->value === 'email' ? 'mail' : 'database')) }}" style="font-size: 2rem;"></i>
            </div>
            <div class="pts-points-label">{{ $notification_log->channel?->label() ?? '-' }}</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
            <!-- محتوى الإشعار -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-message-square"></i>
                        محتوى الإشعار
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; border-right: 4px solid #7367f0;">
                        <strong style="font-size: 1.1rem;">{{ $notification_log->title }}</strong>
                        <p class="mb-0 mt-2 text-muted" style="white-space: pre-wrap; line-height: 1.8;">{{ $notification_log->body }}</p>
                    </div>
                </div>
            </div>

            <!-- التفاصيل -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-info"></i>
                        التفاصيل
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">رقم الإشعار</span>
                        <span class="pts-info-value"><strong>#{{ $notification_log->id }}</strong></span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">القالب</span>
                        <span class="pts-info-value">
                            @if($notification_log->template)
                                <a href="{{ route('ecosystem.points.notification-templates.show', $notification_log->template_id) }}" style="color: #7367f0;">
                                    {{ $notification_log->template->title_ar }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">المستخدم</span>
                        <span class="pts-info-value">
                            @if($notification_log->user_type === \App\Enums\UserType::EMPLOYEE && $notification_log->employee)
                                <span class="pts-badge pts-badge-info">
                                    <i class="feather icon-user"></i> موظف
                                </span>
                                {{ $notification_log->employee->full_name }}
                            @elseif($notification_log->user_type === \App\Enums\UserType::CLIENT && $notification_log->client)
                                <span class="pts-badge pts-badge-success">
                                    <i class="feather icon-users"></i> عميل
                                </span>
                                {{ $notification_log->client->full_name }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">القناة</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-info">{{ $notification_log->channel?->label() ?? '-' }}</span>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">الحالة</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-{{ $notification_log->status?->color() ?? 'secondary' }}">
                                {{ $notification_log->status?->label() ?? '-' }}
                            </span>
                        </span>
                    </div>
                    @if($notification_log->failure_reason)
                    <div class="pts-info-row">
                        <span class="pts-info-label">سبب الفشل</span>
                        <span class="pts-info-value" style="color: #ea5455;">{{ $notification_log->failure_reason }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- البيانات المرفقة -->
            @if(!empty($notification_log->data))
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-database"></i>
                        البيانات المرفقة
                    </h3>
                </div>
                <div class="pts-card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>المتغير</th>
                                    <th>القيمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notification_log->data as $key => $value)
                                <tr>
                                    <td><code style="padding: 2px 8px; background: #fff3cd; border-radius: 4px;">{!! '{' . $key . '}' !!}</code></td>
                                    <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div>
            <!-- التواريخ -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-clock"></i>
                        السجل الزمني
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-plus"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تم الإنشاء</p>
                            <span class="pts-timeline-date">{{ $notification_log->created_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @if($notification_log->sent_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-send"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تم الإرسال</p>
                            <span class="pts-timeline-date">{{ $notification_log->sent_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($notification_log->delivered_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-check"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تم التسليم</p>
                            <span class="pts-timeline-date">{{ $notification_log->delivered_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($notification_log->read_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-eye"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">تمت القراءة</p>
                            <span class="pts-timeline-date">{{ $notification_log->read_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @endif
                    @if($notification_log->failed_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon" style="background: #fce4ec;">
                            <i class="feather icon-x" style="color: #ea5455;"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title" style="color: #ea5455;">فشل الإرسال</p>
                            <span class="pts-timeline-date">{{ $notification_log->failed_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- البيانات الوصفية -->
            @if(!empty($notification_log->metadata))
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-tag"></i>
                        بيانات وصفية
                    </h3>
                </div>
                <div class="pts-card-body">
                    <pre style="background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 0.85rem; direction: ltr; margin: 0;">{{ json_encode($notification_log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#markReadBtn').on('click', function() {
        var btn = $(this);
        Swal.fire({
            title: 'تعليم كمقروء؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('{{ route("ecosystem.points.notification-logs.mark-read", $notification_log->id) }}', {
                    _token: '{{ csrf_token() }}'
                }).done(function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        window.location.reload();
                    }
                }).fail(function() {
                    toastr.error('حدث خطأ');
                });
            }
        });
    });
});
</script>
@endsection
