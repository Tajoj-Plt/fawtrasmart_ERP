<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متى يُحتسب اليومُ غياباً أو نصفَ يوم — بعد أن حضر الموظفُ وانصرف.
 *
 * كان الحكمُ مكتوباً في الشيفرة: أقلُّ من «مهلة الغياب» (وهي مهلةُ تأخيرٍ
 * عند الحضور أصلاً) ⇐ غياب، وأقلُّ من نصف الدوام ⇐ نصف يوم. صار إعدادين
 * ظاهرين في «الدوام والحضور» يضبطهما صاحبُ المنشأة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_settings', 'absent_below_minutes')) {
                $table->unsignedSmallInteger('absent_below_minutes')->default(120)->after('absent_after_minutes')
                    ->comment('صافي عملٍ أقل من هذه الدقائق ⇐ يُحتسب اليوم غياباً');
            }
            if (! Schema::hasColumn('attendance_settings', 'half_day_below_percent')) {
                $table->unsignedTinyInteger('half_day_below_percent')->default(50)->after('absent_below_minutes')
                    ->comment('صافي عملٍ أقل من هذه النسبة من الدوام المطلوب ⇐ نصف يوم');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach (['absent_below_minutes', 'half_day_below_percent'] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
