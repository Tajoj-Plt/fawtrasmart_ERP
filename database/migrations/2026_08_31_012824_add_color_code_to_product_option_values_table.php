<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `saveProductOptions()` (Modules\OnlineStore\Http\Controllers\StoreProductController)
 * وثّق منذ إنشائه أن كل قيمة خيارٍ قد تحمل `color_code?` — ونظام الخيارات
 * الجاهزة (App\Constants\OnlineStoreOptionPresets) يعرّف لوناً لكل قيمة لون
 * فعلاً — لكن لا عمود قط استقبل هذه القيمة، فتضيع فور مغادرة المتصفح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_values', function (Blueprint $table) {
            if (! Schema::hasColumn('product_option_values', 'color_code')) {
                $table->string('color_code', 9)->nullable()->after('label_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_option_values', function (Blueprint $table) {
            if (Schema::hasColumn('product_option_values', 'color_code')) {
                $table->dropColumn('color_code');
            }
        });
    }
};
