<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Support\PayrollAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * استقطاع التأمينات (حصة الموظف) ومساهمة المنشأة عند صرف الراتب.
 *
 * الأجر الخاضع = الأساسي + بدلات القسيمة المصنَّفة `is_insurable`، ثم يُقصّ
 * بسقف الإعدادات إن وُجد. النسب الافتراضية (9.75% / 11.75% وسقف 45,000)
 * قيم بدء شائعة على نمط التأمينات — تُعدَّل من الشاشة وليست قانوناً في الكود.
 *
 * **من يخضع** من بطاقة الموظف (`eligibility()`): المواطن بالنسبتين أعلاه، والمقيم
 * بنسبتي غير المواطن (0% / 2% قيمُ بدء)، والزائر لا يخضع، و`gosi_subject` يُعفي أو
 * يُخضع موظفاً بعينه. وسببُ الاحتساب يُكتب على البند (`explain()`).
 *
 * حصة الموظف بند خصم مولَّد على القسيمة (`origin = social_insurance`).
 * حصة المنشأة تكلفة شركة: لا تُنقص صافي الراتب.
 */
class SocialInsuranceService
{
    /**
     * يكتب (أو يحذف) بند استقطاع التأمينات على القسيمة لفترة الـ run.
     *
     * @return array{
     *     item: SalaryItem|null,
     *     enabled: bool,
     *     employee_amount: float,
     *     employer_amount: float,
     *     wage_base: float,
     *     contributory_wage: float,
     *     employee_payable_account_id: int|null,
     *     employer_payable_account_id: int|null,
     *     expense_account_id: int|null
     * }
     */
    public function syncForRun(PayrollRun $run, SalarySlip $slip, Employee $employee): array
    {
        if (! $this->tablesReady()) {
            return $this->emptyResult();
        }

        $breakdown = $this->breakdown($slip, $run, $employee);
        $item = $this->persist($slip, $breakdown);
        $breakdown['item'] = $item;

        return $breakdown;
    }

    /**
     * @return array{
     *     item: SalaryItem|null,
     *     enabled: bool,
     *     employee_amount: float,
     *     employer_amount: float,
     *     wage_base: float,
     *     contributory_wage: float,
     *     employee_rate: float,
     *     employer_rate: float,
     *     wage_cap: float|null,
     *     name: string,
     *     component_type_id: int|null,
     *     employee_payable_account_id: int|null,
     *     employer_payable_account_id: int|null,
     *     expense_account_id: int|null
     * }
     */
    public function breakdown(SalarySlip $slip, ?PayrollRun $run = null, ?Employee $employee = null): array
    {
        $empty = $this->emptyResult();

        if (! $this->tablesReady()) {
            return $empty;
        }

        $settings = $this->settings();
        $empty['component_type_id'] = $settings['component_type_id'];
        $empty['name'] = $this->itemName($settings['component_type_id']);
        $empty['employee_rate'] = $settings['employee_rate'];
        $empty['employer_rate'] = $settings['employer_rate'];
        $empty['wage_cap'] = $settings['wage_cap'];
        $empty['employee_payable_account_id'] = $settings['employee_payable_account_id'];
        $empty['employer_payable_account_id'] = $settings['employer_payable_account_id'];
        $empty['expense_account_id'] = $settings['expense_account_id'];

        /*
            البدلاتُ الخاضعة: علمُ «خاضع للتأمينات» على البند نفسِه — يُضبط
            من شاشة «بنود الرواتب» ويورَّث إلى نسخته في القسيمة. انظر
            `SalaryItem::scopeInsurable()`.
        */
        $insurableAllowances = (float) $slip->additionItems()
            ->insurable()
            ->sum('amount');

        $wageBase = round((float) ($slip->basic_amount ?? 0) + $insurableAllowances, 2);
        $cap = $settings['wage_cap'];
        $contributory = ($cap !== null && $cap > 0) ? min($wageBase, $cap) : $wageBase;
        $contributory = round(max(0, $contributory), 2);

        $empty['wage_base'] = $wageBase;
        $empty['contributory_wage'] = $contributory;

        if (! $settings['enabled']) {
            $empty['eligibility'] = [
                'subject' => false,
                'category' => null,
                'employee_rate' => 0.0,
                'employer_rate' => 0.0,
                'reason' => 'التأمينات غير مفعَّلة في الإعدادات',
            ];
            $empty['employee_rate'] = 0.0;
            $empty['employer_rate'] = 0.0;
            $empty['explanation'] = 'غير خاضع — التأمينات غير مفعَّلة في الإعدادات';

            return $empty;
        }

        /*
            **الخضوعُ والنسبةُ من بطاقة الموظف — لا نسبةٌ واحدةٌ للجميع.**

            كانت النسبتان تُطبَّقان على كلّ موظفٍ متى فُعِّلت التأمينات:
            المقيمُ يُستقطع منه 9.75% كالمواطن، والزائرُ كذلك، ولا مفتاحَ
            يستثني أحداً. انظر `eligibility()`.
        */
        $employee = $employee ?: $slip->employee;
        $periodTo = $run?->period_to;
        $eligibility = $this->eligibility($employee, $settings, $periodTo);

        $empty['eligibility'] = $eligibility;
        $empty['employee_rate'] = $eligibility['employee_rate'];
        $empty['employer_rate'] = $eligibility['employer_rate'];
        $empty['explanation'] = $this->explain($eligibility, $contributory, $settings['wage_cap'], $wageBase);

        if (! $eligibility['subject']) {
            return $empty;
        }

        $employeeAmount = round($contributory * $eligibility['employee_rate'] / 100, 2);
        $employerAmount = round($contributory * $eligibility['employer_rate'] / 100, 2);

        return array_merge($empty, [
            'enabled' => $employeeAmount > 0.009 || $employerAmount > 0.009,
            'employee_amount' => $employeeAmount,
            'employer_amount' => $employerAmount,
        ]);
    }

