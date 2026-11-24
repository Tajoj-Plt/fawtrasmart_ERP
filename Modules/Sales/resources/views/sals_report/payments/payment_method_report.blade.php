@extends('sales::master')

@section('title')
    تقرير المدفوعات حسب طريقة الدفع
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

        .stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
        .stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
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

        /* الرسم البياني */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
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

            .chart-container {
                height: 300px;
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
            'key' => 'sales-reports.payment-methods',
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
                        <i class="fas fa-credit-card me-3"></i>
                        تقارير المدفوعات حسب طريقة الدفع
                    </h1>
                    <nav class="breadcrumb-custom">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#"><i class="fas fa-home me-2"></i>الرئيسية</a>
                            </li>
                            <li class="breadcrumb-item active">تقرير المدفوعات حسب طريقة الدفع</li>
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
                            <label class="form-label">طريقة الدفع</label>
                            <select name="payment_method" id="payment_method" class="form-control-simple select2">
                                <option value="">جميع طرق الدفع</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method['id'] }}">{{ $method['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الموظف</label>
                            <select name="employee" id="employee" class="form-control-simple select2">
                                <option value="">جميع الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">العميل</label>
                            <select name="client" id="client" class="form-control-simple select2">
                                <option value="">جميع العملاء</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->trade_name }}-{{ $client->code }}</option>
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
                            <label class="form-label">الحساب</label>
                            <select name="account" id="account" class="form-control-simple select2">
                                <option value="">جميع الحسابات</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الخزينة</label>
                            <select name="treasury" id="treasury" class="form-control-simple select2">
                                <option value="">جميع الخزائن</option>
                                @foreach ($treasuries as $treasury)
                                    <option value="{{ $treasury->id }}">{{ $treasury->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع التقرير</label>
                            <select name="report_type" id="report_type" class="form-control-simple select2">
                                <option value="both">المدفوعات وسندات القبض</option>
                                <option value="payments">المدفوعات فقط</option>
                                <option value="receipts">سندات القبض فقط</option>
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
                <div class="stat-icon success">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" id="totalPayments">{{ number_format($totals['total_payments'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي المدفوعات (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value" id="totalReceipts">{{ number_format($totals['total_receipts'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي سندات القبض (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value" id="totalAmount">{{ number_format($totals['total_amount'] ?? 0, 2) }}</div>
                <div class="stat-label">المجموع الإجمالي (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value" id="totalCount">{{ number_format($totals['total_count'] ?? 0, 0) }}</div>
                <div class="stat-label">عدد العمليات</div>
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

        <!-- الرسم البياني -->
        <div class="card-simple" id="chartSection">
            <div class="card-header-simple">
                <h5><i class="fas fa-chart-bar me-2"></i>الرسم البياني للمدفوعات حسب طريقة الدفع</h5>
            </div>
            <div class="card-body-simple">
                <div class="chart-container">
                    <canvas id="paymentMethodChart"></canvas>
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
                    تقرير المدفوعات حسب طريقة الدفع من {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}
                </h5>
            </div>
            <div class="card-body-simple p-0">
                <div class="table-responsive">
                    <table class="table-simple">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>طريقة الدفع</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>الموظف</th>
                                <th>النوع</th>
                                <th>المرجع</th>
                                <th>المبلغ (ريال)</th>
                                <th>ملاحظات</th>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

    <script>
        let paymentMethodChart;

        $(document).ready(function() {
            // تهيئة Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dir: 'rtl',
                minimumResultsForSearch: 0,
                width: '100%'
            });

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
                
                if ($(this).attr('id') === 'summaryViewBtn') {
                    $('#chartSection').show();
                    $('#reportContainer').hide();
                } else {
                    $('#chartSection').hide();
                    $('#reportContainer').show();
                }
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
                    url: '{{ route('salesReports.paymentMethodReportAjax') }}',
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
                $('#totalPayments').text(data.totals.total_payments.toLocaleString());
                $('#totalReceipts').text(data.totals.total_receipts.toLocaleString());
                $('#totalAmount').text(data.totals.total_amount.toLocaleString());
                $('#totalCount').text(data.totals.total_count.toLocaleString());

                // تحديث عنوان التقرير
                $('#reportTitle').html(`
                    <i class="fas fa-table me-2"></i>
                    تقرير المدفوعات حسب طريقة الدفع من ${data.from_date} إلى ${data.to_date}
                `);

                // تحديث الرسم البياني
                updateChart(data.chart_data);

                // تحديث الجدول
                updateTableBody(data.grouped_data);
            }

            function updateChart(chartData) {
                const ctx = document.getElementById('paymentMethodChart').getContext('2d');

                if (paymentMethodChart) {
                    paymentMethodChart.destroy();
                }

                paymentMethodChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'المبلغ (ريال)',
                            data: chartData.payments.map((payment, index) => payment + chartData.receipts[index]),
                            backgroundColor: [
                                'rgba(39, 174, 96, 0.8)',
                                'rgba(52, 152, 219, 0.8)',
                                'rgba(243, 156, 18, 0.8)',
                                'rgba(231, 76, 60, 0.8)',
                                'rgba(155, 89, 182, 0.8)',
                                'rgba(241, 196, 15, 0.8)'
                            ],
                            borderColor: [
                                'rgba(39, 174, 96, 1)',
                                'rgba(52, 152, 219, 1)',
                                'rgba(243, 156, 18, 1)',
                                'rgba(231, 76, 60, 1)',
                                'rgba(155, 89, 182, 1)',
                                'rgba(241, 196, 15, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ' + context.parsed.toLocaleString() + ' ريال (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function updateTableBody(groupedData) {
                let html = '';
                let counter = 1;
                
                Object.keys(groupedData).forEach(methodId => {
                    const methodData = groupedData[methodId];
                    const paymentMethod = methodData.payment_method;
                    
                    // رأس طريقة الدفع
                    html += `<tr style="background: #f8f9fa;"><td colspan="9"><strong>${paymentMethod.name}</strong></td></tr>`;
                    
                    // المدفوعات
                    if (methodData.payments && methodData.payments.length > 0) {
                        methodData.payments.forEach(payment => {
                            html += `
                                <tr>
                                    <td>${counter++}</td>
                                    <td>${paymentMethod.name}</td>
                                    <td>${new Date(payment.payment_date).toLocaleDateString('ar-SA')}</td>
                                    <td>${payment.invoice?.client?.trade_name || 'غير محدد'}</td>
                                    <td>${payment.employee?.name || 'غير محدد'}</td>
                                    <td><span class="badge bg-success">مدفوعة</span></td>
                                    <td>${payment.reference_number || '--'}</td>
                                    <td>${parseFloat(payment.amount).toLocaleString()}</td>
                                    <td>${payment.notes || '--'}</td>
                                </tr>
                            `;
                        });
                    }

                    // سندات القبض
                    if (methodData.receipts && methodData.receipts.length > 0) {
                        methodData.receipts.forEach(receipt => {
                            html += `
                                <tr>
                                    <td>${counter++}</td>
                                    <td>${paymentMethod.name}</td>
                                    <td>${new Date(receipt.date).toLocaleDateString('ar-SA')}</td>
                                    <td>${receipt.client?.trade_name || 'غير محدد'}</td>
                                    <td>${receipt.employee?.name || 'غير محدد'}</td>
                                    <td><span class="badge bg-primary">سند قبض</span></td>
                                    <td>${receipt.code || '--'}</td>
                                    <td>${parseFloat(receipt.amount).toLocaleString()}</td>
                                    <td>${receipt.description || '--'}</td>
                                </tr>
                            `;
                        });
                    }
                });
                
                $('#reportTableBody').html(html);
            }

            function exportToExcel() {
                const table = document.querySelector('.table-simple');
                const wb = XLSX.utils.table_to_book(table);
                XLSX.writeFile(wb, 'تقرير_المدفوعات_حسب_طريقة_الدفع.xlsx');
            }
        });
    </script>
@endsection