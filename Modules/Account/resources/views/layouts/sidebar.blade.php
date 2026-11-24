@php
    use App\Constants\planFeatures\AccountPlanFeatures;
    use App\Support\PlanLock;

    /*
        حدود باقة فوترة — **قفلٌ لا إخفاء**.

        صلاحية النظام (`hasPermission`) تُخفي البند: محاسبٌ لا يملك صلاحية
        الخزينة لا شأن له بها. أمّا الميزة خارج الباقة فتُقفَل وتبقى ظاهرة —
        إخفاؤها يجعل المنشأة لا تعرف أنها موجودة فلا تشتريها.

        والمقفول يقود إلى شاشة الاشتراك: شاشتُه تردّه بـ402 على كل حال
        (وسيط `plan:` في Modules/Account/routes/web.php).
    */
    $planLockUrl = PlanLock::url();

    $plan = [
        'journal' => ! PlanLock::locked(AccountPlanFeatures::JOURNAL_ENTRIES),
        'expenses' => ! PlanLock::locked(AccountPlanFeatures::EXPENSES),
        'incomes' => ! PlanLock::locked(AccountPlanFeatures::INCOMES),
        'treasury' => ! PlanLock::locked(AccountPlanFeatures::TREASURY),
        'chart' => ! PlanLock::locked(AccountPlanFeatures::CHART_OF_ACCOUNTS),
        'cost_centers' => ! PlanLock::locked(AccountPlanFeatures::COST_CENTERS),
        'assets' => ! PlanLock::locked(AccountPlanFeatures::ASSETS),
        'reports' => ! PlanLock::locked(AccountPlanFeatures::ACCOUNT_REPORTS),
        'payable_cheques' => ! PlanLock::locked(AccountPlanFeatures::PAYABLE_CHEQUES),
        'received_cheques' => ! PlanLock::locked(AccountPlanFeatures::RECEIVED_CHEQUES),
        'check_books' => ! PlanLock::locked(AccountPlanFeatures::CHECK_BOOKS),
    ];

    $planKeys = [
        'journal' => AccountPlanFeatures::JOURNAL_ENTRIES,
        'expenses' => AccountPlanFeatures::EXPENSES,
        'incomes' => AccountPlanFeatures::INCOMES,
        'treasury' => AccountPlanFeatures::TREASURY,
        'chart' => AccountPlanFeatures::CHART_OF_ACCOUNTS,
        'cost_centers' => AccountPlanFeatures::COST_CENTERS,
        'assets' => AccountPlanFeatures::ASSETS,
        'reports' => AccountPlanFeatures::ACCOUNT_REPORTS,
        'payable_cheques' => AccountPlanFeatures::PAYABLE_CHEQUES,
        'received_cheques' => AccountPlanFeatures::RECEIVED_CHEQUES,
        'check_books' => AccountPlanFeatures::CHECK_BOOKS,
    ];

    /** سمات البند المقفول: تلميحٌ يسمّي الميزة الناقصة. */
    $planAttrs = function (string $slot) use ($plan, $planKeys) {
        return $plan[$slot] ? '' : ' title="'.e(PlanLock::reason($planKeys[$slot])).'"';
    };
@endphp

