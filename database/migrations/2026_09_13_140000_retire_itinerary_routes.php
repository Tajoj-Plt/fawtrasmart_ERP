<?php

use App\Models\CronJob;
use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * تقاعدُ «خطوط السير المسمّاة» — عادت شاشةُ التخطيط إلى الآلية الأسبوعية.
 *
 * حيث نُفّذ ترحيلٌ سابقٌ حُذف ملفُّه (`2026_09_13_120000_create_itinerary_routes`):
 *   • `itinerary_routes` تُقاعَد: فارغةً تُسقَط، وفيها صفوفٌ يُعاد تسميتها.
 *   • صفُّ المهمّة `itinerary_generate_weekly_routes` يُرفع — أمرُها حُذف، فتشغيلُها
 *     فشلٌ متكرّر بلا سبب.
 *   • `employee_client_visits.itinerary_route_id` و`planned_duration_minutes` يبقيان
 *     مكانهما بلا استعمال (الأعمدة لا تُحذف) — شاشةُ التخطيط لم تعد تكتب وقتاً ولا مدّة.
 *
 * وحيث لم تُنفَّذ تلك النسخة فلا شيء هنا يفعل شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaRetire::table('itinerary_routes');

        if (Schema::hasTable('cron_jobs')) {
            CronJob::where('key', 'itinerary_generate_weekly_routes')->delete();
        }
    }

    public function down(): void
    {
        // لا رجعة — انظر ديباجة الملف
    }
};
