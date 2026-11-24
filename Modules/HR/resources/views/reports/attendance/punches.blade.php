{{--
    سجلات الحضور — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `Reports\AttendancePunchReportController::index`. ثلاثةُ عروض:
    كلُّ التسجيلات (ختمٌ لكلّ صف)، وأسبابُ الرفض، ولكلّ موظف.
    التصديرُ والطباعةُ للعرض الحاليّ كلِّه (`export=rows`).
--}}
@extends('hr::master')

@section('title')
    سجلات الحضور
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $viewTitles = ['all' => 'كل التسجيلات', 'reasons' => 'أسباب الرفض', 'employees' => 'لكل موظف'];
    $viewIcons = ['all' => 'list', 'reasons' => 'ban', 'employees' => 'users'];
    $hm = fn (int $m) => $m > 0 ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '—';
    $pager = match ($reportView) { 'reasons' => $reasons, 'employees' => $perEmployee, default => $rows };
    $unit = match ($reportView) { 'reasons' => 'سبب', 'employees' => 'موظف', default => 'تسجيل' };
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'fingerprint',
        'headTitle' => 'سجلات الحضور',
        'headSubtitle' => 'كل تسجيل حضور وانصراف واستراحة كما وقع — من جهاز الفرع أو الجوال — مقبولاً أو مرفوضاً مع سبب الرفض',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.attendance-punches',
        'headGuide' => [
            'title' => 'كيف تُقرأ هذه السجلات؟',
            'lead' => 'كل مرة يمسح فيها الموظف بطاقته على الجهاز أو يسجّل من الجوال تُحفظ هنا كما هي، ولا تُعدَّل بعد ذلك.',
            'blocks' => [[
                '«مقبول» تسجيلٌ دخل في حساب اليوم. و«مرفوض» لم يُحسب، وسببه مكتوب بجانبه — مثل: خارج نطاق الموقع، أو خارج نافذة التسجيل.',
                '«الوقت المعتمد» يظهر فقط حين يختلف عن وقت التسجيل الفعلي — مثل انصرافٍ متأخر قُصّ إلى نهاية الوردية.',
                'التأخير والانصراف المبكر هنا لحظة التسجيل؛ أما أرقام اليوم النهائية فهي في «ملخص الحضور الشامل».',
                'التسجيلات ببطاقة غير معروفة لا تتبع موظفاً، فتظهر فقط حين لا يُحصر التقرير بقسم أو فرع أو موظف.',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.reports.attendance.punches') }}" id="filterForm">
                <input type="hidden" name="view" value="{{ $reportView }}">
                @if (request('rejection_code'))<input type="hidden" name="rejection_code" value="{{ request('rejection_code') }}">@endif
                <div class="ag-filter-grid">
                    @include('hr::reports.partials.rpt-employee-fields', ['defaultEmployeeStatus' => ''])

                    <div class="rpt-field">
                        <label for="punch_type">نوع التسجيل</label>
                        <select name="punch_type" id="punch_type" class="form-control">
                            <option value="">كل الأنواع</option>
                            @foreach ($typeLabels as $key => $label)
                                <option value="{{ $key }}" @selected(request('punch_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="result">النتيجة</label>
                        <select name="result" id="result" class="form-control">
                            <option value="">الكل</option>
                            <option value="accepted" @selected(request('result') === 'accepted')>مقبول</option>
                            <option value="rejected" @selected(request('result') === 'rejected')>مرفوض</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="channel">طريقة التسجيل</label>
                        <select name="channel" id="channel" class="form-control">
                            <option value="">كل الطرق</option>
                            @foreach ($channelLabels as $key => $label)
                                <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="kiosk_id">جهاز الحضور</label>
                        <select name="kiosk_id" id="kiosk_id" class="form-control rpt-select2">
                            <option value="">كل الأجهزة</option>
                            @foreach ($kiosks as $item)
                                <option value="{{ $item->id }}" @selected((string) request('kiosk_id') === (string) $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            @foreach ($viewTitles as $key => $label)
                                <button type="submit" name="view" value="{{ $key }}" class="btn {{ $reportView === $key ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-{{ $viewIcons[$key] }}"></i> {{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.reports.attendance.punches') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">كل التسجيلات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['total']) }}</bdi></div><div class="ag-kpi__sub">لـ {{ number_format($totals['employees']) }} موظف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-fingerprint"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">مقبولة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['accepted']) }}</bdi></div><div class="ag-kpi__sub">دخلت في حساب اليوم</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">مرفوضة</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['rejected']) }}</bdi></div><div class="ag-kpi__sub">{{ $totals['rejected_pct'] }}% من التسجيلات</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-times-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">من الجوال</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['field']) }}</bdi></div><div class="ag-kpi__sub">من جهاز الفرع {{ number_format($totals['kiosk']) }}{{ $totals['manual'] ? ' · يدوي '.number_format($totals['manual']) : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-mobile-alt"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $viewTitles[$reportView] }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pager->total()) }} {{ $unit }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($reportView === 'reasons')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>سبب الرفض</th><th>نص آخر رسالة</th><th>عدد المرات</th><th>عدد الموظفين</th><th>بلا موظف</th><th>آخر مرة</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($reasons as $row)
                            <tr>
                                <td>{{ $reasons->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($row['code'])
                                        <a href="{{ route('hr.reports.attendance.punches', array_merge(request()->except(['page', 'view']), ['view' => 'all', 'result' => 'rejected', 'rejection_code' => $row['code']])) }}">{{ $row['title'] }}</a>
                                    @else
                                        {{ $row['title'] }}
                                    @endif
                                </td>
                                <td class="small">{{ $row['sample'] }}</td>
                                <td class="rpt-num"><strong>{{ number_format($row['count']) }}</strong></td>
                                <td class="rpt-num">{{ $row['employees'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['unknown'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['last'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="rpt-empty"><i class="fas fa-inbox"></i> لا تسجيلات مرفوضة في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($reportView === 'employees')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr><th>#</th><th>الموظف</th><th>القسم</th><th>كل التسجيلات</th><th>حضور مقبول</th><th>انصراف مقبول</th><th>استراحات</th><th>مرفوضة</th><th>تأخير</th><th>انصراف مبكر</th><th>آخر تسجيل</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($perEmployee as $row)
                            <tr>
                                <td>{{ $perEmployee->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    <a href="{{ route('hr.reports.attendance.punches', array_merge(request()->except(['page', 'view']), ['view' => 'all', 'employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    <div class="text-muted small">{{ $row['job_title'] }}</div>
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td class="rpt-num"><strong>{{ $row['total'] }}</strong></td>
                                <td class="rpt-num">{{ $row['in'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['out'] ?: '—' }}</td>
                                <td class="rpt-num">{{ $row['breaks'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['rejected'] ? 'rpt-neg' : '' }}">{{ $row['rejected'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['late'] ? 'rpt-neg' : '' }}">{{ $hm($row['late']) }}</td>
                                <td class="rpt-num {{ $row['early'] ? 'rpt-neg' : '' }}">{{ $hm($row['early']) }}</td>
                                <td class="rpt-num">
                                    {{ $row['last'] }}
                                    <div class="small"><span class="rpt-pill {{ $row['last_result'] === 'accepted' ? 'rpt-pill-success' : 'rpt-pill-danger' }}">{{ $row['last_type'] }}</span></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty"><i class="fas fa-inbox"></i> لا تسجيلات في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>التاريخ والوقت</th><th>الموظف</th><th>القسم</th><th>النوع</th><th>الوقت المعتمد</th>
                            <th>طريقة التسجيل</th><th>الجهاز</th><th>الموقع</th><th>تأخير (د)</th><th>مبكر (د)</th><th>النتيجة</th><th>سبب الرفض</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="rpt-num">{{ $row['date'] }} <strong>{{ $row['time'] }}</strong></td>
                                <td class="rpt-name">
                                    @if ($row['employee_id'])
                                        <a href="{{ route('hr.reports.attendance.punches', array_merge(request()->except(['page']), ['employee_id' => $row['employee_id']])) }}">{{ $row['employee'] }}</a>
                                    @else
                                        <span class="text-muted">{{ $row['employee'] }}</span>
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['department'] }}</span></td>
                                <td>{{ $row['type_label'] }}</td>
                                <td class="rpt-num">
                                    @if ($row['applied'])
                                        <strong>{{ $row['applied'] }}</strong>
                                        @if ($row['adjusted'])<div class="small text-muted">{{ $row['adjusted'] }}</div>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $row['channel_label'] }}<div class="small text-muted">{{ $row['method_label'] }}</div></td>
                                <td>{{ $row['source'] }}</td>
                                <td class="small">
                                    @if ($row['maps_url'])
                                        <a href="{{ $row['maps_url'] }}" target="_blank" rel="noopener">{{ $row['location'] }}</a>
                                    @else
                                        {{ $row['location'] }}
                                    @endif
                                </td>
                                <td class="rpt-num {{ $row['late'] ? 'rpt-neg' : '' }}">{{ $row['late'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['early'] ? 'rpt-neg' : '' }}">{{ $row['early'] ?: '—' }}</td>
                                <td><span class="rpt-pill {{ $row['result'] === 'accepted' ? 'rpt-pill-success' : 'rpt-pill-danger' }}">{{ $row['result'] === 'accepted' ? 'مقبول' : 'مرفوض' }}</span></td>
                                <td class="small">
                                    @if ($row['reason'])
                                        <strong>{{ $row['reason_title'] }}</strong>
                                        @if ($row['reason'] !== $row['reason_title'])<div class="text-muted">{{ $row['reason'] }}</div>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا تسجيلات في هذا المدى والنطاق</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if ($pager->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من {{ number_format($pager->total()) }}</small>
                {{ $pager->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'سجلات الحضور — '.$viewTitles[$reportView],
        'frInfo' => $periodLabel,
        'frHeaders' => match ($reportView) {
            'reasons' => ['#', 'سبب الرفض', 'نص آخر رسالة', 'عدد المرات', 'عدد الموظفين', 'بلا موظف', 'آخر مرة'],
            'employees' => ['#', 'الموظف', 'القسم', 'كل التسجيلات', 'حضور مقبول', 'انصراف مقبول', 'استراحات', 'مرفوضة', 'تأخير', 'انصراف مبكر', 'آخر تسجيل'],
            default => ['#', 'التاريخ والوقت', 'الموظف', 'القسم', 'النوع', 'الوقت المعتمد', 'طريقة التسجيل', 'الجهاز', 'الموقع', 'تأخير (د)', 'مبكر (د)', 'النتيجة', 'سبب الرفض'],
        },
    ])
@endsection
