<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Services\Wallets\ClientWalletService;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use App\Support\TreasuryAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * محافظ العملاء — البند ١١ من «تقرير الإصلاح والتحسين».
 *
 * **لماذا هنا لا في المبيعات؟** المحفظة صفةٌ للعميل لا للفاتورة: رصيدُه
 * عندنا يُودَع ويُسحَب ويُسدَّد منه، ويُقرأ من ملفّه. وكانت الشاشة في
 * وحدة المبيعات باسم «محفظة العملاء» وهي في الحقيقة تعدّل **الرصيد
 * الافتتاحي لحساب ذمم العميل** — أي ما *عليه* لا ما *له* — بقيدٍ على
 * حسابٍ غير مربوط أصلاً، فلا تعمل.
 *
 * **الفرق في سطر:** تلك تغيّر دَينَ العميل علينا، وهذه تحفظ مالَه عندنا.
 *
 * الحساب لكل عميل تحت الأب المضبوط في «الحسابات المحاسبية»
 * (`client_wallets_parent`) داخل الخصوم، والقيود كلُّها في
 * `App\Services\Wallets\ClientWalletService` — لا يكتب هذا المتحكّم قيداً
 * بنفسه، بل يتحقّق ويفوّض ويسجّل.
 *
 * @see \App\Services\Wallets\ClientWalletService محرّك المحفظة وقيودها
 */
