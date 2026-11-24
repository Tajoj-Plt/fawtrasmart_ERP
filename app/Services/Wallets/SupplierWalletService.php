<?php

namespace App\Services\Wallets;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierWalletTransaction;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\Auth;

/**
 * محفظة المورد — نظير `ClientWalletService` في دورة المشتريات.
 *
 * **الاتجاه معكوس، وهو الوحيد الذي يستقيم.** محفظة العميل التزام (مالٌ
 * له عندنا)، ومحفظة المورد **أصل**: مالٌ لنا لديه — دفعةٌ مقدَّمة
 * سلّمناها ولم نستهلكها بعد.
 *
 * ولو جُعلت التزاماً لَما استقام السداد: سدادُ فاتورة شراء يجب أن يُنقص
 * ما علينا للمورد (طرفٌ مدين)، والطرفُ الدائن إمّا أصلٌ ينقص أو التزامٌ
 * يزيد. فمحفظةٌ دائنة تزيد ما علينا بدل أن تُنقصه — ينتقل الدَّين بين
 * حسابين ولا يُسدَّد شيء.
 *
 *     الأصول
 *     └── دفعات مقدَّمة للموردين
 *             └── محفظة المورد (محمد)
 *
 * **القيود الثلاثة** — ورصيدُ المحفظة مدينٌ دائماً:
 *
 *     دفعة مقدَّمة       من ح/ محفظة المورد    إلى ح/ الخزينة
 *     استرداد نقدي      من ح/ الخزينة         إلى ح/ محفظة المورد
 *     سداد فاتورة شراء   من ح/ المورد (دائنة)  إلى ح/ محفظة المورد
 *
 * @see \App\Services\Wallets\ClientWalletService النظير في دورة المبيعات
 */
class SupplierWalletService
{
    public const BATCH_PREFIX = 'SWALLET_';

    /** أنواع الحركة — نفس قيم عمود `type` في `supplier_wallet_transactions` */
    public const TYPES = ['deposit', 'withdraw', 'invoice_paid', 'refund'];

    /** الأنواع التي تحتاج خزينة (نقدٌ يخرج أو يدخل) */
    public const TREASURY_TYPES = ['deposit', 'withdraw'];

    /** الأنواع التي تحتاج فاتورة شراء */
    public const INVOICE_TYPES = ['invoice_paid', 'refund'];

    /**
     * حساب محفظة المورد — يُنشأ عند أوّل حركةٍ عليه.
     *
     * @throws \Exception إن لم يكن الحساب الأب مربوطاً في الإعدادات
     */
    public static function accountFor(Supplier $supplier): Account
    {
        $account = Account::where('supplier_id', $supplier->id)
            ->where('type_accont', 'supplier_wallet')
            ->first();

        if ($account) {
            return $account;
        }

        $parent = AccountingSettings::account('supplier_wallets_parent');

        if (! $parent) {
            throw new \Exception('حساب «دفعات مقدَّمة للموردين» غير مربوط — اربطه من: الإعدادات ← الحسابات المحاسبية.');
        }

        return Account::create([
            'name' => 'محفظة المورد - '.self::supplierName($supplier),
            'code' => self::nextCode($parent),
            'parent_id' => $parent->id,
            'type' => 'sub',
            // أصل: المحفظة مالٌ لنا لدى المورد
            'account_type' => 'assets',
            'balance_type' => Account::DEBIT,
            'balance' => 0,
            'opening_balance' => 0,
            'supplier_id' => $supplier->id,
            // تميّزها عن حساب ذمم المورد، وكلاهما يحمل `supplier_id`
            'type_accont' => 'supplier_wallet',
            'is_active' => 1,
        ]);
    }

    /** رصيد المحفظة — موجبٌ يعني مالاً لنا لدى المورد */
    public static function balance(Supplier $supplier): float
    {
        $account = Account::where('supplier_id', $supplier->id)
            ->where('type_accont', 'supplier_wallet')
            ->first();

        return $account ? (float) $account->balance : 0.0;
    }

