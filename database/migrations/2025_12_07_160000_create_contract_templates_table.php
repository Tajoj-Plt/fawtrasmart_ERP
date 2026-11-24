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
        if (! Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // اسم القالب
                $table->text('description')->nullable(); // وصف القالب
                $table->enum('contract_type', ['دائم', 'مؤقت', 'متعاقد', 'جزئي'])->default('دائم'); // نوع العقد
                $table->text('header')->nullable(); // ترويسة العقد
                $table->longText('content'); // محتوى العقد (HTML)
                $table->text('footer')->nullable(); // تذييل العقد
                $table->text('terms_and_conditions')->nullable(); // الشروط والأحكام
                $table->boolean('is_active')->default(true); // نشط/غير نشط
                $table->integer('default_duration_months')->nullable(); // مدة العقد الافتراضية بالأشهر
                $table->boolean('has_probation_period')->default(false); // هل يوجد فترة تجريبية
                $table->integer('probation_duration_months')->nullable(); // مدة الفترة التجريبية
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
