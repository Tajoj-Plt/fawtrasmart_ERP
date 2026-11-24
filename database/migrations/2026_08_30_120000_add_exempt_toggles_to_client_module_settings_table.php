<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_module_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('client_module_settings', 'exempt_location_enabled')) {
                $table->boolean('exempt_location_enabled')->default(true)->after('location_enabled');
            }
            if (! Schema::hasColumn('client_module_settings', 'exempt_exchange_enabled')) {
                $table->boolean('exempt_exchange_enabled')->default(true)->after('invoice_transfer_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_module_settings', function (Blueprint $table) {
            $table->dropColumn(['exempt_location_enabled', 'exempt_exchange_enabled']);
        });
    }
};
