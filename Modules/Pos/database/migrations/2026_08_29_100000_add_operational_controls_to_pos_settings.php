<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضوابطُ التشغيل الثلاثة — أعمدةٌ عامّة لمفاتيحَ كانت فرعيّةً فقط.
 *
 * ═══════════════ ما كان ═══════════════
 *
 * `ResolvesPosSettings` تعرّف ثلاثةَ ضوابطَ حقيقية:
 *
 *   • `require_shift_open`  — أتُلزَم جلسةٌ مفتوحة قبل البيع؟
 *   • `allow_cash_refund`   — أيُردّ المبلغُ نقداً من الدرج؟
 *   • `max_refund_amount`   — أقصى مرتجعٍ بلا اعتماد
 *
 * وكلُّها تُقرأ بـ`posSetting($branchKey, null, …)` — أي **من إعدادات الفرع
 * وحدها، بلا عمودٍ عامّ**. فالمنشأةُ لا تستطيع ضبطها إلا فرعاً فرعاً، ولا
 * موضعَ لها في شاشة إعدادات نقاط البيع أصلاً. ومن لا يفتح شاشةَ الفروع لا
 * يعرف بوجودها.
 *
 * وأثرُ ذلك أنّ اثنين منها يعملان بالافتراض دائماً: كلُّ مرتجعٍ يحتاج
 * اعتماداً (`max_refund_amount = 0`)، ولا ردَّ نقديّ إطلاقاً
 * (`allow_cash_refund = false`) — والمستخدمُ يظنّهما عطباً لا إعداداً.
 *
 * ═══════════════ والترتيب يبقى ═══════════════
 *
 * الفرعُ أخصُّ من العام فيُسأل أوّلاً — هذه الأعمدةُ هي ما يُرجَع إليه حين
 * لا يخصِّص الفرعُ قيمة، لا ما يغلبه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_general_settings', 'require_shift_open')) {
                // الافتراضُ «مطلوبة»: العهدةُ ضمانةُ الصندوق، ومن يُسقطها
                // يُسقطها عامداً
                $table->boolean('require_shift_open')->default(true)
                    ->comment('أتُلزَم جلسةٌ مفتوحة قبل البيع؟');
            }

            if (! Schema::hasColumn('pos_general_settings', 'allow_cash_refund')) {
                // الافتراضُ «لا»: الردُّ النقدي يفرّغ الدرج بلا أثرٍ في
                // الحساب، فيُطلب صراحةً
                $table->boolean('allow_cash_refund')->default(false)
                    ->comment('أيُسمح بردّ المبلغ نقداً من الدرج؟');
            }

            if (! Schema::hasColumn('pos_general_settings', 'max_refund_amount')) {
                // `null` يعني «بلا حدّ»، و`0` يعني «كلُّ مرتجعٍ يحتاج اعتماداً» —
                // وهما مختلفان، فالعمودُ يقبل الفراغ
                $table->decimal('max_refund_amount', 15, 2)->nullable()
                    ->comment('أقصى مرتجعٍ بلا اعتماد — 0 يعني أن كلَّها تحتاج اعتماداً');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            $table->dropColumn(['require_shift_open', 'allow_cash_refund', 'max_refund_amount']);
        });
    }
};
