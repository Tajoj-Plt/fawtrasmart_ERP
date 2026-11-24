@extends('purchases::master')
@section('title')
    عروض أسعار المشتريات
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
    {{-- بطاقات الملخّص والجدول: نفس ورقة أنماط شاشة «سجلّ النشاطات» --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <style>
        .form-control {
            margin-bottom: 10px;
        }

        #loading-indicator {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 0.375rem;
        }

        .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .content-header-title {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            .card {
                margin: 10px;
                padding: 10px;
            }

            .table {
                font-size: 0.8rem;
                width: 100%;
                overflow-x: auto;
            }

            .table th,
            .table td {
                white-space: nowrap;
            }

            .form-check {
                margin-bottom: 10px;
            }

            .form-control {
                width: 100%;
            }

            .dropdown-menu {
                min-width: 200px;
            }
        }

        @media (max-width: 480px) {
            .table th,
            .table td {
                font-size: 0.7rem;
            }
        }

        /* تخصيص الأزرار */
        .custom-btn {
            min-width: 120px;
            margin: 5px;
            justify-content: center;
        }

        .custom-dropdown {
            min-width: 200px;
        }

        .tab-content {
            position: relative;
            z-index: 1;
        }

        .sidebar {
            position: fixed;
            z-index: 100;
            top: 0;
            right: 0;
            height: 100vh;
            width: 250px;
            background: #f8f9fa;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .invoice-wrapper {
            contain: content;
            position: relative;
            z-index: 1;
            width: 100%;
            overflow: visible;
            padding: 20px 0;
        }

        .sidebar {
            position: fixed !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            transform: none !important;
            margin: 0 !important;
        }

        .main-content {
            transition: none !important;
            transform: none !important;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }

        .pdf-wrapper {
            width: 100%;
            overflow-x: auto;
            background: white;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        [dir="rtl"] .pdf-wrapper {
            direction: rtl;
        }

        .tab-content>.active {
            overflow: visible !important;
        }

        .custom-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .custom-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0056b3;
        }

        .custom-btn i {
            margin-right: 5px;
        }

        .toast-container {
            z-index: 9999;
        }

        /* تحسينات الاستجابة للشاشات الصغيرة */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
        }

        @media (max-width: 992px) {
            .invoice-header .row {
                flex-direction: column;
                gap: 20px;
            }

            .invoice-header .col-lg-8,
            .invoice-header .col-lg-4 {
                width: 100%;
                flex: none;
            }

            .invoice-header .d-flex.flex-column.align-items-end {
                align-items: flex-start !important;
            }
        }

        @media (max-width: 768px) {
            .content-header .col-md-9 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .invoice-header h3 {
                font-size: 1.5rem;
            }

            .d-flex.align-items-center.gap-2.flex-wrap {
                flex-direction: column;
                align-items: stretch !important;
            }

            .vr {
                display: none;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
                justify-content: center;
            }

            .card-body {
                padding: 1rem;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-control {
                font-size: 0.9rem;
            }
        }

        /* تحسينات إضافية للشاشات الصغيرة جداً */
        @media (max-width: 400px) {
            .nav-tabs .nav-link {
                font-size: 0.7rem;
                padding: 0.3rem 0.5rem;
            }

            .btn {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
        }

        /* تحسينات للشاشات اللمسية */
        @media (hover: none) and (pointer: coarse) {
            .btn, .nav-link, .dropdown-item {
                padding: 0.75rem 1rem;
                font-size: 1rem;
            }

            .custom-btn {
                min-width: auto;
                padding: 0.75rem 1rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.sections.purchase_quotation'),
            'icon' => 'fa-tags',
            'crumbs' => [['label' => __('purchase_cycle.sections.purchase_quotation')]],
            'action' =>
                '<div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none align-self-center" role="status"></div>' .
                '<a href="' . route('pricesPurchase.create') . '" class="btn btn-primary">' .
                '<i class="fas fa-plus-circle me-1"></i>' . e(__('purchase_cycle.actions.create')) . '</a>',
        ])

        <div class="content-body">
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            {{-- الأعداد تأتي من المتحكّم حين يُربَط هذا العرض بمسار؛ وحتى ذلك
                 الحين تُعرض أصفاراً بدل أن تنكسر الشاشة --}}
            @include('purchases::partials.stats-cards', [
                'cards' => [
                    ['tone' => 'primary', 'icon' => 'fa-tags', 'label' => __('purchase_cycle.sections.purchase_quotation'), 'value' => $stats['total'] ?? 0, 'is_total' => true],
                    ['tone' => 'warning', 'icon' => 'fa-clock', 'label' => __('purchase_cycle.statuses.Under Review'), 'value' => $stats['review'] ?? 0],
                    ['tone' => 'success', 'icon' => 'fa-check-circle', 'label' => __('purchase_cycle.statuses.approval'), 'value' => $stats['approved'] ?? 0],
                    ['tone' => 'danger', 'icon' => 'fa-times-circle', 'label' => __('purchase_cycle.statuses.disagree'), 'value' => $stats['rejected'] ?? 0],
                ],
            ])

            {{-- البحث والتصفية — كما هو --}}
            <!-- نموذج البحث -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center p-2">
                    <div class="d-flex gap-2">
                        <span class="hide-button-text">بحث وتصفية</span>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <!-- إخفاء / إظهار -->
                        <button class="btn btn-outline-primary btn-sm waves-effect waves-light"
                            onclick="toggleSearchFields(this)">
                            <i class="fa fa-times me-1"></i>
                            <span class="hide-button-text">إخفاء</span>
                        </button>

                        <!-- بحث متقدم -->
                        <button class="btn btn-outline-primary btn-sm waves-effect waves-light" data-bs-toggle="collapse"
                            data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                            <i class="fa fa-filter me-1"></i>
                            <span class="button-text">متقدم</span>
                        </button>

                        <!-- إعادة تعيين -->
                        <button type="button" id="resetSearch"
                            class="btn btn-outline-primary btn-sm waves-effect waves-light">
                            <i class="fa fa-refresh me-1"></i>
                            إعادة تعيين
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="search-form">
                        @csrf
                        <div class="row g-3">

                        {{-- «تابع لـ» — الشجرةُ نفسُها في التقارير والنماذج؛ الحقلُ المخفي
                             `branch` يقرؤه `scopeToBranchNode()`، وصنفُ `search-input`
                             يربطه بمستمع إعادة التحميل --}}
                        @include('reports.partials.affiliation-tree', [
                            'treeNodes' => \Modules\Purchases\Support\HeaderPickers::treeNodes(),
                            'name' => 'branch',
                            'showExact' => false,
                            'label' => __('branches.node_filter.column'),
                            'treeInputClass' => 'search-input',
                        ])
                            <!-- الحقول الأساسية -->
                            <div class="col-md-4">
                                <label for="employee_id">موظف</label>
                                <select name="employee_id" class="form-control select2 search-input" id="employee_id">
                                    <option value="">اختر الموظف</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="code">الكود</label>
                                <input type="text" class="form-control search-input" name="code" id="code"
                                    placeholder="ادخل الكود">
                            </div>

                            <div class="col-md-4">
                                <label for="status">الحالة</label>
                                <select class="form-control search-input" name="status" id="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="approval">موافقة</option>
                                    <option value="disagree">مرفوض</option>
                                    <option value="Under Review">تحت المعالجة</option>
                                    <option value="Convert to Quotation">عرض سعر</option>
                                </select>
                            </div>
                        </div>

                        <!-- البحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="row g-3 mt-2">
                                <div class="col-md-3">
                                    <label for="follow_status">حالة المتابعة</label>
                                    <select name="follow_status" class="form-control search-input" id="follow_status">
                                        <option value="">جميع حالات المتابعة</option>
                                        <option value="approval">موافقة</option>
                                        <option value="disagree">مرفوض</option>
                                        <option value="Under Review">تحت المعالجة</option>
                                        <option value="Convert to Quotation">عرض سعر</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="order_date_from">تاريخ الطلب (من)</label>
                                    <input type="date" class="form-control search-input" name="order_date_from"
                                        id="order_date_from">
                                </div>

                                <div class="col-md-3">
                                    <label for="order_date_to">تاريخ الطلب (إلى)</label>
                                    <input type="date" class="form-control search-input" name="order_date_to" id="order_date_to">
                                </div>

                                <div class="col-md-3">
                                    <label for="due_date_from">تاريخ الاستحقاق (من)</label>
                                    <input type="date" class="form-control search-input" name="due_date_from" id="due_date_from">
                                </div>

                                <div class="col-md-3">
                                    <label for="due_date_to">تاريخ الاستحقاق (إلى)</label>
                                    <input type="date" class="form-control search-input" name="due_date_to" id="due_date_to">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="title">مسمى</label>
                                    <input type="text" class="form-control search-input" name="title" id="title">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions mt-2">
                            <button type="submit" class="btn btn-primary" id="search-btn">
                                <i class="fa fa-search me-1"></i>بحث
                            </button>
                            <button type="button" class="btn btn-outline-warning" id="clear-filters">
                                <i class="fa fa-times me-1"></i>إلغاء
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- النتائج --}}
            <div class="card table-card-modern" id="reportCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-list"></i> {{ __('purchase_cycle.sections.purchase_quotation') }}
                    </h4>

                    <div class="po-toolbar">
                        <span class="po-hint results-info">
                            {{ __('purchase_cycle.search.results_count', ['count' => \App\Support\LanguageSettings::formatNumber($purchaseOrders->total(), 0)]) }}
                        </span>

                        <label for="perPage" class="po-hint mb-0">{{ __('purchase_cycle.invoice.per_page') }}</label>
                        <select id="perPage" class="form-control form-control-sm" style="width: auto;">
                            @foreach ([10, 25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected(request('per_page', 10) == $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="card-content">
                    <div id="table-container">
                        @include('purchases::purchases.prices-purchase.partials.table', [
                            'purchaseOrders' => $purchaseOrders,
                        ])
                    </div>

                    <div class="card-footer p-0" id="pagination-top">
                        @include('purchases::purchases.prices-purchase.partials.pagination', [
                            'purchaseOrders' => $purchaseOrders,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script>
        $(document).ready(function() {
            let searchTimeout;

            // Handle page size selection
            $('#perPage').on('change', function() {
                performSearch(1); // Reset to first page when changing page size
            });

            // البحث التلقائي عند الكتابة أو التغيير
            $('.search-input').on('input change', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    performSearch();
                }, 500); // انتظار نصف ثانية بعد التوقف عن الكتابة
            });

            // البحث عند الضغط على زر البحث
            $('#search-form').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            // مسح الفلاتر
            $('#clear-filters').on('click', function() {
                $('#search-form')[0].reset();
                performSearch();
            });

            // التعامل مع روابط الـ pagination
            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                let url = $(this).attr('href');
                let page = new URL(url).searchParams.get('page');
                performSearch(page);
            });

            // دالة البحث الرئيسية
            function performSearch(page = 1) {
                showLoading();

                let formData = $('#search-form').serialize();
                formData += '&page=' + page;
                formData += '&per_page=' + $('#perPage').val(); // Add page size to the request
                formData += '&is_ajax=1'; // إضافة معلمة واضحة للطلب AJAX

                $.ajax({
                    url: '{{ route('pricesPurchase.index') }}',
                    method: 'GET',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: false,
                    success: function(response) {
                        if (response.success) {
                            $('#table-container').html(response.data);
                            $('#pagination-top').html(response.pagination);
                            updatePaginationInfo(response);
                            initializeEvents();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في تحميل البيانات:', error);
                        console.error('حالة الخطأ:', status);
                        console.error('استجابة الخادم:', xhr.responseText);
                        $('#table-container').html(
                            '<div class="alert alert-danger text-center">' +
                            '<p class="mb-0">حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.</p>' +
                            '</div>'
                        );
                    },
                    complete: function() {
                        hideLoading();
                    }
                });
            }

            function showLoading() {
                $('#table-container').css('opacity', '0.6');
                if ($('#loading-indicator').length === 0) {
                    $('#table-container').prepend(`
                <div id="loading-indicator" class="text-center p-3">
                    <div class="spinner-border text-primary" role="status">
                    </div>
                </div>
            `);
                }
                $('#loading-spinner').removeClass('d-none');
                $('#search-btn').prop('disabled', true);
            }

            function hideLoading() {
                $('#loading-indicator').remove();
                $('#table-container').css('opacity', '1');
                $('#loading-spinner').addClass('d-none');
                $('#search-btn').prop('disabled', false);
            }

            function updatePaginationInfo(response) {
                $('.pagination-info').text(`صفحة ${response.current_page} من ${response.last_page}`);
                if (response.total > 0) {
                    $('.results-info').text(`${response.from}-${response.to} من ${response.total}`);
                } else {
                    $('.results-info').text('لا توجد نتائج');
                }
            }

            function initializeEvents() {
                // أحداث الحذف
                $('.delete-order').off('click').on('click', function(e) {
                    e.preventDefault();
                    const orderId = $(this).data('order-id');
                    const orderCode = $(this).data('order-code');

                    if (confirm('هل أنت متأكد من حذف عرض السعر رقم "' + orderCode + '"؟')) {
                        deleteOrder(orderId);
                    }
                });

                // تحديد الكل
                $('#selectAll').off('change').on('change', function() {
                    $('.order-checkbox').prop('checked', $(this).prop('checked'));
                });

                $('.order-checkbox').off('change').on('change', function() {
                    let totalCheckboxes = $('.order-checkbox').length;
                    let checkedCheckboxes = $('.order-checkbox:checked').length;
                    $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
                    $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
                });
                
                // Pagination Click Events
                $(document).on('click', '.paginate_button:not(.disabled) a', function(e) {
                    e.preventDefault();
                    let url = $(this).attr('href');
                    if (url) {
                        let page = new URL(url).searchParams.get('page') || 1;
                        performSearch(page);
                    }
                });
            }
            
            function deleteOrder(orderId) {
                const row = $(`.delete-order[data-order-id="${orderId}"]`).closest('tr');
                row.css('opacity', '0.5');

                $.ajax({
                    url: `/purchases/pricesPurchase/${orderId}`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        alert('تم حذف عرض السعر بنجاح');
                        performSearch();
                    },
                    error: function() {
                        row.css('opacity', '1');
                        alert('حدث خطأ أثناء حذف عرض السعر');
                    }
                });
            }

            // دوال التحكم في البحث المتقدم
            function toggleSearchText(button) {
                const buttonText = button.querySelector('.button-text');
                if (buttonText.textContent.trim() === 'متقدم') {
                    buttonText.textContent = 'بحث بسيط';
                } else {
                    buttonText.textContent = 'متقدم';
                }
            }

            function toggleSearchFields(button) {
                const searchCard = $('#search-form').closest('.card');
                const buttonText = button.querySelector('.hide-button-text');
                const icon = button.querySelector('i');

                if (buttonText.textContent === 'اخفاء') {
                    searchCard.hide();
                    buttonText.textContent = 'اظهار';
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-eye');
                } else {
                    searchCard.show();
                    buttonText.textContent = 'اخفاء';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-times');
                }
            }

            initializeEvents();
        });
    </script>
@endsection