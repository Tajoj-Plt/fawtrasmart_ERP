<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    { 
        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id('log_id');
                $table->unsignedBigInteger('template_id')->nullable()->comment('معرف القالب');
                $table->string('recipient_email', 150)->comment('بريد المستلم');
                $table->string('recipient_type', 30)->default('customer')->comment('نوع المستلم');
                $table->unsignedBigInteger('recipient_id')->nullable()->comment('معرف المستلم');
                $table->string('subject', 200)->comment('عنوان البريد');
                $table->longText('email_content')->comment('محتوى البريد');
                $table->string('status', 30)->default('pending')->comment('حالة البريد');
                $table->timestamp('sent_at')->nullable()->comment('وقت الإرسال');
                $table->text('error_message')->nullable()->comment('رسالة الخطأ');
                $table->json('metadata')->nullable()->comment('بيانات إضافية');
                $table->timestamps();
            
                // Foreign key
                $table->foreign('template_id')
                    ->references('template_id')
                    ->on('email_templates')
                    ->onDelete('set null');
            
                $table->index(['recipient_type', 'recipient_id']);
                $table->index(['status', 'sent_at']);
                $table->index('recipient_email');
            });
        }
    }

    public function down(): void
    { 
        Schema::dropIfExists('email_logs');
    }
};