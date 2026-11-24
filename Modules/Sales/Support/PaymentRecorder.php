<?php

namespace Modules\Sales\Support;

use App\Models\CreditNotification;
use App\Models\PaymentsProcess;
use App\Models\Receipt;
use App\Models\ReceivedCheque;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * «سجَّلها» في جدول مدفوعات العملاء — من **المستند الأصل** لا من عمود السند.
 *
 * ── لماذا ──
 *
 * الدفعةُ في `payments_process` تُولَد من مستندٍ آخر في أكثر الحالات: سندُ
 * القبض يوزَّع على الفواتير فيُكتب لكل فاتورةٍ صفّ، والفاتورةُ المدفوعة عند
 * إنشائها تُنشئ دفعتَها معها، والإيداعُ على الفاتورة يُسجَّل من شاشة السداد.
 * وعمودُ `employee_id` في الصفّ المولَّد لا يُكتب دائماً، وحين يُملأ تلقائياً
 * يُملأ برقم **موظف** في عمودٍ يُقرأ رقمَ **مستخدم** — فيظهر السندُ باسم شخصٍ
 * آخر يحمل الرقم نفسه (سندٌ سجّله المنصوب يُقرأ «سجَّلها مشتاق»).
 *
 * فالاسمُ هنا يُؤخذ ممّا خُزّن في المستند الأصل نفسِه، وبهذا الترتيب:
 *
 *   ١. **سند القبض** — `reference_number` هو رقمُ السند (`receipts.id`) أو
 *      رمزُه (`receipts.code`)؛ المُسجِّل مُنشئُ السند. صفوفُ التوزيع لا
 *      تكتب خزينةً قطّ، فمطابقةُ الرقم تُشترط لها خزينةٌ فارغة كي لا يُلتقط
 *      مرجعٌ كُتب باليد صادف رقمَ سند.
 *   ٢. **الإشعار الدائن** — `CN-<id>`؛ المُسجِّل مُصدرُ الإشعار.
 *   ٣. **الشيك المستلَم** — رقمُ الشيك مع ملاحظة التحصيل؛ المُسجِّل من حصّله.
 *   ٤. **الفاتورة** — الدفعةُ المولَّدة مع الفاتورة (بملاحظتها الثابتة) أو
 *      أيُّ دفعةٍ على فاتورةٍ بلا مُسجِّل؛ المُسجِّل مُنشئُ الفاتورة.
 *   ٥. وإلّا فعمودُ السند نفسُه — دفعةُ الإيداع من شاشة السداد تكتبه
 *      بـ`users.id` (انظر `PaymentsProcess::$recorded_by_name`).
 *
 * تُحمَّل المستنداتُ دفعةً واحدة لصفحة الجدول، لا استعلاماً لكل صفّ.
 * القراءةُ فقط: لا يُمسّ مسارُ حفظٍ ولا صفٌّ مخزَّن.
 */
class PaymentRecorder
{
    private const RECEIPT_TYPES = ['client payments', 'sale', 'previous_balance_payment'];

    private const CREDIT_NOTE_TYPES = ['credit_note_previous_balance', 'credit_note_settlement'];

