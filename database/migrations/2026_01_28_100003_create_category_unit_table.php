<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إنشاء الجدول الوسيط بين الأصناف والوحدات
     * علاقة many-to-many
     */
    public function up(): void
    {
        if (! Schema::hasTable('category_unit')) {
            Schema::create('category_unit', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id')->comment('الصنف');
                $table->unsignedBigInteger('unit_id')->comment('الوحدة');
                $table->decimal('conversion_factor', 15, 4)->default(1)->comment('معامل التحويل للوحدة الأساسية');
                $table->boolean('is_base_unit')->default(false)->comment('هل هي الوحدة الأساسية؟');
                $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                $table->timestamps();

                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('cascade');

                $table->foreign('unit_id')
                    ->references('id')
                    ->on('units')
                    ->onDelete('cascade');

                // كل صنف يمكن أن يرتبط بوحدة مرة واحدة فقط
                $table->unique(['category_id', 'unit_id'], 'category_unit_unique');
            
                $table->index('category_id');
                $table->index('unit_id');
                $table->index('is_base_unit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_unit');
    }
};
