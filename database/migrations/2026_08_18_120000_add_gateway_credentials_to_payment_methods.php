<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات اعتماد بوابة الدفع على طريقة الدفع.
 *
 * المفتاح السرّي وسرّ الويب هوك نصّان طويلان لا سلاسل قصيرة: التعمية
 * بـ`APP_KEY` تُطيل القيمة أضعافاً، و`string` يقصّها فيصل إلى البوابة
 * مفتاحٌ ناقص يفشل بلا رسالة مفهومة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_methods', 'public_key')) {
                $table->string('public_key')->nullable()->after('instructions');
            }
            if (! Schema::hasColumn('payment_methods', 'secret_key')) {
                $table->text('secret_key')->nullable()->after('public_key');
            }
            if (! Schema::hasColumn('payment_methods', 'webhook_url')) {
                $table->string('webhook_url')->nullable()->after('secret_key');
            }
            if (! Schema::hasColumn('payment_methods', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable()->after('webhook_url');
            }
            if (! Schema::hasColumn('payment_methods', 'gateway_mode')) {
                $table->string('gateway_mode', 10)->default('test')->after('webhook_secret');
            }
            // مسار ملفّ الإعدادات المولَّد — يُحفظ ليُحذف الملف القديم عند
            // تغيّر الرمز، فلا تتراكم ملفات يتيمة بأسماء قديمة
            if (! Schema::hasColumn('payment_methods', 'config_file_path')) {
                $table->string('config_file_path')->nullable()->after('gateway_mode');
            }
            if (! Schema::hasColumn('payment_methods', 'config_file_generated_at')) {
                $table->timestamp('config_file_generated_at')->nullable()->after('config_file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            foreach ([
                'public_key', 'secret_key', 'webhook_url', 'webhook_secret',
                'gateway_mode', 'config_file_path', 'config_file_generated_at',
            ] as $column) {
                if (Schema::hasColumn('payment_methods', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
