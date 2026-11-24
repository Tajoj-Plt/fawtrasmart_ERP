<?php

namespace App\Support;

use App\Models\ClientPermission;
use Illuminate\Support\Facades\Cache;

/**
 * سجلّ صلاحيات بوابة العميل — المصدر الوحيد لتعريفها وقراءتها وللتنقّل فيها.
 *
 * **ما كان**
 *
 * شاشة «صلاحيات العميل» كانت تقسّم المفاتيح بـ
 * `$ClientPermissions->where('category', 'basic')` وجدول `client_permissions`
 * **لا عمود `category` فيه** — فالأقسام تخرج فارغةً دائماً: الشاشة تُفتح بلا
 * مفتاحٍ واحد، والحفظ يُطفئ الثمانية جميعاً. وحتى لو عُرضت، لم يكن أحدٌ يقرأ
 * `is_active`: ثماني مفاتيح تُخزَّن ولا تحرس شيئاً.
 *
 * **ما صار**
 *
 * التقسيم هنا في الكود لا في عمودٍ بالجدول — والجدول يحفظ `is_active` وحدها
 * وهي كلُّ ما يخصّه. ولكلّ مفتاحٍ هنا ثلاثةُ أشياء تُشتقّ منه:
 *
 *   `routes` ← ما يُسمح به في `ClientAccessMiddleware`
 *   `nav`    ← بطاقتُه ورابطُه في تنقّل البوابة
 *   `label`  ← اسمُه في شاشة الإعدادات
 *
 * فمصدرٌ واحد يحكم الحارس والقائمة والشاشة معاً: أطفئ «كشف الحساب» فيختفي
 * تبويبُه ويُقفل راوته في اللحظة نفسها. ولا قائمةَ تنقّلٍ مكتوبةٌ بيدٍ في
 * القالب تفترق عمّا يُسمح به فعلاً — فرابطٌ يظهر ثم يُردّ صاحبُه أسوأ من
 * رابطٍ لا يظهر.
 *
 * **مفاتيح بلا شاشة**: `view_work_orders` و`view_shared_notes_attachments`
 * لا شاشةَ لها بعد. تُعرَض في الإعدادات موسومةً «لا شاشة بعد» بدل أن تُعرض
 * كأنها تعمل — مفتاحٌ يَعِد ولا يفي أسوأ من مفتاحٍ يقول إنه لم يُبنَ.
 *
 * @see \Modules\Client\Http\Controllers\ClientSettingController::permission()
 * @see \App\Http\Middleware\ClientAccessMiddleware
 * @see \Modules\Client\resources\views\portal\layout.blade.php التنقّل
 */
class ClientPortalPermissions
{
    /** مفتاح البوّابة الرئيسي — إطفاؤه يقفل البوابة كلَّها. */
    public const MASTER = 'activate_client_registration';

    /** كاش حالة التفعيل (ساعة) — يُنظَّف عند كل حفظ. */
    private const CACHE_KEY = 'client_portal_permissions.active';

