{{--
    تقرير كشف حساب العملاء — على نمط التصميم المعتمد («أعمار ديون الفواتير»):
    ترويسةٌ فاتحة، شبكةُ فلترةٍ واحدة، خمسةُ مؤشّرات، ثمّ بطاقةُ كشفٍ لكلّ عميل.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها ومعرّفاتها — ظاهرةٌ كلُّها في شبكةٍ واحدة.
    • الكشفُ يُبنى في JavaScript من `SERVER_STATEMENT` (يأتي مع الصفحة)،
      والدالّةُ `createClientStatement` تبني الجدولَ نفسَه بأعمدته السبعة
      وصفَّي الافتتاحي والختامي — تغيّرت أصنافُ ما تبنيه وحدَها.
    • التصديرُ (`exportToExcelProfessional`) بلا تعديل، والطباعةُ تطبع
      المؤشّراتِ ثمّ الكشوفَ كما كانت.

    لا ترقيمَ صفحات: الكشفُ جدولٌ لكلّ عميل لا جدولٌ واحدٌ طويل، وتقطيعُ
    كشفِ عميلٍ واحدٍ على صفحاتٍ يُخفي رصيدَه الجاري عن قارئه.
--}}
@extends('client::master')
@section('title')
    تقرير كشف حساب العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    {{-- اسمُ العميل رابطاً إلى صفحته — نفس قاعدة reports.partials.client-link؛ العنوانُ يُبنى في JavaScript فلا يُضمَّن الملفُّ الجزئيّ --}}
    <style>
        .rpt-client-link{color:inherit;text-decoration:none;border-bottom:1px dashed currentColor}.rpt-client-link:hover{color:var(--app-primary,#7367f0)}
        .rpt .ag-statement-code { color: var(--rpt-muted); font-weight: 500; font-size: .84rem; }
        .rpt .ag-statement-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .rpt .ag-statement .rpt-scroll { border-top: 0; }
    </style>
@endsection

@section('content')
@php
    $periodLabel = null;
    if (!empty($periodContext['start']) && !empty($periodContext['end'])) {
        $periodLabel = 'من '.\Illuminate\Support\Carbon::parse($periodContext['start'])->format('d/m/Y')
            .' إلى '.\Illuminate\Support\Carbon::parse($periodContext['end'])->format('d/m/Y');
    }
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'file-invoice-dollar',
        'headTitle' => 'كشف حساب العملاء',
        'headSubtitle' => 'حركة كل عميلٍ المالية — مبيعات وتحصيلات ورصيد — حسب الفلاتر المختارة',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.account-statement',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="filter-form">

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" id="date_from" name="date_from" class="form-control" aria-label="من تاريخ">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" id="date_to" name="date_to" class="form-control" aria-label="إلى تاريخ">
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

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'client_id',
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
                        <select id="status" name="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
                            <option value="">كل الحالات</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
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
                        <label for="category">تصنيف العميل</label>
                        <select id="category" name="category" class="form-control rpt-select2">
                            <option value="">جميع التصنيفات</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    {{-- يقيس الرصيدَ الختاميَّ لا مجموعَ الحركات — انظر المتحكّم --}}
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="hide_zero_balance" name="hide_zero_balance" value="1">
                        <label class="form-check-label" for="hide_zero_balance">إخفاء من رصيدُه صفر</label>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ url()->current() }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== نتائج التقرير ===================== --}}
    <div id="report-container" class="d-none">
        {{-- المؤشّراتُ وتنبيهُ القصّ — يملؤهما JavaScript --}}
        <div id="statement-kpis"></div>

        {{-- رأسُ الكشوف: العددُ وأزرارُ التصدير والطباعة (خارج ما يُطبع) --}}
        <div class="rpt-card" id="reportCard">
            <div class="rpt-card__head ag-table-head">
                <h4>
                    <i class="fas fa-table"></i> كشوفات حسابات العملاء
                    <span class="rpt-pill rpt-pill-secondary" id="statementCount">0 عميل</span>
                </h4>
                <div class="ag-tools no-print">
                    <button type="button" id="export-excel" class="btn btn-sm ag-btn-soft d-none"><i class="fas fa-file-excel"></i> تصدير</button>
                    <button type="button" id="print-button" class="btn btn-sm ag-btn-soft d-none"><i class="fas fa-print"></i> طباعة</button>
                </div>
            </div>
        </div>

        {{-- بطاقةُ كشفٍ لكلّ عميل — يملؤها JavaScript --}}
        <div id="print-section"></div>
    </div>

    <div id="no-selection" class="rpt-card">
        <div class="rpt-empty">
            <i class="fas fa-inbox"></i>
            يرجى اختيار الفلاتر لعرض كشوفات الحسابات
        </div>
    </div>

    <div id="error-message" class="alert alert-danger d-none"></div>

    @include('sales::sals_report.report_partials.print-footer')
