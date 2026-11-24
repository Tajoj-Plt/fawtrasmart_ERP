<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمودُ `shipping_tax` على مستندات الشراء.
 *
 * كانت ضريبةُ الشحن تُجمع مع ضريبة البنود قبل الحفظ:
 *
 *     'total_tax' => $total_tax + $shipping_tax,
 *
 * في `InvoicesPurchaseController` و`ReturnsInvoiceController`
 * و`CityNoticesController` جميعاً. فالفصلُ يُتلَف عند الحفظ، ولا سبيل
 * لاستردادِه عند بناء القيد — ولا عند التعديل ولا في الإقرار الضريبي.
 *
 * فالعمودُ شرطُ الفصل لا زينةٌ عليه: `total_tax` يبقى على معناه (المجموع،
 * فلا تتبدّل المطبوعات ولا التقارير القائمة)، و`shipping_tax` يحفظ الجزء
 * المطلوب طرحُه ليُرحَّل إلى حسابه.
 *
 * والجدولُ واحدٌ للفاتورة والمرتجع والإشعار المدين (`purchase_invoices.type`)،
 * فالعمودُ يخدم الثلاثة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoices') || Schema::hasColumn('purchase_invoices', 'shipping_tax')) {
            return;
        }

        Schema::table('purchase_invoices', function (Blueprint $table) {
            /* صفرٌ افتراضاً لا `null`: المستنداتُ السابقة لم تفصل ضريبةَ
               شحنها، فحصّتُها المعروفة صفر — و`null` يجبر كلَّ قارئٍ على
               `COALESCE`. الدقّةُ نفسُ `total_tax` تماماً. */
            $table->decimal('shipping_tax', 15, 2)->default(0)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'shipping_tax')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropColumn('shipping_tax');
            });
        }
    }
};
