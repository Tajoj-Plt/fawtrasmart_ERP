<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{ 
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id('template_id');
                $table->string('template_code', 50)->unique()->comment('كود فريد للقالب');
                $table->string('template_name', 100)->comment('اسم القالب');
                $table->string('template_category', 30)->default('notifications')->comment('تصنيف القالب');
                $table->string('subject', 200)->comment('عنوان البريد');
                $table->longText('html_content')->comment('محتوى HTML');
                $table->text('text_content')->nullable()->comment('محتوى نصي بديل');
                $table->json('variables')->nullable()->comment('المتغيرات المتاحة');
                $table->string('language', 5)->default('ar')->comment('لغة القالب');
                $table->boolean('is_active')->default(true)->comment('حالة التفعيل');
                $table->timestamps();
            
                $table->index(['template_category', 'is_active']);
                $table->index('template_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
}; 