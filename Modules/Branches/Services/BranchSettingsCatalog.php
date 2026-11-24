<?php

namespace Modules\Branches\Services;

use App\Models\BranchSetting;

/**
 * كتالوج إعدادات الفروع — المصدر الوحيد لتعريف كل إعداد في النظام.
 *
 * قبل هذا الملف كانت الإعدادات صفوفاً في قاعدة البيانات فقط: من يريد إضافة
 * إعداد يكتب `INSERT` يدوياً، ولا أحد يعرف ما معنى المفتاح ولا نوعه ولا قيمته
 * الافتراضية. الآن التعريف في الكود (مراجَع مع الشيفرة) و`sync()` ينقله إلى
 * قاعدة البيانات، فتبقى المفاتيح القائمة كما هي ولا تُفقد قيم الفروع.
 *
 * لإضافة إعداد جديد: أضف سطراً في `definitions()` ثم شغّل
 * `php artisan branches:sync-settings`.
 *
 * الأنواع المدعومة: boolean | number | text | textarea | select | time | date
 */
class BranchSettingsCatalog
{
    /**
     * تصنيفات الإعدادات: المفتاح => [العنوان، الأيقونة، الشرح].
     *
     * العناوين تُقرأ من ملفّ الترجمة عند كل استدعاء لا مرّةً واحدة، فتتبع
     * لغة المستخدم الحالي. الأيقونة وحدها تبقى هنا لأنها ليست نصّاً.
     */
    public static function groups(): array
    {
        $icons = [
            'sharing' => 'share-2',
            'sales' => 'shopping-cart',
            'inventory' => 'package',
            'customers' => 'users',
            'accounting' => 'book-open',
            'pos' => 'monitor',
            'operations' => 'clock',
            'notifications' => 'bell',
            'documents' => 'printer',
        ];

        $groups = [];

        foreach ($icons as $key => $icon) {
            $groups[$key] = [
                'label' => __('branches.setting_groups.' . $key . '.label'),
                'icon' => $icon,
                'hint' => __('branches.setting_groups.' . $key . '.hint'),
            ];
        }

        return $groups;
    }

