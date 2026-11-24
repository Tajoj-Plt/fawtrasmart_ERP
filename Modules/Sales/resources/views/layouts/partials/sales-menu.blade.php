{{--
    مجموعة «المبيعات» في الشريط الجانبي — **المصدر الوحيد لها**.

    ══════════════════ لماذا جزءٌ مشترك؟ ══════════════════

    كانت هذه القائمة مكتوبةً **ثلاث مرّات**: هنا في شريط وحدة المبيعات،
    ومرّتين في `resources/views/layouts/sidebar.blade.php` — واحدةٌ في فرع
    الموظّف وأخرى في فرع المدير. والنسخ الثلاث تباعدت:

      • نسخة المدير تفحص بـ`@can('sales.quotes.create')` — أسماءُ صلاحياتٍ
        من النظام القديم، و`@can` تسأل Spatie مباشرةً بينما صلاحيات هذا
        النظام على الدور (`role_id` ← `role_has_permissions`)، انظر
        `app/Helpers/PermissionHelper.php`.
      • ولا تعرف النسختان شيئاً عن حدود الاشتراك ولا عن «شاشة التطبيقات»
        ولا عن مفاتيح «إعدادات الفواتير».

    فأيّ بندٍ يُضاف إلى إحداها يغيب عن الأخريين: أُضيفت «أوامر البيع» إلى
    شريط الوحدة فظهرت في شاشات المبيعات وحدها، وغابت عن كل شاشةٍ في
    المخزون أو المشتريات أو الإعدادات — وهي أكثر ما يُتنقَّل منه.

    الآن: ملفٌّ واحد يُضمَّن من الشريطين.

    ══════════════════ المتغيّرات ══════════════════

    يُحسب كلُّ ما يلزمه بنفسه، إلّا أن يكون الأب قد حسبه — فشريط الوحدة
    يحتاج المساعدات نفسها لمجموعاته الأخرى (الأقساط وأوامر التوريد
    والتقارير)، فتُقرأ منه بلا إعادة حساب. و`@include` يمرّر متغيّرات الأب
    إلى الابن، فالفحص `isset($planHref)` يكفي للتفريق.
--}}
@php
    use App\Constants\planFeatures\SalesPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;

    $getLocal = $getLocal ?? App::getLocale();

    /* «إعدادات الفواتير» — تُقرأ **مرّةً واحدة** هنا لا عند كل بند.
       كانت ثلاثة بنودٍ تنادي `SalesInvoiceSetting::current()` كلٌّ في
       شرطه: ثلاثة استعلامات في كل تحميل، وثلاثة مواضع تُصلَح إن اختلّت
       القراءة. */
    $invoiceFlags = \App\Models\SalesInvoiceSetting::current();

    /* الأب حسبها؟ تُقرأ منه. وإلّا تُحسب هنا — انظر تعليق الرأس. */
    if (! isset($planHref)) {


        /*
         * إعدادات التطبيقات — المفتاح ← الحالة.
         *
         * الفحص بالنوع لا بـ`isset()` وحدها. `@extends` يمرّر متغيّرات قالب
         * الشاشة كلَّها إلى `sales::master` عبر `get_defined_vars()`، ومنها
         * إلى هنا. فأيّ شاشة تُعرّف `$settings` لنفسها — وهو اسمٌ شائع —
         * تطمس هذه المصفوفة، فيصير `$settings['sales']` قراءةً على كائنٍ أو
         * نصّ، وتختفي مجموعات القائمة كلُّها بلا خطأ يُرى. وقع هذا فعلاً في
         * شاشة «إنشاء فاتورة مبيعات» فظهرت بقائمة جانبية شبه فارغة.
         */
        $settings = (isset($settings) && is_array($settings))
            ? $settings
            : \App\Models\ApplicationSetting::pluck('status', 'key')->toArray();

        /** أمفعَّلٌ هذا التطبيق في «شاشة التطبيقات»؟ */
        $appOn = fn (string $key) => ($settings[$key] ?? null) === 'active';

        /** أيقونة الرابط الفرعي، مؤشَّرةً حين يقع المسار الحالي داخل بادئتها */
        $sub = fn (string $prefix) => request()->is("$getLocal/$prefix") ? 'active' : '';

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

        /** القسم كلّه مقفولٌ حين لا تشمل الباقة شيئاً منه — ويبقى ظاهراً. */
        $planAnySales = SubscriptionGate::permits(SalesPlanFeatures::all());
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
    }
@endphp

@once
    @push('styles')
        {{-- البند المقفول مخفَّتٌ لا مخفيّ — انظر تعليق `$plan` --}}
        <style>
            .sl-plan-locked > a { opacity: .72; }
            .sl-plan-locked > a:hover { opacity: 1; }
        </style>
    @endpush
