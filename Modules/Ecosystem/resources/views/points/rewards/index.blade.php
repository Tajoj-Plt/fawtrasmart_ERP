@extends('ecosystem::master')

@section('title', 'المكافآت')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">المكافآت</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">المكافآت</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.rewards.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="feather icon-plus"></i> إضافة مكافأة
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
                    <form method="GET" action="{{ route('ecosystem.points.rewards.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="اسم المكافأة..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($userTypes as $type)
                                            <option value="{{ $type }}" {{ request('user_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\UserType::from($type)->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المكافأة</label>
                                    <select name="reward_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($rewardTypes as $type)
                                            <option value="{{ $type }}" {{ request('reward_type') == $type ? 'selected' : '' }}>
                                                {{ \App\Enums\RewardType::from($type)->label() }}
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.rewards.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-gift"></i>
                </div>
                <div class="stat-label">إجمالي المكافآت</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-package"></i>
                    <span>مكافأة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">مكافآت نشطة</div>
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>متاحة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">مكافآت مميزة</div>
                <div class="stat-value">{{ number_format($stats['featured']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-award"></i>
                    <span>مميزة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-repeat"></i>
                </div>
                <div class="stat-label">إجمالي الاستبدال</div>
                <div class="stat-value">{{ number_format($stats['total_redeemed']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-shopping-bag"></i>
                    <span>عملية</span>
                </div>
            </div>
        </div>

        <!-- Rewards Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-gift"></i> قائمة المكافآت</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash-2"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.rewards.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $rewards->total() }} مكافأة</span>
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
                                <th width="25%">المكافأة</th>
                                <th width="10%">نوع المستخدم</th>
                                <th width="12%">نوع المكافأة</th>
                                <th width="10%">النقاط</th>
                                <th width="10%">الكمية</th>
                                <th width="10%">الحالة</th>
                                <th width="10%">التمييز</th>
                                <th width="10%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rewards as $reward)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" 
                                                   id="check-{{ $reward->id }}" value="{{ $reward->id }}">
                                            <label class="custom-control-label" for="check-{{ $reward->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($reward->image)
                                                <img src="{{ asset('storage/' . $reward->image) }}" alt="{{ $reward->name }}" 
                                                     class="rounded mr-2" style="width: 45px; height: 45px; object-fit: cover;">
                                            @else
                                                <div class="rounded mr-2 d-flex align-items-center justify-content-center bg-light-primary" 
                                                     style="width: 45px; height: 45px;">
                                                    <i class="feather icon-gift text-primary"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{ $reward->name }}</strong>
                                                @if($reward->name_en)
                                                    <br><small class="text-muted">{{ $reward->name_en }}</small>
                                                @endif
                                                @if($reward->minTier)
                                                    <br><span class="badge badge-light-secondary" style="font-size: 10px;">
                                                        الحد الأدنى: {{ $reward->minTier->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $reward->user_type->value === 'client' ? 'info' : 'warning' }}">
                                            {{ $reward->user_type->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ $reward->reward_type->icon() }} {{ $reward->reward_type->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-primary font-weight-bold">{{ number_format($reward->points_required) }}</span>
                                        <small class="d-block text-muted">نقطة</small>
                                    </td>
                                    <td>
                                        @if($reward->quantity_available)
                                            <span class="badge badge-light-{{ $reward->getRemainingQuantity() > 0 ? 'success' : 'danger' }}">
                                                {{ number_format($reward->getRemainingQuantity()) }} / {{ number_format($reward->quantity_available) }}
                                            </span>
                                        @else
                                            <span class="badge badge-light-secondary">غير محدود</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $reward->is_active ? 'success' : 'danger' }} toggle-status cursor-pointer"
                                              data-id="{{ $reward->id }}"
                                              data-status="{{ $reward->is_active ? 'active' : 'inactive' }}">
                                            <i class="feather icon-{{ $reward->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                            {{ $reward->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $reward->is_featured ? 'warning' : 'secondary' }} toggle-featured cursor-pointer"
                                              data-id="{{ $reward->id }}">
                                            <i class="feather icon-star{{ $reward->is_featured ? '' : '-off' }}"></i>
                                            {{ $reward->is_featured ? 'مميز' : 'عادي' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.rewards.show', $reward->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.rewards.edit', $reward->id) }}">
                                                    <i class="feather icon-edit mr-1"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.rewards.destroy', $reward->id) }}" 
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
                                    <td colspan="9" class="text-center py-4">
                                        <i class="feather icon-gift font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد مكافآت</p>
                                        <a href="{{ route('ecosystem.points.rewards.create') }}" class="btn btn-sm btn-primary mt-2">
                                            <i class="feather icon-plus"></i> إضافة مكافأة جديدة
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $rewards, 'label' => 'مكافأة'])
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
            text: 'سيتم حذف ' + ids.length + ' مكافأة نهائياً!',
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
                    url: '{{ route("ecosystem.points.rewards.bulk-delete") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', 'تم حذف المكافآت بنجاح', 'success')
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
        var rewardId = btn.data('id');
        var currentStatus = btn.data('status');
        var action = currentStatus == 'active' ? 'تعطيل' : 'تفعيل';
        
        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: `هل تريد ${action} هذه المكافأة؟`,
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
                    url: '{{ url("ecosystem/points/rewards") }}/' + rewardId + '/toggle-status',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('تم!', response.message, 'success')
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

    // Toggle featured
    $('.toggle-featured').click(function() {
        var btn = $(this);
        var rewardId = btn.data('id');
        
        $.ajax({
            url: '{{ url("ecosystem/points/rewards") }}/' + rewardId + '/toggle-featured',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function() {
                Swal.fire('خطأ!', 'حدث خطأ أثناء تغيير التمييز', 'error');
            }
        });
    });

    // Delete confirmation
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه المكافأة نهائياً!',
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
