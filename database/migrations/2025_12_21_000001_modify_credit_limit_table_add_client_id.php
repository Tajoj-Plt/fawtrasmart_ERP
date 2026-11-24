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
        // إضافة client_id إلى جدول credit_limit
        Schema::table('credit_limit', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_limit', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('id');
                if (! SchemaGuard::hasForeignKey('credit_limit', 'credit_limit_client_id_foreign')) {
                    $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                }
                if (! SchemaGuard::hasIndex('credit_limit', 'credit_limit_client_id_unique')) {
                    $table->unique(['client_id']);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_limit', function (Blueprint $table) {
            if (Schema::hasColumn('credit_limit', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropUnique(['client_id']);
                $table->dropColumn('client_id');
            }
        });
    }
};
