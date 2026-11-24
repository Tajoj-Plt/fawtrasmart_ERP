<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يفصل «أين يقع العميل تنظيمياً» عن «أين تُقيَّد محاسبته».
 *
 * كان `clients.branch_id` يحمل المعنيين معاً، فاضطُرّ اختيارُ الفرع أن
 * يقتصر على أوراق الشجرة (`ClientController::branchesForClientTreeSelect`):
 * لو حمل العمودُ منطقةً أو منفذاً لجرّت كلُّ قيود اليومية وتقارير المبيعات
 * صفَّ منطقةٍ لا فرعاً محاسبياً. فبقيت المنشأةُ ممنوعةً من نسبة عميل إلى
 * منطقة أو منفذ — وهو ما تصفه `branches.node_types.explain` بأنه السلوك
 * المقصود أصلاً: «فرع» وحدة محاسبية، والبقيّة وحدات تنظيمية تتبع محاسبياً
 * لأقرب فرع فوقها.
 *
 * فصار:
 *   `tree_node_id` = العقدة التي اختارها المستخدم (منطقة/فرع/مجموعة/منفذ،
 *                    بأي عمق) — عليها الفهرس والفلترة والتقارير التنظيمية.
 *   `branch_id`    = الفرع المحاسبي، يُشتقّ آلياً من العقدة عبر
 *                    `Branch::resolveAccountingBranchId()` ولا يختاره أحد.
 *
 * الترحيل يملأ `tree_node_id` من `branch_id` — العملاء الحاليون كلهم أوراق
 * فالقيمتان تتطابقان عندهم، ولا يتغيّر شيء في شاشاتهم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'tree_node_id')) {
                $table->unsignedBigInteger('tree_node_id')->nullable()->after('branch_id')
                    ->comment('العقدة التنظيمية في شجرة branches — منطقة/فرع/مجموعة/منفذ بأي عمق');
            }
        });

        if (! Schema::hasColumn('clients', 'tree_node_id')) {
            return;
        }

        /* الترحيل يحفظ ما كانت الشاشةُ تعرضه فعلاً، لا `branch_id` وحدَه.
           فـ`Client::branchTreeDisplayPaths()` القديمة كانت ترتّب المرتكز:
           حيُّ العميل (`neighborhoods.region_id`) ثمّ `group_id` ثمّ
           `branch_id` — ونسخُ `branch_id` وحدَه كان يغيّر الموقعَ المعروض
           لكل عميلٍ له حيّ. فالتعبئةُ هنا بنفس الترتيب معكوساً: الأعمُّ
           أوّلاً ثمّ ما يسود عليه. */
        DB::table('clients')->whereNull('tree_node_id')->update([
            'tree_node_id' => DB::raw('branch_id'),
        ]);

        $branchIds = DB::table('branches')->pluck('id')->map(fn ($id) => (int) $id)->flip();

        if (Schema::hasColumn('clients', 'group_id')) {
            DB::table('clients')->whereNotNull('group_id')
                ->select('id', 'group_id')->orderBy('id')
                ->chunk(500, function ($rows) use ($branchIds) {
                    foreach ($rows as $row) {
                        if (isset($branchIds[(int) $row->group_id])) {
                            DB::table('clients')->where('id', $row->id)
                                ->update(['tree_node_id' => (int) $row->group_id]);
                        }
                    }
                });
        }

        if (Schema::hasTable('neighborhoods')) {
            DB::table('neighborhoods')->whereNotNull('client_id')->whereNotNull('region_id')
                ->select('client_id', 'region_id')->orderBy('id')
                ->chunk(500, function ($rows) use ($branchIds) {
                    foreach ($rows as $row) {
                        if (isset($branchIds[(int) $row->region_id])) {
                            DB::table('clients')->where('id', $row->client_id)
                                ->update(['tree_node_id' => (int) $row->region_id]);
                        }
                    }
                });
        }

        try {
            Schema::table('clients', function (Blueprint $table) {
                if (! SchemaGuard::hasIndex('clients', 'clients_tree_node_id_index')) {
                    $table->index('tree_node_id');
                }
            });
        } catch (\Throwable $e) {
            // فهرسٌ موجودٌ باسمٍ آخر في قاعدةٍ سبقت الهجرة — لا يمنع العمل
        }

        try {
            Schema::table('clients', function (Blueprint $table) {
                if (! SchemaGuard::hasForeignKey('clients', 'clients_tree_node_id_foreign')) {
                    $table->foreign('tree_node_id')->references('id')->on('branches')->onDelete('set null');
                }
            });
        } catch (\Throwable $e) {
            // SQLite لا يضيف FK على عمود قائم عبر ALTER TABLE؛ العلاقة تبقى منطقية
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'tree_node_id')) {
            return;
        }

        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropForeign(['tree_node_id']);
            });
        } catch (\Throwable $e) {
            // بلا قيدٍ أصلاً — لا شيء لإسقاطه
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('tree_node_id');
        });
    }
};
