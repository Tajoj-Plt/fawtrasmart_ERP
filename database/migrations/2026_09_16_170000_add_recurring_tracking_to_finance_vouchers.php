<?php

use App\Services\Cron\CronScheduleParser;
use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تتبّع نسخ سندات القبض والصرف المكرَّرة — ومهمّةُ إصدارها.
 *
 * ══════════ لماذا عمودان على السند لا جدولُ تتبّع؟ ══════════
 *
 * النسخةُ المولَّدة **سندٌ كامل** في جدوله — بقيدها وخزينتها ورقمها —
 * لا سجلُّ إصدارٍ يشير إلى سند. فجدولٌ جانبيّ كان سيحمل (الأصل، الرقم،
 * السند) ويتطلّب كتابتين يجب أن تتّفقا: سندٌ حُفظ ثم أخفقت كتابةُ
 * سجلّه يُصدَر ثانيةً في التشغيل التالي.
 *
 * أمّا العمودان فيُكتبان **في إدراج السند نفسه** (يضبطهما المولّد في حدث
 * `creating`)، والفريدُ على (`recurring_parent_id`, `recurring_sequence`)
 * يجعل إصدار النسخة نفسها مرّتين مستحيلاً على مستوى القاعدة — ولو شُغّلت
 * المهمّة مرّتين متزامنتين. و`NULL` لا يصطدم بـ`NULL` في الفريد، فالسندات
 * العادية لا تتأثّر.
 *
 * وشاشة السند تقرأ نسخها بعلاقةٍ واحدة (`recurringCopies`)، و«التالية في»
 * تُحسب من تاريخ الأصل ودورته — فلا حاجة لعمود `last_generated_at` يُخزّن
 * ما يُشتقّ.
 *
 * ══════════ صفّ `cron_jobs` ══════════
 *
 * `CronJobsSeeder` يحمل التعريف للتثبيتات الجديدة ولـ«تحديث النظام». وهنا
 * يُضاف الصفّ للتثبيتات القائمة إن غاب — **نشطاً**: لا يُصدر إلّا ما علَّمه
 * المستخدم «سند مكرَّر» بنفسه، والشاشة تَعِده بذلك صراحةً. ومن أراد إيقافه
 * يوقفه من «المهام المجدولة»؛ وصفٌّ قائمٌ لا يُمسّ.
 */
return new class extends Migration
{
    private const TABLES = ['receipts', 'expenses'];

    private const CRON_KEY = 'finance_recurring_vouchers';

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'recurring_parent_id')) {
                    $blueprint->unsignedBigInteger('recurring_parent_id')->nullable()->after('end_date')
                        ->comment('السند المكرَّر الأصل الذي وُلِّدت منه هذه النسخة');
                }

                if (! Schema::hasColumn($table, 'recurring_sequence')) {
                    $blueprint->unsignedInteger('recurring_sequence')->nullable()->after('recurring_parent_id')
                        ->comment('رقم النسخة في السلسلة — 1 أوّل تكرار بعد الأصل');
                }
            });

            $unique = $table.'_recurring_occurrence_unique';

            if (! SchemaGuard::hasIndex($table, $unique)) {
                Schema::table($table, function (Blueprint $blueprint) use ($unique) {
                    // يخدم الفهرسَ على الأصل أيضاً: عمودُه الأوّل
                    $blueprint->unique(['recurring_parent_id', 'recurring_sequence'], $unique);
                });
            }
        }

        $this->registerCronJob();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $unique = $table.'_recurring_occurrence_unique';

            if (SchemaGuard::hasIndex($table, $unique)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($unique));
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['recurring_sequence', 'recurring_parent_id'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')->where('key', self::CRON_KEY)->delete();
        }
    }

    private function registerCronJob(): void
    {
        if (! Schema::hasTable('cron_jobs') || DB::table('cron_jobs')->where('key', self::CRON_KEY)->exists()) {
            return;
        }

        $expression = CronScheduleParser::build('daily', '00:35');

        try {
            $next = (new \Cron\CronExpression($expression))->getNextRunDate(now())->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $next = null;
        }

        $row = [
            'key' => self::CRON_KEY,
            'name' => 'إصدار سندات القبض والصرف المكرَّرة',
            'description' => 'يُصدر النسخة المستحقّة من كل سند قبض أو صرف مكرَّر بمسار الحفظ نفسه (القيد والخزينة والترقيم)، حتى تاريخ نهاية التكرار، ويتخطّى الفترات المقفلة.',
            'type' => 'maintenance',
            'handler' => 'finance:generate-recurring-vouchers',
            'parameters' => json_encode([]),
            'schedule_type' => 'daily',
            'cron_expression' => $expression,
            'schedule_time' => '00:35',
            'schedule_day' => null,
            'status' => 'active',
            'is_system' => true,
            'is_protected' => false,
            'prevent_overlap' => true,
            'timeout' => 900,
            'notify_on_failure' => true,
            'notify_on_success' => false,
            'next_run_at' => $next,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // الأعمدة تغيّرت عبر الهجرات — يُكتب الموجود منها وحده
        $columns = Schema::getColumnListing('cron_jobs');

        DB::table('cron_jobs')->insert(array_intersect_key($row, array_flip($columns)));
    }
};
