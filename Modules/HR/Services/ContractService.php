<?php

namespace Modules\HR\Services;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\SalaryItem;
use App\Support\CurrencySettings;
use App\Traits\CalculationFormulaTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * منطقُ العقد الموحّد — تُنادى من شاشة العقود ومن «إنشاء عقدٍ مع الموظف».
 *
 * ── لماذا خدمة ──
 *
 * كان حسابُ العقد مبعثراً بين ثلاثة أماكن لا يتّفق منها اثنان: جافاسكربت
 * الشاشة يحسب الصيغَ على حقلٍ اسمُه `basic_salary` لا وجود له (فكلُّ صيغةٍ
 * تُحسب على صفر)، والمتحكّمُ يحفظ ما يصله كما هو، وقاعدةُ التحقّق تشترط
 * `amount` — أي حقلَ «الأساسي» المنفصل — فمن أدخل «راتب أساسي» بنداً في
 * المستحقات (وهو بندٌ قائمٌ في كتالوج البنود) وترك الحقلَ فارغاً رُدّ عليه
 * «مبلغ العقد مطلوب» وبنودُه كلُّها بمبالغ.
 *
 * فالحسابُ هنا وحده، والتحقّقُ على **البنود الفعلية** لا على حقلٍ منفصل.
 *
 * ── معنى المبالغ ──
 *
 *   • `contracts.amount`      الراتبُ الأساسي («basic») — وهو ما يقرؤه مسيرُ
 *                             الرواتب أساساً (`SalarySlipController`) ثم يُضيف
 *                             إليه بنودَ العقد. فلا يُخزَّن فيه الإجمالي، وإلا
 *                             حُسبت البنودُ مرّتين في القسيمة.
 *   • إجمالي المستحقات        مجموعُ بنود النوع 1.
 *   • إجمالي الراتب           الأساسي + المستحقات — وهو «مبلغ العقد».
 *   • صافي الراتب             الإجمالي − المستقطعات.
 */
class ContractService
{
    use CalculationFormulaTrait;

    /** مدّةُ التجربة الافتراضية (نظام العمل السعودي: 90 يوماً) */
    public const DEFAULT_PROBATION_DAYS = 90;

    /** عملةُ العقد — عملةُ النظام من شاشة «العملات» وحدها، لا يختارها المستخدم */
    public static function currency(): string
    {
        return CurrencySettings::code();
    }

