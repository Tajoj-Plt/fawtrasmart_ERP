@extends('ecosystem::master')

@section('title', 'تفاصيل الإحالة')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل الإحالة</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.referrals.index') }}">إدارة الإحالات</a></li>
                        <li class="breadcrumb-item active">تفاصيل الإحالة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12">
        <a href="{{ route('ecosystem.points.referrals.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
            <i class="feather icon-arrow-right"></i> العودة للقائمة
        </a>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-md-8">
            <!-- معلومات الإحالة -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> معلومات الإحالة</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6 text-center">
                            <div class="stat-card-modern success" style="text-align: center;">
                                <div class="stat-icon"><i class="feather icon-arrow-up-right"></i></div>
                                <div class="stat-label">نقاط المُحيل</div>
                                <div class="stat-value">{{ $referral->referrer_points ? '+' . number_format($referral->referrer_points) : '0' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="stat-card-modern info" style="text-align: center;">
                                <div class="stat-icon"><i class="feather icon-user-plus"></i></div>
                                <div class="stat-label">نقاط المُحال إليه</div>
                                <div class="stat-value">{{ $referral->referred_points ? '+' . number_format($referral->referred_points) : '0' }}</div>
                            </div>
                        </div>
                    </div>

                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td class="font-weight-bold" style="width: 200px;">كود الإحالة</td>
                                <td><code class="text-primary" style="font-size: 1.1rem;">{{ $referral->referral_code }}</code></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الحالة</td>
                                <td>
                                    <span class="badge badge-{{ $referral->status->color() }}" style="font-size: 0.9rem;">
                                        {{ $referral->status->label() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الإنشاء</td>
                                <td>{{ $referral->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                            @if($referral->confirmed_at)
                            <tr>
                                <td class="font-weight-bold">تاريخ التأكيد</td>
                                <td>
                                    <i class="feather icon-check text-success"></i>
                                    {{ $referral->confirmed_at->format('Y-m-d H:i:s') }}
                                </td>
                            </tr>
                            @endif
                            @if($referral->cancelled_at)
                            <tr>
                                <td class="font-weight-bold">تاريخ الإلغاء</td>
                                <td>
                                    <i class="feather icon-x text-danger"></i>
                                    {{ $referral->cancelled_at->format('Y-m-d H:i:s') }}
                                </td>
                            </tr>
                            @endif
                            @if($referral->cancellation_reason)
                            <tr>
                                <td class="font-weight-bold">سبب الإلغاء</td>
                                <td>{{ $referral->cancellation_reason }}</td>
                            </tr>
                            @endif
                            @if($referral->expires_at)
                            <tr>
                                <td class="font-weight-bold">تاريخ الانتهاء</td>
                                <td>
                                    {{ $referral->expires_at->format('Y-m-d H:i') }}
                                    @if($referral->expires_at->isPast())
                                        <span class="badge badge-danger">منتهي</span>
                                    @else
                                        <span class="badge badge-success">ساري</span>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- المُحيل والمُحال -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-user"></i> المُحيل</h4>
                        </div>
                        <div class="card-body">
                            @if($referral->referrer)
                                <div class="text-center mb-2">
                                    <div class="avatar avatar-lg bg-light-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <span style="font-size: 1.5rem;">{{ mb_substr($referral->referrer->name, 0, 1) }}</span>
                                    </div>
                                </div>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted">الاسم:</td>
                                        <td><strong>{{ $referral->referrer->name }}</strong></td>
                                    </tr>
                                    @if($referral->referrer->phone)
                                    <tr>
                                        <td class="text-muted">الهاتف:</td>
                                        <td>{{ $referral->referrer->phone }}</td>
                                    </tr>
                                    @endif
                                    @if($referral->referrer->email)
                                    <tr>
                                        <td class="text-muted">البريد:</td>
                                        <td>{{ $referral->referrer->email }}</td>
                                    </tr>
                                    @endif
                                </table>
                            @else
                                <p class="text-muted text-center">بيانات المُحيل غير متوفرة</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-user-plus"></i> المُحال إليه</h4>
                        </div>
                        <div class="card-body">
                            @if($referral->referred)
                                <div class="text-center mb-2">
                                    <div class="avatar avatar-lg bg-light-info rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <span style="font-size: 1.5rem;">{{ mb_substr($referral->referred->name, 0, 1) }}</span>
                                    </div>
                                </div>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted">الاسم:</td>
                                        <td><strong>{{ $referral->referred->name }}</strong></td>
                                    </tr>
                                    @if($referral->referred->phone)
                                    <tr>
                                        <td class="text-muted">الهاتف:</td>
                                        <td>{{ $referral->referred->phone }}</td>
                                    </tr>
                                    @endif
                                    @if($referral->referred->email)
                                    <tr>
                                        <td class="text-muted">البريد:</td>
                                        <td>{{ $referral->referred->email }}</td>
                                    </tr>
                                    @endif
                                </table>
                            @else
                                <p class="text-muted text-center">بيانات المُحال إليه غير متوفرة</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- معاملات النقاط المرتبطة -->
            @if($referral->referrerTransaction || $referral->referredTransaction)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-credit-card"></i> معاملات النقاط المرتبطة</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>المستفيد</th>
                                    <th>النقاط</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($referral->referrerTransaction)
                                <tr>
                                    <td><span class="badge badge-success">نقاط المُحيل</span></td>
                                    <td>{{ $referral->referrer?->name ?? '-' }}</td>
                                    <td><span class="badge badge-light-success">+{{ number_format($referral->referrerTransaction->points ?? 0) }}</span></td>
                                    <td>{{ $referral->referrerTransaction->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                                @endif
                                @if($referral->referredTransaction)
                                <tr>
                                    <td><span class="badge badge-info">نقاط المُحال</span></td>
                                    <td>{{ $referral->referred?->name ?? '-' }}</td>
                                    <td><span class="badge badge-light-info">+{{ number_format($referral->referredTransaction->points ?? 0) }}</span></td>
                                    <td>{{ $referral->referredTransaction->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- الإجراءات -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-zap"></i> إجراءات</h4>
                </div>
                <div class="card-body">
                    @if($referral->isPending())
                        <button class="btn btn-block btn-success mb-1 confirm-referral" data-id="{{ $referral->id }}">
                            <i class="feather icon-check-circle"></i> تأكيد الإحالة
                        </button>
                        <button class="btn btn-block btn-outline-danger mb-1 cancel-referral" data-id="{{ $referral->id }}">
                            <i class="feather icon-x-circle"></i> إلغاء الإحالة
                        </button>
                    @elseif($referral->isConfirmed())
                        <div class="alert alert-success text-center">
                            <i class="feather icon-check-circle"></i>
                            <br>هذه الإحالة مؤكدة
                        </div>
                        <button class="btn btn-block btn-outline-danger mb-1 cancel-referral" data-id="{{ $referral->id }}">
                            <i class="feather icon-x-circle"></i> إلغاء الإحالة
                        </button>
                    @else
                        <div class="alert alert-danger text-center">
                            <i class="feather icon-x-circle"></i>
                            <br>هذه الإحالة ملغية
                        </div>
                    @endif
                </div>
            </div>

            <!-- البيانات الإضافية -->
            @if($referral->metadata && count($referral->metadata) > 0)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-database"></i> بيانات إضافية</h4>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        @foreach($referral->metadata as $key => $value)
                        <tr>
                            <td class="text-muted">{{ $key }}:</td>
                            <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
            @endif

            <!-- الجدول الزمني -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-clock"></i> الجدول الزمني</h4>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled timeline-modern">
                        <li class="mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-primary mr-1">
                                    <i class="feather icon-plus-circle"></i>
                                </span>
                                <div>
                                    <strong>تم إنشاء الإحالة</strong>
                                    <br><small class="text-muted">{{ $referral->created_at->format('Y-m-d H:i:s') }}</small>
                                </div>
                            </div>
                        </li>
                        @if($referral->confirmed_at)
                        <li class="mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-success mr-1">
                                    <i class="feather icon-check-circle"></i>
                                </span>
                                <div>
                                    <strong>تم تأكيد الإحالة</strong>
                                    <br><small class="text-muted">{{ $referral->confirmed_at->format('Y-m-d H:i:s') }}</small>
                                </div>
                            </div>
                        </li>
                        @endif
                        @if($referral->cancelled_at)
                        <li class="mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge badge-danger mr-1">
                                    <i class="feather icon-x-circle"></i>
                                </span>
                                <div>
                                    <strong>تم إلغاء الإحالة</strong>
                                    <br><small class="text-muted">{{ $referral->cancelled_at->format('Y-m-d H:i:s') }}</small>
                                    @if($referral->cancellation_reason)
                                        <br><small class="text-muted">السبب: {{ $referral->cancellation_reason }}</small>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
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
