<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مستودعُ نقطة البيع — إعدادٌ يُختار، لا عهدةٌ تُستنتج.
 *
 * ═══════════════ ما كان ═══════════════
 *
 * المستودعُ الذي تُقرأ منه الكمّياتُ ويُخصم منه المباع كان يُشتقّ من
 * **عهدة الموظّف**: `PosCustody` تحلّ موظّفَ الجهاز ثم تقرأ مستودعَه من
 * `default_warehouses` — أي «مخزون المندوب».
 *
 * وهو منطقٌ يصحّ لمندوب المبيعات الذي يحمل بضاعتَه في سيارته، ولا يصحّ
 * لنقطة بيعٍ ثابتة: الكاشيرُ لا يملك مخزناً، إنما يبيع من مخزن الفرع.
 * فكان لزاماً أن يُربط كلُّ كاشيرٍ بمستودعٍ في شاشة الموظفين — ومن لم
 * يُربط لا يبيع، ومن رُبط بمستودعٍ خاصٍّ به انقسم مخزونُ الفرع الواحد
 * على عدد الكاشيرين، فلا يُعرف رصيدُ الصنف من شاشةٍ واحدة.
 *
 * ═══════════════ وما صار ═══════════════
 *
 * عمودٌ واحد في إعدادات نقاط البيع، بالتوريث نفسِه الذي تعمل به بقيّةُ
 * الإعدادات: صفُّ الفرع ← الصفُّ العام. فالفرعُ يختار مخزنَه، ومن لم
 * يختر يرث مخزنَ المنشأة.
 *
 * ولا مفتاحَ أجنبيّ عمداً — على نسق `2026_08_29_300000`: حذفُ مستودعٍ
 * يجب أن يُفرِغ الربطَ لا أن يمنع الحذف، والبيعُ حينها يتوقّف برسالةٍ
 * تدلّ على الشاشة بدل أن يُخصم من مخزنٍ مخمَّن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_general_settings', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')
                    ->nullable()
                    ->after('default_customer_id')
                    ->comment('مستودعُ نقطة البيع — يُقرأ منه المعروض ويُخصم منه المباع، وحسابُه هو الطرفُ الدائن في قيد التكلفة');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_general_settings', 'store_house_id')) {
                $table->dropColumn('store_house_id');
            }
        });
    }
};
