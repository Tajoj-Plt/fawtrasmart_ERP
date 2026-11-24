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
        if (! Schema::hasTable('client_payment_cards')) {
            Schema::create('client_payment_cards', function (Blueprint $table) {
                $table->id();
            
                // العلاقة مع العميل
                $table->foreignId('client_id')
                      ->constrained('clients')
                      ->onDelete('cascade');
            
                // العلاقة مع طريقة الدفع (اختياري)
                $table->foreignId('payment_method_id')
                      ->nullable()
                      ->constrained('payment_methods')
                      ->onDelete('set null');
            
                // نوع البطاقة
                $table->enum('card_type', ['visa', 'mastercard', 'mada', 'amex', 'other'])->default('visa');
            
                // اسم حامل البطاقة
                $table->string('card_holder_name');
            
                // آخر 4 أرقام من البطاقة (لعرضها للعميل)
                $table->string('card_last_four', 4);
            
                // أول 6 أرقام (BIN) - اختياري للتعرف على البنك
                $table->string('card_bin', 6)->nullable();
            
                // شهر انتهاء الصلاحية
                $table->tinyInteger('expiry_month');
            
                // سنة انتهاء الصلاحية
                $table->smallInteger('expiry_year');
            
                // Token من بوابة الدفع (لإعادة الاستخدام)
                $table->string('token')->nullable();
            
                // معرف البطاقة من بوابة الدفع
                $table->string('gateway_card_id')->nullable();
            
                // اسم بوابة الدفع
                $table->string('gateway_name')->nullable();
            
                // هل البطاقة افتراضية
                $table->boolean('is_default')->default(false);
            
                // هل البطاقة نشطة
                $table->boolean('is_active')->default(true);
            
                // اسم البطاقة المختصر (يعطيه العميل)
                $table->string('nickname')->nullable();
            
                // عنوان الفاتورة
                $table->string('billing_address')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_country')->nullable();
                $table->string('billing_postal_code')->nullable();
            
                // بيانات إضافية (JSON)
                $table->json('metadata')->nullable();
            
                // آخر استخدام
                $table->timestamp('last_used_at')->nullable();
            
                $table->timestamps();
                $table->softDeletes();

                // فهارس
                $table->index(['client_id', 'is_default']);
                $table->index(['client_id', 'is_active']);
                $table->index('card_last_four');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payment_cards');
    }
};
