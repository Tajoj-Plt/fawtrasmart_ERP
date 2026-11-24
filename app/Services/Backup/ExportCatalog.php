<?php

namespace App\Services\Backup;

/**
 * فهرس ما يجوز تصديره، بأسماء عربية.
 *
 * شاشة التصدير كانت تعرض جداول القاعدة الثلاثمئة والسبعة والعشرين بأسمائها
 * الإنجليزية وأعمدتها كما هي. وهذا خلل من وجهين:
 *
 * 1. **أمنيّ.** بنية قاعدة البيانات — أسماء الجداول والأعمدة والعلاقات —
 *    معلومة لا يحتاجها المستخدم وتفيد من يريد استغلال ثغرة. والأسوأ أن
 *    القائمة كانت تشمل `sessions` و`password_reset_tokens`
 *    و`personal_access_tokens` و`permissions`: جداول مفاتيح وجلسات لا يجوز
 *    تصديرها أصلاً مهما كانت صلاحية الطالب.
 * 2. **عمليّ.** المستخدم لا يعرف أن العملاء في `clients` وأن بنود الفاتورة
 *    في `invoice_items`، ولا يميّز `logs` من `journal_entry_details`.
 *
 * الحلّ قائمة بيضاء صريحة لا قائمة سوداء: ما ليس هنا لا يُصدَّر. وكل عمود
 * يجب أن يحمل اسماً عربياً وإلا لم يُعرَض ولم يُصدَّر — فلا يتسرّب اسم تقني
 * إلى الشاشة ولا إلى ترويسة الملفّ.
 *
 * إضافة كيان جديد = سطر هنا. ولا يُضاف جدول لأنه موجود، بل لأن للمستخدم
 * حاجة معلومة إلى تصديره.
 */
class ExportCatalog
{
    /**
     * أسماء الأعمدة المتكرّرة في أغلب الجداول.
     *
     * تُطبَّق على كل كيان تلقائياً، فتبقى قوائم الكيانات أدناه مقتصرة على
     * ما يخصّها وحدها.
     *
     * @var array<string, string>
     */
    public const COMMON = [
        'id' => 'الرقم',
        'code' => 'الرمز',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'notes' => 'ملاحظات',
        'status' => 'الحالة',
        'type' => 'النوع',
        'date' => 'التاريخ',
        'amount' => 'المبلغ',
        'total' => 'الإجمالي',
        'quantity' => 'الكمية',
        'discount' => 'الخصم',
        'city' => 'المدينة',
        'region' => 'المنطقة',
        'country' => 'الدولة',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'mobile' => 'الجوّال',
        'gender' => 'الجنس',
        'currency' => 'العملة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ آخر تعديل',
        'postal_code' => 'الرمز البريدي',
        'tax_number' => 'الرقم الضريبي',
        'credit_limit' => 'سقف الائتمان',
        'barcode' => 'الباركود',
        'weight' => 'الوزن',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'birth_date' => 'تاريخ الميلاد',
        'date_of_birth' => 'تاريخ الميلاد',
    ];

    /**
     * الكيانات القابلة للتصدير.
     *
     * `columns` أسماء عربية لأعمدة يخصّ هذا الكيان وحده. وما ورد في
     * `COMMON` يُضاف تلقائياً. أي عمود لا يظهر في أيّهما لا يُصدَّر —
     * وهذا مقصود: الحقول الداخلية (الرموز المؤقّتة، المعرّفات الأجنبية،
     * أعلام النظام) لا شأن للمستخدم بها.
     *
     * @return array<string, array{label:string,group:string,hint?:string,columns?:array<string,string>}>
     */
    /**
     * الكيانات المنسَّقة يدوياً مع كل جداول القاعدة.
     *
     * كان الفهرس قائمةً بيضاء من واحد وثلاثين كياناً، والقاعدة ثلاثمئة
     * وثلاثون جدولاً — فمن أراد تصدير «طلبات الإجازة» أو «قيود اليومية»
     * لم يجد لهما ذكراً. صار الفهرس يشمل الجداول كلّها: ما كُتب هنا بيدٍ
     * يبقى كما هو لأنه أدقّ، وما عداه يُترجَم في `ArabicSchemaTranslator`.
     *
     * النتيجة محفوظة في الذاكرة: تُستدعى هذه الدالّة عشرات المرّات في
     * الطلب الواحد — من `tableForKey` لكل مفتاح — وقراءة بنية القاعدة في
     * كل مرّة تُبطئ الشاشة بلا سبب.
     *
     * @return array<string, array{label:string,group:string,hint?:string,columns?:array<string,string>}>
     */
    protected static ?array $merged = null;

