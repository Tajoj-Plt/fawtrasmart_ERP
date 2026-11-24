<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Employee;
use App\Models\notifications;
use App\Models\User;
use App\Services\DeductionAccountingService;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\GeneratesHrDocumentNumbers;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Requests\DeductionRequest;
use Modules\HR\Support\BranchTree;

/**
 * إدارة خصميات الموظفين (الحسومات / الجزاءات).
 *
 * ══════════════════ الدورة ══════════════════
 *
 *   إنشاء (قيد الانتظار) ← اعتماد (يُقيَّد) ← اقتطاعٌ من الراتب عند صرفه
 *                                          ← أو تسويةٌ يدوية (تحصيلٌ نقدي / إعفاء)
 *                        ← رفض | إلغاء (يُعكس ما قُيِّد ولم يُقتطع)
 *
 * ══════════════════ المحاسبة ══════════════════
 *
 * كانت الخصميةُ بلا قيدٍ حتى يُصرف الراتب. صارت تُقيَّد **عند اعتمادها**
 * على حساب نوعها: مدين ذمّة الموظف / دائن حساب النوع (غياب، تأخير، جزاء…
 * من «إعدادات الحسابات المحاسبية»، مجموعة «الرواتب والموظفين»). وقيدُ الراتب لا يُقيّدها ثانيةً
 * — انظر `DeductionAccountingService` و`PayrollAccountant`.
 *
 * ══════════════════ الصلاحيات والنطاق ══════════════════
 *
 * كلُّ فعلٍ بصلاحيته (`permission.role:` على المسار وهنا)، وكلُّ سجلٍّ مفردٍ
 * يمرّ على `authorizeEmployeeScope()` — فلا يبلغ أحدٌ خصميةَ موظفٍ خارج
 * نطاقه بتغيير الرقم في العنوان.
 */
class DeductionController extends Controller
{
    use GeneratesHrDocumentNumbers;
    use ScopesToAccessibleEmployees;

    public function __construct(private DeductionAccountingService $accounting)
    {
        // مجموعة `deductions.*` — `approve` تشمل الرفض، و`settle` التسويةَ اليدوية
        // (تحصيلٌ نقديّ أو إعفاء)، والإلغاءُ من التعديل كما كان.
        $this->middleware('permission.role:deductions.index', ['only' => ['index']]);
        $this->middleware('permission.role:deductions.show', ['only' => ['show', 'history']]);
        $this->middleware('permission.role:deductions.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:deductions.edit', ['only' => ['edit', 'update', 'cancel']]);
        $this->middleware('permission.role:deductions.destroy', ['only' => ['destroy']]);
        $this->middleware('permission.role:deductions.approve', ['only' => ['approve', 'reject']]);
        $this->middleware('permission.role:deductions.settle', ['only' => ['settle']]);
    }

    /**
     * قائمة الخصميات — ضمن نطاق المستخدم، والإحصاءاتُ من النطاق نفسِه.
     */
    public function index(Request $request)
    {
        $base = Deduction::query();
        $this->scopeToEmployees($base);

        $query = (clone $base)
            ->with(['employee:id,first_name,middle_name,nickname', 'creator:id,name', 'approver:id,name']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('employee', function ($qe) use ($term) {
                    $qe->where('first_name', 'like', "%$term%")
                        ->orWhere('middle_name', 'like', "%$term%")
                        ->orWhere('nickname', 'like', "%$term%");
                })->orWhere('code', 'like', "%$term%")
                    ->orWhere('reason', 'like', "%$term%");
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('status') && array_key_exists($request->status, Deduction::statuses())) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type') && array_key_exists($request->type, Deduction::types())) {
            $query->where('deduction_type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->filled('settled_filter')) {
            if ($request->settled_filter === 'settled') {
                $query->where('status', Deduction::STATUS_SETTLED);
            } elseif ($request->settled_filter === 'unsettled') {
                $query->unsettled();
            }
        }

        $perPage = in_array((int) $request->per_page, [10, 20, 50, 100], true) ? (int) $request->per_page : 20;
        $deductions = $query->latest('id')->paginate($perPage)->withQueryString();

        // كانت الإحصاءاتُ تُعدّ على الجدول كلِّه لكلّ من فتح الشاشة
        $statistics = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->unsettled()->count(),
            'unsettled_amount' => (float) (clone $base)->unsettled()->selectRaw('SUM(amount - settled_amount) as t')->value('t'),
            'settled_amount' => (float) (clone $base)->whereNotIn('status', [Deduction::STATUS_CANCELLED, Deduction::STATUS_REJECTED])->sum('settled_amount'),
        ];

