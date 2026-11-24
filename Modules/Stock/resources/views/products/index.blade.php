@extends('stock::master')

@section('title', 'المنتجات والخدمات')

@section('content')
    {{-- قائمة الإضافة فيها شروط وصلاحيات، فمكانها قسمٌ لا سلسلة نصّية --}}
    @section('page-actions')
        @can('stock.products.edit')
            @php
                /*
                    كان «خدمة جديدة» خلف
                    `in_array($businessType, ['services', 'both'])` — ونشاط
                    المنشأة هنا `products`، فلم يظهر الخيار قطّ ولا سبيل إلى
                    إنشاء خدمةٍ من هذه الشاشة إطلاقاً.

                    والقيد نفسه لا يصحّ: منشأةٌ تبيع سلعاً تبيع معها التركيب
                    والشحن والضمان — وكلّها خدمات. وتبويب «الخدمات» في هذه
                    الصفحة نفسها معروضٌ للجميع بلا شرط، فكان النظام يَعِد
                    بتصفيةِ ما لا يسمح بإنشائه.

                    نشاط المنشأة يبقى مؤثّراً: يقدّم ما يناسبه في الترتيب.
                */
                $businessType = optional($account_setting)->business_type;
                $servicesFirst = in_array($businessType, ['services'], true);
            @endphp

            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="feather icon-plus"></i> إضافة جديد
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    @php
                        $stockedEntry = [
                            'url' => route('products.create'),
                            'type' => \App\Enums\ProductType::PRODUCTS,
                        ];
                        $serviceEntry = [
                            'url' => route('products.create_services'),
                            'type' => \App\Enums\ProductType::SERVICES,
                        ];
                        $digitalEntry = [
                            'url' => route('products.create_services', ['type' => 'digital']),
                            'type' => \App\Enums\ProductType::DIGITAL,
                        ];

                        $entries = $servicesFirst
                            ? [$serviceEntry, $digitalEntry, $stockedEntry]
                            : [$stockedEntry, $serviceEntry, $digitalEntry];
                    @endphp

                    @foreach ($entries as $entry)
                        <a class="dropdown-item" href="{{ $entry['url'] }}">
                            <i class="feather icon-{{ $entry['type']->icon() }}"></i>
                            <span>{{ $entry['type']->label() }}</span>
                            <small class="d-block text-muted">{{ $entry['type']->description() }}</small>
                        </a>
                    @endforeach

                    @unless ($role === false)
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('products.compiled') }}">
                            <i class="feather icon-{{ \App\Enums\ProductType::COMPILED->icon() }}"></i>
                            <span>{{ \App\Enums\ProductType::COMPILED->label() }}</span>
                            <small class="d-block text-muted">{{ \App\Enums\ProductType::COMPILED->description() }}</small>
                        </a>
                    @endunless
                </div>
            </div>
        @endcan

        @can('stock.products.export')
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather icon-download"></i> تصدير
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                {{-- إكسل المخصَّص يصدّر ما تراه الشاشة الآن بعد الفلترة،
                     بترويسة وبطاقات إحصائية — لا تنزيلاً خاماً من الخادم --}}
                <a class="dropdown-item" href="javascript:void(0)" id="exportExcel">
                    <i class="feather icon-grid"></i> Excel (المعروض)
                </a>
                <a class="dropdown-item" href="javascript:void(0)" onclick="exportProducts('excel')">
                    <i class="feather icon-server"></i> Excel (كل النتائج)
                </a>
                <a class="dropdown-item" href="javascript:void(0)" onclick="exportProducts('pdf')">
                    <i class="feather icon-file-text"></i> PDF
                </a>
            </div>
        </div>
        @endcan

        <button type="button" class="btn btn-outline-secondary" id="printReport">
            <i class="feather icon-printer"></i> طباعة
        </button>

        @can('stock.products.import')
        <button type="button" class="btn btn-outline-secondary" onclick="importProducts()">
            <i class="feather icon-upload"></i> استيراد
        </button>
        @endcan
    @endsection

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'المنتجات والخدمات',
            'icon' => 'package',
            'sub' => 'كل المنتجات والخدمات وكمياتها وأسعارها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                            data-toggle="collapse" data-bs-target="#advancedSearch" data-target="#advancedSearch"
                            aria-expanded="false" aria-controls="advancedSearch">
                        <i class="feather icon-sliders"></i> بحث متقدّم
                    </button>
                </div>

                <form id="filterForm" data-search-url="{{ route('products.search') }}">
                    @csrf
                    <input type="hidden" name="tab_product_type" value="">

                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">كلمة مفتاحية</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       placeholder="الاسم أو الوصف…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select id="category" name="category" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($categories ?? [] as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                {{--
                                    كانت الخيارات: نشط / متوقف / غير نشط — وعمود
                                    `status` في القاعدة `enum('active','unactive')`
                                    لا ثالث لهما. فقيمتا «متوقف» و«غير نشط» لا
                                    تُخزَّنان أصلاً، والبحث بهما يعيد صفر نتائج
                                    دائماً بلا سببٍ ظاهر للمستخدم.

                                    الخيارات الآن هي الحالتان الحقيقيتان،
                                    مقروءتان من `ProductStatus` كي لا تنفصل
                                    الشاشة عن النموذج مرّةً أخرى.
                                --}}
                                <select id="status" name="status" class="form-control">
                                    <option value="">الكل</option>
                                    @foreach (\App\Enums\ProductStatus::toArray() as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearch">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="track_inventory">نوع التتبع</label>
                                    <select id="track_inventory" name="track_inventory" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach ([0 => 'الرقم التسلسلي', 1 => 'رقم الشحنة', 2 => 'تاريخ الانتهاء', 3 => 'رقم الشحنة وتاريخ الانتهاء', 4 => 'الكمية فقط'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="product_type">نوع المنتج</label>
                                    <select id="product_type" name="product_type" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="products">منتجات</option>
                                        <option value="services">خدمات</option>
                                        <option value="compiled">تجميعية</option>
                                        <option value="digital">رقمي</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="stock_status">حالة المخزون</label>
                                    <select id="stock_status" name="stock_status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="in_stock">متوفر</option>
                                        <option value="low_stock">منخفض</option>
                                        <option value="out_of_stock">نفد</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="available_pos">متاح بنقاط البيع</label>
                                    <select id="available_pos" name="available_pos" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1">نعم</option>
                                        <option value="0">لا</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="barcode">الباركود</label>
                                    <input type="text" id="barcode" name="barcode" class="form-control stk-num">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="product_code">كود المنتج</label>
                                    <input type="text" id="product_code" name="product_code" class="form-control stk-num">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="from_date">من تاريخ</label>
                                    <input type="date" id="from_date" name="from_date" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="to_date">إلى تاريخ</label>
                                    <input type="date" id="to_date" name="to_date" class="form-control">
                                </div>
                            </div>
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

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card stk-table-card">
            <div class="stk-table-head">
                <ul class="nav nav-tabs stk-tabs" id="productTypeTabs" role="tablist">
                    {{-- الأنواع من المصدر الواحد، فلا يُنسى نوعٌ جديد في تبويبٍ هنا --}}
                    @foreach (['' => 'الكل'] + \App\Enums\ProductType::toArray() as $type => $label)
                        <li class="nav-item" role="presentation">
                            <button type="button" role="tab"
                                    class="nav-link {{ $loop->first ? 'active' : '' }}"
                                    data-type="{{ $type }}">
                                {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                @include('stock::components.per-page')
            </div>

            <div id="productsContainer">
                @include('stock::products.partials.products_list', ['products' => $products])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        const PRODUCT_HEADERS = ['#', 'المنتج', 'الرقم التسلسلي', 'الباركود', 'التصنيف',
            'العلامة التجارية', 'النوع', 'الحالة', 'المخزون', 'سعر الشراء', 'سعر البيع', 'أُضيف'];

        /** الصفوف المعروضة الآن — تُقرأ من data-* لا من نصّ الخلايا المركَّبة */
        function collectProductRows() {
            const rows = [];
            let index = 1;

            document.querySelectorAll('#productsTable tbody tr[data-name]').forEach(function (tr) {
                const data = tr.dataset;

                rows.push({
                    values: [
                        index++,
                        data.name || '',
                        data.serial || '',
                        data.barcode || '',
                        data.category || '',
                        data.brand || '',
                        data.type || '',
                        data.status || '',
                        data.quantity === '' ? '' : (parseFloat(data.quantity) || 0),
                        parseFloat(data.purchase) || 0,
                        parseFloat(data.sale) || 0,
                        data.created || '',
                    ],
                    alignments: ['center', 'right', 'center', 'center', 'right', 'right',
                        'center', 'center', 'center', 'center', 'center', 'center'],
                });
            });

            return rows;
        }

        function productTotals(rows) {
            return {
                count: rows.length,
                quantity: rows.reduce((total, row) => total + (parseFloat(row.values[8]) || 0), 0),
                stockValue: rows.reduce((total, row) =>
                    total + ((parseFloat(row.values[8]) || 0) * (parseFloat(row.values[9]) || 0)), 0),
                services: rows.filter(row => row.values[6] === 'خدمة').length,
            };
        }

        function exportProductsExcel() {
            const rows = collectProductRows();

            if (! rows.length) {
                showMessage('لا صفوف معروضة للتصدير', 'warning');
                return;
            }

            const totals = productTotals(rows);

            exportToExcelProfessional({
                fileName: 'قائمة_المنتجات',
                mainTitle: 'قائمة المنتجات',
                subTitle: @json(config('app.name')),

                reportInfo: {
                    'تاريخ التقرير': new Date().toISOString().slice(0, 10),
                    'عدد الصفوف المعروضة': String(totals.count),
                },

                statisticsCards: [
                    { title: 'عدد المنتجات', value: String(totals.count), unit: ' صنف', color: 'primary', borderColor: 'primary' },
                    { title: 'إجمالي الكميات', value: formatNumber(totals.quantity), unit: '', color: 'success', borderColor: 'success' },
                    { title: 'قيمة المخزون بالتكلفة', value: formatNumber(totals.stockValue), unit: '', color: 'warning', borderColor: 'warning' },
                    { title: 'خدمات', value: String(totals.services), unit: ' خدمة', color: 'gray', borderColor: 'gray' },
                ],

                tableTitle: 'تفاصيل المنتجات',
                tableHeaders: PRODUCT_HEADERS,
                columnWidths: [6, 26, 16, 18, 16, 16, 10, 12, 12, 14, 14, 18],
                tableData: rows,

                summaryRows: [{
                    label: 'الإجمالي',
                    values: ['', '', '', '', '', '', '', formatNumber(totals.quantity), '', '', ''],
                    mergeFrom: 1,
                    mergeTo: 8,
                    color: 'primary',
                    alignment: 'right',
                }],

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير قائمة المنتجات بنجاح',
                errorMessage: 'تعذّر تصدير قائمة المنتجات',
            });
        }

        function printProducts() {
            const totals = productTotals(collectProductRows());

            printTable({
                tableSelector: '#productsTable tbody tr[data-name]',
                title: 'قائمة المنتجات',
                infoCards: [
                    { label: 'عدد المنتجات', value: totals.count + ' صنف' },
                    { label: 'إجمالي الكميات', value: formatNumber(totals.quantity), class: 'text-success' },
                    { label: 'قيمة المخزون بالتكلفة', value: formatNumber(totals.stockValue) },
                    { label: 'خدمات', value: totals.services + ' خدمة' },
                ],
                filterInfo: {
                    'تاريخ الطباعة': new Date().toISOString().slice(0, 10),
                },
                tableHeaders: ['المنتج', 'التصنيف والنوع', 'المخزون', 'الأسعار', 'أُضيف', 'إجراءات'],
                columnWidths: ['30%', '23%', '15%', '17%', '15%', '0%'],
                // عمود «إجراءات» أزرارٌ لا تُنقر على الورق — يُحذف من الطباعة
                skipColumns: [5],
                descriptionColumn: 0,
                windowWidth: 1300,
                windowHeight: 900,
            });
        }
    </script>

    <script>
        const productsRoutes = {
            search: @json(route('products.search')),
            export: @json(route('products.export.get')),
            import: @json(route('products.import')),
            base: @json(url('/stock/products')),
            deleteBase: @json(url('/stock/products/delete')),
        };
        const csrfToken = @json(csrf_token());

        function showLoading() {
            $('#productsContainer').css('opacity', '.55');
            if (!$('#loading-indicator').length) {
                $('#productsContainer').prepend(
                    '<div id="loading-indicator" class="text-center p-4">'
                    + '<div class="spinner-border text-primary" role="status"></div></div>'
                );
            }
        }

        function hideLoading() {
            $('#loading-indicator').remove();
            $('#productsContainer').css('opacity', '1');
        }

        function filterProducts(page = 1, perPage = null) {
            showLoading();

            const data = $('#filterForm').serialize()
                + '&page=' + page
                + '&per_page=' + (perPage || $('#perPage').val() || 10);

            $.ajax({
                url: productsRoutes.search,
                method: 'GET',
                data: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: false,
                success: function (response) {
                    $('#productsContainer').html(response.html);
                    if (response.total !== undefined) {
                        $('#products-count').text('إجمالي: ' + response.total + ' منتج');
                    }
                },
                error: function () {
                    $('#productsContainer').html(
                        '<div class="alert alert-danger m-2">تعذّر تحميل البيانات — حاول مرة أخرى</div>'
                    );
                },
                complete: hideLoading,
            });
        }

        /* الحذف: كانت القائمة تنادي `confirmDelete` ولا وجود لها في أي ملف،
           فلا يحدث شيء عند الضغط. عُرِّفت هنا. */
        function confirmDelete(id, name) {
            const submit = () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = productsRoutes.deleteBase + '/' + id;
                form.innerHTML =
                    '<input type="hidden" name="_token" value="' + csrfToken + '">'
                    + '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(form);
                form.submit();
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'حذف المنتج؟',
                    text: name,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(r => { if (r.isConfirmed) submit(); });
            } else if (confirm('حذف المنتج «' + name + '»؟')) {
                submit();
            }
        }

        function exportProducts(type) {
            const query = $('#filterForm').serialize();
            const url = productsRoutes.export + '?type=' + type + '&' + query;
            if (type === 'pdf') {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
        }

        function importProducts() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.xlsx,.xls,.csv';

            input.onchange = function (event) {
                const file = event.target.files[0];
                if (!file) return;

                const body = new FormData();
                body.append('file', file);
                body.append('_token', csrfToken);

                showLoading();
                fetch(productsRoutes.import, { method: 'POST', body: body })
                    .then(r => r.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('تعذّر الاستيراد: ' + (data.message || ''));
                        }
                    })
                    .catch(() => {
                        hideLoading();
                        alert('تعذّر الاستيراد');
                    });
            };

            input.click();
        }

        $(function () {
            if ($.fn.select2) {
                $('#filterForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $(document).on('click', '#exportExcel', exportProductsExcel);
            $(document).on('click', '#printReport', printProducts);

            // تبويب مُحدَّد مسبقاً من الرابط
            const preset = new URLSearchParams(window.location.search).get('tab_product_type');
            if (preset) {
                $('#productTypeTabs .nav-link').removeClass('active');
                $('#productTypeTabs .nav-link[data-type="' + preset + '"]').addClass('active');
                $('input[name="tab_product_type"]').val(preset);
            }

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                filterProducts();
            });

            /* بحث تلقائي بعد سكونٍ قصير — لكن ليس على كل ضغطة مفتاح */
            $('#filterForm').on('change input', 'input, select', function () {
                clearTimeout(window.productSearchTimer);
                window.productSearchTimer = setTimeout(() => filterProducts(), 500);
            });

            $('#resetSearch').on('click', function () {
                $('#filterForm')[0].reset();
                $('input[name="tab_product_type"]').val('');
                if ($.fn.select2) $('#filterForm .select2').val('').trigger('change');
                $('#productTypeTabs .nav-link').removeClass('active').first().addClass('active');
                filterProducts();
            });

            $('#perPage').on('change', function () {
                filterProducts(1, $(this).val());
            });

            $('#productTypeTabs .nav-link').on('click', function () {
                $('#productTypeTabs .nav-link').removeClass('active');
                $(this).addClass('active');
                $('input[name="tab_product_type"]').val($(this).data('type'));
                filterProducts();
            });

            // الترقيم: الروابط تحمل href حقيقياً، ونحن نلتقطها لنبقى في الصفحة
            $(document).on('click', '#productsContainer .pagination a', function (e) {
                e.preventDefault();
                const page = $(this).data('page')
                    || new URL(this.href, window.location.origin).searchParams.get('page');
                if (page) filterProducts(page);
            });

            // تغيير الحالة من القائمة إن وُجدت
            $(document).on('change', '.status-dropdown', function () {
                const id = $(this).data('product-id');
                fetch(productsRoutes.base + '/' + id + '/update-status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ status: this.value }),
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) filterProducts();
                        else alert('تعذّر تحديث الحالة: ' + (data.message || ''));
                    })
                    .catch(() => alert('تعذّر تحديث الحالة'));
            });
        });
    </script>
@endsection