    public static function entities(): array
    {
        return static::$merged ??= array_merge(static::discovered(), static::curated());
    }

    /** يُنسى المحفوظ — للاختبارات وبعد تغيّر بنية القاعدة */
    public static function flush(): void
    {
        static::$merged = null;
    }

    /**
     * كل جدول في القاعدة لم يرد في الفهرس اليدوي.
     *
     * تعذّر قراءة البنية (أثناء الترحيل مثلاً) يُعيد قائمة فارغة بدل أن
     * يرمي: الفهرس يُقرأ من مواضع لا قاعدة فيها بعد.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function discovered(): array
    {
        try {
            $tables = app(BackupTableRegistry::class)->names();
        } catch (\Throwable $e) {
            return [];
        }

        $curated = static::curated();
        $out = [];

        foreach ($tables as $table) {
            if (isset($curated[$table])) {
                continue;
            }

            /* جداولُ الجلسات والمفاتيح والرموز لا تدخل الفهرسَ أصلاً.

               كانت تُدرَج بوسم «حسّاس» — شارةٌ على الشاشة لا أكثر — فتُختار
               وتُصدَّر كغيرها، مع أن ترويسةَ هذا الملفّ تقول إنها لا يجوز
               تصديرُها مهما كانت صلاحيةُ الطالب. ما ليس في الفهرس لا يُصدَّر. */
            if (ArabicSchemaTranslator::isSensitive($table)) {
                continue;
            }

