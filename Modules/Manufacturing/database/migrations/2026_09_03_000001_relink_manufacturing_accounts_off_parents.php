<?php

use App\Constants\SystemAccountIds;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نقلُ ترحيل التصنيع عن الحسابات **الأب** إلى أبنائها.
 *
 * ── القاعدة ──────────────────────────────────────────────────────────
 *
 * الحسابُ الأب يجب أن يساوي مجموعَ أبنائه. فإن حمل هو نفسُه أرصدةً
 * مباشرة، صار رصيدُه = مجموع الأبناء + ما رُحِّل عليه — ويُحتسب المبلغ
 * مرّتين في أي تقريرٍ يجمع الشجرة.
 *
 * ── ما كان مربوطاً ───────────────────────────────────────────────────
 *
 *   finished_goods_inventory  →  16   «المخزون»          (123)  ثمانية أبناء
 *   manufacturing_overhead    →  2962 «مصروفات التصنيع»  (550)  أربعة أبناء
 *
 * بينما إخوتُهما في المديول نفسه مربوطون بأبناءٍ صحيحين:
 *
 *   raw_materials_inventory     →  83   (1232)  بلا أبناء ✅
 *   work_in_progress_inventory  →  3201 (1237)  بلا أبناء ✅
 *   waste_inventory             →  3202 (1238)  بلا أبناء ✅
 *
 * والثابتان في `SystemAccountIds` كانا يوثّقان الحال بنفسيهما:
 * «(نفس INVENTORY)» و«(نفس مصروفات التصنيع)».
 *
 * ── إلى أين ──────────────────────────────────────────────────────────
 *
 *   finished_goods_inventory  →  82   «مخزون البضاعة»  (1231)  ابن 16
 *   manufacturing_overhead    →  2965 «أجور العمالة»   (5503)  ابن 2962
 *
 * كلاهما موجودٌ في `AccountSeeder` أصلاً (السطران 84 و392)، بلا أبناء،
 * ومن النوع الصحيح. **فلا يُنشأ حسابٌ جديد.**
 *
 * ── حدود التصحيح ─────────────────────────────────────────────────────
 *
 * لا يُلمس ربطٌ يدويٌّ مخالف. إن كان المدير قد ربط المفتاح بحسابٍ ثالث
 * من شاشة «الحسابات المحاسبية»، فذاك قرارُه ويبقى. لا نصحّح إلا ما هو
 * **على الأب بعينه** — أي ما تركه التركيبُ الافتراضي.
 *
 * ولا تُرحَّل قيودٌ قائمة: التصحيح للربط لا للتاريخ. وقواعدُ اليوم بلا
 * حركةٍ واحدة على أيٍّ من الحسابين، فلا شيء يُسوَّى.
 */
return new class extends Migration
{
    /** المفتاح ⟵ [الأب الخاطئ، الابن الصحيح] */
    private array $relink = [
        'finished_goods_inventory' => [16, SystemAccountIds::FINISHED_GOODS_INVENTORY],
        'manufacturing_overhead' => [2962, SystemAccountIds::MANUFACTURING_OVERHEAD],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach ($this->relink as $key => [$from, $to]) {
            $this->move($key, $from, $to);
        }
    }

    /**
     * العكسُ يعيد العطل.
     *
     * تُترك متاحةً للاكتمال البنيوي، لكنّ إعادةَ الترحيل إلى حسابٍ أب
     * خطأٌ محاسبيّ لا حالةٌ سابقة يُرجَع إليها.
     */
    public function down(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        foreach ($this->relink as $key => [$from, $to]) {
            $this->move($key, $to, $from);
        }
    }

    private function move(string $key, int $from, int $to): void
    {
        $target = DB::table('accounts')->where('id', $to)->first(['id', 'name', 'code']);

        if ($target === null) {
            echo "\n  ⚠️  الحساب {$to} غير موجود — تُرك المفتاح «{$key}» كما هو.\n";
            echo "      شغّل AccountSeeder ثم أعد هذه الهجرة.\n\n";

            return;
        }

        // الشرط على الأب الخاطئ وحده: ربطٌ يدويٌّ مخالف لا يُمَسّ.
        $affected = DB::table('accounting_settings')
            ->where('key', $key)
            ->where('account_id', $from)
            ->update([
                'account_id' => $target->id,
                'account_code' => $target->code,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            echo "\n  ✓ «{$key}»: {$from} ⟵ {$target->id} {$target->name} ({$target->code})\n";
        } else {
            $current = DB::table('accounting_settings')->where('key', $key)->value('account_id');

            echo "\n  · «{$key}» غير مربوط بالأب {$from} (الحالي: "
                .($current ?? 'فارغ').") — تُرك كما هو.\n";
        }
    }
};
