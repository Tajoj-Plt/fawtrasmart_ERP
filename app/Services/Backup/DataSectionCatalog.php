<?php

namespace App\Services\Backup;

/**
 * أقسام بيانات النظام كما يراها المستخدم لا كما هي مخزَّنة.
 *
 * شاشة النسخ الاحتياطي كانت تعرض جداول القاعدة بأسمائها الإنجليزية وأحجامها
 * وعدد صفوفها، وتطلب من المسؤول أن يؤشّر ما يريد نسخه. وهذا خلل من وجهين:
 *
 * 1. **أمنيّ.** أسماء الجداول وأحجامها ترسم خريطة بنية النظام لمن يقرأ
 *    الشاشة — أو مصدرها. `sessions` و`personal_access_tokens`
 *    و`password_reset_tokens` كانت معروضة بأسمائها في قائمة اختيار.
 * 2. **عمليّ.** المسؤول لا يعرف أن العملاء في `clients` وبنود الفاتورة في
 *    `invoice_items`، ولا يميّز `logs` من `journal_entry_details`. فيؤشّر
 *    خطأً، أو لا يؤشّر شيئاً ويترك الإعداد على حاله.
 *
 * البديل هنا: قسمٌ واحد لكل معنى يعرفه صاحب المنشأة — «العملاء»،
 * «المبيعات والفواتير»، «المخزون والأصناف» — وخلفه قائمة الجداول التي
 * يترجَم إليها القسم في الخادم وحده.
 *
 * القواعد تُطبَّق بالترتيب وأول قسم يطابق يأخذ الجدول، فترتيب الأقسام أدناه
 * جزء من التعريف لا تجميلٌ: `employee_client_visits` يقع في «الزيارات» لأنها
 * قبل «الموظفون»، و`product_reviews` في «المتجر الإلكتروني» لأنه قبل
 * «المخزون». وما لا يطابق شيئاً يقع في «بيانات أخرى» فلا يسقط من نسخة كاملة.
 *
 * @see ExportCatalog للفهرس المقابل في شاشة تصدير البيانات — ذاك على مستوى
 *      الحقل وهذا على مستوى القسم، والفرق مقصود: التصدير يُخرج ملفّ بيانات
 *      يُقرأ، والنسخة الاحتياطية تُستَرجَع فلا معنى فيها لحقل دون حقل.
 */
