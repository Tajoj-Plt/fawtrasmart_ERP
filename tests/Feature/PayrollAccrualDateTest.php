<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * المصروفُ يُعترَف به في الفترة التي اكتُسب فيها لا يومَ دفعه.
 *
 * **ما كان**
 *
 * `$postingDate = now()` يُستعمل لقيدَي الاستحقاق والصرف **معاً**. فرواتبُ
 * سبتمبر المصروفةُ في ٥ أكتوبر تُحمَّل على أكتوبر، وتخرج تكلفةُ العمالة من
 * الشهر الذي اكتُسبت فيه — فلا تطابق الإيرادَ الذي أنتجته.
 *
 * والأدهى أنّ قيدَ الاستحقاق يُنشأ ويُقفَل في اللحظة نفسها: يُدان حسابُ
 * المستحقات ثمّ يُدان عكسُه في القيد التالي بالثانية ذاتها. فلا يحمل
 * «مستحقات الرواتب» رصيداً في نهاية أيِّ فترة — وهو نصفُ الغرض من وجود
 * قيد استحقاقٍ أصلاً.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ للاستحقاق تاريخاً خاصّاً به مأخوذاً من `period_to`، وأنّ الصرفَ يبقى
 * بتاريخ خروج النقد، وأنّ حصةَ المنشأة — وهي استحقاقٌ كذلك — تتبع تاريخَ
 * الاستحقاق، وأنّ فترةَ استحقاقٍ مقفلة لا يُرحَّل فيها.
 *
 * و`JournalEntry` يختم `accounting_period_id` من عمود `date`
 * (`BelongsToAccountingPeriod`)، فتغييرُ التاريخ ينقل القيدَ إلى فترته
 * الصحيحة بلا عملٍ إضافي.
 *
 * والقياسُ على الشيفرة: بناءُ صرفةٍ كاملةٍ يحتاج شجرةَ حساباتٍ وإعداداتِ
 * تأميناتٍ وفتراتٍ محاسبية — وهو ما يبنيه [PayrollDisbursementPostingTest]
 * بخدمةِ فتراتٍ بديلة. والمقيسُ هنا العقدُ نفسُه: أيُّ تاريخٍ يحمله كلُّ قيد.
 */
class PayrollAccrualDateTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(app_path('Services/PayrollAccountant.php'));
    }

    /** الشيفرةُ بلا تعليقات — فلا يُحتسب نصُّ شرحٍ على أنّه تنفيذ. */
    private function code(): string
    {
        $code = preg_replace('#/\*.*?\*/#s', '', $this->source());

        return (string) preg_replace('#//.*$#m', '', (string) $code);
    }

    /** جسدُ قيدٍ بعينه من نصّ الملف. */
    private function entryBlock(string $reference): string
    {
        $at = strpos($this->code(), "'reference_number' => '{$reference}'");
        $this->assertNotFalse($at, "قيد {$reference} مفقود");

        return substr($this->code(), $at, 420);
    }

    /** للاستحقاق تاريخٌ خاصٌّ به، مأخوذٌ من نهاية فترة الصرفة. */
    public function test_the_accrual_has_its_own_date_taken_from_the_period(): void
    {
        $code = $this->code();

        $this->assertStringContainsString('$accrualDate', $code);
        $this->assertStringContainsString('period_to', $code, 'تاريخُ الاستحقاق من نهاية الفترة');
        $this->assertStringContainsString(
            "'date' => \$accrualDate->toDateString()",
            $this->entryBlock('SAL-ACC-'),
            'قيدُ الاستحقاق يُرحَّل بتاريخ الاستحقاق'
        );
    }

    /** وقيدُ الصرف يبقى بتاريخ خروج النقد. */
    public function test_the_payment_entry_keeps_the_disbursement_date(): void
    {
        $this->assertStringContainsString(
            "'date' => \$postingDate->toDateString()",
            $this->entryBlock('SAL-PAY-'),
            'الصرفُ يُرحَّل يومَ خروج النقد'
        );
    }

    /** وحصةُ المنشأة استحقاقٌ كذلك، فتتبع تاريخَ الاستحقاق. */
    public function test_the_employer_contribution_follows_the_accrual(): void
    {
        $this->assertStringContainsString(
            "'date' => \$accrualDate->toDateString()",
            $this->entryBlock('SAL-GOSI-')
        );
    }

    /**
     * وفترةُ استحقاقٍ مقفلة لا يُرحَّل فيها: يعود القيدُ إلى تاريخ الدفع
     * ويُقال ذلك في وصفه — بدل أن يُرفض الصرفُ كلُّه أو يُكتب في فترةٍ صدرت
     * عنها القوائم.
     */
    public function test_a_closed_or_future_period_falls_back_to_the_payment_date(): void
    {
        $code = $this->code();

        $this->assertStringContainsString(
            'AccountingPeriodGuard::blockReason($accrualDate)',
            $code,
            'تاريخُ الاستحقاق يُفحص على حارس الفترة كما يُفحص تاريخُ الصرف'
        );
        $this->assertStringContainsString(
            '$accrualDate->gt($postingDate)',
            $code,
            'وفترةٌ لم تنتهِ بعد لا يُرحَّل فيها استحقاقٌ سابقٌ لأوانه'
        );
        $this->assertStringContainsString(
            'فترةُ الاستحقاق مقفلة',
            $this->source(),
            'الوصفُ يقول لماذا اختلف التاريخ'
        );
    }

    /** والحارسُ مستوردٌ فعلاً — لا اسمٌ في تعليق. */
    public function test_the_period_guard_is_imported(): void
    {
        $this->assertStringContainsString(
            'use App\Support\AccountingPeriodGuard;',
            $this->source()
        );
    }
}
