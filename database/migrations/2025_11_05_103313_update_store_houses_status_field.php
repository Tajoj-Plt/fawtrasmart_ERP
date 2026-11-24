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
        Schema::table('store_houses', function (Blueprint $table) {
            // Modify the status column to use proper enum values
            $table->enum('status', ['active', 'inactive', 'stopped'])->default('active')->comment('active = نشط, inactive = غير نشط, stopped = متوقف')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_houses', function (Blueprint $table) {
            // Revert to the previous enum definition (keeping the same structure)
            $table->enum('status', ['active', 'inactive', 'stopped'])->default('active')->comment('0 = active , 1 = inactive , 2 = stopped')->change();
        });
    }
};