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
        if (! Schema::hasTable('sales_targets')) {
            Schema::create('sales_targets', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // اسم الهدف
                $table->enum('target_type', ['fixed', 'percentage'])->default('fixed'); // نوع الهدف: ثابت أو نسبة
                $table->decimal('target_value', 15, 2); // قيمة الهدف (مبلغ أو نسبة)
                $table->decimal('base_amount', 15, 2)->nullable(); // المبلغ الأساسي (للنسبة المئوية)
                $table->enum('status', ['active', 'inactive'])->default('active'); // حالة الهدف
                $table->text('description')->nullable(); // وصف الهدف
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
