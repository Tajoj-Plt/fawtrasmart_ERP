<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * دقّةُ التكلفة، وإعداداتُ نموذج أمر التصنيع.
 *
 * ── الدقّة ───────────────────────────────────────────────────────────
 *
 * تكلفةُ الوحدة كانت `decimal(10,2)` في المخزون وبنود الأذون والأوامر.
 * وموادُّ التصنيع تُقاس بالمليلتر والغرام: مادةٌ تكلفتُها ٠٫١٢٠٩ للوحدة
 * تُخزَّن ٠٫١٢، فيدخل منتجٌ من ٥٠٠٠ وحدة المخزونَ بـ٦٠٠ بينما قيدُه في
 * الدفتر ٦٠٤٫٥٠ — المخزونُ والحساباتُ لا يتطابقان، والفرقُ يكبر مع كل أمر.
 *
 * توسيعٌ فقط: لا قيمةَ مخزَّنة تتغيّر، وكلُّ ما يقرأ العمود يقرأ الرقمَ نفسَه.
 *
 * ── الإعدادات ───────────────────────────────────────────────────────
 *
 * `order_form_settings`: أيُّ حقول نموذج الأمر تظهر، وأيُّها إلزاميّ، وأيُّ
 * أقسامه تُعرض — تُضبط من «الإعدادات العامة للتصنيع».
 */
return new class extends Migration
{
    /** جدول ⟵ [عمود ⟵ [الدقّة، الكسور، يقبل الفراغ]] */
    private array $columns = [
        'product_details' => [
            'unit_price' => [18, 6, true],
        ],
        'warehouse_permits_products' => [
            'unit_price' => [18, 6, false],
            'total' => [18, 2, false],
        ],
        'manufactur_orders' => [
            'last_total_cost' => [18, 2, true],
            'actual_quantity' => [18, 4, true],
        ],
        'manufactur_orders_items' => [
            'raw_unit_price' => [18, 6, true],
            'raw_actual_unit_price' => [18, 6, true],
            'raw_total' => [18, 2, true],
            'end_life_unit_price' => [18, 6, true],
            'end_life_total' => [18, 2, true],
            'expenses_price' => [18, 2, true],
            'expenses_total' => [18, 2, true],
            'manu_total_cost' => [18, 4, true],
            'manu_total' => [18, 2, true],
            'operating_time' => [18, 4, true],
        ],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($table, $columns) {
                foreach ($columns as $column => [$precision, $scale, $nullable]) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    $definition = $blueprint->decimal($column, $precision, $scale);

                    if ($nullable) {
                        $definition->nullable();
                    } else {
                        $definition->default(0);
                    }

                    $definition->change();
                }
            });
        }

        if (Schema::hasTable('manufacturing_general_settings')
            && ! Schema::hasColumn('manufacturing_general_settings', 'order_form_settings')) {
            Schema::table('manufacturing_general_settings', function ($blueprint) {
                $blueprint->text('order_form_settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        // التوسيعُ لا يُعاد تضييقُه: التضييقُ يقطع كسوراً مخزَّنة
        if (Schema::hasTable('manufacturing_general_settings')
            && Schema::hasColumn('manufacturing_general_settings', 'order_form_settings')) {
            Schema::table('manufacturing_general_settings', function ($blueprint) {
                $blueprint->dropColumn('order_form_settings');
            });
        }
    }
};
