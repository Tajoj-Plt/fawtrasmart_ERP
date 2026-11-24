<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column to job_roles table for role-based logic
        Schema::table('job_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('job_roles', 'automatic_itinerary_generation')) {
                $table->tinyInteger('automatic_itinerary_generation')->default(0)->after('role_type');
            }
        });

        // Add permission to spatie permissions table for middleware/gate checks
        Permission::firstOrCreate(['name' => 'automatic_itinerary_generation']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            if (Schema::hasColumn('job_roles', 'automatic_itinerary_generation')) {
                $table->dropColumn('automatic_itinerary_generation');
            }
        });

        $permission = Permission::where('name', 'automatic_itinerary_generation')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
