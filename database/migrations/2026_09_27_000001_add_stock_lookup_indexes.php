<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
    فهارس شاشات المخزون — أعمدةٌ تُسأل في كل فتحٍ لمستودع ولا فهرس عليها.
    في الموقع وتطبيق الجوال معاً:

      • `logs.type+type_id` — سجلّ المستودع في صفحة تفاصيله (وسجلّ الإذن
        المخزني): مسحٌ كاملٌ لجدول السجلّات في كل فتح، ~٢ ثانية.
      • `warehouse_permits.store_houses_id+status` و`from_…`/`to_…` — حركات
        المستودع المعتمدة، ومتوسط تكلفة الصنف فيه، والكشف المخزني.

    كل فهرسٍ مشروطٌ بوجود أعمدته وغياب اسمه — قواعدُ المنشآت ليست متطابقة.
*/
return new class extends Migration
{
    /** @var array<string, array<int, array<int, string>>> */
    private array $indexes = [
        'logs' => [['type', 'type_id']],
        'warehouse_permits' => [['store_houses_id', 'status'], ['from_store_houses_id'], ['to_store_houses_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $name = $table.'_'.implode('_', $columns).'_index';

                    if (Schema::hasColumns($table, $columns) && ! Schema::hasIndex($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $name = $table.'_'.implode('_', $columns).'_index';

                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
