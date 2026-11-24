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
            if (! Schema::hasColumn('client_relations', 'classification_id')) {
                $table->unsignedBigInteger('classification_id')->nullable()->after('client_id');
            }
            if (! SchemaGuard::hasForeignKey('client_relations', 'client_relations_classification_id_foreign')) {
                $table->foreign('classification_id')->references('id')->on('note_classifications')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropForeign(['classification_id']);
            $table->dropColumn('classification_id');
        });
    }
};
