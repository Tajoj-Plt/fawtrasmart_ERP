@extends('ecosystem::master')

@section('title', 'سجل الإشعارات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">سجل الإشعارات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">سجل الإشعارات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.notification-logs.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع الحدث</label>
                                    <select name="event_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($eventTypes as $key => $event)
                                            <option value="{{ $key }}" {{ request('event_type') == $key ? 'selected' : '' }}>
                                                {{ $event['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($userTypes as $ut)
                                            <option value="{{ $ut->value }}" {{ request('user_type') == $ut->value ? 'selected' : '' }}>
                                                {{ $ut->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>القناة</label>
                                    <select name="channel" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($channels as $channel)
                                            <option value="{{ $channel->value }}" {{ request('channel') == $channel->value ? 'selected' : '' }}>
                                                {{ $channel->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>إلى تاريخ</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group mb-0">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-search"></i></span>
                                        </div>
                                        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="بحث بالعنوان أو اسم المستخدم...">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-search"></i> بحث
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('ecosystem.points.notification-logs.index') }}" class="btn btn-outline-secondary btn-block">
                                    <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                </a>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-bell"></i>
                </div>
                <div class="stat-label">إجمالي الإشعارات</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>إشعار</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-label">معلّق</div>
                <div class="stat-value">{{ number_format($stats['pending']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-alert-circle"></i>
                    <span>بانتظار الإرسال</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-send"></i>
                </div>
                <div class="stat-label">مُرسل</div>
                <div class="stat-value">{{ number_format($stats['sent']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-check"></i>
                    <span>تم الإرسال</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">مقروء</div>
                <div class="stat-value">{{ number_format($stats['read']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-eye"></i>
                    <span>تمت القراءة</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon">
                    <i class="feather icon-x-circle"></i>
                </div>
                <div class="stat-label">فشل</div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-alert-triangle"></i>
                    <span>فشل الإرسال</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة سجل الإشعارات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-primary" id="markAllReadBtn" style="display: none;">
                        <i class="feather icon-check-square"></i> تعليم كمقروء (<span id="selectedCount">0</span>)
                    </button>
                    <a href="{{ route('ecosystem.points.notification-logs.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $logs->total() }} إشعار</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                        <label class="custom-control-label" for="selectAll"></label>
                                    </div>
                                </th>
                                <th>العنوان</th>
                                <th>المستخدم</th>
                                <th>القناة</th>
                                <th>نوع الحدث</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th class="text-center" style="width: 80px;">عرض</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="row{{ $log->id }}" value="{{ $log->id }}">
                                            <label class="custom-control-label" for="row{{ $log->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            @if(!$log->isRead())
                                                <span class="badge badge-sm badge-primary mr-1" style="font-size: 0.6rem; padding: 2px 5px;">جديد</span>
                                            @endif
                                            <strong title="{{ $log->body }}">{{ Str::limit($log->title, 40) }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        @if($log->user_type === \App\Enums\UserType::EMPLOYEE && $log->employee)
                                            <div>
                                                <span class="badge badge-light-primary">
                                                    <i class="feather icon-briefcase"></i> موظف
                                                </span>
                                                <br><small class="text-muted">{{ $log->employee->full_name }}</small>
                                            </div>
                                        @elseif($log->user_type === \App\Enums\UserType::CLIENT && $log->client)
                                            <div>
                                                <span class="badge badge-light-success">
                                                    <i class="feather icon-user"></i> عميل
                                                </span>
                                                <br><small class="text-muted">{{ $log->client->full_name }}</small>
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $channelColorMap = [
                                                'database' => 'primary',
                                                'sms' => 'success',
                                                'email' => 'warning',
                                                'push' => 'danger',
                                                'whatsapp' => 'success',
                                            ];
                                            $chVal = $log->channel?->value ?? '';
                                            $chBadge = $channelColorMap[$chVal] ?? 'secondary';
                                        @endphp
                                        <span class="badge badge-light-{{ $chBadge }}">
                                            {{ $log->channel?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $logEventInfo = $eventTypes[$log->template?->event_type ?? ''] ?? null;
                                        @endphp
                                        <span class="badge badge-light-secondary">
                                            {{ $logEventInfo['label'] ?? ($log->template?->event_type ?? '—') }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $statusBadgeMap = [
                                                'pending' => 'warning',
                                                'sent' => 'info',
                                                'read' => 'success',
                                                'failed' => 'danger',
                                            ];
                                            $statusIconMap = [
                                                'pending' => 'icon-clock',
                                                'sent' => 'icon-send',
                                                'read' => 'icon-check-circle',
                                                'failed' => 'icon-x-circle',
                                            ];
                                            $sVal = $log->status?->value ?? 'pending';
                                            $sBadge = $statusBadgeMap[$sVal] ?? 'warning';
                                            $sIcon = $statusIconMap[$sVal] ?? 'icon-clock';
                                        @endphp
                                        <span class="badge badge-light-{{ $sBadge }}">
                                            <i class="feather {{ $sIcon }}"></i>
                                            {{ $log->status?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <span>{{ $log->created_at->format('Y-m-d') }}</span>
                                            <br><small class="text-muted">{{ $log->created_at->format('H:i') }}</small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.notification-logs.show', $log->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد إشعارات مسجلة</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $logs, 'label' => 'إشعار'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Select all checkboxes
    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkActions();
    });

    // Individual checkbox
    $('.row-checkbox').on('change', function() {
        updateBulkActions();
        if (!$(this).prop('checked')) {
            $('#selectAll').prop('checked', false);
        }
    });

    function updateBulkActions() {
        var count = $('.row-checkbox:checked').length;
        $('#markAllReadBtn').toggle(count > 0);
        $('#selectedCount').text(count);
    }

    // Mark selected as read
    $('#markAllReadBtn').on('click', function() {
        var ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (ids.length === 0) { toastr.warning('يرجى تحديد إشعارات أولاً'); return; }

        Swal.fire({
            title: 'تأكيد',
            text: 'سيتم تعليم ' + ids.length + ' إشعار كمقروء',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، علّم كمقروء',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("ecosystem.points.notification-logs.bulk-mark-read") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}', ids: ids },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء التحديث');
                    }
                });
            }
        });
    });
});
</script>
@endsection
