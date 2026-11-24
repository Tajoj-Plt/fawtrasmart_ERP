{{--
    القائمة الجانبية لقسم إدارة العقارات. بنودها في `menu` المشترك؛ وهذا
    الملف هو الإطار فقط (الشعار وزرّ الطيّ وبند العودة).
--}}
<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('realestate.dashboard') }}">
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
            <li class="nav-item">
                <a href="{{ route('dashboard_sales.index') }}">
                    <i class="feather icon-arrow-right"></i><span class="menu-title">الرئيسية</span>
                </a>
            </li>

            <li class="navigation-header"><span>إدارة العقارات</span></li>

            @include('rentalmanagement::realestate.layouts.menu', ['nested' => false])
        </ul>
    </div>
</div>
