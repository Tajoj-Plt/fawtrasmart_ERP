{{--
    ═══════════════ لماذا أسماءٌ مسبوقةٌ بـ`pos` ═══════════════

    `@include` يمرّر متغيّرات الأب إلى الابن. وهذا الشريطُ يُضمّن
    `layouts.sidebar` الجذري، وهو يُضمّن `sales::layouts.partials.sales-menu`
    — وذاك يحسب مساعداتِه داخل `if (! isset($planHref))`، أي أنه يعتبر وجودَ
    `$planHref` دليلاً على أنّ الأب حسب **كلَّ** ما يلزمه.

    فلمّا سُمّيت مساعداتُ هذا الشريط بالأسماء العامّة (`$plan`, `$planKeys`,
    `$planHref`, `$planAttrs`, `$planLockUrl`) وقع أمران:

      • `$appOn` لم يُعرَّف إطلاقاً ⇒ الشاشةُ تسقط بـ«Undefined variable».
      • وقبل السقوط، `$plan` و`$planKeys` كانتا **مفاتيحَ باقة نقاط البيع**
        تُقرأ في قائمة المبيعات — فبنودُها تُقفل وتُفتح بمفاتيحَ ليست لها.

    والبادئةُ تحسم ذلك بلا الاعتماد على ترتيب التضمين.
--}}
{{--
    القائمة الجانبية لوحدة نقاط البيع.

    **ما كانت**

    نسخةً كاملة من قائمة المبيعات القديمة — أحد عشر مجموعةً فيها
    المبيعات والأقساط والعمولات وأوامر التوريد والعضويات ووكلاء التأمين
    — ونقاطُ البيع بندٌ صغير بينها. فمن يفتح شاشة «بدء البيع» يجد أمامه
    قائمةَ قسمٍ آخر، ويبحث عن «الجلسات» بين أربعين رابطاً ليست له.
    ونسخةً جامدة كذلك: كل إصلاحٍ في قائمة المبيعات لا يصل إليها.

    **ما صارت**

    نقاط البيع وحدها، على بنية قائمة المخزون
    (`stock::layouts.sidebar`) ومظهرها — نفس ما تعمل به قائمة المبيعات.
    والرابط إلى بقيّة الأقسام من شاشة التطبيقات لا من داخل القائمة.

    الصلاحيات تُفحَص بـ`canAny()`/`hasPermission()` لا بـ
    `hasAnyPermission()` مباشرةً: الأخيرة ترمي `PermissionDoesNotExist`
    لاسمٍ لم يُزرع بعد، فتُسقط الصفحة كلّها بخطأ 500 بدل أن تُخفي رابطاً.

    الرابط يظهر متى اجتمع شرطان: صلاحيةُ المستخدم، وتفعيلُ التطبيق في
    «شاشة التطبيقات» (`application_settings`).
--}}

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $getLocal = App::getLocale();

    /*
     * إعدادات التطبيقات — المفتاح ← الحالة.
     *
     * الفحص بالنوع لا بـ`isset()` وحدها: `@extends` يمرّر متغيّرات قالب
     * الشاشة كلَّها إلى القالب الأمّ ومنها إلى هنا، فأيّ شاشة تُعرّف
     * `$settings` لنفسها — وهو اسمٌ شائع — تطمس هذه المصفوفة وتختفي
     * مجموعات القائمة بلا خطأ يُرى.
     */
    $settings = (isset($settings) && is_array($settings))
        ? $settings
        : \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();

    $posOn = ($settings['pos'] ?? null) === 'active';

    $canSell = canAny([
        'points_sale_add_discount',
        'points_sale_edit_product_prices',
        'points_sale_open_sessions_all',
        'points_sale_open_sessions_own',
    ]);

    $canSessions = canAny([
        'points_sale_view_own_sessions',
        'points_sale_close_sessions_all',
        'points_sale_close_sessions_own',
        'points_sale_confirm_close_sessions_all',
        'points_sale_confirm_close_sessions_own',
    ]);

    $canReports = canAny(['pos_view_reports', 'pos_reports_view', 'pos.reports.profit', 'pos.reports.variance']);
    $canSettings = hasPermission('pos_manage_settings');

    // الدرجُ النقدي والأجهزةُ والورديات — أقسامٌ لم تكن لها روابط
    $canCashDrawer = canAny([
        'pos.cash_drawer.view',
        'pos.cash_drawer.pay_in',
        'pos.cash_drawer.pay_out',
        'pos.cash_drawer.drop',
        'pos.cash_drawer.approve',
    ]);

    $canDevices = canAny(['pos.devices.view', 'pos.devices.manage']);
    $canShifts = canAny(['pos.shifts.view', 'pos.shifts.manage']);

    /*
        «بانتظار التأكيد» — بندٌ يظهر لمن يؤكّد وحده.

        العدُّ هنا لا في المتحكّم: القائمة تُعرض في كل شاشة، والرقمُ هو ما
        يجعل المشرفَ يعلم أن عليه عملاً دون أن يفتح الشاشة.
    */
    $canConfirm = canAny([
        'points_sale_confirm_close_sessions_all',
        'points_sale_confirm_close_sessions_own',
    ]);

    /*
        ═══════════════ حدود باقة فوترة — قفلٌ لا إخفاء ═══════════════

        الفرقُ بين الحارسَين مقصود، وهو نفسُ ما تعمل به قائمةُ المخزون:

          • **صلاحيةُ النظام** (`canAny`/`hasPermission`) **تُخفي**. كاشيرٌ لا
            صلاحيةَ له على الأجهزة لا شأنَ له بها ولا يشتريها، وعرضُ قفلٍ
            له يدعوه إلى بابٍ ليس بابه.

          • **ميزةٌ خارج الباقة** (`$posPlan`) **تُقفَل وتبقى ظاهرة**. إخفاؤها
            يجعل المنشأة لا تعرف أنها موجودة أصلاً — فلا تسأل عنها ولا
            تشتريها، وتظنّ النظام لا يملكها. والقفلُ يقول: موجودةٌ وخارج
            باقتك.

        والبندُ المقفول يقود إلى شاشة الاشتراك لا إلى شاشته — إذ يُردّ عنها
        بـ402 على كل حال (وسيط `plan:` في `Modules/Pos/routes/web.php`).
        فالقفلُ واجهةٌ تشرح، والمنعُ في الوسيط وحده.

        وميزةٌ لا يعرفها كتالوج فوترة **غير مقفولة** — فلا يُقفل بندٌ لأن
        اللوحة لم تُعرِّف ميزته بعد.
    */
    $posPlanKeys = [
        'terminal' => PosPlanFeatures::TERMINAL,
        'sessions' => PosPlanFeatures::SESSIONS,
        'confirm' => PosPlanFeatures::SESSION_CONFIRM,
        'cash_drawer' => PosPlanFeatures::CASH_DRAWER,
        'devices' => PosPlanFeatures::DEVICES,
        'printers' => PosPlanFeatures::PRINTERS,
        'settings' => PosPlanFeatures::SETTINGS,
        'reports' => PosPlanFeatures::REPORTS,
    ];

    $posPlan = [];

    foreach ($posPlanKeys as $slot => $feature) {
        $posPlan[$slot] = SubscriptionGate::permits($feature);
    }

    // الورديات تتبع مفتاح الأجهزة — تُدار من الشاشة نفسها ولا تُباع وحدها
    $posPlan['shifts'] = $posPlan['devices'];
    $posPlanKeys['shifts'] = PosPlanFeatures::DEVICES;

    // القسمُ كلُّه مقفول حين لا تشمل الباقة شيئاً منه — ويبقى ظاهراً
    $posPlanAny = SubscriptionGate::permits(PosPlanFeatures::all());
    $posPlanLockUrl = \App\Support\PlanLock::url();

    /*
        وجهةُ البند: شاشتُه إن كان مسموحاً، وشاشةُ الاشتراك إن كان مقفولاً.

        `route()` لا تُستدعى أصلاً للمقفول: بعض المسارات قد لا تكون مسجَّلة
        إن عُطِّلت وحدتها، وبناءُ عنوانٍ لن يُفتح عبثٌ يرمي استثناءً أحياناً.
    */
    $posPlanHref = function (bool $allowed, string $route, array $params = []) use ($posPlanLockUrl) {
        return $allowed ? route($route, $params) : $posPlanLockUrl;
    };

    /** سماتُ البند المقفول: تلميحٌ يشرح السبب باسم الميزة كما في شاشة الاشتراك. */
    $posPlanAttrs = function (bool $allowed, string $slot) use ($posPlanKeys) {
        return $allowed ? '' : ' title="'.e(\App\Support\PlanLock::reason($posPlanKeys[$slot] ?? $slot)).'"';
    };

    /*
        ═══════════════ حدودُ الاستخدام — شارةٌ لا إخفاء ═══════════════

        البندُ الذي بلغ حدَّه يبقى ظاهراً وقابلاً للفتح عمداً: من استنفد سقفَ
        الأجهزة يحتاج شاشتَها أكثرَ من غيره — ليعطّل جهازاً لم يعد يُستعمل،
        أو ليرى ما عنده قبل أن يشتري إضافة. وإخفاؤها يقفل عليه بابَ الخروج
        من الحدّ نفسِه.

        فالمعروضُ شارةٌ تقول «امتلأ» أو النسبةَ عند اقترابها، والمنعُ يقع على
        الإنشاء وحده (`plan.limit` على المسار و[PlanLimitObserver] على النموذج).

        `limit()` لا `liveLimit()`: هذه قراءةٌ للعرض في كل تحميلِ صفحة، وتلك
        تُطلق `COUNT(*)` لكل ميزة — خمسةُ استعلاماتٍ على كل شاشة ثمنٌ باهظ
        لشارة. والفرقُ بينهما دورةُ مزامنةٍ واحدة، وهو مقبولٌ لتنبيهٍ لا لمنع.
    */
    $gate = app(SubscriptionGate::class);
    $posPlanLimits = [];

    foreach ([
        'devices' => PosPlanFeatures::LIMIT_DEVICES,
        'printers' => PosPlanFeatures::LIMIT_PRINTERS,
        'shifts' => PosPlanFeatures::LIMIT_SHIFTS,
        'sessions' => PosPlanFeatures::LIMIT_SESSIONS,
        'cash_drawer' => PosPlanFeatures::LIMIT_CASH_MOVEMENTS,
    ] as $slot => $feature) {
        $limit = $gate->limit($feature);

        // ما لا حدَّ له لا شارةَ له — والشارةُ لا تظهر قبل ٨٠٪ كي لا تصير
        // زخرفةً دائمة يتوقّف أحدٌ عن رؤيتها
        $posPlanLimits[$slot] = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
            ? null
            : [
                'exhausted' => (bool) $limit['exhausted'],
                'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
                'title' => sprintf(
                    '%s: %d من %d',
                    $limit['label'],
                    (int) $limit['used'],
                    (int) $limit['allowance']
                ),
            ];
    }

    /*
        علامةُ البند: قفلٌ إن كانت الميزة خارج الباقة، وإلا شارةُ الحدّ إن
        قارب الامتلاء. ولا تجتمعان: ميزةٌ لم تُشترَ لا حدَّ لها يُعرض.
    */
    $posPlanMark = function (bool $allowed, ?array $limit) {
        if (! $allowed) {
            return \App\Support\PlanLock::menuBadge();
        }

        if ($limit === null) {
            return '';
        }

        $color = $limit['exhausted'] ? '#EA5455' : '#FF9F43';

        return '<span class="badge badge-pill float-right" title="'.e($limit['title']).'"'
            .' style="background:'.$color.';color:#fff;font-size:10px;line-height:1.4;padding:2px 7px;">'
            .e($limit['text']).'</span>';
    };

    $pendingSessions = 0;

    if ($posOn && $canConfirm) {
        try {
            $pendingSessions = \App\Models\PosSession::where('status', 'pending_review')->count();
        } catch (\Throwable $e) {
            // عمودُ الحالة الجديد غير مُرحَّل بعد يجب ألّا يكسر القائمة كلَّها
            $pendingSessions = 0;
        }
    }
