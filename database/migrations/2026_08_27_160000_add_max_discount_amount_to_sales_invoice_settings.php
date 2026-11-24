<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سقفٌ للخصم بالمبلغ إلى جانب السقف بالنسبة.
 *
 * كان «الحدّ الأقصى للخصم» نسبةً وحدها، والمندوب يُعطي خصمه بالريال كما
 * يُعطيه بالنسبة — فمن أراد أن يقول «لا يزيد الخصم على ثمانين ريالاً في
 * الفاتورة» لم يجد أين يقولها، ويضطرّ إلى ترجمتها نسبةً تختلف باختلاف
 * قيمة كلّ فاتورة.
 *
 * `null` أو صفر = **لا سقف بالمبلغ**، فيبقى السقف بالنسبة وحده كما كان
 * قبل هذا العمود — والترحيل لا يغيّر سلوك منشأةٍ قائمة. والسقفان يسريان
 * معاً متى ضُبطا: يُردّ الخصم إن تجاوز أيّهما.
 *
 * والسقف بالمبلغ للمنشأة كلّها — لا تجاوزَ للفرع فيه، بخلاف
 * `max_discount_percent` الذي يتجاوزه الفرع من «إعدادات الفروع».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'max_discount_amount')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_invoice_settings', 'max_discount_amount')) {
                    $table->decimal('max_discount_amount', 15, 2)
                        ->nullable()
                        ->after('max_discount_percent');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_invoice_settings', 'max_discount_amount')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->dropColumn('max_discount_amount');
            });
        }
    }
};
