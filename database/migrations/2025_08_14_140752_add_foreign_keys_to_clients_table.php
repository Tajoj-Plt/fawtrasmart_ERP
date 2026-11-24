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
        Schema::table('clients', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('clients', 'clients_branch_id_foreign')) {
                $table->foreign(['branch_id'])->references(['id'])->on('branches')->onUpdate('restrict')->onDelete('set null');
            }
            if (! SchemaGuard::hasForeignKey('clients', 'clients_employee_id_foreign')) {
                $table->foreign(['employee_id'])->references(['id'])->on('employees')->onUpdate('restrict')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign('clients_branch_id_foreign');
            $table->dropForeign('clients_employee_id_foreign');
        });
    }
};