        $employees = BranchTree::headerEmployees($this->scopeToEmployees(Employee::query(), 'id'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname']);
        $types = Deduction::types();
        $statuses = Deduction::statuses();
        $canManage = true;

        return view('hr::salaries.deductions.index', compact(
            'deductions', 'statistics', 'employees', 'types', 'statuses', 'canManage'
        ));
    }

    public function create()
    {
        $employees = $this->employeeOptions();
        $types = Deduction::types();
        $nextDeductionCode = $this->previewDocumentNumber('deduction');
        $typeAccounts = $this->typeAccounts();

        return view('hr::salaries.deductions.create', compact('employees', 'types', 'nextDeductionCode', 'typeAccounts'));
    }

    public function store(DeductionRequest $request)
    {
        $validated = $request->validated();

        if (! $this->canAccessEmployee($validated['employee_id'])) {
            return back()->withInput()->withErrors(['employee_id' => 'الموظف المختار خارج نطاق الموظفين المصرّح لك بهم.']);
        }

        try {
            DB::beginTransaction();

            $deductionCode = null;
            try {
                $deductionCode = $this->issueDocumentNumber('deduction');
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'HR.Deduction', 'code');
            }

            $deduction = Deduction::create(array_merge($validated, [
                'code' => $deductionCode,
                'status' => Deduction::STATUS_PENDING,
                'settled_amount' => 0,
                'created_by_user' => Auth::id(),
            ]));

            $this->logHistory($deduction, DeductionHistory::ACTION_CREATED, 'تم إنشاء خصمية بمبلغ '.CurrencySettings::format($deduction->amount));

            DB::commit();

            return redirect()->route('hr.deductions.show', $deduction->id)
                ->with('success', 'تم إنشاء الخصمية بنجاح. بانتظار الاعتماد — ولا قيدَ لها حتى تُعتمد.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'store');
            DB::rollBack();

            return back()->withInput()->with('error', 'فشل إنشاء الخصمية: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $deduction = Deduction::with([
            'employee', 'creator', 'approver', 'rejecter', 'canceller',
            'histories.user', 'journalEntry',
        ])->findOrFail($id);

        $this->authorizeEmployeeScope($deduction->employee_id);

        $canManage = true;
        $typeAccountId = $this->accounting->accountIdFor($deduction->deduction_type);
        $typeAccount = $typeAccountId ? Account::find($typeAccountId, ['id', 'name', 'code']) : null;
        $postingBlock = $deduction->canBeApproved() ? $this->accounting->blockReason($deduction) : null;

        // قيودُ الخصمية كلُّها: الاعتماد وما تلاه (عكس/تحصيل/إعفاء)
        $journals = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('reference_id', $deduction->id)
            ->where(function ($q) use ($deduction) {
                foreach (['DED-', 'DED-CAN-', 'DED-COL-', 'DED-WVR-'] as $prefix) {
                    $q->orWhere('reference_number', $prefix.$deduction->id)
                        ->orWhere('reference_number', 'like', $prefix.$deduction->id.'-%');
                }
            })
            ->orderBy('id')
            ->get(['id', 'reference_number', 'date', 'description']);

        // خزائنُ الإيداع للتحصيل النقدي — شرطُ الإيداع نفسُه عند الحفظ
        $treasuries = $deduction->canBeSettled() && hasPermission('deductions.settle')
            ? Account::whereIn('id', TreasuryAccessGuard::ownDepositable()->pluck('account_id')->filter()->all() ?: [0])
                ->orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('hr::salaries.deductions.show', compact(
            'deduction', 'canManage', 'typeAccount', 'postingBlock', 'journals', 'treasuries'
        ));
    }

    public function edit($id)
    {
        $deduction = Deduction::findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeEdited()) {
            return redirect()->route('hr.deductions.show', $id)
                ->with('error', 'لا يُعدَّل إلا خصميةٌ بانتظار الاعتماد. الحالة: '.$deduction->status_label);
        }

        $employees = $this->employeeOptions($deduction->employee_id);
        $types = Deduction::types();
        $typeAccounts = $this->typeAccounts();

        return view('hr::salaries.deductions.edit', compact('deduction', 'employees', 'types', 'typeAccounts'));
    }

    public function update(DeductionRequest $request, $id)
    {
        $deduction = Deduction::findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeEdited()) {
            return back()->withInput()->with('error', 'لا يُعدَّل إلا خصميةٌ بانتظار الاعتماد. الحالة: '.$deduction->status_label);
        }

