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
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'previous_target_id')) {
                $table->unsignedBigInteger('previous_target_id')->nullable()->after('customer_target_id');
            }
            
            // Optional: add foreign key constraint if customer_targets table uses bigIncrements
            // $table->foreign('previous_target_id')->references('id')->on('customer_targets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('previous_target_id');
        });
    }
};
