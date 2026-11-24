<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توحيد مفردة النسبة: `percentage` لا `percent`.
 *
 * النظام كلُّه يقول `percentage`: نماذجُ فواتير المبيعات
 * (`items[*][discount_type]`)، والتحقّقُ في `CreditNotificationController`
 * (`in:amount,percentage`)، وقارئُ الخصم في `InvoicesController`.
 *
 * وكانت الوحدةُ الجديدة تقول `percent` في ثلاثة مواضع — والمفردتان
 * لشيءٍ واحد تعنيان أن مقارنةً واحدةً منسيّةً تُسقط الخصمَ صامتاً: شرطٌ
 * يفحص `=== 'percentage'` على قيمةٍ محفوظة `percent` يمرّ إلى فرع
 * «مبلغ مقطوع»، فيُحسب خصمُ 10% عشرةَ ريالات ولا يظهر خطأ.
 *
 * وهو بعينه العطلُ المسجَّل في المبيعات حين افترق حقلا الخصم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            DB::table('reservations')->where('discount_type', 'percent')
                ->update(['discount_type' => 'percentage']);
        }

        if (! Schema::hasTable('reservation_settings')) {
            return;
        }

        Schema::table('reservation_settings', function (Blueprint $table) {
            $table->string('deposit_type', 12)->default('percentage')->comment('percentage | fixed')->change();
            $table->string('cancellation_fee_type', 12)->default('none')->comment('none | fixed | percentage')->change();
        });

        DB::table('reservation_settings')->where('deposit_type', 'percent')
            ->update(['deposit_type' => 'percentage']);

        DB::table('reservation_settings')->where('cancellation_fee_type', 'percent')
            ->update(['cancellation_fee_type' => 'percentage']);
    }

    public function down(): void
    {
        if (Schema::hasTable('reservations')) {
            DB::table('reservations')->where('discount_type', 'percentage')
                ->update(['discount_type' => 'percent']);
        }

        if (! Schema::hasTable('reservation_settings')) {
            return;
        }

        DB::table('reservation_settings')->where('deposit_type', 'percentage')
            ->update(['deposit_type' => 'percent']);

        DB::table('reservation_settings')->where('cancellation_fee_type', 'percentage')
            ->update(['cancellation_fee_type' => 'percent']);
    }
};
