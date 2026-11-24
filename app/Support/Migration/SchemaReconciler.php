<?php

namespace App\Support\Migration;

use App\Support\SchemaRetire;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * مصالحةُ المخطَّط أثناء التحديث — «إن لم يكن موجوداً» مطبَّقةً على الجميع.
 *
 * ── المشكلة ──
 *
 * تركيبات العملاء بُنيت من مجموعة ترحيلاتٍ أقدم ثمّ رُقّعت يدوياً، فمخطَّطها
 * لا يطابق ما يفترضه ملفُّ الترحيل. فيسقط التحديث في منتصفه بأحد هذه:
 *
 *     1005 (errno 150)  المفتاح الأجنبي «مُشكَّلٌ خطأً» — نوعان لا يجتمعان
 *     1060              عمودٌ مكرّر
 *     1061 / 1826       فهرسٌ أو قيدٌ بالاسم نفسه
 *     1050              جدولٌ موجود
 *     1091 / 1553       إسقاطُ فهرسٍ أو قيدٍ غير موجود
 *     42S22             تعديلُ عمودٍ غير موجود
 *
 * وسقوطُ ترحيلٍ واحدٍ يوقف كلَّ ما بعده، فيبقى المعلَّقُ معلَّقاً، ويبقى
 * `CheckSystemUpdates` حابساً كلَّ مستخدمٍ على شاشة التحديث. حلقةٌ لا يُخرج
 * منها من داخل الشاشة — وهي الحالة التي وُجدت الشاشة لأجلها أصلاً.
 *
 * ── ولماذا لا يُعالَج في الملفّات؟ ──
 *
 * لأنّ الحرّاس في الملفّات لا تكفي ولا تُدرَك:
 *   • `Schema::hasColumn` يقول «العمودان موجودان» ولا يقول «نوعاهما لا
 *     يجتمعان في مفتاح».
 *   • و`try/catch` حول `$table->foreign(...)` لا يلتقط شيئاً: الـBlueprint
 *     يصفّ الأمر ولا ينفّذه إلا بعد انتهاء الإغلاق.
 *   • و١٣٥ ملفّاً فيها `->foreign(` وحدها؛ وحراسةُ كلٍّ منها يدوياً تُنسى
 *     في الملفّ السادس والثلاثين ثمّ في كلّ ملفٍّ يُكتب بعد اليوم.
 *
 * ── الحلّ: نحويّةٌ تسأل قبل أن تُصدر الأمر ──
 *
 * `compileX()` هي اللحظةُ الوحيدة التي يُعرف فيها ما سيُطلب من القاعدة
 * **قبل** إرساله. فتُركَّب نحويّةٌ ([ReconcilingMySqlGrammar]) تسأل هذا الصنف،
 * وإرجاعُ `null` يجعل `Blueprint::toSql()` يُسقط الأمرَ من أصله — فلا استثناء
 * ولا توقّف. والقاعدةُ واحدة في كلّ الحالات: **لا يُصدَر أمرٌ نتيجتُه قائمةٌ
 * أصلاً، ولا أمرٌ يزيل ما ليس موجوداً.**
 *
 * والمفاتيح الأجنبية وحدها تحتاج أكثر من ذلك: تُوائَم أنواعُ الطرفين متى
 * أمكن بلا مساسٍ بالقيم، وإلّا تُخطّي المفتاح ويُقال. مفتاحٌ ناقصٌ قيدُ
 * سلامةٍ لا بيانات، وغيابُه أهون من نظامٍ مقفل — على أن يُعلَن لا أن يُخفى.
 *
 * ── ما لا يفعله ──
 *
 * لا يحذف عموداً ولا صفّاً ولا جدولاً، ولا يمسّ `dropColumn` (ذاك شأن
 * [\App\Services\Maintenance\MigrationSafetyScanner]). ولا يبدّل نوعاً بما
 * يقصّ قيمة: قبل أيّ تضييقٍ يُسأل العمودُ عن أكبر قيمةٍ فيه وأصغرها، وإن لم
 * تتّسع تُرك المفتاح ولم يُمَسّ العمود. وما ليس عدداً صحيحاً لا يُبدَّل نوعُه
 * إلا على جدولٍ فارغ.
 *
 * ومُركَّبٌ أثناء تشغيلة التحديث وحدها، ويُعاد الأصل في `finally`.
 *
 * @see \App\Support\Migration\ReconcilesSchema
 * @see \App\Services\Maintenance\SystemUpdateService::runMigrations()
 */
class SchemaReconciler
{
    /** رتبةُ الأنواع الصحيحة — تُحدّد أيُّهما أوسع. */
    private const INT_RANK = [
        'tinyint' => 1,
        'smallint' => 2,
        'mediumint' => 3,
        'int' => 4,
        'integer' => 4,
        'bigint' => 5,
    ];

    /** أقصى قيمةٍ يسعها كلُّ نوعٍ صحيح: [موجَب، بإشارة]. */
    private const INT_RANGE = [
        'tinyint' => [255, 127],
        'smallint' => [65535, 32767],
        'mediumint' => [16777215, 8388607],
        'int' => [4294967295, 2147483647],
        'integer' => [4294967295, 2147483647],
    ];

    private static bool $active = false;

    private static ?Grammar $original = null;

