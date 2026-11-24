<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Invoice;
use App\Models\InvoiceTransfer;
use App\Models\notifications;
use App\Models\User;
use App\Services\Clients\InvoiceTransferRules;
use App\Support\DataScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * تسليم الفواتير بين الموظفين واستلامها.
 *
 * **ما يأتي من الإعدادات** — «إعدادات وحدات العملاء» ← تبويب الفواتير:
 *
 *   • *القسم*        ← `invoice_transfer_enabled`: مطفأً تُمنع الشاشة
 *                       والروابط المباشرة معاً، لا إخفاءً بصرياً وحده.
 *   • *الاعتماد*     ← `require_approval`: مطفأً يُنفَّذ التحويل فوراً بلا
 *                       انتظار قبول المستلِم — وهو ما كان مستحيلاً قبله.
 *   • *سببٌ مكتوب*   ← `reason_required`: يقلب قاعدة `nullable` إلى
 *                       `required` في التحويل والرفض معاً.
 *   • *الفاتورة المسدَّدة* ← `allow_transfer_paid`: مطفأً تُرفض فاتورةٌ لا
 *                       مستحقَّ عليها — تحويلُها نقلُ عهدةٍ لا معنى له.
 *   • *سقف المعلّق*  ← `max_open_per_employee`: يمنع إغراق موظفٍ بطلبات.
 *   • *الإشعار*      ← `notify_on_transfer`: يحكم إشعارَ الطرفين.
 *
 * **الصلاحيات**: لم يكن على هذا المتحكّم صلاحيةٌ واحدة — أيُّ مستخدمٍ
 * يصل الشاشة كان يقدر يحوّل أيَّ فاتورة إلى أيّ موظف. صارت على كتالوج
 * `clients_invoice_transfer_*`.
 *
 * **السجلّات**: `OperationLog` في كل عملية — سجلّ النشاط بفرق القيم،
 * وسجلّ الأحداث، وسجلّ الأخطاء عند الفشل. كان `Log::error()` يكتب في ملفّ
 * لارافيل فلا تراه شاشةُ «سجلّ الأخطاء» أصلاً.
 */
