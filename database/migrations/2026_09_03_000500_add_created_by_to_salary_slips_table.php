<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مَن أنشأ القسيمة؟ — عمودٌ لم يكن.
 *
 * `salary_slips` يحمل `approved_by` و`approved_at` و`stopped_by`، ولا يحمل
 * `created_by`. فمن حدّد أجرَ الموظف لا يُعرف إلا من صفٍّ في جدول `logs` —
 * وهو جدولٌ تُتيح شاشتُه الحذفَ والحذفَ الجملي والتنظيف.
 *
 * وأثرُ ذلك أبعدُ من نقصِ بيان: لا يستطيع النظام أن يشترط أن يكون معتمِدُ
 * الأجر غيرَ من حدّده — وهو أوّلُ ما يسأل عنه المدقّق في دورةٍ تُخرج نقداً.
 *
 * والملءُ لما مضى من `logs`: أوّلُ سطرٍ من نوع `salary_slip_log` لكلِّ
 * قسيمة هو سطرُ إنشائها، وصاحبُه مُنشئُها. وما لا سطرَ له يبقى فارغاً —
 * ولا يُخمَّن.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_slips')) {
            return;
        }

        if (! Schema::hasColumn('salary_slips', 'created_by')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_slips', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('status');
                }
            });
        }

        if (! Schema::hasTable('logs')) {
            return;
        }

        /*
            أقدمُ سطرِ سجلٍّ لكلِّ قسيمة — هو سطرُ الإنشاء. ويُقرأ صاحبُه
            دفعةً واحدة لا باستعلامٍ لكلّ صفّ.
        */
        $creators = DB::table('logs')
            ->where('type', 'salary_slip_log')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->get(['type_id', 'created_by'])
            ->unique('type_id')
            ->pluck('created_by', 'type_id');

        foreach ($creators as $slipId => $userId) {
            DB::table('salary_slips')
                ->where('id', $slipId)
                ->whereNull('created_by')
                ->update(['created_by' => $userId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_slips') && Schema::hasColumn('salary_slips', 'created_by')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
