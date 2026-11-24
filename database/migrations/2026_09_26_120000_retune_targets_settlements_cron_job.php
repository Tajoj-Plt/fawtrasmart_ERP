<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مهمة «تسوية أهداف المبيعات والتحصيلات» صارت مفتاحَ الصرف التلقائي
 * الوحيد (أُزيلت الجدولة الديناميكية من `Console\Kernel`). كانت مبذورةً
 * «كل ساعة عند الدقيقة 20»، فوقتُ تسويةٍ مضبوطٌ على 02:10 كان يُصرف 02:20.
 * تُضبط كل عشر دقائق — الأمرُ يفحص موعدَه بنفسه ويصرف مرّةً في اليوم.
 *
 * لا تمسّ الحالة (مفعّلة/معطّلة): ذلك قرارُ المسؤول من الشاشة. ولا تمسّ
 * صفّاً غيّر المسؤولُ جدولتَه بنفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_jobs')) {
            return;
        }

        DB::table('cron_jobs')
            ->where('key', 'targets_settlements')
            ->where('cron_expression', '20 * * * *')
            ->update([
                'schedule_type' => 'every_10_minutes',
                'cron_expression' => '*/10 * * * *',
                'schedule_time' => null,
                'description' => 'مفتاح الصرف التلقائي لعمولات الأهداف: تفعيلُها هنا هو ما يجعل العمولات تُصرف في يوم التسوية ووقتها المضبوطَين في «إعدادات الأهداف» (سند صرف وقيد لكل موظف). تعمل كل عشر دقائق لكنها لا تصرف إلا في موعدها، ومرّةً واحدة في اليوم.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // لا رجوع: الجدولة السابقة لم تكن أصحّ، وإعادتها تُعيد التأخير
    }
};
