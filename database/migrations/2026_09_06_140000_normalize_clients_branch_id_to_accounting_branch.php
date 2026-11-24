<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يجعل `clients.branch_id` فرعاً محاسبياً حقّاً عند كل عميل.
 *
 * ── العلّة ────────────────────────────────────────────────────────────
 *
 * بعد فصل الموضع التنظيميّ (`tree_node_id`) عن الفرع المحاسبيّ
 * (`branch_id`)، صار المعنى الجديد للعمود: «الفرع الذي تُقيَّد عليه فواتير
 * العميل». لكنّ هجرة الفصل ملأت العمود الجديد ولم تمسّ القديم، فبقيت
 * صفوفُ العملاء السابقين تحمل في `branch_id` **الورقة التي كانت مختارةً
 * وقتها** — وهي في أكثر المنشآت مجموعةٌ أو منطقة لا فرع.
 *
 * فيظهر في شاشة العملاء سطرٌ يقول «الفرع المحاسبي: القيروان» والقيروان
 * مجموعةٌ لا فرع، ويجرّ كلُّ تقريرٍ يجمع حسب الفرع صفَّ مجموعةٍ في عمود
 * الفروع.
 *
 * ── ما تفعله ─────────────────────────────────────────────────────────
 *
 * تُعيد اشتقاق `branch_id` من العقدة التنظيمية عبر
 * `Branch::resolveAccountingBranchId()` — العقدةُ نفسُها إن كانت فرعاً،
 * وإلّا أقربُ سلفٍ من نوع «فرع». ولا تُمسّ الصفوف التي يشير عمودها أصلاً
 * إلى فرع صحيح.
 *
 * ── ما لا تفعله ──────────────────────────────────────────────────────
 *
 * لا تلمس `tree_node_id`: موضعُ العميل كما اختاره المستخدم لا يتغيّر،
 * والمعروضُ في الشاشات هو هو. التغييرُ في العمود المحاسبيّ وحده، وهو
 * تصحيحٌ لقيمةٍ لم تكن تعني ما يقوله اسمُها الجديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')
            || ! Schema::hasColumn('clients', 'tree_node_id')
            || ! Schema::hasColumn('clients', 'branch_id')) {
            return;
        }

        Branch::preloadCache();

        // خريطةُ العقدة ← فرعها المحاسبيّ، تُحسب مرّةً لكل عقدة لا لكل عميل
        $resolved = [];
        $resolve = function (int $nodeId) use (&$resolved): ?int {
            if (array_key_exists($nodeId, $resolved)) {
                return $resolved[$nodeId];
            }

            $node = Branch::findCached($nodeId);

            return $resolved[$nodeId] = $node?->resolveAccountingBranchId();
        };

        DB::table('clients')
            ->whereNotNull('tree_node_id')
            ->select('id', 'tree_node_id', 'branch_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($resolve) {
                foreach ($rows as $row) {
                    $target = $resolve((int) $row->tree_node_id);

                    if ($target && (int) $row->branch_id !== $target) {
                        DB::table('clients')->where('id', $row->id)->update(['branch_id' => $target]);
                    }
                }
            });
    }

    public function down(): void
    {
        // لا رجعة: القيمةُ السابقة كانت الورقةَ التنظيمية، وهي محفوظةٌ أصلاً
        // في `tree_node_id` — فلا شيء يُفقد ولا شيء يُستعاد.
    }
};
