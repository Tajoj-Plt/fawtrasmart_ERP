<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EndOfServiceBenefit;
use App\Models\Expense;
use App\Models\JournalEntryDetail;
use App\Models\SalaryComponentType;
use App\Services\EndOfServiceBenefitService;
use App\Services\EndOfServiceCalculator;
use App\Services\PayrollAccountant;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\PayrollAccounts;
use App\Support\PayrollSettings;
use App\Support\TreasuryAccessGuard;
use App\Services\Finance\TreasuryBalanceGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\PostsHrJournalEntries;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * مكافأة نهاية الخدمة: الحساب من مدة الخدمة وسبب الإنهاء، ثم قيد المخصص فالتسوية.
 */
class EndOfServiceBenefitController extends Controller
{
    use PostsHrJournalEntries;
    use ScopesToAccessibleEmployees;

    /**
     * الصلاحيات — كلُّ فعلٍ بصلاحيته (مجموعة `eos.*`):
     *   القائمة ← eos.index        التفاصيل ← eos.show
     *   حساب/إعادة حساب المسودة ← eos.create
     *   اعتماد (قيد المخصص) ← eos.approve
     *   تسوية إلى ذمّة الموظف ← eos.settle
     *   صرفٌ نقديٌّ من خزينة ← eos.pay
     *   الإعدادات ← eos.settings
     */
    public function __construct(private EndOfServiceBenefitService $benefits)
    {
        $this->middleware('permission.role:eos.index', ['only' => ['index']]);
        $this->middleware('permission.role:eos.show', ['only' => ['show']]);
        $this->middleware('permission.role:eos.create', ['only' => ['store', 'recalculate']]);
        $this->middleware('permission.role:eos.approve', ['only' => ['approve']]);
        $this->middleware('permission.role:eos.settle', ['only' => ['settle']]);
        $this->middleware('permission.role:eos.pay', ['only' => ['pay']]);
        $this->middleware('permission.role:eos.settings', ['only' => ['settings', 'updateSettings']]);
    }

    public function index(Request $request)
    {
        $query = EndOfServiceBenefit::query()->with('employee')->orderByDesc('id');
        $this->scopeAnyQueryToEmployees($query, 'end_of_service_benefits.employee_id');

        $base = clone $query;

        if ($request->filled('status') && array_key_exists($request->status, EndOfServiceBenefit::statuses())) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }

        $totals = [
            'count' => (clone $base)->count(),
            'open' => (clone $base)->whereIn('status', [
                EndOfServiceBenefit::STATUS_DRAFT,
                EndOfServiceBenefit::STATUS_APPROVED,
                EndOfServiceBenefit::STATUS_SETTLED,
            ])->count(),
            'unpaid_amount' => (float) (clone $base)->where('status', EndOfServiceBenefit::STATUS_SETTLED)->sum('payable_amount'),
            'paid_amount' => (float) (clone $base)->where('status', EndOfServiceBenefit::STATUS_PAID)->sum('payable_amount'),
        ];

