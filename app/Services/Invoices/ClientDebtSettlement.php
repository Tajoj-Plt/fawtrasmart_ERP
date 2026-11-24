<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceType;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryInvoice;
use App\Models\PaymentsProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Client\Services\CustomerLedgerService;

/**
 * تسويةُ الإشعار الدائن على ديون العميل — الرصيدُ السابق أوّلاً ثمّ
 * الفواتيرُ من الأقدم إلى الأحدث.
 *
 * ══════════ لماذا ══════════
 *
 * كان الإشعار الدائن يُنزِل حسابَ العميل في الأستاذ ولا يقول **أيَّ دَينٍ**
 * أطفأ: عميلٌ عليه افتتاحيٌّ ١٨٠ وفاتورتان بـ١٨٠ يردّ بضاعةً بـ٥٤٠، فيصير
 * حسابُه صفراً وتبقى الفاتورتان «غير مدفوعة» بمستحقٍّ ١٨٠ لكلٍّ منهما.
 * ثمّ يقرأ سندُ القبض التالي الرصيدَ السابق على أنه «رصيدُ الحساب ناقصاً
 * مستحقَّ الفواتير» (صفرٌ ناقص ٣٦٠) فيسدّد فواتيرَ مُطفأةً أصلاً.
 *
 * ══════════ الترتيب ══════════
 *
 *   ١. الفاتورةُ المختارة مرجعاً للإشعار إن وُجدت — من فتح الإشعار من
 *      فاتورةٍ بعينها قصد تلك الفاتورة.
 *   ٢. الرصيدُ السابق (الافتتاحيّ وما لا فاتورةَ له) — بالقاعدة نفسها التي
 *      يتّبعها سندُ القبض في `IncomesController::applyPaymentToInvoices()`.
 *   ٣. الفواتيرُ المفتوحة من الأقدم إلى الأحدث.
 *   ٤. ما فضَل يبقى رصيداً دائناً للعميل في حسابه — لا يُكتب له صفّ.
 *
 * ══════════ ما يُكتب على الفاتورة ══════════
 *
 * ما أطفأه الإشعارُ من فاتورةٍ يُضاف إلى `returned_payment` («مرتجع» في
 * الشاشات والمطبوعات) كما يفعل مرتجعُ المبيعات — لا إلى المدفوع. فالفاتورة
 * تقول «أُرجع منها ١٨٠» لا «دُفعت»، وحالةُ دفعها تبقى للنقد وحده؛ ومرتجعٌ
 * لاحقٌ عليها لا يظنّها مدفوعةً فيردّ من الخزينة نقداً لم يُقبض.
 *
 * ونصيبُ كلِّ فاتورةٍ محفوظٌ في `journal_entry_invoices` (قيدُ الإشعار ←
 * الفاتورة ← المبلغ) — منه يُعرض ومنه يُعكَس عند الإلغاء.
 *
 * ══════════ لماذا لا صفَّ في `payments_process` للفاتورة ══════════
 *
 * عشراتُ المواضع تجمع `payments_process` بـ`invoice_id` أو `employee_id`
 * دون نظرٍ في النوع: «المدفوع» في صفحة الفاتورة ومطبوعاتها، و«المدفوع
 * سابقاً» في شاشة الدفع، ولوحةُ المبيعات والإحصاءات والأهداف، و`Client::
 * payments()` التي يُحسب منها رصيدُ العميل. صفٌّ للإشعار هناك يُقرأ نقداً
 * قُبض في كلّ واحدٍ منها.
 *
 * يبقى صفٌّ واحد لنصيب **الرصيد السابق** — بلا فاتورةٍ ولا موظف، فلا يبلغه
 * ذلك الجمع — بنوعٍ خاصّ لا تقرؤه تقاريرُ التحصيل. و`reference_number`
 * فيه `CN-<id>` لا المعرّفُ مجرّداً: حذفُ سند القبض والقيد يحذفان كلَّ صفٍّ
 * يساوي رقمُه معرّفَهما دون نظرٍ في النوع.
 */
class ClientDebtSettlement
{
    /** ما أطفأه إشعارٌ دائن من الرصيد السابق (الافتتاحي وما لا فاتورة له) */
    public const PREVIOUS_BALANCE_TYPE = 'credit_note_previous_balance';

