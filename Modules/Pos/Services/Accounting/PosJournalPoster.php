<?php

namespace Modules\Pos\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Facades\Auth;

/**
 * القيدُ المحاسبي لنقاط البيع — يُبنى متوازناً أو لا يُكتب.
 *
 * ═════════════════════ أصلُ المشكلة ═════════════════════
 *
 * كانت قيودُ نقاط البيع تُنشأ في سبع دوالّ، كلٌّ منها بهذه البنية:
 *
 *     private function createJournalEntries(...) {
 *         try { ... }
 *         catch (\Exception $e) {
 *             Log::error('خطأ في إنشاء القيود المحاسبية: ' . $e->getMessage());
 *             // ← لا rethrow، لا rollback
 *         }
 *     }
 *
 * و`store()` يفتح `DB::beginTransaction()` ثم يُنشئ الفاتورة ويخصم المخزون
 * ثم يستدعيها. فإذا فشل القيدُ لأي سبب — حسابٌ مفقود، عمودٌ ناقص، قيمةُ
 * `null` — **يُلتقط الاستثناء داخلياً ويُسجَّل، ثم يصل التنفيذ إلى
 * `DB::commit()` بنجاح**.
 *
 * النتيجة: فاتورةٌ مُصدَرة، ومخزونٌ منقوص، ولا قيدَ محاسبي. الميزانيةُ لا
 * تتوازن، والفاتورةُ لا تظهر في دفتر الأستاذ، ولا شيءَ في الشاشة يقول ذلك.
 * ويومُ تشغيلٍ كاملٌ قد يمرّ هكذا.
 *
 * ═════════════════════ القاعدة هنا ═════════════════════
 *
 * **لا `catch` صامت.** ما يفشل يرمي، والمعاملةُ تتراجع كما ينبغي: لا فاتورة
 * ولا خصمَ مخزون ولا قيد — حالةٌ متّسقة يفهمها المستخدم، بدل حالةٍ صامتة
 * لا يفهمها أحد.
 *
 * **ولا قيدَ غيرُ متوازن.** `post()` تجمع المدين والدائن قبل الكتابة وترفض
 * ما لا يتساوى. وهذا يمسك ما لم يكن يُمسك: قيدُ المرتجع مثلاً كان يُنشأ
 * **فقط إذا كانت الفاتورة مدفوعةً بالكامل** (`if ($originalInvoice->payment_status == 1)`)
 * — بلا `else`. فمرتجعُ فاتورةٍ آجلة كان يُعيد البضاعة للمخزون ويُنشئ
 * فاتورةَ مرتجعٍ ولا يكتب قيداً واحداً.
 *
 * ═════════════════════ والسياقُ يُختَم مع القيد ═════════════════════
 *
 * `branch_id` و`accounting_period_id` و`currency` أعمدةٌ موجودة في
 * `journal_entries` منذ البداية — **ولم تكن نقطةُ البيع تملأ منها إلا
 * العملة، وبقيمةٍ ثابتة `'SAR'` مكتوبة في الشيفرة**. فقيودُ نقاط البيع
 * كانت بلا فرعٍ وبلا فترة: لا تظهر في ميزان مراجعة فرع، ولا تُقفل مع فترتها.
 */
class PosJournalPoster
{
    /** سطورُ القيد قيدَ التجميع. */
    private array $lines = [];

    private array $header = [];

    /** فرقُ التقريب المقبول قبل رفض القيد. */
    private const BALANCE_TOLERANCE = 0.01;

    /**
     * بدءُ قيدٍ جديد.
     *
     * @param  array{
     *     reference_number: string,
     *     description: string,
     *     date?: mixed,
     *     currency: string,
     *     reference_type?: mixed,
     *     linking_type?: mixed,
     *     invoice_id?: int|null,
     *     client_id?: int|null,
     *     branch_id?: int|null,
     *     accounting_period_id?: int|null
     * }  $header
     */
    public function __construct(array $header = [])
    {
        $this->header = $header;
    }

    public static function make(array $header = []): self
    {
        return new self($header);
    }

    /** سطرٌ مدين. */
    public function debit(int|Account|null $account, float $amount, string $description = ''): self
    {
        return $this->line($account, $amount, 0.0, $description);
    }

    /** سطرٌ دائن. */
    public function credit(int|Account|null $account, float $amount, string $description = ''): self
    {
        return $this->line($account, 0.0, $amount, $description);
    }

    /**
     * سطرٌ عام.
     *
     * السطورُ الصفرية تُهمَل: قيدُ بيعٍ بلا خصمٍ أو بلا ضريبة لا يحتاج
     * سطراً بصفر — وكانت الشيفرة تكتبها أحياناً وتتخطّاها أحياناً بحسب
     * الدالة، فتختلف صورةُ القيد الواحد بين حالتين.
     */
    public function line(int|Account|null $account, float $debit, float $credit, string $description = ''): self
    {
        $accountId = $account instanceof Account ? (int) $account->id : $account;

        if (! $accountId) {
            throw new \RuntimeException(
                'محاولةُ كتابة سطرٍ في قيد نقطة البيع بلا حساب: '.($description ?: 'بلا وصف')
            );
        }

        if (abs($debit) < 0.005 && abs($credit) < 0.005) {
            return $this;
        }

        $this->lines[] = [
            'account_id' => $accountId,
            'description' => $description ?: ($this->header['description'] ?? ''),
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'is_debit' => $debit > 0,
        ];

        return $this;
    }

