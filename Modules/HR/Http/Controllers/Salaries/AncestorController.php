<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\InstallmentPayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\SalaryAdvance;
use App\Models\Treasury;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\Concerns\GeneratesHrDocumentNumbers;
use Modules\HR\Http\Controllers\Concerns\PostsHrJournalEntries;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;
use Modules\HR\Support\SalaryAdvanceService;

/**
 * السلف القديمة (`salary_advances`) — نظامُ الأقساط المجدولة الأوّل.
 *
 * القائمةُ والإنشاءُ يُحوَّلان إلى «إدارة السلف» الموحَّدة (انظر المسارات)،
 * وبقي هنا عرضُ السلفة القديمة وتعديلُ جدولها وتحصيلُ أقساطها وإلغاؤها.
 *
 * ما أُصلح:
 *   • **الحذف** كان يمحو السلفةَ ويُبقي قيدَ صرفها في الدفتر: الموظفُ مدينٌ
 *     في الأستاذ بسلفةٍ لا وجود لها. صار **إلغاءً** بقيدٍ عكسيّ، ويُمنع متى
 *     سُدِّد قسط.
 *   • **تحصيلُ القسط** كان يقبل رقمَ أيِّ قسطٍ في المنشأة (من سلفةٍ أخرى)
 *     ولا يُحدِّث عددَ الأقساط المسدَّدة.
 *   • **التعديل** كان يُرجع خطأً دائماً (`$advance` غير معرَّف) ويتحقّق من
 *     الموظف في جدول المستخدمين؛ ويُولِّد الأقساطَ فوق القديمة المتأخّرة.
 *   • **الإنشاء** كان يصرف بلا فحصٍ لرصيد الخزينة.
 */
class AncestorController extends Controller
{
    use GeneratesHrDocumentNumbers;
    use PostsHrJournalEntries;
    use ScopesToAccessibleEmployees;

