<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Log as ModelsLog;
use App\Models\PaymentMethod;
use App\Support\DocumentPrintPage;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\DepositSettlement;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Services\DepositService;
use Modules\RentalManagement\Services\PaymentService;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;

class PaymentsController extends RealEstateController
{
    /** العقود التي تُحصَّل عليها دفعات: المعتمدة ولو انتهت أو أُنهيت (متأخرات، صيانة على المستأجر). */
    private const COLLECTIBLE = ['active', 'renewing', 'expired', 'terminated'];

    public function __construct(protected PaymentService $payments)
    {
        $this->middleware('permission.role:realestate.payments.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.payments.schedules', ['only' => ['schedules']]);
        $this->middleware('permission.role:realestate.payments.show', ['only' => ['show', 'receipt']]);
        $this->middleware('permission.role:realestate.payments.create', ['only' => ['create', 'store', 'contractSchedules', 'settleDeposit']]);
        $this->middleware('permission.role:realestate.payments.cancel', ['only' => ['cancel']]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Payment::query())->with(['client', 'contract.unit.building', 'treasuryAccount', 'paymentMethod'])->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('reference_no', 'like', $term)
                ->orWhereHas('contract', fn ($c) => $c->where('code', 'like', $term)));
        });

        foreach (['posting_status', 'payment_method', 'payment_method_id', 'client_id', 'contract_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $query->when($request->filled('from_date'), fn ($q) => $q->whereDate('payment_date', '>=', $request->from_date));
        $query->when($request->filled('to_date'), fn ($q) => $q->whereDate('payment_date', '<=', $request->to_date));

        $payments = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.payments.partials.table', compact('payments'))->render();
        }

        // «المرحَّل» ما له قيد فقط: غير المرحّلة والملغاة خارج الإحصاء
        $posted = $this->scoped(Payment::query())->where('posting_status', 'posted');
        $schedules = PaymentSchedule::whereIn('contract_id', $this->scoped(Contract::query())->select('id'));

        return view('rentalmanagement::realestate.payments.index', [
            'payments' => $payments,
            'clients' => $this->clients(),
            'methods' => $this->paymentMethods(),
            'stats' => [
                'month' => (float) (clone $posted)->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'total' => (float) (clone $posted)->sum('amount'),
                'unposted' => (int) $this->scoped(Payment::query())->where('posting_status', 'unposted')->count(),
                'due_month' => (float) (clone $schedules)->open()->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) as t')->value('t'),
                'overdue' => (float) (clone $schedules)->overdue()->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) as t')->value('t'),
            ],
        ]);
    }

    /** جدول الاستحقاقات عبر العقود: المستحق والمتأخر والقادم. */
    public function schedules(Request $request)
    {
        $query = PaymentSchedule::query()
            ->whereIn('contract_id', $this->scoped(Contract::query())->select('id'))
            ->with(['contract.tenant', 'contract.unit.building'])
            ->orderBy('due_date');

        $bucket = $request->input('bucket', 'open');

        match ($bucket) {
            'overdue' => $query->overdue(),
            'due' => $query->open()->whereDate('due_date', '<=', now()->toDateString()),
            'upcoming' => $query->open()->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()]),
            'paid' => $query->where('status', 'paid'),
            'all' => $query,
            default => $query->open(),
        };

        $query->when($request->filled('building_id'), fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('building_id', $request->building_id)));
        $query->when($request->filled('client_id'), fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('tenant_client_id', $request->client_id)));
        $query->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind));
        $query->when($request->filled('from_date'), fn ($q) => $q->whereDate('due_date', '>=', $request->from_date));
        $query->when($request->filled('to_date'), fn ($q) => $q->whereDate('due_date', '<=', $request->to_date));

        $schedules = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.payments.partials.schedules-table', compact('schedules'))->render();
        }

        return view('rentalmanagement::realestate.payments.schedules', [
            'schedules' => $schedules,
            'bucket' => $bucket,
            'buildings' => $this->buildings(),
            'clients' => $this->clients(),
        ]);
    }

    public function create(Request $request)
    {
        $contract = $request->filled('contract_id')
            ? $this->findScoped(Contract::class, $request->contract_id, ['tenant', 'unit.building'])
            : null;

        return view('rentalmanagement::realestate.payments.create', [
            'contract' => $contract,
            'buildings' => $this->buildings(),
            'contracts' => $this->scoped(Contract::query())->whereIn('status', self::COLLECTIBLE)
                ->whereHas('schedules', fn ($q) => $q->open())->with('tenant', 'unit.building')->latest('id')->get(),
            'open' => $contract ? $contract->schedules()->open()->get() : collect(),
            'treasuries' => $this->treasuryAccounts(),
            'methods' => $this->paymentMethods(),
            'code' => peek_document_number('re-payment'),
            'preselected' => (int) $request->input('schedule_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:re_contracts,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            // طريقة الدفع من طرق النظام؛ النوع القديم (`payment_method`) يبقى مقبولاً من المسارات السابقة
            'payment_method_id' => 'required_without:payment_method|nullable|integer|exists:payment_methods,id',
            'payment_method' => 'nullable|in:'.implode(',', array_keys(Payment::METHOD_KINDS)),
            // الشيك لا خزينة له عند الاستلام؛ الخدمة تُلزم الخزينة لما سواه
            'treasury_account_id' => 'nullable|integer|exists:accounts,id',
            'reference_no' => 'nullable|string|max:100',
            'cheque_due_date' => 'nullable|date',
            'cheque_bank' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:3000',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|numeric|min:0',
        ], [
            'payment_date.before_or_equal' => 'تاريخ الدفعة لا يكون في المستقبل.',
            'payment_method_id.required_without' => 'اختر طريقة الدفع.',
        ], [
            'contract_id' => 'العقد', 'payment_date' => 'تاريخ الدفع', 'amount' => 'المبلغ',
            'payment_method_id' => 'طريقة الدفع', 'payment_method' => 'طريقة الدفع',
            'treasury_account_id' => 'الخزينة/الحساب البنكي', 'reference_no' => 'رقم المرجع',
            'cheque_due_date' => 'تاريخ استحقاق الشيك', 'cheque_bank' => 'بنك الشيك',
        ]);

        $contract = $this->findScoped(Contract::class, $data['contract_id']);
        abort_if(in_array($contract->status, ['draft', 'pending_approval', 'cancelled'], true), 422, 'لا تُسجَّل دفعة على عقدٍ غير معتمد أو ملغى.');

        // خزينةٌ أو بنك حصراً، ومسموحٌ لي بالإيداع فيه — قبل رفع المرفقات
        $method = ! empty($data['payment_method_id']) ? PaymentMethod::find($data['payment_method_id']) : null;
        $isCheque = ($method ? Payment::methodKind($method) : ($data['payment_method'] ?? null)) === 'cheque';

        // الشيك يُسجَّل في «الشيكات المستلمة» ولا خزينة له عند الاستلام
        if (! $isCheque) {
            RealEstateAccounts::assertTreasury($data['treasury_account_id'] ?? null, 'deposit');
        }

        $data['attachments'] = $this->storeFiles($request, 'attachments', 'payments');

        $payment = $this->payments->record($contract, $data, $request->input('allocations', []) ?? []);

        $message = 'تم تسجيل الدفعة '.$payment->code
            .($payment->posting_status === 'unposted' ? ' (غير مرحّلة — الترحيل المحاسبي معطَّل)' : '')
            .($payment->posting_status === 'cheque_pending' ? ' — الشيك سُجّل في «الشيكات المستلمة»، وتُرحَّل الدفعة عند تحصيله' : '')
            .($this->payments->skippedOlder ? ' — تنبيه: بقيت أقساطٌ أقدم استحقاقاً مفتوحة' : '');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true, 'message' => $message, 'redirect' => route('realestate.payments.show', $payment->id),
                'warning' => $this->payments->skippedOlder ? 'older_installments_open' : null,
            ]);
        }

        return redirect()->route('realestate.payments.show', $payment->id)->with('success', $message);
    }

    public function show($id)
    {
        $payment = $this->findScoped(Payment::class, $id, [
            'client', 'contract.owner.account', 'contract.unit.building.owner.account', 'treasuryAccount', 'paymentMethod',
            'allocations.schedule', 'journalEntry.details.account', 'creator',
        ]);

        $contract = $payment->contract;
        $canSettle = $contract && hasPermission('realestate.payments.create');

        return view('rentalmanagement::realestate.payments.show', [
            'payment' => $payment,
            'parts' => $this->payments->breakdown($payment),
            'managedOwner' => $contract ? RealEstateAccounts::managedOwner($contract) : null,
            'depositHeld' => $contract ? app(DepositService::class)->held($contract) : 0.0,
            'settlements' => $contract
                ? DepositSettlement::withoutGlobalScopes()->where('contract_id', $contract->id)->with('journalEntry.details.account', 'treasuryAccount')->latest('id')->get()
                : collect(),
            'withdrawable' => $canSettle ? TreasuryAccessGuard::withdrawable()
                ->map(fn ($t) => (object) ['id' => (int) $t->account_id, 'name' => $t->name]) : collect(),
            'canSettle' => $canSettle,
            'logs' => hasPermission('realestate.logs.view')
                ? ModelsLog::where('type', PaymentService::LOG)->where('type_id', $payment->id)->latest()->get()
                : collect(),
        ]);
    }

    /**
     * الإيصال: قالب المستندات المضبوط في «قوالب المستندات» إن وُجد، وإلا
     * الإيصال المبنيّ هنا (أو قسراً بـ`?builtin=1`).
     */
    public function receipt(Request $request, $id)
    {
        $payment = $this->findScoped(Payment::class, $id, ['client', 'contract.unit.building', 'contract.owner', 'allocations.schedule', 'creator', 'paymentMethod']);

        $templates = '\\Modules\\RentalManagement\\Support\\RealEstateTemplates';

        if (! $request->boolean('builtin') && class_exists($templates)) {
            try {
                $html = $templates::render('realestate_receipt', (int) $payment->id, $request->input('template'));
            } catch (\Throwable $e) {
                report($e);
                $html = null;
            }

            if ($html !== null) {
                return response(DocumentPrintPage::wrap($html, 'إيصال '.$payment->code, route('realestate.payments.show', $payment->id)));
            }
        }

        return view('rentalmanagement::realestate.payments.receipt', [
            'payment' => $payment,
            'parts' => $this->payments->breakdown($payment),
            'managedOwner' => $payment->contract ? RealEstateAccounts::managedOwner($payment->contract) : null,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $payment = $this->findScoped(Payment::class, $id);
        $request->validate(['reason' => 'required|string|max:1000'], ['reason.required' => 'اكتب سبب الإلغاء.']);
        abort_if($payment->posting_status === 'cancelled', 422, 'الدفعة ملغاة سابقاً.');

        $this->payments->cancel($payment, $request->reason);

        return $this->ok($request, $payment->reversal_journal_entry_id ? 'أُلغيت الدفعة وعُكس قيدها' : 'أُلغيت الدفعة');
    }

    /** تسوية تأمين العقد: خصمٌ (للمالك أو للمنشأة) وردّ الباقي للمستأجر، بقيدٍ واحد. */
    public function settleDeposit(Request $request, DepositService $deposits, $contractId)
    {
        $contract = $this->findScoped(Contract::class, $contractId, ['unit.building.owner', 'owner']);

        $data = $request->validate([
            'deducted_amount' => 'nullable|numeric|min:0',
            'deduct_to' => 'nullable|in:owner,office',
            'treasury_account_id' => 'nullable|integer|exists:accounts,id',
            'reason' => 'nullable|string|max:1000',
            'settled_at' => 'nullable|date|before_or_equal:today',
        ], ['settled_at.before_or_equal' => 'تاريخ التسوية لا يكون في المستقبل.'], [
            'deducted_amount' => 'المخصوم', 'treasury_account_id' => 'الخزينة/الحساب البنكي', 'reason' => 'سبب الخصم', 'settled_at' => 'تاريخ التسوية',
        ]);

        $settlement = $deposits->settle(
            $contract, (float) ($data['deducted_amount'] ?? 0),
            ! empty($data['treasury_account_id']) ? (int) $data['treasury_account_id'] : null,
            $data['reason'] ?? null, $data['deduct_to'] ?? 'owner', $data['settled_at'] ?? null,
        );

        if (! $settlement) {
            throw ValidationException::withMessages(['deposit' => 'لا تأمين محتجَز على هذا العقد لتسويته.']);
        }

        return $this->ok($request, sprintf('سُوّي التأمين بالمستند %s: مردود %s، مخصوم %s', $settlement->code,
            RealEstateMoney::format($settlement->refunded_amount, $settlement->currency),
            RealEstateMoney::format($settlement->deducted_amount, $settlement->currency)));
    }

    /** استحقاقات عقدٍ المفتوحة — لنموذج تسجيل الدفعة. */
    public function contractSchedules($contractId)
    {
        $contract = $this->findScoped(Contract::class, $contractId, ['tenant', 'unit.building']);
        $currency = $contract->currency ?: RealEstateMoney::code();

        return response()->json([
            'contract' => [
                'code' => $contract->code,
                'tenant' => $contract->tenant->display_name ?? '—',
                'unit' => $contract->unit->full_label ?? '—',
                'outstanding' => $contract->outstanding(),
                'start_date' => $contract->start_date?->format('Y-m-d'),
                'currency' => $currency,
                'currency_symbol' => RealEstateMoney::symbol($currency),
            ],
            'schedules' => $contract->schedules()->open()->get()->map(fn ($s) => [
                'id' => $s->id,
                'no' => $s->installment_no,
                'kind' => $s->kind_label,
                'due_date' => $s->due_date->format('Y-m-d'),
                'amount_due' => (float) $s->amount_due,
                'balance' => $s->balance,
                'status' => $s->status_meta,
            ]),
        ]);
    }

    /**
     * الخزائن المسموح لي بالإيداع فيها — `value` هو `accounts.id` كما في
     * سندات القبض، والقيد نفسه يسري هنا: لا أودِع في خزينةٍ مُنعت عنّي.
     *
     * @return Collection<int, object{id:int, name:string}>
     */
    private function treasuryAccounts()
    {
        return TreasuryAccessGuard::depositable()
            ->map(fn ($treasury) => (object) ['id' => (int) $treasury->account_id, 'name' => $treasury->name]);
    }

    /**
     * طرق الدفع المفعّلة في النظام مع نوعها المشتقّ (لشرط رقم المرجع).
     *
     * @return Collection<int, object{id:int, name:string, kind:string, is_default:bool}>
     */
    private function paymentMethods()
    {
        return PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => (object) [
                'id' => (int) $method->id, 'name' => $method->name,
                'kind' => Payment::methodKind($method), 'is_default' => (bool) $method->is_default,
            ]);
    }
}
