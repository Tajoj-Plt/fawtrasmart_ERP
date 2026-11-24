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
        Schema::table('product_exchanges', function (Blueprint $table) {
            if (!Schema::hasColumn('product_exchanges', 'return_storehouse_id')) {
                $table->foreignId('return_storehouse_id')
                    ->nullable()
                    ->constrained('store_houses')
                    ->onDelete('restrict')
                    ->comment('المستودع الذي تُرجع له البضاعة التالفة');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_exchanges', function (Blueprint $table) {
            if (Schema::hasColumn('product_exchanges', 'return_storehouse_id')) {
                $table->dropForeignKeyIfExists(['return_storehouse_id']);
                $table->dropColumn('return_storehouse_id');
            }
        });
    }
};
