<?php

namespace Modules\Integration\Http\Controllers\Mobile;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\InvoiceTransfer;
use App\Models\User;
use App\Services\Clients\InvoiceTransferRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Client\Http\Controllers\InvoiceTransferController;
use Symfony\Component\HttpFoundation\Response;

/**
 * «تسليم واستلام الفواتير» لتطبيق الجوال — **شاشة الويب نفسها بياناتٍ لا صفحة**.
 *
 * كلُّ ما في الشاشة (القوائم الثلاث، الدفعات المنتظرة، أيام خطّ السير، زملاء الفرع ومن غاب منهم،
 * خيارات الفلاتر) يحسبه `InvoiceTransferController::index()` ثم يسلّمه لقالب بلايد. هنا تُستدعى
 * `index()` نفسها ويُقرأ ما سلّمته للقالب (`View::getData()`) فيُعاد JSON — فلا تُكرَّر قاعدةٌ
 * واحدة من قواعد العهدة والفرع والنطاق. والإجراءات (تسليم، استلام، رفض، إلغاء) مسارات
 * `routes/mobile_api.php` إلى دوالّ المتحكّم نفسه كما هي.
 */
class MobileInvoiceTransfersController extends Controller
{
    public function overview(Request $request): JsonResponse|Response
    {
        $out = app(InvoiceTransferController::class)->index($request);

        // القسم معطَّل في الإعدادات: تحويلٌ إلى الرئيسية برسالة — يترجمه `MobileApiContext` إلى 422
        if (! $out instanceof View) {
            return $out;
        }

        $d = $out->getData();
        $me = auth()->user();
        $canRespond = hasPermission('clients_invoice_transfer_respond');
        $canCancel = hasPermission('clients_invoice_transfer_cancel');
        $actsOnBehalf = $me->hasRoleName('admin') || $me->hasRoleName('manager');

        // أزرار كلّ صفٍّ كما يحسبها `_table.blade.php`
        $row = fn (InvoiceTransfer $t) => [
            'id' => (int) $t->id,
            'batch' => $t->batch,
            'kind' => $t->kind,
            'direction' => $t->direction,
            'isRequest' => $t->isRequest(),
            'status' => $t->status,
            'statusText' => $t->status_text,
            'directionText' => $t->direction_text,
            'subject' => $t->subject_label,
            'invoiceId' => $t->invoice_id ? (int) $t->invoice_id : null,
            'client' => $t->client ? ['id' => (int) $t->client->id, 'name' => $t->client->trade_name, 'code' => $t->client->code] : null,
            'from' => $t->fromEmployee ? ['id' => (int) $t->fromEmployee->id, 'name' => $t->fromEmployee->name] : null,
            'to' => $t->toEmployee ? ['id' => (int) $t->toEmployee->id, 'name' => $t->toEmployee->name] : null,
            'amount' => $t->invoice_amount !== null ? (float) $t->invoice_amount : null,
            'due' => $t->invoice ? (float) $t->invoice->due_value : null,
            'sentAt' => optional($t->sent_at ?? $t->created_at)->toDateTimeString(),
            'respondedAt' => optional($t->responded_at)->toDateTimeString(),
            'senderNotes' => $t->sender_notes,
            'receiverNotes' => $t->receiver_notes,
            'canRespond' => $t->isPending() && $canRespond && ($t->responderId() === (int) $me->id || $actsOnBehalf),
            'canCancel' => $t->isPending() && $canCancel && $t->initiatorId() === (int) $me->id,
        ];

        // «بانتظار استلامك» دفعاتٍ كما في الشاشة: من سلّم، لأيّ عملاء، ومجموع المستحقّ أو الرصيد
        $batches = collect($d['awaitingBatches'])->map(function ($rows, $key) use ($row) {
            $first = $rows->first();
            $balance = $rows->every(fn ($t) => $t->isBalance());

            return [
                'key' => (string) $key,
                'batch' => $first->batch,
                'from' => $first->fromEmployee?->name,
                'kind' => $balance ? 'balance' : 'invoice',
                'count' => $rows->count(),
                'clients' => $rows->pluck('client')->filter()->unique('id')->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->trade_name, 'code' => $c->code])->values(),
                'sentAt' => optional($first->sent_at ?? $first->created_at)->toDateTimeString(),
                'reason' => $first->sender_notes,
                'total' => round((float) $rows->sum(fn ($t) => $t->isBalance() ? (float) $t->invoice_amount : (float) ($t->invoice->due_value ?? $t->invoice_amount)), 2),
                'rows' => $rows->map($row)->values(),
            ];
        })->values();

        $sent = collect($d['sentTransfers']);
        $received = collect($d['receivedTransfers']);
        $mine = $sent->concat($received)->unique('id');

        return response()->json(['success' => true, 'data' => [
            'awaiting' => $batches,
            'received' => $received->map($row)->values(),
            'sent' => $sent->map($row)->values(),
            'all' => collect($d['allTransfers'])->map($row)->values(),
            'canSeeAll' => (bool) $d['canSeeAll'],
            'stats' => [
                'awaiting' => (int) $d['pendingReceivedCount'],
                'sentPending' => $sent->where('status', 'pending')->count(),
                'accepted' => $mine->where('status', 'accepted')->count(),
                'rejected' => $mine->where('status', 'rejected')->count(),
            ],
            'filters' => $d['filters'],
            'clientOptions' => collect($d['clientOptions'])->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->trade_name, 'code' => $c->code])->values(),
            'employeeOptions' => collect($d['employeeOptions'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values(),
            // نافذة التسليم
            'itineraryDays' => collect($d['itineraryDays'])->map(function ($day) {
                $date = Carbon::parse($day['date']);

                return [
                    'date' => $day['date'],
                    'clients' => (int) $day['clients'],
                    'weekday' => $date->locale('ar')->translatedFormat('l'),
                    'relative' => $date->isToday() ? 'today' : ($date->isTomorrow() ? 'tomorrow' : ($date->isPast() ? 'past' : null)),
                ];
            })->values(),
            'defaultSource' => $d['defaultSource'],
            'branchMates' => collect($d['branchMates'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values(),
            'excludedMates' => collect($d['excludedMates'])->map(fn ($people, $why) => ['why' => $why, 'names' => $people->pluck('name')->values()])->values(),
            'settings' => [
                'requiresApproval' => ClientSettingsHelper::invoiceTransferRequiresApproval(),
                'reasonRequired' => ClientSettingsHelper::invoiceTransferReasonRequired(),
                'allowPaid' => ClientSettingsHelper::invoiceTransferAllowPaid(),
                'maxOpen' => ClientSettingsHelper::invoiceTransferMaxOpenPerEmployee(),
            ],
            'can' => [
                'send' => hasPermission('clients_invoice_transfer_send'),
                'respond' => $canRespond,
                'cancel' => $canCancel,
                'actsOnBehalf' => $actsOnBehalf,
            ],
        ]]);
    }

    /**
     * تبويب «تسليم واستلام الفواتير» في مساحة الزيارة لعميلٍ واحد — حساباتُ
     * `client::visit_workspace.tab-invoice-transfers` نفسها (ودوالّ `InvoiceTransferRules` نفسها):
     *   ① ما سُلِّم لي لهذا العميل وينتظر ردّي، ② رصيدُ حسابه ولمن هو الآن، ③ فواتيرُه المستحقّة
     *   بعهدة كلٍّ منها وطلبِها المعلّق — ومستلِمو فرع العميل لنافذتي التسليم.
     * القالب يحسبها في `@php`، فتُعاد هنا سطراً بسطر؛ أيُّ تعديلٍ في أحدهما يلزمه الآخر.
     */
    public function client(int $clientId): JsonResponse|Response
    {
        if ($block = $this->sectionBlock()) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $user = auth()->user();
        $client = Client::findOrFail($clientId);
        $canSend = hasPermission('clients_invoice_transfer_send');
        $canRespond = hasPermission('clients_invoice_transfer_respond');
        $canCancel = hasPermission('clients_invoice_transfer_cancel');
        $actsOnBehalf = $user->hasRoleName('admin') || $user->hasRoleName('manager');

        $invoices = InvoiceTransferRules::outstandingForClient($user, $clientId);
        $allRecipients = $canSend
            ? InvoiceTransferRules::recipientsForClients($user, [$clientId])->where('in_branch', true)
            : collect();
        $branchId = InvoiceTransferRules::clientBranches([$clientId])[$clientId] ?? null;

        // ① بانتظار استلامي لهذا العميل
        $awaiting = InvoiceTransfer::with(['invoice:id,code,due_value', 'fromEmployee:id,name', 'toEmployee:id,name'])
            ->where('client_id', $clientId)->where('status', 'pending')
            ->respondableBy($user->id)->latest('id')->get();

        // ② الرصيد ولمن هو الآن: تسليمٌ معلّق، أو آخرُ من استلمه، أو مسؤولُ العميل
        $ledger = (float) (InvoiceTransferRules::ledgerBalances([$clientId])[$clientId] ?? 0);
        $myBalance = $canSend ? InvoiceTransferRules::balanceClientsOf($user, [$clientId])->first() : null;
        $balancePending = InvoiceTransfer::with('toEmployee:id,name')
            ->where('client_id', $clientId)->where('kind', 'balance')->where('status', 'pending')->latest('id')->first();
        $balanceHolder = InvoiceTransfer::with('toEmployee:id,name')
            ->where('client_id', $clientId)->where('kind', 'balance')->where('status', 'accepted')
            ->latest('responded_at')->latest('id')->first()?->toEmployee?->name
            ?? User::whereIn('employee_id', DB::table('client_employee')->where('client_id', $clientId)->pluck('employee_id'))
                ->orderBy('id')->value('name');

        $holder = match (true) {
            $balancePending && (int) $balancePending->to_employee_id === (int) $user->id => ['state' => 'pending_to_me', 'name' => null],
            (bool) $balancePending => ['state' => 'pending', 'name' => $balancePending->toEmployee->name ?? null],
            (bool) $myBalance => ['state' => 'mine', 'name' => null],
            default => ['state' => 'other', 'name' => $balanceHolder],
        };

        return response()->json(['success' => true, 'data' => [
            'client' => ['id' => (int) $client->id, 'name' => $client->trade_name, 'code' => $client->code],
            'awaiting' => $awaiting->map(fn (InvoiceTransfer $t) => [
                'id' => (int) $t->id,
                'kind' => $t->kind,
                'isRequest' => $t->isRequest(),
                'invoiceId' => $t->invoice_id ? (int) $t->invoice_id : null,
                'invoiceCode' => $t->invoice_code,
                'from' => $t->isRequest() ? ($t->toEmployee->name ?? null) : ($t->fromEmployee->name ?? null),
                'sentAt' => optional($t->sent_at ?? $t->created_at)->toDateTimeString(),
                'amount' => $t->isBalance() ? (float) $t->invoice_amount : (float) ($t->invoice->due_value ?? 0),
                'reason' => $t->sender_notes,
                'canRespond' => $canRespond && ($t->responderId() === (int) $user->id || $actsOnBehalf),
            ])->values(),
            'receiveAllCount' => $canRespond ? $awaiting->where('direction', '!=', InvoiceTransfer::DIRECTION_REQUEST)->count() : 0,
            'balance' => [
                'ledger' => round($ledger, 2),
                'invoiceDue' => round((float) $invoices->sum('due_value'), 2),
                'holder' => $holder,
                'canSend' => (bool) $myBalance,
                'mine' => $myBalance ? ['balance' => (float) $myBalance['balance'], 'invoiceDue' => (float) $myBalance['invoice_due']] : null,
            ],
            'invoices' => $invoices->map(function ($invoice) use ($user, $canCancel) {
                $pending = $invoice->pending;

                return [
                    'id' => (int) $invoice->id,
                    'code' => $invoice->code,
                    'date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->toDateString() : null,
                    'total' => (float) $invoice->grand_total,
                    'due' => (float) $invoice->due_value,
                    'isMine' => (bool) $invoice->is_mine,
                    'holder' => $invoice->holder,
                    'pending' => $pending ? [
                        'id' => (int) $pending->id,
                        'waitingForMe' => $pending->responderId() === (int) $user->id,
                        'to' => $pending->toEmployee->name ?? null,
                        'canCancel' => $canCancel && $pending->initiatorId() === (int) $user->id,
                    ] : null,
                ];
            })->values(),
            'recipients' => $allRecipients->where('can_receive', true)->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name, 'itineraryDate' => $u->itinerary_date])->values(),
            'noPermission' => $allRecipients->where('can_receive', false)->pluck('name')->values(),
            'branchName' => $branchId ? Branch::findCached($branchId)?->name : null,
            'settings' => [
                'reasonRequired' => ClientSettingsHelper::invoiceTransferReasonRequired(),
                'requiresApproval' => ClientSettingsHelper::invoiceTransferRequiresApproval(),
            ],
            'can' => ['send' => $canSend, 'respond' => $canRespond, 'cancel' => $canCancel],
        ]]);
    }

    /** قسمُ «تسليم الفواتير» مطفأٌ في إعدادات وحدات العملاء — رسالةُ `EnforcesClientModuleSettings` نفسها */
    private function sectionBlock(): ?string
    {
        $web = app(InvoiceTransferController::class);
        $method = new \ReflectionMethod($web, 'sectionBlock');

        return $method->invoke($web, 'invoice_transfer');
    }

    /** `DELETE /{id}/cancel` في الويب — POST هنا لأن جسر خادم الجوال يرسل GET وPOST فقط */
    public function cancel($id)
    {
        return app(InvoiceTransferController::class)->cancel($id);
    }
}
