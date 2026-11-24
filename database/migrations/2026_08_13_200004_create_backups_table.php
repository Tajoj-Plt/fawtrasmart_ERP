<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ النسخ الاحتياطية.
 *
 * `spatie/laravel-backup` يكتب الأرشيف على القرص ولا يحتفظ بسجلّ في قاعدة
 * البيانات — `backup:list` يقرأ الملفات فقط. هذا الجدول يضيف ما تحتاجه
 * الواجهة: من أنشأها، ولماذا، وكم استغرقت، ولماذا فشلت إن فشلت، ويبقى
 * السجلّ قائماً بعد حذف الملف نفسه (`file_deleted_at`).
 *
 * لم يُنشأ جدول `backup_logs` منفصل: مخرجات التشغيل وأخطاؤه محفوظة هنا
 * وفي `cron_job_executions` للتشغيلات المجدولة، وجدول ثالث تكرار محض.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backups')) {
            Schema::create('backups', function (Blueprint $table) {
                $table->id();
                // يُستعمل في روابط التنزيل بدل المعرّف المتسلسل
                $table->uuid('uuid')->unique();

                // database | files | both
                $table->string('type', 20)->default('database');
                // manual | scheduled | pre_restore — الأخيرة نسخة الأمان
                // التلقائية التي تُؤخذ قبل أي استرجاع
                $table->string('trigger', 20)->default('manual');

                $table->string('disk', 60)->default('local');
                $table->string('path')->nullable();
                $table->string('file_name')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                // pending | running | completed | failed
                $table->string('status', 20)->default('pending');

                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('duration_ms')->nullable();

                $table->text('error_message')->nullable();
                $table->longText('output')->nullable();

                // يمنع التنظيف التلقائي من حذفها مهما بلغ عمرها
                $table->boolean('is_locked')->default(false);
                $table->timestamp('file_deleted_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                // المهمة المجدولة التي أنتجتها — الرابط بين شاشتَي Cron و Backup
                $table->foreignId('cron_job_id')->nullable()->constrained('cron_jobs')->nullOnDelete();

                $table->timestamps();

                // التنظيف يمرّ على المكتملة غير المحذوفة مرتّبةً بالأقدم
                $table->index(['status', 'created_at']);
                $table->index(['type', 'status']);
                $table->index('file_deleted_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
