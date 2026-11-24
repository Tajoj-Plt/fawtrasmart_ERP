<?php

use App\Support\SchemaGuard;
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
        Schema::table('appointments', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('appointments', 'appointments_client_id_foreign')) {
                $table->foreign(['client_id'])->references(['id'])->on('clients')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('appointments', 'appointments_employee_id_foreign')) {
                $table->foreign(['employee_id'])->references(['id'])->on('employees')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('appointments_client_id_foreign');
            $table->dropForeign('appointments_employee_id_foreign');
        });
    }
};
