{{--
    مطابقة الرواتب بالمحاسبة — بتصميم تقارير العملاء (rpt / ag).

    المتحكّم: `PayrollReportController::reconciliation`. لكلّ راتبٍ مدفوع: مصروفُه
    (الأساسي + البدلات − الخصم) مقابلَ المدين المرحَّل على حساب مصروف الرواتب في
    قيد الاستحقاق. ثم قيودٌ على حساب المصروف بلا راتبٍ يقابلها.
--}}
@extends('hr::master')

@section('title')
    مطابقة الرواتب بالمحاسبة
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $canOpenRun = Route::has('payroll-runs.show') && hasPermission('disbursements.show');
    $balanced = abs($totals['variance']) < 0.01;
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'balance-scale',
        'headTitle' => 'مطابقة الرواتب بالمحاسبة',
        'headSubtitle' => 'هل كل راتب مدفوع مسجَّل في المحاسبة بالمبلغ نفسه؟ — الرواتب المدفوعة مقابل ما رُحِّل على حساب مصروف الرواتب',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'hr-reports.payroll-reconciliation',
        'headGuide' => [
            'title' => 'كيف تُقرأ المطابقة؟',
            'lead' => 'حساب المصروف: '.($expenseAccount ? $expenseAccount->name.' ('.$expenseAccount->code.')' : 'غير مربوط في «إعدادات الحسابات المحاسبية» — فكل راتب سيظهر «بلا قيد»').'.',
            'blocks' => [[
                '«مصروف الراتب» = الأساسي + البدلات − الخصم (قسط السلفة لا يُنقص المصروف: هو سداد دين).',
                '«مطابق» = قيد الاستحقاق موجود ومبلغه على حساب المصروف يساوي مصروف الراتب. «فيه فرق» = القيد موجود بمبلغ مختلف. «بلا قيد» = لم يُرحَّل.',
                'الرواتب المدفوعة وحدها تدخل هنا — غير المدفوع لا قيد له بعد.',
                'الجدول الثاني: قيود على حساب مصروف الرواتب لا يقابلها راتب في هذه الفترة (قيد يدوي أو راتب خارج المدى).',
            ]],
            'facts' => [],
        ],
        'periodContext' => $periodContext,
    ])

    @include('hr::reports.payroll._run_filters', [
        'action' => route('hr.reports.payroll.reconciliation'),
    ])

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">مصروف الرواتب المدفوعة</div><div class="ag-kpi__value"><bdi>{{ $money($totals['payroll_expense']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($totals['count']) }} راتب · صافيها {{ $money($totals['payroll_net']) }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-money-check-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">المرحَّل على حساب المصروف</div><div class="ag-kpi__value"><bdi>{{ $money($totals['posted_expense']) }}</bdi></div><div class="ag-kpi__sub">مطابق {{ $totals['matched'] }} · فيه فرق {{ $totals['variance_count'] }} · بلا قيد {{ $totals['unposted'] }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-book"></i></span>
        </div>
        <div class="ag-kpi" style="--c:{{ $balanced ? '#10b981' : '#ef4444' }}">
            <div><div class="ag-kpi__label">الفرق</div><div class="ag-kpi__value"><bdi>{{ $money($totals['variance']) }}</bdi></div><div class="ag-kpi__sub">{{ $balanced ? 'لا فرق — المحاسبة مطابقة' : 'راجع الصفوف «فيه فرق» و«بلا قيد»' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">قيود بلا راتب</div><div class="ag-kpi__value"><bdi>{{ $money($totals['orphans']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($orphans->count()) }} قيد على حساب المصروف</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> الرواتب المدفوعة
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($rows->total()) }} راتب</span>
            </h4>
            <div class="ag-tools no-print">
                @include('hr::reports.partials.rpt-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td class="rpt-name">
                                @if ($canOpenRun)
                                    <a href="{{ route('payroll-runs.show', $r['id']) }}" target="_blank" rel="noopener">{{ $r['employee'] }}</a>
                                @else
                                    {{ $r['employee'] }}
                                @endif
                                <div class="text-muted small">{{ $r['department'] }}</div>
                            </td>
                            <td class="rpt-num">{{ $r['period'] }}</td>
                            <td class="rpt-num"><strong>{{ $money($r['payroll_expense']) }}</strong></td>
                            <td class="rpt-num">{{ $money($r['payroll_net']) }}</td>
                            <td class="rpt-num">{{ $r['payroll_advances'] > 0 ? $money($r['payroll_advances']) : '—' }}</td>
                            <td class="rpt-num">{{ $money($r['posted_expense']) }}</td>
                            <td class="rpt-num {{ abs($r['variance']) >= 0.01 ? 'rpt-neg' : '' }}">{{ abs($r['variance']) >= 0.01 ? $money($r['variance']) : '—' }}</td>
                            <td>{{ $r['has_accrual'] ? 'نعم' : 'لا' }}</td>
                            <td>{{ $r['has_payment'] ? 'نعم' : 'لا' }}</td>
                            <td><span class="rpt-pill {{ $r['tone'] }}">{{ $r['state_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($headers) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا رواتب مدفوعة في هذا المدى والنطاق</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->total() > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>الإجمالي</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['payroll_expense']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['payroll_net']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['payroll_advances']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['posted_expense']) }}</strong></td>
                            <td class="rpt-num"><strong>{{ $money($totals['variance']) }}</strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $rows->firstItem() }} إلى {{ $rows->lastItem() }} من {{ number_format($rows->total()) }}</small>
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="rpt-card">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-exclamation-triangle"></i> قيود على حساب مصروف الرواتب بلا راتب يقابلها
                <span class="rpt-pill {{ $orphans->isEmpty() ? 'rpt-pill-success' : 'rpt-pill-warning' }}">{{ number_format($orphans->count()) }} قيد</span>
            </h4>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table">
                <thead>
                    <tr><th>#</th><th>التاريخ</th><th>المرجع</th><th>البيان</th><th>مدين</th></tr>
                </thead>
                <tbody>
                    @forelse ($orphans as $o)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="rpt-num">{{ $o['date'] }}</td>
                            <td class="rpt-num">{{ $o['reference'] }}</td>
                            <td>{{ $o['description'] }}</td>
                            <td class="rpt-num"><strong>{{ $money($o['debit']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="rpt-empty"><i class="fas fa-check-circle"></i> لا قيود بلا راتب في هذا المدى</td></tr>
                    @endforelse
                </tbody>
                @if ($orphans->isNotEmpty())
                    <tfoot>
                        <tr><td colspan="4"><strong>الإجمالي</strong></td><td class="rpt-num"><strong>{{ $money($totals['orphans']) }}</strong></td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => 'مطابقة الرواتب بالمحاسبة',
        'frInfo' => $periodLabel,
        'frHeaders' => $headers,
    ])
@endsection
