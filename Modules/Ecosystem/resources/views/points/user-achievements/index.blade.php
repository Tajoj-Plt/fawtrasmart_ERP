@extends('ecosystem::master')

@section('title', 'إنجازات المستخدمين')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">إنجازات المستخدمين</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.achievements.index') }}">الإنجازات</a></li>
                            <li class="breadcrumb-item active">إنجازات المستخدمين</li>
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
                        <li><a data-action="collapse"><i class="feather icon-chevron-down"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('ecosystem.points.user-achievements.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>البحث</label>
                                    <input type="text" name="search" class="form-control"
                                           placeholder="ابحث باسم المستخدم أو الإنجاز..." value="{{ request('search') }}">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>نوع المستخدم</label>
                                    <select name="user_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="client" {{ request('user_type') == 'client' ? 'selected' : '' }}>عميل</option>
                                        <option value="employee" {{ request('user_type') == 'employee' ? 'selected' : '' }}>موظف</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الإنجاز</label>
                                    <select name="achievement_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach($achievements as $achievement)
                                            <option value="{{ $achievement->id }}" {{ request('achievement_id') == $achievement->id ? 'selected' : '' }}>
                                                {{ $achievement->name }}
                                            </option>
                                        @endforeach
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
                                                {{ $branch->branch_name }}
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
                        </div>

                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>إلى تاريخ</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                                </div>
                            </div>

                            <div class="col-md-10">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-search"></i> بحث
                                        </button>
                                        <a href="{{ route('ecosystem.points.user-achievements.index') }}" class="btn btn-outline-secondary">
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
                    <i class="feather icon-award"></i>
                </div>
                <div class="stat-label">إجمالي الإنجازات المحققة</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-activity"></i>
                    <span>إنجاز</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">إنجازات العملاء</div>
                <div class="stat-value">{{ number_format($stats['clients']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-user-check"></i>
                    <span>عميل</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-briefcase"></i>
                </div>
                <div class="stat-label">إنجازات الموظفين</div>
                <div class="stat-value">{{ number_format($stats['employees']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-user"></i>
                    <span>موظف</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">إجمالي النقاط الممنوحة</div>
                <div class="stat-value">{{ number_format($stats['total_points']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-trending-up"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-calendar"></i>
                </div>
                <div class="stat-label">هذا الشهر</div>
                <div class="stat-value">{{ number_format($stats['this_month']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-clock"></i>
                    <span>إنجاز</span>
                </div>
            </div>
        </div>

        <!-- جدول الإنجازات -->
        <div class="card table-card-modern" id="reportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-award"></i> قائمة إنجازات المستخدمين</h4>
                <div>
                    <a href="{{ route('ecosystem.points.user-achievements.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-download"></i> تصدير
                    </a>
                    <span class="badge-count mr-1">{{ $userAchievements->total() }} إنجاز</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="5%">الأيقونة</th>
                                <th width="15%">الإنجاز</th>
                                <th width="15%">المستخدم</th>
                                <th width="10%">النوع</th>
                                <th width="10%">النقاط الممنوحة</th>
                                <th width="12%">تاريخ التحقيق</th>
                                <th width="10%">الفرع</th>
                                <th width="8%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userAchievements as $userAchievement)
                                <tr>
                                    <td>{{ $loop->iteration + ($userAchievements->currentPage() - 1) * $userAchievements->perPage() }}</td>
                                    <td>
                                        @if($userAchievement->achievement)
                                            @if($userAchievement->achievement->image)
                                                <img src="{{ asset('storage/' . $userAchievement->achievement->image) }}" 
                                                     alt="{{ $userAchievement->achievement->name }}"
                                                     style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                                            @else
                                                <span style="width: 35px; height: 35px; border-radius: 8px; background: {{ $userAchievement->achievement->color ?? '#f0f0f0' }}; display: flex; align-items: center; justify-content: center;">
                                                    @include('ecosystem::points.partials.achievement-icon', ['icon' => $userAchievement->achievement->icon])
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if($userAchievement->achievement)
                                            <a href="{{ route('ecosystem.points.achievements.show', $userAchievement->achievement->id) }}" class="font-weight-bold">
                                                {{ $userAchievement->achievement->name }}
                                            </a>
                                            @if($userAchievement->achievement->name_en)
                                                <br><small class="text-muted">{{ $userAchievement->achievement->name_en }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted">غير متوفر</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($userAchievement->user_type->value == 'employee')
                                            @if($userAchievement->employee)
                                                <a href="#" class="font-weight-bold">
                                                    {{ $userAchievement->employee->first_name }} {{ $userAchievement->employee->last_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">موظف محذوف</span>
                                            @endif
                                        @else
                                            @if($userAchievement->client)
                                                <a href="#" class="font-weight-bold">
                                                    {{ $userAchievement->client->full_name ?: $userAchievement->client->trade_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">عميل محذوف</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $userAchievement->user_type->value == 'client' ? 'info' : 'warning' }}">
                                            {{ $userAchievement->user_type->value == 'client' ? 'عميل' : 'موظف' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            <i class="feather icon-star"></i> {{ number_format($userAchievement->points_awarded) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="feather icon-calendar"></i>
                                            {{ $userAchievement->achieved_at->format('Y/m/d') }}
                                        </span>
                                        <br>
                                        <small class="text-muted">{{ $userAchievement->achieved_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $branch = null;
                                            if($userAchievement->user_type->value == 'employee' && $userAchievement->employee) {
                                                $branch = $userAchievement->employee->branch;
                                            } elseif($userAchievement->user_type->value == 'client' && $userAchievement->client) {
                                                $branch = $userAchievement->client->branch;
                                            }
                                        @endphp
                                        @if($branch)
                                            <span class="badge badge-light-secondary">{{ $branch->branch_name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dropdown actions-dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="{{ route('ecosystem.points.user-achievements.show', $userAchievement->id) }}">
                                                    <i class="feather icon-eye mr-1"></i> عرض التفاصيل
                                                </a>
                                                @if($userAchievement->transaction)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.transactions.show', $userAchievement->transaction->id) }}">
                                                        <i class="feather icon-credit-card mr-1"></i> عرض المعاملة
                                                    </a>
                                                @endif
                                                <div class="dropdown-divider"></div>
                                                @if($userAchievement->user_type->value == 'employee' && $userAchievement->employee_id)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.user-achievements.employee', $userAchievement->employee_id) }}">
                                                        <i class="feather icon-list mr-1"></i> إنجازات الموظف
                                                    </a>
                                                @elseif($userAchievement->user_type->value == 'client' && $userAchievement->client_id)
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.user-achievements.client', $userAchievement->client_id) }}">
                                                        <i class="feather icon-list mr-1"></i> إنجازات العميل
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="feather icon-award font-large-2 text-muted mb-1"></i>
                                        <p class="text-muted mb-0">لا توجد إنجازات محققة</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $userAchievements, 'label' => 'إنجاز'])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        dir: 'rtl',
        language: 'ar'
    });
});
</script>
@endsection
