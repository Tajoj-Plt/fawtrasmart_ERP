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
        Schema::table('job_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('job_roles', 'sales_view_payments_assigned_client')) {
                $table->tinyInteger('sales_view_payments_assigned_client')->default(0)->after('sales_add_payments_assigned_client');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn('sales_view_payments_assigned_client');
        });
    }
};
