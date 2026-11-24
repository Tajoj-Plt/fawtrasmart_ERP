<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * إصلاحُ جدول `migrations` نفسه قبل أن يُكتب فيه.
 *
 * ── العطل الذي وُجدت له ──
 *
 * على قاعدةٍ مستوردةٍ من نظامٍ يعمل، يُشغَّل الترحيلُ بنجاح ثم يسقط **تسجيلُه**:
 *
 *     0001_01_01_000000_create_session_table ............ DONE
 *     SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default
 *     value (SQL: insert into `migrations` (`migration`, `batch`) values (…))
 *
 * لأن `MigrationRepository` يُدرج عمودين لا ثلاثة — `migration` و`batch` —
 * ويترك `id` للترقيم التلقائي. فإن فقد العمودُ خاصّية `AUTO_INCREMENT` صار
 * حقلاً إلزامياً بلا قيمةٍ افتراضية، فترفضه MySQL في الوضع الصارم.
 *
 * وفقدُ الخاصّية ليس نادراً هنا: مِلفّاتُ الطرح (dump) التي تُنقل بها قواعدُ
 * العملاء تُعيد بناء الجدول أحياناً بلا `AUTO_INCREMENT` ولا مفتاحٍ أساسي —
 * لا سيّما حين يُصدَّر الجدول بأداةٍ رسومية أو يُعاد إنشاؤه يدوياً.
 *
 * ── ولماذا هو عطلٌ قاتل لا مزعج ──
 *
 * الترحيلُ يُنفَّذ ولا يُسجَّل. فيبقى «معلَّقاً» أبداً مهما أُعيد، ويبقى عدّادُ
 * المعلَّق أكبر من صفر، فيبقى `CheckSystemUpdates` يحبس كلَّ مستخدمٍ على شاشة
 * التحديث. حلقةٌ لا مخرج منها من داخل الشاشة — وهي بالضبط الحالة التي وُجدت
 * الشاشةُ لأجلها: استضافةٌ مشتركة بلا سطر أوامر.
 *
 * ── ما تفعله ──
 *
 * إصلاحاتٌ لا تمسّ صفّاً من صفوف السجلّ إلا بالترقيم:
 *   1. لا عمودَ `id` أصلاً → يُضاف مفتاحاً تلقائياً.
 *   2. مُعرّفاتٌ مكرّرةٌ أو صفريّةٌ أو فارغة → تُرقَّم من جديد بترتيب
 *      (الدفعة، اسم الترحيل). ولا شيء يشير إلى هذه المعرّفات فترقيمُها آمن.
 *   3. لا مفتاحَ أساسي → يُضاف على `id` (شرطُ MySQL لأي عمودٍ تلقائي).
 *   4. لا `AUTO_INCREMENT` → يُضاف، ويبدأ العدُّ تلقائياً من أكبر معرّفٍ + ١.
 *
 * ولا يُحذف شيء: لا صفّ ولا عمود ولا جدول.
 *
 * @see \App\Services\Maintenance\SystemUpdateService::runMigrations()
 */
class MigrationLedger
{
    /**
     * فحصُ جدول السجلّ وإصلاحُه إن لزم.
     *
     * @return array{ok: bool, repaired: bool, notes: array<int, string>}
     */
    public static function repair(): array
    {
        $notes = [];

        try {
            if (! Schema::hasTable('migrations')) {
                // لم يُهجَّر هذا التركيب قطّ: `Migrator` يُنشئ الجدول بنفسه
                // بالشكل الصحيح، فلا شيء يُصلَح هنا
                return ['ok' => true, 'repaired' => false, 'notes' => []];
            }

            $driver = DB::connection()->getDriverName();

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                // sqlite تُرقّم `INTEGER PRIMARY KEY` تلقائياً بحكم تعريفها،
                // ولا معنى لهذا الإصلاح خارج MySQL
                return ['ok' => true, 'repaired' => false, 'notes' => []];
            }

            $column = self::idColumn();

            if ($column === null) {
                DB::statement('ALTER TABLE `migrations` ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');

                return [
                    'ok' => true,
                    'repaired' => true,
                    'notes' => ['أُضيف عمود `id` المفقود إلى جدول `migrations` مفتاحاً تلقائياً.'],
                ];
            }

            if (str_contains(strtolower((string) $column->extra), 'auto_increment')) {
                return ['ok' => true, 'repaired' => false, 'notes' => []];
            }

            /* من هنا فصاعداً: العمود موجودٌ بلا ترقيمٍ تلقائي — وهو العطل. */

            if (self::renumberIfNeeded()) {
                $notes[] = 'أُعيد ترقيم صفوف `migrations` (كانت فيها معرّفاتٌ مكرّرة أو صفريّة).';
            }

            if (! self::hasPrimaryKey()) {
                DB::statement('ALTER TABLE `migrations` ADD PRIMARY KEY (`id`)');
                $notes[] = 'أُضيف المفتاح الأساسي على `migrations`.`id`.';
            }

            $type = $column->column_type ?: 'int unsigned';
            DB::statement("ALTER TABLE `migrations` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
            $notes[] = 'أُعيدت خاصّية الترقيم التلقائي إلى `migrations`.`id` — كان تسجيلُ كلّ ترحيلٍ يسقط بالخطأ 1364.';

            return ['ok' => true, 'repaired' => true, 'notes' => $notes];
        } catch (Throwable $e) {
            Log::warning('MigrationLedger: تعذّر إصلاح جدول السجلّ', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'repaired' => false,
                'notes' => array_merge($notes, ['تعذّر إصلاح جدول `migrations`: '.$e->getMessage()]),
            ];
        }
    }

    /** وصفُ عمود `id` من كتالوج القاعدة، و`null` إن لم يكن موجوداً. */
    private static function idColumn(): ?object
    {
        return DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type, EXTRA AS extra, IS_NULLABLE AS is_nullable
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['migrations', 'id']
        );
    }

    /** أللجدول مفتاحٌ أساسي؟ — شرطُ MySQL لأي عمودٍ تلقائي. */
    private static function hasPrimaryKey(): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['migrations', 'PRIMARY']
        );

        return $row !== null && (int) $row->c > 0;
    }

    /**
     * ترقيمٌ جديدٌ للصفوف متى منعت حالتُها إضافةَ مفتاحٍ أساسي.
     *
     * لا يُنادى إلا عند الحاجة: ترقيمُ سجلٍّ سليمٍ عبثٌ يبدّل معرّفاتٍ قد
     * يكون أحدٌ قد نظر إليها. والترتيب بـ(الدفعة، الاسم) يُبقي تسلسل
     * التنفيذ مقروءاً كما كان.
     *
     * @return bool أوقع ترقيمٌ فعلاً؟
     */
    private static function renumberIfNeeded(): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total,
                    COUNT(DISTINCT `id`) AS unique_ids,
                    SUM(CASE WHEN `id` IS NULL OR `id` = 0 THEN 1 ELSE 0 END) AS blanks
               FROM `migrations`'
        );

        if ($row === null || (int) $row->total === 0) {
            return false;
        }

        if ((int) $row->unique_ids === (int) $row->total && (int) $row->blanks === 0) {
            return false;
        }

        DB::statement('SET @row := 0');
        DB::statement('UPDATE `migrations` SET `id` = (@row := @row + 1) ORDER BY `batch`, `migration`');

        return true;
    }
}
