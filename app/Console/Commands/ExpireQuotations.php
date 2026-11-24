<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Support\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * إنهاء عروض الأسعار التي انقضت مدّة صلاحيتها.
 *
 * `quotes.expiry_date` كان تاريخاً معروضاً بلا أثر: يمرّ الموعد ويبقى العرض
 * «مقبولاً» فيُحوَّل إلى فاتورة بأسعارٍ انقضت. هذا الأمر ينقله إلى
 * `Quote::STATUS_EXPIRED`، ومن هناك يمنع `blocksConversion()` تحويلَه —
 * ويبقى قابلاً للتعديل، فمن أراد إحياءه مدّ تاريخه وأعاد حالته.
 *
 * ── لماذا مهلة ──
 *
 * `--grace` أيامٌ بعد تاريخ الصلاحية قبل أن تنقلب الحالة. منشأةٌ تتفاوض
 * يوماً أو يومين بعد الموعد لا تريد العرض ينقلب تحت يد مندوبها وهو يكلّم
 * العميل. صفرٌ = ينتهي بانقضاء يومه.
 *
 * ── المستندان ──
 *
 * الجدول يحمل عروض الأسعار وأوامر البيع. المهمّة على **العروض** وحدها:
 * أمرُ البيع التزامٌ قُبِل، ولا يسقط بمضيّ تاريخٍ كان اقتراحاً في العرض.
 *
 * يُشغَّل من «المهام المجدولة» يومياً — انظر `CronRegistry::commands()`
 * وصفَّ `quotations_expiry` في `CronJobsSeeder`.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire
                            {--grace=0 : أيام المهلة بعد تاريخ الصلاحية قبل الإنهاء}
                            {--dry-run : يعرض ما سينتهي دون كتابة}';

    protected $description = 'ينقل عروض الأسعار التي تجاوزت مدّة صلاحيتها إلى حالة «منتهي الصلاحية»';

    public function handle(): int
    {
        $grace = max(0, (int) $this->option('grace'));
        $dryRun = (bool) $this->option('dry-run');

        /* الحدّ في الاستعلام لا في PHP: الجدول قد يحمل عشرات الآلاف من
           الصفوف، وتحميلها كلّها لفحص تاريخٍ إسرافٌ لا داعي له. والمقارنة
           بنهاية اليوم: عرضٌ صلاحيته اليوم لا ينتهي اليوم. */
        $cutoff = now()->startOfDay()->subDays($grace);

        $query = Quote::query()
            ->ofType(Quote::TYPE_QUOTE)
            ->whereNotNull('expiry_date')
            ->whereIn('status', Quote::EXPIRABLE_STATUSES)
            ->whereDate('expiry_date', '<', $cutoff);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('لا عروض أسعار منتهية الصلاحية.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('عرضٌ فقط — لا يُكتب شيء ('.$total.' عرضاً):');

            $query->orderBy('expiry_date')->limit(50)->get()->each(function (Quote $quote) {
                $this->line(sprintf(
                    '  #%s — %s — انتهى في %s — الحالة %s',
                    $quote->quote_number ?: $quote->id,
                    optional($quote->client)->trade_name ?? optional($quote->client)->name ?? '—',
                    $quote->expiry_date?->format('Y-m-d') ?? '—',
                    $quote->status_key
                ));
            });

            return self::SUCCESS;
        }

        $expired = 0;

        /* دفعاتٌ بمعرّفٍ متصاعد لا `chunk()`: التحديث يُخرج الصفَّ من شرط
           الاستعلام نفسِه، فترقيم الصفحات يقفز فوق نصف الصفوف. */
        $query->orderBy('id')->chunkById(200, function ($quotes) use (&$expired) {
            foreach ($quotes as $quote) {
                $before = $quote->status_key;

                $quote->status = Quote::STATUS_EXPIRED;
                $quote->save();

                $expired++;

                /* أثرٌ في سجلّ النشاط: حالةٌ تتغيّر بلا أن يلمسها أحد
                   تحتاج إلى ما يقول من غيّرها ولماذا، وإلّا اتُّهم الموظّف. */
                OperationLog::record(
                    'updated',
                    'quote',
                    $quote,
                    'إنهاء عرض السعر رقم '.($quote->quote_number ?: $quote->id).' لانقضاء مدّة صلاحيته',
                    ['old' => ['status' => $before], 'new' => ['status' => Quote::STATUS_EXPIRED]]
                );
            }
        });

        Log::info('quotations:expire — تم إنهاء '.$expired.' عرض سعر منتهي الصلاحية.', [
            'grace_days' => $grace,
            'cutoff' => $cutoff->toDateString(),
        ]);

        $this->info('انتهى '.$expired.' عرض سعر (المهلة '.$grace.' يوم).');

        return self::SUCCESS;
    }
}
