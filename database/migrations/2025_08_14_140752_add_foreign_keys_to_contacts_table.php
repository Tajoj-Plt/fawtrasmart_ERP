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
        Schema::table('contacts', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('contacts', 'contacts_client_id_foreign')) {
                $table->foreign(['client_id'])->references(['id'])->on('clients')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('contacts', 'contacts_supplier_id_foreign')) {
                $table->foreign(['supplier_id'])->references(['id'])->on('suppliers')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign('contacts_client_id_foreign');
            $table->dropForeign('contacts_supplier_id_foreign');
        });
    }
};
