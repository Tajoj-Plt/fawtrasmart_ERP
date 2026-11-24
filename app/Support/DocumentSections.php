<?php

namespace App\Support;

/**
 * السجلّ المركزي لأقسام الترقيم المتسلسل.
 *
 * كان تعريف الأقسام مكرّراً في مكانين متعارضين: قائمة من 37 قسماً داخل قالب
 * الشاشة، وخريطة من 16 قسماً داخل الكنترولر — فأي قسم موجود في الأولى وغائب
 * عن الثانية كان يعرض الرقم صفراً. صار التعريف هنا وحده.
 *
 * لإضافة قسم جديد: أضف مدخلاً واحداً في `SECTIONS` ولا شيء غيره.
 */
class DocumentSections
{
    /**
     * المجموعات بترتيب عرضها في الشاشة.
     */
    public const GROUPS = [
        'sales' => ['label' => 'المبيعات', 'icon' => 'shopping-cart'],
        'purchases' => ['label' => 'المشتريات', 'icon' => 'shopping-bag'],
        'finance' => ['label' => 'المالية والمحاسبة', 'icon' => 'dollar-sign'],
        'inventory' => ['label' => 'المخزون', 'icon' => 'package'],
        'manufacturing' => ['label' => 'التصنيع', 'icon' => 'tool'],
        'pos' => ['label' => 'نقاط البيع', 'icon' => 'monitor'],
        'hr' => ['label' => 'الموارد البشرية', 'icon' => 'user-check'],
        'entities' => ['label' => 'الجهات والبيانات', 'icon' => 'users'],
        'realestate' => ['label' => 'إدارة العقارات', 'icon' => 'home'],
    ];

