<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب الرسائل النصية.
 *
 * كانت نصوص الرسائل مكتوبة داخل الكود («رسالة تجريبية من نظام الإعدادات»)،
 * فلا يستطيع المستخدم تعديل صياغة رسالة واحدة تصل لعملائه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            Schema::create('sms_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique()->comment('الرمز الذي يستدعيه النظام');
                $table->string('name');
                $table->string('event', 60)->nullable()->comment('الحدث المرتبط');
                $table->text('body');
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();

        // قوالب أوّلية تغطّي الأحداث الشائعة — تُعدَّل من الشاشة لا من الكود
        DB::table('sms_templates')->insertOrIgnore([
            [
                'code' => 'test',
                'name' => 'رسالة تجريبية',
                'event' => 'manual',
                'body' => 'رسالة تجريبية من {business}. إن وصلتك فإعدادات الرسائل تعمل بشكل صحيح.',
                'variables' => json_encode(['business'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'invoice_created',
                'name' => 'إشعار فاتورة جديدة',
                'event' => 'invoice.created',
                'body' => 'عميلنا {customer}، صدرت فاتورتك رقم {number} بمبلغ {amount}. شكراً لتعاملك مع {business}.',
                'variables' => json_encode(['customer', 'number', 'amount', 'business'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'payment_received',
                'name' => 'إشعار استلام دفعة',
                'event' => 'payment.received',
                'body' => 'استلمنا دفعتك بمبلغ {amount} على الفاتورة {number}. المتبقي {remaining}. {business}',
                'variables' => json_encode(['amount', 'number', 'remaining', 'business'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'due_reminder',
                'name' => 'تذكير استحقاق',
                'event' => 'invoice.due',
                'body' => 'تذكير: فاتورتك رقم {number} بمبلغ {amount} تستحق بتاريخ {due_date}. {business}',
                'variables' => json_encode(['number', 'amount', 'due_date', 'business'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
