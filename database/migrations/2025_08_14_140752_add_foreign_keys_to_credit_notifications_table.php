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
        Schema::table('credit_notifications', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('credit_notifications', 'credit_notifications_client_id_foreign')) {
                $table->foreign(['client_id'])->references(['id'])->on('clients')->onUpdate('restrict')->onDelete('set null');
            }
            if (! SchemaGuard::hasForeignKey('credit_notifications', 'credit_notifications_created_by_foreign')) {
                $table->foreign(['created_by'])->references(['id'])->on('users')->onUpdate('restrict')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notifications', function (Blueprint $table) {
            $table->dropForeign('credit_notifications_client_id_foreign');
            $table->dropForeign('credit_notifications_created_by_foreign');
        });
    }
};
