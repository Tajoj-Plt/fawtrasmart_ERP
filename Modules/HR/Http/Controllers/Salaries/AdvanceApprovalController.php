<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use App\Support\PayrollSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Exports\HrReportTableExport;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\SalaryAdvanceService;
use Throwable;

/**
 * «إدارة السلف» — البتُّ في الطلبات وصرفُها وتحصيلُها وإلغاؤها.
 *
 * ══════════════ المراحل ══════════════
 *
 *   طلب ← **اعتماد** (قرار، بلا مال) ← **صرف** (قيد: مدين سلف الموظف /
 *   دائن الخزينة) ← **سداد** من الراتب أو **تحصيل** نقديّ ← مسدَّدة.
 *   و**الإلغاء** عمليةٌ مستقلّة: قبل الصرف بلا قيد، وبعده بقيدٍ عكسيّ،
 *   ويُمنع متى بدأ السداد.
 *
 * كان الاعتمادُ صرفاً، فبوبُ الإشعار («موافقة» بضغطة) كان يُخرج المالَ من
 * خزينةٍ افتراضيّة لم يرها أحد. والاعتمادُ منه الآن قرارٌ وحده؛ والصرفُ
 * من شاشة الطلب بخزينةٍ مختارة ورصيدٍ مفحوص.
 *
 * الأثرُ النقديّ كلُّه في `SalaryAdvanceService`؛ وهذا المتحكّم يتحقّق
 * ويفتح المعاملة ويردّ. وقوائمُ الموظفين كلُّها من `scopeToEmployees()` —
 * نطاقُ الصلاحية وفرعُ الشريط العلويّ معاً.
 */
class AdvanceApprovalController extends Controller
{
    use ScopesToAccessibleEmployees;

    /*
        مجموعة `loans.*` — كلُّ فعلٍ بصلاحيته:
          القائمة والتصدير `loans.index`، العرض `loans.show`، الاعتماد والرفض
          `loans.approve`، الصرف `loans.disburse`، التحصيل/التسوية
          `loans.settle`، الإلغاء `loans.cancel`.
        والإصدارُ المباشر (`create`/`store`) إنشاءٌ واعتمادٌ وصرفٌ في خطوةٍ
        واحدة — فيلزمه الثلاث معاً.
    */
    public function __construct(private SalaryAdvanceService $advances)
    {
        $this->middleware('permission.role:loans.index', ['only' => ['index', 'export']]);
        $this->middleware('permission.role:loans.show', ['only' => ['show']]);
        $this->middleware('permission.role:loans.approve', ['only' => ['approve', 'reject', 'create', 'store']]);
        // `disbursementOptions` تُفشي أرصدةَ الخزائن، فحارسُها حارسُ الصرف نفسُه
        $this->middleware('permission.role:loans.disburse', ['only' => ['disburse', 'create', 'store', 'disbursementOptions']]);
        $this->middleware('permission.role:loans.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:loans.settle', ['only' => ['settle']]);
        $this->middleware('permission.role:loans.cancel', ['only' => ['cancel']]);
    }

    /* ══════════════════ الردود ══════════════════ */

    private function wantsJsonResponse(Request $request): bool
    {
        return $request->wantsJson() || $request->ajax() || $request->expectsJson();
    }

