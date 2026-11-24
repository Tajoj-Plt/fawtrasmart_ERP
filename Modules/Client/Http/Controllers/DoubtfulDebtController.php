<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\DoubtfulDebt;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * الديون المشكوك فيها: تحصيلها، وعكسُ تحصيلها، وشطبُها، وإرجاعُها.
 *
 * **ما يأتي من الإعدادات** — كان كلّه مكتوباً في الشيفرة:
 *
 *   • *الحسابات* ← «الحسابات المحاسبية» وحدها، بلا احتياطٍ برمجي
 *                  (`ResolvesAccountingAccounts`). كانت ستّة مواضع تكتب
 *                  `Account::findOrFail(SystemAccountIds::MAIN_TREASURY)`
 *                  و`…::ALLOWANCE_FOR_DOUBTFUL_DEBTS`، فما يربطه المحاسب
 *                  في الشاشة لا يسري على قيدٍ واحد.
 *   • *العملة*   ← «العملات» (`UsesDocumentCurrency`). كانت `'SAR'` مكتوبة
 *                  نصّاً في اثني عشر موضعاً.
 *   • *الإقفال*  ← «الفترات المحاسبية»: تاريخُ تحصيلٍ أو عكسٍ في فترةٍ
 *                  مقفلة يردّ العملية (`RespectsAccountingPeriods`).
 *   • *حدّ الشطب*← «إعدادات وحدات العملاء»: مبلغٌ يتجاوز الحدّ يحتاج
 *                  صلاحية اعتمادٍ ثانية (`EnforcesClientModuleSettings`).
 *   • *السجلّات* ← `OperationLog`: سجلّ النشاط وسجلّ الأحداث وسجلّ الأخطاء.
 *                  كان `ModelsLog::create()` يكتب سطراً في جدولٍ واحد بلا
 *                  عنوان IP ولا متصفّح ولا فرقِ قيم، و`Log::error()` يكتب
 *                  في ملفّ لارافيل فلا تراه شاشةُ «سجلّ الأخطاء».
 *
 * **الصلاحيات**: كان `middleware('auth')` وحده — أيُّ مستخدمٍ مسجَّل يقدر
 * يشطب دَيناً أو يعكس قيداً. صارت على كتالوج `clients_manage_debts`
 * و`clients_approve_large_debt_writeoff`.
 */