    /**
     * أقسام صلاحيات البوابة ومفاتيحها.
     *
     * لكل مفتاح:
     *   label   العنوان في شاشة الإعدادات
     *   hint    سطرٌ يشرح ما يفتحه المفتاح للعميل بالضبط
     *   routes  أسماء الراوتات التي يحرسها — مصدر قائمة السماح
     *   nav     بطاقتُه في تنقّل البوابة: [route, label, icon, desc]
     *           أو `null` لمفتاحٍ لا يقابله تبويبٌ مستقلّ
     *
     * @return array<string, array{label:string, hint:string, icon:string, permissions:array<string, array>}>
     */
    public static function groups(): array
    {
        return [
            'access' => [
                'label' => 'الدخول والحساب',
                'hint' => 'ما يفتح البوابة للعميل أصلاً، وما يراه ويعدّله في ملفّه.',
                'icon' => 'log-in',
                'permissions' => [
                    self::MASTER => [
                        'label' => 'تفعيل دخول العميل للبوابة',
                        'hint' => 'المفتاح الرئيسي: إطفاؤه يمنع كل حسابات العملاء من الدخول مهما كانت بقية المفاتيح.',
                        'routes' => [],
                        'nav' => null,
                    ],
                    'view_client_profile' => [
                        'label' => 'مشاهدة ملفّه الشخصي',
                        'hint' => 'الصفحة الرئيسية للعميل: بياناته وملخّص فواتيره ومستحقّاته.',
                        'routes' => ['clients.personal'],
                        'nav' => [
                            'route' => 'clients.personal',
                            'label' => 'الرئيسية',
                            'icon' => 'home',
                            'desc' => 'ملخّص حسابك ومستحقّاتك',
                        ],
                    ],
                    'edit_client_profile' => [
                        'label' => 'تعديل ملفّه الشخصي',
                        'hint' => 'يعدّل بيانات تواصله بنفسه (الهاتف والعنوان ونحوها) بلا مراجعة موظّف.',
                        'routes' => ['clients.profile', 'clients.Client_store'],
                        'nav' => [
                            'route' => 'clients.profile',
                            'label' => 'بياناتي',
                            'icon' => 'user',
                            'desc' => 'تعديل بيانات التواصل',
                        ],
                    ],
                ],
            ],

            'documents' => [
                'label' => 'المستندات والمعاملات',
                'hint' => 'ما يطّلع عليه العميل من مستنداته عندنا.',
                'icon' => 'file-text',
                'permissions' => [
                    'view_and_pay_invoices' => [
                        'label' => 'مشاهدة فواتيره',
                        'hint' => 'قائمة فواتيره وحالتها ومستحقّها، والفواتير الآجلة أوّلاً.',
                        'routes' => ['clients.invoice_client'],
                        'nav' => [
                            'route' => 'clients.invoice_client',
                            'label' => 'فواتيري',
                            'icon' => 'file-text',
                            'desc' => 'كل فواتيرك وحالتها',
                        ],
                    ],
                    'view_account_statement' => [
                        'label' => 'مشاهدة كشف حسابه',
                        'hint' => 'حركة حسابه عندنا: كل مدينٍ ودائنٍ برصيدٍ متسلسل — من قيود حسابه المحاسبي.',
                        'routes' => ['clients.portal.statement'],
                        'nav' => [
                            'route' => 'clients.portal.statement',
                            'label' => 'كشف الحساب',
                            'icon' => 'book-open',
                            'desc' => 'حركة حسابك ورصيدك',
                        ],
                    ],
                    /* المرتجعاتُ فواتيرُ `type = 'returned'` لا جدولٌ مستقلّ،
                       ومع ذلك مفتاحُها مستقلٌّ عن «مشاهدة فواتيره»: منشأةٌ
                       تُطلع عميلَها على فواتيره ولا تفتح له سجلَّ ما رُدّ. */
                    'view_returns' => [
                        'label' => 'مشاهدة مرتجعاته',
                        'hint' => 'ما أرجعه من فواتير: رقمُ المرتجع وتاريخُه والفاتورةُ الأصلية وقيمتُه، وهل استُرِدّ نقداً أم نزل من مستحقّه.',
                        'routes' => ['clients.portal.returns'],
                        'nav' => [
                            'route' => 'clients.portal.returns',
                            'label' => 'المرتجعات',
                            'icon' => 'undo',
                            'desc' => 'ما أرجعتَه وكيف سُوِّي',
                        ],
                    ],
                    /* المحفظةُ مالُ العميل عندنا (حسابٌ في الخصوم) لا دَينُه
                       علينا — غيرُ كشف الحساب تماماً، ولذلك مفتاحٌ آخر.
                       عرضٌ فقط: الإيداعُ والسحبُ قيودٌ يكتبها موظّف. */
                    'view_wallet' => [
                        'label' => 'مشاهدة محفظته',
                        'hint' => 'رصيدُه المحفوظ عندنا وحركتُه (إيداعٌ وسحبٌ وسدادُ فواتير). لا يودع ولا يسحب من البوابة.',
                        'routes' => ['clients.portal.wallet'],
                        'nav' => [
                            'route' => 'clients.portal.wallet',
                            'label' => 'محفظتي',
                            'icon' => 'wallet',
                            'desc' => 'رصيدك عندنا وحركته',
                        ],
                    ],
                    'view_and_approve_quotes' => [
                        'label' => 'مشاهدة عروض الأسعار',
                        'hint' => 'عروض الأسعار الصادرة له. الموافقة عليها من البوابة لم تُبنَ بعد.',
                        'routes' => ['clients.questions_client'],
                        'nav' => [
                            'route' => 'clients.questions_client',
                            'label' => 'عروض الأسعار',
                            'icon' => 'tag',
                            'desc' => 'العروض الصادرة لك',
                        ],
                    ],
                    /* السدادُ مفتاحٌ مستقلٌّ عن المشاهدة: منشأةٌ تُطلع عميلَها
                       على فواتيره ولا تقبل منه إعلانَ سدادٍ يراجعه موظّف.
                       ولا مالَ يدخل بهذا المفتاح — الإشعارُ إعلانُ نيّةٍ
                       يعتمده موظّفٌ فيُنشأ سندُ القبض. */
                    'submit_payment_notice' => [
                        'label' => 'إرسال إشعار سداد لفواتيره الآجلة',
                        'hint' => 'يُعلن العميل سدادَ فاتورةٍ (تحويلاً أو شبكةً أو نقداً) ويرفق إيصاله، ولا يُسجَّل سندُ قبضٍ حتى يعتمده موظّف من «إشعارات السداد».',
                        'routes' => ['clients.portal.payments.store', 'clients.portal.payments.cancel'],
                        'nav' => null,
                    ],
                    'view_work_orders' => [
                        'label' => 'عرض أوامر الشغل',
                        'hint' => 'لا شاشة له في البوابة بعد — المفتاح محفوظٌ للتفعيل حين تُبنى.',
                        'routes' => [],
                        'nav' => null,
                    ],
                ],
            ],

            'followup' => [
                'label' => 'المتابعة والمواعيد',
                'hint' => 'ما يشارَك به العميل من متابعة موظّفينا له.',
                'icon' => 'calendar',
                'permissions' => [
                    'view_appointments' => [
                        'label' => 'عرض المواعيد',
                        'hint' => 'مواعيده المسجَّلة عندنا وتواريخها.',
                        'routes' => ['clients.appointments_client'],
                        'nav' => [
                            'route' => 'clients.appointments_client',
                            'label' => 'مواعيدي',
                            'icon' => 'calendar',
                            'desc' => 'زياراتك ومواعيدك',
                        ],
                    ],
                    /* الحجزُ من البوابة: يُسجَّل «بانتظار التأكيد» دائماً
                       ويؤكّده موظّفٌ من شاشة الحجوزات — طلب المستخدم
                       2026-09-18. والأوقاتُ المعروضة من حارس التوفّر نفسِه
                       الذي يحكم حجزَ الموظّف، فلا يرى العميلُ وقتاً مشغولاً. */
                    'book_reservations' => [
                        'label' => 'حجز موعد بنفسه',
                        'hint' => 'يختار العميل خدمةً ويوماً ووقتاً متاحاً فيُسجَّل حجزٌ «بانتظار التأكيد» ويصل إشعارٌ لموظّفينا. يتطلّب خدماتٍ لها أوقاتٌ وموظّفون بورديّات.',
                        'routes' => [
                            'clients.portal.reservations.index',
                            'clients.portal.reservations.create',
                            'clients.portal.reservations.store',
                            'clients.portal.reservations.slots',
                        ],
                        'nav' => [
                            'route' => 'clients.portal.reservations.index',
                            'label' => 'حجوزاتي',
                            'icon' => 'bookmark',
                            'desc' => 'احجز موعداً وتابع حجوزاتك',
                        ],
                    ],
                    /* طلبُ الزيارة **لا يحجز وقتَ مندوب** خلافاً للحجز أعلاه:
                       خطُّ السير الميداني يرتّبه من يعرف حمولةَ اليوم
                       والمسارَ والنطاق، فالطلبُ رغبةٌ يعتمدها موظّفٌ ثمّ
                       يضيف العميلَ إلى الخطّ بنفسه — قاعدةُ إشعار السداد. */
                    'request_field_visit' => [
                        'label' => 'طلب زيارة ميدانية',
                        'hint' => 'يطلب العميل أن يزوره مندوب ويقترح يوماً وفترةً وسبباً، فيصل إشعارٌ لموظّفينا ويُعتمد الطلبُ أو يُرفض بسبب. لا يدخل خطَّ سير أحدٍ حتى يعتمده موظّف.',
                        'routes' => [
                            'clients.portal.visit_requests.index',
                            'clients.portal.visit_requests.store',
                            'clients.portal.visit_requests.cancel',
                        ],
                        'nav' => [
                            'route' => 'clients.portal.visit_requests.index',
                            'label' => 'طلب زيارة ميدانية',
                            'icon' => 'map-marker-alt',
                            'desc' => 'اطلب زيارةً وتابع طلباتك',
                        ],
                    ],
                    /* النقاطُ والمكافآت — كانت **بوابةً ثانيةً مستقلّة** على
                       `/client-portal` بدخولٍ برقم الهاتف وحده (أي أنّ من
                       يعرف رقم العميل يدخل حسابه). وُحِّدت هنا بطلب المستخدم
                       2026-09-19، وصارت عناوينُ تلك البوابة تحوّل إلى هذا
                       القسم. المفتاحُ مستقلّ: منشأةٌ لا تُشغّل نظام النقاط
                       أصلاً لا تعرض قسماً فارغاً لعملائها. */
                    'view_points_rewards' => [
                        'label' => 'مشاهدة نقاطه ومكافآته',
                        'hint' => 'رصيدُ نقاطه ومستواه وإنجازاتُه والمكافآت المتاحة وإحالاتُه — ومنه يحيل أصدقاءه إن كان نظام الإحالات مفعَّلاً.',
                        'routes' => ['clients.portal.points', 'clients.portal.points.refer'],
                        'nav' => [
                            'route' => 'clients.portal.points',
                            'label' => 'نقاطي ومكافآتي',
                            'icon' => 'award',
                            'desc' => 'رصيدك ومستواك ومكافآتك',
                        ],
                    ],
                    'view_shared_notes_attachments' => [
                        'label' => 'مشاهدة الملاحظات والمرفقات المشتركة',
                        'hint' => 'لا شاشة له في البوابة بعد — المفتاح محفوظٌ للتفعيل حين تُبنى.',
                        'routes' => [],
                        'nav' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * كل المفاتيح مسطّحةً: المفتاح ⇦ تعريفه (مع `group` مضافاً).
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        $flat = [];

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $definition) {
                $flat[$key] = $definition + [
                    'nav' => null,
                    'group' => $groupKey,
                    'group_label' => $group['label'],
                ];
            }
        }

        return $flat;
    }

    /**
     * اسم الراوت ⇦ المفتاح الذي يحرسه.
     *
     * @return array<string, string>
     */
    public static function routeMap(): array
    {
        $map = [];

        foreach (self::all() as $key => $definition) {
            foreach ($definition['routes'] as $route) {
                $map[$route] = $key;
            }
        }

        return $map;
    }

    /**
     * المفاتيح المفعَّلة حالياً — من الجدول، مُخزَّنةً لساعة.
     *
     * مفتاحٌ معرَّفٌ هنا ولا صفَّ له في الجدول يُعدّ **مطفأً**: صفٌّ ناقصٌ
     * يعني ميزةً لم تُفعَّل بعد لا ميزةً مفتوحةً بالافتراض — والبوابة تُفتح
     * بقرار لا بسهو.
     *
     * @return array<int, string>
     */
    public static function activeKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return ClientPermission::query()
                ->where('is_active', true)
                ->pluck('key')
                ->all();
        });
    }

    /** هل المفتاح مفعَّل؟ (المفتاح الرئيسي مطفأ ⇦ كلُّ شيءٍ مطفأ) */
    public static function enabled(string $key): bool
    {
        $active = self::activeKeys();

        if (! in_array(self::MASTER, $active, true)) {
            return false;
        }

        return in_array($key, $active, true);
    }

    /** هل البوابة مفتوحة أصلاً؟ */
    public static function portalEnabled(): bool
    {
        return in_array(self::MASTER, self::activeKeys(), true);
    }

    /**
     * أسماء الراوتات المسموح بها لحساب العميل الآن — مشتقّةٌ من المفاتيح
     * المفعَّلة وحدها، فلا قائمةَ سماحٍ مكتوبةً بيدٍ تفترق عن الشاشة.
     *
     * @return array<int, string>
     */
    public static function allowedRoutes(): array
    {
        if (! self::portalEnabled()) {
            return [];
        }

        $active = self::activeKeys();
        $routes = [];

        foreach (self::all() as $key => $definition) {
            if (in_array($key, $active, true)) {
                $routes = array_merge($routes, $definition['routes']);
            }
        }

        return array_values(array_unique($routes));
    }

    /**
     * بطاقات التنقّل المتاحة للعميل الآن — بالترتيب المعرَّف أعلاه.
     *
     * القالب لا يكتب قائمتَه: يمرّ على هذه. فمفتاحٌ يُطفأ يختفي تبويبُه
     * ويُقفل راوته معاً، ولا يبقى في الشريط رابطٌ يَعِد بصفحةٍ تردّ صاحبَها.
     *
     * @return array<int, array{key:string, route:string, label:string, icon:string, desc:string}>
     */
    public static function navItems(): array
    {
        if (! self::portalEnabled()) {
            return [];
        }

        $active = self::activeKeys();
        $items = [];

        foreach (self::all() as $key => $definition) {
            $nav = $definition['nav'] ?? null;

            if (! $nav || ! in_array($key, $active, true)) {
                continue;
            }

            $items[] = $nav + ['key' => $key];
        }

        return $items;
    }

    /**
     * الوجهة التي يُعاد إليها العميل حين يطرق باباً ليس له: أوّل راوتٍ
     * مسموحٍ له. `null` يعني لا شيءَ مفتوحٌ له إطلاقاً — يُسجَّل خروجه
     * بدل حلقة إعادة توجيهٍ إلى صفحةٍ ممنوعةٍ هي الأخرى.
     */
    public static function landingRoute(): ?string
    {
        $allowed = self::allowedRoutes();

        if ($allowed === []) {
            return null;
        }

        // «ملفّه الشخصي» هي الصفحة الرئيسية للبوابة متى كانت مسموحة
        if (in_array('clients.personal', $allowed, true)) {
            return 'clients.personal';
        }

        return $allowed[0];
    }

    /** يُستدعى بعد كل حفظٍ للمفاتيح. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
