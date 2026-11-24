@extends('ecosystem::master')

@section('title', 'مستويات النقاط')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">مستويات النقاط</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">مستويات النقاط</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.tiers.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
                <i class="feather icon-plus"></i> إضافة مستوى
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
                    <form method="GET" action="{{ route('ecosystem.points.tiers.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="اسم المستوى..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
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
                                    <label>الحالة</label>
                                    <select name="is_active" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>نشط</option>
                                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>غير نشط</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.tiers.index') }}" class="btn btn-outline-secondary">
                                            <i class="feather icon-refresh-cw"></i> إعادة تعيين
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
                    <i class="feather icon-layers"></i>
                </div>
                <div class="stat-label">إجمالي المستويات</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>مستوى</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">مستويات نشطة</div>
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>متاح</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">مستويات العملاء</div>
                <div class="stat-value">{{ number_format($stats['client_tiers']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-user-check"></i>
                    <span>للعملاء</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-user"></i>
                </div>
                <div class="stat-label">مستويات الموظفين</div>
                <div class="stat-value">{{ number_format($stats['employee_tiers']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-briefcase"></i>
                    <span>للموظفين</span>
                </div>
            </div>
        </div>

        <!-- Tiers Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-layers"></i> قائمة المستويات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash-2"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.tiers.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $tiers->total() }} مستوى</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th width="3%">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                        <label class="custom-control-label" for="selectAll"></label>
                                    </div>
                                </th>
                                <th width="5%">#</th>
                                <th width="20%">المستوى</th>
                                <th width="12%">نوع المستخدم</th>
                                <th width="15%">نطاق النقاط</th>
                                <th width="10%">المضاعف</th>
                                <th width="10%">الخصم</th>
                                <th width="8%">المزايا</th>
                                <th width="10%">الحالة</th>
                                <th width="7%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tiers as $tier)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" 
                                                   id="check-{{ $tier->id }}" value="{{ $tier->id }}">
                                            <label class="custom-control-label" for="check-{{ $tier->id }}"></label>
                                        </div>
                                    </td>
                                    <td>{{ $tier->sort_order }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($tier->image)
                                                <img src="{{ asset('storage/' . $tier->image) }}" alt="{{ $tier->name }}" 
                                                     class="rounded-circle mr-2" style="width: 35px; height: 35px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px; background: {{ $tier->color ?? '#5c6bc0' }};">
                                                    <i class="{{ $tier->icon ?? 'feather icon-award' }} text-white"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{ $tier->name }}</strong>
                                                @if($tier->name_en)
                                                    <br><small class="text-muted">{{ $tier->name_en }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $tier->user_type->value == 'client' ? 'info' : 'warning' }}">
                                            {{ $tier->user_type->value == 'client' ? 'عميل' : 'موظف' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-primary">{{ number_format($tier->min_points) }}</span>
                                        <span class="text-muted">-</span>
                                        <span class="text-success">{{ $tier->max_points ? number_format($tier->max_points) : '∞' }}</span>
                                    </td>
                                    <td>
                                        @if($tier->points_multiplier > 1)
                                            <span class="badge badge-success">x{{ $tier->points_multiplier }}</span>
                                        @else
                                            <span class="text-muted">x1</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($tier->discount_percentage > 0)
                                            <span class="badge badge-primary">{{ $tier->discount_percentage }}%</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $benefitsCount = !empty($tier->benefits) && is_array($tier->benefits) ? count($tier->benefits) : 0;
                                        @endphp
                                        <span class="badge badge-secondary">{{ $benefitsCount }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $tier->is_active ? 'success' : 'danger' }}">
                                            {{ $tier->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.tiers.show', $tier->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.tiers.edit', $tier->id) }}">
                                                    <i class="feather icon-edit mr-1"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-{{ $tier->is_active ? 'warning' : 'success' }} toggle-status" 
                                                   href="javascript:void(0);"
                                                   data-id="{{ $tier->id }}"
                                                   data-status="{{ $tier->is_active ? 'active' : 'inactive' }}">
                                                    <i class="feather icon-{{ $tier->is_active ? 'x-circle' : 'check-circle' }} mr-1"></i>
                                                    {{ $tier->is_active ? 'إلغاء تفعيل' : 'تفعيل' }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.tiers.destroy', $tier->id) }}" 
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
                                        <i class="feather icon-layers font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد مستويات</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $tiers, 'label' => 'مستوى'])
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
            text: 'سيتم حذف ' + ids.length + ' مستوى نهائياً!',
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
                    url: '{{ route("ecosystem.points.tiers.bulk-delete") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', 'تم حذف المستويات بنجاح', 'success')
                                .then(() => location.reload());
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
        var tierId = btn.data('id');
        var currentStatus = btn.data('status');
        var action = currentStatus == 'active' ? 'إلغاء تفعيل' : 'تفعيل';
        
        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: `هل تريد ${action} هذا المستوى؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: currentStatus == 'active' ? '#dc3545' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `نعم، ${action}`,
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/tiers") }}/' + tierId + '/toggle-status',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', 'تم تغيير الحالة بنجاح', 'success')
                                .then(() => location.reload());
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
            text: 'سيتم حذف هذا المستوى نهائياً!',
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