    /** @var array<int, string> ما يستحقّ أن يُقرأ: مواءمةٌ أو مفتاحٌ تُخطّي */
    private static array $notes = [];

    /** عددُ الأوامر التي أُسقطت لأن نتيجتها قائمةٌ أصلاً — تُلخَّص ولا تُعدَّد. */
    private static int $harmless = 0;

    // ==================== التركيب ====================

    /**
     * تركيبُ النحويّة المصالِحة على الاتصال الحالي.
     *
     * @return bool هل رُكِّبت؟ (لا تُركَّب خارج MySQL/MariaDB)
     */
    public static function install(): bool
    {
        try {
            $connection = DB::connection();

            if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                return false;
            }

            self::$original = $connection->getSchemaGrammar();
            self::$notes = [];
            self::$harmless = 0;

            $grammar = self::$original instanceof MariaDbGrammar
                ? new ReconcilingMariaDbGrammar
                : new ReconcilingMySqlGrammar;

            $connection->setSchemaGrammar($connection->withTablePrefix($grammar));
            self::$active = true;

            return true;
        } catch (Throwable $e) {
            Log::warning('SchemaReconciler: تعذّر التركيب', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /** إعادةُ النحويّة الأصلية — تُنادى في `finally` مهما جرى. */
    public static function uninstall(): void
    {
        if (! self::$active) {
            return;
        }

        try {
            if (self::$original !== null) {
                DB::connection()->setSchemaGrammar(self::$original);
            }
        } catch (Throwable $e) {
            Log::warning('SchemaReconciler: تعذّرت إعادة النحويّة', ['error' => $e->getMessage()]);
        } finally {
            self::$active = false;
            self::$original = null;
        }
    }

    /**
     * تقريرُ ما جرى في هذه التشغيلة.
     *
     * @return array<int, string>
     */
    public static function notes(): array
    {
        $notes = self::$notes;

        if (self::$harmless > 0) {
            $notes[] = 'أُسقط '.self::$harmless.' أمرَ مخطَّطٍ كانت نتيجتُه قائمةً أصلاً '
                .'(عمودٌ أو فهرسٌ أو جدولٌ موجود، أو إسقاطُ ما ليس موجوداً) — لا أثر له على قاعدتك.';
        }

        return $notes;
    }

    // ==================== القرارات ====================

    /** جدولٌ يُنشأ وهو قائم: 1050. */
    public static function allowsCreate(string $table): bool
    {
        return self::guard(fn () => ! Schema::hasTable($table));
    }

    /** عمودٌ يُضاف وهو قائم: 1060. */
    public static function allowsAdd(string $table, ?string $column): bool
    {
        if ($column === null) {
            return true;
        }

        return self::guard(fn () => ! Schema::hasColumn($table, $column));
    }

    /** عمودٌ يُعدَّل وهو غير موجود: 42S22. */
    public static function allowsChange(string $table, ?string $column): bool
    {
        if ($column === null) {
            return true;
        }

        return self::guard(fn () => Schema::hasColumn($table, $column));
    }

    /**
     * عمودٌ طُلب حذفُه فبقي — يُسجَّل ليُقرأ في التقرير.
     *
     * والصفوفُ تُعدّ لتقول للمستخدم ما الذي نجا: «بقي عمود opening_balance
     * في clients وفيه 3,854 صفّاً» جملةٌ يُتَّخذ عليها قرار، بخلاف «تُخطّي
     * حذف».
     *
     * @param  array<int, mixed>  $columns
     */
    public static function allowsDropColumn(string $table, array $columns): bool
    {
        if (! self::$active) {
            return true;
        }

        $present = [];

        foreach ($columns as $column) {
            try {
                if (is_string($column) && Schema::hasColumn($table, $column)) {
                    $present[] = $column;
                }
            } catch (Throwable $e) {
                // كتالوجٌ لا يُسأل: يُعامَل العمود معاملةَ الموجود فيُبقى
                if (is_string($column)) {
                    $present[] = $column;
                }
            }
        }

        if ($present === []) {
            // محذوفٌ أصلاً من هذه القاعدة: لا شيء يُحذف ولا شيء يُقال
            self::$harmless++;

            return false;
        }

        /* عمودٌ لا قيمةَ فيه أصلاً: حذفُه لا يُفقد شيئاً، فيُترك للترحيل أن
           يفعل ما كُتب له. وهذا ما يجعل التركيبَ الجديد يخرج بالمخطَّط
           المقصود تماماً — جداولُه فارغة، فلا عمودَ يبقى بلا سبب. */
        $withData = [];

        foreach ($present as $column) {
            if (self::columnHasData($table, $column)) {
                $withData[] = $column;
            }
        }

        if ($withData === []) {
            self::$harmless++;

            return true;
        }

        $rows = self::rowCount($table);
        $where = $rows > 0 ? ' وفيه '.number_format($rows).' صفّاً' : '';

        self::note('بقي «'.implode('»، «', $withData).'» في «'.$table.'»'.$where
            .' — طلب الترحيل حذفَه، والتحديث لا يحذف عموداً فيه بيانات.');

        return false;
    }

    /** أفي العمود قيمةٌ واحدةٌ غير فارغة؟ — سؤالُ وجودٍ لا عدّ. */
    private static function columnHasData(string $table, string $column): bool
    {
        try {
            return DB::selectOne(
                'SELECT 1 AS found FROM `'.$table.'` WHERE `'.$column.'` IS NOT NULL LIMIT 1'
            ) !== null;
        } catch (Throwable $e) {
            // لا يُعرف ⇒ يُفترض أنّ فيه بيانات: الإبقاء أهون من الحذف
            return true;
        }
    }

    /**
     * إحالةُ جدولٍ طُلب حذفُه إلى التقاعد بدل حذفه.
     *
     * @see \App\Support\SchemaRetire
     */
    public static function retireTable(string $table): void
    {
        if (! self::$active) {
            return;
        }

        $outcome = SchemaRetire::table($table);

        if ($outcome === SchemaRetire::RENAMED) {
            self::note('أُحيل الجدول «'.$table.'» إلى «'.SchemaRetire::PREFIX.$table
                .'» بدل حذفه — بياناتُه باقية، والاسم متاحٌ لمن يُنشئه من جديد.');
        } elseif ($outcome === SchemaRetire::KEPT) {
            self::note('طُلب حذف الجدول «'.$table.'» فتُرك كما هو — تعذّرت إحالتُه إلى التقاعد.');
        } else {
            // غير موجود، أو فارغٌ فأُسقط بلا خسارة
            self::$harmless++;
        }
    }

    /** إعادةُ تسميةِ عمودٍ غير موجود، أو إلى اسمٍ مشغول: كلاهما يسقط. */
    public static function allowsRenameColumn(string $table, ?string $from, ?string $to): bool
    {
        if ($from === null || $to === null) {
            return true;
        }

        return self::guard(fn () => Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to));
    }

    /** فهرسٌ يُنشأ بالاسم نفسه: 1061. */
    public static function allowsIndex(string $table, ?string $index): bool
    {
        if ($index === null || $index === '') {
            return true;
        }

        return self::guard(fn () => ! self::indexExists($table, $index));
    }

    /** مفتاحٌ أساسيٌّ يُضاف وللجدول واحد: 1068. */
    public static function allowsPrimary(string $table): bool
    {
        return self::guard(fn () => ! self::indexExists($table, 'PRIMARY'));
    }

    /** فهرسٌ يُسقَط وهو غير موجود: 1091. */
    public static function allowsDropIndex(string $table, ?string $index): bool
    {
        if ($index === null || $index === '') {
            return true;
        }

        return self::guard(fn () => self::indexExists($table, $index));
    }

    /** مفتاحٌ أساسيٌّ يُسقَط ولا وجود له: 1091. */
    public static function allowsDropPrimary(string $table): bool
    {
        return self::guard(fn () => self::indexExists($table, 'PRIMARY'));
    }

    /** قيدٌ أجنبيٌّ يُسقَط ولا وجود له: 1091 / 1553. */
    public static function allowsDropForeign(string $table, ?string $index): bool
    {
        if ($index === null || $index === '') {
            return true;
        }

        return self::guard(fn () => self::constraintExists($table, $index));
    }

    /**
     * أيُنشأ هذا المفتاح الأجنبي؟ — وقد يُوائم الأنواع قبل أن يأذن.
     *
     * @param  array<int, string>  $columns  أعمدة الجدول التابع
     * @param  array<int, string>  $references  أعمدة الجدول المرجعي
     */
    public static function allowsForeign(
        string $table,
        array $columns,
        ?string $on,
        array $references,
        ?string $constraint,
        bool $creating = false,
        array $pending = []
    ): bool {
        if (! self::$active) {
            return true;
        }

        try {
            return self::decideForeign($table, $columns, $on, $references, $constraint, $creating, $pending);
        } catch (Throwable $e) {
            // فحصٌ سقط لا يجوز أن يُسقط الترحيل: يُتخطّى المفتاح ويُقال
            self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}» — تعذّر فحصُه: ".$e->getMessage());

            return false;
        }
    }

