@extends('ecosystem::master')

@section('title', 'نقاط الموظفين')

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">نقاط الموظفين</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">نقاط الموظفين</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card-modern primary">
                <div class="stat-icon">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-label">إجمالي الموظفين</div>
                <div class="stat-value">{{ number_format($stats['total_employees']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-user"></i>
                    <span>موظف</span>
                </div>
            </div>

            <div class="stat-card-modern info">
                <div class="stat-icon">
                    <i class="feather icon-star"></i>
                </div>
                <div class="stat-label">إجمالي الرصيد</div>
                <div class="stat-value">{{ number_format($stats['total_points']) }}</div>
                <div class="stat-trend">
                    <i class="feather icon-activity"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern success">
                <div class="stat-icon">
                    <i class="feather icon-trending-up"></i>
                </div>
                <div class="stat-label">إجمالي المكتسب</div>
                <div class="stat-value">{{ number_format($stats['total_earned']) }}</div>
                <div class="stat-trend up">
                    <i class="feather icon-plus-circle"></i>
                    <span>نقطة</span>
                </div>
            </div>

            <div class="stat-card-modern warning">
                <div class="stat-icon">
                    <i class="feather icon-trending-down"></i>
                </div>
                <div class="stat-label">إجمالي المستخدم</div>
                <div class="stat-value">{{ number_format($stats['total_spent']) }}</div>
                <div class="stat-trend down">
                    <i class="feather icon-minus-circle"></i>
                    <span>نقطة</span>
                </div>
            </div>
        </div>

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
                    <form method="GET" action="{{ route('ecosystem.points.users.employees') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">بحث</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="{{ request('search') }}" placeholder="اسم الموظف أو البريد...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="month_year">الشهر</label>
                                    <select name="month_year" id="month_year" class="form-control">
                                        @foreach($availableMonths as $m)
                                            <option value="{{ $m['month'] }}-{{ $m['year'] }}" {{ ($filterMonth == $m['month'] && $filterYear == $m['year']) ? 'selected' : '' }}>
                                                {{ $m['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="month" id="filter_month" value="{{ $filterMonth }}">
                                    <input type="hidden" name="year" id="filter_year" value="{{ $filterYear }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الفرع</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">كل الفروع</option>
                                        @foreach(\App\Models\Branch::where('status', 'active')->get() as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>القسم</label>
                                    <select name="department_id" class="form-control">
                                        <option value="">كل الأقسام</option>
                                        @foreach(\App\Models\Department::all() as $dept)
                                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="sort_by">ترتيب حسب</label>
                                    <select name="sort_by" id="sort_by" class="form-control">
                                        <option value="first_name" {{ request('sort_by', 'first_name') == 'first_name' ? 'selected' : '' }}>الاسم الأول</option>
                                        <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>تاريخ الإنشاء</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group" style="margin-top: 24px;">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="feather icon-search"></i> بحث
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group" style="margin-top: 24px;">
                                    <a href="{{ route('ecosystem.points.users.employees') }}" class="btn btn-outline-secondary btn-block">
                                        <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- جدول الموظفين -->
        <div class="card table-card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="feather icon-users"></i> قائمة الموظفين</h4>
                <div>
                    <a href="{{ route('ecosystem.points.transactions.create') }}?user_type=employee" class="btn btn-sm btn-outline-primary">
                        <i class="feather icon-plus"></i> إضافة معاملة
                    </a>
                    <span class="badge-count mr-1">{{ $employees->total() }} موظف</span>
                </div>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الموظف</th>
                                    <th>القسم</th>
                                    <th>الفرع</th>
                                    <th class="text-center">مكتسب الشهر</th>
                                    <th class="text-center">مستخدم الشهر</th>
                                    <th class="text-center">رصيد الشهر</th>
                                    <th class="text-center">عدد المعاملات</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    <tr>
                                        <td>{{ $employee->id }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar mr-1">
                                                    @if($employee->employee_photo)
                                                        <img src="{{ asset('assets/uploads/employee/' . $employee->employee_photo) }}" alt="{{ $employee->full_name }}" height="32" width="32">
                                                    @else
                                                        <span class="avatar-content bg-primary">{{ substr($employee->first_name, 0, 1) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <strong>{{ $employee->full_name }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $employee->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $employee->department?->name ?? '-' }}</td>
                                        <td>{{ $employee->branch?->name ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="text-success font-weight-bold">+{{ number_format($employee->month_earned) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-danger font-weight-bold">-{{ number_format($employee->month_spent) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-pill {{ $employee->month_balance > 0 ? 'badge-success' : ($employee->month_balance < 0 ? 'badge-danger' : 'badge-secondary') }}" style="font-size: 0.9rem;">
                                                {{ number_format($employee->month_balance) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-pill badge-primary">
                                                {{ $employee->month_transactions_count }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown actions-dropdown">
                                                <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-toggle="dropdown">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.users.employees.show', $employee->id) }}">
                                                        <i class="feather icon-eye mr-1"></i> عرض التفاصيل
                                                    </a>
                                                    <a class="dropdown-item" href="{{ url('hr/employee/edit/'.$employee->id) }}">
                                                        <i class="feather icon-edit mr-1"></i> تعديل الموظف
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="{{ route('ecosystem.points.transactions.create') }}?user_type=employee&user_id={{ $employee->id }}">
                                                        <i class="feather icon-plus-circle mr-1"></i> إضافة معاملة نقاط
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="py-3">
                                                <i class="feather icon-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                                <p class="text-muted mt-1">لا يوجد موظفين لديهم نقاط</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                @include('ecosystem::points.partials._smart-pagination', ['paginator' => $employees, 'label' => 'موظف'])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#month_year').on('change', function() {
            var parts = $(this).val().split('-');
            $('#filter_month').val(parts[0]);
            $('#filter_year').val(parts[1]);
            $('#filterForm').submit();
        });
    });
</script>
@endpush
