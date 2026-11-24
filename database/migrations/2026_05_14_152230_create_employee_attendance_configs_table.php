<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الحضور المخصصة لكل موظف.
 * تُعطى الأولوية على الإعدادات العامة (attendance_settings).
 * NULL = استخدم الإعداد العام.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_attendance_configs')) {
            Schema::create('employee_attendance_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->unique();

                // أوقات الحضور/الانصراف (override للوردية)
                $table->time('expected_check_in')->nullable()->comment('وقت الحضور المتوقع');
                $table->time('expected_check_out')->nullable()->comment('وقت الانصراف المتوقع');

                // overrides للإعدادات العامة (NULL = استخدم العام)
                $table->unsignedSmallInteger('late_grace_minutes')->nullable();
                $table->unsignedSmallInteger('absent_after_minutes')->nullable();
                $table->boolean('auto_deduction_enabled')->nullable();
                $table->decimal('late_deduction_amount', 10, 2)->nullable();
                $table->decimal('absence_deduction_amount', 10, 2)->nullable();
                $table->decimal('work_hours_per_day', 4, 2)->nullable();
                $table->enum('checkout_method', ['hours', 'route', 'both'])->nullable();
                $table->boolean('photos_required')->nullable();
                $table->boolean('location_check_enabled')->nullable();
                $table->boolean('ip_check_enabled')->nullable();

                // أيام العمل الأسبوعية (JSON: ["sun","mon","tue","wed","thu"])
                $table->json('weekly_work_days')->nullable()->comment('أيام العمل في الأسبوع');

                // عطلة أسبوعية فردية (تعلو على إعداد المؤسسة)
                $table->json('weekly_off_days')->nullable()->comment('أيام العطلة الأسبوعية');

                // مسار خط السير (إن استخدم checkout_method=route)
                $table->boolean('uses_route_tracking')->default(false);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->foreign('employee_id', 'fk_emp_att_config')
                    ->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_configs');
    }
};
