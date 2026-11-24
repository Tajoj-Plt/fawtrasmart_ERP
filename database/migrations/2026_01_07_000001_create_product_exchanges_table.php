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
        // جدول عمليات التبديل الرئيسي
        if (! Schema::hasTable('product_exchanges')) {
            Schema::create('product_exchanges', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique()->comment('رقم عملية التبديل');


                // المفاتيح الأجنبية
                $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
                $table->unsignedBigInteger(column: 'employee_id')->nullable();
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreignId('storehouse_id')->constrained('store_houses')->onDelete('restrict');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

                // التاريخ والملاحظات
                $table->date('exchange_date')->comment('تاريخ التبديل');
                $table->text('notes')->nullable()->comment('ملاحظات إضافية');

                // الإجماليات
                $table->decimal('total_value', 15, 2)->default(0)->comment('إجمالي قيمة التبديل');

                // الحالة
                $table->tinyInteger('status')->default(1)->comment('1=مكتمل');

                // الحقول الافتراضية
                $table->timestamps();

                // الفهارس
                $table->index('client_id');
                $table->index('employee_id');
                $table->index('storehouse_id');
                $table->index('exchange_date');
                $table->index('code');
            });
        }

        // جدول تفاصيل التبديل (المنتجات)
        if (! Schema::hasTable('product_exchange_items')) {
            Schema::create('product_exchange_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_exchange_id')->constrained('product_exchanges')->onDelete('cascade');
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

                // الكميات
                $table->integer('quantity')->default(1)->comment('الكمية المُبدلة');
                $table->decimal('unit_price', 15, 2)->default(0)->comment('سعر الوحدة');
                $table->decimal('total', 15, 2)->default(0)->comment('الإجمالي');

                // سبب التبديل لكل منتج
                $table->enum('reason', ['damaged', 'missing_perfume', 'defective', 'other'])->default('other')->comment('سبب التبديل');
                $table->text('reason_notes')->nullable()->comment('ملاحظات السبب - للأخرى');

                $table->timestamps();

                // الفهارس
                $table->index('product_exchange_id');
                $table->index('product_id');
                $table->index('reason');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_exchange_items');
        Schema::dropIfExists('product_exchanges');
    }
};
