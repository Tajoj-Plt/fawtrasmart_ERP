<?php

namespace App\Services\Installments;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Support\InstallmentSchedule;
use RuntimeException;

/**
 * تحميل غرامة التأخير على القسط وترحيلُها إلى «قسم الغرامات».
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان
 * ══════════════════════════════════════════════════════════════════════
 *
 * الغرامة رقمٌ يُحسب وقت العرض ويظهر في عمودٍ واحد ثمّ ينتهي أثرُه عند
 * حافّة الشاشة: لا عمودَ يحفظه، ولا قيدَ يُرحّله، ولا فرقَ بين قسطٍ
 * تأخّر يوماً وآخر تأخّر تسعين. فالمنشأة التي تتّفق على غرامةٍ في عقد
 * التقسيط لا تجد في دفاترها أثراً لها، ولا تعرف كم استحقّ منها أصلاً.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما صار — التحميل بالفرق لا بالمجموع
 * ══════════════════════════════════════════════════════════════════════
 *
 * المهمّة المجدولة تمرّ يومياً على الأقساط المتأخّرة، وتحسب لكلٍّ منها ما
 * **استحقّ إلى اليوم** (`InstallmentSchedule::lateFeeFor`)، ثمّ تقارنه
 * بما **حُمِّل من قبل** (`late_fee_amount`) وترحّل **الفرق** وحده.
 *
 * ولذلك لا تُقيَّد الغرامة مرّتين مهما تكرّر التشغيل في اليوم الواحد،
 * وتُقيَّد الزيادةُ وحدها في الاحتساب اليوميّ:
 *
 *     اليوم ١ بعد السماح:  استحقّ 10 — حُمِّل 0  ⇒ قيدٌ بـ10
 *     اليوم ٢:             استحقّ 20 — حُمِّل 10 ⇒ قيدٌ بـ10
 *     تشغيلٌ ثانٍ في اليوم ٢: استحقّ 20 — حُمِّل 20 ⇒ لا قيد
 *
 * والقيد في كلّ مرّة:
 *
 *     من ح/ العميل (ذمّته المدينة)          xx
 *         إلى ح/ غرامات تأخير الأقساط            xx
 *
 * وهو قيدٌ **يزيد الذمّة ويعترف بإيرادٍ عارض** — لا يمسّ خزينةً: لم
 * يُقبَض مالٌ بعد، إنّما استُحقّ.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  حدودٌ مقصودة
 * ══════════════════════════════════════════════════════════════════════
 *
 *   • **لا غرامةَ بلا سياسة.** اتفاقيةٌ بلا `late_fee_type` لا يُحمَّل
 *     عليها شيءٌ مهما تأخّرت. والسياسةُ قرارٌ بشريّ يُضبَط في الاتفاقية
 *     عند توقيعها — وهو الفعلُ الذي يأذن بكلّ ما بعده.
 *
 *   • **لا غرامةَ على قسطٍ ملغى أو مسدَّد.** الأوّل خرج من المطالبة،
 *     والثاني لا متبقّيَ فيه تُحتسب عليه.
 *
 *   • **لا تُخفَّض غرامةٌ حُمِّلت.** لو سُدِّد جزءٌ من القسط فصغُر
 *     المتبقّي فصغُر المستحقّ، يبقى ما حُمِّل كما هو: قيدٌ رُحِّل لا
 *     يُمحى بتغيّر الأساس بعده. والفرقُ السالب يُتخطّى.
 *
 *   • **الحسابُ من «الحسابات المحاسبية» لا من ثابتٍ في الشيفرة.** مفتاحٌ
 *     غير مربوط يُوقف الترحيل برسالةٍ صريحة، ولا يذهب القيد إلى حسابٍ
 *     مخمَّن.
 */
class LateFeeCharger
{
    /** مفتاح حساب الغرامات في «الحسابات المحاسبية». */
    public const ACCOUNT_KEY = 'installment_late_fees';

