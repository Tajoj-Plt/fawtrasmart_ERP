@extends('ecosystem::master')

@section('title', 'إعدادات النقاط')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إعدادات النقاط</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">إعدادات النقاط</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.settings.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
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
                    <form method="GET" action="{{ route('ecosystem.points.settings.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>بحث بالاسم أو الوصف</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="اسم الإعداد أو الوصف أو نوع الإجراء..." value="{{ request('search') }}">
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
                                    <label>نوع الإجراء</label>
                                    <input type="text" name="action_type" class="form-control" 
                                           placeholder="مثال: invoice_created" value="{{ request('action_type') }}">
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
                            <div class="col-md-2">
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
                            <div class="col-md-2">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i> بحث
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <a href="{{ route('ecosystem.points.settings.index') }}" class="btn btn-outline-secondary btn-block">
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
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">إعدادات العملاء</div>
                <div class="stat-value">{{ $settings->where('user_type->value', 'client')->count() ?? 0 }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>نقاط العملاء</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-user"></i>
                </div>
                <div class="stat-label">إعدادات الموظفين</div>
                <div class="stat-value">{{ $settings->where('user_type->value', 'employee')->count() ?? 0 }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>نقاط الموظفين</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">إعدادات نشطة</div>
                <div class="stat-value">{{ $settings->where('is_active', true)->count() ?? 0 }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-award"></i>
                    <span>متاحة للاستخدام</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon">
                    <i class="feather icon-pause-circle"></i>
                </div>
                <div class="stat-label">إعدادات معطّلة</div>
                <div class="stat-value">{{ $settings->where('is_active', false)->count() ?? 0 }}</div>
                <div class="stat-trend">
                    <i class="feather icon-slash"></i>
                    <span>غير مستخدمة</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة إعدادات النقاط</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.settings.export') }}" class="btn btn-sm btn-outline-success">
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
                                <th>نوع المستخدم</th>
                                <th>الإجراء</th>
                                <th>طريقة الحساب</th>
                                <th>النقاط</th>
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
                                        <span class="badge badge-{{ $setting->user_type->value === 'client' ? 'primary' : 'success' }}">
                                            {{ $setting->user_type->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $setting->action_name }}</strong>
                                            <br><code class="text-muted">{{ $setting->action_type }}</code>
                                            @if($setting->description)
                                                <br><small class="text-muted">{{ Str::limit($setting->description, 40) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $calcType = $setting->calculation_type instanceof \App\Enums\CalculationType 
                                                ? $setting->calculation_type 
                                                : \App\Enums\CalculationType::tryFrom($setting->calculation_type ?? 'fixed');
                                        @endphp
                                        <span class="badge badge-light-{{ $calcType?->color() ?? 'primary' }}">
                                            <i class="feather {{ $calcType?->icon() ?? 'icon-hash' }}"></i>
                                            {{ $calcType?->label() ?? 'ثابت' }}
                                        </span>
                                        <br><small class="text-muted">{{ $setting->getCalculationDescription() }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $setting->isPositive() ? 'success' : 'danger' }}">
                                            {{ $setting->points > 0 ? '+' : '' }}{{ number_format($setting->points) }}
                                        </span>
                                        @if($setting->max_points)
                                            <br><small class="text-muted">حد أقصى: {{ $setting->max_points }}</small>
                                        @endif
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
                                              data-name="{{ $setting->action_name }}"
                                              data-status="{{ $setting->is_active ? 'active' : 'inactive' }}"
                                              @if(!$setting->is_active && $setting->disabled_reason) title="سبب التعطيل: {{ $setting->disabled_reason }}" @endif>
                                            <i class="feather icon-{{ $setting->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                            {{ $setting->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                        @if(!$setting->is_active && $setting->disabled_at)
                                            <br><small class="text-muted" title="{{ $setting->disabled_reason }}"><i class="feather icon-clock"></i> {{ $setting->disabled_at->format('m/d') }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.settings.show', $setting->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.settings.edit', $setting->id) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('ecosystem.points.settings.destroy', $setting->id) }}" method="POST" class="delete-form">
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
                                            <p class="mt-2 text-muted">لا توجد إعدادات لعرضها</p>
                                            <a href="{{ route('ecosystem.points.settings.create') }}" class="btn btn-primary mt-2">
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
                    url: '{{ route("ecosystem.points.settings.bulk-delete") }}',
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
        var settingId = btn.data('id');
        var settingName = btn.data('name');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 'active' ? 'inactive' : 'active';

        function doToggle(disabledReason) {
            $.ajax({
                url: '{{ url("ecosystem/points/settings") }}/' + settingId + '/toggle-status',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    disabled_reason: disabledReason || null
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        toastr.error(response.message || 'حدث خطأ أثناء التحديث');
                    }
                },
                error: function() {
                    toastr.error('حدث خطأ أثناء تحديث الحالة');
                }
            });
        }

        if (newStatus == 'inactive') {
            // عند التعطيل — اطلب السبب
            Swal.fire({
                title: `إيقاف الإعداد "${settingName}"؟`,
                input: 'textarea',
                inputLabel: 'سبب التعطيل (اختياري)',
                inputPlaceholder: 'اذكر سبب تعطيل هذا الإعداد...',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، أوقف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) doToggle(result.value);
            });
        } else {
            // عند التفعيل — تأكيد بسيط
            Swal.fire({
                title: `تفعيل الإعداد "${settingName}"؟`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، فعّل',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) doToggle(null);
            });
        }
    });

    // تأكيد الحذف
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
