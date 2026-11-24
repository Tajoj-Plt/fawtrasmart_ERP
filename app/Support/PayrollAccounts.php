<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\Deduction;

/**
 * حساباتُ الرواتب والموارد البشرية — مصدرُها الواحد «إعدادات الحسابات المحاسبية».
 *
 * **لماذا؟**
 *
 * كانت حساباتُ الرواتب مبعثرةً في ثلاثة مواضع: `payroll_settings`
 * (مصروف الرواتب، حسابا نهاية الخدمة)، و`social_insurance_settings`
 * (حسابات التأمينات الثلاثة)، و`accounting_settings` (الأبوان والخصميات
 * والمكافآت). وكلُّ شاشةٍ من شاشات الرواتب تحمل منتقي حسابٍ يكتب في
 * مصدرها وحدها — فيرى المحاسبُ في «الحسابات المحاسبية» شيئاً والقيدُ
 * يذهب إلى غيره.
 *
 * صار كلُّ حسابٍ منها مفتاحاً في `accounting_settings` (مجموعة
 * «الرواتب والموظفين»)، يُربط من شاشة «إعدادات الحسابات المحاسبية» وحدها.
 * وشاشاتُ الرواتب تعرضه للقراءة مع رابطٍ إليها. والأعمدةُ القديمة باقيةٌ
 * في جداولها (لا تُحذف) لكن لا شيء يقرؤها ولا يكتبها.
 *
 * ولا احتياطَ من ثوابت `SystemAccountIds`: المفتاحُ غيرُ المربوط يوقف
 * العمليةَ برسالة `missingMessage()` التي تسمّي الحسابَ والشاشة.
 */
class PayrollAccounts
{
    /** اسمُ الشاشة كما يراه المستخدم — يُذكر في كلّ رسالة «غير مربوط». */
    public const SCREEN = 'إعدادات الحسابات المحاسبية';

    /** مجموعةُ هذه المفاتيح في الشاشة — ومرساتُها (`#as-group-payroll`). */
    public const GROUP = 'الرواتب والموظفين';

    public const SALARY_EXPENSE = 'employees_salaries_expense';

    public const SALARIES_PARENT = 'salaries_wages_parent';

    public const PAYABLES_PARENT = 'employee_payables_parent';

    public const ADVANCES_PARENT = 'employee_advances_parent';

    public const DEDUCTIONS = 'payroll_deductions';

    /** مفتاحُ «مكافآت نقدية مستبدَلة» في مجموعة «نظام المكافآت» — يُعاد استعماله لا يُكرَّر. */
    public const REWARDS_EXPENSE = 'rewards_cash_expense';

    public const EOS_EXPENSE = 'eos_expense';

    public const EOS_PROVISION = 'eos_provision';

    public const GOSI_EMPLOYEE_PAYABLE = 'gosi_employee_payable';

    public const GOSI_EMPLOYER_PAYABLE = 'gosi_employer_payable';

    public const GOSI_EXPENSE = 'gosi_expense';

    public const MAIN_TREASURY = 'main_treasury';

    /** مفتاحُ نوع خصمية: `payroll_deduction_{type}`. */
    public static function deductionTypeKey(string $type): string
    {
        return 'payroll_deduction_'.$type;
    }

