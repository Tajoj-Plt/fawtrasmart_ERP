<?php

namespace App\Services\Wallets;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use Illuminate\Support\Facades\Auth;

/**
 * محفظة العميل — البند ١١ من «تقرير الإصلاح والتحسين».
 *
 * المحفظة **مالٌ للعميل عندنا**: التزامٌ في الخصوم، لا ذمّةٌ في المدينين.
 * ولكل عميلٍ حسابُه تحت الأب المضبوط في «الحسابات المحاسبية»
 * (`client_wallets_parent`):
 *
 *     الخصوم
 *     └── أرصدة محافظ العملاء
 *             ├── محفظة العميل (أحمد)
 *             └── محفظة العميل (خالد)
 *
 * **القيود الثلاثة** — وكلُّها متوازنة، ورصيدُ المحفظة دائنٌ دائماً:
 *
 *     إيداع نقدي           من ح/ الخزينة        إلى ح/ محفظة العميل
 *     سحب نقدي             من ح/ محفظة العميل   إلى ح/ الخزينة
 *     سداد فاتورة          من ح/ محفظة العميل   إلى ح/ العميل (ذمم مدينة)
 *
 * سطرُ السداد هو مربط البند: المحفظة تنقص، وذمّةُ العميل تنقص معها —
 * فما كان عندنا له صار مسدَّداً عنه. ولا تمرّ الخزينة بالقيد أصلاً، لأن
 * النقد دخل يوم الإيداع لا يوم الفاتورة.
 *
 * **لا ثابت احتياطي للحسابات**: الأب يُقرأ من الإعدادات وحدها، وغيابُه
 * يرمي استثناءً يسمّي المفتاح الناقص بدل أن يُكتب القيد على حسابٍ لم
 * يختره أحد — نفس قاعدة بقيّة قيود النظام.
 */
class ClientWalletService
{
    /** مرجع تكرارٍ لقيد المحفظة — الحركة واحدة والقيد واحد */
    public const BATCH_PREFIX = 'CWALLET_';

    /**
     * أنواع الحركة — نفس قيم عمود `type` في `client_wallet_transactions`.
     *
     * تقرؤها شاشتا الإضافة والتعديل لبناء القائمة، ويُصادَق عليها الحفظ،
     * فلا يُقبل نوعٌ لا تعرفه `leg()`.
     */
    public const TYPES = ['deposit', 'withdraw', 'invoice_paid', 'refund'];

    /** الأنواع التي تحتاج خزينة (نقدٌ يدخل أو يخرج) */
    public const TREASURY_TYPES = ['deposit', 'withdraw'];

    /** الأنواع التي تحتاج فاتورة */
    public const INVOICE_TYPES = ['invoice_paid', 'refund'];

    /**
     * حساب محفظة العميل — يُنشأ عند أوّل حركةٍ عليه.
     *
     * @throws \Exception إن لم يكن الحساب الأب مربوطاً في الإعدادات
     */
    public static function accountFor(Client $client): Account
    {
        $account = Account::where('client_id', $client->id)
            ->where('type_accont', 'client_wallet')
            ->first();

        if ($account) {
            return $account;
        }

        $parent = AccountingSettings::account('client_wallets_parent');

        if (! $parent) {
            throw new \Exception('حساب «أرصدة محافظ العملاء» غير مربوط — اربطه من: الإعدادات ← الحسابات المحاسبية.');
        }

        return Account::create([
            'name' => 'محفظة العميل - '.($client->display_name ?: ('#'.$client->id)),
            'code' => self::nextCode($parent),
            'parent_id' => $parent->id,
            'type' => 'sub',
            // التزام: المحفظة مالٌ للعميل عندنا
            'account_type' => 'liabilities',
            'balance_type' => Account::CREDIT,
            'balance' => 0,
            'opening_balance' => 0,
            'client_id' => $client->id,
            // يميّزها عن حساب ذمم العميل، وكلاهما يحمل `client_id`
            'type_accont' => 'client_wallet',
            'is_active' => 1,
        ]);
    }

