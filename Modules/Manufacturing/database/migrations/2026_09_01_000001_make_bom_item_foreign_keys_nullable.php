<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جعل المفاتيح الأجنبية في بنود قوائم المواد تقبل NULL — ثم تنظيف الأصفار.
 *
 * ── الشرط الناقص ─────────────────────────────────────────────────────
 *
 * الهجرة 2026_08_22_000004_null_out_zero_foreign_keys_in_bom_items
 * تنصّ في ترويستها على شرطٍ مسبق:
 *
 *     «⚠️ تحقق أولاً أن الأعمدة nullable في الهجرة الأصلية.
 *        إن كانت NOT NULL فحوّلها أولاً بهجرة منفصلة.»
 *
 * تلك الهجرة المنفصلة لم تُكتب قط. فـ `production_materials_items`
 * ما زالت أعمدتُه NOT NULL، وقد نُفِّذ التنظيف (الدفعة ٥٠٥) بلا أثر:
 * على خادمٍ غير صارم (sql_mode بلا STRICT_TRANS_TABLES — وهو حال
 * الخوادم هنا) يُحوّل MySQL قيمةَ NULL إلى 0 صامتاً بدل أن يرفضها.
 *
 * تحقّقٌ بالتنفيذ على قاعدة التطوير:
 *
 *     UPDATE production_materials_items SET expenses_account_id = NULL WHERE id = 1;
 *     SELECT expenses_account_id FROM production_materials_items WHERE id = 1;  →  0
 *
 * فالعيب الذي وصفته الهجرة السابقة ما يزال قائماً بحذافيره: أربعُ
 * مجموعاتٍ تُفرز بـ whereNotNull فتتطابق جميعاً، وأذونُ مستودعٍ
 * ببنود product_id = 0.
 *
 * ونظيرُه في `manufactur_orders_items` كان قد عولج في الدفعة ٣٢٩
 * (2026_01_11_173400)، فبقي جدولُ قوائم المواد وحده متخلّفاً.
 *
 * ── ما تفعله هذه الهجرة ──────────────────────────────────────────────
 *
 * ١) تحوّل الأعمدة المرجعية إلى nullable.
 * ٢) تعيد تنفيذ التنظيف الذي فشل صامتاً — إذ لا تُعاد الهجرةُ السابقة.
 *
 * الترتيب مقصود: التحويل أولاً وإلا تكرّر الابتلاع نفسه.
 */
return new class extends Migration
{
    private const TABLE = 'production_materials_items';

    /** الأعمدة المرجعية وحدها. الكمياتُ والأسعار أرقامٌ لا مفاتيح — لا تُلمس. */
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

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $present = array_values(array_filter(
            $this->foreignKeyColumns,
            fn (string $column) => Schema::hasColumn(self::TABLE, $column)
        ));

        if ($present === []) {
            return;
        }

        Schema::table(self::TABLE, function ($table) use ($present) {
            foreach ($present as $column) {
                $table->unsignedBigInteger($column)->nullable()->change();
            }
        });

        // التنظيف الذي ابتُلع في الدفعة ٥٠٥ — الآن والأعمدة تقبل NULL فعلاً.
        foreach ($present as $column) {
            DB::table(self::TABLE)->where($column, 0)->update([$column => null]);
        }
    }

    /**
     * لا عكس.
     *
     * إعادة الأعمدة إلى NOT NULL تتطلّب ملأ كل NULL بصفر، وهو عين
     * العيب الذي أُصلح. ولا سبيل للتمييز بين صفرٍ أصلي وصفرٍ مُقحَم.
     */
    public function down(): void
    {
        // مقصود: لا شيء.
    }
};
