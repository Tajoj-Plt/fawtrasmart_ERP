<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدولُ طلبات تصحيح الحضور — طلب → اعتماد → أثر، لا تعديلٌ مباشر.
 *
 * يخزّن الحقول المطلوب تغييرها على `attendance_days` (نفس حقول شاشة
 * التعديل اليدوي: الحالة، أوقات الوردية، الحضور/الانصراف، الإجازة)
 * إلى جانب لقطةٍ من القيم الحالية وقت تقديم الطلب — للتدقيق ولعرض
 * "قبل/بعد" على المعتمِد. لا يُكتب على `attendance_days` نفسها إلا عند
 * الاعتماد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_correction_requests')) {
            Schema::create('attendance_correction_requests', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('employee_id');
                $table->date('attendance_date');

                // الحقول المطلوب تطبيقها — نفس حقول AttendanceDaysRequest
                $table->string('requested_status', 20)->nullable();
                $table->time('requested_start_shift')->nullable();
                $table->time('requested_end_shift')->nullable();
                $table->time('requested_check_in_time')->nullable();
                $table->time('requested_check_out_time')->nullable();
                $table->unsignedTinyInteger('requested_absence_type')->nullable();
                $table->integer('requested_absence_balance')->nullable();
                $table->text('requested_notes')->nullable();

                // لقطةٌ من القيم الحالية وقت تقديم الطلب — للتدقيق وعرض قبل/بعد
                $table->string('current_status', 20)->nullable();
                $table->time('current_start_shift')->nullable();
                $table->time('current_end_shift')->nullable();
                $table->time('current_check_in_time')->nullable();
                $table->time('current_check_out_time')->nullable();
                $table->unsignedTinyInteger('current_absence_type')->nullable();
                $table->integer('current_absence_balance')->nullable();

                $table->text('reason');

                $table->string('status', 20)->default('pending');

                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index(['employee_id', 'attendance_date']);

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
