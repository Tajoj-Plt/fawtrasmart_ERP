<?php

namespace Modules\Integration\Http\Resources\Clients;

use App\Support\CurrencySettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * العميل في التطبيق. `summary()` لصفّ القائمة والخريطة، و`toArray()`
 * للتفصيل (ويُملأ من `show` بما يخصّه: جهات الاتصال، الموظفون…).
 *
 * `balance` رقم «المستحق» نفسه في شاشة العملاء — مجموع `Account::ledger()`
 * للعميل (موجبٌ = مدين) — يُحسب في المتحكّم مرّةً للصفحة ويُمرَّر هنا.
 */
class ClientResource extends JsonResource
{
    public static function summary($client, array $extra = []): array
    {
        $location = $client->locations;
        $due = (float) ($client->due_balance ?? 0);

        return [
            'id' => (int) $client->id,
            'code' => $client->code,
            'trade_name' => $client->trade_name,
            'phone' => $client->phone,
            'mobile' => $client->mobile,
            'status' => $client->status_client ? [
                'id' => (int) $client->status_client->id,
                'name' => $client->status_client->name,
                'color' => $client->status_client->color,
            ] : null,
            'branch' => $client->branch ? ['id' => (int) $client->branch->id, 'name' => $client->branch->name] : null,
            'neighborhood' => $client->neighborhood?->name,
            'location' => ($location && $location->latitude && $location->longitude) ? [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
            ] : null,
            'balance' => round($due, 2),
            'balance_formatted' => CurrencySettings::format(abs($due)),
            'balance_direction' => $due > 0 ? 'debit' : ($due < 0 ? 'credit' : null),
        ] + $extra;
    }

    public function toArray(Request $request): array
    {
        $client = $this->resource;

        return self::summary($client) + [
            'display_name' => $client->display_name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'client_type' => $client->client_type !== null ? (int) $client->client_type : null,
            'tax_number' => $client->tax_number,
            'commercial_registration' => $client->commercial_registration,
            'currency' => $client->currency,
            'printing_method' => $client->printing_method !== null ? (int) $client->printing_method : null,
            'item_quantity_limit' => $client->item_quantity_limit,
            'credit_limit' => $client->credit_limit,
            'notes' => $client->notes,
            'street1' => $client->street1,
            'street2' => $client->street2,
            'city' => $client->city,
            'region' => $client->region,
            'postal_code' => $client->postal_code,
            'country' => $client->country,
            'category' => $client->categoriesClient ? ['id' => (int) $client->categoriesClient->id, 'name' => $client->categoriesClient->name] : null,
            'registration_stage' => $client->registrationStage ? ['id' => (int) $client->registrationStage->id, 'name' => $client->registrationStage->name] : null,
            'tree_node_id' => $client->tree_node_id ? (int) $client->tree_node_id : null,
            'branch_path' => $client->branchTreeDisplayPaths()['main'] ?? $client->branch?->name,
            'attachment_url' => $client->attachments ? asset('assets/uploads/'.$client->attachments) : null,
            'assigned_employees' => $this->whenLoaded('employees', fn () => $client->employees->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => trim($e->first_name.' '.$e->middle_name),
            ])->values()),
            'contacts' => $this->whenLoaded('contacts', fn () => $client->contacts->map(fn ($c) => [
                'id' => (int) $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'mobile' => $c->mobile,
            ])->values()),
            'created_at' => $client->created_at?->toDateTimeString(),
        ];
    }
}