    /** حالاتُ المواطنة كما في نموذج الموظف (`employees.nationality_status`) */
    public const NATIONALITY_CITIZEN = 1;

    public const NATIONALITY_RESIDENT = 2;

    public const NATIONALITY_VISITOR = 3;

    /**
     * هل يخضع الموظف للتأمينات، وبأيّ نسبتين، ولماذا.
     *
     *   • مفتاحُ البطاقة `gosi_subject = 0` ← معفى أيّاً كانت جنسيته.
     *   • تاريخُ بدء الاشتراك بعد نهاية الفترة ← لم يبدأ اشتراكُه بعد.
     *   • مواطن ← نسبتا الإعدادات (9.75% / 11.75% قيمُ بدء).
     *   • مقيم ← نسبتا غير المواطن (0% / 2% أخطار مهنية قيمُ بدء).
     *   • زائر ← غير خاضع، إلا إن فُرض عليه الخضوعُ من البطاقة فبنسبتي غير المواطن.
     *   • حالةٌ غير محددة ← يُعامَل كمواطن (السلوكُ السابق) ويُقال ذلك صراحةً.
     *
     * @param  array<string, mixed>  $settings
     * @return array{subject: bool, category: string|null, employee_rate: float, employer_rate: float, reason: string}
     */
    public function eligibility(?Employee $employee, array $settings, mixed $periodTo = null): array
    {
        $result = fn (bool $subject, ?string $category, float $er, float $mr, string $reason) => [
            'subject' => $subject,
            'category' => $category,
            'employee_rate' => $subject ? $er : 0.0,
            'employer_rate' => $subject ? $mr : 0.0,
            'reason' => $reason,
        ];

        $status = (int) ($employee?->nationality_status ?? 0);
        $flag = $employee && Schema::hasColumn('employees', 'gosi_subject') ? $employee->gosi_subject : null;
        $flag = ($flag === null || $flag === '') ? null : (int) $flag;

        $category = match ($status) {
            self::NATIONALITY_CITIZEN => 'مواطن',
            self::NATIONALITY_RESIDENT => 'مقيم',
            self::NATIONALITY_VISITOR => 'زائر',
            default => null,
        };

        if ($flag === 0) {
            return $result(false, $category, 0, 0, ($category ? $category.' — ' : '').'معفى من بطاقة الموظف');
        }

        $start = $employee && Schema::hasColumn('employees', 'gosi_start_date') ? $employee->gosi_start_date : null;
        if ($start && $periodTo) {
            $startDate = Carbon::parse((string) $start)->startOfDay();
            $end = Carbon::parse($periodTo instanceof \DateTimeInterface ? $periodTo->format('Y-m-d') : (string) $periodTo)->startOfDay();
            if ($startDate->gt($end)) {
                return $result(false, $category, 0, 0, 'لم يبدأ الاشتراك بعد (يبدأ '.$startDate->toDateString().')');
            }
        }

        $citizen = [(float) $settings['employee_rate'], (float) $settings['employer_rate']];
        $nonCitizen = [(float) ($settings['resident_employee_rate'] ?? 0), (float) ($settings['resident_employer_rate'] ?? 2)];
        $forced = $flag === 1 ? ' — خاضع بقرار من بطاقة الموظف' : '';

        return match ($status) {
            self::NATIONALITY_CITIZEN => $result(true, $category, $citizen[0], $citizen[1], 'مواطن'.$forced),
            self::NATIONALITY_RESIDENT => $result(true, $category, $nonCitizen[0], $nonCitizen[1], 'مقيم (نسبتا غير المواطن)'.$forced),
            self::NATIONALITY_VISITOR => $flag === 1
                ? $result(true, $category, $nonCitizen[0], $nonCitizen[1], 'زائر — خاضع بقرار من بطاقة الموظف بنسبتي غير المواطن')
                : $result(false, $category, 0, 0, 'زائر'),
            default => $result(true, null, $citizen[0], $citizen[1], 'حالة المواطنة غير محددة في بطاقة الموظف — عومل كمواطن'.$forced),
        };
    }

