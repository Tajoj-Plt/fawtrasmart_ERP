<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعارات المبيعات في «إعدادات Telegram».
 *
 * كانت فاتورة المبيعات وتنبيها انخفاض الكمية وقرب انتهاء الصلاحية تُرسَل
 * برمز بوتٍ وقناةٍ (`@Salesfatrasmart`) مكتوبين في الشيفرة، من كل تنصيب،
 * بلا مفتاح إيقاف. صارت من هذا الجدول، ومطفأةً افتراضاً: لا تخرج بياناتُ
 * العملاء إلى قناةٍ حتى يختارها صاحبُ النظام بنفسه.
 *
 * `sales_chat_id` قناةٌ مستقلّة للمبيعات اختياراً — فارغةً تُستعمل القناة
 * العامّة `chat_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_settings')) {
            return;
        }

        Schema::table('telegram_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_settings', 'sales_chat_id')) {
                $table->string('sales_chat_id')->nullable();
            }

            foreach (['sales_invoice_enabled', 'low_stock_alert_enabled', 'expiry_alert_enabled'] as $column) {
                if (! Schema::hasColumn('telegram_settings', $column)) {
                    $table->boolean($column)->default(false);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_settings')) {
            return;
        }

        foreach (['sales_chat_id', 'sales_invoice_enabled', 'low_stock_alert_enabled', 'expiry_alert_enabled'] as $column) {
            if (Schema::hasColumn('telegram_settings', $column)) {
                Schema::table('telegram_settings', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
