<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_dependencies')) {
            return;
        }

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('related_task_id');
            $table->enum('type', ['blocks', 'blocked_by', 'depends_on', 'related_to', 'duplicates']);
            $table->timestamps();

            $table->unique(['task_id', 'related_task_id', 'type']);
            $table->index('related_task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('related_task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
