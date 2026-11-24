<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\EmployeeClientVisit;
use App\Models\Visit;
use App\Services\Itinerary\DailyItineraryService;
use App\Support\GeoDistance;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Http\Requests\Clients\ClientRequest;
use Modules\Integration\Http\Resources\Clients\ClientResource;
use Modules\Integration\Services\Clients\ClientForm;
use Modules\Integration\Services\Clients\ClientVisibility;
use Modules\Integration\Services\Clients\ClientWriter;

/**
 * العملاء — القائمة والخريطة والتفصيل والإضافة والتعديل.
 *
 * كل ما هنا منسوخٌ من `Modules\Client\Http\Controllers\ClientController`
 * (لا نداء له): نفس سلسلة «من يرى أيّ عميل» ({@see ClientVisibility})، نفس
 * الفلاتر، نفس قاعدة «الزيارة قبل العرض»، ونفس خطوات الحفظ ({@see ClientWriter}).
 * الصلاحية على المسار (`routes/api/clients.php`)، والنطاق هنا.
 */
class ClientController extends Controller
{
    use RespondsJson;

    private const WITH = ['status_client:id,name,color', 'locations', 'neighborhood', 'branch:id,name'];

    public function __construct(
        private readonly ClientVisibility $visibility,
        private readonly ClientWriter $writer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->validateListFilters($request);
        $user = $request->user();

        $query = Client::with(self::WITH);
        $visible = $this->visibility->apply($query, $user);
        $this->visibility->excludeHidden($query, $user);
        $this->visibility->applyBranchFilter($query, $request->integer('branch_id') ?: null, $this->wantsDescendants($request));
        $this->applyFilters($query, $request);

        $query->when($request->input('sort') === 'name', fn ($q) => $q->orderBy('trade_name'), fn ($q) => $q->orderByDesc('id'));

        $page = $visible
            ? $query->paginate($this->perPage())
            : new LengthAwarePaginator([], 0, $this->perPage());

        $clients = $page->getCollection();
        $this->attachDue($clients);
        $visitState = $this->visitState($clients, $user->id);
        [$lat, $lng] = [$request->input('latitude'), $request->input('longitude')];

        $items = $clients->map(fn (Client $c) => ClientResource::summary($c, [
            'needs_visit' => $visitState['must_visit'] && ! isset($visitState['completed'][$c->id]),
            'open_visit_id' => $visitState['open'][$c->id] ?? null,
            'distance_km' => $this->distanceKm($c, $lat, $lng),
        ]))->values()->all();

        return $this->paginated($page, $items);
    }

    /**
     * الخريطة — نفس `getMapData()`: من له خطّ سيرٍ اليوم لا يرى إلا عملاءه
     * فيه (مصدرُ كلٍّ منهم: الخطّة / أضافه هو / أضافه غيره)، وغيره يرى نطاقه.
     */
    public function map(Request $request): JsonResponse
    {
        $this->validateListFilters($request);
        $user = $request->user();

        $query = Client::with(self::WITH);

        $itineraryRows = EmployeeClientVisit::where('employee_id', $user->id)
            ->whereDate('visit_date', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'cancelled'))
            ->orderBy('id')
            ->get(['client_id', 'added_by']);
        $itineraryMode = $itineraryRows->isNotEmpty();

        if ($itineraryMode) {
            $query->whereIn('id', $itineraryRows->pluck('client_id')->unique()->values());
            $visible = true;
        } else {
            $visible = $this->visibility->apply($query, $user);
        }

        $this->visibility->excludeHidden($query, $user, $itineraryMode);
        $this->visibility->applyBranchFilter($query, $request->integer('branch_id') ?: null, $this->wantsDescendants($request));
        $this->applyFilters($query, $request);

        if (! $visible) {
            return $this->ok(['clients' => [], 'total' => 0, 'itinerary_only' => false]);
        }

        $clients = $query->whereHas('locations', fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', '')->where('longitude', '!=', '')
            ->where('latitude', '!=', 0)->where('longitude', '!=', 0))
            ->limit(2000)
            ->get();

        Branch::preloadCache();
        $this->attachDue($clients);
        $visitState = $this->visitState($clients, $user->id);

