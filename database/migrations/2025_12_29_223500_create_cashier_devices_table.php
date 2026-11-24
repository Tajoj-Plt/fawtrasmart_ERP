<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cashier_devices')) {
            Schema::create('cashier_devices', function (Blueprint $table) {
                $table->id();
                $table->string('device_name');
                $table->foreignId('store_id')->nullable(); // Assuming StoreHouse model implies store_houses table or similar
                $table->foreignId('main_warehouse_id')->nullable(); // Assuming Warehouse model
                $table->foreignId('main_category_id')->nullable(); // Assuming Category model
                $table->string('device_status')->default('active');
                $table->string('device_image')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_devices');
    }
};
