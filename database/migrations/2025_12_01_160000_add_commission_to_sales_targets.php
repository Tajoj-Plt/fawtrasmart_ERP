<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_targets', 'commission_percentage')) {
                $table->decimal('commission_percentage', 5, 2)->default(0)->after('base_amount')->comment('نسبة العمولة من الإنجاز');
            }
        });
    }

    public function down()
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropColumn('commission_percentage');
        });
    }
};
