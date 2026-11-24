<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * الخدمةُ صارت منتجاً من نوع «خدمي» — وهذه الهجرةُ تثبّت ذلك في الفهارس وحدَها.
 *
 * ═══════════ ما تفعله ═══════════
 *
 *  1. `reservation_services.product_id` فريد: الجدولُ لم يعد كتالوج خدمات بل
 *     «ملفَّ حجزٍ» اختياريّاً لكل منتجٍ خدمي (المدّة والتحضير والتنظيف والموظفون
 *     المؤهَّلون — ما لا يعرفه جدول `products`). وملفّان لمنتجٍ واحد يعنيان
 *     مدّتين للخدمة نفسها لا يُعرَف أيّهما يحكم.
 *
 *     و`NULL` مسموحٌ مكرَّراً في MySQL — فالصفوفُ القديمة بلا منتج تبقى كما هي.
 *
 *  2. فهرسٌ على `reservations.product_id`: المنتجُ صار هويّةَ الخدمة في الحجز،
 *     والتقاريرُ واللوحةُ تُجمّع وتُصفّي عليه.
 *
 * ═══════════ وما لا تفعله ═══════════
 *
 * لا حذفَ ولا تعديلَ عمود. وإن وُجد تكرارٌ لمنتجٍ في الملفّات لا يُضاف القيدُ
 * الفريد ويُسجَّل السبب — تصحيحُ البيانات قرارٌ لصاحبها لا لهجرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_services')
            && ! Schema::hasIndex('reservation_services', 'reservation_services_product_unique')) {
            $duplicated = DB::table('reservation_services')
                ->whereNotNull('product_id')
                ->whereNull('deleted_at')
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('product_id');

            if ($duplicated->isEmpty()) {
                Schema::table('reservation_services', function (Blueprint $table) {
                    $table->unique('product_id', 'reservation_services_product_unique');
                });
            } else {
                Log::warning('reservation_services: لم يُضف القيد الفريد على product_id — منتجاتٌ مكرّرة', [
                    'product_ids' => $duplicated->all(),
                ]);
            }
        }

        if (Schema::hasTable('reservations')
            && Schema::hasColumn('reservations', 'product_id')
            && ! Schema::hasIndex('reservations', 'reservations_product_index')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->index('product_id', 'reservations_product_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('reservation_services', 'reservation_services_product_unique')) {
            Schema::table('reservation_services', fn (Blueprint $table) => $table->dropUnique('reservation_services_product_unique'));
        }

        if (Schema::hasIndex('reservations', 'reservations_product_index')) {
            Schema::table('reservations', fn (Blueprint $table) => $table->dropIndex('reservations_product_index'));
        }
    }
};
