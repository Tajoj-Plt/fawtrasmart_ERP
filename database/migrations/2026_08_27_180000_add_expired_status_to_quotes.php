<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالة «منتهي الصلاحية» لعروض الأسعار — ومعها تفعيل مهمّتها المجدولة.
 *
 * `quotes.expiry_date` كان تاريخاً معروضاً لا أثر له: يمرّ الموعد ويبقى
 * العرض «قيد الانتظار» أو «مقبول» إلى الأبد، فيُحوَّل إلى فاتورة بأسعارٍ
 * انقضت مدّتها. ولا حالةَ في التعداد تصف ذلك — و«ملغى» ليست هي: الإلغاء
 * قرارٌ، والانتهاء وقتٌ مضى.
 *
 * والصفوف القائمة لا تتغيّر بالترحيل: التعداد يتّسع فحسب، ثمّ يتولّى الأمر
 * `quotations:expire` نقل ما استحقّ — فيبقى الترحيل قابلاً للتراجع ما لم
 * يُشغَّل الأمر.
 *
 * ── تفعيل المهمّة ──
 *
 * صفّ `cron_jobs.quotations_expiry` مبذورٌ منذ `CronJobsSeeder` معطَّلاً،
 * لأنّ أمره لم يكن مكتوباً بعد. كُتب الآن، فيُفعَّل الصفّ هنا — وهو المطلوب
 * من هذا العمل كلِّه: أن تتغيّر الحالة **بلا تدخّل**. ومن أراد إيقافها
 * يوقفها من «المهام المجدولة»، والتراجع يعيدها معطَّلة كما كانت.
 */
return new class extends Migration
{
    private const STATUSES = "'Draft','Pending','Approved','Converted_to_Invoice','Cancelled','Expired'";

    private const STATUSES_BEFORE = "'Draft','Pending','Approved','Converted_to_Invoice','Cancelled'";

    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('quotes')) {
            DB::statement('ALTER TABLE `quotes` MODIFY `status` ENUM('.self::STATUSES.") NOT NULL DEFAULT 'Draft'");
        }

        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')
                ->where('key', 'quotations_expiry')
                ->where('status', 'disabled')
                ->update(['status' => 'active', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('quotes')) {
            // ما صار «منتهياً» يعود «مقبولاً»: قيمةٌ خارج التعداد تُقرأ فارغة
            DB::table('quotes')->where('status', 'Expired')->update(['status' => 'Approved']);

            DB::statement('ALTER TABLE `quotes` MODIFY `status` ENUM('.self::STATUSES_BEFORE.") NOT NULL DEFAULT 'Draft'");
        }

        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')
                ->where('key', 'quotations_expiry')
                ->update(['status' => 'disabled', 'updated_at' => now()]);
        }
    }
};
