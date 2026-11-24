<!DOCTYPE html>
<html class="loading" lang="ar" data-textdirection="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="نظام نقاط البيع المتطور">
    <meta name="author" content="POS System">
    <title>نظام نقاط البيع</title>

    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <link rel="apple-touch-icon" href="{{asset('app-assets/images/ico/apple-icon-120.png')}}">
    <link rel="shortcut icon" type="image/x-icon" href="{{asset('app-assets/images/ico/favicon.ico')}}">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
 
    <!-- BEGIN: Vendor CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/vendors-rtl.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/extensions/nouislider.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/vendors/css/forms/select/select2.min.css')}}">
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/bootstrap.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/bootstrap-extended.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/colors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/components.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/themes/dark-layout.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/themes/semi-dark-layout.css')}}">

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/core/menu/menu-types/vertical-menu.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/core/colors/palette-gradient.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/plugins/extensions/noui-slider.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('app-assets/css-rtl/pages/app-ecommerce-shop.css')}}">
    <!-- END: Page CSS-->

    <!-- Custom Fonts -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/Cairo/stylesheet.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#7367f0">
    
    {{--
        الطبقاتُ العائمة أوّلاً (قوائمُ ونوافذ)، ثم الشاشةُ نفسُها.
        والترتيبُ مقصود: `pos-terminal` يحمل الرموزَ ويحسم الشاشة.
    --}}
    <link rel="stylesheet" href="{{ asset('css/pos-sales-start.css') }}?v={{ filemtime(public_path('css/pos-sales-start.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pos-terminal.css') }}?v={{ filemtime(public_path('css/pos-terminal.css')) }}">
    

</head>

