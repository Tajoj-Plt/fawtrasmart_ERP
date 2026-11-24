<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Client;
use App\Support\AccountingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ربط العملاء القائمين بشجرة الحسابات.
 *
 * **المشكلة**
 *
 * إنشاء الحساب الفرعي للعميل يقع في `ClientController::store()` وحده —
 * أي أن العميل الذي يدخل النظام من غير تلك الشاشة (استيراد، بذرة، هجرة
 * من نظام سابق، أو إنشاء من واجهة أخرى) يبقى بلا حساب في الشجرة.
 *
 * النتيجة على أرض الواقع: ألفٌ وخمسمئة عميل في الجدول، وصفرُ حساب
 * مرتبط بعميل. فشاشة «سند صرف لعميل» تفتح على قائمةٍ فارغة، ولا سبيل
 * إلى إثبات دينٍ على عميل ولا تسويته، ولا يظهر لأيٍّ منهم رصيدٌ في
 * دليل الحسابات.
 *
 * **ما يفعله هذا الأمر**
 *
 * يُنشئ لكل عميلٍ بلا حساب حساباً فرعياً تحت الحساب المضبوط في
 * «الحسابات المحاسبية» تحت المفتاح `clients_account`، بنفس الخصائص
 * التي تُنشئها شاشة العملاء: النوع والرصيد الافتتاحي ونوعه من الأب.
 *
 * الأمر **قابل للتكرار بلا ضرر**: يتخطّى كل عميلٍ له حسابٌ أصلاً.
 *
 *   php artisan clients:backfill-accounts --dry-run   ← معاينة بلا كتابة
 *   php artisan clients:backfill-accounts             ← التنفيذ
 *   php artisan clients:backfill-accounts --limit=50  ← دفعة محدودة
 */
class BackfillClientAccounts extends Command
{
    protected $signature = 'clients:backfill-accounts
                            {--dry-run : عرض ما سيحدث بلا كتابة}
                            {--limit= : الاكتفاء بهذا العدد من العملاء}';

    protected $description = 'إنشاء حسابات شجرة للعملاء الذين لا حساب لهم وربطها بهم';

    public function handle(): int
    {
        $parent = AccountingSettings::account('clients_account');

        if (! $parent) {
            $this->error('حساب العملاء غير مضبوط في «الإعدادات ← الحسابات المحاسبية» (المفتاح clients_account).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->line('الحساب الأب: <info>'.$parent->code.'</info> — '.$parent->name);

        // العملاء الذين لا حساب لهم بعد
        $linked = Account::whereNotNull('client_id')->pluck('client_id')->all();

        $query = Client::query()
            ->when($linked, fn ($q) => $q->whereNotIn('id', $linked))
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('كل العملاء مرتبطون بحساباتهم — لا شيء ليُنشأ.');

            return self::SUCCESS;
        }

        if ($limit) {
            $query->limit($limit);
        }

        $this->line('عملاء بلا حساب: <comment>'.number_format($total).'</comment>'
            .($limit ? '  (سيُعالَج '.number_format(min($limit, $total)).')' : ''));

        if ($dryRun) {
            $this->newLine();
            $this->warn('وضع المعاينة — لن يُكتب شيء.');
        }

        /*
            الأكواد المحجوزة تُقرأ مرّة واحدة.

            شاشة العملاء تسأل القاعدة عن آخر كودٍ ثم تفحص التعارض في حلقة
            `while` لكل عميل — وهو استعلامان في الأقل لكل صفّ. ألفٌ وخمسمئة
            عميل تعني ثلاثة آلاف استعلام. تُجمع هنا مرّةً ويُتابَع العدّاد
            في الذاكرة.
        */
        $taken = Account::pluck('code')->flip();

        // آخر تسلسل مستعمل تحت هذا الأب، ليُتابع لا ليُعاد من الأول
        $sequence = $this->lastSequenceUnder($parent, $taken);

        $created = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($limit ? min($limit, $total) : $total);
        $bar->start();

        $query->chunkById(200, function ($clients) use (
            $parent, $dryRun, &$taken, &$sequence, &$created, &$skipped, $bar
        ) {
            $rows = [];
            $now = now();

            foreach ($clients as $client) {
                $name = trim((string) ($client->trade_name
                    ?: trim(($client->first_name ?? '').' '.($client->last_name ?? ''))));

                if ($name === '') {
                    // حسابٌ بلا اسم لا يُقرأ في الشجرة ولا يُبحث عنه
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // كودٌ حرّ: يتخطّى ما حُجز من قبلُ بأي صيغة
                do {
                    $sequence++;
                    $code = $parent->code.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                } while ($taken->has($code));

                $taken[$code] = true;

                $rows[] = [
                    'name' => $name,
                    'code' => $code,
                    'client_id' => $client->id,
                    'parent_id' => $parent->id,
                    'account_type' => $parent->account_type,
                    'balance_type' => 'debit',
                    'opening_balance' => 0,
                    'opening_balance_type' => $parent->balance_type,
                    'balance' => 0,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $created++;
                $bar->advance();
            }

            if ($rows && ! $dryRun) {
                // إدراجٌ بالجملة: خمسةَ عشرَ استعلاماً بدل ألفٍ وخمسمئة
                DB::table('accounts')->insert($rows);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'سيُنشأ: ' : 'أُنشئ: ').number_format($created).' حساب عميل');

        if ($skipped) {
            $this->warn('تُخطّي '.number_format($skipped).' عميلاً بلا اسم.');
        }

        if ($dryRun) {
            $this->line('لتنفيذ العملية فعلاً: <info>php artisan clients:backfill-accounts</info>');
        }

        return self::SUCCESS;
    }

    /**
     * آخر تسلسل مستعمل تحت الحساب الأب.
     *
     * يُقرأ من الأكواد القائمة لا من عددها: حسابٌ حُذف يترك فجوة، والعدّ
     * وحده يعطي كوداً محجوزاً.
     */
    private function lastSequenceUnder(Account $parent, $taken): int
    {
        $prefix = $parent->code;
        $highest = 0;

        foreach ($taken->keys() as $code) {
            if (! str_starts_with((string) $code, $prefix)) {
                continue;
            }

            $tail = substr((string) $code, strlen($prefix));

            if ($tail !== '' && ctype_digit($tail)) {
                $highest = max($highest, (int) $tail);
            }
        }

        return $highest;
    }
}
