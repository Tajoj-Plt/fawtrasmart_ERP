<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يُشترَط الموقع على هذا الجهاز» — مفتاحٌ لكلّ جهاز فرع.
 *
 * `GeofenceGuard::verifyAgainstFence()` تُعفي جهازَ الفرع المربوط بالمكان
 * من فحص الإحداثيات:
 *
 *     if (! $context->hasCoordinates() && $context->kiosk
 *         && (int) $context->kiosk->geofence_id === (int) $fence->id) { return null; }
 *
 * والسببُ مكتوبٌ هناك وصحيح: «مكانُه معلومٌ بالربط نفسه، وأغلبُه شاشةٌ
 * ثابتةٌ بلا GPS». فمسحُ بطاقةٍ مطبوعةٍ على جهاز الفرع يُقبل بلا موقع —
 * وهو المقصود في الشاشة الثابتة.
 *
 * لكنّ «جهاز الفرع» قد يكون جوّالَ مشرفٍ أو لوحاً متنقّلاً: مقترنٌ مرّةً
 * ثمّ يُحمل. فمن أراد أن يُشترَط الموقعُ **حتى على الجهاز** لم يكن له
 * مفتاح: `require_geo` على المكان لا يسري عليه بسبب هذا الإعفاء بالذات.
 *
 * فصار لكلّ جهازٍ مفتاحُه. و`false` افتراضاً — هو السلوكُ القائم، فلا
 * تتوقّف شاشةٌ ثابتةٌ قائمةٌ يومَ الترقية.
 *
 * ويبقى الإعفاءُ محصوراً بالجهاز المربوط بالمكان نفسِه: جهازٌ مربوطٌ بمكانٍ
 * آخر — أو بلا مكان — يُفحَص موقعُه كالجوال.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_kiosks') || Schema::hasColumn('attendance_kiosks', 'require_location')) {
            return;
        }

        Schema::table('attendance_kiosks', function (Blueprint $table) {
            $table->boolean('require_location')->default(false)->after('geofence_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_kiosks') && Schema::hasColumn('attendance_kiosks', 'require_location')) {
            Schema::table('attendance_kiosks', function (Blueprint $table) {
                $table->dropColumn('require_location');
            });
        }
    }
};
