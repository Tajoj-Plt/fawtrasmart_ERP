<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الرد بالإشعار الذي رُدّ عليه.
 *
 * كان الرد يُنشَأ كصفٍّ جديد في نفس الجدول بعنوانٍ مسبوقٍ بـ«رد على: »
 * وبلا أيّ عمودٍ يربطه بأصله — فلا سبيل إلى معرفة إشعارٍ رُدّ عليه من
 * إشعارٍ لم يُرَدّ. (وحتى `destroy()` كان يحذف الردود بشرطٍ على
 * `data->original_notification_id` وهو عمودٌ لا وجود له في الجدول.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notifications', 'parent_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            // بلا مفتاحٍ أجنبي: الجدول قائمٌ في قواعدَ إنتاجيةٍ سابقةٍ للهجرات،
            // والفهرس وحده يكفي للاستعلام «إشعارات بلا رد»
            $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notifications', 'parent_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
    }
};
