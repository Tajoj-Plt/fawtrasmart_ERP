<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_closings', 'reported_returns')) {
                $table->decimal('reported_returns', 15, 2)->default(0)->after('reported_collections'); // المرتجعات المُبلَّغ عنها
            }
            if (! Schema::hasColumn('daily_closings', 'system_returns')) {
                $table->decimal('system_returns', 15, 2)->default(0)->after('system_collections');     // المرتجعات المحسوبة بالنظام
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['reported_returns', 'system_returns']);
        });
    }
};
