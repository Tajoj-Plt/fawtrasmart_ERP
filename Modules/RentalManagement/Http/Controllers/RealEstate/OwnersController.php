<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\OwnerEntry;
use Modules\RentalManagement\Services\OwnerLedgerService;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateMoney;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * الملّاك: بياناتهم واتفاقية إدارة أملاكهم (نسبة أو قيمة ثابتة) وكشف حسابهم
 * الجاري وتحويل مستحقاتهم. المالك كيانٌ مستقلّ عن ملفّ العملاء، وهو ما يُختار
 * في المباني والعقود.
 */
class OwnersController extends RealEstateController
{
    private const LOG = OwnerLedgerService::LOG;

    public function __construct(protected OwnerLedgerService $ledger)
    {
        $this->middleware('permission.role:realestate.owners.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.owners.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.owners.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:realestate.owners.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:realestate.owners.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.owners.transfer', ['only' => ['transfer', 'cancelTransfer']]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Owner::query())->withCount('buildings')->orderBy('name');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('mobile', 'like', $term)
                ->orWhere('phone', 'like', $term)->orWhere('id_number', 'like', $term)->orWhere('email', 'like', $term));
        });

        foreach (['status', 'agreement_type', 'owner_type'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $owners = $query->paginate($this->perPage($request));

        $balances = OwnerEntry::withoutGlobalScopes()->whereIn('owner_id', $owners->pluck('id'))->groupBy('owner_id')
            ->selectRaw("owner_id, COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance_total")
            ->toBase()->pluck('balance_total', 'owner_id');

        $data = ['owners' => $owners, 'balances' => $balances, 'canSeeMoney' => hasPermission('realestate.owners.show')];

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.owners.partials.table', $data)->render();
        }

        $all = $this->scoped(Owner::query());

        return view('rentalmanagement::realestate.owners.index', $data + [
            'stats' => [
                'total' => (clone $all)->count(),
                'active' => (clone $all)->where('status', 'active')->count(),
                'percentage' => (clone $all)->where('agreement_type', 'percentage')->count(),
                'fixed' => (clone $all)->where('agreement_type', 'fixed')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('rentalmanagement::realestate.owners.form', [
            'owner' => new Owner(['owner_type' => 'individual', 'agreement_type' => 'percentage', 'commission_basis' => 'collected', 'status' => 'active']),
            'code' => peek_document_number('re-owner'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $owner = new Owner($data);
        $owner->attachments = $this->storeFiles($request, 'attachments', 'owners');
        $owner->code = next_document_number('re-owner');
        $owner->save();

        // حسابه الجاري يُنشأ مع المالك فيكون جاهزاً لأوّل تحصيل
        $this->ledger->ensureAccount($owner);

        OperationLog::record('created', self::LOG, $owner, sprintf('أُضيف المالك **%s** (%s) — %s', $owner->name, $owner->code, $owner->agreement_label));

        return $this->ok($request, 'تمت إضافة المالك', route('realestate.owners.show', $owner->id));
    }

    public function show(Request $request, $id)
    {
        $owner = $this->findScoped(Owner::class, $id, ['account']);
        $canSeeMoney = hasPermission('realestate.owners.show');

        $from = $request->filled('from_date') ? $request->from_date : null;
        $to = $request->filled('to_date') ? $request->to_date : null;

        $base = OwnerEntry::withoutGlobalScopes()->where('owner_id', $owner->id);

        $opening = $from ? $this->signedSum((clone $base)->whereDate('entry_date', '<', $from)) : 0.0;

        $entries = (clone $base)->with(['building', 'unit', 'reversal'])
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderBy('entry_date')->orderBy('id')->get();

        $running = $opening;
        $entries->each(function (OwnerEntry $e) use (&$running) {
            $running = round($running + $e->signed_amount, 2);
            $e->setAttribute('running_balance', $running);
        });

        // صافي كل نوع بعد عكوسه: الحركة الملغاة وعكسها يخرجان معاً من الإجمالي
        $live = fn (string ...$types) => (float) (clone $base)->whereIn('type', $types)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('re_owner_entries as r')->whereColumn('r.reverses_entry_id', 're_owner_entries.id'))
            ->sum('amount');

        $totals = [
            'rent' => $live('rent'),
            'tax' => $live('tax'),
            'commission' => $live('commission'),
            'commission_tax' => $live('commission_tax'),
            'maintenance' => $live('maintenance'),
            'transfer' => $live('transfer'),
        ];

        return view('rentalmanagement::realestate.owners.show', [
            'owner' => $owner,
            'entries' => $entries,
            'opening' => $opening,
            'balance' => $this->ledger->balance($owner),
            'accrued' => $this->ledger->accruedNotCollected($owner),
            'totals' => $totals,
            'buildings' => $owner->buildings()->withCount('units')->orderBy('name_ar')->get(),
            'contracts' => $this->scoped(Contract::query())->ofOwner($owner->id)->with('tenant', 'unit')->latest('id')->limit(20)->get(),
            'treasuries' => hasPermission('realestate.owners.transfer') ? TreasuryAccessGuard::withdrawable()
                ->map(fn ($t) => (object) ['id' => (int) $t->account_id, 'name' => $t->name]) : collect(),
            'canSeeMoney' => $canSeeMoney,
            'filters' => ['from_date' => $from, 'to_date' => $to],
        ]);
    }

    public function edit($id)
    {
        return view('rentalmanagement::realestate.owners.form', [
            'owner' => $this->findScoped(Owner::class, $id),
            'code' => null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $owner = $this->findScoped(Owner::class, $id);
        $data = $this->validated($request);

        $changes = OperationLog::diff($owner->fill($data), array_keys($data));
        $owner->attachments = $this->storeFiles($request, 'attachments', 'owners', $owner->getOriginal('attachments'));
        $owner->save();

        OperationLog::record('updated', self::LOG, $owner, sprintf('عُدّل المالك **%s**', $owner->name), $changes);

        return $this->ok($request, 'تم تحديث بيانات المالك', route('realestate.owners.show', $owner->id));
    }

    public function destroy(Request $request, $id)
    {
        $owner = $this->findScoped(Owner::class, $id);

        if ($owner->buildings()->exists() || Contract::ofOwner($owner->id)->exists() || $owner->entries()->exists()) {
            $message = 'على هذا المالك عقاراتٌ أو عقودٌ أو حركاتٌ مالية؛ غيّر حالته إلى «غير نشط» بدل حذفه.';

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        OperationLog::record('deleted', self::LOG, $owner->id, sprintf('حُذف المالك **%s** (%s)', $owner->name, $owner->code));
        $owner->delete();

        return $this->ok($request, 'تم حذف المالك', route('realestate.owners.index'));
    }

    /** تحويل مستحقات المالك من خزينةٍ أو بنك — مدين حسابه الجاري، دائن الخزينة. */
    public function transfer(Request $request, $id)
    {
        $owner = $this->findScoped(Owner::class, $id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date|before_or_equal:today',
            'treasury_account_id' => 'required|integer|exists:accounts,id',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ], ['date.before_or_equal' => 'تاريخ التحويل لا يكون في المستقبل.'], [
            'amount' => 'المبلغ', 'date' => 'تاريخ التحويل', 'treasury_account_id' => 'الخزينة/الحساب البنكي',
        ]);

        // خزينةٌ أو بنك حصراً — لا أيّ حسابٍ في الدليل
        RealEstateAccounts::assertTreasury($data['treasury_account_id'], 'withdraw');

        $entry = $this->ledger->transfer($owner, $data);

        return $this->ok($request, 'حُوِّل للمالك مبلغ '.RealEstateMoney::format($entry->amount, $entry->currency).' بالمستند '.$entry->reference_no,
            route('realestate.owners.show', $owner->id));
    }

    /** إلغاء تحويلٍ للمالك: قيدٌ عكسي وحركةٌ مقابلة في كشفه. */
    public function cancelTransfer(Request $request, $id, $entryId)
    {
        $owner = $this->findScoped(Owner::class, $id);
        $request->validate(['reason' => 'required|string|max:1000'], ['reason.required' => 'اكتب سبب إلغاء التحويل.']);

        $entry = OwnerEntry::withoutGlobalScopes()->where('owner_id', $owner->id)->findOrFail($entryId);

        $this->ledger->cancelTransfer($owner, $entry, $request->reason);

        return $this->ok($request, 'أُلغي التحويل وعُكس قيده', route('realestate.owners.show', $owner->id));
    }

    private function signedSum($query): float
    {
        return round((float) $query->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as signed_total")
            ->toBase()->value('signed_total'), 2);
    }

    private function validated(Request $request): array
    {
        $percentage = $request->input('agreement_type') === 'percentage';

        // خانة اختيار: غيابها عن الطلب = غير مسجَّل
        $request->merge(['is_vat_registered' => $request->boolean('is_vat_registered')]);

        $data = $request->validate([
            'is_vat_registered' => 'boolean',
            'tax_number' => 'nullable|required_if:is_vat_registered,true|string|max:50',
            'name' => 'required|string|max:191',
            'owner_type' => 'required|'.V::in(V::OWNER_TYPES),
            'id_type' => 'nullable|'.V::in(V::ID_TYPES),
            'id_number' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:100',
            'marital_status' => 'nullable|'.V::in(V::MARITAL_STATUSES),
            'mobile' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s]{7,20}$/'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s]{7,20}$/'],
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:191',
            'iban' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9\s]{11,30}$/'],
            'agreement_type' => 'required|'.V::in(V::AGREEMENT_TYPES),
            'agreement_value' => 'required|numeric|min:0'.($percentage ? '|max:100' : ''),
            'commission_basis' => 'required|'.V::in(V::COMMISSION_BASES),
            'status' => 'required|'.V::in(V::OWNER_STATUSES),
            'notes' => 'nullable|string|max:3000',
        ], [
            'agreement_value.max' => 'النسبة لا تتجاوز 100%.',
            'tax_number.required_if' => 'اكتب الرقم الضريبي للمالك المسجَّل في ضريبة القيمة المضافة.',
            'mobile.regex' => 'رقم الجوال يقبل الأرقام والمسافات وعلامة + في أوّله فقط.',
            'phone.regex' => 'رقم الهاتف يقبل الأرقام والمسافات وعلامة + في أوّله فقط.',
            'iban.regex' => 'صيغة الآيبان غير صحيحة — يبدأ برمز الدولة ثم رقمين (مثل SA03…).',
        ], [
            'phone' => 'رقم الهاتف', 'iban' => 'الآيبان', 'email' => 'البريد الإلكتروني', 'id_number' => 'رقم الهوية/السجل',
            'tax_number' => 'الرقم الضريبي',
            'name' => 'اسم المالك', 'owner_type' => 'نوع المالك', 'mobile' => 'رقم الجوال', 'marital_status' => 'الحالة الاجتماعية',
            'agreement_type' => 'نوع الاتفاق', 'agreement_value' => 'قيمة الاتفاق', 'commission_basis' => 'أساس احتساب العمولة',
        ]);

        // الشركة لا حالة اجتماعية لها
        if (($data['owner_type'] ?? null) === 'company') {
            $data['marital_status'] = null;
        }

        // مالكٌ مكرَّر: الهوية/السجل نفسه، أو الجوال نفسه
        $id = (int) $request->route('id');
        $twin = Owner::query()->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->where(function ($q) use ($data) {
                $q->where('mobile', $data['mobile']);

                if (! empty($data['id_number'])) {
                    $q->orWhere('id_number', $data['id_number']);
                }
            })->first();

        if ($twin) {
            $field = ! empty($data['id_number']) && $twin->id_number === $data['id_number'] ? 'id_number' : 'mobile';

            throw ValidationException::withMessages([
                $field => sprintf('مالكٌ مسجَّل بال%s نفسه: %s (%s).', $field === 'mobile' ? 'جوال' : 'هوية/السجل', $twin->name, $twin->code),
            ]);
        }

        return $data;
    }
}
