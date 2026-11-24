<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مهمّة «توليد الفواتير الدورية» كانت تشير إلى أمرٍ لا وجود له.
 *
 * صفُّها في `cron_jobs` يحمل `handler = 'invoices:generate-recurring'`،
 * وهو اسمٌ لا يقابله أمرُ Artisan في المشروع، ولا هو مسجَّلٌ في
 * `App\Services\Cron\CronRegistry::commands()` — وتلك قائمةٌ بيضاء تردّ
 * ما ليس فيها **قبل** أن يصل إلى `Artisan::call`.
 *
 * فالمهمّة كانت مزدوجة العطل: معطَّلةً (`disabled`) ولو فُعِّلت لأخفقت.
 * والنتيجة أنّ الاشتراكات الدورية لا يُصدَر منها شيء أبداً — يُنشئ
 * المستخدم اشتراكاً وينتظر فواتير لا تأتي.
 *
 * الأمر الحقيقي `sales:generate-periodic-invoices`، ويقوده
 * `App\Services\Invoices\PeriodicInvoiceGenerator`.
 *
 * **لا تُفعَّل المهمّة هنا.** تفعيل مهمّةٍ مجدولة قرارُ المسؤول من شاشة
 * «المهام المجدولة»: تشغيلُها يُصدر فواتير بيعٍ حقيقية بأثرٍ رجعيّ لكل
 * اشتراكٍ مستحقّ. الهجرة تُصلح الوجهة فقط.
 */
return new class extends Migration
{
    private const WRONG = 'invoices:generate-recurring';

    private const RIGHT = 'sales:generate-periodic-invoices';

    public function up(): void
    {
        if (! Schema::hasTable('cron_jobs')) {
            return;
        }

        DB::table('cron_jobs')
            ->where('key', 'recurring_invoices')
            ->where('handler', self::WRONG)
            ->update(['handler' => self::RIGHT, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cron_jobs')) {
            return;
        }

        DB::table('cron_jobs')
            ->where('key', 'recurring_invoices')
            ->where('handler', self::RIGHT)
            ->update(['handler' => self::WRONG, 'updated_at' => now()]);
    }
};
