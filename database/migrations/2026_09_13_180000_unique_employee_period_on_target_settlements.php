<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * تسويةٌ واحدة لكلّ موظفٍ في كلّ فترةٍ من كلّ هدف.
 *
 * ── التحصيلات ──
 * `uniq_coll_target_period` كان على (الهدف، من، إلى) بلا الموظف، والخدمةُ
 * تكتب صفّاً لكل موظف بالتاريخين نفسيهما. فالموظفُ الثاني يخالف المفتاح،
 * وتُلغى التسويةُ كلُّها: هدفُ تحصيلٍ بأكثر من موظفٍ لا يُسوّى أبداً.
 * يُضاف المفتاحُ الجديد بالموظف **أوّلاً** ثم يُزال القديم — القيدُ الأجنبي
 * على `collections_target_id` يحتاج فهرساً يبدأ به، والجديدُ يبدأ به.
 *
 * ── المبيعات ──
 * لا مفتاحَ فريداً إطلاقاً، فضغطتا «تسوية» متزامنتان تصرفان مرّتين. يُضاف
 * على (الهدف، الموظف، من، إلى).
 *
 * لا يُمَسّ صفّ: إن وُجدت تسوياتٌ مكرّرة من قبل (قاعدةٌ حيّة) لا يُضاف المفتاح
 * ويُكتب العددُ في السجلّ — حذفُ تسويةٍ صُرفت قرارُ محاسبٍ لا ترحيل.
 */
return new class extends Migration
{
    private const SALES_TABLE = 'sales_target_settlements';

    private const SALES_INDEX = 'uniq_sales_settle_target_employee_period';

    private const COLL_TABLE = 'collections_target_settlements';

    private const COLL_INDEX = 'uniq_coll_settle_target_employee_period';

    private const COLL_OLD_INDEX = 'uniq_coll_target_period';

    public function up(): void
    {
        $this->addUnique(self::SALES_TABLE, 'sales_target_id', self::SALES_INDEX);

        if ($this->addUnique(self::COLL_TABLE, 'collections_target_id', self::COLL_INDEX)
            && SchemaGuard::hasIndex(self::COLL_TABLE, self::COLL_OLD_INDEX)) {
            Schema::table(self::COLL_TABLE, function (Blueprint $table) {
                $table->dropUnique(self::COLL_OLD_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::SALES_TABLE) && SchemaGuard::hasIndex(self::SALES_TABLE, self::SALES_INDEX)) {
            Schema::table(self::SALES_TABLE, fn (Blueprint $table) => $table->dropUnique(self::SALES_INDEX));
        }

        // القديمُ لا يُعاد إلا حيث لا يخالفه صفّ — تسوياتُ عدّة موظفين تخالفه
        if (Schema::hasTable(self::COLL_TABLE)
            && SchemaGuard::hasIndex(self::COLL_TABLE, self::COLL_INDEX)
            && ! SchemaGuard::hasIndex(self::COLL_TABLE, self::COLL_OLD_INDEX)
            && $this->duplicates(self::COLL_TABLE, ['collections_target_id', 'from_date', 'to_date']) === 0) {
            Schema::table(self::COLL_TABLE, function (Blueprint $table) {
                $table->unique(['collections_target_id', 'from_date', 'to_date'], self::COLL_OLD_INDEX);
                $table->dropUnique(self::COLL_INDEX);
            });
        }
    }

    /**
     * يضيف المفتاح إن لم يكن ولا يخالفه صفّ. يُرجع: هل المفتاحُ قائمٌ بعده؟
     */
    private function addUnique(string $table, string $targetColumn, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        if (SchemaGuard::hasIndex($table, $index)) {
            return true;
        }

        $columns = [$targetColumn, 'employee_id', 'from_date', 'to_date'];
        $duplicates = $this->duplicates($table, $columns);

        if ($duplicates > 0) {
            Log::warning("لم يُضف {$index}: في {$table} {$duplicates} مجموعة تسويات مكرّرة للموظف والفترة نفسيهما — تُراجَع يدوياً.");

            return false;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $index));

        return true;
    }

    /** عددُ مجموعات الصفوف التي تتكرّر فيها هذه الأعمدة. */
    private function duplicates(string $table, array $columns): int
    {
        return DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }
};
