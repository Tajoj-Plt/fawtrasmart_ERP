<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * من أضاف العميل إلى خطّ سير اليوم — `employee_client_visits.added_by`.
 *
 * «خط سيري اليومي» يعرض بجانب كل زيارةٍ أُضيفت من الشاشة نفسها من أضافها:
 * المندوبُ لنفسه، أو دورٌ آخر (مشرفٌ مثلاً) أضافها إلى خطّه. الصفوف التي
 * يبنيها «تخطيط خط السير» الأسبوعي تبقى بلا قيمة — هي الخطّة لا إضافةٌ عليها.
 *
 * بلا مفتاحٍ أجنبي عمداً: قواعد الإنتاج أقدم من مجموعة الترحيلات، وأنواع
 * الأعمدة فيها غير مضمونة ([[legacy-schema-drift-and-schemaguard]]).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_client_visits') || Schema::hasColumn('employee_client_visits', 'added_by')) {
            return;
        }

        Schema::table('employee_client_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('added_by')->nullable()->after('employee_id')->index();
        });
    }

    public function down(): void
    {
        // لا حذفَ لعمود: الترحيلات هنا تُقاعد ولا تحذف ([[migration-retire-not-drop]])
    }
};
