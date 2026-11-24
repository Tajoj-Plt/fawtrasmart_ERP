<?php

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تصحيح بأثر رجعي لقيود الأذون المخزنية اليدوية.
     *
     * كان إذن الإضافة اليدوي يُرحَّل على «المشتريات» وإذن الصرف اليدوي على
     * «تكلفة البضاعة المباعة» — وكلاهما خطأ: لا شراء ولا بيع خلف أيٍّ منهما،
     * فيتضخّم رقم المشتريات وتتضخّم تكلفة المبيعات بلا إيرادٍ مقابل.
     *
     * تُنقل السطور إلى «أطراف دائنة أخرى» للإضافة و«أطراف مدينة أخرى» للصرف،
     * ثم تُعاد أرصدة الحسابات المتأثّرة هرميّاً. القيود العكسية تُستثنى.
     */
    public function up(): void
    {
        $accountId = function (string $key, int $fallback): int {
            try {
                return AccountingSettings::account($key)->id ?? $fallback;
            } catch (\Throwable $e) {
                return $fallback;
            }
        };

        $moves = [
            // مصدر الإذن => [من الحساب، إلى الحساب، الوصف]
            1 => [
                $accountId('purchases', SystemAccountIds::PURCHASES),
                $accountId('other_creditor_parties', SystemAccountIds::OTHER_CREDITOR_PARTIES),
                'إضافة يدوية',
            ],
            2 => [
                $accountId('cost_of_sales', SystemAccountIds::COST_OF_SALES),
                $accountId('other_debtor_parties', SystemAccountIds::OTHER_DEBITOR_PARTIES),
                'صرف يدوي',
            ],
        ];

        $touched = [];

        foreach ($moves as $source => [$from, $to, $label]) {
            if (! $from || ! $to || $from === $to) {
                continue;
            }

            $ids = DB::table('journal_entry_details as d')
                ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
                ->join('warehouse_permits as p', 'p.id', '=', 'e.reference_id')
                ->where('p.permission_source_id', $source)
                ->where('d.account_id', $from)
                ->where('e.reference_number', 'not like', 'REVERSE\_%')
                ->pluck('d.id');

            if ($ids->isEmpty()) {
                continue;
            }

            DB::table('journal_entry_details')->whereIn('id', $ids)->update(['account_id' => $to]);

            $touched[] = $from;
            $touched[] = $to;

            echo '  نُقل '.$ids->count().' سطراً من قيود «'.$label.'» إلى الحساب '.$to.PHP_EOL;
        }

        foreach (array_unique($touched) as $account) {
            try {
                Account::updateBalancesHierarchically($account);
            } catch (\Throwable $e) {
                // إعادة حساب الأرصدة ليست شرطاً لنجاح الترحيل
            }
        }
    }

    public function down(): void
    {
        // لا تراجع: إعادة السطور إلى المشتريات وتكلفة المبيعات تُعيد الخطأ
    }
};
