<?php

namespace Modules\Integration\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Helpers\WeekHelper;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\EmployeeClientVisit;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Visit;
use App\Services\PointsService;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integration\Http\Requests\Clients\ClientRequest;

/**
 * حفظ العميل من التطبيق — نفس خطوات `ClientController::store()/update()` في
 * الموقع بترتيبها وداخل معاملةٍ واحدة: الرقم المتسلسل، التبعيّة في الشجرة
 * والفرع المحاسبي، جهة الاتصال الأساسية، الموظفون المسؤولون، الموقع والحيّ،
 * حساب دخول العميل، حسابه في شجرة الحسابات، ونقاط الموظف.
 *
 * الفرق الوحيد المقصود: يُملأ العميل من الحقول المتحقَّق منها فقط (قائمة
 * `FIELDS`) لا من الطلب كلّه — كي لا يكتب طلبٌ مصنوع حالةَ العميل أو كلمة
 * مروره أو رقمه.
 */
class ClientWriter
{
    public const LOG_MODULE = 'Clients';

    /** حقول نموذج الموقع التي يكتبها التطبيق — لا غيرها. */
    private const FIELDS = [
        'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'email',
        'street1', 'street2', 'city', 'region', 'postal_code', 'country', 'currency',
        'tax_number', 'commercial_registration', 'category_id', 'client_type',
        'registration_stage_id', 'printing_method', 'notes', 'item_quantity_limit',
    ];