    /**
     * سطرٌ واحد يشرح سببَ الاستقطاع أو عدمه — يُعرض على القسيمة والصرفة.
     *
     * @param  array<string, mixed>  $eligibility
     */
    public function explain(array $eligibility, float $contributory, ?float $cap, float $wageBase): string
    {
        $pct = fn (float $r) => rtrim(rtrim(number_format($r, 3), '0'), '.').'%';

        if (! $eligibility['subject']) {
            return 'غير خاضع — '.$eligibility['reason'];
        }

        $capNote = ($cap && $wageBase > $cap + 0.001) ? ' (بعد السقف '.number_format($cap, 2).')' : '';
        $rates = $eligibility['employee_rate'] > 0
            ? ' × '.$pct($eligibility['employee_rate']).' على الموظف، و'.$pct($eligibility['employer_rate']).' على المنشأة'
            : ' — لا يُستقطع من الموظف، و'.$pct($eligibility['employer_rate']).' على المنشأة';

        return 'خاضع — '.$eligibility['reason'].'، الأجر الخاضع '.number_format($contributory, 2).$capNote.$rates;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $row = Schema::hasTable('social_insurance_settings')
            ? DB::table('social_insurance_settings')->first()
            : null;

        $get = function (string $column, mixed $default = null) use ($row) {
            if (! $row || ! Schema::hasColumn('social_insurance_settings', $column)) {
                return $default;
            }
            $value = $row->{$column} ?? $default;

            return $value === '' ? $default : $value;
        };

        $typeId = $get('component_type_id');
        if (! $typeId && Schema::hasTable('salary_component_types')) {
            $typeId = SalaryComponentType::query()
                ->where('code', SalaryComponentType::CODE_SOCIAL_INSURANCE)
                ->value('id');
        }

        $wageCap = $get('wage_cap', 45000);
        $wageCap = $wageCap === null ? null : (float) $wageCap;

        return [
            'enabled' => (bool) $get('enabled', false),
            'employee_rate' => (float) $get('employee_rate', 9.75),
            'employer_rate' => (float) $get('employer_rate', 11.75),
            // غيرُ المواطن: لا استقطاعَ من الموظف، والمنشأةُ تُساهم بالأخطار المهنية
            'resident_employee_rate' => (float) $get('resident_employee_rate', 0),
            'resident_employer_rate' => (float) $get('resident_employer_rate', 2),
            'wage_cap' => $wageCap,
            // الحساباتُ من «إعدادات الحسابات المحاسبية» وحدها (مجموعة «الرواتب
            // والموظفين») — أعمدةُ `social_insurance_settings` القديمة متقاعدة
            // لا تُقرأ. ولا رموزَ احتياطيةٍ في الكود: حسابٌ غير مربوط يوقف
            // الصرفَ برسالة (`PayrollAccountant`) بدل أن يُقيَّد على حسابٍ خمّنه النظام.
            'employee_payable_account_id' => PayrollAccounts::id(PayrollAccounts::GOSI_EMPLOYEE_PAYABLE),
            'employer_payable_account_id' => PayrollAccounts::id(PayrollAccounts::GOSI_EMPLOYER_PAYABLE),
            'expense_account_id' => PayrollAccounts::id(PayrollAccounts::GOSI_EXPENSE),
            'component_type_id' => $typeId ? (int) $typeId : null,
        ];
    }

