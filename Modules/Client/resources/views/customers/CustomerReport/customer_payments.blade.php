{{--
    تقرير مدفوعات العملاء — على نمط التصميم المعتمد (أعمار ديون الفواتير):
    ترويسةٌ فاتحة، شبكةُ فلترةٍ واحدة، أربعةُ مؤشّرات، رسمان (أعمدةٌ بالشهر +
    حلقةٌ بتوزيع الخزائن)، ثمّ الجدولُ بترقيم صفحات في المتصفّح.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها، والمدى الجاهز (`date_type`) يملأ التاريخين.
    • أعمدةُ الجدول التسعة وبياناتُها: عنوانٌ لكلّ عميل، ثمّ دفعاتُه، ثمّ
      مجموعُه، ثمّ المجموعُ الكلّي.
    • زرّا «ملخص/تفاصيل» يُظهران ويُخفيان المؤشّراتِ والرسمين.

    **الترقيمُ على الخادم بالعميل (2026-09-28)** — `pagedGroups`، ٢٥ عميلاً
    افتراضاً، فلا تنقسم مجموعةٌ بين صفحتين. كانت الصفحةُ تطبع الدفعاتِ كلَّها
    (٨٧٥٣ صفّاً، ١٢٨ ثانية) ثمّ تُخفيها في المتصفّح. المؤشّراتُ والرسمان
    والمجموعُ الكلّي من الفترة كلِّها، والتصديرُ والطباعةُ يطلبان صفوفَها
    كلَّها عند الضغط (`?export=rows`).
--}}
@extends('client::master')

@section('title')
    تقرير مدفوعات العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* صفُّ عنوان العميل ومجموعُه يتميّزان عن صفوف الدفعات */
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody td:nth-child(3) { white-space: nowrap; }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        /* كلُّ الخزائن باسمها (لا «أخرى») — القائمةُ تُمرَّر إن طالت، والحلقةُ بجانبها ثابتة */
        .rpt .pay-legend-scroll { max-height: 340px; overflow-y: auto; }
        .rpt .pay-legend-scroll .ag-legend td { padding: 7px 6px; }
        /* عددُ العملاء في الصفحة: التسميةُ والقائمةُ في سطرٍ واحد، والسهمُ لا يغطّي الرقم */
        .rpt .ag-tools .ag-page-size { flex-shrink: 0; }
        .rpt .ag-tools #payPerPage { display: inline-block; width: auto; min-width: 74px; padding-inline: 10px 28px; }
    </style>
@endsection

