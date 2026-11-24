<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SocialInsuranceRemittance;
use App\Services\PayrollAccountant;
use App\Services\SocialInsuranceService;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\PayrollAccounts;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;
use Modules\HR\Http\Controllers\Concerns\PostsHrJournalEntries;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * التأمينات الاجتماعية: الإعدادات، وشاشةُ السداد، وكشفُ الاشتراكات.
 *
 * الصلاحيات (مجموعة `insurance.*`) — كلُّ فعلٍ بصلاحيته:
 *
 *   `insurance.index`    — شاشةُ السداد: رصيدا الذمّتين وموقفُ الأشهر وسجلُّ الدفعات
 *   `insurance.show`     — كشفُ الاشتراكات لكلّ موظف وتصديرُه (مقيَّدٌ بنطاق الموظفين)
 *   `insurance.pay`      — إخراجُ النقد من الخزينة سداداً، ونموذجُه
 *   `insurance.settings` — النسبُ والسقفُ والحسابات والخضوع (تغيّر كلَّ صرفةٍ بعدها)
 *
 * والشاشةُ واحدة (`remittances`) تُفتح بأيٍّ من الأوليين، وكلُّ قسمٍ فيها
 * يظهر لصاحب صلاحيته. وأرقامُ المنشأة (الذمّتان، موقفُ الأشهر، الدفعات،
 * والسدادُ نفسُه) لا تُجزّأ على الموظفين — فلا يراها ولا يُسدِّدها إلا
 * من نطاقُه كلُّ الموظفين؛ ومن نطاقُه أضيق يرى كشفَ موظفيه وحدهم.
 *
 * والخطةُ هي مسيرُ الرواتب — لا مفتاح باقة جديد.
 */
class SocialInsuranceSettingsController extends Controller
{
    use ExportsHrReports;
    use PostsHrJournalEntries;
    use ScopesToAccessibleEmployees;

    /** مفاتيحُ «إعدادات الحسابات المحاسبية» الثلاثة للتأمينات. */
    private const ACCOUNT_KEYS = [
        PayrollAccounts::GOSI_EMPLOYEE_PAYABLE,
        PayrollAccounts::GOSI_EMPLOYER_PAYABLE,
        PayrollAccounts::GOSI_EXPENSE,
    ];

