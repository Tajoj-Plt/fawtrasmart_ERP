<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول ربط «إعدادات الحسابات المحاسبية».
 *
 * طبقة مستقلة تماماً عن `App\Constants\SystemAccountIds`: الثوابت تبقى كما
 * هي ويبقى النظام يعمل بها، وهذا الجدول يسجّل — لكل عملية محاسبية — أي
 * حساب من شجرة الحسابات يخصّها، تمهيداً لربطه بالنظام في مرحلة لاحقة.
 *
 * `account_id` هو المرجع الحقيقي (مفتاح أجنبي على `accounts`)، و`account_code`
 * نسخة معروضة من كود الحساب تُحدَّث معه — كي تُقرأ الشاشة والتقارير بلا ضمّ.
 *
 * الحذف `nullOnDelete`: حذف حساب من الشجرة يترك الإعداد قائماً بلا ربط
 * فيظهر في الشاشة «غير مضبوط»، وهو أوضح من اختفاء صفّ الإعداد كلّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            Schema::create('accounting_settings', function (Blueprint $table) {
                $table->id();

                // المفتاح البرمجي الثابت — مقابل اسم الثابت في SystemAccountIds
                $table->string('key', 100)->unique();

                $table->string('name');
                $table->string('description')->nullable();

                // مجموعة العرض في الشاشة (خزائن، مبيعات، مخزون…) — ثمانون إعداداً
                // في جدول واحد بلا تقسيم لا تُقرأ
                $table->string('group', 100)->nullable()->index();

                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('account_code', 50)->nullable();

                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
