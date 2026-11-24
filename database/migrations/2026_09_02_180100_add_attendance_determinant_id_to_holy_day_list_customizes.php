<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `holy_day_list_customizes.attendance_determinant_id` — العمود الذي يُسند
 * مجموعةَ موظفين إلى محدِّد حضور.
 *
 * الجدولُ أُنشئ لتخصيص قوائم العطلات (holiday_list_id)، ثم أُعيد استعماله
 * لمحدِّدات الحضور: HolyDayListCustomize::$fillable يذكر
 * attendance_determinant_id، والمتحكّم يكتبه ويستعلم به في عشرة مواضع —
 * لكن العمود لم يُخلَق قط.
 *
 * فكانت كلُّ عمليات إسناد الموظفين للمحدِّدات تنتهي بـ1054: عدّ الموظفين،
 * والإحصائيات، وشاشة إدارة الموظفين، والإضافة والإزالة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('holy_day_list_customizes')
            || Schema::hasColumn('holy_day_list_customizes', 'attendance_determinant_id')) {
            return;
        }

        Schema::table('holy_day_list_customizes', function (Blueprint $table) {
            if (! Schema::hasColumn('holy_day_list_customizes', 'attendance_determinant_id')) {
                $table->unsignedBigInteger('attendance_determinant_id')->nullable()->after('holiday_list_id');
            }
            if (! SchemaGuard::hasIndex('holy_day_list_customizes', 'idx_hdlc_determinant')) {
                $table->index('attendance_determinant_id', 'idx_hdlc_determinant');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('holy_day_list_customizes')
            || ! Schema::hasColumn('holy_day_list_customizes', 'attendance_determinant_id')) {
            return;
        }

        Schema::table('holy_day_list_customizes', function (Blueprint $table) {
            $table->dropIndex('idx_hdlc_determinant');
            $table->dropColumn('attendance_determinant_id');
        });
    }
};
