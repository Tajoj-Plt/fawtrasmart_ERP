@php
    $getLocal = App::getLocale();
    // جلب إعدادات النظام العامة للتحكم بعرض القوائم
    try {
        $pointsSettings = \App\Models\Ecosystem\PointsGeneralSetting::instance();
        $systemEnabled = $pointsSettings->system_enabled;
    } catch (\Exception $e) {
        $pointsSettings = null;
        $systemEnabled = true; // افتراضي: مفعّل إذا لم يتم تشغيل الـ migration
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
                    <h2 class="brand-text mb-0">إيكوسيستم</h2>
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
            
            {{-- لوحة التحكم --}}
            <li class="nav-item {{ request()->is('$getLocal/ecosystem') && !request()->is('$getLocal/ecosystem/*') ? 'active' : '' }}">
                <a href="{{ route('ecosystem.dashboard') }}">
                    <i class="feather icon-home"></i>
                    <span class="menu-title" data-i18n="Dashboard">لوحة التحكم</span>
                </a>
            </li>

            {{-- نظام النقاط --}}
            @if($systemEnabled)
            <li class="nav-item has-sub">
                <a href="javascript:void(0);">
                    <i class="feather icon-star"></i>
                    <span class="menu-title" data-i18n="PointsSystem">نظام النقاط</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/settings*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.settings.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="PointsSettings">إعدادات النقاط</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/transactions*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.transactions.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="PointsTransactions">معاملات النقاط</span>
                        </a>
                    </li>
                    @if(!$pointsSettings || $pointsSettings->enable_expiry_system)
                    <li class="{{ request()->routeIs('ecosystem.points.batches.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.batches.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="PointsBatches">دفعات النقاط</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.expiry-settings.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.expiry-settings.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ExpirySettings">إعدادات الانتهاء</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </li>
            @endif
            
            {{-- مستخدمي النقاط --}}
            @if($systemEnabled)
            <li class="nav-item has-sub">
                <a href="javascript:void(0);">
                    <i class="feather icon-users"></i>
                    <span class="menu-title" data-i18n="PointsUsers">مستخدمي النقاط</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/users/employees*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.users.employees') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="EmployeesPoints">نقاط الموظفين</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/users/clients*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.users.clients') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ClientsPoints">نقاط العملاء</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- المستويات والترقيات --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_tiers_system))
            <li class="nav-item has-sub">
                <a href="javascript:void(0);">
                    <i class="feather icon-trending-up"></i>
                    <span class="menu-title" data-i18n="TiersLevels">المستويات</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/tiers*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.tiers.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Tiers">إدارة المستويات</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/upgrade-logs*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.upgrade-logs.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="TierUpgrades">سجل الترقيات</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- المكافآت --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_rewards_system))
            <li class="nav-item has-sub">
                <a href="javascript:void(0);">
                    <i class="feather icon-gift"></i>
                    <span class="menu-title" data-i18n="RewardsSystem">المكافآت</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/rewards") || request()->is("$getLocal/ecosystem/points/rewards/*") && !request()->is("*redemptions*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.rewards.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Rewards">إدارة المكافآت</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/rewards/create") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.rewards.create') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="CreateReward">إضافة مكافأة</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/redemptions") || request()->is("$getLocal/ecosystem/points/redemptions/*") && !request()->is("*create*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.redemptions.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Redemptions">طلبات الاستبدال</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/redemptions/create") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.redemptions.create') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="CreateRedemption">طلب استبدال جديد</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- الإنجازات --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_achievements_system))
            <li class="nav-item has-sub">
                <a href="javascript:void(0);">
                    <i class="feather icon-award"></i>
                    <span class="menu-title" data-i18n="AchievementsSystem">الإنجازات</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/achievements") || request()->is("$getLocal/ecosystem/points/achievements/*") && !request()->is("*create*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.achievements.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Achievements">إدارة الإنجازات</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/user-achievements*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.user-achievements.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="UserAchievements">إنجازات المستخدمين</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- التحديات --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_challenges_system))
            <li class="nav-item has-sub {{ request()->is("$getLocal/ecosystem/points/challenges*") ? 'open' : '' }}">
                <a href="javascript:void(0);">
                    <i class="feather icon-target"></i>
                    <span class="menu-title" data-i18n="ChallengesSystem">التحديات</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->is("$getLocal/ecosystem/points/challenges") || request()->is("$getLocal/ecosystem/points/challenges/create") || request()->is("$getLocal/ecosystem/points/challenges/*/edit") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.challenges.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Challenges">إدارة التحديات</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/challenges/teams*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.challenges.teams.index') }}">
                            <i class="feather icon-users"></i>
                            <span class="menu-item" data-i18n="Teams">إدارة الفرق</span>
                        </a>
                    </li>
                    <li class="{{ request()->is("$getLocal/ecosystem/points/challenges/leaderboard*") ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.challenges.leaderboard.index') }}">
                            <i class="feather icon-bar-chart-2"></i>
                            <span class="menu-item" data-i18n="Leaderboard">لوحة المتصدرين</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- نظام الإحالة --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_referrals_system))
            <li class="nav-item has-sub {{ request()->routeIs('ecosystem.points.referrals.*') || request()->routeIs('ecosystem.points.referral-settings.*') ? 'open' : '' }}">
                <a href="javascript:void(0);">
                    <i class="feather icon-users"></i>
                    <span class="menu-title" data-i18n="ReferralSystem">الإحالات</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->routeIs('ecosystem.points.referrals.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.referrals.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="Referrals">إدارة الإحالات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.referral-settings.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.referral-settings.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ReferralSettings">إعدادات الإحالة</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- الإشعارات --}}
            @if($systemEnabled && (!$pointsSettings || $pointsSettings->enable_notifications_system))
            <li class="nav-item has-sub {{ request()->routeIs('ecosystem.points.notification-templates.*') || request()->routeIs('ecosystem.points.notification-logs.*') ? 'open' : '' }}">
                <a href="javascript:void(0);">
                    <i class="feather icon-bell"></i>
                    <span class="menu-title" data-i18n="NotificationsSystem">الإشعارات</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->routeIs('ecosystem.points.notification-templates.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.notification-templates.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="NotificationTemplates">قوالب الإشعارات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.notification-logs.*') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.notification-logs.index') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="NotificationLogs">سجل الإشعارات</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif
            
            {{-- التقارير والإحصائيات - متاح دائماً --}}
            <li class="nav-item has-sub {{ request()->routeIs('ecosystem.points.reports.*') ? 'open' : '' }}">
                <a href="javascript:void(0);">
                    <i class="feather icon-bar-chart-2"></i>
                    <span class="menu-title" data-i18n="Reports">التقارير</span>
                </a>
                <ul class="menu-content">
                    <li class="{{ request()->routeIs('ecosystem.points.reports.points') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.points') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="PointsReport">تقرير النقاط</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.tiers') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.tiers') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="TiersReport">تقرير المستويات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.rewards') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.rewards') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="RewardsReport">تقرير المكافآت</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.achievements') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.achievements') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="AchievementsReport">تقرير الإنجازات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.challenges') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.challenges') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ChallengesReport">تقرير التحديات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.referrals') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.referrals') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ReferralsReport">تقرير الإحالات</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.expiry') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.expiry') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="ExpiryReport">تقرير انتهاء الصلاحية</span>
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('ecosystem.points.reports.daily-summary') ? 'active' : '' }}">
                        <a href="{{ route('ecosystem.points.reports.daily-summary') }}">
                            <i class="feather icon-circle"></i>
                            <span class="menu-item" data-i18n="DailySummary">الملخص اليومي</span>
                        </a>
                    </li>
                </ul>
            </li>

            {{-- الإعدادات العامة --}}
            <li class="nav-item {{ request()->routeIs('ecosystem.points.general-settings.*') ? 'active' : '' }}">
                <a href="{{ route('ecosystem.points.general-settings.index') }}">
                    <i class="feather icon-sliders"></i>
                    <span class="menu-title" data-i18n="GeneralSettings">الإعدادات العامة</span>
                </a>
            </li>

        </ul>
    </div>
</div>

@endif