</div>

<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('scripts')
{{--
    ══════════ لماذا لا jQuery هنا ══════════

    كانت الصفحةُ تُحمّل `jquery-3.6.0` مرّةً ثانية في ذيلها، والقالبُ قد
    حمّلها ومعها select2 وDataTables في رأسه. والتحميلُ الثاني **يستبدل**
    `window.jQuery` بنسخةٍ نظيفة، فتسقط عنها كلُّ إضافةٍ سُجّلت على الأولى:

        Uncaught TypeError: $(...).select2 is not a function
        Uncaught TypeError: $(...).DataTable is not a function

    فلا يُبنى مُنتقي «تابع لـ» أصلاً (ينتظر select2 ستَّ ثوانٍ ثمّ يستسلم)،
    ولا يُهيَّأ مُنتقي العميل، ولا تعمل بقيّةُ الشاشة — وهو ما ظهر
    للمستخدم: «لا يجلب حساباً ولا فروعاً ولا عملاء».

    السطرُ محذوف. ومن يضيف مكتبةً هنا فليتأكّد أنّها لا تعيد تحميل jQuery.
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    let currentReportData = null;
    const CLIENT_SHOW_URL = @json(route('clients.show', ['id' => '__CLIENT__']));

    $(document).ready(function() {
        $('.rpt-select2').select2({
            dir: "rtl",
            language: { noResults: function() { return "لا توجد نتائج"; } },
            width: '100%'
        });

        /* لا `preventDefault`: النموذجُ يُرسَل ويُعاد تحميلُ الصفحة، فتأتي
           النتيجةُ من الخادم مع الصفحة نفسِها — كما في تقريرَي أعمار
           الديون. ولم يبقَ طلبٌ ثانٍ بعد التحميل. */

        const SERVER_STATEMENT = @json($statementData ?? null);
        const SERVER_MESSAGE = @json($statementMessage ?? null);

        if (SERVER_STATEMENT) {
            currentReportData = SERVER_STATEMENT;
            displayReport(SERVER_STATEMENT);
            $('#no-selection').addClass('d-none');
            $('#report-container').removeClass('d-none');
            $('#print-button, #export-excel').removeClass('d-none');
        } else if (SERVER_MESSAGE) {
            $('#error-message').html(SERVER_MESSAGE).removeClass('d-none');
        }

        $('#filter-form').on('reset', function() {
            setTimeout(function() {
                $('#tree_node').val('');
                $('.rpt-select2').val(null).trigger('change');
                if (window.__rptInitAffiliationTree) {
                    window.__rptInitAffiliationTree();
                }
                $('#report-container').addClass('d-none');
                $('#no-selection').removeClass('d-none');
                $('#print-button, #export-excel').addClass('d-none');
                currentReportData = null;
            }, 10);
        });

        $('#export-excel').on('click', function() {
            exportCustomerAccountStatementToExcel();
        });

        /* حُذفت `loadReport()`: كانت تجمع الفلاتر يدوياً حقلاً حقلاً —
           فكلُّ فلترٍ جديدٍ يحتاج سطراً فيها وإلّا سقط صامتاً — ثمّ تطلب
           البيانات بعد التحميل. صار الإرسالُ من النموذج نفسِه. */

        function kpiCard(color, icon, label, value, sub) {
            return `
                <div class="ag-kpi" style="--c:${color}">
                    <div>
                        <div class="ag-kpi__label">${label}</div>
                        <div class="ag-kpi__value"><bdi>${value}</bdi></div>
                        ${sub ? '<div class="ag-kpi__sub">' + sub + '</div>' : ''}
                    </div>
                    <span class="ag-kpi__icon"><i class="fas fa-${icon}"></i></span>
                </div>
            `;
        }

        function displayReport(data) {
            const groupedClients = data.grouped_clients;
            const kpiSection = $('#statement-kpis');
            const printSection = $('#print-section');
            kpiSection.empty();
            printSection.empty();

            const totals = data.totals || {};
            // المؤشّراتُ لكلّ المطابقين؛ الكشوفُ التفصيلية قد تُقصر على أوّل 200
            const clientsSub = data.truncated && totals.shown_clients
                ? 'عميل · الكشوف المعروضة ' + Number(totals.shown_clients).toLocaleString('en-US')
                : 'عميل';
            kpiSection.append(`
                <div class="ag-kpis">
                    ${kpiCard('#06b6d4', 'users', 'عدد العملاء', totals.total_clients ?? 0, clientsSub)}
                    ${kpiCard('#f97316', 'flag', 'الرصيد الافتتاحي', totals.opening_balance ?? 0, '﷼')}
                    ${kpiCard('#ef4444', 'arrow-up', 'إجمالي المدين', totals.total_debit ?? 0, '﷼')}
                    ${kpiCard('#10b981', 'arrow-down', 'إجمالي الدائن', totals.total_credit ?? 0, '﷼')}
                    ${kpiCard('#8b5cf6', 'balance-scale', 'المديونية الختامية', totals.closing_debt ?? '0.00', '﷼ · ' + (totals.closing_debt_clients ?? 0) + ' عميل مدين — عليهم لنا')}
                    ${kpiCard('#10b981', 'hand-holding-usd', 'أرصدة دائنة للعملاء', totals.closing_credit ?? '0.00', '﷼ · ' + (totals.closing_credit_clients ?? 0) + ' عميل — لهم علينا، لا تُطرح من المديونية')}
                </div>
            `);

            // القصُّ يُعلَن ولا يُسكت عنه — انظر MAX_CLIENTS في المتحكّم
            if (data.truncated && data.notice) {
                kpiSection.append(
                    '<div class="alert alert-warning no-print"><i class="fas fa-info-circle"></i> ' + data.notice + '</div>'
                );
            }

            $('#statementCount').text((totals.total_clients ?? 0) + ' عميل');

            $.each(groupedClients, function(clientName, clientData) {
                const client = clientData.client;
                const operations = clientData.operations;
                const clientTotals = clientData.totals;

                const clientCard = createClientStatement(client, operations, clientTotals);
                printSection.append(clientCard);
            });
        }

        function createClientStatement(client, operations, totals) {
            /*
                الصفُّ الأوّل هو الرصيدُ **الافتتاحي** — رصيدُ ما قبل بداية
                المدى. كان الكشفُ يبدأ من صفرٍ دائماً، فلا يوافق ختامُه رصيدَ
                العميل الفعلي ولا رقمَ تقرير الأرصدة.
            */
            let operationsRows = `
                <tr style="background:rgba(0,0,0,.03); font-weight:600">
                    <td class="rpt-num">—</td>
                    <td class="rpt-name">رصيد افتتاحي</td>
                    <td class="text-center"><span class="text-muted">-</span></td>
                    <td>ما قبل بداية المدى</td>
                    <td class="rpt-num">—</td>
                    <td class="rpt-num">—</td>
                    <td class="rpt-num"><strong>${totals.opening_balance ?? '0.00'} ﷼</strong></td>
                </tr>
            `;

            operations.forEach(function(op) {
                operationsRows += `
                    <tr>
                        <td class="rpt-num">${formatDate(op.date)}</td>
                        <td class="rpt-name" style="color:${getColorByType(op.reference_type)}">${op.type}</td>
                        <td class="text-center">${op.reference ? '<span class="rpt-pill rpt-pill-secondary">' + op.reference + '</span>' : '<span class="text-muted">-</span>'}</td>
                        <td>${op.description || '-'}</td>
                        <td class="rpt-num rpt-neg">${op.debit > 0 ? parseFloat(op.debit).toFixed(2) : '—'}</td>
                        <td class="rpt-num rpt-pos">${op.credit > 0 ? parseFloat(op.credit).toFixed(2) : '—'}</td>
                        <td class="rpt-num"><strong>${parseFloat(op.balance).toFixed(2)} ﷼</strong></td>
                    </tr>
                `;
            });

            operationsRows += `
                <tr style="background:rgba(0,0,0,.05); font-weight:700">
                    <td colspan="4">الرصيد الختامي</td>
                    <td class="rpt-num rpt-neg">${totals.debit ?? '0.00'}</td>
                    <td class="rpt-num rpt-pos">${totals.credit ?? '0.00'}</td>
                    <td class="rpt-num"><strong>${totals.final_balance ?? '0.00'} ﷼</strong></td>
                </tr>
            `;

            return `
                <div class="rpt-card ag-statement">
                    <div class="rpt-card__head ag-table-head">
                        <h4>
                            <i class="fas fa-user-circle"></i> ${client.id ? `<a href="${CLIENT_SHOW_URL.replace('__CLIENT__', client.id)}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل">${client.trade_name}</a>` : client.trade_name}
                            <small class="ag-statement-code">— ${client.code}</small>
                            <span class="rpt-pill rpt-pill-secondary" data-print-skip>${operations.length} حركة</span>
                        </h4>
                        <div class="ag-statement-chips">
                            ${client.phone ? '<span class="rpt-pill rpt-pill-secondary"><i class="fas fa-phone"></i> ' + client.phone + '</span>' : ''}
                            ${client.branch ? '<span class="rpt-pill rpt-pill-secondary"><i class="fas fa-code-branch"></i> ' + client.branch + '</span>' : ''}
                            ${client.status ? '<span class="rpt-pill rpt-pill-info">' + client.status + '</span>' : ''}
                            ${client.target ? '<span class="rpt-pill" style="background:' + (client.target_color || '#6c757d') + '; color:#fff">' + client.target + '</span>' : ''}
                        </div>
                    </div>

                    <div class="rpt-mini">
                        <div><small>الرصيد الافتتاحي</small><strong>${totals.opening_balance || '0.00'} ﷼</strong></div>
                        <div><small>إجمالي المدين</small><strong class="rpt-neg">${totals.debit || '0.00'} ﷼</strong></div>
                        <div><small>إجمالي الدائن</small><strong class="rpt-pos">${totals.credit || '0.00'} ﷼</strong></div>
                        <div><small>الرصيد الختامي</small><strong>${totals.final_balance || '0.00'} ﷼</strong> <span class="rpt-pill ${totals.balance_type === 'مدين' ? 'rpt-pill-danger' : 'rpt-pill-success'}">${totals.balance_type || ''}</span></div>
                    </div>

                    <div class="rpt-scroll table-responsive">
                        <table class="rpt-table ag-table">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>نوع العملية</th>
                                    <th>الرقم</th>
                                    <th>الوصف</th>
                                    <th>مدين</th>
                                    <th>دائن</th>
                                    <th>الرصيد</th>
                                </tr>
                            </thead>
                            <tbody>${operationsRows}</tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('ar-SA', { year: 'numeric', month: '2-digit', day: '2-digit' });
        }

        function getColorByType(refType) {
            const colors = {
                'SI': '#ea5455', 'PI': '#ea5455', 'DI': '#ea5455',
                'SR': '#ff9f43', 'PR': '#ff9f43', 'CN': '#ff9f43', 'DN': '#ff9f43',
                'RV': '#28c76f', 'PV': '#28c76f', 'PAY': '#28c76f', 'REC': '#28c76f',
                'JV': '#7367f0', 'ACC': '#7367f0', 'STK': '#7367f0', 'OPB': '#7367f0',
                'EXP': '#9933cc', 'ADV': '#9933cc', 'DEP': '#9933cc'
            };
            return colors[refType] || '#6e6b7b';
        }

        $('#print-button').on('click', function() {
            printCustomerAccountStatement();
        });

        function printCustomerAccountStatement() {
            /* المطبوعُ = المؤشّراتُ ثمّ الكشوفُ، كما كان حين جمعهما عنصرٌ
               واحد. شارةُ «N حركة» في رأس كلّ كشفٍ للشاشة وحدَها. */
            const printable = $('<div>')
                .append($('#statement-kpis').clone().contents())
                .append($('#print-section').clone().contents());
            printable.find('[data-print-skip]').remove();
            const content = printable.html();
            const dateFrom = $('#date_from').val() || 'غير محدد';
            const dateTo = $('#date_to').val() || 'غير محدد';
            const clientName = $('#client_id option:selected').text() || 'جميع العملاء';
            const branchName = $('#branch option:selected').text() || 'جميع الفروع';

            const printWindow = window.open('', '', 'height=800,width=1200');
            printWindow.document.write('<html><head><title>كشف حساب عميل - طباعة</title>');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: "Cairo", "Arial", sans-serif; margin: 20px; direction: rtl; }
                .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
                .print-header h1 { font-size: 24px; margin: 0; }
                .filter-info { background: #f8f9fa; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
                .filter-info span { margin: 0 15px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: right; }
                th { background-color: #f8f9fa; font-weight: bold; text-align: center; }
                @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div class="print-header"><h1>تقرير كشف حساب العملاء</h1></div>');
            printWindow.document.write('<div class="filter-info">');
            printWindow.document.write('<span><strong>العميل:</strong> ' + clientName + '</span>');
            printWindow.document.write('<span><strong>الفرع:</strong> ' + branchName + '</span>');
            printWindow.document.write('<span><strong>الفترة:</strong> من ' + dateFrom + ' إلى ' + dateTo + '</span>');
            printWindow.document.write('</div>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }

        function exportCustomerAccountStatementToExcel() {
            if (!currentReportData || !currentReportData.grouped_clients) {
                alert('لا توجد بيانات للتصدير');
                return;
            }

            const dateFrom = $('#date_from').val() || 'غير محدد';
            const dateTo = $('#date_to').val() || 'غير محدد';
            const clientName = $('#client_id option:selected').text() || 'جميع العملاء';
            const branchName = $('#branch option:selected').text() || 'جميع الفروع';
            const groupName = $('#region_group option:selected').text() || 'جميع المجموعات';

            const tableData = [];
            let totalDeposits = 0;
            let totalWithdraws = 0;
            // الرصيدُ الختاميّ = افتتاحيٌّ + مدين − دائن، لا مدين − دائن وحدَهما
            let totalClosing = 0;
            const statementNumber = (value) => parseFloat(String(value ?? 0).replace(/,/g, '')) || 0;
            let rowNumber = 1;

            $.each(currentReportData.grouped_clients, function(clientName, clientData) {
                const client = clientData.client;
                const operations = clientData.operations;
                const totals = clientData.totals;

                tableData.push({
                    values: ['', client.code + ' - ' + client.trade_name, '', '', '', '', ''],
                    mergeFrom: 2, mergeTo: 7,
                    background: 'grayLight', color: 'black',
                    cellStyles: [null, { bold: true, alignment: 'center' }, null, null, null, null, null]
                });

                operations.forEach(function(op) {
                    tableData.push({
                        values: [
                            rowNumber++, formatDate(op.date), op.type || '-', op.reference || '-', op.description || '-',
                            op.is_deposit ? formatNumber(parseFloat(op.amount)) : '',
                            !op.is_deposit ? formatNumber(parseFloat(op.amount)) : '',
                            formatNumber(parseFloat(op.balance))
                        ],
                        alignments: ['center', 'center', 'center', 'center', 'right', 'center', 'center', 'center'],
                        cellStyles: [
                            null, null, null, null, null,
                            op.is_deposit ? { color: 'danger', bold: true } : null,
                            !op.is_deposit ? { color: 'success', bold: true } : null,
                            { bold: true }
                        ]
                    });
                });

                tableData.push({
                    values: ['', 'ملخص: ' + client.trade_name, '', '', '', formatNumber(totals.deposits || 0), formatNumber(totals.withdraws || 0), formatNumber(totals.final_balance || 0)],
                    mergeFrom: 1, mergeTo: 4,
                    background: 'warningLight', color: 'warning',
                    cellStyles: [null, { bold: true, alignment: 'right' }, null, null, null, { bold: true, color: 'danger' }, { bold: true, color: 'success' }, { bold: true }]
                });

                /* المجاميعُ تصل منسَّقةً «40,838.43»، و`parseFloat` يقف عند
                   الفاصلة فيقرأ 40 — فكان إجماليُّ التصدير أرقاماً مبتورة. */
                totalDeposits += statementNumber(totals.deposits);
                totalWithdraws += statementNumber(totals.withdraws);
                totalClosing += statementNumber(totals.final_balance);
            });

            const config = {
                fileName: 'كشف_حساب_العملاء',
                mainTitle: 'تقرير كشف حساب العملاء',
                subTitle: 'نظام إدارة العملاء',
                reportInfo: {
                    'الفترة': `من ${dateFrom} إلى ${dateTo}`,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                    'العميل': clientName, 'الفرع': branchName, 'المجموعة': groupName
                },
                statisticsCards: [
                    { title: 'إجمالي المبيعات', value: formatNumber(totalDeposits), unit: ' ﷼', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
                    { title: 'إجمالي التحصيلات', value: formatNumber(totalWithdraws), unit: ' ﷼', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                    { title: 'صافي الرصيد', value: formatNumber(totalClosing), unit: ' ﷼', titleColor: 'purple', valueColor: totalClosing >= 0 ? 'danger' : 'success', backgroundColor: 'purpleLight', borderColor: 'purple' }
                ],
                tableTitle: 'تفاصيل حركات العملاء',
                tableHeaders: ['#', 'التاريخ', 'نوع العملية', 'الرقم', 'الوصف', 'مدين (مبيعات)', 'دائن (تحصيل)', 'الرصيد'],
                columnWidths: [6, 12, 14, 12, 40, 14, 14, 14],
                tableData: tableData,
                summaryRows: [
                    { values: ['', '', '', '', 'إجمالي المبيعات', formatNumber(totalDeposits), '', ''], mergeFrom: 1, mergeTo: 5, background: 'dangerBg', color: 'danger', borderColor: 'danger' },
                    { values: ['', '', '', '', 'إجمالي التحصيلات', '', formatNumber(totalWithdraws), ''], mergeFrom: 1, mergeTo: 5, background: 'successBg', color: 'success', borderColor: 'success' },
                    { values: ['', '', '', '', 'صافي الرصيد', '', '', formatNumber(totalClosing)], mergeFrom: 1, mergeTo: 5, background: 'purpleLight', color: 'purple', borderColor: 'purple' }
                ],
                buttonSelector: '#export-excel',
                successMessage: 'تم تصدير كشف الحساب بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

    });
</script>
@endsection