    /**
     * تنفيذُ فحصٍ بسيط: النتيجة `false` تعني «أُسقط الأمر بلا ضرر».
     *
     * وفشلُ الفحص نفسه يعني «أَمْرِر»: لا يُمنع ترحيلٌ صحيحٌ بسبب استعلامِ
     * كتالوجٍ سقط — رسالةُ MySQL حينئذٍ أصدق من تخمينِنا.
     */
    private static function guard(callable $check): bool
    {
        if (! self::$active) {
            return true;
        }

        try {
            if ($check()) {
                return true;
            }

            self::$harmless++;

            return false;
        } catch (Throwable $e) {
            return true;
        }
    }

    // ==================== المفاتيح الأجنبية ====================

    private static function decideForeign(
        string $table,
        array $columns,
        ?string $on,
        array $references,
        ?string $constraint,
        bool $creating,
        array $pending
    ): bool {
        $columns = array_values(array_filter($columns, 'is_string'));
        $references = array_values(array_filter($references, 'is_string'));

        if ($on === null || $columns === [] || $references === []) {
            return true; // شكلٌ لا نفهمه: يُترك لسلوك لارافيل الأصلي
        }

        /* الجدولُ قد يكون قيد الإنشاء في هذا الـBlueprint نفسه، فلا يُسأل عنه
           الكتالوج: `toSql()` تُصرّف الأوامر كلَّها قبل أن تُنفّذ أوّلها.
           وكذلك أعمدتُه: تُقرأ من الـBlueprint لا من القاعدة. */
        if (! $creating && ! Schema::hasTable($table)) {
            self::note("تُخطّي مفتاحٌ أجنبيٌّ: الجدول «{$table}» غير موجود.");

            return false;
        }

        // مفتاحٌ بالاسم نفسه قائمٌ: إعادةُ إنشائه خطأ 1826، وتخطّيه لا يُنقص شيئاً
        if (! $creating && $constraint !== null && self::constraintExists($table, $constraint)) {
            self::$harmless++;

            return false;
        }

        foreach ($columns as $column) {
            if (! isset($pending[$column]) && self::column($table, $column) === null) {
                self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: العمود «{$column}» غير موجود.");

                return false;
            }
        }

        /* المرجعُ إلى الجدول نفسِه وهو قيد الإنشاء (شجرةٌ تشير إلى أبيها):
           لا كتالوج يُسأل، ولا فهرسٌ يُتحقّق منه — يمرّ كما كُتب. */
        if ($creating && strcasecmp($on, $table) === 0) {
            return true;
        }

        if (! Schema::hasTable($on)) {
            self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: الجدول المرجعي «{$on}» غير موجود.");

            return false;
        }

        foreach ($references as $reference) {
            if (self::column($on, $reference) === null) {
                self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: العمود المرجعي «{$on}.{$reference}» غير موجود.");

                return false;
            }
        }

        /* المحرّك: MyISAM لا يعرف المفاتيح الأجنبية أصلاً — يقبل الأمر
           ويتجاهله في نسخٍ ويرفضه بـerrno 150 في أخرى. وجداولُ MyISAM تصل
           في ملفّات الطرح القديمة. وتحويلُ المحرّك إعادةُ بناءٍ للجدول كلِّه
           لا يُقدِم عليها زرُّ تحديث، فيُتخطّى المفتاح ويُقال. */
        foreach ([$table, $on] as $side) {
            $engine = self::engineOf($side);

            if ($engine !== null && strcasecmp($engine, 'InnoDB') !== 0) {
                self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: محرّك الجدول «{$side}» هو {$engine} ولا يدعم المفاتيح الأجنبية.");

                return false;
            }
        }

        /* العمودُ المرجعيّ يجب أن يكون أوّلَ فهرسٍ ما، وإلّا رفضت MySQL المفتاح
           بالخطأ 1822. وغيابُ الفهرس على عمودٍ كـ`clients.id` ليس تصميماً بل
           أثرُ استيراد: ملفُّ الطرح الذي أسقط `AUTO_INCREMENT` عن
           `migrations.id` يُسقط المفاتيح الأساسية كذلك.

           فيُنشأ الفهرسُ الناقص بدل تخطّي المفتاح: الفهرسُ إضافةٌ محضة — لا
           يغيّر قيمةً ولا يحذف صفّاً — وإنشاؤه يُصلح الجدول ويُتيح المفتاح
           معاً. وإن تعذّر، حينئذٍ فقط يُتخطّى المفتاح. */
        if (! self::referencedColumnsAreIndexed($on, $references) && ! self::createMissingIndex($on, $references)) {
            self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: «{$on}.".implode('،', $references).'» غير مفهرس وتعذّر فهرستُه.');

            return false;
        }

        // المفاتيح المركّبة تُترك كما هي: مواءمةُ أنواعها حالةٌ لم تقع هنا،
        // وتخمينُها أخطر من تركها لرسالة MySQL
        if (count($columns) !== 1 || count($references) !== 1) {
            return true;
        }

        /* عمودٌ يُنشأ الآن: لا وجود له في القاعدة ليُوائَم، فيُقارَن نوعُه
           **المقصود** بنوع المرجع. متوافقان ⇒ يمرّ؛ وإلّا تُخطّي المفتاح —
           ولا يُبدَّل شيء، فالعمود لم يوجد بعد أصلاً. */
        if (isset($pending[$columns[0]]) && self::column($table, $columns[0]) === null) {
            // عمودٌ يُنشأ الآن لا صفوفَ فيه، فلا يتيمَ يُفحص
            return self::pendingMatchesParent($table, $columns[0], $pending[$columns[0]], $on, $references[0]);
        }

        if (! self::alignTypes($table, $columns[0], $on, $references[0])) {
            return false;
        }

        return self::hasNoOrphans($table, $columns[0], $on, $references[0]);
    }

