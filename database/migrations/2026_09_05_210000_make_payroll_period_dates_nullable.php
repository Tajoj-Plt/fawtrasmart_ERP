<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
    تواريخُ مسير الرواتب تقبل الفراغ.

    الأعمدةُ الثلاثة `NOT NULL` بلا قيمةٍ افتراضية، بينما كلُّ ما حولها
    مكتوبٌ على أنّها اختيارية:

      • الشاشةُ تقول تحت «تاريخ النهاية» حرفياً: «اتركه فارغاً للمسير
        المفتوح» — وعدٌ صريحٌ للمستخدم.
      • والوحدةُ تتحقّق منها `nullable` وتُسند `?? null`.
      • و`Payroll::isRunningAt()` تحرس الثلاثة: `if ($this->start_date …)`
        و`if ($this->end_date …)` — أي أنّ الغيابَ حالةٌ محسوبةٌ عندها:
        بلا بدايةٍ يعمل من الأزل، وبلا نهايةٍ يعمل بلا أجل.
      • والقوالبُ كلُّها تعرضها داخل `@if($payroll->start_date && …)`.

    فالمنطقُ كلُّه يفترض الاختيارية، والعمودُ وحده يأبى — فيُردّ الحفظُ
    بخطأ SQL خام: «Column 'end_date' cannot be null».

    ولا خطرَ في التوسيع: تحويلُ عمودٍ من `NOT NULL` إلى `NULL` يقبل ما كان
    مقبولاً وزيادة، فلا صفَّ قائمٌ يتأثّر.
*/
return new class extends Migration
{
    private const COLUMNS = ['registration_date', 'start_date', 'end_date'];

    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->date($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        /*
            الرجوعُ يملأ الفراغَ قبل تضييق العمود، وإلّا رفضت قاعدةُ
            البيانات التحويلَ على صفوفٍ صارت فارغةً بعد الترقية.
            والبديلُ تاريخُ الإنشاء: أقربُ ما يُعرَف عن المسير.
        */
        foreach (self::COLUMNS as $column) {
            DB::table('payrolls')
                ->whereNull($column)
                ->update([$column => DB::raw('DATE(COALESCE(created_at, NOW()))')]);
        }

        Schema::table('payrolls', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->date($column)->nullable(false)->change();
            }
        });
    }
};
