<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
        نموذجا الإضافة والتعديل («ورديات مخصصة») يعرضان حقل «نوع الوردية»
        (رئيسي/فرعي) ويرسلانه باسم `is_main`، والمتحكّم يحفظه فعلاً —
        لكنّ عمود `custom_shifts.is_main` لم يكن موجوداً أصلاً، فكل حفظٍ
        كان يسقط بخطإ 1054 (Unknown column 'is_main'). لا صفوفَ حالية
        تعتمد على قيمةٍ افتراضية، فـ NOT NULL بقيمةٍ افتراضية (١ = رئيسي،
        مطابقةً لأول خيارٍ في القائمة) تكفي.
    */
    public function up(): void
    {
        Schema::table('custom_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_shifts', 'is_main')) {
                $table->boolean('is_main')->default(true)->after('shift_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_shifts', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
