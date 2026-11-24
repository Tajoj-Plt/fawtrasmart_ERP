<?php

namespace App\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Deduction;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * قيودُ خصميات الموظفين — من الاعتماد إلى الاقتطاع أو الإلغاء.
 *
 * ══════════════════ الدورةُ المحاسبية ══════════════════
 *
 * **عند الاعتماد** (`ensurePosted`) — قيد `DED-{id}`:
 *
 *     مدين  مستحقات رواتب الموظف (ذمّته)
 *         دائن  حساب نوع الخصمية (غياب/تأخير/جزاء…)
 *
 * فالخصميةُ تُثبَت يومَ تقع لا يومَ يُصرف الراتب، ويظهر أثرُها في كشف
 * حساب الموظف ودليل الحسابات فوراً.
 *
 * **عند صرف الراتب** — `PayrollAccountant` لا يُقيّد الخصميةَ مرّةً ثانية:
 * قيدُ الاستحقاق يُدين المصروفَ بالإجمالي ويُقيّد الذمّةَ دائنةً بالإجمالي،
 * وقيدُ الصرف يُدين الذمّةَ بـ(الإجمالي − الخصميات المقتطعة). فتنقفل الذمّة:
 *
 *     دائن الإجمالي − مدين (الإجمالي − الخصم) − مدين الخصم (قيد الاعتماد) = صفر
 *
 * وكان النظامُ قبل ذلك يُقيّد الخصميةَ داخل قيد الاستحقاق على حسابٍ واحدٍ
 * (`payroll_deductions`) لكلِّ الأنواع — فإن لم يُربط ذلك الحساب رُفض صرفُ
 * الراتب كلُّه برسالةٍ عن حسابٍ غير مربوط، ولو ضُبط لكلِّ نوعٍ حسابُه.
 *
 * **عند الإلغاء** (`reverseUnsettled`) — قيد `DED-CAN-{id}` يعكس ما قُيِّد
 * ولم يُقتطع بعد: مدين حساب النوع / دائن الذمّة.
 *
 * **التسويةُ اليدوية** خارج الراتب بطريقتين:
 *   • تحصيلٌ نقدي (`collectCash`): مدين الخزينة / دائن الذمّة.
 *   • إعفاء (`waive`): مدين حساب النوع / دائن الذمّة — عكسُ الجزء المُعفى.
 *
 * ══════════════════ `posted_amount` ══════════════════
 *
 * ما غطّته القيودُ من مبلغ الخصمية خارجَ قيد الراتب. الخصمياتُ القائمة قبل
 * هذا التغيير بُدئت فيه بمبلغها المسوّى (غطّاه قيدُ الراتب القديم)، فلا
 * يُقيَّد إلا ما تبقّى — ولا يُحتسب جزءٌ مرّتين.
 *
 * ══════════════════ حسابُ النوع ══════════════════
 *
 * المفتاحُ `payroll_deduction_{type}` في «الحسابات المحاسبية»، وإن لم يُربط
 * فالمفتاحُ العام `payroll_deductions`. ويُضبطان معاً من شاشة «حسابات
 * الرواتب».
 */
class DeductionAccountingService
{
    public const GENERAL_KEY = 'payroll_deductions';

    private const EPSILON = 0.009;

    /** مفتاحُ «الحسابات المحاسبية» لنوع خصمية. */
    public static function typeKey(string $type): string
    {
        return 'payroll_deduction_'.$type;
    }

    /** حسابُ نوع الخصمية — مفتاحُ النوع، وإلا المفتاحُ العام. */
    public function accountIdFor(?string $type): ?int
    {
        if ($type) {
            $id = AccountingSettings::id(self::typeKey($type));
            if ($id) {
                return (int) $id;
            }
        }

        $general = AccountingSettings::id(self::GENERAL_KEY);

        return $general ? (int) $general : null;
    }

    /**
     * سببُ تعذّر قيد هذه الخصمية — أو `null` إن كانت قابلةً للقيد.
     *
     * يُسأل قبل الاعتماد فيُرفض بالسبب لا بعطلٍ من داخل المعاملة.
     */
    public function blockReason(Deduction $deduction): ?string
    {
        $employee = $deduction->employee;
        if (! $employee) {
            return 'الموظف صاحب الخصمية غير موجود.';
        }

        if (! $employee->salary_payable_account_id || ! Account::find($employee->salary_payable_account_id)) {
            return 'الموظف "'.$employee->first_name.'" لا يملك حساب مستحقات رواتب — افتح بطاقة الموظف ثمّ اضغط حفظ لإنشاء الحساب.';
        }

        $accountId = $this->accountIdFor($deduction->deduction_type);
        if (! $accountId || ! Account::find($accountId)) {
            return 'لا حسابَ مربوطٌ لخصميات نوع «'.$deduction->type_label.'» ولا للخصميات عامةً: '.
                \App\Support\PayrollAccounts::missingMessage(self::GENERAL_KEY);
        }

        return null;
    }

