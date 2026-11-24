@extends('purchases::master')

@section('title')
    عرض المورد
@stop

@section('head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
@endsection

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    {{-- نظامُ تصميم شاشة العرض `cx-*` — ملفُّ أصلٍ عامّ في `public/assets/css`
         تستعمله شاشةُ «عرض العميل»، فاستعمالُه هنا لا يربط الوحدتين. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/show_client_redesign.css') }}">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* أنماط مخصصة لتبويب نقاط الأرصدة */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #adb5bd;
        }

        .empty-state h5 {
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .empty-state p {
            margin-bottom: 1rem;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
            margin-bottom: 0;
        }

        .card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
        }

        .table th {
            font-weight: 600;
            color: #495057;
        }

        .badge {
            font-size: 0.75em;
        }

        .nav-tabs .nav-link.active {
            font-weight: 600;
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

        /* Custom styles for supplier */
        .status-badge {
            padding: 0.5em 0.75em;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .payment-section,
        .supplier-section {
            flex: 1;
            max-width: 50%;
        }

        .account-movements-table {
            font-size: 14px;
        }

        .account-movements-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .balance-positive {
            color: #28a745;
            font-weight: 600;
        }

        .balance-negative {
            color: #dc3545;
            font-weight: 600;
        }

        .amount-deposit {
            color: #28a745;
        }

        .amount-withdraw {
            color: #dc3545;
        }

        .operation-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        /* أنماط إضافية للتناسق مع تصميم العميل */
        .actions-card {
            margin-bottom: 1.5rem;
        }

        .actions-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .actions-grid .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-card {
            margin-bottom: 1rem;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if (session('toast_message'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                toastr.{{ session('toast_type', 'success') }}('{{ session('toast_message') }}', '', {
                    positionClass: 'toast-bottom-left',
                    closeButton: true,
                    progressBar: true,
                    timeOut: 5000
                });
            });
        </script>
    @endif

    {{-- الإطار على قياس شاشات العملاء بطلب المستخدم: الترويسة والبطاقات
         والجداول والشارات من نظام تصميم الوحدة. والتخطيط لم يُمَسّ —
         التبويبات وعددُ الأعمدة والنوافذُ وكلُّ `id` تقرؤه السكربتات كما
         هو. والغلافُ `.sl-scope` يمتدّ إلى آخر القسم بما فيه النوافذ:
         قواعدُ النظام كلُّها مقيَّدةٌ به، فغلافٌ يُغلق مبكراً لا يفعل شيئاً. --}}
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'عرض المورد',
            'icon' => 'truck',
            'sub' => $supplier->trade_name,
            'crumbs' => [
                'المشتريات' => route('purchases.dashboard'),
                'الموردون' => route('SupplierManagement.index'),
                'عرض' => null,
            ],
        ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- لوحة إرشاد الشاشة — مطويّةً افتراضياً --}}
    @include('purchases::components.guide', [
        'key' => 'suppliers.show',
        'title' => __('purchase_cycle.guide.supplier.show.title'),
        'lead' => __('purchase_cycle.guide.supplier.show.lead'),
        'open' => false,
        'blocks' => [
            __('purchase_cycle.guide.supplier.balance.title') => [
                __('purchase_cycle.guide.supplier.balance.a'),
                __('purchase_cycle.guide.supplier.balance.b'),
                __('purchase_cycle.guide.supplier.balance.c'),
                __('purchase_cycle.guide.supplier.balance.d'),
            ],
            __('purchase_cycle.guide.supplier.inactive.title') => [
                __('purchase_cycle.guide.supplier.inactive.a'),
                __('purchase_cycle.guide.supplier.inactive.b'),
            ],
        ],
    ])


    {{-- ══════════════ بطاقة رأس المورد ══════════════
         نظيرُ `client::partials.client-info`: الاسمُ والرمز، ثمّ شاراتُ
         الحالة، ثمّ الإجراءاتُ مجموعةً في الترويسة، ثمّ شبكةُ المعلومات
         بأيقوناتها. وكانت ثلاثةَ أعمدةٍ نصّيةٍ عارية وصفَّ أزرارٍ سائباً
         تحتها، فلا تُعرف حالُ المورد من أوّل نظرة.

         الأصنافُ `cx-*` من `public/assets/css/show_client_redesign.css`، وهو
         **ملفُّ أصلٍ عامّ** لا قالبُ وحدة — فاستعمالُه هنا لا يربط المشتريات
         بوحدة العملاء ولا ينكسر بإطفائها في `modules_statuses.json`. --}}
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $supplierName = $supplier->trade_name
            ?: (trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: '#'.$supplier->id);
        $supplierInitial = mb_substr($supplierName ?: '?', 0, 1);

        $rawBalance = (float) ($supplier->account->balance ?? 0);
        // حسابُ المورد دائنٌ بطبيعته: الموجب «له علينا»
        $balanceDirection = $rawBalance >= 0 ? 'دائن' : 'مدين';
    @endphp

    <div class="cx-page">
        <div class="cx-header">
            <div class="cx-header-row">
                <div class="cx-identity">
                    <div class="cx-avatar">{{ $supplierInitial }}</div>

                    <div class="cx-title-block">
                        <div class="cx-title-line">
                            <h1 class="cx-name">{{ $supplierName }}</h1>
                            <span class="cx-code">#{{ $supplier->number_suply }}</span>
                        </div>

                        <div class="cx-badges">
                            @if ($supplier->status == 1)
                                <span class="cx-pill cx-pill-success"><i class="fa fa-check-circle"></i> نشط</span>
                            @else
                                <span class="cx-pill cx-pill-danger"><i class="fa fa-times-circle"></i> غير نشط</span>
                            @endif

                            <span class="cx-pill {{ $rawBalance >= 0 ? 'cx-pill-warning' : 'cx-pill-info' }}">
                                <i class="fa fa-balance-scale"></i>
                                {{ CurrencySettings::format(abs($rawBalance)) }} {{ $balanceDirection }}
                            </span>

                            @if ($stats['wallet_balance'] > 0)
                                <span class="cx-pill cx-pill-primary">
                                    <i class="fa fa-wallet"></i>
                                    محفظة {{ CurrencySettings::format($stats['wallet_balance']) }}
                                </span>
                            @endif
                        </div>

                        @if ($supplier->name || $supplier->email)
                            <div class="cx-sub-line">
                                @if ($supplier->name)
                                    <span><i class="fa fa-building"></i> {{ $supplier->name }}</span>
                                @endif
                                @if ($supplier->email)
                                    <span><i class="fa fa-envelope"></i> {{ $supplier->email }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- الإجراءات في الترويسة لا في بطاقةٍ سائبةٍ تحتها --}}
                <div class="cx-header-actions">
                    @permission('purchases.suppliers.edit')
                        <a href="{{ route('SupplierManagement.edit', $supplier->id) }}" class="btn btn-primary">
                            <i class="fa fa-edit"></i> <span>تعديل البيانات</span>
                        </a>
                    @endpermission

                    @permission('purchases.suppliers.view_statement')
                        <a href="{{ route('SupplierManagement.statement', $supplier->id) }}" class="btn btn-outline-primary">
                            <i class="fa fa-file-alt"></i> <span>كشف حساب</span>
                        </a>
                    @endpermission

                    @permission('purchases.invoices.create')
                        <a href="{{ route('invoicePurchases.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-outline-primary">
                            <i class="fa fa-shopping-cart"></i> <span>فاتورة شراء</span>
                        </a>
                    @endpermission

                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fa fa-ellipsis-h"></i> <span>المزيد</span>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end">
                            @permission('purchases.suppliers.manage_opening_balance')
                                <li>
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modal_opening_balance">
                                        <i class="fa fa-plus-circle"></i> رصيد افتتاحي
                                    </button>
                                </li>
                            @endpermission

                            @permission('purchases.payments.bulk_create')
                                <li>
                                    <a class="dropdown-item" href="{{ route('PaymentSupplier.bulk-payment', ['supplier_id' => $supplier->id]) }}">
                                        <i class="fa fa-file-invoice-dollar"></i> الفواتير المستحقة
                                    </a>
                                </li>
                            @endpermission

                            <li>
                                <a class="dropdown-item" href="{{ route('purchases.supplier-wallet.show', $supplier->id) }}">
                                    <i class="fa fa-wallet"></i> محفظة المورد
                                </a>
                            </li>

                            @permission('purchases.suppliers.assign_employee')
                                <li>
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modal_assign_employee">
                                        <i class="fa fa-user-tag"></i> تعيين لموظف
                                    </button>
                                </li>
                            @endpermission

                            @permission('purchases.suppliers.edit')
                                <li><hr class="dropdown-divider"></li>
                                @if ($supplier->status == 1)
                                    <li>
                                        <button type="button" class="dropdown-item text-danger"
                                            onclick="changeStatus({{ $supplier->id }}, 0, '{{ $supplier->trade_name }}')">
                                            <i class="fa fa-ban"></i> إيقاف المورد
                                        </button>
                                    </li>
                                @else
                                    <li>
                                        <button type="button" class="dropdown-item text-success"
                                            onclick="changeStatus({{ $supplier->id }}, 1, '{{ $supplier->trade_name }}')">
                                            <i class="fa fa-check"></i> تفعيل المورد
                                        </button>
                                    </li>
                                @endif
                            @endpermission

                            @permission('purchases.suppliers.delete')
                                <li>
                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#modal_DELETE1">
                                        <i class="fa fa-trash"></i> حذف المورد
                                    </button>
                                </li>
                            @endpermission
                        </ul>
                    </div>
                </div>
            </div>

            {{-- شبكةُ المعلومات — نظيرُ `cx-meta-grid` في شاشة العميل --}}
            <div class="cx-meta-grid">
                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-phone"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">رقم الهاتف</span>
                        <span class="cx-meta-value">{{ $supplier->phone ?: 'غير محدد' }}</span>
                    </div>
                </div>

                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-envelope"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">البريد الإلكتروني</span>
                        <span class="cx-meta-value">{{ $supplier->email ?: 'غير محدد' }}</span>
                    </div>
                </div>

                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-map-marker-alt"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">العنوان</span>
                        <span class="cx-meta-value">{{ $supplier->address ?: 'غير محدد' }}</span>
                    </div>
                </div>

                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-id-card"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">الرقم الضريبي</span>
                        <span class="cx-meta-value">{{ $supplier->tax_number ?: 'غير محدد' }}</span>
                    </div>
                </div>

                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-file-contract"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">السجل التجاري</span>
                        <span class="cx-meta-value">{{ $supplier->commercial_registration ?: 'غير محدد' }}</span>
                    </div>
                </div>

                <div class="cx-meta-item">
                    <span class="cx-meta-icon"><i class="fa fa-calendar"></i></span>
                    <div class="cx-meta-text">
                        <span class="cx-meta-label">آخر عملية شراء</span>
                        <span class="cx-meta-value">
                            {{ $stats['last_purchase'] ? LanguageSettings::formatDate($stats['last_purchase']) : 'لا شيء بعد' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>


        {{-- بطاقةُ التبويبات على قياس `cx-tabs-card`: الشريطُ ابنٌ مباشر
             للبطاقة (هكذا تنتقيه ورقةُ الأنماط)، والمحتوى في `tab-content`. --}}
        <div class="cx-tabs-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                        <i class="fa fa-info-circle me-1"></i>
                        التفاصيل
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab">
                        <i class="fa fa-file-invoice me-1"></i>
                        فواتير الشراء
                        @if ($purchaseInvoices->total() > 0)
                            <span class="badge">{{ $purchaseInvoices->total() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#returns" role="tab">
                        <i class="fa fa-undo me-1"></i>
                        المرتجعات
                        @if ($returnInvoices->total() > 0)
                            <span class="badge">{{ $returnInvoices->total() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                        <i class="fa fa-credit-card me-1"></i>
                        المدفوعات
                        @if ($payments->total() > 0)
                            <span class="badge">{{ $payments->total() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#account-movements" role="tab">
                        <i class="fa fa-exchange-alt me-1"></i>
                        حركة الحساب
                        @if (count($accountMovements) > 0)
                            <span class="badge">{{ count($accountMovements) }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                        <i class="fa fa-history me-1"></i>
                        سجل النشاطات
                    </a>
                </li>
            </ul>

            <div class="tab-content p-3">
                <!-- تبويب التفاصيل -->
                <div class="tab-pane active" id="details" role="tabpanel">
            {{-- ══════════════ الإحصاءات ══════════════
                 أربعُ بطاقاتٍ تجيب عمّا تُفتح الشاشةُ من أجله: كم اشترينا من هذا
                 المورد، وكم فاتورة، وكم بقي علينا، وكم سدّدنا. --}}
            <div class="cx-stat-grid">
                <div class="cx-stat-card">
                    <div class="cx-stat-top">
                        <span class="cx-stat-label">إجمالي المشتريات</span>
                        <span class="cx-stat-icon is-primary"><i class="fa fa-shopping-cart"></i></span>
                    </div>
                    <div class="cx-stat-value">{{ CurrencySettings::format($stats['total_purchases']) }}</div>
                    @if ($stats['returns_total'] > 0)
                        <span class="cx-stat-trend is-down">
                            <i class="fa fa-undo"></i> مرتجعات {{ CurrencySettings::number($stats['returns_total']) }}
                        </span>
                    @endif
                </div>

                <div class="cx-stat-card">
                    <div class="cx-stat-top">
                        <span class="cx-stat-label">عدد فواتير الشراء</span>
                        <span class="cx-stat-icon is-info"><i class="fa fa-file-invoice"></i></span>
                    </div>
                    <div class="cx-stat-value">{{ LanguageSettings::formatNumber($stats['invoice_count'], 0) }}</div>
                </div>

                <div class="cx-stat-card">
                    <div class="cx-stat-top">
                        <span class="cx-stat-label">المستحقّ علينا</span>
                        <span class="cx-stat-icon {{ $stats['due_value'] > 0 ? 'is-danger' : 'is-success' }}">
                            <i class="fa fa-hand-holding-usd"></i>
                        </span>
                    </div>
                    <div class="cx-stat-value">{{ CurrencySettings::format($stats['due_value']) }}</div>
                    <span class="cx-stat-trend {{ $stats['due_value'] > 0 ? 'is-down' : 'is-up' }}">
                        {{ $stats['due_value'] > 0 ? 'غير مسدَّد' : 'لا مستحقّات' }}
                    </span>
                </div>

                <div class="cx-stat-card">
                    <div class="cx-stat-top">
                        <span class="cx-stat-label">إجمالي المدفوع</span>
                        <span class="cx-stat-icon is-success"><i class="fa fa-money-bill-wave"></i></span>
                    </div>
                    <div class="cx-stat-value">{{ CurrencySettings::format($stats['paid_total']) }}</div>
                </div>
            </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5><i class="fa fa-building me-2"></i>معلومات المورد الأساسية</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>اسم الشركة:</strong></td>
                                            <td>{{ $supplier->name ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>الاسم التجاري:</strong></td>
                                            <td>{{ $supplier->trade_name ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>البريد الإلكتروني:</strong></td>
                                            <td>{{ $supplier->email ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>رقم الهاتف:</strong></td>
                                            <td>{{ $supplier->phone ?? 'غير محدد' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="card-header">
                                    <h5><i class="fa fa-map-marker-alt me-2"></i>العنوان والمعلومات الإضافية</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>العنوان:</strong></td>
                                            <td>{{ $supplier->address ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>الرقم الضريبي:</strong></td>
                                            <td>{{ $supplier->tax_number ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>السجل التجاري:</strong></td>
                                            <td>{{ $supplier->commercial_registration ?? 'غير محدد' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>العملة:</strong></td>
                                            <td>{{ \App\Support\CurrencySettings::name() }} ({{ \App\Support\CurrencySettings::code() }})</td>
                                        </tr>
                                        <tr>
                                            <td><strong>الموظف المسؤول:</strong></td>
                                            <td>{{ $supplier->employee?->full_name ?? 'غير محدَّد' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويب الفواتير -->
                <div class="tab-pane" id="invoices" role="tabpanel">
                    @if ($purchaseInvoices->count() > 0)
                        @include('purchases::purchases.invoices_purchase.partials.table', [
                            'purchaseData' => $purchaseInvoices,
                        ])
                    @else
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <h5>لا توجد فواتير</h5>
                            <p>لم يتم إنشاء أي فواتير شراء لهذا المورد بعد</p>
                            <a href="{{ route('invoicePurchases.create', ['supplier_id' => $supplier->id]) }}"
                                class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>إنشاء فاتورة شراء
                            </a>
                        </div>
                    @endif
                </div>

                <!-- تبويب المرتجعات -->
                <div class="tab-pane" id="returns" role="tabpanel">
                    @if ($returnInvoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم المرتجع</th>
                                        <th>الفاتورة الأصلية</th>
                                        <th>التاريخ</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                        <th>خيارات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($returnInvoices as $invoice)
                                        <tr>
                                            <td>
                                                <strong>{{ $invoice->code }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $invoice->created_at->format('Y-m-d') }}</small>
                                            </td>
                                            <td>
                                                @if($invoice->originalInvoice)
                                                    {{ $invoice->originalInvoice->code }}
                                                @else
                                                    <span class="text-muted">--</span>
                                                @endif
                                            </td>
                                            <td>{{ $invoice->date ? \Carbon\Carbon::parse($invoice->date)->format('Y-m-d') : '--' }}</td>
                                            <td class="text-danger fw-bold">
                                                @php
                                                    $currencySymbol =
                                                        '<img src="' .
                                                        asset('assets/images/Saudi_Riyal.svg') .
                                                        '" alt="ريال سعودي" width="15" style="vertical-align: middle;">';
                                                @endphp
                                                {{ number_format($invoice->grand_total, 2) }} {!! $currencySymbol !!}
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = match ($invoice->payment_status) {
                                                        'paid' => 'bg-success',
                                                        'partially_paid' => 'bg-warning',
                                                        'unpaid' => 'bg-danger',
                                                        default => 'bg-secondary',
                                                    };
                                                    $statusText = match ($invoice->payment_status) {
                                                        'paid' => 'مدفوعة',
                                                        'partially_paid' => 'مدفوعة جزئياً',
                                                        'unpaid' => 'غير مدفوعة',
                                                        default => 'غير معروفة',
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <div class="dropdown">
                                                        <button
                                                            class="btn bg-gradient-info fa fa-ellipsis-v btn-sm"
                                                            type="button" data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item"
                                                                href="{{ route('ReturnsInvoice.show', $invoice->id) }}">
                                                                <i class="fas fa-eye me-2 text-primary"></i>عرض التفاصيل
                                                            </a>
                                                            <a class="dropdown-item"
                                                                href="{{ route('ReturnsInvoice.edit', $invoice->id) }}">
                                                                <i class="fas fa-edit me-2 text-success"></i>تعديل المرتجع
                                                            </a>
                                                            <a class="dropdown-item"
                                                                href=""
                                                                target="_blank">
                                                                <i class="fas fa-print me-2 text-info"></i>طباعة
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الترقيم للمرتجعات -->
                        {{ $returnInvoices->links() }}
                    @else
                        {{-- المرتجع يُنشأ من فاتورةٍ قائمة لا من نموذج فارغ،
                             فالرابط يقود إلى فواتير الشراء لا إلى شاشة إنشاءٍ
                             صارت تُعيد التوجيه --}}
                        <div class="empty-state">
                            <i class="fas fa-undo"></i>
                            <h5>{{ __('purchase_cycle.return.empty_title') }}</h5>
                            <p>{{ __('purchase_cycle.return.empty_hint') }}</p>
                            <a href="{{ route('invoicePurchases.index') }}" class="btn btn-primary">
                                <i class="fas fa-file-invoice me-1"></i>{{ __('purchase_cycle.sections.purchase_invoice') }}
                            </a>
                        </div>
                    @endif
                </div>

                <!-- تبويب المدفوعات -->
                <div class="tab-pane" id="payments" role="tabpanel">
                    @if ($payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الدفع</th>
                                        <th>الفاتورة</th>
                                        <th>المبلغ</th>
                                        <th>تاريخ الدفع</th>
                                        <th>الحالة</th>
                                        <th>خيارات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td><strong>#{{ $payment->payment_number ?? $payment->id }}</strong></td>
                                            <td>{{ $payment->purchase_invoice ? $payment->purchase_invoice->code : '--' }}</td>
                                            <td class="text-success fw-bold">
                                                @php
                                                    $currencySymbol =
                                                        '<img src="' .
                                                        asset('assets/images/Saudi_Riyal.svg') .
                                                        '" alt="ريال سعودي" width="15" style="vertical-align: middle;">';
                                                @endphp
                                                {{ number_format($payment->amount, 2) }} {!! $currencySymbol !!}
                                            </td>
                                            <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : \Carbon\Carbon::parse($payment->created_at)->format('Y-m-d') }}</td>
                                            <td>
                                                @php
                                                    $statusClass = match ($payment->payment_status) {
                                                        1 => 'bg-success',
                                                        2 => 'bg-warning',
                                                        3 => 'bg-secondary',
                                                        default => 'bg-light',
                                                    };
                                                    $statusText = match ($payment->payment_status) {
                                                        1 => 'مكتمل',
                                                        2 => 'غير مكتمل',
                                                        3 => 'مسودة',
                                                        default => 'غير معروف',
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <div class="dropdown">
                                                        <button
                                                            class="btn bg-gradient-info fa fa-ellipsis-v btn-sm"
                                                            type="button" data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            @permission('purchases.payments.view')
                                                                <a class="dropdown-item"
                                                                    href="{{ route('PaymentSupplier.showSupplierPayment', $payment->id) }}">
                                                                    <i class="fas fa-eye me-2 text-primary"></i>عرض التفاصيل
                                                                </a>
                                                            @endpermission
                                                            @permission('purchases.payments.edit')
                                                                <a class="dropdown-item"
                                                                    href="{{ route('PaymentSupplier.editSupplierPayment', $payment->id) }}">
                                                                    <i class="fas fa-edit me-2 text-success"></i>تعديل الدفع
                                                                </a>
                                                            @endpermission
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الترقيم للمدفوعات -->
                        {{ $payments->links() }}
                    @else
                        {{-- الرابط كان يمرّر `['id' => 1]` مكتوباً في القالب،
                             فيقود إلى الفاتورة رقم واحد أياً كان المورّد
                             المعروض. والدفعة تُسجَّل من فاتورتها أو بالدفع
                             المجمَّع لهذا المورّد — وكلاهما يعرف سياقه. --}}
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <h5>{{ __('purchase_cycle.payment.empty_title') }}</h5>
                            <p>{{ __('purchase_cycle.payment.empty_hint') }}</p>
                            @permission('purchases.payments.bulk_create')
                                <a href="{{ route('PaymentSupplier.bulk-payment', ['supplier_id' => $supplier->id]) }}"
                                    class="btn btn-primary">
                                    <i class="fas fa-layer-group me-1"></i>{{ __('purchase_cycle.payment.bulk') }}
                                </a>
                            @endpermission
                        </div>
                    @endif
                </div>

                <!-- تبويب حركة الحساب -->
                <div class="tab-pane" id="account-movements" role="tabpanel">
                    @if (! ($canSeeLedger ?? true))
                        <div class="alert alert-info mb-0">
                            <i class="fa fa-lock me-1"></i>
                            كشف الحساب المحاسبي يُعرض كاملاً لمن يملك رؤية بيانات المشتريات كلِّها —
                            نطاقُك الحاليّ مقيَّد، والرصيدُ لا يُجزَّأ بحسب المستخدم.
                        </div>
                    @elseif (count($accountMovements) > 0)
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="mb-0">
                                <i class="fa fa-exchange-alt me-2" style="color: var(--primary-color);"></i>
                                إجمالي {{ count($accountMovements) }} عملية
                            </h6>
                            <div class="d-flex gap-2">
                                @permission('purchases.suppliers.view_statement')
                                    <a href="{{ route('SupplierManagement.statement', $supplier->id) }}"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fa fa-file-pdf me-1"></i>كشف حساب مفصل
                                    </a>
                                @endpermission
                            </div>
                        </div>

                        <!-- ملخص الحساب -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>الرصيد الافتتاحي</h6>
                                        <h3>{{ number_format($supplier->opening_balance ?? 0, 2) }} ر.س</h3>
                                        <small class="text-muted">
                                            {{ ($supplier->account->opening_balance_type ?? \App\Models\Account::CREDIT) === \App\Models\Account::DEBIT ? 'مدين' : 'دائن' }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6>الرصيد الحالي</h6>
                                        <h3 class="{{ $supplier->account->balance >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format(abs($supplier->account->balance ?? 0), 2) }} ر.س
                                        </h3>
                                        <small class="text-muted">
                                            {{ ($supplier->account->balance ?? 0) >= 0 ? 'دائن' : 'مدين' }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover account-movements-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="12%">التاريخ</th>
                                        <th width="8%">المرجع</th>
                                        <th width="35%">العملية</th>
                                        <th width="15%" class="text-center">المبلغ</th>
                                        <th width="13%" class="text-center">الرصيد</th>
                                        <th width="8%" class="text-center">النوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accountMovements as $movement)
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-dark fw-bold">
                                                        {{ \Carbon\Carbon::parse($movement['date'])->format('Y-m-d') }}
                                                    </span>
                                                    <small class="text-muted">
                                                        {{ \Carbon\Carbon::parse($movement['date'])->format('H:i') }}
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                @if(isset($movement['reference_number']) && $movement['reference_number'])
                                                    <span class="badge bg-secondary">
                                                        {{ $movement['reference_number'] }}
                                                    </span>
                                                @elseif(isset($movement['journalEntry']) && $movement['journalEntry'])
                                                    <span class="badge bg-info">
                                                        {{ $movement['journalEntry'] }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">--</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong>{{ $movement['operation'] }}</strong>
                                                    @if(isset($movement['invoice']) && $movement['invoice'])
                                                        <small class="text-muted">
                                                            <i class="fa fa-file-invoice me-1"></i>
                                                            فاتورة: {{ $movement['invoice']->code ?? $movement['invoice']->id }}
                                                        </small>
                                                    @endif
                                                    @if(isset($movement['client']) && $movement['client'])
                                                        <small class="text-muted">
                                                            <i class="fa fa-user me-1"></i>
                                                            {{ $movement['client']->name }}
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @if ($movement['deposit'] > 0)
                                                    <span class="amount-deposit fw-bold">
                                                        +{{ number_format($movement['deposit'], 2) }}
                                                    </span>
                                                @elseif ($movement['withdraw'] > 0)
                                                    <span class="amount-withdraw fw-bold">
                                                        -{{ number_format($movement['withdraw'], 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">0.00</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="{{ $movement['balance_after'] >= 0 ? 'balance-positive' : 'balance-negative' }}">
                                                    {{ number_format(abs($movement['balance_after']), 2) }}
                                                </span>
                                                <small class="text-muted d-block">
                                                    {{ $movement['balance_after'] >= 0 ? 'دائن' : 'مدين' }}
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                @switch($movement['type'])
                                                    @case('transaction')
                                                        <span class="badge bg-primary operation-badge">
                                                            <i class="fa fa-exchange-alt me-1"></i>معاملة
                                                        </span>
                                                        @break
                                                    @case('expense')
                                                        <span class="badge bg-danger operation-badge">
                                                            <i class="fa fa-arrow-down me-1"></i>مصروف
                                                        </span>
                                                        @break
                                                    @case('revenue')
                                                        <span class="badge bg-success operation-badge">
                                                            <i class="fa fa-arrow-up me-1"></i>إيراد
                                                        </span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-secondary operation-badge">
                                                            <i class="fa fa-question me-1"></i>أخرى
                                                        </span>
                                                @endswitch
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-center">المجموع</td>
                                        <td class="text-center">
                                            @php
                                                $totalDeposit = collect($accountMovements)->sum('deposit');
                                                $totalWithdraw = collect($accountMovements)->sum('withdraw');
                                                $netAmount = $totalDeposit - $totalWithdraw;
                                            @endphp
                                            <span class="{{ $netAmount >= 0 ? 'amount-deposit' : 'amount-withdraw' }}">
                                                {{ $netAmount >= 0 ? '+' : '' }}{{ number_format($netAmount, 2) }}
                                            </span>
                                        </td>
                                        <td class="text-center {{ $supplier->account->balance >= 0 ? 'balance-positive' : 'balance-negative' }}">
                                            {{ number_format(abs($supplier->account->balance ?? 0), 2) }}
                                            <small class="text-muted d-block">
                                                {{ ($supplier->account->balance ?? 0) >= 0 ? 'دائن' : 'مدين' }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info operation-badge">المستحق</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h5>لا توجد حركة على الحساب</h5>
                            <p>
                                @if(!$supplier->account)
                                    لا يوجد حساب مرتبط بهذا المورد
                                @else
                                    لم يتم تسجيل أي حركة مالية على حساب هذا المورد بعد
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                <!-- تبويب سجل النشاطات -->
                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="row mt-4">
                        <div class="col-12">
                            @if ($logs && count($logs) > 0)
                                @php
                                    $previousDate = null;
                                @endphp

                                @foreach ($logs as $date => $dayLogs)
                                    @php
                                        $currentDate = \Carbon\Carbon::parse($date);
                                        $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
                                    @endphp

                                    @if ($diffInDays > 7)
                                        <div class="timeline-date">
                                            <h4>{{ $currentDate->format('Y-m-d') }}</h4>
                                        </div>
                                    @endif

                                    <div class="timeline-day">{{ $currentDate->translatedFormat('l') }}</div>

                                    <ul class="timeline">
                                        @foreach ($dayLogs as $log)
                                            @if ($log)
                                                <li class="timeline-item">
                                                    <div class="timeline-content">
                                                        <div class="time">
                                                            <i class="far fa-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </div>
                                                        <div>
                                                            <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                            {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                            <div class="text-muted">
                                                                {{ $log->user->branch->name ?? 'غير محدّد' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>

                                    @php
                                        $previousDate = $currentDate;
                                    @endphp
                                @endforeach
                            @else
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h5>لا توجد سجلات نشاط</h5>
                                    <p>لا توجد سجلات نشاط حتى الآن!</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- /.cx-page --}}

    <!-- Modal Delete -->
    <div class="modal fade" id="modal_DELETE1" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: var(--danger-color); color: white;">
                    <h5 class="modal-title">حذف المورد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف هذا المورد؟</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>تحذير:</strong> سيؤدي حذف المورد إلى حذف جميع البيانات المرتبطة به
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form action="{{ route('SupplierManagement.destroy', $supplier->id) }}" method="POST"
                        class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">حذف نهائي</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @permission('purchases.suppliers.assign_employee')
        {{-- تعيين المورّد لموظّفٍ مسؤول — نظيرُ «تعيين موظّف» في صفحة العميل --}}
        <div class="modal fade" id="modal_assign_employee" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: var(--primary-color); color: white;">
                        <h5 class="modal-title">تعيين المورد لموظف</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="{{ route('SupplierManagement.assignEmployee', $supplier->id) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="assign_employee_id" class="form-label">الموظف المسؤول</label>
                                <select name="employee_id" id="assign_employee_id" class="form-control">
                                    <option value="">— بلا موظّف مسؤول —</option>
                                    @foreach ($assignableEmployees ?? [] as $employee)
                                        <option value="{{ $employee->id }}" @selected((int) $supplier->employee_id === (int) $employee->id)>
                                            {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="po-help">من يُنسَب إليه هذا المورّد في الشاشات والتقارير ونطاق الرؤية. القائمة على نطاقك وأقسام إعدادات المشتريات.</span>
                            </div>
                            @if ($supplier->employee)
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    المسؤول الحالي: <strong>{{ $supplier->employee->full_name }}</strong>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>حفظ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpermission

    <!-- Modal Opening Balance -->
    <div class="modal fade" id="modal_opening_balance" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: var(--success-color); color: white;">
                    <h5 class="modal-title">إضافة رصيد افتتاحي للمورد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="openingBalanceForm" method="POST" action="{{ route('SupplierManagement.updateOpeningBalance', $supplier->id) }}">
                    @csrf
                    @php
                        // القيم المحفوظة تُعرَض ليكون النموذج تعديلاً لا إدخالاً من
                        // فراغ: المقدار من حساب المورد. الاتجاه لم يعد يُسأل هنا
                        // (انظر التعليق في المتحكّم) — دائنٌ دائماً كالعميل تماماً
                        // بالعكس: هو مدينٌ دائماً.
                        $obAccount = $supplier->account;
                        $obAmount = (float) ($obAccount->opening_balance ?? 0);
                        $obDate = optional($supplier->opening_balance_date)->format('Y-m-d');
                    @endphp
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="opening_balance" class="form-label">المبلغ ({!! $account_setting->currency ?? 'SAR' !!})</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="opening_balance"
                                name="opening_balance" required placeholder="أدخل المبلغ الافتتاحي"
                                value="{{ old('opening_balance', $obAmount ?: '') }}">
                        </div>

                        <div class="mb-3">
                            <label for="opening_balance_date" class="form-label">تاريخ الرصيد</label>
                            <input type="date" class="form-control" id="opening_balance_date"
                                name="opening_balance_date" value="{{ old('opening_balance_date', $obDate) }}">
                            <span class="po-help">تاريخ بداية التعامل. تاريخٌ داخل فترة محاسبية مقفلة يُرَدّ.</span>
                        </div>

                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            يُكتب بهذا قيدٌ في اليومية على حساب المورد ومقابله «حساب الأرصدة الافتتاحية».
                            وحفظُه مرّةً أخرى يستبدل القيد السابق ولا يُضيف إليه، ومبلغُ صفرٍ يحذفه.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>{{-- /.sl-scope --}}
@endsection

@section('scripts')
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
        // دالة تغيير حالة المورد
        // إعداد «منع المستندات على مورد موقوف» (PurchaseSettings::enabled) —
        // يُحقَن هنا فيُحذَّر المستخدم عند الإيقاف قبل أن يُفاجَأ لاحقاً برفض
        // فاتورة له (انظر SupplierGuard::assertActive)
        const blockInactiveSetting = @json(\Modules\Purchases\Services\PurchaseSettings::enabled('supplier_block_inactive'));

        function changeStatus(supplierId, newStatus, supplierName) {
            const statusText = newStatus == 1 ? 'تفعيل' : 'إيقاف';
            const statusColor = newStatus == 1 ? '#28a745' : '#dc3545';
            const icon = newStatus == 1 ? 'success' : 'warning';
            const showBlockWarning = newStatus == 0 && blockInactiveSetting;

            Swal.fire({
                title: `${statusText} المورد`,
                html: `هل أنت متأكد من <strong>${statusText}</strong> المورد <br><strong>"${supplierName}"</strong>؟` +
                    (showBlockWarning ? `<br><br><span class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ __('purchase_cycle.supplier.inactive_block_warning') }}</span>` : ''),
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: statusColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `نعم، ${statusText}`,
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // عرض مؤشر التحميل
                    Swal.fire({
                        title: 'جاري التحديث...',
                        text: 'يرجى الانتظار',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // إرسال طلب AJAX
                    $.ajax({
                        url: `/SupplierManagement/${supplierId}/update-status`,
                        type: 'POST',
                        data: {
                            status: newStatus,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'تم بنجاح!',
                                    text: response.message,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // تحديث الصفحة
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'خطأ!',
                                    text: response.message || 'حدث خطأ أثناء تحديث الحالة',
                                    icon: 'error',
                                    confirmButtonText: 'موافق'
                                });
                            }
                        },
                        error: function(xhr) {
                            let errorMessage = 'حدث خطأ غير متوقع';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }

                            Swal.fire({
                                title: 'خطأ!',
                                text: errorMessage,
                                icon: 'error',
                                confirmButtonText: 'موافق'
                            });
                        }
                    });
                }
            });
        }

        // معالجة إرسال نموذج الرصيد الافتتاحي
        $('#openingBalanceForm').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const supplierName = "{{ $supplier->trade_name }}";
            const amount = $('#opening_balance').val();

            Swal.fire({
                title: 'تأكيد إضافة الرصيد',
                html: `هل أنت متأكد من إضافة رصيد افتتاحي بقيمة <strong>${amount} SAR</strong> للمورد <strong>"${supplierName}"</strong>؟`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، إضافة',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-success me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // إرسال النموذج مباشرة
                    form[0].submit();
                }
            });
        });

        // تفعيل الترقيم للتبويبات
        $(document).ready(function() {
            // معالجة تغيير التبويبات
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                var targetTab = $(e.target).attr("href");

                // إذا كان التبويب النشط هو الفواتير أو المدفوعات، تحديث الترقيم
                if (targetTab === '#invoices' || targetTab === '#payments') {
                    // يمكن إضافة كود AJAX هنا لتحديث البيانات إذا لزم الأمر
                }
            });

            // عرض رسائل النجاح والخطأ
            @if (session('success'))
                Swal.fire({
                    title: 'تم بنجاح!',
                    text: '{{ session('success') }}',
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    title: 'خطأ!',
                    text: '{{ session('error') }}',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            @endif

            // معالجة التوست
            @if (session('toast_message'))
                toastr.{{ session('toast_type', 'success') }}('{{ session('toast_message') }}', '', {
                    positionClass: 'toast-bottom-left',
                    closeButton: true,
                    progressBar: true,
                    timeOut: 5000,
                    progressBar: true
                });
            @endif
        });
    </script>
@endsection
