<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات المتجر: ضريبةُ الشحن.
 *
 * رسمُ الشحن غيرُ شاملٍ للضريبة دائماً (حتى لو كانت أسعار المنتجات شاملة)،
 * وضريبتُه تُضاف فوقه: الإجمالي = المجموع − الخصم + الضريبة + الشحن + ضريبة الشحن.
 * العمودُ يحمل المبلغ المحسوب وقت البيع فلا يتغيّر بتغيّر النسبة لاحقاً،
 * وبه يُعكس القيد عند الإلغاء/الاسترداد بنفس ما رُحِّل.
 *
 * idempotent: الطلباتُ السابقة صفرٌ (لم تُحمَّل ضريبةَ شحن).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('online_orders') || Schema::hasColumn('online_orders', 'shipping_tax_amount')) {
            return;
        }

        Schema::table('online_orders', function (Blueprint $table) {
            $table->decimal('shipping_tax_amount', 12, 2)->default(0)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        // لا تراجع: العمود يحمل بياناتٍ ضريبية للطلبات
    }
};