    /** دفعة مقدَّمة — من ح/ محفظة المورد إلى ح/ الخزينة */
    public static function deposit(Supplier $supplier, float $amount, Account $treasury, ?string $note = null): SupplierWalletTransaction
    {
        if ($amount > (float) $treasury->balance + 0.005) {
            throw new \Exception('رصيد الخزينة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($treasury->balance));
        }

        return self::post($supplier, 'deposit', $amount, $treasury, null, $note);
    }

    /** استرداد نقدي — من ح/ الخزينة إلى ح/ محفظة المورد */
    public static function withdraw(Supplier $supplier, float $amount, Account $treasury, ?string $note = null): SupplierWalletTransaction
    {
        self::assertSufficient($supplier, $amount);

        return self::post($supplier, 'withdraw', $amount, $treasury, null, $note);
    }

    /**
     * سداد فاتورة شراء من المحفظة — من ح/ المورد إلى ح/ محفظة المورد.
     *
     * قفلُ الفاتورة أوّلَ سطر يمنع التكرار — انظر نظيرَه في
     * `ClientWalletService::payInvoice()`.
     */
    public static function payInvoice(PurchaseInvoice $invoice, float $amount, ?string $note = null): SupplierWalletTransaction
    {
        $invoice = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        $supplier = Supplier::findOrFail($invoice->supplier_id);

        $already = (float) ($invoice->wallet_paid_amount ?? 0);
        $remaining = self::outstanding($invoice);

        if ($remaining <= 0) {
            throw new \Exception('الفاتورة '.$invoice->code.' مسدَّدة بالكامل — لا متبقّي يُسدَّد من المحفظة.');
        }

        if ($amount > $remaining + 0.005) {
            throw new \Exception('المبلغ أكبر من المتبقّي على الفاتورة ('.CurrencySettings::format($remaining).').');
        }

        $transaction = self::post($supplier, 'invoice_paid', $amount, null, $invoice, $note);

        $invoice->wallet_paid_amount = round($already + $amount, 2);
        self::applyToInvoice($invoice, -$amount);

        return $transaction;
    }

    /**
     * ردُّ سدادٍ سابق إلى المحفظة — من ح/ محفظة المورد إلى ح/ المورد.
     *
     * تُستدعى عند حذف فاتورة شراء سُوِّيت من المحفظة: بلاها يبقى المال
     * مستهلَكاً من دفعةٍ مقدَّمة لفاتورةٍ لم تعد موجودة.
     *
     * `$amount` فارغاً يعني المسدَّد كلَّه.
     */
    public static function refundInvoice(PurchaseInvoice $invoice, ?float $amount = null, ?string $note = null): ?SupplierWalletTransaction
    {
        $invoice = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        $paidFromWallet = round((float) ($invoice->wallet_paid_amount ?? 0), 2);

        if ($paidFromWallet <= 0) {
            return null;
        }

        $amount = round($amount ?? $paidFromWallet, 2);

        if ($amount > $paidFromWallet + 0.005) {
            throw new \Exception('المبلغ أكبر من المسدَّد من المحفظة ('.CurrencySettings::format($paidFromWallet).').');
        }

        $supplier = Supplier::findOrFail($invoice->supplier_id);

        $transaction = self::post($supplier, 'refund', $amount, null, $invoice, $note);

        $invoice->wallet_paid_amount = round($paidFromWallet - $amount, 2);
        self::applyToInvoice($invoice, $amount);

        return $transaction;
    }

    /**
     * ما يقبله المتبقّي من الفاتورة سداداً من المحفظة — للتوزيع على عدّة
     * فواتير في شاشة الإضافة. نظير `ClientWalletService::payableFromWallet()`.
     */
    public static function payableFromWallet(PurchaseInvoice $invoice): float
    {
        return self::outstanding($invoice);
    }

    /**
     * ما يقبله المتبقّي من الفاتورة سداداً من المحفظة.
     *
     * `due_value` هو الحدّ لا `grand_total`: فاتورةٌ صُرف نقدُها من
     * الخزينة لا يجوز أن تُسدَّد ثانيةً من الدفعة المقدَّمة.
     */
    private static function outstanding(PurchaseInvoice $invoice): float
    {
        $byWallet = (float) $invoice->grand_total - (float) ($invoice->wallet_paid_amount ?? 0);
        $byDue = (float) ($invoice->due_value ?? $invoice->grand_total);

        return round(max(0.0, min($byWallet, $byDue)), 2);
    }

    /**
     * أثرُ حركة المحفظة على الفاتورة: مستحقُّها وحالتُها.
     *
     * `$delta` سالبٌ يُنقص المستحقّ (سداد) وموجبٌ يزيده (ردّ سداد).
     * و`payment_status` هنا نصٌّ لا رقم — على خلاف فاتورة المبيعات.
     * و`cancelled` لا تُمسّ: حالةٌ يختارها المستخدم صراحةً.
     */
    private static function applyToInvoice(PurchaseInvoice $invoice, float $delta): void
    {
        $due = round(max(0.0, (float) ($invoice->due_value ?? $invoice->grand_total) + $delta), 2);

        $invoice->due_value = $due;

        if ($invoice->payment_status !== 'cancelled') {
            $invoice->payment_status = $due <= 0.005
                ? 'paid'
                : ($due < (float) $invoice->grand_total - 0.005 ? 'partially_paid' : 'unpaid');
        }

        $invoice->is_paid = $due <= 0.005;
        $invoice->save();
    }

    /**
     * الرصيد الافتتاحي للمحفظة.
     *
     * رصيدٌ افتتاحيٌّ في اتجاه المحفظة ليس ذمّةً بل مالاً في المحفظة:
     * مورّدٌ «لنا عليه» دفعةٌ مقدَّمة سبقت النظام، وعميلٌ «له عندنا» مالٌ
     * أودعه قبله. فيُقيَّد على حساب المحفظة لا على حساب الذمّة — وإلا
     * ظهر المورد مديناً لنا في حساب الدائنين، وهو موضعٌ لا يصفه.
     *
     * والقيد نفسه الذي يكتبه أيّ رصيدٍ افتتاحي (`OB-{account_id}` مقابل
     * حساب الأرصدة الافتتاحية)، فلا آلية جديدة تُخترع له.
     */
    public static function setOpeningBalance(Supplier $supplier, float $amount): Account
    {
        $wallet = self::accountFor($supplier);

        $wallet->opening_balance = round($amount, 2);
        $wallet->opening_balance_type = Account::DEBIT;
        $wallet->save();

        if ($wallet->opening_balance != 0) {
            $wallet->createOrUpdateOpeningBalanceJournalEntry();
        } else {
            $wallet->deleteExistingOpeningBalanceEntries();
        }

        Account::updateBalancesHierarchically($wallet->id);

        return $wallet->fresh();
    }

    /** تصفير الرصيد الافتتاحي للمحفظة وحذف قيده — إن كانت لها حساب */
    public static function clearOpeningBalance(Supplier $supplier): void
    {
        $wallet = Account::where('supplier_id', $supplier->id)
            ->where('type_accont', 'supplier_wallet')
            ->first();

        if (! $wallet || (float) $wallet->opening_balance == 0.0) {
            return;
        }

        $wallet->opening_balance = 0;
        $wallet->save();
        $wallet->deleteExistingOpeningBalanceEntries();

        Account::updateBalancesHierarchically($wallet->id);
    }

    /**
     * الكتابة المشتركة: حركةٌ في الدفتر، وقيدٌ متوازن، ورصيدان يتحرّكان.
     */
    private static function post(
        Supplier $supplier,
        string $type,
        float $amount,
        ?Account $treasury,
        ?PurchaseInvoice $invoice,
        ?string $note
    ): SupplierWalletTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \Exception('المبلغ يجب أن يكون أكبر من صفر.');
        }

