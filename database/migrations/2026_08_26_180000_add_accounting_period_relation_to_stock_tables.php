<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربطُ مستندات المخزون بالفترة المحاسبية — كما رُبطت مستنداتُ المالية.
 *
 * **لماذا المخزون؟**
 *
 * إذنُ المخزن وتسويةُ الجرد عمليتان محاسبيتان لا مخزنيتان فحسب: كلٌّ منهما
 * تُولّد قيداً يمسّ حساب المخزون وتكلفةَ المبيعات. فإن لم تُنسَب إلى فترة
 * اختلف مجموعُ المخزون في تقاريره عن مجموعه في الدفتر — وهو أوّلُ ما يُكشف
 * عند الجرد.
 *
 * **التاريخُ الحاكم**
 *
 *   • `warehouse_permits.permission_date` — تاريخُ الإذن، وهو تاريخُ الحركة.
 *   • `inventory_adjustments.inventory_time` — وقتُ الجرد الذي عنه سُوِّي.
 *
 * والعمودُ يقبل الفراغ عمداً: مستندٌ تاريخُه خارج كلّ فترةٍ معرَّفة يُحفَظ
 * بلا نسبة ويُرى في «غير مصنَّفة» ليُصحَّح. ومنعُ الحفظ شأنُ ضابط الإقفال
 * (`RespectsAccountingPeriods`) لا شأنُ بنية الجدول.
 */
return new class extends Migration
{
    private const TABLES = [
        'warehouse_permits' => 'permission_date',
        'inventory_adjustments' => 'inventory_time',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $dateColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('accounting_period_id')->nullable()->index();

                // حذفُ فترةٍ يترك مستنداتها بلا نسبة لا يحذفها
                $blueprint->foreign('accounting_period_id')
                    ->references('id')->on('accounting_periods')
                    ->nullOnDelete();
            });

            $this->backfill($table, $dateColumn);
        }
    }

    /** إلحاقُ القائم: تحديثٌ واحدٌ لكلّ فترة لا استعلامٌ لكلّ مستند. */
    private function backfill(string $table, string $dateColumn): void
    {
        if (! Schema::hasColumn($table, $dateColumn)) {
            return;
        }

        foreach (DB::table('accounting_periods')->select('id', 'start_date', 'end_date')->get() as $period) {
            DB::table($table)
                ->whereNull('accounting_period_id')
                ->whereDate($dateColumn, '>=', $period->start_date)
                ->whereDate($dateColumn, '<=', $period->end_date)
                ->update(['accounting_period_id' => $period->id]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign([$table.'_accounting_period_id_foreign']);
                $blueprint->dropColumn('accounting_period_id');
            });
        }
    }
};
