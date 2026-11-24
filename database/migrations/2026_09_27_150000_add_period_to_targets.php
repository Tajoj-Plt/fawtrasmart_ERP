<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدّةُ الهدف — من تاريخٍ إلى تاريخ — وطريقةُ صرف عمولته.
 *
 * كان الهدفُ بلا مدّة: قيمةٌ ومستوياتٌ وموظفون، والتسويةُ المجدولة تقرأ
 * دورةً واحدة من «إعدادات الأهداف» وتصرف عنها لكلّ هدفٍ نشط. فهدفُ «ستّة
 * أشهر» لا مكانَ يُكتب فيه أنّه ستّةُ أشهر، وتُصرف عمولتُه عن الشهر
 * المنقضي — شهرٍ قد يسبق بدايتَه — وتُقاس قيمتُه كلُّها على شهرٍ واحد.
 *
 * الأعمدةُ كلُّها تقبل الفراغ: هدفٌ بلا مدّة يبقى على ما كان.
 *
 *   • `start_date` / `end_date`  — طرفا المدّة، مشمولان.
 *   • `settlement_mode`          — `period_end`: تُصرف مرّةً عند نهاية المدّة
 *                                  عن المدّة كلِّها؛ `cycle`: تُصرف دوريّاً
 *                                  بجدول الإعدادات، داخل المدّة وحدها.
 *   • `period_settled_at`        — متى عالجت التسويةُ المجدولة نهايةَ المدّة،
 *                                  فلا تُعاد كلَّ يوم.
 */
return new class extends Migration
{
    private const TABLES = ['sales_targets', 'collections_targets'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'start_date')) {
                    $blueprint->date('start_date')->nullable();
                }
                if (! Schema::hasColumn($table, 'end_date')) {
                    $blueprint->date('end_date')->nullable();
                }
                if (! Schema::hasColumn($table, 'settlement_mode')) {
                    $blueprint->string('settlement_mode', 20)->nullable();
                }
                if (! Schema::hasColumn($table, 'period_settled_at')) {
                    $blueprint->timestamp('period_settled_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['start_date', 'end_date', 'settlement_mode', 'period_settled_at'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }
    }
};
