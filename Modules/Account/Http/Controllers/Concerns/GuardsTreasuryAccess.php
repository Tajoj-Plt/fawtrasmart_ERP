<?php

namespace Modules\Account\Http\Controllers\Concerns;

use App\Support\TreasuryAccessGuard;
use Illuminate\Validation\ValidationException;

/**
 * صلاحيتا الإيداع والسحب على الخزينة والحساب البنكي — غلافُ المتحكّمات.
 *
 * **ما كان**
 *
 * شاشة إنشاء الخزينة تسأل: من يودع فيها؟ ومن يسحب منها؟ الكلّ، أم موظّفٌ
 * بعينه، أم دورٌ وظيفي، أم فرع. والجواب يُحفظ في
 * `deposit_permissions` و`withdraw_permissions` وقيمتَيهما.
 *
 * ثم **لا يقرؤه أحد**. لا سند صرفٍ يسأل عنه، ولا سند قبض، ولا تحويلٌ بين
 * الخزائن، ولا صرفُ شيك. فالشاشة تَعِد بضبطٍ لا وجود له: يضبط المحاسب
 * خزينةً على «موظّف محدد» ثم يصرف منها كلُّ من يملك صلاحية سندات الصرف.
 *
 * وليس ذلك لعجزٍ في التصميم بل لانقطاعٍ في الربط: الشرط يسكن جدول
 * `treasuries` بينما تشير العمليات إلى `accounts`، ولا عمود بينهما —
 * وسطرُ الربط في `TreasuryController::store` كان مكتوباً ثم عُطّل بتعليق.
 *
 * **ما صار**
 *
 * `treasuries.account_id` يصل الطرفين، والقرارُ في
 * `App\Support\TreasuryAccessGuard` — فيسأله كلُّ من يخرج نقداً أو يدخله،
 * من داخل وحدة الحسابات ومن خارجها (صرفُ الرواتب). وهذا التراتُ غلافُه
 * للمتحكّمات: يرمي خطأ تحقّقٍ يظهر فوق الحقل نفسه — لا رسالة نظامٍ خام
 * ولا صفحة بيضاء — ويسمّي سبب المنع: من المسموح له، لا «ليس لديك صلاحية».
 *
 * @see TreasuryAccessGuard منطقُ القرار ونصُّ الرسائل
 */
trait GuardsTreasuryAccess
{
    /**
     * يمنع الإيداع في خزينةٍ لا يملك المستخدم إيداعها.
     *
     * @param  int|null  $accountId  حساب الخزينة كما يرد في المستند
     * @param  string  $field  الحقل الذي تُعلَّق عليه الرسالة في النموذج
     *
     * @throws ValidationException
     */
    protected function guardTreasuryDeposit($accountId, string $field = 'treasury_id'): void
    {
        $this->guardTreasuryAccess($accountId, 'deposit', $field);
    }

    /**
     * يمنع السحب من خزينةٍ لا يملك المستخدم سحبها.
     *
     * @throws ValidationException
     */
    protected function guardTreasuryWithdraw($accountId, string $field = 'treasury_id'): void
    {
        $this->guardTreasuryAccess($accountId, 'withdraw', $field);
    }

    /**
     * @throws ValidationException
     */
    protected function guardTreasuryAccess($accountId, string $action, string $field): void
    {
        $reason = $this->treasuryBlockReason($accountId, $action);

        if ($reason !== null) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }

    /** سبب المنع، أو `null` إن كانت الحركة مسموحة. */
    protected function treasuryBlockReason($accountId, string $action): ?string
    {
        return TreasuryAccessGuard::blockReason($accountId, $action);
    }
}
