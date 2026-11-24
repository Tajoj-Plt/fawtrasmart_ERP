<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقيبة `options` لإعدادات وحدات العملاء.
 *
 * جدول `general_client_settings` نما عموداً عموداً: كل إعدادٍ جديد هجرةٌ
 * وعمودٌ يبقى فارغاً في كل الصفوف إلّا صفَّ مفتاحه — سبعة عشر عموداً
 * لتسعة مفاتيح. وتوحيد شاشة الإعدادات يضيف ثمانيةَ مفاتيحَ أخرى بنحو
 * ثلاثين إعداداً، فالعمود لكل إعدادٍ يعني ثلاثين عموداً فارغاً في كل صفّ.
 *
 * الأعمدة القائمة تبقى كما هي: `ClientSettingsHelper` يقرؤها بأسمائها،
 * وأربعةَ عشرَ موضعاً في الوحدة يقرأ منه. الجديد وحده يسكن `options`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('general_client_settings', 'options')) {
                $table->json('options')->nullable()->after('itinerary_restrict_to_assigned_clients');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