<body class="vertical-layout vertical-menu-modern 2-columns navbar-floating footer-static menu-collapsed" 
      data-open="click" data-menu="vertical-menu-modern" data-col="2-columns">

    <!-- BEGIN: Content-->
    <div class="app-content">
        <div class="content-wrapper">
            
            <!-- الجانب الأيسر - المنتجات والتصنيفات -->
            <div class="left-card">
                <div class="card">
                    <div class="card-content">
                        <!-- شريط الأدوات العلوي -->
                        <nav class="header-navbar navbar-expand-lg navbar navbar-with-menu navbar-light navbar-shadow">
                            <div class="navbar-wrapper">
                                <div class="navbar-container content">
                                    <div class="navbar-collapse">
                                        <ul class="nav navbar-nav bookmark-icons">
                                            <!--<li class="nav-item">-->
                                            <!--    <a class="nav-link" href="#" title="الطباعة (F4)">-->
                                            <!--        <i class="fas fa-print"></i>-->
                                            <!--        <span class="keyboard-shortcut">F4</span>-->
                                            <!--    </a>-->
                                            <!--</li>-->
                                            <li class="nav-item">
                                                <a class="nav-link" href="#" title="عرض كامل (F11)" onclick="toggleFullscreen()">
                                                    <i class="fas fa-expand"></i>
                                                    <span class="keyboard-shortcut">F11</span>
                                                </a>
                                            </li>

                                            <li class="nav-item">
                                                <a href="{{ url('/') }}" target="_blank" 
                                                   class="nav-link" title="الصفحة الرئيسية (F12)">
                                                    <i class="fas fa-home"></i>
                                                    <span class="keyboard-shortcut">F12</span>
                                                </a>
                                            </li>
                                                                                        <!-- PWA Install Button -->
                                            <li class="nav-item search-button-wrapper" id="pwaInstallBtnContainer">
                                                <a class="nav-link" href="#" id="pwaInstallBtn" title="تثبيت التطبيق">
                                                    <i class="fas fa-download"></i>
                                                    <span class="d-none d-md-inline" style="margin-right: 5px; font-weight: bold;">تثبيت التطبيق</span>
                                                </a>
                                            </li>
                                        </ul>

                                        <ul class="nav navbar-nav">
                                            <li class="dropdown dropdown-user nav-item">
                                                <a class="dropdown-toggle nav-link dropdown-user-link" href="#" data-toggle="dropdown">
                                                    <div class="user-nav d-sm-flex d-none">
                                                        <span class="user-name text-bold-600 text-white">
                                                            {{ auth()->user()->name ?? 'المستخدم' }}
                                                        </span>
                                                        <span class="user-status">
                                                            <span class="text-white" id="currentDateTime"></span>
                                                        </span>
                                                    </div>
                                                    <span>
                                                        <img class="round" src="{{asset('app-assets/images/portrait/small/avatar-s-13.jpg')}}" 
                                                             alt="avatar" height="35" width="35">
                                                    </span>
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#"><i class="feather icon-user"></i> الملف الشخصي</a>
                                                    <a class="dropdown-item" href="#"><i class="feather icon-settings"></i> الإعدادات</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="feather icon-power"></i> تسجيل الخروج</a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </nav>

                        <!-- منطقة البحث والأزرار -->
                        <div class="search-section">
                            <div class="search-row">
                                <div class="search-buttons">
                                    {{-- تطبيق إعداد تفعيل أزرار الأقسام --}}
                                    @if($posSettings->enable_departments ?? true)
                                    <button class="btn btn-search active" id="categoriesBtn" title="التصنيفات (F1)">
                                        <i class="fas fa-th-large"></i>
                                        <span class="d-none d-md-inline">التصنيفات</span>
                                        <span class="keyboard-shortcut">F1</span>
                                    </button>
                                    @endif
                                    
                                    <button class="btn btn-search {{ !($posSettings->enable_departments ?? true) ? 'active' : '' }}" id="productsBtn" title="المنتجات (F2)">
                                        <i class="fas fa-box"></i>
                                        <span class="d-none d-md-inline">المنتجات</span>
                                        <span class="keyboard-shortcut">F2</span>
                                    </button>
                                    
                                    <button class="btn btn-search" id="invoicesBtn" title="الفواتير (F3)">
                                        <i class="fas fa-file-invoice"></i>
                                        <span class="d-none d-md-inline">الفواتير</span>
                                        <span class="keyboard-shortcut">F4</span>
                                    </button>
                                </div>
                                
                                <input type="text" class="form-control search-input" id="searchInput" 
                                       placeholder="ابحث هنا..." autocomplete="off">
                            </div>
                        </div>

                        <!-- منطقة المحتوى الرئيسية -->
                        <div class="content-body">
                            
                            {{-- تطبيق إعداد تفعيل الأقسام - إخفاء قسم التصنيفات إذا كان الإعداد معطلاً --}}
                            @if($posSettings->enable_departments ?? true)
                            <!-- التصنيفات -->
                            <div id="categoriesSection" class="content-section">
                                <div class="categories-grid" id="categoriesGrid">
                                    @foreach ($categories as $category)
                                        @php
                                            $categoryId = is_array($category) ? $category['id'] : $category->id;
                                            $categoryName = is_array($category) ? $category['name'] : $category->name;
                                            $categoryImage = is_array($category) ? ($category['image'] ?? null) : ($category->attachments ?? null);
                                        @endphp
                                        <div class="category-card fade-in" data-category-id="{{ $categoryId }}">
                                            @if($categoryImage)
                                                <img src="{{ $categoryImage }}" 
                                                     alt="{{ $categoryName }}" class="category-image"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="category-image category-image__fallback d-none align-items-center justify-content-center">
                                                    <i class="fas fa-tag"></i>
                                                </div>
                                            @else
                                                <div class="category-image category-image__fallback d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-tag"></i>
                                                </div>
                                            @endif
                                            <h6 class="text-center mb-0 mt-2">{{ $categoryName }}</h6>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <!-- المنتجات -->
                            <div id="productsSection" class="content-section" style="display: {{ ($posSettings->enable_departments ?? true) ? 'none' : 'block' }};">
                                <div class="products-grid" id="productsGrid">
                                    <!-- سيتم ملؤها بـ JavaScript -->
                                </div>
                            </div>

                            <!-- الفواتير -->
                            <div id="invoicesSection" class="content-section" style="display: none;">
                                <div id="invoicesContainer">
                                     سيتم ملؤها بـ JavaScript 
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- الجانب الأيمن - منطقة الطلبات -->
            <div class="right-card">
                <div class="card">
                    <div class="card-content">
                        
                        <!-- رأس منطقة الطلبات -->
                        <div class="order-header">
                            <div class="order-tabs" id="orderTabs">
                                <button class="order-tab active" data-order="1">#1</button>
                                <button class="order-tab" onclick="addNewOrder()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            
                            <!-- قائمة اختيار العميل المنسدلة -->
                            <div class="client-selector-wrapper">
                                <div class="client-selector" id="clientSelector" title="اختيار العميل">
                                    <i class="fas fa-user"></i>
                                    <span id="selectedClientName">{{ $defaultClient->trade_name ?? 'اختر العميل' }}</span>
                                    <i class="fas fa-chevron-down ms-auto"></i>
                                </div>
                                
                                <!-- القائمة المنسدلة -->
                                <div class="client-dropdown" id="clientDropdown">
                                    <div class="client-search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="clientSearchInput" 
                                               placeholder="ابحث بالاسم أو الكود..." 
                                               autocomplete="off">
                                    </div>
                                    <div class="client-results" id="clientResults">
                                        @if($defaultClient)
                                        <div class="client-result-item active" data-client-id="{{ $defaultClient->id }}" 
                                             data-client-name="{{ $defaultClient->trade_name }}"
                                             data-client-phone="{{ $defaultClient->phone }}"
                                             data-client-code="{{ $defaultClient->code }}">
                                            <div class="client-info">
                                                <span class="client-name">{{ $defaultClient->trade_name }}</span>
                                                <span class="client-code">{{ $defaultClient->code }}</span>
                                            </div>
                                            <span class="client-phone">{{ $defaultClient->phone }}</span>
                                        </div>
                                        @endif
                                        <div class="client-loading" style="display: none;">
                                            <div class="spinner"></div>
                                            <span>جاري البحث...</span>
                                        </div>
                                        <div class="client-no-results" style="display: none;">
                                            <i class="fas fa-user-slash"></i>
                                            <span>لا توجد نتائج</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- محتوى الطلب -->
                        <div class="order-content" id="orderContent">
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>لا توجد عناصر في الطلب</p>
                                <small>اختر المنتجات لإضافتها للطلب</small>
                            </div>
                        </div>

                        <!-- ملخص الطلب -->
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>عدد العناصر:</span>
                                <span id="totalItems">0</span>
                            </div>
                            <div class="summary-row">
                                <span>المجموع الفرعي:</span>
                                <span id="subtotalAmount">0.00 ر.س</span>
                            </div>
                            
                            @can('points_sale_add_discount')
                            <div class="discount-section">
                                <label class="form-label fw-bold" style="font-size: 0.9rem;">
                                    <i class="fas fa-percent me-1"></i>الخصم:
                                </label>
                                <div class="discount-controls">
                                    <select class="discount-type-select" id="discountType">
                                        <option value="amount">مبلغ</option>
                                        <option value="percentage">نسبة %</option>
                                    </select>
                                    <input type="number" class="discount-input" id="discountValue" 
                                           value="0" min="0" step="0.01" placeholder="0">
                                </div>
                            </div>
                            @endcan
                            
                            <!-- قسم اختيار الضريبة -->
                            <div class="tax-section" style="margin-top: 10px; padding: 8px; background: rgba(255,193,7,0.1); border-radius: 8px;">
                                <label class="form-label fw-bold" style="font-size: 0.9rem; color: #ffc107;">
                                    <i class="fas fa-calculator me-1"></i>الضريبة:
                                </label>
                                <div class="tax-controls" style="display: flex; gap: 8px; align-items: center;">
                                    <select class="form-control" id="taxType" style="flex: 1; padding: 6px 10px; border-radius: 6px; border: 1px solid #ddd; font-size: 0.85rem;">
                                        {{--
                                            النسبةُ مئويةً لا كسراً.

                                            العمودُ مخزَّنٌ كسراً (٠٫١٥ تعني ١٥٪)، وكان
                                            يُطبع ويُرسَل كما هو — فتقرأ الشاشةُ «٠٫١٥٪»
                                            وتحسب على أساسها، فتخرج الضريبةُ جزءاً من
                                            مئةٍ من الصحيح.

                                            و`TaxCalculator::fraction` تقبل الصيغتين، فلا
                                            تنكسر بياناتٌ أُدخلت بأيّهما.

                                            و`data-type` لم يكن يُرسَل أصلاً: «الشاملة»
                                            تُحسب مضافةً.
                                        --}}
                                        @foreach($taxSettings ?? [] as $tax)
                                            @php $pct = \App\Support\TaxCalculator::fraction($tax->tax) * 100; @endphp
                                            <option value="{{ $tax->id }}"
                                                    data-rate="{{ rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') }}"
                                                    data-type="{{ $tax->type ?: 'excluded' }}"
                                                    {{ ($defaultTaxId == $tax->id) ? 'selected' : '' }}>
                                                {{ $tax->name }}
                                                ({{ rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') }}%{{ ($tax->type ?? '') === 'included' ? ' شاملة' : '' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <span id="taxRateDisplay" class="badge bg-warning text-dark" style="font-size: 0.85rem; padding: 6px 10px;">
{{ rtrim(rtrim(number_format(\App\Support\TaxCalculator::fraction($taxSettings->where('id', $defaultTaxId)->first()?->tax ?? 0) * 100, 2, '.', ''), '0'), '.') }}%
                                    </span>
                                </div>
                            </div>
                            
                            <!-- عرض تفصيل الضريبة -->
                            <div class="summary-row tax-display" style="color: #ffc107; font-size: 0.9rem;">
                                <span><i class="fas fa-receipt me-1"></i>الضريبة:</span>
                                <span id="taxAmount">0.00 ر.س</span>
                            </div>
                            
                            <div class="summary-row total">
                                <span>الإجمالي النهائي:</span>
                                <span id="finalTotal">0.00 ر.س</span>
                            </div>
                            
   <div class="action-buttons">
    {{--
        زرُّ التعليق يحتاج `pos.orders.hold`، والاستردادُ `pos.returns.create`.

        كانا يُعرضان للجميع، والمسارُ يردّ من لا يملكهما بـ403 بعد أن يبني
        الكاشيرُ سلّته ويضغط — فيفقد ما أدخله بلا سببٍ مفهوم.
    --}}
    @permission('pos.orders.hold')
        <button class="action-btn btn-hold" id="holdOrderBtn" title="تعليق الطلب (F5)">
            <i class="fas fa-pause"></i>
            <span>تعليق</span>
            <span class="keyboard-shortcut">F5</span>
        </button>
    @endpermission

    @permission('pos.returns.create')
        <button class="action-btn btn-return" id="returnBtn" title="استرداد (F7)">
            <i class="fas fa-undo"></i>
            <span>استرداد</span>
            <span class="keyboard-shortcut">F7</span>
        </button>
    @endpermission

    <button class="action-btn btn-pay" id="payOrderBtn" title="الدفع (F6)">
        <i class="fas fa-credit-card"></i>
        <span>دفع</span>
        <span class="keyboard-shortcut">F6</span>
    </button>
    
</div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- قالب صف طريقة الدفع -->
    <template id="paymentMethodTemplate">
        <div class="payment-method-row">
            <select class="form-control payment-method-select">
                @if(isset($paymentMethods) && count($paymentMethods) > 0)
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" data-is-online="{{ $method->is_online ? 'true' : 'false' }}">
                            {{ $method->name }}
                        </option>
                    @endforeach
                @endif
            </select>
            <div class="input-group">
                <input type="number" class="form-control payment-amount-input"
                       value="0" min="0" step="0.01" placeholder="0.00">
                <span class="input-group-text">{{ $currencySymbol ?? 'ر.س' }}</span>
            </div>
            <button type="button" class="remove-payment-method" title="حذف">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </template>

    <!-- Modal نافذة الدفع -->
    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content pos-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">
                        <i class="fas fa-credit-card me-2"></i>إتمام عملية الدفع
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <!-- المبلغ الإجمالي مع حالة التحقق -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                {{--
                                    المستحقُّ هو السؤال، فيُعرض رقماً كبيراً لا سطراً في
                                    تنبيهٍ أزرق. والحالةُ بجانبه: مكتملٌ أو ناقص.
                                --}}
                                <div class="pay-due">
                                    <span class="pay-due__label">المبلغ المستحق</span>
                                    <strong class="pay-due__value" id="modalTotalAmount">0.00</strong>
                                    <span id="paymentValidationBadge" class="pay-state pay-state--short">غير مكتمل</span>
                                </div>
                            </div>
                        </div>
                        
                        @if(isset($paymentMethods) && count($paymentMethods) > 0)
                            <!-- أزرار الدفع السريع -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="pay-label">دفع سريع بطريقة واحدة</label>

                                    {{--
                                        الطريقةُ الافتراضية أوّلاً ومميَّزة.

                                        كانت الأزرارُ متساويةً في الشكل، وترتيبُها ترتيبَ
                                        الإعدادات — فلا يُعرف أيُّها المعتاد. والكاشيرُ
                                        يضغط الزرَّ نفسَه مئةَ مرّةٍ في اليوم، فحقُّه أن
                                        يكون أوّلَ ما تقع عليه العين.
                                    --}}
                                    <div class="pay-quick">
                                        @foreach($paymentMethods->sortByDesc(fn ($m) => $m->id == $defaultPaymentMethodId) as $method)
                                            @php $isDefault = $method->id == $defaultPaymentMethodId; @endphp
                                            <button type="button"
                                                    class="quick-pay-btn{{ $isDefault ? ' quick-pay-btn--default' : '' }}"
                                                    data-method-id="{{ $method->id }}"
                                                    data-method-name="{{ $method->name }}"
                                                    data-is-online="{{ $method->is_online ? 'true' : 'false' }}">
                                                {{ $method->name }}
                                                @if ($isDefault)
                                                    <span class="quick-pay-btn__tag">الافتراضي</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- قائمة طرق الدفع المتعددة -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="pay-label mb-0">طرق الدفع المتعددة</label>
                                        <div class="pay-tools">
                                            <button type="button" class="pay-tool" id="resetPaymentsBtn">
                                                <i class="fas fa-redo"></i> إعادة تعيين
                                            </button>
                                            <button type="button" class="pay-tool pay-tool--go" id="addPaymentMethodBtn">
                                                <i class="fas fa-plus"></i> إضافة طريقة
                                            </button>
                                        </div>
                                    </div>
                                    <div id="paymentMethodsList">
                                        <!-- ستضاف طرق الدفع هنا ديناميكياً -->
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        لا توجد طرق دفع متاحة حالياً. يرجى إضافة طرق الدفع من الإعدادات.
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        <!-- ملخص الدفع -->
                        <div class="row mt-4">
                            {{--
                                ثلاثةُ أرقامٍ كانت في بطاقاتٍ ملوّنةٍ بالتساوي — أخضرَ
                                ووردياً وأزرق — فتتساوى في الإلحاح ولا يُعرف أيُّها
                                يستحقّ النظر.

                                وأهمُّها **المتبقّي**: هو وحدَه ما يمنع التأكيد. فيُلوَّن
                                حين يكون ويهدأ حين يصفر. والباقي للعميل كذلك: لا معنى
                                لتلوينه وهو صفر.
                            --}}
                            <div class="col-12">
                                <div class="pay-sums">
                                    <div class="pay-sum">
                                        <span class="pay-sum__label">إجمالي المدفوع</span>
                                        <span class="pay-sum__value" id="totalPaidAmount">0.00</span>
                                    </div>
                                    <div class="pay-sum pay-sum--remaining">
                                        <span class="pay-sum__label">المتبقي</span>
                                        <span class="pay-sum__value" id="remainingAmount">0.00</span>
                                    </div>
                                    <div class="pay-sum pay-sum--change">
                                        <span class="pay-sum__label">الباقي للعميل</span>
                                        <span class="pay-sum__value" id="changeAmount">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times me-2"></i>إلغاء
                    </button>
                    <button type="button" class="btn btn-success" id="confirmPaymentBtn" style="background: var(--success-color);">
                        <i class="fas fa-check me-2"></i>تأكيد الدفع
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Modal نافذة الاسترداد -->
<div class="modal fade" id="returnModal" tabindex="-1" role="dialog" aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content" style="border-radius: var(--border-radius); border: none;">
            <div class="modal-header" style="background: #dc3545; color: white;">
                <h5 class="modal-title" id="returnModalLabel">
                    <i class="fas fa-undo me-2"></i>استرداد المنتجات
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <!-- قائمة المنتجات للاسترداد -->
                <div class="row">
                    <div class="col-md-8">
                        <h6>المنتجات المتاحة للاسترداد:</h6>
                        <div id="returnItemsList" class="mb-3">
                            <!-- سيتم ملؤها ديناميكياً -->
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="return-summary" style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <h6>ملخص الاسترداد</h6>
                            <div class="summary-row">
                                <span>عدد العناصر:</span>
                                <span id="returnTotalItems">0</span>
                            </div>
                            <div class="summary-row">
                                <span>المجموع الفرعي:</span>
                                <span id="returnSubtotalAmount">0.00 ر.س</span>
                            </div>
                            <div class="summary-row">
                                <span>ضريبة القيمة المضافة (15%):</span>
                                <span id="returnTaxAmount">0.00 ر.س</span>
                            </div>
                            <div class="summary-row total">
                                <span>إجمالي الاسترداد:</span>
                                <span id="returnFinalTotal">0.00 ر.س</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 2px solid #dc3545;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times me-2"></i>إلغاء
                </button>
                <button type="button" class="btn btn-danger" id="confirmReturnBtn">
                    <i class="fas fa-check me-2"></i>تأكيد الاسترداد
                </button>
            </div>
        </div>
    </div>
</div>
    <!-- BEGIN: Vendor JS-->
    <script src="{{asset('app-assets/vendors/js/vendors.min.js')}}"></script>
    <script src="{{asset('app-assets/vendors/js/ui/prism.min.js')}}"></script>
    <script src="{{asset('app-assets/vendors/js/extensions/wNumb.js')}}"></script>
    <script src="{{asset('app-assets/vendors/js/extensions/nouislider.min.js')}}"></script>
    <script src="{{asset('app-assets/vendors/js/forms/select/select2.full.min.js')}}"></script>
    <!-- END: Vendor JS-->

    <!-- BEGIN: Theme JS-->
    <script src="{{asset('app-assets/js/core/app-menu.js')}}?v=20260923.1"></script>
    <script src="{{asset('app-assets/js/core/app.js')}}?v=20260923.1"></script>
    <script src="{{asset('app-assets/js/scripts/components.js')}}"></script>
    <!-- END: Theme JS-->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // بيانات التطبيق من Laravel
        const appData = {
            categories: @json($categories ?? []),
            products: @json($products ?? []),
            paymentMethods: @json($paymentMethods ?? []),
            defaultCustomerId: @json($defaultCustomerId ?? null),
            defaultClient: @json($defaultClient ?? null),
            defaultPaymentMethodId: @json($defaultPaymentMethodId ?? null),
            // الضرائب المتاحة
            taxSettings: @json($taxSettings ?? []),
            defaultTaxId: @json($defaultTaxId ?? null),
            // إعدادات POS الكاملة
            settings: {
                showProductImages: @json($posSettings->show_product_images ?? true),
                showPrintWindowAfterConfirm: @json($posSettings->show_print_window_after_confirm ?? true),
                enableDepartments: @json($posSettings->enable_departments ?? false),
                {{--
                    `applyCustomFieldsValidation` حُذف: كان يُمرَّر إلى JS ولا
                    يُقرأ في أيّ سطر، ولا وجودَ لنظام حقولٍ مخصّصة في المشروع
                    أصلاً (لا جدول `custom_fields` ولا نموذجَ له). مفتاحٌ
                    يُطفئه المستخدمُ ويُشعله فلا يتغيّر شيء.
                --}}
                accountingSystemPerInvoice: @json($posSettings->accounting_system_per_invoice ?? false),
                enableAutoSettlement: @json($posSettings->enable_auto_settlement ?? false),
                enableSalesSettlement: @json($posSettings->enable_sales_settlement ?? false),
                invoiceTemplate: @json($posSettings->invoice_template ?? 'default'),
            },
            // رمزُ العملة — كان «ر.س» مكتوبةً في ستٍّ وخمسين موضعاً من الشيفرة
            currencySymbol: @json($currencySymbol ?? 'ر.س'),

            /*
                أيجوز صرفُ قيمة المرتجع من الدرج؟ إذنُ الفرع وصلاحيةُ
                المستخدم معاً — تُقرأ هنا مرّةً ليُعرض الزرُّ أو يُخفى، بدل
                أن يضغطه الكاشيرُ فيُردّ.
            */
            canCashRefund: @json($canCashRefund ?? false),

            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            printUrlTemplate: "",
            // URLs مع locale prefix الصحيح
            urls: {
                productsByCategory: "{{ route('pos.products_by_category') }}",
                search: "{{ route('pos.search') }}",
                searchClients: "{{ route('pos.search_clients') }}",
                productByBarcode: "{{ route('pos.product_by_barcode') }}",
                quickSearch: "{{ route('pos.quick_search_products') }}",
                store: "{{ route('sales_start.store') }}",
                /*
                    ═══════════ العناوينُ من المُوجِّه لا مبنيّةً باليد ═══════════

                    كانت سبعتُها `url('/'.locale.'/POS/…')` — **وبادئةُ اللغة
                    خطأ**: مسارُ الطباعة مسجَّلٌ `POS/return_invoices/{id}/print`
                    بلا بادئة. فيفتح المتصفّح `/ar/POS/return_invoices/356/print`
                    فيرجع **404**، والنافذةُ تخرج فارغةً بلا رسالة.

                    وقد ظلّ خفيّاً لأنّ ما يُفتح في تبويبٍ جديدٍ لا يُرى فشلُه
                    في الشاشة الأمّ.

                    والمعرّفُ نائبٌ يُستبدَل في الشيفرة — فتغييرُ تعريف المسار
                    يسري هنا بلا تعديل.
                */
                invoiceDetails: "{{ route('pos.invoices.details', ['id' => '__ID__']) }}",
                invoiceDetailsApi: "{{ route('pos.invoices.details', ['id' => '__ID__']) }}",
                printInvoice: "{{ route('POS.invoices.print', ['id' => '__ID__']) }}",
                returnPrint: "{{ route('pos.returns.print', ['id' => '__ID__']) }}",
                processReturn: "{{ route('pos.returns.process') }}",
                availableInvoicesForReturn: "{{ route('pos.returns.available') }}",
                invoiceDetailsForReturn: "{{ route('pos.returns.details', ['id' => '__ID__']) }}",

                // كان مكتوباً داخل الشيفرة، ولا مكانَ لـBlade في ملفٍّ ساكن
                serviceWorker: "{{ asset('sw.js') }}"
            }
        };
    </script>

    {{--
        ═══════════ الشيفرةُ خرجت إلى ملفاتها ═══════════

        كانت ثلاثةَ آلافٍ وخمسَمئةِ سطرٍ داخل `<script>` في هذا القالب —
        مئةُ دالّةٍ متجاورةٍ بلا حدود. وثلاثةُ عيوبٍ لازمتها:

          • **لا تُخزَّن في المتصفّح**: مئةٌ وثلاثون كيلوبايت تُنزَّل وتُحلَّل
            مع كل فتحةٍ للشاشة، لأنها جزءٌ من HTML لا ملفٌّ له عنوان.
            والكاشيرُ يفتح هذه الشاشة عشراتِ المرّات في اليوم.
          • **لا تُقرأ**: دالّةُ الفواتير بين دالّتَي المرتجع.
          • **ولا يُؤمَن تكرارُها**: تعريفان باسمٍ واحدٍ لا يُلحظان،
            والمتأخّرُ يغلب صامتاً — وهو ما كان يحدث فعلاً.

        و`appData` وحدها تبقى هنا: مساراتٌ وإعداداتٌ وأوّلُ المنتجات، تتغيّر
        بتغيّر الطلب. أمّا المنطقُ فثابت.

        **والترتيبُ مقصود**: `state` أوّلاً لأنّ مراجعَ العناصر فيه تُقرأ عند
        التحميل لا عند النداء، و`boot` أخيراً لأنه يربط ما سبق. وما بينهما
        تعريفاتُ دوالَّ تُرفَع، فترتيبُها لا يعني شيئاً.
    --}}

    @php
        /*
            ختمُ الإصدار من زمن تعديل الملف.

            الملفاتُ الساكنة يخزّنها المتصفّح — وهو المقصود من إخراجها.
            لكنّ نشرةً جديدةً تُبقي القديمَ في ذاكرته، فيعمل الكاشيرُ
            بشيفرةٍ سابقة ولا يدري. و`filemtime` يجعل العنوانَ يتبدّل مع
            الملف وحده: يتغيّر حين يتغيّر، ويثبت حين يثبت.
        */
        $posAsset = function (string $file) {
            $path = public_path('js/pos/'.$file);

            return asset('js/pos/'.$file).'?v='.(is_file($path) ? filemtime($path) : 0);
        };
    @endphp

    <script src="{{ $posAsset('state.js') }}"></script>
    <script src="{{ $posAsset('barcode.js') }}"></script>
    <script src="{{ $posAsset('client.js') }}"></script>
    <script src="{{ $posAsset('catalog.js') }}"></script>
    <script src="{{ $posAsset('cart.js') }}"></script>
    <script src="{{ $posAsset('payment.js') }}"></script>
    <script src="{{ $posAsset('invoices.js') }}"></script>
    <script src="{{ $posAsset('returns.js') }}"></script>
    <script src="{{ $posAsset('ui.js') }}"></script>
    <script src="{{ $posAsset('boot.js') }}"></script>

</body>
</html>