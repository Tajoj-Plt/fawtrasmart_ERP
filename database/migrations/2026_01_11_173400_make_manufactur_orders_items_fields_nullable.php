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
            // جعل جميع الحقول nullable لأن كل صف قد يحتوي على نوع واحد فقط من البيانات
            $table->unsignedBigInteger('raw_product_id')->nullable()->change();
            $table->unsignedBigInteger('raw_production_stage_id')->nullable()->change();
            
            $table->unsignedBigInteger('expenses_account_id')->nullable()->change();
            $table->unsignedBigInteger('expenses_production_stage_id')->nullable()->change();
            
            $table->unsignedBigInteger('workstation_id')->nullable()->change();
            $table->unsignedBigInteger('manu_production_stage_id')->nullable()->change();
            
            $table->unsignedBigInteger('end_life_product_id')->nullable()->change();
            $table->unsignedBigInteger('end_life_production_stage_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufactur_orders_items', function (Blueprint $table) {
            // إرجاع الحقول إلى NOT NULL
            $table->unsignedBigInteger('raw_product_id')->nullable(false)->change();
            $table->unsignedBigInteger('raw_production_stage_id')->nullable(false)->change();
            
            $table->unsignedBigInteger('expenses_account_id')->nullable(false)->change();
            $table->unsignedBigInteger('expenses_production_stage_id')->nullable(false)->change();
            
            $table->unsignedBigInteger('workstation_id')->nullable(false)->change();
            $table->unsignedBigInteger('manu_production_stage_id')->nullable(false)->change();
            
            $table->unsignedBigInteger('end_life_product_id')->nullable(false)->change();
            $table->unsignedBigInteger('end_life_production_stage_id')->nullable(false)->change();
        });
    }
};