@section('content')
@php
    $currency = \App\Support\CurrencySettings::name();
    $periodLabel = 'من '.\Illuminate\Support\Carbon::parse($fromDate)->format('d/m/Y').' إلى '.\Illuminate\Support\Carbon::parse($toDate)->format('d/m/Y');

    /* الرسمُ الأوّل: المدفوعاتُ بالشهر كما يُعدّها المتحكّم (`chart_data`) */
    $payChart = $chartData ?? ['labels' => [], 'payments' => []];
    $payHasMonths = count($payChart['labels'] ?? []) > 0;

    /* الرسمُ الثاني: توزيعُ المبالغ على الخزائن — من صفوف الجدول نفسِها.
       المبلغُ صافٍ (الإلغاءُ يطرح نفسَه)، فالحلقةُ ترسم الموجبَ وحدَه. */
    $payPalette = ['#3b82f6', '#10b981', '#eab308', '#f97316', '#ef4444', '#8b5cf6', '#06b6d4', '#9f1239', '#64748b', '#84cc16'];
    $payByTreasury = [];
    foreach ($groupedData as $payGroup) {
        foreach ($payGroup['payments'] as $payItem) {
            $payTreasuryName = $payItem['treasury']['name'] ?? 'غير محدد';
            $payByTreasury[$payTreasuryName] = ($payByTreasury[$payTreasuryName] ?? 0) + (float) $payItem['amount'];
        }
    }
    arsort($payByTreasury);
    /* كلُّ خزينةٍ باسمها — كان ما بعد السابعة يُجمع في «أخرى» فلا يُعرف أين
       ذهب عُشرُ المبلغ (قال المستخدم: «ما ودي يطلع أخرى، أبغى أعرف وين»).
       الألوانُ العشرةُ الأولى من اللوحة، وما بعدها تدرّجٌ متباعدٌ حول الدائرة. */
    $payPositiveSum = array_sum(array_filter($payByTreasury, fn ($v) => $v > 0));
    $payTreasuries = [];
    $payIndex = 0;
    foreach ($payByTreasury as $payName => $payValue) {
        $payTreasuries[] = [
            'label' => (string) $payName,
            'value' => round($payValue, 2),
            'pct' => ($payPositiveSum > 0 && $payValue > 0) ? round($payValue / $payPositiveSum * 100, $payValue / $payPositiveSum < 0.01 ? 1 : 0) : null,
            'color' => $payPalette[$payIndex] ?? 'hsl('.(($payIndex * 137) % 360).', 62%, 52%)',
        ];
        $payIndex++;
    }
    $payDonut = array_values(array_filter($payTreasuries, fn ($t) => $t['value'] > 0));
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'hand-holding-usd',
        'headTitle' => 'تقرير مدفوعات العملاء',
        'headSubtitle' => 'المدفوعات المسجَّلة من العملاء — حسب الفرع والموظف والخزينة',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.payments',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="reportForm">
                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        {{-- المدى الذي حُسب به التقرير فعلاً — كان «أوّل الشهر» ثابتاً فيُضيَّق كلُّ بحثٍ تالٍ بلا قصد --}}
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ" value="{{ request('date_from') ?: \Illuminate\Support\Carbon::parse($fromDate)->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ" value="{{ request('date_to') ?: \Illuminate\Support\Carbon::parse($toDate)->format('Y-m-d') }}">
                        </div>
                    </div>

                    @include('client::customers.CustomerReport.partials.period-selector', [
                        'periodContext' => $periodContext ?? [],
                        'startId' => 'date_from',
                        'endId' => 'date_to',
                        'cols' => '',
                        'yearTitle' => 'السنة المالية',
                        'periodTitle' => 'الفترة المحاسبية',
                    ])

                    {{-- مدىً جاهز يملأ حقلَي التاريخ — يبقى كما كان --}}
                    <div class="rpt-field">
                        <label for="date_type">مدى جاهز</label>
                        <select name="date_type" id="date_type" class="form-control rpt-select2">
                            <option value="custom">مخصص</option>
                            <option value="today">اليوم</option>
                            <option value="yesterday">أمس</option>
                            <option value="this_week">هذا الأسبوع</option>
                            <option value="last_week">الأسبوع الماضي</option>
                            <option value="this_month">هذا الشهر</option>
                            <option value="last_month">الشهر الماضي</option>
                            <option value="this_year">هذا العام</option>
                            <option value="last_year">العام الماضي</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'client_id',
                            'id' => 'client_id',
                            'selectedClient' => $selectedClient ?? null,
                            'label' => 'العميل',
                        ])
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
                            @foreach ($statuses ?? [] as $status)
                                <option value="{{ $status->id }}" @selected((string) request('status') === (string) $status->id)>{{ $status->name }}</option>
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
                            @foreach ($clientCategories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_client') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- فلترا سند القبض نفسِه لا العميل: منشئُ القيد والخزينة --}}
                    <div class="rpt-field">
                        <label for="employee_id">موظف السند</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">جميع الموظفين</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        {{-- حسابُ الخزينة أو البنك — سطرٌ لكلّ حساب (جدولُ الخزائن يكرّر الحسابَ الواحد صفوفاً) --}}
                        <label for="treasury_account">الخزينة / البنك</label>
                        <select name="treasury_account" id="treasury_account" class="form-control rpt-select2">
                            <option value="">جميع الخزائن والبنوك</option>
                            @foreach ($treasuries as $treasury)
                                <option value="{{ $treasury->id }}" @selected((int) ($selectedTreasuryAccount ?? 0) === (int) $treasury->id)>
                                    {{ $treasury->name }}{{ (int) $treasury->parent_id === (int) ($bankParentId ?? 0) ? ' (بنك)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    {{-- «وسيلة الدفع» أُزيلت: التقريرُ يقرأ القيودَ، والقيدُ لا يحمل وسيلةَ الدفع --}}

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        <button type="button" class="btn btn-primary active" id="summaryViewBtn"><i class="fas fa-chart-pie"></i> ملخص</button>
                        <button type="button" class="btn btn-outline-primary" id="detailViewBtn"><i class="fas fa-list"></i> تفاصيل</button>
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
    <div id="totalsSection">
        <div class="ag-kpis">
            <div class="ag-kpi" style="--c:#10b981">
                <div>
                    <div class="ag-kpi__label">إجمالي المدفوعات</div>
                    <div class="ag-kpi__value"><bdi id="totalPayments">{{ number_format($totals['total_payments'] ?? 0, 2) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ $currency }} · صافٍ بعد إلغاء السندات</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-money-bill-wave"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#8b5cf6">
                <div>
                    <div class="ag-kpi__label">عدد العملاء</div>
                    <div class="ag-kpi__value"><bdi id="totalClients">{{ $totals['total_clients'] ?? 0 }}</bdi></div>
                    <div class="ag-kpi__sub">عميل سدَّد في الفترة</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#3b82f6">
                <div>
                    <div class="ag-kpi__label">عدد المعاملات</div>
                    <div class="ag-kpi__value"><bdi id="totalTransactions">{{ $totals['total_transactions'] ?? 0 }}</bdi></div>
                    <div class="ag-kpi__sub">معاملة تحصيل</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#f97316">
                <div>
                    <div class="ag-kpi__label">متوسط المدفوعة</div>
                    <div class="ag-kpi__value"><bdi id="averagePayment">{{ number_format($totals['average_payment'] ?? 0, 2) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ $currency }} للمعاملة</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-calculator"></i></span>
            </div>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts" id="chartSection">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> مدفوعات العملاء حسب الشهر</h4>
            </div>
            <div class="rpt-card__body">
                @if ($payHasMonths)
                    <div class="ag-canvas"><canvas id="customerPaymentsChart" aria-label="مدفوعات العملاء حسب الشهر" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا مدفوعات لعرضها</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع المدفوعات حسب الخزينة</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($payTreasuries))
                    <div class="ag-dist">
                        <div class="pay-legend-scroll">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($payTreasuries as $payTreasury)
                                    <tr class="ag-legend__row" style="--c:{{ $payTreasury['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $payTreasury['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ number_format($payTreasury['value'], 2) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $payTreasury['pct'] === null ? '—' : $payTreasury['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        @if (count($payDonut))
                            <div class="ag-donut">
                                <canvas id="treasuryDonut" aria-label="توزيع المدفوعات حسب الخزينة" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ number_format($totals['total_payments'] ?? 0, 2) }}</strong>
                                    <small>{{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا مدفوعات لعرضها</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    @php
        /* رابطُ العميل مكتوباً هنا لا `@include` لكلّ صفّ: كلُّ include يُشغّل
           مُركِّبَ العرض العامّ (`View::composer('*')`) باستعلام — كان صفّان في كلّ
           دفعةٍ ١٠٥٧٣ استعلاماً و١٢٨ ثانيةً لسنةٍ واحدة. السلوكُ نفسُه: تبويبٌ جديد
           وإيقافُ انتشار النقرة، كما في `reports.partials.client-link`. */
        $payLink = function ($id, $name) {
            $name = trim((string) $name) !== '' ? $name : '-';

            return $id
                ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.e($name).'</a>'
                : e($name);
        };
        $payRow = $rowOffset ?? 0;
    @endphp
    <style>
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>

    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4 id="reportTitle">
                <i class="fas fa-table"></i> تقرير مدفوعات العملاء
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($pagedGroups->total()) }} عميل</span>
            </h4>
            <div class="ag-tools no-print">
                {{-- عددُ العملاء في الصفحة — يُرسل النموذجَ بفلاتره كما هي --}}
                <label class="ag-page-size" for="payPerPage">
                    <span>عملاء في الصفحة</span>
                    <select id="payPerPage" name="per_page" form="reportForm" class="form-control" onchange="this.form.submit()">
                        @foreach ($pageSizes as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        <th>#</th><th>العميل</th><th>الحالة</th><th>الوضع</th><th>الهدف</th><th>التاريخ</th><th>رقم المدفوعة</th>
                        <th>نوع السند</th><th>الخزينة</th><th>المبلغ</th><th>تابع لـ</th><th>منشئ القيد</th>
                    </tr>
                </thead>
                {{-- صفُّ عنوانٍ لكلّ عميل، ثمّ دفعاتُه، ثمّ مجموعُه — لعملاء هذه الصفحة وحدَهم --}}
                <tbody id="reportTableBody">
                    @forelse ($pagedGroups as $group)
                        @php
                            $client = $group['client'];
                            $clientLink = $payLink($client['id'] ?? null, $client['trade_name'] ?? '');
                            $clientBadges = array_map(fn ($b) => '<span class="rpt-pill" style="background:'.e($b['color'] ?? '#6c757d').'; color:#fff">'.e($b['name'] ?? '—').'</span>',
                                ['status' => $client['status'] ?? [], 'stage' => $client['stage'] ?? [], 'target' => $client['target'] ?? []]);
                        @endphp
                        <tr class="ag-group-row">
                            <td colspan="12" class="rpt-name">
                                <i class="fas fa-user"></i>
                                <strong>{!! $clientLink !!} - {{ $client['code'] }}</strong>
                                <span class="rpt-pill rpt-pill-secondary">
                                    {{ number_format($group['total_payments'], 2) }} | {{ $group['payments_count'] }} معاملة
                                </span>
                            </td>
                        </tr>
                        @foreach ($group['payments'] as $payment)
                            <tr>
                                <td>{{ ++$payRow }}</td>
                                <td>{!! $clientLink !!} - {{ $client['code'] }}</td>
                                <td>{!! $clientBadges['status'] !!}</td>
                                <td>{!! $clientBadges['stage'] !!}</td>
                                <td>{!! $clientBadges['target'] !!}</td>
                                <td class="rpt-num">{{ $payment['payment_date'] }}</td>
                                <td>{{ $payment['id'] }}</td>
                                <td>{{ $payment['type_label'] ?? 'غير محدد' }}</td>
                                <td>{{ $payment['treasury']['name'] ?? 'غير محدد' }}</td>
                                <td class="rpt-num {{ $payment['amount'] < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ number_format($payment['amount'], 2) }}</strong></td>
                                <td>{{ data_get($payment, 'invoice.employee.branch.name', 'غير محدد') }}</td>
                                <td>{{ data_get($payment, 'invoice.employee.name', 'غير محدد') }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold ag-group-row">
                            <td colspan="9">مجموع {{ $client['trade_name'] }}</td>
                            <td class="rpt-num">{{ number_format($group['total_payments'], 2) }}</td>
                            <td colspan="2">-</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse

                    @if ($pagedGroups->total() > 0)
                        {{-- المجموعُ الكلّيُّ للفترة كلِّها لا للصفحة المعروضة --}}
                        <tr class="fw-bold">
                            <td colspan="9">المجموع الكلي{{ $pagedGroups->hasPages() ? ' (كل الصفحات)' : '' }}</td>
                            <td class="rpt-num">{{ number_format($totals['total_payments'] ?? 0, 2) }}</td>
                            <td colspan="2">-</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if ($pagedGroups->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض العملاء {{ $pagedGroups->firstItem() }} إلى {{ $pagedGroups->lastItem() }} من {{ number_format($pagedGroups->total()) }}</small>
                {{ $pagedGroups->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    {{-- مصدرُ الطباعة: يُملأ بصفوف الفترة كلِّها عند الضغط على «طباعة» --}}
    <table class="d-none" id="payPrintTable"><tbody></tbody></table>


    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    @include('stock::components.report-tools')

    <script>
        const PAY_CHART = @json($payChart);
        const PAY_TREASURIES = @json($payDonut);

        let customerPaymentsChart;

        $(document).ready(function() {
            $('.rpt-select2').select2({ dir: 'rtl', width: '100%', allowClear: true, placeholder: 'اختر...' });

            /* التصديرُ والطباعةُ للفترة كلِّها — الصفحةُ لا تحمل إلّا عملاءَ صفحتها،
               فالصفوفُ تُطلب عند الضغط (`?export=rows`) بالفلاتر نفسِها. */
            const PAY_HEADERS = ['#', 'العميل', 'الحالة', 'الوضع', 'الهدف', 'التاريخ', 'رقم المدفوعة', 'نوع السند', 'الخزينة', 'المبلغ', 'تابع لـ', 'منشئ القيد'];
            const PAY_INFO = { 'الفترة': $('#date_from').val() + ' إلى ' + $('#date_to').val(), 'تاريخ التقرير': new Date().toISOString().slice(0, 10) };
            let payRowsCache = null;

            function payMoney(n) {
                return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function fetchPayRows(button) {
                if (payRowsCache) { return Promise.resolve(payRowsCache); }
                const url = new URL(window.location.href);
                url.searchParams.set('export', 'rows');
                url.searchParams.delete('page');
                const $btn = $(button);
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جارٍ التجهيز…');
                return fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
                    .then(function (data) { payRowsCache = data; return data; })
                    .finally(function () { $btn.prop('disabled', false).html(html); });
            }

            $('#exportExcel').on('click', function () {
                fetchPayRows(this).then(function (data) {
                    if (!data.rows.length) { alert('لا صفوف للتصدير'); return; }
                    exportToExcelProfessional({
                        fileName: 'تقرير_مدفوعات_العملاء',
                        mainTitle: 'تقرير مدفوعات العملاء',
                        subTitle: '',
                        reportInfo: PAY_INFO,
                        statisticsCards: [],
                        tableTitle: 'تقرير مدفوعات العملاء',
                        tableHeaders: PAY_HEADERS,
                        columnWidths: [7, 30, 12, 12, 14, 12, 16, 18, 20, 14, 18, 18],
                        tableData: data.rows.map(function (r) {
                            return { values: r.map(function (v, i) { return i === 9 ? payMoney(v) : v; }) };
                        }),
                        summaryRows: [{
                            values: ['', '', '', '', '', '', '', '', 'المجموع الكلي', payMoney(data.total), '', ''],
                            background: 'warningLight', color: 'warning',
                            cellStyles: [null, null, null, null, null, null, null, null, { bold: true }, { bold: true }]
                        }],
                        buttonSelector: '#exportExcel',
                        successMessage: 'تم تصدير التقرير بنجاح',
                        errorMessage: 'تعذّر تصدير التقرير'
                    });
                }).catch(function () { alert('تعذّر تجهيز صفوف التصدير'); });
            });

            $('#printBtn').on('click', function () {
                fetchPayRows(this).then(function (data) {
                    const tbody = document.querySelector('#payPrintTable tbody');
                    const frag = document.createDocumentFragment();
                    data.rows.concat([['', '', '', '', '', '', '', '', 'المجموع الكلي', data.total, '', '']]).forEach(function (r) {
                        const tr = document.createElement('tr');
                        r.forEach(function (v, i) {
                            const td = document.createElement('td');
                            td.textContent = (i === 9 && v !== '') ? payMoney(v) : v;
                            tr.appendChild(td);
                        });
                        frag.appendChild(tr);
                    });
                    tbody.replaceChildren(frag);
                    printTable({
                        tableSelector: '#payPrintTable tbody tr',
                        title: 'تقرير مدفوعات العملاء',
                        infoCards: [],
                        filterInfo: PAY_INFO,
                        tableHeaders: PAY_HEADERS,
                        columnWidths: [],
                        descriptionColumn: 1,
                        windowWidth: 1400,
                        windowHeight: 900
                    });
                }).catch(function () { alert('تعذّر تجهيز صفوف الطباعة'); });
            });

            /* الرسمان من بيانات الخادم نفسِها التي بُني منها الجدول —
               لا طلبَ ثانياً بعد التحميل. */
            renderChart(PAY_CHART);
            renderTreasuryDonut(PAY_TREASURIES);

            /* المدى الجاهز يملأ التاريخين ثمّ يُرسل النموذج، كما تفعل
               أزرارُ الفترة في تقريرَي أعمار الديون. */
            $('#date_type').change(function() {
                if ($(this).val() !== 'custom') {
                    updateDateRangeBasedOnType($(this).val());
                    $('#reportForm').submit();
                }
            });

            $('#summaryViewBtn, #detailViewBtn').click(function() {
                $('#summaryViewBtn, #detailViewBtn').removeClass('btn-primary active').addClass('btn-outline-primary');
                $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
                const isSummary = $(this).attr('id') === 'summaryViewBtn';
                $('#chartSection, #totalsSection').toggle(isSummary);
            });

            function updateDateRangeBasedOnType(dateType) {
                const today = new Date();
                let fromDate, toDate;
                switch (dateType) {
                    case 'today': fromDate = toDate = today; break;
                    case 'yesterday': fromDate = toDate = new Date(today.getTime() - 86400000); break;
                    case 'this_week': fromDate = new Date(today.setDate(today.getDate() - today.getDay())); toDate = new Date(); break;
                    case 'last_week':
                        toDate = new Date(today.setDate(today.getDate() - today.getDay() - 1));
                        fromDate = new Date(new Date(toDate).setDate(toDate.getDate() - 6));
                        break;
                    case 'this_month': fromDate = new Date(today.getFullYear(), today.getMonth(), 1); toDate = new Date(); break;
                    case 'last_month':
                        fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                    case 'this_year': fromDate = new Date(today.getFullYear(), 0, 1); toDate = new Date(); break;
                    case 'last_year': fromDate = new Date(today.getFullYear() - 1, 0, 1); toDate = new Date(today.getFullYear() - 1, 11, 31); break;
                    default: return;
                }
                $('#date_from').val(formatDateForInput(fromDate));
                $('#date_to').val(formatDateForInput(toDate));
            }

            function renderChart(chartData) {
                const canvas = document.getElementById('customerPaymentsChart');
                /* بلا بيانات تعرض البطاقةُ `rpt-empty` من الخادم ولا لوحةَ رسم */
                if (!canvas || typeof Chart === 'undefined' || !(chartData.labels || []).length) return;

                if (customerPaymentsChart) customerPaymentsChart.destroy();

                const theme = agChartTheme();
                const values = chartData.payments || [];

                customerPaymentsChart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [{
                            label: 'مدفوعات العملاء',
                            data: values,
                            backgroundColor: '#10b981',
                            borderRadius: 6, maxBarThickness: 56,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function(context) { return ' ' + agMoney(context.parsed.y); } } }
                        },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0 } },
                            y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                 ticks: { color: theme.muted, callback: function(v) { return agWhole(v); } } }
                        }
                    },
                    plugins: [agValueLabels(values)]
                });
            }

            function renderTreasuryDonut(items) {
                const canvas = document.getElementById('treasuryDonut');
                if (!canvas || typeof Chart === 'undefined' || !items.length) return;

                const theme = agChartTheme();

                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: items.map(function(t) { return t.label; }),
                        datasets: [{
                            data: items.map(function(t) { return t.value; }),
                            backgroundColor: items.map(function(t) { return t.color; }),
                            borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function(context) {
                                const t = items[context.dataIndex];
                                return ' ' + agMoney(t.value) + (t.pct === null ? '' : ' (' + t.pct + '%)');
                            } } }
                        }
                    }
                });
            }

            function formatDateForInput(date) { return date.toISOString().split('T')[0]; }

        });
    </script>
@endsection
