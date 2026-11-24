<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تحويل المفاتيح الأجنبية الصفرية إلى NULL في بنود قوائم المواد.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * BOMController@store يكتب `?? 0` لكل حقل غير معبّأ:
 *
 *     'raw_production_stage_id' => $request->raw_production_stage_id[$index] ?? 0,
 *     'expenses_account_id'     => $request->expenses_account_id[$index] ?? 0,
 *     'workstation_id'          => $request->workstation_id[$index] ?? 0,
 *     'end_life_product_id'     => $request->end_life_product_id[$index] ?? 0,
 *
 * بينما finish() و createWarehousePermit() يفرزان البنود بـ whereNotNull:
 *
 *     $rawItems         = …->whereNotNull('raw_product_id')->get();
 *     $expenseItems     = …->whereNotNull('expenses_account_id')->get();
 *     $workstationItems = …->whereNotNull('workstation_id')->get();
 *     $endLifeItems     = …->whereNotNull('end_life_product_id')->get();
 *
 * وبما أن الحقول تحمل 0 لا NULL، فـ whereNotNull **يطابق كل السطور**
 * في المجموعات الأربع. النتائج:
 *
 *  · المجموعات الأربع متطابقة، والتصفية بلا أثر إطلاقاً.
 *  · createWarehousePermit يُنشئ بنود إذن بـ product_id = 0 لكل سطر
 *    مصروفات، ويحاول تحديث مخزون منتج رقمه 0 ⇒ صفوف وهمية أو خرق FK.
 *  · حلقة endLifeItems في finish() (سطر 630) **بلا حارس** — بخلاف
 *    حلقتَي المصروفات (726) والتشغيل (749) — فتكتب كل السطور بمنتج 0.
 *
 * ── الترتيب ──────────────────────────────────────────────────────────
 *
 * هذه الهجرة تنظّف البيانات القائمة. والإصلاح في الكود (`?? 0` → `?: null`)
 * يمنع تكرارها. **طبّق الاثنين معاً** — الهجرة وحدها تُنظّف ثم يُعيد
 * الكود ملء الأصفار عند أول حفظ.
 *
 * ⚠️ تحقق أولاً أن الأعمدة nullable في الهجرة الأصلية. إن كانت
 *    NOT NULL فحوّلها أولاً بهجرة منفصلة.
 */
return new class extends Migration
{
    /** الأعمدة المرجعية — تُنظَّف. الحقول العددية (الكميات والأسعار) لا تُلمس. */
    private array $foreignKeyColumns = [
        'raw_product_id',
        'raw_production_stage_id',
        'expenses_account_id',
        'expenses_production_stage_id',
        'workstation_id',
        'manu_production_stage_id',
        'end_life_product_id',
        'end_life_production_stage_id',
    ];

    /**
     * تُحوّل الأعمدةَ المرجعية إلى NULL-able قبل أي كتابة.
     *
     * هجرةُ الإنشاء تعرّفها `unsignedBigInteger` بلا `nullable()` — أي
     * `NOT NULL` — فالتحويلُ إلى NULL أدناه يرمي 1048 على أي قاعدةٍ فيها
     * صفٌّ واحدٌ بصفر. لا يظهر ذلك في التنصيب الجديد لأن الجدولَ فارغ،
     * فتُصيب التحديثاتُ صفرَ صفوف ويمرُّ العيبُ صامتاً.
     *
     * هذا ما يقوله تحذيرُ الهجرة أعلاه حرفياً: «إن كانت NOT NULL فحوّلها
     * أولاً». وهو تحويلٌ لا يفقد شيئاً — توسيعُ مجالِ العمود لا تضييقُه.
     */
    private function makeColumnsNullable(string $table): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $nullable = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->whereIn('COLUMN_NAME', $this->foreignKeyColumns)
            ->where('IS_NULLABLE', 'NO')
            ->pluck('COLUMN_NAME');

        foreach ($nullable as $col) {
            DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$col` BIGINT UNSIGNED NULL");
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('production_materials_items')) {
            return;
        }

        $this->makeColumnsNullable('production_materials_items');

        if (Schema::hasTable('manufactur_orders_items')) {
            $this->makeColumnsNullable('manufactur_orders_items');
        }

        $report = [];

        foreach ($this->foreignKeyColumns as $col) {
            if (! Schema::hasColumn('production_materials_items', $col)) {
                continue;
            }

            $affected = DB::table('production_materials_items')
                ->where($col, 0)
                ->update([$col => null]);

            if ($affected > 0) {
                $report[$col] = $affected;
            }
        }

        // نفس العيب في بنود أوامر التصنيع، إن وُجد
        if (Schema::hasTable('manufactur_orders_items')) {
            foreach ($this->foreignKeyColumns as $col) {
                if (! Schema::hasColumn('manufactur_orders_items', $col)) {
                    continue;
                }

                $affected = DB::table('manufactur_orders_items')
                    ->where($col, 0)
                    ->update([$col => null]);

                if ($affected > 0) {
                    $report['orders.'.$col] = $affected;
                }
            }
        }

        if ($report) {
            echo "\n  صفوف نُظِّفت (0 → NULL):\n";
            foreach ($report as $col => $n) {
                echo '    '.str_pad($col, 34).number_format($n)."\n";
            }
            echo "\n";
        } else {
            echo "\n  لا صفوف بمفاتيح أجنبية صفرية — نظيف.\n\n";
        }
    }

    /**
     * لا عكس.
     *
     * تحويل NULL إلى 0 يعيد العيب، ولا سبيل للتمييز بين صفرٍ كان
     * أصلياً وصفرٍ نتج عن `?? 0`.
     */
    public function down(): void
    {
        // مقصود: لا شيء.
    }
};
