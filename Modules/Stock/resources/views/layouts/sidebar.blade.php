{{--
    القائمة الجانبية لوحدة المخزون.

    الصلاحيات تُفحَص بـ`hasPermission()`/`canAny()` لا بـ
    `hasPermission()`: الأخيرة ترمي `PermissionDoesNotExist`
    لاسمٍ لم يُزرع بعد، فتُسقط الصفحة كلّها بخطأ 500 بدل أن تُخفي رابطاً —
    وهو ما وقع فعلاً مع `storehouse_view`.
--}}
@php
    use App\Constants\planFeatures\StockPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $getLocal = App::getLocale();
    if (!isset($settings)) {
        $settings = \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();
    }

    /*
        حدود باقة فوترة — **قفلٌ لا إخفاء**.

        الفرق بين الحارسَين مقصود:

          • **صلاحية النظام** (`hasPermission`) تُخفي. موظّفٌ لا يملك صلاحية
            المستودعات لا شأن له بها ولا يشتريها، وعرضُ قفلٍ له يدعوه إلى
            بابٍ ليس بابه.

          • **ميزة خارج الباقة** (`$plan`) تُقفَل وتبقى ظاهرة. إخفاؤها يجعل
            المنشأة لا تعرف أنها موجودة أصلاً — فلا تسأل عنها ولا تشتريها،
            وتظنّ النظام لا يملكها. والقفل يقول: موجودةٌ وخارج باقتك.

        والبند المقفول يقود إلى شاشة الاشتراك لا إلى شاشته — إذ يُردّ عنها
        بـ402 على كل حال (وسيط `plan:` في Modules/Stock/routes/web.php).
        فالقفل واجهةٌ تشرح، والمنع في الوسيط وحده.

        وميزةٌ لا يعرفها كتالوج فوترة **غير مقفولة** — فلا يُقفل بندٌ لأن
        اللوحة لم تُعرِّف ميزته بعد.
    */
    $plan = [
        'products' => SubscriptionGate::permits(StockPlanFeatures::PRODUCTS),
        'categories' => SubscriptionGate::permits(StockPlanFeatures::CATEGORIES),
        'price_lists' => SubscriptionGate::permits(StockPlanFeatures::PRICE_LISTS),
        'storehouses' => SubscriptionGate::permits(StockPlanFeatures::STOREHOUSES),
        'permits' => SubscriptionGate::permits(StockPlanFeatures::WAREHOUSE_PERMITS),
        'adjustments' => SubscriptionGate::permits(StockPlanFeatures::INVENTORY_ADJUSTMENT),
        'reports' => SubscriptionGate::permits(StockPlanFeatures::REPORTS),
    ];

    // مفاتيح الميزات بجانب حالتها — يحتاجها التلميح ليسمّي الميزة الناقصة
    // بالاسم الذي يراه المستخدم في شاشة الاشتراك
    $planKeys = [
        'products' => StockPlanFeatures::PRODUCTS,
        'categories' => StockPlanFeatures::CATEGORIES,
        'price_lists' => StockPlanFeatures::PRICE_LISTS,
        'storehouses' => StockPlanFeatures::STOREHOUSES,
        'permits' => StockPlanFeatures::WAREHOUSE_PERMITS,
        'adjustments' => StockPlanFeatures::INVENTORY_ADJUSTMENT,
        'reports' => StockPlanFeatures::REPORTS,
    ];

    // القسم كلّه مقفول حين لا تشمل الباقة شيئاً منه — ويبقى ظاهراً
    $planAnyStock = SubscriptionGate::permits(StockPlanFeatures::stockOnly());
    $planLockUrl = \App\Support\PlanLock::url();

    /*
        وجهةُ البند: شاشتُه إن كان مسموحاً، وشاشةُ الاشتراك إن كان مقفولاً.

        `route()` لا تُستدعى أصلاً للمقفول: بعض المسارات قد لا تكون مسجَّلة
        إن عُطِّلت وحدتها، وبناءُ عنوانٍ لن يُفتح عبثٌ يرمي استثناءً أحياناً.
    */
    $planHref = function (bool $allowed, string $route, array $params = []) use ($planLockUrl) {
        return $allowed ? route($route, $params) : $planLockUrl;
    };

    /** سمات البند المقفول: صنفٌ للتخفيت وتلميحٌ يشرح السبب. */
    $planAttrs = function (bool $allowed, string $slot) use ($planKeys) {
        if ($allowed) {
            return '';
        }

        return ' title="'.e(\App\Support\PlanLock::reason($planKeys[$slot] ?? $slot)).'"';
    };

    /*
        حدود الاستخدام في الشريط — **شارةٌ لا إخفاء**.

        البند الذي بلغ حدّه يبقى ظاهراً وقابلاً للفتح عمداً: من استنفد سقف
        المنتجات يحتاج شاشتها أكثر من غيره — ليحذف ما لم يعد مستعمَلاً أو
        ليرى ما عنده قبل أن يشتري إضافة. وإخفاؤها يقفل عليه باب الخروج من
        الحدّ نفسه.

        فالمعروض شارةٌ تقول «امتلأ» أو النسبة عند اقترابها، والمنعُ يقع على
        الإنشاء وحده (`plan.limit` على المسار، و`PlanLimitObserver` على
        النموذج).

        `limit()` لا `liveLimit()`: هذه قراءةٌ للعرض في كل تحميلِ صفحة، وتلك
        تُطلق `COUNT(*)` لكل ميزة — سبعةُ استعلاماتٍ على كل شاشة ثمنٌ باهظ
        لشارة. والفرق بينهما دورةُ مزامنةٍ واحدة، وهو مقبولٌ لتنبيهٍ لا لمنع.
    */
    $gate = app(SubscriptionGate::class);
    $planLimits = [];

    foreach ([
        'products' => StockPlanFeatures::LIMIT_PRODUCTS,
        'categories' => StockPlanFeatures::LIMIT_CATEGORIES,
        'price_lists' => StockPlanFeatures::LIMIT_PRICE_LISTS,
        'storehouses' => StockPlanFeatures::LIMIT_STOREHOUSES,
        'permits' => StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS,
        'adjustments' => StockPlanFeatures::LIMIT_ADJUSTMENTS,
    ] as $slot => $feature) {
        $limit = $gate->limit($feature);

        // ما لا حدّ له لا شارة له — والشارة لا تظهر قبل ٨٠٪ كي لا تصير
        // زخرفةً دائمة يتوقّف أحدٌ عن رؤيتها
        $planLimits[$slot] = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
            ? null
            : [
                'exhausted' => (bool) $limit['exhausted'],
                'percent' => (int) $limit['percent'],
                'text' => $limit['exhausted']
                    ? 'امتلأ'
                    : $limit['percent'].'٪',
                'title' => sprintf(
                    '%s: %d من %d',
                    $limit['label'],
                    (int) $limit['used'],
                    (int) $limit['allowance']
                ),
            ];
    }
