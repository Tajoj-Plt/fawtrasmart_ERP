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
        Schema::table('clients', function (Blueprint $table) {
            // تاريخ الميلاد
            if (! Schema::hasColumn('clients', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('last_name');
            }
            
            // الجنس (male, female)
            if (! Schema::hasColumn('clients', 'gender')) {
                $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            }
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender']);
        });
    }
};
