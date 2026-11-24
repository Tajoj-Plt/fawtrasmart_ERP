{{--
    محتوى بطاقة جدول دفاتر الحضور — يُعرض في التحميل الأول، ويُعيده
    `attendance_sheets.ajax.index` نصّاً يُوضع في `#resultsContainer`.

    الترقيمُ هنا روابطُ عادية، وسكربتُ الصفحة يعترضها ويحمّل الصفحةَ
    المطلوبة بـAJAX (الرابطُ نفسه يشير إلى مسار AJAX لا إلى الشاشة).
--}}
@php
    $sheetFilterKeys = ['keywords', 'from_date', 'to_date', 'status', 'department', 'branch', 'job_title'];
    $sheetHasFilters = collect(request()->only($sheetFilterKeys))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@if (isset($attendanceSheets) && ! empty($attendanceSheets) && $attendanceSheets->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة دفاتر الحضور
            <span class="stk-count">{{ $attendanceSheets->total() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="attendance-sheets-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الموظف</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th class="text-center">أيام الحضور</th>
                    <th class="text-center">ساعات العمل الفعلية</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $uniqueEmployees = collect();
                    foreach ($attendanceSheets as $attendanceSheet) {
                        foreach ($attendanceSheet->employees as $employee) {
                            $uniqueEmployees->push([
                                'id' => $attendanceSheet->id,
                                'employee_id' => $employee->id,
                                'full_name' => $employee->full_name,
                                'employee_code' => $employee->employee_code ?? $employee->id,
                                'employees_count' => $attendanceSheet->employees->count(),
                                'from_date' => $attendanceSheet->from_date,
                                'to_date' => $attendanceSheet->to_date,
                                'status' => $attendanceSheet->status,
                                'attendance_days' => $attendanceSheet->attendance_days ?? 0,
                                'actual_work_hours' => $attendanceSheet->actual_work_hours ?? 0,
                                'total_days' => $attendanceSheet->total_days ?? 0,
                                'total_hours' => $attendanceSheet->total_hours ?? 0,
                            ]);
                        }
                    }
                    $uniqueEmployees = $uniqueEmployees->unique('id')->values();
                @endphp

                @foreach ($uniqueEmployees as $index => $employee)
                    <tr id="attendance-sheet-{{ $employee['id'] }}">
                        <td class="stk-num text-muted">{{ $attendanceSheets->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-book"></i></span>
                                <span class="stk-media__body">
                                    @permission('attendance_books.show')
                                        <a href="{{ route('attendance_sheets.show', $employee['id']) }}" class="stk-media__title">{{ $employee['full_name'] }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $employee['full_name'] }}</span>
                                    @endpermission
                                    <span class="stk-media__sub">
                                        #{{ $employee['employee_code'] }}
                                        @if ($employee['employees_count'] > 1)
                                            · موظفو الدفتر: {{ $employee['employees_count'] }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td class="stk-num">{{ \Carbon\Carbon::parse($employee['from_date'])->format('d/m/Y') }}</td>
                        <td class="stk-num">{{ \Carbon\Carbon::parse($employee['to_date'])->format('d/m/Y') }}</td>
                        <td class="text-center stk-num">
                            <strong>{{ $employee['attendance_days'] }}</strong>
                            <span class="text-muted small">من {{ $employee['total_days'] }}</span>
                        </td>
                        <td class="text-center stk-num">
                            <strong>{{ $employee['actual_work_hours'] }}</strong>
                            <span class="text-muted small">من {{ $employee['total_hours'] }} ساعة</span>
                        </td>
                        <td class="text-center">
                            @if ($employee['status'] == 0)
                                <span class="stk-state stk-state--warn">تحت المراجعة</span>
                            @else
                                <span class="stk-state stk-state--on">موافق عليه</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if (canAny(['attendance_books.show', 'attendance_books.edit']))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @permission('attendance_books.show')
                                            <a class="dropdown-item" href="{{ route('attendance_sheets.show', $employee['id']) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endpermission
                                        @permission('attendance_books.edit')
                                            <a class="dropdown-item" href="{{ route('attendance_sheets.edit', $employee['id']) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endpermission
                                        {{-- الحذف من هنا معطَّلٌ عمداً (كان مُعلَّقاً) — يبقى من شاشة العرض --}}
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="stk-table-foot">
        <p class="stk-table-foot__count">عرض {{ $attendanceSheets->firstItem() }}–{{ $attendanceSheets->lastItem() }} من {{ $attendanceSheets->total() }}</p>
        @if ($attendanceSheets->hasPages())
            {{ $attendanceSheets->onEachSide(1)->links() }}
        @endif
    </div>
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-book"></i></div>
            <h5 class="stk-empty__title">لا توجد دفاتر حضور</h5>
            <p class="stk-empty__hint">
                @if ($sheetHasFilters)
                    لم يطابق البحث أيّ دفتر — جرّب معايير أوسع
                @else
                    أضف أوّل دفترٍ لتتابع أيام مجموعةٍ من الموظفين في مدّة محدّدة
                @endif
            </p>
            @if ($sheetHasFilters)
                <button type="button" class="btn btn-outline-secondary js-sheets-reset"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            @elseif (hasPermission('attendance_books.create'))
                <a href="{{ route('attendance_sheets.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> أضف دفتر حضور</a>
            @endif
        </div>
    </div>
@endif
