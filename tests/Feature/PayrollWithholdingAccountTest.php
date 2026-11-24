<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * لا تُحجَز حصةُ الموظف من التأمينات بلا حسابِ ذمّةٍ تُقيَّد فيه.
 *
 * **ما كان**
 *
 * الفصلُ في قيد الصرف كان مشروطاً بشيئَين معاً:
 *
 *     $gosiWithheldSeparately = $calc->gosiEmployee > 0.009
 *         && $calc->gosiEmployeeAccountId;
 *
 * فإن لم يكن حسابُ الذمّة مربوطاً في «إعدادات التأمينات الاجتماعية» سقط
 * الطرفُ الثاني، فتُدرَج الحصةُ في `totalDeductions`: تُنقص المصروف في قيد
 * الاستحقاق، ولا يُقابلها سطرٌ دائنٌ في أيِّ حساب.
 *
 * والقيدُ يبقى متوازناً — ولذلك لم يظهر العطب — لكنّ الحصةَ **محجوزةٌ من
 * الموظف فعلاً**: نزلت من صافيه ولم تخرج من الخزينة. فتصير على المنشأة
 * أموالٌ للتأمينات لا يظهر لها أثرٌ في حساب، ولا يعلم بها إلا من يطابق
 * الصافي بالإجمالي يدوياً.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الصرفَ يُمنع حين ينقص الحساب — كما يُمنع لنقص حساب المستحقات وحساب
 * السلف في الموضع نفسه — وأنّ الرسالةَ تدلّ على شاشة الربط، وأنّ الفرعَ
 * الذي كان يُسقط الحصةَ زال.
 *
 * والقياسُ على الشيفرة: بناءُ صرفةٍ كاملةٍ يحتاج شجرةَ حساباتٍ وإعداداتِ
 * تأميناتٍ وفتراتٍ محاسبية، والمقيسُ هنا العقدُ نفسُه — متى يُرفض الحجز.
 */
class PayrollWithholdingAccountTest extends TestCase
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

    /** الحجزُ بلا حسابٍ يمنع الصرف. */
    public function test_withholding_without_a_liability_account_is_refused(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \(\$calc->gosiEmployee > 0\.009 && ! \$calc->gosiEmployeeAccountId\)\s*\{\s*throw/',
            $this->code(),
            'الحجزُ بلا حسابٍ يجب أن يمنع الصرف كما يمنعه نقصُ حساب المستحقات وحساب السلف'
        );
    }

    /** والرسالةُ تسمّي المبلغَ المحجوز وتدلّ على شاشة الربط. */
    public function test_the_message_names_the_amount_and_the_screen(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('إعدادات التأمينات الاجتماعية', $source);
        $this->assertStringContainsString('حساب ذمم حصة الموظف', $source);
    }

    /**
     * وبعد الحارس صار وجودُ الحساب مضموناً، فشرطُ الفصل على المبلغ وحده —
     * ولا يبقى فرعٌ يُدرِج الحصةَ في الخصميات فتتلاشى.
     */
    public function test_the_vanishing_branch_is_gone(): void
    {
        $code = $this->code();

        $this->assertStringContainsString(
            '$gosiWithheldSeparately = $calc->gosiEmployee > 0.009;',
            $code
        );
        $this->assertStringNotContainsString(
            '$calc->gosiEmployee > 0.009 && $calc->gosiEmployeeAccountId',
            $code,
            'الفرعُ الذي كان يُسقط الحصةَ بلا التزامٍ يجب أن يزول'
        );
    }

    /** والمنعُ يقع قبل كتابة أيِّ مستند — فلا سندَ ولا قيدَ لصرفةٍ مرفوضة. */
    public function test_the_guard_runs_before_any_document_is_written(): void
    {
        $code = $this->code();

        $guardAt = strpos($code, '! $calc->gosiEmployeeAccountId');
        $expenseAt = strpos($code, 'Expense::create(');

        $this->assertNotFalse($guardAt);
        $this->assertNotFalse($expenseAt);
        $this->assertLessThan($expenseAt, $guardAt, 'الحارسُ قبل إنشاء سند الصرف');
    }
}
