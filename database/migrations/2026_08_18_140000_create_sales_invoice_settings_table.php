<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مخزن إعدادات الفواتير وعروض الأسعار.
 *
 * كانت شاشة «إعدادات الفواتير» تعرض اثنين وعشرين إعداداً بلا جدول يحملها:
 * المتحكّم لا يمرّر `$settings` فتُقرأ دائماً غير معرَّفة، والزرّ يشير إلى
 * دالّة غير موجودة. فكانت الشاشة نموذجاً يُملأ ولا يُحفَظ.
 *
 * صفّ واحد لا أكثر — `SalesInvoiceSetting::current()` يُنشئه عند أوّل طلب.
 * والأعمدة صريحة لا `key/value`، ليأتي التحقّق من نوع العمود نفسه ويظهر
 * ما هو متاح من قراءة الجدول.
 */
return new class extends Migration
{
    /** الإعدادات المنطقية — كلّها معطَّلة ابتداءً، فالافتراضي هو سلوك النظام قبل الشاشة. */
    private const FLAGS = [
        'allow_free_entry',
        'no_sell_below_cost',
        'enable_max_discount',
        'select_price_list',
        'show_invoice_profit',
        'preview_before_save',
        'default_paid_status',
        'auto_pay_if_balance',
        'manual_invoice_status',
        'disable_delivery_options',
        'enable_sales_adjustment',
        'disable_quotes',
        'manual_quote_status',
        'enable_sales_orders',
        'manual_sales_order_status',
        'apply_offers_to_quotes',
        'copy_notes_on_conversion',
        'custom_journal_description',
        'enable_debit_notification',
        'send_on_social',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sales_invoice_settings')) {
            Schema::create('sales_invoice_settings', function (Blueprint $table) {
                $table->id();

                // قيم مفتاحية لا نصوص معروضة: التسمية تُترجَم في القالب، والمخزَّن
                // ثابت لا يتغيّر بتغيّر لغة الواجهة
                $table->string('min_price_calculation', 20)->default('without_tax');
                $table->string('last_price_display', 20)->default('nothing');

                foreach (self::FLAGS as $flag) {
                    $table->boolean($flag)->default(false);
                }

                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_settings');
    }
};
