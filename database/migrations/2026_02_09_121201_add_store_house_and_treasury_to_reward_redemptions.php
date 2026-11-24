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
        Schema::table('reward_redemptions', function (Blueprint $table) {
            if (! Schema::hasColumn('reward_redemptions', 'store_house_id')) {
                $table->unsignedBigInteger('store_house_id')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('reward_redemptions', 'treasury_account_id')) {
                $table->unsignedBigInteger('treasury_account_id')->nullable()->after('store_house_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropColumn(['store_house_id', 'treasury_account_id']);
        });
    }
};
