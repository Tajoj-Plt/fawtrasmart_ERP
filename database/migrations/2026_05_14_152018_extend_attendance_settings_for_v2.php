<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة جدول إعدادات الحضور لـ:
 *   - مدة السماح للتأخير
 *   - تفعيل/تعطيل الخصميات التلقائية
 *   - قيمة الخصمية الافتراضية
 *   - عدد ساعات العمل
 *   - عدد صور الحضور المطلوبة
 *   - طريقة احتساب الانصراف (وقت/خط سير)
 *   - تفعيل التحقق من الموقع
 *   - تفعيل التحقق من الصور
 *   - تفعيل المسار/خط السير
 *   - بداية يوم العمل الافتراضي
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            // === مدة السماح والتأخير ===
            if (!Schema::hasColumn('attendance_settings', 'late_grace_minutes')) {
                $table->unsignedSmallInteger('late_grace_minutes')->default(15)->after('start_day')
                    ->comment('مدة السماح للتأخير بالدقائق قبل احتسابه تأخراً');
            }
            if (!Schema::hasColumn('attendance_settings', 'absent_after_minutes')) {
                $table->unsignedSmallInteger('absent_after_minutes')->default(120)->after('late_grace_minutes')
                    ->comment('بعد كم دقيقة من بداية الوردية يُعتبر غياباً (لا تأخيراً)');
            }

            // === الخصميات التلقائية ===
            if (!Schema::hasColumn('attendance_settings', 'auto_deduction_enabled')) {
                $table->boolean('auto_deduction_enabled')->default(false)->after('absent_after_minutes')
                    ->comment('تفعيل الخصمية التلقائية على التأخير/الغياب');
            }
            if (!Schema::hasColumn('attendance_settings', 'late_deduction_amount')) {
                $table->decimal('late_deduction_amount', 10, 2)->default(0)->after('auto_deduction_enabled')
                    ->comment('قيمة خصمية التأخير الافتراضية');
            }
            if (!Schema::hasColumn('attendance_settings', 'absence_deduction_amount')) {
                $table->decimal('absence_deduction_amount', 10, 2)->default(0)->after('late_deduction_amount')
                    ->comment('قيمة خصمية الغياب الافتراضية');
            }
            if (!Schema::hasColumn('attendance_settings', 'deduction_calculation_type')) {
                $table->enum('deduction_calculation_type', ['fixed', 'daily_salary', 'per_minute'])->default('fixed')->after('absence_deduction_amount')
                    ->comment('طريقة احتساب الخصمية: مبلغ ثابت | يومية من الراتب | بالدقيقة');
            }

            // === ساعات العمل ===
            if (!Schema::hasColumn('attendance_settings', 'work_hours_per_day')) {
                $table->decimal('work_hours_per_day', 4, 2)->default(8.00)->after('deduction_calculation_type')
                    ->comment('عدد ساعات العمل اليومي المتوقع');
            }

            // === طريقة الانصراف ===
            if (!Schema::hasColumn('attendance_settings', 'checkout_method')) {
                $table->enum('checkout_method', ['hours', 'route', 'both'])->default('hours')->after('work_hours_per_day')
                    ->comment('طريقة الانصراف: ساعات / خط سير / كلاهما');
            }

            // === الصور ===
            if (!Schema::hasColumn('attendance_settings', 'photos_required')) {
                $table->boolean('photos_required')->default(false)->after('checkout_method')
                    ->comment('هل التقاط الصور مطلوب عند الحضور والانصراف؟');
            }
            if (!Schema::hasColumn('attendance_settings', 'photos_count_checkin')) {
                $table->unsignedTinyInteger('photos_count_checkin')->default(0)->after('photos_required')
                    ->comment('عدد الصور المطلوبة عند الحضور');
            }
            if (!Schema::hasColumn('attendance_settings', 'photos_count_checkout')) {
                $table->unsignedTinyInteger('photos_count_checkout')->default(0)->after('photos_count_checkin')
                    ->comment('عدد الصور المطلوبة عند الانصراف');
            }

            // === التحقق من الموقع/IP ===
            if (!Schema::hasColumn('attendance_settings', 'location_check_enabled')) {
                $table->boolean('location_check_enabled')->default(false)->after('photos_count_checkout')
                    ->comment('تفعيل التحقق من الموقع GPS');
            }
            if (!Schema::hasColumn('attendance_settings', 'ip_check_enabled')) {
                $table->boolean('ip_check_enabled')->default(false)->after('location_check_enabled')
                    ->comment('تفعيل التحقق من IP');
            }

            // === فترة السماح للانصراف المبكر ===
            if (!Schema::hasColumn('attendance_settings', 'early_leave_threshold_minutes')) {
                $table->unsignedSmallInteger('early_leave_threshold_minutes')->default(15)->after('ip_check_enabled')
                    ->comment('عدد الدقائق المسموح بها للانصراف المبكر دون تنبيه');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $cols = [
                'late_grace_minutes', 'absent_after_minutes',
                'auto_deduction_enabled', 'late_deduction_amount', 'absence_deduction_amount',
                'deduction_calculation_type', 'work_hours_per_day', 'checkout_method',
                'photos_required', 'photos_count_checkin', 'photos_count_checkout',
                'location_check_enabled', 'ip_check_enabled', 'early_leave_threshold_minutes',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('attendance_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
