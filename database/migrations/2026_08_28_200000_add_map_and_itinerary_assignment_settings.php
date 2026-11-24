<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('general_client_settings', 'map_hide_duration_hours')) {
                $table->unsignedInteger('map_hide_duration_hours')->nullable()->after('notify_employee_on_assignment_change');
            }
            if (! Schema::hasColumn('general_client_settings', 'itinerary_restrict_to_assigned_clients')) {
                $table->boolean('itinerary_restrict_to_assigned_clients')->default(false)->after('map_hide_duration_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            $table->dropColumn(['map_hide_duration_hours', 'itinerary_restrict_to_assigned_clients']);
        });
    }
};