    private const CONTACT_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'mobile'];

    /**
     * @return array{client: Client, visit_id: int|null, message: string}
     */
    public function store(ClientRequest $request, User $user): array
    {
        $data = $request->validated();
        $node = Branch::withoutGlobalScopes()->findOrFail((int) $data['client_tree_branch_id']);
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        // من يملك «بدء زيارة عند الإضافة» ويستطيع دخول مساحة الزيارة — نفس شرط الموقع
        $startVisit = hasPermission('clients_start_visit_on_create')
            && hasPermission(['clients_visits_track', 'clients_itinerary_add', 'clients_visit_workspace_access', 'clients_must_visit_before_view']);

        $client = DB::transaction(function () use ($request, $data, $node, $latitude, $longitude, $user, $startVisit) {
            $client = new Client;
            $client->status_id = ClientSettingsHelper::defaultNewClientStatusId();
            $client->fill($this->clientData($data, $node));
            $client->registration_stage_id = ! empty($data['registration_stage_id'])
                ? (int) $data['registration_stage_id']
                : ClientSettingsHelper::defaultRegistrationStageId();
            $client->code = next_document_number('customer');

            if ($file = $request->file('attachments')) {
                $client->attachments = $this->storeAttachment($file);
            }

            $client->save();

            $client->contacts()->create([
                'first_name' => $client->trade_name,
                'phone' => $client->phone,
                'mobile' => $client->mobile,
                'email' => $client->email,
            ]);

            if (hasPermission('clients_assign_employees')) {
                foreach (array_unique((array) ($data['employee_client_id'] ?? [])) as $employeeId) {
                    ClientEmployee::create(['client_id' => $client->id, 'employee_id' => (int) $employeeId]);
                }
            } elseif ($user->isEmployee()) {
                ClientEmployee::create(['client_id' => $client->id, 'employee_id' => $user->employee_id]);
            }

            $client->locations()->create(['latitude' => $latitude, 'longitude' => $longitude]);

            Neighborhood::create([
                'name' => $data['neighborhood_name_manual'] ?? $this->neighborhoodFromGoogle($latitude, $longitude) ?? 'غير محدد',
                'region_id' => $node->resolveTerritoryBranchForNeighborhood()->id,
                'client_id' => $client->id,
            ]);

            if (! empty($data['email'])) {
                User::create([
                    'name' => trim($client->trade_name.' '.$client->first_name.' '.$client->last_name),
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'role' => 'client',
                    'client_id' => $client->id,
                    'password' => Hash::make(Str::random(16)),
                ]);
            }

            OperationLog::record('created', self::LOG_MODULE, $client, 'إضافة عميل «'.$client->trade_name.'» — من تطبيق الجوال', [
                'new' => $client->only(['code', 'trade_name', 'phone', 'status_id', 'branch_id']),
            ]);

            $this->ensureLedgerAccount($client);

            foreach ((array) ($data['contacts'] ?? []) as $contact) {
                $client->contacts()->create(array_intersect_key((array) $contact, array_flip(self::CONTACT_FIELDS)));
            }

            if ($startVisit) {
                $now = now();
                $week = WeekHelper::getWeekAndYear($now);
                EmployeeClientVisit::updateOrCreate(
                    [
                        'employee_id' => $user->id,
                        'client_id' => $client->id,
                        'day_of_week' => strtolower(WeekHelper::getCurrentDayNameEn($now)),
                        'year' => $week['year'],
                        'week_number' => $week['week'],
                    ],
                    ['visit_date' => $now->toDateString(), 'status' => 'active'],
                );
            }

            try {
                $pointsEmployee = $user->employee_id ?? $user->id;
                foreach (['new_client_acquired', 'profile_completed'] as $action) {
                    app(PointsService::class)->processAction($action, [
                        'employee_id' => $pointsEmployee,
                        'client_id' => $client->id,
                        'amount' => 0,
                        'branch_id' => $client->branch_id,
                        'reference_type' => get_class($client),
                        'reference_id' => $client->id,
                    ]);
                }
            } catch (\Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'points_on_create');
            }

            return $client;
        });

        // الزيارة بعد الحفظ لا داخله: تعثّرُها لا يُسقط عميلاً حُفظ للتوّ
        $visitId = null;
        if ($startVisit) {
            try {
                $visitId = Visit::create([
                    'employee_id' => $user->id,
                    'client_id' => $client->id,
                    'visit_date' => now(),
                    'visit_number' => next_document_number('visit'),
                    'client_latitude' => $latitude,
                    'client_longitude' => $longitude,
                    'recording_method' => 'manual',
                    'is_approved' => false,
                    'stage' => Visit::STAGE_ARRIVED,
                ])->id;

                OperationLog::record('visit_registered', self::LOG_MODULE, $client, 'تسجيل زيارة فور إضافة العميل «'.$client->trade_name.'» — من تطبيق الجوال');
            } catch (\Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'start_visit_on_create', $client);
            }
        }

        $message = match (true) {
            $visitId !== null => 'تمت إضافة العميل وفتح زيارته — أضِف مسؤول العميل لتبدأ.',
            $startVisit => 'تمت إضافة العميل، لكن تعذّر فتح زيارته — افتحها من زرّ الزيارة.',
            default => 'تمت إضافة العميل بنجاح.',
        };

        return ['client' => $client, 'visit_id' => $visitId, 'message' => $message];
    }

    public function update(ClientRequest $request, Client $client, User $user): Client
    {
        $data = $request->validated();
        $node = Branch::withoutGlobalScopes()->findOrFail((int) $data['client_tree_branch_id']);

        return DB::transaction(function () use ($request, $data, $node, $client, $user) {
            $old = $client->getOriginal();
            $fields = $this->clientData($data, $node);

            if (hasPermission('clients_assign_employees')) {
                if (array_key_exists('employee_client_id', $data)) {
                    ClientEmployee::where('client_id', $client->id)->delete();
                    foreach (array_unique((array) $data['employee_client_id']) as $employeeId) {
                        ClientEmployee::create(['client_id' => $client->id, 'employee_id' => (int) $employeeId]);
                    }
                }
            } elseif ($user->isEmployee()) {
                ClientEmployee::firstOrCreate(['client_id' => $client->id, 'employee_id' => $user->employee_id]);
            }

            if ($file = $request->file('attachments')) {
                $previous = $client->attachments;
                $fields['attachments'] = $this->storeAttachment($file);
                MediaStore::delete('assets/uploads', $previous);
            }

            if (array_key_exists('registration_stage_id', $fields) && $fields['registration_stage_id'] === '') {
                $fields['registration_stage_id'] = null;
            }

            $client->update($fields);
            $this->ensureLedgerAccount($client)?->update(['name' => $client->trade_name]);

            // الموقع يُستبدل حين يُرسَل فقط — لا يُمسح موقعٌ مسجَّل لأن الطلب لم يحمله
            $latitude = $data['latitude'] ?? null;
            $longitude = $data['longitude'] ?? null;
            $moved = $latitude !== null && $longitude !== null;

            if ($moved) {
                $client->locations()->delete();
                $client->locations()->create(['latitude' => $latitude, 'longitude' => $longitude, 'client_id' => $client->id]);
            }

            $neighborhood = Neighborhood::where('client_id', $client->id)->first();
            if ($moved || ! empty($data['neighborhood_name_manual']) || ! $neighborhood || (int) $neighborhood->region_id !== (int) $node->resolveTerritoryBranchForNeighborhood()->id) {
                $name = $data['neighborhood_name_manual']
                    ?? ($moved ? $this->neighborhoodFromGoogle($latitude, $longitude) : null)
                    ?? $neighborhood?->name
                    ?? 'غير محدد';

                Neighborhood::updateOrCreate(
                    ['client_id' => $client->id],
                    ['name' => $name, 'region_id' => $node->resolveTerritoryBranchForNeighborhood()->id]
                );
            }

            if (! empty($data['email'])) {
                $account = User::where('client_id', $client->id)->first();
                $payload = [
                    'name' => implode(' ', array_filter([$client->trade_name, $client->first_name, $client->last_name])),
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                ];

                $account
                    ? $account->update($payload)
                    : User::create($payload + ['password' => Hash::make(Str::random(16)), 'role' => 'client', 'client_id' => $client->id]);
            }

            if (array_key_exists('contacts', $data)) {
                $existing = $client->contacts()->get()->keyBy('id');
                $incoming = collect((array) $data['contacts']);
                $keep = $incoming->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

                $client->contacts()->whereNotIn('id', $keep ?: [0])->delete();

                foreach ($incoming as $contact) {
                    $values = array_intersect_key((array) $contact, array_flip(self::CONTACT_FIELDS));
                    $id = (int) ($contact['id'] ?? 0);

                    $id && $existing->has($id)
                        ? $existing[$id]->update($values)
                        : $client->contacts()->create($values);
                }
            }

            OperationLog::record('updated', self::LOG_MODULE, $client, 'تحديث بيانات العميل «'.$client->trade_name.'» — من تطبيق الجوال', [
                'old' => array_intersect_key($old, array_flip(array_merge(self::FIELDS, ['tree_node_id', 'branch_id']))),
                'new' => $client->only(array_merge(self::FIELDS, ['tree_node_id', 'branch_id'])),
            ]);

            return $client;
        });
    }

    /** الحقول المسموحة + التبعيّة في الشجرة والفرع المحاسبي المشتقّ منها. */
    private function clientData(array $data, Branch $node): array
    {
        $fields = array_intersect_key($data, array_flip(self::FIELDS));
        $fields['tree_node_id'] = (int) $node->id;
        $fields['branch_id'] = $node->resolveAccountingBranchId() ?? (int) $node->id;

        return $fields;
    }

    private function storeAttachment(UploadedFile $file): string
    {
        $name = time().'_'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $file->move(public_path('assets/uploads'), $name);

        return $name;
    }

    /** اسم الحيّ من إحداثيّاته — مهلةٌ قصيرة كي لا يعلق الحفظ على خدمةٍ خارجية. */
    private function neighborhoodFromGoogle($latitude, $longitude): ?string
    {
        $key = config('services.google_maps.key');

        if (! $key || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        try {
            $results = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude, 'key' => $key, 'language' => 'ar',
            ])->json('results.0.address_components', []);

            foreach ((array) $results as $component) {
                if (array_intersect(['sublocality', 'neighborhood'], $component['types'] ?? [])) {
                    return $component['long_name'] ?? null;
                }
            }
        } catch (\Throwable) {
            // الحيّ تفصيلٌ لا يُسقط الحفظ
        }

        return null;
    }

    /** حساب العميل في شجرة الحسابات — `ensureClientLedgerAccount()` بالحرف (الرصيد الافتتاحي صفر). */
    private function ensureLedgerAccount(Client $client): ?Account
    {
        if ($existing = Account::ledger()->where('client_id', $client->id)->first()) {
            return $existing;
        }

        $parent = AccountingSettings::account('clients_account');
        if (! $parent) {
            return null;
        }

        $last = Account::where('parent_id', $parent->id)->orderBy('code', 'desc')->first();
        $code = $last ? $this->nextCode($last->code) : $parent->code.'1';
        while (Account::where('code', $code)->exists()) {
            $code = $this->nextCode($code);
        }

        $account = new Account;
        $account->name = $client->trade_name;
        $account->client_id = $client->id;
        $account->account_type = $parent->account_type;
        $account->code = $code;
        $account->balance_type = 'debit';
        $account->parent_id = $parent->id;
        $account->opening_balance = 0;
        $account->opening_balance_type = $parent->balance_type;
        $account->is_active = true;
        $account->save();

        return $account;
    }

    private function nextCode(string $code): string
    {
        return substr($code, 0, -1).((int) substr($code, -1) + 1);
    }
}
