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
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique()->comment('اسم البراند');
                $table->string('slug', 100)->unique()->comment('الاسم المختصر للرابط');
                $table->text('description')->nullable()->comment('وصف البراند');
                $table->string('logo_path', 255)->nullable()->comment('مسار الشعار');
                $table->string('country_of_origin', 50)->nullable()->comment('دولة المنشأ');
                $table->string('contact_email', 100)->nullable()->comment('البريد الإلكتروني');
                $table->string('contact_phone', 20)->nullable()->comment('رقم الهاتف');
                $table->text('website')->nullable()->comment('موقع الويب');

                // حالة البراند
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->comment('حالة البراند');

                // معلومات العرض والترتيب
                $table->integer('display_order')->default(0)->comment('ترتيب العرض');
                $table->boolean('is_featured')->default(false)->comment('هل البراند مميز');

                // معلومات إضافية
                $table->string('notes', 500)->nullable()->comment('ملاحظات');

                // التتبع
                $table->unsignedBigInteger('created_by')->nullable()->comment('معرف المستخدم الذي أنشأ السجل');
                $table->unsignedBigInteger('updated_by')->nullable()->comment('معرف المستخدم الذي عدّل السجل');

                $table->timestamps();
                $table->softDeletes();

                // الفهارس
                $table->index('status');
                $table->index('is_featured');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
