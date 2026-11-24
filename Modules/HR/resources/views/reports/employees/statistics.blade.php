{{--
    إحصائيات الموظفين — لوحةُ مؤشّراتٍ بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `EmployeeReportController::statistics`. بلا تصدير (§11.2):
    مؤشّراتٌ أربعة ثم جداولُ توزيعٍ صغيرة (الحالة، النوع، القسم، الفرع،
    الجنسية، الجنس، سنوات الخدمة، العمر).
--}}
@extends('hr::master')

@section('title')
    إحصائيات الموظفين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#64748b', '#ec4899'];
    $toneColor = ['rpt-pill-success' => '#10b981', 'rpt-pill-warning' => '#f59e0b', 'rpt-pill-danger' => '#ef4444', 'rpt-pill-secondary' => '#64748b'];
    $cards = [
        ['title' => 'حسب الحالة الوظيفية', 'icon' => 'user-tag', 'rows' => $byStatus],
        ['title' => 'حسب القسم', 'icon' => 'sitemap', 'rows' => $byDepartment],
        ['title' => 'حسب الفرع', 'icon' => 'code-branch', 'rows' => $byBranch],
        ['title' => 'حسب النوع', 'icon' => 'id-badge', 'rows' => $byType],
        ['title' => 'حسب الجنسية', 'icon' => 'flag', 'rows' => $byNationality],
        ['title' => 'حسب الجنس', 'icon' => 'venus-mars', 'rows' => $byGender],
        ['title' => 'حسب سنوات الخدمة', 'icon' => 'hourglass-half', 'rows' => $byService, 'note' => 'من له تاريخ تعيين ('.$withHire.')'],
        ['title' => 'حسب العمر', 'icon' => 'birthday-cake', 'rows' => $byAge, 'note' => 'من له تاريخ ميلاد ('.$withBirth.')'],
    ];
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'chart-pie',
        'headTitle' => 'إحصائيات الموظفين',
        'headSubtitle' => 'العدد ومتوسط العمر ومتوسط الخدمة، وتوزيع الموظفين حسب الحالة والقسم والفرع والنوع والجنسية',
        'headGuideKey' => 'hr-reports.employees-statistics',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه الأرقام؟',
            'lead' => 'من ملفات الموظفين كما هي الآن، داخل نطاق رؤيتك فقط.',
            'blocks' => [[
                'تفتح الشاشة على من هم على رأس العمل (نشط + تحت التجربة). غيّر «الحالة الوظيفية» إلى «الكل» لتشمل الجميع.',
                '«متوسط العمر» لمن سُجّل تاريخ ميلاده فقط، و«متوسط الخدمة» لمن سُجّل تاريخ تعيينه — حتى اليوم أو حتى تاريخ الإنهاء.',
                'تاريخ ميلاد يجعل العمر أقل من 15 سنة يُعدّ خطأ إدخال (غالباً تُرك على تاريخ اليوم) فلا يدخل متوسط العمر — صحّحه من ملف الموظف.',
                '«النسبة» في كل جدول من مجموع الموظفين الظاهرين.',
            ]],
            'facts' => [],
        ],
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.employees.statistics') }}" id="filterForm">
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['withPeriod' => false, 'withStatus' => false])

                    @include('hr::reports.employees._status_field', ['statusDefault' => 'working'])

                    <div class="rpt-field">
                        <label for="employee_type">النوع</label>
                        <select name="employee_type" id="employee_type" class="form-control">
                            <option value="">الكل</option>
                            @foreach ($employeeTypes as $value => $label)
                                <option value="{{ $value }}" @selected((string) request('employee_type') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @include('hr::reports.employees._filter_actions', ['reset' => route('hr.reports.employees.statistics')])
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">عدد الموظفين</div><div class="ag-kpi__value"><bdi>{{ number_format($total) }}</bdi></div><div class="ag-kpi__sub">على رأس العمل {{ number_format($working) }} · جدد هذا العام {{ number_format($newThisYear) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">متوسط العمر</div><div class="ag-kpi__value"><bdi>{{ $averageAge === null ? '—' : $averageAge }}</bdi></div><div class="ag-kpi__sub">سنة · من {{ $withBirth }} موظف{{ $badBirth ? ' · '.$badBirth.' تاريخ ميلاد غير صحيح' : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-birthday-cake"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">متوسط الخدمة</div><div class="ag-kpi__value"><bdi>{{ $averageServiceYears === null ? '—' : $averageServiceYears }}</bdi></div><div class="ag-kpi__sub">سنة · من {{ $withHire }} موظف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">نسبة المواطنين</div><div class="ag-kpi__value"><bdi>{{ $citizenShare === null ? '—' : $citizenShare.'%' }}</bdi></div><div class="ag-kpi__sub">{{ number_format($citizens) }} مواطن</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-flag"></i></span>
        </div>
    </div>

    {{-- ===================== الرسوم ===================== --}}
    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== جداول التوزيع ===================== --}}
    <div class="ag-charts">
        @foreach ($cards as $card)
            <div class="rpt-card">
                <div class="rpt-card__head ag-table-head">
                    <h4><i class="fas fa-{{ $card['icon'] }}"></i> {{ $card['title'] }}</h4>
                    @if (! empty($card['note']))<span class="ag-hint">{{ $card['note'] }}</span>@endif
                </div>
                <div class="rpt-card__body">
                    @if ($card['rows']->isEmpty())
                        <div class="rpt-empty"><i class="fas fa-inbox"></i> لا بيانات</div>
                    @else
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($card['rows'] as $row)
                                    <tr class="ag-legend__row" style="--c:{{ isset($row['tone']) ? ($toneColor[$row['tone']] ?? '#64748b') : $palette[$loop->index % count($palette)] }}">
                                        <td><span class="ag-dot"></span>{{ $row['label'] }}</td>
                                        <td class="ag-legend__amount">{{ number_format($row['count']) }}</td>
                                        <td class="ag-legend__pct">{{ $row['share'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
                $('.rpt-select2').select2({ dir: 'rtl', allowClear: true, width: '100%', placeholder: 'اختر...' });
            }
        });
    </script>
@endsection
