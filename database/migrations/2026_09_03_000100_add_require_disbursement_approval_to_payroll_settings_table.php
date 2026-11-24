<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اشتراطُ اعتمادِ الصرف قبل خروج النقد.
 *
 * كان `PayrollRun::canBeDisbursed()` يشترط اعتمادَ المسير **فقط** إن كانت
 * الصرفةُ داخل مسير:
 *
 *     if ($this->payroll_batch_id) { ... يجب أن يكون معتمداً ... }
 *
 * والصرفاتُ التي يولّدها الكرون تُنشأ بلا مسير، و`auto_disburse_payroll_runs`
 * افتراضُه التفعيل. فالمحصّلة أنّ القسيمةَ تُعتمد مرّةً واحدة، ثمّ يخرج النقدُ
 * من الخزينة كلَّ شهرٍ بلا تصريحٍ لهذه الدفعة بعينها وبلا معتمِدٍ مسجَّل —
 * و«مسير الصرفات»، وهو بوابةُ التصريح الوحيدة، اختياريٌّ يسهل ألّا يُستعمل.
 *
 * **الافتراضُ هنا التفعيل عن قصد.** ضابطٌ ماليٌّ يجب أن يُخفق مُقفَلاً لا
 * مفتوحاً: منشأةٌ رقّت النظامَ ولم تُنشئ مسيراً بعد تتوقّف صرفاتُها المعلَّقة
 * وتُخبَر بالسبب في الشاشة وفي مخرَج الكرون، وهذا أهونُ من أن يستمرّ خروجُ
 * النقد بلا تصريح. ومن أراد السلوكَ القديم فمفتاحٌ واحدٌ في «إعدادات الرواتب».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settings')) {
            return;
        }

        if (! Schema::hasColumn('payroll_settings', 'require_disbursement_approval')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'require_disbursement_approval')) {
                    $table->boolean('require_disbursement_approval')
                        ->default(true)
                        ->after('auto_disburse_payroll_runs');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'require_disbursement_approval')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->dropColumn('require_disbursement_approval');
            });
        }
    }
};
