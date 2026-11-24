<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;

/**
 * حصرُ حسابات المصروف في شجرة «مصروفات التصنيع».
 *
 * ── ما كان يحدث ──────────────────────────────────────────────────────
 *
 * شاشتا قائمة المواد وأمر التصنيع تعرضان **كل حسابات النظام**:
 *
 *     $accounts = Account::select('id', 'name')->get();   // كلّها
 *
 * فيمكن تحميلُ «مصروف تصنيع» على حسابٍ بنكي أو ذمم عملاء أو رأس مال.
 * والقيدُ يُرحَّل بـ`expenses_account_id` من البند مباشرةً بلا أي فحصٍ
 * لنوع الحساب:
 *
 *     $lines[] = ['account_id' => (int) $item->expenses_account_id, 'credit' => $amount];
 *
 * فمصروفُ تصنيعٍ بألفٍ يُقيَّد دائناً على «البنك» — أي يُنقص رصيدَ
 * البنك دفترياً بلا حركةٍ نقدية. والخطأُ لا يُكتشف إلا بمطابقةٍ بنكية.
 *
 * ── الحصر ────────────────────────────────────────────────────────────
 *
 * الاختيارُ من **أبناء** «مصروفات التصنيع» (`manufacturing_expenses_parent`)
 * وحدهم — وهم الحسابات التي أُنشئت لهذا الغرض:
 *
 *     2963  مصروفات غير مباشرة   5501
 *     2964  مصروفات مباشرة       5502
 *     2965  أجور العمالة         5503
 *     3203  انحراف تكاليف الإنتاج 5504
 *
 * ويُستبعد الأبُ نفسه: الترحيلُ على حسابٍ له أبناء يكسر معادلة الشجرة.
 *
 * والحصرُ في مكانين معاً — القائمةُ المعروضة وقاعدةُ التحقّق — لأن
 * تقييد الواجهة وحدها لا يمنع طلباً مصنوعاً باليد.
 */
trait ResolvesExpenseAccounts
{
    use ResolvesAccountingAccounts;

    /** حسابات المصروف المسموح اختيارُها — أبناء شجرة مصروفات التصنيع. */
    protected function manufacturingExpenseAccounts(): Collection
    {
        $parentId = $this->accountingAccountId(ManufacturingSources::ACCOUNT_EXPENSES_PARENT);

        if ($parentId === null) {
            /*
                لا شجرةَ مربوطة — نعرض حسابات المصروفات كلَّها بدل قائمةٍ
                فارغة تعطّل الشاشة. ويبقى استبعادُ الآباء قائماً.
            */
            return Account::query()
                ->where('account_type', 'expenses')
                ->where('name', 'not like', '%غير مباشر%')
                ->whereDoesntHave('children')
                ->orderBy('code')
                ->get(['id', 'name', 'code']);
        }

        /*
            المباشرةُ وحدها.

            شجرةُ «مصروفات التصنيع» فيها «مصروفات مباشرة» و«مصروفات غير
            مباشرة» معاً. والثانيةُ لا تُختار هنا: غيرُ المباشر يُجمع ويُوزَّع
            من شاشة «التكاليف غير المباشرة» ثمّ يدخل الأمرَ بقسمه الخاصّ —
            فعرضُه في مصروفات البند يفتح تحميلَه مرّتين.

            وحسابٌ له أبناء (كأن تُفصَّل «مصروفات مباشرة» إلى كهرباء وصيانة…)
            يُعرض أبناؤه لا هو: الترحيلُ على أبٍ يكسر معادلة الشجرة.
        */
        $accounts = collect();

        $collect = function ($parent) use (&$collect, &$accounts) {
            $children = Account::query()
                ->where('parent_id', $parent)
                ->where('name', 'not like', '%غير مباشر%')
                ->orderBy('code')
                ->get(['id', 'name', 'code']);

            foreach ($children as $child) {
                if (Account::where('parent_id', $child->id)->exists()) {
                    $collect($child->id);
                } else {
                    $accounts->push($child);
                }
            }
        };

        $collect($parentId);

        return $accounts->values();
    }

    /** معرّفاتُها وحدها — لقاعدة التحقّق `in:`. */
    protected function allowedExpenseAccountIds(): array
    {
        return $this->manufacturingExpenseAccounts()->pluck('id')->all();
    }

    /**
     * رفضُ أي حساب مصروفٍ خارج الشجرة — على الخادم.
     *
     * تقييدُ القائمة المعروضة يمنع الخطأ لا العبث: طلبٌ مصنوعٌ باليد
     * يمرّر أيَّ معرّف. وهذا هو الفحص الذي يُعتدّ به.
     *
     * @throws ValidationException
     */
    protected function assertExpenseAccountsAllowed(Request $request): void
    {
        $submitted = array_filter(
            array_map('intval', (array) $request->input('expenses_account_id', [])),
            fn (int $id) => $id > 0
        );

        if ($submitted === []) {
            return;
        }

        $rejected = array_values(array_diff(
            array_unique($submitted),
            $this->allowedExpenseAccountIds()
        ));

        if ($rejected === []) {
            return;
        }

        $names = Account::whereIn('id', $rejected)->pluck('name')->implode('، ');

        throw ValidationException::withMessages([
            'expenses_account_id' => sprintf(
                'حساب المصروف يجب أن يكون من حسابات «مصروفات التصنيع». '
                .'الحسابات المرفوضة: %s.',
                $names !== '' ? $names : implode('، ', $rejected)
            ),
        ]);
    }
}
