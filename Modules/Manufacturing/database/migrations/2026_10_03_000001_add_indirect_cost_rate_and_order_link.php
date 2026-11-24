<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * التكاليف غير المباشرة تصل أمرَ التصنيع.
 *
 * كانت شاشةُ «التكاليف غير المباشرة» تحفظ رأساً وبنوداً ثمّ لا يقرؤها شيء:
 * لا يزيد بها أمرٌ ولا تتغيّر تكلفةُ منتج. والربطُ عمودان وثالث:
 *
 *   indirect_costs.unit_rate        نصيبُ الوحدة من المصروف — يحسبه الخادم
 *                                   عند الحفظ (الإجمالي ÷ أساس التوزيع).
 *   indirect_costs.allocation_base  أساسُ التوزيع — مجموعُ كميات أوامر
 *                                   الفترة أو مجموعُ تكاليفها، بحسب النوع.
 *   manufactur_orders_items.indirect_cost_id
 *                                   بندُ مصروفٍ في الأمر مصدرُه تكلفةٌ غير
 *                                   مباشرة: مبلغُه = نصيب الوحدة × أساس
 *                                   الأمر، ويدخل تكلفتَه كأيّ مصروف.
 *
 * إضافةٌ فقط — لا يُمسّ عمودٌ قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indirect_costs')) {
            Schema::table('indirect_costs', function ($table) {
                if (! Schema::hasColumn('indirect_costs', 'unit_rate')) {
                    $table->decimal('unit_rate', 18, 6)->default(0)->after('total');
                }

                if (! Schema::hasColumn('indirect_costs', 'allocation_base')) {
                    $table->decimal('allocation_base', 18, 4)->default(0)->after('total');
                }
            });
        }

        if (Schema::hasTable('manufactur_orders_items')
            && ! Schema::hasColumn('manufactur_orders_items', 'indirect_cost_id')) {
            Schema::table('manufactur_orders_items', function ($table) {
                $table->unsignedBigInteger('indirect_cost_id')->nullable()->after('expenses_account_id');
                $table->index('indirect_cost_id', 'mfg_order_items_indirect_cost_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('manufactur_orders_items')
            && Schema::hasColumn('manufactur_orders_items', 'indirect_cost_id')) {
            Schema::table('manufactur_orders_items', function ($table) {
                $table->dropIndex('mfg_order_items_indirect_cost_idx');
                $table->dropColumn('indirect_cost_id');
            });
        }

        if (Schema::hasTable('indirect_costs')) {
            Schema::table('indirect_costs', function ($table) {
                foreach (['unit_rate', 'allocation_base'] as $column) {
                    if (Schema::hasColumn('indirect_costs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
