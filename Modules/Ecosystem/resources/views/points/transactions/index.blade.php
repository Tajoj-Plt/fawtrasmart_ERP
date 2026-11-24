@extends('ecosystem::master')

@section('title', 'معاملات النقاط')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">معاملات النقاط</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">معاملات النقاط</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.transactions.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
                <i class="feather icon-plus"></i> إضافة معاملة
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
                        <li><a data-action="collapse"><i class="feather icon-chevron-down"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.transactions.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">بحث</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="{{ request('search') }}" placeholder="بحث...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="user_type">نوع المستخدم</label>
                                    <select name="user_type" id="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ request('user_type') == $type ? 'selected' : '' }}>
                                                {{ $type == 'employee' ? 'موظف' : 'عميل' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="status">الحالة</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                                {{ \App\Enums\TransactionStatus::from($status)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="points_type">نوع النقاط</label>
                                    <select name="points_type" id="points_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="earned" {{ request('points_type') == 'earned' ? 'selected' : '' }}>مكتسبة</option>
                                        <option value="spent" {{ request('points_type') == 'spent' ? 'selected' : '' }}>مصروفة</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="branch_id">الفرع</label>
                                    <select name="branch_id" id="branch_id" class="form-control">
                                        <option value="">جميع الفروع</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from">من تاريخ</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" 
                                           value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to">إلى تاريخ</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control" 
                                           value="{{ request('date_to') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحد الأدنى للنقاط</label>
                                    <input type="number" name="min_points" class="form-control" placeholder="من" value="{{ request('min_points') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحد الأقصى للنقاط</label>
                                    <input type="number" name="max_points" class="form-control" placeholder="إلى" value="{{ request('max_points') }}">
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-search"></i> بحث
                                    </button>
                                    <a href="{{ route('ecosystem.points.transactions.index') }}" class="btn btn-secondary">
                                        <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                    </a>
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
                    <i class="feather icon-list"></i>
                </div>
                <div class="stat-label">إجمالي المعاملات</div>
                <div class="stat-value">{{ number_format($stats['total_transactions']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>معاملة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-plus-circle"></i>
                </div>
                <div class="stat-label">إجمالي المكتسب</div>
                <div class="stat-value">{{ number_format($stats['total_earned']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon">
                    <i class="feather icon-minus-circle"></i>
                </div>
                <div class="stat-label">إجمالي المصروف</div>
                <div class="stat-value">{{ number_format($stats['total_spent']) }}</div>
                <div class="stat-trend down">
                    <i class="feather icon-trending-down"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-label">معاملات معلقة</div>
                <div class="stat-value">{{ number_format($stats['pending_count']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-alert-circle"></i>
                    <span>بانتظار المعالجة</span>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة معاملات النقاط</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash-2"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.transactions.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $transactions->total() }} معاملة</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                        <label class="custom-control-label" for="selectAll"></label>
                                    </div>
                                </th>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>نوع الإجراء</th>
                                <th>النقاط</th>
                                <th>الرصيد بعد</th>
                                <th>الحالة</th>
                                <th>الفرع</th>
                                <th>التاريخ</th>
                                <th width="100">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" 
                                                   id="check{{ $transaction->id }}" value="{{ $transaction->id }}">
                                            <label class="custom-control-label" for="check{{ $transaction->id }}"></label>
                                        </div>
                                    </td>
                                    <td>{{ $transaction->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm mr-1 
                                                {{ $transaction->user_type->value == 'employee' ? 'bg-light-primary' : 'bg-light-success' }}">
                                                <span class="avatar-content">
                                                    <i class="feather icon-{{ $transaction->user_type->value == 'employee' ? 'user' : 'users' }}"></i>
                                                </span>
                                            </div>
                                            <div>
                                                @if($transaction->user_type->value == 'employee')
                                                    <strong>{{ $transaction->employee?->full_name ?? 'غير محدد' }}</strong>
                                                    <small class="d-block text-muted">موظف</small>
                                                @else
                                                    <strong>{{ $transaction->client?->trade_name ?? 'غير محدد' }}</strong>
                                                    <small class="d-block text-muted">عميل</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info">{{ $transaction->action_type }}</span>
                                    </td>
                                    <td>
                                        @if($transaction->points > 0)
                                            <span class="text-success font-weight-bold">
                                                <i class="feather icon-plus"></i> {{ number_format($transaction->points) }}
                                            </span>
                                        @else
                                            <span class="text-danger font-weight-bold">
                                                <i class="feather icon-minus"></i> {{ number_format(abs($transaction->points)) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-secondary">{{ number_format($transaction->balance_after) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $transaction->status->color() }}">
                                            {{ $transaction->status->label() }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction->branch?->name ?? 'عام' }}</td>
                                    <td>
                                        <small>{{ $transaction->created_at->format('Y-m-d') }}</small>
                                        <small class="d-block text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.transactions.show', $transaction->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.transactions.edit', $transaction->id) }}">
                                                    <i class="feather icon-edit mr-1"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-{{ $transaction->status->value == 'completed' ? 'warning' : 'success' }} toggle-status" 
                                                   href="javascript:void(0);"
                                                   data-id="{{ $transaction->id }}"
                                                   data-status="{{ $transaction->status->value }}">
                                                    <i class="feather icon-{{ $transaction->status->value == 'completed' ? 'x-circle' : 'check-circle' }} mr-1"></i>
                                                    {{ $transaction->status->value == 'completed' ? 'إلغاء' : 'تأكيد' }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.transactions.destroy', $transaction->id) }}" 
                                                      method="POST" class="delete-form d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather icon-trash-2 mr-1"></i> حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 48px; color: #ccc;"></i>
                                            <p class="mt-2 mb-0">لا توجد معاملات</p>
                                            <a href="{{ route('ecosystem.points.transactions.create') }}" class="btn btn-sm btn-primary mt-2">
                                                <i class="feather icon-plus"></i> إضافة معاملة جديدة
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $transactions, 'label' => 'معاملة'])
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
            text: 'سيتم حذف ' + ids.length + ' معاملة نهائياً!',
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
                    url: '{{ route("ecosystem.points.transactions.bulk-delete") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف', 'error');
                    }
                });
            }
        });
    });

    // Toggle status
    $('.toggle-status').click(function() {
        var btn = $(this);
        var transactionId = btn.data('id');
        var currentStatus = btn.data('status');
        var action = currentStatus == 'completed' ? 'إلغاء' : 'تأكيد';
        
        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: `هل تريد ${action} هذه المعاملة؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: currentStatus == 'completed' ? '#dc3545' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `نعم، ${action}`,
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/transactions") }}/' + transactionId + '/toggle-status',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('خطأ!', 'حدث خطأ أثناء تغيير الحالة', 'error');
                    }
                });
            }
        });
    });

    // Delete confirmation
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه المعاملة نهائياً!',
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
