<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضابطُ الالتزام بنافذة الوردية.
 *
 * ═══════════════ لماذا ضابطٌ لا حكمٌ ثابت ═══════════════
 *
 * قواعدُ الوردية ثلاثٌ متفاوتةُ الحزم:
 *
 *   • **الوردية المعطَّلة** و**اليوم غير المسموح** لا لبسَ فيهما: من عطّل
 *     ورديةً أو حدّد أيّامَها قصد المنعَ صراحةً. فهما يُفرضان دائماً.
 *
 *   • أمّا **نافذةُ الوقت** فأقلُّ قطعاً: منشأةٌ تضبط «٠٨:٠٠ — ١٦:٠٠» ثم
 *     يحتاج الكاشير فتحَ صندوقه السابعةَ والنصف لجردٍ أو تجهيز. ولذلك وُجدت
 *     `grace_minutes` أصلاً — فهي اعترافٌ بأن الحدَّ ليّن.
 *
 * فالوقتُ وحده يُتاح إرخاؤه، والافتراضُ **مُلزِم**: من ضبط أوقاتاً قصد
 * الالتزام بها، ومن أراد غير ذلك يُطفئ الضابط عامداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_general_settings', 'enforce_shift_window')) {
                $table->boolean('enforce_shift_window')->default(true)
                    ->comment('أيُمنع فتحُ الجلسة خارج وقت الوردية؟ (اليومُ والتعطيل يُفرضان دائماً)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_general_settings', function (Blueprint $table) {
            $table->dropColumn('enforce_shift_window');
        });
    }
};