    /**
     * الاسمُ الظاهر لكلّ مفتاح — في الرسائل وفي شاشات العرض.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [
            self::SALARY_EXPENSE => 'مصروف رواتب الموظفين',
            self::PAYABLES_PARENT => 'الحساب الأب لمستحقات الموظفين',
            self::ADVANCES_PARENT => 'الحساب الأب لسلف الموظفين',
            self::REWARDS_EXPENSE => 'مكافآت نقدية مستبدَلة (مصروف المكافآت)',
            self::DEDUCTIONS => 'خصميات الموظفين (الحساب العام)',
            self::EOS_EXPENSE => 'مصروف مكافأة نهاية الخدمة',
            self::EOS_PROVISION => 'مخصص مكافأة نهاية الخدمة',
            self::GOSI_EMPLOYEE_PAYABLE => 'تأمينات مستحقة — حصة الموظف',
            self::GOSI_EMPLOYER_PAYABLE => 'تأمينات مستحقة — حصة المنشأة',
            self::GOSI_EXPENSE => 'مصروف التأمينات الاجتماعية',
            self::MAIN_TREASURY => 'الخزينة الرئيسية',
        ];

        foreach (Deduction::types() as $type => $label) {
            $labels[self::deductionTypeKey($type)] = 'خصميات «'.$label.'»';
        }

        return $labels;
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    /**
     * أقسامُ العرض: العنوان => [المفتاح => ما يقيّده].
     *
     * @return array<string, array{icon: string, keys: array<string, string>}>
     */
    public static function sections(): array
    {
        $deductions = [self::DEDUCTIONS => 'الحسابُ الأب لحسابات أنواع الخصميات في الشجرة، ودائنٌ عند اعتماد أيّ خصميةٍ لم يُربط لنوعها حسابٌ خاص.'];
        foreach (Deduction::types() as $type => $label) {
            $deductions[self::deductionTypeKey($type)] = 'دائنٌ عند اعتماد خصمية «'.$label.'» — غيرُ المربوط يسقط على الحساب العام.';
        }

        return [
            'salaries' => [
                'title' => 'صرف الراتب',
                'icon' => 'dollar-sign',
                'keys' => [
                    self::SALARY_EXPENSE => 'مدينٌ في قيد استحقاق كلّ راتب بالأساسي والبدلات والأجر الإضافي.',
                    self::REWARDS_EXPENSE => 'مدينٌ بالمكافأة النقدية من «كتالوج المكافآت» المصروفة مع الراتب.',
                    self::MAIN_TREASURY => 'الخزينةُ الأخيرة للصرف حين لا تُحدَّد خزينةٌ للصرفة ولا للموظف.',
                ],
            ],
            'employees' => [
                'title' => 'حسابات الموظفين',
                'icon' => 'git-branch',
                'keys' => [
                    self::PAYABLES_PARENT => 'يُفتح تحته حسابُ مستحقات كلّ موظفٍ جديد (خصوم).',
                    self::ADVANCES_PARENT => 'يُفتح تحته حسابُ سلف كلّ موظفٍ جديد (أصول).',
                ],
            ],
            'deductions' => [
                'title' => 'حسابات الخصميات',
                'icon' => 'minus-circle',
                'keys' => $deductions,
            ],
            'eos' => [
                'title' => 'مكافأة نهاية الخدمة',
                'icon' => 'award',
                'keys' => [
                    self::EOS_EXPENSE => 'مدينٌ بمبلغ المكافأة عند اعتماد المسودة.',
                    self::EOS_PROVISION => 'دائنٌ عند الاعتماد، ومدينٌ عند التسوية إلى ذمّة الموظف.',
                ],
            ],
            'gosi' => [
                'title' => 'التأمينات الاجتماعية',
                'icon' => 'shield',
                'keys' => [
                    self::GOSI_EMPLOYEE_PAYABLE => 'دائنٌ بحصة الموظف المستقطَعة عند الصرف، ويُقفل عند السداد.',
                    self::GOSI_EMPLOYER_PAYABLE => 'دائنٌ بمساهمة المنشأة عند الصرف، ويُقفل عند السداد.',
                    self::GOSI_EXPENSE => 'مدينٌ بحصة المنشأة — تكلفةُ شركةٍ لا تمسّ صافي الموظف.',
                ],
            ],
        ];
    }

    /**
     * معرّفُ الحساب المربوط والموجود في الشجرة — أو null. بلا احتياط.
     */
    public static function id(string $key): ?int
    {
        $id = AccountingSettings::id($key);

        return $id && Account::query()->whereKey($id)->exists() ? (int) $id : null;
    }

    public static function account(string $key): ?Account
    {
        $id = self::id($key);

        return $id ? Account::find($id) : null;
    }

    /**
     * الحسابُ المربوط — أو استثناءٌ يسمّي الحسابَ والشاشة.
     *
     * @throws \RuntimeException
     */
    public static function require(string $key, ?string $context = null): int
    {
        $id = self::id($key);
        if (! $id) {
            throw new \RuntimeException(self::missingMessage($key, $context));
        }

        return $id;
    }

    /**
     * «حساب «مصروف مكافأة نهاية الخدمة» غير مربوط — اربطه من إعدادات الحسابات
     * المحاسبية (مجموعة «الرواتب والموظفين») …».
     *
     * الرسالةُ نفسُها في كلّ موضع، على نمط المحافظ (`ClientWalletService`).
     */
    public static function missingMessage(string $key, ?string $context = null): string
    {
        $group = str_starts_with($key, 'rewards_') ? 'نظام المكافآت' : (($key === self::MAIN_TREASURY) ? 'الخزائن والبنوك' : self::GROUP);

        return 'حساب «'.self::label($key).'» غير مربوط — اربطه من '.self::SCREEN.' (مجموعة «'.$group.'»)'
            .($context ? ' قبل '.$context : '').'.';
    }

    /**
     * صفوفُ العرض للقراءة: المفتاح، الاسم، الحساب المربوط أو null، وسببُ غيابه.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{key: string, label: string, account: Account|null, state: string}>
     */
    public static function rows(array $keys): array
    {
        $settings = AccountingSetting::query()
            ->with('account:id,name,code,is_active')
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $rows = [];
        foreach ($keys as $key) {
            $setting = $settings->get($key);
            $account = $setting?->account;

            $state = match (true) {
                ! $setting => 'missing_row',
                ! $setting->account_id || ! $account => 'unmapped',
                ! $setting->is_active => 'inactive',
                default => 'mapped',
            };

            $rows[$key] = [
                'key' => $key,
                'label' => self::label($key),
                'account' => $state === 'mapped' || $state === 'inactive' ? $account : null,
                'state' => $state,
            ];
        }

        return $rows;
    }

    /** هل يرى المستخدمُ شاشةَ الربط؟ زرُّ «تعديل الربط» يظهر له وحده. */
    public static function canEdit(): bool
    {
        return \Illuminate\Support\Facades\Route::has('AccountingSettings.index')
            && function_exists('hasPermission')
            && hasPermission('settings.accounting_settings.view');
    }

    /**
     * رابطُ الشاشة مع مرساة المفتاح (`#as-key-…`) أو المجموعة (`#as-group-payroll`).
     */
    public static function editUrl(?string $key = null): string
    {
        return route('AccountingSettings.index').'#'.($key ? 'as-key-'.$key : 'as-group-payroll');
    }
}
