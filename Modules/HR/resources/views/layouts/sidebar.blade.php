@php
    use App\Constants\planFeatures\HrPlanFeatures;
    use App\Support\PlanLock;

    /*
        حدود باقة فوترة — **قفلٌ لا إخفاء**.

        صلاحية النظام (`@canany`) تُخفي البند: من لا يدير الرواتب لا شأن له
        بها. أمّا الميزة خارج الباقة فتُقفَل وتبقى ظاهرة — إخفاؤها يجعل
        المنشأة لا تعرف أنها موجودة فلا تشتريها.

        والمقفول يقود إلى شاشة الاشتراك: شاشتُه تردّه بـ402 على كل حال
        (وسيط `plan:` في Modules/HR/routes/web.php).
    */
    $planLockUrl = PlanLock::url();

    $plan = [
        'employees' => ! PlanLock::locked(HrPlanFeatures::EMPLOYEES),
        'performance' => ! PlanLock::locked(HrPlanFeatures::PERFORMANCE),
        'departments' => ! PlanLock::locked(HrPlanFeatures::DEPARTMENTS),
        'job_titles' => ! PlanLock::locked(HrPlanFeatures::JOB_TITLES),
        'levels' => ! PlanLock::locked(HrPlanFeatures::FUNCTIONAL_LEVELS),
        'attendance' => ! PlanLock::locked(HrPlanFeatures::ATTENDANCE),
        'shifts' => ! PlanLock::locked(HrPlanFeatures::SHIFTS),
        'attendance_rules' => ! PlanLock::locked(HrPlanFeatures::ATTENDANCE_RULES),
        'leaves' => ! PlanLock::locked(HrPlanFeatures::LEAVES),
        'leave_permissions' => ! PlanLock::locked(HrPlanFeatures::LEAVE_PERMISSIONS),
        'payroll' => ! PlanLock::locked(HrPlanFeatures::PAYROLL),
        'payslips' => ! PlanLock::locked(HrPlanFeatures::PAYSLIPS),
        'salary_items' => ! PlanLock::locked(HrPlanFeatures::SALARY_ITEMS),
        'contracts' => ! PlanLock::locked(HrPlanFeatures::CONTRACTS),
        'advances' => ! PlanLock::locked(HrPlanFeatures::ADVANCES),
        'deductions' => ! PlanLock::locked(HrPlanFeatures::DEDUCTIONS),
        'dashboard' => ! PlanLock::locked(HrPlanFeatures::DASHBOARD),
        'reports' => ! PlanLock::locked(HrPlanFeatures::REPORTS),
        'commissions' => ! PlanLock::locked(HrPlanFeatures::COMMISSIONS),
    ];

    $planKeys = [
        'employees' => HrPlanFeatures::EMPLOYEES,
        'performance' => HrPlanFeatures::PERFORMANCE,
        'departments' => HrPlanFeatures::DEPARTMENTS,
        'job_titles' => HrPlanFeatures::JOB_TITLES,
        'levels' => HrPlanFeatures::FUNCTIONAL_LEVELS,
        'attendance' => HrPlanFeatures::ATTENDANCE,
        'shifts' => HrPlanFeatures::SHIFTS,
        'attendance_rules' => HrPlanFeatures::ATTENDANCE_RULES,
        'leaves' => HrPlanFeatures::LEAVES,
        'leave_permissions' => HrPlanFeatures::LEAVE_PERMISSIONS,
        'payroll' => HrPlanFeatures::PAYROLL,
        'payslips' => HrPlanFeatures::PAYSLIPS,
        'salary_items' => HrPlanFeatures::SALARY_ITEMS,
        'contracts' => HrPlanFeatures::CONTRACTS,
        'advances' => HrPlanFeatures::ADVANCES,
        'deductions' => HrPlanFeatures::DEDUCTIONS,
        'dashboard' => HrPlanFeatures::DASHBOARD,
        'reports' => HrPlanFeatures::REPORTS,
        'commissions' => HrPlanFeatures::COMMISSIONS,
    ];

    /** سمات البند المقفول: تلميحٌ يسمّي الميزة الناقصة. */
    $planAttrs = function (string $slot) use ($plan, $planKeys) {
        return ($plan[$slot] ?? true) ? '' : ' title="'.e(PlanLock::reason($planKeys[$slot])).'"';
    };

    /*
        شارة الامتلاء — تظهر عند بلوغ حدّ الميزة أو مقاربته (٨٠٪).

        **ولا تُخفي البند**: من امتلأ سقفُه يحتاج شاشته أكثر من غيره — ليحذف
        ما لم يعد مستعمَلاً أو ليرى ما عنده قبل شراء إضافة. وإخفاؤها يقفل
        عليه باب الخروج من الحدّ نفسه.

        `limit()` لا `liveLimit()`: قراءةٌ تقع مع كل تحميلِ صفحة، وتلك تُطلق
        `COUNT(*)` لكل ميزة. والمنعُ الفعليّ في `plan.limit` وفي المراقب.
    */
    $planLimits = [];

    foreach ([
        'employees' => HrPlanFeatures::LIMIT_EMPLOYEES,
        'departments' => HrPlanFeatures::LIMIT_DEPARTMENTS,
        // لا شارةَ امتلاءٍ للحضور: حدُّه أُحيل إلى «الموظفون»
        'shifts' => HrPlanFeatures::LIMIT_SHIFTS,
        'leaves' => HrPlanFeatures::LIMIT_LEAVE_REQUESTS,
        'contracts' => HrPlanFeatures::LIMIT_CONTRACTS,
        'payslips' => HrPlanFeatures::LIMIT_PAYSLIPS,
        'advances' => HrPlanFeatures::LIMIT_ADVANCE_REQUESTS,
    ] as $slot => $feature) {
        $l = app(\App\Services\Fawtra\SubscriptionGate::class)->limit($feature);

        $planLimits[$slot] = (! $l['known'] || $l['unlimited'] || (int) $l['percent'] < 80)
            ? null
            : [
                'text' => $l['exhausted'] ? 'امتلأ' : $l['percent'].'٪',
                'title' => sprintf('%s: %d / %d', $l['label'], (int) $l['used'], (int) $l['allowance']),
                'exhausted' => (bool) $l['exhausted'],
            ];
    }

    /**
     * علامة البند: قفلٌ إن كانت الميزة خارج الباقة، وإلا شارةُ الامتلاء إن
     * قارب الحدّ. ولا تجتمعان: ميزةٌ لم تُشترَ لا حدَّ لها يُعرض.
     */
    $planMark = function (string $slot) use ($plan, $planLimits) {
        if (! ($plan[$slot] ?? true)) {
            return PlanLock::menuBadge();
        }

        $u = $planLimits[$slot] ?? null;

        if ($u === null) {
            return '';
        }

        $color = $u['exhausted'] ? 'var(--app-danger, #EA5455)' : 'var(--app-warning, #FF9F43)';

        return '<span class="badge badge-pill float-right" title="'.e($u['title']).'"'
            .' style="background:'.$color.';color:#fff;font-size:10px;line-height:1.4;padding:2px 7px;">'
            .e($u['text']).'</span>';
    };
