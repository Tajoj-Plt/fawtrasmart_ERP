@extends('master')

@section('title')
تقرير حضور تفصيلي موظف واحد
@stop

@section('content')

<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">تقرير حضور تفصيلي موظف واحد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="">الرئيسيه</a>
                        </li>
                        <li class="breadcrumb-item active">عرض
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>




<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">نموذج البحث</h5>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row g-4">
                <!-- الموظف -->
                <div class="col-md-4">
                    <label for="employee-search" class="form-label">الموظف:</label>
                    <input
                        type="text"
                        class="form-control"
                        id="employee-search"
                        name="employee"
                        value="{{ request('employee') }}"
                        placeholder="البحث باسم الموظف أو المعرف أو البريد الإلكتروني">
                </div>

                <!-- الفترة من -->
                <div class="col-md-4">
                    <label for="date_from" class="form-label">الفترة من:</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from', $fromDate->format('Y-m-d')) }}" class="form-control">
                </div>

                <!-- الفترة إلى -->
                <div class="col-md-4">
                    <label for="date_to" class="form-label">الفترة إلى:</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to', $toDate->format('Y-m-d')) }}" class="form-control">
                </div>
            </div>

            <div class="d-flex justify-content-start mt-3">
                <!-- زر البحث -->
                <button type="submit" class="btn btn-primary me-2 waves-effect waves-light">بحث</button>

                <!-- زر الإلغاء -->
                <button type="reset" class="btn btn-outline-warning waves-effect waves-light">إلغاء</button>
            </div>
        </form>
    </div>
</div>

@if (request('employee'))
    @if (! $employee)
        <div class="alert alert-warning mt-4">لا يوجد موظفٌ مطابقٌ لهذا البحث ضمن الموظفين المصرَّح لك بالاطّلاع عليهم.</div>
    @else
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">{{ $employee->full_name }} — {{ $fromDate->format('Y-m-d') }} إلى {{ $toDate->format('Y-m-d') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge bg-success">حاضر: {{ $totals['total_present'] }}</span>
                    <span class="badge bg-danger">غائب: {{ $totals['total_absent'] }}</span>
                    <span class="badge bg-warning">متأخر: {{ $totals['total_late'] }}</span>
                    <span class="badge bg-info">إضافي: {{ $totals['total_overtime_minutes'] }} د</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-center">
                        <thead class="bg-light">
                            <tr>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                                <th>الحضور</th>
                                <th>الانصراف</th>
                                <th>التأخير</th>
                                <th>الإضافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attendanceData as $day)
                                @php
                                    $statusLabels = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'half_day' => 'نصف يوم'];
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($day->attendance_date)->format('Y-m-d') }}</td>
                                    <td>{{ $statusLabels[$day->status] ?? $day->status }}</td>
                                    <td>{{ $day->check_in_time ?? '-' }}</td>
                                    <td>{{ $day->check_out_time ?? '-' }}</td>
                                    <td>{{ (int) $day->late_minutes > 0 ? $day->late_minutes . ' د' : '-' }}</td>
                                    <td>{{ (int) $day->overtime_minutes > 0 ? $day->overtime_minutes . ' د' : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">لا توجد سجلات حضور لهذا الموظف ضمن الفترة المحدَّدة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endif

@endsection
