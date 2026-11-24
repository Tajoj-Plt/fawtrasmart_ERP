{{--
    تقرير أعمار ديون فواتير الموردين — صورةٌ قسماً بقسم من تقرير «أعمار
    ديون الفواتير» في تقارير العملاء
    (`client::customers.CustomerReport.debt_reconstruction_inv`): ترويسةٌ
    فاتحة، شريطُ فلترةٍ مختصر، ثلاثةُ مؤشّرات، رسمان (أعمدة + حلقة بجدول
    توزيع)، تنبيها التسوية، ثمّ جدولُ الفواتير بترقيم صفحات.

    الطرفُ وحدَه تبدّل: مورّدٌ مكان العميل، وفاتورةُ شراءٍ مكان فاتورة
    البيع، ومساراتُ المشتريات مكان مسارات المبيعات. وما لا نظيرَ له عند
    المورّد (وضعُ التسجيل، الهدف، التصنيف، الحي) حلّت محلَّه المدينةُ
    والموظّفُ المسؤول.

    • أعمدةُ الجدول الخمسة عشر بترتيب تقرير العملاء، وبعدها عمودا «فترة
      التأخير» و«إجراءات» (`ag-extra`) يُستثنيان من التصدير والطباعة.
    • فلترُ الشرائح (`period_filter`) في الخادم — بالنقر على صفّ الشريحة
      في جدول التوزيع أو على عمودها/قطعتها في الرسمين، كما في تقرير العملاء.

    ترقيمُ الصفحات في المتصفّح يُخفي الصفوفَ بصنفٍ لا بـ`style.display`، لأنّ
    أداةَ التصدير تتخطّى الصفَّ المخفيَّ بالخاصية وحدَها — فيُصدَّر الجدولُ كلُّه
    لا الصفحةُ المعروضة.
--}}
@extends('purchases::master')

@section('title')
    تقرير أعمار ديون فواتير الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* سبعةَ عشرَ عموداً: «تابع لـ» يأخذ عرضاً يُقرأ به، والتواريخُ لا تنكسر */
        .rpt .ag-table tbody td:nth-child(5) { min-width: 130px; }
        .rpt .ag-table tbody td:nth-child(n+8):nth-child(-n+10) { white-space: nowrap; }
        /* اسمُ المورّد رابطاً — صنفُ رابط الطرف نفسُه في `reports.partials.client-link`
           (يُعرَّف هناك داخل الجزء، وهنا يُكتب لأنّ الرابط مباشرٌ على مسار المورّد) */
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endsection

