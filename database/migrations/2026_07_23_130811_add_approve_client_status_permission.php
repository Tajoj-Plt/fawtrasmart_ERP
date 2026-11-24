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
        $guardName = 'web'; // Adjust if using a different guard

        // Use Spatie's Permission model directly or DB if model isn't available
        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => 'Approve_Client_Status',
                'guard_name' => $guardName,
            ]);
        } else {
            \Illuminate\Support\Facades\DB::table('permissions')->insertOrIgnore([
                'name' => 'Approve_Client_Status',
                'guard_name' => $guardName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            \Spatie\Permission\Models\Permission::where('name', 'Approve_Client_Status')->delete();
        } else {
            \Illuminate\Support\Facades\DB::table('permissions')->where('name', 'Approve_Client_Status')->delete();
        }
    }
};
