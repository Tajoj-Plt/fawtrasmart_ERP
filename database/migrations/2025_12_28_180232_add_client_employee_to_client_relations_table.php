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
        Schema::table('client_relations', function (Blueprint $table) {
            if (! Schema::hasColumn('client_relations', 'client_employee_id')) {
                $table->unsignedBigInteger('client_employee_id')->nullable()->after('employee_id');
            }
            if (! SchemaGuard::hasForeignKey('client_relations', 'client_relations_client_employee_id_foreign')) {
                $table->foreign('client_employee_id')
                    ->references('id')
                    ->on('client_employee')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropForeign(['client_employee_id']);
            $table->dropColumn('client_employee_id');
        });
    }
};
