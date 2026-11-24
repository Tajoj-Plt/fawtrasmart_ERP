<?php

namespace App\Support;

use App\Enums\ProductType;
use App\Models\Product;
use RuntimeException;

/**
 * هل يجوز لهذا الصنف أن يدخل المخزون؟
 *
 * **ما كان**
 *
 * لا شيء يمنع إصدار إذنٍ مخزني لخدمة، ولا إدخال كمّية لها. فتظهر «ساعة
 * استشارة» في أرصدة المستودعات، وتُحسب لها قيمةُ مخزون، وتدخل ورقة الجرد
 * — وكلّها أرقامٌ لا معنى لها تُفسد التقارير بهدوء.
 *
 * ونقاط الكتابة في المخزون **خمس عشرة موزَّعة** على كنترولرات التصنيع
 * والأذون والجرد والمنتجات. حارسٌ في كلٍّ منها يُنسى في السادس عشر.
 *
 * **ما صار**
 *
 * الحكم هنا وحده، ويُطبَّق مرّتين: مرّةً في الشاشة برسالةٍ مفهومة قبل أن
 * يُتعب المستخدم نفسه، ومرّةً في المراقب على الموديل — فلا يمرّ سطرٌ من
 * أي طريق، حاضرٍ أو قادم.
 */
class StockEligibility
{
    /** ذاكرة الطلب: النوع يُقرأ مرّةً لكل صنف مهما تكرّر في البنود. */
    private static array $cache = [];

    /**
     * نوع الصنف — من الموديل إن كان محمَّلاً، وإلّا بقراءة عمودٍ واحد.
     */
    public static function typeOf($product): ?ProductType
    {
        if ($product instanceof Product) {
            return ProductType::from_value($product->type);
        }

        $id = is_numeric($product) ? (int) $product : null;

        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, self::$cache)) {
            $type = Product::whereKey($id)->value('type');

            self::$cache[$id] = $type === null ? null : ProductType::from_value($type);
        }

        return self::$cache[$id];
    }

    /**
     * هل لهذا الصنف رصيدٌ في المستودعات؟
     *
     * الصنف المجهول يُعتبر مخزنيّاً: منعُ حركةٍ مشروعة أسوأ من السماح
     * بحركةٍ لصنفٍ محذوف ستفشل لاحقاً بمفتاحها الأجنبي على أي حال.
     */
    public static function isStockable($product): bool
    {
        $type = self::typeOf($product);

        return $type === null || $type->tracksStock();
    }

    /** نقيض `isStockable` — لقراءةٍ أوضح عند الشرط. */
    public static function isStockless($product): bool
    {
        return ! self::isStockable($product);
    }

    /**
     * رسالة المنع — تسمّي الصنف ونوعه، فلا يبقى المستخدم يخمّن.
     */
    public static function message($product): string
    {
        $type = self::typeOf($product);
        $name = $product instanceof Product
            ? $product->name
            : Product::whereKey($product)->value('name');

        return sprintf(
            'لا يمكن إدخال «%s» في المخزون: نوعه %s ولا رصيد له في المستودعات.',
            $name ?: 'هذا الصنف',
            $type ? $type->label() : 'غير مخزني'
        );
    }

    /**
     * يرمي إن كان الصنف بلا رصيد — الحارس الأخير قبل الكتابة.
     */
    public static function assert($product): void
    {
        if (self::isStockless($product)) {
            throw new RuntimeException(self::message($product));
        }
    }

    /**
     * أوّل صنفٍ غير مخزنيّ في قائمة معرّفات، أو `null` إن كانت كلّها سليمة.
     *
     * تُستعمل في الشاشات: تُفحص البنود كلّها قبل فتح المعاملة، فلا يُحفظ
     * نصف مستند ثم يُرمى.
     */
    public static function firstStockless(array $productIds): ?int
    {
        foreach (array_filter($productIds) as $id) {
            if (self::isStockless($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** لاختبارات تُغيّر أنواع الأصناف داخل الطلب نفسه. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
