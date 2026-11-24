@extends('ecosystem::master')

@section('title', 'تفاصيل دفعة النقاط')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تفاصيل دفعة النقاط #{{ $batch->id }}</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.batches.index') }}">دفعات النقاط</a></li>
                        <li class="breadcrumb-item active">دفعة #{{ $batch->id }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content-header-right text-md-right col-md-3 col-12">
        <a href="{{ route('ecosystem.points.batches.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
            <i class="feather icon-arrow-right"></i> العودة للقائمة
        </a>
    </div>
</div>

<div class="content-body">
    @php
        $daysLeft = $batch->getDaysUntilExpiry();
    @endphp
    
    <div class="row">
        <div class="col-md-8">
            <!-- ملخص النقاط -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-package"></i> ملخص الدفعة</h4>
                </div>
                <div class="card-body">
                    <div class="stats-row">
                        <div class="stat-card-modern primary" style="text-align: center;">
                            <div class="stat-icon"><i class="feather icon-star"></i></div>
                            <div class="stat-label">النقاط الأصلية</div>
                            <div class="stat-value">{{ number_format($batch->original_points) }}</div>
                        </div>

                        <div class="stat-card-modern success" style="text-align: center;">
                            <div class="stat-icon"><i class="feather icon-check-circle"></i></div>
                            <div class="stat-label">المتبقي</div>
                            <div class="stat-value">{{ number_format($batch->remaining_points) }}</div>
                        </div>

                        <div class="stat-card-modern info" style="text-align: center;">
                            <div class="stat-icon"><i class="feather icon-shopping-cart"></i></div>
                            <div class="stat-label">المستخدم</div>
                            <div class="stat-value">{{ number_format($batch->used_points) }}</div>
                        </div>

                        <div class="stat-card-modern danger" style="text-align: center;">
                            <div class="stat-icon"><i class="feather icon-x-circle"></i></div>
                            <div class="stat-label">المنتهي</div>
                            <div class="stat-value">{{ number_format($batch->expired_points) }}</div>
                        </div>
                    </div>

                    <!-- شريط التقدم -->
                    @php
                        $usedPercentage = $batch->original_points > 0 ? ($batch->used_points / $batch->original_points) * 100 : 0;
                        $expiredPercentage = $batch->original_points > 0 ? ($batch->expired_points / $batch->original_points) * 100 : 0;
                        $remainingPercentage = $batch->original_points > 0 ? ($batch->remaining_points / $batch->original_points) * 100 : 0;
                    @endphp
                    <div class="mt-3">
                        <p class="mb-1"><strong>توزيع النقاط</strong></p>
                        <div class="progress" style="height: 20px; border-radius: 10px;">
                            <div class="progress-bar bg-info" style="width: {{ $usedPercentage }}%" 
                                 title="مستخدمة: {{ number_format($batch->used_points) }}">
                                @if($usedPercentage > 10){{ round($usedPercentage) }}%@endif
                            </div>
                            <div class="progress-bar bg-danger" style="width: {{ $expiredPercentage }}%"
                                 title="منتهية: {{ number_format($batch->expired_points) }}">
                                @if($expiredPercentage > 10){{ round($expiredPercentage) }}%@endif
                            </div>
                            <div class="progress-bar bg-success" style="width: {{ $remainingPercentage }}%"
                                 title="متبقية: {{ number_format($batch->remaining_points) }}">
                                @if($remainingPercentage > 10){{ round($remainingPercentage) }}%@endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-1" style="font-size: 0.8rem;">
                            <span class="text-info"><i class="fa fa-circle"></i> مستخدمة ({{ round($usedPercentage) }}%)</span>
                            <span class="text-danger"><i class="fa fa-circle"></i> منتهية ({{ round($expiredPercentage) }}%)</span>
                            <span class="text-success"><i class="fa fa-circle"></i> متبقية ({{ round($remainingPercentage) }}%)</span>
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
                                <td class="font-weight-bold" style="width: 200px;">رقم الدفعة</td>
                                <td>#{{ $batch->id }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">المستخدم</td>
                                <td>
                                    <span class="badge badge-light-{{ $batch->user_type === \App\Enums\UserType::EMPLOYEE ? 'primary' : 'info' }}">
                                        {{ $batch->user_type->label() }}
                                    </span>
                                    -
                                    @if($batch->user_type === \App\Enums\UserType::EMPLOYEE)
                                        <strong>{{ $batch->employee?->full_name ?? 'غير معروف' }}</strong>
                                    @else
                                        <strong>{{ $batch->client?->full_name ?: ($batch->client?->trade_name ?? 'غير معروف') }}</strong>
                                        @if($batch->client?->phone)
                                            <small class="text-muted">({{ $batch->client->phone }})</small>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">المعاملة المرتبطة</td>
                                <td>
                                    @if($batch->transaction)
                                        <a href="{{ route('ecosystem.points.transactions.show', $batch->transaction_id) }}">
                                            معاملة #{{ $batch->transaction_id }}
                                        </a>
                                        <small class="text-muted">({{ $batch->transaction->description ?? '' }})</small>
                                    @else
                                        <span class="text-muted">غير مرتبطة</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الكسب</td>
                                <td>{{ $batch->earned_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الانتهاء</td>
                                <td>
                                    @if($batch->expires_at)
                                        {{ $batch->expires_at->format('Y-m-d H:i') }}
                                        @if($batch->status === \App\Enums\BatchStatus::ACTIVE && $daysLeft !== null)
                                            <br>
                                            <span class="badge badge-{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 30 ? 'warning' : 'info') }}">
                                                @if($daysLeft == 0)
                                                    تنتهي اليوم!
                                                @elseif($daysLeft == 1)
                                                    تنتهي غداً
                                                @else
                                                    {{ $daysLeft }} يوم متبقي
                                                @endif
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge badge-success">لا تنتهي</span>
                                    @endif
                                </td>
                            </tr>
                            @if($batch->expired_at)
                            <tr>
                                <td class="font-weight-bold">تاريخ الانتهاء الفعلي</td>
                                <td>{{ $batch->expired_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="font-weight-bold">الحالة</td>
                                <td>
                                    <span class="badge badge-{{ $batch->status->color() }}" style="font-size: 0.9rem;">
                                        {{ $batch->status->label() }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">تاريخ الإنشاء</td>
                                <td>{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- حالة الدفعة -->
            <div class="card bg-light-{{ $batch->status->color() }}">
                <div class="card-body text-center">
                    @if($batch->status === \App\Enums\BatchStatus::ACTIVE)
                        <i class="feather icon-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                        <h5 class="mt-2">دفعة نشطة</h5>
                        <p class="text-muted">
                            @if($batch->expires_at)
                                @if($daysLeft !== null && $daysLeft <= 30)
                                    <span class="text-warning">تنتهي خلال {{ $daysLeft }} يوم</span>
                                @else
                                    تنتهي في {{ $batch->expires_at->format('Y-m-d') }}
                                @endif
                            @else
                                لا تنتهي
                            @endif
                        </p>
                        <p class="mb-0">
                            <strong class="text-success" style="font-size: 1.5rem;">{{ number_format($batch->remaining_points) }}</strong>
                            <br>نقطة متبقية من أصل {{ number_format($batch->original_points) }}
                        </p>
                    @elseif($batch->status === \App\Enums\BatchStatus::EXPIRED)
                        <i class="feather icon-x-circle" style="font-size: 3rem; color: #dc3545;"></i>
                        <h5 class="mt-2">دفعة منتهية</h5>
                        <p class="text-muted">انتهت في {{ $batch->expired_at?->format('Y-m-d') ?? $batch->expires_at?->format('Y-m-d') }}</p>
                        <p class="mb-0">
                            <strong class="text-danger" style="font-size: 1.5rem;">{{ number_format($batch->expired_points) }}</strong>
                            <br>نقطة انتهت صلاحيتها
                        </p>
                    @else
                        <i class="feather icon-minus-circle" style="font-size: 3rem; color: #6c757d;"></i>
                        <h5 class="mt-2">دفعة مستنفذة</h5>
                        <p class="text-muted">تم استخدام جميع النقاط</p>
                        <p class="mb-0">
                            <strong class="text-info" style="font-size: 1.5rem;">{{ number_format($batch->used_points) }}</strong>
                            <br>نقطة تم استخدامها
                        </p>
                    @endif
                </div>
            </div>

            <!-- بطاقة المستخدم -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-user"></i> صاحب الدفعة</h4>
                </div>
                <div class="card-body text-center">
                    @if($batch->user_type === \App\Enums\UserType::EMPLOYEE && $batch->employee)
                        <div class="mb-2">
                            <span class="badge badge-primary" style="font-size: 0.9rem;">موظف</span>
                        </div>
                        <h5>{{ $batch->employee->full_name }}</h5>
                        <a href="{{ route('ecosystem.points.users.employees.show', $batch->employee_id) }}" 
                           class="btn btn-sm btn-outline-primary mt-1">
                            <i class="feather icon-external-link"></i> عرض الملف
                        </a>
                    @elseif($batch->user_type === \App\Enums\UserType::CLIENT && $batch->client)
                        <div class="mb-2">
                            <span class="badge badge-info" style="font-size: 0.9rem;">عميل</span>
                        </div>
                        <h5>{{ $batch->client->full_name ?: $batch->client->trade_name }}</h5>
                        @if($batch->client->phone)
                            <p class="text-muted mb-1">{{ $batch->client->phone }}</p>
                        @endif
                        <a href="{{ route('ecosystem.points.users.clients.show', $batch->client_id) }}" 
                           class="btn btn-sm btn-outline-info mt-1">
                            <i class="feather icon-external-link"></i> عرض الملف
                        </a>
                    @else
                        <p class="text-muted">مستخدم غير معروف</p>
                    @endif
                </div>
            </div>

            <!-- إجراءات -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-zap"></i> إجراءات</h4>
                </div>
                <div class="card-body">
                    <a href="{{ route('ecosystem.points.batches.index') }}" class="btn btn-block btn-outline-secondary mb-1">
                        <i class="feather icon-arrow-right"></i> العودة للقائمة
                    </a>
                    @if($batch->transaction)
                        <a href="{{ route('ecosystem.points.transactions.show', $batch->transaction_id) }}" 
                           class="btn btn-block btn-outline-primary mb-1">
                            <i class="feather icon-file-text"></i> عرض المعاملة
                        </a>
                    @endif
                    <a href="{{ route('ecosystem.points.expiry-settings.index') }}" class="btn btn-block btn-outline-info">
                        <i class="feather icon-settings"></i> إعدادات الانتهاء
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
