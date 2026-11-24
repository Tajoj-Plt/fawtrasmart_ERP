<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="Vuexy admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
    <meta name="keywords" content="admin template, Vuexy admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="PIXINVENT">
    <title>@yield('title')</title>
    <link rel="apple-touch-icon" href="{{ asset('app-assets/images/ico/apple-icon-120.png')}}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('app-assets/images/ico/favicon.ico')}}">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600" rel="stylesheet">

    <!-- BEGIN: Vendor CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/vendors-rtl.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/charts/apexcharts.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/extensions/tether-theme-arrows.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/extensions/tether.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/extensions/shepherd-theme-default.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/tables/datatable/datatables.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/forms/select/select2.min.css')}}">
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/bootstrap.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/bootstrap-extended.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/colors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/components.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/themes/dark-layout.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/themes/semi-dark-layout.css')}}">

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/core/menu/menu-types/vertical-menu.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/core/colors/palette-gradient.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/pages/dashboard-analytics.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/pages/card-analytics.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/plugins/tour/tour.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/plugins/forms/wizard.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/pages/dashboard-ecommerce.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/pages/app-chat.css')}}">
    <!-- END: Page CSS-->

    <!-- BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('app-assets/css-rtl/custom-rtl.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style-rtl.css')}}">
    <!-- END: Custom CSS-->

    <link rel="stylesheet" href="{{ asset('assets/fonts/Cairo/stylesheet.css') }}">
    <style>
        body, h1, h2, h3, h4, h5, h6,.navigation,.header-navbar,.breadcrumb {
            font-family: 'Cairo';
        }
    </style>

    @yield('css')

    @include('layouts.partials.language-styles')
    {{-- بعد أنماط اللغة: مظهر النظام هو الكلمة الأخيرة في الخط واللون --}}
    @include('layouts.partials.theme-styles')

{{--
    إعدادات العملة وبيانات المنشأة لكل سكربتات الواجهة.

    كانتا مُعرَّفتين في `account::master` و`stock::master` وحدهما، بينما
    تقارير المبيعات ونقطة البيع والعملاء تستعمل قوالبها هي — فبطاقات
    التصدير فيها تُلحق «ريال» مكتوبةً في السكربت، ولا تحمل اسم المنشأة.
    مكانهما هنا: كل القوالب تُضمّن `head_rtl` أو `head_ltr`.
--}}
<script>
    window.AppCurrency = window.AppCurrency || {
        symbol: @json(\App\Support\CurrencySettings::symbol()),
        code: @json(\App\Support\CurrencySettings::code()),
        decimals: {{ \App\Support\CurrencySettings::decimals() }},
        thousands: @json(\App\Support\CurrencySettings::thousandsSeparator()),
        decimal: @json(\App\Support\CurrencySettings::decimalSeparator()),

        /** رقم منسَّق بفواصل العملة المضبوطة، بلا رمز */
        number(value) {
            const n = Number(value) || 0;
            const fixed = Math.abs(n).toFixed(this.decimals);
            const [whole, frac] = fixed.split('.');
            const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, this.thousands);
            const body = frac ? grouped + this.decimal + frac : grouped;

            return (n < 0 ? '-' : '') + body;
        },

        /** رقم منسَّق مع الرمز */
        format(value) {
            return this.number(value) + ' ' + this.symbol;
        },
    };

    window.AppOrg = window.AppOrg || @json(\App\Support\OrganizationInfo::all() + [
        'identity' => \App\Support\OrganizationInfo::identityLine(),
    ]);
</script>


</head>


