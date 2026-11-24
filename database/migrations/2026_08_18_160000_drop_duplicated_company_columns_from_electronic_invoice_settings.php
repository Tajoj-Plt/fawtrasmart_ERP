<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط بيانات المنشأة المكرَّرة من إعدادات الفاتورة الإلكترونية.
 *
 * الاسم التجاري والرقم الضريبي والسجل التجاري والعنوان والدولة مسجَّلة
 * أصلاً في «إعدادات الحساب» — يحفظها `SittingAccountController` في سجلّ
 * `Client` المرتبط بالمستخدم. وتكرارها هنا يعني إدخالها مرّتين، ونسختين
 * تتباعدان: يُعدَّل الرقم الضريبي في مكانه الصحيح فتُرسَل الفاتورة
 * الإلكترونية بالرقم القديم.
 *
 * يبقى في هذا الجدول ما يخصّ الفوترة الإلكترونية وحدها: اسم الفرع أو رقم
 * المجموعة الضريبية، وتصنيف العمل، والاسم الشائع للشهادة.
 */
return new class extends Migration
{
    private const DUPLICATED = ['company_name', 'tax_number', 'commercial_register', 'country_code', 'address'];

    public function up(): void
    {
        Schema::table('electronic_invoice_settings', function (Blueprint $table) {
            foreach (self::DUPLICATED as $column) {
                if (Schema::hasColumn('electronic_invoice_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoice_settings', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('tax_number', 30)->nullable();
            $table->string('commercial_register', 30)->nullable();
            $table->string('country_code', 2)->default('SA');
            $table->string('address')->nullable();
        });
    }
};
