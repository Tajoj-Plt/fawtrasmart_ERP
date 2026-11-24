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
            if (! Schema::hasColumn('client_relations', 'visit_type')) {
                $table->string('visit_type')->nullable()->after('description');
            }
        });

        Schema::table('appointment_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('appointment_notes', 'visit_type')) {
                $table->string('visit_type')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropColumn('visit_type');
        });

        Schema::table('appointment_notes', function (Blueprint $table) {
            $table->dropColumn('visit_type');
        });
    }
};
