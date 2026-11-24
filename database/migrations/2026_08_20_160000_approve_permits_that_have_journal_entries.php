<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * إذنٌ مخزني له قيدٌ مُرحَّل هو إذنٌ نافذ — فلا يصحّ أن يبقى «معلَّقاً»،
     * وإلّا رحّل اعتمادُه القيدَ مرّةً ثانية.
     *
     * تشمل هذه التسوية الأذون المولَّدة تلقائياً من بقيّة الوحدات (المبيعات
     * والمشتريات ونقطة البيع والتصنيع والمتجر) — فهي تُنشئ قيدها فور
     * الإنشاء ولا تمرّ بشاشة الاعتماد أصلاً.
     */
    public function up(): void
    {
        $affected = DB::table('warehouse_permits')
            ->where('status', 'pending')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.reference_id', 'warehouse_permits.id')
                    ->whereNotNull('journal_entries.reference_id');
            })
            ->update(['status' => 'apporod']);

        if ($affected > 0) {
            echo '  اعتُمد '.$affected.' إذناً كان معلَّقاً وله قيدٌ مُرحَّل'.PHP_EOL;
        }
    }

    public function down(): void
    {
        // لا تراجع: إعادتها «معلَّقة» تُعيد خطر ازدواج القيد
    }
};
