@extends('ecosystem::master')

@section('title', 'إدارة الإحالات')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إدارة الإحالات</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">إدارة الإحالات</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.referrals.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="كود الإحالة أو اسم العميل..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach(\App\Enums\ReferralStatus::cases() as $status)
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>معرف المُحال</label>
                                    <input type="number" name="referred_id" class="form-control" 
                                           placeholder="رقم المُحال..." value="{{ request('referred_id') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> بحث
                                    </button>
                                    <a href="{{ route('ecosystem.points.referrals.index') }}" class="btn btn-outline-secondary">
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
                <div class="stat-label">إجمالي الإحالات</div>
                <div class="stat-value">{{ number_format($totalReferrals) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>إحالة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-label">إحالات معلقة</div>
                <div class="stat-value">{{ number_format($pendingCount) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-alert-circle"></i>
                    <span>بانتظار التأكيد</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">إحالات مؤكدة</div>
                <div class="stat-value">{{ number_format($confirmedCount) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-thumbs-up"></i>
                    <span>إحالة ناجحة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-award"></i>
                </div>
                <div class="stat-label">إجمالي النقاط الممنوحة</div>
                <div class="stat-value">{{ number_format($totalPointsAwarded) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-star"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> سجل الإحالات</h4>
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="feather icon-trash"></i> حذف المحدد
                    </button>
                    <a href="{{ route('ecosystem.points.referrals.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $referrals->total() }} إحالة</span>
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
                                <th>كود الإحالة</th>
                                <th>المُحيل</th>
                                <th>المُحال إليه</th>
                                <th>النقاط</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($referrals as $referral)
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input row-checkbox"
                                                   id="row{{ $referral->id }}" value="{{ $referral->id }}">
                                            <label class="custom-control-label" for="row{{ $referral->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="text-primary">{{ $referral->referral_code }}</code>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $referral->referrer?->name ?? '-' }}</strong>
                                            @if($referral->referrer?->phone)
                                                <br><small class="text-muted">{{ $referral->referrer->phone }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $referral->referred?->name ?? '-' }}</strong>
                                            @if($referral->referred?->phone)
                                                <br><small class="text-muted">{{ $referral->referred->phone }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            @if($referral->referrer_points)
                                                <span class="badge badge-light-success" title="نقاط المُحيل">
                                                    <i class="feather icon-arrow-up"></i> +{{ $referral->referrer_points }}
                                                </span>
                                            @endif
                                            @if($referral->referred_points)
                                                <span class="badge badge-light-info" title="نقاط المُحال">
                                                    <i class="feather icon-user-plus"></i> +{{ $referral->referred_points }}
                                                </span>
                                            @endif
                                            @if(!$referral->referrer_points && !$referral->referred_points)
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $referral->status->color() }}">
                                            {{ $referral->status->label() }}
                                        </span>
                                        @if($referral->confirmed_at)
                                            <br><small class="text-muted">{{ $referral->confirmed_at->format('m/d H:i') }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $referral->created_at->format('Y-m-d') }}</small>
                                        <br><small class="text-muted">{{ $referral->created_at->format('H:i') }}</small>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.referrals.show', $referral->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                @if($referral->isPending())
                                                    <a class="dropdown-item text-success confirm-referral" href="#"
                                                       data-id="{{ $referral->id }}">
                                                        <i class="feather icon-check-circle"></i> تأكيد الإحالة
                                                    </a>
                                                @endif
                                                @if(!$referral->isCancelled())
                                                    <a class="dropdown-item text-danger cancel-referral" href="#"
                                                       data-id="{{ $referral->id }}">
                                                        <i class="feather icon-x-circle"></i> إلغاء الإحالة
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد إحالات لعرضها</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $referrals, 'label' => 'إحالة'])
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
            text: 'سيتم حذف ' + ids.length + ' إحالة نهائياً!',
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
                    url: '{{ route("ecosystem.points.referrals.bulk-delete") }}',
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

    // Confirm referral
    $('.confirm-referral').on('click', function(e) {
        e.preventDefault();
        var referralId = $(this).data('id');

        Swal.fire({
            title: 'تأكيد الإحالة',
            text: 'هل تريد تأكيد هذه الإحالة ومنح النقاط؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، أكد',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/referrals") }}/' + referralId + '/confirm',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'حدث خطأ أثناء التأكيد';
                        toastr.error(msg);
                    }
                });
            }
        });
    });

    // Cancel referral
    $('.cancel-referral').on('click', function(e) {
        e.preventDefault();
        var referralId = $(this).data('id');

        Swal.fire({
            title: 'إلغاء الإحالة',
            text: 'هل تريد إلغاء هذه الإحالة؟',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'سبب الإلغاء (اختياري)',
            inputPlaceholder: 'اكتب سبب الإلغاء...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، ألغِ',
            cancelButtonText: 'تراجع',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("ecosystem/points/referrals") }}/' + referralId + '/cancel',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        reason: result.value
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'حدث خطأ أثناء الإلغاء';
                        toastr.error(msg);
                    }
                });
            }
        });
    });
});
</script>
@endsection
