<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * الكميات والأسعار في التصنيع أعدادٌ عشرية لا صحيحة.
 *
 * ── ما كان يحدث ──────────────────────────────────────────────────────
 *
 * خمسةُ أعمدةٍ من نوع `int` تحمل قيماً كسرية بطبيعتها. والتقريب يقع
 * **صامتاً**: لا خطأ ولا تحذير، فقط رقمٌ أصغر مما أدخله المستخدم.
 *
 *     raw_quantity        وصفة تطلب ٢٫٥ كجم دقيق   ⟵ تُحفظ ٢
 *     end_life_quantity   ٠٫٧٥ كجم فاقد            ⟵ تُحفظ ٠
 *     end_life_unit_price سعر ٧٫٥٠ ﷼               ⟵ يُحفظ ٧
 *     operating_time      نصف ساعة تشغيل           ⟵ يُحفظ ٠
 *     quantity            أمر بـ٢٫٥ كجم            ⟵ يُحفظ ٢
 *
 * والأثر يتضاعف: الكمية تدخل في حساب الاحتياج، والاحتياج في الصرف،
 * والصرف في القيد المحاسبي. فخطأُ تقريبٍ واحد في الوصفة يصير فرقاً
 * في المخزون وفي الدفتر معاً.
 *
 * **وأشدُّها `end_life_unit_price`**: عمودُ *سعر* من نوع `int`، بينما
 * `raw_unit_price` في **الجدول نفسه** `DECIMAL(10,2)`. تناقضٌ داخل
 * الجدول الواحد، ولا مسوّغ له.
 *
 * ── الأنواع المختارة ─────────────────────────────────────────────────
 *
 *  · الكميات ⟵ `DECIMAL(18,4)` — نفسُ نوع `product_details.quantity`،
 *    فتُطابق الوصفةُ حركةَ المخزون بلا تقريبٍ عند المقارنة. وأربعُ
 *    خاناتٍ تكفي الجرام والمليلتر.
 *  · الأسعار والأوقات ⟵ `DECIMAL(10,2)` — نفسُ `raw_unit_price`.
 *
 * ── الأمان ───────────────────────────────────────────────────────────
 *
 * توسيعٌ لا تضييق: كلُّ قيمةٍ صحيحة قائمة تُمثَّل تماماً في العشري،
 * فلا فقدَ بيانات. والعكس (`down`) يُضيّق ويُقرّب — انظر تعليقَه.
 */
return new class extends Migration
{
    /** الجدول ⟵ [العمود ⟵ [الدقّة, الكسور]] */
    private array $columns = [
        'production_materials_items' => [
            'raw_quantity' => [18, 4],
            'end_life_quantity' => [18, 4],
            'end_life_unit_price' => [10, 2],
            'operating_time' => [10, 2],
        ],
        'manufactur_orders_items' => [
            'raw_quantity' => [18, 4],
            'end_life_quantity' => [18, 4],
            'end_life_unit_price' => [10, 2],
            'operating_time' => [10, 2],
        ],
        'manufactur_orders' => [
            'quantity' => [18, 4],
        ],
    ];

    public function up(): void
    {
        $this->apply(fn ($blueprint, $column, $precision, $scale) => $blueprint
            ->decimal($column, $precision, $scale)
            ->default(0)
            ->change());
    }

    /**
     * العكسُ يُقرّب.
     *
     * الرجوع إلى `int` يقطع الكسور من كل صفّ — وهو فقدُ بياناتٍ لا
     * يُستردّ. تُترك متاحةً لأن الهجرة يجب أن تكون قابلة للعكس بنيوياً،
     * لكن **لا تُشغَّل على قاعدةٍ فيها بيانات إنتاج**.
     */
    public function down(): void
    {
        $this->apply(fn ($blueprint, $column) => $blueprint
            ->integer($column)
            ->default(0)
            ->change());
    }

    private function apply(callable $mutate): void
    {
        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $present = array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn($table, $column),
                ARRAY_FILTER_USE_KEY
            );

            if ($present === []) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($present, $mutate) {
                foreach ($present as $column => [$precision, $scale]) {
                    $mutate($blueprint, $column, $precision, $scale);
                }
            });
        }
    }
};
