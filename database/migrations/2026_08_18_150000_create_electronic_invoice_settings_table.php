<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الفاتورة الإلكترونية (الفوترة/ZATCA).
 *
 * كانت الشاشات الثلاث واجهةً بلا خلفية: بطاقتا «تفعيل» زرّاهما `href=""`،
 * ونموذجا الإعدادات `action=""` أي يُرسِلان إلى الصفحة نفسها، والمتحكّم
 * ثلاث دوالّ لا تفعل إلا `return view(...)`. ولا جدول ولا نموذج ولا
 * تحقّق. فكان المستخدم يملأ الرقم الضريبي والعنوان ويضغط حفظ فلا يُحفظ
 * شيء ولا تظهر رسالة.
 *
 * صفّ واحد للمنشأة — `ElectronicInvoiceSetting::current()` يُنشئه عند
 * أوّل زيارة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('electronic_invoice_settings')) {
            Schema::create('electronic_invoice_settings', function (Blueprint $table) {
                $table->id();

                // ── التفعيل والبيئة ──
                $table->boolean('is_enabled')->default(false);
                // sandbox = منصّة المحاكاة، production = البوابة الفعلية
                $table->string('environment', 20)->default('sandbox');

                // ── سلوك الإرسال ──
                $table->boolean('auto_send_after_create')->default(false);
                $table->boolean('send_on_payment')->default(false);
                $table->boolean('attach_qr_to_print')->default(true);

                // ── بيانات المنشأة المطلوبة للتسجيل ──
                $table->string('common_name')->nullable();
                $table->string('company_name')->nullable();
                $table->string('tax_number', 30)->nullable();
                $table->string('tax_group_name')->nullable();
                $table->string('commercial_register', 30)->nullable();
                $table->string('country_code', 2)->default('SA');
                $table->string('business_category')->nullable();
                $table->string('address')->nullable();

                // ── الربط ──
                $table->string('api_base_url')->nullable();
                // نصّ لا سلسلة قصيرة: التعمية بـ`APP_KEY` تُطيل القيمة أضعافاً،
                // و`string` يقصّها فيصل إلى البوابة مفتاحٌ ناقص يفشل بلا رسالة مفهومة
                $table->text('client_id')->nullable();
                $table->text('client_secret')->nullable();

                // ── آخر محاولة اتصال ──
                $table->timestamp('last_checked_at')->nullable();
                $table->string('last_check_status', 20)->nullable();
                $table->text('last_check_message')->nullable();

                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_invoice_settings');
    }
};
