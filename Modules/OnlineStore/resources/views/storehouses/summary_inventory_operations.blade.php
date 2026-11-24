@extends('onlinestore::master')

@section('title')
    ملخص عمليات المخزون - {{ $storehouse->name }}
@stop

@section('css')
    <style>
        .incoming-col { background-color: rgba(40, 167, 69, 0.1); }
        .outgoing-col { background-color: rgba(220, 53, 69, 0.1); }
    </style>
@endsection

@section('content')
    <div class="content-body">

        @section('page-actions')
            {{-- الطباعة من المتصفّح (printTable) — رابط «تصدير PDF» القديم كان يصيّر قالباً لا وجود له فينتهي بخطأ 500 --}}
            <button type="button" id="printSummaryBtn" class="btn btn-outline-primary">
                <i class="feather icon-printer"></i> طباعة
            </button>
            <a href="{{ route('store_storehouses.show', $storehouse->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'ملخص عمليات المخزون',
            'icon' => 'bar-chart-2',
            'sub' => 'مخزن: ' . $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المخازن' => route('store_storehouses.index'),
                $storehouse->name => route('store_storehouses.show', $storehouse->id),
                'ملخص العمليات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> عمليات المخزون</h6>
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
                    <table class="table table-bordered mb-0" id="productsTable">
                        <thead>
                            <tr class="table-secondary">
                                <th rowspan="2" class="align-middle">اسم المنتج</th>
                                <th colspan="5" class="text-center incoming-col">الوارد</th>
                                <th colspan="5" class="text-center outgoing-col">المنصرف</th>
                                <th rowspan="2" class="align-middle text-center">إجمالي الحركة</th>
                            </tr>
                            <tr class="table-light">
                                <th class="incoming-col">فواتير الشراء</th>
                                <th class="incoming-col">مرتجع المبيعات</th>
                                <th class="incoming-col">التحويل</th>
                                <th class="incoming-col">يدوي</th>
                                <th class="incoming-col">الإجمالي</th>
                                <th class="outgoing-col">فواتير البيع</th>
                                <th class="outgoing-col">مرتجع مشتريات</th>
                                <th class="outgoing-col">التحويل</th>
                                <th class="outgoing-col">يدوي</th>
                                <th class="outgoing-col">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>{{ $product['name'] }}</td>
                                    <td class="incoming-col text-center">{{ $product['incoming_purchase'] }}</td>
                                    <td class="incoming-col text-center">{{ $product['sales_return_quantity'] }}</td>
                                    <td class="incoming-col text-center">{{ $product['incoming_transfer'] }}</td>
                                    <td class="incoming-col text-center">{{ $product['incoming_manual'] }}</td>
                                    <td class="incoming-col text-center text-success"><strong>{{ $product['incoming_total'] }}</strong></td>
                                    <td class="outgoing-col text-center">{{ $product['sold_quantity'] }}</td>
                                    <td class="outgoing-col text-center">{{ $product['outgoing_purchase_return'] != 0 ? '-'.$product['outgoing_purchase_return'] : 0 }}</td>
                                    <td class="outgoing-col text-center">{{ $product['outgoing_transfer'] != 0 ? '-'.$product['outgoing_transfer'] : 0 }}</td>
                                    <td class="outgoing-col text-center">{{ $product['outgoing_manual'] != 0 ? '-'.$product['outgoing_manual'] : 0 }}</td>
                                    <td class="outgoing-col text-center text-danger"><strong>{{ $product['outgoing_total'] != 0 ? '-'.$product['outgoing_total'] : 0 }}</strong></td>
                                    <td class="text-center {{ $product['movement_total'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        <strong>{{ $product['movement_total'] }}</strong>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12">
                                        <div class="stk-empty">
                                            <span class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></span>
                                            <h6 class="stk-empty__title">لا توجد عمليات مسجلة</h6>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
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
                url: "{{ route('store_storehouses.search-summary-inventory-operations', $storehouse->id) }}",
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
                        <td colspan="12" class="text-center py-4">
                            <i class="fa fa-inbox fa-3x text-muted mb-2 d-block"></i>
                            لا توجد نتائج
                        </td>
                    </tr>
                `);
                return;
            }

            products.forEach(function(p) {
                let movementClass = p.movement_total >= 0 ? 'text-success' : 'text-danger';
                tbody.append(`
                    <tr>
                        <td>${esc(p.name)}</td>
                        <td class="incoming-col text-center">${p.incoming_purchase}</td>
                        <td class="incoming-col text-center">${p.sales_return_quantity}</td>
                        <td class="incoming-col text-center">${p.incoming_transfer}</td>
                        <td class="incoming-col text-center">${p.incoming_manual}</td>
                        <td class="incoming-col text-center text-success"><strong>${p.incoming_total}</strong></td>
                        <td class="outgoing-col text-center">${p.sold_quantity}</td>
                        <td class="outgoing-col text-center">${p.outgoing_purchase_return != 0 ? '-'+p.outgoing_purchase_return : 0}</td>
                        <td class="outgoing-col text-center">${p.outgoing_transfer != 0 ? '-'+p.outgoing_transfer : 0}</td>
                        <td class="outgoing-col text-center">${p.outgoing_manual != 0 ? '-'+p.outgoing_manual : 0}</td>
                        <td class="outgoing-col text-center text-danger"><strong>${p.outgoing_total != 0 ? '-'+p.outgoing_total : 0}</strong></td>
                        <td class="text-center ${movementClass}"><strong>${p.movement_total}</strong></td>
                    </tr>
                `);
            });
        }

        // اسمُ الصنف نصٌّ من قاعدة البيانات — يُهرَّب قبل حقنه في الجدول
        function esc(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function printSummary() {
            printTable({
                tableSelector: '#productsTable tbody tr',
                title: @json('ملخص عمليات المخزون — '.$storehouse->name),
                infoCards: [
                    { label: 'المخزن', value: @json($storehouse->name) },
                    { label: 'عدد الأصناف', value: String($('#productsTable tbody tr').length) }
                ],
                filterInfo: {},
                tableHeaders: ['اسم المنتج', 'وارد: فواتير الشراء', 'وارد: مرتجع المبيعات', 'وارد: التحويل', 'وارد: يدوي', 'إجمالي الوارد',
                    'منصرف: فواتير البيع', 'منصرف: مرتجع مشتريات', 'منصرف: التحويل', 'منصرف: يدوي', 'إجمالي المنصرف', 'إجمالي الحركة'],
                columnWidths: ['19%', '7%', '7%', '7%', '7%', '8%', '7%', '7%', '7%', '7%', '8%', '9%'],
                windowWidth: 1300,
                windowHeight: 800
            });
        }

        $('#printSummaryBtn').on('click', printSummary);

        @if (request('print'))
            setTimeout(printSummary, 400);
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