@endphp

@php
    $getLocal = App::getLocale();
    // جلب جميع الإعدادات من جدول application_settings
    if (!isset($settings)) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }
@endphp


{{--
    قائمةُ الموارد البشرية — واحدةٌ يراها الجميع.

    **ما كان قبلها:** الملفّ يفتح بـ
    `@if (auth()->user()->isEmployee()) @include('layouts.sidebar') @else`،
    فكلُّ مستخدمٍ مربوطٍ بملفّ موظف — و`isEmployee()` ليست إلا
    `employee_id !== null` — يرى على شاشات الموارد البشرية **قائمةَ الموظف
    الميدانيّ** (خط السير، محافظ العملاء، سندات الصرف، أهدافي والتحصيل)
    بدل قائمة الوحدة التي هو فيها.

    والشرطُ يخلط أمرين: «موظفٌ له ملفّ» ليس «موظفٌ بلا صلاحيات إدارية».
    فمديرُ الموارد البشرية موظفٌ له ملفٌّ بالضرورة، فيُحرَم من قائمة وحدته
    في كل شاشةٍ منها — ولا وجهَ لأن تُبدَّل قائمةُ الوحدة بقائمة وحدةٍ أخرى
    بناءً على وجود صفٍّ في `employees`.

    **كلُّ بندٍ يسأل صلاحيةَ شاشته نفسَها** (`<الشاشة>.index` — ما يحرس
    مسارَ القائمة)، ولا صلاحياتِ «هو»: الموظفُ الميدانيُّ يحمل صلاحيةَ
    الشاشة مع `data_scope_own`، فيرى البندَ ولا يرى في الشاشة إلا نفسَه.

    @see \Modules\Client\resources\views\layouts\sidebar عولجت فيها العلّةُ نفسها
--}}
{{-- `data-auto-open-active="false"`: لا مجموعةَ تُفتح من تلقائها.

     كان صنفُ `open` يُرسَل من الخادم مع `active`، ويُضيفه `app-menu.js`
     كذلك لكلّ أبٍ لعنصرٍ نشط. فمن فتح مجموعاتٍ ثمّ خرج من الوحدة وعاد
     وجدها مفتوحةً كما تركها — لأنّ المسار نفسَه يُطابق الشرطَ في كلّ
     صفحةٍ من الوحدة، لا لأنّ حالةً حُفظت. والقوائم الآن تُفتح بالضغط
     وحده، ويبقى `active` للتمييز البصري بلا فتح. --}}
