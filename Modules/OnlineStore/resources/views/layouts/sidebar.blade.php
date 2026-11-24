@php
    use App\Constants\planFeatures\AccountPlanFeatures;
    use App\Constants\planFeatures\OnlineStorePlanFeatures;
    use App\Constants\planFeatures\PurchasesPlanFeatures;
    use App\Constants\planFeatures\StockPlanFeatures;
    use App\Support\PlanLock;

    $getLocal = App::getLocale();
    // جلب جميع الإعدادات من جدول application_settings
    if (!isset($settings)) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }
@endphp

{{-- قائمةُ المتجر وحدَها لكل مستخدم، كالعملاء والمبيعات. كان هنا
     `@if (isEmployee()) @include('layouts.sidebar')` فيرى كلُّ من له
     سجلُّ موظف القائمةَ الموحَّدة بدل قائمة المتجر. القائمةُ الموحَّدة
     تأتي الآن من مفتاح الدور وحده (App\Support\UnifiedSidebar). --}}
@php
    /*
        حدود باقة فوترة — قفلٌ لا إخفاء (انظر تعليق App\Support\PlanLock
        ونظيرها في Modules/Account/resources/views/layouts/sidebar.blade.php).
        صلاحية النظام (hasPermission) تُخفي البند؛ الميزة خارج الباقة تُبقيه
        ظاهراً مقفولاً يقود إلى شاشة الاشتراك.
    */
    $planLockUrl = PlanLock::url();

    $planKeys = [
        'orders' => OnlineStorePlanFeatures::ORDERS,
        'clients' => OnlineStorePlanFeatures::CLIENTS,
        'suppliers' => PurchasesPlanFeatures::SUPPLIERS,
        'products' => OnlineStorePlanFeatures::PRODUCTS,
        'categories' => OnlineStorePlanFeatures::CATALOG,
        'brands' => OnlineStorePlanFeatures::CATALOG,
        'reviews' => OnlineStorePlanFeatures::FEEDBACK,
        'questions' => OnlineStorePlanFeatures::FEEDBACK,
        'storehouses' => StockPlanFeatures::STOREHOUSES,
        'purchase_invoices' => PurchasesPlanFeatures::INVOICES,
        'permits' => StockPlanFeatures::WAREHOUSE_PERMITS,
        'treasury' => AccountPlanFeatures::TREASURY,
        'coupons' => OnlineStorePlanFeatures::COUPONS,
        'banners' => OnlineStorePlanFeatures::CONTENT,
        'geo' => OnlineStorePlanFeatures::SETTINGS,
        'shipping_methods' => OnlineStorePlanFeatures::SHIPPING,
        'pages' => OnlineStorePlanFeatures::CONTENT,
        'email' => OnlineStorePlanFeatures::EMAIL,
        'reports' => OnlineStorePlanFeatures::REPORTS,
        'settings' => OnlineStorePlanFeatures::SETTINGS,
    ];

    $plan = [];
    foreach ($planKeys as $slot => $feature) {
        $plan[$slot] = ! PlanLock::locked($feature);
    }

    /** سمات البند المقفول: تلميحٌ يسمّي الميزة الناقصة. */
    $planAttrs = function (string $slot) use ($plan, $planKeys) {
        return $plan[$slot] ? '' : ' title="'.e(PlanLock::reason($planKeys[$slot])).'"';
    };

    // شارة «أسئلة تنتظر الرد» — عدٌّ واحد خفيف، ولمن يرى الشاشة فقط
    $pendingQuestionsCount = 0;
    if ($plan['questions'] && hasPermission('online_store.questions.view')) {
        try {
            $pendingQuestionsCount = \App\Models\Store\ProductQuestion::pending()->count();
        } catch (\Throwable $e) {
            $pendingQuestionsCount = 0;
        }
    }
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('onlinestore.department.dashboard') }}">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">فوترة</h2>
                </a>
            </li>

            <li class="nav-item nav-toggle"><a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse"><i
                        class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i><i
                        class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"
                        data-ticon="icon-disc"></i></a></li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">

        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            {{-- إدارة الطلبات والعملاء --}}
            @if (canAny(['online_store.orders.view', 'online_store.clients.view', 'purchases.suppliers.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/orders*") || request()->is("$getLocal/online_store/clients*") || request()->is("$getLocal/online_store/suppliers*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-shopping-cart"></i>
                        <span class="menu-title" data-i18n="OrdersManagement">إدارة الطلبات</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.orders.view'))
                            <li class="{{ request()->is("$getLocal/online_store/orders*") ? 'active' : '' }} {{ $plan['orders'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['orders'] ? route('online_orders.index') : $planLockUrl }}"{!! $planAttrs('orders') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Orders">الطلبات</span>{!! $plan['orders'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.clients.view'))
                            <li class="{{ request()->is("$getLocal/online_store/clients*") ? 'active' : '' }} {{ $plan['clients'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['clients'] ? route('store_clients.index') : $planLockUrl }}"{!! $planAttrs('clients') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Clients">العملاء</span>{!! $plan['clients'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('purchases.suppliers.view'))
                            <li class="{{ request()->is("$getLocal/online_store/suppliers*") ? 'active' : '' }} {{ $plan['suppliers'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['suppliers'] ? route('store_suppliers.index') : $planLockUrl }}"{!! $planAttrs('suppliers') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Suppliers">الموردين</span>{!! $plan['suppliers'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- إدارة المنتجات --}}
            @if (canAny([
                        'online_store.products.view', 'online_store.categories.view',
                        'online_store.brands.view', 'online_store.reviews.view', 'online_store.questions.view',
                    ]))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/products*") || request()->is("$getLocal/online_store/categories*") || request()->is("$getLocal/online_store/brands*") || request()->is("$getLocal/online_store/reviews*") || request()->is("$getLocal/online_store/questions*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-package"></i>
                        <span class="menu-title" data-i18n="ProductsManagement">إدارة المنتجات</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.products.view'))
                            <li class="{{ request()->is("$getLocal/online_store/products*") ? 'active' : '' }} {{ $plan['products'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['products'] ? route('store_products.index') : $planLockUrl }}"{!! $planAttrs('products') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Products">المنتجات</span>{!! $plan['products'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.categories.view'))
                            <li class="{{ request()->is("$getLocal/online_store/categories*") ? 'active' : '' }} {{ $plan['categories'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['categories'] ? route('store_categories.index') : $planLockUrl }}"{!! $planAttrs('categories') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Categories">أصناف المنتجات</span>{!! $plan['categories'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.brands.view'))
                            <li class="{{ request()->is("$getLocal/online_store/brands*") ? 'active' : '' }} {{ $plan['brands'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['brands'] ? route('brands.index') : $planLockUrl }}"{!! $planAttrs('brands') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Brands">العلامات التجارية</span>{!! $plan['brands'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reviews.view'))
                            <li class="{{ request()->is("$getLocal/online_store/reviews*") ? 'active' : '' }} {{ $plan['reviews'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reviews'] ? route('product_reviews.index') : $planLockUrl }}"{!! $planAttrs('reviews') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Reviews">تقييمات المنتجات</span>{!! $plan['reviews'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.questions.view'))
                            <li class="{{ request()->is("$getLocal/online_store/questions*") ? 'active' : '' }} {{ $plan['questions'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['questions'] ? route('product_questions.index') : $planLockUrl }}"{!! $planAttrs('questions') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Questions">أسئلة المنتجات</span>{!! $plan['questions'] ? '' : PlanLock::menuBadge() !!}
                                    @if ($pendingQuestionsCount > 0)
                                        <span class="badge badge-pill badge-warning float-right mr-1" title="أسئلة تنتظر الرد">{{ $pendingQuestionsCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- إدارة المخزون --}}
            @if (canAny(['stock.warehouses.view', 'purchases.invoices.view', 'stock.permits.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/storehouses*") || request()->is("$getLocal/online_store/purchase-invoices*") || request()->is("$getLocal/online_store/permits*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-box"></i>
                        <span class="menu-title" data-i18n="InventoryManagement">إدارة المخزون</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('stock.warehouses.view'))
                            <li class="{{ request()->is("$getLocal/online_store/storehouses*") ? 'active' : '' }} {{ $plan['storehouses'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['storehouses'] ? route('store_storehouses.index') : $planLockUrl }}"{!! $planAttrs('storehouses') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Storehouses">المخازن</span>{!! $plan['storehouses'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('purchases.invoices.view'))
                            <li class="{{ request()->is("$getLocal/online_store/purchase-invoices*") ? 'active' : '' }} {{ $plan['purchase_invoices'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['purchase_invoices'] ? route('store_purchase_invoices.index') : $planLockUrl }}"{!! $planAttrs('purchase_invoices') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="PurchaseInvoices">فواتير الشراء</span>{!! $plan['purchase_invoices'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('stock.permits.view'))
                            <li class="{{ request()->is("$getLocal/online_store/permits*") ? 'active' : '' }} {{ $plan['permits'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['permits'] ? route('online_store.permits.index') : $planLockUrl }}"{!! $planAttrs('permits') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="WarehousePermits">الأذون المخزنية</span>{!! $plan['permits'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- الخزائن والحسابات البنكية --}}
            @if (canAny(['account.treasury.view', 'account.treasury_transfers.create', 'online_store.payment_methods.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/treasury*") || request()->is("$getLocal/online_store/payment-methods*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-dollar-sign"></i>
                        <span class="menu-title" data-i18n="TreasuryManagement">المالية</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('account.treasury.view'))
                            <li class="{{ request()->is("$getLocal/online_store/treasury") || request()->is("$getLocal/online_store/treasury?*") ? 'active' : '' }} {{ $plan['treasury'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['treasury'] ? route('store_treasury.index') : $planLockUrl }}"{!! $planAttrs('treasury') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Treasuries">الخزائن والحسابات البنكية</span>{!! $plan['treasury'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('account.treasury_transfers.create'))
                            <li class="{{ request()->is("$getLocal/online_store/treasury/transfer-create*") ? 'active' : '' }} {{ $plan['treasury'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['treasury'] ? route('store_treasury.transfer_create') : $planLockUrl }}"{!! $planAttrs('treasury') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Transfers">تحويل بين الخزائن</span>{!! $plan['treasury'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.payment_methods.view'))
                            <li class="{{ request()->is("$getLocal/online_store/payment-methods*") ? 'active' : '' }}">
                                <a href="{{ route('payment_methods.index') }}">
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="PaymentMethods">طرق الدفع</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- التسويق والعروض --}}
            @if (canAny(['online_store.coupons.view', 'online_store.banners.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/coupons*") || request()->is("$getLocal/online_store/banners*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-tag"></i>
                        <span class="menu-title" data-i18n="MarketingOffers">التسويق والعروض</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.coupons.view'))
                            <li class="{{ request()->is("$getLocal/online_store/coupons*") ? 'active' : '' }} {{ $plan['coupons'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['coupons'] ? route('store_coupons.index') : $planLockUrl }}"{!! $planAttrs('coupons') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Coupons">كوبونات الخصم</span>{!! $plan['coupons'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.banners.view'))
                            <li class="{{ request()->is("$getLocal/online_store/banners*") ? 'active' : '' }} {{ $plan['banners'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['banners'] ? route('store_banners.index') : $planLockUrl }}"{!! $planAttrs('banners') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Banners">البانرات والإعلانات</span>{!! $plan['banners'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- الشحن والتوصيل --}}
            @if (canAny(['online_store.geo.view', 'online_store.shipping_methods.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/countries*") || request()->is("$getLocal/online_store/cities*") || request()->is("$getLocal/online_store/shipping-methods*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-truck"></i>
                        <span class="menu-title" data-i18n="ShippingDelivery">الشحن والتوصيل</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.geo.view'))
                            <li class="{{ request()->is("$getLocal/online_store/countries*") ? 'active' : '' }} {{ $plan['geo'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['geo'] ? route('countries.index') : $planLockUrl }}"{!! $planAttrs('geo') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Countries">الدول</span>{!! $plan['geo'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                            <li class="{{ request()->is("$getLocal/online_store/cities*") ? 'active' : '' }} {{ $plan['geo'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['geo'] ? route('cities.index') : $planLockUrl }}"{!! $planAttrs('geo') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="Cities">المدن</span>{!! $plan['geo'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.shipping_methods.view'))
                            <li class="{{ request()->is("$getLocal/online_store/shipping-methods*") ? 'active' : '' }} {{ $plan['shipping_methods'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['shipping_methods'] ? route('shipping_methods.index') : $planLockUrl }}"{!! $planAttrs('shipping_methods') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ShippingMethods">طرق الشحن</span>{!! $plan['shipping_methods'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- المحتوى والصفحات --}}
            @if (hasPermission('online_store.pages.view'))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/pages*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-file-text"></i>
                        <span class="menu-title" data-i18n="ContentPages">المحتوى والصفحات</span>
                    </a>
                    <ul class="menu-content">
                        <li class="{{ request()->is("$getLocal/online_store/pages*") ? 'active' : '' }} {{ $plan['pages'] ? '' : 'stk-plan-locked' }}">
                            <a href="{{ $plan['pages'] ? route('store_pages.index') : $planLockUrl }}"{!! $planAttrs('pages') !!}>
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Pages">صفحات المتجر</span>{!! $plan['pages'] ? '' : PlanLock::menuBadge() !!}
                            </a>
                        </li>
                    </ul>
                </li>
            @endif

            {{-- الاتصالات --}}
            @if (canAny(['online_store.email_templates.view', 'online_store.email_logs.view']))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/email-templates*") || request()->is("$getLocal/online_store/email-logs*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-mail"></i>
                        <span class="menu-title" data-i18n="Communications">الاتصالات</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.email_templates.view'))
                            <li class="{{ request()->is("$getLocal/online_store/email-templates*") ? 'active' : '' }} {{ $plan['email'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['email'] ? route('email_templates.index') : $planLockUrl }}"{!! $planAttrs('email') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="EmailTemplates">قوالب البريد</span>{!! $plan['email'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.email_logs.view'))
                            <li class="{{ request()->is("$getLocal/online_store/email-logs*") ? 'active' : '' }} {{ $plan['email'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['email'] ? route('email_logs.index') : $planLockUrl }}"{!! $planAttrs('email') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="EmailLogs">سجلات البريد</span>{!! $plan['email'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- التقارير --}}
            @if (canAny([
                        'online_store.reports.view', 'online_store.reports.sales', 'online_store.reports.orders',
                        'online_store.reports.products', 'online_store.reports.clients', 'online_store.reports.coupons',
                        'online_store.reports.shipping', 'online_store.reports.reviews',
                    ]))
                <li class="nav-item has-sub {{ request()->is("$getLocal/online_store/reports*") ? 'open' : '' }}">
                    <a href="javascript:void(0);">
                        <i class="feather icon-bar-chart-2"></i>
                        <span class="menu-title" data-i18n="Reports">التقارير</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('online_store.reports.view'))
                            <li class="{{ request()->is("$getLocal/online_store/reports") || request()->is("$getLocal/online_store/reports?*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.index') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ReportsOverview">نظرة عامة</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.sales'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/sales*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.sales') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="SalesReport">تقرير المبيعات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.orders'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/orders*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.orders') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="OrdersReport">تقرير الطلبات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.products'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/products*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.products') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ProductsReport">تقرير المنتجات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.clients'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/clients*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.clients') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ClientsReport">تقرير العملاء</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.coupons'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/coupons*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.coupons') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="CouponsReport">تقرير الكوبونات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.shipping'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/shipping*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.shipping') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ShippingReport">تقرير الشحن</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                        @if (hasPermission('online_store.reports.reviews'))
                            <li class="{{ request()->is("$getLocal/online_store/reports/reviews*") ? 'active' : '' }} {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                                <a href="{{ $plan['reports'] ? route('onlinestore.reports.reviews') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                                    <i class="feather icon-circle"></i>
                                    <span class="menu-item" data-i18n="ReviewsReport">تقرير التقييمات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif

            {{-- الإعدادات --}}
            @if (hasPermission('online_store.settings.view'))
                <li class="nav-item {{ request()->is("$getLocal/online_store/settings*") ? 'active' : '' }} {{ $plan['settings'] ? '' : 'stk-plan-locked' }}">
                    <a href="{{ $plan['settings'] ? route('store_settings.index') : $planLockUrl }}"{!! $planAttrs('settings') !!}>
                        <i class="feather icon-settings"></i>
                        <span class="menu-title" data-i18n="Settings">الإعدادات</span>{!! $plan['settings'] ? '' : PlanLock::menuBadge() !!}
                    </a>
                </li>
            @endif

        </ul>
    </div>
</div>
