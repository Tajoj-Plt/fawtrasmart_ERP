<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierWalletTransaction;
use App\Services\Wallets\SupplierWalletService;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use App\Support\TreasuryAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Support\HeaderPickers;
use Throwable;

/**
 * محافظ الموردين — نظير «محافظ العملاء» في وحدة العملاء.
 *
 * المحفظة هنا **أصل**: مالٌ لنا لدى المورد — دفعةٌ مقدَّمة سلّمناها ولم
 * نستهلكها بعد. وهو عكسُ محفظة العميل (التزام)، وهو الاتجاه الوحيد الذي
 * يستقيم به السداد: سدادُ فاتورة شراء يُنقص ما علينا للمورد وما لنا لديه
 * معاً. انظر توثيق `SupplierWalletService` للتفصيل.
 *
 * لا يكتب هذا المتحكّم قيداً بنفسه — يتحقّق ويفوّض ويسجّل.
 *
 * @see \App\Services\Wallets\SupplierWalletService محرّك المحفظة وقيودها
 * @see \Modules\Client\Http\Controllers\ClientWalletController النظير في دورة المبيعات
 */
class SupplierWalletController extends Controller
{
    use ScopesToEmployeeHierarchy;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'SupplierWallet';

    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:purchases.supplier_wallet.view', ['only' => ['index', 'show']]);

