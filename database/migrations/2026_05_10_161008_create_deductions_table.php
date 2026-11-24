<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظام الخصميات (الحسومات/الجزاءات) للموظفين.
 *
 * ملاحظة محاسبية:
 *   - الخصمية لا تنشئ قيداً محاسبياً مستقلاً.
 *   - يتم خصمها لاحقاً من قسيمة الراتب أو عند تسوية الراتب.
 *
 * حالات الخصمية:
 *   - pending            : قيد الانتظار
 *   - approved           : معتمدة
 *   - rejected           : مرفوضة
 *   - settled            : تمت تسويتها
 *   - partially_settled  : تمت تسويتها جزئياً
 *   - cancelled          : ملغاة
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deductions')) {
            Schema::create('deductions', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('employee_id');

                // النوع: absence | late | violation | custody_damage | admin_penalty | other
                $table->string('deduction_type', 40)->default('other');

                $table->decimal('amount', 14, 2);
                $table->decimal('settled_amount', 14, 2)->default(0);

                // 'pending', 'approved', 'rejected', 'settled', 'partially_settled', 'cancelled'
                $table->string('status', 30)->default('pending')->index();

                $table->date('date');
                $table->text('reason');
                $table->text('notes')->nullable();

                // المُنشِئ
                $table->unsignedBigInteger('created_by_user')->nullable();

                // الاعتماد / الرفض
                $table->unsignedBigInteger('approved_by_user')->nullable();
                $table->timestamp('approved_at')->nullable();

                $table->unsignedBigInteger('rejected_by_user')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();

                // الإلغاء
                $table->unsignedBigInteger('cancelled_by_user')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();

                // التسوية
                $table->timestamp('settled_at')->nullable();

                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index('deduction_type');
                $table->index('date');

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('created_by_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('approved_by_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('rejected_by_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by_user')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
