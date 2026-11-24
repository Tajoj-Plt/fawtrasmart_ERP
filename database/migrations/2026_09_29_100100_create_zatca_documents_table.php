<?php

use App\Services\Cron\CronScheduleParser;
use Cron\CronExpression;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ المستندات المرسَلة إلى هيئة الزكاة والضريبة والجمارك.
 *
 * جدولٌ مستقلّ لا أعمدةٌ على `invoices`: المستند الواحد قد يُرسَل أكثر من
 * مرّة (رُفض فصُحِّح فأُعيد)، وكلّ إرسالٍ حلقةٌ في سلسلة الهيئة لها عدّادها
 * وبصمتها — فالمرفوض يبقى في السلسلة ولا يُمحى. والإشعار الدائن في جدولٍ
 * غير جدول الفواتير، فالربط بـ`document_type` + `document_id`.
 *
 * `icv` فريدٌ في البيئة الواحدة: هو عدّاد الهيئة، وتكراره يكسر السلسلة.
 *
 * ══════════ صفّ `cron_jobs` ══════════
 *
 * يُضاف **نشطاً**: الأمر لا يفعل شيئاً ما دامت الخدمة معطَّلة أو غير مسجَّلة،
 * ووجوده هو ما يضمن بلوغ الفاتورة المبسّطة الهيئةَ خلال المهلة النظامية إن
 * انقطع الاتصال لحظة إنشائها.
 */
return new class extends Migration
{
    private const CRON_KEY = 'zatca_submit_pending';

    public function up(): void
    {
        if (! Schema::hasTable('zatca_documents')) {
            Schema::create('zatca_documents', function (Blueprint $table) {
                $table->id();

                // invoice | credit_note
                $table->string('document_type', 30);
                $table->unsignedBigInteger('document_id');
                $table->string('document_number', 60)->nullable();

                $table->string('environment', 20);
                // standard ضريبية (اعتماد) | simplified مبسّطة (إبلاغ)
                $table->string('profile', 12);
                // invoice | credit | debit
                $table->string('kind', 12);

                $table->uuid('uuid');
                $table->unsignedBigInteger('icv');
                $table->string('invoice_hash', 100);
                $table->string('previous_hash', 100);

                $table->text('qr')->nullable();
                $table->longText('signed_xml');
                $table->longText('cleared_xml')->nullable();

                // pending | reported | cleared | rejected | failed
                $table->string('status', 12)->default('pending');
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                $table->text('last_error')->nullable();

                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamp('submitted_at')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['environment', 'icv'], 'zatca_documents_env_icv_unique');
                $table->index(['document_type', 'document_id'], 'zatca_documents_document_index');
                $table->index(['status', 'environment'], 'zatca_documents_status_index');
            });
        }

        $this->registerCronJob();
    }

    public function down(): void
    {
        // السجلّ لا يُسقَط: فيه ما أُرسل إلى الهيئة وبصمات سلسلته، وإعادة
        // بنائه مستحيلة

        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')->where('key', self::CRON_KEY)->delete();
        }
    }

    private function registerCronJob(): void
    {
        if (! Schema::hasTable('cron_jobs') || DB::table('cron_jobs')->where('key', self::CRON_KEY)->exists()) {
            return;
        }

        $expression = CronScheduleParser::build('every_15_minutes');

        try {
            $next = (new CronExpression($expression))->getNextRunDate(now())->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $next = null;
        }

        $row = [
            'key' => self::CRON_KEY,
            'name' => 'إرسال الفواتير الإلكترونية المعلَّقة',
            'description' => 'يعيد إرسال ما تعذّر بلوغه هيئة الزكاة والضريبة والجمارك، ويرسل ما لم يُرسَل من الفواتير حين يكون الإرسال التلقائي مفعَّلاً. لا يفعل شيئاً والخدمة معطَّلة.',
            'type' => 'sales',
            'handler' => 'zatca:submit-pending',
            'parameters' => json_encode([]),
            'schedule_type' => 'every_15_minutes',
            'cron_expression' => $expression,
            'schedule_time' => null,
            'schedule_day' => null,
            'status' => 'active',
            'is_system' => true,
            'is_protected' => false,
            'prevent_overlap' => true,
            'timeout' => 600,
            'notify_on_failure' => true,
            'notify_on_success' => false,
            'next_run_at' => $next,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // أعمدة أُضيفت بترحيلاتٍ لاحقة قد تنقص قاعدةً قديمة
        $existing = Schema::getColumnListing('cron_jobs');

        DB::table('cron_jobs')->insert(array_intersect_key($row, array_flip($existing)));
    }
};
