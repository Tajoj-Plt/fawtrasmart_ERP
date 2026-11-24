{{--
    تقرير أعمار ديون الفواتير — لوحةٌ على نمط التصميم المعتمد: ترويسةٌ فاتحة،
    شريطُ فلترةٍ مختصر، أربعةُ مؤشّرات، رسمان (أعمدة + حلقة بجدول توزيع)،
    ثمّ جدولُ الفواتير بترقيم صفحات.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها — ظاهرةٌ كلُّها في شبكةٍ واحدة (أربعةٌ في
      السطر)، وأزرارُ الفترات السريعة فوقها بمقاس أزرار لوحة العملاء.
    • أعمدةُ الجدول الخمسة عشر وبياناتُها وترتيبُها؛ أُضيف بعدها عمودا «فترة
      التأخير» و«إجراءات» (`ag-extra`) ويُستثنيان من التصدير والطباعة، فملفُّ
      Excel والورقةُ المطبوعة كما كانا.
    • فلترُ الشرائح (`period_filter`) — صار بالنقر على صفّ الشريحة في جدول
      التوزيع أو على عمودها/قطعتها في الرسمين.

    ترقيمُ الصفحات في المتصفّح يُخفي الصفوفَ بصنفٍ لا بـ`style.display`، لأنّ
    أداةَ التصدير تتخطّى الصفَّ المخفيَّ بالخاصية وحدَها — فيُصدَّر الجدولُ كلُّه
    لا الصفحةُ المعروضة.
--}}
@extends('client::master')

