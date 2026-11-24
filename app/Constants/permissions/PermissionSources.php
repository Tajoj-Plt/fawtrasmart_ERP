<?php

namespace App\Constants\permissions;

class PermissionSources
{
    // إضافة للمخزن
    const WAREHOUSE_ADD = 1; // اذن إضافة مخزني

    // صرف من المخزن
    const WAREHOUSE_DISBURSEMENT = 2; // اذن صرف مخزني

    // سحب من المخزن
    const WAREHOUSE_WITHDRAWAL = 18; // اذن سحب مخزني

    // أوامر التصنيع
    const MANUFACTURING_MAIN_PRODUCT = 3; // أمر تصنيع المنتج الرئيسي

    const MANUFACTURING_DAMAGED_MATERIALS = 4; // أمر تصنيع المواد الهالكة

    const MANUFACTURING_RETURNS = 16; // أمر تصنيع البنود المرتجعة

    const MANUFACTURING_DISBURSEMENT = 17; // أمر تصنيع لصرف المواد

    // الجرد
    const INVENTORY_WITHDRAWN = 5; // ورقة جرد منصرف

    const INVENTORY_RECEIVED = 6; // ورقة جرد وارد

    const INVENTORY_GENERAL = 19; // ورقة جرد عام

    // المبيعات
    const SALES_INVOICE = 7; // فاتورة مبيعات

    const SALES_CREDIT_NOTE = 9; // إشعار دائن مبيعات

    // مرتجع المبيعات
    const SALES_RETURN = 8; // مرتجع مبيعات

    // المشتريات
    const PURCHASE_INVOICE = 10; // فاتورة مشتريات

    const PURCHASE_DEBIT_NOTE = 12; // إشعار مدين مشتريات

    // مرتجع المشتريات
    const PURCHASE_RETURN = 11; // مرتجع مشتريات

    // التحويلات
    const MANUAL_TRANSFER = 13; // تحويل يدوي بين المخازن

    const POS_SHIFT_OUTBOUND = 14; // تحويل صادر من نقطة البيع

    const POS_SHIFT_INBOUND = 15; // تحويل وارد لنقطة البيع

    // إضافات مقترحة (اختياري)
    const PURCHASE_REQUEST = 20; // طلب شراء

    const CUSTOMER_QUOTATION = 21; // عرض سعر للعميل

    const SALES_ORDER = 22; // أمر بيع

    const PURCHASE_ORDER = 23; // أمر شراء

    const INVENTORY_DAMAGE = 24; // إتلاف مخزون

    const INVENTORY_ADJUSTMENT = 25; // تسوية مخزنية

    const WAREHOUSE_OPENING_BALANCE = 26; // رصيد افتتاحي للمخزون

    const PRODUCT_EXCHANGE = 27; // تبديل منتجات (صادر - تبديل تالف)

    const PRODUCT_EXCHANGE_RETURN = 28; // ارجاع تبديل تالف (وارد)

    // المكافآت
    const REWARD_REDEMPTION = 29; // صرف مكافأة (هدية/منتج)

    // استلام المشتريات
    const PURCHASE_RECEIVING = 30; // استلام مشتريات (بضاعة واردة من أمر شراء)

