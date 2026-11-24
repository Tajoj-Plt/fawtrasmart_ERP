<?php

namespace App\Support\Migration;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * اعتراضُ دوالّ `compileX()` — اللحظةُ الوحيدة التي يُعرف فيها ما سيُطلب من
 * القاعدة **قبل** إرساله إليها.
 *
 * وإرجاعُ `null` هنا ليس حيلة: `Blueprint::toSql()` تتخطّى كلَّ أمرٍ ترجع
 * نحويّتُه `null`، فلا يُصدَر الأمر أصلاً ولا يقع استثناء. أمّا `try/catch`
 * حول `$table->foreign(...)` في الترحيل فلا يلتقط شيئاً: الـBlueprint يصفّ
 * الأمر ولا ينفّذه إلا بعد انتهاء الإغلاق.
 *
 * ── فخُّ «الجدول لم يُنشأ بعد» ──
 *
 * `toSql()` تُصرّف **أوامر المخطَّط كلَّها** ثمّ تُنفّذها واحداً واحداً. فحين
 * يُصرَّف المفتاح الأجنبي المكتوب داخل `Schema::create(...)` لم يكن الجدولُ
 * قد أُنشئ بعد، و`Schema::hasTable()` تقول «غير موجود». ولولا الانتباه لذلك
 * لتُخطّي كلُّ مفتاحٍ في كلّ هجرة إنشاء — وهو ما وقع في أوّل تجربة.
 *
 * فيُقرأ الـBlueprint نفسُه: أيُنشئ جدولاً؟ وأيَّ أعمدةٍ سيحمل؟ وتُمرَّر هذه
 * المعرفة إلى [SchemaReconciler] ليحكم على ما **سيكون**، لا على ما هو كائن.
 *
 * @see \App\Support\Migration\SchemaReconciler
 */
