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
            // إضافة صلاحية عرض العملاء حسب مجموعات الموظف
            if (! Schema::hasColumn('job_roles', 'clients_view_by_employee_groups')) {
                $table->boolean('clients_view_by_employee_groups')->default(false)->after('clients_view_own_clients');
            }
            // إضافة صلاحية عرض العملاء حسب خط السير اليومي
            if (! Schema::hasColumn('job_roles', 'clients_view_by_daily_itinerary')) {
                $table->boolean('clients_view_by_daily_itinerary')->default(false)->after('clients_view_by_employee_groups');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn('clients_view_by_employee_groups');
            $table->dropColumn('clients_view_by_daily_itinerary');
        });
    }
};