@endonce

{{-- ══════════ المبيعات ══════════ --}}
@php
    /*
        بوّابةُ المجموعة = اتحادُ حرّاس بنودها، لا كلُّ صلاحيات المبيعات.

        كانت تسرد ثلاثين اسماً، أكثرُها صلاحياتُ إنشاءٍ وتعديلٍ وحذفٍ وطباعةٍ
        لا بندَ لها في القائمة (البنودُ كلُّها تفحص `…​.view`). فمن يملك
        «إنشاء فاتورة» ولا يملك «عرض الفواتير» كانت تُفتح له مجموعةُ
        «المبيعات» **فارغة**: عنوانٌ يُنقر فلا شيء تحته. وستٌّ وعشرون صلاحيةً
        كانت تفعل ذلك.
    */
    $hasSalesPermissions = canAny([
        'sales.dashboard.view',
        'sales.invoices.view',
        'sales.quotes.view',
        'sales.sales_orders.view',
        'sales.credit_notes.view',
        'sales.returns.view',
        'sales.periodic_invoices.view',
        'sales.payments.view',
        'sales.settings.view',
        'sales.commissions.view',
    ]);
@endphp

@if ($hasSalesPermissions && $appOn('sales'))
    <li class="nav-item {{ request()->is("$getLocal/sales/*") ? 'active open' : '' }} {{ $planClass($planAnySales) }}">
        <a href="#"{!! $planAttrs($planAnySales, 'settings') !!}>
            <i class="feather icon-align-justify"></i>
            <span class="menu-title" data-i18n="Sales">{{ trans('main_trans.sales') }}</span>
        </a>

        <ul class="menu-content">
            @if (hasPermission('sales.dashboard.view'))
                <li class="{{ $planClass($plan['dashboard']) }}">
                    <a href="{{ $planHref($plan['dashboard'], 'dashboard.kpis') }}"{!! $planAttrs($plan['dashboard'], 'dashboard') !!}>
                        <i class="feather icon-circle {{ $sub('sales/dashboard/kpis') }}"></i>
                        <span class="menu-item">مؤشرات الأداء</span>{!! $planMark($plan['dashboard'], null) !!}
                    </a>
                </li>
                {{-- أداء المندوبين — جدولُ اللوحة كاملاً بالأكثر تحصيلاً؛ صلاحيةُ اللوحة نفسُها (الشاشة تفتح `DashboardController::reps`) --}}
                <li class="{{ $planClass($plan['dashboard']) }}">
                    <a href="{{ $planHref($plan['dashboard'], 'dashboard.reps') }}"{!! $planAttrs($plan['dashboard'], 'dashboard') !!}>
                        <i class="feather icon-circle {{ $sub('sales/reps-performance') }}"></i>
                        <span class="menu-item">{{ __('sales_dashboard.reps.title') }}</span>{!! $planMark($plan['dashboard'], null) !!}
                    </a>
                </li>
            @endif

            @if (canAny(['sales.invoices.view']))
                <li class="{{ $planClass($plan['invoices']) }}">
                    <a href="{{ $planHref($plan['invoices'], 'invoices.index') }}"{!! $planAttrs($plan['invoices'], 'invoices') !!}>
                        <i class="feather icon-circle {{ $sub('sales/invoices/index') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.invoice_management') }}</span>{!! $planMark($plan['invoices'], $planLimits['invoices']) !!}
                    </a>
                </li>
            @endif


            @if (canAny(['sales.quotes.view']) && ! $invoiceFlags->disable_quotes)
                <li class="{{ $planClass($plan['quotes']) }}">
                    <a href="{{ $planHref($plan['quotes'], 'questions.index') }}"{!! $planAttrs($plan['quotes'], 'quotes') !!}>
                        <i class="feather icon-circle {{ $sub('sales/questions/index') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Quotation_Management') }}</span>{!! $planMark($plan['quotes'], $planLimits['quotes']) !!}
                    </a>
                </li>
            @endif


            {{-- أوامر البيع — مفتاحُها «تفعيل أوامر البيع» في «إعدادات
                 الفواتير» ← قسم «عروض الأسعار وأوامر البيع». --}}
            @if (canAny(['sales.sales_orders.view']) && $invoiceFlags->enable_sales_orders)
                <li class="{{ $planClass($plan['quotes']) }}">
                    <a href="{{ $planHref($plan['quotes'], 'sales_orders.index') }}"{!! $planAttrs($plan['quotes'], 'quotes') !!}>
                        <i class="feather icon-circle {{ $sub('sales/sales-orders') }}"></i>
                        <span class="menu-item">{{ __('sales_documents.sales_order.doc.title') }}</span>{!! $planMark($plan['quotes'], $planLimits['quotes']) !!}
                    </a>
                </li>
            @endif


            @if (canAny(['sales.credit_notes.view']))
                <li class="{{ $planClass($plan['credit_notes']) }}">
                    <a href="{{ $planHref($plan['credit_notes'], 'CreditNotes.index') }}"{!! $planAttrs($plan['credit_notes'], 'credit_notes') !!}>
                        <i class="feather icon-circle {{ $sub('sales/CreditNotes/*') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Credit_notes') }}</span>{!! $planMark($plan['credit_notes'], $planLimits['credit_notes']) !!}
                    </a>
                </li>
            @endif

            @if (canAny(['sales.returns.view']))
                <li class="{{ $planClass($plan['returns']) }}">
                    <a href="{{ $planHref($plan['returns'], 'ReturnIInvoices.index') }}"{!! $planAttrs($plan['returns'], 'returns') !!}>
                        <i class="feather icon-circle {{ $sub('sales/invoices/invoices_returned') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Returned_invoices') }}</span>{!! $planMark($plan['returns'], $planLimits['returns']) !!}
                    </a>
                </li>
            @endif

            @if (hasPermission('sales.periodic_invoices.view'))
                <li class="{{ $planClass($plan['periodic']) }}">
                    <a href="{{ $planHref($plan['periodic'], 'periodic_invoices.index') }}"{!! $planAttrs($plan['periodic'], 'periodic') !!}>
                        <i class="feather icon-circle {{ $sub('sales/periodic-invoices/*') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Periodic_invoices') }}</span>{!! $planMark($plan['periodic'], $planLimits['periodic']) !!}
                    </a>
                </li>
            @endif

            @if (canAny(['sales.payments.view']))
                <li class="{{ $planClass($plan['payments']) }}">
                    <a href="{{ $planHref($plan['payments'], 'paymentsClient.index') }}"{!! $planAttrs($plan['payments'], 'payments') !!}>
                        <i class="feather icon-circle {{ $sub('sales/paymentsClient/*') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Customer_payments') }}</span>{!! $planMark($plan['payments'], $planLimits['payments']) !!}
                    </a>
                </li>
            @endif






            {{-- «محفظة العملاء» كانت في الشريط العامّ وحده ولا وجود لها في
                 شريط الوحدة، فكانت ستذهب لحظةَ توحيد القائمتين.

                 وكان معها «تبديل بضاعة» و«اختبار الطباعة على الفواتير»،
                 وأُسقطتا من القائمة بطلب المستخدم. «تبديل بضاعة» قائمةٌ
                 ومسارها `product-exchanges.index` يُفتح من لوحة إعدادات
                 المبيعات؛ و«اختبار الطباعة» أُزيلت مع نظام القوالب القديم. --}}
            {{-- «عمولاتي الأسبوعية» كانت معلّقةً في قائمة الموارد البشرية،
                 وهي شاشةُ مبيعاتٍ ومحاسبة: تقرأ SalesTargetSettlement وتصرف
                 من خزينة الموظف بقيدٍ في JournalEntry، ولا تمسّ الحضور.
                 نُقلت إلى قائمتها.

                 وكانت هناك بلا حارسٍ يطابق المتحكّم — يراها من لا يملك
                 sales.commissions.view ثمّ يصطدم بـ 403. والمسار المفحوص
                 كان `sales/weekly-commissions` والعنوان الفعليّ
                 `/weekly-commissions` بلا مقطع sales، فما أُبرز العنصرُ قطّ. --}}
            @if (hasPermission('sales.commissions.view'))
                <li>
                    <a href="{{ route('sales.weekly-commissions.index') }}">
                        <i class="feather icon-circle {{ $sub('weekly-commissions*') }}"></i>
                        <span class="menu-item">عمولاتي الأسبوعية</span>
                    </a>
                </li>
            @endif

            {{-- شاشة إعدادات المبيعات يحرسها `sales.settings.view`، لا `update` —
                 فمن يملك التعديل وحده كان يرى الرابط ويُردّ بـ403 --}}
            @if (hasPermission('sales.settings.view'))
                <li class="{{ $planClass($plan['settings']) }}">
                    <a href="{{ $planHref($plan['settings'], 'sittingInvoice.index') }}"{!! $planAttrs($plan['settings'], 'settings') !!}>
                        <i class="feather icon-circle {{ $sub('sales/settingsInvoice') }}"></i>
                        <span class="menu-item">{{ trans('main_trans.Sales_Settings') }}</span>{!! $planMark($plan['settings'], null) !!}
                    </a>
                </li>
            @endif
        </ul>
    </li>
@endif