    /**
     * الحصول على جميع مصادر الأذونات
     */
    public static function getAll()
    {
        return [
            // إضافة
            self::WAREHOUSE_ADD => 'اذن إضافة مخزني',

            // صرف
            self::WAREHOUSE_DISBURSEMENT => 'اذن صرف مخزني',

            // سحب
            self::WAREHOUSE_WITHDRAWAL => 'اذن سحب مخزني',

            // تصنيع
            self::MANUFACTURING_MAIN_PRODUCT => 'أمر تصنيع المنتج الرئيسي',
            self::MANUFACTURING_DAMAGED_MATERIALS => 'أمر تصنيع المواد الهالكة',
            self::MANUFACTURING_RETURNS => 'أمر تصنيع البنود المرتجعة',
            self::MANUFACTURING_DISBURSEMENT => 'أمر تصنيع لصرف المواد',

            // جرد
            self::INVENTORY_WITHDRAWN => 'ورقة جرد منصرف',
            self::INVENTORY_RECEIVED => 'ورقة جرد وارد',
            self::INVENTORY_GENERAL => 'ورقة جرد عام',

            // مبيعات
            self::SALES_INVOICE => 'فاتورة مبيعات',
            self::SALES_CREDIT_NOTE => 'إشعار دائن مبيعات',

            // مرتجع مبيعات
            self::SALES_RETURN => 'مرتجع مبيعات',

            // مشتريات
            self::PURCHASE_INVOICE => 'فاتورة مشتريات',
            self::PURCHASE_DEBIT_NOTE => 'إشعار مدين مشتريات',

            // مرتجع مشتريات
            self::PURCHASE_RETURN => 'مرتجع مشتريات',

            // تحويل
            self::MANUAL_TRANSFER => 'تحويل يدوي بين المخازن',
            self::POS_SHIFT_OUTBOUND => 'تحويل صادر من نقطة البيع',
            self::POS_SHIFT_INBOUND => 'تحويل وارد لنقطة البيع',

            // إضافات (اختياري - احذفها إذا ما تبيها)
            self::PURCHASE_REQUEST => 'طلب شراء',
            self::CUSTOMER_QUOTATION => 'عرض سعر للعميل',
            self::SALES_ORDER => 'أمر بيع',
            self::PURCHASE_ORDER => 'أمر شراء',
            self::INVENTORY_DAMAGE => 'إتلاف مخزون',
            self::INVENTORY_ADJUSTMENT => 'تسوية مخزنية',
            self::WAREHOUSE_OPENING_BALANCE => 'رصيد افتتاحي للمخزون',
            self::PRODUCT_EXCHANGE => 'تبديل تالف',
            self::PRODUCT_EXCHANGE_RETURN => 'ارجاع تبديل تالف',

            // المكافآت
            self::REWARD_REDEMPTION => 'صرف مكافأة',

            // استلام المشتريات
            self::PURCHASE_RECEIVING => 'استلام مشتريات',
        ];
    }

    /**
     * الحصول على اسم مصدر الأذن
     */
    public static function getName($id)
    {
        $sources = self::getAll();

        return $sources[$id] ?? 'غير معروف';
    }

    /**
     * الحصول على مصادر الأذونات حسب الفئة
     */
    public static function getByCategory($category)
    {
        $categories = [
            'إضافة' => [self::WAREHOUSE_ADD],
            'صرف' => [self::WAREHOUSE_DISBURSEMENT, self::INVENTORY_DAMAGE],
            'سحب' => [self::WAREHOUSE_WITHDRAWAL],
            'تصنيع' => [
                self::MANUFACTURING_MAIN_PRODUCT,
                self::MANUFACTURING_DAMAGED_MATERIALS,
                self::MANUFACTURING_RETURNS,
                self::MANUFACTURING_DISBURSEMENT,
            ],
            'جرد' => [
                self::INVENTORY_WITHDRAWN,
                self::INVENTORY_RECEIVED,
                self::INVENTORY_GENERAL,
                self::INVENTORY_ADJUSTMENT,
            ],
            'مبيعات' => [
                self::SALES_INVOICE,
                self::SALES_CREDIT_NOTE,
                self::CUSTOMER_QUOTATION,
                self::SALES_ORDER,
            ],
            'مرتجع مبيعات' => [self::SALES_RETURN],
            'مشتريات' => [
                self::PURCHASE_INVOICE,
                self::PURCHASE_DEBIT_NOTE,
                self::PURCHASE_REQUEST,
                self::PURCHASE_ORDER,
            ],
            'مرتجع مشتريات' => [self::PURCHASE_RETURN],
            'تحويل' => [
                self::MANUAL_TRANSFER,
                self::POS_SHIFT_OUTBOUND,
                self::POS_SHIFT_INBOUND,
            ],
            'رصيد افتتاحي' => [self::WAREHOUSE_OPENING_BALANCE],
            'تبديل بضاعة' => [self::PRODUCT_EXCHANGE, self::PRODUCT_EXCHANGE_RETURN],
            'مكافآت' => [self::REWARD_REDEMPTION],
        ];

        return $categories[$category] ?? [];
    }

    /**
     * التحقق من صحة معرف المصدر
     */
    public static function isValid($id)
    {
        return array_key_exists($id, self::getAll());
    }
}
