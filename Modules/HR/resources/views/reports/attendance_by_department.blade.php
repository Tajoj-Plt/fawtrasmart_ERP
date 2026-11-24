@extends('hr::master')

@section('title')
    تقرير الحضور التفصيلي
@stop

@section('css')
<style>
    .attendance-report-page {
        padding: 20px;
        background: var(--app-body-bg, #f8f9fa);
        min-height: 100vh;
    }

    .page-header {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .page-header h3 {
        margin: 0;
        color: var(--app-text, #333);
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filters-card {
        background: white;
        padding: 25px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .filters-title {
        font-size: 18px;
        color: var(--app-info, #007bff);
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--app-text, #333);
        font-weight: 500;
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid var(--app-border, #ddd);
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--app-info, #007bff);
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .btn-primary {
        background: var(--app-info, #007bff);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-primary:hover {
        background: var(--app-primary, #0056b3);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,123,255,0.3);
    }

    .btn-secondary {
        background: var(--app-muted, #6c757d);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-right: 10px;
    }

    .btn-secondary:hover {
        background: color-mix(in srgb, var(--app-muted, #545b62) 86%, #000);
    }

    .totals-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .totals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .total-item {
        padding: 20px;
        background: var(--app-body-bg, #f8f9fa);
        border-radius: 8px;
        border-right: 4px solid var(--app-info, #007bff);
    }

    .total-label {
        font-size: 13px;
        color: var(--app-muted, #6c757d);
        margin-bottom: 5px;
    }

    .total-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--app-info, #007bff);
    }

    .data-card {
        background: white;
        padding: 0;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: var(--app-info, #007bff);
        color: white;
    }

    .data-table th {
        padding: 15px;
        text-align: right;
        font-weight: 600;
        font-size: 14px;
    }

    .data-table td {
        padding: 15px;
        text-align: right;
        border-bottom: 1px solid var(--app-border, #f0f0f0);
        font-size: 14px;
    }

    .data-table tbody tr:hover {
        background: var(--app-body-bg, #f8f9fa);
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .status-present {
        background: var(--app-success-soft, #d4edda);
        color: var(--app-success, #155724);
    }

    .status-absent {
        background: var(--app-danger-soft, #f8d7da);
        color: var(--app-danger, #721c24);
    }

    .status-late {
        background: var(--app-warning-soft, #fff3cd);
        color: var(--app-warning, #856404);
    }

    .status-half_day {
        background: #e2e3e5;
        color: var(--app-muted, #383d41);
    }

    .no-data {
        padding: 50px;
        text-align: center;
        color: var(--app-muted, #6c757d);
    }
</style>
@stop

@section('content')
<div class="attendance-report-page">
    <!-- Page Header -->
    <div class="page-header">
        <h3>
            <i class="feather icon-calendar"></i>
            تقرير الحضور التفصيلي
        </h3>
    </div>

    <!-- Filters Card -->
    <div class="filters-card">
        <div class="filters-title">
            <i class="feather icon-filter"></i>
            الفلاتر
        </div>
        
        @permission('hr_reports.attendance')
            <form method="GET" action="{{ route('hr.reports.attendance') }}">
                <div class="form-row">
                    <div class="form-group">
                        <label>القسم</label>
                        <select name="department_id" class="form-control">
                            <option value="">جميع الأقسام</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
    
                    <div class="form-group">
                        <label>الموظف</label>
                        <select name="employee_id" class="form-control">
                            <option value="">جميع الموظفين</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
    
                    <div style="grid-column:1/-1" class="form-group">
                        @include('reports.partials.affiliation-tree', [
                            'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                            'name' => 'branch_id',
                            'showExact' => false,
                            'label' => 'تابع لـ',
                        ])
                    </div>
    
                    <div class="form-group">
                        <label>من تاريخ</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $fromDate->format('Y-m-d')) }}">
                    </div>
    
                    <div class="form-group">
                        <label>إلى تاريخ</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $toDate->format('Y-m-d')) }}">
                    </div>
                </div>
    
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="fetch_data" value="1" class="btn-primary">
                        <i class="feather icon-search"></i>
                        عرض التقرير
                    </button>
                    @permission('hr_reports.export')
                    <button type="submit" name="export" value="xlsx" class="btn-secondary">Excel</button>
                    <button type="submit" name="export" value="pdf" class="btn-secondary">PDF</button>
                    @endpermission
                        <a href="{{ route('hr.reports.attendance') }}" class="btn-secondary">
                            <i class="feather icon-refresh-cw"></i>
                            إعادة تعيين
                        </a>
                </div>
            </form>
        @endpermission
    </div>

    <!-- Totals Card -->
    @if(request('fetch_data'))
    <div class="totals-card">
        <div class="totals-grid">
            <div class="total-item">
                <div class="total-label">إجمالي الموظفين</div>
                <div class="total-value">{{ $totals['total_employees'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">الحضور</div>
                <div class="total-value" style="color: var(--app-success, #28a745);">{{ $totals['total_present'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">الغياب</div>
                <div class="total-value" style="color: var(--app-danger, #dc3545);">{{ $totals['total_absent'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">التأخير</div>
                <div class="total-value" style="color: var(--app-warning, #ffc107);">{{ $totals['total_late'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">الانصراف المبكر</div>
                <div class="total-value" style="color: var(--app-warning, #fd7e14);">{{ $totals['total_early_leave'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">عمل إضافي</div>
                <div class="total-value" style="color: #6f42c1;">{{ $totals['total_overtime'] }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">بلا انصراف مسجَّل</div>
                <div class="total-value" style="color: var(--app-info, #17a2b8);">{{ $totals['total_missing_checkout'] }}</div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="data-card">
        <div class="table-responsive">
            @if($attendanceData->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>كود الموظف</th>
                        <th>اسم الموظف</th>
                        <th>القسم</th>
                        <th>الحالة</th>
                        <th>وقت الحضور</th>
                        <th>وقت الانصراف</th>
                        <th>التأخير</th>
                        <th>الانصراف المبكر</th>
                        <th>الإضافي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendanceData as $record)
                    @php
                        $statusLabels = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'half_day' => 'نصف يوم'];
                        $missingCheckout = $record->check_in_time && ! $record->check_out_time;
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($record->attendance_date)->format('Y-m-d') }}</td>
                        <td>{{ $record->employee_code }}</td>
                        <td>{{ $record->employee_name }}</td>
                        <td>{{ $record->department_name ?? 'غير محدد' }}</td>
                        <td>
                            <span class="status-badge status-{{ $record->status }}">
                                {{ $statusLabels[$record->status] ?? $record->status }}
                            </span>
                        </td>
                        <td>{{ $record->check_in_time ?? '-' }}</td>
                        <td>
                            {{ $record->check_out_time ?? '-' }}
                            @if($missingCheckout)
                                <span style="color: var(--app-info, #17a2b8); font-size: 11px;"> ⏳ بلا انصراف</span>
                            @endif
                        </td>
                        <td>{{ (int) ($record->late_minutes ?? 0) > 0 ? $record->late_minutes . ' د' : '-' }}</td>
                        <td>{{ (int) ($record->early_leave_minutes ?? 0) > 0 ? $record->early_leave_minutes . ' د' : '-' }}</td>
                        <td>{{ (int) ($record->overtime_minutes ?? 0) > 0 ? $record->overtime_minutes . ' د' : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="no-data">
                <i class="feather icon-inbox" style="font-size: 48px; color: var(--app-border, #ddd); margin-bottom: 10px;"></i>
                <p>لا توجد بيانات متاحة</p>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
@stop

@section('js')
@stop
