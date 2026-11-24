<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أسئلةٌ عن السكيما لا يجيب عنها `Schema` — تُستعمل داخل الهجرات لتكون
 * صالحةً للتشغيل على قاعدةٍ سبقتها.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * قواعدُ التركيبات القائمة بُنيت من نسخةٍ أقدم من مجموعة الهجرات: الجداولُ
 * والقيودُ موجودةٌ فعلاً بينما جدول `migrations` لا يذكرها. فإن شغّل أحدُهم
 * التحديث، ترمي `$table->foreign(…)` الخطأ 1005/121 «اسمُ قيدٍ مكرّر»
 * ويتوقّف التحديثُ كلّه عند أول جدول.
 *
 * `Schema::hasTable()` و`Schema::hasColumn()` تغطّيان الجداول والأعمدة، ولا
 * مقابلَ لهما للمفاتيح الأجنبية ولا لقيم ENUM — وهذا ما تسدّه هذه الفئة.
 *
 * ── وSQLite ليست بيئةَ اختباراتٍ وحدها ──────────────────────────────
 *
 * كان هذا الملفّ يفترض أن SQLite لا تكون إلا قاعدةَ اختباراتٍ تُبنى من
 * الصفر في كل تشغيل، فتُعيد كلُّ الدوالّ `false` عليها بلا سؤال. والافتراض
 * سقط: `.env` في بيئات التطوير يشغّل SQLite قاعدةً **حيّةً دائمة** لها
 * ملفٌّ وبياناتٌ تُعمَّر عبر التشغيلات.
 *
 * وأثرُ ذلك ليس فهرساً زائداً بل تعطيلُ النظام كلّه: `hasIndex()` تنفي
 * فهرساً موجوداً، فتحاول الهجرةُ إنشاءه، فترمي «index already exists»
 * وتتوقّف. والهجرةُ المتوقّفة تبقى معلّقةً أبداً، و`CheckSystemUpdates`
 * يحوّل **كلَّ** صفحةٍ إلى شاشة «تحديث النظام» ما دام ترحيلٌ معلَّقاً — فلا
 * تُفتح شاشةٌ واحدة في النظام حتى تنجح الهجرة، وهي لا تنجح.
 *
 * فـ`hasIndex()` تسأل SQLite فعلاً الآن. أمّا `hasForeignKey()` فتبقى
 * `false` عليها: SQLite لا تسمّي قيودَها الأجنبية أصلاً، فلا اسمَ يُطابَق.
 */
class SchemaGuard
{
    /** أسماءُ محرّكات MySQL/MariaDB التي تملك `information_schema`. */
    protected const MYSQL_DRIVERS = ['mysql', 'mariadb'];

    /**
     * هل يحمل الجدولُ مفتاحاً أجنبياً بهذا الاسم؟
     *
     * الاسمُ الافتراضي الذي تولّده Laravel هو `{الجدول}_{العمود}_foreign`،
     * وتقبل `foreign()` اسماً صريحاً كوسيطٍ ثانٍ — مرّر ما يقابل الحالة.
     * المقارنةُ غيرُ حسّاسة لحالة الأحرف لأن أسماءَ القيود في MySQL كذلك.
     */
    public static function hasForeignKey(string $table, string $constraint): bool
    {
        if (! static::onMysql()) {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->whereRaw('LOWER(CONSTRAINT_NAME) = ?', [mb_strtolower($constraint)])
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    /**
     * هل يحمل الجدولُ فهرساً بهذا الاسم؟
     *
     * الاسمُ الافتراضي الذي تولّده Laravel هو `{الجدول}_{الأعمدة}_{النوع}`
     * حيث النوعُ `index` أو `unique`، وتقبل `index()`/`unique()` اسماً صريحاً
     * كوسيطٍ ثانٍ. تُغطّي `STATISTICS` النوعين معاً.
     */
    public static function hasIndex(string $table, string $index): bool
    {
        // SQLite: قاعدةٌ حيّة قد تسبق الهجرة — تُسأل عن فهارسها فعلاً
        // (`Schema::getIndexes()` تعمل على المحرّكين، وأسماءُ فهارس SQLite
        // غيرُ حسّاسةٍ لحالة الأحرف كنظيرتها في MySQL)
        if (static::onSqlite()) {
            if (! Schema::hasTable($table)) {
                return false;
            }

            $needle = mb_strtolower($index);

            foreach (Schema::getIndexes($table) as $existing) {
                if (mb_strtolower((string) ($existing['name'] ?? '')) === $needle) {
                    return true;
                }
            }

            return false;
        }

        if (! static::onMysql()) {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->whereRaw('LOWER(INDEX_NAME) = ?', [mb_strtolower($index)])
            ->exists();
    }

    /**
     * تعريفُ العمود كما هو مخزَّن — مثل `enum('client','invoice')`.
     *
     * تُعيد `null` إن غاب العمودُ أو لم نكن على MySQL.
     */
    public static function columnType(string $table, string $column): ?string
    {
        if (! static::onMysql()) {
            return null;
        }

        $type = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_TYPE');

        return $type === null ? null : (string) $type;
    }

    /**
     * هل العمودُ من نوع ENUM يحمل قيمةً فارغةً مكرّرة؟
     *
     * `enum('a','b','','')` تعريفٌ تقبله MariaDB عند الاستيراد لكنها ترفض
     * بعده **أيَّ** ALTER على الجدول بالخطأ 1291 — فيتعطّل التحديثُ في
     * هجرةٍ لا صلةَ لها بالعمود المعطوب.
     */
    public static function hasDuplicateEmptyEnum(string $table, string $column): bool
    {
        $type = static::columnType($table, $column);

        if ($type === null || ! str_starts_with($type, 'enum(')) {
            return false;
        }

        return substr_count($type, "''") > 1;
    }

    protected static function onMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), static::MYSQL_DRIVERS, true);
    }

    protected static function onSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
}