<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الاستراحةُ أثناء الدوام، والدوامُ المرن للموظف الميداني.
 *
 * - `attendance_days.break_started_at` استراحةٌ مفتوحةٌ الآن، و`break_minutes`
 *   مجموعُ ما أُغلق منها. تفاصيلُ كلِّ استراحة في `attendance_punches`
 *   (`punch_type = rest_in | rest_out`) — سجلٌّ واحد لكل ما جرى في اليوم.
 * - `employee_attendance_configs.work_mode`: `shift` (حسب الوردية، الافتراضي)
 *   أو `flexible` (عددُ ساعاتٍ في اليوم يبدؤها متى شاء). و`break_allowed_minutes`
 *   مدةُ الاستراحة المسموحة له؛ وإلّا فإعدادُ المنشأة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_days', 'break_started_at')) {
                $table->dateTime('break_started_at')->nullable()->after('check_out_time')
                    ->comment('استراحةٌ مفتوحةٌ الآن — تُغلق بإنهائها أو بالانصراف');
            }
            if (! Schema::hasColumn('attendance_days', 'break_minutes')) {
                $table->unsignedSmallInteger('break_minutes')->default(0)->after('break_started_at')
                    ->comment('مجموع دقائق الاستراحات المغلقة في اليوم');
            }
        });

        Schema::table('employee_attendance_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_attendance_configs', 'work_mode')) {
                $table->string('work_mode', 10)->nullable()->after('work_hours_per_day')
                    ->comment('shift = حسب الوردية | flexible = عدد ساعات يبدؤها متى شاء');
            }
            if (! Schema::hasColumn('employee_attendance_configs', 'break_allowed_minutes')) {
                $table->unsignedSmallInteger('break_allowed_minutes')->nullable()->after('work_mode');
            }
        });

        if (! Schema::hasColumn('attendance_settings', 'break_allowed_minutes')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('break_allowed_minutes')->default(60)->after('checkin_early_minutes')
                    ->comment('مدة الاستراحة المسموحة في اليوم — الزائد عنها يُحسب نقصاً في الدوام');
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            foreach (['break_started_at', 'break_minutes'] as $column) {
                if (Schema::hasColumn('attendance_days', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('employee_attendance_configs', function (Blueprint $table) {
            foreach (['work_mode', 'break_allowed_minutes'] as $column) {
                if (Schema::hasColumn('employee_attendance_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        if (Schema::hasColumn('attendance_settings', 'break_allowed_minutes')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->dropColumn('break_allowed_minutes');
            });
        }
    }
};
