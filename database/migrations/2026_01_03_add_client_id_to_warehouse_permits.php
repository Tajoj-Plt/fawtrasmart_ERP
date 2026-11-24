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
        Schema::table('warehouse_permits', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_permits', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('permission_source_id');
            }
            if (! SchemaGuard::hasForeignKey('warehouse_permits', 'warehouse_permits_client_id_foreign')) {
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_permits', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
