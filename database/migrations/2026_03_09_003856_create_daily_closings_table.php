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
        if (! Schema::hasTable('daily_closings')) {
            Schema::create('daily_closings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
            
                // Reported by employee
                $table->decimal('reported_sales', 15, 2)->default(0);           // صافي المبيعات
                $table->decimal('reported_cash_sales', 15, 2)->default(0);      // المباع نقدي
                $table->decimal('reported_receipts', 15, 2)->default(0);        // تحصيل سندات القبض
                $table->decimal('reported_collections', 15, 2)->default(0);     // إجمالي التحصيل
                $table->decimal('reported_expenses', 15, 2)->default(0);        // المصروفات
                $table->decimal('reported_stock_quantity', 15, 2)->default(0);  // المخزون
            
                // Calculated by system
                $table->decimal('system_sales', 15, 2)->default(0);
                $table->decimal('system_cash_sales', 15, 2)->default(0);
                $table->decimal('system_receipts', 15, 2)->default(0);
                $table->decimal('system_collections', 15, 2)->default(0);
                $table->decimal('system_expenses', 15, 2)->default(0);
                $table->decimal('system_stock_quantity', 15, 2)->default(0);
            
                $table->enum('status', ['success', 'error'])->default('success');
                $table->text('notes')->nullable();
            
                $table->timestamps();
            
                $table->foreign('employee_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['employee_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
