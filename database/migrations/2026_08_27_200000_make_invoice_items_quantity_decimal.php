<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الكمية كسريّة.
 *
 * `invoice_items.quantity` كان `INTEGER`: من يبيع بالكيلو أو بالمتر أو
 * بالساعة يكتب `1.5` فتُبتَر إلى `1` في الوضع المتساهل، أو يردّها MySQL
 * بـ`Data truncated` في الوضع الصارم. ولذلك كانت شاشتا عرض السعر والفاتورة
 * تفرضان `step="1"` — قيدُ واجهةٍ سببه عمودٌ لا قرارُ عمل.
 *
 * والمخزون كان كسريّاً أصلاً (`product_details.quantity` و
 * `warehouse_permits_products.quantity` كلاهما `decimal(18,4)`)، وكذلك
 * `invoice_items.quantity_in_base_unit` — فالعمود الوحيد الصحيح كان هذا.
 * أربع خاناتٍ عشرية تطابق ما حوله فلا يختلّ الجرد عند التقريب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->integer('quantity')->nullable()->change();
        });
    }
};
