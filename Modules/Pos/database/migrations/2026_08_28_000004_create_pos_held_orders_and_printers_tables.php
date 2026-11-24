<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الفواتيرُ المعلَّقة والطابعات — ميزةٌ ميتة، وأخرى غائبة.
 *
 * ─────────────────────── المعلَّقة: ثلاثُ نوافذ على العدم ───────────────────────
 *
 * كانت في المتحكّم ثلاثُ نقاط نهاية كاملة — `getHeldInvoices()` و
 * `resumeHeldInvoice()` و`deleteHeldInvoice()` — كلُّها تستعلم عن
 * `invoices.status = 'held'`.
 *
 * **ولا سطرَ واحدٌ في المستودع كلِّه يُنشئ فاتورةً بهذه الحالة.** لا نقطةَ
 * نهايةٍ للتعليق أصلاً. فالميزةُ ثلاثُ واجهاتِ قراءةٍ وحذفٍ فوق بياناتٍ لا
 * تُخلق أبداً — تظهر في الشاشة، ويضغطها الكاشير، فتُرجع قائمةً فارغة دائماً.
 *
 * ─────────────────────── ولماذا جدولٌ مستقل لا حالةٌ على الفاتورة؟ ───────────────────────
 *
 * الفاتورةُ المعلَّقة **ليست فاتورة**: لم يُخصم لها مخزون، ولا قيدَ لها، ولا
 * رقمَ مستندٍ استُهلك لها. وضعُها في `invoices` بحالةٍ خاصّة يُلوّث كلَّ
 * استعلامٍ على الجدول — وكلُّ تقريرٍ ينسى `where status != 'held'` يحتسبها
 * مبيعاتٍ حقيقية.
 *
 * فهي سلّةٌ محفوظة: `payload` يحمل السطور كما هي في الشاشة، وتُستأنف
 * فتُعاد إلى السلّة، ولا تصير فاتورةً إلّا عند الدفع.
 *
 * ─────────────────────── الطابعات ───────────────────────
 *
 * `pos_general_settings.invoice_template` كان الضبطَ الوحيد: `thermal` أو
 * `electronic`. لا عرضَ ورق، ولا عددَ نسخ، ولا طابعةَ مطبخٍ منفصلة، ولا
 * ربطَ طابعةٍ بجهاز. فمنشأةٌ بجهازين وطابعتين لا تستطيع التمييز بينهما.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_held_orders')) {
            Schema::create('pos_held_orders', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('device_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('cashier_id');
                $table->unsignedBigInteger('client_id')->nullable();

                /** تسميةٌ يكتبها الكاشير ليعرفها: «الطاولة ٣»، «أبو محمد». */
                $table->string('label')->nullable();

                /** السطورُ كما هي في السلّة — لا بنودُ فاتورة. */
                $table->json('payload');

                $table->decimal('estimated_total', 15, 2)->default(0);
                $table->string('currency', 10)->nullable();
                $table->unsignedSmallInteger('items_count')->default(0);

                /*
                    انتهاءُ الصلاحية.

                    سلّةٌ معلَّقة تُنسى تبقى إلى الأبد فتتراكم. والصلاحيةُ
                    تنتهي بانتهاء الجلسة في الغالب — لكنّ العمود يسمح
                    بسياسةٍ أطول حين تريدها المنشأة (مطعمٌ يُبقي الطاولات
                    مفتوحةً عبر ورديتين).
                */
                $table->datetime('expires_at')->nullable();

                $table->softDeletes();
                $table->timestamps();

                $table->index(['session_id', 'cashier_id'], 'pos_held_session_cashier_idx');
                $table->index('expires_at', 'pos_held_expires_idx');
            });
        }

        if (! Schema::hasTable('pos_printers')) {
            Schema::create('pos_printers', function (Blueprint $table) {
                $table->id();

                $table->string('name');
                $table->unsignedBigInteger('device_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();

                /** `receipt` | `a4` | `kitchen` | `label` */
                $table->string('type')->default('receipt');

                /** `usb` | `network` | `bluetooth` | `cloud` */
                $table->string('connection')->default('usb');

                $table->string('address')->nullable();

                /** عرضُ الورق بالملّيمتر — ٥٨ أو ٨٠ في الحراري. */
                $table->unsignedSmallInteger('paper_width')->default(80);

                $table->unsignedTinyInteger('copies')->default(1);
                $table->boolean('auto_cut')->default(true);
                $table->boolean('open_drawer')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);

                $table->json('settings')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();

                $table->softDeletes();
                $table->timestamps();

                $table->index(['branch_id', 'type'], 'pos_printers_branch_type_idx');
                $table->index('device_id', 'pos_printers_device_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_held_orders');
        Schema::dropIfExists('pos_printers');
    }
};
