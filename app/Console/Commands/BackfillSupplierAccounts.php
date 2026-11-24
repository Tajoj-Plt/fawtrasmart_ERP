<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Supplier;
use App\Support\AccountingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ربط المورّدين القائمين بشجرة الحسابات.
 *
 * **المشكلة** — نظيرةُ مشكلة العملاء حرفياً:
 *
 * `InvoicesPurchaseController` يُرحّل قيدَ فاتورة الشراء إلى حساب المورّد
 * نفسه: `Account::ledger()->where('supplier_id', …)->first()`. فالمورّد الذي يدخل
 * النظام من غير شاشة المورّدين — استيراداً أو بذرةً أو هجرةً — يبقى بلا
 * حساب، ويرتدّ الترحيلُ على `null`.
 *
 * وقد كان في هذه القاعدة **ثلاثةٌ** من اثنين وعشرين مورّداً لهم حسابات.
 *
 * لماذا أمرٌ مستقلٌّ لا خيارٌ في `clients:backfill-accounts`؟ لأنّ الأبَ
 * مختلف (`suppliers` لا `clients_account`)، واتّجاهَ الرصيد مختلف:
 * المورّد دائنٌ بطبعه والعميلُ مدين.
 *
 *   php artisan suppliers:backfill-accounts --dry-run   ← معاينة بلا كتابة
 *   php artisan suppliers:backfill-accounts             ← التنفيذ
 *
 * الأمر قابل للتكرار بلا ضرر: يتخطّى كل مورّدٍ له حسابٌ أصلاً.
 *
 * @see \App\Console\Commands\BackfillClientAccounts النظير للعملاء
 */
class BackfillSupplierAccounts extends Command
{
    protected $signature = 'suppliers:backfill-accounts
                            {--dry-run : عرض ما سيحدث بلا كتابة}
                            {--limit= : الاكتفاء بهذا العدد من المورّدين}';

    protected $description = 'إنشاء حسابات شجرة للمورّدين الذين لا حساب لهم وربطها بهم';

    public function handle(): int
    {
        $parent = AccountingSettings::account('suppliers');

        if (! $parent) {
            $this->error('حساب الموردين غير مضبوط في «الإعدادات ← الحسابات المحاسبية» (المفتاح suppliers).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->line('الحساب الأب: <info>'.$parent->code.'</info> — '.$parent->name);

        $linked = Account::whereNotNull('supplier_id')->pluck('supplier_id')->all();

        $query = Supplier::query()
            ->when($linked, fn ($q) => $q->whereNotIn('id', $linked))
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('كل المورّدين مرتبطون بحساباتهم — لا شيء ليُنشأ.');

            return self::SUCCESS;
        }

        if ($limit) {
            $query->limit($limit);
        }

        $this->line('مورّدون بلا حساب: <comment>'.number_format($total).'</comment>');

        if ($dryRun) {
            $this->newLine();
            $this->warn('وضع المعاينة — لن يُكتب شيء.');
        }

        // الأكواد المحجوزة مرّةً واحدة، ثمّ العدّاد في الذاكرة
        $taken = Account::pluck('code')->flip();
        $sequence = $this->lastSequenceUnder($parent, $taken);

        $created = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($limit ? min($limit, $total) : $total);
        $bar->start();

        $query->chunkById(200, function ($suppliers) use (
            $parent, $dryRun, &$taken, &$sequence, &$created, &$skipped, $bar
        ) {
            $rows = [];
            $now = now();

            foreach ($suppliers as $supplier) {
                $name = trim((string) ($supplier->trade_name
                    ?: trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? ''))));

                if ($name === '') {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                do {
                    $sequence++;
                    $code = $parent->code.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                } while ($taken->has($code));

                $taken[$code] = true;

                $rows[] = [
                    'name' => $name,
                    'code' => $code,
                    'supplier_id' => $supplier->id,
                    'parent_id' => $parent->id,
                    'account_type' => $parent->account_type,
                    // المورّد دائنٌ بطبعه — وهذا ما يفرق هذا الأمر عن نظيره
                    'balance_type' => 'credit',
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
                DB::table('accounts')->insert($rows);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'سيُنشأ: ' : 'أُنشئ: ').number_format($created).' حساب مورّد');

        if ($skipped) {
            $this->warn('تُخطّي '.number_format($skipped).' مورّداً بلا اسم.');
        }

        if ($dryRun) {
            $this->line('لتنفيذ العملية فعلاً: <info>php artisan suppliers:backfill-accounts</info>');
        }

        return self::SUCCESS;
    }

    /**
     * آخر تسلسل مستعمل تحت الحساب الأب.
     *
     * يُقرأ من الأكواد القائمة لا من عددها: حسابٌ حُذف يترك فجوة، والعدّ
     * وحده يُعيد كوداً محجوزاً.
     */
    private function lastSequenceUnder(Account $parent, $taken): int
    {
        $prefix = (string) $parent->code;
        $max = 0;

        foreach ($taken->keys() as $code) {
            if (! str_starts_with((string) $code, $prefix)) {
                continue;
            }

            $tail = substr((string) $code, strlen($prefix));

            if ($tail !== '' && ctype_digit($tail)) {
                $max = max($max, (int) $tail);
            }
        }

        return $max;
    }
}
