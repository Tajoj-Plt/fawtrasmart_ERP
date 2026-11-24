<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إنشاء جدول وحدات المنتج
     * يرتبط بالجدول الوسيط category_unit
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_units')) {
            Schema::create('product_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->comment('المنتج');
                $table->unsignedBigInteger('category_unit_id')->comment('وحدة الصنف (من الجدول الوسيط)');
                $table->string('barcode')->nullable()->comment('الباركود الفريد');
                $table->decimal('purchase_price', 15, 2)->nullable()->comment('سعر الشراء');
                $table->decimal('sale_price', 15, 2)->nullable()->comment('سعر البيع');
                $table->decimal('min_sale_price', 15, 2)->nullable()->comment('أقل سعر بيع مسموح');
                $table->decimal('wholesale_price', 15, 2)->nullable()->comment('سعر الجملة');
                $table->boolean('is_default')->default(false)->comment('الوحدة الافتراضية');
                $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                $table->timestamps();

                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');

                $table->foreign('category_unit_id')
                    ->references('id')
                    ->on('category_unit')
                    ->onDelete('restrict');

                // كل منتج يمكن أن يرتبط بوحدة صنف مرة واحدة فقط
                $table->unique(['product_id', 'category_unit_id'], 'product_unit_unique');
            
                // فهرس للباركود (فريد ولكن nullable)
                $table->unique('barcode');
            
                $table->index('product_id');
                $table->index('category_unit_id');
                $table->index('is_default');
                $table->index('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
