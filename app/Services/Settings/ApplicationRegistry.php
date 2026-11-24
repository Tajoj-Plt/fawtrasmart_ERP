<?php

namespace App\Services\Settings;

use App\Constants\planFeatures\ApplicationPlanFeatures;
use App\Models\ApplicationSetting;
use App\Services\Fawtra\SubscriptionGate;
use Illuminate\Support\Facades\Cache;

/**
 * سجلّ وحدات النظام القابلة للتفعيل والتعطيل.
 *
 * كانت المفاتيح الثلاثة والثلاثون مكتوبة يدوياً في مكانين: قائمة في
 * `ApplicationManagementController::update` وقوائم أخرى موزّعة على سبع
 * بطاقات في القالب. وكل إضافة وحدة تتطلّب تعديل الموضعين معاً — ونسيان
 * أحدهما لا يُنتج خطأً بل عطلاً صامتاً: مفتاح يظهر في الشاشة ولا يُحفَظ،
 * أو يُحفَظ ولا يظهر.
 *
 * المصدر الآن واحد: هذا الملفّ. المتحكّم يشتقّ منه ما يقبله، والقالب
 * يشتقّ منه ما يعرضه.
 */
class ApplicationRegistry
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    private const CACHE_KEY = 'application_settings.map';

    /**
     * الوحدات مجموعةً بالقسم.
     *
     * `routes` بادئات مسارات الوحدة — يستعملها الوسيط ليعرف أي طلب يخصّ
     * وحدة معطَّلة. غيابها يعني وحدة لا تُعرَف مساراتها من بادئة، وحينها
     * يلزم وضع `module:key` صراحةً على مجموعة مساراتها.
     *
     * البادئات مأخوذة من مسارات النظام الفعلية. وما لم أتيقّن من نطاقه
     * تُرك بلا بادئة عمداً: بادئة خاطئة تقفل على المستخدم منطقةً كاملة،
     * وهو خطأ أسوأ من ألّا يعمل المنع تلقائياً. (`programs/*` مثلاً صفحات
     * تعريفية بالوحدات لا شاشاتها، فلم تُربط.)
     *
     * @return array<string, array{icon:string,modules:array<string, array{label:string,hint?:string,route?:string}>}>
     */
    public static function groups(): array
    {
        return [
            __('settings.applications.groups.sales') => [
                'icon' => 'shopping-cart',
                'modules' => [
                    /*
                        `ReturnsInvoice` كانت هنا وهي **للمشتريات**
                        (Modules/Purchases/routes/web.php)، ومرتجعات المبيعات
                        على `return-invoices`. لم يظهر الخطأ ما دامت المطابقة
                        معطَّلةً بمقطع اللغة؛ فلمّا عملت صار تعطيل المبيعات
                        يقفل مرتجعات المشتريات. نُقلت إلى موضعها.
                    */
                    /*
                        إعدادات المبيعات وملحقاتها كانت خارج القائمة: تُعطَّل
                        المبيعات من الشاشة وتبقى «إعدادات الفواتير» والتصاميم
                        ومصادر الطلب والشحن والتبديل والأهداف والعمولات تُفتح
                        بعناوينها. كلُّ بادئةٍ أُضيفت هنا فُحصت على المسارات
                        المسجَّلة فعلاً: لا تسجّل تحتها وحدةٌ غير المبيعات.
                    */
                    'sales' => ['label' => __('settings.applications.modules.sales'), 'hint' => __('settings.applications.modules.sales_hint'), 'routes' => [
                        'sales', 'invoices', 'return-invoices', 'salesReports', 'sales_reports',
                        'sitting', 'shipping-options', 'exchange-reasons', 'product-exchanges',
                        'targets', 'collections-targets', 'weekly-commissions',
                    ]],
                    'pos' => ['label' => __('settings.applications.modules.pos'), 'hint' => __('settings.applications.modules.pos_hint'), 'routes' => ['POS']],
                    'installments_management' => ['label' => __('settings.applications.modules.installments_management')],
                    // عروض الحسومات وعروض الهدايا — نفسُ ميزتَي الباقة في ApplicationPlanFeatures
                    'offers' => ['label' => __('settings.applications.modules.offers'), 'routes' => ['offers', 'gift-offers']],
                ],
            ],
            __('settings.applications.groups.inventory') => [
                'icon' => 'package',
                'modules' => [
                    'inventory_management' => ['label' => __('settings.applications.modules.inventory_management'), 'hint' => __('settings.applications.modules.inventory_management_hint'), 'routes' => ['stock']],
                    /*
                        التصنيع صار وحدةً مستقلّة ببادئة `manufacturing/`.
                        وكان مسجَّلاً هنا **بلا بادئات مسار** — فوسيط
                        `EnsureModuleEnabled` لا يجد ما يطابقه، ويمرّ الطلب
                        بلا حراسة: يُطفأ التطبيق في الشاشة وتبقى صفحاته
                        تُفتح بعناوينها.
                    */
                    'manufacturing' => [
                        'label' => __('settings.applications.modules.manufacturing'),
                        'routes' => ['manufacturing'],
                    ],
                    'purchase_cycle' => ['label' => __('settings.applications.modules.purchase_cycle'), 'hint' => __('settings.applications.modules.purchase_cycle_hint'), 'routes' => ['invoicePurchases', 'ViewPurchasePrice', 'purchase-settings', 'ReturnsInvoice']],
                ],
            ],
            __('settings.applications.groups.accounts') => [
                'icon' => 'dollar-sign',
                'modules' => [
                    'finance' => ['label' => __('settings.applications.modules.finance')],
                    'general_accounts_journal_entries' => ['label' => __('settings.applications.modules.general_accounts_journal_entries')],
                    'cheque_cycle' => ['label' => __('settings.applications.modules.cheque_cycle')],
                ],
            ],
            __('settings.applications.groups.operations') => [
                'icon' => 'settings',
                'modules' => [
                    'work_orders' => ['label' => __('settings.applications.modules.work_orders')],
/*
                        إدارة العقارات والإيجارات. بادئة `realestate` هي قسم
                        العقارات الجديد كلّه؛ أمّا شاشات الحجز القديمة فمساراتها
                        عامّة الأسماء (`orders`, `Settings`) ولا تُدرَج هنا كي
                        لا يبتلع تعطيلُ هذه الوحدة مساراتِ غيرها.
                    */
                    'rental_management' => ['label' => __('settings.applications.modules.rental_management'), 'routes' => ['realestate']],
                    /*
                        الحجوزات. كان المفتاح هنا **بلا بادئة مسار**، فوسيط
                        `EnsureModuleEnabled` لا يجد ما يطابقه ويمرّ الطلب —
                        أي أن تعطيل الوحدة من الشاشة كان يُخفي بندَ القائمة
                        ولا يقفل شيئاً: من يعرف الرابط يدخلها.
                    */
                    'booking_management' => ['label' => __('settings.applications.modules.booking_management'), 'routes' => ['Reservations']],
                    'time_tracking' => ['label' => __('settings.applications.modules.time_tracking')],
                    'workflow' => ['label' => __('settings.applications.modules.workflow')],
                ],
            ],
            __('settings.applications.groups.crm') => [
                'icon' => 'users',
                'modules' => [
                    'customers' => ['label' => __('settings.applications.modules.customers'), 'hint' => __('settings.applications.modules.customers_hint'), 'routes' => ['clients', 'itinerary']],
                    'customer_followup' => ['label' => __('settings.applications.modules.customer_followup')],
                    'customer_attendance' => ['label' => __('settings.applications.modules.customer_attendance')],
                ],
            ],
            __('settings.applications.groups.hr') => [
                'icon' => 'user-check',
                /*
                    عمداً بلا `routes` هنا رغم أن كل مسارات هذه الأربعة تبدأ
                    بـ`hr/`: moduleForPath() يطابق request()->path() كما هو،
                    وهو 'ar/hr/...' لطلبٍ حقيقي (hideDefaultLocaleInURL
                    false يجعل مقطع اللغة حاضراً دوماً) — لا يطابق أبداً بادئةً
                    مسجَّلةً بلا مقطع لغةٍ في أولها. نفسُ هذا يُسقط الاكتشاف
                    التلقائي لبقية المجموعات (sales وstock وclients وغيرها)
                    بصمتٍ كذلك؛ لم يُصلَح هنا لأنه يتجاوز نطاق HR.

                    الحارس هنا مفعَّلٌ فعلياً عبر تسمية المفتاح صراحةً —
                    `Route::middleware('module:employees')` وما شابهها — في
                    Modules/HR/routes/web.php، لا عبر هذه المصفوفة.
                */
                'modules' => [
                    'employees' => ['label' => __('settings.applications.modules.employees')],
                    'organizational_structure' => ['label' => __('settings.applications.modules.organizational_structure')],
                    'employee_attendance' => ['label' => __('settings.applications.modules.employee_attendance')],
                    'salaries' => ['label' => __('settings.applications.modules.salaries')],
                    'orders' => ['label' => __('settings.applications.modules.orders')],
                ],
            ],
            __('settings.applications.groups.general') => [
                'icon' => 'grid',
                'modules' => [
                    'sms' => ['label' => __('settings.applications.modules.sms')],
                    'ecommerce' => ['label' => __('settings.applications.modules.ecommerce'), 'routes' => ['online_store']],
                    'branches' => ['label' => __('settings.applications.modules.branches'), 'routes' => ['branches']],
                    /*
                        بادئات المهام كلّها لا ثلاثٌ منها: الوحدة تسجّل
                        `workspaces` و`task-manager` و`task-dashboard`
                        و`task-reports` و`comments` أيضاً، وكانت خارج هذه
                        القائمة — فيُعطَّل التطبيق من الشاشة وتبقى لوحة
                        المهام وتقاريرها مفتوحةً لمن يعرف عنوانها.
                    */
                    'tasks' => ['label' => __('settings.applications.modules.tasks'), 'routes' => [
                        'tasks', 'sprints', 'projects', 'workspaces',
                        'task-manager', 'task-dashboard', 'task-reports', 'comments',
                    ]],
                ],
            ],
        ];
    }

    /**
     * كل المفاتيح المعروفة.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (static::groups() as $group) {
            $keys = array_merge($keys, array_keys($group['modules']));
        }

        return $keys;
    }

    public static function labelFor(string $key): string
    {
        foreach (static::groups() as $group) {
            if (isset($group['modules'][$key])) {
                return $group['modules'][$key]['label'];
            }
        }

        return $key;
    }

    /**
     * حالات كل الوحدات — مفتاح ← active|inactive.
     *
     * محفوظة في الذاكرة المؤقتة: كانت تُقرأ من القاعدة في كل قائمة جانبية
     * ومرّتين في بعضها، أي استعلامان لكل صفحة في النظام كلّه.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            try {
                $saved = ApplicationSetting::pluck('status', 'key')->all();
            } catch (\Throwable) {
                $saved = [];
            }

            $map = [];

            // المفتاح غير المحفوظ يُعتبر مفعَّلاً: النظام كان يعمل قبل وجود
            // هذه الشاشة، وتعطيلُ وحدة لمجرّد أن أحداً لم يفتح الإعدادات
            // بعدُ سلوكٌ لا يتوقّعه أحد
            foreach (static::keys() as $key) {
                $map[$key] = ($saved[$key] ?? self::ACTIVE) === self::ACTIVE
                    ? self::ACTIVE
                    : self::INACTIVE;
            }

            return $map;
        });
    }

    /**
     * هل فعَّل المسؤولُ هذه الوحدة؟ — **قرار المنشأة وحده.**
     *
     * لا تسأل عن الباقة. ولمن يحرس وصولاً فعلياً: [isAvailable()] هي
     * الجواب، لأن وحدةً مفعَّلةً خارج الباقة لا تعمل مهما قال هذا المفتاح.
     */
    public static function isActive(string $key): bool
    {
        return (static::statuses()[$key] ?? self::ACTIVE) === self::ACTIVE;
    }

    /**
     * هل تشمل باقة فوترة هذه الوحدة؟ — **قرار البائع وحده.**
     *
     * وحدة بلا خريطة في [ApplicationPlanFeatures] مشمولة دائماً؛ والسبب
     * مشروح هناك.
     */
    public static function isPermitted(string $key): bool
    {
        $features = ApplicationPlanFeatures::for($key);

        if ($features === []) {
            return true;
        }

        return SubscriptionGate::permits($features);
    }

    /**
     * هل تعمل الوحدة فعلاً؟ — مفعَّلةً من الشاشة **و**مشمولةً بالباقة.
     *
     * هذا ما تسأله القوائم والوسائط. وفصلُه عن `isActive()` مقصود: الرسالة
     * التي يقرؤها المستخدم تختلف باختلاف الطبقة التي منعته — «عطّلها
     * مسؤولُك» شيء و«باقتك لا تشملها» شيء آخر، ودمجُهما يُنتج رسالةً
     * واحدةً خاطئةً في نصف الحالات.
     */
    public static function isAvailable(string $key): bool
    {
        return static::isActive($key) && static::isPermitted($key);
    }

    /**
     * حالة كل وحدة من الباقة — مفتاح ← مشمولة أو لا.
     *
     * تُبنى مرّةً للشاشة كلّها: `SubscriptionGate` تقرأ لقطةً محفوظة، فلا
     * استعلام هنا مهما كثرت الوحدات.
     *
     * @return array<string, bool>
     */
    public static function permissions(): array
    {
        $map = [];

        foreach (static::keys() as $key) {
            $map[$key] = static::isPermitted($key);
        }

        return $map;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * الوحدة التي يخصّها هذا المسار، أو null إن لم يخصّ وحدةً بعينها.
     *
     * تُطابَق أطول بادئة أولاً كي لا تلتقط `sales` مسارات `sales_reports`
     * لو أُضيفت وحدة بهذا الاسم لاحقاً.
     *
     * ─────────────────── مقطع اللغة يُنزع أولاً ───────────────────
     *
     * `hideDefaultLocaleInURL => false` يجعل كل مسارٍ حقيقي يبدأ بمقطع
     * لغة: `ar/sales/index` لا `sales/index`. والبادئات هنا مسجَّلة بلا
     * مقطع لغة، فكانت المطابقة تفشل **دائماً** في الطلبات الحقيقية: تُطفأ
     * الوحدة في الشاشة وتبقى صفحاتها تُفتح بعناوينها، والحارس لا يحرس إلا
     * حيث كُتب `module:key` صراحةً على المسار (مسارات HR وحدها).
     *
     * فيُنزع المقطع الأول إن كان لغةً مدعومة — ولا يُنزع سواه: مسارٌ اسمه
     * الأول يشبه رمز لغةٍ غير مدعومة يبقى كما هو.
     */
    public static function moduleForPath(string $path): ?string
    {
        $path = static::withoutLocale(trim($path, '/'));
        $matches = [];

        // بلا حساسية لحالة الأحرف: مسارات النظام تخلط `POS` و`pos`
        // و`Accounts` و`accounts`
        $lower = mb_strtolower($path);

        foreach (static::groups() as $group) {
            foreach ($group['modules'] as $key => $module) {
                foreach ($module['routes'] ?? [] as $prefix) {
                    $needle = mb_strtolower($prefix);

                    if ($lower === $needle || str_starts_with($lower, $needle.'/')) {
                        $matches[$needle] = $key;
                    }
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        uksort($matches, fn ($a, $b) => strlen($b) <=> strlen($a));

        return reset($matches);
    }

    /**
     * المسار بلا مقطع اللغة الأول.
     *
     * اللغات المدعومة تُقرأ من الإعداد بعد أن يُضيّقه
     * `AppServiceProvider::restrictSupportedLocalesToActiveLanguages()` على
     * الفعّال في `system_languages` — فتفعيل لغةٍ جديدة يُدرَك هنا بلا تعديل.
     */
    protected static function withoutLocale(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        $segments = explode('/', $path);
        $locales = array_map(
            'mb_strtolower',
            array_keys((array) config('laravellocalization.supportedLocales', []))
        );

        if (in_array(mb_strtolower($segments[0]), $locales, true)) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }
}
