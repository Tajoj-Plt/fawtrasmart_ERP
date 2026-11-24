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
        if (! Schema::hasTable('product_barcode_settings')) {
            Schema::create('product_barcode_settings', function (Blueprint $table) {
                $table->id();
                $table->string('barcode_type')->default('CODE128')->comment('نوع الباركود: CODE128, EAN13');
                $table->boolean('include_weight')->default(false)->comment('تفعيل الباركود المتضمن الوزن');
                $table->string('barcode_format')->nullable()->comment('صيغة الباركود (مثال: XXXXXXXXWWWWWPPPPN)');
                $table->string('weight_unit_division')->nullable()->comment('تقسيم وحدة الوزن');
                $table->string('currency_division')->nullable()->comment('تقسيم العملة');
                $table->integer('barcode_width')->default(2)->comment('عرض الباركود بالبكسل');
                $table->integer('barcode_height')->default(50)->comment('ارتفاع الباركود بالبكسل');
                $table->boolean('display_value')->default(true)->comment('عرض قيمة الباركود تحته');
                $table->integer('font_size')->default(12)->comment('حجم الخط');
                $table->integer('margin')->default(10)->comment('الهامش حول الباركود');
                $table->timestamps();
                $table->unique('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_barcode_settings');
    }
};