        $sources = [];
        if ($itineraryMode) {
            $adders = app(DailyItineraryService::class)->describeAdders($itineraryRows->pluck('added_by')->filter()->unique()->all());

            foreach ($itineraryRows as $row) {
                $by = $row->added_by ? ($adders[(int) $row->added_by] ?? null) : null;
                $sources[(int) $row->client_id] = match (true) {
                    ! $row->added_by => ['tone' => 'plan', 'label' => 'من خط السير المخطَّط'],
                    (int) $row->added_by === (int) $user->id => ['tone' => 'self', 'label' => 'أضفتَه أنت لخط سيرك'],
                    default => ['tone' => 'other', 'label' => 'أضافه: '.($by['name'] ?? '—').(! empty($by['role']) ? ' · '.$by['role'] : '')],
                };
            }
        }

        $markers = $clients->map(fn (Client $c) => ClientResource::summary($c, [
            'branch_path' => $c->branchTreeDisplayPaths()['main'] ?? $c->branch?->name,
            'needs_visit' => $visitState['must_visit'] && ! isset($visitState['completed'][$c->id]),
            'open_visit_id' => $visitState['open'][$c->id] ?? null,
            'itinerary_source' => $sources[$c->id] ?? null,
        ]))->values();

        return $this->ok(['clients' => $markers, 'total' => $markers->count(), 'itinerary_only' => $itineraryMode]);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();

        if (! $this->visibility->canAccess($user, $client->id)) {
            return $this->fail('لا تملك صلاحية الوصول إلى هذا العميل.', 403);
        }

        /* «الزيارة قبل العرض» — نفس `show()` في الموقع: من يُلزَم بها لا يرى
           ملفّ العميل؛ يُوجَّه إلى زيارته (المفتوحة أو الجديدة). */
        if (hasPermission('clients_must_visit_before_view') && ! hasPermission('clients_exempt_visit_before_view')) {
            if (EmployeeClientVisit::hiddenFinishedClientIdsFor((int) $user->id)->contains($client->id)) {
                return $this->fail('انتهت زيارة هذا العميل اليوم. لفتحه من جديد أرجِعه لخط سيرك أو سجّل له زيارة في يومٍ آخر.', 403, ['code' => 'VISIT_FINISHED_TODAY']);
            }

            $openVisitId = Visit::where('employee_id', $user->id)->where('client_id', $client->id)
                ->whereDate('visit_date', now()->toDateString())->whereNull('departure_time')->value('id');

            $canVisit = hasPermission(['clients_visits_track', 'clients_itinerary_add', 'clients_visit_workspace_access', 'clients_must_visit_before_view']);

            if ($openVisitId || $canVisit) {
                return $this->fail('يجب تسجيل زيارة هذا العميل قبل عرض ملفّه — افتح الزيارة.', 403, [
                    'code' => 'MUST_VISIT_FIRST',
                    'data' => ['client_id' => $client->id, 'open_visit_id' => $openVisitId],
                ]);
            }
        }

        $client->load(array_merge(self::WITH, ['categoriesClient', 'registrationStage', 'contacts', 'employees']));
        Branch::preloadCache();
        $this->attachDue(collect([$client]));

