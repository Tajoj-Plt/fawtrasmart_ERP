<?php

namespace Modules\Pos\Services\Payment;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use Modules\Pos\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosOrderPayment;

/**
 * حفظُ مدفوعات الفاتورة — وتوجيهُ كلِّ دفعةٍ إلى حسابها.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 *     private function processPayments($invoiceId, $payments)
 *     {
 *         $totalPaid = collect($payments)->sum('amount');
 *
 *         $validMethods = PaymentMethod::whereIn('id', $paymentMethodIds)
 *             ->where('status', 0)          // ← عمودٌ نصّي يُقارَن بعدد
 *             ->count();
 *
 *         if ($validMethods !== $paymentMethodIds->count()) {
 *             throw new \Exception('إحدى طرق الدفع غير صحيحة أو غير متاحة');
 *         }
 *
 *         Log::info('تم معالجة المدفوعات للفاتورة: ' . $invoiceId, [...]);
 *     }
 *
 * دالّةٌ اسمها «معالجة المدفوعات» تتحقّق — بفحصٍ لا يوافق شيئاً أبداً لأنّ
 * `status` عمودٌ نصّيّ افتراضُه `'active'` لا `0` — ثم **تكتب في اللوغ
 * وتنتهي**. لا `Payment::create()` ولا صفٌّ في أيّ جدول.
 *
 * فالأثرُ الوحيد الباقي كان عمودَ `invoices.payment_method`: رقمٌ إن كانت
 * الطريقةُ واحدة، ونصَّ JSON مضغوطاً إن كانت متعدّدة. عمودٌ واحدٌ بنوعين،
 * لا يُستعلَم عنه ولا يُجمَع ولا يُفهرَس.
 *
 * ═════════════════════ وتوجيهُ الحساب ═════════════════════
 *
 * كان القيدُ يوجّه المبالغ بقاعدةٍ ثنائية: `is_online` ⇒ البنكُ الافتراضي،
 * وإلّا ⇒ الخزينة. فمنشأةٌ تقبل ثلاثَ بوابات بثلاثة حسابات بنكية تُرحَّل
 * حصيلتُها كلُّها إلى حسابٍ واحد.
 *
 * والآن: `payment_methods.account_id` إن رُبط ← البنكُ الافتراضي ← الخزينة.
 * والقاعدةُ القديمة تبقى احتياطاً، فلا ينكسر شيءٌ قبل أن يربط المحاسب.
 */
class PosPaymentRecorder
{
    use ResolvesAccountingAccounts;
    use ResolvesPosSettings;
    use UsesDocumentCurrency;

    /** ذاكرةُ طرق الدفع للطلب الواحد — الفاتورة تسأل عن الطريقة نفسها مراراً. */
    private array $methodCache = [];

    /**
     * حفظُ دفعات فاتورة.
     *
     * ═════════ الزائدُ باقٍ لا دفعة ═════════
     *
     * الكاشير يستلم ٢٣٠ لفاتورةٍ بـ٢٠٠٫٣٠ ويردّ ٢٩٫٧٠. والمبلغُ الذي **دخل
     * الصندوق فعلاً** هو ٢٠٠٫٣٠ لا ٢٣٠.
     *
     * فلو حُفظت الدفعةُ بـ٢٣٠ لانفصل طرفا القيد: الخزينةُ مدينةٌ بـ٢٣٠
     * وحسابُ العميل دائنٌ بـ٢٠٠٫٣٠ (إجمالي الفاتورة) — فرقٌ قدرُه الباقي
     * المُعاد. وهو بالضبط ما أمسكه حارسُ التوازن في [PosJournalPoster].
     *
     * فتُوزَّع الدفعاتُ على المتبقّي من الفاتورة: ما غطّاه يُحفظ `amount`،
     * والزائدُ يُحفظ `change_amount` مع `tendered_amount` الأصلي. فيبقى
     * أثرُ ما استلمه الكاشير كاملاً، ويوافق القيدُ الفاتورةَ.
     *
     * @param  array<int, array{method_id: int, amount: float, tendered?: float, reference?: string, card_last4?: string}>  $payments
     * @return \Illuminate\Support\Collection<int, PosOrderPayment>
     */
    public function record(Invoice $invoice, array $payments, ?PosSession $session = null)
    {
        $saved = collect();

        $remaining = round((float) $invoice->grand_total, 2);

        foreach ($payments as $payment) {
            $method = $this->method((int) $payment['method_id']);

            if (! $method) {
                throw new \RuntimeException("طريقةُ دفعٍ غير موجودة: {$payment['method_id']}");
            }

            if (! $this->isActive($method)) {
                throw new \RuntimeException("طريقةُ الدفع «{$method->name}» غير مفعَّلة.");
            }

            $offered = round((float) $payment['amount'], 2);

            if ($offered <= 0) {
                continue;
            }

            // ما غطّاه من الفاتورة، وما زاد عنها
            $applied = round(min($offered, max($remaining, 0)), 2);
            $change = round($offered - $applied, 2);

            $remaining = round($remaining - $applied, 2);

            /*
                دفعةٌ كلُّها زائد — الفاتورةُ سُدِّدت بما قبلها.

                تُهمَل ولا تُحفظ بصفرٍ: صفٌّ بمبلغ صفر يُشوّش تقرير التحصيل
                ويُحتسب «دفعةً» في عدّه.
            */
            if ($applied <= 0) {
                continue;
            }

            // المستلَمُ فعلاً: ما صرّح به الكاشير، وإلّا ما عرضه
            $tendered = isset($payment['tendered'])
                ? round((float) $payment['tendered'], 2)
                : $offered;

            $saved->push(PosOrderPayment::create([
                'invoice_id' => $invoice->id,
                'session_id' => $session?->id,
                'payment_method_id' => $method->id,
                'amount' => $applied,
                'tendered_amount' => $tendered,
                // الباقي يُحفظ لا يُحسب في الاستجابة وحدها: الإيصالُ المُعاد
                // طبعُه كان يفقده، ولا تدقيقَ يراه
                'change_amount' => max(0, round($tendered - $applied, 2)),
                'gateway_reference' => $payment['reference'] ?? null,
                'card_last4' => $payment['card_last4'] ?? null,
                'status' => PosOrderPayment::STATUS_COMPLETED,
                // ما يحتاج تسويةً يبقى `settled_at` فارغاً حتى تُطابَق كشوفُ البنك
                'settled_at' => $method->requires_settlement ? null : now(),
                // العملةُ والفرعُ والفترةُ والمُنشئ تُختَم في [PosOrderPayment]
            ]));
        }

        return $saved;
    }

