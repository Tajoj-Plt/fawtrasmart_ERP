<?php

namespace Modules\Manufacturing\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Carbon;
use Modules\Manufacturing\Constants\ManufacturingSources;

/**
 * نقطة واحدة لكل قيد يخرج من موديول التصنيع.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * 1. **الحسابات مكتوبة في الشيفرة.** المولّدات تستعمل ثوابت
 *    `SystemAccountIds` مباشرةً، فما يربطه المحاسب في شاشة «الحسابات
 *    المحاسبية» لا أثر له. هنا تُقرأ من `AccountingSettings` مع الثابت
 *    كاحتياط — نفس تعاقد وحدتَي المخزون والمشتريات.
 *
 * 2. **الأرصدة تُعدَّل يدوياً — ومرتين.** الكود يكتب
 *    `$account->balance -= $amount; $account->save();` ثم
 *    `Account::updateBalancesHierarchically()`. لكن
 *    `JournalEntryDetail::created` يستدعي `Account::updateParentBalances`
 *    تلقائياً، وهي تُعيد **احتساب** الرصيد من القيود لا تزيده. فالتعديل
 *    اليدوي إمّا يُمحى بعد سطور، أو يترك قيمةً شاذة بينهما.
 *    **الحل: لا نلمس `balance` إطلاقاً.**
 *
 * 3. **العكس بالبحث النصّي.** `undoCompletion` يبحث بـ
 *    `description LIKE '%أمر التصنيع: '.$order->name.' (#'.$order->id.')%'`
 *    وتعليقه يزعم أن الجدول بلا معرّف أمر — وهو غير صحيح: القيود تُنشأ
 *    بـ`reference_type` و`reference_id`. وثلاث نتائج للبحث النصّي:
 *      · القيد العكسي يطابق النمط نفسه فيُعكس ثانيةً ⇒ تضخيم مضاعف
 *      · `$order->name` يدخل LIKE بلا هروب ⇒ اسمٌ اسمه `%` يعكس الأستاذ كله
 *      · تعديل الاسم بعد الإتمام يكسر المطابقة ⇒ لا يُعكس شيء بصمت
 *
 * 4. **لا فحص توازن.** القيد يدخل بحالة مُرحَّل ومجموع مدينه من مصدر
 *    ومجموع دائنه من مصدر آخر.
 *
 * 5. **العملة مكتوبة `'SAR'` أو مهملة.** هنا من `CurrencySettings` أو
 *    من عملة المستند المجمَّدة.
 *
 * ── الاستعمال ────────────────────────────────────────────────────────
 *
 *     app(ManufacturingJournalService::class)->post(
 *         lines: [
 *             ['key' => ManufacturingSources::ACCOUNT_WIP, 'debit' => 500, 'description' => 'مواد خام'],
 *             ['key' => ManufacturingSources::ACCOUNT_RAW_MATERIALS, 'credit' => 500],
 *         ],
 *         referenceId: $order->id,
 *         description: 'صرف مواد لأمر التصنيع: '.$order->name,
 *         date: $order->materials_received_at,
 *         currency: $order->currency,
 *     );
 *
 * السطر يقبل `key` (مفتاح إعدادات) أو `account_id` (حساب بعينه، كحساب
 * المستودع أو حساب المصروف المختار في البند).
 */
class ManufacturingJournalService
{
    /** فارق التقريب المسموح قبل رفض القيد. */
    private const BALANCE_TOLERANCE = 0.01;

