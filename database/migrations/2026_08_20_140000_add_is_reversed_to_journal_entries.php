<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * القيود العكسية تُميّز القيد الأصلي بـ`is_reversed` — والعمود غير موجود
     * في الجدول أصلاً، فكانت كل محاولة «إلغاء موافقة» تسقط بـ
     * Unknown column 'is_reversed' فلا يُعكس قيدٌ ولا تعود كميّة.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'is_reversed')) {
                $table->boolean('is_reversed')->default(false)->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'is_reversed')) {
                $table->dropColumn('is_reversed');
            }
        });
    }
};