    /**
     * يحمّل الفرق المستحقّ على قسطٍ ويرحّله.
     *
     * @param  bool  $dryRun  يحسب ولا يكتب — لمعاينة أثر المهمّة
     * @return float المبلغ الذي حُمِّل الآن (0 يعني لا شيء استحقّ)
     *
     * @throws RuntimeException إن لم يُربط حساب الغرامات أو حساب العميل
     */
    public static function charge(InstallmentDetail $detail, bool $dryRun = false, ?Carbon $asOf = null): float
    {
        $agreement = $detail->installment;

        if (! $agreement || ! $agreement->hasLateFeePolicy()) {
            return 0.0;
        }

        if ($detail->status === InstallmentDetail::STATUS_CANCELLED || $detail->is_paid) {
            return 0.0;
        }

        if ($agreement->status === Installment::STATUS_CANCELLED) {
            return 0.0;
        }

        $due = InstallmentSchedule::lateFeeFor(
            (float) $detail->remaining_amount,
            $detail->due_date,
            $agreement->late_fee_type,
            (float) $agreement->late_fee_value,
            (int) $agreement->late_fee_grace_days,
            $agreement->late_fee_basis ?: InstallmentSchedule::FEE_ONCE,
            $agreement->late_fee_max !== null ? (float) $agreement->late_fee_max : null,
            $asOf
        );

        $delta = round($due - (float) $detail->late_fee_amount, 2);

        // الفرقُ السالب يُتخطّى: قيدٌ رُحِّل لا يُعكَس لأن أساسَه صغُر
        if ($delta <= 0.009) {
            return 0.0;
        }

        if ($dryRun) {
            return $delta;
        }

        $feeAccount = self::feeAccount();
        $clientAccount = self::clientAccount($agreement);

        return DB::transaction(function () use ($detail, $agreement, $due, $delta, $feeAccount, $clientAccount, $asOf) {
            $date = $asOf ? $asOf->copy() : now();

            $journal = JournalEntry::create([
                'reference_number' => self::reference($agreement, $detail),
                'date' => $date,
                'description' => __('sales_installments.late_fee.journal', [
                    'number' => $detail->installment_number,
                    'code' => $agreement->code,
                    'days' => InstallmentSchedule::lateDaysFor(
                        $detail->due_date,
                        (int) $agreement->late_fee_grace_days,
                        $asOf
                    ),
                ]),
                'status' => 1,
                'currency' => $agreement->currency_code ?: CurrencySettings::code(),
                'client_id' => $agreement->client_id,
                'invoice_id' => $agreement->invoice_id,
                'created_by_employee' => auth()->id(),
                'reference_type' => ReferenceType::INSTALLMENT_LATE_FEE,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // ذمّة العميل تزيد: مبلغٌ استُحقّ عليه ولم يُقبَض
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $clientAccount->id,
                'description' => __('sales_installments.late_fee.debit_line', [
                    'number' => $detail->installment_number,
                ]),
                'debit' => $delta,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // وإيرادُ الغرامات يُعترَف به — في حسابه هو لا في المبيعات
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $feeAccount->id,
                'description' => __('sales_installments.late_fee.credit_line', [
                    'code' => $agreement->code,
                ]),
                'debit' => 0,
                'credit' => $delta,
                'is_debit' => false,
            ]);

            /* **لا تُكتب الأرصدة بيدٍ هنا.**
             *
             * `JournalEntryDetail::created` يُشغّل `Account::updateParentBalances()`،
             * وهي **تُعيد حساب** رصيد الحساب من قيوده (`computeFullBalance`)
             * ثمّ تُصعّده إلى آبائه. فالسطران أعلاه — لو بقيا — يقعان
             * **بعدها**، فيدهسان قيمةً محسوبةً بقيمةٍ حسابية `القديم + الفرق`
             * مقروءةٍ من نموذجٍ حُمِّل قبل القيد.
             *
             * وتوافقُهما وقتَ كتابة هذه الشيفرة صدفةٌ لا قاعدة: القديمُ كان
             * يساوي ما قبل القيد بالضبط. وأيُّ حركةٍ أخرى على الحساب بين
             * التحميل والحفظ تجعل الورقةَ تخالف دفترَ الأستاذ — ثمّ
             * **يبقى الأبُ على القيمة المحسوبة والابنُ على المكتوبة**،
             * فيختلف المجموع عن أبنائه بلا سببٍ ظاهر.
             *
             * (والنمط نفسُه في `SalesPaymentController::store()` أقلُّ ضرراً:
             * الكتابةُ هناك تسبق القيد فتدهسها إعادةُ الحساب.)
             */
            $detail->forceFill([
                'late_fee_amount' => $due,
                'late_fee_days' => InstallmentSchedule::lateDaysFor(
                    $detail->due_date,
                    (int) $agreement->late_fee_grace_days,
                    $asOf
                ),
                'late_fee_posted_at' => $date,
                'late_fee_journal_id' => $journal->id,
            ])->save();

            // الغرامةُ جزءٌ من ذمّة الفاتورة: يتبعها متبقّيها وحالةُ سدادها
            if ($agreement->invoice) {
                InstallmentFinancing::refreshInvoice($agreement->invoice);
            }

            return $delta;
        });
    }

