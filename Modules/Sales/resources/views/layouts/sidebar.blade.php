{{--
    القائمة الجانبية لوحدة المبيعات.

    **مبنيّة على قياس `purchases::layouts.sidebar` بالضبط**: نفس الترويسة،
    ونفس أصناف الحاوية، وقائمةٌ **مسطَّحة** لشاشات الوحدة وحدها — فمن دخل
    المبيعات رأى شاشات المبيعات كما يرى داخل المشتريات شاشات المشتريات.

    **ما كان قبلها:** مجموعةٌ واحدة اسمها «المبيعات» تُطوى وتُفتح
    (`sales::layouts.partials.sales-menu`) ومعها مجموعة «إدارة الأقساط»،
    فبنود الوحدة كلُّها مخبوءةٌ خلف أكورديون بينما جارتُها المشتريات تعرض
    بنودها مباشرةً. و«محافظ العملاء» لم يكن فيها إطلاقاً — لا في هذا
    الشريط ولا في الجزء المشترك — فمن فتح شاشة مبيعات لم يجد للمحفظة
    أثراً، وهي الشاشة التي يُسدَّد منها رصيدُ العميل على فواتيره.

    **أقفال الباقة وشارات الحدّ باقية كما هي** (`$plan*`): البند خارج
    الباقة يُقفَل ولا يُخفى — انظر شرحها في موضعها أدناه. والجزء المشترك
    `sales-menu` باقٍ بلا تغيير لأن الشريط الجذري يُضمّنه، فوحدةٌ أخرى
    تفتح شاشتها ترى مجموعة المبيعات كما كانت.

    @see \Modules\Purchases\resources\views\layouts\sidebar النظير الذي بُنيت عليه
    @see \Modules\Client\resources\views\layouts\sidebar نظيرها في وحدة العملاء
--}}