    /**
     * ترحيل قيد متوازن مرتبط بأمر تصنيع.
     *
     * @param  array<int, array{key?:string, account_id?:int, fallback?:int, debit?:float, credit?:float, description?:string}>  $lines
     *
     * @throws \RuntimeException إن اختلّ التوازن أو غاب حساب إلزامي
     */
    public function post(
        array $lines,
        int $referenceId,
        string $description,
        Carbon|string|null $date = null,
        ?string $currency = null,
        ?int $clientId = null,
        ?int $employeeId = null,
        ReferenceType $referenceType = ReferenceType::MANUFACTURING_ORDER,
        ?string $stage = null
    ): ?JournalEntry {
        $resolved = $this->resolveLines($lines);

        if ($resolved === []) {
            return null;   // لا سطور ذات قيمة — لا قيد
        }

        $debit = round(array_sum(array_column($resolved, 'debit')), 2);
        $credit = round(array_sum(array_column($resolved, 'credit')), 2);

        if (abs($debit - $credit) > self::BALANCE_TOLERANCE) {
            throw new \RuntimeException(sprintf(
                'قيد غير متوازن — %s: مدين %s ≠ دائن %s (الفرق %s). أُلغيت العملية.',
                $description,
                number_format($debit, 2),
                number_format($credit, 2),
                number_format($debit - $credit, 2)
            ));
        }

        $currency = $currency ?: CurrencySettings::code();

        // `branch_id` و`reference_number` يُملآن تلقائياً في JournalEntry::boot
        $entry = JournalEntry::create([
            'date' => $date ? Carbon::parse($date) : now(),
            'description' => $description,
            'status' => 1,
            'is_posted' => 1,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'linking_type' => LinkingType::MULTIPLE,
            // وسمُ المرحلة — يُمكّن العكسَ الانتقائي. انظر reverseFor().
            'batch_reference' => $stage,
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'created_by_employee' => auth()->id(),
        ]);

        foreach ($resolved as $line) {
            // لا نلمس Account::balance — مراقب JournalEntryDetail::created
            // يستدعي Account::updateParentBalances ويُعيد احتسابه من القيود.
            JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? $description,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'currency' => $currency,
                'is_debit' => $line['debit'] > 0,
            ]);
        }

        return $entry;
    }

    /**
     * عكس قيود أمر تصنيع — بالمفتاح لا بالوصف، وبمرحلتها لا جملةً.
     *
     * يتخطّى ما عُكس سلفاً وما هو عكسٌ أصلاً، فلا يتضخّم مهما تكرّرت
     * دورة إتمام/تراجع.
     *
     * ── لماذا `$stages` ──────────────────────────────────────────────
     *
     * كانت الدالّة تعكس **كل** قيود الأمر بلا تمييز. و`undoCompletion`
     * تستدعيها لتتراجع عن الإتمام وحده، فتعكس معه **قيدَ صرف المواد**
     * الذي رُحِّل في مرحلةٍ سابقة — بينما إذنُ الصرف نفسه لا يُعكَس،
     * فتبقى المواد مستهلَكةً في المخزون ويعود ثمنُها إلى الدفتر.
     *
     * وأثرٌ ثانٍ أسوأ: `rawMaterialsAlreadyPosted()` تفحص **الإذن** لا
     * القيد، والإذنُ باقٍ. فإتمامٌ ثانٍ يُسقط سطرَ «مواد خام» من قيده
     * ظنّاً أنه رُحِّل، وقد عُكس فعلاً — فيبتلع حسابُ الانحراف تكلفةَ
     * المواد كلَّها ويظهر انحرافٌ مواتٍ كاذبٌ بحجمها.
     *
     * فالعكسُ يُقيَّد بمرحلته: `undoCompletion` تعكس قيودَ الإتمام
     * وحدها، و`destroy` تعكس كل شيء (بلا تقييد).
     *
     * @param  array<int, string>|null  $stages  وسومُ المراحل المطلوب عكسها، أو null للكل
     * @return int عدد القيود المعكوسة
     */
    public function reverseFor(
        int $referenceId,
        string $reason,
        ReferenceType $referenceType = ReferenceType::MANUFACTURING_ORDER,
        ?array $stages = null
    ): int {
        $entries = JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where(function ($q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->where('description', 'not like', 'عكس قيد%')
            ->when($stages !== null, fn ($q) => $q->whereIn('batch_reference', $stages))
            ->with('details')
            ->get();

        $count = 0;

        foreach ($entries as $original) {
            $reversal = JournalEntry::create([
                'date' => now(),
                'description' => 'عكس قيد — '.$reason.' — '.$original->description,
                'status' => 1,
                'is_posted' => 1,
                'is_reversed' => true,   // وسمٌ يمنع عكسه هو نفسه
                'currency' => $original->currency,
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'linking_type' => LinkingType::MULTIPLE,
                // العكسُ يرث وسمَ أصله، فيبقى منسوباً إلى مرحلته
                'batch_reference' => $original->batch_reference,
                'client_id' => $original->client_id,
                'employee_id' => $original->employee_id,
                'created_by_employee' => auth()->id(),
            ]);

            foreach ($original->details as $detail) {
                JournalEntryDetail::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id' => $detail->account_id,
                    'description' => 'عكس: '.$detail->description,
                    'debit' => $detail->credit,     // مقلوب
                    'credit' => $detail->debit,
                    'currency' => $detail->currency ?: $original->currency,
                    'is_debit' => (float) $detail->credit > 0,
                ]);
            }

            $original->update(['is_reversed' => true]);

            $count++;
        }

        return $count;
    }

    /**
     * ما تبقّى في حساب «مخزون تحت التشغيل» لأمر بعينه.
     *
     * يجب أن يكون صفراً لكل أمر مكتمل. غير الصفر يعني أن قيد الانحراف
     * لم يُرحَّل — وهو ما كان يحدث في كل أمر قبل هذا الإصلاح.
     */
    public function wipResidueFor(int $orderId): float
    {
        $wipAccountId = $this->accountId(ManufacturingSources::ACCOUNT_WIP);

        if (! $wipAccountId) {
            return 0.0;
        }

        $row = JournalEntryDetail::query()
            ->join('journal_entries as e', 'e.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('e.reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('e.reference_id', $orderId)
            ->where('journal_entry_details.account_id', $wipAccountId)
            ->selectRaw('COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0) AS residue')
            ->first();

        return round((float) ($row->residue ?? 0), 2);
    }

    // ═════════════════════════════════════════════════════════════════
    // حلّ الحسابات
    // ═════════════════════════════════════════════════════════════════

    /**
     * معرّف الحساب المربوط لمفتاح — أو الاحتياط البرمجي.
     */
    public function accountId(string $key, ?int $fallback = null): ?int
    {
        return AccountingSettings::id($key, $fallback);
    }

    /**
     * معرّف الحساب أو استثناء — للقيود التي لا تصحّ بلا حساب.
     *
     * @throws \RuntimeException
     */
    public function requireAccountId(string $key, ?int $fallback = null): int
    {
        $id = $this->accountId($key, $fallback);

        if (! $id) {
            throw new \RuntimeException(sprintf(
                'الحساب المحاسبي «%s» غير مربوط. اربطه من: الإعدادات ← الحسابات المحاسبية، ثم أعِد المحاولة.',
                $key
            ));
        }

        return $id;
    }

    /**
     * يحوّل السطور إلى صيغة قابلة للكتابة، ويُسقط الأصفار.
     *
     * @return array<int, array{account_id:int, debit:float, credit:float, description:?string}>
     */
    private function resolveLines(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            // سطرٌ بصفر لا يُكتب: القيد يمتلئ بسطور بلا أثر ويصعب قراءته
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException(
                    'سطر قيد يحمل مديناً ودائناً معاً — أحدهما فقط في كل سطر.'
                );
            }

            $accountId = isset($line['account_id'])
                ? (int) $line['account_id']
                : $this->requireAccountId($line['key'], $line['fallback'] ?? null);

            $out[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        return $out;
    }
}
