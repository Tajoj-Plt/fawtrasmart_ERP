<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'exempt_exchange_transaction')) {
            Schema::table('clients', function (Blueprint $table) {
                if (! Schema::hasColumn('clients', 'exempt_exchange_transaction')) {
                    $table->boolean('exempt_exchange_transaction')
                        ->default(0)
                        ->comment('0=يلزم فاتورة/سند/إشعار اليوم للتبديل | 1=تبديل مباشر في يوم الخط دون معاملة مالية');
                }
            });
        }

        DB::table('clients')->update(['exempt_exchange_transaction' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'exempt_exchange_transaction')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('exempt_exchange_transaction');
            });
        }
    }
};
