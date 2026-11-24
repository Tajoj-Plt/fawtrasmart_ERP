<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sprint_daily_updates')) {
            return;
        }

        Schema::create('sprint_daily_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sprint_id');
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->text('yesterday')->nullable();
            $table->text('today')->nullable();
            $table->text('blockers')->nullable();
            $table->timestamps();

            $table->unique(['sprint_id', 'user_id', 'date']);
            $table->foreign('sprint_id')->references('id')->on('sprints')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_daily_updates');
    }
};
