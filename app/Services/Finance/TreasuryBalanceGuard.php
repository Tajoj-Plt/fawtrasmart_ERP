<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Support\TreasuryAccessGuard;
use Illuminate\Support\Facades\DB;

/**
 * حارسُ كفاية رصيد الخزينة قبل أيِّ صرفٍ نقدي — نقطةُ القرار الواحدة.
 *
 * **لماذا؟**
 *
 * كان الفحصُ نفسُه منسوخاً في ثلاثة مواضع — صرفُ الراتب
 * (`PayrollAccountant::post`)، وصرفُ مكافأة نهاية الخدمة
 * (`EndOfServiceBenefitController::pay`)، وسدادُ التأمينات
 * (`SocialInsuranceSettingsController::remit`) — بثلاث صيغٍ للرسالة وبلا
 * ضمانٍ أن يبقى الثلاثةُ متطابقين. وكلُّ مخرجٍ نقديٍّ جديد (العمولات…)
 * كان سيكتب نسخةً رابعة.
 *
 * **القواعد**
 *
 *   1. **داخل معاملة وحدها.** القفلُ `lockForUpdate()` بلا معاملةٍ يُفَكّ
 *      فور القراءة، فلا يحمي شيئاً. فيُرفض الاستدعاءُ خارجها صراحةً.
 *   2. **قفلُ صفِّ حساب الخزينة قبل قراءة رصيدها** — فصرفتان متزامنتان من
 *      الخزينة نفسِها تتسلسلان: الثانيةُ تقرأ الرصيدَ بعد تثبيت الأولى.
 *   3. **الرصيدُ من الدفتر لا من العمود المخزَّن** — `accounts.balance`
 *      مشتقٌّ يُحدَّث بمستمعٍ قد يخفق بصمت.
 *   4. **الفحصُ قبل إنشاء القيد أو السند.** فالرفضُ لا يترك أثراً يُنظَّف.
 *
 * والمعرّفُ المطلوب هنا **معرّفُ حساب الخزينة** (`accounts.id`) لا
 * `treasuries.id` — انظر `Treasury::$fillable`: الحركةُ تُقيَّد على الحساب.
 *
 * الاستعمال:
 *
 *     DB::transaction(function () use ($accountId, $amount) {
 *         $treasury = TreasuryBalanceGuard::lockAndAssert($accountId, $amount, 'صرف الراتب');
 *         // ... إنشاء القيد والسند على $treasury
 *     });
 */
class TreasuryBalanceGuard
{
    /** سماحُ التقريب — أجزاءُ الهللة لا تمنع صرفاً */
    private const EPSILON = 0.009;

