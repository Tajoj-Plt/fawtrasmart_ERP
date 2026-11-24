<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المفاتيح الأجنبية الناقصة على جداول علاقات الصلاحيات.
 *
 * **العطل:** جداول Spatie الثلاثة (`role_has_permissions`, `model_has_roles`,
 * `model_has_permissions`) كُتبت في هذا المشروع يدوياً لا من هجرة الحزمة
 * الرسمية، فخرجت بمفاتيح أوّلية وفهارس **بلا أي مفتاح أجنبي**. والدليل أن
 * أحد الفهارس اسمه `role_has_permissions_role_id_foreign` — اسمُ قيدٍ لم
 * يُنشأ قط، بقي من مخطّطٍ صُدِّر من قاعدةٍ ثم أُعيد بناؤه ناقصاً.
 *
 * **الأثر:** حذف دورٍ أو صلاحية يترك صفوفاً يتيمة في جداول العلاقة. وSpatie
 * يقرأ تلك الصفوف ويحاول حلّها إلى صلاحياتٍ لم تعد موجودة، فيُنتج أدواراً
 * تحمل صلاحيات وهمية وعدّاداتٍ خاطئة في شاشة الأدوار. الهجرة الرسمية للحزمة
 * تُنشئ هذه القيود بـ `onDelete('cascade')` لهذا السبب بالذات.
 *
 * **التنظيف قبل القيد:** إضافة مفتاح أجنبي على جدولٍ فيه صفوف يتيمة تفشل
 * (errno 1452). فتُحذف اليتيمة أولاً — وهي بحكم تعريفها تشير إلى ما لا وجود
 * له، أي لا تمنح أحداً شيئاً ولا يضيع بحذفها حق.
 */
return new class extends Migration
{
    /**
     * الجدول ⇦ [العمود، الجدول المرجعي] لكل قيد مطلوب.
     */
    private const CONSTRAINTS = [
        'role_has_permissions' => [
            ['permission_id', 'permissions'],
            ['role_id', 'roles'],
        ],
        'model_has_roles' => [
            ['role_id', 'roles'],
        ],
        'model_has_permissions' => [
            ['permission_id', 'permissions'],
        ],
    ];

    public function up(): void
    {
        foreach (self::CONSTRAINTS as $table => $constraints) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($constraints as [$column, $references]) {
                if (! Schema::hasTable($references) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($this->constraintExists($table, $column)) {
                    continue;
                }

                $this->deleteOrphans($table, $column, $references);

                Schema::table($table, function (Blueprint $blueprint) use ($column, $references) {
                    $blueprint->foreign($column)
                        ->references('id')
                        ->on($references)
                        ->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::CONSTRAINTS as $table => $constraints) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($constraints as [$column, $references]) {
                if (! $this->constraintExists($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropForeign([$column]);
                });
            }
        }
    }

    /**
     * هل القيد موجود؟
     *
     * الفحص على `REFERENCED_TABLE_NAME IS NOT NULL` لا على اسم القيد: الاسم
     * المتوقَّع مستعمَل هنا **كاسم فهرس عادي** لا كقيد، فالمطابقة عليه تعطي
     * إيجابيةً كاذبة وتتخطّى القيد الذي وُجدت الهجرة لإضافته.
     */
    private function constraintExists(string $table, string $column): bool
    {
        // information_schema جدولٌ خاصٌّ بـMySQL. على SQLite نسأل المخطَّط
        // مباشرةً عبر واجهة Laravel المحمولة.
        if (DB::connection()->getDriverName() !== 'mysql') {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array($column, $foreignKey['columns'] ?? [], true)) {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    /**
     * حذف الصفوف التي تشير إلى معرّفٍ غير موجود.
     */
    private function deleteOrphans(string $table, string $column, string $references): void
    {
        DB::table($table)
            ->whereNotIn($column, function ($query) use ($references) {
                $query->select('id')->from($references);
            })
            ->delete();
    }
};
