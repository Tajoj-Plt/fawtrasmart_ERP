@extends('ecosystem::master')

@section('title', 'تفاصيل إعداد الإحالة')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل إعداد الإحالة</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.referral-settings.index') }}">إعدادات الإحالة</a></li>
                        <li class="breadcrumb-item active">تفاصيل الإعداد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12">
        <a href="{{ route('ecosystem.points.referral-settings.edit', $referral_setting->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="feather icon-edit-2"></i> تعديل
        </a>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-md-8">
            <!-- النقاط -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-gift"></i> إعدادات النقاط</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card-modern success" style="text-align: center;">
                                <div class="stat-icon"><i class="feather icon-arrow-up-right"></i></div>
                                <div class="stat-label">نقاط المُحيل</div>
                                <div class="stat-value">+{{ number_format($referral_setting->referrer_points) }}</div>
                                <div class="stat-trend up"><span>نقطة لكل إحالة ناجحة</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card-modern info" style="text-align: center;">
                                <div class="stat-icon"><i class="feather icon-user-plus"></i></div>
                                <div class="stat-label">نقاط المُحال إليه</div>
                                <div class="stat-value">+{{ number_format($referral_setting->referred_points) }}</div>
                                <div class="stat-trend up"><span>نقطة عند التأكيد</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- التفاصيل -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> التفاصيل</h4>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td class="font-weight-bold" style="width: 200px;">نوع التأكيد</td>
                                <td>
                                    <span class="badge badge-primary">{{ $referral_setting->confirmation_type->label() }}</span>
                                </td>
                            </tr>
                            @if($referral_setting->confirmation_value)
                            <tr>
                                <td class="font-weight-bold">قيمة التأكيد</td>
                                <td>
                                    {{ number_format($referral_setting->confirmation_value, 2) }}
                                    {{ $referral_setting->confirmation_type->value === 'days_active' ? 'يوم' : 'ريال' }}
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td class="font-weight-bold">الحد الأقصى شهرياً</td>
                                <td>
                                    @if($referral_setting->max_referrals_per_month)
                                        {{ $referral_setting->max_referrals_per_month }} إحالة
                                    @else
                                        <span class="text-muted">غير محدود</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الحد الأقصى الإجمالي</td>
                                <td>
                                    @if($referral_setting->max_referrals_total)
                                        {{ $referral_setting->max_referrals_total }} إحالة
                                    @else
                                        <span class="text-muted">غير محدود</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الفرع</td>
                                <td>
                                    @if($referral_setting->branch)
                                        <span class="badge badge-light-secondary">{{ $referral_setting->branch->name }}</span>
                                    @else
                                        جميع الفروع
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الحالة</td>
                                <td>
                                    <span class="badge badge-{{ $referral_setting->is_active ? 'success' : 'danger' }}">
                                        {{ $referral_setting->is_active ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الإنشاء</td>
                                <td>{{ $referral_setting->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">آخر تحديث</td>
                                <td>{{ $referral_setting->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- مكافآت المراحل -->
            @if($referral_setting->bonus_milestones && count($referral_setting->bonus_milestones) > 0)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-award"></i> مكافآت المراحل</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>عند إتمام</th>
                                    <th>نقاط إضافية</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($referral_setting->bonus_milestones as $index => $milestone)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ $milestone['count'] ?? 0 }} إحالة
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-success">
                                            +{{ number_format($milestone['points'] ?? 0) }} نقطة
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
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
                    <h4 class="card-title"><i class="feather icon-zap"></i> إجراءات سريعة</h4>
                </div>
                <div class="card-body">
                    <a href="{{ route('ecosystem.points.referral-settings.edit', $referral_setting->id) }}" 
                       class="btn btn-block btn-outline-primary mb-1">
                        <i class="feather icon-edit-2"></i> تعديل الإعداد
                    </a>
                    <form action="{{ route('ecosystem.points.referral-settings.destroy', $referral_setting->id) }}" 
                          method="POST" class="delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-block btn-outline-danger">
                            <i class="feather icon-trash-2"></i> حذف الإعداد
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
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
