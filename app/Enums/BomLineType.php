<?php

namespace App\Enums;

/**
 * نوعُ السطر في بنود قائمة المواد وبنود أمر التصنيع.
 *
 * ── لماذا هذا الملف ──────────────────────────────────────────────────
 *
 * الجدولان `production_materials_items` و`manufactur_orders_items`
 * جدولٌ واحد لأربعة أنواعٍ من السطور. ولم يكن ثمّة عمودٌ يقول أيُّ نوع،
 * فصار النوع **يُستنتج** من امتلاء عمودٍ مرجعي:
 *
 *     $raw      = $items->whereNotNull('raw_product_id');
 *     $expenses = $items->whereNotNull('expenses_account_id');
 *     $ops      = $items->whereNotNull('workstation_id');
 *     $waste    = $items->whereNotNull('end_life_product_id');
 *
 * والاستنتاجُ هشّ لثلاثة أسباب ظهرت كلُّها في الإنتاج:
 *
 *  · **قيمةٌ واحدة تكسره.** حين كانت الأعمدة `NOT NULL` كُتب فيها صفرٌ
 *    بدل NULL، فصدق `whereNotNull` على كل سطرٍ وتطابقت المجموعات
 *    الأربع. أصلحناه، لكنّ الشرط ما زال معلّقاً على غيابِ قيمةٍ لا
 *    على حقيقةٍ مصرَّح بها.
 *
 *  · **أربعةُ أعمدةٍ لتعبيرٍ واحد.** ما يجب أن يكون سؤالاً واحداً
 *    («ما نوع هذا السطر؟») صار أربعةَ فحوصٍ يجب أن تبقى متّسقة في كل
 *    موضعٍ يقرأ الجدول — وهي اليوم في المتحكّمات والقوالب والتقارير.
 *
 *  · **لا فهرس.** `WHERE raw_product_id IS NOT NULL` لا يستفيد من فهرس
 *    مفيد، بخلاف `WHERE line_type = 'raw'`.
 *
 * فالعمود هنا يجعل النوعَ **معلَناً** لا مستنتَجاً. والأعمدة المرجعية
 * تبقى كما هي — لا يُستغنى عنها — لكنّها تصير بياناتِ السطر لا هويّته.
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 *     protected $casts = ['line_type' => \App\Enums\BomLineType::class];
 */
enum BomLineType: string
{
    /** مادّةٌ خام تُصرَف من المخزون — تزيد التكلفة. */
    case Raw = 'raw';

    /** مصروفٌ غير مباشر يُحمَّل على الأمر — يزيد التكلفة. */
    case Expense = 'expense';

    /** عمليةُ تشغيلٍ على محطّة عمل — تزيد التكلفة. */
    case Operation = 'operation';

    /** فاقدٌ أو ناتجٌ ثانوي — **يُخصَم** من التكلفة، وهو مُخرَجٌ لا مُدخَل. */
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Raw => 'مادّة خام',
            self::Expense => 'مصروف',
            self::Operation => 'عملية تشغيل',
            self::Waste => 'فاقد أو ناتج ثانوي',
        };
    }

    /**
     * العمودُ المرجعي الذي يميّز هذا النوع.
     *
     * مصدرٌ واحد لخريطة النوع↔العمود، تستعمله الهجرةُ في ملء الصفوف
     * القائمة ويستعمله أيُّ كودٍ يحتاج التوافق مع الفرز القديم.
     */
    public function discriminatorColumn(): string
    {
        return match ($this) {
            self::Raw => 'raw_product_id',
            self::Expense => 'expenses_account_id',
            self::Operation => 'workstation_id',
            self::Waste => 'end_life_product_id',
        };
    }

    /** هل يزيد هذا النوع تكلفة الأمر؟ الفاقد وحده يُخصَم. */
    public function addsToCost(): bool
    {
        return $this !== self::Waste;
    }

    /** @return array<string, string> للقوائم المنسدلة والفلاتر */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
