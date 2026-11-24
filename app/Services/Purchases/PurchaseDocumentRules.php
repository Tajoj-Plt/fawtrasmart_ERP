<?php

namespace App\Services\Purchases;

use App\Enums\PurchaseDocumentType as Type;

/**
 * قواعد كل نوع من مستندات المشتريات — مصدر واحد بدل `if/else` متناثرة.
 *
 * كل سؤال يخصّ سلوك النوع يُسأل هنا:
 *
 *     PurchaseDocumentRules::isPayable($type)
 *     PurchaseDocumentRules::affectsSupplierBalance($type)
 *     PurchaseDocumentRules::affectsInventory($type)
 *     PurchaseDocumentRules::requiresJournalEntry($type)
 *
 * لإضافة نوع جديد: صفٌّ واحد في `MATRIX` — ولا تُلمس نماذج ولا متحكّمات.
 */
class PurchaseDocumentRules
{
    /**
     * مصفوفة القدرات لكل نوع.
     *
     * payable            هل يُدفع؟ (يدخل حساب المتبقّي وحالة الدفع)
     * supplier_balance   هل يحرّك مديونية المورّد؟
     * inventory          هل يحرّك المخزون؟
     * journal            هل يستوجب قيداً محاسبياً؟
     * direction          اتجاه الأثر: +1 يزيد المديونية، -1 ينقصها، 0 لا أثر
     * requires_origin    هل يلزمه مستند أصلي في `reference_id`؟
     */
    protected const MATRIX = [
        // أمر الشراء طلبُ توريد لا مستند مالي: لا دفع ولا مديونية ولا قيد.
        // قد يحجز كمية متوقّعة، لكنه لا يحرّك رصيد المخزون الفعلي.
        Type::ORDER->value => [
            'payable' => false,
            'supplier_balance' => false,
            'inventory' => false,
            'journal' => false,
            'direction' => 0,
            'requires_origin' => false,
        ],

        Type::INVOICE->value => [
            'payable' => true,
            'supplier_balance' => true,
            'inventory' => true,
            'journal' => true,
            'direction' => 1,
            'requires_origin' => false,
        ],

        // المرتجع يعكس أثر الفاتورة: يُنقص المخزون ويُنقص مديونية المورّد
        Type::RETURN->value => [
            'payable' => false,
            'supplier_balance' => true,
            'inventory' => true,
            'journal' => true,
            'direction' => -1,
            'requires_origin' => true,
        ],

        // الإشعار المدين تسويةٌ مالية على المورّد — لا يمسّ المخزون
        Type::DEBIT_NOTE->value => [
            'payable' => false,
            'supplier_balance' => true,
            'inventory' => false,
            'journal' => true,
            'direction' => -1,
            'requires_origin' => false,
        ],
    ];

    /** القيم الافتراضية لنوع مجهول — أضيق ما يمكن، فلا يُحدث أثراً بالخطأ */
    protected const UNKNOWN = [
        'payable' => false,
        'supplier_balance' => false,
        'inventory' => false,
        'journal' => false,
        'direction' => 0,
        'requires_origin' => false,
    ];

    /**
     * @param  Type|string|null  $type
     * @return array<string, mixed>
     */
    public static function for($type): array
    {
        $value = $type instanceof Type ? $type->value : (Type::fromValue($type)?->value);

        return self::MATRIX[$value] ?? self::UNKNOWN;
    }

    /** هل يقبل المستند الدفع؟ أمر الشراء لا يُدفع. */
    public static function isPayable($type): bool
    {
        return (bool) self::for($type)['payable'];
    }

    /** هل يحرّك رصيد المورّد؟ */
    public static function affectsSupplierBalance($type): bool
    {
        return (bool) self::for($type)['supplier_balance'];
    }

    /** هل يحرّك المخزون؟ */
    public static function affectsInventory($type): bool
    {
        return (bool) self::for($type)['inventory'];
    }

    /** هل يستوجب قيداً محاسبياً؟ */
    public static function requiresJournalEntry($type): bool
    {
        return (bool) self::for($type)['journal'];
    }

    /** اتجاه الأثر على مديونية المورّد: 1 زيادة، -1 نقص، 0 بلا أثر */
    public static function balanceDirection($type): int
    {
        return (int) self::for($type)['direction'];
    }

    /** هل يلزمه مستند أصلي؟ */
    public static function requiresOriginal($type): bool
    {
        return (bool) self::for($type)['requires_origin'];
    }

    /**
     * التحويلات المسموحة بين الأنواع.
     *
     * أمر الشراء ← فاتورة فقط. ولا يُحوَّل مرتجع ولا إشعار إلى شيء.
     */
    public static function canConvert($from, $to): bool
    {
        $from = $from instanceof Type ? $from : Type::fromValue($from);
        $to = $to instanceof Type ? $to : Type::fromValue($to);

        return $from === Type::ORDER && $to === Type::INVOICE;
    }
}