    /**
     * تعريف كل الإعدادات.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // ================= المشاركة بين الفروع =================
            [
                'key' => 'share_cost_center',
                'name' => 'مشاركة مركز التكلفة',
                'description' => 'يستخدم هذا الفرع مركز التكلفة المشترك للمجموعة بدل مركز خاص به.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'share_customers',
                'name' => 'مشاركة العملاء',
                'description' => 'عملاء هذا الفرع مرئيّون لبقية الفروع، ويستطيع هو رؤية عملائهم.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'share_products',
                'name' => 'مشاركة المنتجات',
                'description' => 'كتالوج المنتجات موحّد مع بقية الفروع بدل كتالوج مستقل.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'share_suppliers',
                'name' => 'مشاركة الموردين',
                'description' => 'موردو هذا الفرع متاحون لبقية الفروع في أوامر الشراء.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'account_tree',
                'name' => 'تخصيص الحسابات في شجرة الحسابات',
                'description' => 'للفرع فروع حسابات خاصة به داخل شجرة الحسابات العامة.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'share_employees',
                'name' => 'مشاركة الموظفين',
                'description' => 'يمكن إسناد موظفي الفروع الأخرى لمهام وزيارات هذا الفرع.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'share_price_lists',
                'name' => 'مشاركة قوائم الأسعار',
                'description' => 'يلتزم الفرع بقوائم الأسعار المركزية ولا ينشئ قوائم خاصة.',
                'group' => 'sharing', 'type' => 'boolean', 'default_value' => '1',
            ],

            // ================= المبيعات والفوترة =================
            [
                'key' => 'require_customer_on_invoice',
                'name' => 'إلزام تحديد العميل في الفاتورة',
                'description' => 'لا تُحفظ فاتورة بيع بدون عميل محدّد — يمنع فواتير «عميل نقدي» المجهولة.',
                'group' => 'sales', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'allow_sale_below_cost',
                'name' => 'السماح بالبيع بأقل من التكلفة',
                'description' => 'عند الإيقاف تُرفض أي فاتورة سعر بندٍ فيها أقل من تكلفة الصنف.',
                'group' => 'sales', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'max_discount_percent',
                'name' => 'أقصى نسبة خصم مسموحة (%)',
                'description' => 'أعلى خصم يمنحه موظف الفرع دون موافقة. صفر = لا خصم، ١٠٠ = بلا حد.',
                'group' => 'sales', 'type' => 'number', 'default_value' => '10',
                'options' => ['min' => 0, 'max' => 100, 'step' => 0.5, 'suffix' => '%'],
            ],
            [
                'key' => 'approval_threshold_amount',
                'name' => 'حد الموافقة على الفاتورة',
                'description' => 'الفواتير التي تتجاوز هذا المبلغ تحتاج اعتماد مدير الفرع. صفر = بلا اعتماد.',
                'group' => 'sales', 'type' => 'number', 'default_value' => '0',
                'options' => ['min' => 0, 'step' => 1, 'suffix' => 'ريال'],
            ],
            [
                'key' => 'default_payment_method',
                'name' => 'طريقة الدفع الافتراضية',
                'description' => 'الطريقة المحدّدة مسبقاً عند فتح شاشة فاتورة جديدة في هذا الفرع.',
                'group' => 'sales', 'type' => 'select', 'default_value' => 'cash',
                'options' => ['choices' => [
                    'cash' => 'نقداً',
                    'bank' => 'تحويل بنكي',
                    'card' => 'شبكة / بطاقة',
                    'credit' => 'آجل',
                ]],
            ],
            [
                'key' => 'allow_edit_confirmed_invoice',
                'name' => 'تعديل الفاتورة بعد الاعتماد',
                'description' => 'السماح بتعديل فاتورة معتمدة. إبقاؤه مغلقاً أسلم محاسبياً.',
                'group' => 'sales', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'enable_delivery_notes',
                'name' => 'تفعيل سندات التسليم',
                'description' => 'يصدر الفرع سند تسليم مستقلاً قبل الفاتورة.',
                'group' => 'sales', 'type' => 'boolean', 'default_value' => '0',
            ],

            // ================= المخزون والتحويلات =================
            [
                'key' => 'allow_negative_stock',
                'name' => 'السماح بالمخزون السالب',
                'description' => 'البيع من رصيد غير متوفر. عند الإيقاف تُرفض الفاتورة إذا لم تكف الكمية.',
                'group' => 'inventory', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'enforce_stock_reservation',
                'name' => 'حجز الكمية عند إنشاء الطلب',
                'description' => 'تُحجز الكميات فور إنشاء أمر البيع فلا يبيعها موظف آخر.',
                'group' => 'inventory', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'low_stock_threshold',
                'name' => 'حد تنبيه انخفاض المخزون',
                'description' => 'يُنبَّه الفرع عندما يهبط رصيد صنف عن هذا العدد. صفر = تعطيل التنبيه.',
                'group' => 'inventory', 'type' => 'number', 'default_value' => '5',
                'options' => ['min' => 0, 'step' => 1, 'suffix' => 'وحدة'],
            ],
            [
                'key' => 'allow_inter_branch_transfer',
                'name' => 'التحويل المخزني بين الفروع',
                'description' => 'يستطيع الفرع إرسال واستقبال تحويلات مخزنية من الفروع الأخرى.',
                'group' => 'inventory', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'auto_receive_transfers',
                'name' => 'استلام التحويلات تلقائياً',
                'description' => 'تُضاف الكميات الواردة للمخزون فور إرسالها دون خطوة استلام يدوية.',
                'group' => 'inventory', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'require_stocktake_approval',
                'name' => 'اعتماد الجرد قبل التأثير',
                'description' => 'لا يُعدَّل رصيد المخزون بنتيجة الجرد إلا بعد اعتماد مسؤول.',
                'group' => 'inventory', 'type' => 'boolean', 'default_value' => '1',
            ],

            // ================= العملاء والائتمان =================
            [
                'key' => 'enforce_credit_limit',
                'name' => 'تطبيق سقف الائتمان',
                'description' => 'منع البيع الآجل للعميل الذي تجاوز سقفه الائتماني.',
                'group' => 'customers', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'allow_customer_creation',
                'name' => 'إنشاء عملاء من هذا الفرع',
                'description' => 'عند الإيقاف يختار الفرع من العملاء القائمين فقط ولا ينشئ جدداً.',
                'group' => 'customers', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'require_customer_location',
                'name' => 'إلزام موقع العميل',
                'description' => 'لا يُحفظ عميل جديد بدون إحداثيات موقع — يلزم لتخطيط الزيارات.',
                'group' => 'customers', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'customer_code_prefix',
                'name' => 'بادئة كود العميل',
                'description' => 'تُضاف قبل رقم العميل المتسلسل، مثل: RYD-1042. اتركها فارغة لبلا بادئة.',
                'group' => 'customers', 'type' => 'text', 'default_value' => '',
                'options' => ['maxlength' => 10, 'placeholder' => 'مثال: RYD'],
            ],
            [
                'key' => 'default_credit_days',
                'name' => 'مهلة السداد الافتراضية (يوم)',
                'description' => 'عدد أيام الاستحقاق المقترحة تلقائياً لعملاء هذا الفرع.',
                'group' => 'customers', 'type' => 'number', 'default_value' => '30',
                'options' => ['min' => 0, 'max' => 365, 'step' => 1, 'suffix' => 'يوم'],
            ],

            // ================= المحاسبة والقيود =================
            [
                'key' => 'auto_post_journal_entries',
                'name' => 'ترحيل القيود تلقائياً',
                'description' => 'تُرحَّل قيود الفرع فور اعتماد المستند بدل انتظار ترحيل يدوي.',
                'group' => 'accounting', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'allow_backdated_entries',
                'name' => 'السماح بقيود بتاريخ سابق',
                'description' => 'تسجيل حركات بتاريخ ماضٍ. إبقاؤه مغلقاً يحمي الفترات المقفلة.',
                'group' => 'accounting', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'fiscal_lock_date',
                'name' => 'تاريخ إقفال الفترة',
                'description' => 'لا تُقبل أي حركة بتاريخ يسبق هذا اليوم. اتركه فارغاً لعدم الإقفال.',
                'group' => 'accounting', 'type' => 'date', 'default_value' => '',
            ],
            [
                'key' => 'separate_financial_reports',
                'name' => 'تقارير مالية مستقلّة',
                'description' => 'يظهر الفرع كوحدة مالية منفصلة في التقارير بدل دمجه مع أبيه.',
                'group' => 'accounting', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'require_expense_receipt',
                'name' => 'إلزام مرفق للمصروف',
                'description' => 'لا يُحفظ مصروف بدون صورة فاتورة أو سند مرفق.',
                'group' => 'accounting', 'type' => 'boolean', 'default_value' => '1',
            ],

            // ================= نقاط البيع =================
            [
                'key' => 'enable_pos',
                'name' => 'تفعيل نقطة البيع',
                'description' => 'إتاحة شاشة نقطة البيع لأجهزة هذا الفرع.',
                'group' => 'pos', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'require_shift_open',
                'name' => 'إلزام فتح وردية',
                'description' => 'لا يبيع الكاشير قبل فتح وردية بعهدة نقدية مسجّلة.',
                'group' => 'pos', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'allow_cash_refund',
                'name' => 'السماح بالمرتجع النقدي',
                'description' => 'إرجاع المبلغ نقداً من درج الكاشير بدل إشعار دائن فقط.',
                'group' => 'pos', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'max_refund_amount',
                'name' => 'أقصى مبلغ مرتجع بلا موافقة',
                'description' => 'المرتجعات فوق هذا المبلغ تتطلب اعتماد مسؤول. صفر = كلها تتطلب اعتماداً.',
                'group' => 'pos', 'type' => 'number', 'default_value' => '500',
                'options' => ['min' => 0, 'step' => 1, 'suffix' => 'ريال'],
            ],
            [
                'key' => 'pos_print_receipt_auto',
                'name' => 'طباعة الإيصال تلقائياً',
                'description' => 'يُطبع إيصال البيع فور إتمام العملية دون ضغط زر.',
                'group' => 'pos', 'type' => 'boolean', 'default_value' => '1',
            ],

            // ================= الدوام والحضور =================
            [
                'key' => 'work_start_time',
                'name' => 'بداية الدوام',
                'description' => 'الوقت المعتمد لبداية دوام هذا الفرع.',
                'group' => 'operations', 'type' => 'time', 'default_value' => '08:00',
            ],
            [
                'key' => 'work_end_time',
                'name' => 'نهاية الدوام',
                'description' => 'الوقت المعتمد لنهاية دوام هذا الفرع.',
                'group' => 'operations', 'type' => 'time', 'default_value' => '17:00',
            ],
            [
                'key' => 'weekend_days',
                'name' => 'أيام الإجازة الأسبوعية',
                'description' => 'اليومان اللذان لا يُحتسب فيهما دوام لهذا الفرع.',
                'group' => 'operations', 'type' => 'select', 'default_value' => 'fri_sat',
                'options' => ['choices' => [
                    'fri_sat' => 'الجمعة والسبت',
                    'sat_sun' => 'السبت والأحد',
                    'fri' => 'الجمعة فقط',
                    'none' => 'بلا إجازة ثابتة',
                ]],
            ],
            [
                'key' => 'restrict_login_to_work_hours',
                'name' => 'تقييد الدخول بساعات الدوام',
                'description' => 'يُمنع موظفو الفرع من الدخول خارج الأوقات المحدّدة أعلاه.',
                'group' => 'operations', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'enforce_geofence',
                'name' => 'إلزام الحضور من موقع الفرع',
                'description' => 'لا يُقبل تسجيل حضور خارج النطاق الجغرافي المحدّد أدناه.',
                'group' => 'operations', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'geofence_radius_meters',
                'name' => 'نطاق الحضور الجغرافي (متر)',
                'description' => 'نصف قطر الدائرة حول موقع الفرع الذي يُقبل الحضور داخله.',
                'group' => 'operations', 'type' => 'number', 'default_value' => '150',
                'options' => ['min' => 20, 'max' => 5000, 'step' => 10, 'suffix' => 'متر'],
            ],
            [
                'key' => 'late_tolerance_minutes',
                'name' => 'سماحية التأخير (دقيقة)',
                'description' => 'دقائق لا يُحتسب فيها الموظف متأخراً بعد بداية الدوام.',
                'group' => 'operations', 'type' => 'number', 'default_value' => '15',
                'options' => ['min' => 0, 'max' => 180, 'step' => 5, 'suffix' => 'دقيقة'],
            ],

            // ================= التنبيهات والتقارير =================
            [
                'key' => 'notify_manager_on_large_invoice',
                'name' => 'تنبيه المدير للفواتير الكبيرة',
                'description' => 'يصل تنبيه لمدير الفرع عند تجاوز الفاتورة حدّ الموافقة المحدّد في تبويب المبيعات.',
                'group' => 'notifications', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'notify_on_low_stock',
                'name' => 'تنبيه انخفاض المخزون',
                'description' => 'إشعار عند هبوط أي صنف تحت حد التنبيه المحدّد في تبويب المخزون.',
                'group' => 'notifications', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'notify_on_credit_breach',
                'name' => 'تنبيه تجاوز سقف الائتمان',
                'description' => 'إشعار فوري عند محاولة بيع آجل لعميل تجاوز سقفه.',
                'group' => 'notifications', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'manager_notification_email',
                'name' => 'بريد تنبيهات الفرع',
                'description' => 'تُرسل تنبيهات هذا الفرع إلى هذا البريد. اتركه فارغاً للاكتفاء بتنبيهات النظام.',
                'group' => 'notifications', 'type' => 'text', 'default_value' => '',
                'options' => ['placeholder' => 'manager@example.com', 'input' => 'email', 'maxlength' => 191],
            ],
            [
                'key' => 'daily_summary_enabled',
                'name' => 'الملخّص اليومي',
                'description' => 'تقرير يومي بمبيعات الفرع وحركته يُرسل في الوقت المحدّد أدناه.',
                'group' => 'notifications', 'type' => 'boolean', 'default_value' => '0',
            ],
            [
                'key' => 'daily_summary_time',
                'name' => 'وقت إرسال الملخّص',
                'description' => 'الساعة التي يُرسل فيها الملخّص اليومي للفرع.',
                'group' => 'notifications', 'type' => 'time', 'default_value' => '20:00',
            ],

            // ================= المستندات والطباعة =================
            [
                'key' => 'invoice_prefix',
                'name' => 'بادئة رقم الفاتورة',
                'description' => 'تسبق الرقم المتسلسل لفواتير الفرع، مثل: RYD-2026-0001.',
                'group' => 'documents', 'type' => 'text', 'default_value' => '',
                'options' => ['maxlength' => 10, 'placeholder' => 'مثال: RYD'],
            ],
            [
                'key' => 'print_logo_on_documents',
                'name' => 'طباعة الشعار على المستندات',
                'description' => 'إظهار شعار المنشأة في ترويسة مستندات هذا الفرع.',
                'group' => 'documents', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'print_branch_address',
                'name' => 'طباعة عنوان الفرع',
                'description' => 'إظهار عنوان الفرع وهاتفه أسفل ترويسة المستند بدل بيانات المركز الرئيسي.',
                'group' => 'documents', 'type' => 'boolean', 'default_value' => '1',
            ],
            [
                'key' => 'default_print_copies',
                'name' => 'عدد النسخ المطبوعة',
                'description' => 'كم نسخة تُطبع افتراضياً عند طباعة فاتورة من هذا الفرع.',
                'group' => 'documents', 'type' => 'number', 'default_value' => '1',
                'options' => ['min' => 1, 'max' => 5, 'step' => 1, 'suffix' => 'نسخة'],
            ],
            [
                'key' => 'document_footer_note',
                'name' => 'ملاحظة تذييل المستند',
                'description' => 'نص يظهر أسفل كل مستند مطبوع من هذا الفرع (شروط، شكر، رقم تواصل).',
                'group' => 'documents', 'type' => 'textarea', 'default_value' => '',
                'options' => ['maxlength' => 500, 'rows' => 3],
            ],
        ];
    }

    /** تعريف إعداد واحد بمفتاحه، أو null إن لم يكن معرّفاً. */
    public static function find(string $key): ?array
    {
        static $indexed = null;

        if ($indexed === null) {
            $indexed = [];
            foreach (static::definitions() as $definition) {
                $indexed[$definition['key']] = $definition;
            }
        }

        return $indexed[$key] ?? null;
    }