            $out[$table] = [
                'label' => ArabicSchemaTranslator::table($table),
                'group' => ArabicSchemaTranslator::group($table),
                'hint' => null,
                'discovered' => true,
                'sensitive' => ArabicSchemaTranslator::isSensitive($table),
            ];
        }

        return $out;
    }

    /**
     * الكيانات المكتوبة بيدٍ — تسبق المكتشَفة لأن أسماءها وتلميحاتها أدقّ.
     */
    protected static function curated(): array
    {
        return [
            // ============ العملاء ============
            'clients' => [
                'label' => 'العملاء',
                'group' => 'العملاء',
                'hint' => 'بيانات العملاء وعناوينهم وحدود ائتمانهم',
                'columns' => [
                    'trade_name' => 'الاسم التجاري',
                    'first_name' => 'الاسم الأول',
                    'last_name' => 'اسم العائلة',
                    'client_type' => 'نوع العميل',
                    'street1' => 'العنوان',
                    'street2' => 'العنوان التفصيلي',
                    'commercial_registration' => 'السجلّ التجاري',
                    'credit_period' => 'مدّة الائتمان',
                    'last_login_at' => 'آخر دخول',
                ],
            ],
            'client_addresses' => [
                'label' => 'عناوين العملاء',
                'group' => 'العملاء',
            ],
            'customer_targets' => [
                'label' => 'تصنيفات العملاء',
                'group' => 'العملاء',
            ],

            // ============ المبيعات ============
            'invoices' => [
                'label' => 'فواتير المبيعات',
                'group' => 'المبيعات',
                'hint' => 'رؤوس الفواتير: التواريخ والمبالغ وحالة السداد',
                'columns' => [
                    'invoice_date' => 'تاريخ الفاتورة',
                    'issue_date' => 'تاريخ الإصدار',
                    'payment_terms' => 'شروط السداد',
                    'payment_status' => 'حالة السداد',
                    'payment_method' => 'طريقة الدفع',
                    'due_value' => 'المستحقّ',
                    'grand_total' => 'الإجمالي النهائي',
                    'subtotal' => 'الإجمالي قبل الضريبة',
                    'advance_payment' => 'الدفعة المقدَّمة',
                    'remaining_amount' => 'المتبقّي',
                    'is_paid' => 'مسدَّدة؟',
                    'reference_number' => 'الرقم المرجعي',
                    'discount_amount' => 'قيمة الخصم',
                    'discount_type' => 'نوع الخصم',
                    'shipping_cost' => 'تكلفة الشحن',
                    'tax_total' => 'إجمالي الضريبة',
                    'tax_type' => 'نوع الضريبة',
                ],
            ],
            'invoice_items' => [
                'label' => 'بنود الفواتير',
                'group' => 'المبيعات',
                'hint' => 'أصناف كل فاتورة وكمّياتها وأسعارها',
                'columns' => [
                    'item' => 'الصنف',
                    'unit_price' => 'سعر الوحدة',
                    'unit_name' => 'الوحدة',
                    'quantity_in_base_unit' => 'الكمية بالوحدة الأساسية',
                    'discount_type' => 'نوع الخصم',
                    'tax_1' => 'الضريبة الأولى',
                    'tax_2' => 'الضريبة الثانية',
                ],
            ],
            'quotes' => [
                'label' => 'عروض الأسعار',
                'group' => 'المبيعات',
            ],
            'quote_items' => [
                'label' => 'بنود عروض الأسعار',
                'group' => 'المبيعات',
            ],
            // ============ المقبوضات والمدفوعات ============
            'receipts' => [
                'label' => 'سندات القبض',
                'group' => 'المقبوضات والمدفوعات',
                'columns' => [
                    'seller' => 'المستلِم',
                    'discount_amount' => 'قيمة الخصم',
                    'is_recurring' => 'متكرّر؟',
                    'recurring_frequency' => 'تكرار السند',
                    'tax1_amount' => 'قيمة الضريبة الأولى',
                    'tax2_amount' => 'قيمة الضريبة الثانية',
                    'cancelled_at' => 'تاريخ الإلغاء',
                ],
            ],
            'expenses' => [
                'label' => 'المصروفات',
                'group' => 'المقبوضات والمدفوعات',
                'columns' => [
                    'seller' => 'المورّد/المستفيد',
                    'total_amount' => 'الإجمالي',
                    'is_recurring' => 'متكرّر؟',
                    'recurring_frequency' => 'تكرار المصروف',
                    'tax1_amount' => 'قيمة الضريبة الأولى',
                    'tax2_amount' => 'قيمة الضريبة الثانية',
                ],
            ],
            'payment_vouchers' => [
                'label' => 'سندات الصرف',
                'group' => 'المقبوضات والمدفوعات',
            ],
            'payments_process' => [
                'label' => 'حركات السداد',
                'group' => 'المقبوضات والمدفوعات',
            ],

            // ============ المخزون ============
            'products' => [
                'label' => 'المنتجات',
                'group' => 'المخزون',
                'hint' => 'الأصناف وأسعارها وحدود مخزونها',
                'columns' => [
                    'serial_number' => 'الرقم التسلسلي',
                    'purchase_price' => 'سعر الشراء',
                    'sale_price' => 'سعر البيع',
                    'min_sale_price' => 'أدنى سعر بيع',
                    'profit_margin' => 'هامش الربح',
                    'low_stock_thershold' => 'حدّ إعادة الطلب',
                    'expiry_date' => 'تاريخ الانتهاء',
                    'dimensions' => 'الأبعاد',
                    'tags' => 'الوسوم',
                    'material_type' => 'نوع المادة',
                    'discount_type' => 'نوع الخصم',
                ],
            ],
            'brands' => ['label' => 'العلامات التجارية', 'group' => 'المخزون'],
            'product_groups' => ['label' => 'مجموعات المنتجات', 'group' => 'المخزون'],
            'store_houses' => ['label' => 'المستودعات', 'group' => 'المخزون'],
            'warehouse_permits' => ['label' => 'أذون المستودعات', 'group' => 'المخزون'],
            'inventory_adjustments' => ['label' => 'تسويات المخزون', 'group' => 'المخزون'],

            // ============ الموظفون ============
            'employees' => [
                'label' => 'الموظفون',
                'group' => 'الموظفون',
                'hint' => 'بيانات الموظفين ووظائفهم وتواريخ تعيينهم',
                'columns' => [
                    'first_name' => 'الاسم الأول',
                    'middle_name' => 'الاسم الأوسط',
                    'nickname' => 'الاسم المختصر',
                    'employee_type' => 'نوع التوظيف',
                    'nationality_status' => 'الجنسية',
                    'mobile_number' => 'الجوّال',
                    'phone_number' => 'الهاتف',
                    'personal_email' => 'البريد الشخصي',
                    'current_address_1' => 'العنوان',
                    'hire_date' => 'تاريخ التعيين',
                    'attendance_rate' => 'نسبة الحضور',
                ],
            ],
            'departments' => ['label' => 'الأقسام', 'group' => 'الموظفون'],
            'attendance_records' => ['label' => 'سجلّ الحضور', 'group' => 'الموظفون'],
            'attendance_sheets' => ['label' => 'كشوف الحضور', 'group' => 'الموظفون'],

            // ============ الزيارات ============
            'visits' => [
                'label' => 'زيارات العملاء',
                'group' => 'الزيارات',
                'hint' => 'زيارات المندوبين ومواعيدها وحالتها',
                'columns' => [
                    'visit_date' => 'تاريخ الزيارة',
                    'arrival_time' => 'وقت الوصول',
                    'departure_time' => 'وقت المغادرة',
                    'distance' => 'المسافة',
                    'recording_method' => 'طريقة التسجيل',
                    'is_approved' => 'معتمَدة؟',
                ],
            ],

            // ============ المحاسبة ============
            'accounts' => [
                'label' => 'شجرة الحسابات',
                'group' => 'المحاسبة',
            ],
            'journal_entries' => [
                'label' => 'القيود المحاسبية',
                'group' => 'المحاسبة',
            ],
            'treasuries' => ['label' => 'الخزائن', 'group' => 'المحاسبة'],
            'cost_centers' => ['label' => 'مراكز التكلفة', 'group' => 'المحاسبة'],

            // ============ النقاط والأرصدة ============
            'points_transactions' => ['label' => 'حركات النقاط', 'group' => 'النقاط والأرصدة'],
            'points_tiers' => ['label' => 'مستويات النقاط', 'group' => 'النقاط والأرصدة'],
            'balance_charges' => ['label' => 'شحن الأرصدة', 'group' => 'النقاط والأرصدة'],
            'balance_types' => ['label' => 'أنواع الأرصدة', 'group' => 'النقاط والأرصدة'],
        ];
    }

    /** هل هذا الجدول مسموح تصديره أصلاً؟ */
    public static function has(string $table): bool
    {
        return array_key_exists($table, static::entities());
    }

    /**
     * مفتاح معتَّم يمثّل الكيان في الاستمارة والروابط.
     *
     * الأسماء العربية تحلّ مشكلة العرض، لكن اسم الجدول كان يبقى في مصدر
     * الصفحة داخل `value` كل مربّع اختيار — فيكفي «عرض المصدر» لقراءة
     * أسماء جداول النظام. هذا المفتاح يقطع ذلك: مشتقّ من اسم الجدول
     * ومفتاح التطبيق، ثابت عبر الطلبات، ولا يُستدَلّ منه على شيء.
     *
     * اسم الجدول الحقيقي يبقى في سجلّات الخادم وحدها.
     */
    public static function keyFor(string $table): string
    {
        return substr(hash_hmac('sha256', 'export-entity:'.$table, (string) config('app.key')), 0, 12);
    }

    /** الجدول المقابل لمفتاح، أو null إن لم يطابق شيئاً. */
    public static function tableForKey(?string $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (static::tables() as $table) {
            // مقارنة ثابتة الزمن: المفتاح مشتقّ من سرّ التطبيق
            if (hash_equals(static::keyFor($table), $key)) {
                return $table;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public static function tables(): array
    {
        return array_keys(static::entities());
    }

    /**
     * النصّ المترجَم، أو النصّ العربي المكتوب هنا إن نقصت الترجمة.
     *
     * الفهرس أعلاه يبقى مصدر البنية — الجداول والأعمدة المسموح بها —
     * وقيمه العربية احتياطٌ لا واجهة: ما يُعرَض يمرّ من هنا.
     */
    protected static function tr(string $key, ?string $fallback): ?string
    {
        $full = 'settings.export.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    public static function labelFor(string $table): string
    {
        return static::tr('entities.'.$table.'.label', static::entities()[$table]['label'] ?? $table) ?? $table;
    }

    /**
     * مجموعة الجدول.
     *
     * المرجع واحد للجميع: `ArabicSchemaTranslator`. كان الفهرس اليدوي
     * يحمل أسماء مجموعاته بنفسه وتُترجَم من ملفّ اللغة، فظهرت الشاشة
     * بمجموعتين لكل قسم — «العملاء» للمنسَّق يدوياً و«العملاء» للمكتشَف —
     * ولا تندمجان لاختلاف حرف. توحيد المفردات يُذهب الازدواج.
     */
    public static function groupFor(string $table): string
    {
        if (ArabicSchemaTranslator::isKnown($table)) {
            $key = ArabicSchemaTranslator::groupKey($table);

            return static::tr('groups.'.$key, ArabicSchemaTranslator::GROUPS[$key] ?? $key)
                ?? ArabicSchemaTranslator::GROUPS[$key];
        }

        return static::tr('groups.other', ArabicSchemaTranslator::GROUPS['other'])
            ?? ArabicSchemaTranslator::GROUPS['other'];
    }

    public static function hintFor(string $table): ?string
    {
        return static::tr('entities.'.$table.'.hint', static::entities()[$table]['hint'] ?? null);
    }

    /**
     * ربط اسم المجموعة العربي بمفتاحه اللاتيني في ملفّ الترجمة.
     *
     * @var array<string, string>
     */
    protected const GROUP_KEYS = [
        'clients' => 'العملاء',
        'sales' => 'المبيعات',
        'payments' => 'المقبوضات والمدفوعات',
        'stock' => 'المخزون',
        'employees' => 'الموظفون',
        'visits' => 'الزيارات',
        'accounting' => 'المحاسبة',
        'points' => 'النقاط والأرصدة',
    ];

    /**
     * خريطة أعمدة هذا الكيان: الاسم التقني ← الاسم العربي.
     *
     * ما ليس في الخريطة لا يُصدَّر. لا يُعاد الاسم التقني بديلاً عن اسم
     * مفقود — لأن ذلك يُفشل الغرض كلّه.
     *
     * @return array<string, string>
     */
    public static function columnsFor(string $table): array
    {
        if (! static::has($table)) {
            return [];
        }

        $entity = static::entities()[$table];

        // الجدول المكتشَف لا خريطة أعمدة له، فتُشتقّ أسماؤها من بنيته.
        // ولا يُسقَط عمودٌ عجزت ترجمته — تصديرٌ ناقص بصمت أسوأ من ترويسة
        // فيها اسم إنجليزي واحد
        if ($entity['discovered'] ?? false) {
            $map = [];

            foreach (app(BackupTableRegistry::class)->columns($table) as $column) {
                $map[$column['name']] = ArabicSchemaTranslator::column($column['name']);
            }

            return static::withoutSecretColumns($map);
        }

        // الخاصّ يسبق العامّ: كيان قد يسمّي `date` «تاريخ الزيارة»
        $columns = array_merge(self::COMMON, $entity['columns'] ?? []);

        $commonTr = __('settings.export.common');
        $ownTr = __('settings.export.entities.'.$table.'.columns');

        foreach ($columns as $column => $label) {
            $columns[$column] = (is_array($ownTr) ? ($ownTr[$column] ?? null) : null)
                ?? (is_array($commonTr) ? ($commonTr[$column] ?? null) : null)
                ?? $label;
        }

        return static::withoutSecretColumns($columns);
    }

    /**
     * أعمدةُ الأسرار لا تُصدَّر من أيّ جدول — كلمةُ مرورٍ أو رمزُ دخولٍ أو
     * مفتاحُ ربط.
     *
     * الجدولُ قد يكون مشروعَ التصدير (المستخدمون، إعدادات البريد) وفيه عمودٌ
     * واحدٌ لا يجوز أن يغادر الخادم: `users.password` و`remember_token`،
     * و`smtp_settings.password`، و`sms_settings.api_key`، ورمزُ بوت تيليجرام،
     * وكلمةُ سرّ أرشيف النسخ، ومفتاحُ تجاوز وضع الصيانة. فالحجبُ بالعمود لا
     * بالجدول: يبقى الجدولُ متاحاً وتسقط منه أسرارُه.
     *
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    protected static function withoutSecretColumns(array $columns): array
    {
        return array_filter(
            $columns,
            fn (string $name) => preg_match('/pass(word|wd)|secret|token|api_?key|private_?key|remember|payload|otp/i', $name) !== 1,
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function columnLabel(string $table, string $column): ?string
    {
        return static::columnsFor($table)[$column] ?? null;
    }

    /**
     * الكيانات المتاحة فعلاً، مجموعةً حسب القسم.
     *
     * كيان في الفهرس حُذف جدوله من القاعدة يجب ألّا يُعرَض ثم يفشل عند
     * التصدير. والمجموعة التي خلت من كيان متاح تسقط كلّها.
     *
     * @return array<string, array<int, array{table:string,key:string,label:string,hint:?string}>>
     */
    public static function groupedAvailable(): array
    {
        $existing = app(BackupTableRegistry::class)->names();
        $groups = [];

        foreach (static::grouped() as $group => $entities) {
            $available = array_values(array_filter(
                $entities,
                fn ($entity) => in_array($entity['table'], $existing, true)
            ));

            if ($available !== []) {
                $groups[$group] = $available;
            }
        }

        return $groups;
    }

    /**
     * الكيانات مجموعةً حسب القسم — هكذا تُبنى قائمة الاختيار في الشاشة.
     *
     * @return array<string, array<int, array{table:string,label:string,hint:?string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];
        // الاسم المعروض ← مفتاحه، كي يقع الترتيب على المفتاح لا على النصّ
        $groupKeys = [];

        foreach (static::entities() as $table => $meta) {
            $label = static::groupFor($table);
            $groupKeys[$label] = ArabicSchemaTranslator::isKnown($table)
                ? ArabicSchemaTranslator::groupKey($table)
                : 'other';

            $grouped[$label][] = [
                'table' => $table,
                'key' => static::keyFor($table),
                'label' => static::labelFor($table),
                'hint' => static::hintFor($table),
                // الشاشة تستثني الحسّاس من «تحديد الكل» وتُعلّمه
                'sensitive' => $meta['sensitive'] ?? false,
            ];
        }

        // ترتيب المجموعات مقصود: العملاء والمبيعات أوّلاً وسجلات النظام
        // آخراً. والترتيب على المفتاح اللاتيني لا على الاسم المعروض —
        // الاسم يأتي من ملفّ اللغة فيتغيّر بتغيّرها، والترتيب يجب ألّا يتغيّر
        $order = array_flip(array_keys(ArabicSchemaTranslator::GROUPS));

        uksort($grouped, function ($a, $b) use ($order, $groupKeys) {
            $ka = $groupKeys[$a] ?? 'other';
            $kb = $groupKeys[$b] ?? 'other';

            return ($order[$ka] ?? PHP_INT_MAX) <=> ($order[$kb] ?? PHP_INT_MAX);
        });

        // وداخل المجموعة ترتيب أبجدي عربي — القوائم تبلغ ثلاثين جدولاً
        foreach ($grouped as $group => $entities) {
            usort($entities, fn ($x, $y) => strcoll($x['label'], $y['label']));
            $grouped[$group] = $entities;
        }

        return $grouped;
    }
}
