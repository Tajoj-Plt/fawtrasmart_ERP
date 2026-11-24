<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هل تظهر المنتجاتُ الخدمية والرقمية للموظّف في بنود مستندات البيع؟
 *
 * قائمةُ أصناف الموظّف في الفاتورة وعرض السعر وأمر البيع والمرتجع
 * والإشعار الدائن مقصورةٌ على ما له رصيدٌ في مستودعه (`product_details`).
 * والخدمةُ والرقميُّ لا رصيدَ لهما أصلاً، فكانا يغيبان عن الموظّف بلا
 * خيار — ولا يراهما إلّا المدير.
 *
 * مفتاحان لا واحد: منشأةٌ تريد أن يبيع مندوبوها الخدمات دون الاشتراكات
 * الرقمية، أو العكس. والافتراضُ «يظهر»: غيابُهما عطلٌ لا سياسة.
 */
return new class extends Migration
{
    private const COLUMNS = ['show_service_products', 'show_digital_products'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('sales_invoice_settings', $column)) {
                Schema::table('sales_invoice_settings', function (Blueprint $table) use ($column) {
                    $table->boolean($column)->default(true);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('sales_invoice_settings', $column)) {
                Schema::table('sales_invoice_settings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
