{{--
    حقولُ الفلتر المشتركة لتقارير الموارد البشرية — داخل `.ag-filter-grid`.

    المدى (من/إلى + الفترة المحاسبية/السنة المالية)، ثم القسم والمسمى
    والموظف وحالة الموظف، ثم «تابع لـ» بعرض الشبكة كاملاً (آخراً).

    @param array  $periodContext
    @param Carbon $fromDate, $toDate
    @param bool   $withPeriod     (افتراض true) — تقاريرُ لحظية بلا مدى تمرّر false
    @param bool   $withStatus     (افتراض true) — حالةُ الموظف (نشط/غير نشط/الكل)
    @param collections $departments, $jobTitles, $employeeOptions, $treeNodes
--}}
@php
    $wfPeriod = $withPeriod ?? true;
    $wfStatus = $withStatus ?? true;
    $wfEmpStatus = request('employee_status', $defaultEmployeeStatus ?? 'active');
@endphp

@if ($wfPeriod)
    {{-- أزرارُ الفترات السريعة — كتقرير «أرصدة العملاء» --}}
    <div class="ag-span-all">
        <input type="hidden" name="period" id="periodInput" value="{{ request('period', '') }}">
        @include('client::customers.CustomerReport.partials.report-period-tabs', [
            'tabPeriods' => ['1' => 'اليوم', '7' => 'أسبوع', '30' => 'شهر', '90' => '3 أشهر', '180' => '6 أشهر', '365' => 'سنة', 'custom' => 'مخصص'],
        ])
    </div>

    <div class="rpt-field">
        <label for="from_date">الفترة</label>
        <div class="ag-range">
            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate->format('Y-m-d') }}">
            <span class="ag-range__sep">إلى</span>
            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate->format('Y-m-d') }}">
        </div>
    </div>

    @include('client::customers.CustomerReport.partials.period-selector', [
        'periodContext' => $periodContext ?? [],
        'startId' => 'from_date',
        'endId' => 'to_date',
        'cols' => '',
        'yearTitle' => 'السنة المالية',
        'periodTitle' => 'الفترة المحاسبية',
    ])
@endif

<div class="rpt-field">
    <label for="department_id">القسم</label>
    <select name="department_id" id="department_id" class="form-control rpt-select2">
        <option value="">كل الأقسام</option>
        @foreach ($departments as $item)
            <option value="{{ $item->id }}" @selected((string) request('department_id') === (string) $item->id)>{{ $item->name }}</option>
        @endforeach
    </select>
</div>

<div class="rpt-field">
    <label for="job_title_id">المسمى الوظيفي</label>
    <select name="job_title_id" id="job_title_id" class="form-control rpt-select2">
        <option value="">كل المسميات</option>
        @foreach ($jobTitles as $item)
            <option value="{{ $item->id }}" @selected((string) request('job_title_id') === (string) $item->id)>{{ $item->name }}</option>
        @endforeach
    </select>
</div>

<div class="rpt-field">
    <label for="employee_id">الموظف</label>
    <select name="employee_id" id="employee_id" class="form-control rpt-select2">
        <option value="">كل الموظفين</option>
        @foreach ($employeeOptions as $item)
            <option value="{{ $item->id }}" @selected((string) request('employee_id') === (string) $item->id)>{{ $item->full_name }}</option>
        @endforeach
    </select>
</div>

@if ($wfStatus)
    <div class="rpt-field">
        <label for="employee_status">حالة الموظف</label>
        <select name="employee_status" id="employee_status" class="form-control">
            <option value="active" @selected($wfEmpStatus === 'active')>على رأس العمل</option>
            <option value="inactive" @selected($wfEmpStatus === 'inactive')>منتهية خدمته / موقوف</option>
            <option value="" @selected($wfEmpStatus === '')>الكل</option>
        </select>
    </div>
@endif

{{ $slot ?? '' }}

{{-- «تابع لـ» آخرَ الشبكة دائماً (order) — كتقارير العملاء — مهما أضاف التقريرُ بعده من حقول --}}
<div class="ag-span-all hr-tree-cell">
    <div class="row">
        @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'name' => 'branch_id', 'value' => request('branch_id')])
    </div>
</div>
