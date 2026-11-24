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
        if (! Schema::hasTable('system_currencies')) {
            Schema::create('system_currencies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->string('symbol', 10);
                $table->decimal('exchange_rate', 15, 6)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_currencies');
    }
};
