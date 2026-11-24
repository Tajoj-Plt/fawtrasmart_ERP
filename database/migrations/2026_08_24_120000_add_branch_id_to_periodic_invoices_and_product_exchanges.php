<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الفواتير الدورية وعمليات تبديل المنتجات بالفرع.
 *
 * نفس ما أصلحته `2026_08_22_120000_add_branch_id_to_sales_documents` للفواتير
 * والمرتجعات وعروض الأسعار والإشعارات الدائنة ومدفوعات العملاء — تُكمِله هنا
 * لآخر شاشتين بلا عمود فرع في وحدة المبيعات. كانتا تصفّيان «الفرع» (حين
 * تصفّيان أصلاً) عبر فرع **مُنشئ** المستند الحاليّ لا الفرع الذي حُرِّر فيه
 * المستند فعلاً — فمَن بدَّل فرعه من الهيدر انتقل معه تاريخُه كلّه، و
 * `ProductExchangeController::index()` لم تكن تصفّي فرعاً إطلاقاً.
 *
 * العمود هنا يُجمِّد الفرع مع المستند وقت إنشائه، كما تفعل بقيّة مستندات
 * المبيعات.
 */
return new class extends Migration
{
    /** الجداول المعنيّة: العمود الذي يسبقه `branch_id` في كل جدول. */
    private const TABLES = [
        'periodic_invoices' => 'client_id',
        'product_exchanges' => 'client_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $after) {
                $column = $blueprint->unsignedBigInteger('branch_id')->nullable();

                if (Schema::hasColumn($table, $after)) {
                    $column->after($after);
                }

                $blueprint->index('branch_id', $table.'_branch_id_index');
            });

            $this->backfill($table);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_branch_id_index');
                $blueprint->dropColumn('branch_id');
            });
        }
    }

    /**
     * تعبئة الصفوف القديمة: فرع مُنشئ المستند أوّلاً، وإلّا فرع العميل.
     *
     * `update … join` ليس محمولاً بين MySQL وSQLite (وملفّ الاختبارات يعمل
     * على SQLite في الذاكرة)، فالتعبئة تمرّ على الفروع فرعاً فرعاً.
     */
    private function backfill(string $table): void
    {
        if (Schema::hasColumn($table, 'created_by') && Schema::hasTable('users')) {
            foreach ($this->branchGroups('users', 'id') as $branchId => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table($table)
                        ->whereNull('branch_id')
                        ->whereIn('created_by', $chunk)
                        ->update(['branch_id' => $branchId]);
                }
            }
        }

        if (Schema::hasColumn($table, 'client_id') && Schema::hasTable('clients')) {
            foreach ($this->branchGroups('clients', 'id') as $branchId => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table($table)
                        ->whereNull('branch_id')
                        ->whereIn('client_id', $chunk)
                        ->update(['branch_id' => $branchId]);
                }
            }
        }
    }

    /**
     * معرّفات صفوف الجدول مجموعةً بفرعها.
     *
     * @return array<int, list<int>>
     */
    private function branchGroups(string $table, string $key): array
    {
        if (! Schema::hasColumn($table, 'branch_id')) {
            return [];
        }

        $groups = [];

        DB::table($table)
            ->select($key, 'branch_id')
            ->whereNotNull('branch_id')
            ->orderBy($key)
            ->chunk(2000, function ($rows) use (&$groups, $key) {
                foreach ($rows as $row) {
                    $groups[(int) $row->branch_id][] = (int) $row->{$key};
                }
            });

        return $groups;
    }
};
