<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قالبا الطباعة المختاران في إعدادات نقاط البيع.
 *
 * ─────────────────── ما كان ───────────────────
 *
 * `invoice_template` عمودٌ قائم، وقيمتُه إحدى ثنتين: `thermal` أو
 * `electronic`. وهما **ليستا قالبين** بل وصفَا شكلٍ عامّ لا يقرؤهما شيء:
 * `SalesStartController::print()` كانت تعرض `sales_start.print` دائماً
 * مهما كانت القيمة.
 *
 * وفي «الإعدادات ← قوالب المستندات» مئتا قالبٍ مصمَّم، لكلِّ نوعٍ
 * افتراضيُّه. فيصمّم المستخدمُ إيصالَه هناك، ثم يفتح إعدادات نقاط البيع
 * فيجد خيارين لا صلةَ لهما بما صمّم.
 *
 * ─────────────────── وما صار ───────────────────
 *
 * العمودُ يحمل **معرّفَ قالبٍ** من `document_templates`، أو `default`
 * ليعني «الافتراضيَّ المعيَّن في شاشة القوالب»، أو `builtin` ليعني قالبَ
 * النظام المدمج.
 *
 * وأُضيف `return_template` بجانبه: المرتجعُ مستندٌ آخرُ بنوعٍ آخر
 * (`sales_return`)، وقالبُ الفاتورة لا يصلح له.
 *
 * ─────────────────── والقيمُ القديمة ───────────────────
 *
 * `thermal` و`electronic` تُحوَّلان إلى `default`: لم تكونا تعنيان قالباً،
 * فتحويلُهما إلى معرّفٍ بعينه اختراعُ اختيارٍ لم يقع. و«الافتراضي» يُبقي
 * السلوكَ على ما كان يقصده المستخدم — أحسنُ ما هو معيَّن.
 */
return new class extends Migration
{
    private const TABLE = 'pos_general_settings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'return_template')) {
                $table->string('return_template', 32)->nullable()->after('invoice_template')
                    ->comment('قالب طباعة المرتجع: معرّف قالب، أو default، أو builtin');
            }
        });

        // القيمُ القديمة لا تعني قالباً — تُحوَّل إلى «الافتراضي»
        DB::table(self::TABLE)
            ->whereIn('invoice_template', ['thermal', 'electronic'])
            ->orWhereNull('invoice_template')
            ->update(['invoice_template' => 'default']);

        DB::table(self::TABLE)->whereNull('return_template')->update(['return_template' => 'default']);
    }

    public function down(): void
    {
        if (Schema::hasColumn(self::TABLE, 'return_template')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn('return_template'));
        }
    }
};
