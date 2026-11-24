<?php

namespace App\Console\Commands;

use Database\Seeders\MissingAccountsSeeder;
use Illuminate\Console\Command;

/**
 * زرعُ حسابات الشجرة القياسية الناقصة — أو معاينتُها فقط.
 *
 * الغلافُ موجودٌ من أجل `--dry-run`: `db:seed --class` لا يمرّر خياراً إلى
 * البذرة، وشاشة «تحديث النظام» تُشغّل أوامرَ بوسائطَ ثابتة. فصار للخطوة
 * زرّان: معاينةٌ تُري ما سيُضاف، وتنفيذٌ يضيفه.
 *
 * @see MissingAccountsSeeder
 */
class SeedMissingAccounts extends Command
{
    protected $signature = 'accounts:seed-missing {--dry-run : عرض ما سيُضاف دون كتابة شيء}';

    protected $description = 'إضافة حسابات شجرة الحسابات القياسية الناقصة وحدها — لا يُمسّ حسابٌ قائم';

    public function handle(): int
    {
        $seeder = new MissingAccountsSeeder;
        $seeder->dryRun = (bool) $this->option('dry-run');
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
