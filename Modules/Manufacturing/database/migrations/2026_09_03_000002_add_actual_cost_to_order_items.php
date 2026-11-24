<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * فصلُ التكلفة التقديرية عن التكلفة الفعلية على بند أمر التصنيع.
 *
 * ── لماذا عمودان لا عمود ─────────────────────────────────────────────
 *
 * `raw_unit_price` سعرٌ **يكتبه المستخدم** في شاشة الأمر — تقديرٌ يخطّط
 * به. وتكلفةُ المخزون شيءٌ آخر تماماً: المتوسّطُ المرجّح المخزَّن في
 * `product_details.unit_price` لحظةَ الصرف.
 *
 * وحين يُصرَف بندٌ، الرقمُ الذي يجب أن يُخصم من المخزون ويُرحَّل في
 * القيد هو **الثاني**. أمّا الأول فيبقى — لا يُمحى — لأن الفرق بينهما
 * هو **انحرافُ السعر**، وهو معلومةٌ إدارية تُطلب في التقارير:
 *
 *     انحراف السعر = (التكلفة الفعلية − التقديرية) × الكمية
 *
 * فمن يخطّط بسعر ١٢ ويصرف بتكلفة ٨ يريد أن يرى الفرق، لا أن يُمحى
 * أحدُهما بالآخر.
 *
 * ── ما يُضاف ─────────────────────────────────────────────────────────
 *
 *   raw_actual_unit_price   تكلفة الوحدة من المخزون لحظةَ الصرف
 *   raw_actual_total        الكمية × التكلفة الفعلية — ما رُحِّل فعلاً
 *
 * كلاهما `nullable`: البندُ الذي لم تُصرَف مادتُه بعدُ لا تكلفةَ فعليةً
 * له، و`null` هنا تعني «لم يُصرَف» لا «صفر». وأمرٌ قديمٌ صُرف قبل هذا
 * التغيير يبقى فارغَهما، فتُقرأ تكلفتُه من التقديري كما كانت.
 */
return new class extends Migration
{
    private const TABLE = 'manufactur_orders_items';

    private array $columns = [
        'raw_actual_unit_price' => [10, 2],
        'raw_actual_total' => [18, 4],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($blueprint) {
            $after = 'raw_unit_price';

            foreach ($this->columns as $column => [$precision, $scale]) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    continue;
                }

                $blueprint->decimal($column, $precision, $scale)->nullable()->after($after);
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($blueprint) {
            foreach (array_keys($this->columns) as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
