@php
    $getLocal = App::getLocale();
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="#">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">فوترة</h2>
                </a>
            </li>

            <li class="nav-item nav-toggle"><a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse"><i class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i><i class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary" data-ticon="icon-disc"></i></a></li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">

        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">
            {{-- الرئيسيه --}}


            {{-- المبيعات --}}








                    <ul class="menu-content">
                        <li>
                            <a href="{{ route('clients.invoice_client') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Start Sale">الفواتير</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('clients.questions_client') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="Sessions">عروض الاسعار</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('clients.questions_client') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item" data-i18n="POS Reports">اوامر الشغل</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('clients.appointments_client') }}">
                                <i class="feather icon-circle"></i>
                                <span class="menu-item"
                                    data-i18n="POS Settings">المواعيد</span>
                            </a>
                        </li>



            <!-- نقاط  البيع -->
            @if (
                    canAny([
                        'points_sale_edit_product_prices',
                        'points_sale_add_discount',
                        'points_sale_open_sessions_all',
                        'points_sale_open_sessions_own',
                        'points_sale_close_sessions_all',
                        'points_sale_close_sessions_own',
                        'points_sale_confirm_close_sessions_all',
                        'points_sale_confirm_close_sessions_own',
                    ])
                )
                 {{-- نقاط البيع --}}
            @if(isset($settings['pos']) && $settings['pos'] === 'active')
            <li class="nav-item">
                <a href="#">
                    <i class="feather icon-monitor"></i>
                    <span class="menu-title" data-i18n="POS">{{ trans('main_trans.Point_of_Sale') }}</span>
                </a>
                <ul class="menu-content">
                    <li>
                        <a href="">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Start Sale">{{ trans('main_trans.Start_Sale') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Sessions">{{ trans('main_trans.Sessions') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="POS Reports">{{ trans('main_trans.POS_Reports') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item"
                                data-i18n="POS Settings">{{ trans('main_trans.POS_Settings') }}</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            @endif

            {{-- المتجر الكتروني --}}

            @can('online_store_content_management')
            @if(isset($settings['ecommerce']) && $settings['ecommerce'] === 'active')
                <li class=" nav-item {{ request()->is("$getLocal/online_store/*") ? 'active open' : '' }}"><a href="index.html">
                        <i class="feather icon-shopping-cart">
                        </i><span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Online_store') }}</span>
                    </a>
                    <ul class="menu-content">
                        <li><a href="{{ route('content_management.index') }}"><i class="feather icon-circle {{ request()->is("$getLocal/online_store/content-management/*") ? 'active' : '' }}"></i><span class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Content_management') }}</span></a>
                        </li>
                        <li><a href="{{ route('store_settings.index') }}"><i class="feather icon-circle {{ request()->is("$getLocal/online_store/store_settings/*") ? 'active' : '' }}"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span></a>
                        </li>
                    </ul>
                </li>
                @endif
            @endcan

            {{-- التصنيع --}}
            @can('online_store_content_management')
             @if(isset($settings['manufacturing']) && $settings['manufacturing'] === 'active')

                <li class="nav-item {{ request()->is("$getLocal/Manufacturing/*") ? 'active open' : '' }}">
                    <a href="index.html">
                        <i class="feather icon-layers"></i>
                        <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Manufacturing') }}</span>
                    </a>
                    <ul class="menu-content">
                        <li>
                            <a href="{{ route('BOM.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/Manufacturing/BOM/*") ? 'active' : '' }}"></i> <span class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Bill_of_Materials') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manufacturing.orders.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/Manufacturing/Orders/*") ? 'active' : '' }}"></i>
                                <span class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Manufacturing_Orders') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manufacturing.indirectcosts.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/Manufacturing/indirectcosts/*") ? 'active' : '' }}"></i>
                                <span class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Indirect_Costs') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manufacturing.workstations.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/Manufacturing/Workstations/*") ? 'active' : '' }}"></i>
                                <span class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Workstations') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('Manufacturing.settings.index') }}">
                                <i class="feather icon-circle {{ request()->is("$getLocal/Manufacturing/Settings/*") ? 'active' : '' }}"></i>
                                <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
            @endcan

            <!-- إدارة الحجوزات -->
            @if (
                    canAny([
                        'rental_unit_view_booking_orders',
                        'rental_unit_manage_booking_orders',
                        'rental_unit_manage_rental_settings',
                        'client_follow_up_add_notes_attachments_appointments_all',
                    ])
                )
                @if(isset($settings['booking_management']) && $settings['booking_management'] === 'active')
                <li class="nav-item"><a href="#">
                        <i class="feather icon-bookmark"></i> <!-- أيقونة الحجز -->
                        <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Reservations') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('rental_unit_view_booking_orders')
                            <li><a href="{{ route('Reservations.create') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Add_reservation') }}</span></a>
                            </li>
                        @endcan

                        @can('rental_unit_manage_booking_orders')
                            <li><a href="{{ route('Reservations.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Manage_reservations') }}</span></a>
                            </li>
                        @endcan

                        @can('client_follow_up_add_notes_attachments_appointments_all')
                            <li><a href="{{ route('appointments.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Appointments') }}</span></a>
                            </li>
                        @endcan

                        @can('rental_unit_manage_rental_settings')
                            <li><a href="{{ route('Reservations.Booking_Settings') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Booking_Settings') }}</span></a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endif
            @endif
            {{-- إدارة الأقساط.

                 كان البند الأب يشير إلى `index.html` — ملفٌّ لا وجود له،
                 فالضغط على «إدارة الأقساط» يذهب إلى `/ar/index.html`
                 ويعطي Not Found. وكانت الحراسة صلاحيةَ سُلف الرواتب
                 (قروض الرواتب في الموارد البشرية) لا صلة لها بتقسيط فواتير
                 العملاء. صار كلاهما مطابقاً لـ`layouts/sidebar` وحراسة
                 `Modules/Sales/routes/web.php`. --}}
            @if (auth()->user()?->hasRole('manager')
                || canAny([
                    'sales.installments.view',
                    'sales.installments.create', 'sales.installments.edit_amount',
                ]))
            @if(isset($settings['installments_management']) && $settings['installments_management'] === 'active')
                <li class="nav-item"><a href="{{ route('installments.index') }}">
                        <i class="feather icon-credit-card"></i> <!-- أيقونة الأقساط -->
                        <span class="menu-title"
                            data-i18n="Dashboard">{{ trans('main_trans.Installment_Management') }}</span>
                    </a>
                    <ul class="menu-content">

                        <li><a href="{{ route('installments.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Installment_agreements') }}</span></a>
                        </li>

                        <li><a href="{{ route('installments.agreement_installments') }}"><i
                                    class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Installments') }}</span></a>
                        </li>
                    </ul>
                </li>
                @endif
            @endif

            <!-- إدارة الوحدات والإيجارات -->
            @if (
                    canAny([
                        'rental_unit_view_booking_orders',
                        'rental_unit_manage_booking_orders',
                        'rental_unit_manage_rental_settings',
                        'client_follow_up_add_notes_attachments_appointments_all',
                    ])
                )
                 @if(isset($settings['rental_management']) && $settings['rental_management'] === 'active')
                <li class="nav-item"><a href="index.html">
                        <i class="feather icon-home"></i> <!-- أيقونة الوحدات والإيجارات -->
                        <span class="menu-title"
                            data-i18n="Dashboard">{{ trans('main_trans.Units_and_Rentals_Management') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('rental_unit_view_booking_orders')
                            <li><a href="{{ route('rental_management.units.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Units') }}</span></a>
                            </li>
                        @endcan

                        @can('rental_unit_manage_booking_orders')
                            <li><a href="{{ route('rental_management.orders.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Seizure_orders') }}</span></a>
                            </li>
                        @endcan

                        <li><a href="{{ route('rental_management.rental_price_rule.index') }}"><i
                                    class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Pricing_rules') }}</span></a>
                        </li>
                        <li><a href="{{ route('rental_management.seasonal-prices.index') }}"><i
                                    class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Seasonal_Prices') }}</span></a>
                        </li>

                        @can('rental_unit_manage_rental_settings')
                            <li><a href="{{ route('rental_management.settings.index') }}"><i
                                        class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span></a>
                            </li>
                        @endcan
                    </ul>
                </li>
                @endif
            @endif

            <!-- أوامر التوريد -->

           @if (
                    canAny([
                        'work_cycle',
                    ])
                )
                  <!-- دورات العمل -->
            @if(isset($settings['workflow']) && $settings['workflow'] === 'active')
            <li class="nav-item"><a href="">
                    <i class="feather icon-refresh-ccw"></i> <!-- أيقونة دورات العمل -->
                    <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Business_cycles') }}</span>
                </a>
                <ul class="menu-content">
                    <li><a href=""><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="Analytics">{{ trans('main_trans.Settings') }}</span></a></li>
                </ul>
            </li>
           @endif
            @endif
            <!-- العملاء -->
            @if (
                    canAny([
                        'clients_view_all_clients',
                        'client_follow_up_add_notes_attachments_appointments_all',
                        'clients_edit_client_settings',
                    ])
                )
                 @if(isset($settings['customers']) && $settings['customers'] === 'active')
                <li class="nav-item">
                    <a href="#">
                        <i class="feather icon-user"></i> <!-- أيقونة العملاء -->
                        <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Customers') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('clients_view_all_clients')
                            <li><a href="{{ route('clients.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Customer_management') }}</span>
                                </a>
                            </li>
                        @endcan

                        @can('clients_add_client')
                            <li><a href="{{ route('clients.create') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Add_a_new_customer') }}</span>
                                </a>
                            </li>
                        @endcan

                        <li><a href="{{ route('clients.contacts') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Contact_list') }}</span>
                            </a>
                        </li>

                        <li><a href="{{ route('clients.mang_client') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Customer_relationship_management') }}</span>
                            </a>
                        </li>

                        @can('clients_edit_client_settings')
                            <li><a href="{{ route('clients.setting') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Client_settings') }}</span>
                                </a>
                            </li>
                        @endcan

                    </ul>
                </li>
                @endif
            @endif

            {{-- حضور العملاء --}}
            @can('customer_attendance_display')
            @if(isset($settings['customer_attendance']) && $settings['customer_attendance'] === 'active')
                <li class=" nav-item"><a href="index.html">
                        <i class="feather icon-user-check">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Customer_attendance') }}</span>

                    </a>
                    <ul class="menu-content">
                        @can('customer_attendance_display')
                            <li><a href="{{ route('customer_attendance.index') }}"><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Customer_attendance_records') }}</span></a>
                            </li>
                        @endcan
                    </ul>
                </li>
                @endif
            @endcan

            {{-- ادارة المخازن --}}
            @if (
                    canAny([
                        'stock.products.edit',
                        'stock.products.view',
                        'stock.price_lists.view',
                        'stock.price_lists.edit',
                        'stock.price_lists.delete',
                    ])
                )
                @if(isset($settings['inventory_management']) && $settings['inventory_management'] === 'active')
                <li class=" nav-item {{ request()->is("$getLocal/stock/*") ? 'active open' : '' }}"><a href="">
                        <i class="feather icon-box">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Stock') }}</span>

                    </a>
                    <ul class="menu-content">
                        @can('stock.products.view')
                            <li><a href="{{ route('products.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/stock/products/*") ? 'active' : '' }}"></i><span
                                        class="menu-item" data-i18n="Analytics">
                                        {{ trans('main_trans.products_management') }}</span></a>
                            </li>
                        @endcan

                        @can('stock.permits.view')
                            <li><a href="{{ route('store_permits_management.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/stock/store_permits_management/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Store_permissions_management') }}</span></a>
                            </li>
                        @endcan


                        <li><a href="{{ route('products.traking') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.product_tracking') }}</span></a>
                        </li>

                        @can('stock.price_lists.view')
                            <li><a href="{{ route('price_list.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/stock/price_list/*") ? 'active' : '' }}"></i><span
                                        class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.price_lists') }}</span></a>
                            </li>
                        @endcan

                        {{-- @can('stock.price_lists.view') --}}
                        <li><a href="{{ route('storehouse.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/stock/storehouse/*") ? 'active' : '' }}"></i><span
                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.warehouses') }}</span></a>
                        </li>
                        {{-- @endcan --}}

                        <li><a href="{{ route('inventory_management.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_management/*") ? 'active' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.inventory_Management') }}</span></a>
                        </li>

                        <li><a href="{{ route('inventory_settings.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_settings/*") ? 'active' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.inventory_settings') }}</span></a>
                        </li>

                        <li><a href="{{ route('product_settings.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/stock/product_settings/*") ? 'active' : '' }}"></i><span
                                    class="menu-item" data-i18n="eCommerce">
                                    {{ trans('main_trans.products_Settings') }}</span></a>
                        </li>

                    </ul>

                </li>
                 @endif
            @endif

            {{-- المشتريات --}}
            @if (canAny(['purchases.orders.view']))
            @if(isset($settings['purchase_cycle']) && $settings['purchase_cycle'] === 'active')
                <li class=" nav-item"><a href="index.html">
                        <i class="fa fa-shopping-cart">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Purchases') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('purchases.orders.view')

                            <li><a href="{{ route('PurchaseRequests.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="Analytics"> {{ trans('main_trans.Purchase_Orders') }}</span></a>
                            </li>
                        @endcan

                        <li><a href="{{ route('Quotations.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Quotation_Requests') }}

                                </span></a>
                        </li>

                        <li><a href="{{ route('ViewPurchasePrice.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce"> {{ trans('main_trans.Purchase_Quotations') }}</span></a>
                        </li>
                        <li><a href="{{ route('PurchaseOrders.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">أوامر الشراء</span></a>
                        </li>
                        <li><a href="{{ route('invoicePurchases.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Purchase_Invoices') }}
                                </span></a>
                        </li>
                        <li><a href="{{ route('ReturnsInvoice.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Purchase_Returns') }}</span></a>
                        </li>
                        <li><a href="{{ route('CityNotices.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item" data-i18n="eCommerce">
                                    {{ trans('main_trans.Creditor_notices') }}</span></a>
                        </li>
                        <li><a href="{{ route('SupplierManagement.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce"> {{ trans('main_trans.Supplier_Management') }}
                                    </span></a>
                        </li>
                        <li><a href="{{route('PaymentSupplier.indexPurchase')}}"><i
                                    class="feather icon-circle"></i><span class="menu-item" data-i18n="eCommerce">
                                    {{ trans('main_trans.Supplier_Payments') }}

                                </span></a>
                        </li>
                        <li><a href=""><i
                                    class="feather icon-circle"></i><span class="menu-item" data-i18n="eCommerce">
                                    {{ trans('main_trans.Purchase_Invoices_Settings') }}</span></a>
                        </li>
                        <li><a href=""><i
                                    class="feather icon-circle"></i><span class="menu-item" data-i18n="eCommerce">
                                    {{ trans('main_trans.Supplier_Settings') }}</span></a>

                        </li>
                    </ul>

                </li>
            @endif
            @endif
            {{-- تتبع الوقت --}}
            @if (canAny(['time_entries.index', 'time_entries.create', 'time_entries.edit', 'time_projects.create', 'time_projects.edit', 'time_projects.destroy', 'time_activities.create', 'time_activities.edit', 'time_activities.destroy']))
            @if(isset($settings['time_tracking']) && $settings['time_tracking'] === 'active')
                <li class=" nav-item"><a href="index.html">
                        <i class="feather icon-watch">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Time_Tracking') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('time_entries.index'))
                        <li><a href="{{ route('TrackTime.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Time_Tracking') }}</span></a>
                        </li>
                        @endif
                        @if (hasPermission('time_entries.create'))
                        <li><a href="{{ route('TrackTime.create_invoice_time') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Create_Invoice') }}</span></a>
                        </li>
                        @endif

                        @if (canAny(['time_projects.create', 'time_projects.edit', 'time_projects.destroy', 'time_activities.create', 'time_activities.edit', 'time_activities.destroy', 'time_entries.edit']))
                        <li><a href="{{ route('SittingTrackTime.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Time_Tracking_Settings') }}</span></a>
                        </li>
                        @endif
                    </ul>

                </li>
                @endif
            @endif

            {{-- المالية --}}
            @if (
                    canAny([
                        'account.expenses.view',
                        'account.incomes.view',
                        'account.treasury.view',
                        'account.treasury.edit',
                    ])
                )
                @if(isset($settings['finance']) && $settings['finance'] === 'active')
                            <li class=" nav-item {{ request()->is("$getLocal/finance/*") ? 'active open' : '' }}">
                                <a href="index.html">
                                    <i class="feather icon-dollar-sign">
                                    </i><span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Financial') }}</span>
                                </a>

                                <ul class="menu-content">
                                    @can('account.expenses.view')
                                        <li><a href="{{ route('expenses.index') }}"><i
                                                    class="feather icon-circle {{ request()->is("$getLocal/finance/expenses/*") ? 'active' : '' }}"></i><span
                                                    class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Expenses') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.incomes.view')
                                        <li><a href="{{ route('incomes.index') }}"><i
                                                    class="feather icon-circle {{ request()->is("$getLocal/finance/incomes/*") ? 'active' : '' }}"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Receipts') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.treasury.view')
                                        <li><a href="{{ route('treasury.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">
                                                    {{ trans('main_trans.Cash_and_Bank_Accounts') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.treasury.edit')
                                        <li><a href="{{ route('finance_settings.index') }}"><i
                                                    class="feather icon-circle {{ request()->is("$getLocal/finance/finance_settings/*") ? 'active' : '' }}"></i><span
                                                    class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Financial_Settings') }}</span></a>
                                        </li>
                                    @endcan
                                </ul>

                            </li>
            @endif
            @endif
            {{-- الحسابات العامة --}}
            @if (
                    canAny([
                        'account.journal.view',
                        'account.journal.create',
                        'account.journal.edit',
                        'account.cost_centers.view',
                        'account.assets.create',
                    ])
                )
                  @if(isset($settings['general_accounts_journal_entries']) && $settings['general_accounts_journal_entries'] === 'active')
                            <li class=" nav-item {{ request()->is("$getLocal/Accounts/*") ? 'active open' : '' }}"><a href="index.html">
                                    <i class="feather icon-pie-chart">
                                    </i><span class="menu-title" data-i18n="Dashboard">
                                        {{ trans('main_trans.General_Accounts') }}</span>
                                </a>

                                <ul class="menu-content">
                                    @can('account.journal.view')
                                        <li><a href="{{ route('journal.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item"
                                                    data-i18n="Analytics">{{ trans('main_trans.Journal_Entries') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.journal.create')
                                        <li><a href="{{ route('journal.create') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Add_Entry') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.journal.edit')
                                        <li><a href="{{ route('accounts_chart.view_root') }}"><i class="feather icon-circle {{ request()->is("$getLocal/Accounts/accounts_chart/*") ? 'active' : '' }}"></i><span
                                                    class="menu-item" data-i18n="eCommerce">
                                                    {{ trans('main_trans.Chart_of_Accounts') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.cost_centers.view')
                                        <li><a href="{{ route('cost_centers.index') }}"><i class="feather icon-circle {{ request()->is("$getLocal/Accounts/cost_centers/*") ? 'active' : '' }}"></i><span
                                                    class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Cost_Centers') }}</span></a>
                                        </li>
                                    @endcan

                                    @can('account.assets.create')
                                        <li><a href="{{ route('Assets.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                                    data-i18n="eCommerce">
                                                    {{ trans('main_trans.Assets') }}</span></a>
                                        </li>
                                    @endcan

                                    <li><a href="{{ route('accounts_settings.index') }}"><i class="feather icon-circle"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.General_Accounts_Settings') }}</span></a>
                                    </li>
                                </ul>

                            </li>
            @endif
            @endif
            {{-- الشيكات --}}
            @if (canAny(['account.cheque_books.view', 'account.received_cheques.view']))
            @if(isset($settings['cheque_cycle']) && $settings['cheque_cycle'] === 'active')
                <li class=" nav-item {{ request()->is("$getLocal/cheques*") ? 'active open' : '' }}"><a href="index.html">
                        <i class="feather icon-dollar-sign">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Cheques_Cycle') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('account.cheque_books.view')
                            <li><a href="{{ route('payable_cheques.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/cheques/payable_cheques*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Paid_Cheques') }}</span></a>
                            </li>
                        @endcan

                        @can('account.received_cheques.view')
                            <li><a href="{{ route('received_cheques.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/cheques/received_cheques*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Received_Cheques') }}</span></a>
                            </li>
                        @endcan
                    </ul>

                </li>
            @endif
            @endif
            {{-- الطلبات --}}
            @if (canAny(['orders_management', 'orders_setting_management']))
            @if(isset($settings['orders']) && $settings['orders'] === 'active')
                <li class=" nav-item"><a href="index.html">
                        <i class="feather icon-briefcase">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Orders') }}</span>
                    </a>
                    <ul class="menu-content">
                        @can('orders_management')
                            <li><a href="{{ route('orders.management.mangame') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Order_Management') }}</span></a>
                            </li>
                        @endcan

                        @can('orders_setting_management')
                            <li><a href="{{ route('orders.Settings.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span></a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endif
            @endif
            {{-- الموظفين --}}
            @if (hasPermission('employees.index') || hasPermission('shifts.index'))
            @if(isset($settings['employees']) && $settings['employees'] === 'active')
                <li class=" nav-item {{ request()->is("$getLocal/hr/*") ? 'active open' : '' }}">
                    <a href="index.html">
                        <i class="fa fa-users"></i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Employees') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('employees.index'))
                            <li><a href="{{ route('employee.index') }}"><i
                                        class="feather icon-circle {{ request()->is("$getLocal/hr/employee/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Employee_Management') }}</span></a>
                            </li>
                        @endif


                        @if (hasPermission('shifts.index'))


                        <li><a href="{{ route('shift_management.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/hr/shift_management/*") ? 'active' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Shift_Management') }}</span></a>
                        </li>


                        @endif
                    </ul>

                </li>
            @endif
            @endif
            {{-- الهيكل التنظيمي — كلُّ شاشةٍ بصلاحيتها --}}
            @if (canAny(['departments.index', 'job_titles.index', 'job_levels.index', 'job_types.index']))
            @if(isset($settings['organizational_structure']) && $settings['organizational_structure'] === 'active')
                <li class=" nav-item {{ request()->is("$getLocal/OrganizationalStructure/*") ? 'active open' : '' }}">
                    <a href="index.html">
                        <i class="feather icon-layers">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Organizational_Structure') }}</span>
                    </a>
                    <ul class="menu-content">
                        @if (hasPermission('job_titles.index'))
                        <li><a href="{{ route('JobTitles.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/JobTitles/*") ? 'active open' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="Analytics">{{ trans('main_trans.Job_Titles_Management') }}</span></a>
                        </li>
                        @endif

                        @if (hasPermission('departments.index'))

                        <li><a href="{{ route('department.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/department/*") ? 'active open' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Departments_Management') }}</span></a>
                        </li>

                        @endif

                        @if (hasPermission('job_levels.index'))

                        <li><a href="{{ route('ManagingFunctionalLevels.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingFunctionalLevels/*") ? 'active open' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Job_Levels_Management') }}</span></a>
                        </li>

                        @endif
                        @if (hasPermission('job_types.index'))
                        <li><a href="{{ route('ManagingJobTypes.index') }}"><i
                                    class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingJobTypes/*") ? 'active open' : '' }}"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Job_Types_Management') }}</span></a>
                        </li>
                        @endif
                    </ul>
                </li>

            @endif
            @endif
            {{-- الحضور --}}
            @if(
                    canAny([
                        'attendance_records.index', 'attendance_days.index', 'attendance_books.index',
                        'leave_permissions.index', 'leave_requests.index', 'shifts.index',
                        'attendance_devices.index', 'attendance_settings.manage',
                    ])
                )
                 @if(isset($settings['employee_attendance']) && $settings['employee_attendance'] === 'active')
                            <li class="nav-item {{ (request()->is("$getLocal/presence/*") ? 'active open' : '') }}"><a
                                    href="index.html">

                                    <i class="feather icon-user-check">
                                    </i><span class="menu-title" data-i18n="Dashboard">
                                        {{ trans('main_trans.Attendance') }}</span>
                                </a>
                                <ul class="menu-content">
                                    @if (hasPermission('attendance_records.index'))
                                        <li><a href="{{ route('hr.attendance-punches.index') }}"><i
                                                    class="feather icon-circle {{ (request()->is("$getLocal/hr/attendance-punches*") ? 'active' : '') }}"></i><span
                                                    class="menu-item" data-i18n="Analytics">
                                                    {{ trans('main_trans.Attendance_Records') }}</span></a>

                                        </li>
                                    @endif

                                    @if (hasPermission('attendance_days.index'))
                                        <li><a href="{{ route('attendanceDays.index') }}"><i
                                                    class="feather icon-circle {{ (request()->is("$getLocal/presence/attendanceDays/*") ? 'active' : '') }}"></i><span
                                                    class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Attendance_Days') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('attendance_books.index'))
                                        <li><a href="{{ route('attendance_sheets.index') }}"><i
                                                    class="feather icon-circle {{ (request()->is("$getLocal/presence/attendance-sheets/*") ? 'active' : '') }}"></i><span
                                                    class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Attendance_Books') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('leave_permissions.index'))

                                    <li><a href="{{ route('leave_permissions.index') }}"><i
                                                class="feather icon-circle {{ (request()->is("$getLocal/presence/leave-permissions/*") ? 'active' : '') }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Leave_Permissions') }}</span></a>
                                    </li>

                                    @endif

                                    @if (hasPermission('leave_requests.index'))
                                        <li><a href="{{ route('attendance.leave_requests.index') }}"><i
                                                    class="feather icon-circle"></i><span class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Leave_Requests') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('shifts.index'))

                                    <li><a href="{{ route('shift_management.index') }}"><i
                                                class="feather icon-circle {{ (request()->is("$getLocal/presence/shift_management/*") ? 'active' : '') }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Shift_Management') }}</span></a>
                                    </li>

                                    @endif

                                    @if (hasPermission('shifts.index'))

                                    <li><a href="{{ route('custom_shifts.index') }}"><i
                                                class="feather icon-circle {{ (request()->is("$getLocal/presence/custom-shifts/*") ? 'active' : '') }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Custom_Shifts') }}</span></a>
                                    </li>

                                    @endif

                                    @if (hasPermission('attendance_devices.index'))
                                        <li><a href="{{ route('hr.device-monitor.index') }}"><i
                                                    class="feather icon-circle"></i><span class="menu-item"
                                                    data-i18n="eCommerce">{{ trans('main_trans.Attendance_Sessions_Log') }}</span></a>
                                        </li>
                                    @endif

                                    @if (canAny(['attendance_settings.manage', 'holidays.index', 'attendance_rules.index', 'leave_types.index', 'leave_policies.index']))
                                        <li><a href="{{ route('attendance.settings.index') }}"><i
                                                    class="feather icon-circle {{ (request()->is("$getLocal/presence/settings/*") ? 'active' : '') }}"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span></a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
            @endif
            @endif
            {{-- المرتبات — كلُّ بندٍ بحارس شاشته نفسِه (`x.index`)؛ ولا صلاحياتِ «هو»: النطاقُ يحدّد من --}}
            @if (canAny([
                        'contracts.index',
                        'payroll.index',
                        'payslips.index',
                        'loans.index',
                        'salary_items.index',
                        'payroll_settings.manage',
                        'contracts.notifications', 'salary_items.index',
                        'overtime.settings', 'insurance.settings', 'bank_files.index',
                    ]))
                 @if(isset($settings['salaries']) && $settings['salaries'] === 'active')
                            <li class=" nav-item"><a href="#">
                                    <i class="feather icon-dollar-sign">
                                    </i><span class="menu-title" data-i18n="Dashboard">
                                        {{ trans('main_trans.Salaries') }}</span>
                                </a>
                                <ul class="menu-content">
                                    @if (hasPermission('contracts.index'))
                                        <li><a href="{{ route('Contracts.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Contracts') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('payroll.index'))
                                        <li><a href="{{ route('PayrollProcess.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Payroll') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('payslips.index'))
                                        <li><a href="{{ route('salarySlip.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">
                                                    {{ trans('main_trans.Salary_Slips') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('loans.index'))
                                        <li><a href="{{ route('hr.advance-approvals.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Advances') }}</span></a>
                                        </li>
                                    @endif

                                    @if (hasPermission('salary_items.index'))
                                    <li><a href="{{ route('SalaryItems.index') }}"><i class="feather icon-circle"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Salary_Items') }}</span></a>
                                    </li>
                                    @endif

                                    @if (canAny(['payroll_settings.manage', 'contracts.notifications', 'salary_items.index', 'overtime.settings', 'insurance.settings', 'insurance.index', 'bank_files.index']))
                                        <li><a href="{{ route('SalarySittings.index') }}"><i class="feather icon-circle"></i><span
                                                    class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Settings') }}</span></a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
            @endif
            @endif




            @if (
                canAny([
                    'branches',
                ])
            )
            {{-- الفروع --}}

            @if(isset($settings['branches']) && $settings['branches'] === 'active')
            <li class=" nav-item"><a href="index.html">
                    <i class="feather  icon-briefcase">
                    </i><span class="menu-title" data-i18n="Dashboard">
                        {{ trans('main_trans.Branches') }}</span>

                </a>
                <ul class="menu-content">
                    <li><a href="{{ route('branches.index') }}"><i class="feather icon-circle"></i><span
                                class="menu-item"
                                data-i18n="Analytics">{{ trans('main_trans.Branch_Management') }}</span></a>
                    </li>

                    <li><a href="{{ route('branches.create') }}"><i class="feather icon-circle"></i><span
                                class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Add_Branch') }}</span></a>
                    </li>

                    <li><a href="{{ route('branches.settings') }}"><i class="feather icon-circle"></i><span
                                class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.settings') }}</span></a>
                    </li>


                </ul>

            </li>
      @endif
        @endif


        @if (
            canAny([
                'templates',
            ])
        )
          {{-- القوالب --}}
          <li class=" nav-item"><a href="index.html">
            <i class="feather icon-dollar-sign">
            </i><span class="menu-title" data-i18n="Dashboard">
                {{ trans('main_trans.Templates') }}</span>

        </a>
        <ul class="menu-content">
            <li><a href="dashboard-analytics.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="Analytics">{{ trans('main_trans.Print_Templates') }}</span></a>
            </li>

            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.Ready_Invoice_Templates') }}</span></a>
            </li>
            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.Emails') }}</span></a>
            </li>
            @if(isset($settings['sms']) && $settings['sms'] === 'active')
            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.SMS_Models') }}</span></a>
            </li>
            @endif
            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.Terms_and_Conditions') }}</span></a>
            </li>
            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.File_Management_and_Documents') }}</span></a>
            </li>
            <li><a href="dashboard-ecommerce.html"><i class="feather icon-circle"></i><span class="menu-item"
                        data-i18n="eCommerce">{{ trans('main_trans.Auto_Send_Rules') }}</span></a>
            </li>


        </ul>

    </li>
    @endif



            {{-- الاعدادات --}}
            @can('settings_edit_general_settings')
                <li class=" nav-item"><a href="index.html">
                        <i class="feather icon-settings">
                        </i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Settings') }}</span>
                    </a>
                    <ul class="menu-content">
                        <li><a href="{{ route('AccountInfo.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item" data-i18n="Analytics">
                                    {{ trans('main_trans.Account_Information') }}</span></a>
                        </li>

                        <li><a href="{{ route('SittingAccount.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Account_Settings') }}</span></a>
                        </li>
                        <li><a href="{{ route('SMPT.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.SMTP_Settings') }}</span></a>
                        </li>

                        <li><a href="{{ route('PaymentMethods.index') }}"><i class="feather icon-circle"></i><span
                                    class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Payment_Methods') }}</span></a></li>

                        <li><a href="{{ route('Sms.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.SMS_Settings') }}</span></a></li>

                        <li><a href="{{ route('SequenceNumbering.index', ['section' => 'invoice']) }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Sequential_Numbering_Settings') }}</span></a>
                        </li>
                        <li><a href="{{ route('TaxSitting.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Tax_Settings') }}</span></a>
                        </li>
                        <li><a href="{{ route('Application.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.Applications_Management') }}</span></a>
                        </li>

                        <li><a href="{{ route('AccountInfo.backgroundColor') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                    data-i18n="eCommerce">{{ trans('main_trans.System_Logo_and_Colors') }}</span></a>
                        </li>

                        {{-- مسح الذاكرة المؤقتة — انظر التعليق نفسه في
                             `resources/views/layouts/sidebar_client.blade.php`. --}}
                        @permission('settings.cache.view')
                            <li><a href="{{ route('SettingsCache.index') }}"><i class="feather icon-circle"></i><span class="menu-item"
                                        data-i18n="eCommerce">{{ __('settings.nav.cache') }}</span></a>
                            </li>
                        @endpermission

                    </ul>
                </li>
            @endcan

        </ul>
    </div>
</div>