        return $this->ok((new ClientResource($client))->resolve() + [
            'can' => [
                'edit' => hasPermission('clients_edit_data'),
                'change_status' => hasPermission('clients_change_status'),
                'add_note' => hasPermission(['client_follow_up_add_notes_attachments_appointments_all', 'client_follow_up_add_notes_attachments_appointments_assigned', 'client_follow_up_add_notes_attachments_appointments_own', 'clients_add_employee_note']),
                'view_notes' => hasPermission(['client_follow_up_view_notes_attachments_appointments_all', 'client_follow_up_view_notes_attachments_appointments_assigned', 'client_follow_up_view_notes_attachments_appointments_own']),
                'open_visit' => hasPermission(['clients_visits_track', 'clients_itinerary_add', 'clients_visit_workspace_access', 'clients_must_visit_before_view']),
            ],
        ]);
    }

    /** قوائم نموذج الإضافة/التعديل — `client_id` لتمهيد نموذج تعديل عميلٍ بعينه. */
    public function options(Request $request): JsonResponse
    {
        $client = null;

        if ($request->filled('client_id')) {
            $client = Client::with('employees')->findOrFail($request->integer('client_id'));

            if (! $this->visibility->canAccess($request->user(), $client->id)) {
                return $this->fail('لا تملك صلاحية الوصول إلى هذا العميل.', 403);
            }
        }

        return $this->ok(ClientForm::options($request->user(), $client));
    }

    public function store(ClientRequest $request): JsonResponse
    {
        try {
            $result = $this->writer->store($request, $request->user());
        } catch (\Throwable $e) {
            OperationLog::failure($e, ClientWriter::LOG_MODULE, 'integration.store');

            return $this->fail('تعذّر حفظ العميل — لم يُحفظ شيء. حاول مرّةً أخرى.', 500);
        }

        $client = $result['client']->fresh(self::WITH);
        $this->attachDue(collect([$client]));

        return $this->ok(ClientResource::summary($client, ['visit_id' => $result['visit_id']]), $result['message'], 201);
    }

    public function update(ClientRequest $request, Client $client): JsonResponse
    {
        if (! $this->visibility->canAccess($request->user(), $client->id)) {
            return $this->fail('لا تملك صلاحية الوصول إلى هذا العميل.', 403);
        }

        try {
            $client = $this->writer->update($request, $client->load('employees'), $request->user());
        } catch (\Throwable $e) {
            OperationLog::failure($e, ClientWriter::LOG_MODULE, 'integration.update', $client);

            return $this->fail('تعذّر تعديل العميل — لم يُحفظ شيء. حاول مرّةً أخرى.', 500);
        }

        $client = $client->fresh(self::WITH);
        $this->attachDue(collect([$client]));

        return $this->ok(ClientResource::summary($client), 'تم تحديث بيانات العميل بنجاح.');
    }

    // ═══════════════════════ داخلي ═══════════════════════

    private function validateListFilters(Request $request): void
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'branch_id' => 'nullable|integer|min:1',
            'region' => 'nullable|integer|min:1',
            'client' => 'nullable|integer|min:1',
            'status' => 'nullable',
            'registration_stage' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'type' => 'nullable|integer|in:1,2',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude',
            'sort' => 'nullable|in:newest,name',
            'last_invoice_period' => 'nullable|in:today,week,two_weeks,month,three_months,six_months,year,more_than_year',
            'last_payment_period' => 'nullable|in:today,week,two_weeks,month,three_months,six_months,year,more_than_year',
            'last_activity_period' => 'nullable|in:today,week,two_weeks,month,three_months,six_months,year,more_than_year',
        ]);
    }

    private function wantsDescendants(Request $request): bool
    {
        return ! in_array((string) $request->input('include_descendants', '1'), ['0', 'false', 'off'], true);
    }

    /** فلاتر شاشة العملاء (`applyFilters()`) + `search` بالاسم أو الكود أو الهاتف. */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
            $query->where(fn ($q) => $q->where('trade_name', 'like', $term)
                ->orWhere('code', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('mobile', 'like', $term));
        }
        if ($request->filled('client')) {
            $query->where('id', $request->integer('client'));
        }
        if ($request->filled('name')) {
            $query->where('trade_name', 'like', '%'.addcslashes((string) $request->input('name'), '%_\\').'%');
        }
        if ($request->filled('status')) {
            $request->input('status') === 'no_status'
                ? $query->whereNull('status_id')
                : $query->where('status_id', (int) $request->input('status'));
        }
        if ($request->filled('registration_stage')) {
            $query->where('registration_stage_id', $request->integer('registration_stage'));
        }
        if ($request->filled('region')) {
            $query->inTreeNode($request->integer('region'), $this->wantsDescendants($request));
        }
        if ($request->filled('neighborhood')) {
            $term = (string) $request->input('neighborhood');
            $query->whereHas('Neighborhoodname', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('id', (int) $term));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('type')) {
            $query->where('client_type', $request->integer('type'));
        }
        if ($request->filled('employee')) {
            $query->where('employee_id', $request->integer('employee'));
        }
        if ($request->boolean('over_credit_limit')) {
            $query->whereNotNull('credit_limit')->where('credit_limit', '>', 0)
                ->whereHas('account', fn ($q) => $q->ledger()->whereColumn('accounts.balance', '>', 'clients.credit_limit'));
        }
        if ($request->filled('customer_target_id')) {
            $request->input('customer_target_id') === 'no_target'
                ? $query->whereNull('customer_target_id')
                : $query->where('customer_target_id', $request->integer('customer_target_id'));
        }
        if ($request->filled('last_invoice_period')) {
            [$from, $to] = $this->period($request->input('last_invoice_period'));
            $query->whereHas('invoices', fn ($q) => $q->whereBetween('invoice_date', [$from, $to]));
        }
        if ($request->filled('last_payment_period')) {
            [$from, $to] = $this->period($request->input('last_payment_period'));
            $query->whereHas('invoices.payments', fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('type', 'client payments'));
        }
        if ($request->filled('last_activity_period')) {
            [$from, $to] = $this->period($request->input('last_activity_period'));
            $query->whereIn('id', function ($sub) use ($from, $to) {
                $sub->select('client_id')->from(function ($inner) {
                    $inner->select('invoices.client_id', 'invoices.invoice_date as date')->from('invoices')
                        ->unionAll(DB::table('payments_process')->join('invoices', 'payments_process.invoice_id', '=', 'invoices.id')->select('invoices.client_id', 'payments_process.created_at as date'))
                        ->unionAll(DB::table('accounts')->join('receipts', 'accounts.id', '=', 'receipts.account_id')->select('accounts.client_id', 'receipts.created_at as date'));
                }, 'activities')->groupBy('client_id')->havingRaw('MAX(activities.date) BETWEEN ? AND ?', [$from, $to]);
            });
        }
    }

    /** فترات «آخر فاتورة/دفعة/نشاط» — نفس `getPeriodDates()` في الموقع. */
    private function period(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'two_weeks' => [$now->copy()->subDays(14)->startOfDay(), $now->copy()->subDays(8)->endOfDay()],
            'month' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->subDays(15)->endOfDay()],
            'three_months' => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->subDays(31)->endOfDay()],
            'six_months' => [$now->copy()->subDays(180)->startOfDay(), $now->copy()->subDays(91)->endOfDay()],
            'year' => [$now->copy()->subDays(365)->startOfDay(), $now->copy()->subDays(181)->endOfDay()],
            default => [$now->copy()->subYears(100), $now->copy()->subDays(365)->endOfDay()],
        };
    }

    /** «المستحق» لكل عميل — `Account::ledger()` باستعلامٍ واحد للصفحة. */
    private function attachDue(Collection $clients): void
    {
        if ($clients->isEmpty()) {
            return;
        }

        $due = Account::ledger()->whereIn('client_id', $clients->pluck('id'))
            ->selectRaw('client_id, SUM(balance) as total_due')->groupBy('client_id')->pluck('total_due', 'client_id');

        foreach ($clients as $client) {
            $client->setAttribute('due_balance', (float) ($due[$client->id] ?? 0));
        }
    }

    /**
     * «الزيارة قبل العرض» لكل صفّ — نفس حساب الخريطة والجدول في الموقع:
     * من يُلزَم بها يحتاج زيارةً ما لم تكتمل زيارته اليوم (والعودة المجدولة
     * تُعيد الحاجة)، ومعها رقم الزيارة المفتوحة إن وُجدت.
     */
    private function visitState(Collection $clients, int $userId): array
    {
        $mustVisit = hasPermission('clients_must_visit_before_view') && ! hasPermission('clients_exempt_visit_before_view');
        $completed = [];
        $open = [];

        if ($clients->isNotEmpty()) {
            $visits = Visit::where('employee_id', $userId)
                ->whereIn('client_id', $clients->pluck('id'))
                ->whereDate('visit_date', now()->toDateString())
                ->get(['id', 'client_id', 'stage', 'departure_time']);

            foreach ($visits as $visit) {
                if ($visit->stage === Visit::STAGE_COMPLETED) {
                    $completed[$visit->client_id] = true;
                } elseif ($visit->departure_time === null && ! isset($open[$visit->client_id])) {
                    $open[$visit->client_id] = (int) $visit->id;
                }
            }

            foreach (EmployeeClientVisit::pendingItineraryClientIdsFor($userId) as $pendingId) {
                unset($completed[$pendingId]);
            }

            $open = array_diff_key($open, $completed);
        }

        return ['must_visit' => $mustVisit, 'completed' => $completed, 'open' => $open];
    }

    private function distanceKm(Client $client, $lat, $lng): ?float
    {
        $location = $client->locations;

        if (! is_numeric($lat) || ! is_numeric($lng) || ! $location || ! $location->latitude || ! $location->longitude) {
            return null;
        }

        return round(GeoDistance::metersBetween((float) $lat, (float) $lng, (float) $location->latitude, (float) $location->longitude) / 1000, 2);
    }
}