        return view('hr::salaries.eos.index', [
            'benefits' => $query->paginate(20)->withQueryString(),
            'totals' => $totals,
            'statuses' => EndOfServiceBenefit::statuses(),
            'employees' => $this->scopedEmployeeOptions([])->sortBy('first_name')->values(),
        ]);
    }

    public function show($id)
    {
        $benefit = EndOfServiceBenefit::query()
            ->with(['employee', 'accrualJournal', 'settlementJournal', 'treasury', 'expense'])
            ->findOrFail($id);
        $this->authorizeEmployeeScope($benefit->employee_id);

        $treasuries = $benefit->canBeDisbursed() && hasPermission('eos.pay')
            ? $this->disbursableTreasuries()
            : collect();

        return view('hr::salaries.eos.show', compact('benefit', 'treasuries'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);
        $this->authorizeEmployeeScope((int) $validated['employee_id']);

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $benefit = $this->benefits->syncDraftFor($employee, false);

        if (! $benefit) {
            return redirect()->back()->with('error', 'لا يُحسب الاستحقاق إلا لموظف أُنهيت خدمته.');
        }

        return redirect()->route('hr.eos.show', $benefit->id)
            ->with('success', 'تم حساب مكافأة نهاية الخدمة');
    }

    public function recalculate($id)
    {
        $benefit = EndOfServiceBenefit::query()->findOrFail($id);
        $this->authorizeEmployeeScope($benefit->employee_id);

        if (! $benefit->isDraft()) {
            return redirect()->back()->with('error', 'لا يُعاد الحساب بعد الاعتماد');
        }

        $this->benefits->syncDraftFor($benefit->employee, false);

        return redirect()->route('hr.eos.show', $benefit->id)->with('success', 'أُعيد الحساب');
    }

    public function approve($id)
    {
        $benefit = EndOfServiceBenefit::query()->with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($benefit->employee_id);

        if (! $benefit->isDraft()) {
            return redirect()->back()->with('error', 'لا يُعتمد إلا من المسودة');
        }

        $settings = $this->benefits->settings();
        if ($benefit->payable_amount >= 0.01 && ($missing = $this->missingAccountsMessage(
            [PayrollAccounts::EOS_EXPENSE => $settings['expense_account_id'], PayrollAccounts::EOS_PROVISION => $settings['provision_account_id']],
            'اعتماد المكافأة'
        ))) {
            return redirect()->back()->with('error', $missing);
        }

        try {
            DB::transaction(function () use ($benefit, $settings) {
                $journalId = null;
                if ((float) $benefit->payable_amount >= 0.01) {
                    $journalId = $this->postAccrual($benefit, $settings)->id;
                }
                $benefit->update([
                    'status' => EndOfServiceBenefit::STATUS_APPROVED,
                    'accrual_journal_entry_id' => $journalId,
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                ]);
            });

            return redirect()->route('hr.eos.show', $benefit->id)->with('success', 'اعتُمدت المكافأة وقُيِّد المخصص');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.EOS', 'approve');

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * تسويةُ المخصص إلى ذمّة الموظف — لا صرفٌ نقدي.
     *
     * كانت هذه الدالّةُ اسمُها `pay()` وتضبط `status = paid`. والقيدُ الذي
     * ترحّله صحيحٌ محاسبياً — مدين مخصص / دائن مستحقات الموظف — لكنّه لا
     * يمسّ خزينةً ولا يُنقص رصيداً: فالمكافأةُ بعده **التزامٌ قائم** لا
     * مبلغٌ مدفوع. وكان النظامُ يقولها «مدفوعة» ولا مسارَ فيه يُخرجها نقداً،
     * فيبقى حقُّ الموظف المنتهية خدمتُه مفتوحاً إلى الأبد.
     *
     * فصارت الحالةُ `settled`، والصرفُ النقديُّ خطوةً بعدها في `pay()`.
     */
    public function settle($id)
    {
        $benefit = EndOfServiceBenefit::query()->with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($benefit->employee_id);

        if (! $benefit->canBeSettled()) {
            return redirect()->back()->with('error', 'لا تُسوَّى إلا مكافأة معتمدة');
        }

        $payableAccount = $benefit->employee->salary_payable_account_id;
        $settings = $this->benefits->settings();
        if ((float) $benefit->payable_amount >= 0.01 && ! $payableAccount) {
            return redirect()->back()->with('error', 'الموظف بلا حساب مستحقات — افتح بطاقته ثمّ اضغط حفظ لإنشاء الحساب.');
        }
        if ((float) $benefit->payable_amount >= 0.01 && ($missing = $this->missingAccountsMessage(
            [PayrollAccounts::EOS_PROVISION => $settings['provision_account_id']],
            'تسوية المكافأة'
        ))) {
            return redirect()->back()->with('error', $missing);
        }

        try {
            DB::transaction(function () use ($benefit, $settings, $payableAccount) {
                $journalId = null;
                if ((float) $benefit->payable_amount >= 0.01) {
                    $journalId = $this->postSettlement($benefit, $settings, (int) $payableAccount)->id;
                }
                $benefit->update([
                    'status' => EndOfServiceBenefit::STATUS_SETTLED,
                    'settlement_journal_entry_id' => $journalId,
                    'settled_at' => now(),
                    'settled_by' => Auth::id(),
                ]);
            });

            return redirect()->route('hr.eos.show', $benefit->id)
                ->with('success', 'سُوِّيت المكافأة إلى مستحقات الموظف. تبقى مستحقةً له حتى تُصرف نقداً من خزينة.');
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.EOS', 'settle');

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * الخزائنُ وحساباتُ البنوك التي يجوز للمستخدم السحبُ منها — القائمةُ
     * نفسُها التي تعرضها شاشةُ صرف الرواتب، والحارسُ نفسُه عند الحفظ.
     *
     * @return Collection<int, Account>
     */
    private function disbursableTreasuries()
    {
        return TreasuryBalanceGuard::withdrawableAccounts();
    }

    /**
     * صرفُ المكافأة نقداً من خزينة — الخطوةُ التي لم تكن موجودة.
     *
     * التسويةُ قبلها تنقل المخصصَ إلى ذمّة الموظف؛ وهذه تُخرج المال:
     *
     *     مدين  مستحقات الموظف
     *         دائن  الخزينة
     *
     * ومعها سندُ صرفٍ يظهر في شاشة المصروفات كسند الراتب. وبها يُقفل
     * الالتزامُ الذي كان يبقى مفتوحاً بينما تقول الشاشة «مدفوعة».
     *
     * والضوابطُ هي ضوابطُ صرف الراتب نفسُها، فالنقدُ الخارجُ واحد: شرطُ
     * السحب من الخزينة، وكفايةُ الرصيد محتسبةً من الدفتر تحت قفل، وحارسُ
     * الفترة المحاسبية — وكلُّه في معاملةٍ واحدة.
     */
    public function pay(Request $request, $id)
    {
        $benefit = EndOfServiceBenefit::query()->with('employee')->findOrFail($id);
        $this->authorizeEmployeeScope($benefit->employee_id);

        if (! $benefit->canBeDisbursed()) {
            return redirect()->back()->with(
                'error',
                $benefit->isPaid()
                    ? 'صُرفت هذه المكافأة نقداً من قبل.'
                    : 'لا تُصرف إلا مكافأةٌ سُوِّيت إلى ذمّة الموظف أوّلاً.'
            );
        }

        $validated = $request->validate([
            'treasury_id' => 'nullable|integer|exists:accounts,id',
        ], [
            'treasury_id.exists' => 'الخزينة المختارة غير موجودة',
        ]);
        $validated['treasury_id'] = $validated['treasury_id'] ?? null;

        $payableAccountId = $benefit->employee->salary_payable_account_id ?? null;
        if (! $payableAccountId) {
            return redirect()->back()->with('error', 'الموظف بلا حساب مستحقات — لا يُعرف من أيِّ ذمّةٍ يُصرف.');
        }

        $amount = (float) $benefit->payable_amount;
        if ($amount < 0.01) {
            return redirect()->back()->with('error', 'لا مبلغَ يُصرف في هذه المكافأة.');
        }

        $treasury = $validated['treasury_id']
            ? $this->disbursableTreasuries()->firstWhere('id', (int) $validated['treasury_id'])
            : AccountingSettings::account('main_treasury'); // من «الحسابات المحاسبية» وحدها — لا ثابتَ احتياطيّاً

        if (! $treasury) {
            return redirect()->back()->withInput()->with('error', 'لا توجد خزينة صالحة للصرف. اختر خزينةً أو اربط «الخزينة الرئيسية» في الحسابات المحاسبية.');
        }

        if ($block = TreasuryAccessGuard::withdrawBlockReason($treasury->id)) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            DB::transaction(function () use ($benefit, $treasury, $payableAccountId, $amount) {
                // يُعاد فحصُ الحالة تحت قفل الصف: نقرتان متزامنتان لا تصرفان المكافأة مرّتين
                $fresh = EndOfServiceBenefit::query()->lockForUpdate()->findOrFail($benefit->id);
                if (! $fresh->canBeDisbursed()) {
                    throw new \RuntimeException('صُرفت هذه المكافأة من قبل أو تغيّرت حالتها.');
                }

                /*
                    كفايةُ الرصيد **قبل** القيد والسند، تحت قفل صفّ الخزينة،
                    ومن الدفتر لا من العمود المخزَّن — الحارسُ المشترك نفسُه
                    الذي يسأله صرفُ الراتب. فلا تصير الخزينةُ سالبة.
                */
                $locked = TreasuryBalanceGuard::lockAndAssert($treasury, $amount, 'صرف مكافأة نهاية الخدمة');

                $name = trim(($benefit->employee->first_name ?? '').' '.($benefit->employee->middle_name ?? ''));

                $journal = $this->createHrJournalEntry([
                    'reference_number' => 'EOS-P-'.$benefit->id,
                    'reference_id' => $benefit->id,
                    'date' => now(),
                    'description' => "صرف مكافأة نهاية خدمة — {$name}",
                    'status' => 1,
                    'employee_id' => $benefit->employee_id,
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::PAYROLL,
                    'linking_type' => LinkingType::SINGLE,
                ]);

                JournalEntryDetail::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $payableAccountId,
                    'description' => "إقفال مستحق نهاية الخدمة — {$name}",
                    'debit' => $amount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                JournalEntryDetail::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $locked->id,
                    'description' => "صرف مكافأة نهاية خدمة من {$locked->name} لـ {$name}",
                    'debit' => 0,
                    'credit' => $amount,
                    'is_debit' => false,
                ]);

                $expenseAccountId = $this->benefits->settings()['expense_account_id'] ?? null;

                $expense = Expense::create([
                    'code' => Expense::generateCode(),
                    'amount' => $amount,
                    'description' => "صرف مكافأة نهاية خدمة {$name}",
                    'date' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'treasury_id' => $locked->id,
                    'account_id' => $payableAccountId,
                    'expenses_category_id' => app(PayrollAccountant::class)
                        ->resolveOrCreateCategoryForAccount($expenseAccountId),
                    'is_recurring' => 0,
                    'tax1_amount' => 0,
                    'tax2_amount' => 0,
                ]);

                $benefit->update([
                    'status' => EndOfServiceBenefit::STATUS_PAID,
                    'treasury_id' => $locked->id,
                    'expense_id' => $expense->id,
                    'payment_journal_entry_id' => $journal->id,
                    'paid_at' => now(),
                    'paid_by' => Auth::id(),
                ]);
            });

            return redirect()->route('hr.eos.show', $benefit->id)
                ->with('success', 'صُرفت المكافأة نقداً وأُنشئ سند الصرف والقيد المحاسبي.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.EOS', 'pay');

            return redirect()->back()->withInput()->with('error', 'فشل الصرف: '.$e->getMessage());
        }
    }

    public function settings()
    {
        $eosSettings = $this->benefits->settings();

        /*
            الحسابان للعرض وحده — يُربطان من «إعدادات الحسابات المحاسبية»
            (`eos_expense`/`eos_provision`)، لا من هذه الشاشة. ويُعرضان
            كيفما كان مفتاحُ التفعيل: الاعتمادُ اليدويُّ يحتاجهما في الحالين.
        */
        $accountRows = PayrollAccounts::rows([PayrollAccounts::EOS_EXPENSE, PayrollAccounts::EOS_PROVISION]);

        return view('hr::salaries.eos.settings', compact('eosSettings', 'accountRows'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'eos_first_bracket_years' => 'required|numeric|min:0|max:50',
            'eos_first_months_per_year' => 'required|numeric|min:0|max:12',
            'eos_later_months_per_year' => 'required|numeric|min:0|max:12',
        ], [], [
            'eos_first_bracket_years' => 'سنوات الشريحة الأولى',
            'eos_first_months_per_year' => 'أشهر السنة في الشريحة الأولى',
            'eos_later_months_per_year' => 'أشهر السنة بعدها',
        ]);

        try {
            /*
                مفاتيحُ هذه الشاشة وحدها. وكان الحفظُ يكتب أيضاً:
                  • `eos_component_type_id` من حقلٍ أُزيل — فيُصفَّر عند كلّ حفظ؛
                  • `eos_resignation_bands` بالقيم الافتراضية — فيمحو أيَّ شرائح مخصّصة؛
                  • `eos_expense_account_id`/`eos_provision_account_id` — صار الحسابان
                    مفتاحين في «إعدادات الحسابات المحاسبية»، والعمودان متقاعدان.
                كلُّها لا تُكتب الآن وتبقى كما هي.
            */
            PayrollSettings::put([
                'eos_enabled' => $request->boolean('eos_enabled'),
                'eos_include_default_allowances' => $request->boolean('eos_include_default_allowances'),
                'eos_first_bracket_years' => $validated['eos_first_bracket_years'],
                'eos_first_months_per_year' => $validated['eos_first_months_per_year'],
                'eos_later_months_per_year' => $validated['eos_later_months_per_year'],
            ]);

            $redirect = redirect()->back()->with('success', 'تم حفظ إعدادات نهاية الخدمة');

            // التفعيلُ والحسابان غير مربوطين: يُحفظ ويُنبَّه — المسوداتُ تُنشأ
            // لكنّ اعتمادَها يُرفض حتى يُربطا.
            $settings = $this->benefits->settings();
            if ($request->boolean('eos_enabled') && ($missing = $this->missingAccountsMessage(
                [PayrollAccounts::EOS_EXPENSE => $settings['expense_account_id'], PayrollAccounts::EOS_PROVISION => $settings['provision_account_id']],
                'اعتماد أيّ مكافأة'
            ))) {
                $redirect->with('warning', 'حُفظت الإعدادات، لكن: '.$missing);
            }

            return $redirect;
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.EOS', 'settings');

            return redirect()->back()->with('error', 'تعذّر حفظ الإعدادات')->withInput();
        }
    }

    /**
     * رسالةُ الحسابات الناقصة — أو null إن رُبطت كلُّها.
     *
     * @param  array<string, int|null>  $accounts  المفتاح => المعرّف المحلول
     */
    private function missingAccountsMessage(array $accounts, string $context): ?string
    {
        $missing = array_keys(array_filter($accounts, fn ($id) => ! $id));
        if ($missing === []) {
            return null;
        }

        return implode(' ', array_map(fn ($key) => PayrollAccounts::missingMessage($key, $context), $missing));
    }

    /** @param  array<string, mixed>  $settings */
    private function postAccrual(EndOfServiceBenefit $benefit, array $settings)
    {
        $name = trim(($benefit->employee->first_name ?? '').' '.($benefit->employee->middle_name ?? ''));
        $journal = $this->createHrJournalEntry([
            'reference_number' => 'EOS-A-'.$benefit->id,
            'date' => $benefit->last_working_day ?? now(),
            'description' => "مخصص نهاية خدمة — {$name}",
            'status' => 1,
            'employee_id' => $benefit->employee_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PAYROLL,
            'linking_type' => LinkingType::SINGLE,
        ], 'last_working_day');

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $settings['expense_account_id'],
            'description' => 'مصروف مكافأة نهاية الخدمة',
            'debit' => $benefit->payable_amount,
            'credit' => 0,
            'is_debit' => true,
        ]);
        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $settings['provision_account_id'],
            'description' => 'مخصص نهاية الخدمة',
            'debit' => 0,
            'credit' => $benefit->payable_amount,
            'is_debit' => false,
        ]);

        return $journal;
    }

    /** @param  array<string, mixed>  $settings */
    private function postSettlement(EndOfServiceBenefit $benefit, array $settings, int $payableAccountId)
    {
        $name = trim(($benefit->employee->first_name ?? '').' '.($benefit->employee->middle_name ?? ''));
        $journal = $this->createHrJournalEntry([
            'reference_number' => 'EOS-S-'.$benefit->id,
            'date' => now(),
            'description' => "تسوية نهاية خدمة — {$name}",
            'status' => 1,
            'employee_id' => $benefit->employee_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PAYROLL,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $settings['provision_account_id'],
            'description' => 'إقفال مخصص نهاية الخدمة',
            'debit' => $benefit->payable_amount,
            'credit' => 0,
            'is_debit' => true,
        ]);
        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $payableAccountId,
            'description' => 'مستحق نهاية الخدمة للموظف',
            'debit' => 0,
            'credit' => $benefit->payable_amount,
            'is_debit' => false,
        ]);

        return $journal;
    }
}
