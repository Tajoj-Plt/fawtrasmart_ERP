<?php

use App\Enums\BomLineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نوعُ السطر يصير معلَناً بدل أن يُستنتَج.
 *
 * ── الحال قبل ────────────────────────────────────────────────────────
 *
 * `production_materials_items` و`manufactur_orders_items` جدولٌ واحد
 * لأربعة أنواعٍ من السطور — خام، مصروف، عملية، فاقد — بلا عمودٍ يقول
 * أيُّ نوع. فالنوع يُستنتَج من امتلاء عمودٍ مرجعي:
 *
 *     ->whereNotNull('raw_product_id')        // خام
 *     ->whereNotNull('expenses_account_id')   // مصروف
 *     ->whereNotNull('workstation_id')        // عملية
 *     ->whereNotNull('end_life_product_id')   // فاقد
 *
 * وهذا الاستنتاج انهار فعلاً: حين كانت الأعمدة `NOT NULL` كُتب فيها
 * صفرٌ بدل NULL، فصدقت الشروطُ الأربعة على كل سطر وتطابقت المجموعات.
 * أُصلح ذلك، لكنّ الهويّة تبقى معلّقةً على **غيابِ قيمة** لا على
 * تصريحٍ صريح — وهو شرطٌ يكفي لكسره خطأٌ واحد في أي كاتبٍ للجدول.
 *
 * ── بعد ──────────────────────────────────────────────────────────────
 *
 * عمودٌ `line_type` مفهرس، قيمُه من [\App\Enums\BomLineType].
 * والأعمدة المرجعية تبقى — لا يُستغنى عنها — لكنّها تصير بياناتِ
 * السطر لا هويّته.
 *
 * ── ملءُ الصفوف القائمة ──────────────────────────────────────────────
 *
 * يُملأ بنفس منطق الاستنتاج القديم حرفاً بحرف، بترتيبٍ مقصود: الخام
 * أولاً ثم المصروف ثم العملية ثم الفاقد، وكلُّ تحديثٍ مشروطٌ بأن
 * `line_type` ما يزال فارغاً. فلو بقي في القاعدة صفٌّ قديمٌ يحمل
 * نوعين — من قبل إصلاح الكتابة — أخذ **أوّلَ** نوعٍ ينطبق عليه بدل
 * أن يُحسب مرّتين. وهو أضعفُ الاحتمالين ضرراً: تصنيفٌ واحد قابلٌ
 * للمراجعة، لا سطرٌ يظهر في مجموعتين.
 *
 * وما لا ينطبق عليه شيء (صفٌّ فارغ تماماً) يبقى `NULL` ويُعدّ بياناتٍ
 * تالفة تُراجَع يدوياً — لا نخمّن له نوعاً.
 */
return new class extends Migration
{
    private array $tables = ['production_materials_items', 'manufactur_orders_items'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'line_type')) {
                continue;
            }

            Schema::table($table, function ($blueprint) {
                $blueprint->string('line_type', 20)
                    ->nullable()
                    ->after('id')
                    ->index();
            });

            $this->backfill($table);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'line_type')) {
                continue;
            }

            Schema::table($table, function ($blueprint) {
                $blueprint->dropColumn('line_type');
            });
        }
    }

    private function backfill(string $table): void
    {
        $report = [];

        foreach (BomLineType::cases() as $type) {
            $column = $type->discriminatorColumn();

            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $report[$type->value] = DB::table($table)
                ->whereNull('line_type')
                ->whereNotNull($column)
                ->where($column, '>', 0)
                ->update(['line_type' => $type->value]);
        }

        $orphans = DB::table($table)->whereNull('line_type')->count();

        $this->report($table, $report, $orphans);
    }

    private function report(string $table, array $counts, int $orphans): void
    {
        $total = array_sum($counts);

        if ($total === 0 && $orphans === 0) {
            echo "\n  {$table}: لا صفوف — لا شيء يُملأ.\n";

            return;
        }

        echo "\n  {$table}:\n";

        foreach ($counts as $type => $n) {
            if ($n > 0) {
                echo '    '.str_pad($type, 14).number_format($n)."\n";
            }
        }

        if ($orphans > 0) {
            echo "    ⚠️  {$orphans} صفّاً بلا نوع — لا عمود مرجعي فيها.\n";
            echo "        تُراجَع يدوياً؛ لم يُخمَّن لها نوع.\n";
        }

        echo "\n";
    }
};