        /* الحسابُ يُقفَل لا يُقرأ فقط — انظر نظيرَه في
           `ClientWalletService::post()`: `balance` تُقرأ ثمّ تُكتب، وطلبان
           متزامنان يكتب آخرُهما فوق الأوّل. */
        $wallet = Account::whereKey(self::accountFor($supplier)->id)->lockForUpdate()->first();

        // `$walletIsDebit` يعني: أتزيد المحفظة بهذه الحركة؟ (وهي مدينة بطبعها)
        [$counterparty, $walletIsDebit, $label] = self::leg($supplier, $type, $treasury);
        $reference = self::referenceFor($type);

        if (! $counterparty) {
            throw new \Exception('الحساب المقابل لهذه الحركة غير متاح.');
        }

        /* كفايةُ الرصيد تُعاد **بعد** القفل — الحاجز الأخير دون رصيدٍ سالب.
           والمحفظة هنا مدينةٌ بطبعها، فما يُنقصها هو `! $walletIsDebit`. */
        if (! $walletIsDebit && $amount > (float) $wallet->balance + 0.005) {
            throw new \Exception('رصيد المحفظة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($wallet->balance));
        }

        $counterparty = Account::whereKey($counterparty->id)->lockForUpdate()->first();

        $description = $label.': '.self::supplierName($supplier)
            .($invoice ? ' — فاتورة '.$invoice->code : '')
            .($note ? ' — '.$note : '');

        $entry = JournalEntry::create([
            'reference_number' => $invoice->code ?? null,
            'date' => now(),
            'description' => $description,
            'reference_type' => $reference,
            'status' => 1,
            'currency' => CurrencySettings::code(),
            'purchase_invoice_id' => $invoice->id ?? null,
            'created_by_employee' => Auth::id(),
        ]);

        $entry->batch_reference = self::BATCH_PREFIX.$entry->id;
        $entry->save();

        self::detail($entry, $walletIsDebit ? $wallet : $counterparty, $amount, true, $description);
        self::detail($entry, $walletIsDebit ? $counterparty : $wallet, $amount, false, $description);

        // المحفظة مدينةٌ بطبعها: المدين يزيدها والدائن ينقصها
        $wallet->balance = round((float) $wallet->balance + ($walletIsDebit ? $amount : -$amount), 2);
        $wallet->save();

        // وذمم المورد دائنةٌ بطبعها، والخزينة مدينة — والاتجاه معكوس عن المحفظة
        $counterparty->balance = round((float) $counterparty->balance + ($walletIsDebit ? -$amount : $amount), 2);
        $counterparty->save();

        Account::updateBalancesHierarchically($wallet->id);
        Account::updateBalancesHierarchically($counterparty->id);

        /* الرصيد بعد الحركة يُقرأ بعد إعادة الحساب — انظر نظيرَه في
           `ClientWalletService::post()`. */
        $wallet->refresh();

        return SupplierWalletTransaction::create([
            'supplier_id' => $supplier->id,
            'account_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'treasury_account_id' => $treasury->id ?? null,
            'purchase_invoice_id' => $invoice->id ?? null,
            'journal_entry_id' => $entry->id,
            'reference' => $invoice->code ?? null,
            'note' => $note,
            'branch_id' => $supplier->branch_id ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * طرفا الحركة — انظر نظيرَها في `ClientWalletService::leg()`.
     *
     * @return array{0:?Account,1:bool,2:string} أتزيد المحفظة بهذه الحركة؟ (وهي مدينة بطبعها)
     */
    private static function leg(Supplier $supplier, string $type, ?Account $treasury): array
    {
        return match ($type) {
            'deposit' => [$treasury, true, 'دفعة مقدَّمة لمورد'],
            'withdraw' => [$treasury, false, 'استرداد من محفظة مورد'],
            'invoice_paid' => [self::payableAccount($supplier), false, 'سداد فاتورة شراء من محفظة المورد'],
            'refund' => [self::payableAccount($supplier), true, 'ردّ سداد فاتورة إلى محفظة المورد'],
            default => throw new \Exception('نوع حركة غير معروف: '.$type),
        };
    }

    /** نوع المرجع في القيد — الدفعة صرفٌ والاسترداد قبض، والسدادُ دفعُ مورد */
    private static function referenceFor(string $type): ReferenceType
    {
        return match ($type) {
            'deposit' => ReferenceType::PAYMENT_VOUCHER,
            'withdraw' => ReferenceType::RECEIPT_VOUCHER,
            'invoice_paid', 'refund' => ReferenceType::SUPPLIER_PAYMENT,
            default => throw new \Exception('نوع حركة غير معروف: '.$type),
        };
    }

    /**
     * تعديل حركةٍ قائمة — بكل حقولها.
     *
     * نظيرُ `ClientWalletService::updateTransaction()` حرفاً بحرف، وشرحُ
     * الخطوات الخمس هناك. والفرق الوحيد اتجاهُ المحفظة: هذه **مدينة**
     * بطبعها، فالزيادة `isIncrease()` لا `isCredit()`.
     *
     * @param  array{type:string,amount:float,treasury_account_id:?int,purchase_invoice_id:?int,note:?string}  $data
     *
     * @throws \Exception إن أخرج التعديلُ المحفظةَ إلى رصيدٍ سالب
     */
    public static function updateTransaction(SupplierWalletTransaction $transaction, array $data): SupplierWalletTransaction
    {
        $transaction = SupplierWalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

        $supplier = Supplier::findOrFail($transaction->supplier_id);
        $wallet = Account::whereKey(self::accountFor($supplier)->id)->lockForUpdate()->first();

        $type = $data['type'];
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new \Exception('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new \Exception('نوع حركة غير معروف: '.$type);
        }

        // ── 1. فكّ أثر الحركة القديمة عن فاتورتها ──
        $oldInvoice = $transaction->purchase_invoice_id
            ? PurchaseInvoice::whereKey($transaction->purchase_invoice_id)->lockForUpdate()->first()
            : null;

        if ($oldInvoice) {
            $sign = $transaction->isIncrease() ? -1 : 1;
            $oldInvoice->wallet_paid_amount = round(max(0, (float) $oldInvoice->wallet_paid_amount - ($sign * (float) $transaction->amount)), 2);
            self::applyToInvoice($oldInvoice, $sign * (float) $transaction->amount);
        }

        // ── 2. الطرفان الجديدان ──
        $treasury = ($data['treasury_account_id'] ?? null)
            ? Account::whereKey($data['treasury_account_id'])->lockForUpdate()->first()
            : null;

        $newInvoice = ($data['purchase_invoice_id'] ?? null)
            ? PurchaseInvoice::whereKey($data['purchase_invoice_id'])->lockForUpdate()->firstOrFail()
            : null;

        if ($newInvoice && (int) $newInvoice->supplier_id !== (int) $supplier->id) {
            throw new \Exception('الفاتورة لا تخصّ هذا المورد.');
        }

        [$counterparty, $walletIsDebit, $label] = self::leg($supplier, $type, $treasury);

        if (! $counterparty) {
            throw new \Exception('الحساب المقابل لهذه الحركة غير متاح.');
        }

        $counterparty = Account::whereKey($counterparty->id)->lockForUpdate()->first();

        $description = $label.': '.self::supplierName($supplier)
            .($newInvoice ? ' — فاتورة '.$newInvoice->code : '')
            .(($data['note'] ?? null) ? ' — '.$data['note'] : '');

        // ── 3. إعادة كتابة القيد في مكانه ──
        $entry = JournalEntry::find($transaction->journal_entry_id);

        if (! $entry) {
            throw new \Exception('قيد هذه الحركة غير موجود — تعذّر التعديل.');
        }

        $touched = JournalEntryDetail::where('journal_entry_id', $entry->id)->pluck('account_id')->all();

        JournalEntryDetail::where('journal_entry_id', $entry->id)->delete();

        $entry->description = $description;
        $entry->reference_number = $newInvoice->code ?? null;
        $entry->purchase_invoice_id = $newInvoice->id ?? null;
        $entry->reference_type = self::referenceFor($type);
        $entry->save();

        self::detail($entry, $walletIsDebit ? $wallet : $counterparty, $amount, true, $description);
        self::detail($entry, $walletIsDebit ? $counterparty : $wallet, $amount, false, $description);

        // ── 4. الأرصدة من القيود ──
        foreach (array_unique(array_merge($touched, [$wallet->id, $counterparty->id])) as $accountId) {
            Account::updateBalancesHierarchically($accountId);
        }

        $wallet->refresh();

        if ((float) $wallet->balance < -0.005) {
            throw new \Exception('هذا التعديل يجعل رصيد المحفظة سالباً ('.CurrencySettings::format($wallet->balance).').');
        }

        // ── 5. الحركة نفسها، ثمّ أثرها على الفاتورة الجديدة ──
        $transaction->type = $type;
        $transaction->amount = $amount;
        $transaction->treasury_account_id = $treasury->id ?? null;
        $transaction->purchase_invoice_id = $newInvoice->id ?? null;
        $transaction->reference = $newInvoice->code ?? null;
        $transaction->note = $data['note'] ?? null;
        $transaction->save();

        if ($newInvoice) {
            $sign = $transaction->isIncrease() ? -1 : 1;
            $newInvoice->wallet_paid_amount = round(max(0, (float) $newInvoice->wallet_paid_amount + ($sign * $amount)), 2);
            self::applyToInvoice($newInvoice, -$sign * $amount);
        }

        self::resequenceBalances($supplier, $wallet);

        return $transaction->fresh();
    }

    /** إعادة ترتيب «الرصيد بعدها» — انظر نظيرَها في `ClientWalletService` */
    private static function resequenceBalances(Supplier $supplier, Account $wallet): void
    {
        $rows = SupplierWalletTransaction::where('supplier_id', $supplier->id)->orderBy('id')->get();

        $net = $rows->sum(fn (SupplierWalletTransaction $t) => $t->isIncrease() ? (float) $t->amount : -(float) $t->amount);
        $running = round((float) $wallet->balance - $net, 2);

        foreach ($rows as $row) {
            $running = round($running + ($row->isIncrease() ? (float) $row->amount : -(float) $row->amount), 2);

            if ((float) $row->balance_after !== $running) {
                $row->balance_after = $running;
                $row->save();
            }
        }
    }

    /** حساب ذمم المورد — الطرف المقابل عند السداد */
    private static function payableAccount(Supplier $supplier): ?Account
    {
        return Account::where('supplier_id', $supplier->id)
            ->where(function ($q) {
                $q->whereNull('type_accont')->orWhere('type_accont', '<>', 'supplier_wallet');
            })
            ->first();
    }

    /** @throws \Exception إن كان الرصيد أقلّ من المطلوب */
    private static function assertSufficient(Supplier $supplier, float $amount): void
    {
        $balance = self::balance($supplier);

        if ($amount > $balance + 0.005) {
            throw new \Exception('رصيد المحفظة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($balance));
        }
    }

    /** لا عمود `name` في `suppliers` — كما في `clients` تماماً */
    private static function supplierName(Supplier $supplier): string
    {
        if (filled($supplier->trade_name)) {
            return $supplier->trade_name;
        }

        return trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: ('#'.$supplier->id);
    }

    private static function detail(JournalEntry $entry, Account $account, float $amount, bool $isDebit, string $description): void
    {
        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'description' => $description,
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'is_debit' => $isDebit,
        ]);
    }

    private static function nextCode(Account $parent): string
    {
        $lastChild = Account::where('parent_id', $parent->id)->orderBy('code', 'desc')->first();
        $base = $lastChild ? ((int) $lastChild->code + 1) : ($parent->code.'001');

        $code = $base;
        $counter = 1;

        while (Account::where('code', $code)->exists()) {
            $code = $base + $counter;
            $counter++;

            if ($counter > 1000) {
                throw new \RuntimeException('تعذّر توليد كود حساب فريد للمحفظة.');
            }
        }

        return (string) $code;
    }
}