        /* حارسُ المسار «أيّ صلاحية حركة»، والنوعُ المرسَل يُفحَص بصلاحيته
           وحده في `assertMayPost()` — انظر النظير في `ClientWalletController`. */
        $this->middleware('permission:purchases.supplier_wallet.deposit|purchases.supplier_wallet.withdraw|purchases.supplier_wallet.pay_invoice',
            ['only' => ['create', 'store', 'edit', 'update']]);
    }

    /** صلاحية كل نوع حركة — الحارس الحقيقي، لا حارسُ المسار */
    private const TYPE_PERMISSIONS = [
        'deposit' => 'purchases.supplier_wallet.deposit',
        'withdraw' => 'purchases.supplier_wallet.withdraw',
        'invoice_paid' => 'purchases.supplier_wallet.pay_invoice',
        'refund' => 'purchases.supplier_wallet.pay_invoice',
    ];

    /** تسمية النوع في الشاشات */
    private const TYPE_LABELS = [
        'deposit' => 'دفعة مقدَّمة للمورد',
        'withdraw' => 'استرداد من المحفظة',
        'invoice_paid' => 'سداد فاتورة شراء من المحفظة',
        'refund' => 'ردّ سداد فاتورة إلى المحفظة',
    ];

    /**
     * قائمة محافظ الموردين.
     *
     * الموردون كلُّهم يظهرون — لا أصحابُ المحافظ وحدهم — فالمحفظة تُنشأ
     * عند أوّل دفعة، ومن لا محفظة له رصيدُه صفر.
     */
    public function index(Request $request)
    {
        /* لا عمود `name` في `suppliers` — الاسم `trade_name` وإلا الاسمان.
           والنطاقان معاً كما في `SupplierManagementController::index()`:
           محورُ الأشخاص (`scopeToCreator`) ثم فرعُ الشريط العلويّ وما
           يتبعه ({@see HeaderPickers}) — كانت هذه الشاشةُ بفرعٍ وحدَه،
           فيرى مندوبٌ في فرعه محافظَ زملائه كلِّهم. */
        $query = $this->scopeSuppliers(
            Supplier::query()->select('id', 'trade_name', 'first_name', 'last_name', 'phone', 'created_by', 'employee_id')
        );

        if (! $this->scopeIsPeopleAxis()) {
            HeaderPickers::suppliers($query);
        }

        // أرصدةُ المحفظة تُقاس على الموردين المشمولين بالنطاق أعلاه وحدَهم
        $wallets = Account::where('type_accont', 'supplier_wallet')
            ->whereIn('supplier_id', (clone $query)->pluck('id'))
            ->pluck('balance', 'supplier_id');

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';

            $query->where(function ($q) use ($term) {
                $q->where('trade_name', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($request->input('balance') === 'nonzero') {
            $query->whereIn('id', $wallets->filter(fn ($b) => (float) $b != 0)->keys());
        }

        $suppliers = $query->orderBy('trade_name')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return view('purchases::purchases.supplier_wallet.index', [
            'suppliers' => $suppliers,
            'wallets' => $wallets,
            'pageSizes' => self::PAGE_SIZES,
            'hasFilters' => $request->hasAny(['search', 'balance']),
            'totalBalance' => (float) $wallets->sum(),
            'walletsParentMapped' => AccountingSettings::isMapped('supplier_wallets_parent'),
        ]);
    }

    /** محفظة مورد واحد: الرصيد وكشفُ حركته وفواتيره غير المسدَّدة */
    public function show($supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);

        /* حمايةُ سجلٍّ واحد بعد جلبه — تقييدُ القائمة في `index()` وحده لا
           يمنع من يعرف رقمَ موردٍ خارج نطاقه من كتابته في العنوان. */
        $this->authorizeCreatorScope($supplier->created_by);

        $scopeDocuments = fn ($query) => $this->scopeToBranchNode($this->scopeToCreator($query));

        $transactions = $scopeDocuments(
            SupplierWalletTransaction::where('supplier_id', $supplier->id)
        )->with(['purchaseInvoice:id,code', 'treasury:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->paginate(25);

        $invoices = $scopeDocuments(
            PurchaseInvoice::where('supplier_id', $supplier->id)->where('type', 'invoice')
        )->whereRaw('COALESCE(wallet_paid_amount, 0) < COALESCE(grand_total, 0)')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'code', 'grand_total', 'wallet_paid_amount']);

        /* المرتجعات والإشعارات المدينة — للاطّلاع لا للسداد.
         *
         * لا تدخل حركات المحفظة: كلاهما يُنقص ما علينا للمورد، لا يُسدَّد
         * من مالنا لديه. لكنّ من يفتح المحفظة ليسوّي حساب المورد يحتاج
         * رؤيتهما معها — نظيرُ ما في «محفظة العميل».
         *
         * والنوعان نصّان في العمود نفسِه (`Return` و`City Notice`)، لا
         * جدولان: انظر `PurchaseDocumentType`. */
        $documents = $scopeDocuments(
            PurchaseInvoice::where('supplier_id', $supplier->id)->whereIn('type', ['Return', 'City Notice'])
        )
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'code', 'type', 'date', 'grand_total', 'created_at']);

        return view('purchases::purchases.supplier_wallet.show', [
            'supplier' => $supplier,
            'balance' => SupplierWalletService::balance($supplier),
            'transactions' => $transactions,
            'invoices' => $invoices,
            'returns' => $documents->where('type', 'Return')->values(),
            'debitNotes' => $documents->where('type', 'City Notice')->values(),
            'treasuries' => $this->treasuries(),
            'walletsParentMapped' => AccountingSettings::isMapped('supplier_wallets_parent'),
        ]);
    }

    /**
     * شاشة إضافة حركة — نظير `ClientWalletController::create()`.
     *
     * كانت الحركات الثلاث ثلاثةَ نماذج متجاورة في شاشة العرض، فصارت شاشةً
     * مستقلّة بنموذجٍ واحد يتبدّل بنوع الحركة.
     */
    public function create(Request $request, $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorizeCreatorScope($supplier->created_by);

        return view('purchases::purchases.supplier_wallet.create', $this->formData($supplier) + [
            'type' => $this->defaultType($request->input('type')),
        ]);
    }

    /** حفظ حركة جديدة */
    public function store(Request $request, $supplierId)
    {
        $validated = $this->validateMovement($request);

        return $this->run($supplierId, function (Supplier $supplier) use ($validated) {
            $this->assertMayPost($validated['type']);

            return match ($validated['type']) {
                'deposit' => $this->depositAction($supplier, $validated),
                'withdraw' => $this->withdrawAction($supplier, $validated),
                default => $this->invoiceAction($supplier, $validated),
            };
        }, self::TYPE_LABELS[$validated['type']] ?? 'حركة محفظة');
    }

    /** شاشة تعديل حركة */
    public function edit($supplierId, $transactionId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorizeCreatorScope($supplier->created_by);

        $transaction = SupplierWalletTransaction::where('supplier_id', $supplier->id)
            ->findOrFail($transactionId);

        return view('purchases::purchases.supplier_wallet.edit', $this->formData($supplier) + [
            'transaction' => $transaction,
            'type' => $transaction->type,
        ]);
    }

    /**
     * حفظ تعديل حركة — بكل حقولها.
     *
     * @see \App\Services\Wallets\SupplierWalletService::updateTransaction() ما يفعله الحفظ بالقيد
     */
    public function update(Request $request, $supplierId, $transactionId)
    {
        $validated = $this->validateMovement($request);

        return $this->run($supplierId, function (Supplier $supplier) use ($validated, $transactionId) {
            $this->assertMayPost($validated['type']);

            $transaction = SupplierWalletTransaction::where('supplier_id', $supplier->id)
                ->findOrFail($transactionId);

            $before = $transaction->typeLabel().' '.number_format((float) $transaction->amount, 2);

            // نفسُ فحص الخزينة الذي يحرس الإنشاء — وإلّا مرَّر التعديلُ
            // خزينةً لا يملكها المستخدم بلا فحص، والفحصُ في الإنشاء وحدَه
            // كان يُقفَز عند تعديل حركةٍ قائمة.
            if (! empty($validated['treasury_account_id']) && in_array($validated['type'], ['deposit', 'withdraw'], true)) {
                $this->assertTreasuryAllowed($validated['treasury_account_id'], leavesTreasury: $validated['type'] === 'deposit');
            }

            SupplierWalletService::updateTransaction($transaction, [
                'type' => $validated['type'],
                'amount' => (float) $validated['amount'],
                'treasury_account_id' => $validated['treasury_account_id'] ?? null,
                'purchase_invoice_id' => $validated['purchase_invoice_id'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return 'تم تعديل الحركة #'.$transactionId.' — كانت: '.$before.'.';
        }, 'تعديل حركة محفظة');
    }

    /**
     * دفعة مقدَّمة — تزيد أصلَ المحفظة وتُنقص نقدَ الخزينة، فهي سحبٌ من
     * منظور الخزينة (`leavesTreasury: true`).
     */
    private function depositAction(Supplier $supplier, array $validated): string
    {
        $treasury = $this->assertTreasuryAllowed($validated['treasury_account_id'], leavesTreasury: true);

        SupplierWalletService::deposit($supplier, (float) $validated['amount'], $treasury, $validated['note'] ?? null);

        return 'تم تسجيل دفعة مقدَّمة بمبلغ '.number_format((float) $validated['amount'], 2).' للمورد.';
    }

    /**
     * استرداد نقدي — يُنقص أصلَ المحفظة ويزيد نقدَ الخزينة، فهو إيداعٌ في
     * الخزينة (`leavesTreasury: false`).
     */
    private function withdrawAction(Supplier $supplier, array $validated): string
    {
        $treasury = $this->assertTreasuryAllowed($validated['treasury_account_id'], leavesTreasury: false);

        SupplierWalletService::withdraw($supplier, (float) $validated['amount'], $treasury, $validated['note'] ?? null);

        return 'تم استرداد '.number_format((float) $validated['amount'], 2).' من محفظة المورد.';
    }

    /**
     * سداد فاتورةٍ أو أكثر من المحفظة، أو ردُّ سدادٍ سابق إليها.
     *
     * «سداد عدّة فواتير» ليس نوع حركةٍ جديداً — هو المبلغ نفسه يُوزَّع على
     * الفواتير المختارة بالترتيب (الأقدم أولاً)، فتُنشأ حركةٌ (وقيدٌ) لكل
     * فاتورةٍ أخذت حصّتها — نظير `ClientWalletController::invoiceAction()`.
     * و`payInvoice()`/`post()` تفحصان كفاية المحفظة عند كل استدعاء، فلا
     * فاتورةٌ تُخصَم فوق طاقتها.
     */
    private function invoiceAction(Supplier $supplier, array $validated): string
    {
        $invoiceIds = $this->resolveInvoiceIds($validated);

        $invoices = PurchaseInvoice::whereIn('id', $invoiceIds)
            ->where('supplier_id', $supplier->id)
            ->orderBy('id')
            ->get();

        // فاتورةٌ من نموذجٍ آخر لا تخصّ هذا المورد — ولا تُقبل صامتةً
        if ($invoices->count() !== count($invoiceIds)) {
            throw new \Exception('إحدى الفواتير المختارة لا تخصّ هذا المورد.');
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
                : SupplierWalletService::payableFromWallet($invoice);

            $portion = round(min($remaining, $cap), 2);

            if ($portion <= 0) {
                continue;
            }

            if ($isRefund) {
                SupplierWalletService::refundInvoice($invoice, $portion, $validated['note'] ?? null);
            } else {
                SupplierWalletService::payInvoice($invoice, $portion, $validated['note'] ?? null);
            }

            $remaining = round($remaining - $portion, 2);
            $applied += $portion;
            $paidCodes[] = $invoice->code;
        }

        if (! $paidCodes) {
            throw new \Exception('لا فاتورة قابلة لهذه الحركة ضمن الفواتير المختارة.');
        }

        /* مبلغٌ فاض عن الفواتير المختارة كلِّها لا يُبتلَع صامتاً — وإلا
           ظنّ المستخدم أنّ ما كتبه هو ما سُدِّد. */
        if ($remaining > 0.004) {
            throw new \Exception('المبلغ ('.number_format((float) $validated['amount'], 2).') أكبر مما تقبله الفواتير المختارة ('
                .number_format($applied, 2).') — أنقص المبلغ أو أضف فواتير.');
        }

        $verb = $isRefund ? 'تم ردّ' : 'تم سداد';
        $target = count($paidCodes) === 1
            ? 'فاتورة '.$paidCodes[0]
            : count($paidCodes).' فواتير: '.implode('، ', $paidCodes);

        return $verb.' '.number_format($applied, 2).' '.($isRefund ? 'إلى المحفظة من' : 'من المحفظة على').' '.$target.'.';
    }

    /** معرّفات الفواتير المختارة — من الحقل المفرد (تعديل) أو المتعدّد (إضافة) */
    private function resolveInvoiceIds(array $validated): array
    {
        $ids = $validated['purchase_invoice_ids'] ?? [];

        if (! empty($validated['purchase_invoice_id'])) {
            $ids[] = $validated['purchase_invoice_id'];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /** صلاحية النوع المرسَل — الحارس الحقيقي، لا حارسُ المسار */
    private function assertMayPost(string $type): void
    {
        $permission = self::TYPE_PERMISSIONS[$type] ?? null;

        if (! $permission || ! hasPermission($permission)) {
            throw new \Exception('لا تملك صلاحية هذا النوع من الحركات.');
        }
    }

    /** النوع الافتراضي في نموذج الإضافة — مُرسَلٌ صالح أو الدفعة المقدَّمة */
    private function defaultType(?string $requested): string
    {
        return in_array($requested, SupplierWalletService::TYPES, true) ? $requested : 'deposit';
    }

    /** ما تحتاجه شاشتا الإضافة والتعديل معاً */
    private function formData(Supplier $supplier): array
    {
        return [
            'supplier' => $supplier,
            'balance' => SupplierWalletService::balance($supplier),
            'treasuries' => $this->treasuries(),
            'invoices' => PurchaseInvoice::where('supplier_id', $supplier->id)
                ->where('type', 'invoice')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'code', 'grand_total', 'due_value', 'wallet_paid_amount']),
            'types' => self::TYPE_LABELS,
            'allowedTypes' => array_keys(array_filter(
                self::TYPE_PERMISSIONS,
                fn (string $permission) => hasPermission($permission)
            )),
            'treasuryTypes' => SupplierWalletService::TREASURY_TYPES,
            'invoiceTypes' => SupplierWalletService::INVOICE_TYPES,
            'walletsParentMapped' => AccountingSettings::isMapped('supplier_wallets_parent'),
        ];
    }

    /** الغلاف المشترك: فترةٌ محاسبية، ومعاملة، وسجلّ نشاط، ورسالة */
    private function run($supplierId, callable $action, string $logVerb)
    {
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return back()->withInput()->with('error', $blocked);
        }

        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($supplierId);
            $this->authorizeCreatorScope($supplier->created_by);
            $before = SupplierWalletService::balance($supplier);

            $message = $action($supplier);

            $after = SupplierWalletService::balance($supplier);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $supplier,
                sprintf('%s — %s: من %s إلى %s', $logVerb, $supplier->trade_name,
                    number_format($before, 2), number_format($after, 2)),
                ['old' => ['balance' => $before], 'new' => ['balance' => $after]]
            );

            DB::commit();

            return redirect()->route('purchases.supplier-wallet.show', $supplierId)->with('success', $message);
        } catch (Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'wallet_movement', (int) $supplierId);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * تحقّقٌ واحد للأنواع كلِّها — الخزينة للنقدية والفاتورة للسداد والردّ.
     */
    private function validateMovement(Request $request): array
    {
        $treasuryTypes = implode(',', SupplierWalletService::TREASURY_TYPES);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', SupplierWalletService::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'treasury_account_id' => ['nullable', 'required_if:type,'.$treasuryTypes, 'integer', 'exists:accounts,id'],
            'purchase_invoice_id' => ['nullable', 'integer', 'exists:purchase_invoices,id'],
            'purchase_invoice_ids' => ['nullable', 'array'],
            'purchase_invoice_ids.*' => ['integer', 'exists:purchase_invoices,id'],
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
        if (in_array($validated['type'], SupplierWalletService::INVOICE_TYPES, true)
            && ! $this->resolveInvoiceIds($validated)) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => 'اختر فاتورة واحدة على الأقل.',
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
        /* خزائنُ المستخدم وحدَها — كانت `TreasuryAccounts::cash()` عاريةً
           فتسرد خزائنَ المنشأة كلَّها، فيودع الموظّفُ في خزينةٍ لا يملكها.
           والحارسُ هو نفسُه الذي تستعمله المبيعات
           ({@see \Modules\Sales\Http\Controllers\SalesPaymentController::depositableTreasuryAccounts()}). */
        return TreasuryAccounts::cash()
            ->filter(fn (Account $account) => TreasuryAccessGuard::ownDepositBlockReason($account->id) === null
                || TreasuryAccessGuard::ownWithdrawBlockReason($account->id) === null)
            ->values();
    }

    /**
     * يتحقّق أن الخزينة المرسَلة من خزائن المستخدم — قبل كتابة أيّ قيد.
     *
     * إخفاءُ الخيار من القائمة لا يمنع إرسالَ معرّفٍ غيره في متن الطلب،
     * و`exists:accounts,id` في التحقّق تقبل أيَّ حساب. فالفحصُ يُعاد هنا
     * عند الحفظ — نفسُ ما تفعله المبيعات في جانب الحفظ.
     *
     * **واتّجاهُ المال معكوسٌ عن اسم الحركة**: «دفعة مقدَّمة» تُخرج مالاً من
     * الخزينة (تحتاج صلاحية سحب)، و«استرداد» يُدخله إليها (تحتاج إيداع).
     * والقائمةُ أعلاه تعرض ما للمستخدم فيه أيُّ الاتّجاهين، وهنا يُفحَص
     * الاتّجاهُ المطلوب بعينه.
     *
     * @param  bool  $leavesTreasury  هل تُخرج الحركةُ مالاً من الخزينة؟
     */
    private function assertTreasuryAllowed($accountId, bool $leavesTreasury): Account
    {
        $account = Account::findOrFail($accountId);

        $reason = $leavesTreasury
            ? TreasuryAccessGuard::ownWithdrawBlockReason($account->id)
            : TreasuryAccessGuard::ownDepositBlockReason($account->id);

        if ($reason !== null) {
            throw ValidationException::withMessages(['treasury_account_id' => $reason]);
        }

        return $account;
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->input('per_page');

        return in_array($requested, self::PAGE_SIZES, true) ? $requested : self::PAGE_SIZES[1];
    }
}
