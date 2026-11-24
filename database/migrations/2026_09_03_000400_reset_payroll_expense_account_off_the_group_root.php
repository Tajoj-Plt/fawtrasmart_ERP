<?php

use App\Constants\SystemAccountIds;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مصروفُ الرواتب كان يُرحَّل إلى جذر مجموعة الرواتب لا إلى حسابها.
 *
 * هجرةُ `add_salary_expense_account_to_payroll_settings` تختار قيمةً
 * افتراضية هكذا:
 *
 *     $defaultExpense = DB::table('accounts')->where('id', 3079)->value('id')
 *         ?? DB::table('accounts')->where('parent_id', 3041)->value('id')
 *         ?? 3041;
 *
 * والهجراتُ تسبق السيدرات، فجدولُ `accounts` **فارغٌ** لحظةَ تنفيذها:
 * يسقط الشرطان الأوّلان ويقع الاختيار على `3041` المكتوب نصّاً — وهو
 * «الرواتب والأجور»، **جذرُ المجموعة** الذي تحته عشرةُ أبناء: أجور العمال
 * والبدلات والحوافز والتأمينات والتأمين الطبي ومكافأة نهاية الخدمة
 * والعمولات والخصميات. ويتكرّر ذلك في كلّ تركيبٍ جديد.
 *
 * فيُرحَّل مصروفُ الراتب إلى الجذر، ويبقى «رواتب الموظفين» (5301) — وهو
 * موضعُه — بصفر. والأرقامُ تظلّ صحيحةً في الجذر لأنّه يجمع قيودَه المباشرة
 * وأرصدةَ أبنائه، لكنّ التصنيفَ خطأ: من يفتح دليلَ الحسابات باحثاً عن
 * مصروف الرواتب لا يجده حيث يجب أن يكون.
 *
 * **العلاج: تفريغُ الإعداد.** فـ`PayrollAccountant` يحتاط بـ
 * `SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE` حين يكون الإعدادُ فارغاً،
 * وهو ما تَعِد به شاشةُ «حسابات الرواتب» نصّاً: «إن تُرك فارغاً يُستخدم
 * حساب رواتب الموظفين من دليل الحسابات». فالإصلاحُ إعادةُ الأمر إلى ما
 * تقوله الشاشة، لا كتابةُ معرّفٍ آخر بيدنا.
 *
 * والشرطُ على المعرّف نصّاً لا على «هل له أبناء»: هذه الهجرةُ تسبق
 * السيدرات كسابقتها، فجدولُ الحسابات فارغٌ عندها ولا يُعرف من الشجرة شيء.
 * والقيمةُ المستهدَفة معروفةٌ بعينها — هي التي كتبها الملاذُ الأخير.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settings')
            || ! Schema::hasColumn('payroll_settings', 'salary_expense_account_id')) {
            return;
        }

        DB::table('payroll_settings')
            ->where('salary_expense_account_id', SystemAccountIds::SALARIES_WAGES_PARENT)
            ->update(['salary_expense_account_id' => null]);
    }

    /**
     * لا رجعة: إعادةُ الجذر تعني إعادةَ الترحيل الخاطئ. والفارغُ سلوكٌ
     * موثَّقٌ في الشاشة لا نقصٌ يُستدرك.
     */
    public function down(): void {}
};