    /** القيمة الافتراضية المعرَّفة لمفتاح ما. */
    public static function defaultFor(string $key)
    {
        return static::find($key)['default_value'] ?? null;
    }

    /**
     * مزامنة الكتالوج مع قاعدة البيانات.
     *
     * لا تحذف الإعدادات غير المعرّفة هنا — قد تكون مفاتيح أضافها التركيب
     * الحالي ويعتمد عليها كود قائم؛ تُترك وتظهر في تبويب «أخرى».
     *
     * @return array{created: int, updated: int}
     */
    public static function sync(): array
    {
        $created = 0;
        $updated = 0;
        $order = 0;

        foreach (static::definitions() as $definition) {
            $order += 10;

            $attributes = [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'group' => $definition['group'] ?? 'general',
                'type' => $definition['type'] ?? 'boolean',
                'options' => $definition['options'] ?? null,
                'default_value' => (string) ($definition['default_value'] ?? ''),
                'is_inheritable' => $definition['is_inheritable'] ?? true,
                'sort_order' => $order,
                'is_active' => true,
            ];

            $setting = BranchSetting::where('key', $definition['key'])->first();

            if ($setting) {
                $setting->fill($attributes)->save();
                $updated++;
            } else {
                BranchSetting::create($attributes + ['key' => $definition['key']]);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
