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
        Schema::table('cost_centers', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('cost_centers', 'cost_centers_parent_id_foreign')) {
                $table->foreign(['parent_id'])->references(['id'])->on('cost_centers')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropForeign('cost_centers_parent_id_foreign');
        });
    }
};
