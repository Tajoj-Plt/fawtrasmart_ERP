<?php

namespace App\Support;

/**
 * حقول الدمج المتاحة داخل قوالب المستندات.
 *
 * كانت القائمة مدفونة داخل `DocumentTemplateRenderer::parseTokens()` بلا تسميات
 * عربية، فلا يعرف المستخدم ما المتاح إلا بقراءة الكود. صارت هنا مصدراً واحداً
 * يقرأه المحرّر لبناء قائمة «إدراج حقل»، ويقرأه المصيّر للاستبدال.
 */
class DocumentFields
{
    /**
     * المجموعات وحقولها: المفتاح ← [التسمية، مثال للقيمة].
     */
    public const GROUPS = [
        'business' => [
            'label' => 'بيانات المنشأة',
            'icon' => 'home',
            'fields' => [
                'business.name' => ['الاسم التجاري', 'شركة النموذج التجارية'],
                'business.logo' => ['الشعار', '(صورة)'],
                'business.phone' => ['الهاتف', '011-1234567'],
                'business.email' => ['البريد الإلكتروني', 'info@example.com'],
                'business.tax_number' => ['الرقم الضريبي', '300000000000003'],
                'business.commercial_registration' => ['السجل التجاري', '1010101010'],
                'business.address' => ['العنوان', 'الرياض — المملكة العربية السعودية'],
                'business.website' => ['الموقع الإلكتروني', 'www.example.com'],
                'business.currency' => ['العملة', 'ر.س'],
            ],
        ],

        'customer' => [
            'label' => 'بيانات العميل',
            'icon' => 'user',
            'fields' => [
                'customer.name' => ['الاسم', 'عميل تجريبي'],
                'customer.code' => ['رمز العميل', 'C-000125'],
                'customer.tax_number' => ['الرقم الضريبي', '310000000000003'],
                'customer.phone' => ['الهاتف', '0500000000'],
                'customer.address' => ['العنوان', 'جدة — المملكة العربية السعودية'],
            ],
        ],

        'document' => [
            'label' => 'بيانات المستند',
            'icon' => 'file-text',
            'fields' => [
                'document.title' => ['عنوان المستند', 'فاتورة ضريبية'],
                'document.title_en' => ['العنوان بالإنجليزية', 'Tax Invoice'],
                'document.number' => ['رقم المستند', 'INV-2026-000042'],
                'document.date' => ['التاريخ', '2026-08-12'],
                'document.time' => ['وقت الإصدار', '17:20'],
                'document.employee' => ['الموظف', 'أحمد محمد'],
                'document.branch' => ['الفرع', 'الفرع الرئيسي'],
                'document.due_date' => ['تاريخ الاستحقاق', '2026-09-12'],
                'document.payment_method' => ['طريقة الدفع', 'تحويل بنكي'],
                'document.notes' => ['الملاحظات', 'شكراً لتعاملكم معنا'],
                'document.qr_code' => ['رمز QR', '(صورة)'],
                'document.barcode' => ['الباركود', '(صورة)'],
            ],
        ],

        'totals' => [
            'label' => 'الإجماليات',
            'icon' => 'dollar-sign',
            'fields' => [
                'totals.subtotal' => ['المجموع قبل الضريبة', '1,000.00'],
                'totals.discount' => ['الخصم', '50.00'],
                'totals.shipping' => ['الشحن', '110.00'],
                'totals.tax' => ['الضريبة', '142.50'],
                'totals.grand_total' => ['الإجمالي النهائي', '1,092.50'],
                'totals.paid' => ['المدفوع', '1,000.00'],
                // ما أرجعه مرتجعٌ أو إشعارٌ دائن — `returned_payment`
                'totals.returned' => ['مرتجع', '0.00'],
                'totals.remaining' => ['المتبقي', '92.50'],
            ],
        ],
    ];

