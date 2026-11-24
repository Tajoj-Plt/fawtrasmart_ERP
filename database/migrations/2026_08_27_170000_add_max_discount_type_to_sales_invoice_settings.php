<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السقف الأقصى للخصم: نسبةٌ **أو** مبلغ — لا الاثنان معاً.
 *
 * أُضيف `max_discount_amount` أوّلاً سقفاً ثانياً يسري مع النسبة، فصار في
 * الشاشة حقلان يُملآن معاً ويُقرأ أثرُهما مجتمعاً — وهو ما لم يُرَد. الآن
 * حقلٌ واحدٌ بوحدةٍ تُختار: `max_discount_type` يقول أيّهما السارية،
 * والقيمة تُكتب في عمودها (`max_discount_percent` للنسبة،
 * `max_discount_amount` للمبلغ) فيبقى الرقمان محفوظين، ولا يسري إلّا
 * الذي اختير.
 *
 * والعمودان يبقيان: من بدّل الوحدة ثمّ عاد وجد رقمه الأوّل كما تركه.
 * والافتراضي `percentage` — وهو السلوك الذي كان قبل هذا العمود كلِّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'max_discount_type')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_invoice_settings', 'max_discount_type')) {
                    $table->string('max_discount_type', 12)
                        ->default('percentage')
                        ->after('max_discount_percent');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_invoice_settings', 'max_discount_type')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->dropColumn('max_discount_type');
            });
        }
    }
};