    /** رصيد المحفظة — موجبٌ يعني مالاً للعميل عندنا */
    public static function balance(Client $client): float
    {
        $account = Account::where('client_id', $client->id)
            ->where('type_accont', 'client_wallet')
            ->first();

        return $account ? (float) $account->balance : 0.0;
    }

    /**
     * إيداع نقدي في المحفظة — من ح/ الخزينة إلى ح/ محفظة العميل.
     */
    public static function deposit(Client $client, float $amount, Account $treasury, ?string $note = null): ClientWalletTransaction
    {
        return self::post($client, 'deposit', $amount, $treasury, null, $note);
    }

    /**
     * سحب نقدي من المحفظة — من ح/ محفظة العميل إلى ح/ الخزينة.
     *
     * @throws \Exception إن تجاوز المبلغُ الرصيد
     */
    public static function withdraw(Client $client, float $amount, Account $treasury, ?string $note = null): ClientWalletTransaction
    {
        self::assertSufficient($client, $amount);

        /* الخزينةُ تُفحَص كما تُفحَص المحفظة — نظيرُ الفحص في
           `SupplierWalletService::deposit()`. رصيدُ العميل عندنا لا يعني
           أنّ في الصندوق ما يُخرجه اليوم. */
        if ($amount > (float) $treasury->balance + 0.005) {
            throw new \Exception('رصيد الخزينة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($treasury->balance));
        }

        return self::post($client, 'withdraw', $amount, $treasury, null, $note);
    }

    /**
     * سداد فاتورة من المحفظة — من ح/ محفظة العميل إلى ح/ ذمم العميل.
     *
     * **قفلُ الفاتورة أوّلَ سطر** هو ما يمنع التكرار: ضغطتان على الزرّ أو
     * طلبان متزامنان كانا يقرآن `wallet_paid_amount` نفسَه فيمرّان معاً من
     * فحص المتبقّي — فتُخصَم المحفظة مرّتين لفاتورةٍ واحدة ويُكتب قيدان.
     * ومع `lockForUpdate` ينتظر الثاني الأوّلَ ثمّ يقرأ ما كتبه فيُردّ.
     *
     * @throws \Exception إن تجاوز المبلغُ الرصيد أو المتبقّي من الفاتورة
     */
    public static function payInvoice(Invoice $invoice, float $amount, ?string $note = null): ClientWalletTransaction
    {
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        $client = Client::findOrFail($invoice->client_id);

        $alreadyFromWallet = (float) ($invoice->wallet_paid_amount ?? 0);
        $remaining = self::outstanding($invoice);

        if ($remaining <= 0) {
            throw new \Exception('الفاتورة '.$invoice->code.' مسدَّدة بالكامل — لا متبقّي يُسدَّد من المحفظة.');
        }

        if ($amount > $remaining + 0.005) {
            throw new \Exception('المبلغ أكبر من المتبقّي على الفاتورة ('.CurrencySettings::format($remaining).').');
        }

        $transaction = self::post($client, 'invoice_paid', $amount, null, $invoice, $note);

        $invoice->wallet_paid_amount = round($alreadyFromWallet + $amount, 2);
        self::applyToInvoice($invoice, -$amount);

        return $transaction;
    }

    /**
     * ردُّ سدادٍ سابق إلى المحفظة — من ح/ ذمم العميل إلى ح/ محفظة العميل.
     *
     * المسارُ المعاكس لـ`payInvoice()`، ولا غنى عنه: بلاه يبقى المالُ
     * مخصوماً من محفظة عميلٍ أُلغيت فاتورتُه، وتبقى حركةٌ في دفتره تشير
     * إلى مستندٍ لم يعد له وجود.
     *
     * `$amount` فارغاً يعني المسدَّد كلَّه.
     *
     * @throws \Exception إن تجاوز المبلغُ ما سُدِّد من المحفظة فعلاً
     */
    public static function refundInvoice(Invoice $invoice, ?float $amount = null, ?string $note = null): ?ClientWalletTransaction
    {
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        $paidFromWallet = round((float) ($invoice->wallet_paid_amount ?? 0), 2);

        if ($paidFromWallet <= 0) {
            return null;
        }

        $amount = round($amount ?? $paidFromWallet, 2);

        if ($amount > $paidFromWallet + 0.005) {
            throw new \Exception('المبلغ أكبر من المسدَّد من المحفظة ('.CurrencySettings::format($paidFromWallet).').');
        }

        $client = Client::findOrFail($invoice->client_id);

        $transaction = self::post($client, 'refund', $amount, null, $invoice, $note);

        $invoice->wallet_paid_amount = round($paidFromWallet - $amount, 2);
        self::applyToInvoice($invoice, $amount);

        return $transaction;
    }

    /**
     * ما يقبله المتبقّي من الفاتورة سداداً من المحفظة.
     *
     * كان الفحص `grand_total - wallet_paid_amount` وحده، فيتجاهل النقد
     * المقبوض: فاتورةٌ سُدِّدت نقداً بكاملها تُقبل ثانيةً من المحفظة —
     * تُخصَم المحفظة، ويصير حساب العميل دائناً بقيمتها، والخزينة قبضت
     * نقدها. فالمستحقُّ `due_value` هو الحدّ، والفارقُ من `grand_total`
     * حدٌّ ثانٍ لفواتير سبقت هذا العمود.
     */
    private static function outstanding(Invoice $invoice): float
    {
        $byWallet = (float) $invoice->grand_total - (float) ($invoice->wallet_paid_amount ?? 0);
        $byDue = (float) ($invoice->due_value ?? $invoice->grand_total);

        return round(max(0.0, min($byWallet, $byDue)), 2);
    }

    /**
     * غلافٌ عامّ لـ`outstanding()` — يحتاجه المتحكّم عند توزيع مبلغٍ واحد
     * على عدّة فواتير («سداد كل الفواتير») قبل استدعاء `payInvoice()` على
     * كلٍّ منها بحصّتها، فلا يُعاد اشتقاق الصيغة نفسها خارج الخدمة.
     */
    public static function payableFromWallet(Invoice $invoice): float
    {
        return self::outstanding($invoice);
    }

    /**
     * أثرُ حركة المحفظة على الفاتورة: مستحقُّها وحالتُها.
     *
     * `$delta` سالبٌ يُنقص المستحقّ (سداد) وموجبٌ يزيده (ردّ سداد).
     *
     * الحالتان ٤ و٥ («تحت المراجعة» و«فاشلة») لا تُمسّان: يختارهما
     * المستخدم صراحةً، ولا يجوز لحركةِ محفظةٍ أن تُعيدهما إلى «مكتملة».
     */
    private static function applyToInvoice(Invoice $invoice, float $delta): void
    {
        $due = round(max(0.0, (float) ($invoice->due_value ?? $invoice->grand_total) + $delta), 2);

        $invoice->due_value = $due;

        if (in_array((int) $invoice->payment_status, [1, 2, 3], true)) {
            $invoice->payment_status = $due <= 0.005 ? 1 : 2;
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
    public static function setOpeningBalance(Client $client, float $amount): Account
    {
        $wallet = self::accountFor($client);

        $wallet->opening_balance = round($amount, 2);
        $wallet->opening_balance_type = Account::CREDIT;
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
    public static function clearOpeningBalance(Client $client): void
    {
        $wallet = Account::where('client_id', $client->id)
            ->where('type_accont', 'client_wallet')
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
     * تعديل حركةٍ قائمة — بكل حقولها.
     *
     * **اختارها المستخدم صراحةً** بعد عرض البديل المحاسبي (قيدٌ عكسيّ ثمّ
     * حركةٌ جديدة). فالتنفيذ هنا يحفظ ما يمكن حفظُه من سلامة الدفتر رغم
     * الكتابة في المكان:
     *
     *   • **القيد يُعاد كتابته لا يُحذف**: نفس `journal_entry_id` تبقى،
     *     فلا تصير الحركةُ يتيمةً ولا يُخلَّف قيدٌ بلا حركة. وسطراه
     *     يُستبدلان بالطرفين الجديدين، فيبقى مديناً = دائناً.
     *
     *   • **الأرصدة تُشتقّ من القيود لا تُجمَع يدوياً**:
     *     `updateBalancesHierarchically` تعيد حساب كل حسابٍ مسّته الحركة
     *     قديمةً كانت أو جديدة — فلو تبدّل الطرف المقابل أو الخزينة رجع
     *     الحساب المهجور إلى رقمه الصحيح.
     *
     *   • **أثرُ الفاتورة يُفكّ ثمّ يُعاد**: المسدَّد من المحفظة والمستحقّ
     *     والحالة تعود إلى ما كانت عليه على الفاتورة القديمة، ثمّ تُطبَّق
     *     على الجديدة.
     *
     *   • **`balance_after` يُعاد ترتيبه للحركات كلِّها**: تعديل حركةٍ
     *     قديمة يُبطل عمود «الرصيد بعدها» في كل ما تلاها، وكشفُ حسابٍ
     *     أرقامُه لا تتسلسل أسوأ من كشفٍ لا يُعرض.
     *
     * @param  array{type:string,amount:float,treasury_account_id:?int,invoice_id:?int,note:?string}  $data
     *
     * @throws \Exception إن أخرج التعديلُ المحفظةَ إلى رصيدٍ سالب
     */
    public static function updateTransaction(ClientWalletTransaction $transaction, array $data): ClientWalletTransaction
    {
        $transaction = ClientWalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

        $client = Client::findOrFail($transaction->client_id);
        $wallet = Account::whereKey(self::accountFor($client)->id)->lockForUpdate()->first();

        $type = $data['type'];
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new \Exception('المبلغ يجب أن يكون أكبر من صفر.');
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new \Exception('نوع حركة غير معروف: '.$type);
        }

        // ── 1. فكّ أثر الحركة القديمة عن فاتورتها ──
        $oldInvoice = $transaction->invoice_id
            ? Invoice::whereKey($transaction->invoice_id)->lockForUpdate()->first()
            : null;

        if ($oldInvoice) {
            $sign = $transaction->isCredit() ? -1 : 1;   // ما خُصم يُردّ، وما رُدّ يُخصم
            $oldInvoice->wallet_paid_amount = round(max(0, (float) $oldInvoice->wallet_paid_amount - ($sign * (float) $transaction->amount)), 2);
            self::applyToInvoice($oldInvoice, $sign * (float) $transaction->amount);
        }

        // ── 2. الطرفان الجديدان ──
        $treasury = ($data['treasury_account_id'] ?? null)
            ? Account::whereKey($data['treasury_account_id'])->lockForUpdate()->first()
            : null;

        $newInvoice = ($data['invoice_id'] ?? null)
            ? Invoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail()
            : null;

        if ($newInvoice && (int) $newInvoice->client_id !== (int) $client->id) {
            throw new \Exception('الفاتورة لا تخصّ هذا العميل.');
        }

        [$counterparty, $walletIsDebit, $label] = self::leg($client, $type, $treasury);

        if (! $counterparty) {
            throw new \Exception('الحساب المقابل لهذه الحركة غير متاح.');
        }

        $counterparty = Account::whereKey($counterparty->id)->lockForUpdate()->first();

        $description = $label.': '.$client->display_name
            .($newInvoice ? ' — فاتورة '.$newInvoice->code : '')
            .(($data['note'] ?? null) ? ' — '.$data['note'] : '');

        // ── 3. إعادة كتابة القيد في مكانه ──
        $entry = JournalEntry::find($transaction->journal_entry_id);

        if (! $entry) {
            throw new \Exception('قيد هذه الحركة غير موجود — تعذّر التعديل.');
        }

        // الحسابات التي مسَّها القيد قبل التعديل: تحتاج إعادة حسابٍ ولو هُجرت
        $touched = JournalEntryDetail::where('journal_entry_id', $entry->id)->pluck('account_id')->all();

        JournalEntryDetail::where('journal_entry_id', $entry->id)->delete();

        $entry->description = $description;
        $entry->reference_number = $newInvoice->code ?? null;
        $entry->invoice_id = $newInvoice->id ?? null;
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
        $transaction->invoice_id = $newInvoice->id ?? null;
        $transaction->reference = $newInvoice->code ?? null;
        $transaction->note = $data['note'] ?? null;
        $transaction->save();

        if ($newInvoice) {
            $sign = $transaction->isCredit() ? -1 : 1;
            $newInvoice->wallet_paid_amount = round(max(0, (float) $newInvoice->wallet_paid_amount + ($sign * $amount)), 2);
            self::applyToInvoice($newInvoice, -$sign * $amount);
        }

        self::resequenceBalances($client, $wallet);

        return $transaction->fresh();
    }

    /**
     * إعادة ترتيب عمود «الرصيد بعدها» على حركات المحفظة كلِّها.
     *
     * المرجع رصيدُ الحساب المشتقُّ من القيود: يُطرح منه مجموع أثر الحركات
     * فيُعرف ما قبلها، ثمّ يُمشى عليها بالترتيب. فآخر سطرٍ في الكشف يساوي
     * رصيد المحفظة دائماً — وهو الشرط الذي يقرأه المراجع.
     */
    private static function resequenceBalances(Client $client, Account $wallet): void
    {
        $rows = ClientWalletTransaction::where('client_id', $client->id)->orderBy('id')->get();

        $net = $rows->sum(fn (ClientWalletTransaction $t) => $t->isCredit() ? (float) $t->amount : -(float) $t->amount);
        $running = round((float) $wallet->balance - $net, 2);

        foreach ($rows as $row) {
            $running = round($running + ($row->isCredit() ? (float) $row->amount : -(float) $row->amount), 2);

            if ((float) $row->balance_after !== $running) {
                $row->balance_after = $running;
                $row->save();
            }
        }
    }

    /**
     * الكتابة المشتركة: حركةٌ في الدفتر، وقيدٌ متوازن، ورصيدان يتحرّكان.
     *
     * تُستدعى داخل معاملة المتحكّم — والخطأ هنا يُبطل العملية كلَّها ولا
     * يُبتلع: محفظةٌ تتغيّر وقيدُها يسقط صامتاً تترك الدفتر مخالفاً للرصيد.
     */
    private static function post(
        Client $client,
        string $type,
        float $amount,
        ?Account $treasury,
        ?Invoice $invoice,
        ?string $note
    ): ClientWalletTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \Exception('المبلغ يجب أن يكون أكبر من صفر.');
        }

        /* الحسابُ يُقفَل لا يُقرأ فقط.
           `balance` تُقرأ هنا ثمّ تُكتب بعد أسطر، فطلبان متزامنان على
           المحفظة نفسها يقرآن الرقمَ نفسَه ويكتب آخرُهما فوق الأوّل:
           إيداعان بألفٍ يتركان المحفظة بألف، وسحبان من رصيدٍ يكفي أحدَهما
           يمرّان معاً فيهبط الرصيد تحت الصفر. و`lockForUpdate` تُسلسِلهما
           داخل معاملة المتحكّم. */
        $wallet = Account::whereKey(self::accountFor($client)->id)->lockForUpdate()->first();

        [$counterparty, $walletIsDebit, $label] = self::leg($client, $type, $treasury);
        $reference = self::referenceFor($type);

        if (! $counterparty) {
            throw new \Exception('الحساب المقابل لهذه الحركة غير متاح.');
        }

        /* كفايةُ الرصيد تُعاد **بعد** القفل.
           فحصُ `assertSufficient` في `withdraw()`/`payInvoice()` يقع قبله،
           فيقرأ رقماً قد يكون طلبٌ آخر غيّره قبل أن نصل هنا. وهذا الفحص هو
           الحاجز الأخير: المحفظة لا تُسحب على المكشوف. */
        if ($walletIsDebit && $amount > (float) $wallet->balance + 0.005) {
            throw new \Exception('رصيد المحفظة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($wallet->balance));
        }

        $counterparty = Account::whereKey($counterparty->id)->lockForUpdate()->first();

        $description = $label.': '.$client->display_name
            .($invoice ? ' — فاتورة '.$invoice->code : '')
            .($note ? ' — '.$note : '');

        $entry = JournalEntry::create([
            'reference_number' => $invoice->code ?? null,
            'date' => now(),
            'description' => $description,
            'reference_type' => $reference,
            'status' => 1,
            'currency' => CurrencySettings::code(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id ?? null,
            'created_by_employee' => Auth::id(),
        ]);

        $entry->batch_reference = self::BATCH_PREFIX.$entry->id;
        $entry->save();

        // طرفا القيد — المحفظة أوّلاً حين تكون مدينة، وإلا فالمقابل
        self::detail($entry, $walletIsDebit ? $wallet : $counterparty, $amount, true, $description);
        self::detail($entry, $walletIsDebit ? $counterparty : $wallet, $amount, false, $description);

        // المحفظة دائنةٌ بطبيعتها: الدائن يزيدها والمدين ينقصها
        $wallet->balance = round((float) $wallet->balance + ($walletIsDebit ? -$amount : $amount), 2);
        $wallet->save();

        $counterparty->balance = round((float) $counterparty->balance + ($walletIsDebit ? $amount : -$amount), 2);
        $counterparty->save();

        Account::updateBalancesHierarchically($wallet->id);
        Account::updateBalancesHierarchically($counterparty->id);

        /* الرصيد بعد الحركة يُقرأ **بعد** إعادة الحساب لا قبلها.
           `updateBalancesHierarchically` تُعيد اشتقاق الرصيد من القيود
           فتكتب فوق الجمع اليدوي أعلاه. وقراءةُ الكائن في الذاكرة كانت
           تُثبِّت في كشف الحركة رقماً قد يخالف ما استقرّ عليه الحساب —
           فيقرأ المراجعُ عموداً لا يطابق رصيدَ المحفظة. */
        $wallet->refresh();

        return ClientWalletTransaction::create([
            'client_id' => $client->id,
            'account_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'treasury_account_id' => $treasury->id ?? null,
            'invoice_id' => $invoice->id ?? null,
            'journal_entry_id' => $entry->id,
            'reference' => $invoice->code ?? null,
            'note' => $note,
            'branch_id' => $client->branch_id ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * طرفا الحركة: الحساب المقابل، واتجاهُ المحفظة، وعنوانُ القيد.
     *
     * موضعٌ واحد لتعريفها — تقرؤه الكتابة (`post`) والتعديل
     * (`updateTransaction`) معاً، فلا يفترق الاتجاهُ بين إنشاء الحركة
     * وتصحيحها.
     *
     * @return array{0:?Account,1:bool,2:string} أتكون المحفظة مدينةً بهذه الحركة؟
     */
    private static function leg(Client $client, string $type, ?Account $treasury): array
    {
        return match ($type) {
            'deposit' => [$treasury, false, 'إيداع في محفظة العميل'],
            'withdraw' => [$treasury, true, 'سحب من محفظة العميل'],
            'invoice_paid' => [self::receivableAccount($client), true, 'سداد فاتورة من محفظة العميل'],
            'refund' => [self::receivableAccount($client), false, 'ردّ سداد فاتورة إلى محفظة العميل'],
            default => throw new \Exception('نوع حركة غير معروف: '.$type),
        };
    }

    /**
     * نوع المرجع في القيد — ليس واحداً لكل الحركات.
     *
     * الإيداع سندُ قبضٍ حقيقيّ، والسحب سندُ صرف، والسدادُ تحصيلٌ من عميل —
     * فيُقرأ كلٌّ في تقريره.
     */
    private static function referenceFor(string $type): ReferenceType
    {
        return match ($type) {
            'deposit' => ReferenceType::RECEIPT_VOUCHER,
            'withdraw' => ReferenceType::PAYMENT_VOUCHER,
            'invoice_paid', 'refund' => ReferenceType::CUSTOMER_COLLECTION,
            default => throw new \Exception('نوع حركة غير معروف: '.$type),
        };
    }

    /** حساب ذمم العميل — الطرف المقابل عند السداد من المحفظة */
    private static function receivableAccount(Client $client): ?Account
    {
        return Account::where('client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('type_accont')->orWhere('type_accont', '<>', 'client_wallet');
            })
            ->first();
    }

    /** @throws \Exception إن كان الرصيد أقلّ من المطلوب */
    private static function assertSufficient(Client $client, float $amount): void
    {
        $balance = self::balance($client);

        if ($amount > $balance + 0.005) {
            throw new \Exception('رصيد المحفظة غير كافٍ. الرصيد الحالي: '.CurrencySettings::format($balance));
        }
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

    /** كودٌ فريد تحت الأب — نفس نمط توليد أكواد حسابات العملاء */
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
