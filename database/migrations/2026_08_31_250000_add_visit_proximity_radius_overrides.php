<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نطاق قرب مخصَّص لموظفٍ بعينه لتسجيل/بدء زيارات العملاء — يتجاوز الإعداد
 * العام `visit_proximity_radius_km` (إعدادات وحدات العملاء ← الزيارات) لهذا
 * الموظف تحديداً، بلا المساس بذلك الإعداد نفسه كافتراضٍ عام لبقيّة الموظفين.
 *
 * على `users` لا `employees`: الزيارات تُسنَد لمستخدمٍ لا لسجلّ موظفٍ منفصل
 * (`Visit::employee()` → `User::class`). راجع
 * `ClientSettingsHelper::visitProximityRadiusKmFor()` لترتيب الأولوية:
 * نطاق الموظف المخصَّص إن وُجد، وإلا الإعداد العام.
 *
 * عمداً لا يوجد عمودٌ مقابل على `clients`: طُلب أن يبقى النطاق إعداداً عاماً
 * (بحدّ أقصى مخصَّصٍ للموظف) لا حقلاً على كل عميلٍ بعينه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'visit_proximity_radius_km')) {
                $table->decimal('visit_proximity_radius_km', 5, 2)->nullable()->after('employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('visit_proximity_radius_km');
        });
    }
};