    /**
     * يقفل حسابَ الخزينة ويتحقّق أنّ رصيدَه يكفي للمبلغ، ويعيد الحسابَ المقفل.
     *
     * @param  int|Account  $treasury  حساب الخزينة (`accounts.id`)
     * @param  string  $purpose  وصفُ العملية في رسالة الرفض («صرف الراتب»…)
     *
     * @throws InsufficientTreasuryBalanceException حين لا يكفي الرصيد
     * @throws \RuntimeException حين لا يوجد الحساب
     * @throws \LogicException خارج معاملة
     */
    public static function lockAndAssert(int|Account $treasury, float $amount, string $purpose = 'الصرف'): Account
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('TreasuryBalanceGuard::lockAndAssert يُستدعى داخل معاملة — القفلُ خارجها لا يحمي شيئاً.');
        }

        $id = $treasury instanceof Account ? (int) $treasury->id : (int) $treasury;

        /** @var Account|null $locked */
        $locked = Account::query()->lockForUpdate()->find($id);
        if (! $locked) {
            throw new \RuntimeException('حساب الخزينة المختار غير موجود.');
        }

        // لا شيء يخرج — لا حاجة للرصيد، والقفلُ قائمٌ على كلّ حال
        if ($amount <= self::EPSILON) {
            return $locked;
        }

        $balance = (float) $locked->updateBalanceFromJournalEntries();

        if ($balance + self::EPSILON < $amount) {
            throw new InsufficientTreasuryBalanceException($locked, $balance, $amount, $purpose);
        }

        return $locked;
    }

    /**
     * شرطُ السحب من الخزينة ثمّ كفايةُ رصيدها — ما يسأله كلُّ مخرجٍ نقديٍّ يدويّ.
     *
     * شرطُ السحب قبل الرصيد: من لا يملك السحب لا يفيده أن يُقال له إنّ الرصيد
     * لا يكفي. ومسارُ الكرون بلا جلسة، فيعيد حارسُ الوصول `null`.
     *
     * @param  bool  $own  `true` = خزينةُ الموظف نفسه وحدها (`ownWithdrawBlockReason`)
     */
    public static function assertWithdrawable(int|Account $treasury, float $amount, string $purpose = 'الصرف', bool $own = false): Account
    {
        $id = $treasury instanceof Account ? (int) $treasury->id : (int) $treasury;

        $block = $own
            ? TreasuryAccessGuard::ownWithdrawBlockReason($id)
            : TreasuryAccessGuard::withdrawBlockReason($id);

        if ($block) {
            throw new \RuntimeException($block);
        }

        return self::lockAndAssert($id, $amount, $purpose);
    }

    /**
     * حساباتُ الخزائن التي يجوز لهذا المستخدم السحبُ منها — قائمةُ «خزينة الصرف».
     *
     * `TreasuryAccessGuard::withdrawable()` تقرأ جدول `treasuries` وحده، وفي
     * قواعدَ قائمةٍ خزائنُ لا صفَّ لها فيه (الخزينة الرئيسية نفسُها في قاعدة
     * التطوير) — والحارسُ يُجيز السحبَ منها لغياب الشرط. فتُضمّ إليها حساباتُ
     * الخزائن والبنوك تحت أبوَيها المضبوطَين في «الحسابات المحاسبية» مما لا
     * صفَّ له في `treasuries`. فالقائمةُ والحفظُ يسألان الحارسَ نفسَه.
     *
     * @param  bool  $own  `true` = خزينةُ الموظف نفسه وحدها لمن ليس له `data_scope_all`
     * @return \Illuminate\Support\Collection<int, Account>
     */
    public static function withdrawableAccounts(bool $own = false)
    {
        $ids = ($own ? TreasuryAccessGuard::ownWithdrawable() : TreasuryAccessGuard::withdrawable())
            ->pluck('account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $parents = array_values(array_filter([
            \App\Support\AccountingSettings::id('cash_treasuries_parent'),
            \App\Support\AccountingSettings::id('bank_accounts_parent'),
        ]));

        if ($parents !== []) {
            $registered = \App\Models\Treasury::whereNotNull('account_id')->pluck('account_id')->map(fn ($id) => (int) $id)->all();

            $unregistered = Account::whereIn('parent_id', $parents)
                ->where('is_active', 1)
                ->whereNotIn('id', $registered ?: [0])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => ! $own || TreasuryAccessGuard::ownWithdrawBlockReason($id) === null)
                ->all();

            $ids = array_merge($ids, $unregistered);
        }

        return Account::whereIn('id', array_unique($ids) ?: [0])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * الرصيدُ المخزَّن للعرض (بلا قفلٍ ولا إعادة احتساب) — لتنبيهٍ مبكرٍ في
     * الشاشة وحده. قرارُ الصرف لا يُبنى عليه: ذاك `lockAndAssert()`.
     */
    public static function displayBalance(int|Account|null $treasury): ?float
    {
        if (! $treasury) {
            return null;
        }

        $account = $treasury instanceof Account ? $treasury : Account::find((int) $treasury);

        return $account ? (float) $account->balance : null;
    }
}