    /**
     * كل قسم: التسمية، المجموعة، البادئة المقترحة، والجدول الذي يُستنتج منه
     * آخر رقم عند عدم وجود إعداد محفوظ.
     *
     * `column` هو عمود رقم المستند في ذلك الجدول — يستعمله
     * `DocumentNumberService` ليتخطّى الأرقام المحجوزة فعلاً بدل أن يسلّم
     * رقماً يرفضه التحقّق لاحقاً برسالة «الكود مستخدم من قبل». الأقسام التي
     * لا نعرف عمودها تتركه غائباً، فيعمل التوليد فيها كما كان بلا فحص.
     */
    public const SECTIONS = [
        // ==================== المبيعات ====================
        'invoice' => ['label' => 'فاتورة مبيعات', 'group' => 'sales', 'prefix' => 'INV', 'table' => 'invoices', 'column' => 'code'],
        'return-invoice' => ['label' => 'فاتورة مرتجعة', 'group' => 'sales', 'prefix' => 'RET', 'table' => 'invoices', 'column' => 'code'],
        // `quotes` يحمل مستندين منذ أوامر البيع، فلكلٍّ تسلسلُه: بلا
        // `scope` يرى قسمُ العروض أرقامَ الأوامر محجوزةً فيقفز عنها
        'quotation' => ['label' => 'عرض سعر', 'group' => 'sales', 'prefix' => 'QT', 'table' => 'quotes', 'column' => 'quote_number', 'scope' => ['type' => 'quote']],
        'sales-order' => ['label' => 'أمر بيع', 'group' => 'sales', 'prefix' => 'SOR', 'table' => 'quotes', 'column' => 'quote_number', 'scope' => ['type' => 'sales_order']],
        'credit-note' => ['label' => 'إشعار دائن', 'group' => 'sales', 'prefix' => 'CN', 'table' => 'credit_notifications', 'column' => 'credit_number'],
        'sales-debit-notes' => ['label' => 'إشعارات مدينة المبيعات', 'group' => 'sales', 'prefix' => 'SDN', 'table' => null],
        // `reservations` بلا عمود رقم — الجدول هنا يوهم بفحصِ تصادمٍ لا يقع
        'reservation' => ['label' => 'حجز', 'group' => 'sales', 'prefix' => 'RSV', 'table' => null],
        // الفاتورة الدورية — قالبُ اشتراكٍ تُصدَر منه فواتير، فلها
        // تسلسلها المستقلّ لا تسلسل الفواتير
        'periodic-invoice' => ['label' => 'فاتورة دورية', 'group' => 'sales', 'prefix' => 'PRD', 'table' => 'periodic_invoices', 'column' => 'invoice_number'],
        'invoice-payment' => ['label' => 'مدفوعات الفواتير', 'group' => 'sales', 'prefix' => 'PAY', 'table' => null],
        'payment-return' => ['label' => 'دفع مبلغ مرتجع', 'group' => 'sales', 'prefix' => 'PRT', 'table' => null],
        // تبديل المنتجات — كان `ProductExchange::generateCode()` يبني الرقم
        // بيده من `id` الأخير: بادئة `EX-` مثبَّتة في الموديل لا تُضبط من
        // شاشة الترقيم، وحذفُ آخر سجلّ يُعيد الرقم فيتكرّر. صار قسماً هنا
        // فيسري عليه ما يسري على بقيّة المستندات
        'product-exchange' => ['label' => 'تبديل منتجات', 'group' => 'sales', 'prefix' => 'EX', 'table' => 'product_exchanges', 'column' => 'code'],
        // اتفاقية التقسيط — لم يكن لها رقمٌ تُعرَف به غير `id`، فالمستند
        // المطبوع يخرج بلا رقم ولا يُرجَع إليه في مراسلةٍ ولا مطالبة
        'installment' => ['label' => 'اتفاقية تقسيط', 'group' => 'sales', 'prefix' => 'INS', 'table' => 'installments', 'column' => 'code'],
        // طلب المتجر الإلكتروني — كان `OnlineOrder::generateOrderNumber()` يولّد
        // `'ORD-'.date('Ymd').'-'.uniqid()` بمعزل عن هذه الشاشة، فلا بادئة ولا
        // طول يُضبطان منها، ولا ضمان لعدم التصادم تحت التزامن
        'online-order' => ['label' => 'طلب متجر إلكتروني', 'group' => 'sales', 'prefix' => 'ORD', 'table' => 'online_orders', 'column' => 'order_number'],

        // ==================== المشتريات ====================
        'purchase-invoice' => ['label' => 'فاتورة شراء', 'group' => 'purchases', 'prefix' => 'PINV', 'table' => 'purchase_invoices', 'column' => 'code', 'scope' => ['type' => 'invoice']],
        'purchase-return' => ['label' => 'مرتجع مشتريات', 'group' => 'purchases', 'prefix' => 'PRET', 'table' => 'purchase_invoices', 'column' => 'code', 'scope' => ['type' => 'Return']],
        // التسمية توافق اسم القسم في القائمة («طلبات الشراء») ليجدها المسؤول
        'purchase-order' => ['label' => 'طلب شراء', 'group' => 'purchases', 'prefix' => 'PO', 'table' => 'purchase_orders', 'column' => 'code'],
        // «أوامر الشراء» — تُحفظ في جدول المستندات الموحّد `purchase_invoices`
        // بنوع `Requested`
        'purchase-order-request' => ['label' => 'أمر شراء', 'group' => 'purchases', 'prefix' => 'POR', 'table' => 'purchase_invoices', 'column' => 'code', 'scope' => ['type' => 'Requested']],
        // استلام المشتريات — المستند الجديد بين أمر الشراء وفاتورة الشراء
        'purchase-receiving' => ['label' => 'استلام مشتريات', 'group' => 'purchases', 'prefix' => 'PRC', 'table' => 'purchase_receivings', 'column' => 'code'],
        'purchase-quotation' => ['label' => 'عرض سعر مشتريات', 'group' => 'purchases', 'prefix' => 'PQT', 'table' => 'purchase_quotations_view', 'column' => 'code'],
        'quotation-request' => ['label' => 'طلب عرض أسعار', 'group' => 'purchases', 'prefix' => 'RFQ', 'table' => 'purchase_quotations', 'column' => 'code'],
        'purchase-refund' => ['label' => 'دفع فاتورة شراء', 'group' => 'purchases', 'prefix' => 'PRF', 'table' => null],
        'purchase-refund-payment' => ['label' => 'دفع مرتجع المشتريات', 'group' => 'purchases', 'prefix' => 'PRP', 'table' => null],
        'purchase-credit-notes' => ['label' => 'إشعارات دائنة المشتريات', 'group' => 'purchases', 'prefix' => 'PCN', 'table' => null],
        // إشعار مدين المشتريات — النوع `City Notice` في الجدول الموحّد
        'purchase-debit-note' => ['label' => 'إشعار مدين مشتريات', 'group' => 'purchases', 'prefix' => 'PDN', 'table' => 'purchase_invoices', 'column' => 'code', 'scope' => ['type' => 'City Notice']],

        // ==================== المالية ====================
        'entry' => ['label' => 'قيد يومية', 'group' => 'finance', 'prefix' => 'JE', 'table' => 'journal_entries', 'column' => 'reference_number'],
        'expense' => ['label' => 'مصروف', 'group' => 'finance', 'prefix' => 'EXP', 'table' => 'expenses', 'column' => 'code'],
        'receipt-voucher' => ['label' => 'سند قبض', 'group' => 'finance', 'prefix' => 'RV', 'table' => 'receipts', 'column' => 'code'],
        // `payment_vouchers` بلا عمود رقم — مفتاحه `payment_id` وحده
        'payment-voucher' => ['label' => 'سند صرف', 'group' => 'finance', 'prefix' => 'PV', 'table' => null],

        // ==================== المخزون ====================
        'warehouse-add' => ['label' => 'إذن إضافة مخزون', 'group' => 'inventory', 'prefix' => 'WIN', 'table' => 'warehouse_permits', 'column' => 'number'],
        // كان يشير إلى `warehouse_permits_products` — جدول بنود الإذن، ولا عمود
        // `number` فيه أصلاً؛ فلا يُكتشف رقمٌ محجوز ويتكرّر رقم الإذن
        'warehouse-dispose' => ['label' => 'إذن صرف مخزون', 'group' => 'inventory', 'prefix' => 'WOUT', 'table' => 'warehouse_permits', 'column' => 'number'],
        // و`transfers` جدولٌ لا وجود له في القاعدة — أذون التحويل في
        // `warehouse_permits` نفسه
        'transfer-request' => ['label' => 'طلب تحويل', 'group' => 'inventory', 'prefix' => 'TRF', 'table' => 'warehouse_permits', 'column' => 'number'],
        'inventory-report' => ['label' => 'ورقة الجرد', 'group' => 'inventory', 'prefix' => 'STK', 'table' => 'inventory_adjustments', 'column' => 'reference_number'],
        'products' => ['label' => 'المنتجات', 'group' => 'inventory', 'prefix' => 'P', 'table' => 'products', 'column' => 'serial_number'],
        // شاشة الأصناف تَعِد بتوليدٍ تلقائي («يُترك فارغاً للتوليد التلقائي»)
        // ولم يكن لها قسمٌ هنا ولا مولّدٌ في الكنترولر
        'product-category' => ['label' => 'الأصناف', 'group' => 'inventory', 'prefix' => 'CAT', 'table' => 'categories', 'column' => 'code'],
        'products-custom' => ['label' => 'المنتجات المخصصة', 'group' => 'inventory', 'prefix' => 'PC', 'table' => null],

        // ==================== التصنيع ====================
        'manufacturing-orders' => ['label' => 'أوامر التصنيع', 'group' => 'manufacturing', 'prefix' => 'MO', 'table' => 'manufactur_orders', 'column' => 'code'],
        // كان `'table' => null` — قسمٌ معلَنٌ في شاشة الترقيم بلا جدولٍ خلفه،
        // فلا يُكتشَف رقمٌ محجوز. الجدولُ من هجرة التصنيع `2026_09_10_000001`
        'production-plan' => ['label' => 'خطة إنتاج', 'group' => 'manufacturing', 'prefix' => 'PP', 'table' => 'production_plans', 'column' => 'code'],
        'production-routes' => ['label' => 'مسارات الإنتاج', 'group' => 'manufacturing', 'prefix' => 'PR', 'table' => 'production_paths', 'column' => 'code'],
        'production-material-lists' => ['label' => 'قوائم مواد الإنتاج', 'group' => 'manufacturing', 'prefix' => 'BOM', 'table' => 'production_materials', 'column' => 'code'],
        'workstations' => ['label' => 'محطات العمل', 'group' => 'manufacturing', 'prefix' => 'WS', 'table' => 'work_stations', 'column' => 'code'],
        // التكاليف غير المباشرة كانت المستندَ الوحيد في التصنيع بلا رقم:
        // يُشار إليه في الشاشات بـ`id` الخام، فلا بادئة ولا تسلسل يضبطهما
        // المحاسب. العمود أُضيف في هجرة وحدة التصنيع 2026_09_09_000001
        'indirect-costs' => ['label' => 'التكاليف غير المباشرة', 'group' => 'manufacturing', 'prefix' => 'IC', 'table' => 'indirect_costs', 'column' => 'code'],

        // ==================== نقاط البيع ====================
        //
        // فواتير نقطة البيع ومرتجعاتها تسكن `invoices` كغيرها، لكن بسلسلةٍ
        // مستقلّة: الكاشير يقرأ رقمه على الإيصال الحراري ويُسأل عنه، فخلطُه
        // بسلسلة فواتير المبيعات المكتبية يجعل الترقيم يقفز قفزاتٍ لا
        // يفسّرها شيء. والفصلُ هنا هو ما كان الكنترولر يفعله بيده
        // (`'POS'.str_pad(...)`) بلا قفلٍ ولا إعداد — فيتصادم جهازان يبيعان
        // في اللحظة نفسها.
        'pos-invoice' => ['label' => 'فاتورة نقطة بيع', 'group' => 'pos', 'prefix' => 'POS', 'table' => 'invoices', 'column' => 'code', 'scope' => ['type' => 'pos']],
        'pos-return' => ['label' => 'مرتجع نقطة بيع', 'group' => 'pos', 'prefix' => 'PRT', 'table' => 'invoices', 'column' => 'code', 'scope' => ['type' => 'returned']],
        'pos-session' => ['label' => 'جلسة نقطة بيع', 'group' => 'pos', 'prefix' => 'SES', 'table' => 'pos_sessions', 'column' => 'session_number'],
        'pos-cash-movement' => ['label' => 'حركة درج نقدي', 'group' => 'pos', 'prefix' => 'CDM', 'table' => 'pos_cash_movements', 'column' => 'reference_number'],

        // ==================== الموارد البشرية ====================
        /*
            العقودُ كانت مصنَّفةً في «المبيعات» — وهي وثيقةُ موظف. والمجموعةُ
            تصنيفُ عرضٍ في شاشة الترقيم لا أكثر، فنقلُها لا يمسّ مفتاحَ
            القسم (`contracts`) ولا ما وُلِّد به من أرقام.
        */
        'employee' => ['label' => 'الموظفون', 'group' => 'hr', 'prefix' => 'EMP', 'table' => 'employees', 'column' => 'employee_code'],
        'contracts' => ['label' => 'العقود', 'group' => 'hr', 'prefix' => 'CTR', 'table' => 'contracts', 'column' => 'code'],
        'salary-slip' => ['label' => 'قسائم الرواتب', 'group' => 'hr', 'prefix' => 'SLP', 'table' => 'salary_slips', 'column' => 'code'],
        'payroll-run' => ['label' => 'صرف الرواتب', 'group' => 'hr', 'prefix' => 'PRN', 'table' => 'payroll_runs', 'column' => 'code'],
        'advance' => ['label' => 'السلف', 'group' => 'hr', 'prefix' => 'ADV', 'table' => 'salary_advances', 'column' => 'code'],
        'deduction' => ['label' => 'الخصميات', 'group' => 'hr', 'prefix' => 'DED', 'table' => 'deductions', 'column' => 'code'],

        // ==================== الجهات ====================
        'customer' => ['label' => 'العملاء', 'group' => 'entities', 'prefix' => 'C', 'table' => 'clients', 'column' => 'code'],
        // طرق الدفع — `code` عمودٌ حرٌّ يكتبه المسؤول (CASH، VISA…) منذ
        // البداية، بلا أي ترقيمٍ تلقائي. القسم هنا يمنحه رقماً تسلسلياً
        // عند تركه فارغاً فقط — لا يفرض تنسيقاً على من يفضّل رمزاً مميّزاً
        'payment-method' => ['label' => 'طرق الدفع', 'group' => 'entities', 'prefix' => 'PM', 'table' => 'payment_methods', 'column' => 'code'],
        'supplier' => ['label' => 'الموردون', 'group' => 'entities', 'prefix' => 'S', 'table' => 'suppliers', 'column' => 'number_suply'],
        'branch' => ['label' => 'الفروع', 'group' => 'entities', 'prefix' => 'BR', 'table' => 'branches', 'column' => 'code'],
        'visit' => ['label' => 'زيارة عميل', 'group' => 'entities', 'prefix' => 'VIS', 'table' => 'visits', 'column' => 'visit_number'],
        /*
            عهد الشركة لدى العميل (ثلاجة عرض، رفّ، ستاند) — كان كودُ الأصل
            حقلاً حرّاً يكتبه المندوب بيده في مودال «إضافة أصل جديد لدى
            العميل»، فيخرج كلُّ مندوبٍ بصيغةٍ مختلفة ويتصادم اثنان على
            نفس الكود لنفس العميل (`unique(client_id, asset_code)`).
            صار من هنا كبقيّة المستندات: يُعايَن في الشاشة ويُستهلَك عند
            الحفظ. الفهرس فريدٌ لكلّ عميلٍ على حدة، والفحصُ هنا على الجدول
            كلّه — أضيقُ من اللازم لا أوسع، فلا يسلّم رقماً مرفوضاً.
        */
        'client-asset' => ['label' => 'عهد الشركة لدى العملاء', 'group' => 'entities', 'prefix' => 'CAS', 'table' => 'client_company_assets', 'column' => 'asset_code'],
        'origin' => ['label' => 'الأصول', 'group' => 'entities', 'prefix' => 'AST', 'table' => 'asset_depreciations', 'column' => 'code'],

        // ==================== إدارة العقارات ====================
        're-owner' => ['label' => 'ملّاك العقارات', 'group' => 'realestate', 'prefix' => 'OWN', 'table' => 're_owners', 'column' => 'code'],
        're-building' => ['label' => 'المباني والمجمعات', 'group' => 'realestate', 'prefix' => 'BLD', 'table' => 're_buildings', 'column' => 'code'],
        're-listing' => ['label' => 'العروض العقارية', 'group' => 'realestate', 'prefix' => 'LST', 'table' => 're_listings', 'column' => 'code'],
        're-contract' => ['label' => 'عقود الإيجار والبيع', 'group' => 'realestate', 'prefix' => 'REC', 'table' => 're_contracts', 'column' => 'code'],
        're-payment' => ['label' => 'دفعات الإيجار', 'group' => 'realestate', 'prefix' => 'REP', 'table' => 're_payments', 'column' => 'code'],
        're-maintenance' => ['label' => 'طلبات الصيانة العقارية', 'group' => 'realestate', 'prefix' => 'REM', 'table' => 're_maintenance_requests', 'column' => 'code'],
        // المجمّع كان يأخذ رقمه من تسلسل المباني (`re-building`) وجدولُه غير
        // جدولها، ففحصُ التصادم يقع على `re_buildings` لا عليه
        're-complex' => ['label' => 'المجمعات العقارية', 'group' => 'realestate', 'prefix' => 'CPX', 'table' => 're_complexes', 'column' => 'code'],
        // تحويل مستحقات المالك حركةٌ في دفتره (`re_owner_entries`) بلا عمود
        // رقمٍ مستقلّ — فلا جدول يُفحص
        're-owner-transfer' => ['label' => 'تحويلات مستحقات الملّاك', 'group' => 'realestate', 'prefix' => 'OWT', 'table' => null],
        're-deposit-settlement' => ['label' => 'تسويات تأمين المستأجرين', 'group' => 'realestate', 'prefix' => 'DPS', 'table' => 're_deposit_settlements', 'column' => 'code'],
    ];

