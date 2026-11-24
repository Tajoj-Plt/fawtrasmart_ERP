<?php

namespace App\Enums;

/**
 * أنواع مستندات المشتريات في الجدول الموحّد `purchase_invoices`.
 *
 * **القيم ليست اختياراً — هي ما يقبله عمود `type` فعلاً:**
 *
 *     enum('invoice','Return','Requested','City Notice')
 *
 * فأي تسمية أخرى (`purchase_order` مثلاً) يرفضها MySQL أو تُخزَّن فارغة.
 * التسميات البرمجية هنا واضحة، والقيم تبقى كما في قاعدة البيانات — فلا
 * يحتاج الترحيل إلى لمس بيانات قائمة، ولا تنكسر استعلامات مكتوبة في
 * ثلاثة عشر متحكّماً.
 *
 * الشجرة:
 *
 *     ORDER (أمر شراء)
 *        └── INVOICE (فاتورة شراء)
 *              ├── RETURN (مرتجع شراء)
 *              └── DEBIT_NOTE (إشعار مدين)
 *
 * لإضافة نوع جديد لاحقاً (عرض سعر، إذن استلام، إشعار دائن): أضف حالةً هنا،
 * وقيمةً إلى عمود الـenum بترحيل، وسطراً في `PurchaseDocumentRules`.
 */
enum PurchaseDocumentType: string
{
    /** أمر شراء — طلب توريد، ليس مستنداً مالياً */
    case ORDER = 'Requested';

    /** فاتورة شراء — المستند المالي الأساسي */
    case INVOICE = 'invoice';

    /** مرتجع شراء — يعكس أثر فاتورة */
    case RETURN = 'Return';

    /** إشعار مدين — تسوية على المورّد */
    case DEBIT_NOTE = 'City Notice';

    /**
     * الاسم المعروض — يتبع لغة الواجهة.
     */
    public function getName(): string
    {
        return __('purchase_documents.types.'.$this->name);
    }

    /**
     * كل الأنواع بتفاصيلها — بنفس أسلوب `InvoiceType::getAllWithDetails()`.
     */
    public static function getAllWithDetails(): array
    {
        $types = [];

        foreach (self::cases() as $case) {
            $types[$case->value] = [
                'code' => $case->value,
                'name' => $case->getName(),
            ];
        }

        return $types;
    }

    /**
     * تحويل نصّ إلى حالة — يقبل القيمة أو اسم الحالة، ويتسامح مع حالة
     * الأحرف لأن الشيفرة القائمة تكتب `'return'` بينما العمود `'Return'`.
     */
    public static function fromValue(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->value === $value
                || strcasecmp($case->value, $value) === 0
                || strcasecmp($case->name, $value) === 0) {
                return $case;
            }
        }

        return null;
    }
}
