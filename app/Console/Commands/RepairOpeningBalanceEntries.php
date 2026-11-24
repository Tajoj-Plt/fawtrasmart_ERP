<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * إصلاح قيود الأرصدة الافتتاحية التي فقدت حساباتها.
 *
 * **العطب — وهو أخطر ممّا يبدو**
 *
 * قيدُ الرصيد الافتتاحي للعميل طرفان: حسابُ العميل، وحسابُ «أرصدة
 * افتتاحية». وقد حُذفت حساباتُ عملاء من الشجرة وبقيت قيودُها تشير إليها،
 * فصار طرفٌ من كل قيدٍ معلّقاً على معرّفٍ لا وجود له. ونتيجتُه المرئية:
 * ميزان المراجعة لا يتوازن، لأنّ التقرير يربط `journal_entry_details`
 * بـ`accounts` فيُسقط الطرف اليتيم من الجمع.
 *
 * **ثمّ ما هو أسوأ**: معرّفاتُ `accounts` تُعاد. عدّادُ `auto_increment`
 * في InnoDB يُحتسب من `MAX(id) + 1` عند إقلاع الخادم، فحذفُ حساباتٍ ثمّ
 * إعادةُ التشغيل يُعيد العدّاد إلى ما دون المحذوف. فإذا أُنشئت حساباتٌ
 * جديدة بعدها — بـ`clients:backfill-accounts` مثلاً — أخذت المعرّفات
 * المحذوفة نفسَها، **فالتصق القيدُ اليتيم بحسابٍ لا علاقة له به**:
 *
 *   OPB-1  «رصيد افتتاحي: موسسة الطيب»  ⇐ صار على حساب «مجموعة السلام»
 *   OPB-C23 «رصيد افتتاحي: شركة النور»  ⇐ صار على حساب مورّد
 *
 * فيتوازن الميزان ويبدو كل شيء سليماً، والرصيدُ منسوبٌ إلى غير صاحبه.
 * وهذا لا يُكتشف بالنظر إلى الأرقام — لا بدّ من قراءة وصف القيد.
 *
 * **ما يفعله هذا الأمر**
 *
 * يقرأ اسم الجهة من وصف القيد (`قيد رصيد افتتاحي لحساب: …`)، ويقارنه
 * بحساب الطرف الحالي:
 *
 *   • الاسم مطابق ⇒ سليم، يُترك.
 *   • الاسم مختلف أو الحساب مفقود ⇒ يُعاد الربط بحساب الجهة الصحيحة.
 *   • لا جهة بهذا الاسم في النظام ⇒ يُبلَّغ عنه، ويُحذف القيد كلُّه
 *     بـ`--delete-unresolved` (القيدُ كلُّه لا طرفُه، وإلّا اختلّ توازنه).
 *
 *   php artisan journal:repair-openings --dry-run
 *   php artisan journal:repair-openings
 *   php artisan journal:repair-openings --delete-unresolved
 *
 * @see \App\Console\Commands\BackfillClientAccounts الأمر الذي يُنشئ حسابات العملاء
 */
class RepairOpeningBalanceEntries extends Command
{
    protected $signature = 'journal:repair-openings
                            {--dry-run : عرض ما سيحدث بلا كتابة}
                            {--delete-unresolved : حذف القيود التي لا تُعرف جهتها}';

    protected $description = 'إصلاح قيود الأرصدة الافتتاحية التي فقدت حساباتها أو التصقت بحساب خاطئ';

    /** بادئةُ الوصف التي يُقرأ منها اسم الجهة */
    private const PREFIX = 'قيد رصيد افتتاحي لحساب:';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $entries = DB::table('journal_entries')
            ->where(fn ($q) => $q->where('is_opening_balance', 1)->orWhere('reference_type', 'OPB'))
            ->get();

        if ($entries->isEmpty()) {
            $this->info('لا قيود أرصدة افتتاحية.');

            return self::SUCCESS;
        }

        $this->line('قيود افتتاحية: <info>'.$entries->count().'</info>');
        $this->newLine();

        $openingAccountId = (int) DB::table('accounts')
            ->where('code', '34')->orWhere('name', 'أرصدة افتتاحية')->value('id');

        $rows = [];
        $repairs = [];
        $unresolved = [];