    /** @param  array<string, mixed>  $breakdown */
    private function persist(SalarySlip $slip, array $breakdown): ?SalaryItem
    {
        $existing = SalaryItem::query()
            ->where('salary_slips_id', $slip->id)
            ->where('origin', SalaryItem::ORIGIN_SOCIAL_INSURANCE)
            ->first();

        if (! $breakdown['enabled'] || $breakdown['employee_amount'] < 0.01) {
            $existing?->delete();

            return null;
        }

        $payload = [
            'name' => $breakdown['name'],
            'type' => SalaryItem::TYPE_DEDUCTION,
            'status' => SalaryItem::STATUS_ACTIVE,
            'salary_item_value' => SalaryItem::VALUE_TYPE_AMOUNT,
            'amount' => $breakdown['employee_amount'],
            'salary_slips_id' => $slip->id,
            'component_type_id' => $breakdown['component_type_id'],
            'origin' => SalaryItem::ORIGIN_SOCIAL_INSURANCE,
            // السببُ مع الحساب: «خاضع — مواطن، الأجر الخاضع X × 9.75% على الموظف…»
            'description' => mb_substr((string) ($breakdown['explanation'] ?? sprintf(
                'أجر خاضع %s × %s%% (سقف %s)',
                number_format($breakdown['contributory_wage'], 2),
                rtrim(rtrim(number_format($breakdown['employee_rate'], 3), '0'), '.'),
                $breakdown['wage_cap'] ? number_format($breakdown['wage_cap'], 2) : '—'
            )), 0, 250),
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return SalaryItem::create($payload);
    }

    /** @return array<string, mixed> */
    private function emptyResult(): array
    {
        return [
            'item' => null,
            'enabled' => false,
            'employee_amount' => 0.0,
            'employer_amount' => 0.0,
            'wage_base' => 0.0,
            'contributory_wage' => 0.0,
            'employee_rate' => 0.0,
            'employer_rate' => 0.0,
            'wage_cap' => null,
            'eligibility' => null,
            'explanation' => null,
            'name' => 'تأمينات اجتماعية',
            'component_type_id' => null,
            'employee_payable_account_id' => null,
            'employer_payable_account_id' => null,
            'expense_account_id' => null,
        ];
    }

    private function itemName(?int $componentTypeId): string
    {
        if ($componentTypeId) {
            $name = SalaryComponentType::query()->whereKey($componentTypeId)->value('name');
            if ($name) {
                return $name;
            }
        }

        return 'تأمينات اجتماعية';
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('social_insurance_settings')
            && Schema::hasTable('salary_items')
            && Schema::hasColumn('salary_items', 'origin');
    }
}
