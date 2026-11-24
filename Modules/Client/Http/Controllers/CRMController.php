<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\PaymentsProcess;
use App\Models\Statuses;
use App\Models\User;
use App\Services\Clients\ClientEmployeeCandidates;
use App\Services\Clients\VisitNoteCards;
use App\Support\AffiliationTree;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * شاشة إدارة علاقات العملاء — شجرةُ العميل ومجموعتِه وموظفيه وخطِّه الزمني.
 *
 * **عرضٌ فقط.** لا كتابةَ هنا: تسجيلُ إجراء المتابعة نفسه يجري في
 * `ClientController::addnotes()` و`quickVisitNote()` وتحرسه صلاحيات
 * «المتابعة». ولذلك صلاحيةٌ واحدة على هذا المتحكّم لا أربع.
 *
 * **ما يأتي من الإعدادات**: `crm_enabled` في «إعدادات وحدات العملاء» —
 * مطفأً تُمنع الشاشة وروابطُها المباشرة معاً.
 *
 * **السجلّات**: كل `catch` كان ينادي `Log::error()` — ملفُّ لارافيل لا
 * تراه شاشة «سجلّ الأخطاء». صارت `OperationLog::failure()` فتُكتب في
 * سجلّ الأخطاء وسجلّ الأحداث معاً.
 */
