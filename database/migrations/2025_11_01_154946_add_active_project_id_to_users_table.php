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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'active_project_id')) {
                $table->unsignedBigInteger('active_project_id')->nullable()->after('client_id');
            }
            if (! SchemaGuard::hasForeignKey('users', 'users_active_project_id_foreign')) {
                $table->foreign('active_project_id')->references('id')->on('projects')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_project_id']);
            $table->dropColumn('active_project_id');
        });
    }
};