    /**
     * تجميعُ الدفعات بحسب حسابِ وجهتها — لبناء قيدٍ واحدٍ متعدّد السطور.
     *
     * @param  \Illuminate\Support\Collection<int, PosOrderPayment>  $payments
     * @return array<int, float> معرّفُ الحساب => المبلغ
     */
    public function groupByDestinationAccount($payments, ?int $employeeId = null): array
    {
        $grouped = [];

        foreach ($payments as $payment) {
            $accountId = $this->destinationAccountId($payment, $employeeId);

            $grouped[$accountId] = ($grouped[$accountId] ?? 0) + (float) $payment->amount;
        }

        return array_map(fn ($v) => round($v, 2), $grouped);
    }

    /**
     * الحسابُ الذي تُرحَّل إليه دفعةٌ بعينها.
     *
     * الترتيب: حسابُ الطريقة ← البنكُ الافتراضي (للإلكترونية) ← خزينةُ الكاشير.
     *
     * @throws \RuntimeException إن كانت الطريقة إلكترونيةً بلا حسابٍ ولا بنكٍ افتراضي
     */
    public function destinationAccountId(PosOrderPayment $payment, ?int $employeeId = null): int
    {
        $method = $this->method($payment->payment_method_id);

        if ($method?->account_id && Account::whereKey($method->account_id)->exists()) {
            return (int) $method->account_id;
        }

        if ($method?->is_online) {
            /*
                البنكُ الافتراضي من «الحسابات المحاسبية» (`pos_default_bank`)
                لا من عمودٍ خاصّ بنقطة البيع.

                كان `pos_general_settings.default_bank_account_id` — حسابٌ
                محاسبيّ مضبوطٌ خارج شاشة الحسابات، فلا يعرف المحاسبُ أنه هناك
                ولا يظهر في مراجعة الربط. والعمودُ يبقى احتياطاً للبيانات
                القديمة التي ضُبطت قبل نقل المفتاح.
            */
            $bankId = $this->accountingAccountId('pos_default_bank')
                ?: $this->posSettings()?->default_bank_account_id;

            if (! $bankId) {
                throw new \RuntimeException(
                    "لا يمكن استخدام طريقة الدفع «{$method->name}»: هي طريقةٌ إلكترونية ولم يُربَط لها حساب، ولم يُحدَّد «البنك الافتراضي» في الحسابات المحاسبية."
                );
            }

            return (int) $bankId;
        }

        return (int) $this->treasuryAccountFor($employeeId)->id;
    }

    /**
     * صافي النقد والبطاقة — لتفاصيل الجلسة.
     *
     * كان التقسيمُ يُبنى على `is_online` وحده في المتحكّم. وهو هنا يبقى —
     * لأنّ عمودَي `cash_amount`/`card_amount` في تفاصيل الجلسة ثنائيّان —
     * لكن **التفصيلَ الحقيقي محفوظٌ في `pos_order_payments`**، فالتقريرُ
     * يقرأه منه لا من هذين العمودين.
     *
     * @param  \Illuminate\Support\Collection<int, PosOrderPayment>  $payments
     * @return array{cash: float, card: float}
     */
    public function splitCashAndCard($payments): array
    {
        $cash = 0.0;
        $card = 0.0;

        foreach ($payments as $payment) {
            if ($this->method($payment->payment_method_id)?->is_online) {
                $card += (float) $payment->amount;
            } else {
                $cash += (float) $payment->amount;
            }
        }

        return ['cash' => round($cash, 2), 'card' => round($card, 2)];
    }

    /**
     * أَفعّالةٌ هذه الطريقة؟
     *
     * `status` و`is_active` عمودان موجودان معاً في الجدول — الأولُ نصٌّ
     * افتراضُه `'active'` والثاني بولياني. فالاكتفاءُ بأحدهما يُقصي صفوفاً
     * صحيحة بحسب أيّهما مُلئ.
     */
    private function isActive(PaymentMethod $method): bool
    {
        return $method->status === 'active' || (bool) $method->is_active;
    }

    private function method(int $id): ?PaymentMethod
    {
        return $this->methodCache[$id] ??= PaymentMethod::find($id);
    }
}
