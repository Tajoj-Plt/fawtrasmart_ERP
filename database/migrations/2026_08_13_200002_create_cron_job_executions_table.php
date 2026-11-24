<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ تنفيذ المهام المجدولة.
 *
 * لم يكن في النظام أي أثر لتشغيل المهام: لا آخر تشغيل ولا مدّة ولا سبب فشل.
 * كل تشغيل — مجدولاً كان أو يدوياً — يكتب صفّاً هنا قبل البدء ويحدّثه عند
 * الانتهاء، فيبقى الأثر حتى لو انهار التنفيذ في منتصفه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_job_executions')) {
            Schema::create('cron_job_executions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('cron_job_id')->constrained('cron_jobs')->cascadeOnDelete();
                // نسخة من الاسم وقت التنفيذ: تعديل اسم المهمة لاحقاً يجب ألا
                // يعيد كتابة تاريخها
                $table->string('task_name');

                // schedule | manual — يفصل تشغيل المجدول عن ضغطة «شغّل الآن»
                $table->string('trigger', 20)->default('schedule');
                // pending | running | success | failed | skipped
                $table->string('status', 20)->default('pending');

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedBigInteger('duration_ms')->nullable();
                $table->integer('exit_code')->nullable();

                $table->longText('output')->nullable();
                $table->text('error_message')->nullable();

                $table->string('environment', 40)->nullable();
                $table->string('hostname', 120)->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                // شاشة السجلّ تُرتَّب دائماً بالأحدث، وتُصفّى بالمهمة أو الحالة
                $table->index(['cron_job_id', 'started_at']);
                $table->index(['status', 'started_at']);
                $table->index('started_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_job_executions');
    }
};
