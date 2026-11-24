<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حجزُ المخزون — الكمية الموعودة لمستندٍ قبل صرفها.
 *
 * ── المشكلة ──────────────────────────────────────────────────────────
 *
 * للمخزون اليوم رقمٌ واحد: الرصيد الفعلي. فأمرُ تصنيعٍ يحتاج ١٠٠ كجم
 * دقيق لا يترك أثراً على المخزون حتى لحظة الصرف. وبينهما:
 *
 *  · أمرٌ ثانٍ يُنشأ على الكمية نفسها، فيَعِد كلاهما بما لا يكفيهما.
 *  · فاتورةُ بيعٍ تُخرج الدقيق، فيفشل الصرفُ حين يأتي دورُه.
 *  · ولا شاشةَ تُظهر أن الكمية «موعودة» لأحد.
 *
 * فالرصيدُ الفعلي يقول ١٠٠ بينما المتاحُ للتصرّف صفر.
 *
 * ── القاعدة ──────────────────────────────────────────────────────────
 *
 *     المتاح  =  الرصيد الفعلي  −  المحجوز
 *
 * ── التصميم ──────────────────────────────────────────────────────────
 *
 * صفٌّ لكل (منتج، مستودع، مستند). والربطُ متعدّد الأشكال
 * (`reference_type` + `reference_id`) على نمط `warehouse_permits`
 * و`journal_entries` في هذا النظام — فيصلح لاحقاً لأوامر البيع
 * والتحويلات، لا لأوامر التصنيع وحدها.
 *
 * ── الحالات ──────────────────────────────────────────────────────────
 *
 *   active    حجزٌ قائم — يُخصم من المتاح
 *   consumed  صُرفت مادتُه فعلاً — لم يعد يُخصم (تحوّل إلى حركة مخزون)
 *   released  فُكّ بلا صرف — إلغاءُ الأمر أو حذفُه
 *
 * ولا يُحذف صفٌّ أبداً: الحجزُ سجلٌّ يُسأل عنه لاحقاً — «لماذا لم يكن
 * المتاح كافياً يوم الثلاثاء؟». الحذفُ يمحو الجواب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            return;
        }

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('store_house_id')->constrained('store_houses')->cascadeOnDelete();

            $table->decimal('quantity', 18, 4);

            // الرابط متعدّد الأشكال — نفسُ نمط warehouse_permits
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');

            $table->string('status', 20)->default('active');

            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            /*
                فهرسٌ لسؤال «كم المحجوز من هذا المنتج في هذا المستودع؟»
                — وهو السؤال الذي يُطرح في كل فحص توفّر.
            */
            $table->index(['product_id', 'store_house_id', 'status'], 'stock_res_lookup_idx');

            // ولسؤال «ما حجوزات هذا المستند؟» عند الفكّ والصرف
            $table->index(['reference_type', 'reference_id'], 'stock_res_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
