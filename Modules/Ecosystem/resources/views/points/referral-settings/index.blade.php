@extends('ecosystem::master')

@section('title', 'إعدادات الإحالة')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إعدادات الإحالة</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">إعدادات الإحالة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.referral-settings.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
                <i class="feather icon-plus"></i> إضافة إعداد
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
                    <form method="GET" action="{{ route('ecosystem.points.referral-settings.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع التأكيد</label>
                                    <select name="confirmation_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach(\App\Enums\ConfirmationType::cases() as $type)
                                            <option value="{{ $type->value }}" {{ request('confirmation_type') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">الكل</option>
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" placeholder="ابحث باسم الفرع..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.referral-settings.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-settings"></i>
                </div>
                <div class="stat-label">إجمالي الإعدادات</div>
                <div class="stat-value">{{ $settings->total() }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>إعداد إحالة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">إعدادات نشطة</div>
                <div class="stat-value">{{ $settings->where('is_active', true)->count() }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>متاحة للاستخدام</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-gift"></i>
                </div>
                <div class="stat-label">أعلى نقاط مُحيل</div>
                <div class="stat-value">{{ number_format($settings->max('referrer_points') ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-award"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">أعلى نقاط مُحال</div>
                <div class="stat-value">{{ number_format($settings->max('referred_points') ?? 0) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-bar-chart-2"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة إعدادات الإحالة</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.referral-settings.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $settings->total() }} إعداد</span>
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
                                <th>نقاط المُحيل</th>
                                <th>نقاط المُحال</th>
                                <th>نوع التأكيد</th>
                                <th>الحدود</th>
                                <th>الفرع</th>
                                <th>الحالة</th>
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($settings as $setting)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="row{{ $setting->id }}" value="{{ $setting->id }}">
                                            <label class="custom-control-label" for="row{{ $setting->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-success">
                                            +{{ number_format($setting->referrer_points) }} نقطة
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info">
                                            +{{ number_format($setting->referred_points) }} نقطة
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $setting->confirmation_type->label() }}</strong>
                                            @if($setting->confirmation_value)
                                                <br><small class="text-muted">
                                                    القيمة: {{ number_format($setting->confirmation_value, 2) }}
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            @if($setting->max_referrals_per_month)
                                                <small><i class="feather icon-calendar"></i> شهري: {{ $setting->max_referrals_per_month }}</small><br>
                                            @endif
                                            @if($setting->max_referrals_total)
                                                <small><i class="feather icon-hash"></i> إجمالي: {{ $setting->max_referrals_total }}</small>
                                            @endif
                                            @if(!$setting->max_referrals_per_month && !$setting->max_referrals_total)
                                                <span class="text-muted">غير محدود</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($setting->branch)
                                            <span class="badge badge-light-secondary">{{ $setting->branch->name }}</span>
                                        @else
                                            <span class="text-muted">الكل</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-modern {{ $setting->is_active ? 'active' : 'inactive' }} toggle-status cursor-pointer"
                                              data-id="{{ $setting->id }}"
                                              data-status="{{ $setting->is_active ? 'active' : 'inactive' }}">
                                            <i class="feather icon-{{ $setting->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                            {{ $setting->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.referral-settings.show', $setting->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.referral-settings.edit', $setting->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.referral-settings.destroy', $setting->id) }}" method="POST" class="delete-form">
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
                                    <td colspan="8" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد إعدادات إحالة لعرضها</p>
                                            <a href="{{ route('ecosystem.points.referral-settings.create') }}" class="btn btn-primary mt-2">
                                                <i class="feather icon-plus"></i> إضافة إعداد جديد
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $settings, 'label' => 'إعداد'])
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
            text: 'سيتم حذف ' + ids.length + ' إعداد نهائياً!',
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
                    url: '{{ route("ecosystem.points.referral-settings.bulk-delete") }}',
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

    // Toggle status
    $('.toggle-status').click(function() {
        var btn = $(this);
        var settingId = btn.data('id');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 'active' ? 'inactive' : 'active';

        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: newStatus == 'active' ? 'هل تريد تفعيل هذا الإعداد؟' : 'هل تريد إيقاف هذا الإعداد؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus == 'active' ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus == 'active' ? 'نعم، فعّل' : 'نعم، أوقف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/referral-settings") }}/' + settingId + '/toggle-status',
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

    // Delete confirmation
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا الإعداد نهائياً!',
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