    public function __construct()
    {
        $this->middleware('permission.role:insurance.settings', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:insurance.index|insurance.show', ['only' => ['remittances']]);
        $this->middleware('permission.role:insurance.pay', ['only' => ['remit']]);
    }

    public function edit(SocialInsuranceService $insurance)
    {
        $insurableItems = Schema::hasColumn('salary_items', 'is_insurable')
            ? SalaryItem::query()->catalog()->where('is_insurable', true)->orderBy('name')->get(['id', 'name', 'status'])
            : collect();

        $settings = $insurance->settings();

        // الحساباتُ الثلاثة للعرض وحده — تُربط من «إعدادات الحسابات المحاسبية»
        return view('hr::salaries.sitting.social_insurance', [
            'gosiSettings' => $settings,
            'accountRows' => PayrollAccounts::rows(self::ACCOUNT_KEYS),
            'insurableItems' => $insurableItems,
            'deductionName' => $settings['component_type_id'] && Schema::hasTable('salary_component_types')
                ? (SalaryComponentType::query()->whereKey($settings['component_type_id'])->value('name') ?: 'تأمينات اجتماعية')
                : 'تأمينات اجتماعية',
        ]);
    }

    /**
     * شاشةُ السداد: ما تراكم في ذمّتَي التأمينات، وما سُدِّد منه، وكشفُ
     * الاشتراكات لكلّ موظفٍ وشهر من الرواتب المصروفة فعلاً.
     */
    public function remittances(Request $request, SocialInsuranceService $insurance)
    {
        $settings = $insurance->settings();
        [$employeeAccount, $employerAccount] = $this->liabilityAccounts($settings);

        $firmWide = $this->hasFirmWideScope();
        $showOverview = hasPermission('insurance.index') && $firmWide;
        $showStatement = hasPermission('insurance.show');
        $canRemit = hasPermission('insurance.pay') && $firmWide;

        if (! $showOverview && ! $showStatement) {
            abort(403, 'شاشةُ السداد أرقامُ المنشأة كلِّها، ونطاقُك لا يشمل كلَّ الموظفين — اطلب صلاحية كشف الاشتراكات لترى موظفيك.');
        }

        $filters = $this->statementFilters($request);
        $statement = $this->statementQuery($filters);

        if ($showStatement && $file = $this->exportIfRequested(
            $request,
            'كشف اشتراكات التأمينات الاجتماعية',
            ['الموظف', 'الرقم الوظيفي', 'الفترة', 'رقم الصرف', 'تاريخ الصرف', 'حصة الموظف', 'حصة المنشأة', 'الإجمالي'],
            fn () => (clone $statement)->orderByDesc('period_month')->orderBy('employee_name')->get()->map(fn ($row) => [
                $row->employee_name,
                $row->employee_code ?: '—',
                $row->period_month,
                $row->run_code ?: ('#'.$row->run_id),
                $row->disbursed_at ? substr((string) $row->disbursed_at, 0, 10) : '—',
                (float) $row->employee_share,
                (float) $row->employer_share,
                round((float) $row->employee_share + (float) $row->employer_share, 2),
            ])->all(),
            'gosi_contributions'
        )) {
            return $file;
        }

        $statementTotals = $showStatement
            ? DB::query()
                ->fromSub((clone $statement), 's')
                ->selectRaw('COUNT(*) as runs, COUNT(DISTINCT employee_id) as employees, COALESCE(SUM(employee_share),0) as employee_share, COALESCE(SUM(employer_share),0) as employer_share')
                ->first()
            : null;

        $statementRows = $showStatement
            ? (clone $statement)
                ->orderByDesc('period_month')
                ->orderBy('employee_name')
                ->paginate((int) $request->input('per_page', 25) ?: 25, ['*'], 'rows_page')
                ->withQueryString()
            : null;

        return view('hr::salaries.sitting.social_insurance_remittances', [
            'gosiSettings' => $settings,
            'showOverview' => $showOverview,
            'showStatement' => $showStatement,
            'canRemit' => $canRemit,
            'employeeAccount' => $showOverview ? $employeeAccount : null,
            'employerAccount' => $showOverview ? $employerAccount : null,
            'employeeOutstanding' => $showOverview && $employeeAccount ? (float) $employeeAccount->balance : 0.0,
            'employerOutstanding' => $showOverview && $employerAccount ? (float) $employerAccount->balance : 0.0,
            'treasuries' => $canRemit ? $this->disbursableTreasuries() : collect(),
            'remittances' => $showOverview
                ? SocialInsuranceRemittance::query()
                    ->with(['treasury:id,name,code', 'payer:id,name', 'journalEntry:id,reference_number', 'expense:id,code'])
                    ->orderByDesc('id')
                    ->paginate(10, ['*'], 'page')
                    ->withQueryString()
                : null,
            'months' => $showOverview ? $this->monthlyPosition() : collect(),
            'statementRows' => $statementRows,
            'statementTotals' => $statementTotals,
            'filters' => $filters,
            'employees' => $showStatement ? $this->scopedEmployeeOptions([]) : collect(),
        ]);
    }

    /**
     * هل نطاقُ المستخدم كلُّ الموظفين؟ (`data_scope_all` — كما في
     * {@see ScopesToAccessibleEmployees}).
     *
     * ذمّتا التأمينات حسابان واحدان للمنشأة، والسدادُ دفعةٌ واحدة للجهة:
     * لا تتجزّأ على موظفين، فلا تُعرض ولا تُسدَّد بنطاقٍ أضيق.
     */
    private function hasFirmWideScope(): bool
    {
        return $this->accessibleEmployeeIds() === null;
    }

    /**
     * سدادُ ما حُجز نقداً.
     *
     * صرفُ الراتب يحجز حصةَ الموظف ويُقيّدها دائنةً في ذمّتها، ويُقيّد حصةَ
     * المنشأة مصروفاً والتزاماً. والسدادُ يُقفلهما:
     *
     *     مدين  تأمينات مستحقة — حصة الموظف
     *     مدين  تأمينات مستحقة — حصة المنشأة
     *         دائن  الخزينة
     *
     * والضوابطُ ضوابطُ صرف الراتب نفسُها، فالنقدُ الخارجُ واحد: شرطُ السحب،
     * وقفلُ صفِّ الخزينة، والرصيدُ محتسبٌ من الدفتر، وحارسُ الفترة — في
     * معاملةٍ واحدة. ولا يُسدَّد أكثرُ مما حُجز.
     */
    public function remit(Request $request, SocialInsuranceService $insurance)
    {
        if (! $this->hasFirmWideScope()) {
            abort(403, 'السدادُ يُقفل ذمّةَ المنشأة كلِّها، ونطاقُك لا يشمل كلَّ الموظفين.');
        }

        $validated = $request->validate([
            'employee_amount' => 'nullable|numeric|min:0|max:999999999',
            'employer_amount' => 'nullable|numeric|min:0|max:999999999',
            'treasury_id' => 'required|integer|exists:accounts,id',
            'period_month' => 'required|date_format:Y-m',
            'reference' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:500',
        ], [
            'treasury_id.required' => 'اختر الخزينة أو الحساب البنكي الذي يُسدَّد منه.',
            'treasury_id.exists' => 'الخزينة المختارة غير موجودة.',
            'period_month.required' => 'حدّد الشهر الذي يُسدَّد عنه — به يُطابَق السدادُ بالمحجوز في الكشف.',
            'period_month.date_format' => 'الشهر بصيغة YYYY-MM',
            'employee_amount.numeric' => 'حصة الموظف رقمٌ فقط.',
            'employer_amount.numeric' => 'حصة المنشأة رقمٌ فقط.',
        ], [
            'employee_amount' => 'حصة الموظف',
            'employer_amount' => 'حصة المنشأة',
            'treasury_id' => 'الخزينة',
            'period_month' => 'الشهر',
            'reference' => 'المرجع',
            'notes' => 'الملاحظات',
        ]);

        $employeeAmount = round((float) ($validated['employee_amount'] ?? 0), 2);
        $employerAmount = round((float) ($validated['employer_amount'] ?? 0), 2);
        $total = round($employeeAmount + $employerAmount, 2);

        if ($total < 0.01) {
            return back()->withInput()
                ->withErrors(['employee_amount' => 'أدخل مبلغاً للسداد في إحدى الحصّتين.'])
                ->with('error', 'أدخل مبلغاً للسداد.');
        }

        $settings = $insurance->settings();
        [$employeeAccount, $employerAccount] = $this->liabilityAccounts($settings);

        if ($employeeAmount > 0.009 && ! $employeeAccount) {
            return back()->withInput()->with('error', PayrollAccounts::missingMessage(PayrollAccounts::GOSI_EMPLOYEE_PAYABLE, 'السداد'));
        }
        if ($employerAmount > 0.009 && ! $employerAccount) {
            return back()->withInput()->with('error', PayrollAccounts::missingMessage(PayrollAccounts::GOSI_EMPLOYER_PAYABLE, 'السداد'));
        }

        /*
            لا يُسدَّد أكثرُ مما حُجز: الذمّةُ تُقفل بما فيها، وما زاد عليها
            يقلبها مدينةً — أي دَيناً على الجهة لا لها.
        */
        if ($employeeAccount && $employeeAmount > (float) $employeeAccount->balance + 0.009) {
            return back()->withInput()
                ->withErrors(['employee_amount' => 'أكبر من المستحق ('.number_format((float) $employeeAccount->balance, 2).').'])
                ->with('error', 'حصة الموظف المستحقة ('.number_format((float) $employeeAccount->balance, 2).') أقلُّ من المبلغ المُدخَل.');
        }
        if ($employerAccount && $employerAmount > (float) $employerAccount->balance + 0.009) {
            return back()->withInput()
                ->withErrors(['employer_amount' => 'أكبر من المستحق ('.number_format((float) $employerAccount->balance, 2).').'])
                ->with('error', 'حصة المنشأة المستحقة ('.number_format((float) $employerAccount->balance, 2).') أقلُّ من المبلغ المُدخَل.');
        }

        // الخزينةُ من القائمة المعروضة نفسِها — لا خزينةَ احتياطية تُخمَّن
        $treasury = $this->disbursableTreasuries()->firstWhere('id', (int) $validated['treasury_id']);

        if (! $treasury) {
            return back()->withInput()
                ->withErrors(['treasury_id' => 'الخزينة ليست من الخزائن أو الحسابات البنكية المتاحة لك للصرف.'])
                ->with('error', 'لا توجد خزينة صالحة للصرف.');
        }

        if ($block = TreasuryAccessGuard::withdrawBlockReason($treasury->id)) {
            return back()->withInput()->withErrors(['treasury_id' => $block])->with('error', $block);
        }

        try {
            $remittance = DB::transaction(function () use (
                $treasury, $employeeAccount, $employerAccount,
                $employeeAmount, $employerAmount, $total, $validated
            ) {
                // الحارسُ المشترك لكلِّ مخرجٍ نقدي: قفلُ صفِّ الخزينة، والرصيدُ من الدفتر، ولا سالب
                $locked = \App\Services\Finance\TreasuryBalanceGuard::lockAndAssert($treasury, $total, 'سداد التأمينات');

                $period = $validated['period_month'];
                $label = 'سداد تأمينات اجتماعية عن '.$period;

                $journal = $this->createHrJournalEntry([
                    'reference_number' => 'GOSI-REM-'.now()->format('YmdHis'),
                    'date' => now(),
                    'description' => $label,
                    'status' => 1,
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::PAYROLL,
                    'linking_type' => LinkingType::SINGLE,
                ]);

                if ($employeeAmount > 0.009) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $employeeAccount->id,
                        'description' => 'إقفال تأمينات مستحقة — حصة الموظف',
                        'debit' => $employeeAmount,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);
                }

                if ($employerAmount > 0.009) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $employerAccount->id,
                        'description' => 'إقفال تأمينات مستحقة — حصة المنشأة',
                        'debit' => $employerAmount,
                        'credit' => 0,
                        'is_debit' => true,
                    ]);
                }

