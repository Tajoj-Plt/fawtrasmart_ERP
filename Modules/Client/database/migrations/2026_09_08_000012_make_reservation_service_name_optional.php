<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اسمُ الخدمة يصير اختيارياً — لأن المنتجَ المربوط يعرفه.
 *
 * ═══════════ الازدواج الذي كان ═══════════
 *
 * `reservation_services` جدولُ **قابليّةِ الحجز**: المدّة، ووقتا التحضير
 * والتنظيف، والحدُّ اليومي، والموظفون المؤهَّلون — خمسةُ أشياء لا يعرفها
 * `products` ولا مكانَ لها فيه.
 *
 * لكنّه كان يُلزم `name` حتى حين يُربَط بمنتج، والمنتجُ يحمل الاسمَ نفسه.
 * فيُعدَّل اسمُ المنتج في كتالوج المخزون ويبقى اسمُ الخدمة على ما كان،
 * فيقرأ المستخدمُ اسمين لشيءٍ واحد ولا يعرف أيَّهما الصحيح — وهو بعينه
 * الازدواجُ الذي بُني هذا الجدولُ ليتجنّبه في السعر والضريبة
 * (`effectivePrice()` و`effectiveTaxId()` تقرآن من المنتج).
 *
 * ═══════════ ما صار ═══════════
 *
 *   `product_id` مربوطاً  ⇒ الاسمُ من المنتج، و`name` تجاوزٌ اختياري.
 *   `product_id` فارغاً   ⇒ `name` إلزاميّ — لا مصدرَ آخر له.
 *
 * والقاعدةُ تُحرَس في `StoreReservationServiceRequest` بـ`required_without`،
 * والقراءةُ موحّدةٌ في `ReservationService::displayName()`.
 *
 * الصفوفُ القائمة لا تتأثّر: أسماؤها محفوظةٌ وتبقى تجاوزاتٍ صريحة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservation_services')) {
            return;
        }

        Schema::table('reservation_services', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservation_services')) {
            return;
        }

        /*
            العودةُ تتطلّب ألّا يكون اسمٌ فارغاً، وإلّا رفضت القاعدةُ
            التعديل. فتُملأ الفوارغُ من أسماء المنتجات المربوطة قبله.
        */
        \Illuminate\Support\Facades\DB::table('reservation_services')
            ->whereNull('name')
            ->whereNotNull('product_id')
            ->update([
                'name' => \Illuminate\Support\Facades\DB::raw(
                    '(select products.name from products where products.id = reservation_services.product_id)'
                ),
            ]);

        \Illuminate\Support\Facades\DB::table('reservation_services')
            ->whereNull('name')
            ->update(['name' => 'خدمة حجز']);

        Schema::table('reservation_services', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
