<?php

use App\Support\HrSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\EmployeeSettingsController;

/**
 * إعدادات الموظفين والهيكل التنظيمي — جدولٌ واحدٌ بحقيبة خيارات.
 *
 * كانت إلزاميّةُ حقول ملفّ الموظف مثبَّتةً في الشيفرة: `first_name` و`email`
 * و`date_of_birth` و`country` والهاتفان `required` في `EmployeeRequest`، وما
 * سواها `nullable` — فمنشأةٌ لا تملك تواريخَ ميلاد موظفيها لا تستطيع تسجيلهم،
 * ومنشأةٌ تريد ألّا يُسجَّل موظفٌ بلا قسمٍ ولا مسمّى ولا تاريخِ التحاق لا
 * تستطيع إلزامَه. والأمرُ نفسُه في الهيكل التنظيمي: وصفُ القسم اختياريٌّ
 * دائماً، واسمُه يُقبل مكرَّراً بلا فهرسٍ فريدٍ ولا قاعدةِ تحقّق.
 *
 * فصارت القاعدةُ إعداداً: صفٌّ لكلّ مجموعة، وحقيبةُ `options` تحمل مفاتيحَها.
 * والحقيبةُ لا أعمدة: كلُّ مفتاحٍ جديدٍ عمودٌ يبقى فارغاً في كلّ صفٍّ سوى
 * صفِّه — وهي العلّةُ نفسُها التي من أجلها أُضيفت حقيبةُ `options` إلى
 * `general_client_settings` (2026_08_29_090000).
 *
 * التعريفُ والافتراضاتُ في `App\Support\HrSettings::definitions()` وحده،
 * والصفُّ يُنشأ بافتراضاته عند أوّل قراءةٍ له — فلا بذرةَ تحتاج تشغيلاً.
 *
 * @see HrSettings
 * @see EmployeeSettingsController
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_settings')) {
            return;
        }

        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            // مفتاحُ المجموعة كما في `HrSettings::definitions()`
            $table->string('key', 100)->unique();
            // اسمُها المعروض — للسجلّات ولمن يقرأ الجدول مباشرة
            $table->string('name')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    /**
     * الإسقاطُ يحذف الجدول: لا بيانات عملٍ فيه — إعداداتٌ تُعاد افتراضاتُها
     * من التعريف وحدها عند أوّل قراءة.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
