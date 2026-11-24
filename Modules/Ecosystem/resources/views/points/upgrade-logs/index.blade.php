@extends('ecosystem::master')

@section('title', 'سجل ترقيات المستويات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">سجل ترقيات المستويات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">سجل ترقيات المستويات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.upgrade-logs.export') }}"
                class="btn btn-success dropdown-toggle waves-effect waves-light">
                <i class="feather icon-download"></i> تصدير
            </a>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

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
                    <form method="GET" action="{{ route('ecosystem.points.upgrade-logs.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach ($userTypes as $type)
                                            <option value="{{ $type }}"
                                                {{ request('user_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\UserType::from($type)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع الترقية</label>
                                    <select name="upgrade_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach ($upgradeTypes as $type)
                                            <option value="{{ $type }}"
                                                {{ request('upgrade_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\UpgradeType::from($type)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>من تاريخ</label>
                                    <input type="date" name="date_from" class="form-control"
                                        value="{{ request('date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>إلى تاريخ</label>
                                    <input type="date" name="date_to" class="form-control"
                                        value="{{ request('date_to') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-search"></i> بحث
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control"
                                        placeholder="ابحث باسم المستخدم أو المستوى" value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>المستوى</label>
                                    <select name="tier_id" class="form-control">
                                        <option value="">كل المستويات</option>
                                        @foreach($tiers as $tier)
                                            <option value="{{ $tier->id }}" {{ request('tier_id') == $tier->id ? 'selected' : '' }}>
                                                {{ $tier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">كل الفروع</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <a href="{{ route('ecosystem.points.upgrade-logs.index') }}" class="btn btn-outline-secondary">
                                            <i class="feather icon-x"></i> إعادة تعيين
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
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">ترقيات العملاء</div>
                <div class="stat-value">{{ number_format($stats['client_upgrades'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>عميل</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-user"></i>
                </div>
                <div class="stat-label">ترقيات الموظفين</div>
                <div class="stat-value">{{ number_format($stats['employee_upgrades'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>موظف</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-arrow-up-circle"></i>
                </div>
                <div class="stat-label">ترقيات تلقائية</div>
                <div class="stat-value">{{ number_format($stats['automatic_upgrades'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-zap"></i>
                    <span>تلقائية</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-edit"></i>
                </div>
                <div class="stat-label">ترقيات يدوية</div>
                <div class="stat-value">{{ number_format($stats['manual_upgrades'] ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-edit-2"></i>
                    <span>يدوية</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة ترقيات المستويات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash"></i> حذف المحدد
                    </button>
                    <span class="badge-count mr-1">{{ $logs->total() }} سجل</span>
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
                                <th>نوع المستخدم</th>
                                <th>المستخدم</th>
                                <th>المستوى السابق</th>
                                <th>المستوى الجديد</th>
                                <th>النقاط</th>
                                <th>نوع الترقية</th>
                                <th>التاريخ</th>
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
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
                                        <span
                                            class="badge badge-{{ $log->user_type?->value === 'client' ? 'primary' : 'success' }}">
                                            {{ $log->user_type?->label() ?? ($log->user_type?->value ?? 'غير محدد') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar mr-1">
                                                @if ($log->user_type?->value === 'employee')
                                                    <span
                                                        class="avatar-content">{{ substr($log->employee?->first_name ?? 'غ', 0, 1) }}</span>
                                                @else
                                                    <span
                                                        class="avatar-content">{{ substr($log->client?->first_name ?? ($log->client?->trade_name ?? 'غ'), 0, 1) }}</span>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($log->user_type?->value === 'employee')
                                                    <div class="font-weight-bold">{{ $log->employee?->first_name }}
                                                        {{ $log->employee?->middle_name }}</div>
                                                    @if ($log->employee?->branch)
                                                        <small
                                                            class="text-muted">{{ $log->employee->branch->name }}</small>
                                                    @endif
                                                @else
                                                    <div class="font-weight-bold">
                                                        {{ $log->client?->trade_name ?? $log->client?->first_name . ' ' . $log->client?->last_name }}
                                                    </div>
                                                    @if ($log->client?->branch)
                                                        <small class="text-muted">{{ $log->client->branch->name }}</small>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($log->oldTier)
                                            <span class="badge badge-light-secondary">{{ $log->oldTier->name }}</span>
                                            <br><small class="text-muted">{{ number_format($log->oldTier->min_points) }}
                                                نقطة</small>
                                        @else
                                            <span class="text-muted">غير متوفر</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($log->newTier)
                                            <span
                                                class="badge badge-{{ $log->isUpgrade() ? 'success' : 'danger' }}">{{ $log->newTier->name }}</span>
                                            <br><small class="text-muted">{{ number_format($log->newTier->min_points) }}
                                                نقطة</small>
                                        @else
                                            <span class="text-muted">غير متوفر</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $log->isUpgrade() ? 'success' : 'danger' }}">
                                            {{ $log->points_at_upgrade > 0 ? '+' : '' }}{{ number_format($log->points_at_upgrade) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($log->upgrade_type)
                                            <span
                                                class="badge badge-{{ $log->upgrade_type->value === 'automatic' ? 'info' : 'warning' }}">
                                                {{ $log->upgrade_type->label() }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">غير محدد</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $log->created_at ? $log->created_at->format('Y-m-d') : 'غير محدد' }}</div>
                                        <small
                                            class="text-muted">{{ $log->created_at ? $log->created_at->format('H:i') : 'غير محدد' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item"
                                                    href="{{ route('ecosystem.points.upgrade-logs.show', $log->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد سجلات لعرضها</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
 
                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $logs, 'label' => 'سجل'])
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
                    text: 'سيتم حذف ' + ids.length + ' سجل نهائياً!',
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
                            url: '{{ route('ecosystem.points.upgrade-logs.bulk-delete') }}',
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
        });
    </script>
@endsection