@section('content')
@php
    $currency = \App\Support\CurrencySettings::name();
    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $currentPeriodFilter = (string) request('period_filter', '');
    $rows = collect($reportData);

    /* الشرائحُ الستّ بقيم `period_filter` نفسِها التي يقرؤها المتحكّم */
    $buckets = [
        ['key' => 'today',       'filter' => '1',   'label' => 'غير مستحق',        'short' => 'غير مستحق',  'color' => '#10b981'],
        ['key' => 'days1to30',   'filter' => '7',   'label' => 'من 1 إلى 30 يوم',   'short' => '1–30 يوم',   'color' => '#3b82f6'],
        ['key' => 'days31to60',  'filter' => '30',  'label' => 'من 31 إلى 60 يوم',  'short' => '31–60 يوم',  'color' => '#eab308'],
        ['key' => 'days61to90',  'filter' => '90',  'label' => 'من 61 إلى 90 يوم',  'short' => '61–90 يوم',  'color' => '#f97316'],
        ['key' => 'days91to120', 'filter' => '180', 'label' => 'من 91 إلى 120 يوم', 'short' => '91–120 يوم', 'color' => '#ef4444'],
        ['key' => 'daysOver120', 'filter' => '365', 'label' => 'أكثر من 120 يوم',   'short' => '+120 يوم',   'color' => '#9f1239'],
    ];
    $bucketSum = 0.0;
    foreach ($buckets as $i => $bucket) {
        $buckets[$i]['value'] = (float) ($totals[$bucket['key']] ?? 0);
        $bucketSum += $buckets[$i]['value'];
    }
    foreach ($buckets as $i => $bucket) {
        $buckets[$i]['pct'] = $bucketSum > 0 ? round($bucket['value'] / $bucketSum * 100) : 0;
        $buckets[$i]['element_id'] = $bucket['key'] === 'today' ? 'todayAmount' : $bucket['key'];
    }
    $bucketsByKey = collect($buckets)->keyBy('key');
    $activeBucket = collect($buckets)->firstWhere('filter', $currentPeriodFilter);
    $chartBuckets = array_map(fn ($b) => [
        'label' => $b['label'],
        'short' => $b['short'],
        'value' => round($b['value'], 2),
        'pct' => $b['pct'],
        'color' => $b['color'],
        'filter' => $b['filter'],
    ], $buckets);

    $debtGuide = [
        'title' => 'ما الذي يعرضه تقرير أعمار ديون فواتير الموردين؟',
        'lead' => 'كلُّ فاتورةِ شراءٍ لم تُسدَّد كاملةً حتى تاريخ القطع، مصنَّفةً حسب عدد أيام تأخّرها عن تاريخ استحقاقها — فاتورةً فاتورة، لا إجمالاً على المورّد.',
        'blocks' => [
            'مصدر الأرقام' => 'فواتيرُ الشراء (`purchase_invoices`) وحدَها: الإجماليُّ إجماليُّ الفاتورة، والمتبقّي رقمُ الوحدة نفسُه الذي تقرؤه شاشةُ الفاتورة وكشفُ المورّد، والمدفوعُ فرقُهما. والمرتجعُ ما أُرجع على الفاتورة من مرتجعات شراءٍ حتى تاريخ القطع — يُعرض ولا يُطرح.',
            'عمر الدَّين' => 'الأيامُ من تاريخ الاستحقاق (أو تاريخ الفاتورة إن لم يُحدَّد) إلى تاريخ القطع، ولا يتجاوز اليوم. فاتورةٌ لم يحِن أجلُها تُحسَب «غير مستحقّة».',
            'الفرق عن أعمار ديون الموردين' => 'ذاك يُعمِّر رصيدَ المورّد التراكميّ عبر حركات دفتره، وهذا يقرأ كلَّ فاتورةٍ قائمةً بذاتها — فيُجيب «أيّ فاتورةٍ تحديداً تجاوزت أجلها؟».',
        ],
        'facts' => [
            'المدى المختار يقصر الجدولَ على فواتيره؛ وخانةُ «إظهار الديون الأقدم» تُعيد معه المفتوحَ الأقدمَ من بدايته',
            'النقرُ على شريحةٍ في جدول التوزيع أو الرسمين يقصر الجدولَ على فواتيرها',
            'تنبيهُ التسوية يقارن مديونيةَ الفواتير برصيد الأستاذ لنفس الموردين ويفسّر الفرق',
        ],
    ];
    $suppliersCount = $totals['suppliers_count'] ?? $rows->pluck('supplier_id')->filter()->unique()->count();
    $totalDue = (float) ($totals['total_due'] ?? 0);

    $canViewInvoice = hasPermission('purchases.invoices.view');
    $canInvoicePdf = hasPermission('purchases.invoices.export');
    $canAddPayment = hasPermission('purchases.payments.create');
    $canViewSupplier = hasPermission('purchases.suppliers.view');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'file-invoice-dollar',
        'headTitle' => 'أعمار ديون فواتير الموردين',
        'headSubtitle' => 'فواتير الشراء غير المسدَّدة مصنَّفةً حسب عدد أيام تأخّرها عن تاريخ الاستحقاق',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'purchases-reports.invoice-debt-aging',
        'headGuide' => $debtGuide,
        'headCrumbLabel' => 'تقارير المشتريات',
        'headCrumbUrl' => route('ReportsPurchases.index'),
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ReportsPurchases.invoiceDebtAging') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period') ?? '30' }}">
                <input type="hidden" name="period_filter" id="periodFilterInput" value="{{ request('period_filter') ?? '' }}">

                {{-- ─────────── الفترات السريعة ─────────── --}}
                @include('client::customers.CustomerReport.partials.report-period-tabs')

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" aria-label="من تاريخ"
                                   value="{{ request('from_date') ?: (isset($fromDate) ? $fromDate->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            {{-- المدى الذي حُسب به التقرير — نهايتُه تاريخُ القطع --}}
                            <input type="date" name="to_date" id="to_date" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ request('to_date') ?: (isset($toDate) ? $toDate->format('Y-m-d') : now()->format('Y-m-d')) }}">
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

                    <div class="rpt-field">
                        {{-- مُنتقي Select2 على AJAX — المصدرُ `ReportsPurchases.searchSuppliers` بالشكل نفسِه --}}
                        @include('reports.partials.client-select', [
                            'name' => 'supplier_id',
                            'url' => route('ReportsPurchases.searchSuppliers'),
                            'selectedClient' => $selectedSupplier ?? null,
                            'placeholder' => 'كل الموردين — ابحث بالاسم أو الكود أو الجوال',
                            'label' => 'المورد',
                        ])
                    </div>

                    {{--
                        حالةُ السداد تُشتقّ من متبقّي الفاتورة: مسدَّدةٌ بلا متبقٍّ،
                        وجزئيةٌ إن دُفع منها شيء، وغيرُ مسدَّدةٍ إن لم يُدفع.
                    --}}
                    <div class="rpt-field">
                        <label for="payment_status">حالة السداد</label>
                        <select name="payment_status" id="payment_status" class="form-control rpt-select2" data-placeholder="كل حالات السداد">
                            <option value="">كل حالات السداد</option>
                            <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>غير مسدَّدة</option>
                            <option value="partial" {{ request('payment_status') === 'partial' ? 'selected' : '' }}>مسدَّدة جزئياً</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>مسدَّدة بالكامل</option>
                        </select>
                    </div>

                    {{-- «مسؤول المشتريات» = الموظّفُ المسؤول عن المورّد أو من أضافه بمستخدمه — كـ«مسؤول المبيعات» --}}
                    <div class="rpt-field">
                        <label for="employee_id">مسؤول المشتريات</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2" data-placeholder="كل مسؤولي المشتريات">
                            <option value="">كل مسؤولي المشتريات</option>
                            @foreach($purchaseEmployees ?? [] as $employee)
                                <option value="{{ $employee->id }}" {{ (string) request('employee_id') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة المورد</label>
                        <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
                            <option value="">كل الحالات</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>موقوف</option>
                        </select>
                    </div>

                    {{-- المدينةُ نظيرُ «الحي» في تقرير العملاء — لا وضعَ ولا هدفَ ولا تصنيفَ للمورّد --}}
                    <div class="rpt-field">
                        <label for="city">المدينة</label>
                        <select name="city" id="city" class="form-control rpt-select2">
                            <option value="">جميع المدن</option>
                            @foreach($cities ?? [] as $city)
                                <option value="{{ $city }}" {{ request('city') === $city ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- عمرُ الدين: ما تجاوز عمرُه عدداً من الأيام — App\Support\DebtAge --}}
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.debt-age-select', ['debtAgeHint' => 'الفواتير التي تجاوز عمر استحقاقها الحد'])
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    {{--
                        المدى المختار يقصر الجدولَ على فواتيره (انظر
                        SupplierReportController::buildInvoiceAging). هذه الخانة تُعيد
                        معه الديونَ المفتوحةَ الأقدمَ من بدايته.
                    --}}
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="include_older" name="include_older" value="1" {{ request('include_older') ? 'checked' : '' }}>
                        <label class="form-check-label" for="include_older">إظهار الديون المفتوحة الأقدم من بداية الفترة أيضاً</label>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.invoiceDebtAging') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">إجمالي المستحق على الفواتير</div>
                <div class="ag-kpi__value"><bdi id="totalDue">{{ number_format($totalDue, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · المدفوع <bdi id="totalPaid">{{ number_format($totals['paid_amount'] ?? 0, 2) }}</bdi></div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
        {{-- بطاقتان محايدتان كتقرير العملاء، و`#totalInvoices` يقرؤه التصديرُ والطباعة --}}
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">الموردون</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($suppliersCount) }}</bdi></div>
                <div class="ag-kpi__sub">مورّد لهم فواتير مفتوحة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">عدد الفواتير</div>
                <div class="ag-kpi__value"><bdi id="totalInvoices">{{ $invoicesCount ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">فاتورة مفتوحة حتى تاريخ القطع</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-alt"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> إجمالي المبالغ المستحقة حسب فترة التأخير</h4>
            </div>
            <div class="rpt-card__body">
                @if ($bucketSum > 0)
                    <div class="ag-canvas"><canvas id="agingBar" aria-label="المبالغ حسب فترة التأخير" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا مبالغ مستحقة لعرضها</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع الديون حسب فترة التأخير</h4>
                <span class="ag-hint no-print">اضغط على فترة لعرض فواتيرها فقط</span>
            </div>
            <div class="rpt-card__body">
                <div class="ag-dist">
                    <table class="ag-legend">
                        <tbody>
                            @foreach ($buckets as $bucket)
                                <tr class="ag-legend__row {{ $currentPeriodFilter === $bucket['filter'] ? 'is-active' : '' }}"
                                    style="--c:{{ $bucket['color'] }}" data-filter="{{ $bucket['filter'] }}" tabindex="0"
                                    title="{{ $currentPeriodFilter === $bucket['filter'] ? 'إلغاء فلتر هذه الفترة' : 'عرض فواتير هذه الفترة فقط' }}">
                                    <td><span class="ag-dot"></span>{{ $bucket['label'] }}</td>
                                    <td class="ag-legend__amount"><bdi id="{{ $bucket['element_id'] }}">{{ number_format($bucket['value'], 2) }}</bdi></td>
                                    <td class="ag-legend__pct">{{ $bucket['pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($bucketSum > 0)
                        <div class="ag-donut">
                            <canvas id="agingDonut" aria-label="توزيع الديون حسب فترة التأخير" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($bucketSum, 2) }}</strong>
                                <small>{{ $currency }}</small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{--
        تسويةُ هذا التقرير مع تقرير أرصدة الموردين.

        التقريرانِ يقيسان شيئين: هذا ما بقي على **الفواتير**، وذاك ما على
        **الحساب**. والفرقُ حركاتٌ حقيقيةٌ بلا مستندِ فاتورة — أرصدةٌ
        افتتاحية، وقيودُ يومية، ومرتجعاتٌ وإشعاراتٌ، ودفعاتٌ على الرصيد
        السابق. يُعرض الفرقُ مفسَّراً بدل أن يظنّ القارئُ أحدَ الرقمين خطأً.
    --}}
    {{-- فواتيرُ مفتوحةٌ لموردين حساباتُهم غير دائنة — تُعلَن وحدَها كي لا تُقرأ ديناً حقيقياً --}}
    @if (($totals['not_credit_invoices'] ?? 0) > 0)
        <div class="alert alert-info" role="status">
            <i class="fas fa-hand-holding-usd"></i>
            <strong>{{ number_format($totals['not_credit_invoices']) }} فاتورة مفتوحة</strong>
            (<span dir="ltr">{{ number_format($totals['not_credit_due'], 2) }}</span>)
            لـ <strong>{{ number_format($totals['not_credit_suppliers']) }} مورّد حسابُه غير دائن</strong>
            @if (($totals['not_credit_debit'] ?? 0) > 0)
                — ولنا عليهم أرصدةٌ مدينة <span dir="ltr">{{ number_format($totals['not_credit_debit'], 2) }}</span>
            @endif
            <div class="mt-1"><small>سُدِّد لهم على الحساب لا على الفاتورة، فتبقى الفاتورةُ «مستحقّة» ورقياً ولا يُستحقّ للمورّد فعلياً. معلَّمةٌ في الجدول بشارة «حسابه غير دائن»، وتُطابَق بالربط من شاشة الفاتورة أو سند الصرف.</small></div>
        </div>
    @endif

    @if (!empty($reconciliation['available']))
        <div class="alert alert-{{ abs($reconciliation['difference']) < 0.05 ? 'success' : 'info' }}" role="status">
            <i class="fas fa-balance-scale"></i>
            <strong>مديونية الفواتير:</strong> <span dir="ltr">{{ number_format($reconciliation['invoice_debt'], 2) }}</span>
            <span class="mx-2">|</span>
            <strong>رصيد الأستاذ لنفس الموردين:</strong> <span dir="ltr">{{ number_format($reconciliation['ledger_debt'], 2) }}</span>
            <span class="mx-2">|</span>
            <strong>الفرق:</strong> <span dir="ltr">{{ number_format($reconciliation['difference'], 2) }}</span>
            @if (abs($reconciliation['difference']) >= 0.05)
                <div class="mt-1"><small>{{ $reconciliation['note'] }}</small></div>
            @endif
        </div>
    @endif

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> تفاصيل أعمار ديون فواتير الموردين
                <span class="rpt-pill rpt-pill-secondary">{{ $invoicesCount ?? 0 }} فاتورة</span>
            </h4>
            <div class="ag-tools no-print">
                @if ($activeBucket)
                    <button type="button" class="ag-filter-chip" style="--c:{{ $activeBucket['color'] }}" data-filter="{{ $activeBucket['filter'] }}" title="إلغاء فلتر الفترة">
                        {{ $activeBucket['label'] }} <i class="fas fa-times"></i>
                    </button>
                @endif
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="agingTable">
                <thead>
                    <tr>
                        <th>#</th><th>رقم الفاتورة</th><th>كود المورد</th><th>اسم المورد</th>
                        <th>تابع لـ</th><th>الحالة</th><th>المسؤول</th>
                        <th>تاريخ الفاتورة</th><th>تاريخ الاستحقاق</th><th>أيام التأخر</th>
                        <th>إجمالي الفاتورة</th><th>المرتجع</th><th>إشعار مدين</th><th>المدفوع</th><th>المستحق</th><th>حالة السداد</th>
                        <th class="ag-extra">فترة التأخير</th><th class="ag-extra">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse($reportData as $item)
                        @php
                            $daysLate = (int) ($item['days_late'] ?? 0);
                            $rowBucket = $bucketsByKey[\Modules\Purchases\Http\Controllers\Reports\SupplierReportController::invoiceBucketFor($daysLate)];
                            $statusColor = $item['status_color'] ?? '#6c757d';
                            $invoiceId = $item['invoice_id'] ?? null;
                            $supplierId = $item['supplier_id'] ?? null;
                        @endphp
                        <tr data-row="1">
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($canViewInvoice && $invoiceId)
                                    <a href="{{ route('invoicePurchases.show', $invoiceId) }}" target="_blank" rel="noopener" class="ag-invoice-link"><strong>{{ $item['invoice_number'] ?? '-' }}</strong></a>
                                @else
                                    <strong>{{ $item['invoice_number'] ?? '-' }}</strong>
                                @endif
                            </td>
                            <td>{{ $item['supplier_code'] ?? '-' }}</td>
                            <td class="rpt-name">
                                {{-- اسمُ المورّد رابطاً إلى صفحته — في تبويبٍ جديد كي لا يضيع التقريرُ بفلاتره --}}
                                @if ($supplierId && $canViewSupplier)
                                    <a href="{{ route('SupplierManagement.show', $supplierId) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة المورد" onclick="event.stopPropagation()">{{ $item['supplier_name'] ?: '-' }}</a>
                                @else
                                    {{ $item['supplier_name'] ?: '-' }}
                                @endif
                            </td>
                            <td>
                                {{ $item['branch'] ?? '-' }}
                                @if(!empty($item['affiliation_type']))
                                    <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                @endif
                            </td>
                            {{-- الحالةُ نشط/موقوف، والمدينةُ تحتها — كما تُقرأ الحالةُ والوضعُ معاً في تقرير العملاء --}}
                            <td>
                                <span class="rpt-pill" style="background-color: {{ $statusColor }}; color: white;">{{ $item['status_name'] ?? '-' }}</span>
                                @if(!empty($item['city']))
                                    <div class="mt-1">
                                        <span class="rpt-pill rpt-pill-secondary">{{ $item['city'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $item['employee_name'] ?? '-' }}</span></td>
                            <td class="rpt-num">{{ $item['invoice_date'] ?? '-' }}</td>
                            <td class="rpt-num" title="تاريخُ الفاتورة + مدّة الائتمان ({{ $item['credit_period'] ?? 0 }} يوم)">{{ $item['due_date'] ?? '-' }}</td>
                            <td><span class="ag-days" style="--c:{{ $rowBucket['color'] }}">{{ $daysLate }} يوم</span></td>
                            <td class="rpt-num">{{ number_format($item['total_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['returned_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['debit_noted_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-pos">{{ number_format($item['paid_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-neg"><strong>{{ number_format($item['total_due'] ?? 0, 2) }}</strong></td>
                            <td>
                                <span class="rpt-pill {{ ($item['payment_state'] ?? '') === 'paid' ? 'rpt-pill-success' : (($item['payment_state'] ?? '') === 'partial' ? 'rpt-pill-warning' : 'rpt-pill-danger') }}">
                                    {{ $item['payment_state_label'] ?? '-' }}
                                </span>
                                @if (!empty($item['account_not_credit']))
                                    <div class="mt-1">
                                        <span class="rpt-pill rpt-pill-success" title="حسابُ المورّد غير دائن حتى تاريخ القطع (رصيده {{ number_format($item['account_balance'] ?? 0, 2) }}) — سُدِّد له على الحساب لا على الفاتورة">حسابه غير دائن</span>
                                    </div>
                                @endif
                            </td>
                            <td class="ag-extra"><span class="ag-bucket" style="--c:{{ $rowBucket['color'] }}">{{ $rowBucket['label'] }}</span></td>
                            <td class="ag-extra">
                                <div class="dropdown">
                                    <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($canViewInvoice && $invoiceId)
                                            <a class="dropdown-item" href="{{ route('invoicePurchases.show', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-eye me-2 text-primary"></i> عرض الفاتورة</a>
                                        @endif
                                        @if ($canInvoicePdf && $invoiceId)
                                            <a class="dropdown-item" href="{{ route('invoicePurchases.pdf', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-2 text-danger"></i> PDF</a>
                                        @endif
                                        @if ($canAddPayment && $invoiceId && ($item['total_due'] ?? 0) > 0.005)
                                            <a class="dropdown-item" href="{{ route('PaymentSupplier.createPurchase', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-money-bill me-2 text-success"></i> إضافة دفعة</a>
                                        @endif
                                        @if ($supplierId && $canViewSupplier)
                                            <a class="dropdown-item" href="{{ route('SupplierManagement.show', $supplierId) }}" target="_blank" rel="noopener"><i class="fas fa-user me-2 text-info"></i> ملف المورد</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="18" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
                @if(count($reportData) > 0)
                    <tfoot id="reportTableFooter">
                        <tr>
                            <td colspan="10">الإجمالي الكلي</td>
                            <td class="rpt-num">{{ number_format($totals['total_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['returned_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['debit_noted_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-pos">{{ number_format($totals['paid_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-neg">{{ number_format($totals['total_due'] ?? 0, 2) }}</td>
                            <td></td>
                            <td class="ag-extra"></td>
                            <td class="ag-extra"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'فاتورة',
            'pagerStorage' => 'agSupplierInvoiceAgingPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    {{-- ذيلُ الطباعة بلا عمودَي «فترة التأخير» و«إجراءات» — يُملأ لحظةَ الطباعة --}}
    <table hidden><tfoot id="agPrintFooter"></tfoot></table>

    @include('sales::sals_report.report_partials.print-footer')
</div>

@include('sales::partials.table-menu')
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    @include('stock::components.report-tools')

    <script>
        const AG_BUCKETS = @json($chartBuckets);
        const AG_TOTAL = @json(round($bucketSum, 2));

        $(document).ready(function() {
            if ($.fn.select2) {
                $('.rpt-select2').each(function() {
                    var $el = $(this);
                    if ($el.data('select2')) { $el.select2('destroy'); }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function() { return "لا توجد نتائج"; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            $('.period-btn').click(function() { changePeriod($(this).data('period')); });

            /* فلترُ الشريحة: صفُّ جدول التوزيع، وشارةُ الفلتر فوق الجدول */
            $(document).on('click', '.ag-legend__row[data-filter], .ag-filter-chip[data-filter]', function() {
                applyBucketFilter(String($(this).data('filter')));
            });
            $(document).on('keydown', '.ag-legend__row[data-filter]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyBucketFilter(String($(this).data('filter'))); }
            });

            /* رؤوسُ التصدير والطباعة = أعمدةُ الجدول الأصلية وحدَها */
            var headers = $('#agingTable thead th').not('.ag-extra').map(function() { return $(this).text().trim(); }).get();
            var info = { 'الفترة': ($('#from_date').val() || '') + ' إلى ' + ($('#to_date').val() || '') };
            var cards = [
                { title: 'إجمالي الفواتير', value: $('#totalInvoices').text() },
                { title: 'إجمالي المدفوع', value: $('#totalPaid').text() },
                { title: 'إجمالي المستحق', value: $('#totalDue').text() },
                { title: 'غير مستحق', value: $('#todayAmount').text() },
                { title: '1-30 يوم', value: $('#days1to30').text() },
                { title: '31-60 يوم', value: $('#days31to60').text() },
                { title: '61-90 يوم', value: $('#days61to90').text() },
                { title: '91-120 يوم', value: $('#days91to120').text() },
                { title: '+120 يوم', value: $('#daysOver120').text() }
            ];

            stkReportTools({
                table: '#agingTable',
                headers: headers,
                fileName: 'تقرير_أعمار_ديون_فواتير_الموردين',
                title: 'تقرير أعمار ديون فواتير الموردين',
                info: info,
                cards: cards,
                // الفهارسُ صفريّةٌ على أعمدة الجدول: # والمبالغ الأربعة
                numericColumns: [0, 10, 11, 12, 13],
                exportButton: '#exportExcel'
            });

            $('#printBtn').on('click', function() {
                if (!document.querySelector('#reportTableBody tr[data-row]')) {
                    agWarn('لا توجد بيانات لطباعتها');
                    return;
                }

                var foot = document.getElementById('reportTableFooter');
                var printFoot = document.getElementById('agPrintFooter');
                if (foot && printFoot) {
                    var row = foot.rows[0].cloneNode(true);
                    row.querySelectorAll('.ag-extra').forEach(function(cell) { cell.remove(); });
                    printFoot.innerHTML = '';
                    printFoot.appendChild(row);
                }

                printTable({
                    tableSelector: '#reportTableBody tr',
                    tfootSelector: foot ? '#agPrintFooter' : null,
                    title: 'تقرير أعمار ديون فواتير الموردين',
                    infoCards: cards.map(function(card) { return { label: card.title, value: card.value }; }),
                    filterInfo: Object.assign({ 'تاريخ التقرير': new Date().toISOString().slice(0, 10) }, info),
                    tableHeaders: headers,
                    skipColumns: [15, 16],
                    descriptionColumn: 3,
                    windowWidth: 1400,
                    windowHeight: 900
                });
            });

            renderCharts();
        });

        function applyBucketFilter(filter) {
            var current = $('#periodFilterInput').val();
            $('#periodFilterInput').val(current === filter ? '' : filter);
            $('#filterForm').submit();
        }

        function changePeriod(period) {
            $('#periodInput').val(period);
            $('#periodFilterInput').val('');

            if (period !== 'custom') {
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(startDate.getDate() - parseInt(period));
                /* أزرارُ المدى السريع تُفرغ منتقيَي السنة والفترة: المتحكّم
                   يقدّمهما على حقلَي التاريخ، فلو بقيت قيمةٌ فيهما لظهر
                   الزرُّ مضغوطاً بينما يقرأ الاستعلامُ الفترةَ المختارة. */
                $('#fiscal_year_id, #accounting_period_id').val('');
                if ($.fn.select2) { $('#fiscal_year_id, #accounting_period_id').trigger('change.select2'); }

                $('#from_date').val(formatDateForInput(startDate));
                $('#to_date').val(formatDateForInput(today));
                $('#filterForm').submit();
            }
        }

        function renderCharts() {
            if (typeof Chart === 'undefined' || !(AG_TOTAL > 0)) return;

            var theme = agChartTheme();
            var muted = theme.muted;
            var line = theme.line;
            var surface = theme.surface;

            var labels = AG_BUCKETS.map(function(b) { return b.label; });
            var values = AG_BUCKETS.map(function(b) { return b.value; });
            var colors = AG_BUCKETS.map(function(b) { return b.color; });
            var whole = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

            function tooltipLabel(context) {
                var b = AG_BUCKETS[context.dataIndex];
                return ' ' + formatNumber(b.value) + ' (' + b.pct + '%)';
            }

            function bindFilterClick(chart) {
                chart.canvas.style.cursor = 'pointer';
                chart.canvas.addEventListener('click', function(evt) {
                    var hit = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (hit.length) applyBucketFilter(AG_BUCKETS[hit[0].index].filter);
                });
            }

            var barEl = document.getElementById('agingBar');
            if (barEl) {
                var valueLabels = agValueLabels(values);

                bindFilterClick(new Chart(barEl, {
                    type: 'bar',
                    data: { labels: AG_BUCKETS.map(function(b) { return b.short; }), datasets: [{ data: values, backgroundColor: colors, borderRadius: 6, maxBarThickness: 64 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: tooltipLabel } } },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: muted, font: { size: 11 }, maxRotation: 0, autoSkip: false } },
                            y: { beginAtZero: true, position: 'right', grid: { color: line }, border: { display: false },
                                 ticks: { color: muted, callback: function(v) { return whole.format(v); } } }
                        }
                    },
                    plugins: [valueLabels]
                }));
            }

            var donutEl = document.getElementById('agingDonut');
            if (donutEl) {
                bindFilterClick(new Chart(donutEl, {
                    type: 'doughnut',
                    data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderColor: surface, borderWidth: 3, hoverOffset: 6 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: tooltipLabel } } }
                    }
                }));
            }
        }

        function formatNumber(n) { return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    </script>
@endsection