class InvoiceTransferController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientInvoiceTransfers';

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_invoice_transfer_view', ['only' => [
            'index', 'getAvailableInvoices', 'getInvoiceHistory', 'getStats',
            'getEmployees', 'getClientTransfers', 'sendableClients',
        ]]);
        $this->middleware('permission:clients_invoice_transfer_send', ['only' => ['store', 'storeBalance']]);
        $this->middleware('permission:clients_invoice_transfer_respond', ['only' => ['accept', 'reject', 'respondBatch', 'receiveAll']]);
        $this->middleware('permission:clients_invoice_transfer_cancel', ['only' => ['cancel']]);
    }

    /**
     * عرض صفحة تسليم واستلام الفواتير
     */
    public function index(Request $request)
    {
        if ($block = $this->sectionBlock('invoice_transfer')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $user = auth()->user();

        $filters = [
            'client_id' => $request->integer('client_id') ?: null,
            'employee_id' => $request->integer('employee_id') ?: null,
            'status' => in_array($request->get('status'), ['pending', 'accepted', 'rejected'], true) ? $request->get('status') : null,
            'direction' => in_array($request->get('direction'), ['send', 'request'], true) ? $request->get('direction') : null,
            'q' => trim((string) $request->get('q', '')),
        ];

        $with = ['invoice:id,code,due_value', 'fromEmployee:id,name', 'toEmployee:id,name', 'client:id,trade_name,code'];

        // ما ينتظر ردّي: تسليمٌ إليّ، أو طلبُ استلامٍ من عهدتي
        $receivedTransfers = $this->filterTransfers(InvoiceTransfer::with($with)->respondableBy($user->id), $filters)
            ->latest('id')->get();

        // ما بدأتُه: تسليمٌ أرسلتُه، أو استلامٌ طلبتُه
        $sentTransfers = $this->filterTransfers(InvoiceTransfer::with($with)->initiatedBy($user->id), $filters)
            ->latest('id')->get();

        /* «كلّ التحويلات» لمن نطاقُ رؤيته أوسعُ من نفسه — المديرُ كان لا يرى
           إلّا ما أرسله أو وصله، فلا يعرف أنّ مندوباً ينتظر ردَّ آخر منذ أشهر */
        $scopeUserIds = DataScope::documentUserIds();
        $canSeeAll = $scopeUserIds === null || count(array_diff($scopeUserIds, [(int) $user->id])) > 0;

        $allTransfers = $canSeeAll
            ? $this->filterTransfers(InvoiceTransferRules::constrainVisible(InvoiceTransfer::with($with)), $filters, true)
                ->latest('id')->limit(500)->get()
            : collect();

        // خيارات الفلتر: عملاءُ وموظفو التحويلات التي يراها فقط
        $visible = $canSeeAll
            ? InvoiceTransferRules::constrainVisible(InvoiceTransfer::query())
            : InvoiceTransfer::query()->where(fn ($q) => $q->where('from_employee_id', $user->id)->orWhere('to_employee_id', $user->id));

        $clientOptions = Client::whereIn('id', (clone $visible)->select('client_id')->whereNotNull('client_id'))
            ->orderBy('trade_name')
            ->get(['id', 'trade_name', 'code']);

        $employeeOptions = $canSeeAll
            ? User::whereIn('id', (clone $visible)->select('from_employee_id'))
                ->orWhereIn('id', (clone $visible)->select('to_employee_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $pendingReceivedCount = $receivedTransfers->where('status', 'pending')->count();

        // نافذة التسليم: أيّامُ خطّ سيري (مصدرُ العملاء) وزملاءُ فرعي (المستلِمون)
        $itineraryDays = InvoiceTransferRules::itineraryDays($user->id);
        $today = now()->toDateString();
        $dates = $itineraryDays->pluck('date');
        $defaultSource = $dates->contains($today) ? $today
            : ($dates->first(fn ($day) => $day > $today) ?? 'all');
        $branchMates = InvoiceTransferRules::branchMates($user);
        $excludedMates = InvoiceTransferRules::excludedBranchMates($user)->groupBy('why');

        /* «بانتظار استلامك» دفعاتٌ لا صفوف: المستلِم يرى «سلّمك فلانٌ ٥ فواتير
           للعميل س» ويستلمها بضغطة — لا يُفلتَر هذا القسم، فهو ما يجب فعله الآن */
        $awaitingBatches = InvoiceTransfer::with(['invoice:id,code,due_value,grand_total,invoice_date', 'fromEmployee:id,name', 'toEmployee:id,name', 'client:id,trade_name,code'])
            ->respondableBy($user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->get()
            ->groupBy(fn ($transfer) => $transfer->batch ?: 'single-'.$transfer->id);

        // الخطوة الأولى في نافذة التسليم: عملائي الذين عليهم مستحقّ في عهدتي
        $myClients = InvoiceTransferRules::transferableInvoices($user)
            ->where('due_value', '>', 0)
            ->selectRaw('client_id, COUNT(*) as invoices, SUM(due_value) as due')
            ->groupBy('client_id')
            ->get();
        $myClientNames = Client::whereIn('id', $myClients->pluck('client_id'))->get(['id', 'trade_name', 'code'])->keyBy('id');
        $myClients = $myClients->map(fn ($row) => [
            'id' => (int) $row->client_id,
            'name' => $myClientNames[$row->client_id]->trade_name ?? 'غير محدد',
            'code' => $myClientNames[$row->client_id]->code ?? '',
            'invoices' => (int) $row->invoices,
            'due' => (float) $row->due,
        ])->sortBy('name')->values();

        // الموظفون على رأس العمل — `users.role` لا يحمل «employee» أبداً فلم يكن يظهر مندوب
        $employees = InvoiceTransferRules::recipients($user);

        return view('client::invoice-transfers.index', compact(
            'sentTransfers',
            'receivedTransfers',
            'allTransfers',
            'canSeeAll',
            'pendingReceivedCount',
            'awaitingBatches',
            'myClients',
            'itineraryDays',
            'defaultSource',
            'branchMates',
            'excludedMates',
            'employees',
            'filters',
            'clientOptions',
            'employeeOptions'
        ));
    }

    /**
     * فلاتر الشاشة على أيّ قائمةٍ من القوائم الثلاث — الموظفُ يُطبَّق على
     * «كلّ التحويلات» وحدها، فالقائمتان الأخريان مقيّدتان بالمستخدم أصلاً.
     */
    private function filterTransfers($query, array $filters, bool $withEmployee = false)
    {
        return $query
            ->when($filters['client_id'], fn ($q, $id) => $q->where('client_id', $id))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['direction'], fn ($q, $direction) => $q->where('direction', $direction))
            ->when($withEmployee && $filters['employee_id'], fn ($q) => $q->where(fn ($either) => $either
                ->where('from_employee_id', $filters['employee_id'])
                ->orWhere('to_employee_id', $filters['employee_id'])))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($match) => $match
                ->where('invoice_code', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('client', fn ($client) => $client
                    ->where('trade_name', 'like', '%'.$filters['q'].'%')
                    ->orWhere('code', 'like', '%'.$filters['q'].'%'))));
    }

    /**
     * تسليم فاتورةٍ أو دفعةِ فواتير إلى موظف.
     *
     * `invoice_ids[]` دفعةٌ (كلُّ فواتير عميلٍ مستحقّة، أو فواتيرُ كلِّ عملائي)
     * و`invoice_id` فاتورةٌ واحدة — والواحدةُ دفعةٌ من فاتورة. كلُّ فاتورةٍ
     * صفُّ تحويلٍ مستقلّ يجمعها `batch`، فيستلمها المستلِم معاً بـ
     * `respondBatch()` أو واحدةً واحدة. الدفعةُ كلُّها أو لا شيء: فاتورةٌ
     * واحدة لا تُسلَّم تردّ الطلب كلَّه برقمها وسببها.
     */
    public function store(Request $request)
    {
        if ($block = $this->sectionBlock('invoice_transfer')) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $request->validate([
            'invoice_id' => 'required_without:invoice_ids|nullable|integer|exists:invoices,id',
            'invoice_ids' => 'required_without:invoice_id|nullable|array|max:500',
            'invoice_ids.*' => 'integer|exists:invoices,id',
            'to_employee_id' => 'required|exists:users,id',
            // «سببٌ مكتوب مطلوب» إعدادٌ لا ثابت — القاعدة تُشتقّ منه
            'sender_notes' => $this->transferReasonRule(),
            'visit_id' => 'nullable|integer|exists:visits,id',
        ], [
            'invoice_id.required_without' => 'اختر فاتورةً واحدةً على الأقل',
            'invoice_ids.required_without' => 'اختر فاتورةً واحدةً على الأقل',
            'to_employee_id.required' => 'يجب اختيار الموظف المستلم',
            'to_employee_id.exists' => 'الموظف غير موجود',
            'sender_notes.required' => 'يجب كتابة سبب التسليم — الحقل مطلوب في إعدادات وحدات العملاء',
            'sender_notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ]);

        $user = auth()->user();
        $ids = collect($request->input('invoice_ids') ?: [$request->input('invoice_id')])
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ((int) $request->to_employee_id === (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك تسليم الفاتورة لنفسك'], 422);
        }

        $receiver = User::find($request->to_employee_id);

        if ($block = InvoiceTransferRules::recipientBlock($receiver) ?? $this->mateBlock($user, $receiver)) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $invoices = Invoice::with('client:id,trade_name,code,branch_id')->whereIn('id', $ids)->get();
        $pendingIds = InvoiceTransfer::whereIn('invoice_id', $ids)->where('status', 'pending')->pluck('invoice_id')->all();

        /* العهدة قبل كلّ شيء: القائمةُ في النافذة وعدٌ لا قاعدة، والمعرّفات
           تصل من الطلب كما هي — فلا تُسلَّم إلّا فاتورةٌ في عهدة المرسِل نفسه */
        foreach ($invoices as $invoice) {
            if (in_array($invoice->id, $pendingIds, true)) {
                return response()->json(['success' => false, 'message' => "الفاتورة ({$invoice->code}) عليها طلبُ تحويلٍ معلّق بالفعل"], 422);
            }

            if ($block = InvoiceTransferRules::invoiceBlock($user, $invoice)) {
                return response()->json(['success' => false, 'message' => $block], 403);
            }

            if ($block = $this->invoiceTransferBlock((float) ($invoice->due_value ?? 0) <= 0)) {
                return response()->json(['success' => false, 'message' => "الفاتورة ({$invoice->code}): {$block}"], 422);
            }
        }

        // المستلِم من فرع كلِّ عميلٍ في الدفعة
        if ($block = InvoiceTransferRules::branchBlock($receiver, $invoices->pluck('client_id')->unique()->all())) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        // سقفُ المعلّق يُعدّ بطلبات الدفعة نفسها
        $openTransfers = InvoiceTransfer::respondableBy($receiver->id)->where('status', 'pending')->count();

        if ($block = $this->invoiceTransferBlock(false, $openTransfers + $invoices->count() - 1)) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        // مطفأً يعني التحويل الفوري: لا انتظار قبولٍ ولا طلبٌ معلّق
        $requiresApproval = ClientSettingsHelper::invoiceTransferRequiresApproval();
        $batch = (string) \Illuminate\Support\Str::uuid();

        try {
            DB::beginTransaction();

            $transfers = $invoices->map(function (Invoice $invoice) use ($user, $receiver, $request, $requiresApproval, $batch) {
                $transfer = InvoiceTransfer::create([
                    'invoice_id' => $invoice->id,
                    'from_employee_id' => $user->id,
                    'to_employee_id' => $receiver->id,
                    'client_id' => $invoice->client_id,
                    'direction' => InvoiceTransfer::DIRECTION_SEND,
                    'visit_id' => $request->visit_id,
                    'batch' => $batch,
                    'status' => $requiresApproval ? 'pending' : 'accepted',
                    'sent_at' => now(),
                    'responded_at' => $requiresApproval ? null : now(),
                    'sender_notes' => $request->sender_notes,
                    'invoice_amount' => $invoice->grand_total,
                    'invoice_code' => $invoice->code,
                ]);

                // التحويل الفوري ينقل مسؤولية الفاتورة الآن — وإلّا انتظر القبول
                if (! $requiresApproval) {
                    $invoice->employee_id = $receiver->employee_id;
                    $invoice->save();
                }

                return $transfer->setRelation('invoice', $invoice)
                    ->setRelation('fromEmployee', $user)
                    ->setRelation('toEmployee', $receiver);
            });

            $this->announce($transfers, $requiresApproval ? 'sent' : 'sent_now', $user, $request->sender_notes);

            foreach ($transfers as $transfer) {
                OperationLog::record(
                    $requiresApproval ? 'transfer_requested' : 'transferred',
                    self::LOG_MODULE,
                    $transfer,
                    ($requiresApproval ? 'طلب تسليم' : 'تحويل فوري لـ')
                        ." الفاتورة {$transfer->invoice_code} من {$user->name} إلى {$receiver->name}",
                    ['new' => [
                        'invoice_id' => $transfer->invoice_id,
                        'to_employee_id' => $receiver->id,
                        'status' => $transfer->status,
                        'batch' => $batch,
                    ]]
                );
            }

            DB::commit();

            $what = $transfers->count() === 1
                ? 'الفاتورة '.$transfers->first()->invoice_code
                : $transfers->count().' فواتير';

            return response()->json([
                'success' => true,
                'message' => $requiresApproval
                    ? "أُرسلت {$what} إلى {$receiver->name} — تبقى باسمك حتى يستلمها"
                    : "صارت {$what} باسم {$receiver->name} — الاعتماد غير مطلوب في الإعدادات",
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'store', $ids->first());

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إرسال الطلب'], 500);
        }
    }

    /**
     * تسليمُ رصيد عميلٍ أو أرصدةِ عملاءٍ إلى موظف — الرصيدُ من الأستاذ لا من
     * الفواتير: الإشعارُ الدائن الذي لم يُحوَّل مرتجعاً يُنقص الحسابَ ولا
     * يمسّ مستحقَّ الفاتورة. صفٌّ لكلّ عميل (`kind = balance`) بلا فاتورة،
     * و`invoice_amount` = الرصيد يوم التسليم. حين يُستلَم ({@see handOver()})
     * تنتقل معه فواتيرُ المسلِّم المستحقّة لذلك العميل.
     */
    public function storeBalance(Request $request)
    {
        if ($block = $this->sectionBlock('invoice_transfer')) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $request->validate([
            'client_ids' => 'required|array|min:1|max:500',
            'client_ids.*' => 'integer|exists:clients,id',
            'to_employee_id' => 'required|exists:users,id',
            'sender_notes' => $this->transferReasonRule(),
            'visit_id' => 'nullable|integer|exists:visits,id',
        ], [
            'client_ids.required' => 'اختر عميلاً واحداً على الأقل',
            'to_employee_id.required' => 'يجب اختيار الموظف المستلم',
            'sender_notes.required' => 'يجب كتابة سبب التسليم — الحقل مطلوب في إعدادات وحدات العملاء',
        ]);

        $user = auth()->user();
        $clientIds = collect($request->input('client_ids'))->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ((int) $request->to_employee_id === (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك تسليم الرصيد لنفسك'], 422);
        }

        $receiver = User::find($request->to_employee_id);

        if ($block = InvoiceTransferRules::recipientBlock($receiver) ?? $this->mateBlock($user, $receiver)) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        // صاحبُ الرصيد فقط، والرصيدُ مدين، ولا تسليمَ معلّقٌ عليه
        $held = InvoiceTransferRules::balanceClientsOf($user, $clientIds)->keyBy('id');

        foreach ($clientIds as $clientId) {
            if (! $held->has($clientId)) {
                $name = Client::find($clientId)?->trade_name ?? '#'.$clientId;

                return response()->json(['success' => false, 'message' => "رصيد العميل «{$name}» ليس في عهدتك، أو رصيده صفر، أو عليه تسليمٌ معلّق"], 422);
            }
        }

        if ($block = InvoiceTransferRules::branchBlock($receiver, $clientIds)) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $openTransfers = InvoiceTransfer::respondableBy($receiver->id)->where('status', 'pending')->count();

        if ($block = $this->invoiceTransferBlock(false, $openTransfers + count($clientIds) - 1)) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $requiresApproval = ClientSettingsHelper::invoiceTransferRequiresApproval();
        $batch = (string) \Illuminate\Support\Str::uuid();

        try {
            DB::beginTransaction();

            $transfers = collect($clientIds)->map(function (int $clientId) use ($user, $receiver, $request, $requiresApproval, $batch, $held) {
                $transfer = InvoiceTransfer::create([
                    'invoice_id' => null,
                    'from_employee_id' => $user->id,
                    'to_employee_id' => $receiver->id,
                    'client_id' => $clientId,
                    'direction' => InvoiceTransfer::DIRECTION_SEND,
                    'kind' => InvoiceTransfer::KIND_BALANCE,
                    'visit_id' => $request->visit_id,
                    'batch' => $batch,
                    'status' => $requiresApproval ? 'pending' : 'accepted',
                    'sent_at' => now(),
                    'responded_at' => $requiresApproval ? null : now(),
                    'sender_notes' => $request->sender_notes,
                    'invoice_amount' => $held[$clientId]['balance'],
                    'invoice_code' => null,
                ])->setRelation('fromEmployee', $user)->setRelation('toEmployee', $receiver);

                if (! $requiresApproval) {
                    $this->handOver($transfer);
                }

                OperationLog::record('balance_sent', self::LOG_MODULE, $transfer,
                    "تسليم رصيد العميل #{$clientId} ({$held[$clientId]['balance']}) من {$user->name} إلى {$receiver->name}",
                    ['new' => ['client_id' => $clientId, 'amount' => $held[$clientId]['balance'], 'status' => $transfer->status, 'batch' => $batch]]);

                return $transfer;
            });

            $this->announce($transfers, $requiresApproval ? 'sent' : 'sent_now', $user, $request->sender_notes);

            DB::commit();

            $what = count($clientIds) === 1 ? 'رصيد العميل' : 'أرصدة '.count($clientIds).' عملاء';

            return response()->json([
                'success' => true,
                'message' => $requiresApproval
                    ? "تم إرسال {$what} إلى {$receiver->name} — يبقى باسمك حتى يستلمه"
                    : "صار {$what} باسم {$receiver->name}",
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'storeBalance', $clientIds[0] ?? null);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء التسليم'], 500);
        }
    }

    /**
     * ما يحدث عند الاستلام: الفاتورةُ تصير باسم المستلِم، والرصيدُ تنتقل معه
     * فواتيرُ المسلِّم المستحقّة لذلك العميل — فيتبع التحصيلُ الرصيد.
     */
    private function handOver(InvoiceTransfer $transfer): void
    {
        $receiverEmployeeId = $transfer->toEmployee?->employee_id;

        if (! $receiverEmployeeId) {
            return;
        }

        if (! $transfer->isBalance()) {
            $transfer->invoice->employee_id = $receiverEmployeeId;
            $transfer->invoice->save();

            return;
        }

        if ($transfer->fromEmployee) {
            InvoiceTransferRules::custodyInvoicesOf($transfer->fromEmployee, (int) $transfer->client_id)
                ->get()
                ->each(function (Invoice $invoice) use ($receiverEmployeeId) {
                    $invoice->employee_id = $receiverEmployeeId;
                    $invoice->save();
                });
        }
    }

    /**
     * جدولُ نافذة التسليم: عملاءُ يومٍ من خطّ سيري (`source` = التاريخ) أو
     * كلُّ عملائي (`source=all`)، ولكلٍّ رصيدُه ومستحقُّ فواتيره وما في
     * عهدتي منه — ومع `receiver_id` يُعلَّم العميلُ الذي خارج فرع المستلِم.
     * الخادمُ يعيد التحقّق عند الإرسال (`store`/`storeBalance`).
     */
    public function sendableClients(Request $request)
    {
        $request->validate([
            'source' => ['required', 'string', 'regex:/^(all|\d{4}-\d{2}-\d{2})$/'],
            'receiver_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = auth()->user();
        $source = $request->input('source');
        $receiver = $request->filled('receiver_id') ? User::find($request->integer('receiver_id')) : null;
        $ids = $source === 'all'
            ? InvoiceTransferRules::myClientIds($user)
            : InvoiceTransferRules::itineraryClientIds($user, $source);

        return response()->json([
            'success' => true,
            'source' => $source,
            'clients' => InvoiceTransferRules::sendableClients($user, $ids, $receiver),
        ]);
    }

    /** المستلِمُ من فرع المسلِّم — «للموظف الموجود في فرعه». */
    private function mateBlock(User $sender, User $receiver): ?string
    {
        return InvoiceTransferRules::branchMates($sender)->contains('id', $receiver->id)
            ? null
            : "الموظف «{$receiver->name}» ليس من فرعك، أو لا يملك صلاحية الاستلام";
    }

    /**
     * الردُّ على دفعةٍ كاملة: استلامُ كلِّ ما ينتظر ردّي فيها أو رفضُه.
     */
    /**
     * «استلام الكل»: كلُّ ما سلّمه الزملاءُ لي وينتظر استلامي — دفعةً دفعة،
     * فتُكتب ملاحظةُ كلِّ عميلٍ وإشعارُ كلِّ مسلِّم كما في الاستلام الواحد.
     * ما سُلِّم لي فقط (`to_employee_id` = أنا، اتجاه التسليم) — لا نيابةً.
     */
    public function receiveAll(Request $request)
    {
        $request->validate([
            'receiver_notes' => 'nullable|string|max:1000',
            'client_id' => 'nullable|integer',
        ]);

        // `client_id`: «استلام الكل» في زيارة عميلٍ يخصّه وحده
        $clientId = $request->filled('client_id') ? $request->integer('client_id') : null;
        $pending = InvoiceTransfer::where('to_employee_id', auth()->id())
            ->where('direction', '!=', InvoiceTransfer::DIRECTION_REQUEST)
            ->where('status', 'pending')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            // الفواتيرُ قبل الأرصدة: الرصيدُ ينقل الفواتير معه
            ->orderByRaw("kind = 'balance'")
            ->orderBy('id')
            ->get(['id', 'batch', 'kind']);

        if ($pending->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لا شيء بانتظار استلامك'], 404);
        }

        $done = 0;
        $errors = [];
        $sub = new Request(array_filter(['mode' => 'accept', 'receiver_notes' => $request->receiver_notes, 'client_id' => $clientId]));

        foreach ($pending->groupBy(fn ($t) => $t->batch ?: 'single-'.$t->id) as $key => $rows) {
            $response = $rows->first()->batch
                ? $this->respondBatch($sub, $rows->first()->batch)
                : $this->accept($sub, $rows->first()->id);
            $body = json_decode($response->getContent(), true) ?: [];

            if ($response->getStatusCode() === 200 && ($body['success'] ?? false)) {
                $done += $rows->count();
            } else {
                $errors[] = $body['message'] ?? 'تعذّر استلام دفعة';
            }
        }

        return response()->json([
            'success' => $done > 0,
            'message' => ($done ? 'تم استلام كلّ ما سُلِّم لك ('.$done.') — صار باسمك' : 'لم يُستلَم شيء')
                .($errors ? ' — تعذّر: '.implode('؛ ', array_unique($errors)) : ''),
        ], $done > 0 ? 200 : 422);
    }

    public function respondBatch(Request $request, string $batch)
    {
        $reject = $request->input('mode') === 'reject';

        $request->validate([
            'mode' => 'required|in:accept,reject',
            'receiver_notes' => $reject ? $this->transferReasonRule() : 'nullable|string|max:1000',
        ], [
            'receiver_notes.required' => 'يجب كتابة سبب الرفض — الحقل مطلوب في إعدادات وحدات العملاء',
        ]);

        $user = auth()->user();
        // `client_id`: الزيارةُ تردّ على عميلها وحده من دفعةٍ قد تضمّ عملاء آخرين
        $query = InvoiceTransfer::with(['invoice.client:id,trade_name,code', 'fromEmployee', 'toEmployee'])
            ->where('batch', $batch)
            ->where('status', 'pending')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')));

        if (! ($user->hasRoleName('admin') || $user->hasRoleName('manager'))) {
            $query->respondableBy($user->id);
        }

        $transfers = $query->get()->filter(fn ($transfer) => $transfer->invoice || $transfer->isBalance())->values();

        if ($transfers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لا شيء في هذه الدفعة ينتظر ردّك'], 404);
        }

        if (! $reject) {
            foreach ($transfers as $transfer) {
                if ($transfer->isBalance()) {
                    if (! $transfer->toEmployee?->employee_id) {
                        return response()->json(['success' => false, 'message' => 'المستلِم ليس له سجلّ موظف — لا يمكن أن يصير الرصيد باسمه'], 422);
                    }

                    continue;
                }

                if (! $transfer->toEmployee?->employee_id) {
                    return response()->json(['success' => false, 'message' => 'المستلِم ليس له سجلّ موظف — لا يمكن أن تصير الفواتير باسمه'], 422);
                }

                // …إلّا إن صارت عند المستلِم نفسه: استلامُ رصيد العميل ينقل فواتيرَ المسلِّم معه، فتُقبل كما هي
                if ($transfer->fromEmployee
                    && ! InvoiceTransferRules::inCustodyOf($transfer->fromEmployee, $transfer->invoice)
                    && ! ($transfer->toEmployee && InvoiceTransferRules::inCustodyOf($transfer->toEmployee, $transfer->invoice))) {
                    return response()->json(['success' => false, 'message' => "الفاتورة ({$transfer->invoice->code}) لم تعد في عهدة {$transfer->fromEmployee->name} — ارفضها وحدها ثم استلم الباقي"], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            foreach ($transfers as $transfer) {
                $transfer->status = $reject ? 'rejected' : 'accepted';
                $transfer->responded_at = now();
                $transfer->receiver_notes = $request->receiver_notes;
                $transfer->save();

                if (! $reject) {
                    $this->handOver($transfer);
                }

                OperationLog::record(
                    $reject ? 'rejected' : 'accepted',
                    self::LOG_MODULE,
                    $transfer,
                    ($reject ? 'رفض' : 'استلام')." {$transfer->subject_label} {$transfer->invoice_code} بواسطة {$user->name} (دفعة)",
                    ['old' => ['status' => 'pending'], 'new' => ['status' => $transfer->status, 'batch' => $batch]]
                );
            }

            $this->announce($transfers, $reject ? 'rejected' : 'accepted', $user, $request->receiver_notes);

            DB::commit();

            $what = $transfers->first()->isBalance()
                ? ($transfers->count() === 1 ? 'رصيد العميل' : 'أرصدة '.$transfers->count().' عملاء')
                : ($transfers->count() === 1 ? 'الفاتورة '.$transfers->first()->invoice_code : $transfers->count().' فواتير');

            return response()->json([
                'success' => true,
                'message' => $reject
                    ? "رُفضت {$what} — بقيت في عهدة ".($transfers->first()->fromEmployee->name ?? 'صاحبها')
                    : "تم استلام {$what} — صارت باسم ".($transfers->first()->toEmployee->name ?? 'المستلِم'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'respondBatch', $transfers->first()->id);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء حفظ الردّ'], 500);
        }
    }

    /**
     * ملاحظةُ العميل وإشعارُ الطرف الآخر لكلّ حدثٍ في التحويل — بنصٍّ يقول
     * ما حدث ولمن وبكم. يقبل تحويلاً واحداً أو دفعة: ملاحظةٌ لكلّ عميلٍ في
     * الدفعة تسرد فواتيره ومستحقّها، وإشعارٌ واحد يجملها.
     *
     * كان الإشعار يُكتب بـ`message` و`data`، والجدول لا عمودَ لهما:
     * `notifications` يُسقط `message` متى وُجد `description`، فوصل كلُّ إشعارٍ
     * بكلمتين («تسليم فاتورة») بلا رقمٍ ولا عميلٍ ولا نتيجة. والملاحظة كانت
     * `type = invoice` — نوعُ ملاحظات المستندات التلقائية الذي تُسقط بطاقةُ
     * «ملاحظات الزيارات» نصَّه ({@see \App\Services\Clients\VisitNoteCards})،
     * فتظهر بطاقةٌ فارغة. صارت ملاحظةً مكتوبة (`client`) تحمل `invoice_id`.
     *
     * @param  InvoiceTransfer|\Illuminate\Support\Collection<int, InvoiceTransfer>  $transfers  كلٌّ منها محمَّلٌ بفاتورته وطرفيه
     * @param  'sent'|'sent_now'|'requested'|'accepted'|'rejected'|'cancelled'  $event
     */
    private function announce($transfers, string $event, User $actor, ?string $notes): void
    {
        $transfers = collect($transfers instanceof InvoiceTransfer ? [$transfers] : $transfers)
            ->filter(fn ($t) => $t->invoice || $t->isBalance())->values();

        if ($transfers->isEmpty()) {
            return;
        }

        $transfers->each(fn ($t) => $t->loadMissing('client:id,trade_name,code'));
        $first = $transfers->first();
        $isBalance = $first->isBalance();
        // الرصيد يُعرض بقيمته يوم التسليم، والفاتورةُ بمستحقّها الآن
        $amountOf = fn ($t) => (float) ($t->isBalance() ? $t->invoice_amount : $t->invoice->due_value);

        $from = $first->fromEmployee->name ?? 'غير معروف';
        $to = $first->toEmployee->name ?? 'غير معروف';
        $kind = $isBalance ? 'تسليم رصيد' : ($first->isRequest() ? 'طلب استلام' : 'تسليم');
        $onBehalf = in_array($event, ['accepted', 'rejected'], true) && $first->responderId() !== (int) $actor->id
            ? " (بواسطة {$actor->name} نيابةً)" : '';
        $fmt = fn ($value) => number_format((float) $value, 2);

        $count = $transfers->count();
        $what = $isBalance
            ? ($count === 1 ? 'رصيد العميل '.($first->client->trade_name ?? '') : 'أرصدة '.$count.' عملاء')
            : ($count === 1 ? 'الفاتورة '.$first->invoice->code : $count.' فواتير');
        $clients = $transfers->groupBy('client_id');
        // الرصيد يسمّي عميله في `$what` نفسه
        $clientLabel = $isBalance ? '' : ($clients->count() === 1
            ? ' للعميل '.($first->client->trade_name ?? 'غير محدد')
            : ' لـ'.$clients->count().' عملاء');
        $totalDue = $fmt($transfers->sum($amountOf));
        $totalLabel = $isBalance ? 'إجمالي الرصيد' : 'إجمالي المستحق';

        [$process, $headline, $receiverId, $title, $message] = match ($event) {
            'sent' => [
                $isBalance ? 'تسليم رصيد' : 'تسليم فاتورة',
                "سلّم {$from} إلى {$to} — بانتظار استلام {$to}",
                $first->to_employee_id,
                "{$from} سلّمك {$what} — بانتظار استلامك",
                "أرسل {$from} إليك {$what}{$clientLabel} — {$totalLabel} {$totalDue}. استلمها من «تحويلات الفواتير» أو من زيارة العميل.",
            ],
            'sent_now' => [
                $isBalance ? 'تسليم رصيد' : 'تسليم فاتورة',
                "سلّم {$from} إلى {$to} — صارت باسمه فوراً (الاعتماد غير مطلوب)",
                $first->to_employee_id,
                "صارت {$what} باسمك",
                "سلّمك {$from} {$what}{$clientLabel} — {$totalLabel} {$totalDue}. صارت باسمك.",
            ],
            'requested' => [
                'طلب استلام فاتورة',
                "طلب {$to} الاستلام من عهدة {$from} — بانتظار موافقة {$from}",
                $first->from_employee_id,
                "{$to} يطلب استلام {$what} من عهدتك",
                "يطلب {$to} استلام {$what}{$clientLabel} — {$totalLabel} {$totalDue}. وافق أو ارفض من «تحويلات الفواتير».",
            ],
            'accepted' => [
                $isBalance ? 'استلام رصيد' : 'استلام فاتورة',
                "استلم {$to} من {$from}{$onBehalf} — ".($isBalance ? 'صار الرصيد باسمه' : 'صارت باسمه'),
                $first->initiatorId(),
                "تم الاستلام — {$what} ".($isBalance && $count === 1 ? 'صار' : 'صارت')." باسم {$to}",
                "استلم {$to} {$what}{$clientLabel} — {$totalLabel} {$totalDue}.{$onBehalf}",
            ],
            'rejected' => [
                'رفض فاتورة',
                "رُفض {$kind} من {$from} إلى {$to}{$onBehalf} — بقيت في عهدة {$from}",
                $first->initiatorId(),
                "رُفض {$kind} {$what}",
                "رفض {$actor->name} {$kind} {$what}{$clientLabel} — {$totalLabel} {$totalDue}. بقيت في عهدة {$from}.",
            ],
            'cancelled' => [
                'إلغاء تحويل فاتورة',
                "ألغى {$actor->name} {$kind} (من {$from} إلى {$to})",
                $first->responderId(),
                "أُلغي {$kind} {$what}",
                "ألغى {$actor->name} {$kind} {$what}{$clientLabel} — لم يعد بانتظار ردّك.",
            ],
        };

        $notesLabel = in_array($event, ['accepted', 'rejected'], true) ? 'الردّ' : 'السبب';
        $notesText = trim((string) $notes) !== '' ? " — {$notesLabel}: ".trim($notes) : '';

        // ملاحظةٌ لكلّ عميل: فواتيرُه في هذا الحدث بأرقامها ومستحقّها
        foreach ($clients as $clientTransfers) {
            $one = $clientTransfers->first();
            $list = $clientTransfers->map(fn ($t) => $t->isBalance()
                ? 'رصيد حساب العميل '.$fmt($t->invoice_amount)
                : $t->invoice->code.' (المستحق '.$fmt($t->invoice->due_value).')')->implode('، ');
            $label = $one->isBalance() ? 'الرصيد' : ($clientTransfers->count() === 1 ? 'الفاتورة' : $clientTransfers->count().' فواتير');

            try {
                ClientRelation::create([
                    'client_id' => $one->client_id,
                    'employee_id' => $actor->id,
                    'type' => 'client',
                    'invoice_id' => ! $one->isBalance() && $clientTransfers->count() === 1 ? $one->invoice_id : null,
                    'description' => $one->isBalance()
                        ? "{$headline} — {$list}{$notesText}"
                        : "{$headline} — {$label}: {$list} — إجمالي المستحق ".$fmt($clientTransfers->sum($amountOf)).$notesText,
                    'date' => now()->toDateString(),
                    'time' => now()->toTimeString(),
                    'status' => 'completed',
                    'process' => $process,
                ]);
            } catch (\Exception $logError) {
                // لا نوقف العملية إذا فشل التسجيل
                Log::warning('Failed to log invoice transfer note: '.$logError->getMessage());
            }
        }

        // الإشعار إعدادٌ لا سلوكٌ ثابت
        if (! ClientSettingsHelper::invoiceTransferNotify() || ! $receiverId || (int) $receiverId === (int) $actor->id) {
            return;
        }

        try {
            notifications::create([
                'user_id' => $actor->id,
                'receiver_id' => $receiverId,
                'client_id' => $clients->count() === 1 ? $first->client_id : null,
                'title' => $title,
                'description' => $message.$notesText,
                'type' => 'invoice_transfer'.(in_array($event, ['accepted', 'rejected', 'cancelled'], true) ? '_'.$event : ''),
                'read' => false,
            ]);
        } catch (\Exception $notifError) {
            Log::warning('Failed to send notification: '.$notifError->getMessage());
        }
    }

    /**
     * يقيّد الاستعلام بما يملك المستخدم الردَّ عليه — المديرُ يردّ نيابةً.
     */
    private function respondableQuery($user, $id)
    {
        $query = InvoiceTransfer::where('id', $id)->where('status', 'pending');

        if (! ($user->hasRoleName('admin') || $user->hasRoleName('manager'))) {
            $query->respondableBy($user->id);
        }

        return $query;
    }

    /**
     * قبول طلب تسليم فاتورة
     */
    public function accept(Request $request, $id)
    {
        try {
            $request->validate([
                'receiver_notes' => 'nullable|string|max:1000',
            ]);

            $user = auth()->user();

            // يقبل المستلِم في التسليم، وصاحبُ العهدة في طلب الاستلام — والمديرُ نيابةً
            $transfer = $this->respondableQuery($user, $id)
                ->with(['invoice', 'fromEmployee', 'toEmployee'])
                ->first();

            if (! $transfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب التحويل غير موجود أو لا يمكنك قبوله',
                ], 404);
            }

            $receiverEmployeeId = $transfer->toEmployee?->employee_id;

            if ($transfer->isBalance()) {
                if (! $receiverEmployeeId) {
                    return response()->json(['success' => false, 'message' => 'المستلِم ليس له سجلّ موظف — لا يمكن أن يصير الرصيد باسمه'], 422);
                }

                DB::beginTransaction();
                $transfer->status = 'accepted';
                $transfer->responded_at = now();
                $transfer->receiver_notes = $request->receiver_notes;
                $transfer->save();
                $this->handOver($transfer);
                $this->announce($transfer, 'accepted', $user, $request->receiver_notes);
                OperationLog::record('accepted', self::LOG_MODULE, $transfer, "استلام رصيد العميل #{$transfer->client_id} بواسطة {$user->name}",
                    ['old' => ['status' => 'pending'], 'new' => ['status' => 'accepted']]);
                DB::commit();

                return response()->json(['success' => true, 'message' => 'تم استلام رصيد العميل — صار باسم '.($transfer->toEmployee->name ?? 'المستلِم')]);
            }

            if (! $transfer->invoice || ! $receiverEmployeeId) {
                return response()->json([
                    'success' => false,
                    'message' => $transfer->invoice
                        ? 'المستلِم ليس له سجلّ موظف — لا يمكن أن تصير الفاتورة باسمه'
                        : 'الفاتورة المرتبطة بالطلب لم تعد موجودة',
                ], 422);
            }

            /* الفاتورة تخرج من عهدة `from`: إن تغيّر مسؤولُها منذ الطلب فالقبول
               يأخذها من غير صاحبها — يُرفض ويُلغى الطلب من مُنشئه */
            // …إلّا إن صارت عند المستلِم نفسه: استلامُ رصيد العميل ينقل فواتيرَ المسلِّم معه، فتُقبل كما هي
            if ($transfer->fromEmployee
                && ! InvoiceTransferRules::inCustodyOf($transfer->fromEmployee, $transfer->invoice)
                && ! ($transfer->toEmployee && InvoiceTransferRules::inCustodyOf($transfer->toEmployee, $transfer->invoice))) {
                return response()->json([
                    'success' => false,
                    'message' => "الفاتورة ({$transfer->invoice->code}) لم تعد في عهدة {$transfer->fromEmployee->name} — ارفض الطلب",
                ], 422);
            }

            DB::beginTransaction();

            // قبول التحويل
            $transfer->status = 'accepted';
            $transfer->responded_at = now();
            $transfer->receiver_notes = $request->receiver_notes;
            $transfer->save();

            /* الفاتورة تنتقل إلى المستلِم المسمّى في الطلب لا إلى من ضغط «قبول»:
               المدير الذي يقبل نيابةً كان يأخذ الفاتورة باسمه هو */
            $invoice = $transfer->invoice;
            $invoice->employee_id = $receiverEmployeeId;
            $invoice->save();

            $this->announce($transfer->setRelation('invoice', $invoice), 'accepted', $user, $request->receiver_notes);

            OperationLog::record(
                'accepted',
                self::LOG_MODULE,
                $transfer,
                "قبول استلام الفاتورة {$invoice->code} بواسطة {$user->name}",
                ['old' => ['status' => 'pending'], 'new' => ['status' => 'accepted', 'employee_id' => $invoice->employee_id]]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "تمت الموافقة — صارت الفاتورة {$invoice->code} باسم ".($transfer->toEmployee->name ?? 'المستلِم'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'accept', (int) $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء قبول الطلب: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * رفض طلب تسليم فاتورة
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            // «سببٌ مكتوب مطلوب» يسري على الرفض كما يسري على التحويل
            'receiver_notes' => $this->transferReasonRule(),
        ], [
            'receiver_notes.required' => 'يجب كتابة سبب الرفض — الحقل مطلوب في إعدادات وحدات العملاء',
        ]);

        $user = auth()->user();

        // يرفض المستلِم في التسليم، وصاحبُ العهدة في طلب الاستلام — والمديرُ نيابةً
        $transfer = $this->respondableQuery($user, $id)->first();

        if (! $transfer) {
            return response()->json([
                'success' => false,
                'message' => 'طلب التحويل غير موجود أو لا يمكنك رفضه',
            ], 404);
        }

        try {
            $transfer->reject($request->receiver_notes);

            $invoice = $transfer->invoice;

            $transfer->loadMissing(['fromEmployee', 'toEmployee']);
            $this->announce($transfer->setRelation('invoice', $invoice), 'rejected', $user, $request->receiver_notes);

            OperationLog::record(
                'rejected',
                self::LOG_MODULE,
                $transfer,
                "رفض استلام الفاتورة {$transfer->invoice_code} بواسطة {$user->name}"
                    .($request->receiver_notes ? ' — السبب: '.$request->receiver_notes : ''),
                ['old' => ['status' => 'pending'], 'new' => ['status' => 'rejected']]
            );

            return response()->json([
                'success' => true,
                'message' => ($transfer->isBalance()
                    ? 'رُفض استلام رصيد العميل '.($transfer->client->trade_name ?? '').' — بقي في عهدة '
                    : "رُفض الطلب — بقيت الفاتورة {$transfer->invoice_code} في عهدة ").($transfer->fromEmployee->name ?? 'صاحبها'),
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reject', (int) $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء رفض الطلب',
            ], 500);
        }
    }

    /**
     * إلغاء طلب تسليم (من قبل المرسل)
     */
    public function cancel($id)
    {
        $user = auth()->user();

        // يُلغي من بدأ التحويل: المرسِل في التسليم، والطالب في طلب الاستلام
        $transfer = InvoiceTransfer::where('id', $id)
            ->initiatedBy($user->id)
            ->where('status', 'pending')
            ->first();

        if (! $transfer) {
            return response()->json([
                'success' => false,
                'message' => 'طلب التحويل غير موجود أو لا يمكنك إلغاؤه',
            ], 404);
        }

        try {
            $transfer->loadMissing(['fromEmployee', 'toEmployee']);
            $this->announce($transfer, 'cancelled', $user, null);

            // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
            OperationLog::record(
                'cancelled',
                self::LOG_MODULE,
                $transfer,
                "إلغاء طلب تسليم الفاتورة {$transfer->invoice_code} بواسطة {$user->name}",
                ['old' => ['status' => 'pending', 'to_employee_id' => $transfer->to_employee_id]]
            );

            $transfer->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب التسليم بنجاح',
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel', (int) $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الطلب',
            ], 500);
        }
    }

    /**
     * جلب الفواتير المتاحة للتسليم
     */
    public function getAvailableInvoices(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->get('search', '');
            $clientId = $request->get('client_id');

            /* العهدة لا الرؤية: كانت القائمة على نطاق الرؤية، فمن يرى فواتير
               المنشأة كلَّها (`data_scope_all`) يُسلِّمها كلَّها ولو كانت باسم
               مندوبٍ آخر — يُسلِّم الموظفُ الآن ما هو مسؤولُ مبيعاتها وحده */
            $query = InvoiceTransferRules::whereNetDue(InvoiceTransferRules::transferableInvoices($user))
                ->with(['client:id,trade_name,code']);

            // تطبيق فلتر العميل إذا تم تحديده
            if ($clientId) {
                $query->where('client_id', $clientId);
            }

            // تطبيق البحث
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($cq) use ($search) {
                            $cq->where('trade_name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            }

            // الدفعةُ قد تكون كلَّ فواتير الموظف المستحقّة — لا تُقصّ عند مئة
            $rows = $query->orderByDesc('invoice_date')->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'code', 'client_id', 'grand_total', 'due_value', 'invoice_date', 'employee_id', 'created_by']);
            $holders = InvoiceTransferRules::holderNames($rows);

            $invoices = $rows->map(fn ($invoice) => [
                'id' => $invoice->id,
                'code' => $invoice->code,
                'client_id' => $invoice->client_id,
                'amount' => number_format((float) $invoice->grand_total, 2),
                'due' => number_format((float) $invoice->due_value, 2),
                'client_name' => $invoice->client->trade_name ?? 'غير محدد',
                'client_code' => $invoice->client->code ?? '',
                'date' => $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '-',
                'holder' => $holders[$invoice->id] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAvailableInvoices: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الفواتير',
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getInvoiceHistory($invoiceId)
    {
        $transfers = InvoiceTransfer::with(['fromEmployee:id,name', 'toEmployee:id,name'])
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transfers,
        ]);
    }

    /**
     * جلب إحصائيات التحويلات للمستخدم
     */
    public function getStats()
    {
        try {
            $user = auth()->user();
            $isManagerOrAdmin = ($user->hasRoleName('manager') || $user->hasRoleName('admin')); // ✅ hasRoleName() بدلاً من عمود role النصي

            if ($isManagerOrAdmin) {
                // المدير يرى جميع الإحصائيات
                $stats = [
                    'sent_pending' => InvoiceTransfer::where('status', 'pending')->count(),
                    'sent_accepted' => InvoiceTransfer::where('status', 'accepted')->count(),
                    'sent_rejected' => InvoiceTransfer::where('status', 'rejected')->count(),
                    'received_pending' => InvoiceTransfer::where('status', 'pending')->count(),
                    'received_accepted' => InvoiceTransfer::where('status', 'accepted')->count(),
                    'received_rejected' => InvoiceTransfer::where('status', 'rejected')->count(),
                ];
            } else {
                $stats = [
                    'sent_pending' => InvoiceTransfer::initiatedBy($user->id)->where('status', 'pending')->count(),
                    'sent_accepted' => InvoiceTransfer::initiatedBy($user->id)->where('status', 'accepted')->count(),
                    'sent_rejected' => InvoiceTransfer::initiatedBy($user->id)->where('status', 'rejected')->count(),
                    'received_pending' => InvoiceTransfer::respondableBy($user->id)->where('status', 'pending')->count(),
                    'received_accepted' => InvoiceTransfer::respondableBy($user->id)->where('status', 'accepted')->count(),
                    'received_rejected' => InvoiceTransfer::respondableBy($user->id)->where('status', 'rejected')->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getStats: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب قائمة الموظفين للتسليم
     */
    public function getEmployees()
    {
        try {
            $user = auth()->user();

            $employees = InvoiceTransferRules::recipients($user)
                ->map(fn (User $employee) => $employee->only(['id', 'name', 'role', 'can_receive']))
                ->values();

            return response()->json([
                'success' => true,
                'data' => $employees,
                'count' => $employees->count(),
                'current_user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting employees', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموظفين: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * جلب التحويلات للعميل
     */
    public function getClientTransfers($clientId)
    {
        try {
            $user = auth()->user();

            // تحديد الصلاحيات حسب الدور
            $isManagerOrAdmin = ($user->hasRoleName('manager') || $user->hasRoleName('admin')); // ✅ hasRoleName() بدلاً من عمود role النصي

            // الفواتير المرسلة (التسليم) - الموظف يرى فقط ما أرسله هو
            $sentQuery = InvoiceTransfer::with(['invoice', 'toEmployee:id,name'])
                ->where('client_id', $clientId)
                ->initiatedBy($user->id); // ما بدأه: تسليمٌ أرسله أو استلامٌ طلبه

            $sentTransfers = $sentQuery->orderBy('created_at', 'desc')->get();

            // الفواتير المستلمة (الاستلام) - الموظف يرى فقط ما هو مرسل إليه
            $receivedQuery = InvoiceTransfer::with(['invoice', 'fromEmployee:id,name'])
                ->where('client_id', $clientId)
                ->respondableBy($user->id); // ما ينتظر ردَّه

            $receivedTransfers = $receivedQuery->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'sent' => $sentTransfers,
                    'received' => $receivedTransfers,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getClientTransfers: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التحويلات',
                'data' => [
                    'sent' => [],
                    'received' => [],
                ],
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
