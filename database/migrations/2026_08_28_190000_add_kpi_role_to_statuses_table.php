<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            // 'active' | 'stopped' | null — أيّ حالة عميل (section = null) تُحتسب
            // نشطة/موقوفة في مؤشرات لوحة التحكّم. بلا قيمة = لا تُحتسب في أيّهما.
            if (! Schema::hasColumn('statuses', 'kpi_role')) {
                $table->string('kpi_role', 20)->nullable()->after('section');
            }
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn('kpi_role');
        });
    }
};
