<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * وحدةُ القياس في بند الوصفة — كما هي في بند الأمر.
 *
 * ── الفجوة ───────────────────────────────────────────────────────────
 *
 * `manufactur_orders_items` يملك `raw_unit_id` و`raw_conversion_factor`
 * (أُضيفا في 2026_01_20_000001). و`production_materials_items` — وهو
 * **مصدرُ** تلك البنود — لا يملكهما.
 *
 * فالوصفة تقول «٢ دقيق» ولا تقول كيلوجراماً أم جراماً أم كيساً. والأمرُ
 * المبنيّ عليها له حقلُ وحدةٍ لا يجد ما يملؤه، فيقع أحد أمرين: تُترك
 * الوحدة فارغةً فتُفترض وحدةُ المنتج الأساسية، أو يعيد المستخدم
 * إدخالها في كل أمرٍ يدوياً. وكلاهما يفتح باب خطأٍ بمعامل ١٠٠٠.
 *
 * والشاشةُ ترسل `raw_unit_id[]` أصلاً — الحقل موجودٌ في النموذج وليس
 * له عمودٌ يستقبله في هذا الجدول.
 *
 * ── ما يُضاف ─────────────────────────────────────────────────────────
 *
 *  · `raw_unit_id`          — الوحدة المختارة للبند
 *  · `raw_conversion_factor` — معاملُ التحويل إلى وحدة المنتج الأساسية،
 *                              مجمَّدٌ مع البند كي لا يتبدّل الاحتياج
 *                              إن عُدّلت جداول الوحدات لاحقاً
 *
 * كلاهما `nullable`: الوصفُ القائم بلا وحدةٍ يبقى صالحاً، ويُفهم على
 * وحدة المنتج الأساسية كما هو اليوم — فلا تتغيّر دلالةُ بياناتٍ قائمة.
 *
 * **الاستهلاكُ في الحساب ليس هنا.** هذه الهجرة تضيف الحقلين فقط؛
 * استعمالُهما في اشتقاق الاحتياج موضعُه المرحلة ٦ (الكمية الأساسية).
 */
return new class extends Migration
{
    private const TABLE = 'production_materials_items';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($blueprint) {
            if (! Schema::hasColumn(self::TABLE, 'raw_unit_id')) {
                $blueprint->unsignedBigInteger('raw_unit_id')
                    ->nullable()
                    ->after('raw_production_stage_id')
                    ->index();
            }

            if (! Schema::hasColumn(self::TABLE, 'raw_conversion_factor')) {
                $blueprint->decimal('raw_conversion_factor', 18, 6)
                    ->nullable()
                    ->after('raw_unit_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($blueprint) {
            foreach (['raw_conversion_factor', 'raw_unit_id'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