    public function __construct(private CustomerLedgerService $ledger) {}

    /** مرجعُ صفوف التسوية في `payments_process.reference_number` */
    public static function reference(CreditNotification $note): string
    {
        return 'CN-'.$note->id;
    }

    /**
     * خطّةُ التسوية دون كتابة شيء — تُعرض قبل الحفظ وتُنفَّذ بعده.
     *
     * تُحسب **قبل** ترحيل قيد الإشعار: الرصيدُ السابق مشتقٌّ من رصيد الحساب،
     * وقيدُ الإشعار يُنزله.
     *
     * @return array{
     *     previous_balance: float,
     *     previous_pay: float,
     *     invoices: list<array{id: int, code: ?string, date: ?string, total: float, due: float, pay: float}>,
     *     unallocated: float
     * }
     */
    public function plan(int $clientId, float $amount, ?int $preferredInvoiceId = null): array
    {
        $remaining = round(max(0, $amount), 2);
        $invoices = $this->openInvoices($clientId);

        $dues = $invoices->mapWithKeys(fn (Invoice $invoice) => [
            $invoice->id => round(max(0, $invoice->calculateRemainingAmount()), 2),
        ]);

        $previousBalance = $this->previousBalance($clientId, (float) $dues->sum());

        // الصفّ: الفاتورةُ المختارة مرجعاً، ثمّ الرصيدُ السابق، ثمّ البقيّة
        $preferred = $preferredInvoiceId ? $invoices->firstWhere('id', $preferredInvoiceId) : null;

        $queue = $preferred ? [$preferred] : [];
        $queue[] = self::PREVIOUS_BALANCE_TYPE;
        foreach ($invoices as $invoice) {
            if ($invoice !== $preferred) {
                $queue[] = $invoice;
            }
        }

        $lines = [];
        $previousPay = 0.0;

        foreach ($queue as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            if ($invoice === self::PREVIOUS_BALANCE_TYPE) {
                $previousPay = min($remaining, $previousBalance);
                $remaining = round($remaining - $previousPay, 2);

                continue;
            }

            $due = $dues[$invoice->id] ?? 0.0;
            if ($due <= 0) {
                continue;
            }

            $pay = min($remaining, $due);
            $remaining = round($remaining - $pay, 2);

            $lines[] = [
                'id' => $invoice->id,
                'code' => $invoice->code,
                'date' => optional($invoice->invoice_date)->format('Y-m-d'),
                'total' => (float) $invoice->grand_total,
                'due' => $due,
                'pay' => $pay,
            ];
        }

        return [
            'previous_balance' => $previousBalance,
            'previous_pay' => round($previousPay, 2),
            'invoices' => $lines,
            'unallocated' => $remaining,
        ];
    }

    /**
     * تنفيذُ الخطّة: «مرتجع» كلِّ فاتورةٍ وربطُ قيد الإشعار بها، وصفُّ الرصيد السابق.
     *
     * @param  array  $plan  ما أعادته `plan()`
     * @param  int  $journalEntryId  قيدُ الإشعار — عليه تُحفظ أنصبةُ الفواتير
     */
    public function apply(CreditNotification $note, array $plan, int $journalEntryId): void
    {
        if (($plan['previous_pay'] ?? 0) > 0) {
            PaymentsProcess::create([
                'client_id' => $note->client_id,
                'invoice_id' => null,
                'employee_id' => null,
                'amount' => $plan['previous_pay'],
                'type' => self::PREVIOUS_BALANCE_TYPE,
                'payment_date' => $note->credit_date ?: now(),
                'payment_status' => 1,
                'reference_number' => self::reference($note),
                'notes' => 'تسوية رصيد سابق - إشعار دائن '.$note->credit_number,
            ]);
        }

        $codes = [];

        foreach ($plan['invoices'] ?? [] as $line) {
            if ($line['pay'] <= 0) {
                continue;
            }

            $this->adjustReturned((int) $line['id'], (float) $line['pay']);

            JournalEntryInvoice::create([
                'entry_id' => $journalEntryId,
                'invoice_id' => $line['id'],
                'invoice_type' => InvoiceType::SALE->value,
                'allocation_amount' => $line['pay'],
            ]);

            $codes[] = $line['code'] ?? $line['id'];
        }

        // القيدُ الذي يُطفئ أكثرَ من فاتورة يُعلَّم «متعدّد الربط» كقيد السند
        if (count($codes) > 1) {
            JournalEntry::whereKey($journalEntryId)->update([
                'linking_type' => LinkingType::MULTIPLE,
                'batch_reference' => implode(', ', $codes),
            ]);
        }
    }