    /**
     * يُقيّد ما لم يُقيَّد بعد من مبلغ الخصمية (مدين الذمّة / دائن حساب النوع).
     *
     * آمنٌ للاستدعاء مراراً: يُقيّد الفرقَ وحده، فلا يتكرّر قيد.
     *
     * @throws \RuntimeException إن تعذّر القيد (حسابٌ غير مربوط، فترةٌ مقفلة)
     */
    public function ensurePosted(Deduction $deduction): ?JournalEntry
    {
        return DB::transaction(function () use ($deduction) {
            $d = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);

            $need = round((float) $d->amount - (float) $d->posted_amount, 2);
            if ($need <= self::EPSILON) {
                return null;
            }

            if ($block = $this->blockReason($d)) {
                throw new \RuntimeException('تعذّر قيد الخصمية #'.$d->id.': '.$block);
            }

            $employee = $d->employee;
            $typeAccountId = (int) $this->accountIdFor($d->deduction_type);
            $name = $this->employeeName($d);
            $isTopUp = (float) $d->posted_amount > self::EPSILON || $d->journal_entry_id;

            $journal = $this->journal(
                $d,
                $this->reference('DED-'.$d->id),
                $this->postingDate($d->date),
                // يُقال في الوصف أين تذهب: قيدٌ على ذمّة الموظف يُقتطع من راتبه — لا قيدٌ بلا قسيمة
                'خصمية «'.$d->type_label.'» على '.$name.' — تُقتطع من راتب '.$name.' عند الصرف التالي'
                    .($d->reason ? ' — '.mb_substr((string) $d->reason, 0, 100) : ''),
                [
                    [(int) $employee->salary_payable_account_id, $need, 0, 'خصمية على مستحقات '.$name.' — تُقتطع من الراتب'],
                    [$typeAccountId, 0, $need, 'خصمية '.$d->type_label.' — '.$name],
                ]
            );

            $d->forceFill([
                'posted_amount' => round((float) $d->posted_amount + $need, 2),
                'journal_entry_id' => $isTopUp && $d->journal_entry_id ? $d->journal_entry_id : $journal->id,
            ])->saveQuietly();

            $deduction->setRawAttributes($d->getAttributes(), true);

            return $journal;
        });
    }

    /**
     * يعكس ما قُيِّد ولم يُقتطع بعد — عند إلغاء خصميةٍ معتمدة.
     */
    public function reverseUnsettled(Deduction $deduction, ?string $reason = null): ?JournalEntry
    {
        return DB::transaction(function () use ($deduction, $reason) {
            $d = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);

            $amount = round((float) $d->posted_amount - (float) $d->settled_amount, 2);
            if ($amount <= self::EPSILON) {
                return null;
            }

            $journal = $this->counterEntry($d, $amount, 'DED-CAN-'.$d->id,
                'إلغاء خصمية «'.$d->type_label.'» على '.$this->employeeName($d).($reason ? ' — '.$reason : ''));

            $d->forceFill(['posted_amount' => round((float) $d->posted_amount - $amount, 2)])->saveQuietly();
            $deduction->setRawAttributes($d->getAttributes(), true);

            return $journal;
        });
    }

    /**
     * إعفاءُ الموظف من جزءٍ من الخصمية — تسويةٌ بلا نقد.
     */
    public function waive(Deduction $deduction, float $amount, ?string $notes = null): JournalEntry
    {
        $this->ensurePosted($deduction);

        return DB::transaction(function () use ($deduction, $amount, $notes) {
            $d = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);

            return $this->counterEntry($d, round($amount, 2), 'DED-WVR-'.$d->id,
                'إعفاء من خصمية «'.$d->type_label.'» على '.$this->employeeName($d).($notes ? ' — '.$notes : ''));
        });
    }

    /**
     * تحصيلُ الخصمية نقداً من الموظف إلى خزينة — تسويةٌ خارج الراتب.
     *
     * @param  int  $treasuryAccountId  حساب الخزينة (`accounts.id` لا `treasuries.id`)
     */
    public function collectCash(Deduction $deduction, int $treasuryAccountId, float $amount, ?string $notes = null): JournalEntry
    {
        if ($block = TreasuryAccessGuard::ownDepositBlockReason($treasuryAccountId)) {
            throw new \RuntimeException($block);
        }

        $treasury = Account::find($treasuryAccountId);
        if (! $treasury) {
            throw new \RuntimeException('الخزينة المختارة غير موجودة.');
        }

        $this->ensurePosted($deduction);

        return DB::transaction(function () use ($deduction, $treasury, $amount, $notes) {
            $d = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);
            $amount = round($amount, 2);
            $name = $this->employeeName($d);

            return $this->journal(
                $d,
                $this->reference('DED-COL-'.$d->id),
                $this->postingDate(now()),
                'تحصيل خصمية «'.$d->type_label.'» نقداً من '.$name.($notes ? ' — '.$notes : ''),
                [
                    [(int) $treasury->id, $amount, 0, 'تحصيل خصمية من '.$name.' إلى '.$treasury->name],
                    [(int) $d->employee->salary_payable_account_id, 0, $amount, 'سداد خصمية من '.$name],
                ]
            );
        });
    }

    // ───────────────────────── مساعدات ─────────────────────────

    /** مدين حساب النوع / دائن الذمّة — عكسُ قيد الاعتماد بمقدارٍ. */
    private function counterEntry(Deduction $d, float $amount, string $ref, string $description): JournalEntry
    {
        if ($block = $this->blockReason($d)) {
            throw new \RuntimeException('تعذّر قيد الخصمية #'.$d->id.': '.$block);
        }

        $name = $this->employeeName($d);

        return $this->journal(
            $d,
            $this->reference($ref),
            $this->postingDate(now()),
            $description,
            [
                [(int) $this->accountIdFor($d->deduction_type), $amount, 0, 'عكس خصمية '.$d->type_label.' — '.$name],
                [(int) $d->employee->salary_payable_account_id, 0, $amount, 'ردّ خصمية إلى مستحقات '.$name],
            ]
        );
    }

    /**
     * @param  array<int, array{0:int,1:float,2:float,3:string}>  $lines  [حساب، مدين، دائن، وصف]
     */
    private function journal(Deduction $d, string $ref, Carbon $date, string $description, array $lines): JournalEntry
    {
        $debit = round(array_sum(array_column($lines, 1)), 2);
        $credit = round(array_sum(array_column($lines, 2)), 2);
        if (abs($debit - $credit) > 0.001 || $debit <= 0) {
            throw new \RuntimeException('قيد الخصمية غير متوازن.');
        }

        $journal = JournalEntry::create([
            'reference_number' => $ref,
            'reference_id' => $d->id,
            'date' => $date->toDateString(),
            'description' => mb_substr($description, 0, 250),
            'status' => 1,
            // فارغةٌ عمداً: `HasDocumentCurrency` يملؤها بعملة النظام
            'currency' => null,
            // فارغٌ عمداً: يُختم فرعُ مَن يعتمد الخصم (StampsOwnerContext)، كسائر القيود — فيظهر في «القيود» عنده
            'branch_id' => null,
            'employee_id' => $d->employee_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PAYROLL,
            'linking_type' => LinkingType::SINGLE,
        ]);

        foreach ($lines as [$accountId, $dr, $cr, $text]) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $accountId,
                'description' => mb_substr($text, 0, 250),
                'debit' => $dr,
                'credit' => $cr,
                'is_debit' => $dr > 0,
            ]);
        }

        return $journal;
    }

    /**
     * تاريخُ الخصمية نفسُه إن كان مفتوحاً وغير مستقبلي — وإلا اليوم.
     *
     * @throws \RuntimeException إن كانت فترةُ اليوم نفسُها مقفلة
     */
    private function postingDate($date): Carbon
    {
        $today = now()->startOfDay();
        $candidate = $date ? Carbon::parse($date)->startOfDay() : $today;

        if ($candidate->gt($today) || AccountingPeriodGuard::blockReason($candidate) !== null) {
            $candidate = $today;
        }

        if ($block = AccountingPeriodGuard::blockReason($candidate)) {
            throw new \RuntimeException($block);
        }

        return $candidate;
    }

    /** مرجعٌ فريد: الأوّلُ بلا لاحقة، وما بعده بعدّاد. */
    private function reference(string $base): string
    {
        $count = JournalEntry::withoutGlobalScopes()
            ->where(function ($q) use ($base) {
                $q->where('reference_number', $base)->orWhere('reference_number', 'like', $base.'-%');
            })
            ->count();

        return $count === 0 ? $base : $base.'-'.($count + 1);
    }

    private function employeeName(Deduction $d): string
    {
        $e = $d->employee;

        return trim(($e->first_name ?? '').' '.($e->middle_name ?? '')) ?: ('#'.$d->employee_id);
    }
}
