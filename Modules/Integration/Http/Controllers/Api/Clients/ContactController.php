<?php

namespace Modules\Integration\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Controllers\Api\Concerns\RespondsJson;
use Modules\Integration\Services\Clients\ClientVisibility;
use Modules\Integration\Services\Clients\ContactService;

/**
 * مسؤولو العميل (موظفو المحلّ) — عرضٌ وإضافة، بنفس صلاحيات «ملاحظات
 * الموظفين» في ملفّ العميل بالموقع، ومحصوراً بعملاء نطاقه.
 */
class ContactController extends Controller
{
    use RespondsJson;

    public function __construct(private readonly ClientVisibility $visibility) {}

    public function index(Request $request, Client $client): JsonResponse
    {
        abort_unless($this->visibility->canAccess($request->user(), $client->id), 403, 'لا تملك صلاحية الوصول إلى هذا العميل.');

        return $this->ok(ContactService::listFor($client->id));
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        abort_unless($this->visibility->canAccess($request->user(), $client->id), 403, 'لا تملك صلاحية الوصول إلى هذا العميل.');

        $contact = ContactService::create($request, $client->id, $request->user(), 'Clients');

        return $this->ok(ContactService::present($contact), 'تمت إضافة مسؤول العميل.', 201);
    }
}
