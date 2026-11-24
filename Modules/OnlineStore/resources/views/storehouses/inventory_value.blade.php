@extends('onlinestore::master')

@section('title')
    قيمة المخزون التقديرية - {{ $storehouse->name }}
@stop

@section('content')
    <div class="content-body">

        @section('page-actions')
            {{-- الطباعة من المتصفّح (printTable) — رابط PDF القديم كان يصيّر قالباً لا وجود له فينتهي بخطأ 500 --}}
            <a href="#" id="printValueBtn" class="btn btn-outline-primary">
                <i class="feather icon-printer"></i> طباعة
            </a>
            <a href="{{ route('store_storehouses.show', $storehouse->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'قيمة المخزون التقديرية',
            'icon' => 'dollar-sign',
            'sub' => 'مخزن: ' . $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المخازن' => route('store_storehouses.index'),
                $storehouse->name => route('store_storehouses.show', $storehouse->id),
                'قيمة المخزون' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">إجمالي سعر الشراء</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalPurchaseValue) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">إجمالي سعر البيع المتوقع</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalSaleValue) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">الربح المتوقع</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalExpectedProfit) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" class="form-control" name="search" placeholder="بحث بالاسم أو الباركود…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category_id">التصنيف</label>
                                <select class="form-control select2" id="category_id" name="category_id">
                                    <option value="">كل التصنيفات</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" class="btn btn-outline-secondary" id="resetSearch">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> منتجات المخزن</h6>
            </div>

            <div class="card-body position-relative">
                <div id="loading-indicator" class="position-absolute w-100 h-100 d-none" style="top: 0; left: 0; z-index: 10; background: rgba(255,255,255,.85);">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="productsTable">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>الاسم</th>
                                <th>التصنيف</th>
                                <th class="text-center">الكمية</th>
                                @php $valueCurrency = \App\Support\CurrencySettings::code(); @endphp
                                <th class="text-center">سعر البيع الحالي ({{ $valueCurrency }})</th>
                                <th class="text-center">سعر الشراء ({{ $valueCurrency }})</th>
                                <th class="text-center">إجمالي سعر البيع ({{ $valueCurrency }})</th>
                                <th class="text-center">إجمالي سعر الشراء ({{ $valueCurrency }})</th>
                                <th class="text-center">الربح المتوقع ({{ $valueCurrency }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td class="stk-num">{{ $product['barcode'] ?? '-' }}</td>
                                    <td>{{ $product['name'] ?? '-' }}</td>
                                    <td>{{ $product['category'] }}</td>
                                    <td class="text-center stk-num">{{ \App\Support\CurrencySettings::quantity($product['quantity']) }}</td>
                                    <td class="text-center stk-num">{{ number_format($product['sale_price'], 2) }}</td>
                                    <td class="text-center stk-num">{{ number_format($product['purchase_price'], 2) }}</td>
                                    <td class="text-center stk-num">{{ number_format($product['sale_total'], 2) }}</td>
                                    <td class="text-center stk-num">{{ number_format($product['purchase_total'], 2) }}</td>
                                    <td class="text-center stk-num {{ $product['expected_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($product['expected_profit'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="stk-empty">
                                            <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                            <h6 class="stk-empty__title">لا توجد منتجات في هذا المخزن</h6>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="6">الإجمالي</th>
                                <th class="text-center">{{ number_format($totalSaleValue, 2) }}</th>
                                <th class="text-center">{{ number_format($totalPurchaseValue, 2) }}</th>
                                <th class="text-center text-success">{{ number_format($totalExpectedProfit, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="{{ asset('js/print-table.js') }}"></script>
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
        }

        // البحث
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            performSearch();
        });

        $('#resetSearch').on('click', function() {
            $('#searchForm')[0].reset();
            $('.select2').val('').trigger('change');
            performSearch();
        });

        let searchTimeout;
        $('#searchForm input, #searchForm select').on('change input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performSearch();
            }, 500);
        });

        function performSearch() {
            showLoading();
            $.ajax({
                url: "{{ route('store_storehouses.search-inventory-value', $storehouse->id) }}",
                type: 'GET',
                data: $('#searchForm').serialize(),
                success: function(response) {
                    if (response.success) {
                        updateTable(response.products);
                    }
                    hideLoading();
                },
                error: function() {
                    hideLoading();
                }
            });
        }

        function updateTable(products) {
            let tbody = $('#productsTable tbody');
            tbody.empty();

            if (products.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fa fa-inbox fa-3x text-muted mb-2 d-block"></i>
                            لا توجد نتائج
                        </td>
                    </tr>
                `);
                return;
            }

            let totalPurchase = 0, totalSale = 0;

            products.forEach(function(p) {
                totalPurchase += p.purchase_total;
                totalSale += p.sale_total;
                let profitClass = p.expected_profit >= 0 ? 'text-success' : 'text-danger';
                tbody.append(`
                    <tr>
                        <td>${esc(p.barcode || '-')}</td>
                        <td>${esc(p.name)}</td>
                        <td>${esc(p.category)}</td>
                        <td>${p.quantity}</td>
                        <td>${parseFloat(p.sale_price).toFixed(2)}</td>
                        <td>${parseFloat(p.purchase_price).toFixed(2)}</td>
                        <td>${parseFloat(p.sale_total).toFixed(2)}</td>
                        <td>${parseFloat(p.purchase_total).toFixed(2)}</td>
                        <td class="${profitClass}">${parseFloat(p.expected_profit).toFixed(2)}</td>
                    </tr>
                `);
            });

            // تحديث الإجمالي
            let totalProfit = totalSale - totalPurchase;
            $('#productsTable tfoot th:eq(1)').text(totalSale.toFixed(2));
            $('#productsTable tfoot th:eq(2)').text(totalPurchase.toFixed(2));
            $('#productsTable tfoot th:eq(3)').text(totalProfit.toFixed(2));
        }

        // نصوصٌ من قاعدة البيانات — تُهرَّب قبل حقنها في الجدول
        function esc(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function printValue() {
            const currency = @json(\App\Support\CurrencySettings::code());

            printTable({
                tableSelector: '#productsTable tbody tr',
                tfootSelector: '#productsTable tfoot tr',
                title: @json('قيمة المخزون التقديرية — '.$storehouse->name),
                infoCards: [
                    { label: 'المخزن', value: @json($storehouse->name) },
                    { label: 'إجمالي سعر الشراء', value: $('#productsTable tfoot th:eq(2)').text().trim() + ' ' + currency },
                    { label: 'إجمالي سعر البيع المتوقع', value: $('#productsTable tfoot th:eq(1)').text().trim() + ' ' + currency },
                    { label: 'الربح المتوقع', value: $('#productsTable tfoot th:eq(3)').text().trim() + ' ' + currency, class: 'text-success' }
                ],
                filterInfo: {},
                tableHeaders: ['الكود', 'الاسم', 'التصنيف', 'الكمية', 'سعر البيع', 'سعر الشراء', 'إجمالي البيع', 'إجمالي الشراء', 'الربح المتوقع'],
                columnWidths: ['10%', '22%', '12%', '8%', '9%', '9%', '10%', '10%', '10%'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        $('#printValueBtn').on('click', function (e) { e.preventDefault(); printValue(); });

        @if (request('print'))
            setTimeout(printValue, 400);
        @endif

        function showLoading() {
            $('#loading-indicator').removeClass('d-none');
        }

        function hideLoading() {
            $('#loading-indicator').addClass('d-none');
        }
    });
</script>
@endsection
