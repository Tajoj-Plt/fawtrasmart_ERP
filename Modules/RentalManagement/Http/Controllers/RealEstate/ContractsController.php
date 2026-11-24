<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Account;
use App\Models\Client;
use App\Models\Log as ModelsLog;
use App\Models\TaxSitting;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\ContractRenewal;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\TenantProfile;
use Modules\RentalManagement\Models\TermTemplate;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\ContractService;
use Modules\RentalManagement\Support\RealEstateSettings;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class ContractsController extends RealEstateController
{
    /** تنبيهاتٌ لا تمنع الحفظ (مالك مختلف، مستأجر تجاري بلا رقم ضريبي…) — تُعرض بعده. */
    private array $warnings = [];

    public function __construct(protected ContractService $contracts)
    {
        $this->middleware('permission.role:realestate.contracts.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.contracts.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.contracts.print', ['only' => ['print']]);
        $this->middleware('permission.role:realestate.contracts.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:realestate.contracts.edit', ['only' => ['edit', 'update', 'submit']]);
        $this->middleware('permission.role:realestate.contracts.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.contracts.approve', ['only' => ['approve', 'reject']]);
        $this->middleware('permission.role:realestate.contracts.cancel', ['only' => ['cancel']]);
        $this->middleware('permission.role:realestate.contracts.terminate', ['only' => ['terminate']]);
        $this->middleware('permission.role:realestate.contracts.amend', ['only' => ['amend']]);
        $this->middleware('permission.role:realestate.contracts.renew', ['only' => ['renewForm', 'storeRenewal']]);
        $this->middleware('permission.role:realestate.contracts.approve_renewal', ['only' => ['approveRenewal', 'rejectRenewal']]);
        $this->middleware('permission.role:realestate.payments.regenerate_schedule', ['only' => ['regenerateSchedule']]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Contract::query())->with(['tenant', 'unit.building', 'renewedInto'])->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)
                ->orWhereHas('tenant', fn ($c) => $c->where('trade_name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term))
                ->orWhereHas('unit', fn ($u) => $u->where('unit_no', 'like', $term)));
        });

        foreach (['status', 'contract_type', 'building_id', 'unit_id', 'tenant_client_id', 'payment_frequency'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $query->when($request->filled('from_date'), fn ($q) => $q->whereDate('start_date', '>=', $request->from_date));
        $query->when($request->filled('to_date'), fn ($q) => $q->whereDate('end_date', '<=', $request->to_date));
        $query->when($request->filled('expiring_in'), fn ($q) => $q->whereIn('status', ['active', 'renewing'])
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays((int) $request->expiring_in)->toDateString()]));

        $contracts = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.contracts.partials.table', compact('contracts'))->render();
        }

        $counts = $this->scoped(Contract::query())->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('rentalmanagement::realestate.contracts.index', [
            'contracts' => $contracts,
            'counts' => $counts,
            'total' => $counts->sum(),
            'buildings' => $this->buildings(),
            'clients' => $this->clients(),
        ]);
    }

    public function create(Request $request)
    {
        $listing = $request->filled('listing_id') ? $this->findScoped(Listing::class, $request->listing_id) : null;

        // الطلبات لا تُنتج عقوداً — العروض فقط
        abort_if($listing && $listing->listing_kind !== 'offer', 404);

        $unitId = $request->input('unit_id', $listing?->unit_id);
        $unit = $unitId ? $this->findScoped(Unit::class, $unitId, ['building.owner']) : null;

        // نوع العقد والضريبة من استخدام الوحدة — لا من افتراضٍ ثابت
        $policy = $unit ? $this->contracts->taxPolicy($unit) : null;
        $tax = $policy && $policy['taxable'] ? $this->defaultTax() : null;

        $contract = new Contract([
            'contract_type' => $unit ? $this->contracts->contractTypeFor($unit) : 'residential_lease',
            'unit_id' => $unit?->id,
            'listing_id' => $listing?->id,
            'owner_id' => $unit?->building?->owner_id,
            'tenant_client_id' => $request->input('tenant_client_id', $request->input('client_id')),
            'start_date' => now(),
            'end_date' => now()->addYear()->subDay(),
            'duration_months' => 12,
            'annual_value' => $listing ? ($listing->rent_period === 'monthly' ? $listing->asking_price * 12 : $listing->asking_price)
                : ($unit ? ($unit->rent_period === 'monthly' ? $unit->base_rent * 12 : $unit->base_rent) : 0),
            'payment_frequency' => RealEstateSettings::get('default_payment_frequency', 'monthly'),
            'tax_id' => $tax?->id,
            'tax_rate' => $tax ? (float) $tax->tax : 0,
            'tax_included' => $tax ? $tax->type === 'included' : false,
            'currency' => CurrencySettings::code(),
        ]);

        return view('rentalmanagement::realestate.contracts.form', $this->formData($contract, $unit?->building?->owner_id) + [
            'code' => peek_document_number('re-contract'),
            'terms' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->contracts->assertNoOverlap($data['unit_id'], $data['start_date'], $data['end_date'], null, $this->overlapConfirmed($request));

        try {
            $contract = DB::transaction(function () use ($request, $data) {
                $contract = new Contract($data);
                $contract->code = next_document_number('re-contract');
                $contract->status = 'draft';
                // لقطة العملة وقت الإنشاء — لا تتغيّر بتغيّر عملة النظام لاحقاً
                $contract->currency = CurrencySettings::code();
                $contract->attachments = $this->storeFiles($request, 'attachments', 'contracts');
                $contract->save();

                $this->syncTerms($contract, $request);
                $this->contracts->generateSchedule($contract);

                return $contract;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            OperationLog::failure($e, ContractService::LOG, 'store');

            throw $e;
        }

        OperationLog::record('created', ContractService::LOG, $contract, sprintf('أُنشئ العقد **%s** مسودةً', $contract->code));

        // «حفظ وإرسال»: يمضي في مساره بحسب سياسة الاعتماد
        if ($request->input('action') === 'submit') {
            $this->advance($contract, $this->overlapConfirmed($request));
        }

        return $this->okWithWarnings($request, 'تم حفظ العقد', route('realestate.contracts.show', $contract->id));
    }

    public function show($id)
    {
        $contract = $this->findScoped(Contract::class, $id, [
            'tenant.account', 'owner.account', 'tax', 'unit.building.owner', 'unit.floor', 'listing', 'previous', 'renewedInto',
            'terms', 'amendments.user', 'renewals.renewedContract', 'renewals.creator', 'schedules', 'payments', 'approver', 'creator',
        ]);

        $rent = $contract->schedules->where('kind', 'rent')->where('status', '!=', 'cancelled');
        $ownership = $contract->unit ? $this->contracts->ownership($contract->unit) : 'managed';

        return view('rentalmanagement::realestate.contracts.show', [
            'contract' => $contract,
            'totals' => [
                'due' => (float) $rent->sum('amount_due'),
                'paid' => (float) $rent->sum('amount_paid'),
                'balance' => (float) $rent->sum('amount_due') - (float) $rent->sum('amount_paid'),
                'overdue' => (float) $rent->where('status', 'overdue')->sum(fn ($s) => $s->balance),
                'deposit_paid' => (float) $contract->schedules->where('kind', 'deposit')->sum('amount_paid'),
                'deposit_open' => (float) $contract->schedules->where('kind', 'deposit')->where('status', '!=', 'cancelled')->sum(fn ($s) => $s->balance),
                'deposit_held' => $contract->heldDeposit(),
            ],
            'ownership' => $ownership,
            'postingAccount' => $this->postingAccount($contract, $ownership),
            'tenantProfile' => TenantProfile::withoutGlobalScopes()->where('client_id', $contract->tenant_client_id)->first(),
            'ownerMismatch' => $contract->owner_id && $contract->unit?->building?->owner_id
                && (int) $contract->owner_id !== (int) $contract->unit->building->owner_id,
            'maintenance' => hasPermission('realestate.maintenance.view')
                ? MaintenanceRequest::where('contract_id', $contract->id)->latest('id')->limit(100)->get()
                : null,
            'treasuries' => in_array($contract->status, ['active', 'renewing'], true) && hasPermission('realestate.contracts.terminate')
                ? TreasuryAccessGuard::withdrawable()
                : collect(),
            'logs' => hasPermission('realestate.logs.view')
                ? ModelsLog::where('type', ContractService::LOG)->where('type_id', $contract->id)->latest()->limit(50)->get()
                : collect(),
        ]);
    }

    public function print(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id, ['tenant', 'owner', 'tax', 'unit.building.owner', 'unit.floor', 'terms', 'schedules', 'approver']);

        // قالب «قوالب المستندات» أولاً إن وُجد — و`?builtin=1` يفرض القالب المدمج
        $templates = '\\Modules\\RentalManagement\\Support\\RealEstateTemplates';

        if (! $request->boolean('builtin') && class_exists($templates) && method_exists($templates, 'render')) {
            try {
                $html = $templates::render('realestate_contract', (int) $contract->id, $request->input('template'));
            } catch (\Throwable $e) {
                OperationLog::failure($e, ContractService::LOG, 'print', $contract);
                $html = null;
            }

            if (is_string($html) && $html !== '') {
                return response(DocumentPrintPage::wrap($html, 'عقد '.$contract->code, route('realestate.contracts.show', $contract->id)));
            }
        }

        return view('rentalmanagement::realestate.contracts.print', [
            'contract' => $contract,
            'tenantProfile' => TenantProfile::withoutGlobalScopes()->where('client_id', $contract->tenant_client_id)->first(),
            'lessor' => $contract->effectiveOwner(),
            'ownership' => $contract->unit ? $this->contracts->ownership($contract->unit) : 'managed',
        ]);
    }

    public function edit($id)
    {
        $contract = $this->findScoped(Contract::class, $id, ['terms']);

        if (! $contract->isEditable()) {
            return redirect()->route('realestate.contracts.show', $contract->id)
                ->with('error', 'العقد المعتمد لا يُعدَّل من هنا — استعمل «تعديل عقد معتمد» ليُسجَّل التغيير.');
        }

        return view('rentalmanagement::realestate.contracts.form', $this->formData($contract, $contract->owner_id) + [
            'code' => null,
            'terms' => $contract->terms,
        ]);
    }

    public function update(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless($contract->isEditable(), 422, 'العقد المعتمد لا يُعدَّل مباشرةً.');

        $data = $this->validated($request, $contract);
        $this->contracts->assertNoOverlap($data['unit_id'], $data['start_date'], $data['end_date'], $contract->id, $this->overlapConfirmed($request));

        // تعديل عقدٍ «بانتظار الاعتماد» يعيده مسودة: المعتمِد لا يعتمد محتوىً تغيّر بعد إرساله
        $wasPending = $contract->status === 'pending_approval';

        DB::transaction(function () use ($request, $contract, $data, $wasPending) {
            $contract->fill($data);

            if ($wasPending) {
                $contract->status = 'draft';
            }

            $contract->attachments = $this->storeFiles($request, 'attachments', 'contracts', $contract->getOriginal('attachments'));
            $contract->save();

            $this->syncTerms($contract, $request);
            $this->contracts->generateSchedule($contract);
        });

        OperationLog::record('updated', ContractService::LOG, $contract, $wasPending
            ? sprintf('عُدّل العقد **%s** وهو بانتظار الاعتماد فأُعيد إلى المسودة', $contract->code)
            : sprintf('عُدّلت مسودة العقد **%s**', $contract->code));

        if ($request->input('action') === 'submit' && $contract->status === 'draft') {
            $this->advance($contract, $this->overlapConfirmed($request));
        } elseif ($wasPending) {
            $this->warnings[] = 'كان العقد بانتظار الاعتماد فأُعيد إلى المسودة — أرسله للاعتماد من جديد.';
        }

        return $this->okWithWarnings($request, 'تم تحديث العقد', route('realestate.contracts.show', $contract->id));
    }

    public function submit(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless($contract->status === 'draft', 422, 'العقد ليس مسودة.');

        if ($ask = $this->askOverlapConfirmation($request, $contract)) {
            return $ask;
        }

        $this->advance($contract, $this->overlapConfirmed($request));

        return $this->ok($request, match ($contract->fresh()->status) {
            'active' => 'العقد ساري الآن',
            'expired' => 'سُجّل العقد السابق منتهياً (تسجيل تاريخي)',
            default => 'أُرسل العقد للاعتماد',
        });
    }

    public function approve(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless($contract->status === 'pending_approval', 422, 'العقد ليس بانتظار الاعتماد.');

        if ($ask = $this->askOverlapConfirmation($request, $contract)) {
            return $ask;
        }

        $this->contracts->approve($contract, $this->overlapConfirmed($request));

        return $this->ok($request, 'تم اعتماد العقد وصار سارياً');
    }

    public function reject(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless($contract->status === 'pending_approval', 422, 'العقد ليس بانتظار الاعتماد.');

        $this->contracts->reject($contract, $request->input('reason'));

        return $this->ok($request, 'أُعيد العقد إلى المسودة');
    }

    public function cancel(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        $request->validate(['reason' => 'required|string|max:1000'], ['reason.required' => 'اكتب سبب الإلغاء.']);
        abort_if($contract->isClosed(), 422, 'العقد منتهٍ أو ملغى بالفعل.');

        $this->contracts->cancel($contract, $request->reason);

        return $this->ok($request, 'تم إلغاء العقد');
    }

    public function terminate(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless(in_array($contract->status, ['active', 'renewing'], true), 422, 'لا يُنهى إلا عقدٌ سارٍ.');

        $latest = $contract->end_date->lt(now()->startOfDay()) ? $contract->end_date->toDateString() : now()->toDateString();
        $treasuryIds = TreasuryAccessGuard::withdrawable()->pluck('account_id')->filter()->map(fn ($v) => (int) $v)->all();

        $data = $request->validate([
            'terminated_at' => 'required|date|after_or_equal:'.$contract->start_date->toDateString().'|before_or_equal:'.$latest,
            'reason' => 'required|string|max:1000',
            'deposit_deducted' => 'nullable|numeric|min:0',
            'deduct_to' => 'nullable|in:owner,office,company',
            'treasury_account_id' => ['nullable', 'integer', Rule::in($treasuryIds)],
        ], [
            'terminated_at.before_or_equal' => 'تاريخ الإنهاء لا يتجاوز اليوم ولا تاريخ نهاية العقد ('.$contract->end_date->format('Y-m-d').').',
            'terminated_at.after_or_equal' => 'تاريخ الإنهاء لا يسبق بداية العقد ('.$contract->start_date->format('Y-m-d').').',
            'treasury_account_id.in' => 'اختر خزينةً أو حساباً بنكياً تملك صلاحية الصرف منه.',
        ], [
            'terminated_at' => 'تاريخ الإنهاء', 'reason' => 'سبب الإنهاء', 'deposit_deducted' => 'المخصوم من التأمين',
            'deduct_to' => 'المخصوم لصالح', 'treasury_account_id' => 'خزينة ردّ التأمين',
        ]);

        try {
            $this->contracts->terminate(
                $contract, $data['terminated_at'], $data['reason'], (float) ($data['deposit_deducted'] ?? 0),
                isset($data['treasury_account_id']) ? (int) $data['treasury_account_id'] : null, $data['deduct_to'] ?? 'owner'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            OperationLog::failure($e, ContractService::LOG, 'terminate', $contract);

            throw $e;
        }

        // الوجهة صريحة: تُعاد قراءة الصفحة فتظهر الحالة الجديدة والأزرار المناسبة لها
        return $this->ok($request, 'تم إنهاء العقد وتحرير الوحدة', route('realestate.contracts.show', $contract->id));
    }

    public function amend(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);
        abort_unless(in_array($contract->status, ['active', 'renewing'], true), 422, 'التعديل المسجَّل للعقود السارية فقط.');

        $data = $request->validate([
            'end_date' => 'required|date|after:'.$contract->start_date->toDateString(),
            'annual_value' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:5000',
            'reason' => 'required|string|max:1000',
            'effective_date' => 'nullable|date|after_or_equal:'.$contract->start_date->toDateString().'|before_or_equal:end_date',
        ], [
            'reason.required' => 'اكتب سبب التعديل — يُحفظ في سجلّ العقد.',
            'effective_date.before_or_equal' => 'تاريخ السريان لا يتجاوز تاريخ نهاية العقد.',
            'effective_date.after_or_equal' => 'تاريخ السريان لا يسبق بداية العقد.',
        ], ['end_date' => 'تاريخ النهاية', 'annual_value' => 'القيمة السنوية', 'effective_date' => 'يسري من تاريخ']);

        $this->contracts->assertNoOverlap($contract->unit_id, $contract->start_date, $data['end_date'], $contract->id, $this->overlapConfirmed($request));

        $summary = $this->contracts->amend(
            $contract,
            ['end_date' => $data['end_date'], 'annual_value' => $data['annual_value'], 'notes' => $data['notes'] ?? null],
            $data['reason'],
            $data['effective_date'] ?? now()->toDateString(),
        );

        if (! $summary['changed']) {
            return $this->ok($request, 'لا تغيير — القيم كما هي.');
        }

        $message = array_intersect($summary['changed'], ['annual_value', 'end_date'])
            ? sprintf('سُجّل التعديل وأُعيد حساب الأقساط غير المدفوعة من %s: عُدّل %d، أُضيف %d، حُذف %d. إجمالي العقد الآن %s.',
                Carbon::parse($data['effective_date'] ?? now())->format('Y-m-d'), $summary['updated'], $summary['added'], $summary['removed'], $contract->money($summary['total']))
            : 'سُجّل التعديل في سجلّ العقد.';

        return $this->ok($request, $message, route('realestate.contracts.show', $contract->id));
    }

    public function regenerateSchedule(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);

        abort_if($contract->isClosed(), 422, 'لا يُعاد بناء جدول استحقاقات عقدٍ '.$contract->status_meta['label'].'.');

        DB::transaction(function () use ($contract) {
            $contract->update([
                'duration_months' => $this->contracts->wholeMonths($contract->start_date, $contract->end_date),
                'total_value' => $this->contracts->totalFor((float) $contract->annual_value, $contract->start_date, $contract->end_date),
            ]);

            $this->contracts->generateSchedule($contract);
        });

        OperationLog::record('schedule_regenerated', ContractService::LOG, $contract, sprintf('أُعيد بناء جدول استحقاقات العقد **%s**', $contract->code));

        return $this->ok($request, 'أُعيد بناء جدول الاستحقاقات');
    }

    public function destroy(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);

        if (! $contract->isDeletable()) {
            $message = 'لا يُحذف إلا مسودةٌ بلا دفعات — استعمل الإلغاء.';

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $contract->delete();
        OperationLog::record('deleted', ContractService::LOG, $contract->id, sprintf('حُذفت مسودة العقد **%s**', $contract->code));

        return $this->ok($request, 'تم حذف المسودة', route('realestate.contracts.index'));
    }

    // ═══════════════ التجديد ═══════════════

    public function renewForm($id)
    {
        $contract = $this->findScoped(Contract::class, $id, ['tenant', 'unit.building', 'schedules']);

        if (! in_array($contract->status, ['active', 'renewing', 'expired'], true) || $contract->renewedInto) {
            return redirect()->route('realestate.contracts.show', $contract->id)->with('error', 'هذا العقد لا يقبل تجديداً (غير سارٍ أو جُدّد من قبل).');
        }

        $increase = (float) RealEstateSettings::get('default_renewal_increase', 0);
        $start = $contract->end_date->copy()->addDay();

        return view('rentalmanagement::realestate.contracts.renew', [
            'contract' => $contract,
            'pending' => $contract->renewals()->whereIn('renewal_status', ['draft', 'review', 'approved'])->first(),
            'defaults' => [
                'new_start_date' => $start->toDateString(),
                'new_end_date' => $start->copy()->addMonthsNoOverflow(max(1, (int) $contract->duration_months))->subDay()->toDateString(),
                'increase_percentage' => $increase,
                'new_rent_value' => round((float) $contract->annual_value * (1 + $increase / 100), 2),
            ],
            'outstanding' => $contract->outstanding(),
            'heldDeposit' => $contract->heldDeposit(),
        ]);
    }

    public function storeRenewal(Request $request, $id)
    {
        $contract = $this->findScoped(Contract::class, $id);

        if (! in_array($contract->status, ['active', 'renewing', 'expired'], true) || $contract->renewedInto) {
            return redirect()->route('realestate.contracts.show', $contract->id)->with('error', 'هذا العقد لا يقبل تجديداً (غير سارٍ أو جُدّد من قبل).');
        }

        $data = $request->validate([
            // يبدأ بعد نهاية العقد الحالي: لا عقدان ساريان على الوحدة نفسها
            'new_start_date' => 'required|date|after:'.$contract->end_date->toDateString(),
            'new_end_date' => 'required|date|after:new_start_date',
            'new_rent_value' => 'required|numeric|min:0.01',
            'payment_frequency' => 'required|'.V::in(V::PAYMENT_FREQUENCIES),
            'deposit_adjustment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:3000',
        ], [
            'new_start_date.after' => sprintf('العقد الحالي سارٍ حتى %s — يبدأ التجديد من %s أو بعده.',
                $contract->end_date->format('Y-m-d'), $contract->end_date->copy()->addDay()->format('Y-m-d')),
            'deposit_adjustment.min' => 'تعديل التأمين زيادةٌ فقط — التأمين المحتجز يُرحَّل كما هو.',
        ], ['new_start_date' => 'بداية التجديد', 'new_end_date' => 'نهاية التجديد', 'new_rent_value' => 'قيمة الإيجار الجديدة', 'deposit_adjustment' => 'تعديل التأمين']);

        if ($contract->renewals()->whereIn('renewal_status', ['draft', 'review', 'approved'])->exists()) {
            return back()->with('error', 'لهذا العقد طلب تجديدٍ قائم.');
        }

        // عقدٌ آخر (لمستأجرٍ آخر) يشغل الوحدة في فترة التجديد؟
        $this->contracts->assertNoOverlap($contract->unit_id, $data['new_start_date'], $data['new_end_date'], $contract->id, $this->overlapConfirmed($request));

        $old = (float) $contract->annual_value;

        $renewal = ContractRenewal::create($data + [
            'source_contract_id' => $contract->id,
            'deposit_adjustment' => $data['deposit_adjustment'] ?? 0,
            'increase_percentage' => $old > 0 ? round(((float) $data['new_rent_value'] - $old) / $old * 100, 2) : 0,
            'renewal_status' => 'review',
            'created_by' => auth()->id(),
        ]);

        if ($contract->status === 'active') {
            $contract->update(['status' => 'renewing']);
        }

        OperationLog::record('renewal_requested', ContractService::LOG, $contract, sprintf(
            'طُلب تجديد العقد **%s** بقيمة **%s** (%s%%)', $contract->code,
            number_format((float) $renewal->new_rent_value, 2), $renewal->increase_percentage));

        // من يملك الاعتماد يُتمّ التجديد في الخطوة نفسها إن طلب ذلك
        if ($request->input('action') === 'complete' && hasPermission('realestate.contracts.approve_renewal')) {
            $new = $this->contracts->completeRenewal($renewal);

            return redirect()->route('realestate.contracts.show', $new->id)->with('success', 'تم التجديد — هذا هو العقد الجديد المرتبط بسابقه.');
        }

        return redirect()->route('realestate.contracts.show', $contract->id)->with('success', 'أُرسل طلب التجديد للموافقة');
    }

    public function approveRenewal(Request $request, $id, $renewalId)
    {
        $contract = $this->findScoped(Contract::class, $id);
        $renewal = $contract->renewals()->whereKey($renewalId)->first();

        if (! $renewal) {
            return $this->fail($request, 'طلب التجديد غير موجود على هذا العقد.', 404);
        }

        // اعتمادٌ مكرَّر (نقرتان أو تبويبان): رسالة مفهومة ووجهة إلى العقد الناتج، لا 404
        if ($renewal->renewal_status === 'completed') {
            return $this->fail($request, 'التجديد مُعتمد سابقاً'.($renewal->renewedContract ? ' — العقد الناتج '.$renewal->renewedContract->code.'.' : '.'), 422,
                $renewal->renewed_contract_id ? route('realestate.contracts.show', $renewal->renewed_contract_id) : null);
        }

        if ($renewal->renewal_status === 'rejected') {
            return $this->fail($request, 'طلب التجديد مرفوضٌ سابقاً — أنشئ طلباً جديداً.', 422);
        }

        $new = $this->contracts->completeRenewal($renewal);

        return $this->ok($request, 'تم اعتماد التجديد وإنشاء العقد الجديد', route('realestate.contracts.show', $new->id));
    }

    public function rejectRenewal(Request $request, $id, $renewalId)
    {
        $contract = $this->findScoped(Contract::class, $id);
        $renewal = $contract->renewals()->whereKey($renewalId)->first();

        if (! $renewal || $renewal->renewal_status !== 'review') {
            return $this->fail($request, match ($renewal?->renewal_status) {
                'completed' => 'التجديد مُعتمد سابقاً — لا يُرفض.',
                'rejected' => 'طلب التجديد مرفوضٌ سابقاً.',
                default => 'طلب التجديد غير موجود على هذا العقد.',
            }, $renewal ? 422 : 404);
        }

        $renewal->update(['renewal_status' => 'rejected', 'approved_by' => auth()->id()]);

        if ($contract->status === 'renewing') {
            $contract->update(['status' => $contract->end_date->isPast() && ! $contract->end_date->isToday() ? 'expired' : 'active']);
            $this->contracts->syncUnit($contract);
        }

        OperationLog::record('renewal_rejected', ContractService::LOG, $contract, sprintf('رُفض طلب تجديد العقد **%s**', $contract->code));

        return $this->ok($request, 'رُفض طلب التجديد');
    }

    // ═══════════════ داخلي ═══════════════

    /** مسودة ← (اعتماد مطلوب؟ بانتظار الاعتماد : ساري). */
    private function advance(Contract $contract, bool $overlapConfirmed = false): void
    {
        $this->contracts->submit($contract, $overlapConfirmed);

        if (RealEstateSettings::enabled('require_contract_approval') && ! hasPermission('realestate.contracts.approve')) {
            return;
        }

        $this->contracts->approve($contract, $overlapConfirmed);
    }

    /** التجاوز الصريح: خانة «تجاوز منع التداخل» في النموذج، أو `force` من حوار التأكيد. */
    private function overlapConfirmed(Request $request): bool
    {
        return $request->boolean('override_overlap') || $request->boolean('force');
    }

    /**
     * لأزرار الإجراء (إرسال/اعتماد): تداخلٌ يملك المستخدم تجاوزَه يُردّ 409
     * بسؤالٍ صريح، و`list-scripts` يعيد الطلب بـ`force=1` إن وافق.
     */
    private function askOverlapConfirmation(Request $request, Contract $contract)
    {
        if ($this->overlapConfirmed($request) || ! hasPermission('realestate.contracts.override_overlap')) {
            return null;
        }

        $other = $this->contracts->overlapping($contract->unit_id, $contract->start_date, $contract->end_date, [$contract->id, $contract->previous_contract_id]);

        if (! $other) {
            return null;
        }

        return response()->json([
            'success' => false,
            'confirm' => true,
            'message' => sprintf('الوحدة مشغولة بالعقد %s حتى %s. المتابعة تعني عقدين متداخلين على الوحدة نفسها، ويُسجَّل التجاوز باسمك.', $other->code, $other->end_date->format('Y-m-d')),
        ], 409);
    }

    /** ردّ فشلٍ مفهوم: JSON للإجراءات، ورسالة خطأ مع رجوعٍ للصفحات. */
    private function fail(Request $request, string $message, int $status = 422, ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'redirect' => $redirect], $status);
        }

        return ($redirect ? redirect($redirect) : back())->with('error', $message);
    }

    /** نجاحٌ معه تنبيهاتٌ لم تمنع الحفظ. */
    private function okWithWarnings(Request $request, string $message, ?string $redirect = null)
    {
        $warnings = array_values(array_unique($this->warnings));

        if ($warnings && ! $request->expectsJson()) {
            session()->flash('warning', implode(' · ', $warnings));
        }

        $response = $this->ok($request, $message, $redirect);

        if ($warnings && $request->expectsJson()) {
            $response->setData($response->getData(true) + ['warnings' => $warnings]);
        }

        return $response;
    }

    /** الحساب الذي يُقيَّد عليه إيجار العقد: جاري المالك للمبنى المُدار، وإيرادات الإيجار للمملوك. */
    private function postingAccount(Contract $contract, string $ownership): array
    {
        if ($ownership === 'managed' && ($owner = $contract->effectiveOwner())) {
            return ['label' => 'حساب المالك الجاري', 'account' => $owner->account];
        }

        $accountId = RealEstateSettings::get('rent_revenue_account_id');

        return ['label' => 'حساب إيرادات الإيجار', 'account' => $accountId ? Account::find($accountId) : null];
    }

    /** ما يحتاجه نموذج العقد: القوائم، ضرائب النظام، وأنواع العقود المتاحة. */
    private function formData(Contract $contract, ?int $ownerId): array
    {
        // «بيع» لا يُعرض للعقود الجديدة — يبقى ظاهراً لعقد بيعٍ قائم فقط
        $types = collect(V::CONTRACT_TYPES)->only(Contract::CREATABLE_TYPES);

        if ($contract->exists && isset(V::CONTRACT_TYPES[$contract->contract_type]) && ! $types->has($contract->contract_type)) {
            $types->put($contract->contract_type, V::CONTRACT_TYPES[$contract->contract_type]);
        }

        $units = $this->units();
        $unitMeta = [];

        foreach ($units->load('building.owner') as $unit) {
            $policy = $this->contracts->taxPolicy($unit);
            $unitMeta[$unit->id] = ['usage' => $policy['usage'], 'ownership' => $policy['ownership'], 'owner_id' => $unit->building?->owner_id];
        }

        return [
            'contract' => $contract,
            'units' => $units,
            'unitMeta' => $unitMeta,
            'clients' => $this->clients(),
            'owners' => $this->owners($ownerId),
            'ownerVat' => $this->ownerVatMap(),
            'tenants' => $this->tenantOptions(),
            'termTemplates' => $this->termTemplates(),
            'contractTypes' => $types->all(),
            'taxes' => TaxSitting::query()->orderBy('name')->get(['id', 'name', 'tax', 'type']),
            'defaultTaxId' => $this->defaultTax()?->id,
        ];
    }

    /** @return array<int, bool>|null تسجيل كل مالك في الضريبة — أو null إن لم يُضف العمود بعد. */
    private function ownerVatMap(): ?array
    {
        if (! ContractService::hasColumn('re_owners', 'is_vat_registered')) {
            return null;
        }

        return $this->scoped(Owner::query())->pluck('is_vat_registered', 'id')->map(fn ($v) => (bool) $v)->all();
    }

    /**
     * الضريبة الافتراضية للعقد التجاري: إعداد العقارات `default_tax_id`، ثم
     * الضريبة الشاملة الافتراضية للمستندات، ثم ضريبة النظام المطابقة للنسبة
     * الافتراضية القديمة.
     */
    private function defaultTax(): ?TaxSitting
    {
        $id = RealEstateSettings::get('default_tax_id');
        $tax = $id ? TaxSitting::find($id) : null;

        return $tax
            ?? TaxSitting::comprehensiveDefault()
            ?? TaxSitting::query()->where('tax', (float) RealEstateSettings::get('default_tax_rate', 15))->where('tax', '>', 0)->orderBy('id')->first();
    }

    /** قوالب الشروط الفعّالة — تُصفّى بنوع العقد في المتصفح. */
    private function termTemplates()
    {
        return TermTemplate::where('is_active', true)
            ->orderBy('sort')->orderBy('id')->get(['id', 'title', 'body', 'contract_type', 'is_default']);
    }

    private function syncTerms(Contract $contract, Request $request): void
    {
        $contract->terms()->delete();

        foreach ((array) $request->input('terms', []) as $i => $term) {
            if (filled($term['title'] ?? null)) {
                $contract->terms()->create(['title' => $term['title'], 'body' => $term['body'] ?? null, 'sort' => $i]);
            }
        }
    }

    /** خيارات حقل «المستأجر / العميل»: المستأجرون المسجّلون مع جوالهم ورقم هويتهم وانتهائها ورقمهم الضريبي. */
    private function tenantOptions()
    {
        $tenants = Client::query()->whereIn('id', $this->tenantClientIds())
            ->select('id', 'trade_name', 'first_name', 'last_name', 'code', 'phone', 'mobile', 'tax_number')->orderBy('trade_name')->get();

        $profiles = TenantProfile::withoutGlobalScopes()->whereIn('client_id', $tenants->pluck('id'))->get(['client_id', 'id_number', 'id_expiry_date'])->keyBy('client_id');

        return $tenants->each(function ($tenant) use ($profiles) {
            $profile = $profiles->get($tenant->id);
            $tenant->setAttribute('id_number', $profile?->id_number);
            $tenant->setAttribute('id_expired', (bool) $profile?->idExpired());
            $tenant->setAttribute('id_expiry', $profile?->id_expiry_date?->format('Y-m-d'));
        });
    }

    /**
     * التحقق من نموذج العقد — والقواعد التي لا يُترك أمرها للمتصفح:
     * نوع العقد يتبع استخدام الوحدة، والضريبة تُشتقّ من ضريبة النظام المختارة
     * (لا من أرقامٍ يرسلها العميل) ولا تُقبل على سكني ولا لمالكٍ غير مسجَّل.
     */
    private function validated(Request $request, ?Contract $contract = null): array
    {
        // المتحكّم قد يعيش أكثر من طلب (Octane، الاختبارات): تنبيهات كلّ طلبٍ له وحده
        $this->warnings = [];

        // «بيع» مرفوضٌ للعقود الجديدة؛ عقد بيعٍ قائم يُحفظ بنوعه
        $types = Contract::CREATABLE_TYPES;

        if ($contract && $contract->contract_type === 'sale') {
            $types[] = 'sale';
        }

        $data = $request->validate([
            'contract_type' => ['required', Rule::in($types)],
            // من جدول المستأجرين لا من عموم العملاء: من لم يُسجَّل مستأجراً يُسجَّل أولاً من قسم المستأجرين
            'tenant_client_id' => ['required', Rule::in($this->tenantClientIds())],
            'owner_id' => 'nullable|exists:re_owners,id',
            'unit_id' => ['required', Rule::exists('re_units', 'id')->whereNull('deleted_at')],
            'listing_id' => ['nullable', Rule::exists('re_listings', 'id')->where('listing_kind', 'offer')],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'annual_value' => 'required|numeric|min:0.01',
            'payment_frequency' => 'required|'.V::in(V::PAYMENT_FREQUENCIES),
            'security_deposit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|integer|exists:tax_sittings,id',
            'signed_at' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'terms' => 'nullable|array',
            'terms.*.title' => 'nullable|string|max:191',
            'terms.*.body' => 'nullable|string|max:5000',
        ], [
            'tenant_client_id.in' => 'اختر المستأجر من المستأجرين المسجّلين — أو سجّله أولاً من «مستأجر جديد».',
            'contract_type.in' => 'نوع العقد غير متاح — المتاح: إيجار سكني أو إيجار تجاري.',
            'unit_id.exists' => 'الوحدة المختارة غير موجودة أو حُذفت.',
            'end_date.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.',
        ], [
            'contract_type' => 'نوع العقد', 'tenant_client_id' => 'المستأجر', 'owner_id' => 'المالك', 'unit_id' => 'الوحدة',
            'start_date' => 'تاريخ البداية', 'end_date' => 'تاريخ النهاية', 'annual_value' => 'القيمة السنوية',
            'payment_frequency' => 'دورية السداد', 'security_deposit' => 'التأمين', 'tax_id' => 'الضريبة',
            'signed_at' => 'تاريخ التوقيع', 'notes' => 'الملاحظات',
        ]);

        unset($data['terms']);

        $unit = $this->scoped(Unit::query())->with('building.owner')->find($data['unit_id']);

        if (! $unit) {
            throw ValidationException::withMessages(['unit_id' => 'الوحدة المختارة خارج نطاق صلاحيتك أو غير موجودة.']);
        }

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $historical = $request->boolean('is_historical');

        // عقدٌ انتهت مدّته قبل اليوم لا يُنشأ «سارياً» خطأً — إلا تسجيلاً تاريخياً صريحاً
        if ($end->lt(now()->startOfDay()) && ! $historical) {
            throw ValidationException::withMessages([
                'end_date' => 'تاريخ نهاية العقد مضى. إن كان عقداً سابقاً يُسجَّل للتاريخ فعلِّم «عقد سابق (تسجيل تاريخي)».',
            ]);
        }

        $buildingOwnerId = $unit->building?->owner_id;
        // بلا مالكٍ صريح يُسجَّل مالك المبنى — فيلزم العقدَ مالكُه لو بيع المبنى أو تغيّر مالكه لاحقاً
        $data['owner_id'] = $data['owner_id'] ?? $buildingOwnerId;
        $owner = $data['owner_id'] ? Owner::withTrashed()->find($data['owner_id']) : null;

        // المالك على العقد غير مالك المبنى: يُقبل (وكالة، بيع لاحق…) لكن يُنبَّه ويُسجَّل
        if ($buildingOwnerId && $data['owner_id'] && (int) $data['owner_id'] !== (int) $buildingOwnerId) {
            $this->warnings[] = sprintf('المالك المسجَّل على العقد (%s) غير مالك المبنى (%s).', $owner?->name ?? '—', $unit->building->owner?->name ?? '—');
        }

        $policy = $this->contracts->taxPolicy($unit, $owner);
        $isLease = $data['contract_type'] !== 'sale';

        // نوع العقد يتبع استخدام الوحدة
        if ($isLease && $data['contract_type'] !== ($expected = $this->contracts->contractTypeFor($unit))) {
            throw ValidationException::withMessages([
                'contract_type' => sprintf('الوحدة %s — نوع العقد يجب أن يكون «%s».',
                    $policy['usage'] === 'commercial' ? 'تجارية' : 'سكنية', V::label(V::CONTRACT_TYPES, $expected)),
            ]);
        }

        // الضريبة: من ضريبة النظام المختارة. غياب الحقل كلّياً = الافتراضي بحسب الوحدة.
        $tax = $request->has('tax_id')
            ? (! empty($data['tax_id']) ? TaxSitting::find($data['tax_id']) : null)
            : ($policy['taxable'] ? $this->defaultTax() : null);

        if ($tax && ! $policy['taxable']) {
            throw ValidationException::withMessages([
                'tax_id' => $policy['usage'] === 'residential'
                    ? 'العقد السكني بلا ضريبة — اختر «بدون ضريبة».'
                    : 'المالك غير مسجَّل في الضريبة — لا تُضاف ضريبة على هذا العقد. اختر «بدون ضريبة» أو سجّل المالك في الضريبة أولاً.',
            ]);
        }

        if (! $tax && $policy['taxable'] && $isLease) {
            $this->warnings[] = 'العقد تجاري خاضع للضريبة وحُفظ «بدون ضريبة».';
        }

        $data['tax_id'] = $tax?->id;
        $data['tax_rate'] = $tax ? (float) $tax->tax : 0;
        $data['tax_included'] = $tax ? $tax->type === 'included' : false;

        // تنبيهان عن المستأجر لا يمنعان الحفظ
        $tenant = Client::find($data['tenant_client_id']);
        $profile = TenantProfile::withoutGlobalScopes()->where('client_id', $data['tenant_client_id'])->first();

        if ($policy['usage'] === 'commercial' && $isLease && blank($tenant?->tax_number)) {
            $this->warnings[] = 'المستأجر في عقدٍ تجاري بلا رقمٍ ضريبي — أكمله من ملف المستأجر لتصحّ الفاتورة الضريبية.';
        }

        if ($profile?->idExpired() && ! $historical) {
            $this->warnings[] = 'هوية المستأجر منتهية منذ '.$profile->id_expiry_date->format('Y-m-d').'.';
        }

        $data['building_id'] = $unit->building_id;
        $data['duration_months'] = $this->contracts->wholeMonths($start, $end);
        // القيمة بالأيام لما لا يكمل شهراً: 45 يوماً ليست شهرين
        $data['total_value'] = $this->contracts->totalFor((float) $data['annual_value'], $start, $end);
        $data['security_deposit'] = $data['security_deposit'] ?? 0;

        if (ContractService::hasColumn('re_contracts', 'is_historical')) {
            $data['is_historical'] = $historical && $end->lt(now()->startOfDay());
        }

        return $data;
    }
}
