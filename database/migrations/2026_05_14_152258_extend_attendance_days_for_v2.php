<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة attendance_days لـ:
 *   - ربط الخصمية المُولَّدة تلقائياً (deduction_id)
 *   - عدد دقائق التأخير
 *   - عدد دقائق الانصراف المبكر
 *   - الفترة المُجدولة (expected_check_in/out)
 *   - الإحداثيات الجغرافية
 *   - حالة الإجازة/العطلة (إن وُجدت)
 *   - علم الـ processed (هل عُولج بالـ cron؟)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            // ربط الخصمية المُولَّدة
            if (!Schema::hasColumn('attendance_days', 'deduction_id')) {
                $table->unsignedBigInteger('deduction_id')->nullable()->after('absence_balance')
                    ->comment('معرف الخصمية المُنشَأة تلقائياً');
            }

            // التأخير والانصراف المبكر
            if (!Schema::hasColumn('attendance_days', 'late_minutes')) {
                $table->unsignedSmallInteger('late_minutes')->default(0)->after('deduction_id')
                    ->comment('عدد دقائق التأخير');
            }
            if (!Schema::hasColumn('attendance_days', 'early_leave_minutes')) {
                $table->unsignedSmallInteger('early_leave_minutes')->default(0)->after('late_minutes')
                    ->comment('عدد دقائق الانصراف المبكر');
            }
            if (!Schema::hasColumn('attendance_days', 'overtime_minutes')) {
                $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('early_leave_minutes')
                    ->comment('عدد دقائق العمل الإضافي');
            }

            // المُتوقَّع (snapshot من إعدادات يوم الحضور)
            if (!Schema::hasColumn('attendance_days', 'expected_check_in')) {
                $table->time('expected_check_in')->nullable()->after('overtime_minutes');
            }
            if (!Schema::hasColumn('attendance_days', 'expected_check_out')) {
                $table->time('expected_check_out')->nullable()->after('expected_check_in');
            }

            // علم العطلة/الإجازة
            if (!Schema::hasColumn('attendance_days', 'is_holiday')) {
                $table->boolean('is_holiday')->default(false)->after('expected_check_out')
                    ->comment('يوم عطلة رسمية');
            }
            if (!Schema::hasColumn('attendance_days', 'is_weekly_off')) {
                $table->boolean('is_weekly_off')->default(false)->after('is_holiday')
                    ->comment('عطلة أسبوعية');
            }
            if (!Schema::hasColumn('attendance_days', 'is_on_leave')) {
                $table->boolean('is_on_leave')->default(false)->after('is_weekly_off')
                    ->comment('في إجازة معتمدة');
            }
            if (!Schema::hasColumn('attendance_days', 'leave_request_id')) {
                $table->unsignedBigInteger('leave_request_id')->nullable()->after('is_on_leave');
            }

            // معالجة الـ cron
            if (!Schema::hasColumn('attendance_days', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('leave_request_id')
                    ->comment('متى عالج cron هذا اليوم آخر مرة');
            }
            if (!Schema::hasColumn('attendance_days', 'auto_generated')) {
                $table->boolean('auto_generated')->default(false)->after('processed_at')
                    ->comment('هل أُنشئ هذا اليوم تلقائياً من cron؟');
            }

            // الإحداثيات الجغرافية لحظة الحضور
            if (!Schema::hasColumn('attendance_days', 'checkin_latitude')) {
                $table->decimal('checkin_latitude', 10, 7)->nullable()->after('auto_generated');
            }
            if (!Schema::hasColumn('attendance_days', 'checkin_longitude')) {
                $table->decimal('checkin_longitude', 10, 7)->nullable()->after('checkin_latitude');
            }
            if (!Schema::hasColumn('attendance_days', 'checkout_latitude')) {
                $table->decimal('checkout_latitude', 10, 7)->nullable()->after('checkin_longitude');
            }
            if (!Schema::hasColumn('attendance_days', 'checkout_longitude')) {
                $table->decimal('checkout_longitude', 10, 7)->nullable()->after('checkout_latitude');
            }
        });

        // فهارس مهمة للأداء
        Schema::table('attendance_days', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('attendance_days'))->pluck('name')->toArray();
            if (!in_array('idx_att_emp_date', $indexes)) {
                if (! SchemaGuard::hasIndex('attendance_days', 'idx_att_emp_date')) {
                    $table->index(['employee_id', 'attendance_date'], 'idx_att_emp_date');
                }
            }
            if (!in_array('idx_att_status', $indexes)) {
                if (! SchemaGuard::hasIndex('attendance_days', 'idx_att_status')) {
                    $table->index(['status', 'attendance_date'], 'idx_att_status');
                }
            }
            if (!in_array('idx_att_processed', $indexes)) {
                if (! SchemaGuard::hasIndex('attendance_days', 'idx_att_processed')) {
                    $table->index(['processed_at'], 'idx_att_processed');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            foreach ([
                'deduction_id', 'late_minutes', 'early_leave_minutes', 'overtime_minutes',
                'expected_check_in', 'expected_check_out',
                'is_holiday', 'is_weekly_off', 'is_on_leave', 'leave_request_id',
                'processed_at', 'auto_generated',
                'checkin_latitude', 'checkin_longitude', 'checkout_latitude', 'checkout_longitude',
            ] as $col) {
                if (Schema::hasColumn('attendance_days', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