class ClientWalletController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientWallet';

    /** الأحجام المسموحة لعدد السجلّات في الصفحة */
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients.wallet.view', ['only' => ['index', 'show']]);

        /* شاشتا الإضافة والتعديل تقبلان الأنواع الثلاثة، فحارسُ المسار
           «أيّ صلاحية حركة»، والنوعُ المرسَل يُفحَص بصلاحيته وحده داخل
           `store()`/`update()` — انظر `assertMayPost()`. ولو حُرست الشاشة
           بصلاحيةٍ واحدة لَحُجبت عمّن يملك غيرها. */
        $this->middleware('permission:clients.wallet.deposit|clients.wallet.withdraw|clients.wallet.pay_invoice',
            ['only' => ['create', 'store', 'edit', 'update']]);
    }

    /** صلاحية كل نوع حركة — الحارس الحقيقي، لا حارسُ المسار */
    private const TYPE_PERMISSIONS = [
        'deposit' => 'clients.wallet.deposit',
        'withdraw' => 'clients.wallet.withdraw',
        'invoice_paid' => 'clients.wallet.pay_invoice',
        'refund' => 'clients.wallet.pay_invoice',
    ];

    /** تسمية النوع في الشاشات */
    private const TYPE_LABELS = [
        'deposit' => 'إيداع في المحفظة',
        'withdraw' => 'سحب من المحفظة',
        'invoice_paid' => 'سداد فاتورة من المحفظة',
        'refund' => 'ردّ سداد فاتورة إلى المحفظة',
    ];

    /**
     * قائمة محافظ العملاء.
     *
     * العملاء كلُّهم يظهرون — لا أصحابُ الحسابات وحدهم — فمحفظةُ العميل
     * تُنشأ عند أوّل إيداع، ومن لا محفظة له رصيدُه صفر. ولو سُردت
     * الحسابات وحدها لَما وجد المستخدم عميلاً يريد أن يودع له أوّل مرّة.
     */
    public function index(Request $request)
    {
        /* فرعُ الشريط العلويّ — كانت الشاشةُ تعرض عملاءَ المنشأة كلَّها ومجموعَ
           محافظهم أيّاً كان الفرع المختار؛ ونظيرتُها في المشتريات تقرؤه. العميلُ
           يُطابَق بعقدته وما تحتها، والرئيسيُّ يعني المنشأة كلَّها. */
        $node = HeaderBranchScope::nodeId();
        $inBranch = fn ($q) => $q->when($node, fn ($c) => $c->inTreeNode($node));

        $wallets = Account::where('type_accont', 'client_wallet')
            ->when($node, fn ($q) => $q->whereIn('client_id', $inBranch(Client::query())->select('clients.id')))
            ->pluck('balance', 'client_id');

        // لا عمود `name` في `clients` — الاسم `trade_name`، وإلا الاسمان معاً
        $query = $inBranch(Client::query()->select('id', 'code', 'trade_name', 'first_name', 'last_name', 'phone'));

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';

            $query->where(function ($q) use ($term) {
                $q->where('trade_name', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        // «ذوو الرصيد» يُقرأ من الحسابات لا من جدول العملاء
        if ($request->input('balance') === 'nonzero') {
            $query->whereIn('id', $wallets->filter(fn ($b) => (float) $b != 0)->keys());
        }

        $clients = $query->orderBy('trade_name')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return view('client::wallet.index', [
            'clients' => $clients,
            'wallets' => $wallets,
            'pageSizes' => self::PAGE_SIZES,
            'hasFilters' => $request->hasAny(['search', 'balance']),
            'totalBalance' => (float) $wallets->sum(),
            'walletsParentMapped' => AccountingSettings::isMapped('client_wallets_parent'),
        ]);
    }

    /**
     * محفظة عميل واحد: الرصيد وكشفُ حركته وفواتيره غير المسدَّدة.
     */
    public function show($clientId)
    {
        $client = Client::findOrFail($clientId);

        $transactions = ClientWalletTransaction::where('client_id', $client->id)
            ->with(['invoice:id,code', 'treasury:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->paginate(25);

        /* الفواتير المرشَّحة للسداد من المحفظة: ما لم يُسدَّد منها كاملاً
           من المحفظة بعد. والمقارنة بـ`grand_total` لا بـ`due_value`،
           فالأخير يتغيّر بسندات القبض ولا يعرف شيئاً عن المحفظة.
           ولا فاتورة مرتجعة، ولا فاتورة رُدَّت عليها فاتورة مرتجع، ولا
           فاتورة صدر عليها إشعار دائن سارٍ — نفس القيد المطبَّق في شاشة
           الدفع المجمَّع (SalesPaymentController::getClientUnpaidInvoices). */
        $invoices = $this->payableInvoicesQuery($client)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'code', 'grand_total', 'wallet_paid_amount', 'created_at']);

        /* مستنداتٌ لا تُسدَّد من المحفظة — هي بطبيعتها ما يُخصَم منه لا ما
           يُسدَّد — لكن العميل يحتاج رؤيتها من محفظته بلا تنقّل: مرتجعاتُه
           (Invoice بنوع `returned`) وإشعاراتُه الدائنة السارية. لا صلة
           لهما بحركات المحفظة نفسها، فهي هنا للعرض والرجوع إليهما فقط. */
        $returns = Invoice::where('client_id', $client->id)
            ->where('type', 'returned')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'code', 'grand_total', 'reference_id', 'created_at']);

        $creditNotes = CreditNotification::where('client_id', $client->id)
            ->where('status', '!=', 0)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'credit_number', 'grand_total', 'due_value', 'status', 'credit_date', 'invoice_id']);

        return view('client::wallet.show', [
            'client' => $client,
            'balance' => ClientWalletService::balance($client),
            'transactions' => $transactions,
            'invoices' => $invoices,
            'returns' => $returns,
            'creditNotes' => $creditNotes,
            'treasuries' => $this->treasuries(),
            'walletsParentMapped' => AccountingSettings::isMapped('client_wallets_parent'),
        ]);
    }

    /**
     * شاشة إضافة حركة.
     *
     * كانت الحركات الثلاث ثلاثةَ نماذج متجاورة في شاشة العرض، كلٌّ في
     * بطاقةٍ ضيّقة — فالشاشة تخلط «ما الرصيد» بـ«ماذا أفعل به»، ولا موضع
     * فيها لشرح القيد الذي سيُكتب. صارت شاشةً مستقلّة بنموذجٍ واحد يتبدّل
     * بنوع الحركة، كبقيّة مستندات النظام.
     */
    public function create(Request $request, $clientId)
    {
        $client = Client::findOrFail($clientId);

        return view('client::wallet.create', $this->formData($client) + [
            'type' => $this->defaultType($request->input('type')),
        ]);
    }

    /** حفظ حركة جديدة */
    public function store(Request $request, $clientId)
    {
        $validated = $this->validateMovement($request);

        return $this->run($clientId, function (Client $client) use ($validated) {
            $this->assertMayPost($validated['type']);

            return match ($validated['type']) {
                'deposit' => $this->depositAction($client, $validated),
                'withdraw' => $this->withdrawAction($client, $validated),
                default => $this->invoiceAction($client, $validated),
            };
        }, self::TYPE_LABELS[$validated['type']] ?? 'حركة محفظة');
    }

    /**
     * شاشة تعديل حركة.
     *
     * @see \App\Services\Wallets\ClientWalletService::updateTransaction() ما يفعله الحفظ بالقيد
     */
    public function edit($clientId, $transactionId)
    {
        $client = Client::findOrFail($clientId);

        $transaction = ClientWalletTransaction::where('client_id', $client->id)
            ->findOrFail($transactionId);

        return view('client::wallet.edit', $this->formData($client, $transaction->invoice_id) + [
            'transaction' => $transaction,
            'type' => $transaction->type,
        ]);
    }

    /**
     * حفظ تعديل حركة — بكل حقولها.
     *
     * التعديل المباشر اختيار المستخدم بعد عرض البديل (قيدٌ عكسيّ ثمّ حركة
     * جديدة). والخدمة تُعيد كتابة القيد في مكانه وتُبقيه متوازناً وتعيد
     * اشتقاق الأرصدة من القيود — لا جمعاً يدوياً فوق أرقامٍ قديمة.
     */
    public function update(Request $request, $clientId, $transactionId)
    {
        $validated = $this->validateMovement($request);

        return $this->run($clientId, function (Client $client) use ($validated, $transactionId) {
            $this->assertMayPost($validated['type']);

            $transaction = ClientWalletTransaction::where('client_id', $client->id)
                ->findOrFail($transactionId);

            $before = $transaction->typeLabel().' '.number_format((float) $transaction->amount, 2);

            ClientWalletService::updateTransaction($transaction, [
                'type' => $validated['type'],
                'amount' => (float) $validated['amount'],
                'treasury_account_id' => $validated['treasury_account_id'] ?? null,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return 'تم تعديل الحركة #'.$transactionId.' — كانت: '.$before.'.';
        }, 'تعديل حركة محفظة');
    }

    /** إيداع نقدي — من ح/ الخزينة إلى ح/ محفظة العميل */
    private function depositAction(Client $client, array $validated): string
    {
        $treasury = Account::findOrFail($validated['treasury_account_id']);

        ClientWalletService::deposit($client, (float) $validated['amount'], $treasury, $validated['note'] ?? null);

        return 'تم إيداع '.number_format((float) $validated['amount'], 2).' في محفظة العميل.';
    }

    /** سحب نقدي — من ح/ محفظة العميل إلى ح/ الخزينة */
    private function withdrawAction(Client $client, array $validated): string
    {
        $treasury = Account::findOrFail($validated['treasury_account_id']);

        ClientWalletService::withdraw($client, (float) $validated['amount'], $treasury, $validated['note'] ?? null);

        return 'تم سحب '.number_format((float) $validated['amount'], 2).' من محفظة العميل.';
    }

    /**
     * سداد فاتورةٍ أو أكثر من المحفظة، أو ردُّ سدادٍ سابق إليها.
     *
     * «سداد كل الفواتير» ليس نوع حركةٍ جديداً — هو المبلغ نفسه يُوزَّع على
     * الفواتير المختارة بالترتيب (الأقدم أولاً)، فتُنشأ حركةٌ (وقيدٌ) لكل
     * فاتورةٍ أخذت حصّتها، تماماً كالدفع المجمَّع بشاشة المبيعات
     * (SalesPaymentController::bulkPaymentStore). و`payInvoice()`/`post()`
     * تفحصان كفاية المحفظة عند كل استدعاء، فلا فاتورةٌ تُخصَم فوق طاقتها.
     */
    private function invoiceAction(Client $client, array $validated): string
    {
        $invoiceIds = $this->resolveInvoiceIds($validated);

        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get();

        // فاتورةٌ من نموذجٍ آخر لا تخصّ هذا العميل — ولا تُقبل صامتةً
        if ($invoices->count() !== count($invoiceIds)) {
            throw new \Exception('إحدى الفواتير المختارة لا تخصّ هذا العميل.');
        }

        $isRefund = $validated['type'] === 'refund';
        $remaining = round((float) $validated['amount'], 2);
        $applied = 0.0;
        $paidCodes = [];

        foreach ($invoices as $invoice) {
            if ($remaining <= 0.004) {
                break;
            }

            $cap = $isRefund
                ? round((float) ($invoice->wallet_paid_amount ?? 0), 2)
                : ClientWalletService::payableFromWallet($invoice);

            $portion = round(min($remaining, $cap), 2);

            if ($portion <= 0) {
                continue;
            }

            if ($isRefund) {
                ClientWalletService::refundInvoice($invoice, $portion, $validated['note'] ?? null);
            } else {
                ClientWalletService::payInvoice($invoice, $portion, $validated['note'] ?? null);
            }

            $remaining = round($remaining - $portion, 2);
            $applied += $portion;
            $paidCodes[] = $invoice->code;
        }

        if (! $paidCodes) {
            throw new \Exception('لا فاتورة قابلة لهذه الحركة ضمن الفواتير المختارة.');
        }

        $verb = $isRefund ? 'تم ردّ' : 'تم سداد';
        $target = count($paidCodes) === 1
            ? 'فاتورة '.$paidCodes[0]
            : count($paidCodes).' فواتير: '.implode('، ', $paidCodes);

        return $verb.' '.number_format($applied, 2).' على '.$target.'.';
    }

    /** معرّفات الفواتير المختارة — من الحقل المفرد (تعديل) أو المتعدّد (إضافة) */
    private function resolveInvoiceIds(array $validated): array
    {
        $ids = $validated['invoice_ids'] ?? [];

        if (! empty($validated['invoice_id'])) {
            $ids[] = $validated['invoice_id'];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * صلاحية النوع المرسَل — الحارس الحقيقي.
     *
     * حارسُ المسار يقبل «أيّ صلاحية حركة» كي لا تُحجب الشاشة عمّن يملك
     * واحدةً منها، فلولا هذا الفحص لَأمكن لصاحب «الإيداع» أن يسحب.
     */
    private function assertMayPost(string $type): void
    {
        $permission = self::TYPE_PERMISSIONS[$type] ?? null;

        if (! $permission || ! hasPermission($permission)) {
            throw new \Exception('لا تملك صلاحية هذا النوع من الحركات.');
        }
    }

    /** النوع الافتراضي في نموذج الإضافة — مُرسَلٌ صالح أو الإيداع */
    private function defaultType(?string $requested): string
    {
        return in_array($requested, ClientWalletService::TYPES, true) ? $requested : 'deposit';
    }

    /**
     * ما تحتاجه شاشتا الإضافة والتعديل معاً.
     *
     * $keepInvoiceId فاتورة الحركة الجارِ تعديلها — تصل من `edit()` وحدها.
     * سدادُها عبر هذه الحركة نفسها قد يكون رفع `wallet_paid_amount` حتى
     * ساوى `grand_total`، فتسقط من قائمة «القابلة للسداد» رغم أنها فاتورة
     * الحركة المعروضة — فيفقد النموذج قيمتها المختارة بصمت. تُضاف هنا
     * صراحةً كي يبقى اختيارها ظاهراً حتى لو غيّر المستخدم نوع الحركة ثم عاد.
     */
    private function formData(Client $client, ?int $keepInvoiceId = null): array
    {
        $invoices = $this->payableInvoicesQuery($client)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'code', 'grand_total', 'due_value', 'wallet_paid_amount']);

        if ($keepInvoiceId && ! $invoices->contains('id', $keepInvoiceId)) {
            $kept = Invoice::where('client_id', $client->id)
                ->find($keepInvoiceId, ['id', 'code', 'grand_total', 'due_value', 'wallet_paid_amount']);

            if ($kept) {
                $invoices->push($kept);
            }
        }

        return [
            'client' => $client,
            'balance' => ClientWalletService::balance($client),
            'treasuries' => $this->treasuries(),
            'invoices' => $invoices,
            'types' => self::TYPE_LABELS,
            'allowedTypes' => array_keys(array_filter(
                self::TYPE_PERMISSIONS,
                fn (string $permission) => hasPermission($permission)
            )),
            'treasuryTypes' => ClientWalletService::TREASURY_TYPES,
            'invoiceTypes' => ClientWalletService::INVOICE_TYPES,
            'walletsParentMapped' => AccountingSettings::isMapped('client_wallets_parent'),
        ];
    }

    /**
     * الغلاف المشترك: فترةٌ محاسبية، ومعاملة، وسجلّ نشاط، ورسالة.
     *
     * الكتابة كلُّها داخل معاملة واحدة — فحركةٌ تُسجَّل وقيدُها يسقط
     * تترك الرصيد مخالفاً للدفتر.
     */
    private function run($clientId, callable $action, string $logVerb)
    {
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return back()->withInput()->with('error', $blocked);
        }

        try {
            DB::beginTransaction();

            $client = Client::findOrFail($clientId);
            $before = ClientWalletService::balance($client);

            $message = $action($client);

            $after = ClientWalletService::balance($client);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $client,
                sprintf('%s — %s: من %s إلى %s', $logVerb, $client->display_name,
                    number_format($before, 2), number_format($after, 2)),
                ['old' => ['balance' => $before], 'new' => ['balance' => $after]]
            );

            DB::commit();

            return redirect()->route('clients.wallet.show', $clientId)->with('success', $message);
        } catch (Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'wallet_movement', (int) $clientId);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * تحقّقٌ واحد للأنواع كلِّها.
     *
     * الخزينة مطلوبةٌ للنقدية وحدها، والفاتورة للسداد والردّ وحدهما —
     * و`required_if` تقرأ النوع المرسَل، فنموذجٌ واحد يخدم الأربعة بلا أن
     * يطلب حقلاً لا معنى له في نوعه.
     */
    private function validateMovement(Request $request): array
    {
        $treasuryTypes = implode(',', ClientWalletService::TREASURY_TYPES);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', ClientWalletService::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'treasury_account_id' => ['nullable', 'required_if:type,'.$treasuryTypes, 'integer', 'exists:accounts,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'type.required' => 'اختر نوع الحركة.',
            'type.in' => 'نوع حركة غير معروف.',
            'amount.required' => 'أدخل المبلغ.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'treasury_account_id.required_if' => 'اختر الخزينة.',
        ]);

        // الخزينةُ تكفيها `required_if`، لكن الفاتورة تصل من حقلين مختلفين
        // (مفردٌ في التعديل، ومتعدّدٌ في الإضافة) فلا تعبّر عنها قاعدةٌ واحدة
        if (in_array($validated['type'], ClientWalletService::INVOICE_TYPES, true)
            && ! $this->resolveInvoiceIds($validated)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoice_id' => 'اختر فاتورة واحدة على الأقل.',
            ]);
        }

        return $validated;
    }

    /**
     * الخزائن النقدية وحدها — لا بنوك ولا غيرها.
     *
     * كان الاستعلام `whereIn('type_accont', [0, 1])`، وذاك العمود ليس
     * علامةَ خزينة: تُسنده شاشةُ الخزائن عند الإنشاء منها وحدها، فتبقى
     * الخزائن المزروعة مع الشجرة `NULL` — فيُسقطها الاستعلام ويجلب مكانها
     * مستودعاتٍ وسُلفاً صادف أن حملت صفراً.
     */
    private function treasuries()
    {
        return TreasuryAccounts::cash();
    }

    /**
     * فواتير العميل القابلة للسداد من المحفظة — لا فاتورة سُدِّدت منها
     * كاملاً عبر المحفظة سلفاً، ولا فاتورة مرتجعة، ولا فاتورة رُدَّت (أُنشئ
     * ضدّها مرتجع)، ولا فاتورة صدر عليها إشعار دائن سارٍ (الحالة 0 = ملغي،
     * لا تُستثنى بها). نفس القيد المطبَّق بشاشة الدفع المجمَّع للعملاء —
     * راجع Modules\Sales\Http\Controllers\SalesPaymentController::getClientUnpaidInvoices().
     */
    private function payableInvoicesQuery(Client $client)
    {
        return Invoice::where('client_id', $client->id)
            ->whereRaw('COALESCE(wallet_paid_amount, 0) < COALESCE(grand_total, 0)')
            ->where('type', '!=', 'returned')
            ->whereDoesntHave('relatedDocuments', fn ($q) => $q->where('type', 'returned'))
            ->whereDoesntHave('creditNotifications', fn ($q) => $q->where('status', '!=', 0));
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }
}
