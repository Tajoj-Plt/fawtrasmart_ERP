<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لقطة نصية ثابتة لمتغيّر المنتج المختار (مثال: "اللون: أحمر / المقاس: L")
 * وقت البيع — بلا هذا العمود يستحيل معرفة أيّ خيارٍ اشتراه العميل فعلياً من
 * فاتورة طلبٍ لمنتجٍ له متغيّرات، فتبقى الفاتورة النظامية (ZATCA) ناقصة البيان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'variant_label')) {
                $table->string('variant_label')->nullable()->after('item');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('variant_label');
        });
    }
};
