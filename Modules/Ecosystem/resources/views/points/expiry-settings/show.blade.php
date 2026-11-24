@extends('ecosystem::master')

@section('title', 'تفاصيل إعداد انتهاء الصلاحية')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل إعداد انتهاء الصلاحية</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.expiry-settings.index') }}">إعدادات الانتهاء</a></li>
                        <li class="breadcrumb-item active">تفاصيل الإعداد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12">
        <a href="{{ route('ecosystem.points.expiry-settings.edit', $expiry_setting->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="feather icon-edit-2"></i> تعديل
        </a>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-md-8">
            <!-- ملخص سريع -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-clock"></i> ملخص الإعداد</h4>
                </div>
                <div class="card-body">
                    <div class="stats-row">
                        <div class="stat-card-modern {{ $expiry_setting->user_type === \App\Enums\UserType::EMPLOYEE ? 'primary' : 'info' }}" style="text-align: center;">
                            <div class="stat-icon">
                                <i class="feather icon-{{ $expiry_setting->user_type === \App\Enums\UserType::EMPLOYEE ? 'briefcase' : 'shopping-bag' }}"></i>
                            </div>
                            <div class="stat-label">نوع المستخدم</div>
                            <div class="stat-value" style="font-size: 1.2rem;">{{ $expiry_setting->user_type->label() }}</div>
                        </div>

                        <div class="stat-card-modern warning" style="text-align: center;">
                            <div class="stat-icon">
                                <i class="feather icon-clock"></i>
                            </div>
                            <div class="stat-label">نوع الانتهاء</div>
                            <div class="stat-value" style="font-size: 1.2rem;">{{ $expiry_setting->expiry_type->label() }}</div>
                        </div>

                        <div class="stat-card-modern {{ $expiry_setting->is_active ? 'success' : 'danger' }}" style="text-align: center;">
                            <div class="stat-icon">
                                <i class="feather icon-{{ $expiry_setting->is_active ? 'check-circle' : 'x-circle' }}"></i>
                            </div>
                            <div class="stat-label">الحالة</div>
                            <div class="stat-value" style="font-size: 1.2rem;">{{ $expiry_setting->is_active ? 'نشط' : 'غير نشط' }}</div>
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
                                <td class="font-weight-bold" style="width: 200px;">نوع المستخدم</td>
                                <td>
                                    <span class="badge badge-{{ $expiry_setting->user_type === \App\Enums\UserType::EMPLOYEE ? 'primary' : 'info' }}">
                                        {{ $expiry_setting->user_type->label() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">نوع الانتهاء</td>
                                <td>
                                    <span class="badge badge-warning">{{ $expiry_setting->expiry_type->label() }}</span>
                                </td>
                            </tr>

                            @if($expiry_setting->expiry_type === \App\Enums\ExpiryType::FIFO)
                            <tr>
                                <td class="font-weight-bold">مدة الصلاحية</td>
                                <td>
                                    <strong>{{ $expiry_setting->expiry_days }}</strong> يوم من تاريخ الكسب
                                </td>
                            </tr>
                            @elseif(in_array($expiry_setting->expiry_type, [\App\Enums\ExpiryType::FIXED_DATE, \App\Enums\ExpiryType::ANNIVERSARY]))
                            <tr>
                                <td class="font-weight-bold">تاريخ الانتهاء</td>
                                <td>
                                    {{ $expiry_setting->expiry_day }} / {{ \Carbon\Carbon::create(null, $expiry_setting->expiry_month)->translatedFormat('F') }}
                                    @if($expiry_setting->expiry_type === \App\Enums\ExpiryType::ANNIVERSARY)
                                        <small class="text-muted">(من ذكرى الانضمام)</small>
                                    @else
                                        <small class="text-muted">(كل عام)</small>
                                    @endif
                                </td>
                            </tr>
                            @endif

                            <tr>
                                <td class="font-weight-bold">أيام التحذير</td>
                                <td>
                                    @if($expiry_setting->warning_days)
                                        <span class="badge badge-light-warning">
                                            <i class="feather icon-alert-triangle"></i>
                                            {{ $expiry_setting->warning_days }} يوم قبل الانتهاء
                                        </span>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">فترة السماح</td>
                                <td>
                                    @if($expiry_setting->grace_period_days)
                                        <span class="badge badge-light-info">
                                            <i class="feather icon-shield"></i>
                                            {{ $expiry_setting->grace_period_days }} يوم إضافية
                                        </span>
                                    @else
                                        <span class="text-muted">بدون فترة سماح</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">أيام التذكير</td>
                                <td>
                                    @if($expiry_setting->reminder_days && count($expiry_setting->reminder_days) > 0)
                                        @foreach($expiry_setting->reminder_days as $day)
                                            <span class="badge badge-light-primary mr-1">{{ $day }} يوم</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الفرع</td>
                                <td>
                                    @if($expiry_setting->branch)
                                        <span class="badge badge-light-secondary">{{ $expiry_setting->branch->name }}</span>
                                    @else
                                        جميع الفروع
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">الحالة</td>
                                <td>
                                    <span class="badge badge-{{ $expiry_setting->is_active ? 'success' : 'danger' }}">
                                        {{ $expiry_setting->is_active ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الإنشاء</td>
                                <td>{{ $expiry_setting->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">آخر تحديث</td>
                                <td>{{ $expiry_setting->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- المستويات المستثناة -->
            @if($expiry_setting->exclude_tiers && count($expiry_setting->exclude_tiers) > 0)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-shield"></i> المستويات المستثناة</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">هذه المستويات لا تنطبق عليها سياسة الانتهاء - نقاطهم لا تنتهي</p>
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المستوى</th>
                                    <th>الحد الأدنى للنقاط</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $excludedTiers = \App\Models\Ecosystem\PointsTier::whereIn('id', $expiry_setting->exclude_tiers)->get();
                                @endphp
                                @foreach($excludedTiers as $index => $tier)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ $tier->name }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($tier->min_points) }} نقطة</td>
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
            <!-- شرح نوع الانتهاء -->
            <div class="card bg-light-warning">
                <div class="card-body">
                    <h6><i class="feather icon-clock"></i> كيف يعمل {{ $expiry_setting->expiry_type->label() }}؟</h6>
                    <div style="font-size: 0.85rem;">
                        @switch($expiry_setting->expiry_type)
                            @case(\App\Enums\ExpiryType::FIFO)
                                <p>عند كسب النقاط، تُنشأ <strong>دفعة</strong> لكل معاملة. كل دفعة تنتهي صلاحيتها بعد <strong>{{ $expiry_setting->expiry_days }} يوم</strong> من تاريخ كسبها.</p>
                                <p>عند الاستبدال، يتم خصم النقاط من <strong>الدفعات الأقدم أولاً</strong> (First In, First Out).</p>
                                @break
                            @case(\App\Enums\ExpiryType::FIXED_DATE)
                                <p>جميع النقاط المكتسبة تنتهي في <strong>{{ $expiry_setting->expiry_day }} / {{ \Carbon\Carbon::create(null, $expiry_setting->expiry_month)->translatedFormat('F') }}</strong> من كل عام.</p>
                                <p>مناسب للبرامج الموسمية التي تُجدد سنوياً.</p>
                                @break
                            @case(\App\Enums\ExpiryType::ANNIVERSARY)
                                <p>النقاط تنتهي في <strong>ذكرى انضمام</strong> كل مستخدم. كل مستخدم له تاريخ انتهاء مختلف.</p>
                                <p>مناسب للبرامج المخصصة لكل عميل.</p>
                                @break
                            @case(\App\Enums\ExpiryType::NEVER)
                                <p>النقاط <strong>لا تنتهي أبداً</strong> وتبقى صالحة ما لم يتم استبدالها.</p>
                                <p>مناسب للمستويات العليا أو برامج الولاء الخاصة.</p>
                                @break
                        @endswitch
                    </div>
                </div>
            </div>

            <!-- الإجراءات -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-zap"></i> إجراءات سريعة</h4>
                </div>
                <div class="card-body">
                    <a href="{{ route('ecosystem.points.expiry-settings.edit', $expiry_setting->id) }}"
                       class="btn btn-block btn-outline-primary mb-1">
                        <i class="feather icon-edit-2"></i> تعديل الإعداد
                    </a>
                    <a href="{{ route('ecosystem.points.batches.index') }}"
                       class="btn btn-block btn-outline-info mb-1">
                        <i class="feather icon-package"></i> عرض الدفعات
                    </a>
                    <form action="{{ route('ecosystem.points.expiry-settings.destroy', $expiry_setting->id) }}"
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
