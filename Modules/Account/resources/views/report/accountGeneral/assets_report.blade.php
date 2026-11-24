@extends('account::master')

@section('title')
    تقرير الأصول
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الأصول',
        'crumbs' => [
            'الرئيسية' => null,
            'التقارير' => route('GeneralAccountReports.index'),
            'تقرير الأصول' => null,
        ],
    ])

    <div class="content-body">
        <div class="alert alert-info">
            <i class="feather icon-info"></i>
            هذا التقرير يُبنى من <strong>شجرة الحسابات</strong> — يجلب جميع حسابات الأصول (تحت الحساب الأب رقم {{ \App\Constants\SystemAccountIds::ASSETS }} وكل أحفادها بأي مستوى) مع الأرصدة المحسوبة من القيود المحاسبية.
            <br><small>الافتراضي: عرض الحسابات التفصيلية فقط (بدون العناوين). فعّل خيار "تضمين العناوين" لإظهار حسابات الآباء أيضاً.</small>
        </div>

        <!-- Filter Card -->
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <i class="feather icon-filter"></i> بحث وفلترة
                            </div>
                            <div>
                                <button class="btn btn-outline-success" id="exportExcel">
                                    <i class="feather icon-file-text"></i> تصدير Excel
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="feather icon-printer"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.assetsReport') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-3',
                            ])

                            <div class="form-group col-md-3">
                                <label>من تاريخ (اختياري - للرصيد الافتتاحي)</label>
                                <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>نوع الفترة</label>
                                @php $dt = $dateType ?? 'custom'; @endphp
                                <select name="date_type" class="form-control">
                                    <option value="custom"       {{ $dt=='custom' ? 'selected' : '' }}>مخصص</option>
                                    <option value="this_month"   {{ $dt=='this_month' ? 'selected' : '' }}>هذا الشهر</option>
                                    <option value="last_month"   {{ $dt=='last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                                    <option value="this_quarter" {{ $dt=='this_quarter' ? 'selected' : '' }}>هذا الربع</option>
                                    <option value="this_year"    {{ $dt=='this_year' ? 'selected' : '' }}>هذا العام</option>
                                    <option value="last_year"    {{ $dt=='last_year' ? 'selected' : '' }}>العام الماضي</option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>الفرع</label>
                                <select name="branch" class="form-control">
                                    <option value="">جميع الفروع</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" {{ ($branchFilter ?? '') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>الحساب الأب (يشمل كل أحفاده)</label>
                                <select name="parent_id" class="form-control select2">
                                    <option value="">كل الآباء</option>
                                    @foreach($parentAccounts as $p)
                                        <option value="{{ $p->id }}" {{ ($parentFilter ?? '') == $p->id ? 'selected' : '' }}>
                                            {{ $p->code ? $p->code.' - ' : '' }}{{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label>الحسابات المحددة (متعدد)</label>
                                <select name="account_ids[]" class="form-control select2" multiple>
                                    @foreach($allAccountsInTree as $acc)
                                        <option value="{{ $acc->id }}"
                                            {{ in_array($acc->id, (array)($accountIdsFilter ?? [])) ? 'selected' : '' }}>
                                            {{ $acc->code ? $acc->code.' - ' : '' }}{{ $acc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">اختر حساباً أو أكثر — اتركها فارغة لعرض الكل.</small>
                            </div>

                            <div class="form-group col-md-3">
                                <label>الحالة</label>
                                <select name="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" {{ (string)($statusFilter ?? '') === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="0" {{ (string)($statusFilter ?? '') === '0' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>الحركة</label>
                                <select name="movement" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="with_movement" {{ ($movementFilter ?? '') == 'with_movement' ? 'selected' : '' }}>فيها حركة</option>
                                    <option value="no_movement"   {{ ($movementFilter ?? '') == 'no_movement'   ? 'selected' : '' }}>بدون حركة</option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>تجميع حسب</label>
                                <select name="group_by" class="form-control">
                                    <option value="All"      {{ $groupBy == 'All'      ? 'selected' : '' }}>بدون تجميع</option>
                                    <option value="parent"   {{ in_array($groupBy, ['parent','category']) ? 'selected' : '' }}>الحساب الأب</option>
                                    <option value="status"   {{ $groupBy == 'status'   ? 'selected' : '' }}>الحالة</option>
                                    <option value="movement" {{ $groupBy == 'movement' ? 'selected' : '' }}>وجود الحركة</option>
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label>بحث في الحساب (اسم/كود)</label>
                                <input type="text" name="account_search" class="form-control" placeholder="اسم أو كود الحساب..." value="{{ $accountSearch ?? '' }}">
                            </div>

                            <div class="form-group col-md-3 d-flex align-items-end">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="include_headers" name="include_headers" value="1" {{ ($includeHeaders ?? false) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="include_headers">
                                        تضمين حسابات العناوين (الآباء)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light">
                                <i class="feather icon-search"></i> عرض التقرير
                            </button>
                            <a href="{{ route('GeneralAccountReports.assetsReport') }}" class="btn btn-outline-danger waves-effect waves-light">
                                <i class="feather icon-x"></i> إلغاء الفلترة
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">عدد حسابات الأصول</span>
                <span class="stk-metric__value">{{ number_format($totals['count']) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الرصيد الحالي (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_balance'], 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">حركة الفترة (ريال)</span>
                <span class="stk-metric__value {{ $totals['period_movement'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totals['period_movement'], 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__label">حسابات بحركة / بدون حركة</span>
                <span class="stk-metric__value">{{ $totals['with_movement'] }} / {{ $totals['without_movement'] }}</span>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row mt-2">
            <div class="col-lg-6 col-md-6 col-12">
                <div class="card border shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar bg-light-success p-50 mr-1"><span class="avatar-content"><i class="feather icon-check-circle"></i></span></div>
                        <div>
                            <h4 class="mb-0">{{ $totals['active_count'] }}</h4>
                            <small class="text-muted">حسابات نشطة</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-12">
                <div class="card border shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar bg-light-secondary p-50 mr-1"><span class="avatar-content"><i class="feather icon-pause-circle"></i></span></div>
                        <div>
                            <h4 class="mb-0">{{ $totals['inactive_count'] }}</h4>
                            <small class="text-muted">حسابات غير نشطة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets Table Card -->
        <div class="card" id="assetsCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل حسابات الأصول</h6>
                        <small class="text-muted">
                            عدد الحسابات: {{ $totals['count'] }} • الفترة: {{ $fromDate ?: 'منذ البداية' }} → {{ $toDate }}
                        </small>
                    </div>

                    @if($groupBy !== 'All' && $groupedRows->count() > 0)
                        @foreach($groupedRows as $groupKey => $groupRows)
                            <div class="mb-4">
                                <h5 class="text-primary border-bottom pb-1 mb-2">
                                    <i class="feather icon-folder"></i> {{ $groupKey ?: 'غير مصنف' }}
                                    <span class="badge badge-pill bg-light text-dark-primary">{{ $groupRows->count() }} حساب</span>
                                </h5>

                                <div class="table-responsive">
                                    @include('account::report.accountGeneral.partials.assets_table', ['assets' => $groupRows])
                                </div>

                                <div class="row mt-2 mb-3">
                                    <div class="col-md-4">
                                        <div class="border p-2 rounded text-center">
                                            <small class="text-muted">إجمالي الرصيد الحالي</small>
                                            <h6 class="mb-0 text-primary">{{ number_format($groupRows->sum('closing_balance'), 2) }} ريال</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border p-2 rounded text-center">
                                            <small class="text-muted">إجمالي مدين</small>
                                            <h6 class="mb-0 text-success">{{ number_format($groupRows->sum('total_debit'), 2) }} ريال</h6>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border p-2 rounded text-center">
                                            <small class="text-muted">إجمالي دائن</small>
                                            <h6 class="mb-0 text-danger">{{ number_format($groupRows->sum('total_credit'), 2) }} ريال</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="table-responsive">
                            @include('account::report.accountGeneral.partials.assets_table', ['assets' => $rows])
                        </div>
                    @endif

                    @if($rows->count() == 0)
                        <div class="alert alert-warning text-center">
                            <i class="feather icon-alert-circle"></i>
                            لا توجد حسابات أصول مطابقة لمعايير البحث
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="feather icon-pie-chart"></i> الملخص النهائي</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-white py-2"><h6 class="mb-0"><i class="feather icon-info text-muted"></i> معلومات عامة</h6></div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">عدد حسابات الأصول</td>
                                        <td class="text-left fw-bold">{{ number_format($totals['count']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">حسابات نشطة</td>
                                        <td class="text-left fw-bold text-success">{{ number_format($totals['active_count']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">حسابات غير نشطة</td>
                                        <td class="text-left fw-bold text-secondary">{{ number_format($totals['inactive_count']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">حسابات بحركة في الفترة</td>
                                        <td class="text-left fw-bold text-info">{{ number_format($totals['with_movement']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">حسابات بدون حركة</td>
                                        <td class="text-left fw-bold">{{ number_format($totals['without_movement']) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-white py-2"><h6 class="mb-0"><i class="feather icon-dollar-sign text-muted"></i> القيم المالية</h6></div>
                            <div class="card-body py-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">الرصيد الافتتاحي الإجمالي</td>
                                        <td class="text-left fw-bold">{{ number_format($totals['opening_balance'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">إجمالي مدين</td>
                                        <td class="text-left fw-bold text-success">{{ number_format($totals['total_debit'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">إجمالي دائن</td>
                                        <td class="text-left fw-bold text-danger">{{ number_format($totals['total_credit'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">حركة الفترة</td>
                                        <td class="text-left fw-bold {{ $totals['period_movement'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totals['period_movement'], 2) }}</td>
                                    </tr>
                                    <tr class="border-top">
                                        <td class="text-muted fw-bold">إجمالي الرصيد الحالي</td>
                                        <td class="text-left fw-bold text-primary">{{ number_format($totals['total_balance'], 2) }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer py-2"><small class="text-muted">جميع القيم بالريال السعودي</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script src="{{ asset('js/print-table.js') }}"></script>

<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ placeholder: 'اختر...', allowClear: true, dir: 'rtl', width: '100%' });
        }

        $('#exportExcel').on('click', function(e) { e.preventDefault(); exportAssetsReport(); });
        $('#printBtn').on('click', function(e) { e.preventDefault(); printAssetsReport(); });
    });

    function fmt(n) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ===== تصدير Excel احترافي =====
    function exportAssetsReport() {
        const rows = @json($rows);
        const totals = @json($totals);
        const fromDate = '{{ $fromDate ?? '' }}';
        const toDate   = '{{ $toDate ?? '' }}';
        const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';
        const parentName = $('select[name="parent_id"] option:selected').text() || 'كل الآباء';

        const tableData = rows.map((r, i) => ({
            values: [
                i + 1,
                r.code || '-',
                r.name,
                r.parent_name || '-',
                fmt(r.opening_balance),
                fmt(r.total_debit),
                fmt(r.total_credit),
                fmt(r.period_movement),
                fmt(r.closing_balance),
                r.is_active ? 'نشط' : 'غير نشط',
                r.has_movement ? 'فيه حركة' : 'بدون حركة'
            ],
            alignments: ['center','center','right','right','center','center','center','center','center','center','center'],
            cellStyles: [
                null, null, null, null, null,
                { color: 'success' }, { color: 'danger' },
                null, { color: 'primary', bold: true },
                null, null
            ]
        }));

        const config = {
            fileName: 'تقرير_الأصول',
            mainTitle: 'تقرير الأصول',
            subTitle: 'نظام المحاسبة المتكامل',
            reportInfo: {
                'الفترة': (fromDate || 'منذ البداية') + ' إلى ' + (toDate || '-'),
                'الفرع': branchName,
                'الحساب الأب': parentName,
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            statisticsCards: [
                { title: 'عدد حسابات الأصول', value: totals.count, unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' },
                { title: 'إجمالي مدين', value: fmt(totals.total_debit), unit: ' ' + window.AppCurrency.symbol, titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                { title: 'إجمالي دائن', value: fmt(totals.total_credit), unit: ' ' + window.AppCurrency.symbol, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
                { title: 'الرصيد الحالي', value: fmt(totals.total_balance), unit: ' ' + window.AppCurrency.symbol, titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' }
            ],
            tableTitle: 'تفاصيل حسابات الأصول',
            tableHeaders: ['#', 'الكود', 'اسم الحساب', 'الأب', 'افتتاحي', 'مدين', 'دائن', 'حركة الفترة', 'الرصيد الحالي', 'الحالة', 'الحركة'],
            columnWidths: [5, 10, 26, 18, 12, 12, 12, 14, 14, 10, 12],
            tableData: tableData,
            summaryRows: [{
                values: ['', '', '', 'الإجمالي', fmt(totals.opening_balance), fmt(totals.total_debit), fmt(totals.total_credit), fmt(totals.period_movement), fmt(totals.total_balance), '', ''],
                cellStyles: [null, null, null, { bold: true }, { bold: true }, { bold: true, color: 'success' }, { bold: true, color: 'danger' }, { bold: true }, { bold: true, color: 'primary' }, null, null]
            }],
            summarySheet: {
                sheetName: 'الملخص',
                title: 'ملخص تقرير الأصول',
                tables: [
                    { title: 'إحصائيات', headerColor: 'primary', data: [
                        { label: 'عدد حسابات الأصول', value: totals.count, unit: 'حساب', color: 'primary' },
                        { label: 'حسابات نشطة', value: totals.active_count, unit: 'حساب', color: 'success' },
                        { label: 'حسابات غير نشطة', value: totals.inactive_count, unit: 'حساب', color: 'warning' },
                        { label: 'حسابات بحركة', value: totals.with_movement, unit: 'حساب', color: 'primary' },
                        { label: 'حسابات بدون حركة', value: totals.without_movement, unit: 'حساب' }
                    ]},
                    { title: 'القيم المالية', headerColor: 'success', data: [
                        { label: 'الرصيد الافتتاحي', value: fmt(totals.opening_balance), unit: ' ' + window.AppCurrency.symbol },
                        { label: 'إجمالي مدين', value: fmt(totals.total_debit), unit: ' ' + window.AppCurrency.symbol, color: 'success' },
                        { label: 'إجمالي دائن', value: fmt(totals.total_credit), unit: ' ' + window.AppCurrency.symbol, color: 'danger' },
                        { label: 'حركة الفترة', value: fmt(totals.period_movement), unit: ' ' + window.AppCurrency.symbol },
                        { label: 'الرصيد الحالي', value: fmt(totals.total_balance), unit: ' ' + window.AppCurrency.symbol, color: 'primary' }
                    ]}
                ]
            },
            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير تقرير الأصول بنجاح!',
            errorMessage: 'حدث خطأ أثناء التصدير'
        };

        exportToExcelProfessional(config);
    }

    // ===== الطباعة المخصصة =====
    function printAssetsReport() {
        const totals = @json($totals);
        const fromDate = '{{ $fromDate ?? '' }}';
        const toDate   = '{{ $toDate ?? '' }}';
        const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';
        const parentName = $('select[name="parent_id"] option:selected').text() || 'كل الآباء';

        printTable({
            tableSelector: '.assetsTableBody tr',
            title: 'تقرير الأصول',
            infoCards: [
                { label: 'عدد الحسابات', value: totals.count },
                { label: 'إجمالي مدين', value: fmt(totals.total_debit) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                { label: 'إجمالي دائن', value: fmt(totals.total_credit) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                { label: 'الرصيد الحالي', value: fmt(totals.total_balance) + ' ' + window.AppCurrency.symbol, class: 'text-primary' },
                { label: 'حركة الفترة', value: fmt(totals.period_movement) + ' ' + window.AppCurrency.symbol },
                { label: 'حسابات نشطة', value: totals.active_count },
                { label: 'حسابات بحركة', value: totals.with_movement }
            ],
            filterInfo: {
                'الفترة': (fromDate || 'منذ البداية') + ' → ' + (toDate || '-'),
                'الفرع': branchName,
                'الحساب الأب': parentName,
                'تاريخ الطباعة': new Date().toLocaleDateString('ar-SA')
            },
            tableHeaders: ['#', 'الكود', 'اسم الحساب', 'الأب', 'افتتاحي', 'مدين', 'دائن', 'حركة الفترة', 'الرصيد الحالي', 'الحالة', 'الحركة'],
            columnWidths: ['4%', '7%', '18%', '14%', '9%', '9%', '9%', '11%', '11%', '4%', '4%'],
            descriptionColumn: 3,
            windowWidth: 1400,
            windowHeight: 900
        });
    }
</script>
@endsection

@section('styles')
<style>
    .stat-card { transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-5px); }
    .table th { white-space: nowrap; }
    @media print {
        .card-title > div:last-child, .form-actions, #reportForm, .breadcrumb-wrapper, .alert-info,
        .btn, .pagination, nav { display: none !important; }
    }
</style>
@endsection
