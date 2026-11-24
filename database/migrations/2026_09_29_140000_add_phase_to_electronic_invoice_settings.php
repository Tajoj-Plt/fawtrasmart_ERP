<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مرحلة الفوترة الإلكترونية التي تعمل بها المنشأة.
 *
 * الهيئة تُلزم المنشآت على مرحلتين: الأولى «الإصدار» — فاتورةٌ من نظامٍ
 * إلكتروني برمز QR، بلا اتصالٍ بالهيئة — وهي على الجميع. والثانية «الربط
 * والتكامل» — توقيعٌ وإرسال — وتُلزَم بها المنشأة حين تُشعرها الهيئة بحسب
 * إيراداتها. فمنشأةٌ لم يبلغها الإشعار بعدُ تعمل بالأولى، ولا معنى لطلب
 * شهادةٍ ورمز OTP منها.
 *
 * الافتراضي `phase1`. ومن سجَّل لدى الهيئة قبل هذا الترحيل يُنقَل إلى
 * `phase2`: وإلا توقّف إرساله بترحيلٍ لم يطلبه.
 */
return new class extends Migration
{
    private const TABLE = 'electronic_invoice_settings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'phase')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('phase', 10)->default('phase1');
            });
        }

        if (Schema::hasColumn(self::TABLE, 'production_certificate')) {
            DB::table(self::TABLE)->whereNotNull('production_certificate')->update(['phase' => 'phase2']);
        }
    }

    public function down(): void
    {
        // لا يُسقَط العمود: به يُعرَف أتُرسِل المنشأة إلى الهيئة أم لا
    }
};
