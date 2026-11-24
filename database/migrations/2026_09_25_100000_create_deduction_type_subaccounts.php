<?php

use App\Support\DeductionTypeAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * حسابٌ فرعيٌّ لكلِّ نوع خصمية تحت «خصميات الموظفين» وربطُ مفتاحه.
 *
 * الأبُ هو الحسابُ المربوط بالمفتاح العام `payroll_deductions` — يُقرأ من
 * «الحسابات المحاسبية» لا من كودٍ ثابت. والمفتاحُ المربوط سلفاً لا يُمسّ،
 * وإن لم يُربط العامُّ تُخطّي الترحيلُ كلُّه بسطرٍ في السجل.
 * التفاصيل في `App\Support\DeductionTypeAccounts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasTable('accounting_settings')) {
            return;
        }

        $result = DeductionTypeAccounts::ensure();

        if ($result['skipped']) {
            Log::warning('create_deduction_type_subaccounts: '.$result['skipped']);

            return;
        }

        Log::info('create_deduction_type_subaccounts', $result);
    }

    /**
     * لا تراجع: الحساباتُ قد تحمل قيوداً بعد إنشائها، والربطُ قد يعدّله
     * المحاسب — فكُّه آلياً يُسقط الخصميات على الحساب العام بصمت.
     */
    public function down(): void
    {
        //
    }
};
