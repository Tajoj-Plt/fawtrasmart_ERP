<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يوحّد نوعَ كل عمودٍ مرجعيٍّ مع العمود الذي يشير إليه.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * MySQL ترفض المفتاحَ الأجنبي بالخطأ 150 ما لم يتطابق العمودان في النوع
 * **والإشارة**. الأعمدة أدناه وصلت من سكيما الإنتاج القديمة `BIGINT` بإشارة
 * أو `INT`، بينما `id` المرجعيّ `BIGINT UNSIGNED` — فتتعثّر كلُّ هجرةٍ تحاول
 * إضافةَ القيد، وهو ما أوقف `2025_12_18_000001_add_treasury_id_to_salary_slips`.
 *
 * المستودعُ يعرّفها كلَّها `unsignedBigInteger` في هجرات الإنشاء: الانحرافُ
 * في القاعدة القائمة وحدها، فالتصحيحُ يُعيدها إلى ما يقوله المستودع أصلاً.
 *
 * ── سلامةُ البيانات ──────────────────────────────────────────────────
 *
 * فُحصت السبعةُ قبل الكتابة: لا قيمةَ سالبةً في أيٍّ منها ولا صفَّ يتيماً،
 * وكلُّها `NULL`-able بلا قيمةٍ افتراضية. فالتحويلُ توسيعٌ لا يفقد شيئاً —
 * ولهذا يُحافَظ على `NULL` صراحةً في كل جملة.
 *
 * ── لماذا هذا التاريخ ────────────────────────────────────────────────
 *
 * `2025_08_14_150001` بعد هجرة المزامنة مباشرةً وقبل أولِ هجرةٍ تضيف قيداً
 * يعتمد على هذه الأنواع. الترتيبُ في Laravel باسم الملف.
 */
return new class extends Migration
{
    /**
     * العمود ← النوع الذي يجب أن يكون عليه ليطابق مرجعه.
     *
     * @var array<string, array<string, string>>
     */
    private array $columns = [
        'employee_client_visits' => ['approved_by' => 'BIGINT UNSIGNED NULL'],
        'notifications' => ['receiver_id' => 'BIGINT UNSIGNED NULL'],
        'salary_slips' => ['treasury_id' => 'BIGINT UNSIGNED NULL'],
        'credit_limit' => ['client_id' => 'BIGINT UNSIGNED NULL'],
        'product_exchange_items' => ['reason_id' => 'BIGINT UNSIGNED NULL'],
        'product_exchanges' => ['return_storehouse_id' => 'BIGINT UNSIGNED NULL'],
        'store_houses' => ['account_id' => 'BIGINT UNSIGNED NULL'],
    ];

    public function up(): void
    {
        // SQLite (بيئةُ الاختبارات وحدها) تُبنى من الصفر بالأنواع الصحيحة.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->columns as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $column => $definition) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $current = SchemaGuard::columnType($table, $column);

                // مطابقٌ سلفاً — كلُّ تركيبٍ جديد يمرُّ من هنا بلا عمل.
                if ($current !== null && str_contains($current, 'unsigned') && str_starts_with($current, 'bigint')) {
                    continue;
                }

                DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
            }
        }
    }

    /**
     * لا عكس.
     *
     * إعادةُ الإشارة تكسر المفاتيحَ الأجنبية التي بُنيت فوق هذا التصحيح،
     * والنوعُ الحالي هو ما تعرّفه هجراتُ الإنشاء أصلاً.
     */
    public function down(): void
    {
        // مقصود: لا شيء.
    }
};