<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true" data-auto-open-active="false">
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
            <li class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}">
                    <i class="feather icon-home"></i>
                    <span class="menu-title">الرئيسية</span>
                </a>
            </li>


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

            @endif{{-- الموظفين --}}
            @if (canAny(['employees.index', 'performance.index', 'employee_settings.manage']))
                @if (isset($settings['employees']) && $settings['employees'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/hr/*") ? 'active' : '' }}">
                        <a href="#">
                            <i class="feather icon-users"></i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Employees') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('employees.index'))
                                <li class="{{ $plan['employees'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['employees'] ? route('employee.index') : $planLockUrl }}"{!! $planAttrs('employees') !!}><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/employee/*") ? 'active' : '' }}"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Employee_Management') }}</span>{!! $planMark('employees') !!}</a>
                                </li>
                            @endif

                            {{-- إعداداتُ ملفّ الموظف وقواعدُ الهيكل التنظيمي — تحت سور
                                 باقة «الموظفون» نفسِه الذي يحمي مساراتها. --}}
                            @if (hasPermission('employee_settings.manage'))
                                <li class="{{ $plan['employees'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['employees'] ? route('hr.employee_settings.index') : $planLockUrl }}"{!! $planAttrs('employees') !!}><i
                                            class="feather icon-circle {{ request()->is("$getLocal/hr/employee/settings") ? 'active' : '' }}"></i><span
                                            class="menu-item">إعدادات الموظفين والهيكل</span>{!! $planMark('employees') !!}</a>
                                </li>
                            @endif

                            @if (hasPermission('performance.index'))
<li class="{{ $plan['performance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['performance'] ? route('hr.employee_performance.index') : $planLockUrl }}"{!! $planAttrs('performance') !!}><i class="feather icon-circle"></i><span class="menu-item"
                                data-i18n="eCommerce">أداء الموظفين</span>{!! $planMark('performance') !!}</a></li>
                            @endif



                        </ul>

                    </li>
                @endif
            @endif
            {{-- لوحة الموارد البشرية — بصلاحيتها (`hr_dashboard.index`)؛ والنطاقُ يحدّد من تشمل --}}
            @if (isset($settings['employees']) && $settings['employees'] === 'active')
                @if (hasPermission('hr_dashboard.index'))
                    <li class="nav-item {{ request()->is('*hr/dashboard') || request()->is('*hr/employee-performance*') ? 'active' : '' }}">
                        <a href="#">
                            <i class="feather icon-trending-up"></i><span class="menu-title" data-i18n="Dashboard">أداء الفريق</span>
                        </a>
                        <ul class="menu-content">
                            <li class="{{ $plan['dashboard'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['dashboard'] ? route('hr.dashboard.index') : $planLockUrl }}"{!! $planAttrs('dashboard') !!}><i class="feather icon-circle {{ request()->is('*hr/dashboard') ? 'active' : '' }}"></i><span class="menu-item" data-i18n="eCommerce">داشبورد أداء الموظفين</span>{!! $planMark('dashboard') !!}</a></li>
                        </ul>
                    </li>
                @endif
            @endif
            {{-- الهيكل التنظيمي — كلُّ شاشةٍ بصلاحيتها: الأقسام، المسمّيات، المستويات، الأنواع --}}
            @if (canAny(['departments.index', 'job_titles.index', 'job_levels.index', 'job_types.index', 'employee_settings.manage']))
                @if (isset($settings['organizational_structure']) && $settings['organizational_structure'] === 'active')
                    <li class=" nav-item {{ request()->is("$getLocal/OrganizationalStructure/*") ? 'active' : '' }}">
                        <a href="#">
                            <i class="feather icon-layers">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Organizational_Structure') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('job_titles.index'))
                            <li class="{{ $plan['job_titles'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['job_titles'] ? route('JobTitles.index') : $planLockUrl }}"{!! $planAttrs('job_titles') !!}><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/JobTitles/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="Analytics">{{ trans('main_trans.Job_Titles_Management') }}</span>{!! $planMark('job_titles') !!}</a>
                            </li>
                            @endif

                            @if (hasPermission('departments.index'))
                            <li class="{{ $plan['departments'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['departments'] ? route('department.index') : $planLockUrl }}"{!! $planAttrs('departments') !!}><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/department/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Departments_Management') }}</span>{!! $planMark('departments') !!}</a>
                            </li>
                            @endif

                            @if (hasPermission('job_levels.index'))
                            <li class="{{ $plan['levels'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['levels'] ? route('ManagingFunctionalLevels.index') : $planLockUrl }}"{!! $planAttrs('levels') !!}><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingFunctionalLevels/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Job_Levels_Management') }}</span>{!! $planMark('levels') !!}</a>
                            </li>
                            @endif
                            @if (hasPermission('job_types.index'))
                            <li class="{{ $plan['levels'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['levels'] ? route('ManagingJobTypes.index') : $planLockUrl }}"{!! $planAttrs('levels') !!}><i
                                        class="feather icon-circle {{ request()->is("$getLocal/OrganizationalStructure/ManagingJobTypes/*") ? 'active' : '' }}"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">{{ trans('main_trans.Job_Types_Management') }}</span>{!! $planMark('levels') !!}</a>
                            </li>
                            @endif
                            {{-- القواعدُ في شاشة إعدادات الموظفين — تبويبُ «الهيكل التنظيمي» منها --}}
                            @if (hasPermission('employee_settings.manage'))
                            <li class="{{ $plan['employees'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['employees'] ? route('hr.employee_settings.index', ['tab' => 'org']) : $planLockUrl }}"{!! $planAttrs('employees') !!}><i
                                        class="feather icon-circle"></i><span
                                        class="menu-item">إعدادات الهيكل</span>{!! $planMark('employees') !!}</a>
                            </li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif
            {{--
                الحضور — سبعةُ بنودٍ بدل خمسة عشر.

                كلُّ بندٍ يفتح أوّلَ شاشةٍ من مجموعته يملك المستخدمُ صلاحيتَها،
                والتنقّلُ بين شاشات المجموعة من تبويباتٍ أعلاها
                (`hr::attendance._partials.section-tabs`). والبوّابةُ اتحادُ
                حرّاس الشاشات كلِّها — نطاقُ الرؤية يصفّي البيانات داخلها.
            --}}
            @php
                $attGroups = [
                    'daily' => [
                        ['attendance_days.index', 'hr.attendance-log.index'],
                        ['attendance_team.index', 'hr.team-board.index'],
                        ['attendance_records.index', 'hr.attendance-punches.index'],
                        ['attendance_corrections.index', 'hr.attendance-corrections.index'],
                        ['attendance_corrections.approve', 'hr.attendance-correction-approvals.index'],
                        ['attendance_books.index', 'attendance_sheets.index'],
                    ],
                    'leaves' => [
                        ['leave_requests.index', 'attendance.leave_requests.index'],
                        ['leave_permissions.index', 'leave_permissions.index'],
                        ['leave_balances.index', 'employee_leave_balances.index'],
                    ],
                    'shifts' => [
                        ['shifts.index', 'shift_management.index'],
                    ],
                    'devices' => [
                        ['attendance_places.index', 'attendance.settings.geofences.index'],
                        ['attendance_devices.index', 'hr.device-monitor.index'],
                        ['attendance_cards.index', 'barcode.cards'],
                        ['attendance_devices.index', 'machines.index'],
                    ],
                ];
                $attFirst = function (string $group) use ($attGroups) {
                    foreach ($attGroups[$group] as [$perm, $route]) {
                        if (hasPermission($perm)) {
                            return $route;
                        }
                    }

                    return null;
                };
                $attDaily = $attFirst('daily');
                $attLeaves = $attFirst('leaves');
                $attShifts = $attFirst('shifts');
                $attDevices = $attFirst('devices');
                $attSelf = hasPermission('attendance_registration.mobile');
                $attSettings = canAny(['attendance_settings.manage', 'holidays.index', 'attendance_rules.index', 'leave_types.index', 'leave_policies.index']);
                // شاشةُ جهاز الفرع لحساب تابلت الاستقبال — لم يكن لها بندٌ فلا تُفتح إلا بكتابة عنوانها
                $attKiosk = hasPermission('attendance_registration.kiosk');
                $attAny = $attDaily || $attLeaves || $attShifts || $attDevices || $attSelf || $attKiosk || $attSettings;

                $attIs = fn (array $paths) => request()->is(array_merge(
                    array_map(fn ($p) => "$getLocal/hr/$p", $paths),
                    array_map(fn ($p) => "$getLocal/hr/$p/*", $paths),
                ));
                $attDailyPaths = ['attendance-log', 'team-board', 'attendance-punches', 'attendance-corrections', 'attendance-correction-approvals', 'attendance-sheets', 'attendanceDays'];
                $attLeavePaths = ['leave_requests', 'leave-permissions', 'employee-leave-balances'];
                $attShiftPaths = ['shift_management', 'custom-shifts'];
                $attDevicePaths = ['settings/geofences','settings/kiosks', 'device-monitor', 'barcode', 'machines'];
            @endphp
            @if ($attAny && isset($settings['employee_attendance']) && $settings['employee_attendance'] === 'active')
                <li class="nav-item {{ $attIs(array_merge(['attendance', 'field-attendance', 'settings'], $attDailyPaths, $attLeavePaths, $attShiftPaths, $attDevicePaths)) ? 'active' : '' }}"><a href="#">
                        <i class="feather icon-user-check"></i><span class="menu-title" data-i18n="Dashboard">
                            {{ trans('main_trans.Attendance') }}</span>
                    </a>
                    <ul class="menu-content">
                        <li class="{{ $plan['attendance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance'] ? route('hr.attendance.hub') : $planLockUrl }}"{!! $planAttrs('attendance') !!}><i
                                    class="feather icon-circle {{ $attIs(['attendance']) ? 'active' : '' }}"></i><span
                                    class="menu-item">لوحة الحضور</span>{!! $planMark('attendance') !!}</a>
                        </li>

                        @if ($attDaily)
                            <li class="{{ $plan['attendance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance'] ? route($attDaily) : $planLockUrl }}"{!! $planAttrs('attendance') !!}><i
                                        class="feather icon-circle {{ $attIs($attDailyPaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الحضور اليومي</span>{!! $planMark('attendance') !!}</a>
                            </li>
                        @endif

                        @if ($attLeaves)
                            <li class="{{ $plan['leaves'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['leaves'] ? route($attLeaves) : $planLockUrl }}"{!! $planAttrs('leaves') !!}><i
                                        class="feather icon-circle {{ $attIs($attLeavePaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الإجازات</span>{!! $planMark('leaves') !!}</a>
                            </li>
                        @endif

                        @if ($attShifts)
                            <li class="{{ $plan['shifts'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['shifts'] ? route($attShifts) : $planLockUrl }}"{!! $planAttrs('shifts') !!}><i
                                        class="feather icon-circle {{ $attIs($attShiftPaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الورديات</span>{!! $planMark('shifts') !!}</a>
                            </li>
                        @endif

                        @if ($attDevices)
                            <li class="{{ $plan['attendance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance'] ? route($attDevices) : $planLockUrl }}"{!! $planAttrs('attendance') !!}><i
                                        class="feather icon-circle {{ $attIs($attDevicePaths) ? 'active' : '' }}"></i><span
                                        class="menu-item">الأجهزة وأماكن التسجيل</span>{!! $planMark('attendance') !!}</a>
                            </li>
                        @endif

                        @if ($attSelf)
                            <li class="{{ $plan['attendance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance'] ? route('hr.field-attendance.index') : $planLockUrl }}"{!! $planAttrs('attendance') !!}><i
                                        class="feather icon-circle {{ $attIs(['field-attendance']) ? 'active' : '' }}"></i><span
                                        class="menu-item">سجّل حضوري</span>{!! $planMark('attendance') !!}</a>
                            </li>
                        @endif

                        @if ($attKiosk)
                            <li class="{{ $plan['attendance'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance'] ? route('barcode.scan') : $planLockUrl }}"{!! $planAttrs('attendance') !!}><i
                                        class="feather icon-circle {{ $attIs(['barcode/scan']) ? 'active' : '' }}"></i><span
                                        class="menu-item">شاشة جهاز الفرع</span>{!! $planMark('attendance') !!}</a>
                            </li>
                        @endif

                        @if ($attSettings)
                            <li class="{{ $plan['attendance_rules'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['attendance_rules'] ? route('attendance.settings.index') : $planLockUrl }}"{!! $planAttrs('attendance_rules') !!}><i
                                        class="feather icon-circle {{ request()->is("$getLocal/hr/settings/*") && ! $attIs(['settings/geofences', 'settings/kiosks']) ? 'active' : '' }}"></i><span
                                        class="menu-item">الإعدادات</span>{!! $planMark('attendance_rules') !!}</a>
                            </li>
                        @endif
                    </ul>
                </li>
            @endif
            {{-- المرتبات --}}
            @if (canAny([
                        'contracts.index',
                        'payroll.index',
                        'payslips.index',
                        'disbursements.index',
                        'eos.index',
                        'insurance.index', 'insurance.show',
                        'loans.index',
                        'loans.create',
                        'deductions.index',
                        'commissions.index',
                        'payroll_settings.manage',
                        'contracts.notifications', 'salary_items.index',
                        'overtime.settings', 'insurance.settings', 'bank_files.index',
                    ]))
                @if (isset($settings['salaries']) && $settings['salaries'] === 'active')
                    <li class=" nav-item"><a href="#">
                            <i class="feather icon-dollar-sign">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Salaries') }}</span>
                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('contracts.index'))
                                <li class="{{ $plan['contracts'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['contracts'] ? route('Contracts.index') : $planLockUrl }}"{!! $planAttrs('contracts') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="Analytics">{{ trans('main_trans.Contracts') }}</span>{!! $planMark('contracts') !!}</a>
                                </li>
                            @endif

                            @if (hasPermission('payroll.index'))
                                <li class="{{ $plan['payroll'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['payroll'] ? route('PayrollProcess.index') : $planLockUrl }}"{!! $planAttrs('payroll') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Payroll') }}</span>{!! $planMark('payroll') !!}</a>
                                </li>
                            @endif

                            @if (hasPermission('payslips.index'))
                                {{-- قسائم الرواتب (اتفاقيات مستمرة) --}}
                                <li class="{{ $plan['payslips'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['payslips'] ? route('salarySlip.index') : $planLockUrl }}"{!! $planAttrs('payslips') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item" data-i18n="eCommerce">
                                            {{ trans('main_trans.Salary_Slips') }}</span>{!! $planMark('payslips') !!}</a>
                                </li>
                            @endif
                            @if (hasPermission('disbursements.index'))
                                {{-- سجل صرف الرواتب الشهري --}}
                                <li class="{{ $plan['payroll'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['payroll'] ? route('payroll-runs.index') : $planLockUrl }}"{!! $planAttrs('payroll') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item">
                                            سجل صرف الرواتب</span>{!! $planMark('payroll') !!}</a>
                                </li>
                            @endif
                            @if (hasPermission('eos.index'))
                                <li class="{{ $plan['payroll'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['payroll'] ? route('hr.eos.index') : $planLockUrl }}"{!! $planAttrs('payroll') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item">مكافأة نهاية الخدمة</span>{!! $planMark('payroll') !!}</a>
                                </li>
                            @endif
                            @if (canAny(['insurance.index', 'insurance.show']))
                                {{-- سدادُ ما حُجز: الذمّتان تُقفلان نقداً --}}
                                <li class="{{ $plan['payroll'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['payroll'] ? route('hr.gosi.remittances') : $planLockUrl }}"{!! $planAttrs('payroll') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item">سداد التأمينات</span>{!! $planMark('payroll') !!}</a>
                                </li>
                            @endif
                            {{-- «مسير الصرفات» أُلغي: اعتمادُ مسير الرواتب هو الاعتمادُ الوحيد للصرف،
                                 وروابطُه القديمة تُحوَّل إلى سجلات الصرف. --}}

                            {{-- ⚠️ بعد دمج النظامين (2026-05-11): عنصر "السلف" القديم (ancestor) أُزيل
                                 لأن /hr/advance-approvals/ صار يعرض كل السلف (قديمة + حديثة) في شاشة واحدة.
                                 السلف القديمة لا تزال تُعرض وتُسدَّد أقساطها من هناك مباشرةً. --}}

                            {{-- واجهة الموظف: طلب سلفة --}}
                            @if (hasPermission('loans.create'))
                                <li class="{{ $plan['advances'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['advances'] ? route('hr.advance-requests.index') : $planLockUrl }}"{!! $planAttrs('advances') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">طلبات السلف</span>{!! $planMark('advances') !!}</a>
                                </li>
                            @endif

                            {{-- واجهة المدير الموحَّدة: قديم + حديث --}}
                            @if (hasPermission('loans.index'))
                                <li class="{{ $plan['advances'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['advances'] ? route('hr.advance-approvals.index') : $planLockUrl }}"{!! $planAttrs('advances') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">إدارة السلف</span>{!! $planMark('advances') !!}</a>
                                </li>
                            @endif

                            {{-- ============== الخصميات (الحسومات/الجزاءات) ============== --}}
                            {{-- الموظف العادي يرى "خصمياتي" دائماً، الإداريون يرون قائمة الإدارة --}}
                            @if (hasPermission('deductions.index'))
                                <li class="{{ $plan['deductions'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $plan['deductions'] ? route('hr.deductions.index') : $planLockUrl }}"{!! $planAttrs('deductions') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item">الخصميات</span>{!! $planMark('deductions') !!}
                                    </a>
                                </li>
                            @endif



                            <!-- تقرير العمولات المصروفة -->
                            @if (hasPermission('commissions.index'))
                            <li class="{{ $plan['commissions'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['commissions'] ? route('hr.commissions-report.index') : $planLockUrl }}"{!! $planAttrs('commissions') !!}><i class="feather icon-circle"></i><span
                                        class="menu-item"
                                        data-i18n="eCommerce">سجل العمولات</span>{!! $planMark('commissions') !!}</a>
                            </li>
                            @endif

                            {{--
                                ما يُضبَط مرّةً ثم يُنسى انتقل إلى شاشة «الإعدادات»
                                (بلاطاتٌ فيها): بنودُ الرواتب، وكتالوجُ
                                البدلات، والأجرُ الإضافي، والتأميناتُ الاجتماعية،
                                وملفُّ الرواتب البنكي. وبقي في القائمة ما يُفتَح في
                                دورة الشهر.

                                و«إصدار سلفة جديدة» و«إضافة خصمية» رُفعا: كلاهما
                                زرٌّ في رأس شاشته أصلاً، فكانا بنداً يكرّر زرّاً.
                            --}}

                            @if (canAny(['payroll_settings.manage', 'contracts.notifications', 'salary_items.index', 'overtime.settings', 'insurance.settings', 'insurance.index', 'bank_files.index']))
                                <li class="{{ $plan['salary_items'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['salary_items'] ? route('SalarySittings.index') : $planLockUrl }}"{!! $planAttrs('salary_items') !!}><i class="feather icon-circle"></i><span
                                            class="menu-item"
                                            data-i18n="eCommerce">{{ trans('main_trans.Sittings') }}</span>{!! $planMark('salary_items') !!}</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif

            <!-- تقارير الموظفين -->
            {{-- التقارير — بصلاحيات مجموعة `hr_reports.*` نفسِها التي تحرس شاشتها --}}
            @if (canAny(['hr_reports.employees', 'hr_reports.attendance', 'hr_reports.payroll']))
                <li class="nav-item">
                        <a href="{{ route('hr.reports.index') ?? '#' }}">
                            <i class="feather icon-file-text"></i>
                            <span class="menu-title" data-i18n="Dashboard">تقارير الموارد البشرية</span>
                        </a>
                </li>
            @endif





        </ul>
    </div>
</div>