    /**
     * @param  iterable<int, PaymentsProcess>  $payments  صفوفُ الصفحة، وفاتورتُها محمَّلة
     * @return array<int, string> معرّفُ السند ← اسمُ مَن سجّله
     */
    public static function names(iterable $payments): array
    {
        $payments = Collection::make($payments);

        if ($payments->isEmpty()) {
            return [];
        }

        $references = $payments->pluck('reference_number')->filter(fn ($ref) => filled($ref))
            ->map(fn ($ref) => (string) $ref)->unique()->values();

        $numeric = $references->filter(fn ($ref) => ctype_digit($ref))->map(fn ($ref) => (int) $ref)->values();

        $receiptsByCode = $references->isEmpty()
            ? collect()
            : Receipt::query()->whereIn('code', $references)->get(['id', 'code', 'created_by'])->keyBy('code');

        $receiptsById = $numeric->isEmpty()
            ? collect()
            : Receipt::query()->whereIn('id', $numeric)->get(['id', 'code', 'created_by'])->keyBy('id');

        $creditNoteIds = $references->filter(fn ($ref) => preg_match('/^CN-(\d+)$/', $ref))
            ->map(fn ($ref) => (int) substr($ref, 3))->values();

        $creditNotes = $creditNoteIds->isEmpty()
            ? collect()
            : CreditNotification::query()->whereIn('id', $creditNoteIds)->get(['id', 'created_by'])->keyBy('id');

        $cheques = $numeric->isEmpty()
            ? collect()
            : ReceivedCheque::query()->whereIn('id', $numeric)->get(['id', 'created_by'])->keyBy('id');

        // المُسجِّل لكل صفّ كـ`users.id` — ثم الأسماءُ دفعةً واحدة
        $userIdByPayment = [];

        foreach ($payments as $payment) {
            $userId = static::originUserId($payment, $receiptsByCode, $receiptsById, $creditNotes, $cheques);

            if ($userId) {
                $userIdByPayment[$payment->id] = (int) $userId;
            }
        }

        if (! $userIdByPayment) {
            return [];
        }

        $users = User::query()->with('employee')
            ->whereIn('id', array_unique(array_values($userIdByPayment)))
            ->get()
            ->keyBy('id');

        $names = [];

        foreach ($userIdByPayment as $paymentId => $userId) {
            $user = $users->get($userId);

            if (! $user) {
                continue;
            }

            // الاسمُ من ملفّ الموظف حين يكون له ملفّ — كما في شاشات الموارد البشرية
            $name = ($user->employee?->full_name ?: null) ?? $user->name;

            if (filled($name)) {
                $names[$paymentId] = $name;
            }
        }

        return $names;
    }

    /**
     * `users.id` لمُسجِّل السند من مستنده الأصل — أو `null` فيُقرأ عمودُ السند.
     */
    private static function originUserId(
        PaymentsProcess $payment,
        Collection $receiptsByCode,
        Collection $receiptsById,
        Collection $creditNotes,
        Collection $cheques
    ): ?int {
        $reference = filled($payment->reference_number) ? (string) $payment->reference_number : null;
        $isNumeric = $reference !== null && ctype_digit($reference);

        // ١. سند القبض
        if ($reference !== null && in_array($payment->type, self::RECEIPT_TYPES, true)) {
            $receipt = $receiptsByCode->get($reference);

            if (! $receipt && $isNumeric && $payment->treasury_id === null) {
                $receipt = $receiptsById->get((int) $reference);
            }

            if ($receipt && $receipt->created_by) {
                return (int) $receipt->created_by;
            }
        }

        // ٢. الإشعار الدائن
        if ($reference !== null && in_array($payment->type, self::CREDIT_NOTE_TYPES, true)
            && preg_match('/^CN-(\d+)$/', $reference, $m)) {
            $note = $creditNotes->get((int) $m[1]);

            if ($note && $note->created_by) {
                return (int) $note->created_by;
            }
        }

        // ٣. الشيك المستلَم
        if ($isNumeric && str_contains((string) $payment->notes, 'شيك رقم #')) {
            $cheque = $cheques->get((int) $reference);

            if ($cheque && $cheque->created_by) {
                return (int) $cheque->created_by;
            }
        }

        // ٤. الفاتورة: دفعتُها المولَّدة معها، أو أيُّ دفعةٍ عليها بلا مُسجِّل
        $invoice = $payment->relationLoaded('invoice') ? $payment->invoice : null;

        if ($invoice && $invoice->created_by) {
            $bornWithInvoice = str_starts_with((string) $payment->notes, 'تم إنشاء الدفعة تلقائي');

            if ($bornWithInvoice || ! $payment->employee_id) {
                return (int) $invoice->created_by;
            }
        }

        // ٥. عمودُ السند نفسُه
        return null;
    }
}