    /**
     * أفي العمود قيمٌ لا مقابلَ لها في الجدول المرجعي؟
     *
     * ── الخطأ الذي جاءت لأجله ──
     *
     *     1452 Cannot add or update a child row: a foreign key constraint
     *     fails … CONSTRAINT `credit_notifications_created_by_foreign`
     *
     * المفتاحُ هنا سليمُ التركيب: النوعان يجتمعان والمرجعُ مفهرس. الذي يمنعه
     * **البياناتُ نفسُها**: صفوفٌ في `created_by` تحمل مُعرّفَ مستخدمٍ لم يعد
     * في `users` — حُذف المستخدم يوم لم يكن ثمّة قيدٌ يمنع ذلك. فترفض MySQL
     * إضافةَ القيد لأن الموجودَ يخالفه أصلاً.
     *
     * ── ولماذا لا تُصلَّح البيانات؟ ──
     *
     * إصلاحُها هنا يعني كتابة `NULL` فوق قيمٍ في جدول العميل. وهي كتابةٌ
     * تبدو بريئة — القيد نفسُه `ON DELETE SET NULL` — لكنها تبقى **تعديلاً
     * لبياناتٍ مخزَّنة يفعله زرُّ تحديث**، وذلك ما لا يُفعل هنا بحال. فيُتخطّى
     * المفتاح، وتبقى الصفوف كما هي، ويُقال العدد والعمود في التقرير ليقرّر
     * صاحبُ النظام بنفسه.
     *
     * ── والفحص خفيف ──
     *
     * `EXISTS … LIMIT 1` لا `COUNT(*)`: السؤال «أثمّ يتيمٌ واحد؟» لا «كم».
     * والعدُّ لا يُطلب إلا في الحالة النادرة التي يوجد فيها، ولأجل الرسالة.
     */
    private static function hasNoOrphans(string $table, string $column, string $on, string $reference): bool
    {
        try {
            $orphan = DB::selectOne(
                'SELECT 1 AS found FROM `'.$table.'` AS c'
                .' LEFT JOIN `'.$on.'` AS p ON c.`'.$column.'` = p.`'.$reference.'`'
                .' WHERE c.`'.$column.'` IS NOT NULL AND p.`'.$reference.'` IS NULL LIMIT 1'
            );

            if ($orphan === null) {
                return true;
            }

            $count = DB::selectOne(
                'SELECT COUNT(*) AS c FROM `'.$table.'` AS c'
                .' LEFT JOIN `'.$on.'` AS p ON c.`'.$column.'` = p.`'.$reference.'`'
                .' WHERE c.`'.$column.'` IS NOT NULL AND p.`'.$reference.'` IS NULL'
            );

            $rows = $count === null ? '?' : number_format((int) $count->c);

            self::note(
                "تُخطّي مفتاحٌ أجنبيٌّ «{$table}.{$column}» ← «{$on}.{$reference}»: "
                ."{$rows} صفّاً يشير إلى قيمٍ لم تعد موجودة في «{$on}» — "
                .'البياناتُ باقيةٌ كما هي ولم يُنشأ القيد. '
                .'نظّف هذه الصفوف بنفسك ثمّ أعد التحديث إن أردت القيد.'
            );

            return false;
        } catch (Throwable $e) {
            // استعلامٌ سقط (جدولٌ ضخم، أو صلاحيةٌ ناقصة): لا يُمنع المفتاح
            // بسببه — تُترك المحاولة لـMySQL ورسالتُها أصدق من تخميننا
            return true;
        }
    }

