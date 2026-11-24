@extends('ecosystem::master')

@section('title', 'تفاصيل قالب الإشعار')

@section('content')
<link rel="stylesheet" href="{{ asset('modules/ecosystem/css/points-show.css') }}">

<div class="pts-container">
    <!-- Header -->
    <div class="pts-header">
        <h1 class="pts-header-title">
            <i class="feather icon-bell"></i>
            قالب الإشعار
        </h1>
        <div class="pts-header-actions">
            <a href="{{ route('ecosystem.points.notification-templates.index') }}" class="pts-btn pts-btn-outline">
                <i class="feather icon-arrow-right"></i>
                رجوع
            </a>
            <a href="{{ route('ecosystem.points.notification-templates.edit', $notification_template->id) }}" class="pts-btn pts-btn-primary">
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
            <div class="pts-hero-label">نوع الحدث</div>
            <h2 class="pts-hero-title">{{ $eventInfo['label'] ?? $notification_template->event_type }}</h2>
            <code class="pts-hero-code">{{ $notification_template->event_type }}</code>
            <span class="pts-badge {{ $notification_template->is_active ? 'pts-badge-success' : 'pts-badge-secondary' }}" style="margin-right: 0.5rem;">
                <span class="pts-status-dot {{ $notification_template->is_active ? 'active' : 'inactive' }}"></span>
                {{ $notification_template->is_active ? 'نشط' : 'غير نشط' }}
            </span>
        </div>
        <div class="pts-hero-points">
            <div class="pts-points-value positive">
                {{ number_format($notification_template->logs->count()) }}
            </div>
            <div class="pts-points-label">إشعار مرسل</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="pts-grid pts-grid-2">
        <!-- Main Info -->
        <div>
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
                        <span class="pts-info-label">نوع الحدث</span>
                        <span class="pts-info-value">
                            <span class="pts-badge pts-badge-info">
                                <i class="feather icon-zap"></i>
                                {{ $eventInfo['label'] ?? $notification_template->event_type }}
                            </span>
                        </span>
                    </div>
                    @if($eventInfo)
                    <div class="pts-info-row">
                        <span class="pts-info-label">الوصف</span>
                        <span class="pts-info-value">{{ $eventInfo['description'] }}</span>
                    </div>
                    @endif
                    <div class="pts-info-row">
                        <span class="pts-info-label">المتغيرات</span>
                        <span class="pts-info-value">
                            @foreach($notification_template->getVariables() as $var)
                                <code style="padding: 2px 8px; background: #fff3cd; border-radius: 4px; margin-left: 4px;">{!! '{' . $var . '}' !!}</code>
                            @endforeach
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">قنوات الإرسال</span>
                        <span class="pts-info-value">
                            @foreach($notification_template->getChannels() as $ch)
                                @php
                                    $channelEnum = \App\Enums\NotificationChannel::tryFrom($ch);
                                @endphp
                                <span class="pts-badge pts-badge-info" style="margin-left: 4px;">
                                    <i class="feather icon-check-circle"></i>
                                    {{ $channelEnum ? $channelEnum->label() : $ch }}
                                </span>
                            @endforeach
                        </span>
                    </div>
                </div>
            </div>

            <!-- المحتوى بالعربية -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-edit-3"></i>
                        المحتوى بالعربية
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">العنوان</span>
                        <span class="pts-info-value"><strong>{{ $notification_template->title_ar }}</strong></span>
                    </div>
                    <div class="pts-divider"></div>
                    <div class="pts-description" style="white-space: pre-wrap;">{{ $notification_template->body_ar }}</div>
                </div>
            </div>

            <!-- المحتوى بالإنجليزية -->
            @if($notification_template->title_en || $notification_template->body_en)
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-globe"></i>
                        المحتوى بالإنجليزية
                    </h3>
                </div>
                <div class="pts-card-body">
                    @if($notification_template->title_en)
                    <div class="pts-info-row">
                        <span class="pts-info-label">Title</span>
                        <span class="pts-info-value"><strong>{{ $notification_template->title_en }}</strong></span>
                    </div>
                    @endif
                    @if($notification_template->body_en)
                    <div class="pts-divider"></div>
                    <div class="pts-description" dir="ltr" style="white-space: pre-wrap;">{{ $notification_template->body_en }}</div>
                    @endif
                </div>
            </div>
            @endif

            <!-- آخر الإشعارات المرسلة -->
            @if($notification_template->logs->count() > 0)
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-list"></i>
                        آخر الإشعارات المرسلة
                    </h3>
                </div>
                <div class="pts-card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>القناة</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notification_template->logs as $log)
                                <tr>
                                    <td>
                                        @if($log->user_type === \App\Enums\UserType::EMPLOYEE && $log->employee)
                                            <i class="feather icon-user"></i> {{ $log->employee->full_name }}
                                        @elseif($log->client)
                                            <i class="feather icon-users"></i> {{ $log->client->full_name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info">{{ $log->channel?->label() ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $log->status?->color() ?? 'secondary' }}">
                                            {{ $log->status?->label() ?? '-' }}
                                        </span>
                                    </td>
                                    <td><small>{{ $log->created_at->format('Y-m-d H:i') }}</small></td>
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
            <!-- السجل -->
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
                            <span class="pts-timeline-date">{{ $notification_template->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @if($notification_template->created_at != $notification_template->updated_at)
                    <div class="pts-timeline-item">
                        <div class="pts-timeline-icon">
                            <i class="feather icon-edit-2"></i>
                        </div>
                        <div class="pts-timeline-content">
                            <p class="pts-timeline-title">آخر تحديث</p>
                            <span class="pts-timeline-date">{{ $notification_template->updated_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- إحصائيات -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-bar-chart-2"></i>
                        إحصائيات
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div class="pts-info-row">
                        <span class="pts-info-label">إجمالي الإرسال</span>
                        <span class="pts-info-value">
                            <strong>{{ number_format($notification_template->logs->count()) }}</strong>
                        </span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">عدد القنوات</span>
                        <span class="pts-info-value">{{ count($notification_template->getChannels()) }}</span>
                    </div>
                    <div class="pts-info-row">
                        <span class="pts-info-label">عدد المتغيرات</span>
                        <span class="pts-info-value">{{ count($notification_template->getVariables()) }}</span>
                    </div>
                </div>
            </div>

            <!-- معاينة الإشعار -->
            <div class="pts-card">
                <div class="pts-card-header">
                    <h3 class="pts-card-title">
                        <i class="feather icon-eye"></i>
                        معاينة الإشعار
                    </h3>
                </div>
                <div class="pts-card-body">
                    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; border-right: 4px solid #7367f0;">
                        <strong>{{ $notification_template->title_ar }}</strong>
                        <p class="mb-0 mt-1 text-muted" style="font-size: 0.9rem; white-space: pre-wrap;">{{ $notification_template->body_ar }}</p>
                    </div>
                    <small class="text-muted mt-2 d-block">* المتغيرات بين {} سيتم استبدالها بالقيم الفعلية عند الإرسال</small>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="pts-card">
                <div class="pts-card-body">
                    <button type="button" class="pts-btn {{ $notification_template->is_active ? 'pts-btn-outline' : 'pts-btn-primary' }}" style="width: 100%;" id="toggleStatusBtn">
                        <i class="feather icon-{{ $notification_template->is_active ? 'pause' : 'play' }}"></i>
                        {{ $notification_template->is_active ? 'تعطيل القالب' : 'تفعيل القالب' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" action="{{ route('ecosystem.points.notification-templates.destroy', $notification_template->id) }}" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#toggleStatusBtn').on('click', function() {
        const action = {{ $notification_template->is_active ? 'true' : 'false' }} ? 'تعطيل' : 'تفعيل';
        Swal.fire({
            title: action + ' القالب؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('{{ route("ecosystem.points.notification-templates.toggle-status", $notification_template->id) }}', {
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
            title: 'حذف القالب؟',
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
@endsection
