<?php

namespace App\Support\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * إعادةُ المفاتيح الأساسية والترقيم التلقائي إلى **كلّ** جداول القاعدة.
 *
 * ── الجذر، لا العَرَض ──
 *
 * ملفّاتُ الطرح (dump) التي تُنقل بها قواعدُ العملاء تصل أحياناً بجداولَ بلا
 * `PRIMARY KEY` وبلا `AUTO_INCREMENT` على `id`. والأثر واحدٌ في كل مرّة:
 *
 *     SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a
 *     default value (SQL: insert into `permissions` (...) values (...))
 *
 * لأنّ لارافيل — ككلّ كودٍ سويّ — يُدرج بلا `id` معتمداً على الترقيم
 * التلقائي. فحيثما فُقد، سقط الإدراج.
 *
 * وقد عولج هذا مرّتين قبل هذا الملفّ، ومرّتاه ناقصتان:
 *   • [\App\Support\MigrationLedger] أصلح جدول `migrations` وحده، فمضى
 *     التسجيل وسقط أوّلُ إدراجٍ في `permissions`.
 *   • و[SchemaReconciler::restorePrimaryKey()] أصلح ما **يصادف** أن يشير
 *     إليه مفتاحٌ أجنبيٌّ معلَّق، فأصلح `customer_targets` وترك `permissions`
 *     لأنّ لا مفتاحَ يشير إليه.
 *
 * فكلاهما كان يطارد العَرَض. وهذا الصنف يمسح الجداول كلَّها مرّةً واحدة قبل
 * أن يبدأ التحديث، فلا يبقى جدولٌ يسقط الإدراجُ فيه.
 *
 * ── وشروطُه صارمة: لا يُبدَّل معرّفٌ واحد ──
 *
 * المعرّفاتُ مشارٌ إليها من جداولَ أخرى، فتبديلُ واحدٍ منها يقطع رابطاً لا
 * يُستعاد. فلا يُصلَح جدولٌ إلا إذا كانت قيمُه **صالحةً للمفتاح أصلاً**:
 * بلا فراغٍ ولا صفرٍ ولا تكرار. وما لم تصلح قيمُه يُترك ويُقال — ولا يُلمَس
 * صفٌّ فيه. (بخلاف `migrations` وحده، ولا شيء يشير إلى معرّفاته، فيجوز
 * ترقيمُه — وذلك في [\App\Support\MigrationLedger] لا هنا.)
 *
 * وما لا `id` فيه أصلاً — جداولُ الربط ذاتُ المفاتيح المركّبة مثل
 * `role_has_permissions` — لا يعنيه هذا الفحص.
 *
 * @see \App\Services\Maintenance\SystemUpdateService::runMigrations()
 */
class PrimaryKeyRepair
{
    /** الأنواعُ التي يصحّ عليها الترقيم التلقائي. */
    private const INT_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    /**
     * مسحُ الجداول كلِّها وإصلاحُ ما فقد مفتاحه.
     *
     * @return array{repaired: int, skipped: int, notes: array<int, string>}
     */
    public static function repairAll(): array
    {
        $notes = [];
        $repaired = 0;
        $skipped = 0;

        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                return ['repaired' => 0, 'skipped' => 0, 'notes' => []];
            }

