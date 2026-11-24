{{--
    الشريط الجانبي لوحدة التصنيع.

    كان التصنيع بنداً داخل شريط المخزون، وبنودُه تُبرز نشاطها بمقارنة
    `request()->is("$getLocal/Manufacturing/BOM/*")` — وهو مسارٌ لم يوجد
    قطّ (المسارات كانت تحت `stock/BOM/…`). فلا يُبرَز البند الحالي أبداً،
    ولا يعرف الداخل أين هو من القائمة.

    وحين صار التصنيع وحدةً قائمةً لزمه شريطُه: بنوده كلّها في مستوًى واحد
    لا مطويّة تحت غيره، ومسارات الإبراز تُبنى من `route()` نفسها فلا
    تتخلّف عنها إن تغيّرت البادئة مرّةً أخرى.
--}}
@php
    /*
        كان هنا `$getLocal = LaravelLocalization::setLocale() ?: app()->getLocale();`
        — بقيّةٌ من شريط المخزون حين كان الإبراز يقارن مساراتٍ مكتوبةً بيدها.

        وهو ضارٌّ من وجهين. الأول أنه ميت: بعد أن صار `$isOn` يشتقّ المسار
        من `route()` لم يعد `$getLocal` يُستعمل في سطرٍ واحد.

        والثاني أخطر: `setLocale()` بلا وسيط لا يقرأ اللغة فحسب، بل **يضبطها**.
        فإن لم تجد ما ترجع إليه رجعت إلى اللغة الافتراضية — فتنقلب لغة الطلب
        من العربية إلى الإنجليزية في منتصف تصيير الشريط. وما بعده من
        `trans('main_trans.*')` يُقرأ بالإنجليزية؛ ومفاتيح مثل
        `Manufacturing_Orders` غير معرَّفةٍ في `en`، فيُطبع المفتاح نيّئاً:
        «main_trans.Manufacturing_Orders».
    */

    /**
     * هل الصفحة الحالية هي هذا المسار أو أحد أبنائه؟
     *
     * يُقارَن بالعنوان المولَّد من اسم المسار لا بنصٍّ مكتوب بيده — فلو
     * تغيّرت البادئة بقي الإبراز صحيحاً.
     */
    $isOn = function (string $routeName): bool {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            return false;
        }

        $path = trim(parse_url(route($routeName), PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return false;
        }

        return request()->is($path) || request()->is($path.'/*')
            || request()->is(dirname($path).'/*');
    };

    $sections = [
        [
            'label' => 'لوحة التصنيع',
            'icon' => 'home',
            'route' => 'manufacturing.dashboard',
            'permission' => 'stock.manufacturing_orders.view',
            // اللوحة بابُ دخولٍ عامّ: تكفيها أيُّ ميزةٍ من الأربع
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::manufacturingOnly(),
        ],
        /*
            خطط الإنتاج قبل الأوامر: ترتيبُ الشريط يحكي مسارَ العمل —
            تُخطَّط الدفعةُ أوّلاً ثمّ تُولَّد أوامرُها.

            وميزتُها في الباقة ميزةُ أوامر التصنيع: ما تُنتجه أوامر، فلا
            يُعرض بندُها لباقةٍ لا تشمل الأوامر — كما يُحرَس مسارُها.
        */
        [
            'label' => trans('main_trans.Production_Plans'),
            'icon' => 'calendar',
            'route' => 'manufacturing.production-plans.index',
            'permission' => 'stock.production_plans.view',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING,
        ],
        [
            'label' => trans('main_trans.Manufacturing_Orders'),
            'icon' => 'clipboard',
            'route' => 'manufacturing.orders.index',
            'permission' => 'stock.manufacturing_orders.view',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING,
        ],
        [
            'label' => trans('main_trans.Bill_of_Materials'),
            'icon' => 'layers',
            'route' => 'BOM.index',
            'permission' => 'stock.bom.view',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::BOM,
        ],
        [
            'label' => trans('main_trans.Workstations'),
            'icon' => 'cpu',
            'route' => 'manufacturing.workstations.index',
            'permission' => 'stock.workstations.view',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::WORKSTATIONS,
        ],
        [
            'label' => trans('main_trans.Indirect_Costs'),
            'icon' => 'percent',
            'route' => 'manufacturing.indirectcosts.index',
            'permission' => 'stock.indirect_costs.view',
            // لا مفتاحَ لها في الكتالوج — تتبع أوامر التصنيع، كما في المسارات
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING,
        ],
        /*
            كان يشير إلى `ReportsProduction.index` — مسارٍ في وحدة التقارير
            يستدعي `RentalsController@production`، دالةً لا وجود لها. فكان
            الرابط يُنتج 500 لا تقريراً. صار يقود إلى تقارير الوحدة نفسها.
        */
        [
            'label' => 'تقارير التصنيع',
            'icon' => 'bar-chart-2',
            'route' => 'manufacturing.reports.index',
            'permission' => 'stock.reports.manufacturing',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::REPORTS,
        ],
        [
            'label' => trans('main_trans.Sittings'),
            'icon' => 'settings',
            'route' => 'Manufacturing.settings.index',
            /*
                كانت `stock.manufacturing_settings.view` — وهي صلاحيةٌ غير
                معرَّفة في القاعدة. و`can()` على صلاحيةٍ غير موجودة تُرجع
                false دائماً، فبندُ الإعدادات لا يظهر لأحدٍ أبداً وإن مَلَك
                الصلاحية. والاسم القائم فعلاً هو ما يحرس به المسارُ نفسَه.
            */
            'permission' => 'stock.settings.manufacturing',
            'plan' => \App\Constants\planFeatures\StockPlanFeatures::MANUFACTURING,
        ],
    ];