    /**
     * البنودُ التي يُختار منها في العقد: الكتالوجُ وحده — لا نُسَخُ العقود
     * ولا القسائم ولا القوالب. كانت شاشةُ الإنشاء تستثني القوالبَ فقط فتظهر
     * نسخُ العقود السابقة والقسائم في القائمة مكرَّرةً باسم البند نفسه.
     */
    public static function catalogItems(int $type): Collection
    {
        // `catalog()` في الموديل: التعريفاتُ وحدها (لا نُسَخ ولا بنودٌ مولَّدة)
        return SalaryItem::query()->catalog()
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    /**
     * تاريخُ الانتهاء من البداية والمدّة — شاملاً يومَ البداية:
     * عقدُ سنةٍ يبدأ 2026-01-01 ينتهي 2026-12-31.
     */
    public function computeEndDate($start, $duration, $unit): ?Carbon
    {
        $duration = (int) $duration;

        if (! $start || $duration < 1) {
            return null;
        }

        try {
            $date = Carbon::parse($start)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $end = match ((int) $unit) {
            Contract::DURATION_DAY => $date->copy()->addDays($duration),
            Contract::DURATION_MONTH => $date->copy()->addMonthsNoOverflow($duration),
            Contract::DURATION_YEAR => $date->copy()->addYearsNoOverflow($duration),
            default => null,
        };

        return $end?->subDay();
    }

    /** رقمٌ من نصٍّ قد يحمل فواصلَ آلاف — أو null إن لم يكن رقماً */
    public static function number($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace([',', '٬', ' '], '', (string) $value));

        // أرقامٌ عربية-هندية تُكتب أحياناً من لوحة مفاتيح عربية
        $value = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * يبني صفوفَ البنود من مُدخَلات النموذج — المستحقات والمستقطعات.
     *
     * لكل صفّ: البندُ المصدر (من الكتالوج)، والصيغة،
     * والمبلغ. المبلغُ بالترتيب: ما كُتب في الصفّ إن كان رقماً، وإلا ناتجُ
     * الصيغة على الأساسي، وإلا مبلغُ البند في الكتالوج.
     *
     * الصفُّ بلا بندٍ مختار يُهمَل (صفٌّ أُضيف وتُرك).
     *
     * المصدرُ المقبول: بندُ كتالوج، أو — في التعديل — نسخةٌ في العقد نفسِه
     * (`$contractId`) لبندٍ لم يعد في الكتالوج بالاسم ذاته. ولا تُقبل نسخُ
     * عقودٍ أخرى ولا قسائم ولا بنودُ «قوالب الرواتب» (أُلغيت 2026-09-24):
     * البنودُ هي ما يُدخله المستخدمُ صفّاً صفّاً في العقد فقط.
     *
     * @return array{additions: array<int, array>, deductions: array<int, array>}
     */
    public function buildItemRows(array $input, float $basic, ?int $contractId = null): array
    {
        $rows = ['additions' => [], 'deductions' => []];

        foreach (['addition' => SalaryItem::TYPE_ADDITION, 'deduction' => SalaryItem::TYPE_DEDUCTION] as $prefix => $type) {
            $ids = (array) ($input[$prefix.'_type'] ?? []);
            $formulas = (array) ($input[$prefix.'_calculation_formula'] ?? []);
            $amounts = (array) ($input[$prefix.'_amount'] ?? []);

            $sources = SalaryItem::whereIn('id', array_filter($ids, 'is_numeric'))
                ->where(function ($q) use ($contractId) {
                    $q->whereNull('contracts_id');
                    if ($contractId) {
                        $q->orWhere('contracts_id', $contractId);
                    }
                })
                ->whereNull('salary_slips_id')
                ->whereNull('salary_template_id')
                ->get()
                ->keyBy('id');

            foreach ($ids as $key => $id) {
                if (! is_numeric($id) || ! $sources->has((int) $id)) {
                    continue;
                }

                $source = $sources->get((int) $id);
                $formula = trim((string) ($formulas[$key] ?? ''));
                $typed = self::number($amounts[$key] ?? null);

                if ($typed !== null) {
                    $amount = $typed;
                } elseif ($formula !== '') {
                    $amount = $this->evaluateCalculationFormula($formula, $this->getFormulaVariables($basic));
                } else {
                    $amount = (float) ($source->amount ?? 0);
                }

                $rows[$type === SalaryItem::TYPE_ADDITION ? 'additions' : 'deductions'][] = [
                    'key' => $key,
                    'source' => $source,
                    'type' => $type,
                    'name' => $source->name,
                    'formula' => $formula !== '' ? $formula : null,
                    'amount' => round(max(0, $amount), 2),
                ];
            }
        }

        return $rows;
    }

    /**
     * إجمالياتُ العقد من الأساسي والبنود.
     *
     * @param  iterable  $additions  صفوفٌ أو نماذجُ SalaryItem — المعتبرُ `amount`
     * @return array{basic: float, additions: float, deductions: float, gross: float, net: float}
     */
    public static function totals(?float $basic, iterable $additions, iterable $deductions): array
    {
        $sum = function (iterable $items): float {
            $total = 0.0;
            foreach ($items as $item) {
                $total += (float) (is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
            }

            return round($total, 2);
        };

        $basic = round((float) $basic, 2);
        $add = $sum($additions);
        $ded = $sum($deductions);
        $gross = round($basic + $add, 2);

        return [
            'basic' => $basic,
            'additions' => $add,
            'deductions' => $ded,
            'gross' => $gross,
            'net' => round($gross - $ded, 2),
        ];
    }

    /**
     * يستبدل بنودَ العقد بالصفوف المبنيّة — نسخٌ من البند المصدر مربوطةٌ
     * بالعقد (الاسم والنوع والحساب)، بمبلغ الصفّ وصيغته.
     *
     * كان التعديلُ لا يستبدل إلا إن وصل صفٌّ واحدٌ على الأقل، فمن حذف
     * البنودَ كلَّها من الشاشة بقيت محفوظةً رغماً عنه.
     */
    public function syncItems(Contract $contract, array $rows): void
    {
        SalaryItem::where('contracts_id', $contract->id)
            ->whereIn('type', [SalaryItem::TYPE_ADDITION, SalaryItem::TYPE_DEDUCTION])
            ->delete();

        foreach (array_merge($rows['additions'] ?? [], $rows['deductions'] ?? []) as $row) {
            /** @var SalaryItem $source */
            $source = $row['source'];

            SalaryItem::create([
                'name' => $source->name,
                'type' => $row['type'],
                'status' => $source->status,
                'description' => $source->description,
                'salary_item_value' => $source->salary_item_value,
                'calculation_formula' => $row['formula'],
                'amount' => $row['amount'],
                'contracts_id' => $contract->id,
                'account_id' => $source->account_id,
                'component_type_id' => $source->component_type_id,
            ]);
        }
    }

    // ───────────── «إنشاء عقدٍ للموظف» من شاشة الموظف ─────────────

    /**
     * هل يُعرض قسمُ «إنشاء عقد للموظف» ويُقبل؟ الطبقاتُ الثلاث نفسُها التي
     * تحرس شاشة العقود: صلاحيةُ الإنشاء، وميزةُ الباقة، وتفعيلُ «الرواتب».
     */
    public static function canCreateFromEmployeeForm(): bool
    {
        if (! function_exists('hasPermission') || ! hasPermission('contracts.create')) {
            return false;
        }

        try {
            if (\App\Support\PlanLock::locked(\App\Constants\planFeatures\HrPlanFeatures::CONTRACTS)) {
                return false;
            }
            if (class_exists(\App\Services\Settings\ApplicationRegistry::class)
                && ! \App\Services\Settings\ApplicationRegistry::isActive('salaries')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * يتحقّق من حقول `contract[...]` في نموذج الموظف — ويعيد مُدخَلاتٍ جاهزة
     * لـ`createForEmployee()`، أو null إن لم يُطلب إنشاءُ عقد.
     *
     * يُنادى **قبل** فتح المعاملة: `ValidationException` داخلها كانت ستُلتقط
     * خطأً عامّاً (500) بدل أن تعود رسائلَ حقول.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateEmployeeContract(\Illuminate\Http\Request $request): ?array
    {
        if (! $request->boolean('contract.create')) {
            return null;
        }

        $fail = fn (string $key, string $message) => throw \Illuminate\Validation\ValidationException::withMessages([$key => $message]);

        if (! self::canCreateFromEmployeeForm()) {
            $fail('contract.create', 'لا تملك إنشاء العقود (الصلاحية أو الباقة أو تطبيق الرواتب معطّل) — ألغِ خيار «إنشاء عقد للموظف».');
        }

        $limit = app(\App\Services\Fawtra\SubscriptionGate::class)->checkLive(\App\Constants\planFeatures\HrPlanFeatures::LIMIT_CONTRACTS, 1);
        if (! ($limit['allowed'] ?? true)) {
            $fail('contract.create', $limit['reason'] ?? 'بلغت حدّ العقود في باقتك.');
        }

        $number = fn ($v) => ($v === null || trim((string) $v) === '') ? null : (self::number($v) ?? $v);
        $input = (array) $request->input('contract', []);
        $input['basic_salary'] = $number($input['basic_salary'] ?? null);

        $validator = \Illuminate\Support\Facades\Validator::make(
            $input + ['job_title_id' => $request->input('job_title_id'), 'job_level_id' => $request->input('job_level_id')],
            [
                'job_title_id' => 'required|exists:jop_titles,id',
                'job_level_id' => 'required|exists:functional_levels,id',
                'start_date' => 'required|date',
                'duration' => 'required|integer|min:1|max:1200',
                'duration_unit' => 'required|in:1,2,3',
                'probation_end_date' => 'nullable|date|after_or_equal:start_date',
                'contract_date' => 'nullable|date',
                'basic_salary' => 'nullable|numeric|min:0',
                'receiving_cycle' => 'nullable|in:1,2,3,4,5',
            ],
            [
                'job_title_id.required' => 'المسمّى الوظيفي مطلوب لإنشاء عقد الموظف.',
                'job_level_id.required' => 'المستوى الوظيفي مطلوب لإنشاء عقد الموظف.',
                'start_date.required' => 'تاريخ بداية العقد مطلوب.',
                'duration.required' => 'مدة العقد مطلوبة.',
                'duration.min' => 'مدة العقد 1 على الأقل.',
                'duration_unit.required' => 'وحدة مدة العقد مطلوبة.',
                'probation_end_date.after_or_equal' => 'نهاية التجربة لا تسبق بداية العقد.',
                'basic_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقماً.',
            ]
        );

        if ($validator->fails()) {
            // مفاتيحُ الحقول كما في النموذج (`contract.start_date`) كي يُعلَّم الحقلُ نفسُه
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[in_array($key, ['job_title_id', 'job_level_id'], true) ? $key : 'contract.'.$key] = $messages;
            }
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $data = $validator->validated();
        $basic = (float) ($data['basic_salary'] ?? 0);
        // لا بنودَ من شاشة الموظف: «قوالب الرواتب» أُلغيت، والبنودُ تُضاف من شاشة العقد
        $rows = ['additions' => [], 'deductions' => []];
        $totals = self::totals($basic, $rows['additions'], $rows['deductions']);

        if ($totals['gross'] <= 0) {
            $fail('contract.basic_salary', 'مبلغ العقد صفر: أدخل الراتب الأساسي.');
        }

        return $data + ['rows' => $rows];
    }

    /**
     * ينشئ عقدَ الموظف من مُدخَلات `validateEmployeeContract()` — داخل
     * معاملة المتحكّم، فإن سقط حفظُ الموظف لم يبقَ عقدٌ يتيم.
     */
    public function createForEmployee(Employee $employee, array $data): Contract
    {
        $start = Carbon::parse($data['start_date'])->toDateString();
        $join = $employee->hire_date ? Carbon::parse($employee->hire_date)->toDateString() : $start;
        $probation = ! empty($data['probation_end_date'])
            ? Carbon::parse($data['probation_end_date'])->toDateString()
            : Carbon::parse($join)->addDays(self::DEFAULT_PROBATION_DAYS - 1)->toDateString();

        $contract = new Contract([
            'employee_id' => $employee->id,
            'job_title_id' => $data['job_title_id'],
            'job_level_id' => $data['job_level_id'],
            'start_date' => $start,
            'type_contract' => Contract::TYPE_PERIOD,
            'duration' => (int) $data['duration'],
            'duration_unit' => (int) $data['duration_unit'],
            'end_date' => $this->computeEndDate($start, $data['duration'], $data['duration_unit'])?->toDateString(),
            'amount' => isset($data['basic_salary']) && $data['basic_salary'] !== null ? (float) $data['basic_salary'] : null,
            'join_date' => $join,
            'probation_end_date' => $probation,
            'contract_date' => ! empty($data['contract_date']) ? Carbon::parse($data['contract_date'])->toDateString() : now()->toDateString(),
            'receiving_cycle' => ! empty($data['receiving_cycle']) ? (int) $data['receiving_cycle'] : Contract::CYCLE_MONTHLY,
            'currency' => self::currency(),
        ]);
        $contract->code = next_document_number('contracts');
        $contract->created_by = auth()->id();
        $contract->save();

        $this->syncItems($contract, $data['rows'] ?? ['additions' => [], 'deductions' => []]);

        \App\Support\OperationLog::record('created', 'salary_contracts_log', $contract,
            'تم انشاء عقد رقم **'.$contract->code.'** مع إضافة الموظف **'.$employee->full_name.'**');

        return $contract;
    }

    /**
     * بياناتُ الموظف التي يُملأ بها العقد — المسمّى والمستوى والفرع والقسم
     * وتاريخ التعيين وآخرُ عقوده (مرشّحةً للعقد الأساسي).
     */
    public function employeeProfile(Employee $employee, ?int $exceptContractId = null): array
    {
        $employee->loadMissing(['job_title', 'department', 'job_level', 'branch']);

        $contracts = Contract::where('employee_id', $employee->id)
            ->when($exceptContractId, fn ($q) => $q->where('id', '!=', $exceptContractId))
            ->orderByDesc('start_date')
            ->get(['id', 'code', 'start_date', 'end_date']);

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'code' => $employee->employee_code,
            'job_title_id' => $employee->job_title_id,
            'job_title' => $employee->job_title->name ?? null,
            'job_level_id' => $employee->job_level_id,
            'job_level' => $employee->job_level->name ?? null,
            'department' => $employee->department->name ?? null,
            'branch' => $employee->branch->name ?? null,
            'hire_date' => optional($employee->hire_date ? Carbon::parse($employee->hire_date) : null)?->toDateString(),
            'contracts' => $contracts->map(fn (Contract $c) => [
                'id' => $c->id,
                'label' => trim(($c->code ?: '#'.$c->id).' — '.optional($c->start_date)->format('Y-m-d')
                    .($c->end_date ? ' ← '.$c->end_date->format('Y-m-d') : '')),
            ])->values()->all(),
        ];
    }
}