    private function responseSuccess(Request $request, string $message, array $extra = [], ?int $showId = null)
    {
        if ($this->wantsJsonResponse($request)) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $extra));
        }

        return $showId
            ? redirect()->route('hr.advance-approvals.show', $showId)->with('success', $message)
            : redirect()->route('hr.advance-approvals.index')->with('success', $message);
    }

    private function responseError(Request $request, string $message, int $status = 422, bool $withInput = false)
    {
        if ($this->wantsJsonResponse($request)) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        $redirect = back()->with('error', $message);

        return $withInput ? $redirect->withInput() : $redirect;
    }

    /** رسالةُ الخطأ للمستخدم — `ValidationException` (فترةٌ مقفلة) تحمل نصَّها في أخطائها. */
    private function errorText(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return (string) collect($e->errors())->flatten()->first();
        }

        return $e->getMessage();
    }

    private function markAdvanceRequestNotificationsAsHandled(int $advanceRequestId): void
    {
        $updateData = ['read' => 1];
        if (Schema::hasColumn('notifications', 'popup_shown')) {
            $updateData['popup_shown'] = 1;
        }

        // «#12» في آخر الوصف — `%#1%` كان يطابق #10 و#11 أيضاً
        notifications::where('type', 'advance_request')
            ->where('description', 'like', '%#'.$advanceRequestId)
            ->where('read', 0)
            ->update($updateData);
    }

    private function notifyRequester(SalaryAdvanceRequest $advance, string $title, string $description, string $type): void
    {
        $receiverId = User::where('employee_id', $advance->employee_id)->value('id');

        if (! $receiverId || (int) $receiverId === (int) Auth::id()) {
            return;
        }

        notifications::create([
            'user_id' => Auth::id(),
            'receiver_id' => $receiverId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'read' => 0,
            'is_scheduled' => 0,
            'is_sent' => 1,
        ]);
    }

    private function log(int $id, string $description): void
    {
        ModelsLog::create([
            'type' => 'salary_advance_request_log',
            'type_id' => $id,
            'type_log' => 'log',
            'description' => $description,
            'created_by' => Auth::id(),
        ]);
    }

    /** السلفةُ في نطاق المستخدم — وإلّا 403، فرقمٌ في العنوان لا يفتح سلفةَ غيره. */
    private function findScoped($id): SalaryAdvanceRequest
    {
        $advance = SalaryAdvanceRequest::with(['employee.advanceAccount', 'treasury'])->findOrFail($id);
        $this->authorizeEmployeeScope($advance->employee_id);

        return $advance;
    }

    /* ══════════════════ القائمة ══════════════════ */

    /**
     * صفوفُ القائمة الموحَّدة (الطلبات + السلف القديمة) بفلاتر الطلب ونطاق
     * المستخدم — مصدرٌ واحد للشاشة (`index`) ولملفّ Excel (`export`).
     */
    private function unifiedRows(Request $request): Collection
    {
        $stage = (string) $request->input('stage', '');
        $source = (string) $request->input('source', '');

        $queryB = SalaryAdvanceRequest::with(['employee.advanceAccount', 'treasury']);
        $this->scopeToEmployees($queryB);

        if ($stage !== '') {
            AdvanceRequestController::applyStage($queryB, $stage);
        }
        if ($request->filled('employee_id')) {
            $queryB->where('employee_id', $request->employee_id);
        }
        if ($request->filled('date_from')) {
            $queryB->whereDate('requested_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $queryB->whereDate('requested_date', '<=', $request->date_to);
        }

        $legacy = collect();
        // السلفُ القديمة لا تعرف مراحلَ الطلب — تُعرض مع مراحلها هي فقط
        if ($source !== 'request' && in_array($stage, ['', 'disbursed', 'repaying', 'settled', 'cancelled'], true)) {
            $queryA = SalaryAdvance::with(['employee.advanceAccount', 'payments']);
            $this->scopeToEmployees($queryA);

            if ($request->filled('employee_id')) {
                $queryA->where('employee_id', $request->employee_id);
            }
            if ($request->filled('date_from')) {
                $queryA->whereDate('submission_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $queryA->whereDate('submission_date', '<=', $request->date_to);
            }

            $legacy = $queryA->get()->map(fn (SalaryAdvance $a) => $this->legacyRow($a))
                ->filter(fn ($row) => $stage === '' || $row->stage === $stage);
        }

        $requestsB = $source === 'legacy' ? collect() : $queryB->get()->map(fn (SalaryAdvanceRequest $r) => (object) [
            '_source' => 'request',
            'id' => $r->id,
            'employee' => $r->employee,
            'amount' => (float) $r->amount,
            'remaining' => $r->remainingAmount(),
            'advance_type' => $r->advance_type ?? 'normal',
            'reason' => $r->reason,
            'stage' => $r->stage(),
            'stage_label' => $r->stageLabel(),
            'stage_class' => $r->stageClass(),
            'installments_count' => (int) $r->installments_count,
            'paid_installments' => (int) $r->paid_installments,
            'start_month' => $r->start_month,
            'requested_date' => $r->requested_date,
            'date_sort' => $r->requested_date ?? $r->created_at,
            'original' => $r,
        ]);

        return $requestsB->concat($legacy)
            ->sortByDesc(fn ($r) => $r->date_sort ? Carbon::parse($r->date_sort)->timestamp : 0)
            ->values();
    }

    /**
     * قائمةٌ موحَّدة للنظامين: الطلبات (`salary_advance_requests`) والسلف
     * القديمة (`salary_advances`)، في نطاق المستخدم.
     *
     * كانت الفلاترُ في الشاشة تُرسل `employee_id`/`from_date`/`to_date`
     * ويقرأ المتحكّمُ `employee_search`/`date_from`/`date_to` — فلا يصفّي
     * شيء. ولم يكن للقائمة نطاقٌ أصلاً.
     */
    public function index(Request $request)
    {
        $unified = $this->unifiedRows($request);

        $perPage = in_array((int) $request->per_page, [10, 20, 50, 100], true) ? (int) $request->per_page : 20;
        $currentPage = max(1, (int) $request->input('page', 1));

        $requests = new LengthAwarePaginator(
            $unified->slice(($currentPage - 1) * $perPage, $perPage)->values(),
            $unified->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name', 'nickname'), 'id')
            ->orderBy('first_name')->get();

        // الإحصاءاتُ في النطاق نفسه — كانت تعدّ سلفَ المنشأة كلِّها
        $scopedB = fn () => $this->scopeToEmployees(SalaryAdvanceRequest::query());
        $open = $scopedB()->where('status', 'approved')->whereNotNull('disbursed_at')->where('is_settled', false)->get();

        $statistics = [
            'pending' => $scopedB()->where('status', 'pending')->count(),
            'awaiting_disbursement' => $scopedB()->where('status', 'approved')->whereNull('disbursed_at')->count(),
            'open_count' => $open->count(),
            'open_amount' => $open->sum(fn ($r) => $r->remainingAmount()),
        ];

        return view('hr::salaries.advance_approvals.index', compact('requests', 'statistics', 'employees'));
    }

    /**
     * «إدارة السلف» إلى Excel — الصفوفُ نفسُها التي تعرضها القائمة
     * (`unifiedRows()`: الفلاتر ونطاق المستخدم)، كلُّها لا صفحةٌ واحدة.
     */
    public function export(Request $request)
    {
        $rows = $this->unifiedRows($request)->map(function ($r) {
            $employee = $r->employee;

            return [
                ($r->_source === 'legacy' ? 'L-' : '').$r->id,
                $r->_source === 'legacy' ? 'سلفة قديمة' : 'طلب سلفة',
                $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
                $r->requested_date ? Carbon::parse($r->requested_date)->format('Y-m-d') : '',
                $r->advance_type === 'scheduled' ? 'مجدولة' : 'عادية',
                round((float) $r->amount, 2),
                round((float) $r->remaining, 2),
                $r->installments_count > 0 ? $r->paid_installments.' / '.$r->installments_count : '',
                (string) ($r->start_month ?? ''),
                $r->stage_label,
                (string) ($r->reason ?? ''),
            ];
        })->all();

        return Excel::download(
            new HrReportTableExport(
                'إدارة السلف',
                ['#', 'المصدر', 'الموظف', 'تاريخ الطلب', 'النوع', 'المبلغ', 'المتبقّي', 'الأقساط المسدَّدة', 'شهر البداية', 'المرحلة', 'السبب'],
                $rows
            ),
            'advances_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    private function legacyRow(SalaryAdvance $a): object
    {
        $paidCount = $a->payments->where('status', 'paid')->count();
        $paidAmount = (float) $a->payments->where('status', 'paid')->sum('amount');
        $total = (int) ($a->total_installments ?? 0);

        $stage = match (true) {
            ($a->status ?? 'active') === 'cancelled' => 'cancelled',
            $total > 0 && $paidCount >= $total => 'settled',
            $paidCount > 0 => 'repaying',
            default => 'disbursed',
        };

        return (object) [
            '_source' => 'legacy',
            'id' => $a->id,
            'employee' => $a->employee,
            'amount' => (float) $a->amount,
            'remaining' => $stage === 'cancelled' ? 0.0 : max(0, round((float) $a->amount - $paidAmount, 2)),
            'advance_type' => $total > 1 ? 'scheduled' : 'normal',
            'reason' => $a->note,
            'stage' => $stage,
            'stage_label' => SalaryAdvanceRequest::STAGES[$stage][0],
            'stage_class' => SalaryAdvanceRequest::STAGES[$stage][1],
            'installments_count' => $total,
            'paid_installments' => $paidCount,
            'start_month' => $a->installment_start_date ? Carbon::parse($a->installment_start_date)->format('Y-m') : null,
            'requested_date' => $a->submission_date,
            'date_sort' => $a->submission_date ?? $a->created_at,
            'original' => $a,
        ];
    }

    /* ══════════════════ العرض ══════════════════ */

    public function show($id)
    {
        $request = SalaryAdvanceRequest::with([
            'employee.treasuryEmployee.treasury', 'employee.advanceAccount', 'treasury',
            'approvedBy', 'approver', 'disbursedBy', 'cancelledBy',
        ])->findOrFail($id);

        $this->authorizeEmployeeScope($request->employee_id);

        $treasuries = $this->advances->treasuryOptions('withdraw');
        $depositTreasuries = $this->advances->treasuryOptions('deposit');
        $defaultTreasuryId = $request->treasury_id ?: $this->advances->defaultTreasuryId($request->employee_id);
        $advanceSummary = $request->employee ? $request->employee->getAdvanceSummary() : null;
        $cancelBlock = $this->advances->cancelBlockReason($request);

        $logs = ModelsLog::where('type', 'salary_advance_request_log')
            ->where('type_id', $request->id)
            ->with('user')
            ->orderByDesc('id')
            ->get();

        // قيودُ السلفة: الصرف، والتحصيلات، والعكس عند الإلغاء
        $journals = JournalEntry::query()
            ->where(function ($q) use ($request) {
                $q->whereIn('id', array_filter([$request->disbursement_journal_id, $request->cancellation_journal_id]) ?: [0])
                    ->orWhere('reference_number', 'like', 'ADV-SETTLE-'.$request->id.'-%');
            })
            ->orderBy('id')
            ->get();

        return view('hr::salaries.advance_approvals.show', compact(
            'request', 'defaultTreasuryId', 'treasuries', 'depositTreasuries', 'advanceSummary', 'cancelBlock', 'logs', 'journals'
        ));
    }

    /**
     * خياراتُ الصرف لبوب الإشعار — خزائنُ يُسمح للمعتمِد بالسحب منها،
     * وأرصدتُها، والافتراضيّةُ منها.
     *
     * **لماذا نداءٌ منفصل؟** كان بوبُ الإشعار يعتمد وحدَه ثمّ يُطلب من
     * المعتمِد أن يفتح شاشةَ الطلب ليصرف — خطوتان ونقلةُ صفحةٍ لقرارٍ واحد.
     * والصرفُ لا يُسنَد إلى خزينةٍ مفترَضة: ذاك ما كان قبل إعادة بناء
     * السلف، فكان المالُ يخرج من خزينةٍ لم يرَها أحد. فالبوبُ يسأل هذه
     * الدالّةَ، ويعرض الخزائنَ بأرصدتها، ويصرف من واحدةٍ **مختارة**.
     *
     * والرصيدُ المعروض للتنبيه وحده: قرارُ الصرف يُفحص من الدفتر تحت قفلٍ
     * في `TreasuryBalanceGuard::assertWithdrawable()` عند الحفظ.
     */
    public function disbursementOptions(Request $request, $id)
    {
        $advance = $this->findScoped($id);

        $allowed = PayrollSettings::value('advance_popup_disburse', 1);

        $treasuries = $this->advances->treasuryOptions('withdraw')->map(fn ($t) => [
            'id' => (int) $t->id,
            'name' => (string) $t->name,
            'balance' => round((float) $t->balance, 2),
            'balance_label' => CurrencySettings::format($t->balance),
        ])->values();

        $default = $this->advances->defaultTreasuryId($advance->employee_id);

        return response()->json([
            'success' => true,
            // الصرفُ متاحٌ من البوب متى: أُجيز في الإعدادات، ويملك المستخدمُ
            // صلاحيتَه، وثَمّ خزينةٌ يُسمح له بالسحب منها، والطلبُ قابلٌ للاعتماد
            'can_disburse' => (bool) $allowed
                && hasPermission('loans.disburse')
                && $treasuries->isNotEmpty()
                && $advance->canBeApproved(),
            'allowed_by_settings' => (bool) $allowed,
            'amount' => round((float) $advance->amount, 2),
            'amount_label' => CurrencySettings::format($advance->amount),
            'employee' => $advance->employee ? trim(($advance->employee->first_name ?? '').' '.($advance->employee->middle_name ?? '')) : '',
            'stage_label' => $advance->stageLabel(),
            'treasuries' => $treasuries,
            // الخزينةُ المربوطةُ بالموظف، وإلّا «الرئيسية» — وتبقى `null` إن لم
            // تكن ضمن ما يُسمح له بالسحب منه، فلا تُعرَض خزينةٌ تُرفض عند الحفظ
            'default_treasury_id' => $treasuries->firstWhere('id', (int) $default) ? (int) $default : null,
        ]);
    }

    /* ══════════════════ البتّ ══════════════════ */

    /**
     * اعتمادُ الطلب — ومعه الصرفُ إن طُلب صراحةً (`disburse_now`) من شاشة
     * الطلب بخزينةٍ مختارة. والاعتمادُ من بوب الإشعار قرارٌ وحده.
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'disburse_now' => 'nullable|boolean',
            'treasury_id' => 'nullable|required_if:disburse_now,1|exists:accounts,id',
            'admin_notes' => 'nullable|string|max:500',
        ], [
            'treasury_id.required_if' => 'اختر الخزينة التي تُصرف منها السلفة',
            'treasury_id.exists' => 'الخزينة المحددة غير موجودة',
        ]);

        $disburseNow = $request->boolean('disburse_now');

        if ($disburseNow && ! hasPermission('loans.disburse')) {
            return $this->responseError($request, 'لا تملك صلاحية صرف السلف — اعتمد الطلب دون صرف.', 403, true);
        }

        $advance = SalaryAdvanceRequest::with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($advance->employee_id);

        try {
            DB::transaction(function () use ($advance, $validated, $disburseNow) {
                $locked = SalaryAdvanceRequest::with('employee')->lockForUpdate()->findOrFail($advance->id);

                if (! $locked->canBeApproved()) {
                    throw new \RuntimeException('لا يمكن اعتماد هذا الطلب — حالته: '.$locked->stageLabel().'.');
                }
                if (! $locked->employee) {
                    throw new \RuntimeException('سجل الموظف غير موجود لهذا الطلب.');
                }

                // ⛔ لا اعتمادَ لسلفةٍ لا يُعرف على أيّ حسابٍ تُقيَّد
                $locked->employee->getAdvanceAccountOrFail();

                $locked->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_date' => now(),
                    'admin_notes' => $validated['admin_notes'] ?? null,
                ]);

                $this->log($locked->id, 'اعتُمد طلب السلفة بمبلغ '.CurrencySettings::format($locked->amount));

                if ($disburseNow) {
                    $this->advances->disburse($locked, (int) $validated['treasury_id']);
                }

                $this->markAdvanceRequestNotificationsAsHandled((int) $locked->id);

                $this->notifyRequester(
                    $locked,
                    'تمت الموافقة على طلب السلفة',
                    'تمت الموافقة على طلب السلفة رقم #'.$locked->id.' بمبلغ '.CurrencySettings::format($locked->amount).
                        ($disburseNow ? ' وصُرفت.' : ' — بانتظار الصرف من الخزينة.'),
                    'advance_approved'
                );
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'approve');

            return $this->responseError($request, 'تعذّر الاعتماد: '.$this->errorText($e), 422, true);
        }

        return $this->responseSuccess(
            $request,
            $disburseNow ? 'اعتُمدت السلفة وصُرفت، وكُتب قيد الصرف.' : 'اعتُمد الطلب — وهو الآن بانتظار الصرف من الخزينة.',
            ['status' => 'approved'],
            (int) $id
        );
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'يرجى إدخال سبب الرفض',
            'rejection_reason.max' => 'سبب الرفض يجب ألا يتجاوز 500 حرف',
        ]);

        $advance = SalaryAdvanceRequest::findOrFail($id);
        $this->authorizeEmployeeScope($advance->employee_id);

        try {
            DB::transaction(function () use ($advance, $validated) {
                $locked = SalaryAdvanceRequest::lockForUpdate()->findOrFail($advance->id);

                if (! $locked->canBeApproved()) {
                    throw new \RuntimeException('لا يمكن رفض هذا الطلب — حالته: '.$locked->stageLabel().'.');
                }

                $locked->update([
                    'status' => 'rejected',
                    'approved_by' => Auth::id(),
                    'approved_date' => now(),
                    'rejection_reason' => $validated['rejection_reason'],
                ]);

                $this->log($locked->id, 'رُفض طلب السلفة: '.$validated['rejection_reason']);
                $this->markAdvanceRequestNotificationsAsHandled((int) $locked->id);

                $this->notifyRequester(
                    $locked,
                    'تم رفض طلب السلفة',
                    'تم رفض طلب السلفة رقم #'.$locked->id.'. السبب: '.$validated['rejection_reason'],
                    'advance_rejected'
                );
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'reject');

            return $this->responseError($request, 'تعذّر الرفض: '.$this->errorText($e), 422, true);
        }

        return $this->responseSuccess($request, 'رُفض الطلب وأُخطر صاحبه.', ['status' => 'rejected'], (int) $id);
    }

    /* ══════════════════ الصرف والتحصيل والإلغاء ══════════════════ */

    public function disburse(Request $request, $id)
    {
        $validated = $request->validate([
            'treasury_id' => 'required|exists:accounts,id',
        ], [
            'treasury_id.required' => 'اختر الخزينة التي تُصرف منها السلفة',
            'treasury_id.exists' => 'الخزينة المحددة غير موجودة',
        ]);

        $advance = $this->findScoped($id);

        try {
            $journal = DB::transaction(fn () => $this->advances->disburse($advance, (int) $validated['treasury_id']));
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'disburse');

            return back()->withInput()->with('error', 'تعذّر الصرف: '.$this->errorText($e));
        }

        return redirect()->route('hr.advance-approvals.show', $advance->id)
            ->with('success', 'صُرفت السلفة وكُتب قيد الصرف #'.$journal->id.' — وهي الآن في دورة السداد.');
    }

    /**
     * التسوية: تحصيلُ سدادٍ نقديّ. لا تُلغي السلفة — تنقلها في دورة السداد.
     */
    public function settle(Request $request, $id)
    {
        $advance = $this->findScoped($id);

        $validated = $request->validate([
            'treasury_id' => 'required|exists:accounts,id',
            'settlement_month' => ($advance->isScheduled() ? 'required' : 'nullable').'|date_format:Y-m',
            'settlement_amount' => 'nullable|numeric|min:0.01',
            'settlement_notes' => 'nullable|string|max:500',
        ], [
            'settlement_month.required' => 'يرجى تحديد شهر التسوية',
            'treasury_id.required' => 'يرجى اختيار الخزينة',
            'treasury_id.exists' => 'الخزينة المحددة غير موجودة',
            'settlement_amount.min' => 'مبلغ التحصيل يجب أن يكون أكبر من صفر',
        ]);

        try {
            $journal = DB::transaction(fn () => $this->advances->repayCash(
                $advance,
                (int) $validated['treasury_id'],
                $validated['settlement_month'] ?? null,
                isset($validated['settlement_amount']) ? (float) $validated['settlement_amount'] : null,
                $validated['settlement_notes'] ?? null
            ));
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'settle');

            return back()->withInput()->with('error', 'تعذّرت التسوية: '.$this->errorText($e));
        }

        $advance->refresh();

        return redirect()->route('hr.advance-approvals.show', $advance->id)
            ->with('success', 'حُصِّل السداد وكُتب القيد #'.$journal->id.' — حالة السلفة: '.$advance->stageLabel().
                ' (المتبقّي '.CurrencySettings::format($advance->remainingAmount()).').');
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ], [
            'cancellation_reason.required' => 'اذكر سبب الإلغاء',
        ]);

        $advance = $this->findScoped($id);

        try {
            $journal = DB::transaction(function () use ($advance, $validated) {
                $journal = $this->advances->cancel($advance, $validated['cancellation_reason']);
                $this->markAdvanceRequestNotificationsAsHandled((int) $advance->id);

                $this->notifyRequester(
                    $advance,
                    'أُلغيت السلفة',
                    'أُلغيت السلفة رقم #'.$advance->id.'. السبب: '.$validated['cancellation_reason'],
                    'advance_rejected'
                );

                return $journal;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'cancel');

            return back()->withInput()->with('error', 'تعذّر الإلغاء: '.$this->errorText($e));
        }

        return redirect()->route('hr.advance-approvals.show', $advance->id)
            ->with('success', $journal
                ? 'أُلغيت السلفة ورُدّ مبلغها إلى الخزينة بالقيد العكسي #'.$journal->id.'.'
                : 'أُلغيت السلفة — لم تكن قد صُرفت، فلا قيد.');
    }

    /* ══════════════════ الإصدار المباشر ══════════════════ */

    public function create()
    {
        $employees = $this->scopeToEmployees(Employee::active(), 'id')
            ->whereNotNull('employee_advance_account_id')
            ->with('advanceAccount')
            ->orderBy('first_name')
            ->get();

        $employeesWithoutAccount = $this->scopeToEmployees(Employee::active(), 'id')
            ->whereNull('employee_advance_account_id')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname']);

        $treasuries = $this->advances->treasuryOptions('withdraw');
        $defaultTreasuryId = $this->advances->defaultTreasuryId(null);

        return view('hr::salaries.advance_approvals.create', compact('employees', 'employeesWithoutAccount', 'treasuries', 'defaultTreasuryId'));
    }

    /**
     * إصدارُ سلفةٍ للموظف مباشرةً: اعتمادٌ وصرفٌ في معاملةٍ واحدة. الرصيدُ
     * يُفحص تحت قفلٍ قبل القيد، ولا يبقى من الرفض أثر.
     */
    public function store(Request $request)
    {
        $rules = [
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:1',
            'treasury_id' => 'required|exists:accounts,id',
            'requested_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'admin_notes' => 'nullable|string|max:500',
            'advance_type' => 'required|in:normal,scheduled',
        ];

        if ($request->advance_type === 'scheduled') {
            $rules['installments_count'] = 'required|integer|min:2|max:24';
            $rules['start_month'] = 'required|date_format:Y-m';
        }

        $validated = $request->validate($rules, [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'amount.required' => 'يرجى إدخال المبلغ',
            'amount.min' => 'المبلغ يجب أن يكون 1 على الأقل',
            'treasury_id.required' => 'يرجى اختيار الخزينة',
            'treasury_id.exists' => 'الخزينة المحددة غير موجودة',
            'requested_date.required' => 'يرجى إدخال التاريخ',
            'advance_type.required' => 'يرجى تحديد نوع السلفة',
            'installments_count.required' => 'يرجى إدخال عدد الأقساط',
            'installments_count.min' => 'عدد الأقساط يجب أن يكون 2 على الأقل',
            'installments_count.max' => 'عدد الأقساط يجب ألا يتجاوز 24 شهر',
            'start_month.required' => 'يرجى تحديد شهر بداية الخصم',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (! $this->canAccessEmployee($employee->id)) {
            return back()->withInput()->withErrors(['employee_id' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بهم.']);
        }
        if (! $employee->hasAdvanceAccount()) {
            return back()->withInput()->withErrors(['employee_id' => 'لا يوجد حساب سلف مرتبط بهذا الموظف في شجرة الحسابات.']);
        }

        $isScheduled = $validated['advance_type'] === 'scheduled';
        $installmentsCount = $isScheduled ? (int) $validated['installments_count'] : null;
        $installmentAmount = $isScheduled ? round($validated['amount'] / $installmentsCount, 2) : null;

        try {
            $advance = DB::transaction(function () use ($validated, $isScheduled, $installmentsCount, $installmentAmount) {
                $advance = SalaryAdvanceRequest::create([
                    'employee_id' => $validated['employee_id'],
                    'amount' => $validated['amount'],
                    'advance_type' => $validated['advance_type'],
                    'installments_count' => $installmentsCount,
                    'installment_amount' => $installmentAmount,
                    'start_month' => $isScheduled ? $validated['start_month'] : null,
                    'paid_installments' => 0,
                    'remaining_installments' => $installmentsCount,
                    'requested_date' => $validated['requested_date'],
                    'reason' => $validated['reason'] ?? 'سلفة صادرة من الإدارة',
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_date' => now(),
                    'admin_notes' => $validated['admin_notes'] ?? null,
                ]);

                $this->log($advance->id, 'أصدرت الإدارة السلفة مباشرةً بمبلغ '.CurrencySettings::format($advance->amount).
                    ($isScheduled ? ' (مجدولة على '.$installmentsCount.' قسط)' : ''));

                // الرصيدُ يُفحص تحت قفلٍ داخل `disburse()` قبل القيد — والرفضُ يُرجع السجلَّ كلَّه
                $this->advances->disburse($advance, (int) $validated['treasury_id'], $validated['requested_date'], 'ADV-ISSUE-');

                return $advance;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, 'HR.AdvanceApproval', 'store');

            return back()->withInput()->with('error', 'تعذّر إصدار السلفة: '.$this->errorText($e));
        }

        $message = 'صدرت السلفة وصُرفت من الخزينة';
        if ($isScheduled) {
            $message .= ' — يُقتطع '.CurrencySettings::format($installmentAmount).' شهرياً لمدة '.$installmentsCount.' شهر';
        }

        return redirect()->route('hr.advance-approvals.show', $advance->id)->with('success', $message);
    }
}
