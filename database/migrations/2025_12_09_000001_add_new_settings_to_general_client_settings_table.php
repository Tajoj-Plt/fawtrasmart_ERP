<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('general_client_settings', 'location_required')) {
                $table->boolean('location_required')->default(1)->after('is_active');
            }
            if (! Schema::hasColumn('general_client_settings', 'group_required')) {
                $table->boolean('group_required')->default(1)->after('location_required');
            }
            if (! Schema::hasColumn('general_client_settings', 'employee_can_edit_visit_status')) {
                $table->boolean('employee_can_edit_visit_status')->default(0)->after('group_required');
            }
            if (! Schema::hasColumn('general_client_settings', 'employee_can_add_visit_notes')) {
                $table->boolean('employee_can_add_visit_notes')->default(1)->after('employee_can_edit_visit_status');
            }
            if (! Schema::hasColumn('general_client_settings', 'enable_route_tracking')) {
                $table->boolean('enable_route_tracking')->default(1)->after('employee_can_add_visit_notes');
            }
            if (! Schema::hasColumn('general_client_settings', 'route_settings')) {
                $table->text('route_settings')->nullable()->after('enable_route_tracking');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'location_required',
                'group_required',
                'employee_can_edit_visit_status',
                'employee_can_add_visit_notes',
                'enable_route_tracking',
                'route_settings'
            ]);
        });
    }
};