            foreach (self::candidates() as $row) {
                $outcome = self::repair($row->table_name, (string) $row->column_type);

                if ($outcome === null) {
                    continue;
                }

                if ($outcome['ok']) {
                    $repaired++;
                } else {
                    $skipped++;
                }

                $notes[] = $outcome['note'];
            }
        } catch (Throwable $e) {
            Log::warning('PrimaryKeyRepair: تعذّر المسح', ['error' => $e->getMessage()]);

            return [
                'repaired' => $repaired,
                'skipped' => $skipped,
                'notes' => array_merge($notes, ['تعذّر فحصُ المفاتيح الأساسية: '.$e->getMessage()]),
            ];
        }

        return ['repaired' => $repaired, 'skipped' => $skipped, 'notes' => $notes];
    }

    /**
     * الجداولُ التي لها عمود `id` صحيحُ النوع بلا ترقيمٍ تلقائي.
     *
     * استعلامٌ واحدٌ على الكتالوج لا استعلامٌ لكل جدول: القاعدة فيها مئاتُ
     * الجداول، وسؤالُها واحداً واحداً يُضيف ثوانيَ إلى تحديثٍ طويل أصلاً.
     *
     * @return array<int, object>
     */
    private static function candidates(): array
    {
        $types = "'".implode("','", self::INT_TYPES)."'";

        return DB::select(
            'SELECT c.TABLE_NAME AS table_name, c.COLUMN_TYPE AS column_type
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES t
                 ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE()
                AND c.COLUMN_NAME = ?
                AND t.TABLE_TYPE = ?
                AND c.DATA_TYPE IN ('.$types.')
                AND c.EXTRA NOT LIKE ?
              ORDER BY c.TABLE_NAME',
            ['id', 'BASE TABLE', '%auto_increment%']
        );
    }

    /**
     * إصلاحُ جدولٍ واحد.
     *
     * @return array{ok: bool, note: string}|null `null` إن لم يكن ثمّة ما يُصلَح
     */
    private static function repair(string $table, string $type): ?array
    {
        try {
            $state = DB::selectOne(
                'SELECT COUNT(*) AS total, COUNT(DISTINCT `id`) AS uniques,'
                .' SUM(CASE WHEN `id` IS NULL OR `id` = 0 THEN 1 ELSE 0 END) AS blanks'
                .' FROM `'.$table.'`'
            );

            if ($state === null) {
                return null;
            }

            $total = (int) $state->total;

            // معرّفاتٌ مكرّرةٌ أو فارغة: لا تصلح مفتاحاً، ولا تُصلَّح لأجله
            if ($total > 0 && ((int) $state->uniques !== $total || (int) $state->blanks > 0)) {
                return [
                    'ok' => false,
                    'note' => 'الجدول «'.$table.'» بلا ترقيمٍ تلقائي على `id`، ولم يُصلَح: '
                        .'فيه معرّفاتٌ مكرّرةٌ أو فارغة، وإصلاحُها يعني تبديلَ معرّفاتٍ تشير إليها جداولُ أخرى. '
                        .'نظّفها بنفسك ثمّ أعد التحديث.',
                ];
            }

            $hasPrimary = self::hasIndex($table, 'PRIMARY');

            if (! $hasPrimary) {
                DB::statement('ALTER TABLE `'.$table.'` ADD PRIMARY KEY (`id`)');
            } elseif (! self::idIsFirstInSomeIndex($table)) {
                /* له مفتاحٌ أساسيٌّ على عمودٍ آخر: لا يُنازَع، ويكفي فهرسٌ
                   فريدٌ على `id` — MySQL تشترط أن يكون العمودُ التلقائي أوّلَ
                   فهرسٍ ما، لا أن يكون المفتاحَ الأساسي. */
                DB::statement('ALTER TABLE `'.$table.'` ADD UNIQUE INDEX `'.substr($table.'_id_unique', 0, 64).'` (`id`)');
            }

            // العمودُ التلقائي لا يقبل `NULL` بحال، فيُثبَّت `NOT NULL`
            // مهما كان تعريفُه قبلَه — ولا قيمةَ فارغةَ فيه أصلاً، فُحص أعلاه
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `id` '.$type.' NOT NULL AUTO_INCREMENT');

            return [
                'ok' => true,
                'note' => 'أُعيد الترقيم التلقائي إلى «'.$table.'.id»'
                    .($hasPrimary ? '' : ' مع مفتاحه الأساسي')
                    .' — كان كلُّ إدراجٍ جديد فيه يسقط بالخطأ 1364. لم يتبدّل أيُّ معرّف.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'note' => 'تعذّر إصلاح «'.$table.'»: '.$e->getMessage(),
            ];
        }
    }

    private static function hasIndex(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return $row !== null && (int) $row->c > 0;
    }

    /** أهو أوّلُ عمودٍ في فهرسٍ ما؟ — شرطُ MySQL للعمود التلقائي. */
    private static function idIsFirstInSomeIndex(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1',
            [$table, 'id']
        );

        return $row !== null && (int) $row->c > 0;
    }
}
