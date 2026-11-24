@php
    $getLocal = App::getLocale();
@endphp


    {{-- البنود تُقرأ من `SettingsNavigation` لا تُكتب هنا: كانت مكتوبة
         يدوياً في هذا الملفّ وفي لوحة الإعدادات معاً، فتباعدتا — 27 بنداً
         هنا و11 هناك، وشاشة «إدارة التطبيقات» غائبة عن الاثنين. --}}
    @php
        $navGroups = \App\Services\Settings\SettingsNavigation::visible();
    @endphp

    {{-- إصلاحات الوضع المطوي: القالب يعتمد على أنيميشن fade مدّتها 0.3s
         لإظهار أسماء البنود، فتظهر القائمة فارغة أثناء التوسّع، ويطلع
         خلف البند النشط شريط بعرض 260px خارج حدود الشريط الضيّق. --}}
    <style>
        /* الأسماء تظهر فوراً عند التوسّع بدل انتظار الأنيميشن */
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu.expanded .navigation > li > a > span,
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu.expanded .navigation > li.navigation-header span {
            -webkit-animation: none;
                    animation: none;
            opacity: 1;
        }

        /* لا شيء يتجاوز حدود الشريط أثناء انتقال 300ms بين 80px و260px */
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu:not(.expanded) .main-menu-content,
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu:not(.expanded) .navigation {
            overflow-x: hidden;
        }

        /* عنوان المجموعة في الوضع المطوي: شرطة بدل فراغ صامت */
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu:not(.expanded) .navigation > li.navigation-header {
            position: relative;
            height: 1.75rem;
        }
        body.vertical-layout.vertical-menu-modern.menu-collapsed .main-menu:not(.expanded) .navigation > li.navigation-header::before {
            content: '';
            position: absolute;
            top: 50%;
            right: 24px;
            width: 32px;
            height: 2px;
            border-radius: 2px;
            background: #d6d6d6;
        }
    </style>

    <div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
        <div class="navbar-header">
            <ul class="nav navbar-nav flex-row">
                <li class="nav-item mr-auto">
                    <a class="navbar-brand" href="{{ route('SittingAccount.dashboard') }}">
                        <div class="brand-logo"></div>
                        <h2 class="brand-text mb-0">{{ __('settings.layout.brand') }}</h2>
                    </a>
                </li>
                <li class="nav-item nav-toggle">
                    <a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse">
                        <i class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i>
                        <i class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"
                            data-ticon="icon-disc"></i>
                    </a>
                </li>
            </ul>
        </div>

        <div class="shadow-bottom"></div>

        <div class="main-menu-content">
            <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

                <li class="nav-item {{ request()->routeIs('SittingAccount.dashboard') ? 'active' : '' }}">
                    <a href="{{ route('SittingAccount.dashboard') }}" title="{{ __('settings.layout.settings_panel') }}">
                        <i class="feather icon-grid"></i>
                        <span class="menu-title">{{ __('settings.layout.settings_panel') }}</span>
                    </a>
                </li>

                @foreach ($navGroups as $groupName => $group)
                    <li class="navigation-header"><span>{{ $groupName }}</span></li>

                    @foreach ($group['items'] as $item)
                        @if (empty($item['children']))
                            <li class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                <a href="{{ $item['url'] }}" title="{{ $item['label'] }}">
                                    <i class="feather icon-{{ $item['icon'] }}"></i>
                                    <span class="menu-title">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @else
                            @php
                                // القسم يبقى مفتوحاً ما دام المستخدم داخل أي
                                // من شاشاته — الأب أو أحد أبنائه
                                $patterns = array_merge(
                                    [$item['route']],
                                    array_column($item['children'], 'route')
                                );
                                $open = collect($patterns)->contains(fn ($r) => request()->routeIs($r));
                            @endphp
                            <li class="nav-item {{ $open ? 'active open' : '' }}">
                                <a href="#" title="{{ $item['label'] }}">
                                    <i class="feather icon-{{ $item['icon'] }}"></i>
                                    <span class="menu-title">{{ $item['label'] }}</span>
                                </a>
                                <ul class="menu-content">
                                    <li>
                                        <a href="{{ $item['url'] }}">
                                            <i class="feather icon-circle {{ request()->routeIs($item['route']) ? 'active' : '' }}"></i>
                                            <span class="menu-item">{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}">
                                                <i class="feather icon-circle {{ request()->routeIs($child['route']) ? 'active' : '' }}"></i>
                                                <span class="menu-item">{{ $child['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    @endforeach
                @endforeach

            </ul>
        </div>
    </div>