@endphp

{{--
    علامة البند: قفلٌ إن كانت الميزة خارج الباقة، وإلا شارةُ الحدّ إن قارب
    الامتلاء. ولا تجتمعان: ميزةٌ لم تُشترَ لا حدّ لها يُعرض.
--}}
@php
    $planMark = function (bool $allowed, ?array $limit) {
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
@endphp



<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            {{--
                كان `href="#"` — فالنقر على الشعار لا يفعل شيئاً سوى إضافة
                `#` إلى العنوان. وهو أوّل ما يُنقر للعودة إلى البداية.
            --}}
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('stock.dashboard') }}">
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




            @php
                /*
                    أسماء الصلاحيات هنا مطابقةٌ لِما تفحصه Modules/Stock/routes/web.php
                    فعلياً لكل رابط أدناه — لا لتخميناتٍ قديمة. راجع
                    app/Constants/permissions/StockPermissions.php.

                    المنتجات وقوائم الأسعار كلاهما مُحوَّلان إلى كتالوجَي
                    stock.products و stock.price_lists الجديدين — نفس ما
                    تفحصه ProductsController/PriceListController وراوتاتهما الآن فعلياً.
                */
                $hasProductPermissions = canAny([
                    'stock.products.view',
                    'stock.products.show',
                    'stock.products.edit',
                    'stock.products.delete',
                    'stock.products.services',
                ]);
                $hasInventoryPermissions = canAny([
                    'stock.permits.view',
                    'stock.price_lists.view',
                    'stock.warehouses.view',
                    'stock.adjustments.view',
                    'stock.settings.view',
                    'stock.settings.products',
                    'stock.products.movements',
                ]);

                // عرض إدارة المنتجات فقط عندما يكون لديه صلاحية عرض المنتجات فقط
                $hasOnlyProductsView =
                    hasPermission('stock.products.view') &&
                    !canAny([
                        'stock.products.edit',
                        'stock.products.delete',
                        'stock.products.services',
                        'stock.permits.view',
                        'stock.price_lists.view',
                        'stock.warehouses.view',
                        'stock.adjustments.view',
                        'stock.settings.view',
                        'stock.settings.products',
                        'stock.products.movements',
                    ]);
            @endphp

            @if ($hasProductPermissions || $hasInventoryPermissions)
                @if (isset($settings['inventory_management']) && $settings['inventory_management'] === 'active')
                    {{--
                        كان `href=""` — وهو **إعادة تحميلٍ للصفحة الحالية** لا
                        رابطاً معطّلاً فحسب: المتصفّح يقرأ العنوان الفارغ على
                        أنه العنوان الحالي. فالنقر على «إدارة المخزون» أو
                        أيقونتها يُنعش الصفحة نفسها ولا يوجّه إلى لوحةٍ قطّ،
                        ولا رابط إلى لوحة المخزون من هذا الشريط إطلاقاً.
                    --}}
                    <li class=" nav-item {{ request()->is("$getLocal/stock/*") ? 'active open' : '' }} {{ $planAnyStock ? '' : 'stk-plan-locked' }}"><a
                            href="{{ $planAnyStock ? route('stock.dashboard') : $planLockUrl }}"{!! $planAnyStock ? '' : ' title="'.e(\App\Support\PlanLock::reason(App\Constants\planFeatures\StockPlanFeatures::stockOnly())).'"' !!}>
                            <i class="feather icon-box">
                            </i><span class="menu-title" data-i18n="Dashboard">
                                {{ trans('main_trans.Stock') }}</span>

                        </a>
                        <ul class="menu-content">
                            @if (hasPermission('stock.products.view'))
                                <li class="{{ $plan['products'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['products'] ? route('products.index') : $planLockUrl }}"{!! $planAttrs($plan['products'], 'products') !!}><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/products/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Analytics">
                                            {{ trans('main_trans.products_management') }}</span>{!! $planMark($plan['products'], $planLimits['products']) !!}</a>
                                </li>
                                @if (hasPermission('stock.units.view'))
                                <li class="{{ $plan['products'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['products'] ? route('units.index') : $planLockUrl }}"{!! $planAttrs($plan['products'], 'products') !!}><i
                                            class="feather icon-circle {{ request()->is("$getLocal/stock/units/*") ? 'active' : '' }}"></i><span
                                            class="menu-item" data-i18n="Units">
                                            إدارة الوحدات</span>{!! $planMark($plan['products'], null) !!}</a>
                                </li>
                                @endif
                            @endif

                            @if (!$hasOnlyProductsView)
                                @if (hasPermission('stock.permits.view'))
                                    <li class="{{ $plan['permits'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['permits'] ? route('store_permits_management.index') : $planLockUrl }}"{!! $planAttrs($plan['permits'], 'permits') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/store_permits_management/*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.Store_permissions_management') }}</span>{!! $planMark($plan['permits'], $planLimits['permits']) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.products.movements'))
                                    <li class="{{ $plan['products'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['products'] ? route('products.traking') : $planLockUrl }}"{!! $planAttrs($plan['products'], 'products') !!}><i
                                                class="feather icon-circle"></i><span class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.product_tracking') }}</span>{!! $planMark($plan['products'], null) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.price_lists.view'))
                                    <li class="{{ $plan['price_lists'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['price_lists'] ? route('price_list.index') : $planLockUrl }}"{!! $planAttrs($plan['price_lists'], 'price_lists') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/price_list/*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.price_lists') }}</span>{!! $planMark($plan['price_lists'], $planLimits['price_lists']) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.warehouses.view'))
                                    <li class="{{ $plan['storehouses'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['storehouses'] ? route('storehouse.index') : $planLockUrl }}"{!! $planAttrs($plan['storehouses'], 'storehouses') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/storehouse/*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.warehouses') }}</span>{!! $planMark($plan['storehouses'], $planLimits['storehouses']) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.adjustments.view'))
                                    <li class="{{ $plan['adjustments'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['adjustments'] ? route('inventory_management.index') : $planLockUrl }}"{!! $planAttrs($plan['adjustments'], 'adjustments') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_management/*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.inventory_Management') }}</span>{!! $planMark($plan['adjustments'], $planLimits['adjustments']) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.reports.employee_statement'))
                                    <li class="{{ $plan['storehouses'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['storehouses'] ? route('storehouse.employee_statement') : $planLockUrl }}"{!! $planAttrs($plan['storehouses'], 'storehouses') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/employee-statement*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">الكشف المخزني</span>{!! $planMark($plan['storehouses'], null) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.settings.view'))
                                    <li class="{{ $plan['storehouses'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['storehouses'] ? route('inventory_settings.index') : $planLockUrl }}"{!! $planAttrs($plan['storehouses'], 'storehouses') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/inventory_settings/*") ? 'active' : '' }}"></i><span
                                                class="menu-item"
                                                data-i18n="eCommerce">{{ trans('main_trans.inventory_settings') }}</span>{!! $planMark($plan['storehouses'], null) !!}</a>
                                    </li>
                                @endif

                                @if (hasPermission('stock.settings.products'))
                                    <li class="{{ $plan['products'] ? '' : 'stk-plan-locked' }}"><a href="{{ $plan['products'] ? route('product_settings.index') : $planLockUrl }}"{!! $planAttrs($plan['products'], 'products') !!}><i
                                                class="feather icon-circle {{ request()->is("$getLocal/stock/product_settings/*") ? 'active' : '' }}"></i><span
                                                class="menu-item" data-i18n="eCommerce">
                                                {{ trans('main_trans.products_Settings') }}</span>{!! $planMark($plan['products'], null) !!}</a>
                                    </li>
                                @endif


                            @endif

                        </ul>

                    </li>
                @endif
            @endif

            {{--
                كانت هنا كتلةُ التصنيع كاملةً — قوائمُ المواد وأوامرُ التصنيع
                والتكاليفُ غير المباشرة ومحطّاتُ العمل والإعدادات وحاسبةُ
                التكلفة — داخل شريط المخزون.

                وقد صار التصنيع وحدةً قائمةً بذاتها لها شريطها الجانبي
                (`manufacturing::layouts.sidebar`) ولوحتُها وتقاريرها. فبقاؤه
                هنا يعني مدخلين لشيءٍ واحد ونصفَ قائمةٍ في المكان الخطأ.

                مدخله الآن: بطاقةُ «التصنيع» في الصفحة الرئيسية.
            --}}
            
            @if (hasPermission('stock.reports.view'))
                <li class="nav-item {{ $plan['reports'] ? '' : 'stk-plan-locked' }}">
                    <a href="{{ $plan['reports'] ? route('StorHouseReport.index') ?? '#' : $planLockUrl }}"{!! $planAttrs($plan['reports'], 'reports') !!}>
                        <i class="feather icon-file-text"></i>
                        <span class="menu-title" data-i18n="Dashboard">تقرير المخزون</span>{!! $planMark($plan['reports'], null) !!}
                    </a>
                </li>
            @endif


            {{--
                ورابط «تقرير التصنيع» تبِع كتلتَه: كان يشير إلى
                `ReportsProduction.index` — مسارٍ يستدعي دالةً لا وجود لها
                فيُنتج 500 — وموضعُه الآن شريطُ وحدة التصنيع.
            --}}





        </ul>
    </div>
</div>

