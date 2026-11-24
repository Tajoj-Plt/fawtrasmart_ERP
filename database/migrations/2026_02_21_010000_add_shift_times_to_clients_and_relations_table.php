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
        Schema::table('client_relations', function (Blueprint $table) {
            if (!Schema::hasColumn('client_relations', 'shift_start')) {
                $table->time('shift_start')->nullable();
            }
            if (!Schema::hasColumn('client_relations', 'shift_end')) {
                $table->time('shift_end')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropColumn(['shift_start', 'shift_end']);
        });
    }
};