        $validated = $request->validated();

        if (! $this->canAccessEmployee($validated['employee_id'])) {
            return back()->withInput()->withErrors(['employee_id' => 'الموظف المختار خارج نطاق الموظفين المصرّح لك بهم.']);
        }

        try {
            DB::beginTransaction();

            $original = $deduction->only(['employee_id', 'deduction_type', 'amount', 'date', 'reason', 'notes']);
            $deduction->update($validated);

            $changes = [];
            foreach ($validated as $k => $v) {
                if (($original[$k] ?? null) != $v) {
                    $changes[$k] = ['old' => $original[$k] ?? null, 'new' => $v];
                }
            }

            if (! empty($changes)) {
                $this->logHistory($deduction, DeductionHistory::ACTION_UPDATED, 'تعديل بيانات الخصمية', null, $changes);
            }

            DB::commit();

            return redirect()->route('hr.deductions.show', $id)->with('success', 'تم تحديث الخصمية بنجاح');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'update');
            DB::rollBack();

            return back()->withInput()->with('error', 'فشل التحديث: '.$e->getMessage());
        }
    }

    /**
     * اعتماد خصمية — وقيدُها في اللحظة نفسها.
     *
     * يُسأل أوّلاً هل يمكن قيدُها (حسابُ النوع وذمّةُ الموظف)، فيُرفض الاعتمادُ
     * بالسبب بدل أن تُعتمد خصميةٌ بلا قيدٍ ثمّ يفشل صرفُ الراتب بسببها.
     */
    public function approve(Request $request, $id)
    {
        $deduction = Deduction::with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeApproved()) {
            return back()->with('error', 'لا يمكن اعتماد خصمية بحالة: '.$deduction->status_label);
        }

        if ($block = $this->accounting->blockReason($deduction)) {
            return back()->with('error', 'لا تُعتمد الخصمية قبل ضبط قيدها: '.$block);
        }

        $notes = $request->input('approval_notes');

        try {
            DB::beginTransaction();

            $locked = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);
            if (! $locked->canBeApproved()) {
                throw new \RuntimeException('تغيّرت حالة الخصمية: '.$locked->status_label);
            }

            // القيدُ قبل الحالة: إن فشل لم تُعتمد
            $journal = $this->accounting->ensurePosted($locked);

            $locked->update([
                'status' => Deduction::STATUS_APPROVED,
                'approved_by_user' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->logHistory($locked, DeductionHistory::ACTION_APPROVED,
                'تم اعتماد الخصمية'.($journal ? ' وقُيِّدت بالقيد '.$journal->reference_number : ''), $notes);
            $this->notifyEmployee($locked, 'تم اعتماد خصمية',
                'تم اعتماد خصمية بمبلغ '.CurrencySettings::format($locked->amount).' بسبب: '.$locked->reason
            );

            DB::commit();

            return back()->with('success', 'تم اعتماد الخصمية وإنشاء قيدها المحاسبي. تُقتطع من الراتب عند صرفه.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'approve');
            DB::rollBack();

            return back()->with('error', 'فشل الاعتماد: '.$e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $deduction = Deduction::findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeRejected()) {
            return back()->with('error', 'لا يمكن رفض خصمية بحالة: '.$deduction->status_label);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], ['rejection_reason.required' => 'يرجى إدخال سبب الرفض']);

        try {
            DB::beginTransaction();

            $deduction->update([
                'status' => Deduction::STATUS_REJECTED,
                'rejected_by_user' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $this->logHistory($deduction, DeductionHistory::ACTION_REJECTED, 'تم رفض الخصمية', $validated['rejection_reason']);
            $this->notifyEmployee($deduction, 'تم رفض خصمية', 'تم رفض الخصمية. السبب: '.$validated['rejection_reason']);

            DB::commit();

            return back()->with('success', 'تم رفض الخصمية');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'reject');
            DB::rollBack();

            return back()->withInput()->with('error', 'فشل الرفض: '.$e->getMessage());
        }
    }

    /**
     * إلغاء خصمية — ويُعكس ما قُيِّد منها ولم يُقتطع بعد (عبر حدث الموديل).
     *
     * ما اقتُطع في راتبٍ مصروف لا يُلغى هنا: خرج فعلاً، وتصحيحُه بعكس الصرفة.
     */
    public function cancel(Request $request, $id)
    {
        $deduction = Deduction::findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeCancelled()) {
            return back()->with('error', 'لا يمكن إلغاء خصمية بحالة: '.$deduction->status_label);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);
        $reason = $validated['cancellation_reason'] ?? null;

        try {
            DB::beginTransaction();

            $locked = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);
            if (! $locked->canBeCancelled()) {
                throw new \RuntimeException('تغيّرت حالة الخصمية: '.$locked->status_label);
            }

            $reversible = $locked->posted_outstanding;

            $locked->update([
                'status' => Deduction::STATUS_CANCELLED,
                'cancelled_by_user' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->logHistory($locked, DeductionHistory::ACTION_CANCELLED,
                'تم إلغاء الخصمية'.($reversible > 0.009 ? ' وعُكس قيدُ '.CurrencySettings::format($reversible).' لم يُقتطع' : ''), $reason);
            $this->notifyEmployee($locked, 'تم إلغاء خصمية', 'تم إلغاء الخصمية'.($reason ? '. السبب: '.$reason : ''));

            DB::commit();

            return back()->with('success', 'تم إلغاء الخصمية'.($reversible > 0.009 ? ' وعكس قيدها.' : '.'));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'cancel');
            DB::rollBack();

            return back()->withInput()->with('error', 'فشل الإلغاء: '.$e->getMessage());
        }
    }

    /**
     * تسويةٌ يدوية خارج الراتب — كاملة أو جزئية، بإحدى طريقتين لكلٍّ قيدُها:
     *
     *   • تحصيلٌ نقدي (`cash`): الموظفُ دفع المبلغ نقداً ← مدين الخزينة / دائن ذمّته.
     *   • إعفاء (`waiver`): تنازلت المنشأة عنه ← مدين حساب النوع / دائن ذمّته.
     *
     * كانت التسويةُ اليدوية تزيد «المسوّى» بلا قيد، فتبقى ذمّةُ الموظف مدينةً
     * بمبلغٍ لن يُقتطع أبداً.
     */
    public function settle(Request $request, $id)
    {
        $deduction = Deduction::with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeSettled()) {
            return back()->with('error', 'لا يمكن تسوية خصمية بحالة: '.$deduction->status_label);
        }

        $maxRemaining = $deduction->remaining_amount;

        $validated = $request->validate([
            'settle_amount' => 'required|numeric|min:0.01|max:'.$maxRemaining,
            'settlement_method' => 'required|in:cash,waiver',
            'treasury_id' => 'required_if:settlement_method,cash|nullable|integer|exists:accounts,id',
            'settlement_notes' => 'nullable|string|max:1000',
        ], [
            'settle_amount.required' => 'يرجى إدخال المبلغ المراد تسويته',
            'settle_amount.max' => 'المبلغ يتجاوز المتبقي ('.CurrencySettings::format($maxRemaining).')',
            'settlement_method.required' => 'اختر طريقة التسوية',
            'treasury_id.required_if' => 'اختر الخزينة التي استُلم فيها المبلغ',
        ]);

        $amount = round((float) $validated['settle_amount'], 2);
        $method = $validated['settlement_method'];
        $notes = $validated['settlement_notes'] ?? null;

        if ($block = $this->accounting->blockReason($deduction)) {
            return back()->withInput()->with('error', $block);
        }

        try {
            DB::beginTransaction();

            $locked = Deduction::query()->lockForUpdate()->findOrFail($deduction->id);
            if (! $locked->canBeSettled() || $amount > $locked->remaining_amount + 0.001) {
                throw new \RuntimeException('تغيّر المتبقي من الخصمية — أعد فتح الشاشة.');
            }

            $journal = $method === 'cash'
                ? $this->accounting->collectCash($locked, (int) $validated['treasury_id'], $amount, $notes)
                : $this->accounting->waive($locked, $amount, $notes);

            $locked->refresh();
            $newSettled = round((float) $locked->settled_amount + $amount, 2);
            $isFullySettled = $newSettled >= (float) $locked->amount - 0.001;

            $locked->update([
                'settled_amount' => $newSettled,
                'status' => $isFullySettled ? Deduction::STATUS_SETTLED : Deduction::STATUS_PARTIALLY_SETTLED,
                'settled_at' => $isFullySettled ? now() : $locked->settled_at,
            ]);

            $label = $method === 'cash' ? 'تحصيل نقدي' : 'إعفاء';
            $this->logHistory(
                $locked,
                $isFullySettled ? DeductionHistory::ACTION_SETTLED : DeductionHistory::ACTION_PARTIALLY_SETTLED,
                ($isFullySettled ? 'تسوية نهائية' : 'تسوية جزئية').' ('.$label.') بمبلغ '.CurrencySettings::format($amount).' — قيد '.$journal->reference_number,
                $notes,
                null,
                $amount
            );

            $this->notifyEmployee(
                $locked,
                $isFullySettled ? 'تم تسوية خصمية' : 'تسوية جزئية لخصمية',
                'تم تسوية '.CurrencySettings::format($amount).' ('.$label.')'.
                ($isFullySettled ? ' — تسوية نهائية' : ' — متبقي: '.CurrencySettings::format($locked->fresh()->remaining_amount))
            );

            DB::commit();

            return back()->with('success', 'تمت التسوية ('.$label.') وأُنشئ قيدها '.$journal->reference_number.'.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'settle');
            DB::rollBack();

            return back()->withInput()->with('error', 'فشل التسوية: '.$e->getMessage());
        }
    }

    /** حذف خصمية (فقط للمعلقة/المرفوضة/الملغاة — لا قيدَ قائمٌ لها). */
    public function destroy($id)
    {
        $deduction = Deduction::findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        if (! $deduction->canBeDeleted()) {
            return back()->with('error', 'لا يمكن حذف خصمية بحالة: '.$deduction->status_label);
        }

        // ملغاةٌ لها قيودٌ تاريخية (اعتمادٌ ثمّ عكس) — تبقى أثراً لا تُحذف
        if ($deduction->journal_entry_id) {
            return back()->with('error', 'لهذه الخصمية قيودٌ محاسبية (اعتمادٌ ثمّ عكس) — تبقى ملغاةً ولا تُحذف.');
        }

        try {
            $deduction->delete();

            return redirect()->route('hr.deductions.index')->with('success', 'تم حذف الخصمية');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Deduction', 'destroy');

            return back()->with('error', 'فشل الحذف: '.$e->getMessage());
        }
    }

    public function history($id)
    {
        $deduction = Deduction::with(['employee', 'histories.user'])->findOrFail($id);
        $this->authorizeEmployeeScope($deduction->employee_id);

        return view('hr::salaries.deductions.history', compact('deduction'));
    }

    // ============== Helpers ==============

    /**
     * موظفو النموذج: النشطون ضمن النطاق وفرع الشريط — ومعهم موظفُ السجلّ المفتوح.
     */
    private function employeeOptions(?int $keepId = null)
    {
        $keep = $keepId ? [$keepId] : [];

        $query = BranchTree::headerEmployees($this->scopeToEmployees(Employee::query(), 'id'), $keep)
            ->where(function ($q) use ($keepId) {
                $q->whereIn('status', ['active', '1', 1]);
                if ($keepId) {
                    $q->orWhere('id', $keepId);
                }
            });

        return $query->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'nickname']);
    }

    /**
     * حسابُ كلِّ نوع كما سيُقيَّد — ليُعرض في النموذج قبل الحفظ.
     *
     * @return array<string, string|null>
     */
    private function typeAccounts(): array
    {
        $out = [];
        foreach (array_keys(Deduction::types()) as $type) {
            $id = $this->accounting->accountIdFor($type);
            $acc = $id ? Account::find($id, ['id', 'name', 'code']) : null;
            $out[$type] = $acc ? trim(($acc->code ? $acc->code.' — ' : '').$acc->name) : null;
        }

        return $out;
    }

    private function logHistory(
        Deduction $deduction,
        string $action,
        string $description,
        ?string $notes = null,
        ?array $changes = null,
        ?float $amount = null
    ): void {
        DeductionHistory::create([
            'deduction_id' => $deduction->id,
            'action' => $action,
            'user_id' => Auth::id(),
            'description' => $description,
            'changes' => $changes,
            'notes' => $notes,
            'amount' => $amount,
        ]);
    }

    private function notifyEmployee(Deduction $deduction, string $title, string $body): void
    {
        try {
            $employeeUser = User::where('employee_id', $deduction->employee_id)->first();
            if (! $employeeUser) {
                return;
            }

            // إشعار داخلي للموظف فقط — بدون client_id ولا ربط بسجل العميل
            $payload = [
                'user_id' => Auth::id(),
                'receiver_id' => $employeeUser->id,
                'title' => $title,
                'description' => $body,
                'type' => 'deduction',
                'read' => 0,
                'is_sent' => 1,
                'is_scheduled' => 0,
            ];

            if (Schema::hasColumn('notifications', 'data')) {
                $payload['data'] = ['employee_only' => true];
            }

            notifications::create($payload);
        } catch (\Throwable $e) {
            // تجاهل أخطاء الإشعار حتى لا تكسر العملية الرئيسية
        }
    }
}
