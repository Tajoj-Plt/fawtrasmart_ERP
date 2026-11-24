<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Services\PayrollDisbursementService;
use App\Services\PayrollRetroPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\PrintsPayrollDocuments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Requests\PayrollRunDisburseRequest;
use Modules\HR\Support\BranchTree;

/**
 * سجلات الصرف (PayrollRun) — السجل الفعلي لكل صرف شهري.
 *
 * مسؤولياته:
 *   - عرض قائمة الصرفات بمراحلها (بانتظار اعتماد مسير الرواتب / قبل يوم الصرف / مستحقة / موقوفة / مدفوعة / ملغاة)
 *   - عرض تفاصيل صرفة (بنود + سند صرف + قيود)
 *   - الصرف اليدوي — **المسار الوحيد لـ«صرف الراتب»**، ومنه يصرف زرُّ شاشة
 *     القسيمة أيضاً. انظر رأس `PayrollRun` لقرار «صرف الراتب/صرف القسيمة».
 *   - الإلغاء والإيقاف والتسوية الرجعية على المعلّقة وحدها، والعكس على المدفوعة
 *
 * **الصلاحيات** — كلُّ فعلٍ بصلاحيته، والإيقافُ مفصولٌ عن التسوية:
 *   عرض ← disbursements.index / disbursements.show
 *   صرف ← payslips.disburse               إلغاء ← disbursements.cancel
 *   عكس ← disbursements.reverse           إيقاف/رفعه ← disbursements.stop
 *   تسوية رجعية ← disbursements.settle    طباعة ← payslips.print
 *
 * **النطاق**: القائمةُ والإحصاءاتُ مقيّدةٌ بنطاق الموظفين، وكلُّ فعلٍ على سجلٍّ
 * مفرد يمرّ على `authorizeRun()` — فصاحبُ `data_scope_own` لا يبلغ إلا صرفاتِه.
 * لا صلاحيةَ «الخاصّ»: الصلاحيةُ تقول ماذا، والنطاقُ يقول على من.
 */