    /**
     * مرادفات لأقسام كانت تُستدعى بأسماء أخرى في القوالب القديمة،
     * فلا ينكسر أي رابط قائم بعد التوحيد.
     */
    public const ALIASES = [
        'sales' => 'invoice',
        'section' => 'invoice',
        'sales-invoice' => 'invoice',
        'client' => 'customer',
        'customers' => 'customer',
        'invoices' => 'invoice',
        'journal' => 'entry',
        'journal-entry' => 'entry',
    ];

    /** القسم الافتراضي حين لا يُمرَّر قسم صالح */
    public const DEFAULT = 'invoice';

    /**
     * يحوّل أي مُدخل إلى مفتاح قسم صالح — يحلّ المرادفات ويرجع الافتراضي
     * بدل أن يمرّر قسماً مجهولاً يعرض صفراً كما كان يحدث سابقاً.
     */
    public static function resolve(?string $section): string
    {
        $section = trim((string) $section);

        if ($section === '') {
            return self::DEFAULT;
        }

        if (isset(self::SECTIONS[$section])) {
            return $section;
        }

        if (isset(self::ALIASES[$section]) && isset(self::SECTIONS[self::ALIASES[$section]])) {
            return self::ALIASES[$section];
        }

        return self::DEFAULT;
    }

