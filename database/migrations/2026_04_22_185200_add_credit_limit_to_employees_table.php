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
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'credit_limit')) {
                $table->decimal('credit_limit', 15, 2)->nullable()->default(0)->after('daily_qty_limit')->comment('الحد الائتماني المسموح به لعملاء هذا الموظف');
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
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'credit_limit')) {
                $table->dropColumn('credit_limit');
            }
        });
    }
};
