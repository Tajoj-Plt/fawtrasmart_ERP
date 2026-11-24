<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط مستندات المبيعات بالفرع.
 *
 * الشاشات الخمس — المبيعات والمرتجع وعروض الأسعار والإشعارات الدائنة
 * ومدفوعات العملاء — كانت تصفّي «الفرع» عبر فرع **مُنشئ** المستند:
 *
 *     $query->whereHas('createdByUser', fn ($q) => $q->where('branch_id', $id));
 *
 * وهذا يقرأ الفرع **الحاليّ** للموظّف لا الفرع الذي حُرِّر فيه المستند.
 * فمَن بدَّل فرعه من الهيدر انتقل معه تاريخُه كلّه إلى الفرع الجديد،
 * واختفت فواتيرُه من الفرع الذي أصدرها. ومدفوعات العملاء لم يكن فيها
 * تصفيةُ فرعٍ أصلاً.
 *
 * العمود هنا يُجمِّد الفرع مع المستند وقت إنشائه، كما يفعل `supply_orders`.
 *
 * التعبئة الرجعية بأفضل المتاح للصفوف القديمة: فرع المُنشئ أوّلاً، ثم فرع
 * العميل. وما بقي `null` يظهر في كل الفروع — سابقٌ للعمود لا مخفيّ.
 */
return new class extends Migration
{
    /** الجداول المعنيّة: العمود الذي يسبقه `branch_id` في كل جدول. */
    private const TABLES = [
        'invoices' => 'client_id',
        'quotes' => 'client_id',
        'credit_notifications' => 'client_id',
        'payments_process' => 'client_id',
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
     * تعبئة الصفوف القديمة.
     *
     * `update … join` ليس محمولاً بين MySQL وSQLite (وملفّ الاختبارات
     * يعمل على SQLite في الذاكرة)، فالتعبئة تمرّ على الفروع فرعاً فرعاً:
     * عددُها عشرات لا آلاف، والاستعلام يبقى `where … in (…)` عادياً.
     */
    private function backfill(string $table): void
    {
        // ① فرع الفاتورة التي صدر عنها المستند — أدقُّ ما يُتاح للسندات
        //    والإشعارات: السند تابعٌ لفاتورته لا لمن أدخله
        if ($table !== 'invoices' && Schema::hasColumn($table, 'invoice_id')) {
            foreach ($this->branchGroups('invoices', 'id') as $branchId => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table($table)
                        ->whereNull('branch_id')
                        ->whereIn('invoice_id', $chunk)
                        ->update(['branch_id' => $branchId]);
                }
            }
        }

        // ② فرع مُنشئ المستند — نفسُ دلالة التصفية القديمة
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

        // ③ وإلّا فرع العميل — أثبتُ ما تبقّى حين يكون المُنشئ بلا فرع
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
