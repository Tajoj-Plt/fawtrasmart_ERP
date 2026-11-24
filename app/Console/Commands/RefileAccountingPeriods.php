<?php

namespace App\Console\Commands;

use App\Support\OrphanedPeriodDocuments;
use Illuminate\Console\Command;

/**
 * إلحاقُ المستندات التي خرجت عن التقويم بفتراتها.
 *
 * المستندُ يُختَم بفترته لحظةَ إنشائه. فإن لم تكن ثمّة فترةٌ تغطّي تاريخَه —
 * لأنّ التقويم لم يُرسَم بعد، أو لفجوةٍ بين سنتين — حُفظ بفترةٍ فارغة.
 *
 * وهذا الأمرُ يُلحقه بها **بعد** أن تُرسَم: يُنادى مرّةً بعد توليد فترات
 * السنة، فيُنسَب كلُّ مستندٍ إلى الفترة التي يقع تاريخُه فيها.
 *
 * ولا يمسّ مستنداً منسوباً: الشرطُ `accounting_period_id IS NULL` وحده —
 * فإعادةُ تشغيله لا تُعيد تصنيف ما صُنِّف، ولا تنقض إقفالاً حصل.
 */
class RefileAccountingPeriods extends Command
{
    protected $signature = 'accounting:refile-periods {--dry-run : يَعُدّ ولا يكتب}';

    protected $description = 'إلحاق المستندات التي بلا فترة محاسبية بفتراتها بعد رسم التقويم';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $before = OrphanedPeriodDocuments::summary();

        if ($before['total'] === 0) {
            $this->info('لا مستندَ خارج التقويم — كلُّها منسوبةٌ إلى فتراتها.');

            return self::SUCCESS;
        }

        $this->line('خارج التقويم قبل الإلحاق:');

        foreach ($before['tables'] as $table => $row) {
            $this->line(sprintf('  %-22s %s', $row['label'], number_format($row['count'])));
        }

        $this->newLine();

        $filed = OrphanedPeriodDocuments::refile($dryRun);

        if (! $filed) {
            $this->warn(
                'لا مستندَ يقع في فترةٍ معرَّفة. '
                .'ولّد فترات السنة من شاشة «الفترات المحاسبية»، وتحقّق من ألّا فجوةَ في التقويم.'
            );

            return self::SUCCESS;
        }

        $this->line($dryRun ? 'كان سيُلحَق:' : 'أُلحِق:');

        $total = 0;

        foreach ($filed as $table => $count) {
            $label = OrphanedPeriodDocuments::TABLES[$table][0] ?? $table;
            $this->line(sprintf('  %-22s %s', $label, number_format($count)));
            $total += $count;
        }

        $this->newLine();

        $remaining = OrphanedPeriodDocuments::summary()['total'];

        $this->info(sprintf(
            '%s %s مستنداً. والباقي خارج التقويم: %s.',
            $dryRun ? 'سيُلحَق' : 'أُلحِق',
            number_format($total),
            number_format($remaining)
        ));

        if ($remaining > 0) {
            $this->warn(
                'ما بقي تواريخُه لا تقع في أيّ فترة — إمّا فجوةٌ في التقويم، وإمّا تاريخٌ أُدخل سهواً. '
                .'يُرى في الشاشات باختيار «بلا فترة» في فلتر الفترة.'
            );
        }

        return self::SUCCESS;
    }
}
