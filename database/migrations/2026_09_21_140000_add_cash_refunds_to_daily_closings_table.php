<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرتجع النقدي بنداً مستقلاً في الإقفال اليومي.
 *
 * كان يُطرح من «المباع نقداً» صامتاً، فيومٌ بلا بيعٍ نقدي وفيه مرتجعٌ
 * صُرف للعميل يُخرج المباعَ نقداً سالباً — رقماً لا يقبله نموذجُ الإقفال
 * اليدوي، فلا يستطيع الموظف إقفال يومه. صار له عمودان: ما أبلغه الموظف
 * وما سجّله النظام، ويُطرح من إجمالي التحصيل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_closings', 'reported_cash_refunds')) {
                $table->decimal('reported_cash_refunds', 15, 2)->default(0)->after('reported_returns');
            }
            if (! Schema::hasColumn('daily_closings', 'system_cash_refunds')) {
                $table->decimal('system_cash_refunds', 15, 2)->default(0)->after('system_returns');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['reported_cash_refunds', 'system_cash_refunds']);
        });
    }
};
