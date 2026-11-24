<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\EmployeeClientVisit;
use App\Models\notifications;
use App\Models\User;
use App\Models\VisitJustification;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * إدارة تبريرات/إعفاءات الزيارات — الشاشة الإدارية الموحَّدة.
 *
 * قسمان في شاشةٍ واحدة (`visit_justifications.index`):
 *  - «فترات التبرير/الإعفاء» (`VisitJustification`) — الإدارة الكاملة
 *    (إضافة/تعديل/حذف/اعتماد) لسجلّ فترةٍ كاملة، مستقلٍّ عن صلاحية الموظف
 *    الحالية. هذا هو الإصلاح الجذري لمشكلة «تغيير الصلاحية يعيد فتح الفترة».
 *  - «تبريرات الزيارات الفردية» — المراجعة القديمة لتبرير كل صفّ زيارةٍ
 *    نصّياً (`EmployeeClientVisit.justification`)، التي يقدّمها الموظف نفسه
 *    من شاشة «تبريراتي» حين يُحجَب بمعرفة `CheckIncompleteVisits`. لم تُمسّ
 *    آليتها — فقط استفادت من استثناء الفترات المعتمدة (راجع
 *    `EmployeeClientVisit::needingJustificationQuery()`).
 *
 * @see \Modules\Client\Http\Controllers\IncompleteVisitsController الجزء الذاتي-الخدمة
 */
class VisitJustificationController extends Controller
{
    private const LOG_MODULE = 'VisitJustification';

    /**
     * فرع المستخدم الحالي، وهل هو فرعٌ رئيسي (يرى كل الفروع)، وقائمة
     * الفروع الظاهرة له — نفس منطق الشاشة الأصلي، مركزيّاً بدل تكراره.
     */
    private function branchScope(): array
    {
        $currentUser = Auth::user();
        $userBranch = $currentUser->branch ?? null;
        $isMainBranch = $userBranch ? (bool) $userBranch->is_main : false;

        $branches = $isMainBranch
            ? Branch::where('status', 1)->orderBy('name')->get()
            : ($userBranch ? collect([$userBranch]) : collect());

        return [$branches, $isMainBranch, $userBranch];
    }

    /** قائمة الموظفين المرئيين لفرع المستخدم — لقوائم الفلترة والإضافة. */
    private function visibleEmployees(bool $isMainBranch, $userBranch)
    {
        $query = User::whereNotNull('employee_id')->with('branch');

        if (! $isMainBranch && $userBranch) {
            $query->where('branch_id', $userBranch->id);
        }

        return $query->orderBy('name')->get();
    }

    // ═══════════════════════ فترات التبرير/الإعفاء ═══════════════════════

