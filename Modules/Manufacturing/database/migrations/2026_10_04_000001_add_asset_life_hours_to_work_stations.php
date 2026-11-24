<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * عمرُ الأصل بالساعات على محطّة العمل.
 *
 * كانت قيمةُ الأصل (`cost_origin`) تُجمع كلُّها على تكلفة الساعة، فتصير
 * ساعةُ محطّةٍ فيها آلةٌ بمئة ألف بمئة ألف. والعمودُ الجديد مقامُ القسمة:
 *
 *   إهلاك الساعة = cost_origin ÷ asset_life_hours   (صفر إن خلا العمر)
 *   تكلفة الساعة = المصروفات + الأجور + إهلاك الساعة
 *
 * إضافةٌ فقط — لا يُمسّ عمودٌ قائم ولا يُعاد حسابُ محطّةٍ محفوظة؛ تُصحَّح
 * تكلفتُها عند أوّل حفظٍ لها من شاشة التعديل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_stations')
            && ! Schema::hasColumn('work_stations', 'asset_life_hours')) {
            Schema::table('work_stations', function ($table) {
                $table->decimal('asset_life_hours', 12, 2)->nullable()->after('origin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_stations')
            && Schema::hasColumn('work_stations', 'asset_life_hours')) {
            Schema::table('work_stations', function ($table) {
                $table->dropColumn('asset_life_hours');
            });
        }
    }
};