class DataSectionCatalog
{
    /**
     * الأقسام بترتيب المطابقة.
     *
     * `tables` أسماء صريحة، و`prefixes` بادئات، و`suffixes` لواحق.
     * و`hidden` قسم لا يُعرَض في الاختيار: بياناته تقنية بحتة (جلسات،
     * مهامّ مؤجَّلة، رموز مؤقّتة) لا يعني للمستخدم أن يضمّها أو يستبعدها،
     * وعرضها هو نفسه ما نتجنّبه هنا. تبقى داخل النسخة الكاملة كما هي.
     *
     * @return array<string, array{label:string,icon:string,hint:string,tables?:array<int,string>,prefixes?:array<int,string>,suffixes?:array<int,string>,hidden?:bool}>
     */
    public static function sections(): array
    {
        return [
            'clients' => [
                'label' => 'العملاء',
                'icon' => 'users',
                'hint' => 'ملفّات العملاء وعناوينهم وتصنيفاتهم وحدود ائتمانهم',
                'prefixes' => ['client_'],
                'tables' => [
                    'clients', 'categories_clients', 'contacts', 'type_client', 'hidden_clients',
                    'customer_targets', 'credit_limit', 'credit_notifications', 'doubtful_debts',
                    'note_classifications', 'collections_targets', 'collections_target_achievements',
                    'collections_target_commission_tiers', 'collections_target_settlements',
                ],
            ],

            'sales' => [
                'label' => 'المبيعات والفواتير',
                'icon' => 'file-text',
                'hint' => 'الفواتير وبنودها وعروض الأسعار والمستهدفات والعمولات',
                'prefixes' => ['invoice_', 'quote', 'sales_', 'periodic_invoice'],
                'tables' => [
                    'invoices', 'invoice_transfers', 'taxs_invoice', 'quotes',
                    'targets', 'target_settings', 'employee_targets',
                ],
            ],

            'pos' => [
                'label' => 'نقاط البيع',
                'icon' => 'shopping-bag',
                'hint' => 'جلسات الكاشير وحركاته وإقفالاته اليومية',
                'prefixes' => ['pos_', 'daily_closing'],
                'tables' => ['cashier_devices', 'shifts_pos'],
            ],

            'store' => [
                'label' => 'المتجر الإلكتروني',
                'icon' => 'globe',
                'hint' => 'الطلبات الإلكترونية والسلال والتقييمات وخيارات الشحن',
                'prefixes' => ['online_', 'shipping_', 'cart', 'order_'],
                'tables' => [
                    'store_banners', 'store_pages', 'store_coupons', 'store_coupon_usages',
                    'wishlists', 'recently_viewed_products', 'product_reviews',
                    'product_review_helpfuls', 'product_questions',
                    'order_sources', 'settings_order_sources', 'settings _ordersources',
                ],
            ],

            'purchases' => [
                'label' => 'المشتريات والمورّدون',
                'icon' => 'truck',
                'hint' => 'فواتير الشراء وأوامره وعروض المورّدين وبياناتهم',
                'prefixes' => ['purchase_'],
                'tables' => ['suppliers'],
            ],

            'inventory' => [
                'label' => 'المخزون والأصناف',
                'icon' => 'package',
                'hint' => 'الأصناف وأسعارها ووحداتها والمستودعات وحركات الجرد',
                'prefixes' => ['product_', 'inventory_', 'warehouse_permits', 'price_list', 'unit_'],
                'tables' => [
                    'products', 'brands', 'categories', 'category_unit', 'units', 'sub_units',
                    'template_units', 'store_houses', 'default_warehouses', 'compiled_products',
                    'seasonal_prices', 'pricing_rules', 'offers', 'exchange_reasons',
                ],
            ],

            'manufacturing' => [
                'label' => 'التصنيع',
                'icon' => 'settings',
                'hint' => 'أوامر التصنيع ومراحله وخاماته ومحطّات العمل',
                'prefixes' => ['manufactur', 'production_', 'work_stations', 'indirect_cost'],
                'tables' => ['machines', 'manual_manufacturing_order_statuses'],
            ],

            'accounting' => [
                'label' => 'المحاسبة والخزائن',
                'icon' => 'book',
                'hint' => 'شجرة الحسابات والقيود والسندات والمصروفات والشيكات',
                'prefixes' => [
                    'journal_', 'treasury', 'receipt', 'payment_voucher', 'installment',
                    'asset_dep', 'expenses', 'cheque',
                ],
                'tables' => [
                    'accounts', 'accounts_backup', 'chart_of_accounts', 'cost_centers',
                    'transactions', 'bank_accounts', 'treasuries', 'payable_cheques',
                    'received_cheques', 'receipts', 'payment_vouchers', 'payments_process', 'income',
                ],
            ],

            'visits' => [
                'label' => 'الزيارات والمتابعة الميدانية',
                'icon' => 'map-pin',
                'hint' => 'زيارات المندوبين ومساراتهم ومواقع العملاء',
                'tables' => [
                    'visits', 'employee_client_visits', 'employee_tracking', 'locations', 'directions',
                ],
            ],

            'hr' => [
                'label' => 'الموظفون والحضور والرواتب',
                'icon' => 'user-check',
                'hint' => 'ملفّات الموظفين والحضور والإجازات ومسيّرات الرواتب',
                'prefixes' => [
                    'employee', 'attendance_', 'leave_', 'payroll', 'salary', 'holiday',
                    'holy_day', 'shift', 'custom_shifts', 'deduction', 'department',
                ],
                'tables' => [
                    'employees', 'departments', 'jop_titles', 'job_roles', 'types_jobs',
                    'functional_levels', 'treasury_employees',
                ],
            ],

            'loyalty' => [
                'label' => 'النقاط والولاء',
                'icon' => 'award',
                'hint' => 'أرصدة النقاط ومستوياتها والهدايا والمكافآت',
                'prefixes' => [
                    'points_', 'challenge', 'achievement', 'reward',
                    'gift_offer', 'referral', 'tier_upgrades', 'excluded_client',
                ],
                'tables' => ['user_achievements'],
            ],

            'subscriptions' => [
                'label' => 'الاشتراكات والحجوزات والعقود',
                'icon' => 'calendar',
                'hint' => 'الاشتراكات والحجوزات والمواعيد والعقود ووكلاء التأمين',
                'prefixes' => ['subscription', 'system_subscription', 'appointment', 'contract'],
                'tables' => ['reservations', 'bookings'],
            ],

            /* قبل «إعدادات النظام» و«السجلّات» عمداً: `re_settings` ينتهي
               بـ`_settings` فيقع هناك لو تأخّر هذا القسم، ونسخةٌ لقسم العقارات
               بلا إعداداته تُسترجَع بحساباتٍ غير مربوطة. */
            'realestate' => [
                'label' => 'إدارة العقارات',
                'icon' => 'home',
                'hint' => 'الملّاك والمباني والوحدات وعقود الإيجار ودفعاتها والصيانة والعروض العقارية',
                'prefixes' => ['re_'],
            ],

            'tasks' => [
                'label' => 'المهامّ والمشاريع',
                'icon' => 'check-square',
                'hint' => 'المهامّ ومشاريعها ودوراتها وتعليقاتها',
                'prefixes' => ['task', 'project'],
                'tables' => ['sprints', 'workspaces', 'statuses', 'procedures', 'comments'],
            ],

            'users' => [
                'label' => 'المستخدمون والصلاحيات',
                'icon' => 'shield',
                'hint' => 'حسابات المستخدمين وأدوارهم وصلاحياتهم',
                'prefixes' => ['role', 'model_has_', 'permission'],
                'tables' => ['users', 'roles', 'permissions'],
            ],

            'settings' => [
                'label' => 'إعدادات النظام والفروع',
                'icon' => 'sliders',
                'hint' => 'الفروع والعملات والضرائب والقوالب وضبط النظام كلّه',
                'suffixes' => ['_settings', '_sittings', '_setthings'],
                'prefixes' => ['branch', 'system_', 'template', 'document_template', 'api_'],
                'tables' => [
                    'cities', 'countries', 'neighborhoods', 'taxes', 'payment_methods',
                    'email_templates', 'sms_templates', 'signatures', 'color_sittings',
                    'business_data', 'general_settings', 'serial_settings', 'workspaces',
                ],
            ],

            'logs' => [
                'label' => 'السجلّات والإشعارات',
                'icon' => 'activity',
                'hint' => 'سجلّات الأحداث والأخطاء والرسائل والإشعارات — تكبر بسرعة، واستبعادها يصغّر النسخة كثيراً',
                'suffixes' => ['_logs', '_log'],
                'prefixes' => ['notification', 'cron_job', 'telescope_', 'backup', 'email_', 'sms_'],
                'tables' => ['logs', 'notifications'],
            ],

            'internal' => [
                'label' => 'بيانات تشغيلية داخلية',
                'icon' => 'cpu',
                'hint' => 'بيانات مؤقّتة يديرها النظام لنفسه',
                'hidden' => true,
                'prefixes' => ['cache', 'job', 'failed_job', 'personal_access', 'password_reset'],
                'tables' => ['sessions', 'migrations', 'trusted_devices'],
            ],

            'other' => [
                'label' => 'بيانات أخرى',
                'icon' => 'more-horizontal',
                'hint' => 'ما لا يندرج تحت الأقسام أعلاه',
                'tables' => [], // المتبقّي كلّه — يُملأ في `map()`
            ],
        ];
    }

