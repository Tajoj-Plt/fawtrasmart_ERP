<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('shifts_pos')) {
            Schema::create('shifts_pos', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained('shifts_pos')->cascadeOnDelete();
                $table->string('attachment')->nullable();
                $table->text('description')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts_pos');
    }
};
