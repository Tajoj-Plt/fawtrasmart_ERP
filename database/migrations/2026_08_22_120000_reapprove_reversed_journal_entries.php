<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعادة اعتماد القيود التي نُزع اعتمادُها عند الإلغاء.
 *
 * كان إلغاء سند القبض يُرجع القيد الأصلي إلى `status = 0` — أي «معلّق»،
 * وهي حالةُ ما لم يُعتمد بعد. والقيد قد اعتُمد ورُحِّل، وإنما عُكس بقيدٍ
 * آخر يقف إلى جانبه. فبقيت في الدفتر قيودٌ تبدو منتظرةً اعتماداً لا
 * تنتظره، وتُحصى في اللوحة ضمن «المعلّقة».
 *
 * الشيفرة صُحّحت في `IncomesController::cancel`؛ هذه الترحيلة تُصلح ما
 * كُتب قبلها. ولا تمسّ إلّا ما وصفُه يشهد أنه عُكس فعلاً — فقيدٌ معلّق
 * حقيقةً يبقى معلّقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        $fixed = DB::table('journal_entries')
            ->where('status', 0)
            ->where('description', 'LIKE', '%[ملغي - القيد العكسي #%')
            ->update(['status' => 1]);

        if ($fixed > 0) {
            DB::table('journal_entries')
                ->where('status', 1)
                ->where('description', 'LIKE', '%[ملغي - القيد العكسي #%')
                ->whereNull('approved_by_employee')
                ->update(['approved_by_employee' => DB::raw('created_by_employee')]);
        }
    }

    public function down(): void
    {
        // لا عكس: إرجاعها إلى «معلّق» يُعيد الخطأ نفسه الذي أُصلح.
    }
};
