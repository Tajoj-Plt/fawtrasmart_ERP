<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('region_groubs', function (Blueprint $table) {
            if (!Schema::hasColumn('region_groubs', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->nullable()->default(0)->after('directions_id')->comment('الحد الائتماني المسموح به لهذه المجموعة');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('region_groubs', function (Blueprint $table) {
            if (Schema::hasColumn('region_groubs', 'credit_limit')) {
                $table->dropColumn('credit_limit');
            }
        });
    }
};
