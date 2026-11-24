@extends('ecosystem::master')

@section('title', 'طلبات استبدال المكافآت')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">طلبات استبدال المكافآت</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.rewards.index') }}">المكافآت</a></li>
                            <li class="breadcrumb-item active">طلبات الاستبدال</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-3 col-12">
            <a href="{{ route('ecosystem.points.redemptions.verify-voucher') }}" class="btn btn-outline-info waves-effect waves-light mr-1">
                <i class="feather icon-tag"></i> التحقق من قسيمة
            </a>
            <a href="{{ route('ecosystem.points.redemptions.create') }}" class="btn btn-primary dropdown-toggle waves-effect waves-light">
                <i class="feather icon-plus"></i> طلب استبدال جديد
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
                    <form method="GET" action="{{ route('ecosystem.points.redemptions.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control"
                                           placeholder="رقم الطلب، كود القسيمة..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach(\App\Enums\RedemptionStatus::cases() as $status)
                                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                                {{ $status->label() }}
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
                                        <option value="employee" {{ request('user_type') == 'employee' ? 'selected' : '' }}>موظف</option>
                                        <option value="client" {{ request('user_type') == 'client' ? 'selected' : '' }}>عميل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>المكافأة</label>
                                    <select name="reward_id" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($rewards as $reward)
                                            <option value="{{ $reward->id }}" {{ request('reward_id') == $reward->id ? 'selected' : '' }}>
                                                {{ $reward->name }}
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.redemptions.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-list"></i>
                </div>
                <div class="stat-label">إجمالي الطلبات</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>طلب</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-label">طلبات معلقة</div>
                <div class="stat-value">{{ number_format($stats['pending']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-alert-circle"></i>
                    <span>بانتظار الموافقة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-check"></i>
                </div>
                <div class="stat-label">موافق عليها</div>
                <div class="stat-value">{{ number_format($stats['approved']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-thumbs-up"></i>
                    <span>تمت الموافقة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">تم التسليم</div>
                <div class="stat-value">{{ number_format($stats['delivered']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-package"></i>
                    <span>مسلّمة</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon">
                    <i class="feather icon-x-circle"></i>
                </div>
                <div class="stat-label">ملغاة</div>
                <div class="stat-value">{{ number_format($stats['cancelled']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-slash"></i>
                    <span>ملغاة</span>
                </div>
            </div>
        </div>

        <!-- جدول الطلبات -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-repeat"></i> قائمة طلبات الاستبدال</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkCancelBtn" style="display: none;">
                        <i class="feather icon-x-circle"></i> إلغاء المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.redemptions.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $redemptions->total() }} طلب</span>
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
                                <th width="10%">رقم الطلب</th>
                                <th width="15%">المكافأة</th>
                                <th width="15%">المستفيد</th>
                                <th width="5%">الكمية</th>
                                <th width="8%">القيمة</th>
                                <th width="10%">الحالة</th>
                                <th width="10%">كود القسيمة</th>
                                <th width="10%">التاريخ</th>
                                <th width="7%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($redemptions as $redemption)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="check-{{ $redemption->id }}" value="{{ $redemption->id }}">
                                            <label class="custom-control-label" for="check-{{ $redemption->id }}"></label>
                                        </div>
                                    </td>
                                    <td>{{ $loop->iteration + ($redemptions->currentPage() - 1) * $redemptions->perPage() }}</td>
                                    <td>
                                        <a href="{{ route('ecosystem.points.redemptions.show', $redemption) }}" class="text-primary font-weight-bold">
                                            {{ $redemption->redemption_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($redemption->reward)
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 30px; height: 30px; background: #f0f0f0;">
                                                    <span style="font-size: 14px;">{{ $redemption->reward->reward_type->icon() }}</span>
                                                </div>
                                                <div>
                                                    <strong>{{ $redemption->reward->name }}</strong>
                                                    <br><small class="text-muted">{{ $redemption->reward->reward_type->label() }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">محذوفة</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($redemption->user_type->value === 'employee')
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 30px; height: 30px; background: #e8f5e9;">
                                                    <i class="feather icon-briefcase text-warning" style="font-size: 13px;"></i>
                                                </div>
                                                <div>
                                                    <strong>{{ $redemption->employee ? $redemption->employee->full_name : 'غير معروف' }}</strong>
                                                    <br><small class="text-muted">موظف</small>
                                                </div>
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center"
                                                     style="width: 30px; height: 30px; background: #e3f2fd;">
                                                    <i class="feather icon-user text-info" style="font-size: 13px;"></i>
                                                </div>
                                                <div>
                                                    <strong>{{ $redemption->client ? $redemption->client->full_name : 'غير معروف' }}</strong>
                                                    <br><small class="text-muted">عميل</small>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">{{ $redemption->quantity }}</span>
                                    </td>
                                    <td>
                                        @if($redemption->reward_value)
                                            <strong>{{ number_format($redemption->reward_value, 2) }}</strong>
                                            @if($redemption->reward && $redemption->reward->reward_type->value === 'discount_percentage')
                                                <small class="text-muted">%</small>
                                            @else
                                                <small class="text-muted">ر.س</small>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $redemption->status->color() }}">
                                            {{ $redemption->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($redemption->voucher_code)
                                            <code class="bg-light p-1 rounded">{{ $redemption->voucher_code }}</code>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $redemption->created_at->format('Y/m/d') }}</small>
                                        <br><small class="text-muted">{{ $redemption->created_at->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.redemptions.show', $redemption) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض التفاصيل
                                                </a>

                                                @if($redemption->isPending())
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-success approve-action"
                                                       href="javascript:void(0);"
                                                       data-id="{{ $redemption->id }}">
                                                        <i class="feather icon-check mr-1"></i> موافقة
                                                    </a>
                                                @endif

                                                @if($redemption->isApproved())
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-info process-action"
                                                       href="javascript:void(0);"
                                                       data-id="{{ $redemption->id }}">
                                                        <i class="feather icon-settings mr-1"></i> بدء التجهيز
                                                    </a>
                                                    <a class="dropdown-item text-primary deliver-action"
                                                       href="javascript:void(0);"
                                                       data-id="{{ $redemption->id }}">
                                                        <i class="feather icon-package mr-1"></i> تسليم
                                                    </a>
                                                @endif

                                                @if($redemption->status->value === \App\Enums\RedemptionStatus::PROCESSING->value)
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-primary deliver-action"
                                                       href="javascript:void(0);"
                                                       data-id="{{ $redemption->id }}">
                                                        <i class="feather icon-package mr-1"></i> تسليم
                                                    </a>
                                                @endif

                                                @if(!$redemption->isCancelled())
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger cancel-action"
                                                       href="javascript:void(0);"
                                                       data-id="{{ $redemption->id }}"
                                                       data-number="{{ $redemption->redemption_number }}">
                                                        <i class="feather icon-x-circle mr-1"></i> إلغاء
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="feather icon-inbox font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد طلبات استبدال</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $redemptions, 'label' => 'طلب'])
            </div>
        </div>
    </div>

    <!-- Modal إلغاء الطلب -->
    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="cancelForm" method="POST">
                    @csrf
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="feather icon-x-circle"></i> إلغاء طلب الاستبدال
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle"></i>
                            سيتم إلغاء الطلب <strong id="cancelNumber"></strong> واسترجاع النقاط المخصومة.
                        </div>
                        <div class="form-group">
                            <label for="cancellation_reason">سبب الإلغاء <span class="text-danger">*</span></label>
                            <textarea name="cancellation_reason" id="cancellation_reason"
                                      class="form-control" rows="3" required
                                      placeholder="أدخل سبب الإلغاء..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">تراجع</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-x-circle"></i> تأكيد الإلغاء
                        </button>
                    </div>
                </form>
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
        toggleBulkBtn();
    });

    // Individual checkbox
    $('.row-checkbox').on('change', function() {
        toggleBulkBtn();
        if (!$(this).prop('checked')) {
            $('#selectAll').prop('checked', false);
        }
    });

    // Toggle bulk button
    function toggleBulkBtn() {
        const checkedCount = $('.row-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkCancelBtn').show();
        } else {
            $('#bulkCancelBtn').hide();
        }
    }

    // الموافقة على الطلب
    $('.approve-action').click(function() {
        var redemptionId = $(this).data('id');

        Swal.fire({
            title: 'الموافقة على الطلب؟',
            text: 'سيتم إنشاء القيد المحاسبي عند الموافقة (ماعدا الهدايا/المنتجات تُنشأ عند التسليم)',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28c76f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، وافق',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = $('<form>', {
                    method: 'POST',
                    action: '{{ url("ecosystem/points/redemptions") }}/' + redemptionId + '/approve'
                });
                form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                form.appendTo('body').submit();
            }
        });
    });

    // بدء التجهيز
    $('.process-action').click(function() {
        var redemptionId = $(this).data('id');

        Swal.fire({
            title: 'بدء تجهيز الطلب؟',
            text: 'سيتم تحديث حالة الطلب إلى قيد التجهيز',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#00cfe8',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، ابدأ التجهيز',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = $('<form>', {
                    method: 'POST',
                    action: '{{ url("ecosystem/points/redemptions") }}/' + redemptionId + '/process'
                });
                form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                form.appendTo('body').submit();
            }
        });
    });

    // تسليم المكافأة
    $('.deliver-action').click(function() {
        var redemptionId = $(this).data('id');

        Swal.fire({
            title: 'تسليم المكافأة؟',
            text: 'سيتم تسليم المكافأة للمستفيد. إذا كان المنتج هدية/منتج سيتم خصم المخزون وإنشاء القيد المحاسبي.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، تسليم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                var form = $('<form>', {
                    method: 'POST',
                    action: '{{ url("ecosystem/points/redemptions") }}/' + redemptionId + '/deliver'
                });
                form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                form.appendTo('body').submit();
            }
        });
    });

    // إلغاء الطلب - فتح Modal
    $('.cancel-action').click(function() {
        var id = $(this).data('id');
        var number = $(this).data('number');
        $('#cancelNumber').text(number);
        $('#cancelForm').attr('action', '{{ url("ecosystem/points/redemptions") }}/' + id + '/cancel');
        $('#cancelModal').modal('show');
    });

    // إلغاء مجمع
    $('#bulkCancelBtn').on('click', function() {
        const ids = $('.row-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم إلغاء ' + ids.length + ' طلب وإرجاع النقاط!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، ألغِ الطلبات',
            cancelButtonText: 'تراجع',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $('#cancelNumber').text(ids.length + ' طلب');
                $('#cancelForm').attr('action', '{{ url("ecosystem/points/redemptions") }}/' + ids[0] + '/cancel');
                $('#cancelForm').data('bulk-ids', ids);
                $('#cancelModal').modal('show');
            }
        });
    });
});
</script>
@endsection
