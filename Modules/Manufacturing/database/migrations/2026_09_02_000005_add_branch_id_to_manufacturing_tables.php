<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `branch_id` على أوامر التصنيع وقوائم المواد.
 *
 * ── الفجوة ───────────────────────────────────────────────────────────
 *
 * لا عمودَ فرعٍ على أيٍّ من الجدولين. فمنشأةٌ بمصنعين أو مستودعين
 * منفصلين تخلط أوامرَهما في قائمةٍ واحدة، ولا سبيل لقصر ما يراه
 * الموظّف على فرعه — بينما الفواتير والمخزون والأذون كلُّها مُفرَّعة.
 *
 * ── النمط المتّبع في المشروع ─────────────────────────────────────────
 *
 * يُتَّبع حرفاً بحرف نمطُ `2026_08_30_180000_add_branch_id_to_online_store_tables`
 * وسمةِ [\App\Traits\BelongsToBranch]:
 *
 *  · `nullable` **عمداً** — `null` تعني «كل الفروع» لا «بلا فرع»،
 *    وهي دلالة `scopeForBranch` التي تُرجع صفوفَ الفرع المطلوب
 *    **مع** الصفوف الفارغة.
 *  · `nullOnDelete` — حذفُ فرعٍ لا يحذف أوامرَ تصنيعٍ مُرحَّلة
 *    محاسبياً؛ تصير «كل الفروع» ويراجعها محاسب.
 *  · الصفوفُ القائمة تبقى `NULL` — أي تُرى من كل الفروع. وهذا
 *    السلوكُ الحالي بالضبط، فلا يتغيّر شيءٌ على أحد بهذه الهجرة.
 *
 * ── حدودُ هذه الهجرة ─────────────────────────────────────────────────
 *
 * **بنيةٌ فقط — لا عزل.** إضافة العمود لا تمنع أحداً من رؤية شيء.
 * التطبيقُ الفعلي — ملءُ العمود عند الإنشاء من `current_branch_id()`،
 * وتضييقُ الاستعلامات، وسياسةُ الوصول — موضعُه **المرحلة ١٠**.
 * يُذكر هذا صراحةً كي لا يُفهَم أن ثغرة عزل الفروع أُغلقت هنا.
 */
return new class extends Migration
{
    private array $tables = ['manufactur_orders', 'production_materials'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
