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
        if (! Schema::hasTable('note_classifications')) {
            Schema::create('note_classifications', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('frequency')->default('weekly'); // weekly, bi_weekly, monthly, daily, custom_days
                $table->integer('interval_days')->nullable(); // For custom_days
                $table->string('day_of_week')->nullable(); // Preferred day
                $table->boolean('is_automated')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_classifications');
    }
};
