<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
    ختمُ الفترة في الحجوزات ودفعات العقود وسجلّ تدقيق نقاط البيع — مفتاحٌ أجنبيّ.

    تتمّةُ `2026_10_01_100000`: الجداولُ الثلاثة تحمل العمودَ بلا مفتاحٍ ولا فهرس،
    والمفتاحُ يُنشئ الفهرسَ معه. وتُختم الآن من مودلاتها (`BelongsToAccountingPeriod`).

    القيودُ والسنداتُ والأذونُ والشيكات يربطها `accounting_period_id` بمفتاحٍ
    أجنبيّ إلى `accounting_periods` (حذفُ الفترة يُفرغ الختم). وهذه الجداولُ
    تحمل العمودَ نفسه وتُختم به (`BelongsToAccountingPeriod`) بلا مفتاح: فلا
    يمنع شيءٌ أن يشير ختمُ فاتورةٍ إلى فترةٍ حُذفت، فتخرج من قوائم سنتها ولا
    يربطها «ربط العمليات غير المصنَّفة» لأنها ليست فارغة.

    يُفرَغ أوّلاً كلُّ ختمٍ يشير إلى فترةٍ غير موجودة، ثم يُضاف المفتاح بالقاعدة
    نفسها التي على بقيّة الجداول (`SET NULL`).
*/
return new class extends Migration
{
    private const TABLES = [
        'reservations',
        're_payments',
        'pos_audit_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id') || $this->hasForeignKey($table)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('accounting_period_id')
                ->whereNotIn('accounting_period_id', DB::table('accounting_periods')->select('id'))
                ->update(['accounting_period_id' => null]);

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreign('accounting_period_id', "{$table}_accounting_period_id_foreign")
                    ->references('id')->on('accounting_periods')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && $this->hasForeignKey($table)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign("{$table}_accounting_period_id_foreign"));
            }
        }
    }

    private function hasForeignKey(string $table): bool
    {
        // `information_schema` خاصٌّ بـ MySQL — وعلى SQLite (قاعدة الاختبار
        // والتطوير المحلّي) كان الاستعلام يُسقط `migrate` كلّه
        if (DB::getDriverName() !== 'mysql') {
            return collect(Schema::getForeignKeys($table))
                ->contains(fn (array $key) => in_array('accounting_period_id', $key['columns'], true));
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'accounting_period_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