                JournalEntryDetail::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $locked->id,
                    'description' => $label.' من '.$locked->name,
                    'debit' => 0,
                    'credit' => $total,
                    'is_debit' => false,
                ]);

                $expense = Expense::create([
                    'code' => Expense::generateCode(),
                    'amount' => $total,
                    'description' => $label,
                    'date' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'treasury_id' => $locked->id,
                    'account_id' => $employeeAccount?->id ?? $employerAccount?->id,
                    'expenses_category_id' => app(PayrollAccountant::class)
                        ->resolveOrCreateCategoryForAccount($employerAccount?->id ?? $employeeAccount?->id),
                    'is_recurring' => 0,
                    'tax1_amount' => 0,
                    'tax2_amount' => 0,
                ]);

                return SocialInsuranceRemittance::create([
                    'period_month' => $period,
                    'employee_amount' => $employeeAmount,
                    'employer_amount' => $employerAmount,
                    'total_amount' => $total,
                    'treasury_id' => $locked->id,
                    'expense_id' => $expense->id,
                    'journal_entry_id' => $journal->id,
                    'branch_id' => current_branch_id(),
                    'reference' => $validated['reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'paid_at' => now(),
                    'paid_by' => Auth::id(),
                ]);
            });

            ModelsLog::create([
                'type' => 'payroll_log',
                'type_id' => $remittance->id,
                'type_log' => 'log',
                'description' => 'سُدِّدت تأمينات اجتماعية عن '.$remittance->period_month.' بمبلغ '.number_format($total, 2),
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('hr.gosi.remittances')
                ->with('success', 'سُدِّدت التأمينات عن '.$remittance->period_month.' وأُنشئ سند الصرف والقيد المحاسبي.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.GOSI', 'remit');

            return back()->withInput()->with('error', 'فشل السداد: '.$e->getMessage());
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'employee_rate' => 'required|numeric|min:0|max:100',
            'employer_rate' => 'required|numeric|min:0|max:100',
            'wage_cap' => 'nullable|numeric|min:0',
            'resident_employee_rate' => 'nullable|numeric|min:0|max:100',
            'resident_employer_rate' => 'nullable|numeric|min:0|max:100',
        ], [
            'employee_rate.required' => 'نسبة حصة الموظف مطلوبة.',
            'employer_rate.required' => 'نسبة حصة المنشأة مطلوبة.',
            'employee_rate.max' => 'النسبة لا تتجاوز 100%.',
            'employer_rate.max' => 'النسبة لا تتجاوز 100%.',
        ], [
            'employee_rate' => 'نسبة الموظف',
            'employer_rate' => 'نسبة المنشأة',
            'wage_cap' => 'سقف الأجر',
            'resident_employee_rate' => 'نسبة الموظف غير المواطن',
            'resident_employer_rate' => 'نسبة المنشأة عن غير المواطن',
        ]);

        /*
            التفعيلُ بلا حساباتٍ يوقف كلَّ صرفِ راتبٍ بعده (حارسُ
            `PayrollAccountant`) — فيُرفض **تشغيلُه** هنا بدل أن يُكتشف يومَ
            الصرف. والحساباتُ تُربط من «إعدادات الحسابات المحاسبية»، فلا حقلَ
            لها في هذا النموذج. أمّا إن كانت مفعّلةً أصلاً ثمّ فُكّ ربطُ حسابٍ
            هناك، فحفظُ النسب هنا لا يُمنع — يُحفظ ويُنبَّه.
        */
        $missing = $this->missingAccounts();
        $wasEnabled = (bool) (DB::table('social_insurance_settings')->value('enabled') ?? false);
        if ($request->boolean('enabled') && $missing !== [] && ! $wasEnabled) {
            return back()->withInput()
                ->with('error', 'لا يُفعَّل الاستقطاع قبل ربط حساباته: '.implode(' ', array_map(
                    fn ($key) => PayrollAccounts::missingMessage($key),
                    $missing
                )));
        }

        try {
            $this->saveSettings($request, $validated);

            $redirect = redirect()->back()->with('success', 'تم تحديث إعدادات التأمينات الاجتماعية');
            if ($request->boolean('enabled') && $missing !== []) {
                $redirect->with('warning', 'حُفظت الإعدادات، لكن صرفَ الرواتب سيُرفض: '.implode(' ', array_map(
                    fn ($key) => PayrollAccounts::missingMessage($key),
                    $missing
                )));
            }

            return $redirect;
        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.SocialInsuranceSettings', 'update');

            return redirect()->back()->with('error', 'حدث خطأ أثناء حفظ الإعدادات')->withInput();
        }
    }

    /** @return array<int, string> مفاتيحُ التأمينات غيرُ المربوطة */
    private function missingAccounts(): array
    {
        return array_values(array_filter(self::ACCOUNT_KEYS, fn ($key) => ! PayrollAccounts::id($key)));
    }

    /**
     * @return array{month: ?string, employee_id: ?int}
     */
    private function statementFilters(Request $request): array
    {
        $month = (string) $request->input('month', '');

        return [
            'month' => preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null,
            'employee_id' => $request->filled('employee_id') ? (int) $request->employee_id : null,
        ];
    }

    /**
     * كشفُ الاشتراكات: صفٌّ لكلّ صرفة راتبٍ فيها تأمينات.
     *
     * المصدرُ `payroll_run_items` للصرفات المصروفة فعلاً وغير المعكوسة —
     * لا القسائم: القسيمةُ اتفاقٌ مستمرّ، والصرفُ هو ما حُجز فعلاً وقُيّد.
     * حصةُ الموظف بندُ الخصم المولَّد (`origin = social_insurance`)،
     * وحصةُ المنشأة بندُ `employer`.
     *
     * والنطاقُ نطاقُ الموظفين (`ScopesToAccessibleEmployees`): من لا يرى
     * موظفاً لا يرى اشتراكَه ولا يُصدّره.
     */
    private function statementQuery(array $filters)
    {
        $gosiItemIds = DB::table('salary_items')
            ->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)
            ->select('id');

        $query = DB::table('payroll_runs as r')
            ->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->join('payroll_run_items as i', 'i.payroll_run_id', '=', 'r.id')
            ->where('r.status', PayrollRun::STATUS_DISBURSED)
            ->whereNull('r.reversed_at')
            ->where(function ($q) use ($gosiItemIds) {
                $q->where(function ($d) use ($gosiItemIds) {
                    $d->where('i.item_type', PayrollRunItem::TYPE_DEDUCTION)
                        ->where('i.source_table', 'salary_items')
                        ->whereIn('i.source_id', $gosiItemIds);
                })->orWhere('i.item_type', PayrollRunItem::TYPE_EMPLOYER);
            })
            ->groupBy('r.id', 'r.code', 'r.employee_id', 'r.period_from', 'r.disbursed_at', 'e.first_name', 'e.middle_name', 'e.employee_code')
            ->selectRaw("r.id as run_id, r.code as run_code, r.employee_id, DATE_FORMAT(r.period_from, '%Y-%m') as period_month, r.disbursed_at,
                TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.middle_name,''))) as employee_name, e.employee_code,
                SUM(CASE WHEN i.item_type = ? THEN i.amount ELSE 0 END) as employee_share,
                SUM(CASE WHEN i.item_type = ? THEN i.amount ELSE 0 END) as employer_share",
                [PayrollRunItem::TYPE_DEDUCTION, PayrollRunItem::TYPE_EMPLOYER]);

        $this->scopeAnyQueryToEmployees($query, 'r.employee_id');

        if ($filters['month']) {
            $query->whereRaw("DATE_FORMAT(r.period_from, '%Y-%m') = ?", [$filters['month']]);
        }
        if ($filters['employee_id']) {
            $query->where('r.employee_id', $filters['employee_id']);
        }

        return $query;
    }

    /**
     * موقفُ كلّ شهر: ما حُجز (من الصرفات) مقابلَ ما سُدِّد عنه.
     *
     * على مستوى المنشأة لا الموظف: الذمّتان حسابان واحدان، والسدادُ دفعةٌ
     * واحدة للجهة. فلا يُقيَّد بنطاق الموظفين — كما لا تُقيَّد أرصدةُ الذمّتين.
     *
     * @return Collection<int, object>
     */
    private function monthlyPosition(): Collection
    {
        $gosiItemIds = DB::table('salary_items')
            ->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)
            ->select('id');

        $withheld = DB::table('payroll_runs as r')
            ->join('payroll_run_items as i', 'i.payroll_run_id', '=', 'r.id')
            ->where('r.status', PayrollRun::STATUS_DISBURSED)
            ->whereNull('r.reversed_at')
            ->where(function ($q) use ($gosiItemIds) {
                $q->where(function ($d) use ($gosiItemIds) {
                    $d->where('i.item_type', PayrollRunItem::TYPE_DEDUCTION)
                        ->where('i.source_table', 'salary_items')
                        ->whereIn('i.source_id', $gosiItemIds);
                })->orWhere('i.item_type', PayrollRunItem::TYPE_EMPLOYER);
            })
            ->groupByRaw("DATE_FORMAT(r.period_from, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(r.period_from, '%Y-%m') as period_month,
                COUNT(DISTINCT r.employee_id) as employees,
                SUM(CASE WHEN i.item_type = ? THEN i.amount ELSE 0 END) as employee_share,
                SUM(CASE WHEN i.item_type = ? THEN i.amount ELSE 0 END) as employer_share",
                [PayrollRunItem::TYPE_DEDUCTION, PayrollRunItem::TYPE_EMPLOYER])
            ->get()
            ->keyBy('period_month');

        $remitted = SocialInsuranceRemittance::query()
            ->whereNotNull('period_month')
            ->groupBy('period_month')
            ->selectRaw('period_month, SUM(total_amount) as total, COUNT(*) as payments')
            ->get()
            ->keyBy('period_month');

        return $withheld->keys()->merge($remitted->keys())->unique()->sortDesc()->take(12)
            ->map(function ($month) use ($withheld, $remitted) {
                $w = $withheld->get($month);
                $due = round((float) ($w->employee_share ?? 0) + (float) ($w->employer_share ?? 0), 2);
                $paid = round((float) ($remitted->get($month)->total ?? 0), 2);

                return (object) [
                    'period_month' => $month,
                    'employees' => (int) ($w->employees ?? 0),
                    'employee_share' => (float) ($w->employee_share ?? 0),
                    'employer_share' => (float) ($w->employer_share ?? 0),
                    'due' => $due,
                    'paid' => $paid,
                    'remaining' => round($due - $paid, 2),
                    'state' => $paid < 0.01 ? 'unpaid' : ($paid + 0.009 >= $due ? 'paid' : 'partial'),
                ];
            })
            ->values();
    }

    /**
     * حسابا الذمّة — من «إعدادات الحسابات المحاسبية» (عبر `SocialInsuranceService::settings()`).
     *
     * @param  array<string, mixed>  $settings
     * @return array{0: Account|null, 1: Account|null}
     */
    private function liabilityAccounts(array $settings): array
    {
        return [
            $settings['employee_payable_account_id'] ? Account::find($settings['employee_payable_account_id']) : null,
            $settings['employer_payable_account_id'] ? Account::find($settings['employer_payable_account_id']) : null,
        ];
    }

    /**
     * الخزائنُ وحساباتُ البنوك المتاحة لهذا المستخدم للصرف.
     *
     * الأبوان من «إعدادات الحسابات المحاسبية» وحدها — لا ثوابتَ احتياطية.
     * والقائمةُ تمرّ بشرط السحب نفسِه الذي يُفحص عند الحفظ
     * (`TreasuryAccessGuard`)، فلا يُعرض خيارٌ يُرفض بعد اختياره.
     *
     * @return Collection<int, Account>
     */
    private function disbursableTreasuries()
    {
        $parents = array_values(array_filter([
            AccountingSettings::id('cash_treasuries_parent'),
            AccountingSettings::id('bank_accounts_parent'),
        ]));

        if ($parents === []) {
            return collect();
        }

        return Account::whereIn('parent_id', $parents)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->filter(fn (Account $account) => TreasuryAccessGuard::withdrawBlockReason($account->id) === null)
            ->values();
    }

    /** @param  array<string, mixed>  $validated */
    private function saveSettings(Request $request, array $validated): void
    {
        if (! Schema::hasTable('social_insurance_settings')) {
            return;
        }

        $data = [
            'enabled' => $request->boolean('enabled'),
            'employee_rate' => $validated['employee_rate'],
            'employer_rate' => $validated['employer_rate'],
            'wage_cap' => isset($validated['wage_cap']) && $validated['wage_cap'] !== ''
                ? $validated['wage_cap']
                : null,
            // أعمدةُ الحسابات الثلاثة متقاعدة — لا تُكتب؛ الربطُ في «إعدادات الحسابات المحاسبية».
            // `component_type_id` (اسمُ بند الخصم المولَّد) لم يعد يُحرَّر من
            // الشاشة منذ تقاعد كتالوج البدلات — قيمتُه المحفوظة تبقى كما هي.
            'updated_at' => now(),
        ];

        // نسبتا غير المواطن (مقيم، وزائرٌ فُرض خضوعُه) — تُحفظ متى وُجد عمودُهما
        foreach (['resident_employee_rate' => 0, 'resident_employer_rate' => 2] as $column => $default) {
            if (Schema::hasColumn('social_insurance_settings', $column)) {
                $data[$column] = isset($validated[$column]) && $validated[$column] !== '' ? $validated[$column] : $default;
            }
        }

        $existing = DB::table('social_insurance_settings')->first();
        if ($existing) {
            DB::table('social_insurance_settings')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('social_insurance_settings')->insert($data);
        }
    }
}
