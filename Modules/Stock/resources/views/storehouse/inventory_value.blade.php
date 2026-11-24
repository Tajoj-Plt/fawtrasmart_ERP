@extends('stock::master')

@section('title', 'قيمة المخزون — ' . $storehouse->name)

@section('content')
    @php
        // الإجماليات تُحسب مرّةً هنا لا ثلاث مرّات في ذيل الجدول
        $totalSale = $products->sum(fn ($row) => optional($row->product)->sale_price * $row->quantity);
        $totalPurchase = $products->sum(fn ($row) => optional($row->product)->purchase_price * $row->quantity);
        $totalProfit = $totalSale - $totalPurchase;

        $hasFilters = request()->hasAny([
            'keywords', 'category', 'quantity_filter',
            'min_sale_price', 'max_sale_price',
            'min_purchase_price', 'max_purchase_price',
            'min_profit', 'max_profit',
        ]);
    @endphp

    <div class="content-body">

        @section('page-actions')
            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> طباعة
            </button>

            <button type="button" class="btn btn-outline-secondary" id="exportExcel">
                <i class="feather icon-download"></i> تصدير Excel
            </button>

            {{-- حُذف تصدير الخادم المكرَّر: زرّ «تصدير Excel» أعلاه يصدّر المعروض
                 بترويسة وبطاقات إحصائية، وبقي PDF وحده --}}
            @permission('stock.reports.export')
            <a href="{{ route('storehouse.inventory_value.export', [$storehouse->id, 'pdf']) }}"
               class="btn btn-outline-secondary">
                <i class="feather icon-file-text"></i> PDF
            </a>
            @endpermission

            {{-- الأرصدة الافتتاحية تُحدَّد أصنافاً وكمياتٍ في شاشتها، لا بضغطةٍ عمياء --}}
            @permission('stock.permits.create_opening')
            <a href="{{ route('store_permits_management.opening_balance', ['warehouse' => $storehouse->id]) }}"
               class="btn btn-primary">
                <i class="feather icon-star"></i> رصيد افتتاحي
            </a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'قيمة المخزون التقديرية',
            'icon' => 'dollar-sign',
            'sub' => $storehouse->name . ' — بأسعار البيع والشراء الحالية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => route('storehouse.index'),
                $storehouse->name => route('storehouse.show', $storehouse->id),
                'قيمة المخزون' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="bulkOpeningBalanceForm" method="POST" class="d-none"
              action="{{ route('storehouse.inventory_value.generate_bulk_opening_balance', $storehouse->id) }}">
            @csrf
        </form>

        {{-- الإجماليات فوق الجدول: هي الرقم الذي يُفتح التقرير لأجله --}}
        <div class="stk-metrics" id="totals">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num" data-total="count">{{ number_format($products->count()) }}</span>
                <span class="stk-metric__label">عدد الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__value stk-num" data-total="purchase">{{ number_format($totalPurchase, 2) }}</span>
                <span class="stk-metric__label">إجمالي التكلفة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__value stk-num" data-total="sale">{{ number_format($totalSale, 2) }}</span>
                <span class="stk-metric__label">إجمالي البيع المتوقَّع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__value stk-num" data-total="profit">{{ number_format($totalProfit, 2) }}</span>
                <span class="stk-metric__label">الربح المتوقَّع ({{ App\Support\CurrencySettings::code() }})</span>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="stk-count">فلاتر مفعّلة</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                                data-bs-toggle="collapse" data-target="#advancedSearchForm"
                                data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                <form id="searchForm" method="GET">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="الاسم أو الكود…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select id="category" name="category" class="form-control select2">
                                    <option value="">جميع التصنيفات</option>
                                    @foreach ($categories ?? [] as $category)
                                        <option value="{{ $category->id }}"
                                            {{ request('category') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="quantity_filter">الكمية</label>
                                <select id="quantity_filter" name="quantity_filter" class="form-control">
                                    <option value="">جميع الكميات</option>
                                    @foreach (['low_stock' => 'كمية منخفضة', 'out_of_stock' => 'نفدت الكمية', 'high_stock' => 'كمية عالية'] as $value => $label)
                                        <option value="{{ $value }}" {{ request('quantity_filter') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            @php
                                // كل نطاق: [الاسم، التسمية]
                                $ranges = [
                                    ['min_sale_price', 'max_sale_price', 'سعر البيع'],
                                    ['min_purchase_price', 'max_purchase_price', 'سعر الشراء'],
                                    ['min_profit', 'max_profit', 'الربح المتوقَّع'],
                                ];
                            @endphp

                            @foreach ($ranges as [$min, $max, $label])
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label>{{ $label }}</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" name="{{ $min }}"
                                                   class="form-control stk-num" placeholder="من"
                                                   value="{{ request($min) }}">
                                            <input type="number" step="0.01" name="{{ $max }}"
                                                   class="form-control stk-num" placeholder="إلى"
                                                   value="{{ request($max) }}">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card stk-table-card">
            <div class="stk-print-head">
                <h3>قيمة المخزون — {{ $storehouse->name }}</h3>
                <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> الأصناف
                    <span class="stk-count" id="rows-count">{{ $products->count() }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:12%">الكود</th>
                            <th>الصنف</th>
                            <th class="text-center" style="width:9%">الكمية</th>
                            <th class="text-center" style="width:11%">سعر البيع</th>
                            <th class="text-center" style="width:11%">متوسط الشراء</th>
                            <th class="text-center" style="width:12%">إجمالي البيع</th>
                            <th class="text-center" style="width:12%">إجمالي الشراء</th>
                            <th class="text-center" style="width:12%">الربح المتوقَّع</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-table-body">
                        @forelse ($products as $row)
                            @php
                                $product = $row->product;
                                $rowPurchase = optional($product)->purchase_price * $row->quantity;
                                $rowSale = optional($product)->sale_price * $row->quantity;
                                $rowProfit = $rowSale - $rowPurchase;
                            @endphp

                            <tr>
                                <td class="text-center stk-num">{{ optional($product)->barcode ?: '—' }}</td>
                                <td><span class="stk-media__title">{{ optional($product)->name ?? 'منتج محذوف' }}</span></td>
                                <td class="text-center stk-num">{{ number_format($row->quantity) }}</td>
                                <td class="text-center stk-num">{{ number_format(optional($product)->sale_price, 2) }}</td>
                                <td class="text-center stk-num">{{ number_format(optional($product)->purchase_price, 2) }}</td>
                                <td class="text-center stk-num">{{ number_format($rowSale, 2) }}</td>
                                <td class="text-center stk-num">{{ number_format($rowPurchase, 2) }}</td>
                                <td class="text-center stk-num {{ $rowProfit < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($rowProfit, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="stk-empty">
                                        <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                                        <h5 class="stk-empty__title">لا أصناف</h5>
                                        <p class="stk-empty__hint">لا شيء يطابق معايير البحث في هذا المستودع</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot id="inventory-table-footer">
                        <tr class="stk-total-row">
                            <td colspan="5" class="text-center">الإجمالي</td>
                            <td class="text-center stk-num" data-total="sale">{{ number_format($totalSale, 2) }}</td>
                            <td class="text-center stk-num" data-total="purchase">{{ number_format($totalPurchase, 2) }}</td>
                            <td class="text-center stk-num" data-total="profit">{{ number_format($totalProfit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#reportTable',
                fileName: 'قيمة_المخزون',
                title: 'قيمة المخزون',
                subTitle: @json($storehouse->name),
                numericColumns: [0, 2, 3, 4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            const money = value => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            function setTotals(count, sale, purchase, profit) {
                // الإجماليات معروضة في موضعين — بطاقة المؤشّرات وذيل الجدول
                $('[data-total="count"]').text(Number(count).toLocaleString('en-US'));
                $('[data-total="sale"]').text(money(sale));
                $('[data-total="purchase"]').text(money(purchase));
                $('[data-total="profit"]').text(money(profit));
                $('#rows-count').text(count);
            }

            function render(rows) {
                const tbody = $('#inventory-table-body').empty();

                if (! rows.length) {
                    tbody.append(
                        '<tr><td colspan="8"><div class="stk-empty">'
                        + '<div class="stk-empty__icon"><i class="feather icon-package"></i></div>'
                        + '<h5 class="stk-empty__title">لا نتائج</h5>'
                        + '<p class="stk-empty__hint">لا شيء يطابق معايير البحث</p>'
                        + '</div></td></tr>'
                    );
                    setTotals(0, 0, 0, 0);
                    return;
                }

                let sale = 0, purchase = 0;

                rows.forEach(function (row) {
                    const rowPurchase = row.product.purchase_price * row.quantity;
                    const rowSale = row.product.sale_price * row.quantity;
                    const rowProfit = rowSale - rowPurchase;

                    sale += rowSale;
                    purchase += rowPurchase;

                    tbody.append(
                        '<tr>'
                        + '<td class="text-center stk-num">' + escapeHtml(row.product.barcode || '—') + '</td>'
                        + '<td><span class="stk-media__title">' + escapeHtml(row.product.name) + '</span></td>'
                        + '<td class="text-center stk-num">' + Number(row.quantity).toLocaleString('en-US') + '</td>'
                        + '<td class="text-center stk-num">' + money(row.product.sale_price) + '</td>'
                        + '<td class="text-center stk-num">' + money(row.product.purchase_price) + '</td>'
                        + '<td class="text-center stk-num">' + money(rowSale) + '</td>'
                        + '<td class="text-center stk-num">' + money(rowPurchase) + '</td>'
                        + '<td class="text-center stk-num ' + (rowProfit < 0 ? 'text-danger' : 'text-success') + '">'
                        + money(rowProfit) + '</td>'
                        + '</tr>'
                    );
                });

                setTotals(rows.length, sale, purchase, sale - purchase);
            }

            function search() {
                $('#loading-indicator').removeClass('d-none');

                $.get('{{ route('storehouse.inventory_value.search', $storehouse->id) }}', $('#searchForm').serialize())
                    .done(function (response) {
                        if (response.success) render(response.data);
                    })
                    .fail(function () {
                        Swal.fire
                            ? Swal.fire({ icon: 'error', title: 'تعذّر البحث' })
                            : alert('تعذّر تحميل البيانات');
                    })
                    .always(() => $('#loading-indicator').addClass('d-none'));
            }

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                search();
            });

            /* بحث تلقائي بعد سكونٍ قصير */
            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.inventoryValueTimer);
                window.inventoryValueTimer = setTimeout(search, 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                search();
            });
        });
    </script>
@endsection
