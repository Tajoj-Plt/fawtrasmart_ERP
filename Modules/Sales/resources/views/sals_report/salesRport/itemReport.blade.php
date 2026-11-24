@extends('sales::master')

@section('title')
    تقرير مبيعات البنود
@stop

@section('css')
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #e9ecef;
            --gray: #6c757d;
            --shadow: rgba(0, 0, 0, 0.08);
        }

        * {
            font-family: 'Cairo', sans-serif;
        }

        /* العنوان بدون خلفية */
        .page-header-simple {
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }

        .page-header-simple h1 {
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
            color: var(--secondary);
        }

        .breadcrumb-custom .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
        }

        .breadcrumb-custom .breadcrumb-item a {
            color: var(--gray);
            text-decoration: none;
        }

        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--primary);
        }

        /* الكروت الأساسية */
        .card-simple {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card-simple:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header-simple {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .card-header-simple h5 {
            color: var(--secondary);
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .card-body-simple {
            padding: 1.5rem;
        }

        /* الأزرار - ألوان معدلة */
        .btn-simple {
            padding: 0.6rem 1.2rem;
            border: 2px solid;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* زر التصفية - رمادي */
        .btn-simple.filter {
            background: var(--gray);
            color: white;
            border-color: var(--gray);
        }

        .btn-simple.filter:hover {
            background: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px);
        }

        /* زر التصدير - أخضر */
        .btn-simple.export {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .btn-simple.export:hover {
            background: #219653;
            border-color: #219653;
            transform: translateY(-2px);
        }

        /* زر الطباعة - برتقالي */
        .btn-simple.print {
            background: var(--warning);
            color: white;
            border-color: var(--warning);
        }

        .btn-simple.print:hover {
            background: #e67e22;
            border-color: #e67e22;
            transform: translateY(-2px);
        }

        /* زر إعادة التعيين - رمادي فاتح */
        .btn-simple.reset {
            background: transparent;
            color: var(--gray);
            border-color: var(--gray);
        }

        .btn-simple.reset:hover {
            background: var(--gray);
            color: white;
            transform: translateY(-2px);
        }

        /* إحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
        .stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
        .stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }
        .stat-icon.info { background: rgba(108, 117, 125, 0.1); color: var(--gray); }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* الجداول */
        .table-simple {
            width: 100%;
            border-collapse: collapse;
        }

        .table-simple thead {
            background: var(--light);
        }

        .table-simple th {
            padding: 1rem;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--border);
            text-align: right;
        }

        .table-simple td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            text-align: right;
        }

        .table-simple tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* الفلاتر - تصميم مضغوط */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .form-control-simple {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: white;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            height: 42px;
        }

        .form-control-simple:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        /* التحميل */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            z-index: 10;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* التبويب */
        .view-tabs {
            display: flex;
            gap: 0.5rem;
        }

        .view-tab {
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .view-tab.active {
            background: var(--primary);
            color: white;
        }

        /* التجاوب */
        @media (max-width: 768px) {
            .page-header-simple h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-simple {
                width: 100%;
                justify-content: center;
            }
            
            .table-simple {
                font-size: 0.9rem;
            }

            .form-control-simple {
                font-size: 0.85rem;
            }
        }

        /* تحسينات Select2 - حجم مضغوط */
        .select2-container--bootstrap-5 .select2-selection--single {
            border: 2px solid var(--border);
            border-radius: 8px;
            height: 42px;
            padding: 0.4rem 0.8rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single:focus {
            border-color: var(--primary);
        }

        /* تحسينات للشبكة */
        .row.g-2 {
            --bs-gutter-x: 0.5rem;
            --bs-gutter-y: 0.5rem;
        }
    </style>
@endsection

@section('content')

    {{-- من أجل `.sl-guide` وحدها؛ شاشاتُ التقارير على ورقة `rpt-*` الخاصّة
         بها ولا تتقاطع معها في محدِّد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- ══════════ إرشادُ الشاشة ══════════
         مطويّةٌ افتراضياً: من فتح تقريراً جاء لأرقامه --}}
    <div class="no-print">
        @include('sales::components.guide', [
            'key' => 'sales-reports.by-item-legacy',
            'title' => __('sales_reports.guide.hub.title'),
            'lead' => __('sales_reports.guide.hub.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_reports.guide.hub.a'),
                    __('sales_reports.guide.hub.b'),
                    __('sales_reports.guide.hub.c'),
                    __('sales_reports.guide.hub.d'),
                ],
            ],
        ])
    </div>
    <!-- رأس الصفحة بدون خلفية -->
    <div class="page-header-simple">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-clipboard-list me-3"></i>
                        تقارير مبيعات البنود
                    </h1>
                    <nav class="breadcrumb-custom">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#"><i class="fas fa-home me-2"></i>الرئيسية</a>
                            </li>
                            <li class="breadcrumb-item active">تقرير مبيعات البنود</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- فلاتر التقرير -->
        <div class="card-simple">
            <div class="card-header-simple">
                <h5><i class="fas fa-filter me-2"></i>فلاتر البحث</h5>
            </div>
            <div class="card-body-simple">
                <form id="reportForm">
                    <div class="row g-2">
                        <!-- الصف الأول -->
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">البند</label>
                            <select name="item" id="item" class="form-control-simple select2">
                                <option value="">جميع البنود</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">التصنيف</label>
                            <select name="category" id="category" class="form-control-simple select2">
                                <option value="">جميع التصنيفات</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">العميل</label>
                            <select name="client" id="client" class="form-control-simple select2">
                                <option value="">جميع العملاء</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->trade_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الفرع</label>
                            <select name="branch" id="branch" class="form-control-simple select2">
                                <option value="">جميع الفروع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- الصف الثاني -->
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">المخزن</label>
                            <select name="storehouse" id="storehouse" class="form-control-simple select2">
                                <option value="">جميع المخازن</option>
                                @foreach ($storehouses as $storehouse)
                                    <option value="{{ $storehouse->id }}">{{ $storehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الموظف</label>
                            <select name="employee" id="employee" class="form-control-simple select2">
                                <option value="">جميع الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">حالة الفاتورة</label>
                            <select name="status" id="status" class="form-control-simple select2">
                                <option value="">الكل</option>
                                <option value="فاتورة">فاتورة</option>
                                <option value="مسودة">مسودة</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع الفاتورة</label>
                            <select name="invoice_type" id="invoice_type" class="form-control-simple select2">
                                <option value="">الكل</option>
                                <option value="1">مرتجع</option>
                                <option value="2">اشعار مدين</option>
                                <option value="3">اشعار دائن</option>
                            </select>
                        </div>

                        <!-- الصف الثالث -->
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">تمت الإضافة بواسطة</label>
                            <select name="added_by" id="added_by" class="form-control-simple select2">
                                <option value="">الكل</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع التاريخ</label>
                            <select name="date_type" id="date_type" class="form-control-simple select2">
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

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الفترة</label>
                            <select name="period" id="period" class="form-control-simple select2">
                                <option value="">الكل</option>
                                <option value="daily">يومي</option>
                                <option value="weekly">أسبوعي</option>
                                <option value="monthly">شهري</option>
                                <option value="yearly">سنوي</option>
                            </select>
                        </div>

                        <!-- التواريخ -->
                        <div class="col-lg-3 col-md-6" id="custom_dates">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" name="from_date" id="from_date" class="form-control-simple"
                                value="{{ $fromDate->format('Y-m-d') }}">
                        </div>

                        <div class="col-lg-3 col-md-6" id="custom_to_date">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" name="to_date" id="to_date" class="form-control-simple"
                                value="{{ $toDate->format('Y-m-d') }}">
                        </div>

                        <!-- الأزرار -->
                        <div class="col-lg-6 col-md-12 d-flex align-items-end gap-2">
                            <button type="button" class="btn-simple filter" id="filterBtn">
                                <i class="fas fa-search"></i>تصفية
                            </button>
                            <button type="button" class="btn-simple reset" id="resetBtn">
                                <i class="fas fa-refresh"></i>إعادة تعيين
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-sort-numeric-up"></i>
                </div>
                <div class="stat-value" id="totalQuantity">{{ number_format($totals['total_quantity'] ?? 0, 0) }}</div>
                <div class="stat-label">إجمالي الكمية</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" id="totalAmount">{{ number_format($totals['total_amount'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي المبيعات (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-percent"></i>
                </div>
                <div class="stat-value" id="totalDiscount">{{ number_format($totals['total_discount'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي الخصومات (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value" id="totalInvoices">{{ number_format($totals['total_invoices'] ?? 0, 0) }}</div>
                <div class="stat-label">عدد الفواتير</div>
            </div>
        </div>

        <!-- أدوات التقرير -->
        <div class="card-simple">
            <div class="card-body-simple">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn-simple export" id="exportExcel">
                            <i class="fas fa-file-excel"></i>تصدير إكسل
                        </button>
                        <button class="btn-simple print" id="printBtn">
                            <i class="fas fa-print"></i>طباعة
                        </button>
                    </div>

                    <div class="view-tabs">
                        <div class="view-tab active" id="summaryViewBtn">
                            <i class="fas fa-chart-pie"></i>ملخص
                        </div>
                        <div class="view-tab" id="detailViewBtn">
                            <i class="fas fa-list"></i>تفاصيل
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول التقرير -->
        <div class="card-simple" id="reportContainer" style="position: relative;">
            <div class="loading-overlay">
                <div class="spinner"></div>
            </div>

            <div class="card-header-simple">
                <h5 id="reportTitle">
                    <i class="fas fa-table me-2"></i>
                    تقرير مبيعات البنود من {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}
                </h5>
            </div>
            <div class="card-body-simple p-0">
                <div class="table-responsive">
                    <table class="table-simple">
                        <thead>
                            <tr>
                                <th>المعرف</th>
                                <th>اسم البند</th>
                                <th>كود المنتج</th>
                                <th>التاريخ</th>
                                <th>الموظف</th>
                                <th>الفاتورة</th>
                                <th>العميل</th>
                                <th>سعر الوحدة (ريال)</th>
                                <th>الكمية</th>
                                <th>الخصم (ريال)</th>
                                <th>الإجمالي (ريال)</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <!-- البيانات تأتي من AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

    <script>
        $(document).ready(function() {
            // تهيئة Select2
          

            // تحميل البيانات الأولية
            loadReportData();

            // أحداث الفلاتر
            $('#filterBtn').click(loadReportData);
            $('#resetBtn').click(resetFilters);
            $('.select2, input[type="date"]').change(loadReportData);

            // أحداث الأزرار
            $('#exportExcel').click(exportToExcel);
            $('#printBtn').click(() => window.print());

            // أحداث التبويب
            $('.view-tab').click(function() {
                $('.view-tab').removeClass('active');
                $(this).addClass('active');
            });

            // التعامل مع تغيير نوع التاريخ
            $('#date_type').change(function() {
                const dateType = $(this).val();
                if (dateType === 'custom') {
                    $('#custom_dates, #custom_to_date').fadeIn();
                } else {
                    $('#custom_dates, #custom_to_date').fadeOut();
                    setDateRange(dateType);
                }
                loadReportData();
            });

            function loadReportData() {
                $('.loading-overlay').fadeIn();
                
                $.ajax({
                    url: '{{ route('salesReports.byItemReportAjax') }}',
                    method: 'GET',
                    data: $('#reportForm').serialize(),
                    success: function(response) {
                        updateReportDisplay(response);
                        $('.loading-overlay').fadeOut();
                    },
                    error: function() {
                        alert('حدث خطأ في تحميل البيانات');
                        $('.loading-overlay').fadeOut();
                    }
                });
            }

            function resetFilters() {
                $('#reportForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#date_type').val('custom').trigger('change');
                $('#from_date').val('{{ $fromDate->format('Y-m-d') }}');
                $('#to_date').val('{{ $toDate->format('Y-m-d') }}');
                loadReportData();
            }

            function setDateRange(dateType) {
                const today = new Date();
                let fromDate, toDate;

                switch (dateType) {
                    case 'today':
                        fromDate = toDate = today;
                        break;
                    case 'yesterday':
                        fromDate = toDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
                        break;
                    case 'this_week':
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay());
                        fromDate = startOfWeek;
                        toDate = today;
                        break;
                    case 'last_week':
                        const lastWeekEnd = new Date(today);
                        lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                        const lastWeekStart = new Date(lastWeekEnd);
                        lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                        fromDate = lastWeekStart;
                        toDate = lastWeekEnd;
                        break;
                    case 'this_month':
                        fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        toDate = today;
                        break;
                    case 'last_month':
                        fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                    case 'this_year':
                        fromDate = new Date(today.getFullYear(), 0, 1);
                        toDate = today;
                        break;
                    case 'last_year':
                        fromDate = new Date(today.getFullYear() - 1, 0, 1);
                        toDate = new Date(today.getFullYear() - 1, 11, 31);
                        break;
                    default:
                        return;
                }

                $('#from_date').val(formatDateForInput(fromDate));
                $('#to_date').val(formatDateForInput(toDate));
            }

            function formatDateForInput(date) {
                return date.toISOString().split('T')[0];
            }

            function updateReportDisplay(data) {
                // تحديث الإحصائيات
                $('#totalQuantity').text(data.totals.total_quantity.toLocaleString());
                $('#totalAmount').text(data.totals.total_amount.toLocaleString());
                $('#totalDiscount').text(data.totals.total_discount.toLocaleString());
                $('#totalInvoices').text(data.totals.total_invoices.toLocaleString());

                // تحديث عنوان التقرير
                $('#reportTitle').html(`
                    <i class="fas fa-table me-2"></i>
                    تقرير مبيعات البنود من ${data.from_date} إلى ${data.to_date}
                `);

                // تحديث الجدول
                updateTableBody(data.grouped_items);
            }

            function updateTableBody(groupedItems) {
                let html = '';
                
                Object.keys(groupedItems).forEach(itemName => {
                    const items = groupedItems[itemName];
                    const firstItem = items[0];
                    
                    // رأس البند
                    html += `<tr style="background: #f8f9fa;"><td colspan="11"><strong>${itemName}</strong></td></tr>`;
                    
                    // عناصر البند
                    items.forEach(item => {
                        const isReturn = ['return', 'returned'].includes(item.invoice.type);
                        html += `
                            <tr>
                                <td>${item.id}</td>
                                <td>${item.product.name}</td>
                                <td>${item.product.code}</td>
                                <td>${new Date(item.invoice.invoice_date).toLocaleDateString('ar-SA')}</td>
                                <td>${item.invoice.employee?.full_name || 'غير محدد'}</td>
                                <td>#${item.invoice.code}</td>
                                <td>${item.invoice.client?.trade_name || 'غير محدد'}</td>
                                <td>${parseFloat(item.unit_price).toLocaleString()}</td>
                                <td>${isReturn ? '-' + item.quantity : item.quantity}</td>
                                <td>${parseFloat(item.discount_amount || 0).toLocaleString()}</td>
                                <td>${isReturn ? '-' + parseFloat(item.total_amount).toLocaleString() : parseFloat(item.total_amount).toLocaleString()}</td>
                            </tr>
                        `;
                    });
                });
                
                $('#reportTableBody').html(html);
            }

            function exportToExcel() {
                const table = document.querySelector('.table-simple');
                const wb = XLSX.utils.table_to_book(table);
                XLSX.writeFile(wb, 'تقرير_مبيعات_البنود.xlsx');
            }
        });
    </script>
@endsection