    public function __construct(private SalaryAdvanceService $advances)
    {
        // مجموعة `loans.*` — كلُّ فعلٍ بصلاحيته، والنطاقُ على كلّ سجلٍّ مفرد
        $this->middleware('permission.role:loans.index', ['only' => ['index']]);
        $this->middleware('permission.role:loans.show', ['only' => ['show']]);
        $this->middleware('permission.role:loans.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:loans.settle', ['only' => ['pay', 'storePayments']]);
        // الإنشاءُ القديم يصرف في الخطوة نفسها
        $this->middleware('permission.role:loans.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:loans.disburse', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:loans.cancel', ['only' => ['cancel']]);
        $this->middleware('permission.role:loans.destroy', ['only' => ['destroy']]);
    }

    private function findScoped($id, array $with = []): SalaryAdvance
    {
        $advance = SalaryAdvance::with($with)->findOrFail($id);
        $this->authorizeEmployeeScope($advance->employee_id);

        return $advance;
    }

    private function errorText(\Throwable $e): string
    {
        return $e instanceof ValidationException
            ? (string) collect($e->errors())->flatten()->first()
            : $e->getMessage();
    }

    public function index(Request $request)
    {
        $query = SalaryAdvance::query()->with(['employee.advanceAccount', 'treasury', 'payments']);
        $this->scopeToEmployees($query);

        if ($request->filled('advance_search')) {
            $query->where(function ($q) use ($request) {
                $q->where('id', 'LIKE', "%{$request->advance_search}%")->orWhere('amount', 'LIKE', "%{$request->advance_search}%");
            });
        }
        if ($request->filled('payment_rate')) {
            $query->where('payment_rate', $request->payment_rate);
        }
        if ($request->filled('employee_search')) {
            // قائمةُ الفلتر تُرسل معرّفَ الموظف؛ والنصُّ (رابطٌ قديم) يُبحث به في الاسم
            ctype_digit((string) $request->employee_search)
                ? $query->where('employee_id', (int) $request->employee_search)
                : $query->whereHas('employee', fn ($q) => $q->where('first_name', 'LIKE', "%{$request->employee_search}%"));
        }
        if ($request->filled('next_payment_from')) {
            $query->where('installment_start_date', '>=', $request->next_payment_from);
        }
        if ($request->filled('next_payment_to')) {
            $query->where('installment_start_date', '<=', $request->next_payment_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn ($q) => BranchTree::apply($q, $request->branch_id));
        }
        if ($request->filled('tag')) {
            $query->where('tag', 'LIKE', "%{$request->tag}%");
        }

        $perPage = $request->per_page ?? 10;
        $ancestors = $query->latest()->paginate($perPage);

        $displayedTotalAmount = $ancestors->sum('amount');
        $displayedTotalPaid = $ancestors->sum(fn ($a) => $a->payments->where('status', 'paid')->sum('amount'));
        $displayedTotalInstallments = $ancestors->sum(fn ($a) => $a->payments->where('status', 'paid')->count());
        $displayedProgressPercentage = $displayedTotalAmount > 0 ? ($displayedTotalPaid / $displayedTotalAmount) * 100 : 0;

        $ancestors->each(function ($ancestor) {
            $ancestor->next_payment = $ancestor->payments()->where('status', 'unpaid')->orderBy('due_date')->first();
        });

        $paymentRates = [1 => 'شهري', 2 => 'اسبوعي', 3 => 'يومي'];
        $statuses = ['active' => 'نشط', 'completed' => 'مكتمل', 'cancelled' => 'ملغي'];

        $branches = Branch::all();
        $employees = $this->scopeToEmployees(Employee::query(), 'id')->get();
        $tags = $this->scopeToEmployees(SalaryAdvance::query())->distinct()->whereNotNull('tag')->pluck('tag');
        $account_setting = AccountSetting::where('user_id', auth()->id())->first();

        return view('hr::salaries.ancestor.index', compact('ancestors', 'paymentRates', 'statuses', 'branches', 'tags', 'employees', 'account_setting', 'displayedTotalAmount', 'displayedTotalPaid', 'displayedTotalInstallments', 'displayedProgressPercentage', 'request'));
    }

    public function create()
    {
        $employees = $this->scopeToEmployees(Employee::query(), 'id')
            ->whereNotNull('employee_advance_account_id')
            ->with('advanceAccount')
            ->orderBy('first_name')
            ->get();

        $employeesWithoutAccount = $this->scopeToEmployees(Employee::query(), 'id')
            ->whereNull('employee_advance_account_id')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname']);

        $treasuries = Treasury::all();
        $nextAdvanceCode = $this->previewDocumentNumber('advance');

        return view('hr::salaries.ancestor.create', compact('employees', 'employeesWithoutAccount', 'treasuries', 'nextAdvanceCode'));
    }

    /**
     * تواريخُ استحقاق الأقساط — مصدرٌ واحد للإنشاء وللتعديل.
     *
     * كان الاحتسابُ مكتوباً ثلاث مرّات، وفي إحداها كان «3» ربعَ سنويّ وفي
     * الأخريين يومياً — وشاشةُ الاختيار تقول «يومي».
     */
    private function dueDate(Carbon $start, int $rate, int $index): Carbon
    {
        $due = $start->copy();

        return match ($rate) {
            SalaryAdvance::PAYMENT_WEEKLY => $due->addWeeks($index),
            SalaryAdvance::PAYMENT_DAILY => $due->addDays($index),
            default => $due->addMonths($index),
        };
    }

    public function pay($id)
    {
        $salaryAdvance = $this->findScoped($id, ['payments', 'employee']);

        if (($salaryAdvance->status ?? 'active') === 'cancelled') {
            return redirect()->route('ancestor.show', $id)->with('error', 'السلفة ملغاة — لا أقساط تُحصَّل.');
        }

        $InstallmentPayments = InstallmentPayment::select('id', 'installment_number', 'amount', DB::raw("DATE_FORMAT(due_date, '%Y-%m-%d') as due_date"), 'status')
            ->where('salary_advance_id', $id)
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->get();

        $treasuryId = $this->advances->defaultTreasuryId($salaryAdvance->employee_id);
        $mainTreasuryAccount = $treasuryId ? Account::find($treasuryId) : null;
        $depositTreasuries = $this->advances->treasuryOptions('deposit');

        return view('hr::salaries.ancestor.pay', compact('salaryAdvance', 'InstallmentPayments', 'mainTreasuryAccount', 'depositTreasuries', 'id'));
    }

    /**
     * تحصيلُ قسطٍ نقداً:  مدين الخزينة / دائن حساب سلف الموظف.
     */
    public function storePayments(Request $request, $id)
    {
        $validated = $request->validate([
            'installmentId' => ['required', 'integer', 'exists:installment_payments,id'],
            'payment_date' => ['required', 'date'],
            'treasury_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ], [
            'installmentId.required' => 'اختر القسط',
            'payment_date.required' => 'حدّد تاريخ الدفع',
        ]);

        $salaryAdvance = $this->findScoped($id, ['employee']);

        try {
            DB::transaction(function () use ($validated, $salaryAdvance) {
                /** @var InstallmentPayment $installment */
                $installment = InstallmentPayment::lockForUpdate()
                    ->where('salary_advance_id', $salaryAdvance->id)   // قسطُ هذه السلفة لا غيرها
                    ->find($validated['installmentId']);

                if (! $installment) {
                    throw new \RuntimeException('القسط المختار لا يخصّ هذه السلفة.');
                }
                if ($installment->status !== 'unpaid') {
                    throw new \RuntimeException('هذا القسط ليس مستحقّاً للتحصيل (حالته: '.$installment->status.').');
                }
                if (! $salaryAdvance->employee) {
                    throw new \RuntimeException('سجل الموظف غير موجود لهذه السلفة.');
                }

                $employeeAdvanceAccount = $salaryAdvance->employee->getAdvanceAccountOrFail();
                $employeeName = trim(($salaryAdvance->employee->first_name ?? '').' '.($salaryAdvance->employee->middle_name ?? ''));

                $treasuryId = (int) ($validated['treasury_id'] ?? 0) ?: $this->advances->defaultTreasuryId($salaryAdvance->employee_id);
                $treasury = $treasuryId ? Account::find($treasuryId) : null;
                if (! $treasury) {
                    throw new \RuntimeException('لا توجد خزينة متاحة — اربط «الخزينة الرئيسية» في إعدادات الحسابات المحاسبية.');
                }
                if ($block = TreasuryAccessGuard::depositBlockReason($treasury->id)) {
                    throw new \RuntimeException($block);
                }

                $amount = (float) $installment->amount;

                $journalEntry = $this->createHrJournalEntry([
                    'reference_number' => $installment->id,
                    'date' => $validated['payment_date'],
                    'description' => "تحصيل قسط سلفة رقم {$salaryAdvance->id} - الموظف {$employeeName}",
                    'status' => 1,
                    'employee_id' => $salaryAdvance->employee_id,
                    'created_by_employee' => Auth::user()->employee_id ?: null,
                    'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                    'linking_type' => LinkingType::SINGLE,
                ], 'payment_date');

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $treasury->id,
                    'description' => "تحصيل قسط سلفة من {$employeeName} - {$treasury->name}",
                    'debit' => $amount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $employeeAdvanceAccount->id,
                    'description' => "تحصيل قسط سلفة - {$employeeAdvanceAccount->name}",
                    'debit' => 0,
                    'credit' => $amount,
                    'is_debit' => false,
                ]);

                $installment->update([
                    'status' => 'paid',
                    'payment_date' => $validated['payment_date'],
                    'account_id' => $treasury->id,
                ]);

                $paidCount = InstallmentPayment::where('salary_advance_id', $salaryAdvance->id)->where('status', 'paid')->count();
                $salaryAdvance->update([
                    'paid_installments' => $paidCount,
                    'status' => $paidCount >= (int) $salaryAdvance->total_installments ? 'completed' : 'active',
                ]);

                ModelsLog::create([
                    'type' => 'salary_installment_log',
                    'type_id' => $installment->id,
                    'type_log' => 'log',
                    'description' => 'تحصيل القسط '.$installment->installment_number.' نقداً — قيد #'.$journalEntry->id,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Ancestor', 'storePayments');

            return back()->withInput()->with('error', 'تعذّر تحصيل القسط: '.$this->errorText($e));
        }

        return redirect()->route('ancestor.show', $id)->with('success', 'حُصِّل القسط وكُتب قيده.');
    }

    /**
     * تعديلُ جدول السلفة القديمة — لا مبلغِها ولا موظفِها: قيدُ صرفها كُتب
     * بهما، وتغييرُهما بلا قيدٍ مقابل يفصل السجلَّ عن الدفتر. ومن أخطأ في
     * المبلغ يُلغيها (بقيدٍ عكسيّ) ويُصدر غيرها.
     */
    public function update(Request $request, $id)
    {
        $ancestor = $this->findScoped($id, ['payments']);

        if ($ancestor->payments->where('status', 'paid')->isNotEmpty() || ($ancestor->status ?? 'active') !== 'active') {
            return redirect()->route('ancestor.show', $id)->with('error', 'لا تُعدَّل سلفةٌ سُدِّد منها قسطٌ أو أُغلقت.');
        }

        $validated = $request->validate([
            'installment_amount' => 'required|numeric|min:0.01|lte:'.(float) $ancestor->amount,
            'payment_rate' => 'required|in:monthly,weekly,daily',
            'installment_start_date' => 'required|date',
            'tag' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ], [
            'installment_amount.required' => 'أدخل قيمة القسط',
            'installment_amount.lte' => 'قيمة القسط يجب أن تكون أقل من أو تساوي مبلغ السلفة',
            'payment_rate.required' => 'اختر معدّل السداد',
            'installment_start_date.required' => 'حدّد تاريخ أوّل قسط',
        ]);

        $inRun = DB::table('payroll_run_items as i')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->where('i.source_table', 'installment_payments')
            ->whereIn('i.source_id', $ancestor->payments->pluck('id')->all() ?: [0])
            ->whereNull('r.reversed_at')
            ->exists();

        if ($inRun) {
            return back()->withInput()->with('error', 'أحدُ أقساط السلفة مدرجٌ في مسير رواتب قائم — لا يُعاد جدولُها قبل عكسه.');
        }

        try {
            DB::transaction(function () use ($ancestor, $validated, $request) {
                $rate = ['monthly' => 1, 'weekly' => 2, 'daily' => 3][$validated['payment_rate']];
                $installmentAmount = (float) $validated['installment_amount'];
                $total = (int) ceil((float) $ancestor->amount / $installmentAmount);

                $ancestor->update([
                    'installment_amount' => $installmentAmount,
                    'total_installments' => $total,
                    'payment_rate' => $rate,
                    'installment_start_date' => $validated['installment_start_date'],
                    'pay_from_salary' => $request->has('pay_from_salary') ? 1 : 0,
                    'tag' => $validated['tag'] ?? null,
                    'note' => $validated['note'] ?? null,
                ]);

                // لا مسدَّدَ فيها: يُعاد الجدولُ كلُّه من أوّل قسط
                InstallmentPayment::where('salary_advance_id', $ancestor->id)->where('status', 'unpaid')->delete();
                $this->createInstallments($ancestor->fresh(), $total, $installmentAmount);

                ModelsLog::create([
                    'type' => 'salary_advance_log',
                    'type_id' => $ancestor->id,
                    'type_log' => 'log',
                    'description' => 'أُعيدت جدولة السلفة: '.$total.' قسط × '.number_format($installmentAmount, 2),
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Ancestor', 'update');

            return back()->withInput()->with('error', 'تعذّر تحديث السلفة: '.$this->errorText($e));
        }

        return redirect()->route('ancestor.show', $id)->with('success', 'حُدِّث جدول السلفة وأقساطها.');
    }

    /**
     * إنشاءُ سلفةٍ قديمة (المسار باقٍ للتوافق — الشاشةُ تُحوِّل إلى «إصدار
     * سلفة» في إدارة السلف). الصرفُ بعد `TreasuryBalanceGuard` وقبل القيد.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'submission_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'installment_amount' => 'required|numeric|min:0.01|lte:amount',
            'currency' => 'nullable|integer',
            'payment_rate' => 'required|in:monthly,weekly,daily',
            'installment_start_date' => 'required|date',
            'tag' => 'nullable|string',
            'note' => 'nullable|string',
        ], [
            'employee_id.required' => 'يرجى اختيار الموظف',
            'submission_date.required' => 'يرجى تحديد تاريخ التقديم',
            'amount.required' => 'يرجى إدخال مبلغ السلفة',
            'installment_amount.required' => 'يرجى إدخال مبلغ القسط',
            'installment_amount.lte' => 'قيمة القسط يجب أن تكون أقل من أو تساوي قيمة السلفة',
            'payment_rate.required' => 'يرجى اختيار معدل الدفع',
        ]);

        if (! $this->canAccessEmployee($validated['employee_id'])) {
            return back()->withInput()->withErrors(['employee_id' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بهم.']);
        }

        try {
            DB::transaction(function () use ($validated, $request) {
                $employee = Employee::findOrFail($validated['employee_id']);
                $employeeAdvanceAccount = $employee->getAdvanceAccountOrFail();
                $employeeName = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? ''));

                $treasuryId = $this->advances->defaultTreasuryId($employee->id);
                if (! $treasuryId) {
                    throw new \RuntimeException('لا توجد خزينة متاحة — اربط «الخزينة الرئيسية» في إعدادات الحسابات المحاسبية.');
                }

                $amount = round((float) $validated['amount'], 2);
                // شرطُ السحب والرصيدُ تحت قفل — قبل إنشاء السلفة وقيدها
                $treasury = TreasuryBalanceGuard::assertWithdrawable($treasuryId, $amount, 'صرف السلفة');

                $installmentAmount = (float) $validated['installment_amount'];
                $total = (int) ceil($amount / $installmentAmount);
                $treasuryRow = Treasury::where('account_id', $treasury->id)->value('id');

                try {
                    $code = $this->issueDocumentNumber('advance');
                } catch (\Throwable $e) {
                    $code = null;
                    OperationLog::failure($e, 'HR.Advance', 'code');
                }

                $advance = SalaryAdvance::create([
                    'code' => $code,
                    'employee_id' => $employee->id,
                    'submission_date' => $validated['submission_date'],
                    'amount' => $amount,
                    'currency' => $validated['currency'] ?? 1,
                    'installment_amount' => $installmentAmount,
                    'total_installments' => $total,
                    'paid_installments' => 0,
                    'payment_rate' => ['monthly' => 1, 'weekly' => 2, 'daily' => 3][$validated['payment_rate']],
                    'installment_start_date' => $validated['installment_start_date'],
                    'treasury_id' => $treasuryRow ?: $treasury->id,
                    'pay_from_salary' => $request->has('pay_from_salary') ? 1 : 0,
                    'tag' => $validated['tag'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'status' => 'active',
                ]);

                $this->createInstallments($advance, $total, $installmentAmount);

                $journalEntry = $this->createHrJournalEntry([
                    'reference_number' => $advance->id,
                    'date' => $advance->submission_date ?? now(),
                    'description' => "صرف سلفة رقم {$advance->id} للموظف {$employeeName}",
                    'status' => 1,
                    'employee_id' => $advance->employee_id,
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::EXPENSES,
                    'linking_type' => LinkingType::SINGLE,
                ], 'submission_date');

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $employeeAdvanceAccount->id,
                    'description' => "صرف سلفة - {$employeeAdvanceAccount->name}",
                    'debit' => $amount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $treasury->id,
                    'description' => "صرف سلفة من {$treasury->name} للموظف {$employeeName}",
                    'debit' => 0,
                    'credit' => $amount,
                    'is_debit' => false,
                ]);

                ModelsLog::create([
                    'type' => 'salary_advance_log',
                    'type_id' => $advance->id,
                    'type_log' => 'log',
                    'description' => 'أُنشئت السلفة وصُرفت — قيد #'.$journalEntry->id,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Ancestor', 'store');

            return back()->withInput()->with('error', 'تعذّر إنشاء السلفة: '.$this->errorText($e));
        }

        return redirect()->route('hr.advance-approvals.index')->with('success', 'تم إضافة السلفة وصرفها');
    }

    protected function createInstallments($salaryAdvance, $totalInstallments, $installmentAmount)
    {
        $startDate = Carbon::parse($salaryAdvance->installment_start_date);
        $rows = [];

        for ($i = 1; $i <= $totalInstallments; $i++) {
            // القسطُ الأخير يحمل الباقي كي يساوي المجموعُ مبلغَ السلفة
            $current = $i == $totalInstallments
                ? round((float) $salaryAdvance->amount - $installmentAmount * ($totalInstallments - 1), 2)
                : $installmentAmount;

            $rows[] = [
                'salary_advance_id' => $salaryAdvance->id,
                'installment_number' => $i,
                'amount' => $current,
                'due_date' => $this->dueDate($startDate, (int) $salaryAdvance->payment_rate, $i - 1),
                'status' => 'unpaid',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('installment_payments')->insert($rows);
        }
    }

    public function show($id)
    {
        $ancestor = SalaryAdvance::with(['employee.advanceAccount', 'treasury', 'payments'])->find($id);

        if (! $ancestor) {
            return redirect()->route('hr.advance-approvals.index')->with('error', 'السلفة غير موجودة');
        }

        $this->authorizeEmployeeScope($ancestor->employee_id);

        $logs = ModelsLog::where(function ($query) use ($id, $ancestor) {
            $query->where('type', 'salary_advance_log')->where('type_id', $id);
            $query->orWhere(function ($q) use ($ancestor) {
                $q->where('type', 'salary_installment_log')->whereIn('type_id', $ancestor->payments->pluck('id'));
            });
        })
            ->with('user.branch')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        $totalPaid = $ancestor->payments->where('status', 'paid')->sum('amount');
        $paidInstallments = $ancestor->payments->where('status', 'paid')->count();
        $progressPercentage = $ancestor->amount > 0 ? ($totalPaid / $ancestor->amount) * 100 : 0;
        $account_setting = AccountSetting::where('user_id', auth()->id())->first();
        $advanceSummary = $ancestor->employee ? $ancestor->employee->getAdvanceSummary() : null;
        $cancelBlock = $this->advances->legacyCancelBlockReason($ancestor);
        $disbursement = $this->advances->legacyDisbursement($ancestor);
        $cancellationJournal = $ancestor->cancellation_journal_id ? JournalEntry::find($ancestor->cancellation_journal_id) : null;

        return view('hr::salaries.ancestor.show', compact(
            'ancestor', 'logs', 'id', 'totalPaid', 'paidInstallments', 'progressPercentage',
            'account_setting', 'advanceSummary', 'cancelBlock', 'disbursement', 'cancellationJournal'
        ));
    }

    public function edit($id)
    {
        $ancestor = $this->findScoped($id, ['employee', 'payments']);

        if ($ancestor->payments->where('status', 'paid')->isNotEmpty()) {
            return redirect()->route('ancestor.show', $id)->with('error', 'لا يمكن التعديل: سُدِّد من السلفة قسطٌ أو أكثر.');
        }
        if (($ancestor->status ?? 'active') !== 'active') {
            return redirect()->route('ancestor.show', $id)->with('error', 'لا تُعدَّل سلفةٌ مغلقة.');
        }

        $employees = BranchTree::headerEmployees(Employee::query(), [$ancestor->employee_id])->get();
        $treasure = Account::find($ancestor->treasury_id);

        return view('hr::salaries.ancestor.edit', compact('ancestor', 'employees', 'treasure'));
    }

    /**
     * إلغاءُ السلفة القديمة — قيدٌ عكسيّ إلى الخزينة وأقساطٌ «ملغاة».
     */
    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ], [
            'cancellation_reason.required' => 'اذكر سبب الإلغاء',
        ]);

        $advance = $this->findScoped($id, ['employee']);

        try {
            $journal = DB::transaction(fn () => $this->advances->cancelLegacy($advance, $validated['cancellation_reason']));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Ancestor', 'cancel');

            return back()->withInput()->with('error', 'تعذّر الإلغاء: '.$this->errorText($e));
        }

        return redirect()->route('ancestor.show', $id)->with('success', $journal
            ? 'أُلغيت السلفة ورُدّ مبلغها إلى الخزينة بالقيد العكسي #'.$journal->id.'.'
            : 'أُلغيت السلفة وأقساطها — لم يُعثر على قيد صرفٍ يُعكَس.');
    }

    /**
     * الحذفُ لم يعد يمحو السلفة: كان يُبقي قيدَ صرفها في الدفتر بلا سجلّ.
     * السلفُ تُلغى بقيدٍ عكسيّ (`cancel`) ولا تُحذف.
     */
    public function destroy($id)
    {
        $this->findScoped($id);

        return redirect()->route('ancestor.show', $id)
            ->with('error', 'السلف لا تُحذف — استخدم «إلغاء السلفة» فيُكتب قيدٌ عكسيّ ويبقى السجلّ.');
    }
}
