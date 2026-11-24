<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمودُ `shipping_tax` على «عروض أسعار الشراء».
 *
 * نظيرُ `add_shipping_tax_to_purchase_invoices`، ولنفس السبب: العرضُ
 * يُحوَّل إلى أمر شراء (`ViewPurchasePriceController::convertToPurchaseOrder()`)،
 * والأمرُ يُستلَم ثمّ يُفوتَر. فما لم يحفظ العرضُ حصّةَ ضريبة الشحن وحدها
 * وصلت السلسلةُ كلُّها بصفر، واندمجت ضريبةُ الشحن في سطر ضريبة البنود
 * وضاع حسابُها المستقلّ «ضريبة شحن مشتريات».
 *
 * `total_tax` يبقى على معناه (المجموع) فلا تتبدّل المطبوعات ولا التقارير.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_quotations_view')
            || Schema::hasColumn('purchase_quotations_view', 'shipping_tax')) {
            return;
        }

        Schema::table('purchase_quotations_view', function (Blueprint $table) {
            /* صفرٌ افتراضاً لا `null`: العروضُ السابقة لم تفصل ضريبةَ شحنها،
               فحصّتُها المعروفة صفر — و`null` يجبر كلَّ قارئٍ على `COALESCE`. */
            $table->decimal('shipping_tax', 15, 2)->default(0)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_quotations_view')
            && Schema::hasColumn('purchase_quotations_view', 'shipping_tax')) {
            Schema::table('purchase_quotations_view', function (Blueprint $table) {
                $table->dropColumn('shipping_tax');
            });
        }
    }
};