        foreach ($entries as $entry) {
            $name = $this->partyName($entry->description ?? '');

            if ($name === null) {
                continue;   // قيدٌ افتتاحي بصيغةٍ أخرى — لا يُمَسّ
            }

            // الطرف المقابل لحساب «أرصدة افتتاحية» هو طرف الجهة
            $detail = DB::table('journal_entry_details')
                ->where('journal_entry_id', $entry->id)
                ->when($openingAccountId, fn ($q) => $q->where('account_id', '!=', $openingAccountId))
                ->first();

            if (! $detail) {
                continue;
            }

            $current = DB::table('accounts')->find($detail->account_id);
            $target = $this->resolveAccount($name);

            $status = match (true) {
                $target === null => 'غير معروفة',
                $current === null => 'الحساب مفقود',
                (int) $current->id === (int) $target->id => 'سليم',
                default => 'حسابٌ خاطئ',
            };

            $rows[] = [
                $entry->reference_number,
                mb_substr($name, 0, 24),
                $current ? mb_substr($current->name, 0, 22) : '(مفقود #'.$detail->account_id.')',
                $target ? mb_substr($target->name, 0, 22) : '—',
                $status,
            ];

            if ($status === 'سليم') {
                continue;
            }

            if ($target === null) {
                $unresolved[] = $entry;

                continue;
            }

            $repairs[] = ['detail_id' => $detail->id, 'account_id' => (int) $target->id];
        }

        $this->table(['القيد', 'الجهة في الوصف', 'الحساب الحالي', 'الحساب الصحيح', 'الحالة'], $rows);

        if ($repairs === [] && $unresolved === []) {
            $this->info('كل القيود الافتتاحية سليمة — لا شيء ليُصلَح.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('سيُعاد ربط: <info>'.count($repairs).'</info> طرفاً');
        $this->line('لا تُعرف جهتها: <comment>'.count($unresolved).'</comment> قيداً'
            .($this->option('delete-unresolved') ? ' — <error>ستُحذف</error>' : ' — تُترك (استعمل --delete-unresolved لحذفها)'));

        if ($dry) {
            $this->newLine();
            $this->comment('عرضٌ فقط — لم يتغيّر شيء.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($repairs, $unresolved) {
                foreach ($repairs as $r) {
                    DB::table('journal_entry_details')
                        ->where('id', $r['detail_id'])
                        ->update(['account_id' => $r['account_id']]);
                }

                if ($this->option('delete-unresolved')) {
                    foreach ($unresolved as $entry) {
                        // القيدُ كلُّه لا طرفُه: حذفُ طرفٍ واحد يترك قيداً
                        // غير متوازن، وهو أسوأ ممّا نُصلح
                        DB::table('journal_entry_details')->where('journal_entry_id', $entry->id)->delete();
                        DB::table('journal_entries')->where('id', $entry->id)->delete();
                    }
                }
            });
        } catch (Throwable $e) {
            $this->error('أخفق الإصلاح ولم يتغيّر شيء: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('إعادة احتساب الأرصدة…');
        $this->recalculate();

        $this->info('تمّ.');

        return self::SUCCESS;
    }

    /** اسمُ الجهة من وصف القيد، أو `null` إن لم يكن بالصيغة المعروفة */
    private function partyName(string $description): ?string
    {
        if (! str_contains($description, self::PREFIX)) {
            return null;
        }

        $name = trim(str_replace(self::PREFIX, '', $description));

        return $name !== '' ? $name : null;
    }

    /**
     * حسابُ الجهة صاحبةِ هذا الاسم.
     *
     * يُبحث في العملاء أوّلاً ثمّ المورّدين، بالاسم التجاري كاملاً ثمّ
     * بجزءٍ منه — فالوصفُ قد يكون قُصّ عند الحفظ.
     */
    private function resolveAccount(string $name): ?Account
    {
        foreach ([['clients', 'client_id'], ['suppliers', 'supplier_id']] as [$table, $column]) {
            $party = DB::table($table)->where('trade_name', $name)->first()
                ?? DB::table($table)->where('trade_name', 'like', mb_substr($name, 0, 14).'%')->first();

            if (! $party) {
                continue;
            }

            $account = Account::where($column, $party->id)->first();

            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function recalculate(): void
    {
        foreach (DB::table('accounts')->pluck('id') as $id) {
            try {
                Account::updateBalancesHierarchically($id);
            } catch (Throwable) {
                // حسابٌ واحد لا يُعيد حسابه لا يوقف الباقي
            }
        }
    }
}
