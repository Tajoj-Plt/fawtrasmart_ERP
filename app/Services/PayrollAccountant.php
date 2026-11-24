<?php

namespace App\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpensesCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\PayrollAccounts;
use App\Support\TreasuryAccessGuard;
use App\Services\Finance\TreasuryBalanceGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * سند الصرف وقيدا الاستحقاق والصرف — بعد حساب الأرقام.
 *
 * @phpstan-type Posted array{expense: Expense|null, journal: JournalEntry, accrual: JournalEntry, treasury: Account, settled_by: int}
 */
class PayrollAccountant
{
    /**
     * @return array{expense: Expense|null, journal: JournalEntry, accrual: JournalEntry, treasury: Account, settled_by: int}
     */
    public function post(
        PayrollRun $run,
        SalarySlip $slip,
        Employee $employee,
        PayrollCalculation $calc,
        Carbon $postingDate,
    ): array {
        $payableAccount = $employee->salaryPayableAccount;
        if (! $payableAccount) {
            throw new \RuntimeException(
                "الموظف \"{$employee->first_name}\" لا يملك حساب مستحقات رواتب. ".
                'افتح بطاقة الموظف ثم اضغط حفظ لإنشاء/ربط الحساب.'
            );
        }

        $treasury = $this->resolveTreasury($run, $slip, $employee);
        if (! $treasury) {
            throw new \RuntimeException('لا توجد خزينة متاحة للصرف.');
        }

        /*
            شرطُ السحب من الخزينة — كما يسأله سندُ الصرف والتحويلُ والشيك.

            كان صرفُ الراتب المخرجَ النقديّ الوحيد الذي لا يسأله: فخزينةٌ
            ضُبطت على «موظّف محدد» في شاشتها يصرف منها رواتبَ كلُّ من يملك
            `payslips.disburse`، ويقول له النظام إنّ الضبط ساري
            المفعول وهو لا يسري.

            والفحصُ **قبل** فحص الرصيد: من لا يملك السحب لا يفيده أن يُقال
            له إنّ الرصيد لا يكفي. ومسارُ الكرون بلا جلسة، فيعيد الحارس
            `null` ولا يتغيّر الصرفُ التلقائي.
        */
        if ($block = TreasuryAccessGuard::withdrawBlockReason($treasury->id)) {
            throw new \RuntimeException($block);
        }

        /*
            **كفايةُ رصيد الخزينة تحت قفلٍ على صفِّها — قبل أيِّ قيدٍ أو سند.**

            القفلُ في `PayrollDisbursementService` يُقفل صفَّ **الصرفة** وحده،
            فصرفتان متزامنتان لموظفَين مختلفَين تقرآن الرصيدَ نفسَه وتمرّان
            معاً فتنتهي الخزينةُ سالبة. والحارسُ المشترك يُقفل صفَّ حساب
            الخزينة ويحتسب الرصيدَ من الدفتر لا من العمود المخزَّن، ويرفض ما
            يجعله سالباً. وهو الحارسُ نفسُه لصرف مكافأة نهاية الخدمة وسداد
            التأمينات — انظر `App\Services\Finance\TreasuryBalanceGuard`.
        */
        $treasury = TreasuryBalanceGuard::lockAndAssert($treasury, (float) $calc->netSalary, 'صرف صافي الراتب');

        $advanceAccount = $employee->advanceAccount;
        if ($calc->totalAdvances > 0 && ! $advanceAccount) {
            throw new \RuntimeException(
                "الموظف \"{$employee->first_name}\" لديه سلف لكن لا يملك حساب سلف. أعد ربط الحساب."
            );
        }

        /*
            حصةُ الموظف من التأمينات لا تُحجَز بلا حسابٍ تُقيَّد فيه.

            كان الشرطُ `gosiEmployee > 0.009 && gosiEmployeeAccountId`: فإن
            لم يكن حسابُ الذمّة مربوطاً سقط الطرفُ الثاني، فتُدرَج الحصةُ في
            `totalDeductions` وتُنقص المصروف — ولا يُسجَّل بها التزام. وهي
            **محجوزةٌ من الموظف فعلاً**: نزلت من صافيه ولم تخرج من الخزينة.
            فتصير على المنشأة أموالٌ للتأمينات لا يظهر لها أثرٌ في أيِّ حساب،
            ولا يعلم بها إلا من يطابق الصافي بالإجمالي يدوياً.

            والموضعُ يعرف كيف يمنع الصرفَ حين ينقص حساب: يفعلها لحساب
            المستحقات ولحساب السلف أعلاه. فهذا ثالثُها.
        */
        /*
            **الخصمياتُ مُقيَّدةٌ قبل الراتب — لا تُقيَّد فيه ثانيةً.**

            كان قيدُ الاستحقاق يُقيّد كلَّ خصميات الموظف على حسابٍ واحد
            (`payroll_deductions`)، ويرفض الصرفَ كلَّه برسالة «لا حسابَ مربوطٌ
            لها» ما لم يُربط ذلك المفتاحُ بعينه — ولو ضُبط لكلِّ نوعٍ حسابُه.

            صارت الخصميةُ تُقيَّد **عند اعتمادها** على حساب نوعها (مدين ذمّة
            الموظف / دائن حساب النوع) — انظر `DeductionAccountingService`.
            فقيدُ الاستحقاق هنا يُقيّد الذمّةَ دائنةً **بالإجمالي**، وقيدُ
            الصرف يُدينها بـ(الإجمالي − الخصميات المقتطعة): فتنقفل الذمّةُ ولا
            تُحتسب الخصميةُ مرّتين.

            وما لم يُقيَّد بعد من الخصميات المقتطعة الآن — خصمياتٌ اعتُمدت قبل
            هذا التغيير، أو اعتمدها معالجُ الحضور وتعذّر قيدُها — يُقيَّد هنا
            قبل قيد الراتب. وتعذُّرُه (حسابُ نوعٍ غير مربوط) يرفض الصرفَ بالسبب.
        */
        /*
            بنودُ الخصم اليدوية على القسيمة تُقيَّد في قيد الصرف دائنةً على
            حساب البند نفسِه، وإلا على حساب «خصميات الموظفين» العام — ولا
            احتياطَ مكتوباً في الشيفرة. فإن لم يُعرف لها حسابٌ رُفض الصرفُ قبل
            أيِّ قيد، بدل أن يخرج صافيها بلا طرفٍ دائنٍ يقابله.
        */
        $slipDeductionLines = [];
        foreach ($calc->slipDeductions as $entry) {
            $accountId = $entry['account_id'] ?: AccountingSettings::id(DeductionAccountingService::GENERAL_KEY);
            if (! $accountId || ! Account::query()->whereKey($accountId)->exists()) {
                throw new \RuntimeException(
                    'بند الخصم «'.$entry['item']->name.'» على قسيمة الموظف "'.$employee->first_name.
                    '" بلا حسابٍ مربوط، ولا حسابَ عامّاً لخصميات الموظفين. اربط حساباً للبند في «بنود الرواتب» '.
                    'أو «'.PayrollAccounts::label(PayrollAccounts::DEDUCTIONS).'» في '.PayrollAccounts::SCREEN.'.'
                );
            }
            $slipDeductionLines[] = ['account_id' => (int) $accountId, 'amount' => (float) $entry['amount'], 'name' => $entry['item']->name];
        }

        /*
            **المكافآتُ النقدية من «كتالوج المكافآت» ليست راتباً.**

            سطرُها على القسيمة (`origin = reward_redemption`) يدخل الإجماليَّ
            والصافي كأيِّ استحقاق، لكنّ مصروفَه على حساب «مكافآت نقدية مستبدلة»
            لا «رواتب الموظفين» — فيُفصل من مدين قيد الاستحقاق. وبلا حسابٍ
            مربوطٍ لا يُصرف: فالسطرُ يحمل حسابه منذ الاعتماد، ولا احتياطَ مكتوباً.
        */
        $rewardLines = app(Rewards\EmployeeRewardFulfillment::class)->accrualLines($calc->additions);
        foreach ($rewardLines as $line) {
            if (! $line['account_id'] || ! Account::query()->whereKey($line['account_id'])->exists()) {
                throw new \RuntimeException(
                    'سطر المكافأة «'.$line['name'].'» على قسيمة الموظف "'.$employee->first_name.
                    '": '.PayrollAccounts::missingMessage(PayrollAccounts::REWARDS_EXPENSE, 'إعادة الصرف')
                );
            }
        }
        $rewardTotal = round(array_sum(array_column($rewardLines, 'amount')), 2);

        $deductionPoster = app(DeductionAccountingService::class);
        foreach ($calc->deductionPlan['items'] ?? [] as $entry) {
            if ((float) $entry['apply'] > 0.009) {
                $deductionPoster->ensurePosted($entry['deduction']);
            }
        }

        if ($calc->gosiEmployee > 0.009 && ! $calc->gosiEmployeeAccountId) {
            throw new \RuntimeException(
                'تُحجَز حصةُ الموظف "'.$employee->first_name.'" من التأمينات ('.
                number_format($calc->gosiEmployee, 2).') ولا التزامَ يقابلها: '.
                PayrollAccounts::missingMessage(PayrollAccounts::GOSI_EMPLOYEE_PAYABLE).
                ' أو أوقف التأمينات من إعداداتها.'
            );
        }

        /*
            حصةُ المنشأة كانت تُتخطّى بصمتٍ إن غاب أحدُ حسابَيها — فيُصرف
            الراتبُ ولا تُقيَّد تكلفةُ التأمينات ولا التزامُها. الآن تُرفض
            الصرفةُ قبل أيِّ قيدٍ باسم الحساب الناقص.
        */
        if ($calc->gosiEmployer > 0.009) {
            foreach (['employer_payable_account_id' => PayrollAccounts::GOSI_EMPLOYER_PAYABLE, 'expense_account_id' => PayrollAccounts::GOSI_EXPENSE] as $field => $key) {
                if (empty($calc->statutory[$field])) {
                    throw new \RuntimeException(
                        'حصةُ المنشأة من تأمينات الموظف "'.$employee->first_name.'" ('.
                        number_format($calc->gosiEmployer, 2).') لا تُقيَّد: '.PayrollAccounts::missingMessage($key)
                    );
                }
            }
        }

        $settledBy = Auth::id() ?: User::min('id');
        $employeeName = trim(($employee->first_name ?? '').' '.($employee->middle_name ?? ''));

        // من «إعدادات الحسابات المحاسبية» وحدها — لا ثابتَ احتياطيّاً يُرحِّل إلى حسابٍ لم يختره المحاسب
        $salaryExpenseAccountId = PayrollAccounts::require(PayrollAccounts::SALARY_EXPENSE, 'الصرف');

        /*
            لا يُرحَّل مصروفُ الراتب إلى جذر مجموعة الرواتب.

            «الرواتب والأجور» أبٌ تحته عشرةُ أبناء — الأجورُ والبدلاتُ
            والحوافزُ والتأميناتُ ومكافأةُ نهاية الخدمة والعمولاتُ
            والخصميات. فترحيلُ سطر الراتب إليه يخلطه بها جميعاً، ويترك
            «رواتب الموظفين» — موضعَه من الشجرة — بصفر، فلا يجده من يفتح
            الدليلَ باحثاً عنه.

            وقد وقع ذلك فعلاً: هجرةُ الإعداد تختار قيمتَها الافتراضية
            بالبحث في `accounts`، والهجراتُ تسبق السيدرات فالجدولُ فارغٌ
            عندها — فيقع الاختيارُ على الجذر المكتوب نصّاً في ملاذها
            الأخير، في كلّ تركيبٍ جديد.

            والشرطُ على الجذر وحده لا على «كلّ حسابٍ له أبناء»: حسابُ
            «رواتب الموظفين» نفسُه له ابنٌ في الشجرة المزروعة، فمنعُ كلِّ
            ذي أبناءٍ يرفض افتراضَ النظام نفسِه.
        */
        $salariesRootId = AccountingSettings::id(PayrollAccounts::SALARIES_PARENT);
        if ($salariesRootId && (int) $salaryExpenseAccountId === (int) $salariesRootId) {
            throw new \RuntimeException(
                'حساب مصروف الرواتب المضبوط هو «الرواتب والأجور» — وهو جذرُ المجموعة لا حسابُ الرواتب. '.
                'اختر «رواتب الموظفين» أو حساباً فرعياً تحته في '.PayrollAccounts::SCREEN.'.'
            );
        }

        $expensesCategoryId = $this->resolveOrCreateCategoryForAccount($salaryExpenseAccountId);

        /*
            سندُ الصرف يُنشأ حين يخرج نقدٌ فعلاً.

            راتبٌ استهلكته الخصمياتُ والسلفُ كلَّه صافيه صفر. وكان يُنشأ له
            سندُ صرفٍ بـ`0.00`: يظهر في شاشة المصروفات بلا مبلغ، ويحرق رقماً
            من تسلسل المستندات، ويدخل تقاريرَ المصروفات صفّاً لا يصف حركة.
            والقيدُ وحده يكفي لإثبات التسوية.
        */
        $expense = $calc->netSalary <= 0.009 ? null : Expense::create([
            'code' => Expense::generateCode(),
            'amount' => $calc->netSalary,
            'description' => "صرف راتب {$employeeName} للفترة ".$run->period_from->toDateString().' → '.$run->period_to->toDateString(),
            'date' => $postingDate->toDateString(),
            'created_by' => $settledBy,
            'treasury_id' => $treasury->id,
            'account_id' => $payableAccount->id,
            'expenses_category_id' => $expensesCategoryId,
            'is_recurring' => 0,
            'tax1_amount' => 0,
            'tax2_amount' => 0,
        ]);

        // الحسابُ مضمونٌ بالحارس أعلاه، فالشرطُ على المبلغ وحده
        $gosiWithheldSeparately = $calc->gosiEmployee > 0.009;

        /*
            ما يُدان من ذمّة الموظف عند الصرف: الإجماليُّ ناقصَ الخصميات
            المقتطعة — فتلك أُدينت بها الذمّةُ في قيد اعتمادها. ويُحتسب طرحاً
            فيتوازن القيد: صافٍ + سلف + تأمينات = إجمالي − خصميات.
        */
        $payableDebitOnPayment = round($calc->totalEarnings - $calc->otherDeductions, 2);
        // = صافٍ + سلف + تأمينات + بنودُ خصم القسيمة — الأخيرةُ أسطرٌ دائنة في قيد الصرف أدناه

        $accrualDesc = "استحقاق راتب {$employeeName} - ".$run->period_from->format('Y-m');
        if ($calc->otherDeductions > 0.009) {
            $accrualDesc .= ' (الخصميات '.number_format($calc->otherDeductions, 2).' مُقيَّدةٌ عند اعتمادها وتُقتطع عند الصرف)';
        }

        /*
            العملة والفرع — يُمرَّران فارغَين عمداً حين لا قيمة لهما،
            فيملؤهما الموديل بضماناته البنيوية القائمة:

            • **العملة** ← `HasDocumentCurrency` تملأ الفارغ بالعملة
              الافتراضية من شاشة «العملات». وكانت الشيفرة تكتب
              `$slip->currency ?? 'SAR'` — و`??` لا تعمل إلا على `null`،
              فقسيمةٌ بلا عملة تُمرِّر `'SAR'` **نصّاً**، وهي قيمةٌ غير
              فارغة تُبطل حارسَ الموديل.

            • **الفرع** ← فارغٌ عمداً: `StampsOwnerContext` يختم فرعَ مَن يُرحِّل،
              كسائر مستندات النظام (السلف ونهاية الخدمة والفواتير). كان يُمرَّر
              فرعُ الموظف، فقيدُ موظفٍ في «فرع المدينة» يصرفه محاسبٌ في «الفرع
              الرئيسي» يُختم بفرعٍ لا يراه المحاسب — فيختفي من «القيود» عنده
              وهو الذي أنشأه (2026-09-24).
        */
        $entryCurrency = $slip->currency ?: null;
        $entryBranchId = null;

        /*
            **تاريخُ الاستحقاق نهايةُ فترته لا يومُ الدفع.**

            كان القيدان — الاستحقاقُ والصرف — يُرحَّلان بـ`now()` معاً. فرواتبُ
            سبتمبر المصروفةُ في ٥ أكتوبر تُحمَّل على أكتوبر، وتخرج تكلفةُ
            العمالة من الشهر الذي اكتُسبت فيه. والأدهى أنّ قيدَ الاستحقاق
            يُنشأ ويُقفَل في اللحظة نفسها: فلا يحمل حسابُ «مستحقات الرواتب»
            رصيداً في نهاية أيِّ فترة — وذلك نصفُ الغرض من وجوده.

            فصار الاستحقاقُ بتاريخ `period_to`، والصرفُ بتاريخ الدفع. وبينهما
            تحمل المستحقاتُ رصيدَها كما يجب.

            **والفترةُ المقفلة:** لا يُرحَّل فيها شيء. فإن أُقفلت فترةُ
            الاستحقاق قبل الصرف — وهو واردٌ في منشأةٍ تُقفل شهرها ثمّ تصرف —
            يعود القيدُ إلى تاريخ الدفع ويُقال ذلك في وصفه، بدل أن يُرفض
            الصرفُ كلُّه أو يُرحَّل في فترةٍ صدرت عنها القوائم.
        */
        $accrualDate = $run->period_to instanceof Carbon
            ? $run->period_to->copy()
            : Carbon::parse((string) $run->period_to);

        if ($accrualDate->gt($postingDate) || AccountingPeriodGuard::blockReason($accrualDate) !== null) {
            $accrualDate = $postingDate->copy();
            $accrualDesc .= ' — رُحِّل بتاريخ الصرف: فترةُ الاستحقاق مقفلة أو لم تنتهِ بعد';
        }

        $accrualJournal = JournalEntry::create([
            'reference_number' => 'SAL-ACC-'.$run->id,
            'reference_id' => $run->id,
            'date' => $accrualDate->toDateString(),
            'description' => $accrualDesc,
            'status' => 1,
            'currency' => $entryCurrency,
            'branch_id' => $entryBranchId,
            'employee_id' => $employee->id,
            'salary_id' => $slip->id,
            'created_by_employee' => $settledBy,
            'reference_type' => ReferenceType::EXPENSES,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // الإجماليُّ لا الصافي: تكلفةُ العمالة ما استُحقّ لا ما تبقّى بعد الخصم —
        // ناقصاً المكافآت، فلها حسابُها أدناه
        $salaryExpenseDebit = round($calc->totalEarnings - $rewardTotal, 2);
        if ($salaryExpenseDebit > 0.009 || $rewardTotal <= 0.009) {
            JournalEntryDetail::create([
                'journal_entry_id' => $accrualJournal->id,
                'account_id' => $salaryExpenseAccountId,
                'description' => "مصروف راتب {$employeeName} - إجمالي الاستحقاق",
                'debit' => $salaryExpenseDebit,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }
        foreach ($rewardLines as $line) {
            JournalEntryDetail::create([
                'journal_entry_id' => $accrualJournal->id,
                'account_id' => $line['account_id'],
                'description' => "{$line['name']} - {$employeeName}",
                'debit' => $line['amount'],
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        JournalEntryDetail::create([
            'journal_entry_id' => $accrualJournal->id,
            'account_id' => $payableAccount->id,
            'description' => "مستحقات راتب {$employeeName} - إجمالي",
            'debit' => 0,
            'credit' => $calc->totalEarnings,
            'is_debit' => false,
        ]);

        $journal = JournalEntry::create([
            'reference_number' => 'SAL-PAY-'.$run->id,
            /*
                يبقى فارغاً بلا سند: `ExpensesController::findExpenseJournalEntry()`
                يطابق `reference_type` مع `reference_id`، فمعرّفٌ مستعارٌ من
                جدولٍ آخر قد يصادف سنداً قائماً فيُعكس قيدُ راتبٍ مكان سواه.
            */
            'reference_id' => $expense?->id,
            'date' => $postingDate->toDateString(),
            'description' => "صرف راتب {$employeeName} - ".$run->period_from->toDateString().' → '.$run->period_to->toDateString(),
            'status' => 1,
            'currency' => $entryCurrency,
            'branch_id' => $entryBranchId,
            'employee_id' => $employee->id,
            'salary_id' => $slip->id,
            'created_by_employee' => $settledBy,
            'reference_type' => ReferenceType::EXPENSES,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $payableAccount->id,
            'description' => "صرف مستحقات راتب {$employeeName}",
            'debit' => $payableDebitOnPayment,
            'credit' => 0,
            'is_debit' => true,
        ]);

        /*
            سطرُ الخزينة يُكتب حين يخرج نقدٌ فعلاً.

            راتبٌ استهلكته الخصمياتُ والسلفُ كلَّه صافيه صفر — ولا نقدَ
            يخرج. وكان يُكتب له سطرٌ بـ`0.00` على الخزينة: لا يغيّر رصيداً
            ويُثقل كشفَ الحساب بحركةٍ لا تصف شيئاً، فيقرأ المحاسبُ صفّاً
            يظنّه صرفاً حتى يتبيّن مبلغَه.
        */
        if ($calc->netSalary > 0.009) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $treasury->id,
                'description' => "صرف راتب صافي من {$treasury->name} لـ {$employeeName}",
                'debit' => 0,
                'credit' => $calc->netSalary,
                'is_debit' => false,
            ]);
        }

        if ($calc->totalAdvances > 0 && $advanceAccount) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $advanceAccount->id,
                'description' => "تسوية سلف من راتب {$employeeName} - {$advanceAccount->name}",
                'debit' => 0,
                'credit' => $calc->totalAdvances,
                'is_debit' => false,
            ]);
        }

        foreach ($slipDeductionLines as $line) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $line['account_id'],
                'description' => "بند خصم «{$line['name']}» من راتب {$employeeName}",
                'debit' => 0,
                'credit' => $line['amount'],
                'is_debit' => false,
            ]);
        }

        if ($gosiWithheldSeparately) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journal->id,
                'account_id' => (int) $calc->gosiEmployeeAccountId,
                'description' => "تأمينات اجتماعية - حصة {$employeeName}",
                'debit' => 0,
                'credit' => $calc->gosiEmployee,
                'is_debit' => false,
            ]);
        }

        $gosiEmployerAccountId = $calc->statutory['employer_payable_account_id'] ?? null;
        $gosiExpenseAccountId = $calc->statutory['expense_account_id'] ?? null;
        if ($calc->gosiEmployer > 0.009 && $gosiEmployerAccountId && $gosiExpenseAccountId) {
            $employerJournal = JournalEntry::create([
                'reference_number' => 'SAL-GOSI-'.$run->id,
                'reference_id' => $run->id,
                // حصةُ المنشأة استحقاقٌ كذلك، فتتبع تاريخَ الاستحقاق لا الدفع
                'date' => $accrualDate->toDateString(),
                'description' => "تأمينات اجتماعية - حصة المنشأة عن {$employeeName}",
                'status' => 1,
                'currency' => $entryCurrency,
                'branch_id' => $entryBranchId,
                'employee_id' => $employee->id,
                'salary_id' => $slip->id,
                'created_by_employee' => $settledBy,
                'reference_type' => ReferenceType::EXPENSES,
                'linking_type' => LinkingType::SINGLE,
            ]);
            JournalEntryDetail::create([
                'journal_entry_id' => $employerJournal->id,
                'account_id' => (int) $gosiExpenseAccountId,
                'description' => "مصروف تأمينات اجتماعية - حصة المنشأة عن {$employeeName}",
                'debit' => $calc->gosiEmployer,
                'credit' => 0,
                'is_debit' => true,
            ]);
            JournalEntryDetail::create([
                'journal_entry_id' => $employerJournal->id,
                'account_id' => (int) $gosiEmployerAccountId,
                'description' => "تأمينات مستحقة - حصة المنشأة عن {$employeeName}",
                'debit' => 0,
                'credit' => $calc->gosiEmployer,
                'is_debit' => false,
            ]);
        }

        return [
            // `null` حين لا نقدَ يخرج — انظر إنشاء السند أعلاه
            'expense' => $expense,
            'journal' => $journal,
            'accrual' => $accrualJournal,
            'treasury' => $treasury,
            'settled_by' => (int) $settledBy,
        ];
    }

    public function resolveTreasury(PayrollRun $run, SalarySlip $slip, Employee $employee): ?Account
    {
        if ($run->treasury_id) {
            $t = Account::find($run->treasury_id);
            if ($t) {
                return $t;
            }
        }
        if ($slip->treasury_id) {
            $t = Account::find($slip->treasury_id);
            if ($t) {
                return $t;
            }
        }

        $te = TreasuryEmployee::where('employee_id', $employee->id)->first();
        if ($te && $te->treasury_id) {
            $t = Account::find($te->treasury_id);
            if ($t) {
                return $t;
            }
        }

        /*
            الاحتياطُ الأخير: مفتاحُ «الخزينة الرئيسية» من شاشة «الحسابات
            المحاسبية»، ثمّ الثابتُ المزروع.

            كان بحثاً بالاسم العربيّ حرفياً — `where('name', 'الخزينة
            الرئيسية')`. فمنشأةٌ سمّت خزينتها غير ذلك، أو تعمل بالإنجليزية،
            أو أعادت تسميةَ خزينتها الرئيسية، لا يجد لها هذا السطر خزينةً
            فيرمي «لا توجد خزينة متاحة للصرف» ويتوقّف صرفُ الرواتب كلُّه —
            بينما الخزينةُ قائمةٌ ومربوطةٌ في الإعدادات.

            وهو العطبُ نفسه الذي نُقل عنه `SalesPaymentController` إلى هذا
            المفتاح؛ ومسارُ الرواتب كان آخرَ من بقي عليه.
        */
        // بلا ثابتٍ احتياطيّ: غيرُ المربوط يُرفض برسالة المتّصل («لا توجد خزينة متاحة للصرف»)
        return AccountingSettings::account('main_treasury');
    }

    /**
     * تصنيفُ المصروف المقابل لحساب — يشاركه صرفُ مكافأة نهاية الخدمة.
     *
     * فسندُ المكافأة يظهر في شاشة المصروفات مصنَّفاً كسند الراتب، لا بلا
     * تصنيفٍ تحت «—».
     */
    public function resolveOrCreateCategoryForAccount($accountId): ?int
    {
        if (empty($accountId)) {
            return null;
        }

        $account = Account::find($accountId);
        if (! $account || empty($account->name)) {
            return null;
        }

        $name = trim($account->name);

        $exact = ExpensesCategory::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first();
        if ($exact) {
            return (int) $exact->id;
        }

        $partial = ExpensesCategory::where('name', 'like', '%'.$name.'%')->first();
        if ($partial) {
            return (int) $partial->id;
        }

        $created = ExpensesCategory::create(['name' => $name]);

        return (int) $created->id;
    }
}
