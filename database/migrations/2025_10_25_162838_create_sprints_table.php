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
        if (! Schema::hasTable('sprints')) {
            Schema::create('sprints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->nullable()->comment('المشروع المرتبط بالسبرنت');
                $table->string('name')->comment('اسم السبرنت');
                $table->text('goal')->nullable()->comment('هدف السبرنت');
                $table->date('start_date')->comment('تاريخ البداية');
                $table->date('end_date')->comment('تاريخ النهاية');
                $table->enum('status', ['planned', 'active', 'completed', 'closed'])->default('planned')->comment('حالة السبرنت');
                $table->timestamps();

                $table->index(['project_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
