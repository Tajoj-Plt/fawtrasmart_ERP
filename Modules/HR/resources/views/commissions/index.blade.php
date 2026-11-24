{{--
    سجلّ العمولات — بتصميم تقارير العملاء (rpt / ag).
    المتحكّم: `CommissionsReportController::index`. نوعان (أهداف المبيعات / التحصيلات)
    وتبويبان (المصروفة / قيد الانتظار). التصديرُ والطباعةُ لكلّ صفوف التبويب
    (`export=rows`)، وتصديرُ Excel المفصَّل باقٍ على `hr.commissions.export`.
--}}
@extends('hr::master')

@section('title', 'سجل العمولات')

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    // الطباعة بالقوالب — تظهر حين يُسجَّل مسارها
    $commissionPrintRoute = collect(['hr.commissions.print', 'hr.commission.print', 'hr.commissions-report.print'])
        ->first(fn ($r) => \Illuminate\Support\Facades\Route::has($r));
    $isCollections = ($kind ?? 'sales') === \App\Models\TargetSetting::TYPE_COLLECTIONS;
    $isPaid = $tab !== 'pending';

    // صلاحياتُ الصفوف — مرّةً للصفحة
    $comCan = [
        'show' => hasPermission('commissions.show'),
        // قالبُ سند العمولة يقرأ تسويةَ أهداف المبيعات وحدها
        'print' => ! $isCollections && $commissionPrintRoute && hasPermission('commissions.print'),
        'export' => hasPermission('commissions.export'),
    ];

    // الرابطُ يحمل النوعَ والتبويب والفلاتر — فالتنقّلُ بينها لا يُسقط ما اختير
    $link = fn (array $change) => route('hr.commissions-report.index', array_filter(
        $change + request()->except('page'),
        fn ($value) => $value !== null && $value !== ''
    ));

    $targetsRoute = $isCollections ? 'collections-targets.index' : 'sales-targets.index';
    $kindLabel = $isCollections ? 'أهداف التحصيلات' : 'أهداف المبيعات';
    $money = fn ($v) => \App\Support\CurrencySettings::format($v);

    $pendingLabels = \Modules\HR\Http\Controllers\CommissionsReportController::PENDING_LABELS;
    $pendingTone = [
        \App\Services\Targets\PendingCommissions::TREASURY_SHORT => 'rpt-pill-warning',
        \App\Services\Targets\PendingCommissions::NO_TREASURY => 'rpt-pill-warning',
        \App\Services\Targets\PendingCommissions::NO_EMPLOYEE => 'rpt-pill-danger',
        \App\Services\Targets\PendingCommissions::AWAITING_RUN => 'rpt-pill-secondary',
        \App\Services\Targets\PendingCommissions::MANUAL => 'rpt-pill-secondary',
        \App\Services\Targets\PendingCommissions::NOT_DUE => 'rpt-pill-secondary',
    ];

    $rangeLabel = request('from_date') || request('to_date')
        ? 'صُرفت '.(request('from_date') ? 'من '.request('from_date').' ' : '').(request('to_date') ? 'إلى '.request('to_date') : '')
        : 'كل التواريخ';
@endphp

