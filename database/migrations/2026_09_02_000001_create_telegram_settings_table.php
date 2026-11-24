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
        if (! Schema::hasTable('telegram_settings')) {
            Schema::create('telegram_settings', function (Blueprint $table) {
                $table->id();
                $table->string('bot_token')->nullable();
                $table->string('chat_id')->nullable();

                $table->boolean('daily_report_enabled')->default(true);

                $table->boolean('report_show_sales')->default(true);
                $table->boolean('report_show_collections')->default(true);
                $table->boolean('report_show_returns')->default(true);
                $table->boolean('report_show_expenses')->default(true);
                $table->boolean('report_show_stock')->default(true);

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_settings');
    }
};