@section('title')
    تقرير أعمار ديون الفواتير
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* سبعةَ عشرَ عموداً: «تابع لـ» يأخذ عرضاً يُقرأ به، والتواريخُ لا تنكسر */
        .rpt .ag-table tbody td:nth-child(5) { min-width: 130px; }
        .rpt .ag-table tbody td:nth-child(n+8):nth-child(-n+10) { white-space: nowrap; }
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
        'title' => __('client_debt.guide.title'),
        'lead' => __('client_debt.guide.invoices.lead'),
        'blocks' => [
            __('client_debt.guide.invoices.source_title') => __('client_debt.guide.invoices.source'),
            __('client_debt.guide.age_title') => __('client_debt.guide.age'),
            __('client_debt.guide.credit_title') => __('client_debt.guide.credit'),
        ],
        // بلا سطر «المتأخّر = تجاوز N يوماً» — مؤشّرُ المتأخّرين في لوحة العملاء وحدَها
        'facts' => array_slice(__('client_debt.guide.facts'), 1),
    ];
    $clientsCount = $totals['clients_count'] ?? $rows->pluck('client_id')->filter()->unique()->count();
    $totalDue = (float) ($totals['total_due'] ?? 0);

    $canViewInvoice = hasPermission('sales.invoices.view');
    $canInvoicePdf = hasPermission('sales.invoices.export_pdf');
    $canAddPayment = hasPermission('sales.payments.create');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'file-invoice-dollar',
        'headTitle' => 'أعمار ديون الفواتير',
        'headSubtitle' => 'فواتير العملاء غير المسدَّدة مصنَّفةً حسب عدد أيام تأخّرها عن تاريخ الاستحقاق',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.debt-aging',
        'headGuide' => $debtGuide,
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="filterForm">
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
                            {{-- المدى الذي حُسب به التقرير — كان «أوّل الشهر» ثابتاً فتبدأ «فواتير الفترة فقط» من أوّل الشهر بعد أوّل بحث --}}
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
                        @include('reports.partials.client-select', [
                            'name' => 'customer',
                            'selectedClient' => $selectedClient ?? null,
                            'label' => 'العميل',
                        ])
                    </div>

                    {{--
                        حالةُ السداد تُحسب بتاريخ القطع لا تُقرأ من العمود
                        المحفوظ `invoices.payment_status` — ذاك حالةُ اليوم،
                        فيقول عن فاتورةٍ ديسمبريةٍ إنّها «مسدَّدة» في تقريرٍ
                        مؤرَّخٍ قبل أن يُدفع فيها ريال.
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

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.sales-employee-select', [
                            'salesEmployees' => $salesEmployees ?? collect(),
                            'label' => 'مسؤول المبيعات',
                        ])
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة العميل</label>
                        <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
                            <option value="">كل الحالات</option>
                            @foreach($statuses ?? [] as $status)
                                <option value="{{ $status->id }}" {{ request('status') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.stage-select', [
                            'registrationStages' => $registrationStages ?? collect(),
                            'label' => 'وضع العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.target-select', [
                            'customerTargets' => $customerTargets ?? collect(),
                            'label' => 'هدف العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        <label for="category_client">تصنيف العميل</label>
                        <select name="category_client" id="category_client" class="form-control rpt-select2">
                            <option value="">جميع التصنيفات</option>
                            @foreach($categories ?? [] as $category)
                                <option value="{{ $category->id }}" {{ request('category_client') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="neighborhood">الحي</label>
                        <select name="neighborhood" id="neighborhood" class="form-control rpt-select2">
                            <option value="">جميع الأحياء</option>
                            @foreach($neighborhoods ?? [] as $nh)
                                <option value="{{ $nh->name }}" data-region-id="{{ $nh->region_id }}" {{ request('neighborhood') == $nh->name ? 'selected' : '' }}>{{ $nh->name }}</option>
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
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    {{--
                        المدى المختار يقصر الجدولَ على فواتيره (انظر
                        CustomerDebtsController::reportRows). هذه الخانة تُعيد
                        معه الديونَ المفتوحةَ الأقدمَ من بدايته.
                    --}}
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="include_older" name="include_older" value="1" {{ request('include_older') ? 'checked' : '' }}>
                        <label class="form-check-label" for="include_older">إظهار الديون المفتوحة الأقدم من بداية الفترة أيضاً</label>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ url()->current() }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
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
        {{-- بطاقاتُ «المتأخّرين» (تجاوز N يوماً) حُذفت من التقارير بطلب المستخدم
             (2026-09-16) — تبقى في لوحة العملاء وحدَها. البطاقتان محايدتان،
             و`#totalInvoices` يقرؤه التصديرُ والطباعة. --}}
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">العملاء</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($clientsCount) }}</bdi></div>
                <div class="ag-kpi__sub">عميل عليهم فواتير مفتوحة</div>
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
        تسويةُ هذا التقرير مع تقرير أرصدة العملاء.

        التقريرانِ يقيسان شيئين: هذا ما بقي على **الفواتير**، وذاك ما على
        **الحساب**. والفرقُ حركاتٌ حقيقيةٌ بلا مستندِ فاتورة — أرصدةٌ
        افتتاحية، وقيودُ يومية، وإشعاراتٌ، ودفعاتٌ على الرصيد السابق. يُعرض
        الفرقُ مفسَّراً بدل أن يظنّ القارئُ أحدَ الرقمين خطأً.
    --}}
    {{-- فواتيرُ مفتوحةٌ لعملاءَ حساباتُهم غير مدينة — تُعلَن وحدَها كي لا تُقرأ ديناً حقيقياً --}}
    @if (($totals['not_debit_invoices'] ?? 0) > 0)
        <div class="alert alert-info" role="status">
            <i class="fas fa-hand-holding-usd"></i>
            <strong>{{ number_format($totals['not_debit_invoices']) }} فاتورة مفتوحة</strong>
            (<span dir="ltr">{{ number_format($totals['not_debit_due'], 2) }}</span>)
            لـ <strong>{{ number_format($totals['not_debit_clients']) }} عميل حسابُه غير مدين</strong>
            @if (($totals['not_debit_credit'] ?? 0) > 0)
                — ولهم أرصدةٌ دائنة <span dir="ltr">{{ number_format($totals['not_debit_credit'], 2) }}</span>
            @endif
            <div class="mt-1"><small>سدّدوا على الحساب لا على الفاتورة، فتبقى الفاتورةُ «مستحقّة» ورقياً والعميلُ لا يدين فعلياً. معلَّمةٌ في الجدول بشارة «حسابه غير مدين»، وتُطابَق بالربط من شاشة الفاتورة أو السند.</small></div>
        </div>
    @endif

    @if (!empty($reconciliation['available']))
        <div class="alert alert-{{ abs($reconciliation['difference']) < 0.05 ? 'success' : 'info' }}" role="status">
            <i class="fas fa-balance-scale"></i>
            <strong>مديونية الفواتير:</strong> <span dir="ltr">{{ number_format($reconciliation['invoice_debt'], 2) }}</span>
            <span class="mx-2">|</span>
            <strong>رصيد الأستاذ لنفس العملاء:</strong> <span dir="ltr">{{ number_format($reconciliation['ledger_debt'], 2) }}</span>
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
                <i class="fas fa-table"></i> تفاصيل أعمار ديون الفواتير
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
                        <th>#</th><th>رقم الفاتورة</th><th>كود العميل</th><th>اسم العميل</th>
                        <th>تابع لـ</th><th>الحالة</th><th>الهدف</th>
                        <th>تاريخ الفاتورة</th><th>تاريخ الاستحقاق</th><th>أيام التأخر</th>
                        <th>إجمالي الفاتورة</th><th>المرتجع</th><th>المدفوع</th><th>المستحق</th><th>حالة السداد</th>
                        <th class="ag-extra">فترة التأخير</th><th class="ag-extra">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse($reportData as $item)
                        @php
                            $daysLate = (int) ($item['days_late'] ?? 0);
                            $rowBucket = $bucketsByKey[\Modules\Client\Services\CustomerLedgerService::bucketFor($daysLate)];
                            $statusColor = $item['status_color'] ?? '#6c757d';
                            $invoiceId = $item['invoice_id'] ?? null;
                        @endphp
                        <tr data-row="1">
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if (($item['invoice_type'] ?? '') === 'ledger')
                                    {{-- بندٌ مفتوحٌ في الأستاذ بلا فاتورة (رصيد افتتاحي، قيد…) — يكمل الجدولَ إلى المديونية --}}
                                    <strong>{{ $item['invoice_number'] ?? '-' }}</strong>
                                    <small class="d-block text-muted">{{ $item['ledger_label'] ?? '' }}</small>
                                @elseif ($canViewInvoice && $invoiceId)
                                    <a href="{{ route('invoices.show', $invoiceId) }}" target="_blank" rel="noopener" class="ag-invoice-link"><strong>{{ $item['invoice_number'] ?? '-' }}</strong></a>
                                @else
                                    <strong>{{ $item['invoice_number'] ?? '-' }}</strong>
                                @endif
                            </td>
                            <td>{{ $item['client_code'] ?? '-' }}</td>
                            <td class="rpt-name">
                                @include('reports.partials.client-link', ['id' => $item['client_id'] ?? null, 'name' => $item['client_name'] ?? ''])
                            </td>
                            <td>
                                {{ $item['branch'] ?? '-' }}
                                @if(!empty($item['affiliation_type']))
                                    <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                @endif
                            </td>
                            {{-- الحالةُ والوضعُ في عمودٍ واحد: الحالةُ نشط/موقوف، والوضعُ
                                 مرحلةُ التسجيل تحتها — حقلان مستقلّان يُقرآن معاً. --}}
                            <td>
                                <span class="rpt-pill" style="background-color: {{ $statusColor }}; color: white;">{{ $item['status_name'] ?? '-' }}</span>
                                @if(!empty($item['stage_name']))
                                    <div class="mt-1">
                                        <span class="rpt-pill" style="background-color: {{ $item['stage_color'] ?? '#6c757d' }}; color: white;">{{ $item['stage_name'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><span class="rpt-pill" style="background-color: {{ $item['target_color'] ?? '#6c757d' }}; color: white;">{{ $item['target_name'] ?? '-' }}</span></td>
                            <td class="rpt-num">{{ $item['invoice_date'] ?? '-' }}</td>
                            <td class="rpt-num" title="تاريخُ الفاتورة + مدّة الائتمان ({{ $item['credit_period'] ?? 0 }} يوم)">{{ $item['due_date'] ?? '-' }}</td>
                            <td><span class="ag-days" style="--c:{{ $rowBucket['color'] }}">{{ $daysLate }} يوم</span></td>
                            <td class="rpt-num">{{ number_format($item['total_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['returned_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-pos">{{ number_format($item['paid_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-neg"><strong>{{ number_format($item['total_due'] ?? 0, 2) }}</strong></td>
                            <td>
                                <span class="rpt-pill {{ ($item['payment_state'] ?? '') === 'paid' ? 'rpt-pill-success' : (($item['payment_state'] ?? '') === 'partial' ? 'rpt-pill-warning' : 'rpt-pill-danger') }}">
                                    {{ $item['payment_state_label'] ?? '-' }}
                                </span>
                                @if (!empty($item['account_not_debit']))
                                    <div class="mt-1">
                                        <span class="rpt-pill rpt-pill-success" title="حسابُ العميل غير مدين حتى تاريخ القطع (رصيده {{ number_format($item['account_balance'] ?? 0, 2) }}) — سدّد على الحساب لا على الفاتورة">حسابه غير مدين</span>
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
                                            <a class="dropdown-item" href="{{ route('invoices.show', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-eye me-2 text-primary"></i> عرض الفاتورة</a>
                                        @endif
                                        @if ($canInvoicePdf && $invoiceId)
                                            <a class="dropdown-item" href="{{ route('invoices.generatePdf', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-2 text-danger"></i> PDF</a>
                                        @endif
                                        @if ($canAddPayment && $invoiceId && ($item['total_due'] ?? 0) > 0.005)
                                            <a class="dropdown-item" href="{{ route('paymentsClient.create', $invoiceId) }}" target="_blank" rel="noopener"><i class="fas fa-money-bill me-2 text-success"></i> إضافة دفعة</a>
                                        @endif
                                        @if (!empty($item['client_id']))
                                            <a class="dropdown-item" href="{{ route('clients.show', $item['client_id']) }}" target="_blank" rel="noopener"><i class="fas fa-user me-2 text-info"></i> ملف العميل</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="17" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
                @if(count($reportData) > 0)
                    <tfoot id="reportTableFooter">
                        <tr>
                            <td colspan="10">الإجمالي الكلي</td>
                            <td class="rpt-num">{{ number_format($totals['total_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['returned_amount'] ?? 0, 2) }}</td>
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
            'pagerStorage' => 'agInvoiceAgingPageSize',
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
                fileName: 'تقرير_أعمار_ديون_الفواتير',
                title: 'تقرير أعمار ديون الفواتير',
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
                    title: 'تقرير أعمار ديون الفواتير',
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