    /**
     * الأقساط المستحقّة للتحميل — متأخّرةٌ في اتفاقيةٍ لها سياسةُ غرامة.
     *
     * التصفيةُ في الاستعلام لا في PHP: جدولُ الأقساط ينمو بعدد
     * الاتفاقيات في عدد أقساطها، وقراءتُه كلَّه كلّ ليلةٍ لتصفيةِ عشرةٍ
     * منه استعلامٌ يكبر بلا حدّ.
     */
    public static function chargeable(?Carbon $asOf = null)
    {
        $today = ($asOf ? $asOf->copy() : now())->startOfDay()->toDateString();

        return InstallmentDetail::query()
            ->with(['installment.client', 'installment.invoice'])
            ->unpaid()
            ->whereDate('due_date', '<', $today)
            ->whereHas('installment', function ($agreement) {
                $agreement->whereIn('late_fee_type', ['percent', 'fixed'])
                    ->where('late_fee_value', '>', 0)
                    ->where('status', '!=', Installment::STATUS_CANCELLED);
            })
            ->orderBy('due_date');
    }

    /**
     * حساب الغرامات — من «الحسابات المحاسبية» وحدها.
     *
     * بلا احتياطٍ من `SystemAccountIds`: مفتاحٌ غير مربوط يعني أنّ
     * المحاسب لم يقرّر بعدُ أين تذهب الغرامة، والتخمينُ عنه يضع إيراداً
     * في حسابٍ يُكتشَف خطؤه عند الإقفال.
     */
    public static function feeAccount(): Account
    {
        $account = AccountingSettings::account(self::ACCOUNT_KEY);

        if (! $account) {
            throw new RuntimeException(__('settings.accounting_settings.account_not_mapped', [
                'account' => __('sales_installments.fields.late_fee_account'),
            ]));
        }

        return $account;
    }

    /** حسابُ ذمّة العميل — لا حسابُ محفظته. */
    private static function clientAccount(Installment $agreement): Account
    {
        $account = $agreement->client_id
            ? Account::ledger()->where('client_id', $agreement->client_id)->first()
            : null;

        if (! $account) {
            throw new RuntimeException(__('sales_installments.messages.no_client_account'));
        }

        return $account;
    }

    /** مرجعُ القيد — رقمُ الاتفاقية ورقمُ القسط، فيُعرَف مصدرُه بلا بحث. */
    private static function reference(Installment $agreement, InstallmentDetail $detail): string
    {
        return sprintf('%s-LF%02d', $agreement->code ?: ('INS'.$agreement->id), (int) $detail->installment_number);
    }
}
