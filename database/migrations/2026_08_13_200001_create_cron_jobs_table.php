<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ المهام المجدولة.
 *
 * كانت الجدولة كلها مكتوبة داخل `app/Console/Kernel.php`، فأي تغيير في وقت
 * مهمة أو إيقافها يتطلّب تعديل الكود ونشره. هذا الجدول ينقل القرار إلى
 * قاعدة البيانات: `cron:run-due` يقرأ منه كل دقيقة وينفّذ ما استحقّ.
 *
 * `handler` ليس أمراً حرّاً — يُطابَق دائماً على سجلّ الأوامر المسموحة في
 * `App\Services\Cron\CronRegistry` قبل التنفيذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_jobs')) {
            Schema::create('cron_jobs', function (Blueprint $table) {
                $table->id();

                // مُعرّف ثابت لا يتغيّر بتغيّر الاسم المعروض — عليه تعتمد المهام
                // النظامية في الـ seeder فلا تتكرّر عند إعادة التشغيل
                $table->string('key', 120)->unique();
                $table->string('name');
                $table->text('description')->nullable();

                // command | job | backup | notification | report | cleanup | maintenance | custom
                $table->string('type', 30)->default('command');

                // اسم الأمر أو صنف الـ Job — يجب أن يكون ضمن القائمة البيضاء
                $table->string('handler');
                $table->json('parameters')->nullable();

                // every_minute | every_5_minutes | ... | hourly | daily | weekly | monthly | cron
                $table->string('schedule_type', 30)->default('daily');
                // مصدر الحقيقة عند التنفيذ: يُشتَقّ من schedule_type أو يُدخَل يدوياً
                $table->string('cron_expression', 120)->default('0 0 * * *');
                $table->time('schedule_time')->nullable();
                $table->unsignedTinyInteger('schedule_day')->nullable();
                $table->string('timezone', 64)->nullable();

                // active | paused | disabled — «موقوف» مؤقّت و«معطّل» قرار إداري
                $table->string('status', 20)->default('active');
                // المهام الجوهرية للنظام: تُعطَّل ولا تُحذف
                $table->boolean('is_system')->default(false);

                $table->boolean('prevent_overlap')->default(true);
                $table->unsignedInteger('timeout')->default(300);
                $table->boolean('notify_on_failure')->default(true);
                $table->boolean('notify_on_success')->default(false);

                // لقطة آخر تنفيذ — مكرّرة عمداً من جدول التنفيذات لتفادي
                // استعلام فرعي لكل صفّ في شاشة القائمة
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('last_finished_at')->nullable();
                $table->string('last_status', 20)->nullable();
                $table->unsignedBigInteger('last_duration_ms')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('next_run_at')->nullable();

                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failure_count')->default(0);
                // يُصفَّر عند أول نجاح — عليه يُبنى تنبيه «مهمة متعثّرة»
                $table->unsignedInteger('consecutive_failures')->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                // `cron:run-due` يستعلم كل دقيقة عن المهام النشطة المستحقّة
                $table->index(['status', 'next_run_at']);
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
