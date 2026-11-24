<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سنة البناء بالتقويمين (الصكوك ورخص البناء هجرية غالباً)، ومستودعٌ للمبنى
 * من مستودعات المخزون — مرجعٌ إلى `store_houses` لا مستودعٌ ثانٍ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_buildings', function (Blueprint $table) {
            if (! Schema::hasColumn('re_buildings', 'construction_year_hijri')) {
                $table->unsignedSmallInteger('construction_year_hijri')->nullable()->after('construction_year');
            }

            if (! Schema::hasColumn('re_buildings', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')->nullable()->after('manager_user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_buildings', function (Blueprint $table) {
            $table->dropIndex(['store_house_id']);
            $table->dropColumn(['construction_year_hijri', 'store_house_id']);
        });
    }
};
