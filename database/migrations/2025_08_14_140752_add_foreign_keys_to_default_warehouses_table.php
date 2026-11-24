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
        Schema::table('default_warehouses', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('default_warehouses', 'default_warehouses_employee_id_foreign')) {
                $table->foreign(['employee_id'])->references(['id'])->on('employees')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('default_warehouses', 'default_warehouses_storehouse_id_foreign')) {
                $table->foreign(['storehouse_id'])->references(['id'])->on('store_houses')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('default_warehouses', function (Blueprint $table) {
            $table->dropForeign('default_warehouses_employee_id_foreign');
            $table->dropForeign('default_warehouses_storehouse_id_foreign');
        });
    }
};
