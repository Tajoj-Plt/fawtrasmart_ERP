<?php

namespace Modules\RentalManagement\Console;

use App\Models\Account;
use Illuminate\Console\Command;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\PropertyAccountService;

/**
 * حسابات المباني والوحدات القديمة في دليل الحسابات.
 *
 * كان كل مبنى ووحدة يُنشأ لهما حسابٌ في الشجرة. لم يعد يُنشأ شيء منها: إيجار
 * المبنى المملوك يُقيَّد على «إيرادات الإيجار» والمُدار على الحساب الجاري للمالك.
 * ما أُنشئ سابقاً يبقى في الشجرة بلا فائدة.
 *
 * الأمر يعرض ما يمكن إزالته **ولا يحذف شيئاً** إلا بـ`--force`، وحتى معه لا يُزال حسابٌ
 * عليه قيدٌ أو تحته حسابات: ذلك تاريخٌ مالي يبقى.
 */
class ReleasePropertyAccountsCommand extends Command
{
    protected $signature = 'realestate:release-property-accounts {--force : احذف الحسابات الفارغة فعلاً (الافتراضي: عرضٌ فقط)}';

    protected $description = 'يعرض حسابات المباني والوحدات القديمة التي لا حركة عليها، ويحذفها عند --force';

    public function handle(PropertyAccountService $service): int
    {
        $rows = collect();

        foreach ([['مبنى', Building::withTrashed()->whereNotNull('account_id')->get()], ['وحدة', Unit::withTrashed()->whereNotNull('account_id')->get()]] as [$kind, $owners]) {
            foreach ($owners as $owner) {
                $account = Account::find($owner->account_id);

                if (! $account) {
                    continue;
                }

                $postings = $account->journalEntryDetails()->count();
                $children = $account->children()->count();

                $rows->push([
                    'owner' => $owner, 'kind' => $kind, 'account' => $account, 'postings' => $postings, 'children' => $children,
                    'removable' => $postings === 0 && $children === 0,
                ]);
            }
        }

        $this->table(['النوع', 'الاسم', 'رمز الحساب', 'الحساب', 'قيود', 'أبناء', 'الحالة'], $rows->map(fn ($r) => [
            $r['kind'], $r['owner']->name_ar ?? $r['owner']->unit_no, $r['account']->code, $r['account']->name, $r['postings'], $r['children'],
            $r['removable'] ? 'فارغ — يمكن حذفه' : 'عليه حركة/أبناء — يبقى',
        ])->all());

        $removable = $rows->where('removable', true);
        $this->info(sprintf('الإجمالي %d حساباً: %d فارغاً، و%d عليه حركة أو أبناء.', $rows->count(), $removable->count(), $rows->count() - $removable->count()));

        if (! $this->option('force')) {
            $this->comment('عرضٌ فقط — لم يُحذف شيء. أضف --force لحذف الحسابات الفارغة.');

            return self::SUCCESS;
        }

        // الأبناء (حسابات الوحدات) قبل الآباء (حسابات المباني): الأب لا يُحذف وتحته ابن
        foreach ($removable->sortByDesc(fn ($r) => $r['kind'] === 'وحدة') as $row) {
            $service->release($row['owner']);
        }

        $this->info('حُذفت الحسابات الفارغة. أعد تشغيل الأمر لحذف حسابات المباني التي صارت بلا أبناء.');

        return self::SUCCESS;
    }
}
