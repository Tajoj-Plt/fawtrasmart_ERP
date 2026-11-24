<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الخدمة القابلة للحجز — **امتدادٌ للمنتج لا بديلٌ عنه**.
 *
 * النظام يعرف الخدمات أصلاً: `products` بنوع `ProductType::SERVICES`، ولها
 * سعرٌ وضريبةٌ وحسابٌ محاسبيّ وتظهر في بنود الفواتير. فإنشاءُ كتالوج
 * خدماتٍ ثانٍ هنا يعني سعرين لخدمةٍ واحدة يفترقان أوّلَ تعديل.
 *
 * لكنّ المنتج لا يعرف شيئاً ممّا يحتاجه الحجز: كم تستغرق؟ كم يلزم قبلها
 * من تحضير وبعدها من تنظيف؟ من الموظفون المؤهَّلون لأدائها؟ كم مرّة تُحجَز
 * في اليوم؟
 *
 * فهذا الجدول يحمل **البُعد الزمني وحده**، ويشير إلى المنتج بـ`product_id`
 * لبقيّة الأمر. والسعرُ هنا `nullable` عمداً: فارغاً يعني «اقرأه من
 * المنتج»، ومملوءاً يعني سعرَ حجزٍ يخالف سعرَ البيع المباشر.
 *
 * و`product_id` نفسُه `nullable`: خدمةٌ تُحجَز ولا تُفوتَر (استشارة
 * مجّانية، معاينة) لا تحتاج منتجاً — وإلزامُه يجبر المستخدم على تلويث
 * كتالوج المنتجات بأصنافٍ لا تُباع.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_services')) {
            return;
        }

        Schema::create('reservation_services', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();

            /* الربط بكتالوج المنتجات — فارغاً تكون الخدمة غير قابلة للفوترة */
            $table->unsignedBigInteger('product_id')->nullable();

            /* الفرع — فارغاً تكون الخدمة متاحة في كل الفروع */
            $table->unsignedBigInteger('branch_id')->nullable();

            // ─────────── البُعد الزمني ───────────
            $table->unsignedInteger('duration')->default(30)->comment('مدّة الخدمة بالدقائق');
            $table->unsignedInteger('buffer_before')->default(0)->comment('وقت التحضير قبل الموعد — يُحجَز ولا يُحتسب في السعر');
            $table->unsignedInteger('buffer_after')->default(0)->comment('وقت التنظيف بعد الموعد');
            $table->unsignedInteger('max_daily')->nullable()->comment('الحدّ الأقصى لحجوزات هذه الخدمة يومياً — فارغاً بلا حدّ');

            // ─────────── المال ───────────
            /* فارغاً ⇒ يُقرأ من المنتج المربوط. انظر ترويسة الملف. */
            $table->decimal('price', 15, 2)->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'reservation_services_active_sort_index');
            $table->index('branch_id', 'reservation_services_branch_index');
            $table->index('product_id', 'reservation_services_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_services');
    }
};