@endphp

<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow" data-scroll-to-active="true">
    <div class="navbar-header">
        <ul class="nav navbar-nav flex-row">
            <li class="nav-item mr-auto">
                <a class="navbar-brand" href="{{ route('dashboard') }}">
                    <div class="brand-logo"></div>
                    <h2 class="brand-text mb-0">التصنيع</h2>
                </a>
            </li>
            <li class="nav-item nav-toggle">
                <a class="nav-link modern-nav-toggle pr-0" data-toggle="collapse">
                    <i class="feather icon-x d-block d-xl-none font-medium-4 primary toggle-icon"></i>
                    <i class="toggle-icon feather icon-disc font-medium-4 d-none d-xl-block collapse-toggle-icon primary"></i>
                </a>
            </li>
        </ul>
    </div>

    <div class="shadow-bottom"></div>

    <div class="main-menu-content">
        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            {{-- العودة إلى الرئيسية: الوحدة مستقلّة، فيلزم مخرجٌ منها --}}
            <li class="nav-item">
                <a href="{{ route('dashboard') }}">
                    <i class="feather icon-grid"></i>
                    <span class="menu-title">القائمة الرئيسية</span>
                </a>
            </li>

            @foreach ($sections as $section)
                @continue(! \Illuminate\Support\Facades\Route::has($section['route']))
                @continue($section['permission'] && ! auth()->user()->can($section['permission']))
                {{--
                    حدُّ الباقة — طبقةٌ ثالثة إلى جانب وجود المسار والصلاحية.

                    بدونها يرى المستخدمُ بنداً لميزةٍ لم تشترها منشأتُه، فينقره
                    فيردّه `EnsurePlanFeature` بصفحة 402. أي أن الشريط يعرض
                    باباً يعرف أنه مقفل — وهو ما يجعل «رقِّ باقتك» رسالةَ عطلٍ
                    لا رسالةَ عرض.

                    والمصفوفة تعني «تكفي واحدة» — وهي حال اللوحة الجامعة.
                    وميزةٌ لا يعرفها الكتالوج تُعرَض (انظر `SubscriptionGate::allows()`)
                    فلا يُخفى ما لم يقصد أحدٌ إخفاءه.
                --}}
                @continue(! empty($section['plan']) && ! \App\Services\Fawtra\SubscriptionGate::permits($section['plan']))

                <li class="nav-item {{ $isOn($section['route']) ? 'active' : '' }}">
                    <a href="{{ route($section['route']) }}">
                        <i class="feather icon-{{ $section['icon'] }}"></i>
                        <span class="menu-title">{{ $section['label'] }}</span>
                    </a>
                </li>
            @endforeach

            {{--
                كان هنا رابطٌ إلى لوحة المخزون بحجّة أن المخزون شريكُ التصنيع
                في المواد. وهو خطأ: الشريط الجانبي يعرّف حدودَ الوحدة، فرابطٌ
                إلى وحدةٍ أخرى داخله يُبطل الفصل الذي جُعل من أجله. والانتقالُ
                بين الوحدات موضعه الصفحةُ الرئيسية وبطاقةُ الأقسام في الشريط
                العلوي.
            --}}
        </ul>
    </div>
</div>