    /** القسم الذي يجمع ما لم يطابق قسماً آخر. */
    public const FALLBACK = 'other';

    /**
     * مفتاح معتَّم يمثّل القسم في الاستمارة.
     *
     * مفتاح القسم اللاتيني (`clients`, `hr`) ليس اسم جدول، لكنه يظلّ إشارة
     * إلى بنية النظام تظهر في مصدر الصفحة. الاشتقاق من مفتاح التطبيق يجعله
     * ثابتاً عبر الطلبات ولا يدلّ على شيء.
     */
    public static function keyFor(string $section): string
    {
        return substr(hash_hmac('sha256', 'backup-section:'.$section, (string) config('app.key')), 0, 12);
    }

    /** القسم المقابل لمفتاح معتَّم، أو null إن لم يطابق شيئاً. */
    public static function sectionForKey(?string $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (array_keys(static::sections()) as $section) {
            // مقارنة ثابتة الزمن: المفتاح مشتقّ من سرّ التطبيق
            if (hash_equals(static::keyFor($section), $key)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * توزيع الجداول الموجودة فعلاً على الأقسام.
     *
     * الجداول تأتي من `BackupTableRegistry` لا من الفهرس: قسمٌ يذكر جدولاً
     * حُذف من القاعدة يجب ألّا يُرسله إلى أمر النسخ فيُفشله كلّه.
     *
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        $sections = static::sections();
        $map = array_fill_keys(array_keys($sections), []);

        foreach (app(BackupTableRegistry::class)->names() as $table) {
            $map[static::sectionOf($table, $sections)][] = $table;
        }

        return $map;
    }

    /**
     * القسم الذي يقع فيه جدول — أول تطابق في ترتيب الأقسام.
     *
     * @param  array<string, array<string, mixed>>|null  $sections
     */
    public static function sectionOf(string $table, ?array $sections = null): string
    {
        $sections ??= static::sections();

        foreach ($sections as $key => $meta) {
            if (in_array($table, $meta['tables'] ?? [], true)) {
                return $key;
            }

            foreach ($meta['prefixes'] ?? [] as $prefix) {
                if (str_starts_with($table, $prefix)) {
                    return $key;
                }
            }

            foreach ($meta['suffixes'] ?? [] as $suffix) {
                if (str_ends_with($table, $suffix)) {
                    return $key;
                }
            }
        }

        return self::FALLBACK;
    }

    /**
     * الأقسام المعروضة في شاشة الاختيار، مع حجم كل قسم وعدد سجلّاته.
     *
     * القسم الفارغ لا يُعرَض: منشأة لا تستعمل التصنيع لا شأن لها بقسمه،
     * وعرضه يضيف ضجيجاً إلى قائمة الاختيار.
     *
     * @return array<int, array{key:string,label:string,icon:string,hint:string,rows:int,bytes:int}>
     */
    public static function forPicker(): array
    {
        $sections = static::sections();
        $map = static::map();
        $stats = collect(app(BackupTableRegistry::class)->all())->keyBy('name');

        $out = [];

        foreach ($map as $section => $tables) {
            if ($tables === [] || ($sections[$section]['hidden'] ?? false)) {
                continue;
            }

            $out[] = [
                'key' => static::keyFor($section),
                'label' => static::labelFor($section),
                'icon' => $sections[$section]['icon'] ?? 'database',
                'hint' => static::hintFor($section),
                'rows' => (int) collect($tables)->sum(fn ($table) => $stats[$table]['rows'] ?? 0),
                'bytes' => (int) collect($tables)->sum(fn ($table) => $stats[$table]['bytes'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * الجداول التي تمثّلها مفاتيح أقسام مختارة.
     *
     * هذه هي نقطة التحويل الوحيدة من لغة المستخدم إلى لغة القاعدة، وما بعدها
     * أسماءٌ خرجت من قائمة مغلقة لا من الطلب.
     *
     * @param  array<int, mixed>|null  $keys
     * @return array<int, string>
     */
    public static function tablesForKeys(?array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $map = static::map();
        $tables = [];

        foreach ($keys as $key) {
            $section = static::sectionForKey(is_string($key) ? trim($key) : null);

            if ($section !== null) {
                $tables = array_merge($tables, $map[$section] ?? []);
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * مفاتيح الأقسام المؤشَّرة ابتداءً، مستنتَجةً من أسماء جداول محفوظة.
     *
     * الإعدادات تُخزَّن أسماءَ جداول (فما بعدها يفهمها محرّك النسخ وحده)،
     * والشاشة تحتاج أقساماً. القسم يُعدّ مؤشَّراً إن كان كل ما فيه محفوظاً —
     * تطابقٌ جزئي يعني إعداداً قديماً أُنشئ بالمنتقي السابق، وعرضه «مؤشَّراً»
     * يكذب على المستخدم عند أوّل حفظ.
     *
     * @param  array<int, string>|null  $tables
     * @return array<int, string>
     */
    public static function keysForTables(?array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $keys = [];

        foreach (static::map() as $section => $sectionTables) {
            if ($sectionTables !== [] && array_diff($sectionTables, $tables) === []) {
                $keys[] = static::keyFor($section);
            }
        }

        return $keys;
    }

    /**
     * الجداول المحفوظة التي لا يغطّيها أي قسم مؤشَّر.
     *
     * تُستعمل لتنبيه المستخدم إلى إعداد قديم: «محفوظ لديك اختيار أدقّ من
     * الأقسام» أفضل من محوه صامتاً عند أوّل حفظ.
     *
     * @param  array<int, string>|null  $tables
     */
    public static function uncoveredCount(?array $tables): int
    {
        if (empty($tables)) {
            return 0;
        }

        $covered = static::tablesForKeys(static::keysForTables($tables));

        return count(array_diff($tables, $covered));
    }

    /**
     * وصف مجموعة جداول بأسماء أقسامها — لعرض نطاق نسخة سابقة.
     *
     * @param  array<int, string>|null  $tables
     * @return array<int, string>
     */
    public static function labelsForTables(?array $tables): array
    {
        if (empty($tables)) {
            return [];
        }

        $sections = [];

        foreach ($tables as $table) {
            $sections[static::sectionOf($table)] = true;
        }

        return array_values(array_map(
            fn ($section) => static::labelFor($section),
            array_keys($sections)
        ));
    }

    /**
     * النصّ المترجَم، أو العربي المكتوب هنا إن نقصت الترجمة.
     *
     * الفهرس أعلاه يبقى مصدر البنية، وقيمه العربية احتياطٌ لا واجهة.
     */
    protected static function tr(string $key, string $fallback): string
    {
        $full = 'settings.backup.sections.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    public static function labelFor(string $section): string
    {
        return static::tr($section.'.label', static::sections()[$section]['label'] ?? $section);
    }

    public static function hintFor(string $section): string
    {
        return static::tr($section.'.hint', static::sections()[$section]['hint'] ?? '');
    }
}