    public static function exists(?string $section): bool
    {
        return isset(self::SECTIONS[(string) $section]) || isset(self::ALIASES[(string) $section]);
    }

    /**
     * اسم القسم مترجَماً.
     *
     * `SECTIONS` ثابتٌ لا يقبل نداء `__()`، فقيمه العربية احتياطٌ يظهر إن
     * نقصت الترجمة. مفاتيح الأقسام فيها شُرطة (`return-invoice`) وهي
     * مقبولة في مفاتيح الترجمة، بخلاف النقطة.
     */
    public static function label(?string $section): string
    {
        $key = self::resolve($section);

        return self::tr('sections.'.$key, self::SECTIONS[$key]['label']);
    }

    protected static function tr(string $key, string $fallback): string
    {
        $full = 'settings.sequence.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    public static function suggestedPrefix(?string $section): string
    {
        return self::SECTIONS[self::resolve($section)]['prefix'] ?? '';
    }

    public static function table(?string $section): ?string
    {
        return self::SECTIONS[self::resolve($section)]['table'] ?? null;
    }

    /**
     * عمود رقم المستند في جدول القسم — `null` حين لا يكون معروفاً،
     * وعندها لا يُفحص التصادم أصلاً.
     */
    public static function column(?string $section): ?string
    {
        return self::SECTIONS[self::resolve($section)]['column'] ?? null;
    }

    public static function group(?string $section): string
    {
        return self::SECTIONS[self::resolve($section)]['group'] ?? 'sales';
    }

    /**
     * شرط تمييز القسم داخل جدوله حين يشترك فيه أكثر من نوع مستند.
     *
     * أربعة أقسام تُحفظ كلّها في `purchase_invoices` — الفاتورة والمرتجع
     * وأمر الشراء والإشعار المدين — ويميّزها العمود `type` وحده. بلا هذا
     * الشرط يرى كلُّ قسمٍ مستنداتِ الأقسام الثلاثة الأخرى، فيبدأ عدّاده من
     * رقمٍ لا علاقة له بمستنداته هو.
     *
     * @return array<string, mixed> عمود ← قيمة؛ فارغة حين لا يشترك الجدول
     */
    public static function scope(?string $section): array
    {
        return self::SECTIONS[self::resolve($section)]['scope'] ?? [];
    }

    /**
     * الأقسام الأخرى التي تشترك مع هذا القسم في جدول واحد.
     *
     * جدول `purchase_invoices` يحمل أربعة أنواع، والعمود `code` فيه فريد
     * على مستوى الجدول كلّه — فلا يجوز أن تتطابق صيغتا نوعين، وإلا انتهيا
     * إلى الرمز نفسه فيرفضه الفهرس. تُستعمل القائمة في التحقّق من الصيغة.
     *
     * @return array<int, string>
     */
    public static function siblings(?string $section): array
    {
        $section = self::resolve($section);
        $table = self::table($section);

        if (! $table) {
            return [];
        }

        $siblings = [];

        foreach (self::SECTIONS as $key => $definition) {
            if ($key !== $section && ($definition['table'] ?? null) === $table) {
                $siblings[] = $key;
            }
        }

        return $siblings;
    }

    /** كل المفاتيح الصالحة — تُستعمل في قاعدة التحقق `in:` */
    public static function keys(): array
    {
        return array_keys(self::SECTIONS);
    }

    /**
     * الأقسام مرتّبة داخل مجموعاتها — جاهزة للعرض في الشاشة.
     *
     * @return array<string, array{label: string, icon: string, sections: array<string, array>}>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::GROUPS as $key => $group) {
            $group['label'] = self::tr('groups.'.$key, $group['label']);
            $grouped[$key] = $group + ['sections' => []];
        }

        foreach (self::SECTIONS as $key => $section) {
            $group = $section['group'];

            if (! isset($grouped[$group])) {
                continue;
            }

            $section['label'] = self::tr('sections.'.$key, $section['label']);
            $grouped[$group]['sections'][$key] = $section;
        }

        return $grouped;
    }
}