@endphp


    <div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
        <div class="navbar-header">
            <ul class="nav navbar-nav flex-row">
                <li class="nav-item mr-auto">
                    {{-- الشعارُ يعود إلى رئيسية نقاط البيع لا إلى شاشة
                         التطبيقات: من هو داخل الوحدة يقصد بالنقر رأسَها
                         لا الخروجَ منها. --}}
                    <a class="navbar-brand" href="{{ route('dashboard.pos') }}">
                        <div class="brand-logo"></div>
                        <h2 class="brand-text mb-0">فوترة</h2>
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

                {{-- ══════════ نقاط البيع ══════════ --}}
                @if ($posOn && ($canSell || $canSessions || $canReports || $canSettings || $canDevices || $canShifts || $canCashDrawer))
                    <li class="nav-item {{ request()->is("$getLocal/POS/*") ? 'active open' : '' }} {{ $posPlanAny ? '' : 'stk-plan-locked' }}">
                        <a href="{{ $posPlanAny ? '#' : $posPlanLockUrl }}"{!! $posPlanAny ? '' : ' title="'.e(\App\Support\PlanLock::reason(PosPlanFeatures::all())).'"' !!}>
                            <i class="feather icon-monitor"></i>
                            <span class="menu-title" data-i18n="POS">{{ trans('main_trans.Point_of_Sale') }}</span>
                        </a>

                        <ul class="menu-content">
                            {{--
                                الرئيسية أوّلاً — وبلا حراسةِ صلاحيةٍ بعينها.

                                هي مقصدُ الدخول للوحدة، وبوّابتُها «أيُّ صلاحيةِ
                                نقاط بيع تفتح». فشرطُ صلاحيةٍ محدَّدة عليها يردّ
                                من فتحت له البوّابةُ عمداً.
                            --}}
                            <li>
                                <a href="{{ route('dashboard.pos') }}">
                                    <i class="feather icon-circle {{ request()->is("$getLocal/dashboard/pos") ? 'active' : '' }}"></i>
                                    <span class="menu-item">الرئيسية</span>
                                </a>
                            </li>

                            @if ($canSell)
                                <li class="{{ $posPlan['terminal'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['terminal'], 'sales_start.index') }}"{!! $posPlanAttrs($posPlan['terminal'], 'terminal') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/sales*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">{{ trans('main_trans.Start_Sale') }}</span>{!! $posPlanMark($posPlan['terminal'], null) !!}
                                    </a>
                                </li>
                            @endif

                            @if ($canSessions)
                                <li class="{{ $posPlan['sessions'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['sessions'], 'pos.sessions.index') }}"{!! $posPlanAttrs($posPlan['sessions'], 'sessions') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/session*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">{{ trans('main_trans.Sessions') }}</span>{!! $posPlanMark($posPlan['sessions'], $posPlanLimits['sessions']) !!}
                                    </a>
                                </li>
                            @endif

                            {{--
                                بانتظار التأكيد — العهدةُ لا تُعتمد حتى يؤكّدها مشرف.

                                وعدَّادُ المنتظِر يبقى ظاهراً حتى لو كانت الميزةُ
                                خارج الباقة: هو رقمٌ عن عملٍ **قائم فعلاً** لا
                                دعوةٌ إلى ميزةٍ تُشترى.
                            --}}
                            @if ($canConfirm)
                                <li class="{{ $posPlan['confirm'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['confirm'], 'pos.sessions.index', ['status' => 'pending_review']) }}"{!! $posPlanAttrs($posPlan['confirm'], 'confirm') !!}>
                                        <i class="feather icon-circle"></i>
                                        <span class="menu-item">بانتظار التأكيد</span>
                                        @if ($posPlan['confirm'] && $pendingSessions > 0)
                                            <span class="badge badge-pill badge-warning float-right">{{ $pendingSessions }}</span>
                                        @else
                                            {!! $posPlanMark($posPlan['confirm'], null) !!}
                                        @endif
                                    </a>
                                </li>
                            @endif

                            @if ($canCashDrawer)
                                <li class="{{ $posPlan['cash_drawer'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['cash_drawer'], 'pos.cash_drawer.index') }}"{!! $posPlanAttrs($posPlan['cash_drawer'], 'cash_drawer') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/cash-drawer*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">الدرج النقدي</span>{!! $posPlanMark($posPlan['cash_drawer'], $posPlanLimits['cash_drawer']) !!}
                                    </a>
                                </li>
                            @endif

                            @if ($canDevices)
                                <li class="{{ $posPlan['devices'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['devices'], 'pos.settings.devices.index') }}"{!! $posPlanAttrs($posPlan['devices'], 'devices') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/Devices*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">أجهزة الكاشير</span>{!! $posPlanMark($posPlan['devices'], $posPlanLimits['devices']) !!}
                                    </a>
                                </li>

                                <li class="{{ $posPlan['printers'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['printers'], 'pos.settings.printers.index') }}"{!! $posPlanAttrs($posPlan['printers'], 'printers') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/Printers*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">الطابعات</span>{!! $posPlanMark($posPlan['printers'], $posPlanLimits['printers']) !!}
                                    </a>
                                </li>
                            @endif

                            @if ($canShifts)
                                <li class="{{ $posPlan['shifts'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['shifts'], 'pos.settings.shift.index') }}"{!! $posPlanAttrs($posPlan['shifts'], 'shifts') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/Shift/*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">الورديات</span>{!! $posPlanMark($posPlan['shifts'], $posPlanLimits['shifts']) !!}
                                    </a>
                                </li>

                                {{--
                                    التصنيفاتُ تحت الورديات ونفسِ صلاحيتها ونفسِ
                                    مفتاح الباقة — و`ShiftCategory*` لا `Shift*`
                                    في فحص التنشيط، وإلّا أُضيء العنصران معاً.
                                --}}
                                <li class="{{ $posPlan['shifts'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['shifts'], 'pos.settings.shift_categories.index') }}"{!! $posPlanAttrs($posPlan['shifts'], 'shifts') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/ShiftCategory*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">تصنيفات الورديات</span>
                                    </a>
                                </li>
                            @endif

                            @if ($canSettings)
                                <li class="{{ $posPlan['settings'] ? '' : 'stk-plan-locked' }}">
                                    <a href="{{ $posPlanHref($posPlan['settings'], 'pos.settings.index') }}"{!! $posPlanAttrs($posPlan['settings'], 'settings') !!}>
                                        <i class="feather icon-circle {{ request()->is("$getLocal/POS/Settings*") ? 'active' : '' }}"></i>
                                        <span class="menu-item">{{ trans('main_trans.POS_Settings') }}</span>{!! $posPlanMark($posPlan['settings'], null) !!}
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif

                {{-- ══════════ تقارير نقاط البيع ══════════ --}}
                @if ($posOn && $canReports)
                    <li class="nav-item {{ $posPlan['reports'] ? '' : 'stk-plan-locked' }}">
                        <a href="{{ $posPlanHref($posPlan['reports'], 'pos_reports.index') }}"{!! $posPlanAttrs($posPlan['reports'], 'reports') !!}>
                            <i class="feather icon-file-text"></i>
                            <span class="menu-title">{{ trans('main_trans.POS_Reports') }}</span>{!! $posPlanMark($posPlan['reports'], null) !!}
                        </a>
                    </li>
                @endif

                {{-- «العودة إلى التطبيقات» محذوفٌ من القائمة: أيقونةُ الشبكة
                     في الشريط العلويّ تفتح شاشة التطبيقات نفسها، فكان بنداً
                     مكرّراً يزاحم روابط الوحدة في آخر القائمة. --}}

            </ul>
        </div>
    </div>

