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
        if (! Schema::hasTable('sales_target_settlements')) {
            Schema::create('sales_target_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_target_id'); // الهدف المرتبط
                $table->date('from_date'); // بداية الفترة المسواة
                $table->date('to_date'); // نهاية الفترة المسواة
                $table->text('notes')->nullable(); // ملاحظات التسوية
                $table->unsignedBigInteger('settled_by'); // من قام بالتسوية
                $table->timestamps();
            
                $table->foreign('sales_target_id')->references('id')->on('sales_targets')->onDelete('cascade');
            
                // منع تسوية نفس الفترة أكثر من مرة لنفس الهدف
                $table->unique(['sales_target_id', 'from_date', 'to_date'], 'unique_target_period');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_target_settlements');
    }
};