class PayrollRunController extends Controller
{
    use PrintsPayrollDocuments;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:disbursements.index', ['only' => ['index']]);
        $this->middleware('permission.role:disbursements.show', ['only' => ['show']]);
        // الطباعةُ بصلاحيتها — والنطاقُ يقصر صاحبَ `data_scope_own` على سجلّاته
        $this->middleware('permission.role:payslips.print', ['only' => ['print']]);
        $this->middleware('permission.role:payslips.disburse', ['only' => ['disburse']]);
        $this->middleware('permission.role:disbursements.cancel', ['only' => ['cancel']]);
        $this->middleware('permission.role:disbursements.reverse', ['only' => ['reverse']]);
        $this->middleware('permission.role:disbursements.stop', ['only' => ['hold', 'release']]);
        $this->middleware('permission.role:disbursements.settle', ['only' => ['addRetro', 'removeRetro']]);
    }

    public function index(Request $request)
    {
        $base = $this->visibleRuns();

        $query = (clone $base)
            ->with(['employee', 'slip.payroll', 'disburser', 'treasury'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = (string) $request->status;
            // `pending` و`disbursed` القديمتان تبقيان مقبولتين في الروابط المحفوظة
            $stage = match ($status) {
                PayrollRun::STATUS_PENDING => null,
                PayrollRun::STATUS_DISBURSED => PayrollRun::STAGE_PAID,
                default => $status,
            };

            if ($stage === null) {
                $query->pending()->whereNull('held_at');
            } elseif (array_key_exists($stage, PayrollRun::stages())) {
                $query->stage($stage);
            }
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }
        if ($request->filled('period_from')) {
            $query->whereDate('period_from', '>=', $request->period_from);
        }
        if ($request->filled('period_to')) {
            $query->whereDate('period_to', '<=', $request->period_to);
        }

        $runs = $query->paginate(20)->withQueryString();

        $employees = BranchTree::headerEmployees($this->scopeToEmployees(Employee::query(), 'id'))
            ->orderBy('first_name')->get(['id', 'first_name', 'middle_name']);

        // الإحصاءاتُ من النطاق نفسِه — كانت تعدّ صرفات المنشأة كلِّها لكلّ من فتح الشاشة
        $statistics = [
            'total' => (clone $base)->count(),
            'awaiting_approval' => (clone $base)->awaitingApproval()->count(),
            'scheduled' => (clone $base)->scheduledAhead()->count(),
            'ready' => (clone $base)->readyToPay()->count(),
            'held' => (clone $base)->held()->count(),
            'disbursed' => (clone $base)->disbursed()->count(),
            'cancelled' => (clone $base)->cancelled()->count(),
            'due_today' => (clone $base)->due()->count(),
            'net_total_this_month' => (float) (clone $base)->disbursed()
                ->whereMonth('disbursed_at', now()->month)
                ->whereYear('disbursed_at', now()->year)
                ->sum('net_salary'),
        ];

        $statuses = PayrollRun::stages();

        return view('hr::salaries.payroll_runs.index', compact('runs', 'employees', 'statuses', 'statistics'));
    }

    public function show($id)
    {
        $run = PayrollRun::with([
            'slip', 'employee.advanceAccount', 'employee.salaryPayableAccount',
            'contract', 'treasury', 'disburser', 'holder', 'batch',
            'expense', 'journalEntry', 'accrualJournalEntry', 'reversalJournal',
            'items',
        ])->findOrFail($id);

        $this->authorizeRun($run);

        // الخزائن التي يجوز للمستخدم السحبُ منها — الحارسُ نفسُه عند الحفظ
        $treasuries = $run->canBeDisbursed(true) && hasPermission('payslips.disburse')
            ? TreasuryBalanceGuard::withdrawableAccounts()
            : collect();

        // كلُّ قيود الصرفة (ولو بعد العكس)، والخصمياتُ المُقيَّدة التي تنتظر الاقتطاع
        $trailService = app(\App\Services\PayrollJournalTrail::class);
        $journalTrail = $trailService->forRun($run);
        $pendingDeductions = $run->isDisbursed() ? collect() : $trailService->pendingPostedDeductions($run->employee);

        // لماذا احتُسبت التأمينات أو لم تُحتسب — من بطاقة الموظف والإعدادات
        $gosiExplanation = $run->slip
            ? (app(\App\Services\SocialInsuranceService::class)->breakdown($run->slip, $run, $run->employee)['explanation'] ?? null)
            : null;

        return view('hr::salaries.payroll_runs.show', compact('run', 'treasuries', 'journalTrail', 'pendingDeductions', 'gosiExplanation'));
    }

    /**
     * «صرف الراتب» — الصرف اليدوي لصرفةٍ معلّقة بعد تاريخها واعتمادها.
     *
     * الحراسةُ على ثلاث طبقات، كلُّها قبل أيِّ قيدٍ أو سند:
     *   1. أهليةُ الصرفة (`canBeDisbursed`) — ثمّ تُعاد تحت قفل صفّها.
     *   2. الخزينةُ من القائمة المسموح للمستخدم السحبُ منها.
     *   3. كفايةُ الرصيد تحت قفل صفِّ الخزينة (`TreasuryBalanceGuard`).
     */
    public function disburse(PayrollRunDisburseRequest $request, $id, PayrollDisbursementService $service)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        // «صرفٌ مبكر» قبل يوم الصرف: لا يُقبل إلا بتأكيدٍ صريح من النموذج
        $early = $request->boolean('early');

        if (! $run->canBeDisbursed($early)) {
            return back()->with('error',
                'لا يمكن صرف هذا الراتب الآن. '.($run->disbursementBlockReason($early) ?? $run->status_label)
            );
        }
        $early = $early && ! $run->isDueByDate();

        // خزينة الصرف المختارة: تُحفظ على الـ run فيستخدمها resolveTreasury أولاً (run → slip → employee → رئيسية)
        if ($request->filled('treasury_id')) {
            $selectedTreasury = TreasuryBalanceGuard::withdrawableAccounts()
                ->firstWhere('id', (int) $request->treasury_id);

            if (! $selectedTreasury) {
                return back()->withInput()->with('error', 'الخزينة المختارة غير متاحة لك للصرف منها.');
            }

            $run->treasury_id = $selectedTreasury->id;
            $run->save();
        }

        try {
            $run = $service->disburse($run, PayrollRun::TRIGGER_MANUAL, $early);

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => sprintf(
                    'تم صرف راتب يدوياً%s للفترة %s → %s بصافي %s',
                    $early ? ' (صرف مبكر قبل يوم الصرف)' : '',
                    $run->period_from->toDateString(),
                    $run->period_to->toDateString(),
                    number_format((float) $run->net_salary, 2)
                ),
                'created_by' => Auth::id(),
            ]);

            $message = 'تم صرف الراتب: أصبح السجل «مدفوعاً» وأُنشئ القيد المحاسبي وسند الصرف.';

            // من شاشة مسير الرواتب يُعاد إليها
            return $request->boolean('back')
                ? back()->with('success', $message)
                : redirect()->route('payroll-runs.show', $run->id)->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('PayrollRun disburse failed', ['run_id' => $id, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'فشل الصرف: '.$e->getMessage());
        }
    }

    public function cancel(Request $request, $id)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        if (! $run->canBeCancelled()) {
            return back()->with('error', 'لا يُلغى إلا سجلُّ صرفٍ معلّق. الحالة: '.$run->stage_label);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);
        $reason = $validated['cancellation_reason'] ?? null;

        $run->update([
            'status' => PayrollRun::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);

        ModelsLog::create([
            'type' => 'payroll_run_log',
            'type_id' => $run->id,
            'type_log' => 'log',
            'description' => 'تم إلغاء صرف الراتب'.($reason ? '. السبب: '.$reason : ''),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'تم إلغاء عملية الصرف');
    }

    /**
     * عكس راتب مصروف: قيد عكسي بلا حذف الأصلي، ثم إعادة العملية إلى الانتظار.
     */
    public function reverse(Request $request, $id, PayrollDisbursementService $service)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:3|max:500',
        ], [
            'reversal_reason.required' => 'سبب العكس مطلوب',
        ]);

        try {
            $run = $service->reverse($run, $validated['reversal_reason'], Auth::id());

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => 'عُكس صرف الراتب. السبب: '.$validated['reversal_reason'],
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('payroll-runs.show', $run->id)
                ->with('success', 'عُكس الصرف وأُنشئ قيد عكسي. يمكن إعادة الصرف بعد التصحيح.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'فشل العكس: '.$e->getMessage());
        }
    }

    /**
     * إيقاف صرفة معلّقة بقرار إداري — لا تُصرف يدوياً ولا بالكرون حتى يُرفع الإيقاف.
     */
    public function hold(Request $request, $id)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        $validated = $request->validate([
            'hold_reason' => 'required|string|min:3|max:500',
        ], [
            'hold_reason.required' => 'سبب الإيقاف مطلوب',
        ]);

        try {
            $run = $run->hold($validated['hold_reason'], Auth::id());

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => 'أُوقف صرف الراتب. السبب: '.$validated['hold_reason'],
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('payroll-runs.show', $run->id)
                ->with('success', 'أُوقفت الصرفة. لن تُصرف حتى يُرفع الإيقاف.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'فشل الإيقاف: '.$e->getMessage());
        }
    }

    /**
     * رفع إيقاف الصرفة لإعادة الصرف.
     */
    public function release($id)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        try {
            $run = $run->release();

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => 'رُفع إيقاف صرف الراتب',
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('payroll-runs.show', $run->id)
                ->with('success', 'رُفع الإيقاف. يمكن صرف الراتب وفق الجدول.');
        } catch (\Throwable $e) {
            return back()->with('error', 'فشل رفع الإيقاف: '.$e->getMessage());
        }
    }

    /**
     * فروقات زيادة بأثر رجعي على صرفة معلّقة — تُصرف مع الصافي.
     *
     * لا على مدفوعةٍ ولا ملغاةٍ ولا موقوفة: `canReceiveRetro()` تُسأل هنا
     * للرسالة، ثمّ تُعاد تحت قفل الصف في `PayrollRetroPayService`.
     */
    public function addRetro(Request $request, $id, PayrollRetroPayService $service)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        if (! $run->canReceiveRetro()) {
            return back()->with('error', 'لا تُضاف تسويةٌ إلا على صرفةٍ معلّقة غير موقوفة. الحالة: '.$run->stage_label);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string|min:3|max:500',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date|after_or_equal:period_from',
        ], [
            'name.required' => 'اسم البند مطلوب',
            'amount.required' => 'المبلغ مطلوب',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر',
            'reason.required' => 'سبب التسوية مطلوب',
        ]);

        try {
            $item = $service->add(
                $run,
                $validated['name'],
                (float) $validated['amount'],
                $validated['reason'],
                $validated['period_from'] ?? null,
                $validated['period_to'] ?? null
            );

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => sprintf(
                    'أُضيفت تسوية رجعية «%s» بمبلغ %s',
                    $item->name,
                    number_format((float) $item->amount, 2)
                ),
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('payroll-runs.show', $run->id)
                ->with('success', 'أُضيفت التسوية الرجعية. ستُصرف مع الصافي.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'فشل إضافة التسوية: '.$e->getMessage());
        }
    }

    public function removeRetro($id, $item, PayrollRetroPayService $service)
    {
        $run = PayrollRun::findOrFail($id);
        $this->authorizeRun($run);

        $row = PayrollRunItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('item_type', PayrollRunItem::TYPE_RETRO)
            ->findOrFail($item);

        try {
            $service->remove($run, $row);

            ModelsLog::create([
                'type' => 'payroll_run_log',
                'type_id' => $run->id,
                'type_log' => 'log',
                'description' => 'حُذفت تسوية رجعية: '.$row->name,
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('payroll-runs.show', $run->id)
                ->with('success', 'حُذفت التسوية الرجعية.');
        } catch (\Throwable $e) {
            return back()->with('error', 'فشل الحذف: '.$e->getMessage());
        }
    }

    /**
     * طباعة قسيمة صرف الراتب (Payslip) — بقالب «قسيمة راتب» من «قوالب
     * المستندات» (الافتراضيّ، أو `?template=`) مثبَّتاً على هذا الصرف.
     *
     * كانت ملفَّ بلايد ثابتاً (`payroll_runs/print`) — مصدرَ قوالب ثانياً لا
     * يراه محرِّرُ القوالب، فتعديلُ القالب في الإعدادات لا يظهر هنا. الملفُّ
     * باقٍ بلا مُستدعٍ.
     */
    public function print(Request $request, $id)
    {
        $run = PayrollRun::findOrFail($id);

        $this->authorizeRun($run);

        $html = $this->renderPayrollTemplate('salary_slip', $run->salary_slip_id, $request->input('template'), null, (int) $run->id);

        if ($html === null) {
            return redirect()->route('payroll-runs.show', $run->id)
                ->with('error', 'لا يوجد قالبُ طباعةٍ فعّالٌ لقسيمة الراتب. فعّل قالباً من الإعدادات ← قوالب المستندات.');
        }

        return $this->printResponse($html, 'قسيمة صرف راتب #'.$run->id, route('payroll-runs.show', $run->id));
    }

    // ───────────────────────── النطاق ─────────────────────────

    /** الصرفاتُ المرئية لهذا المستخدم: نطاقُ الموظفين وفرعُ الشريط العلويّ. */
    private function visibleRuns()
    {
        return $this->scopeToEmployees(PayrollRun::query());
    }

    /** حراسةُ السجلّ المفرد: موظفُ الصرفة داخل نطاق المستخدم، وإلّا 403. */
    private function authorizeRun(PayrollRun $run): void
    {
        $this->authorizeEmployeeScope($run->employee_id);
    }
}
