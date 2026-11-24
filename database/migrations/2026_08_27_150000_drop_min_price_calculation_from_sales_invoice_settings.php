<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط «حساب الحدّ الأدنى لسعر البيع» من إعدادات الفواتير.
 *
 * `min_price_calculation` كان يخيّر المنشأة بين مقارنة البند بسعر التكلفة
 * **قبل الضريبة** أو **شاملاً الضريبة** أو كليهما — وهو خيارٌ لا وجه له:
 * سعر التكلفة مسجَّلٌ بلا ضريبة، فمقارنةُ سعرٍ يحمل ضريبتَه به تجعل
 * «منع البيع بأقلّ من التكلفة» يمرّ كلّما بلغت الضريبةُ الفرق — أي أنّ
 * الخيار كان باباً لتعطيل الحارس من حيث يُظنّ أنه ضبطٌ له.
 *
 * المقارنة الآن قبل الضريبة دائماً (`DocumentTaxCalculator::line()['net']`)،
 * وهو ما كان الخيار الافتراضي أصلاً. أُسقط بطلب المستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales_invoice_settings', 'min_price_calculation')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                if (Schema::hasColumn('sales_invoice_settings', 'min_price_calculation')) {
                    $table->dropColumn('min_price_calculation');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'min_price_calculation')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->string('min_price_calculation', 20)->default('without_tax');
            });
        }
    }
};