    /**
     * حقول سطر الصنف — تُستعمل داخل الصف المتكرر فقط.
     */
    public const ITEM_FIELDS = [
        'index' => ['تسلسل السطر', '1'],
        'code' => ['رمز الصنف', 'P-001'],
        'name' => ['اسم الصنف', 'منتج تجريبي'],
        'quantity' => ['الكمية', '2'],
        'unit' => ['الوحدة', 'قطعة'],
        'price' => ['سعر الوحدة', '500.00'],
        'discount' => ['الخصم', '25.00'],
        'tax' => ['الضريبة', '71.25'],
        'total' => ['الإجمالي', '1,046.25'],
    ];

    /**
     * الشروط الجاهزة التي يمكن تعليق ظهور عنصر عليها.
     */
    public const CONDITIONS = [
        'settings.show_logo' => 'عند تفعيل عرض الشعار',
        'settings.show_qr' => 'عند تفعيل عرض رمز QR',
        'settings.show_barcode' => 'عند تفعيل عرض الباركود',
        'settings.show_customer' => 'عند تفعيل عرض بيانات العميل',
        'settings.show_tax' => 'عند تفعيل عرض الضريبة',
        'settings.show_discount' => 'عند تفعيل عرض الخصم',
    ];

    /** الحقول التي تحمل صورة — تُدرج كوسم img لا كنص */
    public const IMAGE_FIELDS = ['business.logo', 'document.qr_code', 'document.barcode'];

    /**
     * كل مفاتيح الحقول العامة مسطّحة.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::GROUPS as $group) {
            $keys = array_merge($keys, array_keys($group['fields']));
        }

        return $keys;
    }

    public static function isImage(string $key): bool
    {
        return in_array($key, self::IMAGE_FIELDS, true);
    }

    /**
     * الحقول والشروط بأسمائها المترجَمة.
     *
     * الثوابت أعلاه تبقى مرجع البنية — المفاتيح والأيقونات — وقيمها العربية
     * احتياطٌ يظهر إن نقصت الترجمة. النقطة في اسم الحقل (`business.name`)
     * مسارٌ داخل المصفوفة عند `__()`، فتُحوَّل إلى شُرطة سفلية.
     */
    public static function groups(): array
    {
        $out = [];

        foreach (self::GROUPS as $key => $group) {
            $fields = [];

            foreach ($group['fields'] as $field => [$label, $sample]) {
                $fields[$field] = [
                    self::tr('fields.'.self::slug($field), $label),
                    self::tr('samples.'.self::slug($field), $sample),
                ];
            }

            $out[$key] = [
                'label' => self::tr('field_groups.'.$key, $group['label']),
                'icon' => $group['icon'],
                'fields' => $fields,
            ];
        }

        return $out;
    }

    public static function itemFields(): array
    {
        $out = [];

        foreach (self::ITEM_FIELDS as $field => [$label, $sample]) {
            $out[$field] = [
                self::tr('item_fields.'.$field, $label),
                self::tr('item_samples.'.$field, $sample),
            ];
        }

        return $out;
    }

    public static function conditions(): array
    {
        $out = [];

        foreach (self::CONDITIONS as $key => $label) {
            $out[$key] = self::tr('conditions.'.self::slug($key), $label);
        }

        return $out;
    }

    private static function slug(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    private static function tr(string $key, string $fallback): string
    {
        $full = 'settings.doc_templates.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    public static function label(string $key): ?string
    {
        foreach (self::GROUPS as $group) {
            if (isset($group['fields'][$key])) {
                return self::tr('fields.'.self::slug($key), $group['fields'][$key][0]);
            }
        }

        return isset(self::ITEM_FIELDS[$key])
            ? self::tr('item_fields.'.$key, self::ITEM_FIELDS[$key][0])
            : null;
    }

    /**
     * قيمة تجريبية للحقل — تُعرض داخل المحرّر بدل الرمز الخام.
     */
    public static function sample(string $key): string
    {
        foreach (self::GROUPS as $group) {
            if (isset($group['fields'][$key])) {
                return self::tr('samples.'.self::slug($key), $group['fields'][$key][1]);
            }
        }

        return isset(self::ITEM_FIELDS[$key])
            ? self::tr('item_samples.'.$key, self::ITEM_FIELDS[$key][1])
            : $key;
    }
}
