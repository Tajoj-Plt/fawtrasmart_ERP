<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manufactur_orders_items', function (Blueprint $table) {
            // إضافة حقل الوحدة ومعامل التحويل للمواد الخام
            if (!Schema::hasColumn('manufactur_orders_items', 'raw_unit_id')) {
                $table->unsignedBigInteger('raw_unit_id')->nullable()->after('raw_production_stage_id');
            }
            if (!Schema::hasColumn('manufactur_orders_items', 'raw_conversion_factor')) {
                $table->decimal('raw_conversion_factor', 10, 4)->default(1)->after('raw_unit_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufactur_orders_items', function (Blueprint $table) {
            if (Schema::hasColumn('manufactur_orders_items', 'raw_unit_id')) {
                $table->dropColumn('raw_unit_id');
            }
            if (Schema::hasColumn('manufactur_orders_items', 'raw_conversion_factor')) {
                $table->dropColumn('raw_conversion_factor');
            }
        });
    }
};