class CRMController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientCRM';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients_crm_view');
    }

    public function mang_client(Request $request)
    {
        if ($block = $this->sectionBlock('crm')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        // زيادة الوقت المسموح — بحارس: استضافاتٌ مشتركة تعطّل الدالّة، وفي PHP 8
        // استدعاءُ دالّةٍ معطَّلة خطأٌ قاتل (500) لا تحذير
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        // Get the current user's branch
        $user = Auth::user();
        $branchId = $user->branch_id;

        // Get all branches for filter
        $allBranches = \App\Models\Branch::select('id', 'name')->get();

        // Get selected branch from request or use user's branch
        $selectedBranchId = $request->input('branch_id', $branchId);

        // Check if the selected branch is main
        $selectedBranch = \App\Models\Branch::find($selectedBranchId);
        $isMainBranch = $selectedBranch && ($selectedBranch->name === 'main' || $selectedBranch->is_main === 1);

        $userBranch = $branchId ? \App\Models\Branch::withoutGlobalScopes()->find($branchId) : null;
        $userIsMainBranch = $userBranch && ($userBranch->name === 'main' || $userBranch->is_main === 1);
        $cascadeBranches = $this->getCascadeBranchesForFilter($userBranch, $userIsMainBranch);

        // الجهات وحدها — العملاء يُنسبون إليها أدناه بتبعيّتهم، لا بعلاقات الجهة
        $query = Branch::query();

        /* الجهة المختارة وما تحتها في الشجرة **وحدها** — نفس تعريف «تابع لـ»
           (`AffiliationTree`/`Client::scopeInTreeNode`). كان يُضاف كلُّ عنصرٍ
           `territory_office_id` له = المختارة ولو لم يكن تحتها، فتصفيةُ «منطقة مكة
           المكرمة» تُدخل «مجموعة الليث والغالة وغميقة» (٤٤ عميلاً) من خارجها. */
        if (! $isMainBranch && $selectedBranch) {
            $treeIds = $selectedBranch->descendantBranchIds()->push((int) $selectedBranch->id)->unique()->values()->all();
            $query->whereIn('id', $treeIds);
        }

        $regionGroups = $query->select('id', 'name', 'territory_office_id', 'parent_id', 'node_type', 'is_territory')->get();

        /* كلُّ عميلٍ في جهةٍ واحدة: «تابع لـ» (`tree_node_id`)، وللصفوف القديمة بلا تبعيّة
           `branch_id` ثم منطقة حيّه — نفس ترتيب `AffiliationTree::constrainClients()`
           و`BranchesController::clientNodeExpression()`.

           كانت الشجرة تجمع `Branch::clients` (على `branch_id` = الفرع المحاسبي المشتقّ)
           مع `neighborhoods.region_id`: فعميلُ المنفذ أو المجموعة يظهر أيضاً تحت الفرع
           الذي فوقه وفي منطقة حيّه (٢٥٩٩ عميلاً محلياً)، ولا يظهر أصلاً من لم يُنسب إلا
           بـ`tree_node_id` (٣٠ عميلاً). */
        $nodeIdSet = $regionGroups->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        $clientsByNode = collect();

        if ($nodeIdSet) {
            $clientsQuery = Client::query()
                ->select('id', 'branch_id', 'tree_node_id', 'trade_name', 'first_name', 'last_name', 'phone', 'code', 'status_id', 'registration_stage_id', 'customer_target_id')
                ->with(['status_client:id,name,color', 'registrationStage:id,name,color', 'customerTarget:id,name,color'])
                ->orderBy('id');
            AffiliationTree::constrainClients($clientsQuery, array_keys($nodeIdSet));
            $treeClients = $clientsQuery->get();
            $treeClients->whereNull('tree_node_id')->load('neighborhood:id,client_id,region_id');

            $clientsByNode = $treeClients->groupBy(function ($client) use ($nodeIdSet) {
                if ($client->tree_node_id) {
                    return (int) $client->tree_node_id;
                }

                if ($client->branch_id && isset($nodeIdSet[(int) $client->branch_id])) {
                    return (int) $client->branch_id;
                }

                return (int) optional($client->neighborhood)->region_id;
            });
        }

        // Build the tree hierarchy of groups
        $regionGroupsMap = $regionGroups->keyBy('id');

        foreach ($regionGroups as $group) {
            $group->childGroups = collect();
            $group->directClients = $clientsByNode->get((int) $group->id, collect());
        }

        $roots = collect();

        foreach ($regionGroups as $group) {
            if ($group->parent_id && $regionGroupsMap->has($group->parent_id)) {
                $regionGroupsMap->get($group->parent_id)->childGroups->push($group);
            } else {
                $roots->push($group);
            }
        }

        // Calculate recursive client counts for each node
        $calculateTotalClients = function ($group) use (&$calculateTotalClients) {
            $directCount = $group->directClients->count();

            $childCount = 0;
            foreach ($group->childGroups as $child) {
                $childCount += $calculateTotalClients($child);
            }
            $group->total_client_count = $directCount + $childCount;

            return $group->total_client_count;
        };

        foreach ($roots as $root) {
            $calculateTotalClients($root);
        }

        // Flat array of groups ordered hierarchically for the dropdown filter with depth indents
        $orderedGroups = collect();
        $addNodeAndChildren = function ($node, $depth = 0) use (&$orderedGroups, &$addNodeAndChildren) {
            // نوعُ العرض لا المخزَّن — المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»
            $nodeType = Branch::displayNodeType($node, $node->childGroups);
            $typeLabel = [
                'branch' => 'فرع',
                'region' => 'منطقة',
                'group' => 'مجموعة',
                'outlet' => 'منفذ',
            ][$nodeType] ?? 'فرع';

            $node->displayName = str_repeat('— ', $depth).$node->name.' ('.$typeLabel.')';
            $orderedGroups->push($node);
            foreach ($node->childGroups as $child) {
                $addNodeAndChildren($child, $depth + 1);
            }
        };

        foreach ($roots as $root) {
            $addNodeAndChildren($root, 0);
        }

        // Get all statuses (شاشة الجوّال تبني فلترها منها كما هي)
        $statuses = Statuses::select('id', 'name')->get();

        /* فلترا سطح المكتب: «الحالة» للحالة المعتمدة وحدها (نشط/موقوف)، و«الوضع»
           لمراحل التسجيل (`section = registration_stage`) — كانت قائمةُ الحالة تخلط
           الاثنين مع الحالات القديمة. القديمةُ التي ما زال عليها عملاء تبقى
           قابلةً للفلترة في مجموعةٍ مستقلّة، وإلّا اختفى عملاؤها من أي فلتر. */
        $statusOptions = Statuses::assignable()->orderBy('id')->get(['id', 'name']);
        $legacyStatusOptions = Statuses::whereNull('section')->whereNull('kpi_role')
            ->whereIn('id', Client::query()->whereNotNull('status_id')->select('status_id')->distinct())
            ->orderBy('name')->get(['id', 'name']);
        $stageOptions = Statuses::section('registration_stage')->ordered()->get(['id', 'name']);

        $userAgent = $request->header('User-Agent');
        $isMobile = preg_match('/Mobile|Android|BlackBerry|iPhone|Windows Phone/i', $userAgent);
        $viewName = $isMobile ? 'client::relestion_mang_client_mobile' : 'client::relestion_mang_client';

        return view($viewName, [
            'regionGroups' => $roots,
            'allBranches' => $allBranches,
            'selectedBranchId' => $selectedBranchId,
            'clientGroups' => $orderedGroups,
            'statuses' => $statuses,
            'statusOptions' => $statusOptions,
            'legacyStatusOptions' => $legacyStatusOptions,
            'stageOptions' => $stageOptions,
            'isMainBranch' => $isMainBranch,
            'cascadeBranches' => $cascadeBranches,
        ]);
    }

    /**
     * جلب تفاصيل عميل معين
     */
    public function getClientDetails(Request $request, $clientId)
    {
        try {
            $client = Client::with([
                'account',
                'status_client',
                'branch:id,name',
                'invoices' => function ($q) {
                    $q->where('type', '!=', 'returned')
                        ->whereNull('reference_number')
                        ->orderBy('created_at', 'desc')
                        ->limit(20);
                },
                'employee',
                'visits' => function ($q) {
                    $q->with('employee')
                        ->orderBy('visit_date', 'desc')
                        ->limit(20);
                },
                'clientRelations' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(20);
                },
                'clientRelations.employee',
                // رصد المنافسين المرافق للملاحظة — قسم `note.competitors` في
                // `crm_shared_scripts_details` كان يقرؤه ولا يصله شيء من هنا
                'clientRelations.competitorObservations.competitor',
                'invoiceTransfers' => function ($q) {
                    $q->with(['fromEmployee', 'toEmployee', 'invoice'])
                        ->orderBy('created_at', 'desc')
                        ->limit(20);
                },
                'productExchanges' => function ($q) {
                    $q->with(['storehouse', 'employee', 'items.product', 'items.exchangeReason'])
                        ->orderBy('exchange_date', 'desc')->limit(20);
                },
            ])->find($clientId);

            if (! $client) {
                return response()->json(['success' => false, 'error' => 'Client not found'], 404);
            }

            // Get balance
            $balance = 0;
            if ($client->account) {
                $balance = $client->account->balance ?? 0;
            }

            // Get payments
            $paymentsData = [];
            if ($client->invoices) {
                $invoiceIds = $client->invoices->pluck('id')->toArray();
                if (! empty($invoiceIds)) {
                    $payments = PaymentsProcess::whereIn('invoice_id', $invoiceIds)
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $paymentsData = $payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'invoice_id' => $payment->invoice_id,
                            'amount' => $payment->amount ?? 0,
                            'payment_date' => $payment->created_at,
                            'payment_method' => $payment->payment_method ?? 'غير محدد',
                            'created_at' => $payment->created_at,
                        ];
                    })->toArray();
                }
            }

            // بطاقات «ملاحظات الزيارات» — الزيارة والمستند والمنافس والعهدة لكل ملاحظة
            $visitNoteCards = VisitNoteCards::build($client->clientRelations, (int) $client->id);

            $clientData = [
                'id' => $client->id,
                'name' => $client->trade_name,
                'trade_name' => $client->trade_name,
                'phone' => $client->phone,
                'email' => $client->email,
                'branch_name' => $client->branch->name ?? null,
                'employee_name' => $client->employee->name ?? null,
                'code' => $client->code,
                'created_at' => $client->created_at,
                'balance' => $balance,
                'invoices' => $client->invoices->map(function ($invoice) {
                    $isPaid = $invoice->is_paid;
                    $statusText = 'غير محدد';
                    if ($isPaid) {
                        $statusText = 'مدفوعة بالكامل';
                    } elseif ($invoice->grand_total > 0) {
                        $statusText = 'مدفوعة جزئياً';
                    }

                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => $invoice->grand_total ?? $invoice->total ?? 0,
                        'date' => $invoice->date ?? $invoice->created_at->format('Y-m-d'),
                        'is_paid' => $isPaid,
                        'status_text' => $statusText,
                        'status_class' => $isPaid ? 'success' : 'warning',
                        'created_at' => $invoice->created_at,
                    ];
                })->toArray(),
                'clientRelations' => $client->clientRelations->map(function ($relation) use ($visitNoteCards) {
                    $attachmentsArray = $this->processAttachments($relation->attachments);
                    $clientEmployeeData = null;

                    if ($relation->clientEmployee) {
                        $clientEmployeeData = [
                            'id' => $relation->clientEmployee->id,
                            'employee_name' => $relation->clientEmployee->employee_name ?? '',
                            'employee_phone' => $relation->clientEmployee->employee_phone ?? '',
                            'employee_position' => $relation->clientEmployee->employee_position ?? '',
                            'is_primary' => $relation->clientEmployee->is_primary ?? false,
                            'image' => $relation->clientEmployee->image ?? null,
                            'invoice_id' => $relation->clientEmployee->invoice_id ?? null,
                        ];
                    }

                    return [
                        'id' => $relation->id,
                        'client_id' => $relation->client_id,
                        'employee_id' => $relation->employee_id,
                        'employee' => $relation->relationLoaded('employee') && $relation->employee ? $relation->employee->name : 'غير معروف',
                        'description' => $relation->description ?? '',
                        'process' => $relation->process ?? '',
                        'status' => $relation->status ?? '',
                        'deposit_count' => $relation->deposit_count ?? null,
                        'site_type' => $relation->site_type ?? null,
                        'competitor_documents' => $relation->competitor_documents ?? null,
                        'attachments' => $relation->attachments,
                        'attachments_array' => $attachmentsArray,
                        'clientEmployee' => $clientEmployeeData,
                        'competitors' => $relation->competitorObservations
                            ->whereNotNull('competitor_id')
                            ->map(fn ($o) => ['name' => $o->display_name, 'asset_count' => $o->asset_count])
                            ->values(),
                        'card' => $visitNoteCards[$relation->id] ?? null,
                        'created_at' => $relation->created_at,
                    ];
                })->toArray(),
                /* شريط «المنافسون عند العميل الآن» في تبويب ملاحظات الزيارات —
                   آخر رصدٍ لكل منافس. صفُّ «لا يوجد منافس» بلا اسم فلا يدخله. */
                'competitorObservations' => \App\Models\VisitCompetitorObservation::where('client_id', $client->id)
                    ->with(['competitor:id,name', 'employee:id,name', 'visit:id,visit_number'])
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->map(fn ($o) => [
                        'name' => $o->competitor_id ? $o->display_name : null,
                        'asset_count' => $o->competitor_id ? (int) $o->asset_count : null,
                        'photo_url' => $o->photo_path ? asset('assets/uploads/visit_competitors/'.$o->photo_path) : null,
                        'employee' => $o->employee->name ?? null,
                        'visit_number' => $o->visit->visit_number ?? null,
                        'created_at' => $o->created_at?->format('Y-m-d h:i A'),
                    ])
                    ->values()
                    ->toArray(),
                'visits' => $client->visits->map(function ($visit) {
                    $employeeName = 'غير محدد';
                    if ($visit->relationLoaded('employee') && $visit->employee) {
                        $employeeName = $visit->employee->name ?? 'غير محدد';
                    }

                    return [
                        'id' => $visit->id,
                        'client_id' => $visit->client_id,
                        'visit_date' => $visit->visit_date,
                        'employee' => $employeeName,
                        'created_at' => $visit->created_at,
                    ];
                })->toArray(),
                'payments' => $paymentsData,
                'invoiceTransfers' => $client->invoiceTransfers ? $client->invoiceTransfers->map(function ($transfer) {
                    return [
                        'id' => $transfer->id,
                        'invoice_code' => $transfer->invoice ? $transfer->invoice->code : ($transfer->invoice_code ?? '--'),
                        'invoice_amount' => $transfer->invoice_amount,
                        'from_employee_id' => $transfer->from_employee_id,
                        'to_employee_id' => $transfer->to_employee_id,
                        'from_employee' => $transfer->fromEmployee ? $transfer->fromEmployee->name : '--',
                        'to_employee' => $transfer->toEmployee ? $transfer->toEmployee->name : '--',
                        'status' => $transfer->status,
                        'status_text' => $transfer->status_text,
                        'status_color' => $transfer->status_color,
                        'created_at' => $transfer->created_at,
                        'notes' => $transfer->sender_notes,
                    ];
                })->toArray() : [],
                'productExchanges' => $client->productExchanges->map(function ($exchange) {
                    return [
                        'id' => $exchange->id,
                        'reference_no' => $exchange->reference_no ?? $exchange->id,
                        'exchange_date' => $exchange->exchange_date,
                        'storehouse' => $exchange->storehouse ? $exchange->storehouse->name : '--',
                        'items_count' => $exchange->items ? $exchange->items->count() : 0,
                        'status' => 'completed',
                        'created_at' => $exchange->created_at,
                    ];
                })->toArray(),
                'status_name' => $client->status_client ? $client->status_client->name : null,
                'status_color' => $client->status_client ? $client->status_client->color : null,
            ];

            return response()->json(['success' => true, 'client' => $clientData]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'get_client_details', (int) $clientId);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * موظفو نافذتَي «إضافة إلى خط السير» و«إرسال إشعار» لعميلٍ واحد.
     *
     * **من يظهر** — `ClientEmployeeCandidates`: من أقسام موظفي العملاء في
     * «إعدادات وحدات العملاء» وحدها، ثمّ أحدُ اثنين:
     * - المُسنَدون للعميل صراحةً (`client_employee`) — `group = assigned`، أوّلاً؛
     * - التابعون لعقدته: المنطقة أو الفرع أو المجموعة أو المنفذ الذي يتبعه العميل
     *   وما تحتها — `group = branch`.
     *
     * كانت القائمة تُبنى من `neighborhoods` و`branch_id` لا من `tree_node_id` الذي
     * يختاره المستخدم، وبلا قيد القسم. والشرطُ نفسه يُفرض عند الحفظ في
     * `ItineraryController::filterClientEligibleEmployees` — قائمةٌ مقيَّدة وحدها
     * ليست حارساً.
     *
     * **معرّف مستخدم لا موظف.** خطُّ السير يُكتب في `employee_client_visits.employee_id`
     * وهو معرّفُ صفٍّ في `users`، بينما `client_employee.employee_id` معرّفُ صفٍّ في
     * `employees` — الخدمة تترجم بينهما.
     */
    public function getEmployeesForClient(Request $request, $clientId)
    {
        try {
            if (! Client::whereKey($clientId)->exists()) {
                return response()->json(['success' => true, 'employees' => []]);
            }

            $sets = app(ClientEmployeeCandidates::class)->forClients([(int) $clientId]);

            $employees = $sets['assigned']
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'group' => 'assigned'])
                ->concat($sets['node']->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'group' => 'branch']))
                ->values();

            return response()->json([
                'success' => true,
                'employees' => $employees,
                'assigned_count' => $sets['assigned']->count(),
                'branch_count' => $sets['node']->count(),
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'get_employees_for_client', (int) $clientId);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * موظفو نافذة «الإضافة الجماعية لخط السير».
     *
     * مع `client_ids` (العملاء المحدَّدون): من قسم موظفي العملاء، ومُسنَدٌ لأحدهم أو
     * تابعٌ لعقدة أحدهم — والحفظ يتخطّى كلَّ زوج موظف/عميل لا يستوفي الشرط.
     * بلا عملاء: من القسم، وتابعون للمجموعة وما تحتها.
     *
     * كانت تتّسع لأسلاف المجموعة صعوداً حتى الجذر، وبلا مجموعةٍ محدَّدة كانت
     * النافذة تجلب كلَّ موظفي النطاق من `employees.list`.
     */
    public function getEmployeesForGroup(Request $request)
    {
        try {
            $candidates = app(ClientEmployeeCandidates::class);
            $clientIds = array_values(array_filter(array_map('intval', (array) $request->input('client_ids', []))));

            if ($clientIds !== []) {
                $sets = $candidates->forClients($clientIds);

                return response()->json([
                    'success' => true,
                    'employees' => $sets['assigned']->concat($sets['node'])
                        ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                        ->values(),
                ]);
            }

            $groupId = (int) $request->input('group_id');

            if (! $groupId) {
                return response()->json(['success' => false, 'error' => 'Group ID is required'], 400);
            }

            if (! Branch::withoutGlobalScopes()->whereKey($groupId)->exists()) {
                return response()->json(['success' => false, 'error' => 'Group not found'], 404);
            }

            return response()->json([
                'success' => true,
                'employees' => $candidates->forNode($groupId),
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'get_employees_for_group');

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * جلب عملاء المجموعة عند النقر عليها
     */
    public function getGroupClients(Request $request)
    {
        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(30);
            }

            $groupId = $request->input('group_id');

            if (! $groupId) {
                return response()->json(['success' => false, 'error' => 'Group ID is required'], 400);
            }

            $group = Branch::withoutGlobalScopes()->find($groupId);
            if (! $group) {
                return response()->json(['success' => false, 'error' => 'Group not found'], 404);
            }

            // جلب الأباء والأبناء لتشمل كل الشجرة المتعلقة بهذه المجموعة
            $validBranchIds = collect([$groupId])
                ->merge($group->descendantBranchIds())
                ->unique()
                ->toArray();

            /* عملاءُ الجهة وما تحتها بتبعيّتهم (`tree_node_id`)، والأعمدة القديمة للصفوف
               بلا تبعيّة فقط — كانت تُجمع من `neighborhoods.region_id` و`branch_id`
               فتُدخل عملاءَ ليسوا تابعين لهذه الجهة وتُسقط من نُسب بـ`tree_node_id` وحده. */
            $clientIdsQuery = Client::query()->select('id');
            AffiliationTree::constrainClients($clientIdsQuery, array_map('intval', $validBranchIds));
            $clientIds = $clientIdsQuery->pluck('id');

            if ($clientIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'clients' => [],
                    'count' => 0,
                ]);
            }

            // جلب العملاء مع بياناتهم الاساسية فقط لتسريع العرض
            $clients = Client::whereIn('id', $clientIds)
                ->with(['status_client', 'registrationStage', 'customerTarget'])
                ->select('id', 'trade_name', 'first_name', 'last_name', 'phone', 'code', 'status_id', 'registration_stage_id', 'customer_target_id', 'created_at', 'branch_id')
                ->get();

            // Map clients data
            $clientsData = $clients->map(function ($client) {
                $name = $client->trade_name ?: trim($client->first_name.' '.$client->last_name);
                if (empty($name)) {
                    $name = 'عميل بدون اسم ('.$client->id.')';
                }

                return [
                    'id' => $client->id,
                    'name' => $name,
                    'trade_name' => $name,
                    'phone' => $client->phone,
                    'code' => $client->code,
                    'status_id' => $client->status_id ?? null,
                    'status_name' => $client->status_client ? $client->status_client->name : null,
                    'status_color' => $client->status_client ? $client->status_client->color : null,
                    // وضع العميل (مرحلة التسجيل)
                    'stage_id' => $client->registration_stage_id ?? null,
                    'stage_name' => $client->registrationStage ? $client->registrationStage->name : null,
                    'stage_color' => $client->registrationStage ? $client->registrationStage->color : null,
                    // فئة العميل (هدف العميل)
                    'target_id' => $client->customer_target_id ?? null,
                    'target_name' => $client->customerTarget ? $client->customerTarget->name : null,
                    'target_color' => $client->customerTarget ? $client->customerTarget->color : null,
                    'created_at' => $client->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'clients' => $clientsData,
                'count' => $clientsData->count(),
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'get_group_clients');

            return response()->json([
                'success' => false,
                'error' => 'حدث خطأ أثناء تحميل العملاء: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * البحث عن عميل لجلب موقعه في الشجرة (المجموعة/الفرع)
     */
    public function searchClientNode(Request $request)
    {
        try {
            $query = $request->input('q');
            if (! $query || mb_strlen($query) < 2) {
                return response()->json([]);
            }

            $clients = Client::select('id', 'trade_name', 'code', 'phone', 'branch_id')
                ->where('trade_name', 'LIKE', "%{$query}%")
                ->orWhere('code', 'LIKE', "%{$query}%")
                ->orWhere('phone', 'LIKE', "%{$query}%")
                ->limit(20)
                ->get();

            $results = $clients->map(function ($client) {
                $groupId = null;
                // محاولة الحصول على المجموعة من الحي
                $neighborhood = \App\Models\Neighborhood::where('client_id', $client->id)->first();
                if ($neighborhood && $neighborhood->region_id) {
                    $groupId = $neighborhood->region_id;
                } elseif ($client->branch_id) {
                    // إذا لم يكن في حي، نستخدم فرعه
                    $groupId = $client->branch_id;
                }

                return [
                    'id' => $client->id,
                    'text' => $client->trade_name.($client->code ? ' - '.$client->code : ''),
                    'group_id' => $groupId,
                ];
            });

            return response()->json(['results' => $results]);

        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'search_client_node');

            return response()->json(['results' => []]);
        }
    }

    /**
     * الحصول على نص نوع الموقع بالعربية
     */
    private function getSiteTypeText($siteType)
    {
        $types = [
            'independent_booth' => 'بسطة مستقلة',
            'grocery' => 'بقالة',
            'supplies' => 'تموينات',
            'markets' => 'أسواق',
            'station' => 'محطة',
        ];

        return $types[$siteType] ?? $siteType;
    }

    /**
     * معالجة المرفقات
     */
    private function processAttachments($attachments)
    {
        $attachmentsArray = [];

        if ($attachments) {
            if (is_string($attachments)) {
                $decoded = json_decode($attachments, true);
                if (is_array($decoded)) {
                    $attachmentsArray = $decoded;
                } else {
                    $attachmentsArray = [$attachments];
                }
            } elseif (is_array($attachments)) {
                $attachmentsArray = $attachments;
            }
        }

        return $attachmentsArray;
    }

    /**
     * بناء بيانات فلاتر الشجرة الجانبية
     */
    private function getCascadeBranchesForFilter(?Branch $userBranch, bool $isMainBranch)
    {
        $query = Branch::withoutGlobalScopes()
            ->select('id', 'name', 'parent_id', 'is_territory', 'node_type')
            ->orderBy('name');

        if (! $isMainBranch && $userBranch) {
            $scopeIds = $userBranch->descendantBranchIds()
                ->push((int) $userBranch->id)
                ->merge($userBranch->ancestors()->pluck('id'))
                ->unique()
                ->values()
                ->all();
            $query->whereIn('id', $scopeIds);
        }

        $branches = $query->get();
        $childrenOf = $branches->groupBy('parent_id');

        // تسميةُ النوع للعرض (`type_label`) — المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»،
        // و`is_territory` وحدَه كان يسمّي في الفلتر كلَّ مجموعةٍ ومنفذٍ «منطقة»
        return $branches->each(function ($branch) use ($childrenOf) {
            $branch->type_label = Branch::nodeTypeMeta(
                Branch::displayNodeType($branch, $childrenOf->get($branch->id, collect()))
            )['label'];
        })->values();
    }

    // عرض بطاقة الملاحظة من الـ Blade template
    public function renderNote(Request $request)
    {
        $note = $request->input('note');

        // البحث عن الملاحظة الفعلية من قاعدة البيانات
        $noteModel = ClientEmployeeNote::find($note['id']);

        if (! $noteModel) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        // عرض الـ partial من الـ Blade
        $html = view('client::partials.tabs.note-card', ['note' => $noteModel])->render();

        return response()->json(['html' => $html]);
    }
}
