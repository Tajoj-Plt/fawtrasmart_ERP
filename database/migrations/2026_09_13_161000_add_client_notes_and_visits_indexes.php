<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارسُ ملاحظات العملاء وزياراتهم الميدانية.
 *
 * `client_relations` (نحو 47 ألف ملاحظة) بلا فهرسٍ على العميل، و`visits` بلا فهرسٍ
 * على العميل ولا المندوب. فـ«آخر ملاحظات العميل» — في ملاحظات اليوم وشاشة العميل
 * و`VisitNoteCards` — مسحٌ كامل لكل عميل: نحو ثلث ثانية للعميل، و15 ثانية ليوم مندوبٍ
 * فيه خمسون عميلاً.
 *
 * كلُّ فهرسٍ محروسٌ بـ«إن لم يكن موجوداً» — قواعدُ الإنتاج تسبق مجموعةَ الترحيلات.
 */
return new class extends Migration
{
    private const INDEXES = [
        'client_relations' => [
            'cr_client_created_index' => ['client_id', 'created_at'],
            'cr_employee_created_index' => ['employee_id', 'created_at'],
        ],
        'visits' => [
            'visits_client_started_index' => ['client_id', 'started_at'],
            'visits_employee_date_index' => ['employee_id', 'visit_date'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (SchemaGuard::hasIndex($table, $name)) {
                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue 2;
                    }
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (SchemaGuard::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