    /** أفي القيد سطورٌ أصلاً؟ */
    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function totalDebit(): float
    {
        return round(array_sum(array_column($this->lines, 'debit')), 2);
    }

    public function totalCredit(): float
    {
        return round(array_sum(array_column($this->lines, 'credit')), 2);
    }

    /**
     * كتابةُ القيد — أو رميُ استثناء.
     *
     * @throws \RuntimeException إن كان القيد فارغاً أو غيرَ متوازن
     */
    public function post(): ?JournalEntry
    {
        if ($this->isEmpty()) {
            // قيدٌ بلا سطور ليس خطأً دائماً: فاتورةٌ بإجمالي صفر لا قيد لها
            return null;
        }

        $debit = $this->totalDebit();
        $credit = $this->totalCredit();

        if (abs($debit - $credit) > self::BALANCE_TOLERANCE) {
            throw new \RuntimeException(sprintf(
                'قيدٌ غيرُ متوازن في نقطة البيع (%s): مدين %s، دائن %s، الفرق %s',
                $this->header['reference_number'] ?? 'بلا مرجع',
                number_format($debit, 2),
                number_format($credit, 2),
                number_format($debit - $credit, 2)
            ));
        }

        $entry = JournalEntry::create([
            'reference_number' => $this->header['reference_number'] ?? null,
            'date' => $this->header['date'] ?? now(),
            'description' => $this->header['description'] ?? null,
            'status' => 1,
            'currency' => $this->header['currency'] ?? null,
            'client_id' => $this->header['client_id'] ?? null,
            'invoice_id' => $this->header['invoice_id'] ?? null,
            'reference_type' => $this->header['reference_type'] ?? null,
            'linking_type' => $this->header['linking_type'] ?? null,
            // السياقُ الذي لم يكن يُملأ
            'branch_id' => $this->header['branch_id'] ?? null,
            'accounting_period_id' => $this->header['accounting_period_id'] ?? null,
            'created_by_employee' => Auth::id(),
        ]);

        foreach ($this->lines as $line) {
            JournalEntryDetail::create(array_merge($line, [
                'journal_entry_id' => $entry->id,
                'currency' => $this->header['currency'] ?? null,
            ]));
        }

        /*
            ولا تحديثَ للأرصدة هنا.

            `JournalEntryDetail::created` يتولّاه لكل سطر — انظر التعليق
            على `applyBalances` المحذوفة أدناه.
        */
        return $entry;
    }

    /**
     * ⚠️ حُذفت `applyBalances()` — كانت تُضاعف أثرَ كل قيد.
     *
     * ═══════════════ الازدواج ═══════════════
     *
     * `JournalEntryDetail::created` خطّافٌ في `boot()` يُنادي
     * `Account::updateParentBalances()` لكل سطرٍ يُكتب. وهي **تُعيد حسابَ
     * الرصيد من كل القيود** (`computeFullBalance`) — لا تُضيف الفرق.
     * فالسطرُ الجديد داخلٌ في الحساب لحظةَ كتابته.
     *
     * ثم كانت `applyBalances()` تجري بعدها وتُضيف الفرقَ **مرّةً ثانية**:
     *
     *     $account->increment('balance', $delta);
     *
     * فبيعٌ بمئةٍ يزيد حسابَ العميل مئتين. قِيس على حسابٍ رصيدُه ١٧٠٠:
     * بعد قيدٍ مدينُه ١٠٠ صار العمودُ ١٩٠٠ والصحيحُ من القيود ١٨٠٠.
     *
     * والخطأُ **تراكميّ**: كل فاتورةٍ تزيد الانحراف، وميزانُ المراجعة
     * يبقى متوازناً لأنّ الجانبين يتضاعفان معاً — فلا يُكتشف إلّا بمقارنة
     * عمود `accounts.balance` بمجموع `journal_entry_details`.
     *
     * ═══════════════ ولماذا الحذف لا الإصلاح ═══════════════
     *
     * الخطّافُ في النموذج يعمل لكل من يكتب سطرَ قيد — نقاطُ البيع
     * والسنداتُ والقيودُ اليدوية. فحسابُ الرصيد مسؤوليّتُه هو، ومَن يكتب
     * سطراً لا يعنيه كيف يُحدَّث الرصيد.
     *
     * وطبيعةُ الحساب (`balance_type`) التي كانت تُقرأ هنا يقرؤها
     * `calculateNetBalance` في النموذج نفسِه — فلا يضيع شيء.
     */
}
