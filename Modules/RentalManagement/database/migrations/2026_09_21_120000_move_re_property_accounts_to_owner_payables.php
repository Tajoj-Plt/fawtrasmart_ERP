<?php

use Illuminate\Database\Migrations\Migration;
use Modules\RentalManagement\Services\PropertyAccountService;

/**
 * حسابات المباني والوحدات مكانُها الخصوم تحت «مستحقات ملاك العقار». ما أنشأه
 * الترحيل السابق تحت الإيرادات يُنقل هنا؛ وعلى قاعدةٍ لم تعرف الإصدار الأول
 * لا يجد ما ينقله.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            app(PropertyAccountService::class)->realign();
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // نقل بيانات — لا عودة عنه
    }
};
