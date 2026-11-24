<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\Supplier;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\MaintenanceCostService;
use Modules\RentalManagement\Support\RealEstateMoney;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class MaintenanceController extends RealEstateController
{
    public const LOG = 're_maintenance_log';

    public function __construct()
    {
        $this->middleware('permission.role:realestate.maintenance.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.maintenance.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.maintenance.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:realestate.maintenance.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:realestate.maintenance.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.maintenance.assign', ['only' => ['status']]);
        $this->middleware('permission.role:realestate.maintenance.costs', ['only' => ['storeCost', 'destroyCost', 'postCost']]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(MaintenanceRequest::query())->with(['unit.building', 'building', 'assignee', 'reporter'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('title', 'like', $term)->orWhere('description', 'like', $term));
        });

        foreach (['status', 'priority', 'category', 'building_id', 'unit_id', 'assigned_to_user_id', 'charge_to'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        // صيانة وحدة أم صيانة مبنى — يُشتقّ النطاق من وجود الوحدة
        $query->when($request->input('scope') === 'unit', fn ($q) => $q->whereNotNull('unit_id'));
        $query->when($request->input('scope') === 'building', fn ($q) => $q->whereNull('unit_id'));

        $query->when($request->boolean('late'), fn ($q) => $q->openOnes()->whereNotNull('scheduled_at')->where('scheduled_at', '<', now()));

        $requests = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.maintenance.partials.table', compact('requests'))->render();
        }

        $scope = $this->scoped(MaintenanceRequest::query());
        $counts = (clone $scope)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('rentalmanagement::realestate.maintenance.index', [
            'requests' => $requests,
            'counts' => $counts,
            'scopeCounts' => [
                'all' => (clone $scope)->count(),
                'unit' => (clone $scope)->whereNotNull('unit_id')->count(),
                'building' => (clone $scope)->whereNull('unit_id')->count(),
            ],
            'buildings' => $this->buildings(),
            'users' => $this->users(),
            'topCategories' => (clone $scope)->selectRaw('category, COUNT(*) as c')->groupBy('category')->orderByDesc('c')->limit(5)->pluck('c', 'category'),
            'upcoming' => (clone $scope)->openOnes()->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now())
                ->with('unit.building', 'building')->orderBy('scheduled_at')->limit(6)->get(),
            'monthCost' => (float) (clone $scope)->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('actual_cost'),
        ]);
    }

    public function create(Request $request)
    {
        return view('rentalmanagement::realestate.maintenance.form', [
            'item' => new MaintenanceRequest([
                'unit_id' => $request->input('scope') === 'building' ? null : $request->input('unit_id'),
                'building_id' => $request->input('building_id'),
                'priority' => 'medium', 'category' => 'other', 'charge_to' => 'owner',
            ]),
            'code' => peek_document_number('re-maintenance'),
        ] + $this->lists());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $item = new MaintenanceRequest($data);
        $item->code = next_document_number('re-maintenance');
        $item->status = filled($item->assigned_to_user_id) ? 'waiting' : 'open';
        $item->attachments = $this->storeFiles($request, 'attachments', 'maintenance');
        $item->save();

        OperationLog::record('created', self::LOG, $item, sprintf('فُتح طلب الصيانة **%s** — %s', $item->code, $item->title));
        $this->notifyAssignee($item);

        return $this->ok($request, 'تم فتح طلب الصيانة', route('realestate.maintenance.show', $item->id));
    }

    public function show($id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id, [
            'unit.building', 'building', 'contract.tenant', 'reporter', 'assignee', 'contractor', 'costs', 'creator',
        ]);

        // من يتحمّل التكلفة فعلاً: المالك في المبنى المُدار، وإلا المنشأة. ولكل تكلفةٍ طرفٌ مقابل إلزامي
        $costs = app(MaintenanceCostService::class);
        $owner = $item->charge_to !== 'company' ? $costs->managedOwner($item) : null;
        $item->costs->load('treasuryAccount', 'supplier', 'schedule', 'journalEntry');

        return view('rentalmanagement::realestate.maintenance.show', [
            'item' => $item,
            'chargedOwner' => $owner,
            'supplierAccount' => $costs->supplierAccount($item),
            'treasuries' => hasPermission('realestate.maintenance.costs')
                ? TreasuryAccessGuard::withdrawable()->map(fn ($t) => (object) ['id' => (int) $t->account_id, 'name' => $t->name]) : collect(),
            'logs' => hasPermission('realestate.logs.view')
                ? ModelsLog::where('type', self::LOG)->where('type_id', $item->id)->latest()->limit(40)->get()
                : collect(),
        ]);
    }

    public function edit($id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);

        if (in_array($item->status, ['completed', 'cancelled'], true)) {
            return redirect()->route('realestate.maintenance.show', $item->id)->with('error', 'الطلب مغلق — لا يُعدَّل.');
        }

        return view('rentalmanagement::realestate.maintenance.form', [
            'item' => $item,
            'code' => null,
        ] + $this->lists());
    }

    public function update(Request $request, $id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);
        abort_if(in_array($item->status, ['completed', 'cancelled'], true), 422, 'الطلب مغلق — لا يُعدَّل.');

        $data = $this->validated($request);
        $reassigned = (int) ($data['assigned_to_user_id'] ?? 0) !== (int) $item->assigned_to_user_id;

        $changes = OperationLog::diff($item->fill($data), array_keys($data));
        $item->attachments = $this->storeFiles($request, 'attachments', 'maintenance', $item->getOriginal('attachments'));
        $item->save();

        OperationLog::record('updated', self::LOG, $item, sprintf('عُدّل طلب الصيانة **%s**', $item->code), $changes);

        if ($reassigned) {
            $this->notifyAssignee($item);
        }

        return $this->ok($request, 'تم تحديث الطلب', route('realestate.maintenance.show', $item->id));
    }

    /**
     * مسار الحالة: مفتوحة ← بانتظار ← قيد التنفيذ ← مكتملة، والإلغاء من أيّ
     * حالةٍ مفتوحة. الإغلاق يتطلّب ملاحظات الإنجاز ويثبّت التكلفة الفعلية.
     */
    public function status(Request $request, $id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id, ['unit']);

        $data = $request->validate([
            'status' => 'required|'.V::in(V::MAINTENANCE_STATUSES),
            'resolution_notes' => 'nullable|required_if:status,completed|string|max:3000',
            'actual_cost' => 'nullable|numeric|min:0',
        ], ['resolution_notes.required_if' => 'اكتب ما أُنجز قبل إغلاق الطلب.']);

        // المكتمل يُعاد فتحه إلى «قيد التنفيذ» فقط (عملٌ لم يكتمل فعلاً)؛ الملغى لا يُفتح
        $reopen = $item->status === 'completed' && $data['status'] === 'in_progress';
        abort_if($item->status === 'cancelled' || ($item->status === 'completed' && ! $reopen), 422, $item->status === 'cancelled' ? 'الطلب ملغى — لا يُعاد فتحه.' : 'الطلب مكتمل — يُعاد فتحه إلى «قيد التنفيذ» فقط.');

        $old = $item->status;

        if ($reopen) {
            $item->completed_at = null;
        }
        $item->status = $data['status'];

        if ($data['status'] === 'in_progress') {
            $item->started_at = $item->started_at ?: now();
        }

        if ($data['status'] === 'completed') {
            $item->started_at = $item->started_at ?: now();
            $item->completed_at = now();
            $item->resolution_notes = $data['resolution_notes'];

            /*
                التكلفة الفعلية = مجموع بنود التكلفة دائماً، ولكل بندٍ قيدٌ
                وطرفٌ مقابل. فرقمٌ يُدخل عند الإغلاق يزيد على البنود لا
                يُقيَّد بنداً صامتاً بلا قيد: يُطلب تسجيله من «إضافة تكلفة».
            */
            $lines = (float) $item->costs()->sum('amount');
            $entered = (float) ($data['actual_cost'] ?? 0);

            if (round($entered - $lines, 2) > 0) {
                throw ValidationException::withMessages(['actual_cost' => sprintf(
                    'التكلفة المُدخلة تزيد على البنود المسجَّلة بـ %s — أضف الفرق من «إضافة تكلفة» (مع الخزينة أو المقاول) قبل الإغلاق.',
                    RealEstateMoney::format($entered - $lines))]);
            }

            $item->actual_cost = $lines;
        }

        /*
            إلغاء الطلب يلغي أثره المحاسبي: تُعكس قيود تكاليفه وحركات المالك
            ويُسحب قسط المستأجر — في معاملةٍ واحدة مع تغيير الحالة. وإن كان
            قسطٌ منها محصَّلاً رُفض الإلغاء حتى تُلغى دفعته.
        */
        $reversed = 0;

        DB::transaction(function () use ($item, $data, &$reversed) {
            if ($data['status'] === 'cancelled') {
                $service = app(MaintenanceCostService::class);

                foreach ($item->costs()->get() as $cost) {
                    $service->remove($cost);
                    $reversed++;
                }

                $item->actual_cost = 0;
            }

            $item->save();
        });

        if ($reversed) {
            OperationLog::record('cost_removed', self::LOG, $item, sprintf('أُلغي طلب الصيانة **%s** فعُكست قيود %d من تكاليفه', $item->code, $reversed));
        }

        OperationLog::record('status_changed', self::LOG, $item, sprintf(
            'طلب الصيانة **%s**: «%s» ← «%s»', $item->code,
            V::label(V::MAINTENANCE_STATUSES, $old), V::label(V::MAINTENANCE_STATUSES, $item->status)));

        if ($item->created_by && $item->created_by !== auth()->id() && in_array($item->status, ['completed', 'cancelled'], true)) {
            notifications::create([
                'user_id' => auth()->id(),
                'receiver_id' => $item->created_by,
                'title' => 'طلب الصيانة '.$item->code.' — '.V::label(V::MAINTENANCE_STATUSES, $item->status),
                'description' => $item->title,
                'type' => $this->notificationType('MAINTENANCE_CLOSED', $item->status === 'completed' ? 'success' : 'warning'),
            ]);
        }

        return $this->ok($request, 'تم تحديث حالة الطلب');
    }

    public function storeCost(Request $request, MaintenanceCostService $costs, $id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);

        if (in_array($item->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'الطلب مغلق — لا تُسجَّل عليه تكاليف.');
        }

        $data = $request->validate([
            'description' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0.01',
            'cost_date' => 'nullable|date|before_or_equal:today',
            'paid_from' => 'nullable|in:treasury,supplier',
            'treasury_account_id' => 'nullable|integer|exists:accounts,id',
        ], ['cost_date.before_or_equal' => 'تاريخ التكلفة لا يكون في المستقبل.'], [
            'description' => 'بيان التكلفة', 'amount' => 'المبلغ', 'cost_date' => 'التاريخ',
            'paid_from' => 'مصدر الدفع', 'treasury_account_id' => 'الخزينة/الحساب البنكي',
        ]);

        // القيد والطرف المقابل وقسط المستأجر (إن كانت عليه) في الخدمة — معاملةٌ واحدة
        $cost = $costs->add($item, $data);

        OperationLog::record('cost_added', self::LOG, $item, sprintf(
            'أُضيفت تكلفة **%s** على طلب الصيانة **%s** — %s', RealEstateMoney::format($cost->amount, $cost->currency), $item->code, $cost->description));

        return back()->with('success', 'أُضيفت التكلفة وقُيِّدت'.($cost->schedule_id ? ' وأُضيف قسطها إلى استحقاقات المستأجر' : ''));
    }

    /** تقييد تكلفةٍ قديمة بلا قيد (سُجّلت قبل إلزام الطرف المقابل) — متاح حتى على طلبٍ مغلق. */
    public function postCost(Request $request, MaintenanceCostService $costs, $id, $costId)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);
        abort_if($item->status === 'cancelled', 422, 'الطلب ملغى.');
        $cost = $item->costs()->findOrFail($costId);

        $data = $request->validate([
            'paid_from' => 'nullable|in:treasury,supplier',
            'treasury_account_id' => 'nullable|integer|exists:accounts,id',
        ], [], ['paid_from' => 'مصدر الدفع', 'treasury_account_id' => 'الخزينة/الحساب البنكي']);

        $cost = $costs->post($cost, $data);

        OperationLog::record('cost_posted', self::LOG, $item, sprintf(
            'قُيِّدت تكلفة **%s** على طلب الصيانة **%s** — %s', RealEstateMoney::format($cost->amount, $cost->currency), $item->code, $cost->description));

        return back()->with('success', 'قُيِّدت التكلفة'.($cost->schedule_id ? ' وأُضيف قسطها إلى استحقاقات المستأجر' : ''));
    }

    public function destroyCost(MaintenanceCostService $costs, $id, $costId)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);
        abort_if($item->status === 'cancelled', 422, 'الطلب ملغى.');
        $cost = $item->costs()->findOrFail($costId);
        $label = RealEstateMoney::format($cost->amount, $cost->currency);

        $costs->remove($cost);

        OperationLog::record('cost_removed', self::LOG, $item, sprintf('حُذفت تكلفة **%s** من طلب الصيانة **%s** وعُكس قيدها', $label, $item->code));

        return back()->with('success', 'حُذفت التكلفة وعُكس قيدها');
    }

    public function destroy(Request $request, $id)
    {
        $item = $this->findScoped(MaintenanceRequest::class, $id);

        if ($item->costs()->exists() || $item->status === 'completed') {
            $message = 'طلبٌ عليه تكاليف أو مُنجَز لا يُحذف — ألغِه إن لزم.';

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $item->delete();
        OperationLog::record('deleted', self::LOG, $item->id, sprintf('حُذف طلب الصيانة **%s**', $item->code));

        return $this->ok($request, 'تم حذف الطلب', route('realestate.maintenance.index'));
    }

    private function lists(): array
    {
        return [
            'buildings' => $this->buildings(),
            'units' => $this->units(),
            'clients' => $this->clients(),
            'users' => $this->users(),
            'suppliers' => Supplier::query()->orderBy('trade_name')->get(['id', 'trade_name']),
        ];
    }

    /** نوع الإشعار من أنواع العقارات (تُضبط من «إعدادات الإشعارات»)، وإلا النوع العام السابق. */
    private function notificationType(string $constant, string $fallback): string
    {
        $class = '\Modules\RentalManagement\Support\RealEstateNotifications';

        return class_exists($class) && defined($class.'::'.$constant) ? constant($class.'::'.$constant) : $fallback;
    }

    private function notifyAssignee(MaintenanceRequest $item): void
    {
        if (! $item->assigned_to_user_id || $item->assigned_to_user_id === auth()->id()) {
            return;
        }

        notifications::create([
            'user_id' => auth()->id(),
            'receiver_id' => $item->assigned_to_user_id,
            'title' => 'أُسند إليك طلب الصيانة '.$item->code,
            'description' => $item->title.' — '.$item->location_label,
            'type' => $this->notificationType('MAINTENANCE_ASSIGNED', $item->priority === 'urgent' ? 'error' : 'info'),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'scope' => 'nullable|in:unit,building',
            'building_id' => 'nullable|required_if:scope,building|required_without:unit_id|exists:re_buildings,id',
            'unit_id' => 'nullable|required_if:scope,unit|exists:re_units,id',
            'reported_by_client_id' => 'nullable|exists:clients,id',
            'category' => 'required|'.V::in(V::MAINTENANCE_CATEGORIES),
            'title' => 'required|string|max:191',
            'description' => 'nullable|string|max:5000',
            'priority' => 'required|'.V::in(V::PRIORITIES),
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'contractor_supplier_id' => 'nullable|exists:suppliers,id',
            'scheduled_at' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'charge_to' => 'required|'.V::in(V::CHARGE_TO),
        ], [
            'building_id.required_without' => 'حدّد العقار أو الوحدة محلّ البلاغ.',
            'building_id.required_if' => 'اختر المبنى محلّ الصيانة.',
            'unit_id.required_if' => 'اختر الوحدة محلّ الصيانة.',
        ], [
            'title' => 'عنوان البلاغ', 'category' => 'التصنيف', 'priority' => 'الأولوية', 'charge_to' => 'التكلفة على',
        ]);

        // صيانة المبنى لا تحمل وحدةً ولا عقداً حتى لو بقيت قيمةٌ في الحقل المخفي
        if (($data['scope'] ?? null) === 'building') {
            $data['unit_id'] = null;
            $data['contract_id'] = null;
        }

        unset($data['scope']);

        if (! empty($data['unit_id'])) {
            $unit = $this->findScoped(Unit::class, $data['unit_id'], ['activeContract']);

            if (! empty($data['building_id']) && (int) $data['building_id'] !== (int) $unit->building_id) {
                throw ValidationException::withMessages(['unit_id' => 'الوحدة المختارة لا تتبع هذا العقار.']);
            }

            $data['building_id'] = $unit->building_id;
            $data['contract_id'] = $unit->activeContract->id ?? null;
            $data['reported_by_client_id'] = $data['reported_by_client_id'] ?? ($unit->activeContract->tenant_client_id ?? null);
        }

        $data['estimated_cost'] = $data['estimated_cost'] ?? 0;

        return $data;
    }
}