class DoubtfulDebtController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\UsesDocumentCurrency;

    /** قيمة العمود `type` في سجلّ النشاط — بها يربط المستندُ خطَّه الزمني */
    public const LOG_MODULE = 'ClientDoubtfulDebts';

    /** مفتاح الخزينة في «الحسابات المحاسبية» */
    private const ACCOUNT_TREASURY = 'main_treasury';

    /** مفتاح مخصّص الديون المشكوك فيها */
    private const ACCOUNT_ALLOWANCE = 'allowance_for_doubtful_debts';

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_manage_debts', ['only' => [
            'index', 'show', 'updateCollection', 'reverseCollection',
            'reverseWithoutCollection', 'writeOff',
        ]]);
    }

    /**
     * قائمة الديون المشكوك فيها.
     */
    public function index(Request $request)
    {
        if ($block = $this->sectionBlock('doubtful_debts')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        /* فرعُ الشريط العلويّ — كانت الشاشة لا تقرؤه: القائمة والبلاطات عن المنشأة
           كلِّها أيّاً كان الفرع المختار. الدَّينُ يتبع عميلَه، والعميلُ يُطابَق بعقدته
           وما تحتها (`inTreeNode`)، والرئيسيُّ يعني المنشأة كلَّها (`HeaderBranchScope`). */
        $node = HeaderBranchScope::nodeId();
        $scoped = fn () => DoubtfulDebt::query()
            ->when($node, fn ($q) => $q->whereHas('client', fn ($c) => $c->inTreeNode($node)));

        $query = $scoped()->with(['client', 'account', 'journalEntry', 'createdByUser'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $doubtfulDebts = $query->paginate(20)->withQueryString();

        // البلاطاتُ على فرع الشريط نفسه، لا على فلاتر البحث
        $stats = [
            'total' => $scoped()->count(),
            'pending' => $scoped()->where('status', 'pending')->count(),
            'collected' => $scoped()->where('status', 'collected')->count(),
            'written_off' => $scoped()->where('status', 'written_off')->count(),
            'total_amount' => $scoped()->sum('original_balance'),
            'total_provision' => $scoped()->sum('provision_amount'),
            'total_collected' => $scoped()->sum('collected_amount'),
        ];

        // قائمةُ اختيار العميل في الفلتر على الفرع نفسه
        $clients = Client::query()
            ->when($node, fn ($q) => $q->inTreeNode($node))
            ->orderBy('trade_name')
            ->get(['id', 'trade_name', 'code']);

        $headerBranch = $node ? Branch::findCached($node) : null;

        return view('client::doubtful_debts.index', compact('doubtfulDebts', 'stats', 'clients', 'headerBranch'));
    }

    /**
     * تفاصيل دَينٍ مشكوك فيه.
     */
    public function show($id)
    {
        if ($block = $this->sectionBlock('doubtful_debts')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $doubtfulDebt = DoubtfulDebt::with(['client', 'account', 'journalEntry', 'createdByUser', 'collectedByUser'])
            ->findOrFail($id);

        return view('client::doubtful_debts.show', compact('doubtfulDebt'));
    }

    /**
     * تحصيل دَينٍ مشكوك فيه — قيدٌ عكسي ثم قيدُ تحصيل.
     */
    public function updateCollection(Request $request, $id)
    {
        $validated = $request->validate([
            'collected_amount' => 'required|numeric|min:0',
            'collection_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank,check',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // الفترة تُفحَص قبل أي كتابة: القيد المرحَّل إلى سنةٍ أُقفلت لا يُسحَب
        if ($block = $this->accountingPeriodBlock($validated['collection_date'])) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            $doubtfulDebt = DB::transaction(function () use ($validated, $id) {
                $doubtfulDebt = DoubtfulDebt::lockForUpdate()->findOrFail($id);
                $client = $doubtfulDebt->client;
                $clientAccount = $doubtfulDebt->account;

                $collectedAmount = (float) $validated['collected_amount'];
                $remainingBalance = $doubtfulDebt->getRemainingBalance();

                if ($collectedAmount <= 0) {
                    throw new \RuntimeException('المبلغ المدفوع يجب أن يكون أكبر من صفر');
                }

                if ($collectedAmount > $remainingBalance && $remainingBalance > 0) {
                    throw new \RuntimeException(
                        'المبلغ المدفوع أكبر من الرصيد المتبقي: '.number_format($remainingBalance, 2)
                    );
                }

                $treasury = $this->requireAccountingAccount(self::ACCOUNT_TREASURY);
                $balanceToSettle = $doubtfulDebt->provision_amount;
                $currency = $this->defaultCurrency();

                // ══ قيد عكسي: إرجاع المديونية للعميل ══
                $reverseEntry = $this->createEntry(
                    'DD-REVERSE-'.now()->format('YmdHis').'-'.$client->id,
                    $validated['collection_date'],
                    'قيد عكسي - إرجاع مديونية مشكوك - '.$client->trade_name,
                    $client->id,
                    $currency
                );

                $this->createLine($reverseEntry, $treasury->id, 'قيد عكسي - استرجاع من العميل - '.$client->trade_name, $balanceToSettle, 0, $client->id, $currency);
                $this->createLine($reverseEntry, $clientAccount->id, 'قيد عكسي - إرجاع مديونية - '.$client->trade_name, 0, $balanceToSettle, $client->id, $currency);

                // ══ قيد التحصيل الفعلي ══
                $collectEntry = $this->createEntry(
                    'DD-COLLECT-'.now()->format('YmdHis').'-'.$client->id,
                    $validated['collection_date'],
                    'تحصيل مديونية مشكوك فيها - '.$client->trade_name.' - المبلغ: '.$collectedAmount,
                    $client->id,
                    $currency
                );

                $this->createLine($collectEntry, $clientAccount->id, 'تحصيل مديونية مشكوك - '.$client->trade_name, $balanceToSettle, 0, $client->id, $currency);
                $this->createLine($collectEntry, $treasury->id, 'استقبال تحصيل مديونية مشكوك - '.$client->trade_name, 0, $balanceToSettle, $client->id, $currency);

                $doubtfulDebt->collected_amount = ($doubtfulDebt->collected_amount ?? 0) + $collectedAmount;
                $doubtfulDebt->collected_date = $validated['collection_date'];
                $doubtfulDebt->collected_by = auth()->id();
                $doubtfulDebt->notes = trim(($doubtfulDebt->notes ?? '')."\n".($validated['notes'] ?? ''));

                if ($doubtfulDebt->isFullyCollected()) {
                    $doubtfulDebt->status = 'collected';
                }

                $doubtfulDebt->save();

                // تصفية كاملة لحساب العميل
                $clientAccount->balance = 0;
                $clientAccount->save();

                OperationLog::record(
                    'collected',
                    self::LOG_MODULE,
                    $doubtfulDebt,
                    'تحصيل '.number_format($collectedAmount, 2).' من دَين مشكوك فيه للعميل '.$client->trade_name.
                    ' — القيد العكسي '.$reverseEntry->reference_number.' وقيد التحصيل '.$collectEntry->reference_number,
                    ['new' => [
                        'collected_amount' => $doubtfulDebt->collected_amount,
                        'status' => $doubtfulDebt->status,
                        'currency' => $currency,
                    ]]
                );

                return $doubtfulDebt;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update_collection', (int) $id);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('doubtful-debts.show', $id)->with(
            'success',
            $doubtfulDebt->isFullyCollected()
                ? 'تم تحصيل المبلغ بنجاح. سُجّل قيدٌ عكسي وقيدُ تحصيل، والحساب مُسوّى الآن.'
                : 'تم تحصيل '.number_format((float) $request->collected_amount, 2).' بنجاح. الرصيد المتبقي: '.number_format($doubtfulDebt->getRemainingBalance(), 2)
        );
    }

    /**
     * إلغاء التحصيل — إرجاع المديونية للعميل ثم تسجيل سدادها.
     */
    public function reverseCollection(Request $request, $id)
    {
        $validated = $request->validate([
            'reversal_date' => 'required|date',
            'reversal_reason' => 'required|string|max:500',
        ]);

        if ($block = $this->accountingPeriodBlock($validated['reversal_date'])) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            $reversalAmount = DB::transaction(function () use ($validated, $id) {
                $doubtfulDebt = DoubtfulDebt::lockForUpdate()->findOrFail($id);
                $client = $doubtfulDebt->client;
                $clientAccount = $doubtfulDebt->account;

                $reversalAmount = (float) ($doubtfulDebt->collected_amount ?? 0);

                if ($reversalAmount <= 0) {
                    throw new \RuntimeException('لا يوجد مبلغ محصَّل للإلغاء');
                }

                $treasury = $this->requireAccountingAccount(self::ACCOUNT_TREASURY);
                $currency = $this->defaultCurrency();

                // ══ قيد عكسي: إرجاع المديونية ══
                $reverseEntry = $this->createEntry(
                    'DD-REVERSE-'.now()->format('YmdHis').'-'.$client->id,
                    $validated['reversal_date'],
                    'إلغاء تحصيل مديونية مشكوك - '.$client->trade_name.' - المبلغ: '.$reversalAmount,
                    $client->id,
                    $currency
                );

                $this->createLine($reverseEntry, $treasury->id, 'استرجاع تحصيل مديونية مشكوك - '.$client->trade_name, $reversalAmount, 0, $client->id, $currency);
                $this->createLine($reverseEntry, $clientAccount->id, 'إرجاع مديونية مشكوك - '.$client->trade_name, 0, $reversalAmount, $client->id, $currency);

                // ══ قيد السداد ══
                $paymentEntry = $this->createEntry(
                    'DD-PAYMENT-'.now()->format('YmdHis').'-'.$client->id,
                    $validated['reversal_date'],
                    'سداد مديونية مشكوك - '.$client->trade_name.' - المبلغ: '.$reversalAmount,
                    $client->id,
                    $currency
                );

                $this->createLine($paymentEntry, $treasury->id, 'استقبال سداد مديونية مشكوك - '.$client->trade_name, $reversalAmount, 0, $client->id, $currency);
                $this->createLine($paymentEntry, $clientAccount->id, 'سداد مديونية مشكوك - '.$client->trade_name, 0, $reversalAmount, $client->id, $currency);

                $doubtfulDebt->collected_amount = 0;
                $doubtfulDebt->collected_date = null;
                $doubtfulDebt->collected_by = null;
                $doubtfulDebt->status = 'pending';
                $doubtfulDebt->notes = ($doubtfulDebt->notes ?? '')."\n\nإلغاء تحصيل: ".$validated['reversal_reason'];
                $doubtfulDebt->save();

                $clientAccount->balance = $doubtfulDebt->provision_amount;
                $clientAccount->save();

                OperationLog::record(
                    'reversed',
                    self::LOG_MODULE,
                    $doubtfulDebt,
                    'إلغاء تحصيل دَين مشكوك فيه للعميل '.$client->trade_name.
                    ' بمبلغ '.number_format($reversalAmount, 2).' — السبب: '.$validated['reversal_reason'],
                    ['new' => ['status' => 'pending', 'collected_amount' => 0, 'currency' => $currency]]
                );

                return $reversalAmount;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reverse_collection', (int) $id);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('doubtful-debts.show', $id)->with(
            'success',
            'تم إلغاء التحصيل بنجاح. أُرجعت المديونية وسُجّل السداد بمبلغ '.number_format($reversalAmount, 2)
        );
    }

    /**
     * شطب الدين المشكوك فيه — إلغاءُ المخصّص مقابل حساب العميل.
     *
     * الشطب فوق الحدّ المضبوط في «إعدادات وحدات العملاء» يحتاج صلاحية
     * اعتمادٍ ثانية؛ والحدّ فارغاً يُبقي السلوك السابق.
     */
    public function writeOff(Request $request, $id)
    {
        $validated = $request->validate([
            'write_off_reason' => 'required|string|max:500',
        ]);

        $doubtfulDebt = DoubtfulDebt::findOrFail($id);
        $remainingProvision = (float) $doubtfulDebt->provision_amount - (float) ($doubtfulDebt->collected_amount ?? 0);

        if ($block = $this->debtWriteoffBlock($remainingProvision)) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            DB::transaction(function () use ($validated, $id, $remainingProvision) {
                $doubtfulDebt = DoubtfulDebt::lockForUpdate()->findOrFail($id);
                $client = $doubtfulDebt->client;
                $clientAccount = $doubtfulDebt->account;

                $allowance = $this->requireAccountingAccount(self::ACCOUNT_ALLOWANCE);
                $currency = $this->defaultCurrency();

                $doubtfulDebt->status = 'written_off';
                $doubtfulDebt->notes = ($doubtfulDebt->notes ?? '')."\nشطب: ".$validated['write_off_reason'];
                $doubtfulDebt->save();

                // الرقم كان `'DD-'. '-' . $client->id` فيخرج `DD--12`،
                // ويتكرّر حرفياً لكل شطبٍ على نفس العميل
                $entry = $this->createEntry(
                    'DD-WRITEOFF-'.now()->format('YmdHis').'-'.$client->id,
                    now()->toDateString(),
                    'شطب مديونية مشكوك فيها - '.$client->trade_name,
                    $client->id,
                    $currency
                );

                $this->createLine($entry, $clientAccount->id, 'شطب مخصص ديون مشكوك - '.$client->trade_name, $remainingProvision, 0, $client->id, $currency);
                $this->createLine($entry, $allowance->id, 'إلغاء مخصص ديون مشكوك - '.$client->trade_name, 0, $remainingProvision, $client->id, $currency);

                OperationLog::record(
                    'written_off',
                    self::LOG_MODULE,
                    $doubtfulDebt,
                    'شطب دَين مشكوك فيه للعميل '.$client->trade_name.
                    ' بمبلغ '.number_format($remainingProvision, 2).' — السبب: '.$validated['write_off_reason'],
                    ['new' => ['status' => 'written_off', 'amount' => $remainingProvision, 'currency' => $currency]]
                );
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'write_off', (int) $id);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('doubtful-debts.show', $id)
            ->with('success', 'تم شطب المديونية المشكوك فيها بنجاح.');
    }

    /**
     * إرجاع المديونية بحذف قيدها الأصلي — بلا قيود جديدة.
     *
     * هذه العملية تمحو قيداً مرحَّلاً، فتُفحَص بتاريخ القيد المحذوف لا
     * بتاريخ اليوم: حذفُ قيدٍ من سنةٍ مقفلة يغيّر أرصدةَ قوائمها كما لو
     * أُضيف إليها قيدٌ جديد.
     */
    public function reverseWithoutCollection(Request $request, $id)
    {
        $validated = $request->validate([
            'reversal_date' => 'required|date',
            'reason' => 'required|string|max:100',
            'notes' => 'required|string|max:500',
        ]);

        $doubtfulDebt = DoubtfulDebt::with('journalEntry')->findOrFail($id);

        $originalEntryDate = $doubtfulDebt->journalEntry?->date ?? $validated['reversal_date'];

        foreach ([$validated['reversal_date'], $originalEntryDate] as $date) {
            if ($block = $this->accountingPeriodBlock($date)) {
                return redirect()->back()->withInput()->with('error', $block);
            }
        }

        try {
            $originalBalance = DB::transaction(function () use ($validated, $id) {
                $doubtfulDebt = DoubtfulDebt::lockForUpdate()->findOrFail($id);
                $client = $doubtfulDebt->client;
                $clientAccount = $doubtfulDebt->account;

                $originalBalance = (float) $doubtfulDebt->original_balance;
                $journalEntryId = $doubtfulDebt->journal_entry_id;

                if ($journalEntryId) {
                    JournalEntryDetail::where('journal_entry_id', $journalEntryId)->delete();
                    JournalEntry::where('id', $journalEntryId)->delete();
                }

                $clientAccount->balance = $clientAccount->balance + $originalBalance;
                $clientAccount->save();

                OperationLog::record(
                    'reverted',
                    self::LOG_MODULE,
                    (int) $id,
                    'إرجاع مديونية مشكوك فيها بلا قيود للعميل '.$client->trade_name.
                    ' بمبلغ '.number_format($originalBalance, 2).
                    ' — السبب: '.$validated['reason'].' — '.$validated['notes'].
                    ' — القيد المحذوف: '.($journalEntryId ?? 'لا يوجد'),
                    ['old' => ['journal_entry_id' => $journalEntryId, 'original_balance' => $originalBalance]]
                );

                $doubtfulDebt->delete();

                return $originalBalance;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reverse_without_collection', (int) $id);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('doubtful-debts.index')->with(
            'success',
            'تم إرجاع المديونية بنجاح. حُذفت من الديون المشكوك فيها وحُذف قيدها الأصلي وأُرجع الرصيد للعميل: '
            .\App\Support\CurrencySettings::format($originalBalance)
        );
    }

    /**
     * رأسُ قيدٍ بعملة النظام — كانت `'SAR'` مكتوبةً في كل نداء.
     */
    private function createEntry(string $reference, string $date, string $description, int $clientId, string $currency): JournalEntry
    {
        return JournalEntry::create([
            'reference_number' => $reference,
            'date' => $date,
            'description' => $description,
            'status' => 1,
            'currency' => $currency,
            'client_id' => $clientId,
            'reference_type' => \App\Enums\ReferenceType::JOURNAL_VOUCHER,
            'created_by_employee' => auth()->id(),
            'approved_by_employee' => auth()->id(),
            'is_posted' => 1,
            'branch_id' => auth()->user()->branch_id,
        ]);
    }

    /**
     * طرفُ قيد — ثمانيةُ نداءاتٍ متطابقة كانت مكتوبةً بالكامل في كل موضع.
     */
    private function createLine(
        JournalEntry $entry,
        int $accountId,
        string $description,
        float $debit,
        float $credit,
        int $reference,
        string $currency
    ): JournalEntryDetail {
        return JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accountId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'reference' => $reference,
            'currency' => $currency,
            'is_debit' => $debit > 0,
        ]);
    }
}