@php
    use App\Constants\planFeatures\SalesPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $getLocal = App::getLocale();

    /*
     * إعدادات التطبيقات — المفتاح ← الحالة.
     *
     * الفحص بالنوع لا بـ`isset()` وحدها. `@extends` يمرّر متغيّرات قالب
     * الشاشة كلَّها إلى `sales::master` عبر `get_defined_vars()`، ومنها
     * إلى هنا. فأيّ شاشة تُعرّف `$settings` لنفسها — وهو اسمٌ شائع —
     * تطمس هذه المصفوفة، فتصير `$settings['sales']` قراءةً على كائنٍ أو
     * نصّ، وتختفي القائمة كلُّها بلا خطأ يُرى. وقع هذا فعلاً في شاشة
     * «إنشاء فاتورة مبيعات» فظهرت بقائمة جانبية شبه فارغة.
     */
    $settings = (isset($settings) && is_array($settings))
        ? $settings
        : \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();

    /** أمفعَّلٌ هذا التطبيق في «شاشة التطبيقات»؟ */
    $appOn = fn (string $key) => ($settings[$key] ?? null) === 'active';

    /** أيقونة البند، مؤشَّرةً حين يقع المسار الحالي داخل بادئتها */
    $sub = fn (string $prefix) => request()->is("$getLocal/$prefix") || request()->is($prefix) ? 'active' : '';

    /** مفاتيح «إعدادات الفواتير» التي تُظهر بنوداً أو تُخفيها */
    $invoiceFlags = \App\Models\SalesInvoiceSetting::current();

    /*
        ══════════════ الطبقة الثالثة: ماذا اشترت المنشأة؟ ══════════════

        فوق الصلاحية (من يفعل؟) وتفعيل التطبيق (أمُشغَّلةٌ الوحدة؟) يقف سؤالٌ
        ثالثٌ مستقلٌّ تماماً: **أشُتريت الميزة أصلاً؟** ولا يُغني عنه غيرُه —
        مديرٌ يملك كل صلاحيات النظام لا يفتح شاشةً خارج الباقة.

        و**البند خارج الباقة يُقفَل ولا يُخفى**: إخفاؤه يجعل المنشأة لا تعرف
        أن الميزة موجودة، فلا تسأل عنها ولا تشتريها وتحسب النظام لا يملكها.
        والقفل يقول: موجودةٌ وخارج باقتك — ويقود إلى شاشة الاشتراك لا إلى
        شاشتها، إذ تُردّ عنها بـ402 على كل حال (وسيط `plan:` على المسارات).

        وميزةٌ لا يعرفها كتالوج فوترة **غير مقفولة**: المجهول مسموح.
    */
    $plan = [
        'dashboard' => SubscriptionGate::permits(SalesPlanFeatures::DASHBOARD),
        'invoices' => SubscriptionGate::permits(SalesPlanFeatures::INVOICES),
        'quotes' => SubscriptionGate::permits(SalesPlanFeatures::QUOTES),
        'credit_notes' => SubscriptionGate::permits(SalesPlanFeatures::CREDIT_NOTES),
        'returns' => SubscriptionGate::permits(SalesPlanFeatures::RETURN_INVOICES),
        'periodic' => SubscriptionGate::permits(SalesPlanFeatures::PERIODIC_INVOICES),
        'payments' => SubscriptionGate::permits(SalesPlanFeatures::PAYMENTS),
        'installments' => SubscriptionGate::permits(SalesPlanFeatures::INSTALLMENTS),
        'reports' => SubscriptionGate::permits(SalesPlanFeatures::reports()),
        'settings' => SubscriptionGate::permits(SalesPlanFeatures::all()),
    ];

    // مفاتيح الميزات بجانب حالتها — يحتاجها التلميح ليسمّي الميزة الناقصة
    // بالاسم الذي يراه المستخدم في شاشة الاشتراك
    $planKeys = [
        'dashboard' => SalesPlanFeatures::DASHBOARD,
        'invoices' => SalesPlanFeatures::INVOICES,
        'quotes' => SalesPlanFeatures::QUOTES,
        'credit_notes' => SalesPlanFeatures::CREDIT_NOTES,
        'returns' => SalesPlanFeatures::RETURN_INVOICES,
        'periodic' => SalesPlanFeatures::PERIODIC_INVOICES,
        'payments' => SalesPlanFeatures::PAYMENTS,
        'installments' => SalesPlanFeatures::INSTALLMENTS,
        'reports' => SalesPlanFeatures::reports(),
        'settings' => SalesPlanFeatures::all(),
    ];

    $planLockUrl = \App\Support\PlanLock::url();

    /*
        وجهةُ البند: شاشتُه إن كان مسموحاً، وشاشةُ الاشتراك إن كان مقفولاً.
        و`route()` لا تُستدعى للمقفول: مسارٌ قد لا يكون مسجَّلاً إن عُطِّلت
        وحدتُه، وبناءُ عنوانٍ لن يُفتح عبثٌ يرمي أحياناً.
    */
    $planHref = fn (bool $allowed, string $route, array $params = []) => $allowed
        ? route($route, $params)
        : $planLockUrl;

    /** صنفُ التخفيت للبند المقفول. */
    $planClass = fn (bool $allowed) => $allowed ? '' : 'sl-plan-locked';

    /** تلميحُ البند المقفول: أي ميزةٍ ينقصها وما اسمها في شاشة الاشتراك. */
    $planAttrs = function (bool $allowed, string $slot) use ($planKeys) {
        return $allowed ? '' : ' title="'.e(\App\Support\PlanLock::reason($planKeys[$slot] ?? $slot)).'"';
    };

    /*
        حدود الاستخدام في الشريط — **شارةٌ لا إخفاء**.

        البند الذي بلغ حدّه يبقى ظاهراً وقابلاً للفتح عمداً: من استنفد سقف
        الفواتير يحتاج شاشتها أكثر من غيره — ليرى ما عنده قبل أن يشتري
        إضافة. وإخفاؤها يقفل عليه بابَ الخروج من الحدّ نفسه. والمنعُ يقع على
        الإنشاء وحده: `plan.limit` على المسار و`PlanLimitObserver` على النموذج.

        `limit()` لا `liveLimit()`: هذه قراءةٌ للعرض في كل تحميلِ صفحة، وتلك
        تُطلق `COUNT(*)` لكل ميزة — سبعةُ استعلاماتٍ على كل شاشة ثمنٌ باهظ
        لشارة. والفرق بينهما دورةُ مزامنةٍ واحدة، وهو مقبولٌ لتنبيهٍ لا لمنع.
    */
    $salesGate = app(SubscriptionGate::class);
    $planLimits = [];

    foreach ([
        'invoices' => SalesPlanFeatures::LIMIT_INVOICES,
        'quotes' => SalesPlanFeatures::LIMIT_QUOTES,
        'credit_notes' => SalesPlanFeatures::LIMIT_CREDIT_NOTES,
        'returns' => SalesPlanFeatures::LIMIT_RETURN_INVOICES,
        'periodic' => SalesPlanFeatures::LIMIT_PERIODIC_INVOICES,
        'payments' => SalesPlanFeatures::LIMIT_PAYMENTS,
        'installments' => SalesPlanFeatures::LIMIT_INSTALLMENT_PLANS,
    ] as $slot => $feature) {
        $limit = $salesGate->limit($feature);

        // ما لا حدّ له لا شارة له — والشارة لا تظهر قبل ٨٠٪ كي لا تصير
        // زخرفةً دائمة يتوقّف أحدٌ عن رؤيتها
        $planLimits[$slot] = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
            ? null
            : [
                'exhausted' => (bool) $limit['exhausted'],
                'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
                'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
            ];
    }

    /*
        علامة البند: قفلٌ إن كانت الميزة خارج الباقة، وإلا شارةُ الحدّ إن
        قارب الامتلاء. ولا تجتمعان: ميزةٌ لم تُشترَ لا حدَّ لها يُعرض.
    */
    $planMark = function (bool $allowed, ?array $limit) {
        if (! $allowed) {
            return \App\Support\PlanLock::menuBadge();
        }

        if ($limit === null) {
            return '';
        }

        return '<span class="badge badge-pill float-right" title="'.e($limit['title']).'"'
            .' style="background:'.($limit['exhausted'] ? '#EA5455' : '#FF9F43').';color:#fff;'
            .'font-size:10px;line-height:1.4;padding:2px 7px;">'.e($limit['text']).'</span>';
    };
