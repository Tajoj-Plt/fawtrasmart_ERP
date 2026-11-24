@php
    $getLocal = App::getLocale();
    // جلب جميع الإعدادات من جدول application_settings
    if (!isset($settings)) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }
@endphp


@if (auth()->user()->isEmployee())
    @include('layouts.sidebar')
@else
<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
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
                    'sales_view_all_credit_notices',
                    'sales_view_own_credit_notices',
                    ]))
                @if (isset($settings['sales']) && $settings['sales'] === 'active')
                @endif

            @endif
   @if (canAny([
                    'rental_unit_view_booking_orders',
                    'rental_unit_manage_booking_orders',
                    'rental_unit_manage_rental_settings',
                ]))
                @if (isset($settings['rental_management']) && $settings['rental_management'] === 'active')
                    <li class="nav-item"><a href="#">
                            <i class="feather icon-home"></i> <!-- أيقونة الوحدات والإيجارات -->
                            <span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Units_and_Rentals_Management') }}</span>
                        </a>
                        <ul class="menu-content">
                            @canany(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders'])
                                <li><a href="{{ route('rental_management.units.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Units') }}</span></a>
                                </li>
                            @endcanany

                            @canany(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders'])
                                <li><a href="{{ route('rental_management.orders.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Seizure_orders') }}</span></a>
                                </li>
                            @endcanany

                            @canany(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders', 'rental_unit_manage_rental_settings'])
                                <li><a href="{{ route('rental_management.rental_price_rule.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Pricing_rules') }}</span></a>
                                </li>
                            @endcanany

                            @canany(['rental_unit_view_booking_orders', 'rental_unit_manage_booking_orders', 'rental_unit_manage_rental_settings'])
                                <li><a href="{{ route('rental_management.seasonal-prices.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Seasonal_Prices') }}</span></a>
                                </li>
                            @endcanany

                            @can('rental_unit_manage_rental_settings')
                                <li><a href="{{ route('rental_management.settings.index') }}"><i
                                            class="feather icon-circle"></i><span class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Sittings') }}</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
            @endif


        </ul>
    </div>
</div>

@endif
