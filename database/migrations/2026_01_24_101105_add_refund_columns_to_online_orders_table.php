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
        Schema::table('online_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('online_orders', 'refund_reason')) {
                $table->string('refund_reason', 500)->nullable()->after('cancel_notes');
            }
            if (!Schema::hasColumn('online_orders', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            if (Schema::hasColumn('online_orders', 'refund_reason')) {
                $table->dropColumn('refund_reason');
            }
            if (Schema::hasColumn('online_orders', 'refund_amount')) {
                $table->dropColumn('refund_amount');
            }
        });
    }
};
