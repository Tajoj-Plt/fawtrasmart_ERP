{{--
    قائمة الموظفين — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::employees`. لحظيٌّ بلا مدى: موظفٌ
    لكلّ صف كما هو اليوم. التصديرُ والطباعةُ لكل الصفوف (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    قائمة الموظفين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'users',
        'headTitle' => 'قائمة الموظفين',
        'headSubtitle' => 'كل موظف كما هو اليوم: القسم والمسمى والفرع والمدير، وتاريخ التعيين ومدة الخدمة، والجنسية والهوية، ونهاية العقد والحالة',
        'headGuideKey' => 'hr-reports.employees-list',
        'headGuide' => [
            'title' => 'من أين تأتي هذه البيانات؟',
            'lead' => 'من ملفات الموظفين كما هي الآن، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                '«مدة الخدمة» من تاريخ التعيين حتى اليوم — أو حتى تاريخ الإنهاء لمن انتهت خدمته.',
                '«رقم الهوية/الإقامة» من وثائق الموظف (الهوية الوطنية أو الإقامة)، و«نهاية العقد» من آخر عقد مسجّل له.',
                '«على رأس العمل» = نشط + تحت التجربة. و«عقود تنتهي قريباً» لمن على رأس العمل خلال '.$notice.' يوماً (مدة الإنذار في إعدادات الرواتب).',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field')

                    <div class="rpt-field">
                        <label for="employee_type">النوع</label>
                        <select name="employee_type" id="employee_type" class="form-control">
                            <option value="">الكل</option>
                            @foreach ($employeeTypes as $value => $label)
                                <option value="{{ $value }}" @selected((string) request('employee_type') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="direct_manager_id">المدير المباشر</label>
                        <select name="direct_manager_id" id="direct_manager_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($employeeOptions as $item)
                                <option value="{{ $item->id }}" @selected((string) request('direct_manager_id') === (string) $item->id)>{{ $item->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="hire_from">تاريخ التعيين</label>
                        <div class="ag-range">
                            <input type="date" name="hire_from" id="hire_from" class="form-control" value="{{ request('hire_from') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="hire_to" id="hire_to" class="form-control" value="{{ request('hire_to') }}">
                        </div>
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">الموظفون</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['all']) }}</bdi></div><div class="ag-kpi__sub">في هذا النطاق</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">على رأس العمل</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['working']) }}</bdi></div><div class="ag-kpi__sub">نشط + تحت التجربة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-check"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">جدد هذا الشهر</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['new']) }}</bdi></div><div class="ag-kpi__sub">تعيين في {{ now()->locale('ar')->translatedFormat('F Y') }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-user-plus"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">عقود تنتهي قريباً</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['expiring']) }}</bdi></div><div class="ag-kpi__sub">خلال {{ $notice }} يوماً{{ $totals['expired'] ? ' · و'.$totals['expired'].' منتهية' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-file-contract"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-table"></i> الموظفون <span class="rpt-pill rpt-pill-secondary">{{ number_format($rows->total()) }} موظف</span></h4>
            @include('hr::reports.employees._table_tools')
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th><th>الرقم</th><th>الموظف</th><th>القسم</th><th>المسمى</th><th>الفرع</th><th>المدير المباشر</th>
                        <th>التعيين</th><th>مدة الخدمة</th><th>الجنسية</th><th>الهوية/الإقامة</th><th>نهاية العقد</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="rpt-num">{{ $row['code'] }}</td>
                            <td class="rpt-name">
                                @include('hr::reports.employees._name', ['empId' => $row['id'], 'empName' => $row['name']])
                                @if ($row['is_new'])<span class="rpt-pill rpt-pill-success">جديد</span>@endif
                            </td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['job_title'] }}</span></td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $row['branch'] }}</span></td>
                            <td>{{ $row['manager'] }}</td>
                            <td class="rpt-num">{{ $row['hire_date'] ?? '—' }}</td>
                            <td>{{ $row['tenure'] }}</td>
                            <td>{{ $row['nationality'] }}</td>
                            <td class="rpt-num">{{ $row['id_number'] }}</td>
                            <td class="rpt-num">
                                {{ $row['contract_end'] }}
                                @if ($row['contract_days'] !== null && $row['working'])
                                    @if ($row['contract_days'] < 0)
                                        <div><span class="rpt-pill rpt-pill-danger">منتهٍ</span></div>
                                    @elseif ($row['contract_days'] <= $notice)
                                        <div><span class="rpt-pill rpt-pill-warning">باقٍ {{ $row['contract_days'] }} يوماً</span></div>
                                    @endif
                                @endif
                            </td>
                            <td><span class="rpt-pill {{ $row['status_tone'] }}">{{ $row['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا موظفين في هذا النطاق</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('hr::reports.employees._pager', ['pager' => $rows])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'قائمة الموظفين',
        'frInfo' => 'كما في '.now()->format('Y-m-d'),
        'frHeaders' => ['#', 'الرقم', 'الموظف', 'القسم', 'المسمى', 'الفرع', 'المدير المباشر', 'التعيين', 'مدة الخدمة', 'الجنسية', 'الهوية/الإقامة', 'نهاية العقد', 'الحالة'],
    ])
@endsection
