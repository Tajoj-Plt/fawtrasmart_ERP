<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع عمود كلمة مرور البريد.
 *
 * النموذج يشفّر كلمة المرور قبل الحفظ، والقيمة المشفَّرة تتجاوز 228 حرفاً
 * بينما العمود `varchar(255)`. أي كلمة مرور واقعية تتخطّى الحدّ فيرفض
 * MySQL الصف كاملاً برسالة «Data too long» — ولهذا بقي الجدول فارغاً
 * ولم تُحفظ إعدادات بريد واحدة قط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smtp_settings', function (Blueprint $table) {
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('smtp_settings', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->change();
        });
    }
};
