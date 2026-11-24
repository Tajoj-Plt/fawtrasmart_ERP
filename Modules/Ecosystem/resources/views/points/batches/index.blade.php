@extends('ecosystem::master')

@section('title', 'دفعات النقاط')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">دفعات النقاط</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">النظام البيئي</a></li>
                            <li class="breadcrumb-item active">دفعات النقاط</li>
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
                    <form method="GET" action="{{ route('ecosystem.points.batches.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach(\App\Enums\UserType::cases() as $type)
                                            <option value="{{ $type->value }}" {{ request('user_type') == $type->value ? 'selected' : '' }}>
                                                {{ $type->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>الحالة</label>
                                    <select name="status" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach(\App\Enums\BatchStatus::cases() as $status)
                                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>بحث</label>
                                    <input type="text" name="search" class="form-control" 
                                           value="{{ request('search') }}" placeholder="اسم أو هاتف...">
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
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.batches.index') }}" class="btn btn-outline-secondary">
                                            <i class="feather icon-refresh-cw"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="expiringSoon" 
                                           name="expiring_soon" value="1" {{ request('expiring_soon') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="expiringSoon">
                                        <i class="feather icon-alert-triangle text-warning"></i>
                                        تنتهي خلال 30 يوم فقط
                                    </label>
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
                    <i class="feather icon-package"></i>
                </div>
                <div class="stat-label">إجمالي الدفعات</div>
                <div class="stat-value">{{ number_format($totalBatches) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-layers"></i>
                    <span>دفعة نقاط</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-label">دفعات نشطة</div>
                <div class="stat-value">{{ number_format($activeBatches) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>بها رصيد</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-alert-triangle"></i>
                </div>
                <div class="stat-label">تنتهي قريباً</div>
                <div class="stat-value">{{ number_format($expiringSoonCount) }}</div>
                <div class="stat-trend down">
                    <i class="feather icon-clock"></i>
                    <span>خلال 30 يوم</span>
                </div>
            </div>

            <div class="stat-card-modern danger">
                <div class="stat-icon">
                    <i class="feather icon-x-circle"></i>
                </div>
                <div class="stat-label">نقاط منتهية</div>
                <div class="stat-value">{{ number_format($totalExpiredPoints) }}</div>
                <div class="stat-trend down">
                    <i class="feather icon-trending-down"></i>
                    <span>نقطة انتهت</span>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> قائمة دفعات النقاط</h4>
                <div>
                    <a href="{{ route('ecosystem.points.batches.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $batches->total() }} دفعة</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>النقاط الأصلية</th>
                                <th>المتبقي</th>
                                <th>المستخدم</th>
                                <th>المنتهي</th>
                                <th>تاريخ الكسب</th>
                                <th>تاريخ الانتهاء</th>
                                <th>الحالة</th>
                                <th class="text-center" style="width: 100px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batches as $batch)
                                @php
                                    $daysLeft = $batch->getDaysUntilExpiry();
                                    $isExpiringSoon = $daysLeft !== null && $daysLeft <= 30 && $daysLeft > 0;
                                @endphp
                                <tr class="{{ $isExpiringSoon ? 'table-warning' : '' }}">
                                    <td>{{ $batch->id }}</td>
                                    <td>
                                        <div>
                                            <span class="badge badge-light-{{ $batch->user_type === \App\Enums\UserType::EMPLOYEE ? 'primary' : 'info' }} badge-sm">
                                                {{ $batch->user_type->label() }}
                                            </span>
                                            <br>
                                            @if($batch->user_type === \App\Enums\UserType::EMPLOYEE)
                                                <strong>{{ $batch->employee?->full_name ?? 'غير معروف' }}</strong>
                                            @else
                                                <strong>{{ $batch->client?->full_name ?: ($batch->client?->trade_name ?? 'غير معروف') }}</strong>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            {{ number_format($batch->original_points) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $batch->remaining_points > 0 ? 'success' : 'secondary' }}">
                                            {{ number_format($batch->remaining_points) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($batch->used_points > 0)
                                            <span class="text-info">{{ number_format($batch->used_points) }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($batch->expired_points > 0)
                                            <span class="text-danger">{{ number_format($batch->expired_points) }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $batch->earned_at?->format('Y-m-d') }}</small>
                                    </td>
                                    <td>
                                        @if($batch->expires_at)
                                            <small class="{{ $isExpiringSoon ? 'text-danger font-weight-bold' : '' }}">
                                                {{ $batch->expires_at->format('Y-m-d') }}
                                            </small>
                                            @if($daysLeft !== null && $batch->status === \App\Enums\BatchStatus::ACTIVE)
                                                <br>
                                                <small class="text-{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 30 ? 'warning' : 'muted') }}">
                                                    @if($daysLeft == 0)
                                                        تنتهي اليوم!
                                                    @elseif($daysLeft == 1)
                                                        تنتهي غداً
                                                    @else
                                                        {{ $daysLeft }} يوم متبقي
                                                    @endif
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted">لا تنتهي</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $batch->status->color() }}">
                                            {{ $batch->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="actions-dropdown dropdown">
                                            <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.batches.show', $batch->id) }}">
                                                    <i class="feather icon-eye"></i> عرض التفاصيل
                                                </a>
                                                @if($batch->transaction)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.transactions.show', $batch->transaction_id) }}">
                                                        <i class="feather icon-file-text"></i> عرض المعاملة
                                                    </a>
                                                @endif
                                                @if($batch->user_type === \App\Enums\UserType::EMPLOYEE && $batch->employee_id)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.users.employees.show', $batch->employee_id) }}">
                                                        <i class="feather icon-user"></i> ملف الموظف
                                                    </a>
                                                @elseif($batch->user_type === \App\Enums\UserType::CLIENT && $batch->client_id)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.users.clients.show', $batch->client_id) }}">
                                                        <i class="feather icon-user"></i> ملف العميل
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather icon-inbox" style="font-size: 3rem; color: #b8c2cc;"></i>
                                            <p class="mt-2 text-muted">لا توجد دفعات نقاط لعرضها</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $batches, 'label' => 'دفعة'])
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Auto-submit on expiring soon checkbox
    $('#expiringSoon').on('change', function() {
        $('#filterForm').submit();
    });
});
</script>
@endsection