@php
    $getLocal = App::getLocale();

    /*
        خريطة تفعيل التطبيقات — منها تُقرَّر أيّ مجموعةٍ تظهر في الشريط.

        كان الشرط `if (!isset($settings))` وحده، فيقبل أيَّ متغيّرٍ بهذا
        الاسم يصل إلى القالب. وهذا يقع كثيراً بلا قصد:

          • `AccountsSettingsController::accounting_general()` يمرّر
            `'settings' => AccountingGeneralSetting::current()` — نموذجٌ لا
            خريطة.
          • و`accounts_routing.blade.php` يكتب
            `@forelse ($groups as $groupName => $settings)` — ومتغيّرُ الحلقة
            هذا يتسرّب إلى القالب الأب، لأن `@extends` في Blade يُمرّر
            `get_defined_vars()` كلَّها إلى الأب.

        وفي الحالتين ينجح `isset` فلا تُجلَب الخريطة، ثم يفشل
        `$settings['finance']` صامتاً — فتختفي «المالية» و«الحسابات العامة»
        و«دورة الشيكات» من الشريط في تلك الصفحات وحدها.

        فصار الشرط على النوع لا على الوجود: خريطةٌ نصّية أو تُجلب من جديد.
    */
    $isSettingsMap = isset($settings)
        && is_array($settings)
        && (empty($settings) || is_string(reset($settings)));

    if (! $isSettingsMap) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('account.department.dashboard') }}">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">فوترة</h2>
                </a>
            </li>

            <li class="nav-item nav-toggle">
        <a href="{{ route('account.department.dashboard') }}" class="nav-link modern-nav-toggle pr-0" data-bs-toggle="collapse">
            <i class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i><i
                        class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"
                        data-ticon="icon-disc"></i></a></li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">

        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">
            {{-- المالية --}}
            @php
                $isFinanceActive = request()->routeIs('expenses.*') ||
                                  request()->routeIs('incomes.*') ||
                                  request()->routeIs('treasury.*') ||
                                  request()->routeIs('finance_settings.*');
            @endphp
            @if (canAny([
                        'account.expenses.view',
                        'account.incomes.view',
                        'account.treasury.view',
                        'account.settings.view',
                    ]))
                @if (isset($settings['finance']) && $settings['finance'] === 'active')
                    <li class=" nav-item {{ $isFinanceActive ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-dollar-sign">
                            </i><span class="menu-title"
                                data-i18n="Dashboard">{{ trans('main_trans.Financial') }}</span>
                        </a>

                        <ul class="menu-content">
                            @if (hasPermission('account.expenses.view'))
                                <li class="{{ request()->routeIs('expenses.index') ? 'active' : '' }} {{ $plan['expenses'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['expenses'] ? route('expenses.index') : $planLockUrl }}"{!! $planAttrs('expenses') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Expenses') }}</span>{!! $plan['expenses'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.incomes.view'))
                                <li class="{{ request()->routeIs('incomes.index') ? 'active' : '' }} {{ $plan['incomes'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['incomes'] ? route('incomes.index') : $planLockUrl }}"{!! $planAttrs('incomes') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Receipts') }}</span>{!! $plan['incomes'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.treasury.view'))
                                <li class="{{ request()->routeIs('treasury.index') ? 'active' : '' }} {{ $plan['treasury'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['treasury'] ? route('treasury.index') : $planLockUrl }}"{!! $planAttrs('treasury') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Cash_and_Bank_Accounts') }}</span>{!! $plan['treasury'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.settings.view'))
                                <li class="{{ request()->routeIs('finance_settings.index') ? 'active' : '' }} {{ $plan['treasury'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['treasury'] ? route('finance_settings.index') : $planLockUrl }}"{!! $planAttrs('treasury') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Financial_Settings') }}</span>{!! $plan['treasury'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif
                        </ul>

                    </li>
                @endif
            @endif

            {{-- الحسابات العامة --}}
            @php
                $isAccountsActive = request()->routeIs('journal.*') ||
                                   request()->routeIs('accounts_chart.*') ||
                                   request()->routeIs('cost_centers.*') ||
                                   request()->routeIs('Assets.*') ||
                                   request()->routeIs('accounts_settings.*') ||
                                   request()->routeIs('GeneralAccountReports.*');
            @endphp
            @if (canAny([
                        'account.journal.view',
                        'account.journal.create',
                        'account.chart.view',
                        'account.cost_centers.view',
                        'account.chart.general_ledger',
                        'account.assets.view',
                        'account.settings.view',
                    ]))
                @if (isset($settings['general_accounts_journal_entries']) && $settings['general_accounts_journal_entries'] === 'active')
                    <li class=" nav-item {{ $isAccountsActive ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-pie-chart"></i>
                            <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.General_Accounts') }}</span>
                        </a>

                        <ul class="menu-content">
                            @if (hasPermission('account.journal.view'))
                                <li class="{{ request()->routeIs('journal.index') ? 'active' : '' }} {{ $plan['journal'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['journal'] ? route('journal.index') : $planLockUrl }}"{!! $planAttrs('journal') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Journal_Entries') }}</span>{!! $plan['journal'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.journal.create'))
                                <li class="{{ request()->routeIs('journal.create') ? 'active' : '' }} {{ $plan['journal'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['journal'] ? route('journal.create') : $planLockUrl }}"{!! $planAttrs('journal') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Add_Entry') }}</span>{!! $plan['journal'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.chart.view'))
                                <li class="{{ request()->routeIs('accounts_chart.view_root') ? 'active' : '' }} {{ $plan['chart'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['chart'] ? route('accounts_chart.view_root') : $planLockUrl }}"{!! $planAttrs('chart') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Chart_of_Accounts') }}</span>{!! $plan['chart'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.cost_centers.view'))
                                <li class="{{ request()->routeIs('cost_centers.index') ? 'active' : '' }} {{ $plan['cost_centers'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['cost_centers'] ? route('cost_centers.index') : $planLockUrl }}"{!! $planAttrs('cost_centers') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Cost_Centers') }}</span>{!! $plan['cost_centers'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.chart.general_ledger'))
                                <li class="{{ request()->routeIs('journal.generalLedger') ? 'active' : '' }} {{ $plan['chart'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['chart'] ? route('journal.generalLedger') : $planLockUrl }}"{!! $planAttrs('chart') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="Analytics">حساب الاستاذ</span>{!! $plan['chart'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.assets.view'))
                                <li class="{{ request()->routeIs('Assets.index') ? 'active' : '' }} {{ $plan['assets'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['assets'] ? route('Assets.index') : $planLockUrl }}"{!! $planAttrs('assets') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Assets') }}</span>{!! $plan['assets'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.settings.view'))
                                <li class="{{ request()->routeIs('accounts_settings.index') ? 'active' : '' }} {{ $plan['chart'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['chart'] ? route('accounts_settings.index') : $planLockUrl }}"{!! $planAttrs('chart') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.General_Accounts_Settings') }}</span>{!! $plan['chart'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                        </ul>
                    </li>
                @endif
            @endif

            {{-- الشيكات --}}
            @php
                $isChequesActive = request()->routeIs('payable_cheques.*') ||
                                  request()->routeIs('received_cheques.*') ||
                                  request()->routeIs('checksReports.*');
            @endphp
            @if (canAny(['account.payable_cheques.view', 'account.received_cheques.view', 'account.cheque_books.view']))
                @if (isset($settings['cheque_cycle']) && $settings['cheque_cycle'] === 'active')
                    <li class=" nav-item {{ $isChequesActive ? 'active open' : '' }}">
                        <a href="#">
                            <i class="feather icon-dollar-sign"></i>
                            <span class="menu-title" data-i18n="Dashboard">{{ trans('main_trans.Cheques_Cycle') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('account.payable_cheques.view'))
                                <li class="{{ request()->routeIs('payable_cheques.index') ? 'active' : '' }} {{ $plan['payable_cheques'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['payable_cheques'] ? route('payable_cheques.index') : $planLockUrl }}"{!! $planAttrs('payable_cheques') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="Analytics">{{ trans('main_trans.Paid_Cheques') }}</span>{!! $plan['payable_cheques'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.received_cheques.view'))
                                <li class="{{ request()->routeIs('received_cheques.index') ? 'active' : '' }} {{ $plan['received_cheques'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['received_cheques'] ? route('received_cheques.index') : $planLockUrl }}"{!! $planAttrs('received_cheques') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">{{ trans('main_trans.Received_Cheques') }}</span>{!! $plan['received_cheques'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif

                            @if (hasPermission('account.cheque_books.view'))
                                <li class="{{ request()->routeIs('check_books.index') ? 'active' : '' }} {{ $plan['check_books'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['check_books'] ? route('check_books.index') : $planLockUrl }}"{!! $planAttrs('check_books') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item" data-i18n="eCommerce">دفاتر الشيكات</span>{!! $plan['check_books'] ? '' : PlanLock::menuBadge() !!}
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif

            <!-- تقارير المالية -->


                @if (hasPermission('account.reports.view'))
                <li class="nav-item {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                    <a href="{{ $plan['reports'] ? route('GeneralAccountReports.index') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                        <i class="feather icon-file-text"></i>
                        <span class="menu-title" data-i18n="Dashboard">تقرير الحسابات العامة</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                    </a>
                </li>
                @endif

                @if (hasPermission('account.reports.cheques'))
                <li class="nav-item {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                    <a href="{{ $plan['reports'] ? route('checksReports.index') : $planLockUrl }}"{!! $planAttrs('reports') !!}>
                        <i class="feather icon-file-text"></i>
                        <span class="menu-title" data-i18n="Dashboard">تقرير الشيكات</span>{!! $plan['reports'] ? '' : PlanLock::menuBadge() !!}
                    </a>
                </li>
                @endif
        </ul>
    </div>
</div>
