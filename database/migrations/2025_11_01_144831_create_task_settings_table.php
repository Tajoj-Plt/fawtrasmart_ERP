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
        if (! Schema::hasTable('task_settings')) {
            Schema::create('task_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('setting_key');
                $table->json('setting_value')->nullable();
                $table->string('setting_type')->default('json');
                $table->text('description')->nullable();
                $table->timestamps();
            
                $table->index(['project_id', 'setting_key']);
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_settings');
    }
};