trait ReconcilesSchema
{
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return SchemaReconciler::allowsCreate($blueprint->getTable())
            ? parent::compileCreate($blueprint, $command, $connection)
            : null;
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsAdd($blueprint->getTable(), $this->columnName($command))
            ? parent::compileAdd($blueprint, $command)
            : null;
    }

    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return SchemaReconciler::allowsChange($blueprint->getTable(), $this->columnName($command))
            ? parent::compileChange($blueprint, $command, $connection)
            : null;
    }

    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        // داخل إنشاءِ جدولٍ لا معنى للفحص: الجدول لم يوجد بعد
        return $blueprint->creating() || SchemaReconciler::allowsPrimary($blueprint->getTable())
            ? parent::compilePrimary($blueprint, $command)
            : null;
    }

    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->allowsKey($blueprint, $command)
            ? parent::compileUnique($blueprint, $command)
            : null;
    }

    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->allowsKey($blueprint, $command)
            ? parent::compileIndex($blueprint, $command)
            : null;
    }

    public function compileFullText(Blueprint $blueprint, Fluent $command)
    {
        return $this->allowsKey($blueprint, $command)
            ? parent::compileFullText($blueprint, $command)
            : null;
    }

    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->allowsKey($blueprint, $command)
            ? parent::compileSpatialIndex($blueprint, $command)
            : null;
    }

    /**
     * حذفُ عمود — لا يقع أبداً أثناء التحديث.
     *
     * قرارُ صاحب النظام: «ما ودّي يحذف أي شي إلا إذا هو مكرر». وترحيلٌ مثل
     * `2025_11_29_remove_opening_balance_from_clients_table` يُسقط عمودين في
     * جدولٍ فيه ٣٬٨٥٤ صفّاً — لا لأنهما مكرّران بل لأن الميزة انتقلت. فيبقى
     * العمودان مكانَهما، ويمضي الترحيل.
     *
     * وعمودٌ متروكٌ لا يكلّف شيئاً: لا يقرؤه كودٌ بعد أن نُقلت الميزة، ولا
     * يظهر في شاشة. أمّا حذفُه فيمحو ما فيه بلا رجعة — أو يوقف التحديثَ كلَّه
     * عند حارس [\App\Services\Maintenance\MigrationSafetyScanner]، وهو ما كان
     * يقع فعلاً: يقف عند أوّل حذفٍ فلا ينزل ما بعده أبداً.
     *
     * **إلا عموداً لا قيمةَ فيه**: حذفُه لا يُفقد شيئاً، فيمضي كما كُتب.
     * وبهذا يخرج التركيبُ الجديد بالمخطَّط المقصود حرفياً — جداولُه فارغة —
     * ولا يُثقَل بأعمدةٍ بقيت بلا سبب.
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsDropColumn($blueprint->getTable(), (array) $command->columns)
            ? parent::compileDropColumn($blueprint, $command)
            : null;
    }

    /**
     * حذفُ جدول — يُستبدل بإحالته إلى التقاعد.
     *
     * الفارغُ يُسقَط، وما فيه صفوفٌ يُعاد تسميته `retired_<الاسم>` فتبقى
     * بياناته ويتحرّر الاسمُ لمن يُنشئه بشكلٍ جديد. يُنفَّذ هنا في التصريف
     * — كما تُنفَّذ مواءمةُ الأنواع — ثمّ يُرجَع `null` فلا يُصدَر أمرُ الحذف.
     *
     * @see \App\Support\SchemaRetire
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        SchemaReconciler::retireTable($blueprint->getTable());

        return null;
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        SchemaReconciler::retireTable($blueprint->getTable());

        return null;
    }

    /** إعادةُ تسميةِ عمودٍ غير موجود، أو إلى اسمٍ مشغول: كلاهما يسقط. */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $from = is_string($command->from ?? null) ? $command->from : null;
        $to = is_string($command->to ?? null) ? $command->to : null;

        return SchemaReconciler::allowsRenameColumn($blueprint->getTable(), $from, $to)
            ? parent::compileRenameColumn($blueprint, $command, $connection)
            : null;
    }

    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsDropPrimary($blueprint->getTable())
            ? parent::compileDropPrimary($blueprint, $command)
            : null;
    }

    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsDropIndex($blueprint->getTable(), $this->indexName($command))
            ? parent::compileDropUnique($blueprint, $command)
            : null;
    }

    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsDropIndex($blueprint->getTable(), $this->indexName($command))
            ? parent::compileDropIndex($blueprint, $command)
            : null;
    }

    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return SchemaReconciler::allowsDropForeign($blueprint->getTable(), $this->indexName($command))
            ? parent::compileDropForeign($blueprint, $command)
            : null;
    }

    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        $pending = $this->pendingColumns($blueprint);

        $allowed = SchemaReconciler::allowsForeign(
            $blueprint->getTable(),
            (array) $command->columns,
            $command->on === null ? null : (string) $command->on,
            (array) $command->references,
            $this->indexName($command),
            $blueprint->creating(),
            $pending
        );

        if (! $allowed) {
            return null;
        }

        $sql = parent::compileForeign($blueprint, $command);

        /* على جدولٍ قائمٍ وأعمدةٍ قائمة: يُنفَّذ الأمر هنا داخل `try` فلا
           يُسقط الترحيلَ إن رفضته القاعدة لسببٍ لم تفطن له الفحوص. وما عدا
           ذلك يُترك للـBlueprint في مجراه الطبيعي — انظر
           [SchemaReconciler::runForeignKey()]. */
        $ran = SchemaReconciler::runForeignKey(
            (string) $sql,
            $blueprint->getTable(),
            $blueprint->creating(),
            (array) $command->columns,
            $pending
        );

        return $ran ? null : $sql;
    }

    /**
     * الأعمدة التي سيحملها هذا الـBlueprint حين يُنفَّذ — باسمها ونوعها
     * المقصود، لا كما هي في القاعدة الآن.
     *
     * تُقرأ لحالةٍ واحدة: مفتاحٌ أجنبيٌّ على عمودٍ يُنشأ في هذه اللحظة نفسها،
     * فلا يجوز سؤالُ القاعدة عنه — ولا الحكمُ عليه بالغياب.
     *
     * @return array<string, array{type: string, unsigned: bool}>
     */
    private function pendingColumns(Blueprint $blueprint): array
    {
        $pending = [];

        foreach ($blueprint->getColumns() as $column) {
            if (! is_string($column->name ?? null)) {
                continue;
            }

            try {
                $type = $this->getType($column);
            } catch (\Throwable $e) {
                // نوعٌ لا يُصرَّف وحدَه (`computed` مثلاً): يُترك بلا نوع،
                // فيُعامَل المفتاح معاملةَ ما لا يُعرف — أي يمرّ كما كُتب
                $type = '';
            }

            $pending[$column->name] = [
                'type' => (string) $type,
                'unsigned' => (bool) ($column->unsigned ?? false),
            ];
        }

        return $pending;
    }

    /** فهرسٌ داخل إنشاءِ جدولٍ لا يُفحص: الجدول لم يوجد بعد. */
    private function allowsKey(Blueprint $blueprint, Fluent $command): bool
    {
        return $blueprint->creating()
            || SchemaReconciler::allowsIndex($blueprint->getTable(), $this->indexName($command));
    }

    private function indexName(Fluent $command): ?string
    {
        return is_string($command->index ?? null) ? $command->index : null;
    }

    private function columnName(Fluent $command): ?string
    {
        $column = $command->column ?? null;

        if ($column === null) {
            return null;
        }

        return is_string($column->name ?? null) ? $column->name : null;
    }
}
