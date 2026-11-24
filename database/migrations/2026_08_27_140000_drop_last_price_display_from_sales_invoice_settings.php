<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط «عرض آخر سعر بيع والحدّ الأدنى للسعر» من إعدادات الفواتير.
 *
 * `last_price_display` كان تلميحاً بجانب حقل السعر في شاشة الفاتورة: يعرض
 * آخر سعرٍ بِيع به الصنف أو سعر شرائه أو كليهما. لا يمنع شيئاً ولا يغيّر
 * محفوظاً — ومن أراد حارساً فـ«منع البيع بأقلّ من سعر التكلفة» هو الحارس،
 * يردّ الحفظ لا يُلمِّح. أُسقط بطلب المستخدم.
 *
 * ومعه سقط استعلامُ آخر الأسعار في `InvoicesController::create()` — استعلامٌ
 * على `invoice_items` كان يجري في كلّ فتحةٍ للشاشة لأجل تلميحٍ مطفأ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales_invoice_settings', 'last_price_display')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                if (Schema::hasColumn('sales_invoice_settings', 'last_price_display')) {
                    $table->dropColumn('last_price_display');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_invoice_settings', 'last_price_display')) {
            Schema::table('sales_invoice_settings', function (Blueprint $table) {
                $table->string('last_price_display', 20)->default('nothing');
            });
        }
    }
};
