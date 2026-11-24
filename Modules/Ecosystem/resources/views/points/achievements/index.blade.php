@extends('ecosystem::master')

@section('title', 'الإنجازات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">الإنجازات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">الإنجازات</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            {{-- إضافة الإنجازات معطلة - الإنجازات تُدار من السيدر فقط --}}
            <span class="btn btn-secondary disabled" style="cursor: not-allowed; opacity: 0.6;" title="الإنجازات تُدار تلقائياً من النظام">
                <i class="feather icon-lock"></i> إضافة إنجاز معطلة
            </span>
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
                    <form method="GET" action="{{ route('ecosystem.points.achievements.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>البحث</label>
                                    <input type="text" name="search" class="form-control" placeholder="ابحث باسم الإنجاز..." value="{{ request('search') }}">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="client" {{ request('user_type') === 'client' ? 'selected' : '' }}>عملاء</option>
                                        <option value="employee" {{ request('user_type') === 'employee' ? 'selected' : '' }}>موظفين</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الفئة</label>
                                    <select name="category" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="loyalty" {{ request('category') === 'loyalty' ? 'selected' : '' }}>الولاء</option>
                                        <option value="sales" {{ request('category') === 'sales' ? 'selected' : '' }}>المبيعات</option>
                                        <option value="collection" {{ request('category') === 'collection' ? 'selected' : '' }}>التحصيل</option>
                                        <option value="attendance" {{ request('category') === 'attendance' ? 'selected' : '' }}>الحضور</option>
                                        <option value="teamwork" {{ request('category') === 'teamwork' ? 'selected' : '' }}>العمل الجماعي</option>
                                        <option value="acquisition" {{ request('category') === 'acquisition' ? 'selected' : '' }}>اكتساب العملاء</option>
                                        <option value="referral" {{ request('category') === 'referral' ? 'selected' : '' }}>الإحالة</option>
                                        <option value="engagement" {{ request('category') === 'engagement' ? 'selected' : '' }}>التفاعل</option>
                                        <option value="orders" {{ request('category') === 'orders' ? 'selected' : '' }}>الطلبات</option>
                                        <option value="spending" {{ request('category') === 'spending' ? 'selected' : '' }}>الإنفاق</option>
                                        <option value="visits" {{ request('category') === 'visits' ? 'selected' : '' }}>الزيارات</option>
                                        <option value="tasks" {{ request('category') === 'tasks' ? 'selected' : '' }}>المهام</option>
                                        <option value="milestone" {{ request('category') === 'milestone' ? 'selected' : '' }}>معالم بارزة</option>
                                        <option value="development" {{ request('category') === 'development' ? 'selected' : '' }}>التطوير</option>
                                        <option value="customer_service" {{ request('category') === 'customer_service' ? 'selected' : '' }}>خدمة العملاء</option>
                                        <option value="special" {{ request('category') === 'special' ? 'selected' : '' }}>خاص</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع الإنجاز</label>
                                    <select name="is_one_time" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="yes" {{ request('is_one_time') === 'yes' ? 'selected' : '' }}>مرة واحدة</option>
                                        <option value="no" {{ request('is_one_time') === 'no' ? 'selected' : '' }}>متكرر</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('ecosystem.points.achievements.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-x"></i> إعادة تعيين
                                </a>
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
                    <i class="feather icon-award"></i>
                </div>
                <div class="stat-label">إجمالي الإنجازات</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>إنجاز</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">إنجازات نشطة</div>
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
                <div class="stat-label">إنجازات العملاء</div>
                <div class="stat-value">{{ number_format($stats['client_achievements']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-user-check"></i>
                    <span>للعملاء</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-user"></i>
                </div>
                <div class="stat-label">إنجازات الموظفين</div>
                <div class="stat-value">{{ number_format($stats['employee_achievements']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-briefcase"></i>
                    <span>للموظفين</span>
                </div>
            </div>
        </div>

        <!-- Achievements Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> قائمة الإنجازات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash-2"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.achievements.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $achievements->total() }} إنجاز</span>
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
                                <th width="5%">الأيقونة</th>
                                <th width="20%">الإنجاز</th>
                                <th width="10%">النوع</th>
                                <th width="10%">الفئة</th>
                                <th width="15%">الشرط</th>
                                <th width="8%">النقاط</th>
                                <th width="10%">التكرار</th>
                                <th width="10%">الحالة</th>
                                <th width="7%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($achievements as $achievement)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox" 
                                                   id="check-{{ $achievement->id }}" value="{{ $achievement->id }}">
                                            <label class="custom-control-label" for="check-{{ $achievement->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        @if($achievement->image)
                                            <img src="{{ asset('storage/' . $achievement->image) }}" 
                                                 alt="{{ $achievement->name }}"
                                                 style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                                        @else
                                            <span style="width: 40px; height: 40px; border-radius: 8px; background: {{ $achievement->color ?? '#f0f0f0' }}; display: flex; align-items: center; justify-content: center;">
                                                @include('ecosystem::points.partials.achievement-icon', ['icon' => $achievement->icon, 'size' => '1.8rem'])
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('ecosystem.points.achievements.show', $achievement->id) }}" class="font-weight-bold">
                                            {{ $achievement->name }}
                                        </a>
                                        @if($achievement->name_en)
                                            <br><small class="text-muted">{{ $achievement->name_en }}</small>
                                        @endif
                                        @if($achievement->is_hidden)
                                            <span class="badge badge-light-warning badge-sm ml-1">مخفي</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $achievement->user_type->value == 'client' ? 'info' : 'warning' }}">
                                            {{ $achievement->user_type->value == 'client' ? 'عميل' : 'موظف' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-secondary">{{ $achievement->category }}</span>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $achievement->condition_type }}<br>
                                            {{ $achievement->condition_operator->label() }} {{ number_format($achievement->condition_value) }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            <i class="feather icon-star"></i> {{ number_format($achievement->bonus_points) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($achievement->is_one_time)
                                            <span class="badge badge-light-primary">مرة واحدة</span>
                                        @else
                                            <span class="badge badge-light-info">متكرر</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $achievement->is_active ? 'success' : 'danger' }}">
                                            {{ $achievement->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.achievements.show', $achievement->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.achievements.edit', $achievement->id) }}">
                                                    <i class="feather icon-edit mr-1"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-{{ $achievement->is_active ? 'warning' : 'success' }} toggle-status" 
                                                   href="javascript:void(0);"
                                                   data-id="{{ $achievement->id }}"
                                                   data-status="{{ $achievement->is_active ? 'active' : 'inactive' }}">
                                                    <i class="feather icon-{{ $achievement->is_active ? 'x-circle' : 'check-circle' }} mr-1"></i>
                                                    {{ $achievement->is_active ? 'إلغاء تفعيل' : 'تفعيل' }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.achievements.destroy', $achievement->id) }}" 
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
                                        <i class="feather icon-award font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد إنجازات</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $achievements, 'label' => 'إنجاز'])
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
            text: 'سيتم حذف ' + ids.length + ' إنجاز نهائياً!',
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
                    url: '{{ route("ecosystem.points.achievements.bulk-delete") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        location.reload();
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
        var achievementId = btn.data('id');
        var currentStatus = btn.data('status');
        var action = currentStatus == 'active' ? 'إلغاء تفعيل' : 'تفعيل';
        
        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: `هل تريد ${action} هذا الإنجاز؟`,
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
                    url: '{{ url("ecosystem/points/achievements") }}/' + achievementId + '/toggle-status',
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
            text: 'سيتم حذف هذا الإنجاز نهائياً!',
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
