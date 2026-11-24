@extends('onlinestore::master')

@section('title', 'تقرير المنتجات')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
<style>
    .rank-badge {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
    }
    .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4a); color: #333; }
    .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #333; }
    .rank-3 { background: linear-gradient(135deg, #cd7f32, #dda15e); color: #fff; }
    .rank-default { background: #f0f0f0; color: #666; }
</style>
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير المنتجات',
            'icon' => 'package',
            'sub' => 'أداء المبيعات حسب المنتج والفئة والعلامة التجارية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'المنتجات' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> الفترة الزمنية</h6>
                </div>
                <form method="GET">
                    <div class="row">
                        {{-- الفترة المحاسبية + مدًى حرّ — نفس الجزء المستعمل في تقارير الحسابات --}}
                        @include('partials.accounting-period-filter', [
                            'period' => $accountingPeriod ?? [],
                            'withDates' => true,
                            'fromName' => 'start_date',
                            'toName' => 'end_date',
                            'colClass' => 'col-md-4 col-sm-6',
                        ])
                    </div>
                    <div class="stk-filters__foot">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-filter"></i> تصفية
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">إجمالي الوحدات المباعة</span>
                <span class="stk-metric__value">{{ number_format($totalProductsSold) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-box"></i></span>
                <span class="stk-metric__label">منتجات مختلفة</span>
                <span class="stk-metric__value">{{ number_format($uniqueProductsSold) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">متوسط الوحدات/طلب</span>
                <span class="stk-metric__value">{{ number_format($avgUnitsPerOrder, 1) }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> المبيعات حسب الفئة</h6>
                    </div>
                    <div class="card-body">
                        <div id="categoryChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-bar-chart-2"></i> المبيعات حسب العلامة التجارية</h6>
                    </div>
                    <div class="card-body">
                        <div id="brandChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أفضل المنتجات مبيعاً</h6>
                        <div class="no-print">
                            <button type="button" id="printTopProductsBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportTopProductsBtn" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="topProductsTable">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>الطلبات</th>
                                    <th>متوسط السعر</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProducts as $index => $item)
                                    <tr>
                                        <td>
                                            <span class="rank-badge {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-default' }}">
                                                {{ $index + 1 }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="stk-media">
                                                @if ($item->product && $item->product->images)
                                                    @php $images = is_array($item->product->images) ? $item->product->images : json_decode($item->product->images, true); @endphp
                                                    @if (!empty($images))
                                                        <span class="stk-media__thumb"><img src="{{ asset('storage/' . $images[0]) }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                                    @else
                                                        <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                                    @endif
                                                @else
                                                    <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                                @endif
                                                <span class="stk-media__body">
                                                    <span class="stk-media__title">{{ $item->product->name ?? 'منتج محذوف' }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="stk-num">{{ number_format($item->total_quantity) }}</td>
                                        <td class="stk-num">{{ number_format($item->orders_count) }}</td>
                                        <td class="stk-num">{{ number_format($item->avg_price, 2) }}</td>
                                        <td class="stk-num fw-bold text-success">{{ number_format($item->total_revenue, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-down text-danger"></i> الأقل مبيعاً</h6>
                    </div>
                    <div class="card-body">
                        @forelse($lowSellingProducts as $item)
                            <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                <div class="stk-media">
                                    @if ($item->product && $item->product->images)
                                        @php $images = is_array($item->product->images) ? $item->product->images : json_decode($item->product->images, true); @endphp
                                        @if (!empty($images))
                                            <span class="stk-media__thumb"><img src="{{ asset('storage/' . $images[0]) }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                        @endif
                                    @else
                                        <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                    @endif
                                    <span class="stk-media__body">
                                        <span class="stk-media__title">{{ Str::limit($item->product->name ?? 'محذوف', 20) }}</span>
                                    </span>
                                </div>
                                <span class="badge badge-light-danger">{{ $item->total_quantity }} قطعة</span>
                            </div>
                        @empty
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                <p class="stk-empty__hint mb-0">لا توجد بيانات</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="stk-table-head__title"><i class="feather icon-folder"></i> تفاصيل الفئات</h6>
                        <div class="no-print">
                            <button type="button" id="printCategoryBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportCategoryBtn" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="salesByCategoryTable">
                            <thead>
                                <tr>
                                    <th>الفئة</th>
                                    <th>الكمية</th>
                                    <th>الطلبات</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salesByCategory as $cat)
                                    <tr>
                                        <td class="fw-bold">{{ $cat->name }}</td>
                                        <td class="stk-num">{{ number_format($cat->total_quantity) }}</td>
                                        <td class="stk-num">{{ number_format($cat->orders_count) }}</td>
                                        <td class="stk-num text-success">{{ number_format($cat->total_revenue, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> تفاصيل العلامات التجارية</h6>
                        <div class="no-print">
                            <button type="button" id="printBrandBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportBrandBtn" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="salesByBrandTable">
                            <thead>
                                <tr>
                                    <th>العلامة التجارية</th>
                                    <th>الكمية</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salesByBrand as $brand)
                                    <tr>
                                        <td class="fw-bold">{{ $brand->name }}</td>
                                        <td class="stk-num">{{ number_format($brand->total_quantity) }}</td>
                                        <td class="stk-num text-success">{{ number_format($brand->total_revenue, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/l10n/ar.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-basic', { locale: 'ar', dateFormat: 'Y-m-d' });
    }

    // التحقق من ApexCharts
    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts not loaded!');
        return;
    }

    // البيانات المحولة من Controller
    var categoryData = @json($salesByCategoryChart ?? []);
    var brandData = @json($salesByBrandChart ?? []);

    console.log('Category Data:', categoryData);
    console.log('Brand Data:', brandData);

    // رسم الفئات
    var categoryChartEl = document.querySelector("#categoryChart");
    if (categoryChartEl && categoryData && categoryData.length > 0) {
        new ApexCharts(categoryChartEl, {
            series: categoryData.map(function(c) { return c.total_revenue || 0; }),
            chart: { type: 'pie', height: 350 },
            labels: categoryData.map(function(c) { return c.name || 'غير محدد'; }),
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                    }
                }
            },
            responsive: [{ breakpoint: 480, options: { chart: { width: 300 } } }]
        }).render();
    } else if (categoryChartEl) {
        categoryChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }

    // رسم العلامات التجارية
    var brandChartEl = document.querySelector("#brandChart");
    if (brandChartEl && brandData && brandData.length > 0) {
        new ApexCharts(brandChartEl, {
            series: [{
                name: 'الإيرادات',
                data: brandData.map(function(b) { return b.total_revenue || 0; })
            }],
            chart: { type: 'bar', height: 350, toolbar: { show: false } },
            xaxis: {
                categories: brandData.map(function(b) { return b.name || 'غير محدد'; })
            },
            colors: ['#7367f0'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    horizontal: true
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                    }
                }
            }
        }).render();
    } else if (brandChartEl) {
        brandChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }
});
</script>

<script>
    function formatNumber(n, decimals = 2) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    // نص الخلية بلا مسافات زائدة (خلايا اسم المنتج تحوي صورة مصغّرة + عنوان متداخلين)
    function cellText(cell) {
        return $(cell).text().trim().replace(/\s+/g, ' ');
    }

    $(document).ready(function() {
        // طباعة/تصدير: أفضل المنتجات مبيعاً
        $('#printTopProductsBtn').click(function() {
            printTopProductsReport();
        });
        $('#exportTopProductsBtn').click(function() {
            exportTopProductsReport();
        });

        // طباعة/تصدير: تفاصيل الفئات
        $('#printCategoryBtn').click(function() {
            printCategorySalesReport();
        });
        $('#exportCategoryBtn').click(function() {
            exportCategorySalesReport();
        });

        // طباعة/تصدير: تفاصيل العلامات التجارية
        $('#printBrandBtn').click(function() {
            printBrandSalesReport();
        });
        $('#exportBrandBtn').click(function() {
            exportBrandSalesReport();
        });
    });

    // =====================================
    // أفضل المنتجات مبيعاً
    // =====================================
    function printTopProductsReport() {
        printTable({
            tableSelector: '#topProductsTable tbody tr',
            title: 'تقرير أفضل المنتجات مبيعاً',
            infoCards: [
                { label: 'إجمالي الوحدات المباعة', value: '{{ number_format($totalProductsSold) }}' },
                { label: 'منتجات مختلفة', value: '{{ number_format($uniqueProductsSold) }}' },
                { label: 'متوسط الوحدات/طلب', value: '{{ number_format($avgUnitsPerOrder, 1) }}' }
            ],
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['#', 'المنتج', 'الكمية', 'الطلبات', 'متوسط السعر', 'الإيرادات'],
            columnWidths: ['5%', '35%', '15%', '15%', '15%', '15%'],
            windowWidth: 1200,
            windowHeight: 800
        });
    }

    function exportTopProductsReport() {
        var tableData = [];
        var rowNumber = 1;

        $('#topProductsTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 6) return; // تجاهل صف «لا توجد بيانات»

            tableData.push({
                values: [
                    rowNumber++,
                    cellText(cells[1]),
                    cellText(cells[2]),
                    cellText(cells[3]),
                    cellText(cells[4]) + ' ' + window.AppCurrency.symbol,
                    cellText(cells[5]) + ' ' + window.AppCurrency.symbol
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_أفضل_المنتجات_مبيعاً',
            mainTitle: 'تقرير أفضل المنتجات مبيعاً',
            subTitle: 'تقرير المنتجات - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الوحدات المباعة',
                    value: '{{ number_format($totalProductsSold) }}',
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'منتجات مختلفة',
                    value: '{{ number_format($uniqueProductsSold) }}',
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'متوسط الوحدات/طلب',
                    value: '{{ number_format($avgUnitsPerOrder, 1) }}',
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                }
            ],

            tableTitle: 'أفضل المنتجات مبيعاً',
            tableHeaders: ['#', 'المنتج', 'الكمية', 'الطلبات', 'متوسط السعر', 'الإيرادات'],
            columnWidths: [5, 35, 15, 15, 15, 15],

            tableData: tableData,

            buttonSelector: '#exportTopProductsBtn',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // تفاصيل الفئات (المبيعات حسب الفئة)
    // =====================================
    function printCategorySalesReport() {
        printTable({
            tableSelector: '#salesByCategoryTable tbody tr',
            title: 'تقرير المبيعات حسب الفئة',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['الفئة', 'الكمية', 'الطلبات', 'الإيرادات'],
            columnWidths: ['30%', '20%', '20%', '30%'],
            windowWidth: 1200,
            windowHeight: 800
        });
    }

    function exportCategorySalesReport() {
        var tableData = [];

        $('#salesByCategoryTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 4) return; // تجاهل صف «لا توجد بيانات»

            tableData.push({
                values: [
                    cellText(cells[0]),
                    cellText(cells[1]),
                    cellText(cells[2]),
                    formatNumber(cellText(cells[3])) + ' ' + window.AppCurrency.symbol
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' }, bold: true },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_المبيعات_حسب_الفئة',
            mainTitle: 'تقرير المبيعات حسب الفئة',
            subTitle: 'تقرير المنتجات - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            tableTitle: 'المبيعات حسب الفئة',
            tableHeaders: ['الفئة', 'الكمية', 'الطلبات', 'الإيرادات'],
            columnWidths: [30, 15, 15, 20],

            tableData: tableData,

            buttonSelector: '#exportCategoryBtn',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // تفاصيل العلامات التجارية (المبيعات حسب العلامة التجارية)
    // =====================================
    function printBrandSalesReport() {
        printTable({
            tableSelector: '#salesByBrandTable tbody tr',
            title: 'تقرير المبيعات حسب العلامة التجارية',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['العلامة التجارية', 'الكمية', 'الإيرادات'],
            columnWidths: ['40%', '25%', '35%'],
            windowWidth: 1200,
            windowHeight: 800
        });
    }

    function exportBrandSalesReport() {
        var tableData = [];

        $('#salesByBrandTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 3) return; // تجاهل صف «لا توجد بيانات»

            tableData.push({
                values: [
                    cellText(cells[0]),
                    cellText(cells[1]),
                    formatNumber(cellText(cells[2])) + ' ' + window.AppCurrency.symbol
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' }, bold: true },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_المبيعات_حسب_العلامة_التجارية',
            mainTitle: 'تقرير المبيعات حسب العلامة التجارية',
            subTitle: 'تقرير المنتجات - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            tableTitle: 'المبيعات حسب العلامة التجارية',
            tableHeaders: ['العلامة التجارية', 'الكمية', 'الإيرادات'],
            columnWidths: [35, 20, 25],

            tableData: tableData,

            buttonSelector: '#exportBrandBtn',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }
</script>
@endsection
