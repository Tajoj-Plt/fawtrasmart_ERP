<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذفُ `payrolls.attendance_check` — خانة واجهة لا تُقرأ عند الترحيل.
 *
 * `Payroll` مظلةٌ زمنية لا حدثَ صرف (الصرفُ في `PayrollRun`)، والخانةُ
 * كانت تُحفظ وتُعرض فقط في نموذجي إنشاء/تعديل مسير الرواتب وشاشة
 * عرضه — بلا أيّ فحصٍ يقرؤها في أيّ مسار توليدٍ أو ترحيل. قرارٌ عمليٌّ
 * صريح: تُحذف بدل أن تبقى تُوهم مستخدم HR بأنها تفعل شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payrolls', 'attendance_check')) {
            Schema::table('payrolls', function (Blueprint $table) {
                if (Schema::hasColumn('payrolls', 'attendance_check')) {
                    $table->dropColumn('attendance_check');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payrolls', 'attendance_check')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->boolean('attendance_check')->default(false);
            });
        }
    }
};
