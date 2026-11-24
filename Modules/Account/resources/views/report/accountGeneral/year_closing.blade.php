@extends('account::master')

@section('title')
    تقرير الإقفال السنوي
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الإقفال السنوي',
        'crumbs' => [
            'الرئيسية' => null,
            'التقارير' => route('GeneralAccountReports.index'),
            'تقرير الإقفال السنوي' => null,
        ],
    ])

    @php
        /*
            على نمط تقارير سندات الصرف والقبض («المصروفات حسب التصنيف»):
            بطاقةُ فلاترٍ بأزرار البحث والطباعة والتصدير، ثم بطاقاتُ المؤشرات،
            ثم جدولُ التقرير بأسلوب `report reports_currency` وتذييلُ «الإجمالي
            العام». و«نوع العرض» يبدّل الجدول: الملخّص حسب الفرع، والتفاصيل
            حسب الحساب — والطباعةُ والتصدير يتبعان المعروض.
        */
        $isSummary = request('view_type', 'details') === 'summary';
        $closed = $report['closed'] ?? false;
        $net = $report['net'] ?? 0;
        $retained = $report['retained'] ?? null;
        $retainedLabel = $retained ? trim(($retained->code ? $retained->code.' — ' : '').$retained->name) : 'غير مضبوط';
        $symbol = \App\Support\CurrencySettings::symbol();
        $showJournal = Route::has('journal.show');
        $stateLabel = [
            'active' => ['قائم', 'success'],
            'reversed' => ['معكوس', 'warning'],
            'reversal' => ['قيد عكسي', 'info'],
        ];

        // صفوفُ الحسابات كما تُعرض وتُطبع وتُصدَّر — سطرُ حساب الترحيل آخرَها
        $accountRows = collect($report['accounts'] ?? [])->map(fn ($r) => $r + ['kind_label' => $r['kind'] === 'revenue' ? 'إيراد' : 'مصروف'])->values();
        if ($retained && $accountRows->isNotEmpty()) {
            $accountRows->push([
                'code' => $retained->code,
                'name' => $retained->name,
                'kind_label' => $net >= 0 ? 'ترحيل الربح' : 'ترحيل الخسارة',
                'balance_debit' => 0,
                'balance_credit' => 0,
                'closing_debit' => $net < 0 ? -$net : 0,
                'closing_credit' => $net > 0 ? $net : 0,
                'is_retained' => true,
            ]);
        }
        $totals = [
            'balance_debit' => round($accountRows->sum('balance_debit'), 2),
            'balance_credit' => round($accountRows->sum('balance_credit'), 2),
            'closing_debit' => round($accountRows->sum('closing_debit'), 2),
            'closing_credit' => round($accountRows->sum('closing_credit'), 2),
        ];
        $money = fn ($v) => (float) $v ? number_format((float) $v, 2) : '';
    @endphp

    <div class="content-body">
        <!-- قسم الفلاتر -->
        <div class="card stk-filters no-print">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> خيارات البحث</h6>
            </div>
            <div>
                <div class="card-body">
                    <form method="GET" action="{{ route('GeneralAccountReports.yearClosing') }}" id="filterForm">
                        <div class="row">
                            <!-- السنة المالية -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>السنة المالية</label>
                                <select name="fiscal_year_id" class="form-control">
                                    @foreach ($years as $option)
                                        <option value="{{ $option->id }}" @selected($year && $year->id === $option->id)>
                                            {{ $option->name }} — {{ $option->isOpen() ? 'مفتوحة (معاينة)' : 'مقفلة' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- نوع العرض -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع العرض</label>
                                <div class="btn-group d-block" role="group">
                                    <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-list"></i> الملخص
                                    </button>
                                    <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-th-list"></i> التفاصيل
                                    </button>
                                </div>
                            </div>

                            <!-- البحث -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> بحث
                                    </button>
                                    <a href="{{ route('GeneralAccountReports.yearClosing') }}" class="btn btn-secondary">
                                        <i class="fa fa-refresh"></i> إعادة تعيين
                                    </a>
                                </div>
                            </div>

                            <!-- الطباعة والتصدير -->
                            @if ($report)
                                <div class="col-md-3 col-sm-6 mb-1">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="button" id="printBtn" class="btn btn-success">
                                            <i class="fa fa-print"></i> طباعة
                                        </button>
                                        <button type="button" id="exportExcel" class="btn btn-info">
                                            <i class="fa fa-file-excel-o"></i> Excel
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if (! $report)
            <div class="card mt-2">
                <div class="card-body">
                    <div class="stk-empty">
                        <span class="stk-empty__icon"><i class="feather icon-calendar"></i></span>
                        <h6 class="stk-empty__title">لا سنة مالية معرَّفة</h6>
                        @if (Route::has('AccountingPeriods.index'))
                            <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-sm btn-outline-primary mt-1">أضف سنة مالية</a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- السنة المفتوحة: معاينةٌ وأسبابُ منع إقفالها --}}
            @unless ($closed)
                <div class="alert alert-warning d-block no-print">
                    <i class="feather icon-eye"></i>
                    السنة <strong>{{ $year->name }}</strong> مفتوحة — الأرقام <strong>معاينة</strong> لما سيُرحَّل عند إقفالها، لا قيودٌ مُنشأة.
                    @if (! empty($readiness['blockers']))
                        <ul class="mb-0 mt-1">
                            @foreach ($readiness['blockers'] as $blocker)
                                <li>{{ $blocker['message'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endunless

            <!-- Statistics Cards -->
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                    <span class="stk-metric__label">إجمالي الإيرادات ({{ $symbol }})</span>
                    <span class="stk-metric__value">{{ number_format($report['revenue'], 2) }}</span>
                </div>

                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                    <span class="stk-metric__label">إجمالي المصروفات ({{ $symbol }})</span>
                    <span class="stk-metric__value">{{ number_format($report['expenses'], 2) }}</span>
                </div>

                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                    <span class="stk-metric__label">{{ $net >= 0 ? 'صافي الربح المُرحَّل' : 'صافي الخسارة المُرحَّلة' }} ({{ $symbol }})</span>
                    <span class="stk-metric__value {{ $net >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format(abs($net), 2) }}</span>
                </div>

                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                    <span class="stk-metric__label">{{ $closed ? 'قيود الإقفال' : 'قيود الإقفال المتوقّعة' }}</span>
                    <span class="stk-metric__value">{{ $closed ? $report['entries']->count() : $report['branches']->count() }}</span>
                </div>
            </div>

            <!-- Report Table -->
            <div class="card mt-2" id="reportCard">
                <div class="card-content">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                            <h6 class="stk-table-head__title">
                                <i class="feather {{ $isSummary ? 'icon-git-branch' : 'icon-list' }}"></i>
                                {{ $isSummary ? 'الإقفال حسب الفرع' : 'الحسابات المُقفلة' }}
                            </h6>
                            <div class="text-muted">
                                السنة {{ $year->name }}: {{ $year->start_date->format('d/m/Y') }} إلى {{ $year->end_date->format('d/m/Y') }}
                                · {{ $closed ? 'مقفلة' : 'مفتوحة' }}
                                @if ($closed && $report['closed_at'])
                                    في {{ $report['closed_at'] }}{{ $report['closed_by'] ? ' بواسطة '.$report['closed_by'] : '' }}
                                @endif
                                · حساب الترحيل: {{ $retainedLabel }}
                            </div>
                        </div>

                        <div class="table-responsive">
                            @if ($isSummary)
                                {{-- الملخّص: الفروع --}}
                                <table class="table report reports_currency no-collapse" id="reportTable">
                                    <thead>
                                        <tr class="report-results-head">
                                            <th class="first-column">#</th>
                                            <th class="first-column">الفرع</th>
                                            <th class="first-column">الإيرادات</th>
                                            <th class="first-column">المصروفات</th>
                                            <th class="first-column">الصافي</th>
                                            <th class="first-column">قيد الإقفال</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($report['branches'] as $i => $row)
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $row['branch'] }}</td>
                                                <td>{{ number_format($row['revenue'], 2) }}</td>
                                                <td>{{ number_format($row['expenses'], 2) }}</td>
                                                <td class="{{ $row['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['net'], 2) }}</td>
                                                <td>
                                                    @if ($row['entry_id'] && $showJournal)
                                                        <a href="{{ route('journal.show', $row['entry_id']) }}" class="text-primary">{{ $row['entry_number'] }}</a>
                                                    @else
                                                        {{ $row['entry_number'] ?? '-' }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6">
                                                    <div class="stk-empty">
                                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                        <h6 class="stk-empty__title">لا أرصدة إيرادات أو مصروفات في هذه السنة</h6>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($report['branches']->isNotEmpty())
                                        <tfoot>
                                            <tr class="section-net">
                                                <td class="first-column" colspan="2">الإجمالي العام</td>
                                                <td>{{ number_format($report['revenue'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td>{{ number_format($report['expenses'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td>{{ number_format($net, 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            @else
                                {{-- التفاصيل: الحسابات --}}
                                <table class="table report reports_currency no-collapse" id="reportTable">
                                    <thead>
                                        <tr class="report-results-head">
                                            <th class="first-column">#</th>
                                            <th class="first-column">رقم الحساب</th>
                                            <th class="first-column">اسم الحساب</th>
                                            <th class="first-column">النوع</th>
                                            <th class="first-column">رصيد مدين</th>
                                            <th class="first-column">رصيد دائن</th>
                                            <th class="first-column">إقفال مدين</th>
                                            <th class="first-column">إقفال دائن</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($accountRows as $i => $row)
                                            <tr @class(['fw-bold' => ! empty($row['is_retained'])])>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $row['code'] }}</td>
                                                <td>{{ $row['name'] }}</td>
                                                <td>{{ $row['kind_label'] }}</td>
                                                <td>{{ $money($row['balance_debit']) }}</td>
                                                <td>{{ $money($row['balance_credit']) }}</td>
                                                <td>{{ $money($row['closing_debit']) }}</td>
                                                <td>{{ $money($row['closing_credit']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8">
                                                    <div class="stk-empty">
                                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                        <h6 class="stk-empty__title">لا حسابات إيرادات أو مصروفات لها رصيد في هذه السنة</h6>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($accountRows->isNotEmpty())
                                        <tfoot>
                                            <tr class="section-net">
                                                <td class="first-column" colspan="4">الإجمالي العام</td>
                                                <td>{{ number_format($totals['balance_debit'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td>{{ number_format($totals['balance_credit'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td>{{ number_format($totals['closing_debit'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                                <td>{{ number_format($totals['closing_credit'], 2) }} <span class="sar_symbol">{{ $symbol }}</span></td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- المطابقة والسجلّ — بأسلوب الجدول نفسه --}}
            @if ($closed)
                <div class="card mt-2">
                    <div class="card-content">
                        <div class="card-body">
                            <h6 class="stk-table-head__title mb-2"><i class="feather icon-check-square"></i> مطابقة الإقفال</h6>
                            <div class="table-responsive">
                                <table class="table report reports_currency no-collapse">
                                    <thead>
                                        <tr class="report-results-head">
                                            <th class="first-column">الفحص</th>
                                            <th class="first-column">النتيجة</th>
                                            <th class="first-column">التفصيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($report['checks'] as $check)
                                            <tr>
                                                <td>{{ $check['label'] }}</td>
                                                <td class="{{ $check['ok'] ? 'text-success' : 'text-danger' }}">
                                                    <i class="feather {{ $check['ok'] ? 'icon-check-circle' : 'icon-x-circle' }}"></i>
                                                    {{ $check['ok'] ? 'مطابق' : 'غير مطابق' }}
                                                </td>
                                                <td>{{ $check['detail'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card mt-2">
                <div class="card-content">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                            <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> سجلّ الإقفال وإعادة الفتح</h6>
                            <div class="text-muted">إعادةُ الفتح تعكس قيود الإقفال ولا تحذفها</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table report reports_currency no-collapse">
                                <thead>
                                    <tr class="report-results-head">
                                        <th class="first-column">#</th>
                                        <th class="first-column">رقم القيد</th>
                                        <th class="first-column">الحالة</th>
                                        <th class="first-column">الفرع</th>
                                        <th class="first-column">تاريخ القيد</th>
                                        <th class="first-column">أُنشئ في</th>
                                        <th class="first-column">بواسطة</th>
                                        <th class="first-column">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($report['history'] as $i => $row)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                @if ($showJournal)
                                                    <a href="{{ route('journal.show', $row['id']) }}" class="text-primary">{{ $row['number'] }}</a>
                                                @else
                                                    {{ $row['number'] }}
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light-{{ $stateLabel[$row['state']][1] }} text-{{ $stateLabel[$row['state']][1] }}">{{ $stateLabel[$row['state']][0] }}</span>
                                                @if ($row['reverses'])
                                                    <small class="text-muted d-block">يعكس {{ $row['reverses'] }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $row['branch'] }}</td>
                                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                            <td>{{ $row['created_at'] }}</td>
                                            <td>{{ $row['by'] }}</td>
                                            <td>{{ number_format($row['total'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <div class="stk-empty">
                                                    <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                    <h6 class="stk-empty__title">لم تُقفل هذه السنة بعد</h6>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
@if ($report)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('#exportExcel').on('click', function(e) { e.preventDefault(); exportYearClosingReport(); });
            $('#printBtn').on('click', function(e) { e.preventDefault(); printYearClosingReport(); });
        });

        function fmt(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        const yearClosing = {
            isSummary: @json($isSummary),
            year: @json($year->name),
            range: @json($year->start_date->format('d/m/Y').' إلى '.$year->end_date->format('d/m/Y')),
            status: @json($closed ? 'مقفلة' : 'مفتوحة — معاينة'),
            closedAt: @json($report['closed_at']),
            closedBy: @json($report['closed_by']),
            retained: @json($retainedLabel),
            revenue: {{ (float) $report['revenue'] }},
            expenses: {{ (float) $report['expenses'] }},
            net: {{ (float) $net }},
            entries: {{ $closed ? $report['entries']->count() : $report['branches']->count() }},
            accounts: @json($accountRows),
            totals: @json($totals),
            branches: @json($report['branches']),
            checks: @json($report['checks']),
            history: @json($report['history']),
        };
        const stateNames = { active: 'قائم', reversed: 'معكوس', reversal: 'قيد عكسي' };
        const netLabel = yearClosing.net >= 0 ? 'صافي الربح المُرحَّل' : 'صافي الخسارة المُرحَّلة';

        function reportInfo(dateLabel) {
            const info = {
                'السنة المالية': yearClosing.year + ' (' + yearClosing.range + ')',
                'الحالة': yearClosing.status,
                'حساب الترحيل': yearClosing.retained,
            };
            if (yearClosing.closedAt) {
                info['أُقفلت في'] = yearClosing.closedAt + (yearClosing.closedBy ? ' — ' + yearClosing.closedBy : '');
            }
            info[dateLabel] = new Date().toLocaleDateString('ar-SA');
            return info;
        }

        // ===== تصدير Excel احترافي =====
        function exportYearClosingReport() {
            const currency = ' ' + window.AppCurrency.symbol;

            const table = yearClosing.isSummary ? {
                title: 'الإقفال حسب الفرع',
                headers: ['#', 'الفرع', 'الإيرادات', 'المصروفات', 'الصافي', 'قيد الإقفال'],
                widths: [6, 28, 18, 18, 18, 20],
                rows: yearClosing.branches.map((b, i) => ({
                    values: [i + 1, b.branch, fmt(b.revenue), fmt(b.expenses), fmt(b.net), b.entry_number || '-'],
                    alignments: ['center', 'right', 'center', 'center', 'center', 'center'],
                    cellStyles: [null, null, { color: 'success' }, { color: 'danger' }, { bold: true, color: b.net >= 0 ? 'success' : 'danger' }, null]
                })),
                summary: ['', 'الإجمالي العام', fmt(yearClosing.revenue), fmt(yearClosing.expenses), fmt(yearClosing.net), '']
            } : {
                title: 'الحسابات المُقفلة',
                headers: ['#', 'رقم الحساب', 'اسم الحساب', 'النوع', 'رصيد مدين', 'رصيد دائن', 'إقفال مدين', 'إقفال دائن'],
                widths: [5, 14, 30, 14, 15, 15, 15, 15],
                rows: yearClosing.accounts.map((r, i) => ({
                    values: [i + 1, r.code || '-', r.name, r.kind_label,
                        r.balance_debit ? fmt(r.balance_debit) : '', r.balance_credit ? fmt(r.balance_credit) : '',
                        r.closing_debit ? fmt(r.closing_debit) : '', r.closing_credit ? fmt(r.closing_credit) : ''],
                    alignments: ['center', 'center', 'right', 'center', 'center', 'center', 'center', 'center'],
                    cellStyles: [null, null, r.is_retained ? { bold: true } : null, null, null, null,
                        { color: 'success', bold: !!r.is_retained }, { color: 'danger', bold: !!r.is_retained }]
                })),
                summary: ['', '', '', 'الإجمالي العام', fmt(yearClosing.totals.balance_debit), fmt(yearClosing.totals.balance_credit), fmt(yearClosing.totals.closing_debit), fmt(yearClosing.totals.closing_credit)]
            };

            const config = {
                fileName: 'تقرير_الإقفال_السنوي_' + yearClosing.year,
                mainTitle: 'تقرير الإقفال السنوي — ' + yearClosing.year,
                subTitle: 'نظام المحاسبة المتكامل',
                reportInfo: reportInfo('تاريخ التصدير'),
                statisticsCards: [
                    { title: 'إجمالي الإيرادات', value: fmt(yearClosing.revenue), unit: currency, titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                    { title: 'إجمالي المصروفات', value: fmt(yearClosing.expenses), unit: currency, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
                    { title: netLabel, value: fmt(Math.abs(yearClosing.net)), unit: currency, titleColor: 'primary', valueColor: yearClosing.net >= 0 ? 'success' : 'danger', backgroundColor: 'grayBg', borderColor: 'primary' },
                    { title: 'قيود الإقفال', value: yearClosing.entries, unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' }
                ],
                tableTitle: table.title,
                tableHeaders: table.headers,
                columnWidths: table.widths,
                tableData: table.rows,
                summaryRows: [{ values: table.summary, cellStyles: table.summary.map(v => v ? { bold: true } : null) }],
                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص الإقفال السنوي — ' + yearClosing.year,
                    tables: [
                        { title: 'النتيجة', headerColor: 'primary', data: [
                            { label: 'إجمالي الإيرادات', value: fmt(yearClosing.revenue), unit: currency, color: 'success' },
                            { label: 'إجمالي المصروفات', value: fmt(yearClosing.expenses), unit: currency, color: 'danger' },
                            { label: netLabel + ' إلى ' + yearClosing.retained, value: fmt(Math.abs(yearClosing.net)), unit: currency, color: yearClosing.net >= 0 ? 'success' : 'danger' }
                        ]},
                        { title: yearClosing.isSummary ? 'الحسابات المُقفلة (صافي الإقفال)' : 'الإقفال حسب الفرع (الصافي)', headerColor: 'success',
                          data: yearClosing.isSummary
                            ? yearClosing.accounts.map(r => ({ label: (r.code || '') + ' — ' + r.name, value: fmt((r.closing_debit || 0) - (r.closing_credit || 0)), unit: currency }))
                            : yearClosing.branches.map(b => ({ label: b.branch + (b.entry_number ? ' — ' + b.entry_number : ''), value: fmt(b.net), unit: currency, color: b.net >= 0 ? 'success' : 'danger' })) },
                        { title: 'مطابقة الإقفال', headerColor: 'primary', data: yearClosing.checks.map(c => ({
                            label: c.label, value: c.ok ? '✔ مطابق' : '✘ غير مطابق', unit: c.detail, color: c.ok ? 'success' : 'danger'
                        }))},
                        { title: 'سجلّ الإقفال وإعادة الفتح', headerColor: 'primary', data: yearClosing.history.map(h => ({
                            label: h.number + ' — ' + h.branch, value: fmt(h.total), unit: stateNames[h.state] + ' • ' + (h.created_at || ''),
                            color: h.state === 'active' ? 'success' : (h.state === 'reversed' ? 'warning' : 'primary')
                        }))}
                    ].filter(t => t.data.length > 0)
                },
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير الإقفال السنوي بنجاح!',
                errorMessage: 'حدث خطأ أثناء التصدير'
            };

            exportToExcelProfessional(config);
        }

        // ===== الطباعة المخصصة =====
        function printYearClosingReport() {
            const currency = ' ' + window.AppCurrency.symbol;

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير الإقفال السنوي — ' + yearClosing.year + (yearClosing.isSummary ? ' (حسب الفرع)' : ''),
                infoCards: [
                    { label: 'إجمالي الإيرادات', value: fmt(yearClosing.revenue) + currency, class: 'text-success' },
                    { label: 'إجمالي المصروفات', value: fmt(yearClosing.expenses) + currency, class: 'text-danger' },
                    { label: netLabel, value: fmt(Math.abs(yearClosing.net)) + currency, class: yearClosing.net >= 0 ? 'text-success' : 'text-danger' },
                    { label: 'قيود الإقفال', value: yearClosing.entries }
                ],
                filterInfo: reportInfo('تاريخ الطباعة'),
                tableHeaders: yearClosing.isSummary
                    ? ['#', 'الفرع', 'الإيرادات', 'المصروفات', 'الصافي', 'قيد الإقفال']
                    : ['#', 'رقم الحساب', 'اسم الحساب', 'النوع', 'رصيد مدين', 'رصيد دائن', 'إقفال مدين', 'إقفال دائن'],
                columnWidths: yearClosing.isSummary
                    ? ['5%', '30%', '16%', '16%', '16%', '17%']
                    : ['4%', '10%', '28%', '10%', '12%', '12%', '12%', '12%'],
                descriptionColumn: yearClosing.isSummary ? 1 : 2,
                windowWidth: 1400,
                windowHeight: 900
            });
        }
    </script>
@endif
@endsection
