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
            // نوع الهالك: scrap = قابل للاسترداد (يُطرح من التكلفة), waste = غير قابل للاسترداد (يُضاف للتكلفة)
            if (! Schema::hasColumn('manufactur_orders_items', 'end_life_waste_type')) {
                $table->string('end_life_waste_type', 20)->default('scrap')->after('end_life_product_id');
            }
        });
        
        Schema::table('production_materials_items', function (Blueprint $table) {
            // نوع الهالك في قوائم المواد أيضاً
            if (! Schema::hasColumn('production_materials_items', 'end_life_waste_type')) {
                $table->string('end_life_waste_type', 20)->default('scrap')->after('end_life_product_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufactur_orders_items', function (Blueprint $table) {
            $table->dropColumn('end_life_waste_type');
        });
        
        Schema::table('production_materials_items', function (Blueprint $table) {
            $table->dropColumn('end_life_waste_type');
        });
    }
};
