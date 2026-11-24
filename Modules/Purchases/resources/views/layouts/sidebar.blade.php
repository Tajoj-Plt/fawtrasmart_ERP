@php
    $getLocal = App::getLocale();
    // جلب جميع الإعدادات من جدول application_settings
    if (!isset($settings)) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }
@endphp


<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow menu-hide" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="#">
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
            {{-- الرئيسيه --}}


            @if (canAny([
                        'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.invoices.delete',
                        'sales.quotes.view', 'sales.quotes.create', 'sales.quotes.edit', 'sales.quotes.delete',
                        'sales.payments.view', 'sales.payments.create', 'sales.payments.edit', 'sales.payments.delete',
                        'sales.credit_notes.view', 'sales.credit_notes.create', 'sales.credit_notes.edit', 'sales.credit_notes.delete',
                        'sales.returns.view', 'sales.returns.create',
                    ]))
                @if (isset($settings['sales']) && $settings['sales'] === 'active')
                @endif

            @endif
            @if (canAny([
                    'stock.products.edit',
                    'stock.products.view',
                    'stock.price_lists.view',
                    'stock.price_lists.edit',
                    'stock.price_lists.delete',
                ]))

            @endif

            {{-- المشتريات — بوابة القائمة وكل رابط فيها بصلاحيات كتالوج
                 purchases.* الجديد فقط، لا الأسماء القديمة (purchase_cycle_*/
                 inv_manage_*) التي لم تعد تحرس أي مسار في هذا القسم --}}
            {{-- وبوّابةُ القسم بالبادئة لا بتعدادٍ يدوي: كان التعدادُ يُسقط
                 `receiving` و`supplier_wallet` رغم أن القائمة تعرض بنديهما،
                 فيبقى البندُ محجوباً عمّن لا يملك سواه. والبادئةُ تشمل
                 الجديدَ من الصلاحيات بلا تحديثٍ هنا. --}}
            @if (auth()->user()->hasAnyPermissionWithPrefix(\App\Constants\permissions\PurchasesPermissions::PREFIX))
                @if (isset($settings['purchase_cycle']) && $settings['purchase_cycle'] === 'active')


                            @can('purchases.orders.view')
                            <li>
                                <a href="{{ route('PurchaseRequests.index') }}">
                                    <i class="feather icon-file-text"></i>
                                    <span class="menu-item">{{ trans('main_trans.Purchase_Orders') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.quotations.view')
                            <li>
                                <a href="{{ route('Quotations.index') }}">
                                    <i class="feather icon-file"></i>
                                    <span class="menu-item">{{ trans('main_trans.Quotation_Requests') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.price_quotations.view')
                            <li>
                                <a href="{{ route('ViewPurchasePrice.index') }}">
                                    <i class="feather icon-tag"></i>
                                    <span class="menu-item">{{ trans('main_trans.Purchase_Quotations') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.order_requests.view')
                            <li>
                                <a href="{{ route('PurchaseOrders.index') }}">
                                    <i class="feather icon-shopping-bag"></i>
                                    <span class="menu-item">أوامر الشراء</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.receiving.view')
                            <li>
                                <a href="{{ route('PurchaseReceiving.index') }}">
                                    <i class="feather icon-truck"></i>
                                    <span class="menu-item">استلام المشتريات</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.invoices.view')
                            <li>
                                <a href="{{ route('invoicePurchases.index') }}">
                                    <i class="feather icon-file-text"></i>
                                    <span class="menu-item">{{ trans('main_trans.Purchase_Invoices') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.returns.view')
                            <li>
                                <a href="{{ route('ReturnsInvoice.index') }}">
                                    <i class="feather icon-rotate-ccw"></i>
                                    <span class="menu-item">{{ trans('main_trans.Purchase_Returns') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.debit_notices.view')
                                @if (\Modules\Purchases\Services\PurchaseSettings::enabled('enable_debit_notice'))
                                    <li>
                                        <a href="{{ route('CityNotices.index') }}">
                                            <i class="feather icon-alert-circle"></i>
                                            <span class="menu-item">{{ trans('main_trans.Creditor_notices') }}</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('purchases.suppliers.view')
                            <li>
                                <a href="{{ route('SupplierManagement.index') }}">
                                    <i class="feather icon-users"></i>
                                    <span class="menu-item">{{ trans('main_trans.Supplier_Management') }}</span>
                                </a>
                            </li>
                            @endcan

                            {{-- محافظ الموردين — رصيدنا لدى المورد --}}
                            @permission('purchases.supplier_wallet.view')
                            <li>
                                <a href="{{ route('purchases.supplier-wallet.index') }}">
                                    <i class="feather icon-credit-card"></i>
                                    <span class="menu-item">محافظ الموردين</span>
                                </a>
                            </li>
                            @endpermission

                            @can('purchases.payments.view')
                            <li>
                                <a href="{{ route('PaymentSupplier.indexPurchase') }}">
                                    <i class="feather icon-credit-card"></i>
                                    <span class="menu-item">{{ trans('main_trans.Supplier_Payments') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.settings.view')
                            <li>
                                <a href="{{ route('purchases.settings.index') }}">
                                    <i class="feather icon-settings"></i>
                                    <span class="menu-item">{{ __('purchase_settings.page.title') }}</span>
                                </a>
                            </li>
                            @endcan

                            @can('purchases.reports.view')
                            <li>
                                <a href="{{ route('ReportsPurchases.index') }}">
                                    <i class="feather icon-bar-chart-2"></i>
                                    <span class="menu-item">{{ trans('main_trans.Purchases_Report') }}</span>
                                </a>
                            </li>
                            @endcan


                @endif
            @endif

        </ul>
    </div>
</div>
