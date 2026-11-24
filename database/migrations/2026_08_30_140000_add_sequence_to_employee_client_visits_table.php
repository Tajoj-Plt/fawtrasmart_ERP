<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ترتيب العميل داخل يوم خط السير — الآلية الوحيدة الممكنة اليوم لحفظ
     * ترتيبٍ يدوي (`itinerary_order_strategy = manual`)، أو لإعادة ترتيب
     * المندوب لخطّه بنفسه (`itinerary_allow_reorder_by_employee`).
     */
    public function up(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_client_visits', 'sequence')) {
                $table->unsignedInteger('sequence')->nullable()->after('week_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            $table->dropColumn('sequence');
        });
    }
};
