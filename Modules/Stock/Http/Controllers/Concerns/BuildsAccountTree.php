<?php

namespace Modules\Stock\Http\Controllers\Concerns;

use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * قائمة الحسابات مرتَّبةً على هيئة شجرة الحسابات.
 *
 * كانت هذه الدالة مكتوبةً داخل متحكّم أوامر التصنيع وحده، فكل شاشةٍ أخرى
 * تحتاج «حساباً» تعرض قائمةً مسطَّحة بلا ترتيبٍ ولا مستويات.
 *
 * **ولماذا الحراسةُ من الحلقات؟**
 *
 * المرورُ كان استدعاءً ذاتياً بلا ذاكرةٍ لما زاره: حسابٌ أبوه ابنُه — أو أبوه
 * نفسُه — يجعل الاستدعاء **لا ينتهي أبداً**. والصفحةُ لا تُخطئ حينئذٍ بل
 * تتجمّد حتى تنقضي مهلةُ الخادم، فيُقال «الصفحة لا تعمل» ولا يظهر في اللوغ
 * سببٌ يُقرأ.
 *
 * ولا يُشترط أن تكون الحلقةُ مقصودة: نقلُ حسابٍ تحت أحد فروعه من شاشة
 * الشجرة يصنعها، وتصحيحُ `parent_id` يدوياً في القاعدة يصنعها.
 */
trait BuildsAccountTree
{
    /** حدُّ العمق — شجرةٌ محاسبية لا تبلغه، وما تجاوزه فخللٌ لا بنية. */
    private const MAX_TREE_DEPTH = 30;

    /**
     * @param  bool  $activeOnly  اقتصار القائمة على الحسابات النشطة
     */
    protected function accountTreeOptions(bool $activeOnly = true): Collection
    {
        $query = Account::select('id', 'name', 'code', 'parent_id', 'type', 'account_type', 'is_active');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        $accounts = $query->orderBy('code')->orderBy('name')->get()->groupBy('parent_id');

        $ordered = collect();

        /*
            `$seen` مفتاحُها معرّفُ الحساب — بحثٌ في زمنٍ ثابت.

            وكان الفحصُ لاحقاً بـ`in_array` على مصفوفةٍ تكبر مع كلّ حساب:
            زمنٌ تربيعيّ يُثقل شجرةً من بضعة آلاف حساب ثقلاً محسوساً.
        */
        $seen = [];

        $traverse = function ($parentId, int $depth) use (&$traverse, &$ordered, &$seen, $accounts) {
            if ($depth > self::MAX_TREE_DEPTH) {
                return;
            }

            foreach ($accounts->get($parentId, collect()) as $account) {
                // حلقةٌ في الشجرة: حسابٌ زُرناه يعود فيظهر تحت نفسه
                if (isset($seen[$account->id])) {
                    continue;
                }

                $seen[$account->id] = true;

                $this->decorateAccountOption($account, $depth);
                $ordered->push($account);

                $traverse($account->id, $depth + 1);
            }
        };

        $traverse(null, 0);

        /*
            حسابٌ لم يبلغه المرور — أبوه محذوفٌ أو غير نشط، أو هو في حلقةٍ
            مغلقة — يُلحَق بجذر القائمة كي لا يختفي من الشاشة.
        */
        foreach ($accounts->flatten() as $account) {
            if (isset($seen[$account->id])) {
                continue;
            }

            $seen[$account->id] = true;

            $this->decorateAccountOption($account, 0);
            $ordered->push($account);
        }

        return $ordered;
    }

    /** صفاتُ العرض في القائمة المنسدلة — موضعٌ واحد للمرور وللأيتام. */
    private function decorateAccountOption(Account $account, int $depth): void
    {
        $label = $account->code ? $account->code.' - '.$account->name : $account->name;

        $account->setAttribute('dropdown_depth', $depth);
        $account->setAttribute('dropdown_text', $label);
        $account->setAttribute('dropdown_code', $account->code);
        $account->setAttribute('dropdown_label', ($depth > 0 ? str_repeat('— ', $depth) : '').$label);
        // الحساب الرئيسي عنوانٌ في الشجرة، والترحيل لا يكون إلا على حسابٍ فرعي
        $account->setAttribute('dropdown_selectable', $account->type === 'sub');
    }
}