    /**
     * إلغاءُ التسوية مع إلغاء الإشعار: يُطرح ما أضافه إلى «مرتجع» فواتيره،
     * وتُحذف روابطُ قيده وصفُّ الرصيد السابق.
     *
     * **قبل** حذف قيود الإشعار — منها تُقرأ أنصبةُ الفواتير.
     */
    public function reverse(CreditNotification $note): void
    {
        $links = $this->invoiceLinks($note);

        foreach ($links as $link) {
            $this->adjustReturned((int) $link->invoice_id, -(float) $link->allocation_amount);
        }

        JournalEntryInvoice::whereKey($links->pluck('link_id'))->delete();

        PaymentsProcess::where('reference_number', self::reference($note))
            ->where('type', self::PREVIOUS_BALANCE_TYPE)
            ->delete();
    }

    /** أنصبةُ الفواتير من قيود الإشعار — `journal_entry_invoices` */
    private function invoiceLinks(CreditNotification $note): Collection
    {
        $entryIds = JournalEntry::where('reference_type', ReferenceType::CREDIT_NOTE)
            ->where('reference_number', $note->id)
            ->where('client_id', $note->client_id)
            ->pluck('id');

        return JournalEntryInvoice::whereIn('entry_id', $entryIds)
            ->where('invoice_type', InvoiceType::SALE->value)
            ->orderBy('link_id')
            ->get();
    }

