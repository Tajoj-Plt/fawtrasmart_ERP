<?php

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
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('read');
            }
            if (! Schema::hasColumn('notifications', 'is_scheduled')) {
                $table->boolean('is_scheduled')->default(false)->after('scheduled_at');
            }
            if (! Schema::hasColumn('notifications', 'is_sent')) {
                $table->boolean('is_sent')->default(false)->after('is_scheduled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'is_scheduled', 'is_sent']);
        });
    }
};
