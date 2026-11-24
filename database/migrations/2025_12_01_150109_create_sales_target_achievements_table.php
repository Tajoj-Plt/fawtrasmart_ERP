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
        if (! Schema::hasTable('sales_target_achievements')) {
            Schema::create('sales_target_achievements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_target_id'); // الهدف المرتبط
                $table->unsignedBigInteger('employee_id'); // الموظف
                $table->unsignedBigInteger('treasury_id')->nullable(); // الخزينة المرتبطة بالموظف
                $table->decimal('achieved_amount', 15, 2)->default(0); // المبلغ المحقق
                $table->decimal('target_amount', 15, 2); // مبلغ الهدف المطلوب
                $table->decimal('achievement_percentage', 5, 2)->default(0); // نسبة الإنجاز
                $table->integer('payments_count')->default(0); // عدد المدفوعات
                $table->timestamp('last_payment_at')->nullable(); // آخر دفعة
                $table->timestamps();
            
                $table->foreign('sales_target_id')->references('id')->on('sales_targets')->onDelete('cascade');
                $table->foreign('treasury_id')->references('id')->on('accounts')->onDelete('set null');
                $table->unique(['sales_target_id', 'employee_id']); // موظف واحد لكل هدف
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_target_achievements');
    }
};