    /**
     * فهرسةُ العمود المرجعي حين يكون بلا فهرس.
     *
     * الفهرسُ إضافةٌ محضة: لا يغيّر قيمةً ولا يحذف صفّاً. ويُنشأ غيرَ فريد
     * عمداً — الفريدُ يسقط على تكرارٍ قائم، وInnoDB يقبل الفهرس العادي مرجعاً
     * للمفتاح الأجنبي. والغرضُ إتمامُ التحديث لا إعادةُ تصميم الجدول.
     *
     * @param  array<int, string>  $references
     */
    private static function createMissingIndex(string $table, array $references): bool
    {
        $name = substr($table.'_'.implode('_', $references).'_fkidx', 0, 64);

        try {
            if (self::indexExists($table, $name)) {
                return true;
            }

            // مفتاحٌ أساسيٌّ إن أمكن — وهو الأصل الذي فُقد لا فهرسٌ نُضيفه
            if (self::restorePrimaryKey($table, $references)) {
                return true;
            }

            $columns = '`'.implode('`, `', $references).'`';
            DB::statement('CREATE INDEX `'.$name.'` ON `'.$table.'` ('.$columns.')');

            self::note("أُنشئ الفهرس «{$name}» على «{$table}» — كان العمود المرجعي بلا فهرس فيتعذّر أي مفتاحٍ يشير إليه.");

            return true;
        } catch (Throwable $e) {
            Log::warning('SchemaReconciler: تعذّرت فهرسة العمود المرجعي', [
                'table' => $table,
                'columns' => $references,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * أيوافق النوعُ المقصود لعمودٍ قيد الإنشاء نوعَ العمود المرجعي؟
     *
     * المقارنة على الأساس والإشارة (وطولِ النصّ إن كان نصّاً) — لا على النصّ
     * الكامل: `bigint` من الـBlueprint و`bigint(20) unsigned` من الكتالوج
     * شيءٌ واحد.
     *
     * @param  array{type: string, unsigned: bool}  $pending
     */
    private static function pendingMatchesParent(string $table, string $column, array $pending, string $on, string $reference): bool
    {
        $parent = self::column($on, $reference);

        if ($parent === null || $pending['type'] === '') {
            return true; // لا معرفةَ كافية للحكم: تُترك الرسالة لـMySQL
        }

        $childBase = self::baseType($pending['type']);
        $parentBase = self::baseType($parent->column_type);
        $parentUnsigned = str_contains(strtolower((string) $parent->column_type), 'unsigned');

        $compatible = $childBase === $parentBase && $pending['unsigned'] === $parentUnsigned;

        // النصوصُ يشترط فيها الطولُ أيضاً
        if ($compatible && in_array($childBase, ['char', 'varchar'], true)) {
            $compatible = self::length($pending['type']) === self::length((string) $parent->column_type);
        }

        if ($compatible) {
            return true;
        }

        self::note(
            "تُخطّي مفتاحٌ أجنبيٌّ «{$table}.{$column}» ← «{$on}.{$reference}»: "
            .'العمود يُنشأ الآن بنوع '.$pending['type'].($pending['unsigned'] ? ' unsigned' : '')
            ." والمرجع {$parent->column_type} — لا يجتمعان."
        );

        return false;
    }

    /** الطولُ بين القوسين، و`null` إن لم يُذكر. */
    private static function length(string $type): ?int
    {
        return preg_match('/\((\d+)\)/', $type, $m) === 1 ? (int) $m[1] : null;
    }

    /** مواءمةُ نوع العمود التابع مع المرجعي — أو تخطّي المفتاح. */
    private static function alignTypes(string $table, string $column, string $on, string $reference): bool
    {
        $child = self::column($table, $column);
        $parent = self::column($on, $reference);

        if ($child === null || $parent === null) {
            return false;
        }

        $sameType = strcasecmp((string) $child->column_type, (string) $parent->column_type) === 0;
        $sameCollation = strcasecmp((string) $child->collation, (string) $parent->collation) === 0;

        if ($sameType && $sameCollation) {
            return true; // متوافقان أصلاً
        }

        $childBase = self::baseType($child->column_type);
        $parentBase = self::baseType($parent->column_type);

        // ── أعدادٌ صحيحة: تُوائَم بعد التحقّق من اتّساع القيم ──
        if (isset(self::INT_RANK[$childBase], self::INT_RANK[$parentBase])) {
            if (! self::valuesFit($table, $column, (string) $parent->column_type)) {
                self::note(
                    "تُخطّي مفتاحٌ أجنبيٌّ «{$table}.{$column}» ← «{$on}.{$reference}»: "
                    ."النوعان ({$child->column_type} مقابل {$parent->column_type}) لا يجتمعان، "
                    .'ومواءمتهما تقصّ قيماً موجودة — تُركت البيانات ولم يُنشأ المفتاح.'
                );

                return false;
            }

            return self::modify($table, $column, $child, (string) $parent->column_type, null, $on, $reference);
        }

        // ── ما ليس عدداً: فرقُ المقارنة وحده لا يمسّ القيم ──
        if ($sameType && ! $sameCollation) {
            return self::modify($table, $column, $child, (string) $child->column_type, (string) $parent->collation, $on, $reference);
        }

        // ── نوعان مختلفان وليسا عددين: لا يُبدَّل إلا على جدولٍ فارغ ──
        if (self::rowCount($table) === 0) {
            return self::modify($table, $column, $child, (string) $parent->column_type, (string) $parent->collation, $on, $reference);
        }

        self::note(
            "تُخطّي مفتاحٌ أجنبيٌّ «{$table}.{$column}» ← «{$on}.{$reference}»: "
            ."النوعان ({$child->column_type} مقابل {$parent->column_type}) لا يجتمعان، "
            .'والجدول ليس فارغاً فلم يُبدَّل نوعُ العمود.'
        );

        return false;
    }

    /**
     * تنفيذُ `MODIFY` على العمود التابع، مع حفظ خصائصه الأخرى.
     *
     * والفشلُ لا يصعد استثناءً: يُسجَّل ويُتخطّى المفتاح — أهونُ من ترحيلٍ يسقط.
     */
    private static function modify(string $table, string $column, object $child, string $type, ?string $collation, string $on, string $reference): bool
    {
        $sql = 'ALTER TABLE `'.$table.'` MODIFY `'.$column.'` '.$type;

        if ($collation !== null && $collation !== '') {
            $sql .= ' COLLATE '.$collation;
        }

        $sql .= $child->is_nullable === 'YES' ? ' NULL' : ' NOT NULL';

        if ($child->column_default !== null) {
            $sql .= ' DEFAULT '.self::defaultLiteral((string) $child->column_default);
        } elseif ($child->is_nullable === 'YES') {
            $sql .= ' DEFAULT NULL';
        }

        if ((string) $child->column_comment !== '') {
            $sql .= " COMMENT '".str_replace("'", "''", (string) $child->column_comment)."'";
        }

        try {
            DB::statement($sql);
            self::note("وُوئم «{$table}.{$column}» ({$child->column_type} ← {$type}) ليصحّ المفتاح إلى «{$on}.{$reference}».");

            return true;
        } catch (Throwable $e) {
            self::note(
                "تُخطّي مفتاحٌ أجنبيٌّ «{$table}.{$column}» ← «{$on}.{$reference}»: "
                .'تعذّرت مواءمة النوع — '.$e->getMessage()
            );

            return false;
        }
    }

    // ==================== أدوات ====================

    /** أتتّسع قيمُ العمود للنوع الجديد؟ — جدولٌ فارغٌ يتّسع دائماً. */
    private static function valuesFit(string $table, string $column, string $targetType): bool
    {
        $base = self::baseType($targetType);
        $unsigned = str_contains(strtolower($targetType), 'unsigned');

        // bigint بإشارة يسع كلَّ ما دونه؛ ولا داعي لسؤال الجدول
        if ($base === 'bigint' && ! $unsigned) {
            return true;
        }

        $row = DB::selectOne('SELECT MIN(`'.$column.'`) AS lo, MAX(`'.$column.'`) AS hi FROM `'.$table.'`');

        if ($row === null || ($row->hi === null && $row->lo === null)) {
            return true; // لا صفوف، أو كلُّها فارغة
        }

        if ($unsigned && $row->lo !== null && (float) $row->lo < 0) {
            return false; // قيمٌ سالبة لا يسعها نوعٌ موجَب
        }

        if ($base === 'bigint') {
            return true;
        }

        $range = self::INT_RANGE[$base] ?? null;

        if ($range === null) {
            return false;
        }

        $max = $unsigned ? $range[0] : $range[1];
        $min = $unsigned ? 0 : -$range[1] - 1;

        return (float) ($row->hi ?? 0) <= $max && (float) ($row->lo ?? 0) >= $min;
    }

    /** أوّلُ الأعمدة في فهرسٍ ما؟ — شرطُ MySQL للعمود المرجعي. */
    private static function referencedColumnsAreIndexed(string $table, array $references): bool
    {
        $rows = DB::select(
            'SELECT INDEX_NAME AS idx, COLUMN_NAME AS col, SEQ_IN_INDEX AS seq
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
              ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );

        $indexes = [];

        foreach ($rows as $row) {
            $indexes[$row->idx][(int) $row->seq] = $row->col;
        }

        foreach ($indexes as $columns) {
            ksort($columns);
            $prefix = array_slice(array_values($columns), 0, count($references));

            if (array_map('strtolower', $prefix) === array_map('strtolower', $references)) {
                return true;
            }
        }

        return false;
    }

    /**
     * إعادةُ المفتاح الأساسي المفقود إلى جدولٍ فقده في الاستيراد.
     *
     * ── لماذا هذا موجود ──
     *
     * تقريرُ التحديث على قاعدةٍ حقيقية قال: «`clients.id` غير مفهرس»، وكذلك
     * `employees` و`invoices` و`accounts` و`branches`… ثلاثون جدولاً. وجدولٌ
     * بلا مفتاحٍ أساسيٍّ على `id` ليس تصميماً: هو أثرُ ملفِّ طرحٍ (dump) أسقط
     * المفاتيح — وهو الملفُّ نفسُه الذي أسقط `AUTO_INCREMENT` عن
     * `migrations.id` (انظر [\App\Support\MigrationLedger]).
     *
     * وأثرُه لا يقف عند المفاتيح الأجنبية: عمودُ `id` بلا `AUTO_INCREMENT`
     * يعني أنّ **كلَّ إدراجٍ جديد** في ذلك الجدول يسقط بالخطأ 1364 — لا
     * يُنشأ عميلٌ ولا فاتورة. فإعادةُ المفتاح إصلاحٌ للنظام لا تمهيدٌ لقيد.
     *
     * ── وحدودُه ──
     *
     * لا يُعاد إلا حين تكون القيم **صالحةً له أصلاً**: عمودٌ واحد، بلا فراغٍ
     * ولا صفرٍ ولا تكرار. فلا صفٌّ يُمَسّ ولا معرّفٌ يتبدّل — والمعرّفات
     * مشارٌ إليها من جداولَ أخرى، فتبديلُها يقطع الروابط. وما لم تصلح قيمُه
     * يُكتفى له بفهرسٍ عادي.
     *
     * و`AUTO_INCREMENT` تُضاف بعدها لعمود `id` الصحيح وحده، وMySQL تبدأ
     * العدّ تلقائياً من أكبر معرّفٍ + ١.
     *
     * @param  array<int, string>  $references
     * @return bool هل أُعيد المفتاح الأساسي؟
     */
    private static function restorePrimaryKey(string $table, array $references): bool
    {
        if (count($references) !== 1) {
            return false;
        }

        $column = $references[0];

        try {
            if (self::indexExists($table, 'PRIMARY')) {
                return false; // له مفتاحٌ على عمودٍ آخر: لا يُنازَع
            }

            $row = DB::selectOne(
                'SELECT COUNT(*) AS total, COUNT(DISTINCT `'.$column.'`) AS uniques,'
                .' SUM(CASE WHEN `'.$column.'` IS NULL OR `'.$column.'` = 0 THEN 1 ELSE 0 END) AS blanks'
                .' FROM `'.$table.'`'
            );

            if ($row === null) {
                return false;
            }

            // قيمٌ مكرّرةٌ أو فارغة: لا يصلح مفتاحاً، ولا تُصلَّح البيانات لأجله
            if ((int) $row->total !== (int) $row->uniques || (int) $row->blanks > 0) {
                return false;
            }

            DB::statement('ALTER TABLE `'.$table.'` ADD PRIMARY KEY (`'.$column.'`)');

            $note = 'أُعيد المفتاح الأساسي على «'.$table.'.'.$column.'» — كان مفقوداً من القاعدة';

            $definition = self::column($table, $column);
            $base = $definition === null ? '' : self::baseType($definition->column_type);

            if ($column === 'id' && isset(self::INT_RANK[$base])
                && ! str_contains(strtolower((string) $definition->extra), 'auto_increment')) {
                DB::statement('ALTER TABLE `'.$table.'` MODIFY `'.$column.'` '
                    .$definition->column_type.' NOT NULL AUTO_INCREMENT');

                $note .= '، وأُعيد الترقيم التلقائي معه — بدونه يسقط كلُّ إدراجٍ جديد بالخطأ 1364';
            }

            self::note($note.'. لم يتبدّل أيُّ معرّف.');

            return true;
        } catch (Throwable $e) {
            Log::warning('SchemaReconciler: تعذّرت إعادة المفتاح الأساسي', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** محرّكُ الجدول، و`null` إن تعذّرت معرفتُه. */
    private static function engineOf(string $table): ?string
    {
        try {
            $row = DB::selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );

            return $row === null || $row->engine === null ? null : (string) $row->engine;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * تنفيذُ أمر المفتاح الأجنبي بنفسنا، فلا يُسقط الترحيلَ إن سقط.
     *
     * ── لماذا هنا لا في `Blueprint` ──
     *
     * كلُّ الفحوص قبله تمنع ما نعرفه: نوعان لا يجتمعان، مرجعٌ بلا فهرس،
     * صفوفٌ يتيمة، محرّكٌ لا يدعم. ويبقى **ما لا نعرفه** — وقاعدةٌ مضى عليها
     * سنواتٌ من الترقيع تُخرج ما لم يخطر. فإن سقط الأمر بعد كل ذلك، سقط
     * الترحيلُ كلُّه ووقف التحديث، وعُدنا إلى حيث بدأنا.
     *
     * فيُنفَّذ هنا داخل `try`: نجح ⇒ قيدٌ زائد، وسقط ⇒ سطرٌ في التقرير. وفي
     * الحالتين يمضي الترحيل.
     *
     * ── وشرطُ استعماله ──
     *
     * لا يُستعمل إلا حين يكون العمود قائماً في القاعدة فعلاً. فـ`toSql()`
     * تُصرّف الأوامر كلَّها قبل أن تُنفّذ أوّلها، فتنفيذُنا هنا يسبق إنشاءَ
     * أعمدةِ الـBlueprint نفسِه — والمفتاحُ على عمودٍ يُنشأ الآن يجب أن يُترك
     * في مجراه الطبيعي بعد العمود. والحالةُ التي تهمّ — سلسلة
     * `add_foreign_keys_to_*` على جداولَ قائمة — هي هذه بعينها.
     *
     * @return bool أنُفِّذ هنا؟ (`false` ⇒ يُترك للـBlueprint)
     */
    public static function runForeignKey(string $sql, string $table, bool $creating, array $columns, array $pending): bool
    {
        if (! self::$active || $creating) {
            return false;
        }

        foreach ($columns as $column) {
            // عمودٌ يُنشأ في هذا الـBlueprint: الأمرُ يجب أن يأتي بعده
            if (! is_string($column) || isset($pending[$column])) {
                return false;
            }
        }

        try {
            DB::statement($sql);
        } catch (Throwable $e) {
            self::note("تُخطّي مفتاحٌ أجنبيٌّ على «{$table}»: رفضته القاعدة — "
                .preg_replace('/\s+/', ' ', $e->getMessage()));
        }

        return true;
    }

    /** أفهرسٌ بهذا الاسم موجود؟ (و`PRIMARY` اسمُ المفتاح الأساسي في MySQL) */
    private static function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return $row !== null && (int) $row->c > 0;
    }

    /** أقيدٌ أجنبيٌّ بهذا الاسم موجود؟ */
    private static function constraintExists(string $table, string $constraint): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        return $row !== null && (int) $row->c > 0;
    }

    /**
     * وصفُ عمودٍ من كتالوج القاعدة — يُسأل في كل مرّة، بلا ذاكرة.
     *
     * كانت هنا ذاكرةٌ للتشغيلة الواحدة فأنتجت عطلاً حقيقياً: الترحيلُ الذي
     * يُنشئ `client_relations.client_employee_id` سأل عنه **قبل** إنشائه
     * (التصريفُ يسبق التنفيذ) فخُزّن الجواب «غير موجود»، ثمّ جاء ترحيلٌ
     * لاحقٌ يُعيد توجيه مفتاحه فقرأ الجواب القديم فتُخطّي المفتاح بلا سبب.
     *
     * والمخطَّطُ يتبدّل تحت أقدامنا بطبيعته هنا — كلُّ ترحيلٍ يغيّره — فذاكرةٌ
     * تصفُه ذاكرةٌ تكذب. وثمنُ السؤال استعلامُ كتالوجٍ لا يُقاس بجانب ترحيلٍ
     * كامل.
     */
    private static function column(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable,
                    COLUMN_DEFAULT AS column_default, COLUMN_COMMENT AS column_comment,
                    COLLATION_NAME AS collation, EXTRA AS extra
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    /** `bigint(20) unsigned` ⇦ `bigint` */
    private static function baseType(?string $type): string
    {
        return preg_match('/^([a-z]+)/i', (string) $type, $m) === 1 ? strtolower($m[1]) : '';
    }

    private static function defaultLiteral(string $default): string
    {
        if ($default === '') {
            return "''";
        }

        if (is_numeric($default)) {
            return $default;
        }

        if (in_array(strtoupper($default), ['CURRENT_TIMESTAMP', 'NULL'], true)) {
            return strtoupper($default);
        }

        return "'".str_replace("'", "''", $default)."'";
    }

    private static function rowCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable $e) {
            return -1;
        }
    }

    private static function note(string $message): void
    {
        self::$notes[] = $message;
        Log::info('SchemaReconciler: '.$message);
    }
}
