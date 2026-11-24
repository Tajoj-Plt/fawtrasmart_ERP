@extends('ecosystem::master')

@section('title', 'قوالب الإشعارات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">قوالب الإشعارات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">قوالب الإشعارات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.notification-templates.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
                <i class="feather icon-plus"></i> إضافة قالب
            </a>
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
                    <form method="GET" action="{{ route('ecosystem.points.notification-templates.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-4">
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
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="is_active" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>نشط</option>
                                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>غير نشط</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.notification-templates.index') }}" class="btn btn-outline-secondary">
                                            <i class="feather icon-refresh-cw"></i>
                                        </a>
                                    </div>
                                </div>
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
                    <i class="feather icon-file-text"></i>
                </div>
                <div class="stat-label">إجمالي القوالب</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>قالب</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">قوالب نشطة</div>
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>متاحة للاستخدام</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-pause-circle"></i>
                </div>
                <div class="stat-label">قوالب معطلة</div>
                <div class="stat-value">{{ number_format($stats['inactive']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-alert-circle"></i>
                    <span>غير مفعلة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-send"></i>
                </div>
                <div class="stat-label">إجمالي المرسل</div>
                <div class="stat-value">{{ number_format($stats['total_sent']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-bar-chart-2"></i>
                    <span>إشعار</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-bell"></i> قائمة قوالب الإشعارات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.notification-templates.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $templates->total() }} قالب</span>
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
                                <th>نوع الحدث</th>
                                <th>العنوان</th>
                                <th>القنوات</th>
                                <th>عدد الإرسال</th>
                                <th>الحالة</th>
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="row{{ $template->id }}" value="{{ $template->id }}">
                                            <label class="custom-control-label" for="row{{ $template->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $eventInfo = $eventTypes[$template->event_type] ?? null;
                                        @endphp
                                        <span class="badge badge-light-primary">
                                            <i class="feather icon-tag"></i>
                                            {{ $eventInfo['label'] ?? $template->event_type }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $template->title_ar }}</strong>
                                            @if($template->title_en)
                                                <br><small class="text-muted">{{ $template->title_en }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @foreach($template->getChannels() as $ch)
                                            @php
                                                $channelEnum = \App\Enums\NotificationChannel::tryFrom($ch);
                                                $channelColorMap = [
                                                    'database' => 'primary',
                                                    'sms' => 'success',
                                                    'email' => 'warning',
                                                    'push' => 'danger',
                                                    'whatsapp' => 'success',
                                                ];
                                                $chBadge = $channelColorMap[$ch] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-light-{{ $chBadge }}">
                                                {{ $channelEnum ? $channelEnum->label() : $ch }}
                                            </span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info">
                                            <i class="feather icon-send"></i>
                                            {{ number_format($template->logs_count) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-modern {{ $template->is_active ? 'active' : 'inactive' }} toggle-status cursor-pointer"
                                              data-id="{{ $template->id }}"
                                              data-name="{{ $template->title_ar }}"
                                              data-status="{{ $template->is_active ? 'active' : 'inactive' }}">
                                            <i class="feather icon-{{ $template->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                            {{ $template->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.notification-templates.show', $template->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.notification-templates.edit', $template->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.notification-templates.destroy', $template->id) }}" method="POST" class="delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item delete-item text-danger">
                                                        <i class="feather icon-trash-2"></i> حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-bell-off" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد قوالب إشعارات</p>
                                            <a href="{{ route('ecosystem.points.notification-templates.create') }}" class="btn btn-primary mt-2">
                                                <i class="feather icon-plus"></i> إضافة قالب جديد
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $templates, 'label' => 'قالب'])
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
        toggleBulkDeleteBtn();
    });

    // Individual checkbox
    $('.row-checkbox').on('change', function() {
        toggleBulkDeleteBtn();
        if (!$(this).prop('checked')) {
            $('#selectAll').prop('checked', false);
        }
    });

    // Toggle bulk delete button
    function toggleBulkDeleteBtn() {
        const checkedCount = $('.row-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkDeleteBtn').show();
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }

    // Bulk delete
    $('#bulkDeleteBtn').on('click', function() {
        const ids = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف ' + ids.length + ' قالب نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("ecosystem.points.notification-templates.bulk-delete") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء الحذف');
                    }
                });
            }
        });
    });

    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        var templateId = btn.data('id');
        var templateName = btn.data('name');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 'active' ? 'inactive' : 'active';

        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: newStatus == 'active' ?
                `هل تريد تفعيل القالب "${templateName}"؟` :
                `هل تريد تعطيل القالب "${templateName}"؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus == 'active' ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus == 'active' ? 'نعم، فعّل' : 'نعم، عطّل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/notification-templates") }}/' + templateId + '/toggle-status',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.toggleClass('active inactive');
                            btn.find('i').toggleClass('icon-check-circle icon-x-circle');
                            btn.data('status', newStatus);
                            btn.contents().filter(function() { return this.nodeType === 3; }).remove();
                            btn.append(newStatus == 'active' ? ' نشط' : ' غير نشط');
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message || 'حدث خطأ أثناء التحديث');
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء تحديث الحالة');
                    }
                });
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا القالب نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endsection