@endphp

@once
    @push('styles')
        {{-- البند المقفول مخفَّتٌ لا مخفيّ — انظر تعليق `$plan` أعلاه --}}
        <style>
            .sl-plan-locked > a { opacity: .72; }
            .sl-plan-locked > a:hover { opacity: 1; }
        </style>
    @endpush
@endonce

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow menu-hide" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto"><a class="navbar-brand" href="{{ route('dashboard') }}">
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

            {{-- المبيعات — البوابة تجمع حرّاس البنود كلَّهم، فلا تحجب
                 بوابةٌ ضيّقة رابطاً يملكه صاحبه. --}}
            @if (canAny([
                'sales.dashboard.view', 'sales.invoices.view', 'sales.quotes.view',
                'sales.sales_orders.view', 'sales.credit_notes.view', 'sales.returns.view',
                'sales.periodic_invoices.view', 'sales.payments.view',
                'sales.installments.view', 'sales.installments.create',
                'sales.installments.edit_amount', 'sales.installments.agreement',
                'sales.reports.view', 'sales.daily_closing.view',
                'sales.settings.update',
            ]))
                @if ($appOn('sales'))

                    @if (hasPermission('sales.dashboard.view'))
                        <li class="{{ $planClass($plan['dashboard']) }}">
                            <a href="{{ $planHref($plan['dashboard'], 'dashboard.kpis') }}"{!! $planAttrs($plan['dashboard'], 'dashboard') !!}>
                                <i class="feather icon-activity {{ $sub('sales/dashboard/kpis') }}"></i>
                                <span class="menu-item">مؤشرات الأداء</span>{!! $planMark($plan['dashboard'], null) !!}
                            </a>
                        </li>
                        {{-- أداء المندوبين — جدولُ اللوحة كاملاً بالأكثر تحصيلاً؛ صلاحيةُ اللوحة نفسُها --}}
                        <li class="{{ $planClass($plan['dashboard']) }}">
                            <a href="{{ $planHref($plan['dashboard'], 'dashboard.reps') }}"{!! $planAttrs($plan['dashboard'], 'dashboard') !!}>
                                <i class="feather icon-users {{ $sub('sales/reps-performance') }}"></i>
                                <span class="menu-item">{{ __('sales_dashboard.reps.title') }}</span>{!! $planMark($plan['dashboard'], null) !!}
                            </a>
                        </li>
                    @endif

                    @if (canAny(['sales.invoices.view']))
                        <li class="{{ $planClass($plan['invoices']) }}">
                            <a href="{{ $planHref($plan['invoices'], 'invoices.index') }}"{!! $planAttrs($plan['invoices'], 'invoices') !!}>
                                <i class="feather icon-file-text {{ $sub('sales/invoices/index') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.invoice_management') }}</span>{!! $planMark($plan['invoices'], $planLimits['invoices']) !!}
                            </a>
                        </li>
                    @endif

                    @if (canAny(['sales.quotes.view']) && ! $invoiceFlags->disable_quotes)
                        <li class="{{ $planClass($plan['quotes']) }}">
                            <a href="{{ $planHref($plan['quotes'], 'questions.index') }}"{!! $planAttrs($plan['quotes'], 'quotes') !!}>
                                <i class="feather icon-file {{ $sub('sales/questions/index') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Quotation_Management') }}</span>{!! $planMark($plan['quotes'], $planLimits['quotes']) !!}
                            </a>
                        </li>
                    @endif

                    {{-- أوامر البيع — مفتاحُها «تفعيل أوامر البيع» في «إعدادات
                         الفواتير» ← قسم «عروض الأسعار وأوامر البيع». --}}
                    @if (canAny(['sales.sales_orders.view']) && $invoiceFlags->enable_sales_orders)
                        <li class="{{ $planClass($plan['quotes']) }}">
                            <a href="{{ $planHref($plan['quotes'], 'sales_orders.index') }}"{!! $planAttrs($plan['quotes'], 'quotes') !!}>
                                <i class="feather icon-shopping-bag {{ $sub('sales/sales-orders') }}"></i>
                                <span class="menu-item">{{ __('sales_documents.sales_order.doc.title') }}</span>{!! $planMark($plan['quotes'], $planLimits['quotes']) !!}
                            </a>
                        </li>
                    @endif

                    @if (canAny(['sales.credit_notes.view']))
                        <li class="{{ $planClass($plan['credit_notes']) }}">
                            <a href="{{ $planHref($plan['credit_notes'], 'CreditNotes.index') }}"{!! $planAttrs($plan['credit_notes'], 'credit_notes') !!}>
                                <i class="feather icon-alert-circle {{ $sub('sales/CreditNotes/*') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Credit_notes') }}</span>{!! $planMark($plan['credit_notes'], $planLimits['credit_notes']) !!}
                            </a>
                        </li>
                    @endif

                    @if (canAny(['sales.returns.view']))
                        <li class="{{ $planClass($plan['returns']) }}">
                            <a href="{{ $planHref($plan['returns'], 'ReturnIInvoices.index') }}"{!! $planAttrs($plan['returns'], 'returns') !!}>
                                <i class="feather icon-rotate-ccw {{ $sub('sales/invoices/invoices_returned') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Returned_invoices') }}</span>{!! $planMark($plan['returns'], $planLimits['returns']) !!}
                            </a>
                        </li>
                    @endif

                    @if (hasPermission('sales.periodic_invoices.view'))
                        <li class="{{ $planClass($plan['periodic']) }}">
                            <a href="{{ $planHref($plan['periodic'], 'periodic_invoices.index') }}"{!! $planAttrs($plan['periodic'], 'periodic') !!}>
                                <i class="feather icon-repeat {{ $sub('sales/periodic-invoices/*') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Periodic_invoices') }}</span>{!! $planMark($plan['periodic'], $planLimits['periodic']) !!}
                            </a>
                        </li>
                    @endif

                    @if (canAny(['sales.payments.view']))
                        <li class="{{ $planClass($plan['payments']) }}">
                            <a href="{{ $planHref($plan['payments'], 'paymentsClient.index') }}"{!! $planAttrs($plan['payments'], 'payments') !!}>
                                <i class="feather icon-credit-card {{ $sub('sales/paymentsClient/*') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Customer_payments') }}</span>{!! $planMark($plan['payments'], $planLimits['payments']) !!}
                            </a>
                        </li>
                    @endif

                    {{-- إدارة الأقساط — كانت مجموعةً مطويّة ببندين، وصارت
                         بندين مسطَّحين كبقيّة القائمة. وحراستُها مطابقةٌ
                         لحراسة `Modules/Sales/routes/web.php`؛ كانت
                         صلاحيةَ قروض الرواتب (اليوم `loans.*`) في
                         الموارد البشرية، لا صلة لها بتقسيط فواتير العملاء. --}}
                    @if (canAny([
                            'sales.installments.view',
                            'sales.installments.create',
                            'sales.installments.edit_amount',
                            'sales.installments.agreement',
                        ])
                        && $appOn('installments_management'))
                        <li class="{{ $planClass($plan['installments']) }}">
                            <a href="{{ $planHref($plan['installments'], 'installments.index') }}"{!! $planAttrs($plan['installments'], 'installments') !!}>
                                <i class="feather icon-clipboard {{ $sub('installments') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Installment_agreements') }}</span>{!! $planMark($plan['installments'], $planLimits['installments']) !!}
                            </a>
                        </li>
                        <li class="{{ $planClass($plan['installments']) }}">
                            <a href="{{ $planHref($plan['installments'], 'installments.agreement_installments') }}"{!! $planAttrs($plan['installments'], 'installments') !!}>
                                <i class="feather icon-calendar {{ $sub('installments/agreement-installments') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Installments') }}</span>{!! $planMark($plan['installments'], null) !!}
                            </a>
                        </li>
                    @endif

                    {{-- شاشة إعدادات المبيعات يحرسها `sales.settings.view`، لا `update` —
                         فمن يملك التعديل وحده كان يرى الرابط ويُردّ بـ403 --}}
                    @if (hasPermission('sales.settings.view'))
                        <li class="{{ $planClass($plan['settings']) }}">
                            <a href="{{ $planHref($plan['settings'], 'sittingInvoice.index') }}"{!! $planAttrs($plan['settings'], 'settings') !!}>
                                <i class="feather icon-settings {{ $sub('sales/settingsInvoice') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Sales_Settings') }}</span>{!! $planMark($plan['settings'], null) !!}
                            </a>
                        </li>
                    @endif

                    @if (hasPermission('sales.reports.view'))
                        <li>
                            <a href="{{ route('salesReports.index') }}">
                                <i class="feather icon-bar-chart-2 {{ $sub('sales/reports') }}"></i>
                                <span class="menu-item">{{ trans('main_trans.Sales_Report') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (hasPermission('sales.daily_closing.view'))
                        <li>
                            <a href="{{ route('daily-closing.index') }}">
                                <i class="feather icon-lock {{ $sub('sales/daily-closing') }}"></i>
                                <span class="menu-item">الإقفال اليومي</span>
                            </a>
                        </li>
                    @endif

                @endif
            @endif

        </ul>
    </div>
</div>