    /**
     * «مرتجع» الفاتورة — العمودُ الذي يكتبه مرتجعُ المبيعات
     * (`ReturnInvoiceController`) وتطرحه الشاشاتُ والمطبوعات من المستحق.
     *
     * الإشعارُ يكتب فيه ولا يمسّ حالةَ الدفع: الفاتورةُ تقول «أُرجع منها
     * كذا» لا «دُفعت»، وحالتُها المخزَّنة تبقى للنقد وحده.
     */
    private function adjustReturned(int $invoiceId, float $delta): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        $invoice->returned_payment = round(max(0, (float) $invoice->returned_payment + $delta), 2);
        $invoice->save();
    }

    /**
     * ما سدّده الإشعار — لشاشة العرض: الرصيدُ السابق أوّلاً ثمّ الفواتير.
     *
     * @return Collection<int, object{invoice: ?Invoice, amount: float}>
     */
    public function settlementsFor(CreditNotification $note): Collection
    {
        $previous = (float) PaymentsProcess::where('reference_number', self::reference($note))
            ->where('type', self::PREVIOUS_BALANCE_TYPE)
            ->sum('amount');

        $links = $this->invoiceLinks($note);
        $invoices = Invoice::whereKey($links->pluck('invoice_id'))->get(['id', 'code', 'grand_total', 'invoice_date'])->keyBy('id');

        return collect($previous > 0 ? [(object) ['invoice' => null, 'amount' => $previous]] : [])
            ->merge($links->map(fn ($link) => (object) [
                'invoice' => $invoices[$link->invoice_id] ?? null,
                'amount' => (float) $link->allocation_amount,
            ]));
    }

    /**
     * فواتيرُ العميل المفتوحة من الأقدم — بشروط سند القبض نفسِها
     * (`IncomesController::getUnpaidClientInvoices()`) وإلّا اختلف الاثنان
     * في الرصيد السابق، ومعها استبعادُ الملغاة.
     */
    private function openInvoices(int $clientId): Collection
    {
        $withReturns = Invoice::where('type', 'returned')
            ->whereNotNull('reference_number')
            ->pluck('reference_number');

        return Invoice::where('client_id', $clientId)
            ->where('is_paid', false)
            ->where('type', '!=', 'returned')
            ->whereNull('voided_at')
            ->whereNotIn('id', $withReturns)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * الرصيدُ الافتتاحي المتبقّي — **أصغرُ** قراءتين محاسبيّتين.
     *
     * ══════════ ما كان ══════════
     *
     * كان وحدَه: رصيدُ العميل في الأستاذ − مستحقُّ فواتيره المفتوحة. وهذا
     * **باقٍ** لا «افتتاحيّ»: كلُّ دَينٍ في الأستاذ لا تُطابقه فاتورةٌ مفتوحة
     * يسقط فيه — قيدُ تسويةٍ يدويّ، سندٌ قُيِّد ولم يُربط بفاتورته، فاتورةٌ
     * مستحقُّها في `payments_process` غيرُ ما في القيد. فيُعرض تحت اسم «الرصيد
     * الافتتاحي / السابق» ويُسدَّد منه، والافتتاحيُّ نفسُه مسدَّدٌ منذ زمن.
     * وهو ما شكا منه المستخدم: «يقول الرصيد الافتتاحي رغم أنّه تسدّد».
     *
     * ══════════ القراءتان ══════════
     *
     *   ١. **الافتتاحيُّ من الحسابات:** سطورُ قيد الرصيد الافتتاحي
     *      (`reference_type = OPB`) على حساب العميل، مطروحاً منها ما سُدِّد
     *      منه صراحةً — سندُ قبضٍ على الرصيد السابق، وإشعارٌ دائنٌ سابق.
     *      ولا يُقرأ `accounts.opening_balance`: الأرصدةُ المُرحَّلة قيودٌ
     *      والعمودُ صفرٌ عند كثيرٍ منها.
     *
     *   ٢. **الأستاذ:** الرصيدُ − مستحقُّ الفواتير المفتوحة، كما كان.
     *
     * ══════════ ولماذا الأصغر ══════════
     *
     * كلٌّ منهما يُخطئ وحدَه في اتّجاه، والأصغرُ يُصيب في الاتّجاهين:
     *
     *   • الأولى وحدَها تدّعي دَيناً لا يراه الأستاذ — عميلٌ رصيدُه صفرٌ
     *     سدّد افتتاحيَّه بقيدٍ لا بسند، فيبقى «مئةً» عليه ويُطفئ الإشعارُ
     *     دَيناً غيرَ موجود.
     *   • والثانية وحدَها تُسمّي كلَّ باقٍ افتتاحيّاً.
     *
     * فالافتتاحيُّ لا يتجاوز ما فُتح به ولم يُسدَّد، ولا يتجاوز ما في
     * الأستاذ فعلاً. وما زاد في الأستاذ عن الفواتير والافتتاحيّ لا يُنسب إلى
     * أيّهما: يبقى من الإشعار غيرَ موزَّع، رصيداً دائناً للعميل.
     *
     * على بيانات هذه المنشأة القراءةُ الثانية أصغرُ أو مساويةٌ عند كلّ عميل،
     * فالنتيجةُ هنا لم تتغيّر لعميلٍ واحد — التغييرُ يقع حيث وقع الخلل وحدَه.
     */
    private function previousBalance(int $clientId, float $openInvoicesDue): float
    {
        $account = Account::ledger()->where('client_id', $clientId)->first();

        if (! $account) {
            return 0.0;
        }

        $balance = (float) ($this->ledger->balancesAt([$account->id], null)[$account->id] ?? 0);
        $ledgerResidual = max(0, $balance - $openInvoicesDue);

        $opening = (float) DB::table('journal_entry_details as d')
            ->join('journal_entries as j', 'j.id', '=', 'd.journal_entry_id')
            ->where('d.account_id', $account->id)
            ->where('j.reference_type', ReferenceType::OPENING_BALANCE->value)
            ->selectRaw('COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0) AS amount')
            ->value('amount');

        $openingPaid = (float) PaymentsProcess::where('client_id', $clientId)
            ->whereIn('type', ['previous_balance_payment', 'opening_balance_payment', self::PREVIOUS_BALANCE_TYPE])
            ->sum('amount');

        $openingRemaining = max(0, $opening - $openingPaid);

        return round(min($ledgerResidual, $openingRemaining), 2);
    }
}