    public function index(Request $request)
    {
        [$branches, $isMainBranch, $userBranch] = $this->branchScope();

        $base = VisitJustification::query();
        if (! $isMainBranch && $userBranch) {
            $base->whereHas('employee', fn ($q) => $q->where('branch_id', $userBranch->id));
        }

        $periodStats = [
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
            'rejected' => (clone $base)->rejected()->count(),
        ];

        $periodsQuery = (clone $base)->with(['employee.branch', 'client', 'createdBy', 'approvedBy']);

        if ($request->filled('employee_id')) {
            $periodsQuery->where('employee_id', $request->employee_id);
        }
        if ($request->filled('client_id')) {
            $periodsQuery->where('client_id', $request->client_id);
        }
        if ($request->filled('branch_id')) {
            $periodsQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $request->branch_id));
        }
        if ($request->filled('status')) {
            $periodsQuery->where('status', match ($request->status) {
                'approved' => VisitJustification::STATUS_APPROVED,
                'rejected' => VisitJustification::STATUS_REJECTED,
                default => VisitJustification::STATUS_PENDING,
            });
        }
        if ($request->filled('date_from')) {
            $periodsQuery->where('date_to', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $periodsQuery->where('date_from', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $periodsQuery->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('trade_name', 'like', "%{$search}%"));
            });
        }

        $periods = $periodsQuery->orderBy('date_from', 'desc')->paginate(15)->withQueryString();

        $employees = $this->visibleEmployees($isMainBranch, $userBranch);
        $clients = Client::orderBy('trade_name')->get(['id', 'trade_name', 'code']);

        // ── التبويب الثاني: تبريرات الزيارات الفردية (كما كانت) ──
        try {
            $rowsQuery = EmployeeClientVisit::query()->with(['employee.branch', 'client']);
            if (! $isMainBranch && $userBranch) {
                $rowsQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $userBranch->id));
            }
            $allVisits = $rowsQuery->get();

            $rowJustifications = $allVisits->filter(fn ($v) => ! is_null($v->justification) && trim($v->justification) !== '');
            $rowJustifications = $rowJustifications->filter(
                fn ($v) => is_null($v->justification_approved) || $v->justification_approved == 0
            )->sortByDesc('justification_date')->values();

            $rowStats = [
                'pending' => $rowJustifications->count(),
                'approved' => $allVisits->filter(fn ($v) => $v->justification_approved == 1)->count(),
                'rejected' => $allVisits->filter(fn ($v) => $v->justification_approved == 2)->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error loading legacy row justifications: '.$e->getMessage());
            $rowJustifications = collect();
            $rowStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        }

        return view('client::visit_justifications.index', compact(
            'periods', 'periodStats', 'employees', 'clients', 'branches',
            'rowJustifications', 'rowStats'
        ));
    }

    public function create()
    {
        [, $isMainBranch, $userBranch] = $this->branchScope();

        $employees = $this->visibleEmployees($isMainBranch, $userBranch);
        $clients = Client::orderBy('trade_name')->get(['id', 'trade_name', 'code']);

        return view('client::visit_justifications.create', compact('employees', 'clients'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePeriod($request);

        $existing = VisitJustification::fullyCoveringPeriod(
            $validated['employee_id'], $validated['client_id'], $validated['date_from'], $validated['date_to']
        );

        if ($existing) {
            return redirect()->back()->withInput()->with(
                'error',
                'الفترة المطلوبة مغطاة بالكامل بتبريرٍ سابق (#'.$existing->id.') من '.$existing->date_from->format('Y-m-d').' إلى '.$existing->date_to->format('Y-m-d').'.'
            );
        }

        $canApprove = hasPermission('clients_visit_justifications_approve');

        $period = VisitJustification::create([
            ...$validated,
            'status' => $canApprove ? VisitJustification::STATUS_APPROVED : VisitJustification::STATUS_PENDING,
            'created_by' => Auth::id(),
            'approved_by' => $canApprove ? Auth::id() : null,
            'approved_at' => $canApprove ? now() : null,
        ]);

        OperationLog::record(
            'visit_justification_period_created',
            self::LOG_MODULE,
            $period,
            'إضافة فترة تبرير/إعفاء للموظف «'.($period->employee->name ?? $period->employee_id).'» مع العميل «'.($period->client->trade_name ?? $period->client_id).'» من '.$validated['date_from'].' إلى '.$validated['date_to'],
            ['new' => $validated]
        );

        $this->notifyEmployee($period, $canApprove ? 'تمت إضافة واعتماد إعفاء زيارات لك' : 'تمت إضافة تبرير زياراتٍ لك بانتظار الاعتماد');

        return redirect()->route('admin.visit-justifications.index')
            ->with('success', $canApprove ? 'تمت إضافة واعتماد فترة التبرير بنجاح.' : 'تمت إضافة فترة التبرير، بانتظار الاعتماد.');
    }

    // ═══════════════════ تبرير جماعي بالفترة ═══════════════════
    //
    // نفس ما يفعله store() لكن لعميلٍ واحد — هنا لموظفٍ واحد وكل عملائه
    // الذين لهم زياراتٌ غير مكتملة ضمن فترةٍ واحدة دفعةً واحدة، بدل تبريرٍ
    // فردي لكل عميل. صفّ VisitJustification واحد لكل عميلٍ مكتشَف — لا
    // تعديل على جدول employee_client_visits نفسه، فالاستثناء يعمل عبر
    // EmployeeClientVisit::needingJustificationQuery() كما هو.

    public function bulkPage()
    {
        [, $isMainBranch, $userBranch] = $this->branchScope();
        $employees = $this->visibleEmployees($isMainBranch, $userBranch);

        return view('client::visit_justifications.bulk', compact('employees'));
    }

    /**
     * العملاء الذين لهذا الموظف زياراتٌ غير مكتملة معهم ضمن الفترة —
     * نفس تعريف «غير مكتملة» المستعمَل في CheckIncompleteVisits تماماً
     * (needingJustificationQuery)، لا تعريفٌ جديد يفترق عنه.
     */
    public function bulkSearch(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ], [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'date_from.required' => 'يرجى تحديد تاريخ البداية',
            'date_to.required' => 'يرجى تحديد تاريخ النهاية',
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
        ]);

        $counts = EmployeeClientVisit::needingJustificationQuery((int) $validated['employee_id'])
            ->whereBetween('visit_date', [$validated['date_from'], $validated['date_to']])
            ->select('client_id', DB::raw('count(*) as total'))
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        $clients = Client::whereIn('id', $counts->keys())
            ->orderBy('trade_name')
            ->get(['id', 'trade_name', 'code'])
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->display_name,
                'code' => $client->code,
                'incomplete_count' => $counts->get($client->id, 0),
            ]);

        return response()->json(['clients' => $clients->values()]);
    }

    /**
     * إنشاء فترة تبريرٍ واحدة لكل عميلٍ من العملاء المُختارين — بنفس
     * منطق store() حرفياً (الاعتماد التلقائي لمن يملك الصلاحية، ومنع
     * التغطية الكاملة المكرَّرة)، مكرَّراً داخل معاملةٍ واحدة.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:clients,id',
        ], [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'date_from.required' => 'يرجى تحديد تاريخ البداية',
            'date_to.required' => 'يرجى تحديد تاريخ النهاية',
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.required' => 'سبب التبرير مطلوب',
            'client_ids.required' => 'لم يُعثر على عملاء بزياراتٍ غير مكتملة ضمن هذه الفترة',
        ]);

        $employeeId = (int) $validated['employee_id'];
        $canApprove = hasPermission('clients_visit_justifications_approve');
        $created = [];
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach (array_unique($validated['client_ids']) as $clientId) {
                $existing = VisitJustification::fullyCoveringPeriod(
                    $employeeId, (int) $clientId, $validated['date_from'], $validated['date_to']
                );

                if ($existing) {
                    $skipped++;

                    continue;
                }

                $created[] = VisitJustification::create([
                    'employee_id' => $employeeId,
                    'client_id' => $clientId,
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => $canApprove ? VisitJustification::STATUS_APPROVED : VisitJustification::STATUS_PENDING,
                    'created_by' => Auth::id(),
                    'approved_by' => $canApprove ? Auth::id() : null,
                    'approved_at' => $canApprove ? now() : null,
                ]);
            }

            $employeeName = User::find($employeeId)->name ?? $employeeId;

            OperationLog::record(
                'visit_justification_period_bulk_created',
                self::LOG_MODULE,
                $employeeId,
                'تبرير جماعي بالفترة للموظف «'.$employeeName.'»: '.count($created).' فترة لعملاء مختلفين، من '
                    .$validated['date_from'].' إلى '.$validated['date_to'].($skipped ? ' (تخطّي '.$skipped.' مغطًّى مسبقاً)' : ''),
                ['new' => ['employee_id' => $employeeId, 'client_count' => count($created), 'date_from' => $validated['date_from'], 'date_to' => $validated['date_to']]]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_store');

            return redirect()->back()->withInput()->with('error', 'تعذّر إنشاء فترات التبرير: '.$e->getMessage());
        }

        // إشعارٌ واحدٌ ملخِّص للموظف — لا إشعارٌ لكل عميل، فمئات الإشعارات
        // لموظفٍ واحد عن نفس الفعل لا تُقرأ، تُتجاهَل
        if (count($created)) {
            try {
                notifications::create([
                    'user_id' => $employeeId,
                    'type' => 'visit',
                    'title' => $canApprove ? 'تمت إضافة واعتماد إعفاء زيارات جماعي لك' : 'تمت إضافة تبرير زياراتٍ جماعي لك بانتظار الاعتماد',
                    'message' => count($created).' عميل من '.$validated['date_from'].' إلى '.$validated['date_to'],
                    'read' => false,
                    'data' => ['bulk' => true, 'client_count' => count($created)],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to notify employee for bulk visit justification: '.$e->getMessage());
            }
        }

        $message = 'تم إنشاء '.count($created).' فترة تبرير بنجاح.';
        if ($skipped) {
            $message .= ' وتخطّي '.$skipped.' عميل مغطًّى بفترةٍ سابقة بالفعل.';
        }

        return redirect()->route('admin.visit-justifications.index')->with('success', $message);
    }

    public function show($id)
    {
        $period = VisitJustification::with(['employee.branch', 'client', 'createdBy', 'updatedBy', 'approvedBy'])
            ->findOrFail($id);

        return view('client::visit_justifications.show', compact('period'));
    }

    public function edit($id)
    {
        [, $isMainBranch, $userBranch] = $this->branchScope();

        $period = VisitJustification::findOrFail($id);
        $employees = $this->visibleEmployees($isMainBranch, $userBranch);
        $clients = Client::orderBy('trade_name')->get(['id', 'trade_name', 'code']);

        return view('client::visit_justifications.edit', compact('period', 'employees', 'clients'));
    }

    public function update(Request $request, $id)
    {
        $period = VisitJustification::findOrFail($id);
        $validated = $this->validatePeriod($request);

        $existing = VisitJustification::fullyCoveringPeriod(
            $validated['employee_id'], $validated['client_id'], $validated['date_from'], $validated['date_to'], $period->id
        );

        if ($existing) {
            return redirect()->back()->withInput()->with(
                'error',
                'الفترة المطلوبة مغطاة بالكامل بتبريرٍ آخر (#'.$existing->id.') من '.$existing->date_from->format('Y-m-d').' إلى '.$existing->date_to->format('Y-m-d').'.'
            );
        }

        $before = $period->only(['employee_id', 'client_id', 'date_from', 'date_to', 'reason', 'notes']);

        $period->update([...$validated, 'updated_by' => Auth::id()]);

        OperationLog::record(
            'visit_justification_period_updated',
            self::LOG_MODULE,
            $period,
            'تعديل فترة تبرير/إعفاء رقم #'.$period->id,
            ['old' => $before, 'new' => $validated]
        );

        return redirect()->route('admin.visit-justifications.index')->with('success', 'تم تحديث فترة التبرير بنجاح.');
    }

    public function destroy($id)
    {
        $period = VisitJustification::findOrFail($id);

        OperationLog::record(
            'visit_justification_period_deleted',
            self::LOG_MODULE,
            $period->id,
            'حذف فترة تبرير/إعفاء رقم #'.$period->id.' للموظف «'.($period->employee->name ?? $period->employee_id).'»',
            ['old' => $period->only(['employee_id', 'client_id', 'date_from', 'date_to', 'reason'])]
        );

        $period->delete();

        return redirect()->route('admin.visit-justifications.index')->with('success', 'تم حذف فترة التبرير.');
    }

    public function approvePeriod($id)
    {
        $period = VisitJustification::findOrFail($id);

        $period->update([
            'status' => VisitJustification::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        OperationLog::record('visit_justification_period_approved', self::LOG_MODULE, $period, 'اعتماد فترة تبرير/إعفاء رقم #'.$period->id);
        $this->notifyEmployee($period, 'تمت الموافقة على فترة إعفاء زياراتك');

        return redirect()->back()->with('success', 'تم اعتماد فترة التبرير.');
    }

    public function rejectPeriod($id)
    {
        $period = VisitJustification::findOrFail($id);

        $period->update([
            'status' => VisitJustification::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        OperationLog::record('visit_justification_period_rejected', self::LOG_MODULE, $period, 'رفض فترة تبرير/إعفاء رقم #'.$period->id);
        $this->notifyEmployee($period, 'تم رفض فترة إعفاء زياراتك');

        return redirect()->back()->with('success', 'تم رفض فترة التبرير.');
    }

    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ], [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'client_id.required' => 'يرجى اختيار العميل',
            'date_from.required' => 'يرجى تحديد تاريخ البداية',
            'date_to.required' => 'يرجى تحديد تاريخ النهاية',
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.required' => 'سبب التبرير مطلوب',
        ]);
    }

    private function notifyEmployee(VisitJustification $period, string $title): void
    {
        try {
            notifications::create([
                'user_id' => $period->employee_id,
                'type' => 'visit',
                'title' => $title,
                'message' => $title.' — العميل: '.($period->client->trade_name ?? $period->client_id).' من '.$period->date_from->format('Y-m-d').' إلى '.$period->date_to->format('Y-m-d'),
                'read' => false,
                'data' => [
                    'visit_justification_id' => $period->id,
                    'client_name' => $period->client->trade_name ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to notify employee for visit justification period '.$period->id.': '.$e->getMessage());
        }
    }

    // ═══════════════════ تبريرات الزيارات الفردية (قديمة، بلا تغيير في الآلية) ═══════════════════

    /**
     * الموافقة على جميع تبريرات الزيارات للموظف المختار دفعة واحدة.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveByEmployee(Request $request)
    {
        try {
            $employeeId = $request->employee_id;

            if (! $employeeId) {
                return redirect()->back()->with('error', 'يرجى اختيار موظف.');
            }

            $pendingJustifications = EmployeeClientVisit::with(['employee', 'client'])
                ->where('employee_id', $employeeId)
                ->whereNotNull('justification')
                ->where('justification', '!=', '')
                ->where(function ($query) {
                    $query->whereNull('justification_approved')
                        ->orWhere('justification_approved', 0);
                })
                ->get();

            $count = $pendingJustifications->count();
            $employeeName = $pendingJustifications->first()?->employee?->name ?? 'الموظف';

            if ($count === 0) {
                return redirect()->back()->with('info', 'لا توجد تبريرات معلقة للموظف '.$employeeName.'.');
            }

            foreach ($pendingJustifications as $visit) {
                try {
                    $visit->update([
                        'justification_approved' => 1,
                        'approved_by' => Auth::id(),
                    ]);

                    try {
                        notifications::create([
                            'user_id' => $visit->employee_id,
                            'type' => 'visit',
                            'title' => 'تمت الموافقة على التبرير',
                            'message' => 'تمت الموافقة على تبريرك لزيارة العميل '.($visit->client->trade_name ?? 'غير محدد'),
                            'read' => false,
                            'data' => [
                                'visit_id' => $visit->id,
                                'client_name' => $visit->client->trade_name ?? 'غير محدد',
                            ],
                        ]);
                    } catch (\Exception $notifError) {
                        Log::warning('Failed to create notification for visit '.$visit->id.': '.$notifError->getMessage());
                    }
                } catch (\Exception $visitError) {
                    Log::error('Failed to approve visit '.$visit->id.': '.$visitError->getMessage());
                }
            }

            return redirect()->back()->with('success', 'تمت الموافقة على '.$count.' تبرير للموظف '.$employeeName.' بنجاح.');
        } catch (\Exception $e) {
            Log::error('Error approving visit justifications by employee: '.$e->getMessage());

            return redirect()->back()->with('error', 'خطأ: '.$e->getMessage());
        }
    }

    /**
     * الموافقة على جميع تبريرات الزيارات المعلقة دفعة واحدة.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveAll(Request $request)
    {
        try {
            $pendingJustifications = EmployeeClientVisit::with(['employee', 'client'])
                ->whereNotNull('justification')
                ->where('justification', '!=', '')
                ->where(function ($query) {
                    $query->whereNull('justification_approved')
                        ->orWhere('justification_approved', 0);
                })
                ->get();

            $count = $pendingJustifications->count();

            if ($count === 0) {
                return redirect()->back()->with('info', 'لا توجد تبريرات معلقة للموافقة عليها.');
            }

            foreach ($pendingJustifications as $visit) {
                try {
                    $visit->update([
                        'justification_approved' => 1,
                        'approved_by' => Auth::id(),
                    ]);

                    try {
                        notifications::create([
                            'user_id' => $visit->employee_id,
                            'type' => 'visit',
                            'title' => 'تمت الموافقة على التبرير',
                            'message' => 'تمت الموافقة على تبريرك لزيارة العميل '.($visit->client->trade_name ?? 'غير محدد'),
                            'read' => false,
                            'data' => [
                                'visit_id' => $visit->id,
                                'client_name' => $visit->client->trade_name ?? 'غير محدد',
                            ],
                        ]);
                    } catch (\Exception $notifError) {
                        Log::warning('Failed to create notification for visit '.$visit->id.': '.$notifError->getMessage());
                    }
                } catch (\Exception $visitError) {
                    Log::error('Failed to approve visit '.$visit->id.': '.$visitError->getMessage());
                }
            }

            return redirect()->back()->with('success', 'تمت الموافقة على '.$count.' تبرير بنجاح.');
        } catch (\Exception $e) {
            Log::error('Error approving all visit justifications: '.$e->getMessage());

            return redirect()->back()->with('error', 'خطأ: '.$e->getMessage());
        }
    }

    /**
     * الموافقة على تبرير زيارة محدد.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, $id)
    {
        try {
            $visit = EmployeeClientVisit::findOrFail($id);

            $updated = $visit->update([
                'justification_approved' => 1,
                'approved_by' => Auth::id(),
            ]);

            if ($updated) {
                try {
                    notifications::create([
                        'user_id' => $visit->employee_id,
                        'type' => 'visit',
                        'title' => 'تمت الموافقة على التبرير',
                        'message' => 'تمت الموافقة على تبريرك لزيارة العميل '.($visit->client->trade_name ?? 'غير محدد'),
                        'read' => false,
                        'data' => [
                            'visit_id' => $visit->id,
                            'client_name' => $visit->client->trade_name ?? 'غير محدد',
                        ],
                    ]);
                } catch (\Exception $notifError) {
                    Log::warning('Failed to create notification for visit '.$visit->id.': '.$notifError->getMessage());
                }

                return redirect()->back()->with('success', 'تمت الموافقة على التبرير بنجاح.');
            }

            return redirect()->back()->with('error', 'فشل تحديث التبرير.');
        } catch (\Exception $e) {
            Log::error('Error approving visit justification: '.$e->getMessage());

            return redirect()->back()->with('error', 'خطأ: '.$e->getMessage());
        }
    }

    /**
     * رفض تبرير زيارة محدد.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, $id)
    {
        try {
            $visit = EmployeeClientVisit::findOrFail($id);

            $updated = $visit->update([
                'justification_approved' => 2,
                'approved_by' => Auth::id(),
            ]);

            if ($updated) {
                try {
                    notifications::create([
                        'user_id' => $visit->employee_id,
                        'type' => 'visit',
                        'title' => 'تم رفض التبرير',
                        'message' => 'تم رفض تبريرك لزيارة العميل '.($visit->client->trade_name ?? 'غير محدد').'. يرجى تعديله.',
                        'read' => false,
                        'data' => [
                            'visit_id' => $visit->id,
                            'client_name' => $visit->client->trade_name ?? 'غير محدد',
                        ],
                    ]);
                } catch (\Exception $notifError) {
                    Log::warning('Failed to create notification for visit '.$visit->id.': '.$notifError->getMessage());
                }

                return redirect()->back()->with('success', 'تم رفض التبرير بنجاح.');
            }

            return redirect()->back()->with('error', 'فشل تحديث التبرير.');
        } catch (\Exception $e) {
            Log::error('Error rejecting visit justification: '.$e->getMessage());

            return redirect()->back()->with('error', 'خطأ: '.$e->getMessage());
        }
    }
}