<div class="rpt">
    @include('hr::reports.partials.rpt-head', [
        'headIcon' => 'percent',
        'headTitle' => 'سجل العمولات',
        'headSubtitle' => 'ما صُرف من عمولات '.$kindLabel.'، وما استُحقّ ولم يُصرف بعد',
        'headPeriod' => $rangeLabel,
        'headGuideKey' => 'hr-commissions.index',
        'headGuide' => [
            'title' => 'ما هذه الشاشة، ومن أين تأتي أرقامها؟',
            'lead' => 'العمولات لا تُحسب هنا ولا تُصرف: تُحسب وتُصرف بتسوية الأهداف في المبيعات، وتُقرأ هنا.',
            'blocks' => [[
                'العمولة = صافي مبيعات الموظف (أو تحصيله) في الفترة × نسبة أعلى شريحة بلغها إنجازه من هدفه، ولا تُسوّى الفترة نفسها مرتين.',
                'لكل عمولة مصروفة سند صرف وقيد: مدين حساب العمولة / دائن الخزينة. و«بانتظار اعتماد القيد» = كُتب السند والقيد والقيد ينتظر الاعتماد.',
                '«قيد الانتظار» = من بلغ مستوى عمولة ولم تُصرف له: خزينته لا تكفي، أو لا خزينة له، أو لم يحلّ موعد التسوية؛ ومن مدة هدفه جارية يظهر بما تراكم له حتى اليوم.',
                'القائمة والمؤشرات في حدود نطاقك من الموظفين.',
            ]],
            'facts' => [],
        ],
        'periodContext' => [],
    ])

    @if (! $setting->commission_account_id)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            حساب العمولة غير مضبوط في إعدادات {{ $kindLabel }} — لا تُسوّى عمولةٌ حتى يُضبط.
            @permission('sales.target_settings.view')
                <a href="{{ route('sales-targets.settings', $kind) }}" class="alert-link">ضبطه في إعدادات الأهداف</a>
            @endpermission
        </div>
    @endif

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('hr.commissions-report.index') }}" id="filterForm">
                <input type="hidden" name="kind" value="{{ $kind }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label>نوع الأهداف</label>
                        <div class="btn-group d-flex" role="group">
                            <a href="{{ $link(['kind' => 'sales', 'target_id' => null]) }}" class="btn {{ $isCollections ? 'btn-outline-primary' : 'btn-primary' }}"><i class="fas fa-chart-line"></i> المبيعات</a>
                            <a href="{{ $link(['kind' => 'collections', 'target_id' => null]) }}" class="btn {{ $isCollections ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-coins"></i> التحصيلات</a>
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label>الحالة</label>
                        <div class="btn-group d-flex" role="group">
                            <a href="{{ $link(['tab' => 'paid']) }}" class="btn {{ $isPaid ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-check-circle"></i> المصروفة ({{ $stats['filtered_count'] }})</a>
                            <a href="{{ $link(['tab' => 'pending']) }}" class="btn {{ ! $isPaid ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-clock"></i> قيد الانتظار ({{ $pending->count() }})</a>
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">كل الموظفين</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}" @selected((string) request('employee_id') === (string) $emp->id)>{{ $emp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="target_id">الهدف</label>
                        <select name="target_id" id="target_id" class="form-control rpt-select2">
                            <option value="">كل الأهداف</option>
                            @foreach ($targets as $target)
                                <option value="{{ $target->id }}" @selected((string) request('target_id') === (string) $target->id)>{{ $target->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($isPaid)
                        <div class="rpt-field">
                            <label for="from_date">تاريخ الصرف</label>
                            <div class="ag-range">
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date') }}">
                                <span class="ag-range__sep">إلى</span>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date') }}">
                            </div>
                        </div>

                        <div class="rpt-field">
                            <label for="commission_type">دورة العمولة</label>
                            <select name="commission_type" id="commission_type" class="form-control">
                                <option value="">الكل</option>
                                <option value="monthly" @selected(request('commission_type') === 'monthly')>شهرية</option>
                                <option value="weekly" @selected(request('commission_type') === 'weekly')>أسبوعية</option>
                            </select>
                        </div>

                        <div class="rpt-field">
                            <label for="state">حالة القيد</label>
                            <select name="state" id="state" class="form-control">
                                <option value="">الكل</option>
                                <option value="posted" @selected(request('state') === 'posted')>مصروفة</option>
                                <option value="pending_approval" @selected(request('state') === 'pending_approval')>بانتظار الاعتماد</option>
                            </select>
                        </div>
                    @endif
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('hr.commissions-report.index', ['kind' => $kind, 'tab' => $tab]) }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                        @permission('sales.targets.settle')
                            @if (Route::has($targetsRoute))
                                <a href="{{ route($targetsRoute) }}" class="btn ag-btn-soft"><i class="fas fa-bullseye"></i> {{ $kindLabel }} وتسويتها</a>
                            @endif
                        @endpermission
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">المصروف (حسب الفلتر)</div><div class="ag-kpi__value"><bdi>{{ $money($stats['filtered_total']) }}</bdi></div><div class="ag-kpi__sub">{{ number_format($stats['filtered_count']) }} عمولة</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">عمولات شهرية</div><div class="ag-kpi__value"><bdi>{{ $money($stats['total_monthly']) }}</bdi></div><div class="ag-kpi__sub">{{ $stats['count_monthly'] }} عمولة · أسبوعية {{ $money($stats['total_weekly']) }} ({{ $stats['count_weekly'] }})</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-calendar-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">مستحقة لم تُصرف</div><div class="ag-kpi__value"><bdi>{{ $money($stats['pending_due_total']) }}</bdi></div><div class="ag-kpi__sub">{{ $stats['pending_due_count'] }} عمولة{{ $stats['pending_running_count'] ? ' · متراكمة لأهداف جارية '.$money($stats['pending_running_total']) : '' }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div><div class="ag-kpi__label">بانتظار اعتماد القيد</div><div class="ag-kpi__value"><bdi>{{ number_format($stats['pending_approval']) }}</bdi></div><div class="ag-kpi__sub">سندها صدر وقيدها ينتظر</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-pause-circle"></i></span>
        </div>
    </div>

    @include('hr::reports.partials.rpt-charts')

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isPaid ? 'العمولات المصروفة' : 'عمولات قيد الانتظار' }} — {{ $kindLabel }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isPaid ? number_format($commissions->total()) : number_format($pending->count()) }} عمولة</span>
            </h4>
            <div class="ag-tools no-print">
                @if ($isPaid)
                    @include('hr::reports.partials.rpt-page-size')
                @endif
                @if ($comCan['export'])
                    <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                    <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
                    @if ($isPaid && Route::has('hr.commissions.export'))
                        <a href="{{ route('hr.commissions.export', request()->except('page')) }}" class="btn btn-sm ag-btn-soft" title="ملف Excel مفصَّل بأرقام القيود ومن صرفها"><i class="fas fa-file-download"></i> Excel مفصَّل</a>
                    @endif
                @endif
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($isPaid)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>الموظف</th><th>الهدف</th><th>الدورة</th><th>الفترة</th><th>تاريخ الصرف</th>
                            <th>العمولة</th><th>طريقة الصرف</th><th>الحالة</th>
                            @if ($comCan['print'])<th class="no-print"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commissions as $commission)
                            @php
                                $paidAt = $commission->direct_paid_at ?? $commission->settled_date;
                                $awaiting = \Modules\HR\Http\Controllers\CommissionsReportController::awaitsJournalApproval($commission, $journalStatus);
                            @endphp
                            <tr>
                                <td>{{ $commissions->firstItem() + $loop->index }}</td>
                                <td class="rpt-name">
                                    @if ($comCan['show'])
                                        <a href="{{ route('hr.commissions-report.show', [$commission->id, 'kind' => $kind]) }}">{{ $commission->employee->full_name ?? 'غير محدد' }}</a>
                                    @else
                                        {{ $commission->employee->full_name ?? 'غير محدد' }}
                                    @endif
                                </td>
                                <td>{{ $commission->target->name ?? '—' }}</td>
                                <td>{{ optional($commission->target)->commission_period === 'weekly' ? 'أسبوعية' : 'شهرية' }}</td>
                                <td class="rpt-num">{{ optional($commission->from_date)->format('Y-m-d') }} ← {{ optional($commission->to_date)->format('Y-m-d') }}</td>
                                <td class="rpt-num">{{ $paidAt ? \Carbon\Carbon::parse($paidAt)->format('Y-m-d') : '—' }}</td>
                                <td class="rpt-num"><strong>{{ $money($commission->commission_amount) }}</strong></td>
                                <td>{{ $commission->paid_directly ? 'صرف مباشر' : ($commission->direct_journal_entry_id ? 'قيد صرف' : 'تسوية على الراتب') }}</td>
                                <td>
                                    @if ($awaiting)
                                        <span class="rpt-pill rpt-pill-warning">بانتظار اعتماد القيد</span>
                                    @else
                                        <span class="rpt-pill rpt-pill-success">{{ $commission->paid_directly ? 'صرف مباشر' : 'مصروفة' }}</span>
                                    @endif
                                </td>
                                @if ($comCan['print'])
                                    <td class="no-print"><a href="{{ route($commissionPrintRoute, $commission->id) }}" target="_blank" rel="noopener" class="btn btn-sm ag-btn-soft" title="طباعة السند"><i class="fas fa-print"></i></a></td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $comCan['print'] ? 10 : 9 }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا عمولات مصروفة — تظهر هنا بعد تسوية {{ $kindLabel }} وصرفها</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th><th>الموظف</th><th>الهدف</th><th>الفترة</th><th>المحقَّق</th><th>نسبة الإنجاز</th>
                            <th>العمولة</th><th>خزينة الصرف</th><th>سبب الانتظار</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{{ $row['employee_name'] }}</td>
                                <td>{{ $row['target_name'] }}</td>
                                <td class="rpt-num">{{ $row['from'] }} ← {{ $row['to'] }}</td>
                                <td class="rpt-num">{{ $money($row['achieved']) }}<div class="small text-muted">من {{ $money($row['target_amount']) }}</div></td>
                                <td class="rpt-num">{{ $row['percentage'] }}%</td>
                                <td class="rpt-num"><strong>{{ $money($row['commission']) }}</strong></td>
                                <td>
                                    {{ $row['treasury_name'] ?? '—' }}
                                    @if ($row['treasury_balance'] !== null)<div class="small text-muted">الرصيد {{ $money($row['treasury_balance']) }}</div>@endif
                                </td>
                                <td>
                                    <span class="rpt-pill {{ $pendingTone[$row['state']] ?? 'rpt-pill-secondary' }}">{{ $pendingLabels[$row['state']] ?? $row['state'] }}</span>
                                    @if ($row['due_on'])
                                        <div class="small text-muted">{{ $row['state'] === \App\Services\Targets\PendingCommissions::NOT_DUE ? 'تُصرف بعد' : 'حلّ موعدها' }} {{ $row['due_on'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty"><i class="fas fa-check-circle"></i> لا عمولات قيد الانتظار — كل من بلغ مستوى عمولة في الأهداف النشطة صُرفت له</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if ($isPaid && $commissions->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $commissions->firstItem() }} إلى {{ $commissions->lastItem() }} من {{ number_format($commissions->total()) }}</small>
                {{ $commissions->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('hr::reports.partials.rpt-scripts', [
        'frTitle' => ($isPaid ? 'العمولات المصروفة — ' : 'عمولات قيد الانتظار — ').$kindLabel,
        'frInfo' => $rangeLabel,
        'frHeaders' => $isPaid
            ? ['#', 'الموظف', 'الهدف', 'الدورة', 'الفترة', 'تاريخ الصرف', 'العمولة', 'طريقة الصرف', 'الحالة']
            : ['#', 'الموظف', 'الهدف', 'الفترة', 'المحقَّق', 'نسبة الإنجاز', 'العمولة', 'خزينة الصرف', 'سبب الانتظار'],
    ])
@endsection
