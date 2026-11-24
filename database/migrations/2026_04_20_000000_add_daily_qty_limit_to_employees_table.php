<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('employees', 'daily_qty_limit')) {
                $blueprint->integer('daily_qty_limit')->nullable()->comment('الحد اليومي لإجمالي كميات المنتجات المبدلة');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $blueprint) {
            $blueprint->dropColumn('daily_qty_limit');
        });
    }
};
