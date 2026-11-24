<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عناوين عملاء المتجر: حقولُ الخريطة.
 *
 * الواجهة تملأ العنوانَ من خريطة Google (الإحداثيات، العنوان المنسَّق، الدولة
 * والمدينة كنصٍّ حرّ). الخادم يطابق الدولة/المدينة مع جداول المتجر إن وُجد
 * تطابق فيحفظ معرّفيهما (`country_id`, `city_id`) — وإلا يبقى النصّ كما جاء.
 *
 * idempotent: كل عمودٍ يُضاف إن لم يوجد. `latitude`/`longitude` موجودان أصلاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_addresses')) {
            return;
        }

        Schema::table('client_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('client_addresses', 'formatted_address')) {
                $table->text('formatted_address')->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('client_addresses', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->after('country');
            }
            if (! Schema::hasColumn('client_addresses', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        // لا تراجع: الأعمدة تحمل بيانات عناوين العملاء
    }
};
